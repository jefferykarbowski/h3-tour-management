# H3TM Analytics CRON Setup for Pantheon

## Overview
The H3TM plugin now includes **automatic analytics injection** via WordPress CRON that runs every hour to detect and process new tours added to the `/h3panos/` directory.

## Features
- ✅ **Automatic Detection**: Finds new tours without analytics  
- ✅ **GA4 Injection**: Adds tour-specific tracking code
- ✅ **WordPress Integration**: Admin panel + notifications
- ✅ **Backup System**: Creates backup files before modification
- ✅ **Logging**: Detailed logs for troubleshooting
- ✅ **Manual Trigger**: Run injection on-demand from admin

## WordPress Integration

### Admin Panel
Navigate to: **H3 Tour Management → Analytics CRON**

**Features:**
- View CRON schedule and last run status  
- See execution results (new tours, processed, errors)
- Manual injection trigger button
- Real-time processing feedback

### Notifications
- Admin notices show newly processed tours
- Email notifications (optional)
- Webhook status endpoint for monitoring

## CRON Schedule

**Default**: Every hour
**Hook**: `h3tm_inject_analytics` 

The CRON job automatically:
1. Scans `/h3panos/` directory for new tours
2. Identifies tours without GA4 analytics
3. Injects tour-specific tracking code
4. Creates backup files  
5. Logs all activities

## File Structure

```
wp-content/plugins/h3-tour-management/
├── includes/
│   └── class-h3tm-cron-analytics.php    # WordPress integration
├── tools/
│   └── cron-inject-analytics.php        # CRON execution script  
└── logs/
    └── h3tm-analytics-cron.log          # Activity logs
```

## How It Works

### When Client Adds New Tour:
1. Client uploads tour to `/h3panos/New-Tour-Name/`
2. **Within 1 hour**, CRON detects the new tour
3. Analytics code is **automatically injected**
4. **Admin receives notification**
5. **Your shortcode immediately finds the new data**

### Analytics Code Injected:
```html
<!-- Google Analytics 4 - Auto-injected by CRON -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-08Q1M637NJ"></script>
<script>
  gtag('config', 'G-08Q1M637NJ', {
    page_title: 'New-Tour-Name'
  });
  
  gtag('event', 'tour_view', {
    'tour_name': 'New-Tour-Name',
    'event_category': '3dvista_tour',
    'auto_injected': true
  });
</script>
```

## Manual Operation

### Via Admin Panel:
1. Go to **H3 Tour Management → Analytics CRON**
2. Click **"Run Analytics Injection Now"**
3. View real-time results

### Via WP-CLI (Pantheon Terminal):
```bash
wp cron event run h3tm_inject_analytics
```

### Via URL (for external monitoring):
```
https://yoursite.com/wp-admin/admin-ajax.php?action=h3tm_cron_status
```

## Troubleshooting

### If CRON Isn't Running:
```bash
# Check WordPress CRON status
wp cron event list

# Manually trigger
wp cron event run h3tm_inject_analytics
```

### Log Location:
```
/logs/h3tm-analytics-cron.log
```

### Common Issues:
- **File permissions**: Ensure WordPress can read/write h3panos directory
- **Backup directory**: Script creates `.backup-*` files - ensure disk space
- **GA4 ID**: Verify `G-08Q1M637NJ` is correct in script

## Pantheon-Specific Notes

### File Permissions:
Pantheon automatically handles file permissions for WordPress

### Logs:
Logs are written to the plugin's `/logs/` directory within the codebase

### CRON Reliability:
WordPress CRON on Pantheon is reliable and runs with web traffic

## Monitoring & Alerts

### Admin Notices:
New tours processed in last 24 hours show admin notices

### Webhook Monitoring:
```bash
curl "https://yoursite.com/wp-admin/admin-ajax.php?action=h3tm_cron_status"
```

### Log Monitoring:
Check logs for entries like:
```
[2025-09-13 15:53:54] SUCCESS: New-Tour-Name - Analytics injected
[2025-09-13 15:53:54] 🎉 1 new tours now have GA4 tracking!
```

## Result
✅ **Zero-maintenance analytics injection**  
✅ **Client uploads tours → Analytics automatic**  
✅ **Your shortcodes work immediately**  
✅ **Full audit trail and monitoring**