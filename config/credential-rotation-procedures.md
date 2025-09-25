# AWS Credential Rotation Procedures

## Overview

This document outlines the secure procedures for rotating AWS credentials in the H3 Tour Management plugin to maintain security best practices and minimize the risk of credential compromise.

## Rotation Schedule

- **Production**: Quarterly (every 90 days)
- **Staging**: Semi-annually (every 180 days)
- **Development**: Annually (every 365 days)
- **Emergency**: Immediately upon suspected compromise

## Automated Rotation Process

### 1. Pre-Rotation Checklist

```php
// WordPress function to prepare for rotation
function h3tm_pre_rotation_check() {
    $checklist = array(
        'current_config_valid' => H3TM_AWS_Security::validate_configuration(true),
        'recent_uploads' => check_recent_s3_activity(24), // Last 24 hours
        'backup_available' => verify_credential_backup(),
        'maintenance_window' => is_maintenance_window()
    );

    foreach ($checklist as $item => $status) {
        if (!$status['success']) {
            return array(
                'ready' => false,
                'error' => "Pre-rotation check failed: {$item}",
                'details' => $status
            );
        }
    }

    return array('ready' => true);
}
```

### 2. AWS Credential Generation

```bash
#!/bin/bash
# AWS CLI script for credential rotation

# Set variables
IAM_USER="h3tm-s3-upload"
OLD_KEY_ID="$1"
ENVIRONMENT="$2" # dev/staging/prod

# Generate new access key
echo "Generating new access key for ${IAM_USER}..."
NEW_CREDENTIALS=$(aws iam create-access-key --user-name ${IAM_USER} --output json)

if [ $? -ne 0 ]; then
    echo "ERROR: Failed to create new access key"
    exit 1
fi

# Extract new credentials
NEW_ACCESS_KEY=$(echo ${NEW_CREDENTIALS} | jq -r '.AccessKey.AccessKeyId')
NEW_SECRET_KEY=$(echo ${NEW_CREDENTIALS} | jq -r '.AccessKey.SecretAccessKey')

echo "New access key created: ${NEW_ACCESS_KEY}"

# Test new credentials
export AWS_ACCESS_KEY_ID=${NEW_ACCESS_KEY}
export AWS_SECRET_ACCESS_KEY=${NEW_SECRET_KEY}

aws s3 ls s3://your-h3-tours-bucket-${ENVIRONMENT}/tours/ > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "New credentials validated successfully"

    # Store credentials securely for deployment
    echo "{
        \"access_key\": \"${NEW_ACCESS_KEY}\",
        \"secret_key\": \"${NEW_SECRET_KEY}\",
        \"old_key_id\": \"${OLD_KEY_ID}\",
        \"created_at\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"
    }" | gpg --armor --cipher-algo AES256 --compress-algo 2 --symmetric --output new-credentials-${ENVIRONMENT}.gpg

    echo "New credentials encrypted and ready for deployment"
else
    echo "ERROR: New credentials validation failed"

    # Clean up failed key
    aws iam delete-access-key --user-name ${IAM_USER} --access-key-id ${NEW_ACCESS_KEY}
    exit 1
fi
```

### 3. WordPress Configuration Update

```php
// WordPress rotation function
function h3tm_rotate_aws_credentials($new_access_key, $new_secret_key, $old_access_key = null) {
    // Validate rotation prerequisites
    $pre_check = h3tm_pre_rotation_check();
    if (!$pre_check['ready']) {
        return array(
            'success' => false,
            'error' => $pre_check['error']
        );
    }

    // Get current configuration
    $current_config = H3TM_AWS_Security::get_config_status();
    if (!$current_config['configured']) {
        return array(
            'success' => false,
            'error' => 'No current AWS configuration found'
        );
    }

    // Create backup of current configuration
    $backup_result = h3tm_backup_current_credentials($old_access_key);
    if (!$backup_result['success']) {
        return array(
            'success' => false,
            'error' => 'Failed to backup current credentials: ' . $backup_result['error']
        );
    }

    // Store new credentials
    $store_result = H3TM_AWS_Security::store_credentials(
        $new_access_key,
        $new_secret_key,
        $current_config['region'],
        $current_config['bucket']
    );

    if (!$store_result) {
        // Restore from backup
        h3tm_restore_credentials_from_backup($backup_result['backup_id']);
        return array(
            'success' => false,
            'error' => 'Failed to store new credentials'
        );
    }

    // Validate new configuration
    $validation = H3TM_AWS_Security::validate_configuration(true);
    if (!$validation['valid']) {
        // Restore from backup
        h3tm_restore_credentials_from_backup($backup_result['backup_id']);
        return array(
            'success' => false,
            'error' => 'New credentials validation failed: ' . $validation['message']
        );
    }

    // Log successful rotation
    H3TM_Security::log_security_event('credential_rotation_completed', array(
        'old_access_key_id' => $old_access_key ? substr($old_access_key, 0, 8) . '...' : 'unknown',
        'new_access_key_id' => substr($new_access_key, 0, 8) . '...',
        'backup_id' => $backup_result['backup_id']
    ));

    return array(
        'success' => true,
        'backup_id' => $backup_result['backup_id'],
        'rotated_at' => current_time('mysql')
    );
}
```

### 4. Credential Backup and Recovery

```php
// Backup current credentials before rotation
function h3tm_backup_current_credentials($access_key_id = null) {
    $credentials = H3TM_AWS_Security::get_credentials();
    if (!$credentials) {
        return array('success' => false, 'error' => 'No credentials to backup');
    }

    $backup_id = 'backup_' . date('Y-m-d_H-i-s') . '_' . wp_generate_password(8, false);

    $backup_data = array(
        'access_key' => $credentials['access_key'],
        'secret_key' => $credentials['secret_key'],
        'region' => $credentials['region'],
        'bucket' => $credentials['bucket'],
        'backed_up_at' => current_time('mysql'),
        'access_key_id' => $access_key_id
    );

    // Encrypt backup data
    $encrypted_backup = H3TM_AWS_Security::encrypt_data(json_encode($backup_data));

    // Store backup in WordPress options (temporary)
    update_option("h3tm_credential_backup_{$backup_id}", $encrypted_backup, false);

    // Schedule backup cleanup (7 days)
    wp_schedule_single_event(time() + (7 * DAY_IN_SECONDS), 'h3tm_cleanup_credential_backup', array($backup_id));

    return array(
        'success' => true,
        'backup_id' => $backup_id
    );
}

// Restore credentials from backup
function h3tm_restore_credentials_from_backup($backup_id) {
    $encrypted_backup = get_option("h3tm_credential_backup_{$backup_id}");
    if (!$encrypted_backup) {
        return array('success' => false, 'error' => 'Backup not found');
    }

    $backup_json = H3TM_AWS_Security::decrypt_data($encrypted_backup);
    if (!$backup_json) {
        return array('success' => false, 'error' => 'Failed to decrypt backup');
    }

    $backup_data = json_decode($backup_json, true);
    if (!$backup_data) {
        return array('success' => false, 'error' => 'Invalid backup data');
    }

    // Restore credentials
    $result = H3TM_AWS_Security::store_credentials(
        $backup_data['access_key'],
        $backup_data['secret_key'],
        $backup_data['region'],
        $backup_data['bucket']
    );

    if ($result) {
        H3TM_Security::log_security_event('credential_restore_completed', array(
            'backup_id' => $backup_id,
            'restored_at' => current_time('mysql')
        ));
    }

    return array('success' => $result);
}
```

## Manual Rotation Process

### Emergency Rotation (Immediate)

1. **Immediate Actions**
   ```bash
   # Disable current access key immediately
   aws iam update-access-key --user-name h3tm-s3-upload --access-key-id CURRENT_KEY --status Inactive
   ```

2. **Generate Emergency Credentials**
   ```bash
   # Create new key pair
   aws iam create-access-key --user-name h3tm-s3-upload
   ```

3. **Update WordPress Configuration**
   - Access WordPress admin
   - Navigate to 3D Tours > Settings
   - Update AWS credentials with new values
   - Test configuration immediately

4. **Clean Up**
   ```bash
   # Delete old access key after verification
   aws iam delete-access-key --user-name h3tm-s3-upload --access-key-id OLD_KEY
   ```

### Planned Rotation (Quarterly)

1. **Schedule Maintenance Window**
   - Plan 2-hour maintenance window
   - Notify users of potential service interruption
   - Ensure no critical uploads scheduled

2. **Pre-Rotation Validation**
   ```php
   // Run WordPress function
   $check = h3tm_pre_rotation_check();
   if (!$check['ready']) {
       // Reschedule rotation
       return;
   }
   ```

3. **Execute Rotation**
   ```bash
   # Run the rotation script
   ./scripts/rotate-aws-credentials.sh CURRENT_ACCESS_KEY prod
   ```

4. **Post-Rotation Testing**
   ```php
   // Test all functionality
   $validation = H3TM_AWS_Security::validate_configuration(true);
   $upload_test = test_presigned_url_generation();
   $download_test = test_presigned_download();
   ```

## Verification and Testing

### Automated Tests

```php
// WordPress function to test all AWS functionality
function h3tm_test_aws_functionality() {
    $tests = array();

    // Test 1: Configuration validation
    $tests['config_validation'] = H3TM_AWS_Security::validate_configuration(true);

    // Test 2: Presigned URL generation
    $test_key = 'tours/test_' . time() . '.zip';
    $tests['presigned_url'] = (bool) H3TM_AWS_Security::generate_presigned_upload_url($test_key);

    // Test 3: S3 connectivity
    try {
        $credentials = H3TM_AWS_Security::get_credentials();
        $s3_client = new Aws\S3\S3Client(array(
            'version' => 'latest',
            'region' => $credentials['region'],
            'credentials' => array(
                'key' => $credentials['access_key'],
                'secret' => $credentials['secret_key']
            )
        ));

        $result = $s3_client->listObjectsV2(array(
            'Bucket' => $credentials['bucket'],
            'Prefix' => 'tours/',
            'MaxKeys' => 1
        ));

        $tests['s3_connectivity'] = true;

    } catch (Exception $e) {
        $tests['s3_connectivity'] = false;
        $tests['s3_error'] = $e->getMessage();
    }

    return $tests;
}
```

### Manual Verification Steps

1. **Access WordPress Admin**
   - Navigate to 3D Tours > Settings
   - Verify AWS configuration shows "Connected"
   - Check last validation timestamp

2. **Test File Upload**
   - Upload a test ZIP file
   - Verify it uploads to S3 successfully
   - Confirm the file appears in tour list

3. **Test File Download**
   - Generate a presigned download URL
   - Verify the URL works in browser
   - Check access logs for successful request

## Rollback Procedures

### Immediate Rollback (< 1 hour)

```php
// WordPress function for immediate rollback
function h3tm_emergency_rollback($backup_id) {
    // Restore from backup
    $restore_result = h3tm_restore_credentials_from_backup($backup_id);

    if ($restore_result['success']) {
        // Re-activate old AWS key if still exists
        $backup_data = get_backup_data($backup_id);
        if ($backup_data['access_key_id']) {
            // Attempt to reactivate via AWS CLI or API
            reactivate_aws_access_key($backup_data['access_key_id']);
        }

        // Validate rollback
        $validation = H3TM_AWS_Security::validate_configuration(true);

        return array(
            'success' => $validation['valid'],
            'message' => $validation['message']
        );
    }

    return $restore_result;
}
```

### Extended Rollback (> 1 hour)

1. **Assess Current State**
   - Check if old AWS credentials still exist
   - Verify backup data integrity
   - Identify root cause of rotation failure

2. **Coordinate with AWS**
   - If old credentials deleted, create new temporary credentials
   - Ensure S3 bucket access is maintained
   - Update IAM policies if necessary

3. **WordPress Recovery**
   - Use backup restoration process
   - Update configuration with working credentials
   - Perform full functionality testing

## Monitoring and Alerting

### CloudWatch Alarms

```json
{
  "AlarmName": "H3TM-CredentialRotationFailure",
  "MetricName": "Errors",
  "Namespace": "H3TM/CredentialRotation",
  "Statistic": "Sum",
  "Period": 300,
  "EvaluationPeriods": 1,
  "Threshold": 1,
  "ComparisonOperator": "GreaterThanOrEqualToThreshold",
  "AlarmActions": ["arn:aws:sns:region:account:h3tm-alerts"]
}
```

### WordPress Monitoring

```php
// Log rotation events
add_action('h3tm_credential_rotation_started', function($data) {
    wp_mail(
        get_option('admin_email'),
        'AWS Credential Rotation Started',
        'AWS credential rotation has started for environment: ' . ($data['environment'] ?? 'unknown')
    );
});

add_action('h3tm_credential_rotation_completed', function($data) {
    wp_mail(
        get_option('admin_email'),
        'AWS Credential Rotation Completed',
        'AWS credential rotation completed successfully'
    );
});

add_action('h3tm_credential_rotation_failed', function($data) {
    wp_mail(
        get_option('admin_email'),
        'URGENT: AWS Credential Rotation Failed',
        'AWS credential rotation failed. Immediate action required. Error: ' . $data['error']
    );
});
```

## Security Considerations

1. **Access Control**
   - Only senior administrators should perform manual rotations
   - Automated rotations should run with minimal required permissions
   - All rotation activities must be logged and auditable

2. **Data Protection**
   - Backup credentials are encrypted and automatically cleaned up
   - Old credentials are immediately disabled after successful rotation
   - Rotation process includes integrity validation at each step

3. **Business Continuity**
   - Rotations are scheduled during maintenance windows
   - Rollback procedures are tested and documented
   - Emergency procedures are available for immediate execution

4. **Compliance**
   - Rotation schedule meets industry requirements
   - All activities are logged for audit purposes
   - Documentation is maintained and version controlled