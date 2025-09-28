# S3 Implementation Details - H3 Tour Management

## AWS S3 Configuration

### Bucket Structure
- **Bucket Name**: h3-tour-files-h3vt
- **Region**: us-east-1
- **Tour Path**: `tours/[tour-name]/`
- **File Structure**: Each tour folder contains all tour assets (HTML, images, JS, CSS, etc.)

### Authentication Method
- **Type**: AWS Signature Version 4
- **Credentials Storage**: 
  - Option 1: WordPress database options (`h3tm_aws_access_key`, `h3tm_aws_secret_key`)
  - Option 2: wp-config.php constants (not currently used)
- **Headers Required**:
  - `x-amz-date`: ISO 8601 datetime
  - `x-amz-content-sha256`: SHA256 hash of payload
  - `Authorization`: AWS4-HMAC-SHA256 signature

## Key Methods

### `list_s3_tours()` in class-h3tm-s3-simple.php
- **Purpose**: Retrieve all tours from S3 bucket
- **Features**:
  - Pagination support with continuation tokens
  - Handles buckets with >1000 objects
  - Converts S3 folder names to display names (dashes to spaces)
  - Proper AWS signature v4 calculation
- **Returns**: Array of tour names (strings)

### `handle_migrate_tour_to_s3()` in class-h3tm-admin.php  
- **Purpose**: AJAX handler for migrating local tours to S3
- **Process**:
  1. Checks if tour already exists in S3
  2. Handles both ZIP files and directories
  3. Extracts ZIPs to temp directory
  4. Uploads all files with proper content types
  5. Cleans up temporary files
- **Naming Convention**: Spaces converted to dashes (e.g., "Cedar Park" → "Cedar-Park")

### `upload_file()` in class-h3tm-s3-simple.php
- **Purpose**: Upload individual files to S3
- **Method**: Uses presigned URLs with PUT method
- **Content Type**: Auto-detected based on file extension

## Tour Naming Conventions

### S3 Storage Names
- Spaces replaced with dashes: "Bee Cave" → "Bee-Cave"
- Special case: "Onion Creek" stored as "Onion Creek" (no dash)
- Case preserved: "Arden-FairOaks", "Sugar-Land"

### Display Names
- Dashes converted back to spaces for UI display
- Original capitalization preserved

### URL Format
- Proxy URL: `https://h3vt.local/h3panos/[tour-name]/`
- Direct S3: `https://h3-tour-files-h3vt.s3.us-east-1.amazonaws.com/tours/[tour-name]/`

## Lambda Integration
- **Function**: h3-tour-processor
- **Runtime**: Node.js 18.x
- **Purpose**: Process uploads, inject analytics, handle tour structure
- **Trigger**: S3 PUT events on tours/ prefix

## Known Issues (Fixed in v2.1.0)
1. ✅ Signature calculation missing proper query parameter encoding
2. ✅ No pagination support (limited to 1000 files)
3. ✅ PHP fatal error accessing array elements on strings
4. ✅ Duplicate entries from database vs S3 listing
5. ✅ Inconsistent naming (spaces vs dashes)

## Database Storage
- **Option**: `h3tm_s3_tours` (legacy, now ignored)
- **Issue**: Accumulated duplicates and stale entries
- **Solution**: Use S3 listing as authoritative source

## Error Handling
- Comprehensive logging with `error_log()` prefixed with "H3TM S3:"
- Response validation for S3 API calls
- Fallback to empty arrays on errors
- User-friendly error messages in admin UI