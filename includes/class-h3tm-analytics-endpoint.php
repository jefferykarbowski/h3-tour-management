<?php
/**
 * Analytics Script Endpoint
 * Serves dynamic analytics JavaScript for S3-hosted tours
 */
class H3TM_Analytics_Endpoint {

    public function __construct() {
        // Add rewrite rule for analytics script
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'serve_analytics_script'));
    }

    /**
     * Add rewrite rule for /h3-analytics.js
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^h3-analytics\.js$',
            'index.php?h3tm_analytics=1',
            'top'
        );

        add_filter('query_vars', function($vars) {
            $vars[] = 'h3tm_analytics';
            return $vars;
        });
    }

    /**
     * Serve dynamic analytics JavaScript
     */
    public function serve_analytics_script() {
        if (!get_query_var('h3tm_analytics')) {
            return;
        }

        // Get GA settings
        $ga_measurement_id = get_option('h3tm_ga_measurement_id', 'G-08Q1M637NJ');
        $analytics_enabled = get_option('h3tm_analytics_enabled', '1');

        // Set headers for JavaScript
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
        header('X-Content-Type-Options: nosniff');

        // Output the dynamic analytics script
        ?>
// H3 Tour Analytics Script
// Dynamically loaded from WordPress for all S3-hosted tours
(function() {
    'use strict';

    // Get tour name from script tag data attribute
    const scriptTag = document.querySelector('script[data-tour-name]');
    const tourName = scriptTag ? scriptTag.getAttribute('data-tour-name') : 'Unknown Tour';

    console.log('H3 Analytics: Loading for tour:', tourName);

    <?php if ($analytics_enabled === '1' && !empty($ga_measurement_id)): ?>
    // Load Google Analytics 4
    (function() {
        const script = document.createElement('script');
        script.src = 'https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js($ga_measurement_id); ?>';
        script.async = true;
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', '<?php echo esc_js($ga_measurement_id); ?>', {
            'page_title': tourName,
            'page_path': '/h3panos/' + tourName + '/',
            'custom_map': {'dimension1': 'tour_name'}
        });

        // Track tour view event
        gtag('event', 'tour_view', {
            'tour_name': tourName,
            'event_category': 'Tour Engagement',
            'event_label': tourName
        });

        console.log('H3 Analytics: Google Analytics loaded for tour:', tourName);
    })();

    // Track tour engagement
    document.addEventListener('DOMContentLoaded', function() {
        // Track time spent on tour
        const startTime = Date.now();

        window.addEventListener('beforeunload', function() {
            const timeSpent = Math.round((Date.now() - startTime) / 1000);
            if (typeof gtag !== 'undefined') {
                gtag('event', 'timing_complete', {
                    'name': 'tour_session',
                    'value': timeSpent,
                    'event_category': 'Tour Engagement',
                    'event_label': tourName
                });
            }
        });

        console.log('H3 Analytics: Time tracking initialized');
    });
    <?php else: ?>
    console.log('H3 Analytics: Analytics disabled in WordPress settings');
    <?php endif; ?>

})();
<?php
        exit();
    }
}