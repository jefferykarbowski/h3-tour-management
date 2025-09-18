# Error Response Specification - H3 Tour Management

## Standardized Error Response Format

All AJAX endpoints and backend operations should return errors in this consistent format:

### Success Response Structure
```json
{
    "success": true,
    "message": "Human-readable success message",
    "data": {
        "operation_id": "unique_operation_identifier",
        "additional_data": "..."
    },
    "timestamp": "2023-12-07 10:30:45"
}
```

### Error Response Structure
```json
{
    "success": false,
    "error": {
        "code": "error_classification_code",
        "message": "Human-readable error message",
        "context": {
            "additional_debug_info": "...",
            "field_errors": {...}
        },
        "timestamp": "2023-12-07 10:30:45",
        "trace_id": "error_unique_trace_id"
    }
}
```

## Error Code Classification

### Validation Errors (4xx family)
- `validation_failed` - Input validation failed
- `missing_parameters` - Required parameters not provided
- `invalid_format` - Parameter format is incorrect
- `duplicate_exists` - Resource already exists
- `not_found` - Requested resource doesn't exist

### Authentication/Authorization Errors (403 family)
- `security_failed` - Security nonce verification failed
- `insufficient_permissions` - User lacks required permissions
- `rate_limit_exceeded` - Too many requests from user

### Filesystem Errors (5xx family)
- `filesystem_error` - General filesystem operation failed
- `directory_not_found` - Target directory doesn't exist
- `permission_denied` - Insufficient filesystem permissions
- `disk_space_insufficient` - Not enough disk space
- `copy_verification_failed` - File copy verification failed

### Database Errors (5xx family)
- `database_error` - General database operation failed
- `transaction_failed` - Database transaction failed
- `connection_failed` - Database connection failed
- `query_failed` - Specific query execution failed

### Operation Errors (5xx family)
- `operation_failed` - General operation failure
- `timeout_exceeded` - Operation exceeded time limit
- `operation_not_found` - Progress tracking operation not found
- `background_queue_failed` - Background processing queue failed

### System Errors (5xx family)
- `unexpected_error` - Unexpected system error
- `service_unavailable` - Required service unavailable
- `configuration_error` - System configuration issue

## Error Context Information

### For Filesystem Errors
```json
"context": {
    "path": "/path/to/file/or/directory",
    "operation": "move|copy|delete|create",
    "file_count": 1234,
    "estimated_size": "125MB"
}
```

### For Database Errors
```json
"context": {
    "table": "wp_h3tm_table_name",
    "query_type": "SELECT|UPDATE|INSERT|DELETE",
    "affected_rows": 42,
    "last_insert_id": 123
}
```

### For Validation Errors
```json
"context": {
    "field_errors": {
        "tour_name": "Name contains invalid characters",
        "file_size": "File size exceeds maximum limit"
    },
    "validation_rules": {
        "tour_name": "alphanumeric_with_spaces",
        "file_size": "max_100mb"
    }
}
```

### For Operation Errors
```json
"context": {
    "operation_id": "rename_67890abcdef",
    "operation_type": "rename_tour",
    "target": "tour_name",
    "estimated_duration": 45,
    "elapsed_time": 30,
    "progress": 67
}
```

## HTTP Status Codes

- **200 OK** - Successful operations and handled errors (WordPress AJAX standard)
- **400 Bad Request** - Validation and client errors (when appropriate)
- **403 Forbidden** - Authentication/authorization failures
- **500 Internal Server Error** - Server-side errors and unexpected failures

## Frontend Error Handling

### JavaScript Error Handling Pattern
```javascript
$.ajax({
    // ... ajax configuration
    success: function(response) {
        if (response.success) {
            // Handle success
            handleSuccess(response.message, response.data);
        } else {
            // Handle structured error
            handleError(response.error);
        }
    },
    error: function(xhr, status, error) {
        // Handle network/server errors
        try {
            const response = JSON.parse(xhr.responseText);
            if (response.error) {
                handleError(response.error);
            } else {
                handleError({
                    code: 'network_error',
                    message: 'Network or server error occurred'
                });
            }
        } catch (e) {
            handleError({
                code: 'unknown_error',
                message: 'An unexpected error occurred'
            });
        }
    }
});

function handleError(error) {
    // Log for debugging
    console.error('Operation failed:', error);

    // Show user-friendly message
    showUserMessage(error.message, 'error');

    // Handle specific error codes
    switch (error.code) {
        case 'rate_limit_exceeded':
            // Disable UI temporarily
            break;
        case 'filesystem_error':
            // Suggest retry or alternative
            break;
        case 'operation_failed':
            // Check if operation can be resumed
            break;
    }
}
```

### User Message Display
- **Validation Errors**: Show field-specific errors inline
- **Permission Errors**: Show clear permission requirements
- **Filesystem Errors**: Suggest actionable solutions
- **Network Errors**: Suggest retry or check connection
- **System Errors**: Provide support contact information

## Logging and Debugging

### Error Logging Format
```php
H3TM_Logger::error('operation', 'Error description', array(
    'error_code' => $error_code,
    'user_id' => get_current_user_id(),
    'request_data' => $sanitized_request_data,
    'system_context' => array(
        'php_version' => PHP_VERSION,
        'wordpress_version' => get_bloginfo('version'),
        'plugin_version' => H3TM_VERSION,
        'memory_usage' => memory_get_usage(true),
        'time_limit' => ini_get('max_execution_time')
    ),
    'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
));
```

### Debug Mode Enhancement
When `WP_DEBUG` is enabled, include additional context:
- Full stack traces
- Request/response data
- System environment details
- Performance metrics

## Migration Guide

### Updating Existing Handlers
1. Replace direct `wp_send_json_error()` calls with structured error responses
2. Add error code classification
3. Include relevant context information
4. Update frontend JavaScript to handle new format
5. Add appropriate logging

### Example Migration
**Before:**
```php
wp_send_json_error('Failed to rename tour');
```

**After:**
```php
wp_send_json_error(array(
    'code' => 'filesystem_error',
    'message' => __('Failed to rename tour directory', 'h3-tour-management'),
    'context' => array(
        'old_name' => $old_name,
        'new_name' => $new_name,
        'path' => $tour_path,
        'operation' => 'directory_rename'
    )
));
```

## Testing Error Responses

### Unit Test Structure
```php
public function test_rename_tour_validation_error() {
    $result = $this->tour_manager->rename_tour('', 'new_name');

    $this->assertFalse($result['success']);
    $this->assertEquals('validation_failed', $result['error']['code']);
    $this->assertContains('required', $result['error']['message']);
    $this->assertArrayHasKey('context', $result['error']);
}
```

### Frontend Testing
- Test error message display
- Verify error code handling
- Check retry mechanisms
- Validate user experience flow