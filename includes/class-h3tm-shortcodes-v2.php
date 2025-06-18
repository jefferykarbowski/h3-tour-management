<?php
/**
 * Shortcodes for H3 Tour Management - GA4 Version
 */
class H3TM_Shortcodes_V2 {
    
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
        
        // Get selected tour from query parameter or use first tour
        $selected_tour = isset($_GET['tour']) ? sanitize_text_field($_GET['tour']) : $tours_data[0]['directory'];
        $selected_tour_title = '';
        
        foreach ($tours_data as $tour) {
            if ($tour['directory'] === $selected_tour) {
                $selected_tour_title = $tour['title'];
                break;
            }
        }
        
        // Get date range from query parameters
        $date_range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : '30days';
        $start_date = $this->calculate_start_date($date_range);
        
        // Get analytics data
        $analytics_data = $this->get_analytics_data($selected_tour_title, $start_date);
        
        // Get tour thumbnail URL
        $thumbnail_url = site_url('/' . H3TM_TOUR_DIR . '/' . rawurlencode($selected_tour) . '/thumbnail.png');
        
        // Build output
        ob_start();
        ?>
        <style>
            .tour-analytics {
                font-family: Arial, sans-serif;
                background: #f5f5f5;
                padding: 0;
                margin: -20px -20px 0 -20px; /* Negative margins to expand to full width */
            }
            
            /* Hero Section */
            .analytics-hero {
                position: relative;
                height: 300px;
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .analytics-hero::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
            }
            .analytics-hero h1 {
                position: relative;
                color: white;
                font-size: 48px;
                margin: 0;
                text-align: center;
                text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
            }
            
            /* Controls Section */
            .analytics-controls {
                background: white;
                padding: 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                gap: 20px;
                align-items: center;
                flex-wrap: wrap;
            }
            .analytics-controls label {
                font-weight: 600;
                margin-right: 8px;
            }
            .analytics-controls select {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                background: white;
            }
            
            /* Content Section */
            .analytics-content {
                padding: 30px 20px;
            }
            
            /* Metrics Grid */
            .analytics-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            .analytics-card {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                text-align: center;
            }
            .analytics-card h3 {
                margin: 0 0 10px 0;
                color: #666;
                font-size: 14px;
                font-weight: normal;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .analytics-card .value {
                font-size: 36px;
                font-weight: bold;
                color: #333;
                line-height: 1.2;
            }
            .analytics-card .value.duration {
                font-size: 24px;
            }
            
            /* Tables */
            .analytics-tables {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            @media (max-width: 768px) {
                .analytics-tables {
                    grid-template-columns: 1fr;
                }
            }
            
            .analytics-table {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .analytics-table h2 {
                margin: 0;
                padding: 15px 20px;
                background: #f8f8f8;
                border-bottom: 1px solid #eee;
                font-size: 16px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #333;
            }
            .analytics-table table {
                width: 100%;
                border-collapse: collapse;
            }
            .analytics-table th,
            .analytics-table td {
                padding: 12px 20px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .analytics-table th {
                background: #fafafa;
                font-weight: 600;
                color: #666;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .analytics-table td {
                font-size: 14px;
            }
            .analytics-table tbody tr:hover {
                background: #f8f8f8;
            }
            .analytics-table tbody tr:last-child td {
                border-bottom: none;
            }
            .analytics-table .number {
                text-align: right;
                font-weight: 600;
            }
            
            .no-data {
                padding: 40px;
                text-align: center;
                color: #999;
                font-style: italic;
            }
            
            .analytics-error {
                background: #fee;
                color: #c33;
                padding: 20px;
                border-radius: 8px;
                margin: 20px;
            }
            
            /* Loading state */
            .analytics-loading {
                text-align: center;
                padding: 60px 20px;
                color: #666;
            }
            
            /* View Tour Link */
            .view-tour-link {
                margin-left: auto;
            }
            .view-tour-link a {
                display: inline-block;
                padding: 8px 16px;
                background: #c1272d;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-size: 14px;
                transition: background 0.3s;
            }
            .view-tour-link a:hover {
                background: #a02025;
            }
        </style>
        
        <div class="tour-analytics">
            <!-- Hero Section with Tour Image -->
            <div class="analytics-hero" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');">
                <h1><?php echo esc_html($selected_tour_title); ?></h1>
            </div>
            
            <!-- Controls Bar -->
            <div class="analytics-controls">
                <form method="get" style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center; width: 100%;">
                    <div>
                        <label for="tour-select">Tour:</label>
                        <select name="tour" id="tour-select" onchange="this.form.submit()">
                            <?php foreach ($tours_data as $tour): ?>
                                <option value="<?php echo esc_attr($tour['directory']); ?>" 
                                        <?php selected($selected_tour, $tour['directory']); ?>>
                                    <?php echo esc_html($tour['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="range-select">Date Range:</label>
                        <select name="range" id="range-select" onchange="this.form.submit()">
                            <option value="7days" <?php selected($date_range, '7days'); ?>>Last 7 Days</option>
                            <option value="30days" <?php selected($date_range, '30days'); ?>>Last 30 Days</option>
                            <option value="90days" <?php selected($date_range, '90days'); ?>>Last 90 Days</option>
                            <option value="365days" <?php selected($date_range, '365days'); ?>>Last Year</option>
                        </select>
                    </div>
                    
                    <div class="view-tour-link">
                        <a href="<?php echo esc_url(site_url('/' . H3TM_TOUR_DIR . '/' . $selected_tour . '/')); ?>" target="_blank">View Tour</a>
                    </div>
                </form>
            </div>
            
            <!-- Main Content -->
            <div class="analytics-content">
                <?php if (isset($analytics_data['error'])): ?>
                    <div class="analytics-error">
                        <strong>Error:</strong> <?php echo esc_html($analytics_data['error']); ?>
                    </div>
                <?php else: ?>
                    <!-- Metrics Cards -->
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <h3>Total Views</h3>
                            <div class="value"><?php echo number_format($analytics_data['metrics']['sessions']); ?></div>
                        </div>
                        
                        <div class="analytics-card">
                            <h3>Total Visitors</h3>
                            <div class="value"><?php echo number_format($analytics_data['metrics']['users']); ?></div>
                        </div>
                        
                        <div class="analytics-card">
                            <h3>Photos Viewed</h3>
                            <div class="value"><?php echo number_format($analytics_data['metrics']['events']); ?></div>
                        </div>
                        
                        <div class="analytics-card">
                            <h3>Avg. Duration</h3>
                            <div class="value duration"><?php echo $this->format_duration($analytics_data['metrics']['avgSessionDuration']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Tables Section -->
                    <div class="analytics-tables">
                        <!-- Top Pages -->
                        <div class="analytics-table">
                            <h2>Top Pages</h2>
                            <?php if (!empty($analytics_data['pages'])): ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Page Path</th>
                                            <th class="number">Views</th>
                                            <th class="number">Users</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analytics_data['pages'] as $page): ?>
                                            <tr>
                                                <td><?php echo esc_html($page['path']); ?></td>
                                                <td class="number"><?php echo number_format($page['views']); ?></td>
                                                <td class="number"><?php echo number_format($page['users']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="no-data">No page data available</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Countries -->
                        <div class="analytics-table">
                            <h2>Visitors by Country</h2>
                            <?php if (!empty($analytics_data['countries'])): ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Country</th>
                                            <th class="number">Users</th>
                                            <th class="number">Sessions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analytics_data['countries'] as $country): ?>
                                            <tr>
                                                <td><?php echo esc_html($country['name']); ?></td>
                                                <td class="number"><?php echo number_format($country['users']); ?></td>
                                                <td class="number"><?php echo number_format($country['sessions']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="no-data">No country data available</div>
                            <?php endif; ?>
                        </div>
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
            return round($seconds) . ' sec';
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
            $request->setMetrics([$sessions, $users, $events, $avgSessionDuration]);
            $request->setDimensionFilter($filterExpression);
            
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
                $metrics['sessions'] = $metricValues[0]->getValue();
                $metrics['users'] = $metricValues[1]->getValue();
                $metrics['events'] = $metricValues[2]->getValue();
                $metrics['avgSessionDuration'] = $metricValues[3]->getValue();
            }
            
            // Get top pages
            $pagePath = new Google_Service_AnalyticsData_Dimension();
            $pagePath->setName('pagePath');
            
            $pageViews = new Google_Service_AnalyticsData_Metric();
            $pageViews->setName('screenPageViews');
            
            $request = new Google_Service_AnalyticsData_RunReportRequest();
            $request->setProperty($PROPERTY_ID);
            $request->setDateRanges([$dateRange]);
            $request->setDimensions([$pagePath]);
            $request->setMetrics([$pageViews, $users]);
            $request->setDimensionFilter($filterExpression);
            $request->setLimit(10);
            
            $ordering = new Google_Service_AnalyticsData_OrderBy();
            $metricOrdering = new Google_Service_AnalyticsData_MetricOrderBy();
            $metricOrdering->setMetricName('screenPageViews');
            $ordering->setMetric($metricOrdering);
            $ordering->setDesc(true);
            $request->setOrderBys([$ordering]);
            
            $pagesResponse = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
            
            $pages = array();
            $rows = $pagesResponse->getRows();
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $dims = $row->getDimensionValues();
                    $mets = $row->getMetricValues();
                    $pages[] = array(
                        'path' => $dims[0]->getValue(),
                        'views' => $mets[0]->getValue(),
                        'users' => $mets[1]->getValue()
                    );
                }
            }
            
            // Get countries
            $country = new Google_Service_AnalyticsData_Dimension();
            $country->setName('country');
            
            $request = new Google_Service_AnalyticsData_RunReportRequest();
            $request->setProperty($PROPERTY_ID);
            $request->setDateRanges([$dateRange]);
            $request->setDimensions([$country]);
            $request->setMetrics([$users, $sessions]);
            $request->setDimensionFilter($filterExpression);
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
                    
                    $countries[] = array(
                        'name' => $countryName,
                        'users' => $mets[0]->getValue(),
                        'sessions' => $mets[1]->getValue()
                    );
                }
            }
            
            return array(
                'metrics' => $metrics,
                'pages' => $pages,
                'countries' => $countries
            );
            
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage()
            );
        }
    }
}