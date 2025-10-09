# Add Rebuild Metadata Button to Settings Page

## Location
`includes/class-h3tm-admin.php` - In the `render_s3_settings_page()` method

## Find This Code (around line 755):
```php
                <div id="s3-test-result" style="margin-top: 10px;"></div>
            </div>

            <?php if (defined('H3_S3_BUCKET') || defined('AWS_ACCESS_KEY_ID')) : ?>
```

## Add This HTML BETWEEN those two sections:

```php
            <div class="h3tm-section" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;"><?php _e('Tour Metadata Management', 'h3-tour-management'); ?></h3>
                <p><?php _e('If tour names or URLs are incorrect, rebuild the metadata to match the actual S3 folder structure.', 'h3-tour-management'); ?></p>
                <button type="button" id="rebuild-tour-metadata" class="button button-secondary">
                    <?php _e('Rebuild Tour Metadata', 'h3-tour-management'); ?>
                </button>
                <span class="spinner" style="float: none; margin-left: 10px;"></span>
                <div id="rebuild-metadata-result" style="margin-top: 10px;"></div>
            </div>
```

## Add JavaScript Handler

**Find this code (around line 923 in the `<script>` section):**
```javascript
            });
        });
        </script>
```

**Add this BEFORE the `</script>` closing tag:**

```javascript

            $('#rebuild-tour-metadata').on('click', function() {
                var $button = $(this);
                var $spinner = $button.next('.spinner');
                var $result = $('#rebuild-metadata-result');

                if (!confirm('This will rebuild all tour metadata. Continue?')) {
                    return;
                }

                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $result.html('');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'h3tm_rebuild_metadata',
                        nonce: '<?php echo wp_create_nonce('h3tm_ajax_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p>Request failed</p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
```

## After Adding

1. Save the file
2. Hard refresh browser: `Ctrl+Shift+R`
3. Go to: **3D Tours → Settings**
4. You'll see a blue box with "Rebuild Tour Metadata" button!

---

**The button will appear between the S3 Status section and the configuration form.**
