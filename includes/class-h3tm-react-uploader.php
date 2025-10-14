<?php
/**
 * React Tour Uploader Integration
 *
 * Handles the enqueuing of React-based tour uploader component
 */

class H3TM_React_Uploader {

    /**
     * Initialize the React uploader integration
     */
    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_filter('script_loader_tag', array(__CLASS__, 'add_module_type'), 10, 3);
    }

    /**
     * Add type="module" to our React scripts
     */
    public static function add_module_type($tag, $handle, $src) {
        if ('h3tm-tour-uploader' === $handle) {
            $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
        }
        return $tag;
    }

    /**
     * Enqueue React uploader scripts and styles
     *
     * @param string $hook The current admin page hook
     */
    public static function enqueue_scripts($hook) {
        // Only load on our plugin's admin pages
        if (strpos($hook, 'h3-tour-management') === false) {
            return;
        }

        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        $dist_path = $plugin_dir . 'assets/dist/';
        $dist_url = $plugin_url . 'assets/dist/';

        // Check if built files exist
        if (!file_exists($dist_path . 'tour-uploader.js')) {
            // Fallback: show admin notice if build files don't exist
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>H3 Tour Management:</strong> React uploader assets not built. ';
                echo 'Run <code>cd frontend && npm install && npm run build</code> to build the uploader component.';
                echo '</p></div>';
            });
            return;
        }

        // Enqueue the compiled CSS (shared between components)
        wp_enqueue_style(
            'h3tm-react-styles',
            $dist_url . 'index.css',
            array(),
            filemtime($dist_path . 'index.css')
        );

        // Enqueue the compiled JavaScript
        wp_enqueue_script(
            'h3tm-tour-uploader',
            $dist_url . 'tour-uploader.js',
            array(), // Dependencies handled by Vite bundler
            filemtime($dist_path . 'tour-uploader.js'),
            true // Load in footer
        );

        // Pass data to JavaScript
        wp_localize_script('h3tm-tour-uploader', 'h3tmData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('h3tm_ajax_nonce'),
            'maxFileSize' => wp_max_upload_size(),
            'uploadDir' => wp_upload_dir()['baseurl'] . '/h3-tours/',
        ));
    }

    /**
     * Render the React uploader container
     *
     * This should be called in the admin page where you want the uploader to appear
     */
    public static function render_uploader() {
        // Debug: Check if built files exist
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $dist_path = $plugin_dir . 'assets/dist/';
        $has_js = file_exists($dist_path . 'tour-uploader.js');
        $has_css = file_exists($dist_path . 'index.css');

        // Debug output (only visible in HTML source)
        echo '<!-- React Uploader: JS=' . ($has_js ? 'exists' : 'missing') . ', CSS=' . ($has_css ? 'exists' : 'missing') . ' -->';
        echo '<div id="h3tm-tour-uploader-root" data-debug-render="true"></div>';
        echo '<script>console.log("Uploader container rendered");</script>';
    }

    /**
     * Alternative: Use WordPress's built-in React (Gutenberg)
     *
     * Uncomment and use this method if you want to use WP's React instead of bundling your own
     */
    /*
    public static function enqueue_scripts_wp_react($hook) {
        if (strpos($hook, 'h3-tour-management') === false) {
            return;
        }

        // Use WordPress's built-in React and ReactDOM
        wp_enqueue_script('wp-element');
        wp_enqueue_script('wp-components');

        $plugin_url = plugin_dir_url(dirname(__FILE__));

        // Your custom script that uses wp.element (WordPress's React wrapper)
        wp_enqueue_script(
            'h3tm-tour-uploader',
            $plugin_url . 'assets/dist/tour-uploader.js',
            array('wp-element', 'wp-components'),
            '1.0.0',
            true
        );

        wp_localize_script('h3tm-tour-uploader', 'h3tmData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('h3tm_upload_tour'),
        ));
    }
    */
}

// Initialize the React uploader
H3TM_React_Uploader::init();
