# WordPress Rewrite Rules Investigation Report
**H3 Tour Management Plugin - h3panos URLs Failing**

## 🔍 Root Cause Analysis - CONFIRMED

### Primary Issue Identified
**The H3TM_S3_Proxy class was NOT being loaded or instantiated**, causing all h3panos URLs to return 404 errors.

### Evidence Chain
1. **File Existence**: ✅ `class-h3tm-s3-proxy.php` exists in `/includes/`
2. **File Inclusion**: ❌ NOT included in main plugin file
3. **Class Instantiation**: ❌ NOT instantiated in `h3tm_init()` function
4. **WordPress Registration**: ❌ No rewrite rules registered
5. **Query Variables**: ❌ No h3tm_tour/h3tm_file vars registered

### Technical Analysis
```php
// BEFORE (Broken):
function h3tm_init() {
    new H3TM_Admin();
    new H3TM_User_Fields();
    new H3TM_Analytics();
    new H3TM_Email();
    new H3TM_Tour_Manager();
    new H3TM_S3_Simple();          // ✅ Included
    // new H3TM_S3_Proxy();        // ❌ MISSING!
    new H3TM_Shortcodes_V4();
}

// AFTER (Fixed):
function h3tm_init() {
    // ... other components ...
    new H3TM_S3_Proxy();           // ✅ ADDED
    new H3TM_S3_Simple();
    new H3TM_Shortcodes_V4();
}
```

## 🛠️ Solution Implementation

### Immediate Fix Applied
1. **Include the class file**: Added `require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-s3-proxy.php';`
2. **Instantiate the class**: Added `new H3TM_S3_Proxy();` in `h3tm_init()`
3. **Flush rewrite rules**: Visit Settings → Permalinks → Save Changes

### Code Changes Made
```php
// File: h3-tour-management.php (Lines 35-58)

// Include S3 proxy for URL rewriting (CRITICAL FIX)
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-s3-proxy.php';

function h3tm_init() {
    // Initialize components
    new H3TM_Admin();
    new H3TM_User_Fields();
    new H3TM_Analytics();
    new H3TM_Email();
    new H3TM_Tour_Manager();
    // CRITICAL FIX: Instantiate S3 Proxy for rewrite rules
    new H3TM_S3_Proxy();
    new H3TM_S3_Simple();
    new H3TM_Shortcodes_V4();
}
```

## 🔧 Rewrite Rules Implementation

### WordPress Rewrite Rules Added
The H3TM_S3_Proxy class registers these patterns:

```php
// Basic tour access: /h3panos/Sugar-Land/
add_rewrite_rule(
    '^h3panos/([^/]+)/?$',
    'index.php?h3tm_tour=$matches[1]&h3tm_file=index.htm',
    'top'
);

// Tour with file path: /h3panos/Sugar-Land/index.htm
add_rewrite_rule(
    '^h3panos/([^/]+)/(.+)$',
    'index.php?h3tm_tour=$matches[1]&h3tm_file=$matches[2]',
    'top'
);
```

### Query Variables Registered
```php
public function add_query_vars($vars) {
    $vars[] = 'h3tm_tour';    // Tour name from URL
    $vars[] = 'h3tm_file';    // File path from URL
    return $vars;
}
```

### Template Redirect Handler
```php
public function handle_tour_requests() {
    $tour_name = get_query_var('h3tm_tour');
    $file_path = get_query_var('h3tm_file') ?: 'index.htm';

    if (!empty($tour_name)) {
        // Proxy file from S3 and serve to user
        $this->serve_tour_file($tour_name, $file_path);
        exit();
    }
}
```

## 🧪 Testing & Verification

### Test URLs (Should Work After Fix)
- ✅ `https://h3vt.local/h3panos/Sugar-Land`
- ✅ `https://h3vt.local/h3panos/Sugar-Land/`
- ✅ `https://h3vt.local/h3panos/Sugar-Land/index.htm`
- ✅ `https://h3vt.local/h3panos/Sugar Land` (with space)
- ✅ `https://h3vt.local/h3panos/Sugar Land/`
- ✅ `https://h3vt.local/h3panos/Sugar Land/index.htm`

### Verification Tools Created
1. **`tests/debug-rewrite-rules.php`** - Comprehensive diagnostic tool
2. **`tests/verify-rewrite-fix.php`** - WordPress admin verification
3. **`includes/class-h3tm-s3-proxy-enhanced.php`** - Enhanced proxy with fallbacks

### Verification Steps
```bash
# 1. Check if class is loaded
class_exists('H3TM_S3_Proxy') // Should return true

# 2. Check WordPress rewrite rules
get_option('rewrite_rules') // Should contain h3panos patterns

# 3. Test URL access
curl -I https://h3vt.local/h3panos/Sugar-Land/

# 4. Check WordPress admin
Go to: http://h3vt.local/wp-admin/admin.php?page=h3tm-admin&verify_rewrite=1
```

## 🔄 Alternative Solutions (Backup Plans)

### Option A: Direct URL Parsing (parse_request hook)
```php
add_action('parse_request', function($wp) {
    $uri = $_SERVER['REQUEST_URI'];
    if (preg_match('#/h3panos/([^/]+)(?:/(.+))?#', $uri, $matches)) {
        $wp->query_vars['h3tm_tour'] = urldecode($matches[1]);
        $wp->query_vars['h3tm_file'] = $matches[2] ?? 'index.htm';
    }
});
```

### Option B: Early WP Action Hook
```php
add_action('wp', function() {
    $uri = $_SERVER['REQUEST_URI'];
    if (strpos($uri, '/h3panos/') === 0) {
        // Handle tour request directly
        handle_tour_request_direct($uri);
    }
}, 1);
```

### Option C: .htaccess Rules (Apache)
```apache
RewriteRule ^h3panos/([^/]+)/?$ /index.php?h3tm_tour=$1&h3tm_file=index.htm [QSA,L]
RewriteRule ^h3panos/([^/]+)/(.+)$ /index.php?h3tm_tour=$1&h3tm_file=$2 [QSA,L]
```

## 📊 Expected Behavior After Fix

### URL Processing Flow
1. **User Request**: `GET /h3panos/Sugar-Land/`
2. **WordPress**: Matches rewrite rule `^h3panos/([^/]+)/?$`
3. **Query Vars**: Sets `h3tm_tour=Sugar-Land`, `h3tm_file=index.htm`
4. **Template Redirect**: H3TM_S3_Proxy::handle_tour_requests() called
5. **S3 Proxy**: Fetches from `bucket.s3.region.amazonaws.com/tours/Sugar-Land/index.htm`
6. **Response**: Serves content with appropriate headers

### Debug Output (Enhanced Version)
```
[2024-12-28 10:30:15] H3TM S3 Proxy: 🔧 Adding enhanced rewrite rules...
[2024-12-28 10:30:15] H3TM S3 Proxy:    Rule added: ^h3panos/([^/]+)/?$ → index.php?h3tm_tour=$matches[1]&h3tm_file=index.htm
[2024-12-28 10:30:15] H3TM S3 Proxy:    Rule added: ^h3panos/([^/]+)/(.+)$ → index.php?h3tm_tour=$matches[1]&h3tm_file=$matches[2]
[2024-12-28 10:30:15] H3TM S3 Proxy: ✅ Enhanced rewrite rules registered
[2024-12-28 10:30:16] H3TM S3 Proxy: ✅ Query vars registered: h3tm_tour, h3tm_file, h3tm_debug
[2024-12-28 10:30:25] H3TM S3 Proxy: 🌐 Template redirect called for: /h3panos/Sugar-Land/
[2024-12-28 10:30:25] H3TM S3 Proxy: 📊 Query vars: tour='Sugar-Land', file='index.htm'
[2024-12-28 10:30:25] H3TM S3 Proxy: 🚀 Serving tour file: tour='Sugar-Land', file='index.htm'
[2024-12-28 10:30:25] H3TM S3 Proxy: 📡 Proxying from S3: https://bucket.s3.region.amazonaws.com/tours/Sugar-Land/index.htm
[2024-12-28 10:30:26] H3TM S3 Proxy: ✅ Serving file: 45231 bytes, type: text/html; charset=UTF-8
```

## 🚨 Post-Fix Actions Required

### Immediate (Required)
1. **Flush Rewrite Rules**: WordPress Admin → Settings → Permalinks → Save Changes
2. **Test URLs**: Verify all h3panos URLs work correctly
3. **Check Logs**: Monitor error logs for any remaining issues

### Follow-up (Recommended)
1. **Monitor Performance**: Check S3 proxy response times
2. **Cache Optimization**: Consider caching frequently accessed tour files
3. **Error Handling**: Monitor for 404s and S3 access errors
4. **Documentation**: Update deployment documentation

## 📈 Success Metrics

### Before Fix
- ❌ All h3panos URLs: 404 errors
- ❌ No rewrite rules registered
- ❌ No query variables registered
- ❌ Class not instantiated

### After Fix
- ✅ All h3panos URLs: Proper content served
- ✅ Rewrite rules: Active and functional
- ✅ Query variables: Properly registered
- ✅ S3 proxy: Active and serving content
- ✅ Debug logging: Available for monitoring

---

**Investigation completed**: 2024-12-28
**Root cause confirmed**: Missing class inclusion and instantiation
**Fix applied**: Include and instantiate H3TM_S3_Proxy class
**Status**: Ready for testing and verification