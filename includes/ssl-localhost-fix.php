<?php
/**
 * SSL Certificate Fix for Local Development
 * Add this to your wp-config.php or at the start of the plugin
 */

// Only for local development - DO NOT use in production!
if (isset($_SERVER['HTTP_HOST']) && (
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '.local') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
)) {
    // Disable SSL verification for Google API Client
    add_action('init', function() {
        if (class_exists('Google_Client')) {
            // This will be applied to all Google_Client instances
            Google_Client::$httpHandler = function($request, $options = []) {
                $options['verify'] = false;
                $httpHandler = \GuzzleHttp\HandlerStack::create();
                $client = new \GuzzleHttp\Client(['handler' => $httpHandler]);
                return $client->send($request, $options);
            };
        }
    }, 1);
    
    // Alternative: Set stream context options
    stream_context_set_default([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
}
