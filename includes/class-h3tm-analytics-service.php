<?php
/**
 * Analytics Service for H3 Tour Management
 * 
 * Handles Google Analytics integration with caching and improved error handling
 * 
 * @package H3_Tour_Management
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Analytics_Service {
    
    /**
     * Google Analytics client instance
     * 
     * @var Google_Client
     */
    private static $client;
    
    /**
     * Analytics Data service instance
     * 
     * @var Google_Service_AnalyticsData
     */
    private static $analytics_service;
    
    /**
     * Cache group for analytics data
     */
    const CACHE_GROUP = 'h3tm_analytics';
    
    /**
     * Cache expiration times (in seconds)
     */
    const CACHE_SHORT = 3600;      // 1 hour
    const CACHE_MEDIUM = 21600;    // 6 hours
    const CACHE_LONG = 86400;      // 24 hours
    
    /**
     * GA4 Property ID
     */
    const PROPERTY_ID = 'properties/491286260';
    
    /**
     * Initialize Analytics Service
     * 
     * @throws Exception If initialization fails
     */
    public static function init() {
        if (self::$analytics_service !== null) {
            return self::$analytics_service;
        }
        
        try {
            // Check for required dependencies
            $vendor_path = ABSPATH . 'vendor/autoload.php';
            if (!file_exists($vendor_path)) {
                throw new Exception('Google API client library not found. Please install via Composer.');
            }
            
            require_once $vendor_path;
            
            // Initialize Google Client
            self::$client = self::create_client();
            
            // Initialize Analytics Data service
            self::$analytics_service = new Google_Service_AnalyticsData(self::$client);
            
            return self::$analytics_service;
            
        } catch (Exception $e) {
            H3TM_Logger::error('analytics', 'Failed to initialize Analytics Service', array(
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }
    
    /**
     * Create and configure Google Client
     * 
     * @return Google_Client
     * @throws Exception
     */
    private static function create_client() {
        $credentials_path = ABSPATH . 'service-account-credentials.json';
        
        if (!file_exists($credentials_path)) {
            throw new Exception('Service account credentials file not found.');
        }
        
        $client = new Google_Client();
        $client->setApplicationName('H3VT Analytics Reporting');
        $client->setAuthConfig($credentials_path);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        
        // Set additional options for better performance
        $client->setHttpClient(new GuzzleHttp\Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => true
        ]));
        
        return $client;
    }
    
    /**
     * Get analytics report for a tour
     * 
     * @param string $tour_title Tour title
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date End date (Y-m-d format)
     * @param bool $use_cache Whether to use cached data
     * @return array Analytics data
     */
    public static function get_tour_analytics($tour_title, $start_date, $end_date = 'today', $use_cache = true) {
        // Generate cache key
        $cache_key = md5("tour_analytics_{$tour_title}_{$start_date}_{$end_date}");
        
        // Check cache if enabled
        if ($use_cache) {
            $cached_data = wp_cache_get($cache_key, self::CACHE_GROUP);
            if ($cached_data !== false) {
                H3TM_Logger::debug('analytics', 'Using cached analytics data', array(
                    'tour' => $tour_title,
                    'cache_key' => $cache_key
                ));
                return $cached_data;
            }
        }
        
        try {
            // Initialize service if needed
            if (self::$analytics_service === null) {
                self::init();
            }
            
            // Build the report request
            $request = self::build_report_request($tour_title, $start_date, $end_date);
            
            // Execute the request
            $response = self::$analytics_service->properties->runReport(self::PROPERTY_ID, $request);
            
            // Process the response
            $data = self::process_report_response($response);
            
            // Cache the results
            $cache_duration = self::get_cache_duration($start_date, $end_date);
            wp_cache_set($cache_key, $data, self::CACHE_GROUP, $cache_duration);
            
            H3TM_Logger::info('analytics', 'Analytics data retrieved successfully', array(
                'tour' => $tour_title,
                'period' => "{$start_date} to {$end_date}"
            ));
            
            return $data;
            
        } catch (Exception $e) {
            H3TM_Logger::error('analytics', 'Failed to get analytics data', array(
                'tour' => $tour_title,
                'error' => $e->getMessage()
            ));
            
            // Return empty data structure on error
            return array(
                'total_events' => 0,
                'total_sessions' => 0,
                'total_users' => 0,
                'avg_session_duration' => 0,
                'images_per_visitor' => 0,
                'error' => true,
                'error_message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Build analytics report request
     * 
     * @param string $tour_title Tour title
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return Google_Service_AnalyticsData_RunReportRequest
     */
    private static function build_report_request($tour_title, $start_date, $end_date) {
        $request = new Google_Service_AnalyticsData_RunReportRequest();
        
        // Set date range
        $dateRange = new Google_Service_AnalyticsData_DateRange();
        $dateRange->setStartDate($start_date);
        $dateRange->setEndDate($end_date);
        $request->setDateRanges([$dateRange]);
        
        // Set metrics
        $metrics = array(
            'eventCount',
            'sessions',
            'totalUsers',
            'averageSessionDuration'
        );
        
        $metric_objects = array();
        foreach ($metrics as $metric_name) {
            $metric = new Google_Service_AnalyticsData_Metric();
            $metric->setName($metric_name);
            $metric_objects[] = $metric;
        }
        $request->setMetrics($metric_objects);
        
        // Set dimension filter for tour title
        $filter = new Google_Service_AnalyticsData_Filter();
        $stringFilter = new Google_Service_AnalyticsData_StringFilter();
        $stringFilter->setMatchType('EXACT');
        $stringFilter->setValue($tour_title);
        $filter->setStringFilter($stringFilter);
        $filter->setFieldName('pageTitle');
        
        $filterExpression = new Google_Service_AnalyticsData_FilterExpression();
        $filterExpression->setFilter($filter);
        $request->setDimensionFilter($filterExpression);
        
        // Set property
        $request->setProperty(self::PROPERTY_ID);
        
        return $request;
    }
    
    /**
     * Process analytics report response
     * 
     * @param Google_Service_AnalyticsData_RunReportResponse $response
     * @return array Processed data
     */
    private static function process_report_response($response) {
        $data = array(
            'total_events' => 0,
            'total_sessions' => 0,
            'total_users' => 0,
            'avg_session_duration' => 0,
            'images_per_visitor' => 0
        );
        
        $rows = $response->getRows();
        
        if (empty($rows)) {
            return $data;
        }
        
        // Get the first (and should be only) row
        $row = $rows[0];
        $metric_values = $row->getMetricValues();
        
        if (count($metric_values) >= 4) {
            $data['total_events'] = intval($metric_values[0]->getValue());
            $data['total_sessions'] = intval($metric_values[1]->getValue());
            $data['total_users'] = intval($metric_values[2]->getValue());
            $data['avg_session_duration'] = round($metric_values[3]->getValue(), 1);
            
            // Calculate images per visitor
            if ($data['total_users'] > 0) {
                $data['images_per_visitor'] = round($data['total_events'] / $data['total_users'], 1);
            }
        }
        
        return $data;
    }
    
    /**
     * Get referral sources for a tour
     * 
     * @param string $tour_title Tour title
     * @param string $start_date Start date
     * @param int $limit Maximum number of results
     * @return array Referral data
     */
    public static function get_referral_sources($tour_title, $start_date, $limit = 10) {
        $cache_key = md5("referrals_{$tour_title}_{$start_date}_{$limit}");
        
        // Check cache
        $cached_data = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        try {
            if (self::$analytics_service === null) {
                self::init();
            }
            
            $request = new Google_Service_AnalyticsData_RunReportRequest();
            
            // Date range
            $dateRange = new Google_Service_AnalyticsData_DateRange();
            $dateRange->setStartDate($start_date);
            $dateRange->setEndDate('today');
            $request->setDateRanges([$dateRange]);
            
            // Metrics
            $users = new Google_Service_AnalyticsData_Metric();
            $users->setName('totalUsers');
            $request->setMetrics([$users]);
            
            // Dimensions
            $referrer = new Google_Service_AnalyticsData_Dimension();
            $referrer->setName('pageReferrer');
            $request->setDimensions([$referrer]);
            
            // Filter
            $filter = new Google_Service_AnalyticsData_Filter();
            $stringFilter = new Google_Service_AnalyticsData_StringFilter();
            $stringFilter->setMatchType('EXACT');
            $stringFilter->setValue($tour_title);
            $filter->setStringFilter($stringFilter);
            $filter->setFieldName('pageTitle');
            
            $filterExpression = new Google_Service_AnalyticsData_FilterExpression();
            $filterExpression->setFilter($filter);
            $request->setDimensionFilter($filterExpression);
            
            // Ordering
            $ordering = new Google_Service_AnalyticsData_OrderBy();
            $metricOrdering = new Google_Service_AnalyticsData_MetricOrderBy();
            $metricOrdering->setMetricName('totalUsers');
            $ordering->setMetric($metricOrdering);
            $ordering->setDesc(true);
            $request->setOrderBys([$ordering]);
            
            // Limit
            $request->setLimit($limit);
            
            // Property
            $request->setProperty(self::PROPERTY_ID);
            
            // Execute request
            $response = self::$analytics_service->properties->runReport(self::PROPERTY_ID, $request);
            
            // Process response
            $referrals = array();
            $rows = $response->getRows();
            
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $dimension_values = $row->getDimensionValues();
                    $metric_values = $row->getMetricValues();
                    
                    if (count($dimension_values) > 0 && count($metric_values) > 0) {
                        $referrer = $dimension_values[0]->getValue();
                        $users = intval($metric_values[0]->getValue());
                        
                        // Clean up referrer URL
                        $referrer_clean = self::clean_referrer_url($referrer);
                        
                        $referrals[] = array(
                            'referrer' => $referrer_clean,
                            'users' => $users
                        );
                    }
                }
            }
            
            // Cache results
            wp_cache_set($cache_key, $referrals, self::CACHE_GROUP, self::CACHE_MEDIUM);
            
            return $referrals;
            
        } catch (Exception $e) {
            H3TM_Logger::error('analytics', 'Failed to get referral data', array(
                'tour' => $tour_title,
                'error' => $e->getMessage()
            ));
            
            return array();
        }
    }
    
    /**
     * Get new vs returning users for a tour
     * 
     * @param string $tour_title Tour title
     * @param string $start_date Start date
     * @return array User type data
     */
    public static function get_user_types($tour_title, $start_date) {
        $cache_key = md5("user_types_{$tour_title}_{$start_date}");
        
        // Check cache
        $cached_data = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        try {
            if (self::$analytics_service === null) {
                self::init();
            }
            
            $request = new Google_Service_AnalyticsData_RunReportRequest();
            
            // Date range
            $dateRange = new Google_Service_AnalyticsData_DateRange();
            $dateRange->setStartDate($start_date);
            $dateRange->setEndDate('today');
            $request->setDateRanges([$dateRange]);
            
            // Metrics
            $users = new Google_Service_AnalyticsData_Metric();
            $users->setName('totalUsers');
            $request->setMetrics([$users]);
            
            // Dimensions
            $userType = new Google_Service_AnalyticsData_Dimension();
            $userType->setName('newVsReturning');
            $request->setDimensions([$userType]);
            
            // Filter
            $filter = new Google_Service_AnalyticsData_Filter();
            $stringFilter = new Google_Service_AnalyticsData_StringFilter();
            $stringFilter->setMatchType('EXACT');
            $stringFilter->setValue($tour_title);
            $filter->setStringFilter($stringFilter);
            $filter->setFieldName('pageTitle');
            
            $filterExpression = new Google_Service_AnalyticsData_FilterExpression();
            $filterExpression->setFilter($filter);
            $request->setDimensionFilter($filterExpression);
            
            // Property
            $request->setProperty(self::PROPERTY_ID);
            
            // Execute request
            $response = self::$analytics_service->properties->runReport(self::PROPERTY_ID, $request);
            
            // Process response
            $user_types = array(
                'new' => 0,
                'returning' => 0
            );
            
            $rows = $response->getRows();
            
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $dimension_values = $row->getDimensionValues();
                    $metric_values = $row->getMetricValues();
                    
                    if (count($dimension_values) > 0 && count($metric_values) > 0) {
                        $type = strtolower($dimension_values[0]->getValue());
                        $count = intval($metric_values[0]->getValue());
                        
                        if ($type === 'new') {
                            $user_types['new'] = $count;
                        } else {
                            $user_types['returning'] = $count;
                        }
                    }
                }
            }
            
            // Cache results
            wp_cache_set($cache_key, $user_types, self::CACHE_GROUP, self::CACHE_MEDIUM);
            
            return $user_types;
            
        } catch (Exception $e) {
            H3TM_Logger::error('analytics', 'Failed to get user type data', array(
                'tour' => $tour_title,
                'error' => $e->getMessage()
            ));
            
            return array('new' => 0, 'returning' => 0);
        }
    }
    
    /**
     * Clear analytics cache
     * 
     * @param string $tour_title Specific tour to clear (optional)
     */
    public static function clear_cache($tour_title = null) {
        if ($tour_title) {
            // Clear cache for specific tour
            $patterns = array(
                "tour_analytics_{$tour_title}_",
                "referrals_{$tour_title}_",
                "user_types_{$tour_title}_"
            );
            
            foreach ($patterns as $pattern) {
                // Note: This is a simplified approach. In production, you might want to
                // implement a more sophisticated cache clearing mechanism
                wp_cache_delete($pattern, self::CACHE_GROUP);
            }
        } else {
            // Clear all analytics cache
            wp_cache_flush();
        }
        
        H3TM_Logger::info('analytics', 'Analytics cache cleared', array(
            'tour' => $tour_title ?: 'all'
        ));
    }
    
    /**
     * Get appropriate cache duration based on date range
     * 
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return int Cache duration in seconds
     */
    private static function get_cache_duration($start_date, $end_date) {
        $start = strtotime($start_date);
        $end = ($end_date === 'today') ? time() : strtotime($end_date);
        $days_diff = ($end - $start) / 86400;
        
        if ($days_diff <= 7) {
            return self::CACHE_SHORT;
        } elseif ($days_diff <= 30) {
            return self::CACHE_MEDIUM;
        } else {
            return self::CACHE_LONG;
        }
    }
    
    /**
     * Clean referrer URL for display
     * 
     * @param string $url Referrer URL
     * @return string Cleaned URL
     */
    private static function clean_referrer_url($url) {
        if (empty($url) || $url === '(direct)') {
            return '(direct)';
        }
        
        // Parse URL and get host
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            return $parsed['host'];
        }
        
        return $url;
    }
    
    /**
     * Test analytics connection
     * 
     * @return array Test results
     */
    public static function test_connection() {
        $results = array(
            'success' => false,
            'message' => '',
            'details' => array()
        );
        
        try {
            // Test initialization
            self::init();
            $results['details']['initialization'] = 'Success';
            
            // Test simple API call
            $request = new Google_Service_AnalyticsData_RunReportRequest();
            $dateRange = new Google_Service_AnalyticsData_DateRange();
            $dateRange->setStartDate('7daysAgo');
            $dateRange->setEndDate('today');
            $request->setDateRanges([$dateRange]);
            
            $metric = new Google_Service_AnalyticsData_Metric();
            $metric->setName('sessions');
            $request->setMetrics([$metric]);
            
            $request->setProperty(self::PROPERTY_ID);
            
            $response = self::$analytics_service->properties->runReport(self::PROPERTY_ID, $request);
            
            $results['success'] = true;
            $results['message'] = 'Analytics connection successful';
            $results['details']['api_call'] = 'Success';
            $results['details']['property_id'] = self::PROPERTY_ID;
            
        } catch (Exception $e) {
            $results['message'] = 'Analytics connection failed: ' . $e->getMessage();
            $results['details']['error'] = $e->getMessage();
        }
        
        return $results;
    }
}