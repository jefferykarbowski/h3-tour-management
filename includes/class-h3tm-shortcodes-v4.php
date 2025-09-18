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

        // Get enhanced analytics data
        if (!isset($analytics_data['error'])) {
            $filterExpression = $this->build_filter_expression($selected_tour === 'all' ? $tours_data : array(array('title' => $tour_title)));
            $dateRange = $this->build_date_range($start_date);

            $analytics_data['devices'] = $this->get_device_data($filterExpression, $dateRange);
            $analytics_data['referrals'] = $this->get_referral_data($filterExpression, $dateRange);

            // Calculate total users from analytics data if available
            $total_users = 0;
            if (isset($analytics_data['metrics'])) {
                $total_users = $analytics_data['metrics']['newUsers'] + $analytics_data['metrics']['returningUsers'];
            }
            $analytics_data['states'] = $this->get_state_data($filterExpression, $dateRange, $total_users);
        }

        // Build output
        ob_start();
        ?>
        <style>
            .analytics-container {
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

            /* Enhanced Analytics Grid */
            .enhanced-analytics-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 30px;
                margin: 40px 0;
            }

            .analytics-section {
                background: white;
                padding: 25px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .analytics-section h3 {
                font-size: 18px;
                margin: 0 0 20px 0;
                color: #333;
                border-bottom: 2px solid #00bcd4;
                padding-bottom: 10px;
            }

            /* Device Types - Pie Chart */
            .device-chart-container {
                display: flex;
                align-items: center;
                gap: 30px;
                flex-wrap: wrap;
            }

            #deviceChart {
                max-width: 200px;
                max-height: 200px;
            }

            .device-legend {
                flex: 1;
                min-width: 200px;
            }

            .legend-item {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 12px;
                padding: 8px;
                border-radius: 4px;
                transition: background-color 0.3s;
            }

            .legend-item:hover {
                background-color: #f8f9fa;
            }

            .legend-color {
                width: 16px;
                height: 16px;
                border-radius: 50%;
                flex-shrink: 0;
            }

            .legend-label {
                font-weight: 600;
                color: #333;
                min-width: 60px;
            }

            .legend-percent {
                font-weight: 700;
                color: #00bcd4;
                min-width: 40px;
            }

            .legend-users {
                color: #666;
                font-size: 12px;
            }

            /* Referral Sources */
            .referral-list {
                space-y: 10px;
            }

            .referral-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 0;
                border-bottom: 1px solid #f0f0f0;
            }

            .referral-item:last-child {
                border-bottom: none;
            }

            .referral-source {
                font-weight: 600;
                color: #333;
            }

            .referral-stats {
                display: flex;
                gap: 10px;
                align-items: center;
            }

            .referral-users {
                color: #00bcd4;
                font-weight: 600;
            }

            .referral-percent {
                color: #666;
                font-size: 12px;
            }


            /* States Section */
            .states-section {
                background: white;
                padding: 25px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .states-section h3 {
                font-size: 18px;
                margin: 0 0 20px 0;
                color: #333;
                border-bottom: 2px solid #00bcd4;
                padding-bottom: 10px;
            }

            .states-container {
                max-height: 400px;
                overflow-y: auto;
                border: 1px solid #f0f0f0;
                border-radius: 8px;
            }

            .state-item {
                display: flex;
                align-items: center;
                padding: 15px 20px;
                border-bottom: 1px solid #f8f9fa;
                transition: background-color 0.2s ease;
            }

            .state-item:hover {
                background-color: #f8f9fa;
            }

            .state-item:last-child {
                border-bottom: none;
            }

            .state-info {
                flex: 1;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 20px;
            }

            .state-name {
                font-weight: 600;
                color: #333;
                min-width: 120px;
                font-size: 14px;
            }

            .state-metrics {
                display: flex;
                gap: 30px;
                align-items: center;
                min-width: 200px;
            }

            .state-metric {
                text-align: center;
            }

            .state-metric-value {
                font-weight: 700;
                color: #00bcd4;
                font-size: 16px;
                display: block;
            }

            .state-metric-label {
                font-size: 11px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-top: 2px;
            }

            .state-bar-container {
                flex: 1;
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 150px;
            }

            .state-bar {
                flex: 1;
                height: 8px;
                background: #f0f0f0;
                border-radius: 4px;
                overflow: hidden;
                position: relative;
            }

            .state-bar-fill {
                height: 100%;
                background: linear-gradient(90deg, #00bcd4, #4dd0e1);
                border-radius: 4px;
                transition: width 0.6s ease;
                position: relative;
            }

            .state-bar-fill::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                animation: shimmer 2s infinite;
            }

            @keyframes shimmer {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }

            .state-percent {
                font-weight: 600;
                color: #00bcd4;
                font-size: 13px;
                min-width: 45px;
                text-align: right;
            }

            /* Responsive States */
            @media (max-width: 768px) {
                .state-item {
                    flex-direction: column;
                    align-items: stretch;
                    gap: 12px;
                }

                .state-info {
                    flex-direction: column;
                    align-items: stretch;
                    gap: 12px;
                }

                .state-metrics {
                    justify-content: space-around;
                    gap: 20px;
                }

                .state-bar-container {
                    min-width: auto;
                }
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
                            <div class="metric-value"><?php echo number_format($analytics_data['metrics']['newUsers']); ?></div>
                            <div class="metric-label">New Users</div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-value"><?php echo number_format($analytics_data['metrics']['returningUsers']); ?></div>
                            <div class="metric-label">Returning Users</div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-value"><?php echo $this->format_duration($analytics_data['metrics']['avgSessionDuration']); ?></div>
                            <div class="metric-label">Avg. Duration</div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-value"><?php echo round($analytics_data['metrics']['bounceRate'] * 100, 1); ?>%</div>
                            <div class="metric-label">Bounce Rate</div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-value"><?php echo round($analytics_data['metrics']['screenPageViewsPerSession'], 1); ?></div>
                            <div class="metric-label">Pages/Session</div>
                        </div>
                    </div>

                    <!-- Enhanced Analytics Sections -->
                    <div class="enhanced-analytics-grid">
                        <!-- Device Types -->
                        <div class="analytics-section">
                            <h3>Device Types</h3>
                            <?php if (!empty($analytics_data['devices'])): ?>
                                <div class="device-chart-container">
                                    <canvas id="deviceChart" width="300" height="300"></canvas>
                                    <div class="device-legend">
                                        <?php foreach ($analytics_data['devices'] as $index => $device): ?>
                                            <div class="legend-item">
                                                <span class="legend-color" style="background-color: <?php echo $this->get_chart_color($index); ?>"></span>
                                                <span class="legend-label"><?php echo esc_html($device['name']); ?></span>
                                                <span class="legend-percent"><?php echo round($device['percent'], 1); ?>%</span>
                                                <span class="legend-users">(<?php echo number_format($device['users']); ?> users)</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="no-data">No device data available</div>
                            <?php endif; ?>
                        </div>

                        <!-- Referral Sources -->
                        <div class="analytics-section">
                            <h3>Traffic Sources</h3>
                            <?php if (!empty($analytics_data['referrals'])): ?>
                                <div class="referral-list">
                                    <?php foreach ($analytics_data['referrals'] as $referral): ?>
                                        <div class="referral-item">
                                            <div class="referral-source"><?php echo esc_html($referral['source']); ?></div>
                                            <div class="referral-stats">
                                                <span class="referral-users"><?php echo number_format($referral['users']); ?> users</span>
                                                <span class="referral-percent">(<?php echo round($referral['percent'], 1); ?>%)</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-data">No referral data available</div>
                            <?php endif; ?>
                        </div>
                    </div>


                    <!-- States -->
                    <?php if (!empty($analytics_data['states'])): ?>
                        <div class="states-section">
                            <h3>Visitors by State</h3>
                            <div class="states-container">
                                <?php
                                $max_users = max(array_column($analytics_data['states'], 'users'));
                                foreach ($analytics_data['states'] as $state):
                                    $percent = $max_users > 0 ? ($state['users'] / $max_users) * 100 : 0;
                                ?>
                                    <div class="state-item">
                                        <div class="state-info">
                                            <div class="state-name"><?php echo esc_html($state['name']); ?></div>

                                            <div class="state-metrics">
                                                <div class="state-metric">
                                                    <span class="state-metric-value"><?php echo number_format($state['users']); ?></span>
                                                    <div class="state-metric-label">Users</div>
                                                </div>
                                                <div class="state-metric">
                                                    <span class="state-metric-value"><?php echo number_format($state['sessions']); ?></span>
                                                    <div class="state-metric-label">Sessions</div>
                                                </div>
                                            </div>

                                            <div class="state-bar-container">
                                                <div class="state-bar">
                                                    <div class="state-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                                                </div>
                                                <span class="state-percent"><?php echo number_format($state['percent'], 1); ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <script>
        // Analytics data for JavaScript
        const analyticsData = {
            devices: <?php echo json_encode($analytics_data['devices'] ?? []); ?>,
            colors: <?php echo !empty($analytics_data['devices']) ? json_encode(array_map(array($this, 'get_chart_color'), array_keys($analytics_data['devices']))) : '[]'; ?>
        };

        // Simple pie chart implementation
        function drawDeviceChart() {
            const canvas = document.getElementById('deviceChart');
            if (!canvas || !analyticsData.devices.length) return;

            const ctx = canvas.getContext('2d');
            const data = analyticsData.devices;
            const colors = analyticsData.colors;

            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = Math.min(centerX, centerY) - 10;

            let total = data.reduce((sum, item) => sum + parseFloat(item.percent), 0);
            let currentAngle = -Math.PI / 2; // Start at top

            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Draw pie slices
            data.forEach((item, index) => {
                const sliceAngle = (parseFloat(item.percent) / total) * 2 * Math.PI;

                // Draw slice
                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
                ctx.closePath();
                ctx.fillStyle = colors[index];
                ctx.fill();

                // Draw border
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.stroke();

                currentAngle += sliceAngle;
            });
        }




        // Initialize everything when page loads
        function initAnalytics() {
            drawDeviceChart();
        }

        // Load when ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAnalytics);
        } else {
            initAnalytics();
        }
        </script>

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

            // Initialize availableTitles to prevent null array_slice error
            $availableTitles = array();

            // Combine all tour titles for filtering
            $tour_titles = array_column($tours, 'title');

            // Date range
            $dateRange = new Google_Service_AnalyticsData_DateRange();
            $dateRange->setStartDate($start_date);
            $dateRange->setEndDate('today');

            // Metrics
            $sessions = new Google_Service_AnalyticsData_Metric();
            $sessions->setName('sessions');

            $newUsers = new Google_Service_AnalyticsData_Metric();
            $newUsers->setName('newUsers');

            $returningUsers = new Google_Service_AnalyticsData_Metric();
            $returningUsers->setName('activeUsers');

            $avgSessionDuration = new Google_Service_AnalyticsData_Metric();
            $avgSessionDuration->setName('averageSessionDuration');

            $bounceRate = new Google_Service_AnalyticsData_Metric();
            $bounceRate->setName('bounceRate');

            $screenPageViewsPerSession = new Google_Service_AnalyticsData_Metric();
            $screenPageViewsPerSession->setName('screenPageViewsPerSession');

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
            $request->setMetrics([$sessions, $newUsers, $returningUsers, $avgSessionDuration, $bounceRate, $screenPageViewsPerSession]); // Enhanced with engagement metrics
            if ($filterExpression) {
                $request->setDimensionFilter($filterExpression);
            }

            $response = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);

            $metrics = array(
                'sessions' => 0,
                'newUsers' => 0,
                'returningUsers' => 0,
                'avgSessionDuration' => 0,
                'bounceRate' => 0,
                'screenPageViewsPerSession' => 0
            );

            $rows = $response->getRows();
            if (!empty($rows)) {
                $row = $rows[0];
                $metricValues = $row->getMetricValues();
                $metrics['sessions'] = $metricValues[0]->getValue();        // Total Visits
                $metrics['newUsers'] = $metricValues[1]->getValue();        // New Users
                $metrics['returningUsers'] = $metricValues[2]->getValue();  // Returning Users
                $metrics['avgSessionDuration'] = $metricValues[3]->getValue(); // Avg Duration
                $metrics['bounceRate'] = $metricValues[4]->getValue();      // Bounce Rate
                $metrics['screenPageViewsPerSession'] = $metricValues[5]->getValue(); // Pages per Session
            }

            // Get states (using total users calculation from newUsers + returningUsers)
            $totalUsers = $metrics['newUsers'] + $metrics['returningUsers'];
            $states = $this->get_state_data($filterExpression, $dateRange, $totalUsers);

            return array(
                'metrics' => $metrics,
                'states' => $states,
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

            $newUsers = new Google_Service_AnalyticsData_Metric();
            $newUsers->setName('newUsers');

            $returningUsers = new Google_Service_AnalyticsData_Metric();
            $returningUsers->setName('activeUsers');

            $avgSessionDuration = new Google_Service_AnalyticsData_Metric();
            $avgSessionDuration->setName('averageSessionDuration');

            $bounceRate = new Google_Service_AnalyticsData_Metric();
            $bounceRate->setName('bounceRate');

            $screenPageViewsPerSession = new Google_Service_AnalyticsData_Metric();
            $screenPageViewsPerSession->setName('screenPageViewsPerSession');

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
            $request->setMetrics([$sessions, $newUsers, $returningUsers, $avgSessionDuration, $bounceRate, $screenPageViewsPerSession]); // Enhanced with engagement metrics
            $request->setDimensionFilter($filterExpression);

            $response = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);

            $metrics = array(
                'sessions' => 0,
                'newUsers' => 0,
                'returningUsers' => 0,
                'avgSessionDuration' => 0,
                'bounceRate' => 0,
                'screenPageViewsPerSession' => 0
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
                $metrics['sessions'] = $metricValues[0]->getValue();        // Total Visits
                $metrics['newUsers'] = $metricValues[1]->getValue();        // New Users
                $metrics['returningUsers'] = $metricValues[2]->getValue();  // Returning Users
                $metrics['avgSessionDuration'] = $metricValues[3]->getValue(); // Avg Duration
                $metrics['bounceRate'] = $metricValues[4]->getValue();      // Bounce Rate
                $metrics['screenPageViewsPerSession'] = $metricValues[5]->getValue(); // Pages per Session
            }

            // Get states (using total users calculation from newUsers + returningUsers)
            $totalUsers = $metrics['newUsers'] + $metrics['returningUsers'];
            $states = $this->get_state_data($filterExpression, $dateRange, $totalUsers);

            return array(
                'metrics' => $metrics,
                'states' => $states,
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
     * Get state data (US states)
     */
    private function get_state_data($filterExpression, $dateRange, $total_users) {
        try {
            $this->initialize_analytics();
            $PROPERTY_ID = "properties/491286260";

            $region = new Google_Service_AnalyticsData_Dimension();
            $region->setName('region');

            $users = new Google_Service_AnalyticsData_Metric();
            $users->setName('totalUsers');

            $sessions = new Google_Service_AnalyticsData_Metric();
            $sessions->setName('sessions');

            $request = new Google_Service_AnalyticsData_RunReportRequest();
            $request->setProperty($PROPERTY_ID);
            $request->setDateRanges([$dateRange]);
            $request->setDimensions([$region]);
            $request->setMetrics([$users, $sessions]);
            if ($filterExpression) {
                $request->setDimensionFilter($filterExpression);
            }

            // Filter for US only
            $countryFilter = new Google_Service_AnalyticsData_Filter();
            $stringFilter = new Google_Service_AnalyticsData_StringFilter();
            $stringFilter->setMatchType('EXACT');
            $stringFilter->setValue('United States');
            $countryFilter->setStringFilter($stringFilter);
            $countryFilter->setFieldName('country');

            $countryFilterExpression = new Google_Service_AnalyticsData_FilterExpression();
            $countryFilterExpression->setFilter($countryFilter);

            // Combine filters if we have tour filter
            if ($filterExpression) {
                $andGroup = new Google_Service_AnalyticsData_FilterExpressionList();
                $andGroup->setExpressions([$filterExpression, $countryFilterExpression]);
                $combinedFilter = new Google_Service_AnalyticsData_FilterExpression();
                $combinedFilter->setAndGroup($andGroup);
                $request->setDimensionFilter($combinedFilter);
            } else {
                $request->setDimensionFilter($countryFilterExpression);
            }

            $request->setLimit(15);

            $ordering = new Google_Service_AnalyticsData_OrderBy();
            $metricOrdering = new Google_Service_AnalyticsData_MetricOrderBy();
            $metricOrdering->setMetricName('totalUsers');
            $ordering->setMetric($metricOrdering);
            $ordering->setDesc(true);
            $request->setOrderBys([$ordering]);

            $statesResponse = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);

            $states = array();
            $rows = $statesResponse->getRows();
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $dims = $row->getDimensionValues();
                    $mets = $row->getMetricValues();

                    $stateName = $dims[0]->getValue();

                    // Handle empty values
                    if (empty($stateName) || $stateName === '(not set)') {
                        $stateName = 'Unknown';
                    }

                    $user_count = $mets[0]->getValue();
                    $session_count = $mets[1]->getValue();
                    $percent = $total_users > 0 ? ($user_count / $total_users) * 100 : 0;

                    $states[] = array(
                        'name' => $stateName,
                        'users' => $user_count,
                        'sessions' => $session_count,
                        'percent' => $percent
                    );
                }
            }

            return $states;

        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * Get chart colors for pie chart
     */
    public function get_chart_color($index) {
        $colors = array(
            '#00bcd4', // Teal
            '#4caf50', // Green
            '#ff9800', // Orange
            '#9c27b0', // Purple
            '#f44336', // Red
            '#2196f3', // Blue
            '#795548', // Brown
            '#607d8b'  // Blue Grey
        );
        return $colors[$index % count($colors)];
    }

    /**
     * Build filter expression for reuse
     */
    private function build_filter_expression($tours) {
        $tour_titles = array_column($tours, 'title');

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

        return $filterExpression;
    }

    /**
     * Build date range for reuse
     */
    private function build_date_range($start_date) {
        $dateRange = new Google_Service_AnalyticsData_DateRange();
        $dateRange->setStartDate($start_date);
        $dateRange->setEndDate('today');
        return $dateRange;
    }

    /**
     * Get device data
     */
    private function get_device_data($filterExpression, $dateRange) {
        try {
            $this->initialize_analytics();
            $PROPERTY_ID = "properties/491286260";

            $device = new Google_Service_AnalyticsData_Dimension();
            $device->setName('deviceCategory');

            $users = new Google_Service_AnalyticsData_Metric();
            $users->setName('totalUsers');

            $request = new Google_Service_AnalyticsData_RunReportRequest();
            $request->setProperty($PROPERTY_ID);
            $request->setDateRanges([$dateRange]);
            $request->setDimensions([$device]);
            $request->setMetrics([$users]);
            if ($filterExpression) {
                $request->setDimensionFilter($filterExpression);
            }

            $response = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);

            $devices = array();
            $total_users = 0;
            $rows = $response->getRows();

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $total_users += $row->getMetricValues()[0]->getValue();
                }

                foreach ($rows as $row) {
                    $dims = $row->getDimensionValues();
                    $mets = $row->getMetricValues();

                    $device_name = $dims[0]->getValue();
                    $user_count = $mets[0]->getValue();
                    $percent = $total_users > 0 ? ($user_count / $total_users) * 100 : 0;

                    $devices[] = array(
                        'name' => $device_name,
                        'users' => $user_count,
                        'percent' => $percent
                    );
                }
            }

            return $devices;

        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * Get referral data
     */
    private function get_referral_data($filterExpression, $dateRange) {
        try {
            $this->initialize_analytics();
            $PROPERTY_ID = "properties/491286260";

            $source = new Google_Service_AnalyticsData_Dimension();
            $source->setName('sessionSource');

            $users = new Google_Service_AnalyticsData_Metric();
            $users->setName('totalUsers');

            $request = new Google_Service_AnalyticsData_RunReportRequest();
            $request->setProperty($PROPERTY_ID);
            $request->setDateRanges([$dateRange]);
            $request->setDimensions([$source]);
            $request->setMetrics([$users]);
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

            $response = $this->analytics_service->properties->runReport($PROPERTY_ID, $request);

            $referrals = array();
            $total_users = 0;
            $rows = $response->getRows();

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $total_users += $row->getMetricValues()[0]->getValue();
                }

                foreach ($rows as $row) {
                    $dims = $row->getDimensionValues();
                    $mets = $row->getMetricValues();

                    $source_name = $dims[0]->getValue();
                    $user_count = $mets[0]->getValue();
                    $percent = $total_users > 0 ? ($user_count / $total_users) * 100 : 0;

                    if (empty($source_name) || $source_name === '(not set)') {
                        $source_name = 'Direct';
                    }

                    $referrals[] = array(
                        'source' => $source_name,
                        'users' => $user_count,
                        'percent' => $percent
                    );
                }
            }

            return $referrals;

        } catch (Exception $e) {
            return array();
        }
    }


}