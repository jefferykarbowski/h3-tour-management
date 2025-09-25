# H3 Tour Management S3 Integration Testing Suite

Comprehensive testing suite for the H3 Tour Management plugin S3 integration functionality. This test suite helps identify and resolve configuration issues, verify functionality, and ensure system reliability.

## 🎯 Test Suite Purpose

The primary goal is to identify and resolve the specific issue where:
- ✅ S3 connection test succeeds
- ❌ S3 AJAX handler returns "S3 not configured"

This test suite provides systematic testing to verify all components work correctly and catch configuration inconsistencies between different WordPress contexts.

## 📁 Test Files Overview

### Core Test Files

| File | Purpose | Focus |
|------|---------|-------|
| `test-s3-configuration.php` | Configuration validation across contexts | Environment vs Database config priority |
| `test-s3-ajax-handlers.php` | AJAX handler registration and execution | Handler callbacks and context consistency |
| `test-s3-presigned-urls.php` | URL generation with various scenarios | AWS Signature v4 compliance and security |
| `test-s3-error-handling.php` | Error handling and fallback mechanisms | System resilience and recovery |
| `test-s3-integration-pipeline.php` | End-to-end integration testing | Tour upload pipeline integration |
| `s3-configuration-debugger.php` | Diagnostic and troubleshooting utility | Real-time configuration analysis |

### Utility Files

| File | Purpose |
|------|---------|
| `run-all-tests.php` | Master test runner |
| `test-report-generator.php` | Consolidated reporting |

## 🚀 Quick Start

### 1. Quick Diagnostic

For immediate issue identification:

```bash
# Via browser (WordPress admin)
https://yoursite.com/wp-content/plugins/h3-tour-management/tests/s3-configuration-debugger.php?h3tm_debug=1

# Via WP-CLI
wp eval-file tests/s3-configuration-debugger.php
```

### 2. Run Specific Tests

```bash
# Configuration validation
wp eval-file tests/test-s3-configuration.php

# AJAX handler testing
wp eval-file tests/test-s3-ajax-handlers.php

# Presigned URL testing
wp eval-file tests/test-s3-presigned-urls.php

# Error handling testing
wp eval-file tests/test-s3-error-handling.php

# Integration pipeline testing
wp eval-file tests/test-s3-integration-pipeline.php
```

### 3. Run Complete Test Suite

```bash
# All tests with consolidated report
wp eval-file tests/run-all-tests.php
```

## 🔧 Test Execution Methods

### Method 1: WordPress CLI (Recommended)

```bash
cd /path/to/wordpress
wp eval-file wp-content/plugins/h3-tour-management/tests/test-name.php
```

**Advantages:**
- Clean environment
- No browser timeouts
- Easy automation
- Detailed logging

### Method 2: Direct Browser Access

```
https://yoursite.com/wp-content/plugins/h3-tour-management/tests/test-name.php?run_test=1
```

**Requirements:**
- Must be logged in as administrator
- Browser must not timeout (large tests may take time)
- JavaScript should be enabled for interactive reports

### Method 3: WordPress Admin Integration

Add to your theme's `functions.php` or create an admin page:

```php
// Add this to create an admin menu for testing
add_action('admin_menu', function() {
    add_management_page(
        'S3 Integration Tests',
        'S3 Tests',
        'manage_options',
        's3-tests',
        function() {
            include plugin_dir_path(__FILE__) . 'tests/run-all-tests.php';
        }
    );
});
```

## 📊 Understanding Test Results

### Result Structure

Each test returns a standardized result structure:

```json
{
  "timestamp": "2024-01-15 10:30:45",
  "test_count": 8,
  "results": {
    "Test Name": {
      "test_data": "...",
      "success_metrics": "..."
    }
  },
  "debug_info": {
    "Test Name": {
      "summary": "Brief test summary",
      "recommendation": "Action to take"
    }
  },
  "overall_assessment": "Status summary",
  "action_items": ["Priority actions to take"]
}
```

### Severity Levels

- 🔴 **Critical**: System broken, requires immediate attention
- 🟠 **Error**: Feature not working, needs fixing
- 🟡 **Warning**: Potential issues, should be addressed
- 🟢 **Info**: Informational, system working correctly

### Common Result Interpretations

#### Configuration Test Results

```json
{
  "overall_assessment": "CRITICAL ISSUES: Configuration inconsistent in AJAX context"
}
```
**Action**: This is the primary issue. Configuration works in normal context but fails in AJAX.

```json
{
  "overall_assessment": "All tests passed - S3 configuration appears consistent"
}
```
**Action**: Configuration is working correctly across all contexts.

#### AJAX Handler Results

```json
{
  "overall_assessment": "CRITICAL ISSUES FOUND: AJAX Context Configuration"
}
```
**Action**: AJAX handlers are registered but configuration fails when called.

## 🔍 Troubleshooting Common Issues

### Issue 1: "S3 not configured" in AJAX but works in admin

**Symptoms:**
- S3 connection test passes
- AJAX handlers return "S3 not configured"
- Configuration debugger shows inconsistency

**Diagnosis:**
```bash
wp eval-file tests/s3-configuration-debugger.php
```
Look for: `"configuration_consistent": false`

**Common Causes:**
1. S3 Integration class instantiated differently in AJAX context
2. WordPress environment variables not available in AJAX
3. Database options not properly loaded in AJAX context
4. Plugin initialization order issues

**Solutions:**
1. Ensure S3 Integration is instantiated during `plugins_loaded` action
2. Verify environment variables are properly defined
3. Check database options are accessible in AJAX context
4. Review plugin initialization order

### Issue 2: All tests fail with class not found errors

**Symptoms:**
- "Class 'H3TM_S3_Integration' not found"
- Plugin appears inactive

**Solutions:**
1. Verify plugin is active: `wp plugin list`
2. Check file permissions
3. Ensure WordPress constants are properly defined
4. Verify plugin file structure

### Issue 3: Network connectivity issues

**Symptoms:**
- Connection timeouts
- SSL/TLS errors
- DNS resolution failures

**Diagnosis:**
```bash
wp eval-file tests/test-s3-error-handling.php
```

**Solutions:**
1. Check server firewall settings
2. Verify SSL certificates are up to date
3. Test DNS resolution manually
4. Check proxy settings if applicable

### Issue 4: AWS authentication failures

**Symptoms:**
- "Access Denied" errors
- "Invalid credentials" messages
- Signature mismatch errors

**Solutions:**
1. Verify AWS credentials are correct
2. Check IAM permissions include S3 access
3. Ensure system clock is synchronized
4. Verify bucket region settings

## 📈 Test Coverage

### Configuration Testing
- ✅ Environment variable detection
- ✅ Database option detection
- ✅ Configuration priority logic
- ✅ Cross-context consistency
- ✅ Validation and sanitization

### AJAX Handler Testing
- ✅ Handler registration
- ✅ Callback verification
- ✅ Context simulation
- ✅ Error handling
- ✅ Permission verification

### Presigned URL Testing
- ✅ Basic URL generation
- ✅ File size variations
- ✅ Special character handling
- ✅ AWS Signature v4 compliance
- ✅ Security validation

### Error Handling Testing
- ✅ Configuration errors
- ✅ Network errors
- ✅ AWS API errors
- ✅ Timeout handling
- ✅ Fallback mechanisms

### Integration Testing
- ✅ End-to-end upload flow
- ✅ File processing pipeline
- ✅ Metadata preservation
- ✅ Performance impact
- ✅ Backward compatibility

## 📋 Test Execution Checklist

### Pre-Testing

- [ ] WordPress site is accessible
- [ ] H3 Tour Management plugin is active
- [ ] User has administrator privileges
- [ ] Server meets system requirements (PHP 7.4+, required extensions)

### During Testing

- [ ] Monitor error logs for additional information
- [ ] Save test results for analysis
- [ ] Note any system performance impacts
- [ ] Document any manual intervention required

### Post-Testing

- [ ] Review all critical and error severity issues
- [ ] Implement recommended fixes
- [ ] Re-run tests to verify fixes
- [ ] Update configuration documentation

## 🔄 Continuous Testing

### Automated Testing Setup

Create a cron job for regular testing:

```bash
# Add to crontab for daily testing
0 2 * * * cd /path/to/wordpress && wp eval-file wp-content/plugins/h3-tour-management/tests/s3-configuration-debugger.php --quiet >> /var/log/h3tm-s3-tests.log 2>&1
```

### Monitoring Integration

Monitor test results with your existing monitoring system:

```bash
# Example integration with monitoring
wp eval-file tests/run-all-tests.php --format=json | your-monitoring-tool
```

## 🆘 Getting Help

### Log Analysis

Check WordPress debug logs for detailed information:

```bash
tail -f /path/to/wordpress/wp-content/debug.log | grep "H3TM"
```

### Support Information

When requesting support, include:

1. **Quick diagnostic results:**
```bash
wp eval-file tests/s3-configuration-debugger.php --quick
```

2. **System information:**
- WordPress version
- PHP version
- Plugin version
- Server environment (shared/VPS/dedicated)

3. **Configuration details:**
- Configuration method (environment variables vs database)
- AWS region
- File sizes being uploaded
- Error patterns (consistent vs intermittent)

### Common Support Requests

**"Tests pass but uploads still fail"**
- Run integration pipeline tests
- Check file size limits
- Verify tour file format

**"Intermittent failures"**
- Run error handling tests
- Check network connectivity
- Review server resources

**"Works in staging but not production"**
- Compare configurations between environments
- Check environment variable availability
- Verify AWS credentials and permissions

## 📚 Additional Resources

- [AWS S3 Documentation](https://docs.aws.amazon.com/s3/)
- [WordPress AJAX API](https://codex.wordpress.org/AJAX_in_Plugins)
- [WordPress Plugin Development](https://developer.wordpress.org/plugins/)
- [H3 Tour Management Plugin Documentation](../README.md)

---

**Last Updated:** 2024-01-15
**Test Suite Version:** 1.0
**Compatible with:** H3 Tour Management v1.5.0+