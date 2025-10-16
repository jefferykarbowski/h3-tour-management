# Deploy Code Changes to Local WordPress

**Issue**: Code changes in Git aren't being used by local WordPress site
**Cause**: PHP opcode cache (OPcache) is serving old cached code

---

## Quick Fix: Restart Local by Flywheel

### Method 1: Restart from App (Easiest)

1. Open **Local by Flywheel** app
2. Find site: **h3vt**
3. Click **Stop** button
4. Wait 5 seconds
5. Click **Start** button
6. Done! ✅

### Method 2: Command Line

```bash
# Stop the site
"C:\Program Files (x86)\Local\lightning-services\lightning-services.exe" stop h3vt

# Wait 5 seconds

# Start the site
"C:\Program Files (x86)\Local\lightning-services\lightning-services.exe" start h3vt
```

---

## Why This Happens

Your plugin is **symlinked** to the Git repository:
```
C:/Users/Jeff/Local Sites/h3vt/app/public/wp-content/plugins/h3-tour-management
→ SYMLINK →
C:/Users/Jeff/Documents/GitHub/h3-tour-management
```

✅ **Good**: Code changes are immediately available
❌ **Problem**: PHP OPcache doesn't detect symlink changes automatically

**Solution**: Restart Local to clear OPcache

---

## Alternative: Disable OPcache (For Development)

Edit Local's PHP configuration:

1. **Local by Flywheel** → Right-click **h3vt** → **Open Site Shell**
2. Edit php.ini:
   ```bash
   nano /opt/local/php/php.ini
   ```
3. Find and change:
   ```ini
   opcache.enable=1
   ```
   To:
   ```ini
   opcache.enable=0
   ```
4. Restart Local

**Warning**: This will slow down PHP but ensures changes are immediate.

---

## After Restarting

Test your tours:
- `https://h3vt.local/h3panos/Arden%20Pikesville/` → Should work ✅
- `https://h3vt.local/h3panos/asdfasdf/` → Should work ✅

Check debug.log:
```
tail -f C:/Users/Jeff/Local\ Sites/h3vt/app/public/wp-content/debug.log
```

You should see:
```
H3TM S3 Proxy: Using CDN helper for "Arden Pikesville"
H3TM S3 Proxy: CDN URLs: https://d14z8re58oj029.cloudfront.net/tours/Arden%20Pikesville/index.htm, ...
```

(Note the **space** in the URL, not a dash!)

---

## Commits That Need to Be Active

Recent commits with fixes:
- `8108e06` - **fix(cdn): correct space handling in tour URLs** ← Critical fix!
- `07c934d` - docs: update to CloudFront Origin Access Control (OAC)
- `62dbae0` - docs: add executive summary for 403 fix

---

**TL;DR**: Restart Local by Flywheel to clear PHP cache, then test again!
