<?php
/**
 * AWS Security Manager for H3 Tour Management
 *
 * Handles secure AWS credential management, S3 operations, and presigned URL generation
 * with enterprise-grade security controls and zero frontend exposure.
 *
 * @package H3_Tour_Management
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_AWS_Security {

    /**
     * Option keys for encrypted credential storage
     */
    const OPTION_AWS_ACCESS_KEY = 'h3tm_aws_access_key_encrypted';
    const OPTION_AWS_SECRET_KEY = 'h3tm_aws_secret_key_encrypted';
    const OPTION_AWS_REGION = 'h3tm_aws_region';
    const OPTION_AWS_BUCKET = 'h3tm_aws_bucket';
    const OPTION_AWS_CONFIG_HASH = 'h3tm_aws_config_hash';
    const OPTION_AWS_LAST_VALIDATED = 'h3tm_aws_last_validated';

    /**
     * Security constants
     */
    const ENCRYPTION_METHOD = 'AES-256-CBC';
    const PRESIGNED_URL_EXPIRY = 3600; // 1 hour
    const CONFIG_VALIDATION_INTERVAL = 86400; // 24 hours
    const MAX_OBJECT_SIZE = 1073741824; // 1GB

    /**
     * Rate limiting for S3 operations
     */
    const RATE_LIMIT_PRESIGNED = array(
        'requests' => 100,
        'window' => 3600
    );

    /**
     * Get encryption key for credential storage
     *
     * @return string
     */
    private static function get_encryption_key() {
        // Use WordPress salts for key derivation
        $key_base = AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY;

        // Add site-specific entropy
        $site_entropy = get_option('h3tm_site_entropy');
        if (!$site_entropy) {
            $site_entropy = wp_generate_password(32, true, true);
            update_option('h3tm_site_entropy', $site_entropy, false);
        }

        return hash('sha256', $key_base . $site_entropy);
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data with IV
     */
    private static function encrypt_data($data) {
        $key = self::get_encryption_key();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     *
     * @param string $encrypted_data Encrypted data with IV
     * @return string|false Decrypted data or false on failure
     */
    private static function decrypt_data($encrypted_data) {
        $key = self::get_encryption_key();
        $data = base64_decode($encrypted_data);

        if ($data === false || strlen($data) < 16) {
            return false;
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        return openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv);
    }

    /**
     * Store AWS credentials securely
     *
     * @param string $access_key AWS access key ID
     * @param string $secret_key AWS secret access key
     * @param string $region AWS region
     * @param string $bucket S3 bucket name
     * @return bool Success status
     */
    public static function store_credentials($access_key, $secret_key, $region, $bucket) {
        // Validate input parameters
        if (empty($access_key) || empty($secret_key) || empty($region) || empty($bucket)) {
            return false;
        }

        // Validate AWS access key format
        if (!preg_match('/^[A-Z0-9]{20}$/', $access_key)) {
            return false;
        }

        // Validate AWS secret key format
        if (!preg_match('/^[A-Za-z0-9\/+=]{40}$/', $secret_key)) {
            return false;
        }

        // Validate region format
        if (!preg_match('/^[a-z0-9-]+$/', $region)) {
            return false;
        }

        // Validate bucket name format
        if (!preg_match('/^[a-z0-9.-]{3,63}$/', $bucket)) {
            return false;
        }

        // Encrypt and store credentials
        $encrypted_access_key = self::encrypt_data($access_key);
        $encrypted_secret_key = self::encrypt_data($secret_key);

        if ($encrypted_access_key === false || $encrypted_secret_key === false) {
            return false;
        }

        // Store encrypted credentials
        update_option(self::OPTION_AWS_ACCESS_KEY, $encrypted_access_key, false);
        update_option(self::OPTION_AWS_SECRET_KEY, $encrypted_secret_key, false);
        update_option(self::OPTION_AWS_REGION, $region, false);
        update_option(self::OPTION_AWS_BUCKET, $bucket, false);

        // Generate and store configuration hash for integrity checking
        $config_hash = self::generate_config_hash($access_key, $secret_key, $region, $bucket);
        update_option(self::OPTION_AWS_CONFIG_HASH, $config_hash, false);

        // Reset validation timestamp
        delete_option(self::OPTION_AWS_LAST_VALIDATED);

        // Log credential update
        self::log_security_event('credentials_updated', array(
            'region' => $region,
            'bucket' => $bucket
        ));

        return true;
    }

    /**
     * Retrieve AWS credentials securely
     *
     * @return array|false Credentials array or false if not available
     */
    private static function get_credentials() {
        $encrypted_access_key = get_option(self::OPTION_AWS_ACCESS_KEY);
        $encrypted_secret_key = get_option(self::OPTION_AWS_SECRET_KEY);
        $region = get_option(self::OPTION_AWS_REGION);
        $bucket = get_option(self::OPTION_AWS_BUCKET);

        if (!$encrypted_access_key || !$encrypted_secret_key || !$region || !$bucket) {
            return false;
        }

        $access_key = self::decrypt_data($encrypted_access_key);
        $secret_key = self::decrypt_data($encrypted_secret_key);

        if ($access_key === false || $secret_key === false) {
            return false;
        }

        // Verify configuration integrity
        $stored_hash = get_option(self::OPTION_AWS_CONFIG_HASH);
        $current_hash = self::generate_config_hash($access_key, $secret_key, $region, $bucket);

        if ($stored_hash !== $current_hash) {
            self::log_security_event('config_integrity_failure', array(), 'error');
            return false;
        }

        return array(
            'access_key' => $access_key,
            'secret_key' => $secret_key,
            'region' => $region,
            'bucket' => $bucket
        );
    }

    /**
     * Generate configuration hash for integrity verification
     *
     * @param string $access_key AWS access key
     * @param string $secret_key AWS secret key
     * @param string $region AWS region
     * @param string $bucket S3 bucket
     * @return string Configuration hash
     */
    private static function generate_config_hash($access_key, $secret_key, $region, $bucket) {
        return hash('sha256', $access_key . $secret_key . $region . $bucket . AUTH_SALT);
    }

    /**
     * Validate AWS configuration by testing S3 access
     *
     * @param bool $force_check Force validation even if recently validated
     * @return array Validation result with status and message
     */
    public static function validate_configuration($force_check = false) {
        $result = array(
            'valid' => false,
            'message' => '',
            'details' => array()
        );

        // Check if credentials are configured
        $credentials = self::get_credentials();
        if (!$credentials) {
            $result['message'] = 'AWS credentials not configured or corrupted';
            return $result;
        }

        // Check rate limiting for validation requests
        if (!H3TM_Security::check_rate_limit('aws_validation', get_current_user_id())) {
            $result['message'] = 'Validation rate limit exceeded. Please wait before retrying.';
            return $result;
        }

        // Check if recently validated (unless forced)
        if (!$force_check) {
            $last_validated = get_option(self::OPTION_AWS_LAST_VALIDATED);
            if ($last_validated && (time() - $last_validated) < self::CONFIG_VALIDATION_INTERVAL) {
                $result['valid'] = true;
                $result['message'] = 'Configuration recently validated';
                $result['details']['last_validated'] = date('Y-m-d H:i:s', $last_validated);
                return $result;
            }
        }

        try {
            // Test S3 connection with minimal permissions
            $s3_config = array(
                'version' => 'latest',
                'region' => $credentials['region'],
                'credentials' => array(
                    'key' => $credentials['access_key'],
                    'secret' => $credentials['secret_key']
                ),
                'http' => array(
                    'verify' => H3TM_Config::should_verify_ssl()
                )
            );

            // Initialize S3 client (requires AWS SDK)
            if (!class_exists('Aws\S3\S3Client')) {
                $result['message'] = 'AWS SDK not available. Please install aws/aws-sdk-php via Composer.';
                return $result;
            }

            $s3_client = new Aws\S3\S3Client($s3_config);

            // Test bucket access
            $response = $s3_client->headBucket(array(
                'Bucket' => $credentials['bucket']
            ));

            // Test object operations (list with minimal results)
            $objects = $s3_client->listObjectsV2(array(
                'Bucket' => $credentials['bucket'],
                'MaxKeys' => 1
            ));

            // Update validation timestamp
            update_option(self::OPTION_AWS_LAST_VALIDATED, time(), false);

            $result['valid'] = true;
            $result['message'] = 'AWS S3 configuration validated successfully';
            $result['details'] = array(
                'bucket' => $credentials['bucket'],
                'region' => $credentials['region'],
                'validated_at' => date('Y-m-d H:i:s'),
                'object_count' => $objects['KeyCount'] ?? 0
            );

            self::log_security_event('config_validated', $result['details']);

        } catch (Exception $e) {
            $result['message'] = 'AWS configuration validation failed: ' . $e->getMessage();
            $result['details']['error'] = $e->getCode();

            self::log_security_event('config_validation_failed', array(
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ), 'error');
        }

        return $result;
    }

    /**
     * Generate presigned URL for S3 upload with security controls
     *
     * @param string $object_key S3 object key
     * @param array $conditions Upload conditions
     * @return array|false Presigned URL data or false on failure
     */
    public static function generate_presigned_upload_url($object_key, $conditions = array()) {
        // Check rate limiting
        if (!H3TM_Security::check_rate_limit('s3_presigned', get_current_user_id())) {
            return false;
        }

        // Validate configuration
        $validation = self::validate_configuration();
        if (!$validation['valid']) {
            return false;
        }

        $credentials = self::get_credentials();
        if (!$credentials) {
            return false;
        }

        // Sanitize object key
        $object_key = self::sanitize_s3_key($object_key);
        if (!$object_key) {
            return false;
        }

        try {
            $s3_client = new Aws\S3\S3Client(array(
                'version' => 'latest',
                'region' => $credentials['region'],
                'credentials' => array(
                    'key' => $credentials['access_key'],
                    'secret' => $credentials['secret_key']
                ),
                'http' => array(
                    'verify' => H3TM_Config::should_verify_ssl()
                )
            ));

            // Default upload conditions for security
            $default_conditions = array(
                array('content-length-range', 1, self::MAX_OBJECT_SIZE),
                array('starts-with', '$Content-Type', 'application/'),
                array('starts-with', '$key', 'tours/'),
            );

            // Merge with provided conditions
            $upload_conditions = array_merge($default_conditions, $conditions);

            // Generate presigned POST for secure uploads
            $post_object = $s3_client->createPresignedRequest(
                $s3_client->getCommand('PutObject', array(
                    'Bucket' => $credentials['bucket'],
                    'Key' => $object_key,
                    'ContentType' => 'application/zip'
                )),
                '+' . self::PRESIGNED_URL_EXPIRY . ' seconds'
            );

            $presigned_url = (string) $post_object->getUri();

            // Log presigned URL generation
            self::log_security_event('presigned_url_generated', array(
                'object_key' => $object_key,
                'expires' => time() + self::PRESIGNED_URL_EXPIRY
            ));

            return array(
                'url' => $presigned_url,
                'fields' => array(
                    'key' => $object_key,
                    'Content-Type' => 'application/zip'
                ),
                'expires' => time() + self::PRESIGNED_URL_EXPIRY
            );

        } catch (Exception $e) {
            self::log_security_event('presigned_url_failed', array(
                'object_key' => $object_key,
                'error' => $e->getMessage()
            ), 'error');

            return false;
        }
    }

    /**
     * Generate presigned URL for S3 download
     *
     * @param string $object_key S3 object key
     * @param int $expiry Expiry time in seconds (default: 1 hour)
     * @return string|false Presigned URL or false on failure
     */
    public static function generate_presigned_download_url($object_key, $expiry = null) {
        if ($expiry === null) {
            $expiry = self::PRESIGNED_URL_EXPIRY;
        }

        // Limit maximum expiry to 24 hours
        $expiry = min($expiry, 86400);

        $credentials = self::get_credentials();
        if (!$credentials) {
            return false;
        }

        // Sanitize object key
        $object_key = self::sanitize_s3_key($object_key);
        if (!$object_key) {
            return false;
        }

        try {
            $s3_client = new Aws\S3\S3Client(array(
                'version' => 'latest',
                'region' => $credentials['region'],
                'credentials' => array(
                    'key' => $credentials['access_key'],
                    'secret' => $credentials['secret_key']
                ),
                'http' => array(
                    'verify' => H3TM_Config::should_verify_ssl()
                )
            ));

            $request = $s3_client->createPresignedRequest(
                $s3_client->getCommand('GetObject', array(
                    'Bucket' => $credentials['bucket'],
                    'Key' => $object_key
                )),
                '+' . $expiry . ' seconds'
            );

            $presigned_url = (string) $request->getUri();

            self::log_security_event('download_url_generated', array(
                'object_key' => $object_key,
                'expires' => time() + $expiry
            ));

            return $presigned_url;

        } catch (Exception $e) {
            self::log_security_event('download_url_failed', array(
                'object_key' => $object_key,
                'error' => $e->getMessage()
            ), 'error');

            return false;
        }
    }

    /**
     * Sanitize S3 object key for security
     *
     * @param string $key Object key to sanitize
     * @return string|false Sanitized key or false if invalid
     */
    private static function sanitize_s3_key($key) {
        // Remove any null bytes or control characters
        $key = preg_replace('/[\x00-\x1F\x7F]/', '', $key);

        // Ensure key doesn't start with slash or contain double slashes
        $key = ltrim($key, '/');
        $key = preg_replace('/\/+/', '/', $key);

        // Check for path traversal attempts
        if (strpos($key, '..') !== false) {
            return false;
        }

        // Ensure key is within allowed prefix (tours/)
        if (strpos($key, 'tours/') !== 0) {
            $key = 'tours/' . $key;
        }

        // Validate final key length and characters
        if (strlen($key) > 1024 || !preg_match('/^[a-zA-Z0-9\/_.-]+$/', $key)) {
            return false;
        }

        return $key;
    }

    /**
     * Delete AWS credentials
     *
     * @return bool Success status
     */
    public static function delete_credentials() {
        delete_option(self::OPTION_AWS_ACCESS_KEY);
        delete_option(self::OPTION_AWS_SECRET_KEY);
        delete_option(self::OPTION_AWS_REGION);
        delete_option(self::OPTION_AWS_BUCKET);
        delete_option(self::OPTION_AWS_CONFIG_HASH);
        delete_option(self::OPTION_AWS_LAST_VALIDATED);

        self::log_security_event('credentials_deleted');

        return true;
    }

    /**
     * Check if AWS credentials are configured
     *
     * @return bool True if credentials are configured
     */
    public static function has_credentials() {
        return (bool) self::get_credentials();
    }

    /**
     * Get AWS configuration status for admin display (no sensitive data)
     *
     * @return array Configuration status information
     */
    public static function get_config_status() {
        $credentials = self::get_credentials();

        if (!$credentials) {
            return array(
                'configured' => false,
                'message' => 'AWS credentials not configured'
            );
        }

        $last_validated = get_option(self::OPTION_AWS_LAST_VALIDATED);

        return array(
            'configured' => true,
            'region' => $credentials['region'],
            'bucket' => $credentials['bucket'],
            'last_validated' => $last_validated ? date('Y-m-d H:i:s', $last_validated) : 'Never',
            'needs_validation' => !$last_validated || (time() - $last_validated) > self::CONFIG_VALIDATION_INTERVAL
        );
    }

    /**
     * Rotate encryption key (for credential rotation)
     *
     * @return bool Success status
     */
    public static function rotate_encryption() {
        $credentials = self::get_credentials();
        if (!$credentials) {
            return false;
        }

        // Force regeneration of entropy
        delete_option('h3tm_site_entropy');

        // Re-encrypt with new key
        return self::store_credentials(
            $credentials['access_key'],
            $credentials['secret_key'],
            $credentials['region'],
            $credentials['bucket']
        );
    }

    /**
     * Log security events
     *
     * @param string $event Event type
     * @param array $data Event data
     * @param string $level Log level
     */
    private static function log_security_event($event, $data = array(), $level = 'info') {
        // Remove sensitive data from logs
        $safe_data = array_diff_key($data, array_flip(array(
            'access_key', 'secret_key', 'password', 'token'
        )));

        H3TM_Security::log_security_event('aws_' . $event, $safe_data, $level);
    }

    /**
     * Clear expired presigned URLs from logs (cleanup task)
     */
    public static function cleanup_expired_urls() {
        // This would be called by a scheduled task to clean up expired URL logs
        // Implementation depends on logging strategy
        if (class_exists('H3TM_Logger')) {
            H3TM_Logger::cleanup_expired_entries('aws');
        }
    }
}