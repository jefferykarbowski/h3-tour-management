<?php
// Fixed methods to add to the shortcode class

// Calculate start date based on range
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

// Format duration in seconds to human readable
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

// Copy of working email analytics get_report method
private function get_report($page_title, $start_date) {
    // Ensure analytics is initialized first
    if (!$this->analytics_service) {
        $this->initialize_analytics();
    }
    
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
    $request->setMetrics([$events, $sessions, $users, $avgSessionDuration]); // EXACT EMAIL ORDER
    $request->setDimensionFilter($filterExpression);
    
    return $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
}

// Copy of working email analytics report_results method
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

// Copy of working email analytics get_countries method
private function get_countries($page_title, $start_date) {
    // Ensure analytics is initialized first
    if (!$this->analytics_service) {
        $this->initialize_analytics();
    }
    
    $PROPERTY_ID = "properties/491286260";
    
    $dateRange = new Google_Service_AnalyticsData_DateRange();
    $dateRange->setStartDate($start_date);
    $dateRange->setEndDate("today");
    
    $users = new Google_Service_AnalyticsData_Metric();
    $users->setName("totalUsers");
    
    $sessions = new Google_Service_AnalyticsData_Metric();
    $sessions->setName("sessions");
    
    // Use country dimension
    $country = new Google_Service_AnalyticsData_Dimension();
    $country->setName("country");
    
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
    $request->setDimensions([$country]);
    $request->setMetrics([$users, $sessions]);
    $request->setOrderBys([$ordering]);
    $request->setDimensionFilter($filterExpression);
    $request->setLimit(10);
    
    return $this->analytics_service->properties->runReport($PROPERTY_ID, $request);
}

// Process country results for display
private function process_country_results($response) {
    $countries = array();
    $rows = $response->getRows();
    $total_users = 0;
    
    // Calculate total users first
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $metricValues = $row->getMetricValues();
            $total_users += $metricValues[0]->getValue();
        }
    }
    
    // Process country data
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
?>