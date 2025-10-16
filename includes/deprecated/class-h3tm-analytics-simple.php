<?php
/**
 * Simple Analytics Email Handler
 * Sends analytics emails without requiring Google API
 */
class H3TM_Analytics_Simple {
    
    /**
     * Send analytics email with sample data
     */
    public function send_analytics_email_simple($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $tours = get_user_meta($user_id, 'h3tm_tours', true);
        if (empty($tours)) {
            throw new Exception('User has no assigned tours');
        }
        
        $tour_manager = new H3TM_Tour_Manager();
        
        foreach ($tours as $tour) {
            $tour_title = trim($tour_manager->get_tour_title($tour));
            
            // Generate email with sample data (replace with actual data when API is available)
            $email_body = $this->generate_sample_analytics_email($tour, $tour_title);
            
            $to = $user->user_email;
            $subject = sprintf(__('Tour Analytics for %s', 'h3-tour-management'), $tour_title);
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            $from_name = get_option('h3tm_email_from_name', 'H3 Photography');
            $from_email = get_option('h3tm_email_from_address', get_option('admin_email'));
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
            
            $result = wp_mail($to, $subject, $email_body, $headers);
            
            if (!$result) {
                throw new Exception('Failed to send email. Please check your email configuration.');
            }
        }
        
        return true;
    }
    
    /**
     * Generate sample analytics email
     */
    private function generate_sample_analytics_email($tour, $tour_title) {
        // Sample data for demonstration
        $sample_data = array(
            'week' => array(
                'events' => rand(50, 200),
                'sessions' => rand(20, 80),
                'users' => rand(15, 60),
                'avg_duration' => rand(2, 8)
            ),
            'month' => array(
                'events' => rand(200, 800),
                'sessions' => rand(80, 320),
                'users' => rand(60, 240),
                'avg_duration' => rand(3, 10)
            ),
            'ninety_days' => array(
                'events' => rand(600, 2400),
                'sessions' => rand(240, 960),
                'users' => rand(180, 720),
                'avg_duration' => rand(3, 12)
            ),
            'all_time' => array(
                'events' => rand(2000, 10000),
                'sessions' => rand(800, 4000),
                'users' => rand(600, 3000),
                'avg_duration' => rand(4, 15)
            )
        );
        
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
                            <img src="' . site_url('/' . H3TM_TOUR_DIR . '/' . rawurlencode($tour) . '/thumbnail.png') . '" alt="' . esc_attr($tour) . '" width="300" style="height:auto;display:block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 36px 30px 42px 30px;font-family: Arial, sans-serif;">
                            <table role="presentation" style="width: 100%;border-collapse: collapse;border: 0;border-spacing: 0;font-family: Arial, sans-serif;">
                                <tr>
                                    <td style="padding: 0 0 36px 0;color: #153643;font-family: Arial, sans-serif;">
                                        <h1 style="font-size:24px;margin:0 0 20px 0;font-family:Arial,sans-serif;">' . esc_html($tour_title) . '</h1>
                                        
                                        <div style="background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin-bottom: 20px; border-radius: 4px;">
                                            <p style="margin: 0; color: #856404; font-size: 14px;">
                                                <strong>Note:</strong> This email contains sample data. To receive actual analytics data, please install the Google API client library.
                                            </p>
                                        </div>
                                        
                                        <table class="styled-table" style="font-family: sans-serif;border-collapse: collapse;margin: 25px 0;font-size: 0.9em;min-width: 400px;box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);width: 100%;color: #000;text-align: left;background-color: #f3f3f3;">
                                            <thead>
                                                <tr style="background-color: #000;color: #ffffff;">
                                                    <th style="padding: 12px 15px;"></th>
                                                    <th style="padding: 12px 15px;">Total Photos Viewed</th>
                                                    <th style="padding: 12px 15px;">Total Tour Views</th>
                                                    <th style="padding: 12px 15px;">Total Visitors</th>
                                                    <th style="padding: 12px 15px;">Avg. Time (min)</th>
                                                </tr>
                                            </thead>
                                            <tbody>';
        
        $periods = array(
            'week' => 'Last 7 Days',
            'month' => 'Last Month',
            'ninety_days' => 'Last 90 Days',
            'all_time' => 'All Time'
        );
        
        foreach ($periods as $key => $label) {
            $data = $sample_data[$key];
            $body .= '<tr style="border-bottom: 1px solid #dddddd;">
                        <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $label . '</td>
                        <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $data['events'] . '</td>
                        <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $data['sessions'] . '</td>
                        <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $data['users'] . '</td>
                        <td style="font-family: Arial, sans-serif;padding: 12px 15px;">' . $data['avg_duration'] . '</td>
                    </tr>';
        }
        
        $body .= '</tbody>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px;font-family: Arial, sans-serif;">
                                        <table role="presentation" style="width: 100%;border-collapse: collapse;border: 0;border-spacing: 0;font-family: Arial, sans-serif;">
                                            <tr>
                                                <td style="width: 260px;padding: 12px 15px;vertical-align: top;color: #153643;font-family: Arial, sans-serif;">
                                                    <p style="margin:0 0 25px 0;font-size:16px;line-height:24px;font-family:Arial,sans-serif;">New vs Returning Visitors</p>
                                                    <div style="background: #f0f0f0; padding: 20px; text-align: center; border-radius: 8px;">
                                                        <p style="margin: 0; font-size: 14px; color: #666;">
                                                            <strong>New:</strong> ' . rand(60, 80) . '%<br>
                                                            <strong>Returning:</strong> ' . rand(20, 40) . '%
                                                        </p>
                                                    </div>
                                                </td>
                                                <td style="width: 20px;padding: 12px 15px;font-size: 0;line-height: 0;font-family: Arial, sans-serif;">&nbsp;</td>
                                                <td style="width: 260px;padding: 12px 15px;vertical-align: top;color: #153643;font-family: Arial, sans-serif;">
                                                    <p style="margin:0 0 25px 0;font-size:16px;line-height:24px;font-family:Arial,sans-serif;">Top Referring Sites</p>
                                                    <table style="width: 100%; font-size: 14px;">
                                                        <tr><td style="padding: 5px 0;">google.com</td><td style="text-align: right;">' . rand(20, 50) . ' users</td></tr>
                                                        <tr><td style="padding: 5px 0;">facebook.com</td><td style="text-align: right;">' . rand(10, 30) . ' users</td></tr>
                                                        <tr><td style="padding: 5px 0;">direct</td><td style="text-align: right;">' . rand(15, 40) . ' users</td></tr>
                                                    </table>
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
}