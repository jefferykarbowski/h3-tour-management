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
     * Check for tour redirects on init
     * Runs before template_redirect for early detection
     */
    public function check_tour_redirect() {
        // Only process tour URLs
        $request_uri = $_SERVER['REQUEST_URI'];

        if (strpos($request_uri, '/h3panos/') === false) {
            return;
        }

        // Extract potential tour slug from URL
        if (preg_match('#^/h3panos/([^/]+)#', $request_uri, $matches)) {
            $requested_slug = sanitize_title($matches[1]);

            // Check if this slug needs to be redirected
            $tour = $this->metadata->find_by_old_slug($requested_slug);

            if ($tour) {
                // Found in url_history, redirect to current slug
                $current_path = str_replace('/h3panos/' . $requested_slug, '/h3panos/' . $tour->tour_slug, $request_uri);

                wp_redirect($current_path, 301);
                exit;
            }
        }
    }

    /**
     * Handle tour request from rewrite rules
     */
    public function handle_tour_request() {
        $tour_slug = get_query_var('tour_slug');
        $tour_path = get_query_var('tour_path');

        if (!$tour_slug) {
            return;
        }

        // Get tour metadata
        $tour = $this->metadata->get_by_slug($tour_slug);

        if (!$tour) {
            // Tour not found, try old slug
            $tour = $this->metadata->find_by_old_slug($tour_slug);

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

        // Tour found - let normal processing continue
        // The actual tour files are served from S3/CloudFront
    }

    /**
     * Get current tour URL from metadata
     *
     * @param string $tour_name_or_slug Tour name or slug
     * @return string|null Current tour URL or null if not found
     */
    public function get_tour_url($tour_name_or_slug) {
        // Try as slug first
        $tour = $this->metadata->get_by_slug($tour_name_or_slug);

        // Try as display name
        if (!$tour) {
            $tour = $this->metadata->get_by_display_name($tour_name_or_slug);
        }

        // Try as old slug
        if (!$tour) {
            $tour = $this->metadata->find_by_old_slug($tour_name_or_slug);
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
