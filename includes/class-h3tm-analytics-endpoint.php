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
     * Add rewrite rules for analytics scripts
     */
    public function add_rewrite_rules() {
        // Legacy endpoint for backward compatibility
        add_rewrite_rule(
            '^h3-analytics\.js$',
            'index.php?h3tm_analytics=1',
            'top'
        );

        // New endpoint for Lambda-injected tours
        add_rewrite_rule(
            '^h3-tour-analytics\.js$',
            'index.php?h3tm_tour_analytics=1',
            'top'
        );

        add_filter('query_vars', function($vars) {
            $vars[] = 'h3tm_analytics';
            $vars[] = 'h3tm_tour_analytics';
            return $vars;
        });
    }

    /**
     * Serve dynamic analytics JavaScript
     */
    public function serve_analytics_script() {
        // Handle new tour analytics endpoint
        if (get_query_var('h3tm_tour_analytics')) {
            $this->serve_tour_analytics_script();
            return;
        }

        // Handle legacy endpoint
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

    /**
     * Serve tour-specific analytics script for Lambda-processed tours
     */
    private function serve_tour_analytics_script() {
        // Get parameters from URL
        $tour_name = isset($_GET['tour']) ? sanitize_text_field($_GET['tour']) : 'Unknown Tour';
        $tour_folder = isset($_GET['folder']) ? sanitize_text_field($_GET['folder']) : '';
        $page_title = isset($_GET['title']) ? sanitize_text_field($_GET['title']) : $tour_name;
        $page_path = isset($_GET['path']) ? sanitize_text_field($_GET['path']) : '/tours/' . $tour_folder . '/';

        // Get GA settings
        $ga_measurement_id = get_option('h3tm_ga_measurement_id', 'G-6P29YLK8Q9');
        $analytics_enabled = get_option('h3tm_analytics_enabled', '1');

        // Set headers for JavaScript
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
        header('X-Content-Type-Options: nosniff');
        header('Access-Control-Allow-Origin: *'); // Allow cross-origin for S3

        // Output the analytics script
        ?>
// H3 Tour Analytics Script v2.0
// Centrally managed analytics for S3-hosted tours
(function() {
    'use strict';

    // Tour information from URL parameters
    const tourName = <?php echo json_encode($tour_name); ?>;
    const tourFolder = <?php echo json_encode($tour_folder); ?>;
    const pageTitle = <?php echo json_encode($page_title); ?>;
    const pagePath = <?php echo json_encode($page_path); ?>;

    console.log('H3 Tour Analytics: Initializing for', tourName);
    console.log('Page Title:', pageTitle);
    console.log('Page Path:', pagePath);

    <?php if ($analytics_enabled === '1' && !empty($ga_measurement_id)): ?>
    // Load Google Analytics 4
    (function() {
        // Check if GA is already loaded
        if (window.h3AnalyticsLoaded) {
            console.log('H3 Analytics: Already loaded, skipping');
            return;
        }
        window.h3AnalyticsLoaded = true;

        // Load GA script
        const script = document.createElement('script');
        script.src = 'https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js($ga_measurement_id); ?>';
        script.async = true;
        document.head.appendChild(script);

        script.onload = function() {
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            window.gtag = gtag;

            gtag('js', new Date());

            // Configure GA4 with tour information
            gtag('config', '<?php echo esc_js($ga_measurement_id); ?>', {
                'page_title': pageTitle,
                'page_path': pagePath,
                'custom_map': {
                    'dimension1': 'tour_name',
                    'dimension2': 'tour_folder',
                    'dimension3': 'page_title'
                },
                'tour_name': tourName,
                'tour_folder': tourFolder,
                'original_title': pageTitle
            });

            // Track tour view event
            gtag('event', 'tour_view', {
                'tour_name': tourName,
                'tour_folder': tourFolder,
                'event_category': 'Tour Engagement',
                'event_label': tourName,
                'source': 's3_hosted'
            });

            console.log('H3 Analytics: Google Analytics 4 configured for', tourName);
        };
    })();

    // Enhanced engagement tracking
    document.addEventListener('DOMContentLoaded', function() {
        const startTime = Date.now();
        let interactionCount = 0;

        // Track clicks within the tour
        document.addEventListener('click', function(e) {
            interactionCount++;

            // Track specific interactions
            if (window.gtag) {
                const target = e.target.closest('a, button, [role="button"]');
                if (target) {
                    gtag('event', 'tour_interaction', {
                        'tour_name': tourName,
                        'interaction_type': 'click',
                        'interaction_target': target.tagName.toLowerCase(),
                        'event_category': 'Tour Engagement'
                    });
                }
            }
        });

        // Track time spent and interactions on unload
        window.addEventListener('beforeunload', function() {
            const timeSpent = Math.round((Date.now() - startTime) / 1000);

            if (window.gtag) {
                // Send timing event
                gtag('event', 'timing_complete', {
                    'name': 'tour_session_duration',
                    'value': timeSpent,
                    'event_category': 'Tour Engagement',
                    'event_label': tourName
                });

                // Send interaction count
                gtag('event', 'tour_engagement', {
                    'tour_name': tourName,
                    'time_spent_seconds': timeSpent,
                    'interaction_count': interactionCount,
                    'event_category': 'Tour Engagement'
                });
            }
        });

        console.log('H3 Analytics: Engagement tracking initialized');
    });

    // Track if tour fails to load properly
    window.addEventListener('error', function(e) {
        if (window.gtag) {
            gtag('event', 'exception', {
                'description': 'Tour loading error: ' + e.message,
                'fatal': false,
                'tour_name': tourName
            });
        }
    });

    <?php else: ?>
    console.log('H3 Analytics: Analytics disabled or not configured');
    <?php endif; ?>

})();

// Expose tour info globally for debugging
window.h3TourInfo = {
    name: <?php echo json_encode($tour_name); ?>,
    folder: <?php echo json_encode($tour_folder); ?>,
    title: <?php echo json_encode($page_title); ?>,
    path: <?php echo json_encode($page_path); ?>,
    analyticsVersion: '2.0',
    gaEnabled: <?php echo json_encode($analytics_enabled === '1'); ?>,
    gaMeasurementId: <?php echo json_encode($analytics_enabled === '1' ? $ga_measurement_id : null); ?>
};
<?php
        exit();
    }
}