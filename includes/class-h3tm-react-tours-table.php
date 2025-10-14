<?php
/**
 * React Tours Table Integration
 *
 * Handles the enqueuing of React-based tours table component
 */

class H3TM_React_Tours_Table {

    /**
     * Initialize the React tours table integration
     */
    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_filter('script_loader_tag', array(__CLASS__, 'add_module_type'), 10, 3);
    }

    /**
     * Add type="module" to our React scripts
     */
    public static function add_module_type($tag, $handle, $src) {
        if ('h3tm-tours-table' === $handle) {
            $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
        }
        return $tag;
    }

    /**
     * Enqueue React tours table scripts and styles
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
        if (!file_exists($dist_path . 'tours-table.js')) {
            // Fallback: show admin notice if build files don't exist
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>H3 Tour Management:</strong> React tours table assets not built. ';
                echo 'Run <code>cd frontend && npm install && npm run build</code> to build the component.';
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
            'h3tm-tours-table',
            $dist_url . 'tours-table.js',
            array(), // Dependencies handled by Vite bundler
            filemtime($dist_path . 'tours-table.js'),
            true // Load in footer
        );

        // Pass data to JavaScript
        wp_localize_script('h3tm-tours-table', 'h3tm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('h3tm_ajax_nonce'),
        ));
    }

    /**
     * Render the React tours table container
     *
     * This should be called in the admin page where you want the tours table to appear
     */
    public static function render_table() {
        // Debug: Check if built files exist
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $dist_path = $plugin_dir . 'assets/dist/';
        $has_js = file_exists($dist_path . 'tours-table.js');
        $has_css = file_exists($dist_path . 'index.css');

        // Debug output (only visible in HTML source)
        echo '<!-- React Tours Table: JS=' . ($has_js ? 'exists' : 'missing') . ', CSS=' . ($has_css ? 'exists' : 'missing') . ' -->';
        echo '<div id="h3tm-tours-table-root" data-component="tours-table"></div>';
        echo '<script>console.log("Tours table container rendered");</script>';
    }
}

// Initialize the React tours table
H3TM_React_Tours_Table::init();
