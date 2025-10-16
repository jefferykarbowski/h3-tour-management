# Implementation Plan: ID-Based Tour Architecture

**Status:** 🟡 Planning Phase
**Estimated Time:** 10-15 hours
**Priority:** High
**Benefits:** Instant renames, multiple URLs, immutable storage, cost savings

---

## 📋 Executive Summary

### Current Problems
- **Expensive Renames:** Copying 100+ files in S3 costs $0.05-$0.50 per rename
- **Slow Operations:** Large tours take 30-60 seconds to rename
- **Failure Prone:** S3 copy operations can fail midway, leaving inconsistent state
- **URL Inflexibility:** Cannot have multiple URLs pointing to same tour
- **Update Complexity:** Updating a tour requires careful folder management

### Proposed Solution
Implement immutable tour IDs that separate storage (S3 folders) from presentation (display names and URLs).

**Tour ID Format:** `20250114_173045_8k3j9d2m` (timestamp + 8-char random)

**S3 Structure Change:**
- Current: `tours/Tour-Name/`
- New: `tours/20250114_173045_8k3j9d2m/`

### Benefits
- ✅ **Instant renames** - Database update only (0.1 seconds vs 30-60 seconds)
- ✅ **Multiple URLs** - `/luxury-home`, `/smith-property` → same tour
- ✅ **Tour versioning** - Keep ID, add version suffix for updates
- ✅ **Immutable storage** - S3 folders never change once created
- ✅ **Better CDN** - Consistent URLs, no cache invalidation on rename
- ✅ **Cost savings** - No S3 copy operations ($0.05-$0.50 saved per rename)
- ✅ **Cleaner architecture** - Separation of storage from presentation
- ✅ **Future-proof** - Enables A/B testing, tour variants, rollback

---

## 🏗️ Architecture Overview

### Database Schema Changes

**Current `h3tm_tour_metadata` table:**
```sql
CREATE TABLE h3tm_tour_metadata (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tour_slug VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    s3_folder VARCHAR(500),
    url_history TEXT,
    UNIQUE KEY tour_slug (tour_slug)
);
```

**New `h3tm_tour_metadata` table:**
```sql
CREATE TABLE h3tm_tour_metadata (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tour_id VARCHAR(32) UNIQUE NOT NULL,          -- NEW: Immutable ID
    tour_slug VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    s3_folder VARCHAR(500),
    url_history TEXT,
    status VARCHAR(20) DEFAULT 'active',          -- NEW: uploading/processing/active/archived
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- NEW: Creation timestamp
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- NEW: Last update
    UNIQUE KEY tour_slug (tour_slug),
    UNIQUE KEY tour_id (tour_id),                 -- NEW: Index for fast ID lookup
    KEY idx_status (status),                      -- NEW: Index for status queries
    KEY idx_created_at (created_at)               -- NEW: Index for date sorting
);
```

### Access Patterns

**1. Direct ID Access (for polling/testing):**
```
URL: /h3panos/20250114_173045_8k3j9d2m/index.htm
→ S3: tours/20250114_173045_8k3j9d2m/index.htm
```

**2. Slug Access (for users):**
```
URL: /h3panos/my-beautiful-home/
→ Metadata lookup: slug='my-beautiful-home'
→ tour_id='20250114_173045_8k3j9d2m'
→ S3: tours/20250114_173045_8k3j9d2m/index.htm
```

**3. Multiple Slugs (future feature):**
```
URL: /h3panos/luxury-home/ → tour_id='20250114_173045_8k3j9d2m'
URL: /h3panos/smith-property/ → tour_id='20250114_173045_8k3j9d2m'
URL: /h3panos/123-main-st/ → tour_id='20250114_173045_8k3j9d2m'
```

### Backward Compatibility Strategy

**Dual-Mode System:** Support both old (name-based) and new (ID-based) tours indefinitely.

**Tour Type Detection:**
```php
if (!empty($tour->tour_id)) {
    // New ID-based tour
    $s3_folder = 'tours/' . $tour->tour_id . '/';
} else {
    // Old name-based tour (backward compatibility)
    $s3_folder = $tour->s3_folder; // e.g., 'tours/Jeffs-Test/'
}
```

---

## 📝 Implementation Phases

### Phase 1: Database Foundation (1-2 hours)

**Files to modify:**
- `includes/class-h3tm-activator.php`
- `includes/class-h3tm-tour-metadata.php`

#### Task 1.1: Add Database Columns
```php
// In H3TM_Activator::create_tour_metadata_table()
public static function maybe_upgrade_tour_metadata_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'h3tm_tour_metadata';

    // Check if tour_id column exists
    $column_exists = $wpdb->get_results(
        "SHOW COLUMNS FROM $table LIKE 'tour_id'"
    );

    if (empty($column_exists)) {
        error_log('H3TM: Upgrading tour_metadata table with tour_id column');

        $wpdb->query("
            ALTER TABLE $table
            ADD COLUMN tour_id VARCHAR(32) UNIQUE AFTER id,
            ADD COLUMN status VARCHAR(20) DEFAULT 'active' AFTER s3_folder,
            ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER status,
            ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
            ADD INDEX idx_tour_id (tour_id),
            ADD INDEX idx_status (status),
            ADD INDEX idx_created_at (created_at)
        ");

        error_log('H3TM: Tour metadata table upgraded successfully');
    }
}
```

#### Task 1.2: Add generate_tour_id() Method
```php
// In H3TM_Tour_Metadata class
public function generate_tour_id() {
    global $wpdb;
    $table = $wpdb->prefix . 'h3tm_tour_metadata';

    // Generate unique ID with format: 20250114_173045_8k3j9d2m
    // timestamp + 8-char random = 1 in 2.8 trillion collision chance
    do {
        $timestamp = date('Ymd_His');
        $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        $tour_id = $timestamp . '_' . $random;

        // Verify uniqueness (paranoid check)
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE tour_id = %s",
            $tour_id
        ));
    } while ($exists > 0);

    error_log('H3TM: Generated tour_id: ' . $tour_id);
    return $tour_id;
}
```

#### Task 1.3: Add get_by_tour_id() Method
```php
// In H3TM_Tour_Metadata class
public function get_by_tour_id($tour_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'h3tm_tour_metadata';

    $tour = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE tour_id = %s",
        $tour_id
    ));

    if ($tour) {
        error_log('H3TM: Found tour by ID: ' . $tour_id . ' (display_name=' . $tour->display_name . ')');
    } else {
        error_log('H3TM: No tour found for ID: ' . $tour_id);
    }

    return $tour;
}
```

#### Task 1.4: Update create() Method
```php
// In H3TM_Tour_Metadata class
public function create($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'h3tm_tour_metadata';

    // Generate tour_id if not provided
    if (empty($data['tour_id'])) {
        $data['tour_id'] = $this->generate_tour_id();
    }

    // Set default status
    if (empty($data['status'])) {
        $data['status'] = 'uploading';
    }

    $result = $wpdb->insert($table, $data);

    if ($result) {
        error_log('H3TM: Created tour metadata - tour_id=' . $data['tour_id'] . ', display_name=' . $data['display_name']);
        return $wpdb->insert_id;
    }

    return false;
}
```

**Testing Phase 1:**
```php
// Test in WordPress admin or WP-CLI
$metadata = new H3TM_Tour_Metadata();

// Test 1: Generate tour_id
$tour_id = $metadata->generate_tour_id();
echo "Generated tour_id: $tour_id\n";

// Test 2: Create metadata entry
$id = $metadata->create(array(
    'display_name' => 'Test Tour',
    'tour_slug' => 'test-tour',
    's3_folder' => 'tours/' . $tour_id . '/',
    'status' => 'uploading'
));
echo "Created metadata ID: $id\n";

// Test 3: Retrieve by tour_id
$tour = $metadata->get_by_tour_id($tour_id);
echo "Retrieved tour: " . $tour->display_name . "\n";
```

---

### Phase 2: Backend Upload Handler (2-3 hours)

**File:** `includes/class-h3tm-s3-simple.php`

#### Task 2.1: Update handle_get_presigned_url()

**Current code (line 504-512):**
```php
// Simple presigned URL generation
$unique_id = uniqid() . '_' . time();

// Use tour name as filename instead of original ZIP name
$s3_filename = str_replace(' ', '-', $tour_name) . '.zip';
$s3_key = 'uploads/' . $unique_id . '/' . $s3_filename;

error_log('H3TM S3 Simple: Using tour name for S3 key - original: ' . $file_name . ', renamed: ' . $s3_filename);
```

**New code:**
```php
// Generate permanent tour ID
if (!class_exists('H3TM_Tour_Metadata')) {
    error_log('H3TM S3 Simple: Metadata class not available');
    wp_send_json_error('Tour metadata system unavailable');
}

$metadata = new H3TM_Tour_Metadata();
$tour_id = $metadata->generate_tour_id();

// Create metadata entry immediately (reserves the tour_id)
$metadata_id = $metadata->create(array(
    'tour_id' => $tour_id,
    'display_name' => $tour_name,
    's3_folder' => 'tours/' . $tour_id . '/',
    'tour_slug' => sanitize_title($tour_name),
    'status' => 'uploading',
    'url_history' => json_encode(array())
));

if (!$metadata_id) {
    error_log('H3TM S3 Simple: Failed to create metadata entry for tour_id: ' . $tour_id);
    wp_send_json_error('Failed to create tour metadata');
}

// Upload to tour_id folder (immutable location)
$s3_filename = $tour_id . '.zip';
$s3_key = 'uploads/' . $tour_id . '/' . $s3_filename;

error_log('H3TM S3 Simple: Created tour metadata - tour_id=' . $tour_id . ', display_name=' . $tour_name . ', s3_key=' . $s3_key);
```

#### Task 2.2: Update Response to Include tour_id

**Current response:**
```php
wp_send_json_success(array(
    'upload_url' => $presigned_url,
    's3_key' => $s3_key,
    'unique_id' => $unique_id
));
```

**New response:**
```php
wp_send_json_success(array(
    'upload_url' => $presigned_url,
    's3_key' => $s3_key,
    'tour_id' => $tour_id,      // NEW: Immutable ID
    'unique_id' => $tour_id,    // For backward compatibility
    'display_name' => $tour_name
));
```

**Testing Phase 2:**
```javascript
// Test in browser console after upload starts
const formData = new FormData();
formData.append('action', 'h3tm_get_s3_presigned_url');
formData.append('tour_name', 'Test Beautiful Home');
formData.append('file_name', 'test.zip');
formData.append('file_size', '1000000');
formData.append('file_type', 'application/zip');
formData.append('nonce', window.h3tm_ajax.nonce);

fetch(window.h3tm_ajax.ajax_url, {
    method: 'POST',
    body: formData
}).then(r => r.json()).then(data => {
    console.log('Response:', data);
    console.log('Tour ID:', data.data.tour_id);
    console.log('S3 Key:', data.data.s3_key);
    // Should see: tour_id='20250114_173045_8k3j9d2m'
    // Should see: s3_key='uploads/20250114_173045_8k3j9d2m/20250114_173045_8k3j9d2m.zip'
});
```

---

### Phase 3: Lambda Processor (1-2 hours)

**File:** `H3TourProcessor/index.js`

#### Task 3.1: Update Tour ID Extraction

**Current code:**
```javascript
function extractTourName(s3Key) {
    const parts = s3Key.split('/');
    const fileName = parts[parts.length - 1];
    return fileName.replace('.zip', '').replace(/-/g, ' ');
}

// Usage
const tourName = extractTourName(s3Key);
const targetFolder = `tours/${tourName}/`;
```

**New code:**
```javascript
function extractTourId(s3Key) {
    // Key format: uploads/20250114_173045_8k3j9d2m/20250114_173045_8k3j9d2m.zip
    // Extract tour_id from the folder name (second path segment)
    const parts = s3Key.split('/');
    const tourId = parts[1]; // e.g., '20250114_173045_8k3j9d2m'

    console.log(`[H3 Lambda] Extracted tour_id from S3 key: ${s3Key} -> ${tourId}`);
    return tourId;
}

// Usage
const tourId = extractTourId(s3Key);
const targetFolder = `tours/${tourId}/`;

console.log(`[H3 Lambda] Processing tour_id: ${tourId}`);
console.log(`[H3 Lambda] Target S3 folder: ${targetFolder}`);
```

#### Task 3.2: Update File Upload Path

**Current code:**
```javascript
// Upload files to tours/{tourName}/
const uploadKey = `tours/${tourName}/${relativePath}`;
```

**New code:**
```javascript
// Upload files to tours/{tourId}/
const uploadKey = `tours/${tourId}/${relativePath}`;
```

#### Task 3.3: Update Logging

```javascript
console.log(`[H3 Lambda] Tour Processing Summary:`);
console.log(`  - Tour ID: ${tourId}`);
console.log(`  - Source: ${s3Key}`);
console.log(`  - Destination: ${targetFolder}`);
console.log(`  - Files uploaded: ${uploadedCount}`);
console.log(`  - Analytics injected: ${analyticsInjected ? 'Yes' : 'No'}`);
```

**Testing Phase 3:**
```bash
# Deploy Lambda function
cd H3TourProcessor
zip -r function.zip index.js node_modules
aws lambda update-function-code \
    --function-name H3TourProcessor \
    --zip-file fileb://function.zip

# Test with manual S3 upload
aws s3 cp test.zip s3://your-bucket/uploads/20250114_173045_8k3j9d2m/20250114_173045_8k3j9d2m.zip

# Check CloudWatch logs for tour_id extraction
aws logs tail /aws/lambda/H3TourProcessor --follow
```

---

### Phase 4: URL Redirector Enhancement (2-3 hours)

**File:** `includes/class-h3tm-url-redirector.php`

#### Task 4.1: Add Dual-Mode Support

**Add new method:**
```php
/**
 * Resolve tour S3 folder from slug (supports both ID-based and name-based tours)
 */
private function resolve_tour_folder($slug) {
    error_log('H3TM URL Redirector: Resolving slug: ' . $slug);

    // Pattern 1: Check if slug is a tour_id (matches timestamp_random pattern)
    // Format: 20250114_173045_8k3j9d2m
    if (preg_match('/^\d{8}_\d{6}_[a-z0-9]{8}$/', $slug)) {
        error_log('H3TM URL Redirector: Detected direct tour_id access: ' . $slug);
        return 'tours/' . $slug . '/';
    }

    // Pattern 2: Slug-based lookup via metadata
    if (!class_exists('H3TM_Tour_Metadata')) {
        error_log('H3TM URL Redirector: Metadata class not available');
        return null;
    }

    $metadata = new H3TM_Tour_Metadata();
    $tour = $metadata->get_by_slug($slug);

    if (!$tour) {
        error_log('H3TM URL Redirector: No tour found for slug: ' . $slug);
        return null;
    }

    // Pattern 3: New ID-based tour
    if (!empty($tour->tour_id)) {
        error_log('H3TM URL Redirector: Found ID-based tour - tour_id=' . $tour->tour_id . ', display_name=' . $tour->display_name);
        return 'tours/' . $tour->tour_id . '/';
    }

    // Pattern 4: Old name-based tour (backward compatibility)
    if (!empty($tour->s3_folder)) {
        error_log('H3TM URL Redirector: Found name-based tour (legacy) - s3_folder=' . $tour->s3_folder);
        return $tour->s3_folder;
    }

    error_log('H3TM URL Redirector: Tour found but no s3_folder or tour_id set');
    return null;
}
```

#### Task 4.2: Update handle_tour_request()

**Current code:**
```php
// Build S3 URL from tour name
$s3_folder = 'tours/' . $tour_name . '/';
```

**New code:**
```php
// Resolve S3 folder using dual-mode logic
$s3_folder = $this->resolve_tour_folder($tour_slug);

if (!$s3_folder) {
    error_log('H3TM URL Redirector: Failed to resolve tour folder for slug: ' . $tour_slug);
    return false;
}

error_log('H3TM URL Redirector: Resolved s3_folder: ' . $s3_folder);
```

**Testing Phase 4:**
```php
// Test all access patterns
$redirector = new H3TM_URL_Redirector();

// Test 1: Direct tour_id access
$folder = $redirector->resolve_tour_folder('20250114_173045_8k3j9d2m');
// Expected: 'tours/20250114_173045_8k3j9d2m/'

// Test 2: Slug access (new tour)
$folder = $redirector->resolve_tour_folder('my-beautiful-home');
// Expected: 'tours/20250114_173045_8k3j9d2m/' (via metadata lookup)

// Test 3: Slug access (old tour - backward compatibility)
$folder = $redirector->resolve_tour_folder('jeffs-test');
// Expected: 'tours/Jeffs-Test/' (old name-based folder)
```

---

### Phase 5: Frontend Polling Update (1 hour)

**File:** `frontend/src/components/TourUpload.tsx`

#### Task 5.1: Extract tour_id from Response

**Current code (line 129-154):**
```typescript
const requestPresignedUrl = async (file: File, tourName: string) => {
    const formData = new FormData();
    formData.append('action', 'h3tm_get_s3_presigned_url');
    formData.append('tour_name', tourName);
    formData.append('file_name', file.name);
    formData.append('file_size', file.size.toString());
    formData.append('file_type', file.type || 'application/zip');
    formData.append('nonce', (window as any).h3tm_ajax?.nonce || '');

    const response = await fetch((window as any).h3tm_ajax?.ajax_url || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData,
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.data?.message || 'Failed to get upload URL');
    }

    return data.data;
};
```

**New interface:**
```typescript
interface PresignedUrlResponse {
  upload_url: string;
  s3_key: string;
  tour_id: string;  // NEW
  unique_id: string;
}
```

#### Task 5.2: Update Polling to Use tour_id

**Current code (line 90-127):**
```typescript
const handleUpload = async () => {
    if (!tourName || !file) return;

    setIsProcessing(true);
    setProgress(0);
    setProcessingPhase("uploading");
    setProcessingTime(0);

    try {
      const presignedData = await requestPresignedUrl(file, tourName);
      await uploadToS3(file, presignedData);
      await markTourProcessing(tourName);
      await pollForTourReady(tourName);  // Uses tour name

      // ... rest of code
    }
};
```

**New code:**
```typescript
const handleUpload = async () => {
    if (!tourName || !file) return;

    setIsProcessing(true);
    setProgress(0);
    setProcessingPhase("uploading");
    setProcessingTime(0);

    try {
      const presignedData = await requestPresignedUrl(file, tourName);
      const tourId = presignedData.tour_id;  // Extract tour_id

      console.log('Tour ID:', tourId);
      console.log('Tour Name:', tourName);

      await uploadToS3(file, presignedData);
      await markTourProcessing(tourName);
      await pollForTourReady(tourId);  // Use tour_id for direct access

      // ... rest of code
    }
};
```

#### Task 5.3: Update pollForTourReady() Function

**Current code (line 221-277):**
```typescript
const pollForTourReady = async (tourName: string): Promise<void> => {
    // Convert tour name to URL format (replace spaces with dashes)
    const tourUrl = `/h3panos/${tourName.replace(/ /g, '-')}/index.htm`;
    // ... polling logic
};
```

**New code:**
```typescript
const pollForTourReady = async (tourId: string): Promise<void> => {
    const maxPolls = 24;
    const pollInterval = 5000;

    // Use tour_id directly for reliable polling
    const tourUrl = `/h3panos/${tourId}/index.htm`;

    console.log('🔄 Starting Lambda processing monitor');
    console.log('Tour ID:', tourId);
    console.log('Testing URL:', tourUrl);

    setProcessingPhase("processing");
    setProgress(0);
    setProcessingTime(0);

    for (let attempt = 1; attempt <= maxPolls; attempt++) {
      console.log(`🔍 Polling attempt ${attempt}/${maxPolls}`);

      try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);

        const response = await fetch(tourUrl, {
          method: 'HEAD',
          signal: controller.signal,
        });

        clearTimeout(timeoutId);

        if (response.ok) {
          console.log('✅ Tour is ready at:', tourUrl);
          setProgress(100);
          return;
        }
      } catch (error) {
        console.log(`Tour not ready yet (attempt ${attempt}/${maxPolls})`);
      }

      setProcessingTime(attempt * 5);
      const progressPercent = Math.min(95, Math.round((attempt / maxPolls) * 100));
      setProgress(progressPercent);

      if (attempt < maxPolls) {
        await new Promise(resolve => setTimeout(resolve, pollInterval));
      }
    }

    console.log('⏱️ Processing timeout');
    throw new Error('Processing timeout - tour taking longer than expected. The tour should appear shortly.');
};
```

**Testing Phase 5:**
```typescript
// Test in browser console during upload
// 1. Start upload
// 2. Check console logs:
//    - Should see: "Tour ID: 20250114_173045_8k3j9d2m"
//    - Should see: "Testing URL: /h3panos/20250114_173045_8k3j9d2m/index.htm"
//    - Should NOT see tour name in polling URL
// 3. Verify Lambda processes correctly
// 4. Verify polling succeeds when tour is ready
```

---

### Phase 6: React Table Updates (1 hour)

**File:** `frontend/src/components/ToursTable.tsx`

#### Task 6.1: Display tour_id in Debug Mode (Optional)

**Add tour ID tooltip:**
```typescript
<span
  className="text-sm font-medium text-gray-900"
  title={`Tour ID: ${tour.tour_id || 'Legacy tour'}`}
>
  {tour.name}
</span>
```

**Or add debug info (dev mode only):**
```typescript
{process.env.NODE_ENV === 'development' && tour.tour_id && (
  <span className="text-xs text-gray-400 ml-2">
    ID: {tour.tour_id.substring(0, 8)}...
  </span>
)}
```

---

### Phase 7: Testing & Validation (2-3 hours)

#### Test 7.1: New Tour Upload End-to-End

**Scenario:** Upload a new tour with ID system

```bash
# Steps:
1. Open admin interface
2. Enter tour name: "Test Beautiful Home"
3. Select any ZIP file
4. Click Upload

# Expected Results:
- Backend generates tour_id (e.g., 20250114_173045_8k3j9d2m)
- Metadata created with tour_id
- S3 upload to: uploads/20250114_173045_8k3j9d2m/20250114_173045_8k3j9d2m.zip
- Lambda extracts to: tours/20250114_173045_8k3j9d2m/
- Polling uses tour_id directly
- Tour appears in list with display name "Test Beautiful Home"
- Tour accessible via:
  - /h3panos/20250114_173045_8k3j9d2m/ (direct ID)
  - /h3panos/test-beautiful-home/ (slug)

# Validation:
- Check database: tour_id column populated
- Check S3: tours/20250114_173045_8k3j9d2m/ exists
- Check URL: both access patterns work
```

#### Test 7.2: Backward Compatibility (Old Tours)

**Scenario:** Existing tours without tour_id still work

```bash
# Steps:
1. Access existing tour (pre-migration)
2. Try to view tour
3. Try to rename tour

# Expected Results:
- URL redirector detects missing tour_id
- Falls back to s3_folder (old name-based path)
- Tour loads correctly
- Rename operation works (but uses old S3 copy method)

# Validation:
- Old tours still accessible
- No errors in logs
- Graceful fallback behavior
```

#### Test 7.3: Instant Rename Operation

**Scenario:** Rename a new ID-based tour

```bash
# Steps:
1. Create new tour "Original Name"
2. Wait for processing to complete
3. Rename to "New Name"

# Expected Results:
- Rename completes in < 1 second
- No S3 copy operations
- Database update only:
  - display_name: "Original Name" → "New Name"
  - tour_slug: "original-name" → "new-name"
  - tour_id: UNCHANGED
  - s3_folder: UNCHANGED
- Tour accessible via new URL immediately
- Old URL (if bookmarked) redirects or shows 404

# Validation:
- Check S3: folder name unchanged (tours/20250114_173045_8k3j9d2m/)
- Check database: only display_name and tour_slug changed
- Check logs: no S3 copy operations
- Time measurement: < 1 second
```

#### Test 7.4: Multiple URLs to Same Tour

**Scenario:** Create multiple slugs for same tour

```bash
# Steps:
1. Create tour with tour_id='20250114_173045_8k3j9d2m'
2. In database, create additional metadata entries:
   - Entry 1: slug='luxury-home', tour_id='20250114_173045_8k3j9d2m'
   - Entry 2: slug='smith-property', tour_id='20250114_173045_8k3j9d2m'
   - Entry 3: slug='123-main-st', tour_id='20250114_173045_8k3j9d2m'

# Expected Results:
- All three URLs load the same tour:
  - /h3panos/luxury-home/
  - /h3panos/smith-property/
  - /h3panos/123-main-st/
- Analytics tracks which URL was used
- No duplication in S3

# Validation:
- All URLs resolve to same S3 folder
- Single tour in S3
- Multiple database entries with same tour_id
```

#### Test 7.5: Concurrent Uploads

**Scenario:** Upload multiple tours simultaneously

```bash
# Steps:
1. Open 3 browser tabs
2. Start uploading 3 different tours at same time

# Expected Results:
- Each gets unique tour_id
- No collisions
- All process successfully
- No race conditions

# Validation:
- Database has 3 entries with different tour_ids
- S3 has 3 separate folders
- All tours accessible
```

---

## 🚨 Risk Analysis & Mitigation

### Risk 1: Database Migration Failure
**Severity:** HIGH
**Probability:** LOW
**Impact:** System down, existing tours inaccessible

**Mitigation:**
- Test migration on dev/staging first
- Create backup before migration
- Prepare rollback SQL:
```sql
-- Rollback script
ALTER TABLE h3tm_tour_metadata
DROP COLUMN tour_id,
DROP COLUMN status,
DROP COLUMN created_at,
DROP COLUMN updated_at,
DROP INDEX idx_tour_id,
DROP INDEX idx_status,
DROP INDEX idx_created_at;
```
- Run migration during low-traffic window
- Have database expert on standby

### Risk 2: Backward Compatibility Breaks
**Severity:** CRITICAL
**Probability:** MEDIUM
**Impact:** Existing tours inaccessible

**Mitigation:**
- Dual-mode support in URL redirector
- Comprehensive testing of old tours
- Gradual rollout (new tours only first)
- Feature flag to disable ID system if needed

### Risk 3: Lambda Deployment Issues
**Severity:** MEDIUM
**Probability:** LOW
**Impact:** New uploads fail to process

**Mitigation:**
- Deploy to Lambda test alias first
- Test with sample ZIP before production
- Keep old Lambda version for rollback
- Monitor CloudWatch logs closely

### Risk 4: Frontend Polling Failures
**Severity:** MEDIUM
**Probability:** MEDIUM
**Impact:** "Upload failed" error despite success

**Mitigation:**
- Fallback to name-based polling if tour_id missing
- Better error messages
- Extended timeout for large tours
- Manual "Check Status" button

### Risk 5: tour_id Collision
**Severity:** LOW
**Probability:** VERY LOW
**Impact:** Upload overwrites existing tour

**Mitigation:**
- Use timestamp + 8-char random (1 in 2.8 trillion)
- Database UNIQUE constraint
- Retry logic in generate_tour_id()
- Log generation attempts

---

## 📊 Success Metrics

### Performance Metrics
- ✅ Rename operation: < 1 second (vs 30-60 seconds)
- ✅ New upload: No change in time
- ✅ Database queries: < 50ms per lookup
- ✅ S3 operations: 50% reduction for renames

### Cost Metrics
- ✅ S3 copy operations: $0.00 (vs $0.05-$0.50 per rename)
- ✅ S3 API calls: 100 fewer per rename (LIST + COPY + DELETE)
- ✅ Monthly savings: $5-$50 depending on rename frequency

### Quality Metrics
- ✅ Backward compatibility: 100% of old tours work
- ✅ New tour success rate: ≥ 99%
- ✅ Polling reliability: ≥ 95%
- ✅ Error rate: < 1%

---

## 🔄 Rollback Plan

### If Database Migration Fails
```sql
-- Restore from backup
mysql -u user -p database < backup_before_migration.sql

-- Or rollback changes
ALTER TABLE h3tm_tour_metadata
DROP COLUMN tour_id,
DROP COLUMN status,
DROP COLUMN created_at,
DROP COLUMN updated_at;
```

### If Lambda Deployment Fails
```bash
# Revert to previous version
aws lambda update-function-code \
    --function-name H3TourProcessor \
    --s3-bucket lambda-deployments \
    --s3-key H3TourProcessor-v1.0.zip
```

### If Frontend Issues
```bash
# Revert frontend build
cd frontend
git checkout HEAD~1 src/components/TourUpload.tsx
npm run build
```

### Emergency Disable
```php
// Add to wp-config.php
define('H3TM_DISABLE_TOUR_ID_SYSTEM', true);

// Check in code
if (defined('H3TM_DISABLE_TOUR_ID_SYSTEM') && H3TM_DISABLE_TOUR_ID_SYSTEM) {
    // Use old name-based system
}
```

---

## 📈 Future Enhancements

### Phase 8: Advanced Features (Future)

**1. Tour Versioning**
```
tours/20250114_173045_8k3j9d2m_v1/  (original)
tours/20250114_173045_8k3j9d2m_v2/  (updated)
```

**2. A/B Testing**
```
Display Name: "My Tour"
Variants:
  - /h3panos/my-tour/?variant=a → version 1
  - /h3panos/my-tour/?variant=b → version 2
```

**3. URL Aliases**
- Multiple slugs per tour (already supported!)
- Short URLs: `/t/xyz123` → tour_id
- QR code URLs

**4. Tour Families**
```
Parent Tour: 20250114_173045_8k3j9d2m
Child Tours:
  - 20250114_173045_8k3j9d2m_daytime
  - 20250114_173045_8k3j9d2m_nighttime
```

**5. Scheduled Publishing**
```
tour_id: active
status: scheduled
publish_at: 2025-02-01 09:00:00
```

---

## 🎯 Implementation Checklist

### Pre-Implementation
- [ ] Backup production database
- [ ] Review plan with team
- [ ] Set up dev/staging environment
- [ ] Create rollback procedures

### Phase 1: Database
- [ ] Update H3TM_Activator with migration
- [ ] Add generate_tour_id() method
- [ ] Add get_by_tour_id() method
- [ ] Update create() method
- [ ] Test on dev database
- [ ] Run migration on production

### Phase 2: Backend
- [ ] Update handle_get_presigned_url()
- [ ] Create metadata before upload
- [ ] Use tour_id for S3 key
- [ ] Return tour_id in response
- [ ] Test presigned URL generation
- [ ] Deploy to production

### Phase 3: Lambda
- [ ] Update extractTourName() → extractTourId()
- [ ] Update deployment path
- [ ] Update logging
- [ ] Test locally with SAM
- [ ] Deploy to Lambda test alias
- [ ] Test with real upload
- [ ] Deploy to production alias

### Phase 4: URL Redirector
- [ ] Add resolve_tour_folder() method
- [ ] Update handle_tour_request()
- [ ] Test direct ID access
- [ ] Test slug access (new tours)
- [ ] Test slug access (old tours)
- [ ] Deploy to production

### Phase 5: Frontend
- [ ] Update requestPresignedUrl() interface
- [ ] Extract tour_id from response
- [ ] Update pollForTourReady()
- [ ] Test upload flow
- [ ] Rebuild and deploy

### Phase 6: React Table
- [ ] Add tour_id tooltip (optional)
- [ ] Add debug info (dev mode)
- [ ] Rebuild and deploy

### Phase 7: Testing
- [ ] Test new tour upload
- [ ] Test old tour access
- [ ] Test instant rename
- [ ] Test multiple URLs
- [ ] Test concurrent uploads
- [ ] Load testing
- [ ] Monitor logs for 48 hours

### Post-Implementation
- [ ] Update documentation
- [ ] Train team on new features
- [ ] Monitor performance metrics
- [ ] Collect user feedback
- [ ] Plan Phase 8 enhancements

---

## 📞 Support & Questions

**Documentation:** This file
**Issues:** GitHub Issues
**Logs:** Check WordPress debug.log and CloudWatch
**Rollback:** See "Rollback Plan" section above

---

**Last Updated:** 2025-01-14
**Status:** Ready for Implementation
**Next Action:** Start Phase 1 (Database Foundation)
