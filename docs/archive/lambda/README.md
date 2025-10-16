# Lambda Documentation Archive

**Archived**: 2025-10-16
**Reason**: Consolidated into comprehensive deployment guide

## Superseded By

**Main Guide**: [`docs/deployment/LAMBDA_SETUP.md`](../../deployment/LAMBDA_SETUP.md)

This comprehensive guide consolidates all Lambda deployment documentation into a single authoritative source with complete setup instructions, troubleshooting, and known issues.

## Archived Files (3 total)

### 1. lambda-deployment-instructions.md (231 lines)
- **Content**: Deployment steps, environment variables, IAM permissions
- **Unique Info**: Basic Lambda setup procedures
- **Status**: All content expanded and preserved in consolidated guide

### 2. lambda-deployment-fix.md (162 lines)
- **Content**: ACL parameter issue and resolution
- **Unique Info**: Before/after timeline of Lambda crashes
- **Status**: Critical issue documented in "Known Issues" section

### 3. lambda-deployment-fix-notes.md (162 lines)
- **Content**: Duplicate of lambda-deployment-fix.md from notes/ subdirectory
- **Unique Info**: Same content as #2 above
- **Status**: Content deduplicated in consolidated guide

## Critical Issue Documented

**ACL Parameter Issue (RESOLVED)**:
```javascript
// ❌ WRONG - Causes AccessControlListNotSupported error
await s3.send(new PutObjectCommand({
    Bucket: bucket,
    Key: s3Key,
    Body: fileData,
    ContentType: getContentType(file.path),
    ACL: 'public-read'  // DO NOT USE
}));

// ✅ CORRECT - Bucket policy handles public access
await s3.send(new PutObjectCommand({
    Bucket: bucket,
    Key: s3Key,
    Body: fileData,
    ContentType: getContentType(file.path)
    // Bucket policy handles public access
}));
```

This critical fix is prominently documented in the consolidated guide's "Known Issues" section.

## Using This Archive

These files are preserved for historical reference. For current Lambda deployment documentation, always refer to:

**[`docs/deployment/LAMBDA_SETUP.md`](../../deployment/LAMBDA_SETUP.md)**

## Restoration

If you need to restore any of these files:

```bash
# Copy back to docs/
cp docs/archive/lambda/[filename].md docs/

# Add to version control if needed
git add docs/[filename].md
```

**Note**: Restoration should only be necessary for historical research, as all content is preserved in the consolidated guide.
