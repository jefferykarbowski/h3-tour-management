<?php
/**
 * URL Redirector Class
 * Handles 301 redirects for renamed tours using url_history
 */
class H3TM_URL_Redirector {

    private $metadata;

    public function __construct() {
        $this->metadata = new H3TM_Tour_Metadata();

        // Hook into WordPress init to check for redirects
        add_action('init', array($this, 'check_tour_redirect'), 1);

        // Add rewrite rules for tour URLs
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_tour_request'));
    }

    /**
     * Add rewrite rules for tour URLs
     */
    public function add_rewrite_rules() {
        // Match /h3panos/{tour-slug}/
        add_rewrite_rule(
            '^h3panos/([^/]+)/?$',
            'index.php?tour_slug=$matches[1]',
            'top'
        );

        // Match /h3panos/{tour-slug}/{path}
        add_rewrite_rule(
            '^h3panos/([^/]+)/(.+)$',
            'index.php?tour_slug=$matches[1]&tour_path=$matches[2]',
            'top'
        );
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'tour_slug';
        $vars[] = 'tour_path';
        return $vars;
    }

    /**
     * Check if a string matches tour_id pattern
     * Format: 20250114_173045_8k3j9d2m (timestamp + 8-char random)
     *
     * @param string $str String to check
     * @return bool True if matches tour_id pattern
     */
    private function is_tour_id($str) {
        return preg_match('/^\d{8}_\d{6}_[a-z0-9]{8}$/', $str) === 1;
    }

    /**
     * Check for tour redirects on init
     * Runs before template_redirect for early detection
     * Supports both tour_id and slug-based URLs
     */
    public function check_tour_redirect() {
        // Only process tour URLs
        $request_uri = $_SERVER['REQUEST_URI'];

        if (strpos($request_uri, '/h3panos/') === false) {
            return;
        }

        // Extract potential tour identifier from URL
        if (preg_match('#^/h3panos/([^/]+)#', $request_uri, $matches)) {
            $identifier = $matches[1];

            // Check if this is a tour_id (no redirect needed for IDs)
            if ($this->is_tour_id($identifier)) {
                return; // Tour IDs are immutable, no redirect needed
            }

            // It's a slug - check if it needs to be redirected
            // URL decode the identifier for comparison (spaces come as %20)
            $decoded_identifier = urldecode($identifier);
            $requested_slug = sanitize_title($decoded_identifier);

            // Check if URL needs normalization (has spaces, capitals, etc.)
            // Compare decoded identifier with canonical slug format
            if ($decoded_identifier !== $requested_slug) {
                // Check if the canonical slug exists as a current tour
                $tour = $this->metadata->get_by_slug($requested_slug);

                if ($tour) {
                    // URL is non-canonical (spaces/capitals), redirect to canonical form
                    $canonical_path = '/h3panos/' . $tour->tour_slug;
                    if (preg_match('#^/h3panos/[^/]+/(.+)$#', $request_uri, $path_matches)) {
                        $canonical_path .= '/' . $path_matches[1];
                    }

                    wp_redirect($canonical_path, 301);
                    exit;
                }
            }

            // Check if this slug needs to be redirected from old slug
            $tour = $this->metadata->find_by_old_slug($requested_slug);

            if ($tour) {
                // Found in url_history, redirect to current slug
                $canonical_path = '/h3panos/' . $tour->tour_slug;
                if (preg_match('#^/h3panos/[^/]+/(.+)$#', $request_uri, $path_matches)) {
                    $canonical_path .= '/' . $path_matches[1];
                }

                wp_redirect($canonical_path, 301);
                exit;
            }
        }
    }

    /**
     * Handle tour request from rewrite rules
     * Supports both tour_id and slug-based URLs (dual-mode)
     */
    public function handle_tour_request() {
        $tour_identifier = get_query_var('tour_slug');
        $tour_path = get_query_var('tour_path');

        if (!$tour_identifier) {
            return;
        }

        $tour = null;

        // Check if this is a tour_id (immutable identifier)
        if ($this->is_tour_id($tour_identifier)) {
            // Direct lookup by tour_id
            $tour = $this->metadata->get_by_tour_id($tour_identifier);

            if (!$tour) {
                // Tour ID doesn't exist
                return;
            }

            // Tour found by ID - let normal processing continue
            return;
        }

        // It's a slug - normalize and try to find tour
        // Normalize the identifier (lowercase, hyphens instead of spaces)
        $normalized_slug = sanitize_title(urldecode($tour_identifier));
        $tour = $this->metadata->get_by_slug($normalized_slug);

        if (!$tour) {
            // Tour not found by slug, try old slug
            $tour = $this->metadata->find_by_old_slug($normalized_slug);

            if ($tour) {
                // Redirect to current slug
                $redirect_url = '/h3panos/' . $tour->tour_slug;
                if ($tour_path) {
                    $redirect_url .= '/' . $tour_path;
                }

                wp_redirect($redirect_url, 301);
                exit;
            }

            // Tour doesn't exist
            return;
        }

        // Tour found by slug - let normal processing continue
        // The actual tour files are served from S3/CloudFront
    }

    /**
     * Get current tour URL from metadata
     * Supports tour_id, slug, display name, and old slugs
     *
     * @param string $identifier Tour ID, name, or slug
     * @return string|null Current tour URL or null if not found
     */
    public function get_tour_url($identifier) {
        $tour = null;

        // Try as tour_id first (most specific)
        if ($this->is_tour_id($identifier)) {
            $tour = $this->metadata->get_by_tour_id($identifier);
        }

        // Try as slug
        if (!$tour) {
            $tour = $this->metadata->get_by_slug($identifier);
        }

        // Try as display name
        if (!$tour) {
            $tour = $this->metadata->get_by_display_name($identifier);
        }

        // Try as old slug
        if (!$tour) {
            $tour = $this->metadata->find_by_old_slug($identifier);
        }

        if ($tour) {
            return home_url('/h3panos/' . $tour->tour_slug . '/');
        }

        return null;
    }

    /**
     * Check if a slug has been used before (in url_history)
     *
     * @param string $slug Slug to check
     * @return bool True if slug has been used
     */
    public function is_slug_historical($slug) {
        $tour = $this->metadata->find_by_old_slug($slug);
        return $tour !== null;
    }

    /**
     * Get URL history for a tour
     *
     * @param string $tour_slug Current tour slug
     * @return array Array of old slugs
     */
    public function get_url_history($tour_slug) {
        $tour = $this->metadata->get_by_slug($tour_slug);

        if (!$tour) {
            return array();
        }

        $url_history = json_decode($tour->url_history, true);

        return is_array($url_history) ? $url_history : array();
    }
}
