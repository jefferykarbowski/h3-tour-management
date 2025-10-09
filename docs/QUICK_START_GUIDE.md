# Quick Start Guide - Tour Management v2.5.0

## 🚀 Activation Steps

### 1. Activate the Plugin
```
WordPress Admin → Plugins → Find "H3 Tour Management"
→ Click "Deactivate" (if already active)
→ Click "Activate"
```

This creates the new database table and migrates existing tours.

### 2. Flush Permalinks
```
Settings → Permalinks → Click "Save Changes"
```

This activates the URL redirect system.

### 3. Hard Refresh Browser
```
Press: Ctrl + Shift + R (Windows) or Cmd + Shift + R (Mac)
```

This loads the new JavaScript and CSS files.

---

## 🎯 Five Tour Management Buttons

Each tour now has FIVE action buttons:

| Button | Color | Function |
|--------|-------|----------|
| **Change URL** | Purple | Modify tour slug while preserving old URL redirects |
| **Update** | Blue | Overwrite tour files with new upload + clear cache |
| **Rename** | Gray | Change display name (URL stays same) |
| **Get Script** | Green | Generate iframe embed code for clients |
| **Delete** | Gray | Archive tour (90-day retention) |

---

## 📋 Common Workflows

### Upload New Tour
1. Enter tour name exactly as you want it displayed
2. Select ZIP file
3. Click "Upload Tour"
4. **The name you type is preserved exactly**

### Update Existing Tour
1. Click "Update" button
2. Select new ZIP file
3. Click "Update Tour"
4. Files overwrite, CloudFront cache clears automatically

### Get Embed Code for Client
1. Click "Get Script" button
2. Choose Standard (fixed) or Responsive (16:9)
3. Click "Copy to Clipboard"
4. Give code to client to paste in their HTML

### Change Tour URL
1. Click "Change URL" button
2. Enter new URL slug (lowercase, hyphens only)
3. Live preview shows new URL
4. Click "Change URL"
5. Old URL automatically redirects (301)

### Rename Tour (Display Name Only)
1. Click "Rename" button  
2. Enter new display name
3. Click "Rename Tour"
4. **URL stays exactly the same!**

---

## ✅ Verification Checklist

After activation, verify:

- [ ] Tour listing loads correctly
- [ ] All five buttons appear for each tour
- [ ] Existing tour names match what you uploaded
- [ ] Clicking any button opens appropriate modal
- [ ] Browser console shows no JavaScript errors (F12)

---

## 🐛 Troubleshooting

**Buttons don't appear:**
- Hard refresh browser (Ctrl+Shift+R)
- Check browser console for errors (F12)

**Tour names don't match upload:**
- They will after first new upload
- Existing tours use S3-converted names (spaces)
- New uploads preserve exact name typed

**URL redirects don't work:**
- Go to Settings → Permalinks → Save Changes
- This flushes rewrite rules

**"Get Script" doesn't copy:**
- Must use HTTPS site (Clipboard API requirement)
- Try selecting text and Ctrl+C manually

---

## 📌 Key Changes from v2.4.7

**Name Preservation:**
- OLD: "Bee Cave" uploaded → displayed as "Bee Cave" ✅
- NEW: "Bee Cave" uploaded → displayed as "Bee Cave" ✅
- **Exactly the same, but now using metadata for accuracy**

**URL Independence:**
- OLD: Rename tour → URL changes → links break ❌
- NEW: Rename tour → URL stays same → links work ✅

**Update Capability:**
- OLD: Delete and re-upload to update ❌
- NEW: Click "Update" → overwrite files ✅

**Client Distribution:**
- OLD: Manually create embed code ❌
- NEW: Click "Get Script" → copy code ✅

---

For full documentation, see: `docs/TOUR_MANAGEMENT_FEATURES_v2.5.0.md`
