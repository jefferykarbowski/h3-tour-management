# H3TM S3 Configuration Testing Suite

Comprehensive testing suite to identify and resolve the "all credentials missing" error that occurs in AJAX contexts but works in WordPress admin.

## Problem Statement

S3 configuration consistently works in WordPress admin pages but always fails during AJAX requests with "all credentials missing" errors. This testing suite provides comprehensive diagnostic tools to identify the exact cause and provide solutions.

## Test Files Overview

### 🎯 Master Test Runner
**File:** `run-comprehensive-s3-tests.php`
- **Purpose:** Orchestrates all tests to provide comprehensive analysis
- **Usage:** `?run_master_s3_tests=1`
- **Output:** Executive summary with root cause analysis and recommendations

### 🔍 Context Validator
**File:** `comprehensive-s3-context-validator.php`
- **Purpose:** Compares S3 configuration between admin and AJAX contexts
- **Usage:** `?run_comprehensive_validation=1`
- **Key Features:**
  - Side-by-side admin vs AJAX comparison
  - Environment variable access validation
  - Database option access validation
  - WordPress initialization timing analysis

### ⚠️ Edge Case Validator
**File:** `s3-edge-case-validator.php`
- **Purpose:** Tests failure scenarios and recovery mechanisms
- **Usage:** `?run_edge_case_validation=1`
- **Test Scenarios:**
  - Missing bucket configuration
  - Missing credentials
  - Partial configuration states
  - Cache interference
  - Memory pressure conditions

### 🛠️ Debug Utilities
**File:** `s3-debug-utilities.php`
- **Purpose:** Real-time configuration monitoring and path tracing
- **Usage:** `?run_s3_debug=1&test_type=quick`
- **Available Tests:**
  - `quick` - Fast diagnostic
  - `trace` - Configuration loading path trace
  - `context` - Context difference analysis
  - `monitor` - Real-time monitoring
  - `recovery` - Recovery mechanism testing

### 📋 Existing Tests
- `test-s3-configuration.php` - Basic configuration validation
- `test-s3-ajax-handlers.php` - AJAX handler testing
- `s3-configuration-debugger.php` - Configuration debugging
- `test-s3-error-handling.php` - Error handling validation

## Quick Start

### 1. Run Master Test Suite (Recommended)
```
https://your-site.com/wp-content/plugins/h3-tour-management/tests/run-comprehensive-s3-tests.php?run_master_s3_tests=1
```

### 2. Quick Diagnostic
```
https://your-site.com/wp-content/plugins/h3-tour-management/tests/s3-debug-utilities.php?run_s3_debug=1&test_type=quick
```

### 3. Context Comparison
```
https://your-site.com/wp-content/plugins/h3-tour-management/tests/comprehensive-s3-context-validator.php?run_comprehensive_validation=1
```

## Test Methodology

### Phase 1: Quick Diagnostic
- Rapid configuration health assessment
- Environment variable detection
- Database option verification
- Class instantiation testing

### Phase 2: Context Validation
- Admin context configuration capture
- AJAX context configuration capture
- Side-by-side comparison analysis
- Critical difference identification

### Phase 3: Edge Case Testing
- Missing component scenarios
- Partial configuration testing
- Cache interference detection
- Recovery mechanism validation

### Phase 4: Configuration Tracing
- Step-by-step loading path analysis
- Environment variable resolution
- Database option resolution
- Priority logic verification

### Phase 5: Root Cause Analysis
- Cross-phase pattern identification
- Evidence correlation
- Confidence-scored conclusions
- Actionable recommendations

## Expected Findings

### Likely Root Causes
1. **AJAX Context Configuration Failure** - Configuration loading fails specifically in AJAX requests
2. **Context-Dependent Data Access** - Environment variables or database options not accessible in AJAX
3. **WordPress Initialization Timing** - Configuration loaded before WordPress is fully initialized in AJAX
4. **Cache Interference** - Cached configuration not properly invalidated between contexts

### Common Issues
- Environment variables not defined in AJAX context
- Database connection not available during AJAX initialization
- Class instantiation timing issues
- Configuration manager caching problems

## Troubleshooting Guide

### If Tests Show "All Healthy"
- Issue may be intermittent or request-specific
- Run monitoring test: `test_type=monitor&duration=60`
- Check specific AJAX endpoints directly

### If Tests Confirm AJAX Issues
1. Check environment variable access in AJAX context
2. Verify database option availability during AJAX
3. Test configuration manager instantiation timing
4. Implement recommended fixes from test output

### If Tests Fail to Run
1. Ensure user has `manage_options` capability
2. Check WordPress is fully loaded
3. Verify plugin files are accessible
4. Check PHP error logs for fatal errors

## Configuration Requirements

### Environment Variables (Recommended)
```php
// In wp-config.php
define('H3_S3_BUCKET', 'your-bucket-name');
define('H3_S3_REGION', 'us-east-1');
define('AWS_ACCESS_KEY_ID', 'your-access-key');
define('AWS_SECRET_ACCESS_KEY', 'your-secret-key');
```

### Database Options (Alternative)
- `h3tm_s3_bucket`
- `h3tm_s3_region`
- `h3tm_aws_access_key`
- `h3tm_aws_secret_key`
- `h3tm_s3_enabled`

## Security Notes

- Tests sanitize credential output (show only first 4 characters)
- No credentials are logged in plain text
- Tests require admin privileges to run
- Temporary configuration changes are automatically restored

## Output Formats

### JSON Export
All tests can export results to JSON files in the uploads directory:
- `h3tm-s3-master-test-results-YYYY-MM-DD-HH-MM-SS.json`
- `h3tm-s3-context-validation-YYYY-MM-DD-HH-MM-SS.json`
- `h3tm-s3-edge-case-validation-YYYY-MM-DD-HH-MM-SS.json`

### Console Output
Tests provide structured console output with:
- Executive summary
- Critical findings
- Root cause analysis
- Recommended actions
- Technical details

## Advanced Usage

### WP-CLI Integration
```bash
wp eval-file tests/run-comprehensive-s3-tests.php
```

### Custom Test Scenarios
```php
$validator = new H3TM_S3_Context_Validator();
$results = $validator->validate_all_contexts();
```

### Monitoring Integration
```php
$debug_utilities = new H3TM_S3_Debug_Utilities(true);
$monitor_results = $debug_utilities->monitor_configuration_changes(120);
```

## Contributing

When adding new tests:
1. Follow existing naming conventions
2. Include comprehensive error handling
3. Sanitize credential output
4. Add appropriate logging
5. Update this README

## Support

For issues with the testing suite:
1. Check WordPress error logs
2. Verify admin permissions
3. Ensure plugin is fully loaded
4. Test individual components first

The comprehensive test suite will identify the root cause of S3 configuration failures in AJAX contexts and provide specific recommendations for resolution.