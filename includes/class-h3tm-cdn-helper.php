<?php
/**
 * CDN Helper - Manages CloudFront and S3 URL generation
 *
 * Provides abstraction layer for URL generation with CloudFront support
 * and automatic fallback to S3 direct access when CloudFront is not configured.
 */
class H3TM_CDN_Helper {

    private static $instance = null;
    private $use_cloudfront = false;
    private $cloudfront_domain = '';
    private $s3_bucket = '';
    private $s3_region = '';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        $this->init_config();
    }

    /**
     * Initialize configuration from options
     */
    private function init_config() {
        // Check if CloudFront is enabled and configured
        $this->use_cloudfront = get_option('h3tm_cloudfront_enabled', false);
        $this->cloudfront_domain = get_option('h3tm_cloudfront_domain', '');

        // Remove protocol if included in domain
        $this->cloudfront_domain = preg_replace('#^https?://#', '', $this->cloudfront_domain);

        // Get S3 configuration for fallback
        $this->s3_bucket = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', '');
        $this->s3_region = defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1');

        // Disable CloudFront if no domain configured
        if (empty($this->cloudfront_domain)) {
            $this->use_cloudfront = false;
        }

        // Log configuration
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('H3TM CDN Helper: CloudFront=' . ($this->use_cloudfront ? 'enabled' : 'disabled') .
                     ', Domain=' . $this->cloudfront_domain);
        }
    }

    /**
     * Get URL for a tour file
     *
     * @param string $tour_name Tour name (may contain spaces or dashes)
     * @param string $file_path File path within tour (e.g., 'index.htm')
     * @return array Array of URLs to try (primary and fallback)
     */
    public function get_tour_urls($tour_name, $file_path = 'index.htm') {
        $urls = array();

        // Convert spaces to dashes for S3/CloudFront compatibility
        // S3 stores tours with dashes instead of spaces
        $tour_name_with_dashes = str_replace(' ', '-', $tour_name);

        if ($this->use_cloudfront) {
            // CloudFront URL - must include /tours/ in path since Lambda uploads to tours/ directory
            $urls[] = sprintf(
                'https://%s/tours/%s/%s',
                $this->cloudfront_domain,
                $tour_name_with_dashes,
                $file_path
            );

            // Try with spaces as fallback (URL encoded)
            if (strpos($tour_name, ' ') !== false) {
                $urls[] = sprintf(
                    'https://%s/tours/%s/%s',
                    $this->cloudfront_domain,
                    rawurlencode($tour_name),
                    $file_path
                );
            }
        } else {
            // S3 direct URL - needs /tours in path
            $urls[] = sprintf(
                'https://%s.s3.%s.amazonaws.com/tours/%s/%s',
                $this->s3_bucket,
                $this->s3_region,
                $tour_name_with_dashes,
                $file_path
            );

            // Try with spaces as fallback (URL encoded)
            if (strpos($tour_name, ' ') !== false) {
                $urls[] = sprintf(
                    'https://%s.s3.%s.amazonaws.com/tours/%s/%s',
                    $this->s3_bucket,
                    $this->s3_region,
                    rawurlencode($tour_name),
                    $file_path
                );
            }
        }

        return array_unique($urls);
    }

    /**
     * Get base URL for tour (directory URL)
     *
     * @param string $tour_name Tour name
     * @return string Tour base URL
     */
    public function get_tour_base_url($tour_name) {
        // Convert spaces to dashes for S3/CloudFront compatibility
        $tour_s3_name = str_replace(' ', '-', $tour_name);

        if ($this->use_cloudfront) {
            // CloudFront URL - must include /tours/ in path since Lambda uploads to tours/ directory
            return sprintf(
                'https://%s/tours/%s/',
                $this->cloudfront_domain,
                $tour_s3_name
            );
        } else {
            // S3 direct URL - needs /tours in path
            return sprintf(
                'https://%s.s3.%s.amazonaws.com/tours/%s/',
                $this->s3_bucket,
                $this->s3_region,
                $tour_s3_name
            );
        }
    }

    /**
     * Get S3 URL for write operations (upload, delete)
     * Always uses direct S3, never CloudFront
     *
     * @param string $s3_key S3 object key
     * @return string S3 URL
     */
    public function get_s3_write_url($s3_key) {
        return sprintf(
            'https://%s.s3.%s.amazonaws.com/%s',
            $this->s3_bucket,
            $this->s3_region,
            $s3_key
        );
    }

    /**
     * Check if CloudFront is enabled
     *
     * @return bool
     */
    public function is_cloudfront_enabled() {
        return $this->use_cloudfront;
    }

    /**
     * Get CloudFront distribution ID for cache invalidation
     *
     * @return string
     */
    public function get_distribution_id() {
        return get_option('h3tm_cloudfront_distribution_id', '');
    }

    /**
     * Invalidate CloudFront cache for a tour
     *
     * @param string $tour_name Tour name to invalidate
     * @return bool Success status
     */
    public function invalidate_tour_cache($tour_name) {
        if (!$this->use_cloudfront) {
            return true; // Nothing to invalidate
        }

        $distribution_id = $this->get_distribution_id();
        if (empty($distribution_id)) {
            error_log('H3TM CDN: Cannot invalidate cache - no distribution ID configured');
            return false;
        }

        // Build invalidation paths
        $tour_s3_name = str_replace(' ', '-', $tour_name);
        $paths = array(
            '/tours/' . $tour_s3_name . '/*',
            '/tours/' . rawurlencode($tour_name) . '/*'
        );

        // Use AWS CLI or SDK to invalidate
        // This would require AWS SDK integration
        // For now, log the requirement
        error_log('H3TM CDN: Cache invalidation needed for paths: ' . implode(', ', $paths));

        // Hook for external invalidation handling
        do_action('h3tm_cloudfront_invalidate', $distribution_id, $paths);

        return true;
    }

    /**
     * Get cache control headers for CloudFront
     *
     * @param string $file_type File extension
     * @return array Headers array
     */
    public function get_cache_headers($file_type = 'html') {
        $headers = array();

        // Different cache times for different file types
        $cache_times = array(
            'html' => 3600,      // 1 hour for HTML
            'htm' => 3600,       // 1 hour for HTM
            'js' => 86400,       // 24 hours for JS
            'css' => 86400,      // 24 hours for CSS
            'jpg' => 604800,     // 7 days for images
            'jpeg' => 604800,
            'png' => 604800,
            'gif' => 604800,
            'mp4' => 604800,     // 7 days for videos
            'webm' => 604800
        );

        $ext = strtolower(pathinfo($file_type, PATHINFO_EXTENSION));
        if (empty($ext)) {
            $ext = strtolower($file_type);
        }

        $max_age = isset($cache_times[$ext]) ? $cache_times[$ext] : 3600;

        // CloudFront-optimized headers
        if ($this->use_cloudfront) {
            $headers['Cache-Control'] = 'public, max-age=' . $max_age . ', s-maxage=' . $max_age;
            $headers['Vary'] = 'Accept-Encoding';
        } else {
            $headers['Cache-Control'] = 'public, max-age=' . $max_age;
        }

        $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', time() + $max_age);

        return $headers;
    }

    /**
     * Filter to replace S3 URLs with CloudFront URLs
     * Can be used as: add_filter('h3tm_s3_url', array($cdn_helper, 'filter_s3_url'));
     *
     * @param string $url Original S3 URL
     * @return string Modified URL (CloudFront or original)
     */
    public function filter_s3_url($url) {
        if (!$this->use_cloudfront) {
            return $url;
        }

        // Pattern to match S3 URLs
        $pattern = '#https?://([^.]+)\.s3\.([^.]+)\.amazonaws\.com/#';

        if (preg_match($pattern, $url, $matches)) {
            // Replace with CloudFront domain
            $cloudfront_url = 'https://' . $this->cloudfront_domain . '/';
            $url = preg_replace($pattern, $cloudfront_url, $url);
        }

        return $url;
    }

    /**
     * Reset instance (useful for testing)
     */
    public static function reset() {
        self::$instance = null;
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', function() {
    H3TM_CDN_Helper::get_instance();
});

// Add filter for URL replacement
add_filter('h3tm_s3_url', array(H3TM_CDN_Helper::get_instance(), 'filter_s3_url'));