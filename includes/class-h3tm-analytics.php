<?php
/**
 * Analytics functionality
 */
class H3TM_Analytics {
    
    private $analytics_service;
    
    public function __construct() {
        // Schedule cron hook
        add_action('h3tm_analytics_cron', array($this, 'send_scheduled_analytics'));
    }
    
    /**
     * Initialize Google Analytics
     */
    private function initialize_analytics() {
        $root = realpath($_SERVER["DOCUMENT_ROOT"]);
        $autoload_path = $root . '/vendor/autoload.php';
        
        if (!file_exists($autoload_path)) {
            throw new Exception('Google API client library not found. Please install it via Composer.');
        }
        
        require_once $autoload_path;
        
        $KEY_FILE_LOCATION = $root . '/service-account-credentials.json';
        if (!file_exists($KEY_FILE_LOCATION)) {
            throw new Exception('Google Analytics service account credentials file not found at: ' . $KEY_FILE_LOCATION);
        }
        
        $client = new Google_Client();
        
        // SSL fix for localhost development
        if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
            strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false) {
            $httpClient = new \GuzzleHttp\Client([
                'verify' => false
            ]);
            $client->setHttpClient($httpClient);
        }
        
        $client->setApplicationName("H3VT Analytics Reporting");
        $client->setAuthConfig($KEY_FILE_LOCATION);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        
        $this->analytics_service = new Google_Service_AnalyticsData($client);
        return $this->analytics_service;
    }
    
    /**
     * Send scheduled analytics emails
     */
    public function send_scheduled_analytics() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'h3tm_user_settings';
        
        // Get current date/time
        $now = current_time('mysql');
        $today = date('Y-m-d');
        $day_of_week = date('w'); // 0 = Sunday
        $day_of_month = date('j');
        
        // Get users based on their email frequency
        $users_to_email = array();
        
        // Daily users
        $daily_users = $wpdb->get_results(
            "SELECT user_id FROM $table_name 
             WHERE email_frequency = 'daily' 
             AND (last_email_sent IS NULL OR DATE(last_email_sent) < '$today')"
        );
        foreach ($daily_users as $user) {
            $users_to_email[] = $user->user_id;
        }
        
        // Weekly users (send on Sundays)
        if ($day_of_week == 0) {
            $weekly_users = $wpdb->get_results(
                "SELECT user_id FROM $table_name 
                 WHERE email_frequency = 'weekly' 
                 AND (last_email_sent IS NULL OR DATE(last_email_sent) < DATE_SUB('$today', INTERVAL 6 DAY))"
            );
            foreach ($weekly_users as $user) {
                $users_to_email[] = $user->user_id;
            }
        }
        
        // Monthly users (send on 1st of month)
        if ($day_of_month == 1) {
            $monthly_users = $wpdb->get_results(
                "SELECT user_id FROM $table_name 
                 WHERE email_frequency = 'monthly' 
                 AND (last_email_sent IS NULL OR DATE(last_email_sent) < DATE_SUB('$today', INTERVAL 1 MONTH))"
            );
            foreach ($monthly_users as $user) {
                $users_to_email[] = $user->user_id;
            }
        }
        
        // Also check users without settings (default to monthly on 1st)
        if ($day_of_month == 1) {
            $users_without_settings = get_users(array(
                'meta_key' => 'h3tm_tours',
                'meta_compare' => 'EXISTS'
            ));
            
            foreach ($users_without_settings as $user) {
                $has_settings = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE user_id = %d",
                    $user->ID
                ));
                
                if (!$has_settings && !in_array($user->ID, $users_to_email)) {
                    $users_to_email[] = $user->ID;
                }
            }
        }
        
        // Send emails
        if (!empty($users_to_email)) {
            $this->initialize_analytics();
            
            foreach ($users_to_email as $user_id) {
                try {
                    $this->send_analytics_for_user($user_id);
                    
                    // Update last email sent
                    $wpdb->query($wpdb->prepare(
                        "INSERT INTO $table_name (user_id, last_email_sent) 
                         VALUES (%d, %s) 
                         ON DUPLICATE KEY UPDATE last_email_sent = %s",
                        $user_id, $now, $now
                    ));
                } catch (Exception $e) {
                    error_log('H3TM Analytics Error for user ' . $user_id . ': ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Send analytics for a specific user
     */
    public function send_analytics_for_user($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $tours = get_user_meta($user_id, 'h3tm_tours', true);
        if (empty($tours)) {
            throw new Exception('User has no assigned tours');
        }
        
        // Initialize analytics service
        if (!$this->analytics_service) {
            $this->initialize_analytics();
        }
        
        $tour_manager = new H3TM_Tour_Manager();
        
        foreach ($tours as $tour) {
            $tour_title = trim($tour_manager->get_tour_title($tour));
            $email_body = $this->generate_analytics_email($tour, $tour_title);
            
            $to = $user->user_email;
            $subject = sprintf(__('Tour Analytics for %s', 'h3-tour-management'), $tour_title);
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            $from_name = get_option('h3tm_email_from_name', 'H3 Photography');
            $from_email = get_option('h3tm_email_from_address', get_option('admin_email'));
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
            
            wp_mail($to, $subject, $email_body, $headers);
        }
    }
    
    /**
     * Generate analytics email body
     */
    private function generate_analytics_email($tour, $tour_title) {
        // Get analytics data
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $weekStats = $this->get_report($tour_title, $weekAgo);
        $weekData = $this->report_results($weekStats);
        
        $monthAgo = date('Y-m-d', strtotime('-30 days'));
        $monthStats = $this->get_report($tour_title, $monthAgo);
        $monthData = $this->report_results($monthStats);
        
        $ninetyDaysAgo = date('Y-m-d', strtotime('-90 days'));
        $ninetyDayStats = $this->get_report($tour_title, $ninetyDaysAgo);
        $ninetyDayData = $this->report_results($ninetyDayStats);
        
        $threeYearsAgo = date('Y-m-d', strtotime('-3 years'));
        $allTimeStats = $this->get_report($tour_title, $threeYearsAgo);
        $allTimeData = $this->report_results($allTimeStats);
        
        $oneThousandDaysAgo = date('Y-m-d', strtotime('-999 days'));
        $referringSites = $this->get_referring_sites($tour_title, $oneThousandDaysAgo);
        $referrerTable = $this->referrer_results($referringSites);
        
        $usersResponse = $this->get_new_vs_returning_users($tour_title, $oneThousandDaysAgo);
        $usersChart = $this->new_vs_returning_users_chart($usersResponse);
        
        // Build email HTML
        $body = '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title></title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin:0;padding:0;">
    <table role="presentation" style="width: 100%;border-collapse: collapse;border: 0;border-spacing: 0;background: #ffffff;font-family: Arial, sans-serif;">
        <tr>
            <td align="center" style="padding: 12px 15px;font-family: Arial, sans-serif;">
                <table role="presentation" style="width: 602px;border-collapse: collapse;border: 1px solid #cccccc;border-spacing: 0;text-align: left;font-family: Arial, sans-serif;">
                    <tr>
                        <td align="center" style="padding: 40px 0 30px 0;background: #000000;font-family: Arial, sans-serif;">
                            <img src="' . site_url('/' . H3TM_TOUR_DIR . '/' . rawurlencode($tour) . '/thumbnail.png') . '" alt="' . esc_attr($tour) . '" width="300" >
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 36px 30px 42px 30px;font-family: Arial, sans-serif;">
                            <table role="presentation" style="width: 100%;border-collapse: collapse;border: 0;border-spacing: 0;font-family: Arial, sans-serif;">
                                <tr>
                                    <td style="padding: 0 0 36px 0;color: #153643;font-family: Arial, sans-serif;">
                                        <h1 style="font-size:24px;margin:0 0 20px 0;font-family:Arial,sans-serif;">' . esc_html($tour_title) . '</h1>
                                        <table class="styled-table" style="font-family: sans-serif;border-collapse: collapse;margin: 25px 0;font-size: 0.9em;min-width: 400px;box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);width: 100%;color: #000;text-align: left;background-color: #f3f3f3;">
                                            <thead>
                                                <tr style="background-color: #000;color: #ffffff;">
                                                    <th style="padding: 12px 15px;"></th>
                                                    <th style="padding: 12px 15px;">Total Photos Viewed</th>
                                                    <th style="padding: 12px 15px;">Total Tour Views</th>
                                                    <th style="padding: 12px 15px;">Total Visitors</th>
                                                    <th style="padding: 12px 15px;">Images Per Visitor</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr style="border-bottom: 1px solid #dddddd;">
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">Last 7 Days</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $weekData[0] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $weekData[1] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $weekData[2] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $weekData[3] . '</td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid #dddddd;">
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">Last Month</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $monthData[0] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $monthData[1] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $monthData[2] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $monthData[3] . '</td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid #dddddd;">
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">Last 90 Days</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $ninetyDayData[0] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $ninetyDayData[1] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $ninetyDayData[2] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $ninetyDayData[3] . '</td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid #dddddd;">
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">All Time</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $allTimeData[0] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $allTimeData[1] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $allTimeData[2] . '</td>
                                                    <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $allTimeData[3] . '</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px;font-family: Arial, sans-serif;">
                                        <table role="presentation" style="width: 100%;border-collapse: collapse;border: 0;border-spacing: 0;font-family: Arial, sans-serif;">
                                            <tr>
                                                <td style="width: 260px;padding: 12px 15px;vertical-align: top;color: #153643;font-family: Arial, sans-serif;">
                                                    <p style="margin:0 0 25px 0;font-size:16px;line-height:24px;font-family:Arial,sans-serif;">New vs Returning Visitors</p>
                                                    <p style="margin:0 0 25px 0;font-size:16px;line-height:24px;font-family:Arial,sans-serif;">' . $usersChart . '</p>
                                                </td>
                                                <td style="width: 20px;padding: 12px 15px;font-size: 0;line-height: 0;font-family: Arial, sans-serif;">&nbsp;</td>
                                                <td style="width: 260px;padding: 12px 15px;vertical-align: top;color: #153643;font-family: Arial, sans-serif;">
                                                    <p style="margin:0 0 25px 0;font-size:16px;line-height:24px;font-family:Arial,sans-serif;">Referring Traffic</p>
                                                    ' . $referrerTable . '
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;background: #c1272d;font-family: Arial, sans-serif;">
                            <table role="presentation" style="width:100%;border-collapse:collapse;border:0;border-spacing:0;font-size:9px;font-family:Arial,sans-serif;">
                                <tr>
                                    <td style="padding: 12px 15px;width: 50%;font-family: Arial, sans-serif;" align="left">
                                        <p style="margin:0;font-size:14px;line-height:16px;font-family:Arial,sans-serif;color:#ffffff;">
                                            <a href="https://h3vt.com/"><img width="150" src="https://h3vt.com/wp-content/uploads/2021/07/H3-Logo.png" style="text-align:center" /></a><br>
                                            &reg; H3 Photography ' . date("Y") . '<br>
                                        </p>
                                    </td>
                                    <td style="padding: 12px 15px;width: 50%;font-family: Arial, sans-serif; text-align: right;" align="right">
                                        <table role="presentation" style="border-collapse: collapse;border: 0;border-spacing: 0;font-family: Arial, sans-serif;  text-align: right;" align="right">
                                            <tr>
                                                <td style="padding: 0 0 0 10px;font-family: Arial, sans-serif;  text-align: right;" align="right">
                                                    <a href="https://h3vt.com/tour-analytics/" style="color:#ffffff;  text-align: right;">View More Analytics on our Site</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $body;
    }
    
    /**
     * Get analytics report
     */
    private function get_report($page_title, $start_date) {
        $PROPERTY_ID = "properties/491286260";
        
        $dateRange = new Google_Service_AnalyticsData_DateRange();
        $dateRange->setStartDate($start_date);
        $dateRange->setEndDate("today");
        
        $sessions = new Google_Service_AnalyticsData_Metric();
        $sessions->setName("sessions");
        
        $users = new Google_Service_AnalyticsData_Metric();
        $users->setName("totalUsers");
        
        $events = new Google_Service_AnalyticsData_Metric();
        $events->setName("eventCount");
        
        $avgSessionDuration = new Google_Service_AnalyticsData_Metric();
        $avgSessionDuration->setName("averageSessionDuration");
        
        $filter = new Google_Service_AnalyticsData_Filter();
        $stringFilter = new Google_Service_AnalyticsData_StringFilter();
        $stringFilter->setMatchType('EXACT');
        $stringFilter->setValue($page_title);
        $filter->setStringFilter($stringFilter);
        $filter->setFieldName('pageTitle');
        
        $filterExpression = new Google_Service_AnalyticsData_FilterExpression();
        $filterExpression->setFilter($filter);
        
        $request = new Google_Service_AnalyticsData_RunReportRequest();
        $request->setProperty($PROPERTY_ID);
        $request->setDateRanges([$dateRange]);
        $request->setMetrics([$events, $sessions, $users, $avgSessionDuration]);
        $request->setDimensionFilter($filterExpression);
        
        return $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
    }
    
    /**
     * Process report results
     */
    private function report_results($response) {
        $rows = $response->getRows();
        
        if (empty($rows)) {
            return [0, 0, 0, 0];
        }
        
        $row = $rows[0];
        $metricValues = $row->getMetricValues();
        
        $valueArray = array();
        foreach ($metricValues as $metricValue) {
            $valueArray[] = round($metricValue->getValue(), 1);
        }
        
        return $valueArray;
    }
    
    /**
     * Get referring sites
     */
    private function get_referring_sites($page_title, $start_date) {
        $PROPERTY_ID = "properties/491286260";
        
        $dateRange = new Google_Service_AnalyticsData_DateRange();
        $dateRange->setStartDate($start_date);
        $dateRange->setEndDate("today");
        
        $users = new Google_Service_AnalyticsData_Metric();
        $users->setName("totalUsers");
        
        $fullReferrer = new Google_Service_AnalyticsData_Dimension();
        $fullReferrer->setName("pageReferrer");
        
        $filter = new Google_Service_AnalyticsData_Filter();
        $stringFilter = new Google_Service_AnalyticsData_StringFilter();
        $stringFilter->setMatchType('EXACT');
        $stringFilter->setValue($page_title);
        $filter->setStringFilter($stringFilter);
        $filter->setFieldName('pageTitle');
        
        $filterExpression = new Google_Service_AnalyticsData_FilterExpression();
        $filterExpression->setFilter($filter);
        
        $ordering = new Google_Service_AnalyticsData_OrderBy();
        $metricOrdering = new Google_Service_AnalyticsData_MetricOrderBy();
        $metricOrdering->setMetricName("totalUsers");
        $ordering->setMetric($metricOrdering);
        $ordering->setDesc(true);
        
        $request = new Google_Service_AnalyticsData_RunReportRequest();
        $request->setProperty($PROPERTY_ID);
        $request->setDateRanges([$dateRange]);
        $request->setDimensions([$fullReferrer]);
        $request->setMetrics([$users]);
        $request->setOrderBys([$ordering]);
        $request->setDimensionFilter($filterExpression);
        $request->setLimit(10);
        
        return $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
    }
    
    /**
     * Format referrer results as table
     */
    private function referrer_results($response) {
        $referrerTable = '<table class="styled-table" style="font-family: sans-serif;border-collapse: collapse;margin: 25px 0;font-size: 0.9em;min-width: 400px;box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);width: 100%;color: #000;text-align: left;background-color: #f3f3f3;"><thead><tr style="background-color: #000;color: #ffffff;"><th style="padding: 12px 15px;">Referrer</th><th style="padding: 12px 15px;">Users</th></tr></thead><tbody>';
        
        $rows = $response->getRows();
        
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $referrerTable .= '<tr style="background-color: #f3f3f3;color: #000000;">';
                
                $dimensionValues = $row->getDimensionValues();
                foreach ($dimensionValues as $dimensionValue) {
                    $referrerTable .= '<td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . esc_html($dimensionValue->getValue()) . '</td>';
                }
                
                $metricValues = $row->getMetricValues();
                foreach ($metricValues as $metricValue) {
                    $referrerTable .= '<td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . esc_html($metricValue->getValue()) . '</td>';
                }
                
                $referrerTable .= '</tr>';
            }
        }
        
        $referrerTable .= '</tbody></table>';
        return $referrerTable;
    }
    
    /**
     * Get new vs returning users
     */
    private function get_new_vs_returning_users($page_title, $start_date) {
        $PROPERTY_ID = "properties/491286260";
        
        $dateRange = new Google_Service_AnalyticsData_DateRange();
        $dateRange->setStartDate($start_date);
        $dateRange->setEndDate("today");
        
        $users = new Google_Service_AnalyticsData_Metric();
        $users->setName("totalUsers");
        
        $userType = new Google_Service_AnalyticsData_Dimension();
        $userType->setName("newVsReturning");
        
        $filter = new Google_Service_AnalyticsData_Filter();
        $stringFilter = new Google_Service_AnalyticsData_StringFilter();
        $stringFilter->setMatchType('EXACT');
        $stringFilter->setValue($page_title);
        $filter->setStringFilter($stringFilter);
        $filter->setFieldName('pageTitle');
        
        $filterExpression = new Google_Service_AnalyticsData_FilterExpression();
        $filterExpression->setFilter($filter);
        
        $request = new Google_Service_AnalyticsData_RunReportRequest();
        $request->setProperty($PROPERTY_ID);
        $request->setDateRanges([$dateRange]);
        $request->setDimensions([$userType]);
        $request->setMetrics([$users]);
        $request->setDimensionFilter($filterExpression);
        
        return $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
    }
    
    /**
     * Generate new vs returning users chart
     */
    private function new_vs_returning_users_chart($response) {
        $dimensionArray = array();
        $metricsArray = array();
        
        $rows = $response->getRows();
        
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $dimensionValues = $row->getDimensionValues();
                foreach ($dimensionValues as $dimensionValue) {
                    $dimensionArray[] = $dimensionValue->getValue();
                }
                
                $metricValues = $row->getMetricValues();
                foreach ($metricValues as $metricValue) {
                    $metricsArray[] = $metricValue->getValue();
                }
            }
        }
        
        // Use QuickChart if available, otherwise use our fallback
        if (file_exists(ABSPATH . '/vendor/autoload.php')) {
            require_once ABSPATH . '/vendor/autoload.php';
        }
        
        if (!class_exists('QuickChart')) {
            require_once H3TM_PLUGIN_DIR . 'includes/class-quickchart.php';
        }
        
        $qc = new QuickChart();
        $qc->setConfig("{
            type: 'pie',
            data: {
              labels: " . json_encode($dimensionArray) . ",
              datasets: [{
                label: 'Users',
                data: " . json_encode($metricsArray) . ",
                backgroundColor: [
                    '#c1272d',
                    'black',
                ],
              }],
            },
            options: {
                plugins: {
                  legend: {
                      color: '#fff',
                      labels: {
                          color: '#fff',
                      },
                  },
                  datalabels: {
                    color: '#fff',
                    font: {
                        size: 24
                    }
                  }
                }
              }
          }");
        
        return '<img src="' . $qc->getUrl() . '" width="260" style="height:auto;display:block;" />';
    }
    
    /**
     * Send test email without analytics data
     */
    public function send_test_email($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $tours = get_user_meta($user_id, 'h3tm_tours', true);
        if (empty($tours)) {
            throw new Exception('User has no assigned tours');
        }
        
        $tour_manager = new H3TM_Tour_Manager();
        $tour = $tours[0]; // Use first tour for test
        $tour_title = trim($tour_manager->get_tour_title($tour));
        
        // Generate test email body
        $body = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Test Analytics Email</title>
</head>
<body style="margin:0;padding:0;">
    <table role="presentation" style="width: 100%;border-collapse: collapse;border: 0;border-spacing: 0;background: #ffffff;font-family: Arial, sans-serif;">
        <tr>
            <td align="center" style="padding: 12px 15px;">
                <table role="presentation" style="width: 602px;border-collapse: collapse;border: 1px solid #cccccc;border-spacing: 0;text-align: left;">
                    <tr>
                        <td align="center" style="padding: 40px 0 30px 0;background: #000000;">
                            <h1 style="color: #ffffff; margin: 0;">H3 Tour Analytics</h1>
                            <p style="color: #ffffff; margin: 10px 0 0 0;">Test Email</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 36px 30px 42px 30px;">
                            <h2 style="color: #153643;font-size:24px;margin:0 0 20px 0;">' . esc_html($tour_title) . '</h2>
                            <p style="margin:0 0 12px 0;font-size:16px;line-height:24px;color:#153643;">
                                This is a test email to verify that the analytics email system is working correctly.
                            </p>
                            <p style="margin:0 0 12px 0;font-size:16px;line-height:24px;color:#153643;">
                                <strong>User:</strong> ' . esc_html($user->display_name) . '<br>
                                <strong>Email:</strong> ' . esc_html($user->user_email) . '<br>
                                <strong>Assigned Tours:</strong> ' . count($tours) . '<br>
                                <strong>Test Tour:</strong> ' . esc_html($tour) . '
                            </p>
                            <p style="margin:20px 0 12px 0;font-size:14px;line-height:20px;color:#666666;">
                                When analytics emails are sent, they will include:
                            </p>
                            <ul style="margin:0 0 20px 0;font-size:14px;line-height:20px;color:#666666;">
                                <li>Total Photos Viewed</li>
                                <li>Total Tour Views</li>
                                <li>Total Visitors</li>
                                <li>Images Per Visitor</li>
                                <li>New vs Returning Visitors Chart</li>
                                <li>Top Referring Sites</li>
                            </ul>
                            <p style="margin:20px 0 12px 0;font-size:14px;line-height:20px;color:#153643;">
                                <strong>Note:</strong> This test email does not include actual analytics data. 
                                To receive analytics data, ensure that:
                            </p>
                            <ul style="margin:0 0 20px 0;font-size:14px;line-height:20px;color:#666666;">
                                <li>Google API client library is installed</li>
                                <li>Service account credentials file exists</li>
                                <li>GA4 is properly configured</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;background: #c1272d;">
                            <p style="margin:0;font-size:14px;line-height:16px;color:#ffffff;">
                                &reg; H3 Photography ' . date("Y") . '<br>
                                <a href="https://h3vt.com/" style="color:#ffffff;">h3vt.com</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        $to = $user->user_email;
        $subject = sprintf(__('[TEST] Tour Analytics for %s', 'h3-tour-management'), $tour_title);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $from_name = get_option('h3tm_email_from_name', 'H3 Photography');
        $from_email = get_option('h3tm_email_from_address', get_option('admin_email'));
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        
        // Debug logging
        error_log('H3TM Test Email Debug:');
        error_log('To: ' . $to);
        error_log('Subject: ' . $subject);
        error_log('From: ' . $from_email);
        error_log('Headers: ' . print_r($headers, true));
        
        $result = wp_mail($to, $subject, $body, $headers);
        
        if (!$result) {
            global $phpmailer;
            $error_msg = 'Failed to send email. ';
            
            // Check if PHPMailer has error info
            if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $error_msg .= 'PHPMailer Error: ' . $phpmailer->ErrorInfo;
            } else {
                $error_msg .= 'Please check your WordPress email configuration.';
            }
            
            error_log('H3TM Email Error: ' . $error_msg);
            throw new Exception($error_msg);
        }
        
        error_log('H3TM Test Email: Successfully sent to ' . $to);
        return true;
    }
}