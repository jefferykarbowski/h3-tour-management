<?php
/**
 * Temporary file to clear all caches
 * Delete this file after running once
 */

// Clear OPcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared!\n";
}

// Clear WordPress object cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    echo "WordPress cache cleared!\n";
}

echo "Cache clearing complete. Delete this file now.\n";
