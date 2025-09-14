<?php
/**
 * Shortcodes for H3 Tour Management - Simplified Analytics
 */
class H3TM_Shortcodes_V4 {
    
    private $analytics_service;
    
    public function __construct() {
        add_shortcode('tour_analytics_display', array($this, 'tour_analytics_display_shortcode'));
    }
    
    /**
     * Initialize Google Analytics service
     */
    private function initialize_analytics() {
        if ($this->analytics_service) {
            return $this->analytics_service;
        }
        
        require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-config.php';
        
        $autoload_path = H3TM_Config::get_autoload_path();
        
        if (!file_exists($autoload_path)) {
            throw new Exception('Google API client library not found. Please install it via Composer.');
        }
        
        require_once $autoload_path;
        
        $KEY_FILE_LOCATION = H3TM_Config::get_credentials_path();
        if (!file_exists($KEY_FILE_LOCATION)) {
            throw new Exception('Google Analytics service account credentials file not found.');
        }
        
        $client = new Google_Client();
        
        // SSL verification based on environment
        if (!H3TM_Config::should_verify_ssl()) {
            $httpClient = new \GuzzleHttp\Client([
                'verify' => false
            ]);
            $client->setHttpClient($httpClient);
        }
        
        $client->setApplicationName("H3VT Analytics Display");
        $client->setAuthConfig($KEY_FILE_LOCATION);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        
        $this->analytics_service = new Google_Service_AnalyticsData($client);
        return $this->analytics_service;
    }
    
    /**
     * Tour Analytics Display Shortcode
     */
    public function tour_analytics_display_shortcode($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<p>Please log in to view analytics.</p>';
        }
        
        $current_user_id = get_current_user_id();
        $assigned_tours = get_user_meta($current_user_id, 'h3tm_tours', true);
        
        if (empty($assigned_tours)) {
            return '<p>No tours assigned to your account.</p>';
        }
        
        // Get tour manager
        $tour_manager = new H3TM_Tour_Manager();
        
        // Prepare tour data
        $tours_data = array();
        foreach ($assigned_tours as $tour) {
            $tour_title = trim($tour_manager->get_tour_title($tour));
            $tours_data[] = array(
                'directory' => $tour,
                'title' => $tour_title
            );
        }
        
        // Get selected tour and date range
        $selected_tour = isset($_GET['tour']) ? sanitize_text_field($_GET['tour']) : 'all';
        $date_range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : '30days';
        
        // Calculate start date
        $start_date = $this->calculate_start_date($date_range);
        
        // Get analytics data
        if ($selected_tour === 'all') {
            // Get combined analytics for all tours
            $analytics_data = $this->get_combined_analytics($tours_data, $start_date);
            $display_title = 'All Tours';
        } else {
            // Get analytics for specific tour
            $tour_title = '';
            foreach ($tours_data as $tour) {
                if ($tour['directory'] === $selected_tour) {
                    $tour_title = $tour['title'];
                    break;
                }
            }
            if ($tour_title) {
                $analytics_data = $this->get_analytics_data($tour_title, $start_date);
                $display_title = $tour_title;
            } else {
                $analytics_data = array('error' => 'Tour not found');
                $display_title = 'Unknown Tour';
            }
        }
        
        // Build output
        ob_start();
        ?>
        <style>
            .analytics-container {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: #f8f9fa;
                padding: 20px;
                max-width: 1200px;
                margin: 0 auto;
            }
            
            /* Controls */
            .analytics-controls {
                background: white;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .controls-row {
                display: flex;
                gap: 20px;
                align-items: center;
                flex-wrap: wrap;
            }
            
            .control-group {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .control-group label {
                font-size: 12px;
                color: #666;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .control-group select {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                background: white;
                min-width: 200px;
            }
            
            /* Global Analytics Section */
            .global-analytics {
                background: white;
                padding: 30px;
                border-radius: 8px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .global-analytics h2 {
                font-size: 24px;
                margin: 0 0 30px 0;
                color: #333;
            }
            
            .metrics-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 30px;
                margin-bottom: 40px;
            }
            
            .metric-card {
                text-align: center;
            }
            
            .metric-value {
                font-size: 48px;
                font-weight: 700;
                color: #00bcd4;
                line-height: 1;
                margin-bottom: 10px;
            }
            
            .metric-label {
                font-size: 14px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            /* Countries Section */
            .countries-section {
                margin-top: 40px;
            }
            
            .countries-section h3 {
                font-size: 18px;
                margin: 0 0 20px 0;
                color: #333;
            }
            
            .countries-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .countries-table th {
                text-align: left;
                padding: 12px;
                border-bottom: 2px solid #e0e0e0;
                font-size: 12px;
                color: #666;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .countries-table td {
                padding: 12px;
                border-bottom: 1px solid #e0e0e0;
                font-size: 14px;
            }
            
            .countries-table tr:last-child td {
                border-bottom: none;
            }
            
            .country-bar {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .country-bar-fill {
                height: 20px;
                background: #00bcd4;
                border-radius: 10px;
                transition: width 0.3s;
            }
            
            .country-percent {
                font-weight: 600;
                color: #00bcd4;
                min-width: 50px;
                text-align: right;
            }
            
            /* Error State */
            .analytics-error {
                background: #fff3cd;
                color: #856404;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border: 1px solid #ffeaa7;
            }
            
            /* Loading State */
            .analytics-loading {
                text-align: center;
                padding: 60px 20px;
                color: #666;
            }
            
            /* No Data */
            .no-data {
                text-align: center;
                padding: 40px;
                color: #999;
                font-style: italic;
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .controls-row {
                    flex-direction: column;
                    align-items: stretch;
                }
                
                .control-group select {
                    width: 100%;
                }
                
                .metrics-grid {
                    grid-template-columns: 1fr;
                    gap: 20px;
                }
                
                .metric-value {
                    font-size: 36px;
                }
            }
        </style>
        
        <div class="analytics-container">
            <!-- Controls -->
            <div class="analytics-controls">
                <form method="get">
                    <div class="controls-row">
                        <div class="control-group">
                            <label for="tour-select">Tour</label>
                            <select name="tour" id="tour-select" onchange="this.form.submit()">
                                <option value="all" <?php selected($selected_tour, 'all'); ?>>All Tours</option>
                                <?php foreach ($tours_data as $tour): ?>
                                    <option value="<?php echo esc_attr($tour['directory']); ?>" 
                                            <?php selected($selected_tour, $tour['directory']); ?>>
                                        <?php echo esc_html($tour['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="control-group">
                            <label for="range-select">Date Range</label>
                            <select name="range" id="range-select" onchange="this.form.submit()">
                                <option value="7days" <?php selected($date_range, '7days'); ?>>Last 7 Days</option>
                                <option value="30days" <?php selected($date_range, '30days'); ?>>Last 30 Days</option>
                                <option value="90days" <?php selected($date_range, '90days'); ?>>Last 90 Days</option>
                                <option value="365days" <?php selected($date_range, '365days'); ?>>Last Year</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Global Analytics -->
            <div class="global-analytics">
                <h2><?php echo esc_html($display_title); ?> Analytics</h2>
                
                <?php if (isset($_GET['debug']) && $_GET['debug'] === '1' && isset($analytics_data['debug_info'])): ?>
                    <div style="background: #f0f0f0; padding: 15px; margin-bottom: 20px; font-size: 12px; border-radius: 4px;">
                        <strong>🔍 Debug Info:</strong><br>
                        Tours Searched: <?php echo esc_html($analytics_data['debug_info']['tours_searched'] ?? 'unknown'); ?><br>
                        Has Data: <?php echo ($analytics_data['debug_info']['has_data'] ?? false) ? 'Yes' : 'No'; ?><br>
                        Rows Found: <?php echo esc_html($analytics_data['debug_info']['rows_found'] ?? 0); ?><br>
                        Method Used: <?php echo esc_html($analytics_data['debug_info']['method_used'] ?? 'unknown'); ?><br>
                        <?php if (isset($analytics_data['debug_info']['exception_type'])): ?>
                            Exception: <?php echo esc_html($analytics_data['debug_info']['exception_type']); ?><br>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($analytics_data['error'])): ?>
                    <div class="analytics-error">
                        <strong>Error:</strong> <?php echo esc_html($analytics_data['error']); ?>
                    </div>
                <?php else: ?>
                    <!-- Metrics -->
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <div class="metric-value"><?php echo number_format($analytics_data['metrics']['sessions']); ?></div>
                            <div class="metric-label">Total Visits</div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-value"><?php echo number_format($analytics_data['metrics']['users']); ?></div>
                            <div class="metric-label">Total Users</div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-value"><?php echo number_format($analytics_data['metrics']['events']); ?></div>
                            <div class="metric-label">Photos Viewed</div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-value"><?php echo $this->format_duration($analytics_data['metrics']['avgSessionDuration']); ?></div>
                            <div class="metric-label">Avg. Duration</div>
                        </div>
                    </div>
                    
                    <!-- Countries -->
                    <div class="countries-section">
                        <h3>Visitors by Country</h3>
                        <?php if (!empty($analytics_data['countries'])): ?>
                            <table class="countries-table">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>Users</th>
                                        <th>Sessions</th>
                                        <th style="width: 40%;">Distribution</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $max_users = max(array_column($analytics_data['countries'], 'users'));
                                    foreach ($analytics_data['countries'] as $country): 
                                        $percent = $max_users > 0 ? ($country['users'] / $max_users) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo esc_html($country['name']); ?></td>
                                            <td><?php echo number_format($country['users']); ?></td>
                                            <td><?php echo number_format($country['sessions']); ?></td>
                                            <td>
                                                <div class="country-bar">
                                                    <div class="country-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                                                    <span class="country-percent"><?php echo number_format($country['percent'], 1); ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">No country data available</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Calculate start date based on range
     */
    private function calculate_start_date($range) {
        switch ($range) {
            case '7days':
                return date('Y-m-d', strtotime('-7 days'));
            case '90days':
                return date('Y-m-d', strtotime('-90 days'));
            case '365days':
                return date('Y-m-d', strtotime('-365 days'));
            case '30days':
            default:
                return date('Y-m-d', strtotime('-30 days'));
        }
    }
    
    /**
     * Format duration in seconds to human readable
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return round($seconds) . 's';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = round($seconds % 60);
            return $minutes . 'm ' . $secs . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }
    
    /**
     * Get combined analytics for all tours
     */
    private function get_combined_analytics($tours, $start_date) {
        try {
            $this->initialize_analytics();
            
            $PROPERTY_ID = "properties/491286260";
            
            // Combine all tour titles for filtering
            $tour_titles = array_column($tours, 'title');
            
            // Date range
            $dateRange = new Google_Service_AnalyticsData_DateRange();
            $dateRange->setStartDate($start_date);
            $dateRange->setEndDate('today');
            
            // Metrics
            $sessions = new Google_Service_AnalyticsData_Metric();
            $sessions->setName('sessions');
            
            $users = new Google_Service_AnalyticsData_Metric();
            $users->setName('totalUsers');
            
            $events = new Google_Service_AnalyticsData_Metric();
            $events->setName('eventCount');
            
            $avgSessionDuration = new Google_Service_AnalyticsData_Metric();
            $avgSessionDuration->setName('averageSessionDuration');
            
            // Create OR filter for all tours
            $filters = array();
            foreach ($tour_titles as $title) {
                $filter = new Google_Service_AnalyticsData_Filter();
                $stringFilter = new Google_Service_AnalyticsData_StringFilter();
                $stringFilter->setMatchType('EXACT');
                $stringFilter->setValue($title);
                $filter->setStringFilter($stringFilter);
                $filter->setFieldName('pageTitle');
                $filters[] = $filter;
            }
            
            $filterExpression = null;
            if (count($filters) > 0) {
                $filterExpression = new Google_Service_AnalyticsData_FilterExpression();
                if (count($filters) === 1) {
                    $filterExpression->setFilter($filters[0]);
                } else {
                    $orGroup = new Google_Service_AnalyticsData_FilterExpressionList();
                    $orGroup->setExpressions(array_map(function($filter) {
                        $expr = new Google_Service_AnalyticsData_FilterExpression();
                        $expr->setFilter($filter);
                        return $expr;
                    }, $filters));
                    $filterExpression->setOrGroup($orGroup);
                }
            }
            
            // Get overall metrics
            $request = new Google_Service_AnalyticsData_RunReportRequest();
            $request->setProperty($PROPERTY_ID);
            $request->setDateRanges([$dateRange]);
            $request->setMetrics([$events, $sessions, $users, $avgSessionDuration]); // EXACT SAME ORDER AS EMAIL ANALYTICS
            if ($filterExpression) {
                $request->setDimensionFilter($filterExpression);
            }
            
            $response = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
            
            $metrics = array(
                'sessions' => 0,
                'users' => 0,
                'events' => 0,
                'avgSessionDuration' => 0
            );
            
            $rows = $response->getRows();
            if (!empty($rows)) {
                $row = $rows[0];
                $metricValues = $row->getMetricValues();
                $metrics['events'] = $metricValues[0]->getValue();     // Photo views (events first in email order)
                $metrics['sessions'] = $metricValues[1]->getValue();   // Tour views  
                $metrics['users'] = $metricValues[2]->getValue();      // Visitors
                $metrics['avgSessionDuration'] = $metricValues[3]->getValue(); // Duration
            }
            
            // Get countries
            $countries = $this->get_country_data($filterExpression, $dateRange, $metrics['users']);
            
            return array(
                'metrics' => $metrics,
                'countries' => $countries,
                'debug_info' => array(
                    'tours_searched' => $tour_title,
                    'has_data' => !empty($rows),
                    'rows_found' => count($rows),
                    'method_used' => 'fixed_email_order_combined',
                    'available_page_titles' => array_slice($availableTitles, 0, 10),
                    'total_available_titles' => count($availableTitles),
                    'filter_type' => 'EXACT',
                    'property_id' => $PROPERTY_ID
                )
            );
            
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
                'debug_info' => array(
                    'tours_searched' => isset($tour_titles) ? implode(', ', $tour_titles) : 'unknown',
                    'has_data' => false,
                    'exception_type' => get_class($e),
                    'method_used' => 'fixed_email_order_combined_failed'
                )
            );
        }
    }
    
    /**
     * Get analytics data using GA4 API
     */
    private function get_analytics_data($tour_title, $start_date) {
        try {
            $this->initialize_analytics();
            
            $PROPERTY_ID = "properties/491286260";
            
            // Date range
            $dateRange = new Google_Service_AnalyticsData_DateRange();
            $dateRange->setStartDate($start_date);
            $dateRange->setEndDate('today');
            
            // Metrics
            $sessions = new Google_Service_AnalyticsData_Metric();
            $sessions->setName('sessions');
            
            $users = new Google_Service_AnalyticsData_Metric();
            $users->setName('totalUsers');
            
            $events = new Google_Service_AnalyticsData_Metric();
            $events->setName('eventCount');
            
            $avgSessionDuration = new Google_Service_AnalyticsData_Metric();
            $avgSessionDuration->setName('averageSessionDuration');
            
            // Filter by page title
            $filter = new Google_Service_AnalyticsData_Filter();
            $stringFilter = new Google_Service_AnalyticsData_StringFilter();
            $stringFilter->setMatchType('EXACT');
            $stringFilter->setValue($tour_title);
            $filter->setStringFilter($stringFilter);
            $filter->setFieldName('pageTitle');
            
            $filterExpression = new Google_Service_AnalyticsData_FilterExpression();
            $filterExpression->setFilter($filter);
            
            // Get overall metrics
            $request = new Google_Service_AnalyticsData_RunReportRequest();
            $request->setProperty($PROPERTY_ID);
            $request->setDateRanges([$dateRange]);
            $request->setMetrics([$events, $sessions, $users, $avgSessionDuration]); // EXACT SAME ORDER AS EMAIL ANALYTICS
            $request->setDimensionFilter($filterExpression);
            
            $response = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
            
            $metrics = array(
                'sessions' => 0,
                'users' => 0,
                'events' => 0,
                'avgSessionDuration' => 0
            );
            
            $rows = $response->getRows();
            
            // Enhanced debugging: Try query without filter to see available data
            $debugRequest = new Google_Service_AnalyticsData_RunReportRequest();
            $debugRequest->setProperty($PROPERTY_ID);
            $debugRequest->setDateRanges([$dateRange]);
            $debugRequest->setMetrics([$sessions]);
            
            // Add pageTitle dimension to see what titles exist
            $pageTitle = new Google_Service_AnalyticsData_Dimension();
            $pageTitle->setName('pageTitle');
            $debugRequest->setDimensions([$pageTitle]);
            $debugRequest->setLimit(50);
            
            $debugResponse = $this->analytics_service->properties->runReport($PROPERTY_ID, $debugRequest);
            $debugRows = $debugResponse->getRows();
            $availableTitles = array();
            if (!empty($debugRows)) {
                foreach($debugRows as $debugRow) {
                    $dimensionValues = $debugRow->getDimensionValues();
                    $availableTitles[] = $dimensionValues[0]->getValue();
                }
            }
            if (!empty($rows)) {
                $row = $rows[0];
                $metricValues = $row->getMetricValues();
                $metrics['events'] = $metricValues[0]->getValue();     // Photo views (events first in email order)
                $metrics['sessions'] = $metricValues[1]->getValue();   // Tour views  
                $metrics['users'] = $metricValues[2]->getValue();      // Visitors
                $metrics['avgSessionDuration'] = $metricValues[3]->getValue(); // Duration
            }
            
            // Get countries
            $countries = $this->get_country_data($filterExpression, $dateRange, $metrics['users']);
            
            return array(
                'metrics' => $metrics,
                'countries' => $countries,
                'debug_info' => array(
                    'tours_searched' => $tour_title,
                    'has_data' => !empty($rows),
                    'rows_found' => count($rows),
                    'method_used' => 'fixed_email_order_combined',
                    'available_page_titles' => array_slice($availableTitles, 0, 10),
                    'total_available_titles' => count($availableTitles),
                    'filter_type' => 'EXACT',
                    'property_id' => $PROPERTY_ID
                )
            );
            
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
                'debug_info' => array(
                    'tours_searched' => isset($tour_titles) ? implode(', ', $tour_titles) : 'unknown',
                    'has_data' => false,
                    'exception_type' => get_class($e),
                    'method_used' => 'fixed_email_order_combined_failed'
                )
            );
        }
    }
    
    /**
     * Get country data
     */
    private function get_country_data($filterExpression, $dateRange, $total_users) {
        $PROPERTY_ID = "properties/491286260";
        
        $country = new Google_Service_AnalyticsData_Dimension();
        $country->setName('country');
        
        $users = new Google_Service_AnalyticsData_Metric();
        $users->setName('totalUsers');
        
        $sessions = new Google_Service_AnalyticsData_Metric();
        $sessions->setName('sessions');
        
        $request = new Google_Service_AnalyticsData_RunReportRequest();
        $request->setProperty($PROPERTY_ID);
        $request->setDateRanges([$dateRange]);
        $request->setDimensions([$country]);
        $request->setMetrics([$users, $sessions]);
        if ($filterExpression) {
            $request->setDimensionFilter($filterExpression);
        }
        $request->setLimit(10);
        
        $ordering = new Google_Service_AnalyticsData_OrderBy();
        $metricOrdering = new Google_Service_AnalyticsData_MetricOrderBy();
        $metricOrdering->setMetricName('totalUsers');
        $ordering->setMetric($metricOrdering);
        $ordering->setDesc(true);
        $request->setOrderBys([$ordering]);
        
        $countriesResponse = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
        
        $countries = array();
        $rows = $countriesResponse->getRows();
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $dims = $row->getDimensionValues();
                $mets = $row->getMetricValues();
                
                $countryName = $dims[0]->getValue();
                
                // Handle empty values
                if (empty($countryName) || $countryName === '(not set)') {
                    $countryName = 'Unknown';
                }
                
                $user_count = $mets[0]->getValue();
                $session_count = $mets[1]->getValue();
                $percent = $total_users > 0 ? ($user_count / $total_users) * 100 : 0;
                
                $countries[] = array(
                    'name' => $countryName,
                    'users' => $user_count,
                    'sessions' => $session_count,
                    'percent' => $percent
                );
            }
        }
        
        return $countries;
    }
}