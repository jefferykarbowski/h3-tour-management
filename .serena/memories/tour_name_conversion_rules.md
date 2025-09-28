# Tour Name Conversion Rules

## Important: Space to Dash Conversion

### The Issue
When tours are uploaded to S3 via Lambda, spaces in tour names are converted to dashes:
- **Local file**: "Bee Cave.zip"
- **S3 folder**: "Bee-Cave/"
- **Display name**: "Bee Cave"

### Conversion Rules

1. **Upload Process (Local → S3)**:
   - User uploads: "Bee Cave.zip"
   - Lambda processes and creates: "tours/Bee-Cave/" in S3
   - Spaces → Dashes

2. **Display Process (S3 → UI)**:
   - S3 folder: "Bee-Cave"
   - Display name: "Bee Cave"
   - Dashes → Spaces

3. **URL Generation**:
   - Tour name: "Bee Cave"
   - URL: `/h3panos/Bee%20Cave` (using rawurlencode)
   - The tour URL handler must then convert back to dashes for S3 access

### Code Implementations

1. **S3 Listing** (class-h3tm-s3-simple.php):
   ```php
   // Convert dashes back to spaces for display
   $tour_display_name = str_replace('-', ' ', $tour_folder);
   ```

2. **Local ZIP Detection**:
   ```php
   // Keep original name with spaces
   $tour_name = preg_replace('/\.zip$/i', '', $dir);
   ```

3. **Duplicate Detection**:
   - Case-insensitive comparison
   - Accounts for both "Bee Cave" and "Bee-Cave" as same tour

### Known Tours
- Local: "Bee Cave.zip"
- S3: "Bee-Cave/", "Onion-Creek/", "Sugar-Land/"
- Display: "Bee Cave", "Onion Creek", "Sugar Land"