<?php
/**
 * Shortcodes for H3 Tour Management - 3dVista Style
 */
class H3TM_Shortcodes_V3 {
    
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
            $thumbnail_url = site_url('/' . H3TM_TOUR_DIR . '/' . rawurlencode($tour) . '/thumbnail.png');
            $tours_data[] = array(
                'directory' => $tour,
                'title' => $tour_title,
                'thumbnail' => $thumbnail_url
            );
        }
        
        // Get date range from query parameters
        $date_range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : 'last_week';
        
        // Calculate date ranges
        $ranges = $this->calculate_date_ranges($date_range);
        
        // Get global analytics data
        $global_analytics = $this->get_global_analytics($tours_data, $ranges['start'], $ranges['end']);
        
        // Get per-tour analytics
        $tour_analytics = array();
        foreach ($tours_data as $tour) {
            $analytics = $this->get_tour_analytics($tour['title'], $ranges['start'], $ranges['end']);
            if ($analytics) {
                $analytics['info'] = $tour;
                $tour_analytics[] = $analytics;
            }
        }
        
        // Sort tours by visits
        usort($tour_analytics, function($a, $b) {
            return $b['visits'] - $a['visits'];
        });
        
        // Build output
        ob_start();
        ?>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap');
            
            .analytics-container {
                font-family: 'Open Sans', sans-serif;
                background: #1a1a1a;
                color: #fff;
                padding: 0;
                margin: -20px -20px 0 -20px;
                min-height: 100vh;
            }
            
            /* Header */
            .analytics-header {
                background: #000;
                padding: 15px 30px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-bottom: 1px solid #333;
            }
            
            .analytics-logo {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .analytics-logo h1 {
                font-size: 20px;
                font-weight: 300;
                margin: 0;
                color: #fff;
            }
            
            .analytics-logo h1 span {
                font-weight: 600;
            }
            
            .home-icon {
                color: #fff;
                text-decoration: none;
                font-size: 20px;
                margin-right: 20px;
            }
            
            /* Time Range Buttons */
            .time-range-selector {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            
            .time-range-btn {
                background: transparent;
                color: #fff;
                border: 1px solid #333;
                padding: 8px 20px;
                border-radius: 20px;
                cursor: pointer;
                text-decoration: none;
                font-size: 14px;
                transition: all 0.3s;
            }
            
            .time-range-btn:hover {
                background: #333;
            }
            
            .time-range-btn.active {
                background: #00bcd4;
                border-color: #00bcd4;
            }
            
            /* Global Analytics Section */
            .global-analytics {
                background: #00bcd4;
                padding: 40px 30px;
            }
            
            .global-analytics h2 {
                font-size: 18px;
                font-weight: 400;
                margin: 0 0 30px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .analytics-metrics {
                display: flex;
                gap: 40px;
                align-items: center;
                flex-wrap: wrap;
            }
            
            .metric-circle {
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                width: 150px;
                height: 150px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
            }
            
            .metric-value {
                font-size: 36px;
                font-weight: 700;
                line-height: 1;
                margin-bottom: 5px;
            }
            
            .metric-value small {
                font-size: 20px;
                font-weight: 400;
            }
            
            .metric-label {
                font-size: 14px;
                font-weight: 400;
                opacity: 0.9;
            }
            
            /* Countries Table */
            .countries-section {
                flex: 1;
                min-width: 300px;
            }
            
            .countries-table {
                background: rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 20px;
            }
            
            .countries-table h3 {
                font-size: 14px;
                font-weight: 600;
                margin: 0 0 15px 0;
                opacity: 0.8;
            }
            
            .country-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }
            
            .country-row:last-child {
                border-bottom: none;
            }
            
            .country-name {
                font-size: 14px;
            }
            
            .country-stats {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            
            .country-percent {
                font-size: 14px;
                font-weight: 600;
                min-width: 50px;
                text-align: right;
            }
            
            .country-views {
                font-size: 14px;
                min-width: 50px;
                text-align: right;
            }
            
            /* Tour Analytics Section */
            .tour-analytics-section {
                padding: 30px;
            }
            
            .tour-analytics-section h2 {
                font-size: 18px;
                font-weight: 400;
                margin: 0 0 30px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .tours-list {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            
            .tour-item {
                background: #2a2a2a;
                border-radius: 8px;
                padding: 20px;
                display: flex;
                gap: 20px;
                align-items: center;
            }
            
            .tour-thumbnail {
                width: 120px;
                height: 80px;
                border-radius: 4px;
                overflow: hidden;
                flex-shrink: 0;
            }
            
            .tour-thumbnail img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .tour-name {
                flex: 1;
                min-width: 200px;
            }
            
            .tour-name h3 {
                font-size: 16px;
                font-weight: 400;
                margin: 0;
                color: #fff;
            }
            
            .tour-metrics {
                display: flex;
                gap: 40px;
                align-items: center;
                flex-wrap: wrap;
            }
            
            .tour-metric {
                text-align: center;
            }
            
            .tour-metric-label {
                font-size: 12px;
                color: #999;
                margin-bottom: 5px;
            }
            
            .tour-metric-value {
                font-size: 24px;
                font-weight: 600;
                color: #fff;
            }
            
            .tour-metric-value small {
                font-size: 14px;
                font-weight: 400;
            }
            
            /* Progress Circles */
            .progress-circle {
                width: 80px;
                height: 80px;
                position: relative;
            }
            
            .progress-circle svg {
                transform: rotate(-90deg);
            }
            
            .progress-circle-bg {
                fill: none;
                stroke: #444;
                stroke-width: 8;
            }
            
            .progress-circle-fill {
                fill: none;
                stroke: #00bcd4;
                stroke-width: 8;
                stroke-linecap: round;
                transition: stroke-dasharray 0.5s;
            }
            
            .progress-percent {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 20px;
                font-weight: 600;
            }
            
            .progress-percent small {
                font-size: 12px;
                font-weight: 400;
            }
            
            /* Country Charts */
            .country-chart {
                width: 200px;
                display: flex;
                gap: 2px;
                align-items: flex-end;
                height: 40px;
            }
            
            .country-bar {
                flex: 1;
                background: #666;
                border-radius: 2px 2px 0 0;
                min-height: 3px;
            }
            
            .country-bar.primary {
                background: #00bcd4;
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .analytics-metrics {
                    flex-direction: column;
                    align-items: flex-start;
                }
                
                .tour-item {
                    flex-direction: column;
                    text-align: center;
                }
                
                .tour-metrics {
                    justify-content: center;
                }
            }
        </style>
        
        <div class="analytics-container">
            <!-- Header -->
            <div class="analytics-header">
                <div style="display: flex; align-items: center;">
                    <a href="<?php echo home_url(); ?>" class="home-icon">🏠</a>
                    <div class="analytics-logo">
                        <h1>3dvista <span>Cloud</span></h1>
                    </div>
                </div>
                
                <div class="time-range-selector">
                    <a href="?range=all_time" class="time-range-btn <?php echo $date_range === 'all_time' ? 'active' : ''; ?>">All Time</a>
                    <a href="?range=last_year" class="time-range-btn <?php echo $date_range === 'last_year' ? 'active' : ''; ?>">Last Year</a>
                    <a href="?range=last_month" class="time-range-btn <?php echo $date_range === 'last_month' ? 'active' : ''; ?>">Last Month</a>
                    <a href="?range=last_week" class="time-range-btn <?php echo $date_range === 'last_week' ? 'active' : ''; ?>">Last Week</a>
                    <a href="?range=custom" class="time-range-btn <?php echo $date_range === 'custom' ? 'active' : ''; ?>">Custom</a>
                </div>
            </div>
            
            <!-- Global Analytics -->
            <div class="global-analytics">
                <h2>▼ Global Analytics</h2>
                
                <div class="analytics-metrics">
                    <!-- Total Visits -->
                    <div class="metric-circle">
                        <div class="metric-value"><?php echo $this->format_number($global_analytics['total_visits']); ?></div>
                        <div class="metric-label">Total Visits</div>
                    </div>
                    
                    <!-- Total Users -->
                    <div class="metric-circle">
                        <div class="metric-value"><?php echo $this->format_number($global_analytics['total_users']); ?></div>
                        <div class="metric-label">Total Users</div>
                    </div>
                    
                    <!-- Time Average -->
                    <div class="metric-circle">
                        <div class="metric-value"><?php echo $this->format_time($global_analytics['avg_duration']); ?></div>
                        <div class="metric-label">Time average</div>
                    </div>
                    
                    <!-- Countries Table -->
                    <div class="countries-section">
                        <div class="countries-table">
                            <h3>Countries &nbsp;&nbsp;&nbsp; % &nbsp;&nbsp;&nbsp; Views</h3>
                            <?php foreach ($global_analytics['top_countries'] as $country): ?>
                                <div class="country-row">
                                    <span class="country-name"><?php echo esc_html($country['name']); ?></span>
                                    <div class="country-stats">
                                        <span class="country-percent"><?php echo number_format($country['percent'], 1); ?>%</span>
                                        <span class="country-views"><?php echo number_format($country['views']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Country Chart -->
                    <div class="country-chart">
                        <?php 
                        $max_views = max(array_column($global_analytics['top_countries'], 'views'));
                        foreach ($global_analytics['top_countries'] as $index => $country): 
                            $height_percent = ($country['views'] / $max_views) * 100;
                        ?>
                            <div class="country-bar <?php echo $index === 0 ? 'primary' : ''; ?>" 
                                 style="height: <?php echo $height_percent; ?>%;"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Tour Analytics -->
            <div class="tour-analytics-section">
                <h2>▼ Analytics by Tour</h2>
                
                <div class="tours-list">
                    <?php foreach ($tour_analytics as $tour): ?>
                        <div class="tour-item">
                            <!-- Thumbnail -->
                            <div class="tour-thumbnail">
                                <img src="<?php echo esc_url($tour['info']['thumbnail']); ?>" 
                                     alt="<?php echo esc_attr($tour['info']['title']); ?>">
                            </div>
                            
                            <!-- Tour Name -->
                            <div class="tour-name">
                                <h3><?php echo esc_html($tour['info']['title']); ?></h3>
                            </div>
                            
                            <!-- Metrics -->
                            <div class="tour-metrics">
                                <!-- Percentage Circle -->
                                <div class="progress-circle">
                                    <svg width="80" height="80">
                                        <circle cx="40" cy="40" r="36" class="progress-circle-bg"></circle>
                                        <circle cx="40" cy="40" r="36" class="progress-circle-fill"
                                                style="stroke-dasharray: <?php echo $tour['percent'] * 2.26; ?>, 226;"></circle>
                                    </svg>
                                    <div class="progress-percent">
                                        <?php echo number_format($tour['percent']); ?><small>%</small>
                                    </div>
                                </div>
                                
                                <!-- Total Visits -->
                                <div class="tour-metric">
                                    <div class="tour-metric-label">Total Visits</div>
                                    <div class="tour-metric-value"><?php echo $this->format_number($tour['visits']); ?></div>
                                </div>
                                
                                <!-- Total Users -->
                                <div class="tour-metric">
                                    <div class="tour-metric-label">Total Users</div>
                                    <div class="tour-metric-value"><?php echo number_format($tour['users']); ?></div>
                                </div>
                                
                                <!-- Time Average -->
                                <div class="tour-metric">
                                    <div class="tour-metric-label">Time average</div>
                                    <div class="tour-metric-value"><?php echo $this->format_time($tour['avg_duration']); ?></div>
                                </div>
                                
                                <!-- Country -->
                                <div class="tour-metric">
                                    <div class="tour-metric-label">Country</div>
                                    <div class="tour-metric-value">
                                        <?php echo number_format($tour['top_country']['percent'], 1); ?><small>%</small><br>
                                        <small style="font-size: 12px; color: #999;"><?php echo esc_html($tour['top_country']['name']); ?></small>
                                    </div>
                                </div>
                                
                                <!-- Country Chart -->
                                <div class="country-chart">
                                    <?php 
                                    $countries = array_slice($tour['countries'], 0, 10);
                                    $max_views = max(array_column($countries, 'views'));
                                    foreach ($countries as $index => $country): 
                                        $height_percent = ($country['views'] / $max_views) * 100;
                                    ?>
                                        <div class="country-bar <?php echo $index === 0 ? 'primary' : ''; ?>" 
                                             style="height: <?php echo $height_percent; ?>%;"></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Calculate date ranges
     */
    private function calculate_date_ranges($range) {
        switch ($range) {
            case 'all_time':
                return array(
                    'start' => '2020-01-01',
                    'end' => 'today'
                );
            case 'last_year':
                return array(
                    'start' => date('Y-m-d', strtotime('-1 year')),
                    'end' => 'today'
                );
            case 'last_month':
                return array(
                    'start' => date('Y-m-d', strtotime('-1 month')),
                    'end' => 'today'
                );
            case 'last_week':
            default:
                return array(
                    'start' => date('Y-m-d', strtotime('-7 days')),
                    'end' => 'today'
                );
        }
    }
    
    /**
     * Format large numbers
     */
    private function format_number($number) {
        if ($number >= 1000000) {
            return number_format($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return number_format($number / 1000, 1) . 'K';
        }
        return number_format($number);
    }
    
    /**
     * Format time duration
     */
    private function format_time($seconds) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $secs);
    }
    
    /**
     * Get global analytics for all tours
     */
    private function get_global_analytics($tours, $start_date, $end_date) {
        try {
            $this->initialize_analytics();
            
            $PROPERTY_ID = "properties/491286260";
            
            // Combine all tour titles for filtering
            $tour_titles = array_column($tours, 'title');
            
            // Date range
            $dateRange = new Google_Service_AnalyticsData_DateRange();
            $dateRange->setStartDate($start_date);
            $dateRange->setEndDate($end_date);
            
            // Metrics
            $sessions = new Google_Service_AnalyticsData_Metric();
            $sessions->setName('sessions');
            
            $users = new Google_Service_AnalyticsData_Metric();
            $users->setName('totalUsers');
            
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
            $request->setMetrics([$sessions, $users, $avgSessionDuration]);
            if ($filterExpression) {
                $request->setDimensionFilter($filterExpression);
            }
            
            $response = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
            
            $total_visits = 0;
            $total_users = 0;
            $avg_duration = 0;
            
            $rows = $response->getRows();
            if (!empty($rows)) {
                $row = $rows[0];
                $metricValues = $row->getMetricValues();
                $total_visits = $metricValues[0]->getValue();
                $total_users = $metricValues[1]->getValue();
                $avg_duration = $metricValues[2]->getValue();
            }
            
            // Get country data
            $country = new Google_Service_AnalyticsData_Dimension();
            $country->setName('country');
            
            $request = new Google_Service_AnalyticsData_RunReportRequest();
            $request->setProperty($PROPERTY_ID);
            $request->setDateRanges([$dateRange]);
            $request->setDimensions([$country]);
            $request->setMetrics([$sessions]);
            if ($filterExpression) {
                $request->setDimensionFilter($filterExpression);
            }
            $request->setLimit(5);
            
            $ordering = new Google_Service_AnalyticsData_OrderBy();
            $metricOrdering = new Google_Service_AnalyticsData_MetricOrderBy();
            $metricOrdering->setMetricName('sessions');
            $ordering->setMetric($metricOrdering);
            $ordering->setDesc(true);
            $request->setOrderBys([$ordering]);
            
            $countryResponse = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
            
            $countries = array();
            $rows = $countryResponse->getRows();
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $dims = $row->getDimensionValues();
                    $mets = $row->getMetricValues();
                    
                    $countryName = $dims[0]->getValue();
                    if (empty($countryName) || $countryName === '(not set)') {
                        $countryName = 'Unknown';
                    }
                    
                    $views = $mets[0]->getValue();
                    $percent = $total_visits > 0 ? ($views / $total_visits) * 100 : 0;
                    
                    $countries[] = array(
                        'name' => $countryName,
                        'views' => $views,
                        'percent' => $percent
                    );
                }
            }
            
            return array(
                'total_visits' => $total_visits,
                'total_users' => $total_users,
                'avg_duration' => $avg_duration,
                'top_countries' => $countries
            );
            
        } catch (Exception $e) {
            return array(
                'total_visits' => 0,
                'total_users' => 0,
                'avg_duration' => 0,
                'top_countries' => array()
            );
        }
    }
    
    /**
     * Get analytics for a specific tour
     */
    private function get_tour_analytics($tour_title, $start_date, $end_date) {
        try {
            $this->initialize_analytics();
            
            $PROPERTY_ID = "properties/491286260";
            
            // Date range
            $dateRange = new Google_Service_AnalyticsData_DateRange();
            $dateRange->setStartDate($start_date);
            $dateRange->setEndDate($end_date);
            
            // Metrics
            $sessions = new Google_Service_AnalyticsData_Metric();
            $sessions->setName('sessions');
            
            $users = new Google_Service_AnalyticsData_Metric();
            $users->setName('totalUsers');
            
            $avgSessionDuration = new Google_Service_AnalyticsData_Metric();
            $avgSessionDuration->setName('averageSessionDuration');
            
            // Filter by tour title
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
            $request->setMetrics([$sessions, $users, $avgSessionDuration]);
            $request->setDimensionFilter($filterExpression);
            
            $response = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
            
            $visits = 0;
            $users_count = 0;
            $avg_duration = 0;
            
            $rows = $response->getRows();
            if (!empty($rows)) {
                $row = $rows[0];
                $metricValues = $row->getMetricValues();
                $visits = $metricValues[0]->getValue();
                $users_count = $metricValues[1]->getValue();
                $avg_duration = $metricValues[2]->getValue();
            }
            
            // Skip if no visits
            if ($visits == 0) {
                return null;
            }
            
            // Get country data
            $country = new Google_Service_AnalyticsData_Dimension();
            $country->setName('country');
            
            $request = new Google_Service_AnalyticsData_RunReportRequest();
            $request->setProperty($PROPERTY_ID);
            $request->setDateRanges([$dateRange]);
            $request->setDimensions([$country]);
            $request->setMetrics([$sessions]);
            $request->setDimensionFilter($filterExpression);
            $request->setLimit(10);
            
            $ordering = new Google_Service_AnalyticsData_OrderBy();
            $metricOrdering = new Google_Service_AnalyticsData_MetricOrderBy();
            $metricOrdering->setMetricName('sessions');
            $ordering->setMetric($metricOrdering);
            $ordering->setDesc(true);
            $request->setOrderBys([$ordering]);
            
            $countryResponse = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
            
            $countries = array();
            $rows = $countryResponse->getRows();
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $dims = $row->getDimensionValues();
                    $mets = $row->getMetricValues();
                    
                    $countryName = $dims[0]->getValue();
                    if (empty($countryName) || $countryName === '(not set)') {
                        $countryName = 'Unknown';
                    }
                    
                    $views = $mets[0]->getValue();
                    $percent = $visits > 0 ? ($views / $visits) * 100 : 0;
                    
                    $countries[] = array(
                        'name' => $countryName,
                        'views' => $views,
                        'percent' => $percent
                    );
                }
            }
            
            // Get top country
            $top_country = !empty($countries) ? $countries[0] : array('name' => 'Unknown', 'percent' => 0);
            
            // Calculate percentage of total (this would need total visits across all tours)
            // For now, we'll use a placeholder
            $percent = 0;
            
            return array(
                'visits' => $visits,
                'users' => $users_count,
                'avg_duration' => $avg_duration,
                'percent' => $percent,
                'top_country' => $top_country,
                'countries' => $countries
            );
            
        } catch (Exception $e) {
            return null;
        }
    }
}