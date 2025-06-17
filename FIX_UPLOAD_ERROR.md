# Fix for Upload Error at Chunk 938

## What's Happening

The error at chunk 938 (approximately 938MB into the upload) suggests one of these issues:
1. **Server timeout** - The server is timing out during the upload
2. **Memory limit** - PHP running out of memory
3. **Disk space** - Insufficient free space
4. **Connection timeout** - Network connection dropping

## Quick Fixes Applied

I've updated the plugin with:
- ✅ **Better error logging** - Check your PHP error log for specific details
- ✅ **Automatic retry** - Failed chunks will retry up to 3 times
- ✅ **Timeout extension** - Each chunk has a 60-second timeout
- ✅ **Progress monitoring** - Shows free disk space during upload
- ✅ **Memory limit increase** - Automatically sets to 512MB for uploads

## Additional Steps to Fix

### 1. Run Diagnostics
```bash
cd "C:\Users\Jeff\Local Sites\h3vt\app\public\wp-content\plugins\h3-tour-management\tools"
php fix-upload-issues.php
```

This will show:
- Current PHP limits
- Available disk space
- Temporary file cleanup

### 2. Check Error Logs

After the error occurs, check:
- **PHP Error Log** (Local Sites → your site → Logs → PHP)
- Look for entries starting with "H3TM Upload Error"

### 3. For Local Sites (Flywheel)

Add to your site's `conf/php/php.ini`:
```ini
max_execution_time = 0
max_input_time = 600
memory_limit = 1024M
post_max_size = 2048M
upload_max_filesize = 2048M
```

Then restart the site in Local Sites.

### 4. Alternative: Split Large Files

If the file is over 1GB, consider:
1. Splitting the ZIP file into smaller parts (500MB each)
2. Uploading them separately
3. Or use FTP to upload directly to the tours directory

### 5. Direct Upload Option

For very large files, you can bypass the web upload:
1. Upload via FTP/SFTP to: `/wp-content/uploads/h3-tours/`
2. Extract the ZIP manually
3. The tour will appear in the management interface

## What the Updates Do

1. **Chunk Padding**: Chunks are now numbered with padding (chunk_000938) to ensure proper ordering
2. **Error Details**: Specific error messages for each failure type
3. **Retry Logic**: Automatically retries failed chunks with 2-second delay
4. **Progress Logging**: Every 100 chunks, progress is logged
5. **Disk Space Check**: Warns if less than 100MB free space

## If Still Failing

The updated code will now show:
- Exact error message (disk space, timeout, etc.)
- Which chunk failed
- Free disk space remaining

Try uploading again and check the more detailed error message.

## Server Requirements for Large Files

For files over 1GB, ensure:
- At least 2x file size in free disk space
- PHP memory_limit at least 512M
- No server timeout limits (max_execution_time = 0)
- Stable internet connection

The chunk upload system can handle files of any size, but server configuration may need adjustment for very large files.