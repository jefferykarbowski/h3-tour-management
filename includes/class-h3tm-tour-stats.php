<?php
/**
 * On-demand tour statistics.
 *
 * Adds a "Tour Stats" page under 3D Tours where an administrator can pull GA4
 * numbers for any tour, or every tour at once, for any date range without
 * waiting for the scheduled analytics email.
 *
 * Tours are matched by URL path rather than page title, because the titles
 * recorded by GA4 ("Arden Courts of Palm Harbor") rarely match the display
 * names stored in the tour metadata table ("Arden Palm Harbor"):
 *   - S3 tours with a tour_id     /tours/{tour_id}/
 *   - older S3 tours without one  /h3panos/{folder}/
 *   - h3vt_tour CPT tours         /tour/{slug}/
 */
class H3TM_Tour_Stats {

    const PROPERTY_ID = 'properties/491286260';
    const PAGE_SLUG   = 'h3tm-tour-stats';
    const CACHE_TTL   = HOUR_IN_SECONDS;

    /**
     * Matches the tour portion of a GA4 pagePath.
     */
    const PATH_PATTERN = '#^/(tours|h3panos|tour)/([^/?\#]+)#';

    private static $service = null;

    /**
     * Whether the last fetch was served from cache, and when it was fetched.
     */
    private $fetched_at = null;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'), 20);
        add_action('admin_post_h3tm_tour_stats_csv', array($this, 'download_csv'));
    }

    public function add_menu() {
        add_submenu_page(
            'h3-tour-management',
            __('Tour Stats', 'h3-tour-management'),
            __('Tour Stats', 'h3-tour-management'),
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'render_page'),
            1
        );
    }

    /* ------------------------------------------------------------------
     * Tour catalog and path resolution
     * ------------------------------------------------------------------ */

    /**
     * Every known tour keyed by its GA4 path prefix.
     *
     * @return array path => array('title' => string, 'path' => string)
     */
    public static function get_tour_catalog() {
        global $wpdb;

        $tours = array();
        $table = $wpdb->prefix . 'h3tm_tour_metadata';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $rows = $wpdb->get_results("SELECT tour_id, display_name, s3_folder FROM {$table}");
            foreach ((array) $rows as $row) {
                $path = self::metadata_path($row);
                if ($path) {
                    $tours[$path] = array('title' => trim($row->display_name), 'path' => $path);
                }
            }
        }

        $cpt_tours = get_posts(array(
            'post_type'   => 'h3vt_tour',
            'post_status' => 'publish',
            'numberposts' => -1,
        ));
        foreach ($cpt_tours as $post) {
            $path = '/tour/' . $post->post_name . '/';
            $tours[$path] = array('title' => trim(get_the_title($post)), 'path' => $path);
        }

        uasort($tours, function ($a, $b) {
            return strnatcasecmp($a['title'], $b['title']);
        });

        return $tours;
    }

    /**
     * GA4 path prefix for a row of the tour metadata table.
     *
     * @param object $row Row with tour_id and s3_folder.
     * @return string|null
     */
    private static function metadata_path($row) {
        if (!empty($row->tour_id)) {
            return '/tours/' . $row->tour_id . '/';
        }

        $folder = trim(preg_replace('#^tours/#', '', (string) $row->s3_folder), '/');
        return $folder === '' ? null : '/h3panos/' . $folder . '/';
    }

    /**
     * Resolve a value stored in the h3tm_tours user meta to a title and GA4 path.
     *
     * Accepts "cpt:{post_id}" markers and any identifier the metadata table
     * knows (tour_id, slug, s3_folder or display name).
     *
     * @param string $assigned Stored assignment value.
     * @return array|null array('title' => string, 'path' => string)
     */
    public static function resolve_assigned($assigned) {
        if (!is_string($assigned) || $assigned === '') {
            return null;
        }

        if (strpos($assigned, 'cpt:') === 0) {
            $post = get_post(absint(substr($assigned, 4)));
            if ($post && $post->post_type === 'h3vt_tour' && $post->post_status === 'publish') {
                return array('title' => trim(get_the_title($post)), 'path' => '/tour/' . $post->post_name . '/');
            }
            return null;
        }

        $tour_manager = new H3TM_Tour_Manager();
        $row = $tour_manager->get_tour_metadata($assigned);
        if (!$row) {
            return null;
        }

        $path = self::metadata_path($row);
        return $path ? array('title' => trim($row->display_name), 'path' => $path) : null;
    }

    /**
     * Reduce a GA4 pagePath to the tour prefix it belongs to.
     *
     * @param string $page_path Full pagePath reported by GA4.
     * @return string|null e.g. "/tours/20251229_180015_hq5cktvw/"
     */
    public static function tour_key_from_page_path($page_path) {
        if (preg_match(self::PATH_PATTERN, $page_path, $m)) {
            return '/' . $m[1] . '/' . $m[2] . '/';
        }
        return null;
    }

    /**
     * Whether a requested tour path is safe to use as a GA4 filter value.
     */
    private static function is_valid_tour_path($path) {
        return is_string($path) && preg_match('#^/(tours|h3panos|tour)/[^/?\#]+/$#', $path);
    }

    /* ------------------------------------------------------------------
     * Date ranges
     * ------------------------------------------------------------------ */

    /**
     * Turn the request parameters into current and previous date ranges.
     *
     * @return array
     */
    private function get_date_ranges($range, $from, $to) {
        $today = new DateTimeImmutable(current_time('Y-m-d'));
        $presets = array('7' => 7, '30' => 30, '90' => 90, '365' => 365);

        if ($range === 'custom') {
            $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
            $end   = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
            if (!$start || !$end) {
                $range = '30';
            } else {
                if ($start > $end) {
                    list($start, $end) = array($end, $start);
                }
                if ($end > $today) {
                    $end = $today;
                }
            }
        }

        if ($range === 'all') {
            $start = new DateTimeImmutable('2020-01-01');
            $end   = $today;
        } elseif ($range !== 'custom') {
            if (!isset($presets[$range])) {
                $range = '30';
            }
            $end   = $today;
            $start = $today->modify('-' . ($presets[$range] - 1) . ' days');
        }

        $days = (int) $start->diff($end)->days + 1;

        $previous = null;
        if ($range !== 'all') {
            $prev_end   = $start->modify('-1 day');
            $prev_start = $prev_end->modify('-' . ($days - 1) . ' days');
            $previous = array('start' => $prev_start->format('Y-m-d'), 'end' => $prev_end->format('Y-m-d'));
        }

        return array(
            'range'    => $range,
            'start'    => $start->format('Y-m-d'),
            'end'      => $end->format('Y-m-d'),
            'days'     => $days,
            'previous' => $previous,
        );
    }

    /* ------------------------------------------------------------------
     * GA4 access
     * ------------------------------------------------------------------ */

    private static function get_service() {
        if (self::$service) {
            return self::$service;
        }

        require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-config.php';

        $autoload = H3TM_Config::get_autoload_path();
        if (!file_exists($autoload)) {
            throw new Exception('The Google API client library is not installed.');
        }
        require_once $autoload;

        $credentials = H3TM_Config::get_credentials_path();
        if (!file_exists($credentials)) {
            throw new Exception('The Google Analytics service account credentials file was not found.');
        }

        $client = new Google_Client();
        if (!H3TM_Config::should_verify_ssl()) {
            $client->setHttpClient(new \GuzzleHttp\Client(array('verify' => false)));
        }
        $client->setApplicationName('H3VT Tour Stats');
        $client->setAuthConfig($credentials);
        $client->setScopes(array('https://www.googleapis.com/auth/analytics.readonly'));

        self::$service = new Google_Service_AnalyticsData($client);
        return self::$service;
    }

    /**
     * Run up to five report requests in one GA4 call, cached for an hour.
     *
     * @param array $requests Report request definitions (arrays).
     * @param bool  $refresh  Skip the cache.
     * @return array One array of rows per request; each row is
     *               array('dims' => string[], 'mets' => float[]).
     */
    private function run_reports(array $requests, $refresh = false) {
        $cache_key = 'h3tm_ts_' . md5(wp_json_encode($requests));

        if (!$refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached) && isset($cached['reports'])) {
                $this->fetched_at = $cached['fetched_at'];
                return $cached['reports'];
            }
        }

        $service = self::get_service();
        $batch = new Google_Service_AnalyticsData_BatchRunReportsRequest(array(
            'requests' => $requests,
        ));
        $response = $service->properties->batchRunReports(self::PROPERTY_ID, $batch);

        $reports = array();
        foreach ((array) $response->getReports() as $report) {
            $rows = array();
            foreach ((array) $report->getRows() as $row) {
                $dims = array();
                foreach ((array) $row->getDimensionValues() as $value) {
                    $dims[] = $value->getValue();
                }
                $mets = array();
                foreach ((array) $row->getMetricValues() as $value) {
                    $mets[] = (float) $value->getValue();
                }
                $rows[] = array('dims' => $dims, 'mets' => $mets);
            }
            $reports[] = $rows;
        }

        $this->fetched_at = time();
        set_transient($cache_key, array('reports' => $reports, 'fetched_at' => $this->fetched_at), self::CACHE_TTL);

        return $reports;
    }

    /**
     * GA4 date ranges, named so multi-range rows can be told apart.
     */
    private static function ga_date_ranges($dates, $with_previous) {
        $ranges = array(array('startDate' => $dates['start'], 'endDate' => $dates['end'], 'name' => 'current'));
        if ($with_previous && $dates['previous']) {
            $ranges[] = array('startDate' => $dates['previous']['start'], 'endDate' => $dates['previous']['end'], 'name' => 'previous');
        }
        return $ranges;
    }

    /**
     * pagePath filter for one tour, or for every tour URL when $path is null.
     */
    private static function path_filter($path = null) {
        $prefixes = $path ? array($path) : array('/tours/', '/h3panos/', '/tour/');

        $expressions = array();
        foreach ($prefixes as $prefix) {
            $expressions[] = array(
                'filter' => array(
                    'fieldName'    => 'pagePath',
                    'stringFilter' => array('matchType' => 'BEGINS_WITH', 'value' => $prefix),
                ),
            );
        }

        return count($expressions) === 1 ? $expressions[0] : array('orGroup' => array('expressions' => $expressions));
    }

    /**
     * Headline metrics, in the order used by the totals and table reports.
     */
    private static $total_metrics = array('screenPageViews', 'totalUsers', 'newUsers', 'sessions', 'averageSessionDuration');

    private static function metric_list(array $names) {
        return array_map(function ($name) {
            return array('name' => $name);
        }, $names);
    }

    /* ------------------------------------------------------------------
     * Data assembly
     * ------------------------------------------------------------------ */

    /**
     * Fetch everything shown on the page for one tour or all tours.
     *
     * @param string|null $path    Tour path, or null for all tours.
     * @param array       $dates   Output of get_date_ranges().
     * @param bool        $refresh Skip the cache.
     * @return array
     */
    private function get_stats($path, $dates, $refresh) {
        $filter = self::path_filter($path);
        $current_only = self::ga_date_ranges($dates, false);

        $requests = array(
            // 0: headline totals for this period and the one before.
            array(
                'dateRanges'      => self::ga_date_ranges($dates, true),
                'metrics'         => self::metric_list(self::$total_metrics),
                'dimensionFilter' => $filter,
            ),
            // 1: daily views and visitors for the trend chart.
            array(
                'dateRanges'      => $current_only,
                'dimensions'      => array(array('name' => 'date')),
                'metrics'         => self::metric_list(array('screenPageViews', 'totalUsers')),
                'dimensionFilter' => $filter,
                'limit'           => 10000,
            ),
        );

        if ($path === null) {
            // 2: per-page rows, rolled up into tours below.
            $requests[] = array(
                'dateRanges'      => self::ga_date_ranges($dates, true),
                'dimensions'      => array(array('name' => 'pagePath'), array('name' => 'pageTitle')),
                'metrics'         => self::metric_list(self::$total_metrics),
                'dimensionFilter' => $filter,
                'limit'           => 10000,
            );
        } else {
            // 2: devices, 3: traffic sources, 4: locations.
            $requests[] = array(
                'dateRanges'      => $current_only,
                'dimensions'      => array(array('name' => 'deviceCategory')),
                'metrics'         => self::metric_list(array('totalUsers')),
                'dimensionFilter' => $filter,
                'orderBys'        => array(array('metric' => array('metricName' => 'totalUsers'), 'desc' => true)),
            );
            $requests[] = array(
                'dateRanges'      => $current_only,
                'dimensions'      => array(array('name' => 'sessionSource')),
                'metrics'         => self::metric_list(array('sessions')),
                'dimensionFilter' => $filter,
                'orderBys'        => array(array('metric' => array('metricName' => 'sessions'), 'desc' => true)),
                'limit'           => 10,
            );
            $requests[] = array(
                'dateRanges'      => $current_only,
                'dimensions'      => array(array('name' => 'region'), array('name' => 'country')),
                'metrics'         => self::metric_list(array('totalUsers')),
                'dimensionFilter' => $filter,
                'orderBys'        => array(array('metric' => array('metricName' => 'totalUsers'), 'desc' => true)),
                'limit'           => 10,
            );
        }

        $reports = $this->run_reports($requests, $refresh);

        $stats = array(
            'totals' => $this->parse_totals($reports[0], (bool) $dates['previous']),
            'trend'  => $this->parse_trend($reports[1], $dates),
        );

        if ($path === null) {
            $stats['tours'] = $this->parse_tour_rows($reports[2]);
        } else {
            $stats['devices']   = $this->parse_simple($reports[2]);
            $stats['sources']   = $this->parse_simple($reports[3]);
            $stats['locations'] = $this->parse_locations($reports[4]);
        }

        return $stats;
    }

    private static function empty_totals() {
        return array_fill_keys(self::$total_metrics, 0);
    }

    /**
     * Totals rows carry a trailing dateRange dimension when two ranges are requested.
     */
    private function parse_totals($rows, $has_previous) {
        $totals = array(
            'current'  => self::empty_totals(),
            'previous' => $has_previous ? self::empty_totals() : null,
        );

        foreach ($rows as $row) {
            $range = isset($row['dims'][0]) ? $row['dims'][0] : 'current';
            $totals[$range] = array_combine(self::$total_metrics, $row['mets']);
        }

        return $totals;
    }

    /**
     * Bucket daily rows into days, weeks or months depending on the range length.
     */
    private function parse_trend($rows, $dates) {
        $by_day = array();
        foreach ($rows as $row) {
            $by_day[$row['dims'][0]] = $row['mets'];
        }

        if ($dates['days'] <= 62) {
            $unit = 'day';
        } elseif ($dates['days'] <= 366) {
            $unit = 'week';
        } else {
            $unit = 'month';
        }

        $start = new DateTimeImmutable($dates['start']);
        $end   = new DateTimeImmutable($dates['end']);

        // An open-ended range starts at the first day with data.
        if ($dates['range'] === 'all' && $by_day) {
            $start = new DateTimeImmutable(min(array_keys($by_day)));
        }

        $buckets = array();
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            if ($unit === 'day') {
                $key = $day->format('Y-m-d');
                $label = $day->format('M j');
            } elseif ($unit === 'week') {
                $monday = $day->modify('monday this week');
                $key = $monday->format('Y-m-d');
                $label = 'Week of ' . $monday->format('M j, Y');
            } else {
                $key = $day->format('Y-m');
                $label = $day->format('M Y');
            }

            if (!isset($buckets[$key])) {
                $buckets[$key] = array('label' => $label, 'views' => 0, 'visitors' => 0, 'days' => 0, 'full_days' => 1);
                if ($unit === 'week') {
                    $buckets[$key]['full_days'] = 7;
                } elseif ($unit === 'month') {
                    $buckets[$key]['full_days'] = (int) $day->format('t');
                }
            }
            $buckets[$key]['days']++;

            $ymd = $day->format('Ymd');
            if (isset($by_day[$ymd])) {
                $buckets[$key]['views'] += $by_day[$ymd][0];
                $buckets[$key]['visitors'] += $by_day[$ymd][1];
            }
        }

        return array('unit' => $unit, 'buckets' => array_values($buckets));
    }

    /**
     * Roll page-level rows up into one row per tour, with the previous period's views.
     *
     * Visitor counts are summed across a tour's pages, so a visitor who opened
     * two pages of the same tour is counted twice. Tours are almost always a
     * single page, so this is close enough for a per-tour table; the headline
     * totals come from a separate de-duplicated query.
     */
    private function parse_tour_rows($rows) {
        $catalog = self::get_tour_catalog();
        $tours = array();

        foreach ($rows as $row) {
            $key = self::tour_key_from_page_path($row['dims'][0]);
            if (!$key) {
                continue;
            }
            $range = isset($row['dims'][2]) ? $row['dims'][2] : 'current';

            if (!isset($tours[$key])) {
                $tours[$key] = array(
                    'path'     => $key,
                    'title'    => isset($catalog[$key]) ? $catalog[$key]['title'] : $row['dims'][1],
                    'ga_title' => $row['dims'][1],
                    'known'    => isset($catalog[$key]),
                    'current'  => self::empty_totals(),
                    'previous' => self::empty_totals(),
                    'duration_weight' => 0,
                );
            }

            $mets = array_combine(self::$total_metrics, $row['mets']);
            foreach (array('screenPageViews', 'totalUsers', 'newUsers', 'sessions') as $metric) {
                $tours[$key][$range][$metric] += $mets[$metric];
            }

            // Keep a session-weighted average duration across a tour's pages.
            if ($range === 'current') {
                $tours[$key]['duration_weight'] += $mets['averageSessionDuration'] * $mets['sessions'];
            }
        }

        foreach ($tours as &$tour) {
            $sessions = $tour['current']['sessions'];
            $tour['current']['averageSessionDuration'] = $sessions > 0 ? $tour['duration_weight'] / $sessions : 0;
            unset($tour['duration_weight']);
        }
        unset($tour);

        // Tours with no views this period only appear because of the previous period.
        $tours = array_filter($tours, function ($tour) {
            return $tour['current']['screenPageViews'] > 0 || $tour['current']['sessions'] > 0;
        });

        uasort($tours, function ($a, $b) {
            return $b['current']['screenPageViews'] <=> $a['current']['screenPageViews'];
        });

        return array_values($tours);
    }

    private function parse_simple($rows) {
        $items = array();
        $total = 0;
        foreach ($rows as $row) {
            $total += $row['mets'][0];
        }
        foreach ($rows as $row) {
            $items[] = array(
                'label'   => $row['dims'][0],
                'value'   => $row['mets'][0],
                'percent' => $total > 0 ? $row['mets'][0] / $total * 100 : 0,
            );
        }
        return $items;
    }

    private function parse_locations($rows) {
        $items = array();
        foreach ($rows as $row) {
            list($region, $country) = $row['dims'];
            $parts = array_filter(array($region, $country), function ($part) {
                return $part !== '' && $part !== '(not set)';
            });
            $label = $parts ? implode(', ', $parts) : __('Unknown', 'h3-tour-management');

            // A blank region and "(not set)" both collapse to the bare country.
            if (!isset($items[$label])) {
                $items[$label] = array('label' => $label, 'value' => 0);
            }
            $items[$label]['value'] += $row['mets'][0];
        }

        uasort($items, function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });

        return array_values($items);
    }

    /* ------------------------------------------------------------------
     * Request handling
     * ------------------------------------------------------------------ */

    private function read_request() {
        $tour = isset($_GET['tour']) ? wp_unslash($_GET['tour']) : '';
        if (!self::is_valid_tour_path($tour)) {
            $tour = '';
        }

        return array(
            'tour'    => $tour,
            'range'   => isset($_GET['range']) ? sanitize_key(wp_unslash($_GET['range'])) : '30',
            'from'    => isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '',
            'to'      => isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '',
            'refresh' => !empty($_GET['refresh']),
        );
    }

    private function page_url(array $args) {
        $args = array_merge(array('page' => self::PAGE_SLUG), $args);
        $args = array_filter($args, function ($value) {
            return $value !== '' && $value !== null;
        });
        return add_query_arg(array_map('rawurlencode', $args), admin_url('admin.php'));
    }

    /**
     * Stream the all-tours table as CSV.
     */
    public function download_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to download tour stats.', 'h3-tour-management'));
        }
        check_admin_referer('h3tm_tour_stats_csv');

        $request = $this->read_request();
        $dates = $this->get_date_ranges($request['range'], $request['from'], $request['to']);

        try {
            $stats = $this->get_stats(null, $dates, false);
        } catch (Exception $e) {
            wp_die(esc_html($e->getMessage()));
        }

        $filename = sprintf('tour-stats-%s-to-%s.csv', $dates['start'], $dates['end']);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        $header = array('Tour', 'URL', 'Views', 'Visitors', 'New visitors', 'Visits', 'Avg. visit length (seconds)');
        if ($dates['previous']) {
            $header[] = 'Views, previous period';
        }
        fputcsv($out, $header);

        foreach ($stats['tours'] as $tour) {
            $line = array(
                $tour['title'],
                home_url($tour['path']),
                (int) $tour['current']['screenPageViews'],
                (int) $tour['current']['totalUsers'],
                (int) $tour['current']['newUsers'],
                (int) $tour['current']['sessions'],
                (int) round($tour['current']['averageSessionDuration']),
            );
            if ($dates['previous']) {
                $line[] = (int) $tour['previous']['screenPageViews'];
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    /* ------------------------------------------------------------------
     * Rendering
     * ------------------------------------------------------------------ */

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $request = $this->read_request();
        $dates   = $this->get_date_ranges($request['range'], $request['from'], $request['to']);
        $catalog = self::get_tour_catalog();
        $path    = $request['tour'] !== '' ? $request['tour'] : null;

        $error = null;
        $stats = null;
        try {
            $stats = $this->get_stats($path, $dates, $request['refresh']);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        $base_args = array(
            'range' => $dates['range'],
            'from'  => $dates['range'] === 'custom' ? $dates['start'] : '',
            'to'    => $dates['range'] === 'custom' ? $dates['end'] : '',
        );

        if ($path) {
            $title = isset($catalog[$path]) ? $catalog[$path]['title'] : $path;
        } else {
            $title = __('All tours', 'h3-tour-management');
        }

        $this->render_styles();
        ?>
        <div class="wrap h3ts">
            <h1><?php esc_html_e('Tour Stats', 'h3-tour-management'); ?></h1>

            <form method="get" class="h3ts-controls" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">

                <label>
                    <span><?php esc_html_e('Tour', 'h3-tour-management'); ?></span>
                    <select name="tour">
                        <option value=""><?php esc_html_e('All tours', 'h3-tour-management'); ?></option>
                        <?php foreach ($this->group_catalog($catalog) as $group_label => $group) : ?>
                            <optgroup label="<?php echo esc_attr($group_label); ?>">
                                <?php foreach ($group as $tour) : ?>
                                    <option value="<?php echo esc_attr($tour['path']); ?>" <?php selected($request['tour'], $tour['path']); ?>>
                                        <?php echo esc_html($tour['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Date range', 'h3-tour-management'); ?></span>
                    <select name="range" class="h3ts-range">
                        <?php
                        $ranges = array(
                            '7'      => __('Last 7 days', 'h3-tour-management'),
                            '30'     => __('Last 30 days', 'h3-tour-management'),
                            '90'     => __('Last 90 days', 'h3-tour-management'),
                            '365'    => __('Last 12 months', 'h3-tour-management'),
                            'all'    => __('All time', 'h3-tour-management'),
                            'custom' => __('Custom dates', 'h3-tour-management'),
                        );
                        foreach ($ranges as $value => $label) :
                            ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($dates['range'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <span class="h3ts-custom" <?php echo $dates['range'] === 'custom' ? '' : 'hidden'; ?>>
                    <label>
                        <span><?php esc_html_e('From', 'h3-tour-management'); ?></span>
                        <input type="date" name="from" value="<?php echo esc_attr($dates['start']); ?>" max="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('To', 'h3-tour-management'); ?></span>
                        <input type="date" name="to" value="<?php echo esc_attr($dates['end']); ?>" max="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                    </label>
                </span>

                <button type="submit" class="button button-primary"><?php esc_html_e('Show stats', 'h3-tour-management'); ?></button>
            </form>

            <?php if ($error) : ?>
                <div class="notice notice-error"><p>
                    <?php
                    printf(
                        /* translators: %s: error message from Google Analytics */
                        esc_html__('Could not load stats from Google Analytics: %s', 'h3-tour-management'),
                        esc_html($error)
                    );
                    ?>
                </p></div>
            <?php else : ?>

                <div class="h3ts-heading">
                    <div>
                        <?php if ($path) : ?>
                            <a class="h3ts-back" href="<?php echo esc_url($this->page_url($base_args)); ?>">&larr; <?php esc_html_e('All tours', 'h3-tour-management'); ?></a>
                        <?php endif; ?>
                        <h2><?php echo esc_html($title); ?></h2>
                        <p class="h3ts-sub">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: 1: start date, 2: end date */
                                __('%1$s to %2$s', 'h3-tour-management'),
                                date_i18n(get_option('date_format'), strtotime($dates['start'])),
                                date_i18n(get_option('date_format'), strtotime($dates['end']))
                            ));
                            if ($dates['previous']) {
                                echo ' &middot; ' . esc_html__('compared with the previous', 'h3-tour-management') . ' ' . esc_html(sprintf(_n('%s day', '%s days', $dates['days'], 'h3-tour-management'), number_format_i18n($dates['days'])));
                            }
                            if ($path) {
                                echo ' &middot; <a href="' . esc_url(home_url($path)) . '" target="_blank" rel="noopener">' . esc_html__('Open tour', 'h3-tour-management') . '</a>';
                            }
                            ?>
                        </p>
                    </div>
                    <p class="h3ts-cache">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: time the data was fetched */
                            __('Data as of %s', 'h3-tour-management'),
                            wp_date(get_option('time_format'), $this->fetched_at)
                        ));
                        ?>
                        &middot;
                        <a href="<?php echo esc_url($this->page_url(array_merge($base_args, array('tour' => $request['tour'], 'refresh' => '1')))); ?>"><?php esc_html_e('Refresh', 'h3-tour-management'); ?></a>
                    </p>
                </div>

                <?php if ($path && strpos($path, '/tour/') === 0 && $stats['totals']['current']['screenPageViews'] == 0) : ?>
                    <div class="notice notice-info inline"><p>
                        <?php esc_html_e('No visits recorded. Tours on the newer /tour/ pages only appear here once their visits are sent to the same Google Analytics property as the other tours.', 'h3-tour-management'); ?>
                    </p></div>
                <?php endif; ?>

                <?php $this->render_totals($stats['totals']); ?>
                <?php $this->render_trend($stats['trend']); ?>

                <?php if ($path) : ?>
                    <div class="h3ts-grid">
                        <?php
                        $this->render_breakdown(__('Devices', 'h3-tour-management'), 'visitors', $stats['devices'], true);
                        $this->render_breakdown(__('Traffic sources', 'h3-tour-management'), 'visits', $stats['sources'], true);
                        $this->render_breakdown(__('Top locations', 'h3-tour-management'), 'visitors', $stats['locations'], false);
                        ?>
                    </div>
                <?php else : ?>
                    <?php $this->render_tour_table($stats['tours'], $dates, $base_args); ?>
                <?php endif; ?>

                <p class="h3ts-note">
                    <?php esc_html_e('Views count each time a tour page is opened. Visitors are unique people (one person on two devices counts twice). A visit is one session; it ends after 30 minutes of inactivity. Google Analytics can take up to a day to finish processing recent visits, so today\'s numbers may still rise.', 'h3-tour-management'); ?>
                </p>
            <?php endif; ?>
        </div>
        <script>
        (function () {
            var range = document.querySelector('.h3ts-range');
            var custom = document.querySelector('.h3ts-custom');
            if (range && custom) {
                range.addEventListener('change', function () {
                    custom.hidden = range.value !== 'custom';
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Split the catalog into S3 tours and /tour/ pages for the picker, adding
     * the URL to any name that appears more than once in a group.
     */
    private function group_catalog($catalog) {
        $groups = array(
            __('3D tours', 'h3-tour-management')               => array(),
            __('Tour pages (/tour/)', 'h3-tour-management') => array(),
        );
        $keys = array_keys($groups);

        foreach ($catalog as $tour) {
            $groups[strpos($tour['path'], '/tour/') === 0 ? $keys[1] : $keys[0]][] = $tour;
        }

        foreach ($groups as &$group) {
            $counts = array_count_values(array_map('strtolower', array_column($group, 'title')));
            foreach ($group as &$tour) {
                $tour['label'] = $counts[strtolower($tour['title'])] > 1
                    ? $tour['title'] . ' (' . $tour['path'] . ')'
                    : $tour['title'];
            }
            unset($tour);
        }
        unset($group);

        return array_filter($groups);
    }

    private function render_totals($totals) {
        $cards = array(
            'screenPageViews'        => __('Views', 'h3-tour-management'),
            'totalUsers'             => __('Visitors', 'h3-tour-management'),
            'newUsers'               => __('New visitors', 'h3-tour-management'),
            'sessions'               => __('Visits', 'h3-tour-management'),
            'averageSessionDuration' => __('Avg. visit length', 'h3-tour-management'),
        );
        ?>
        <div class="h3ts-cards">
            <?php foreach ($cards as $metric => $label) :
                $current = $totals['current'][$metric];
                $previous = $totals['previous'] ? $totals['previous'][$metric] : null;
                $value = $metric === 'averageSessionDuration' ? self::format_duration($current) : number_format_i18n($current);
                ?>
                <div class="h3ts-card">
                    <div class="h3ts-card-label"><?php echo esc_html($label); ?></div>
                    <div class="h3ts-card-value"><?php echo esc_html($value); ?></div>
                    <?php if ($totals['previous'] !== null) : ?>
                        <div class="h3ts-card-delta"><?php echo self::delta_html($current, $previous); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Inline SVG bar chart of views per day, week or month.
     */
    private function render_trend($trend) {
        $buckets = $trend['buckets'];
        if (!$buckets) {
            return;
        }

        $max = max(1, max(array_column($buckets, 'views')));
        $count = count($buckets);
        $width = 1000;
        $height = 180;
        $gap = $count > 60 ? 1 : 3;
        $bar = max(1, ($width - $gap * ($count - 1)) / $count);

        $headings = array(
            'day'   => __('Views per day', 'h3-tour-management'),
            'week'  => __('Views per week', 'h3-tour-management'),
            'month' => __('Views per month', 'h3-tour-management'),
        );
        ?>
        <div class="h3ts-panel">
            <h3><?php echo esc_html($headings[$trend['unit']]); ?></h3>
            <div class="h3ts-chart">
                <div class="h3ts-chart-max"><?php echo esc_html(number_format_i18n($max)); ?></div>
                <svg viewBox="0 0 <?php echo (int) $width; ?> <?php echo (int) $height; ?>" preserveAspectRatio="none" role="img"
                     aria-label="<?php echo esc_attr($headings[$trend['unit']]); ?>">
                    <?php foreach ($buckets as $i => $bucket) :
                        $h = $bucket['views'] > 0 ? max(2, $bucket['views'] / $max * $height) : 0;
                        $x = $i * ($bar + $gap);
                        $partial = $bucket['days'] < $bucket['full_days'];
                        $tip = sprintf(
                            /* translators: 1: period label, 2: views, 3: visitors */
                            __('%1$s: %2$s views, %3$s visitors', 'h3-tour-management'),
                            $bucket['label'],
                            number_format_i18n($bucket['views']),
                            number_format_i18n($bucket['visitors'])
                        );
                        if ($partial) {
                            /* translators: %s: number of days */
                            $tip .= ' ' . sprintf(_n('(partial, %s day)', '(partial, %s days)', $bucket['days'], 'h3-tour-management'), number_format_i18n($bucket['days']));
                        }
                        ?>
                        <rect<?php echo $partial ? ' class="h3ts-partial"' : ''; ?> x="<?php echo esc_attr(round($x, 2)); ?>" y="<?php echo esc_attr(round($height - $h, 2)); ?>"
                              width="<?php echo esc_attr(round($bar, 2)); ?>" height="<?php echo esc_attr(round($h, 2)); ?>">
                            <title><?php echo esc_html($tip); ?></title>
                        </rect>
                    <?php endforeach; ?>
                </svg>
                <div class="h3ts-chart-axis">
                    <span><?php echo esc_html($buckets[0]['label']); ?></span>
                    <?php if ($trend['unit'] !== 'day') : ?>
                        <span><?php esc_html_e('Lighter bars are partial periods. Hover a bar for exact numbers.', 'h3-tour-management'); ?></span>
                    <?php else : ?>
                        <span><?php esc_html_e('Hover a bar for exact numbers.', 'h3-tour-management'); ?></span>
                    <?php endif; ?>
                    <span><?php echo esc_html($buckets[$count - 1]['label']); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_breakdown($heading, $unit, $items, $show_percent) {
        $labels = array(
            '(direct)'   => __('Direct (typed or bookmarked link)', 'h3-tour-management'),
            '(not set)'  => __('Unknown', 'h3-tour-management'),
            'desktop'    => __('Desktop', 'h3-tour-management'),
            'mobile'     => __('Mobile', 'h3-tour-management'),
            'tablet'     => __('Tablet', 'h3-tour-management'),
        );
        $max = $items ? max(array_column($items, 'value')) : 0;
        ?>
        <div class="h3ts-panel">
            <h3><?php echo esc_html($heading); ?></h3>
            <?php if (!$items) : ?>
                <p class="h3ts-empty"><?php esc_html_e('No data for this period.', 'h3-tour-management'); ?></p>
            <?php else : ?>
                <ul class="h3ts-bars">
                    <?php foreach ($items as $item) :
                        $label = isset($labels[$item['label']]) ? $labels[$item['label']] : $item['label'];
                        ?>
                        <li>
                            <div class="h3ts-bar-row">
                                <span class="h3ts-bar-label"><?php echo esc_html($label); ?></span>
                                <span class="h3ts-bar-value">
                                    <?php
                                    $count = number_format_i18n($item['value']);
                                    echo esc_html($unit === 'visits'
                                        /* translators: %s: number of visits */
                                        ? sprintf(_n('%s visit', '%s visits', (int) $item['value'], 'h3-tour-management'), $count)
                                        /* translators: %s: number of visitors */
                                        : sprintf(_n('%s visitor', '%s visitors', (int) $item['value'], 'h3-tour-management'), $count));
                                    ?>
                                    <?php if ($show_percent) : ?>
                                        <em><?php echo esc_html(round($item['percent']) . '%'); ?></em>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="h3ts-bar-track"><div class="h3ts-bar-fill" style="width: <?php echo esc_attr($max > 0 ? round($item['value'] / $max * 100, 1) : 0); ?>%"></div></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_tour_table($tours, $dates, $base_args) {
        $csv_url = wp_nonce_url(
            add_query_arg(array_map('rawurlencode', array_filter(array_merge($base_args, array('action' => 'h3tm_tour_stats_csv')))), admin_url('admin-post.php')),
            'h3tm_tour_stats_csv'
        );
        ?>
        <div class="h3ts-panel">
            <div class="h3ts-panel-head">
                <h3>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: number of tours */
                        _n('%s tour with visits', '%s tours with visits', count($tours), 'h3-tour-management'),
                        number_format_i18n(count($tours))
                    ));
                    ?>
                </h3>
                <?php if ($tours) : ?>
                    <a class="button" href="<?php echo esc_url($csv_url); ?>"><?php esc_html_e('Download CSV', 'h3-tour-management'); ?></a>
                <?php endif; ?>
            </div>

            <?php if (!$tours) : ?>
                <p class="h3ts-empty"><?php esc_html_e('No tour visits were recorded in this period.', 'h3-tour-management'); ?></p>
            <?php else : ?>
                <table class="widefat striped h3ts-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tour', 'h3-tour-management'); ?></th>
                            <th class="num"><?php esc_html_e('Views', 'h3-tour-management'); ?></th>
                            <?php if ($dates['previous']) : ?>
                                <th class="num"><?php esc_html_e('vs. previous', 'h3-tour-management'); ?></th>
                            <?php endif; ?>
                            <th class="num"><?php esc_html_e('Visitors', 'h3-tour-management'); ?></th>
                            <th class="num"><?php esc_html_e('Visits', 'h3-tour-management'); ?></th>
                            <th class="num"><?php esc_html_e('Avg. visit length', 'h3-tour-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tours as $tour) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($this->page_url(array_merge($base_args, array('tour' => $tour['path'])))); ?>"><strong><?php echo esc_html($tour['title']); ?></strong></a>
                                    <div class="h3ts-path">
                                        <?php if ($tour['ga_title'] !== '' && strcasecmp($tour['ga_title'], $tour['title']) !== 0) : ?>
                                            <?php echo esc_html($tour['ga_title']); ?> &middot;
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url(home_url($tour['path'])); ?>" target="_blank" rel="noopener"><?php echo esc_html($tour['path']); ?></a>
                                        <?php if (!$tour['known']) : ?>
                                            &middot; <?php esc_html_e('not in the tour list', 'h3-tour-management'); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="num"><?php echo esc_html(number_format_i18n($tour['current']['screenPageViews'])); ?></td>
                                <?php if ($dates['previous']) : ?>
                                    <td class="num"><?php echo self::delta_html($tour['current']['screenPageViews'], $tour['previous']['screenPageViews']); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts ?></td>
                                <?php endif; ?>
                                <td class="num"><?php echo esc_html(number_format_i18n($tour['current']['totalUsers'])); ?></td>
                                <td class="num"><?php echo esc_html(number_format_i18n($tour['current']['sessions'])); ?></td>
                                <td class="num"><?php echo esc_html(self::format_duration($tour['current']['averageSessionDuration'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * Formatting helpers
     * ------------------------------------------------------------------ */

    public static function format_duration($seconds) {
        $seconds = (int) round($seconds);
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        }
        return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
    }

    /**
     * Percentage change badge. Returns escaped HTML.
     */
    private static function delta_html($current, $previous) {
        if ($previous === null) {
            return '';
        }
        if ($previous <= 0) {
            return $current > 0
                ? '<span class="h3ts-delta h3ts-flat">' . esc_html__('none before', 'h3-tour-management') . '</span>'
                : '<span class="h3ts-delta h3ts-flat">0%</span>';
        }

        $change = ($current - $previous) / $previous * 100;
        $rounded = (int) round($change);
        if ($rounded === 0) {
            return '<span class="h3ts-delta h3ts-flat">0%</span>';
        }

        $class = $rounded > 0 ? 'h3ts-up' : 'h3ts-down';
        $text = ($rounded > 0 ? '+' : '&minus;') . number_format_i18n(abs($rounded)) . '%';
        $title = sprintf(
            /* translators: %s: value in the previous period */
            __('Previous period: %s', 'h3-tour-management'),
            number_format_i18n(round($previous, 1))
        );

        return '<span class="h3ts-delta ' . esc_attr($class) . '" title="' . esc_attr($title) . '">' . $text . '</span>';
    }

    private function render_styles() {
        ?>
        <style>
            .h3ts { max-width: 1200px; }
            .h3ts-controls { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 14px 16px; margin: 16px 0 20px; }
            .h3ts-controls label { display: flex; flex-direction: column; gap: 4px; }
            .h3ts-controls label > span { font-size: 12px; font-weight: 600; color: #50575e; }
            .h3ts-controls select { min-width: 220px; max-width: 100%; }
            .h3ts-custom { display: flex; gap: 12px; }
            .h3ts-custom[hidden] { display: none; }
            .h3ts-heading { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
            .h3ts-heading h2 { margin: 4px 0 2px; font-size: 22px; }
            .h3ts-sub, .h3ts-cache { margin: 0; color: #646970; }
            .h3ts-back { text-decoration: none; font-size: 13px; }
            .h3ts-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 16px; }
            .h3ts-card { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 14px 16px; }
            .h3ts-card-label { font-size: 12px; font-weight: 600; color: #50575e; text-transform: uppercase; letter-spacing: .03em; }
            .h3ts-card-value { font-size: 28px; font-weight: 600; line-height: 1.3; color: #1d2327; font-variant-numeric: tabular-nums; }
            .h3ts-delta { font-size: 12px; font-weight: 600; white-space: nowrap; }
            .h3ts-up { color: #007a3d; }
            .h3ts-down { color: #b32d2e; }
            .h3ts-flat { color: #646970; font-weight: 400; }
            .h3ts-panel { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 14px 16px; margin-bottom: 16px; }
            .h3ts-panel h3 { margin: 0 0 12px; font-size: 14px; }
            .h3ts-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 12px; }
            .h3ts-panel-head h3 { margin: 0; }
            .h3ts-chart { position: relative; padding-top: 18px; }
            .h3ts-chart svg { display: block; width: 100%; height: 180px; border-bottom: 1px solid #dcdcde; }
            .h3ts-chart rect { fill: #2271b1; }
            .h3ts-chart rect.h3ts-partial { fill: #9ec2e6; }
            .h3ts-chart rect:hover { fill: #135e96; }
            .h3ts-chart-max { position: absolute; top: 0; left: 0; font-size: 11px; color: #646970; }
            .h3ts-chart-axis { display: flex; justify-content: space-between; font-size: 11px; color: #646970; margin-top: 4px; }
            .h3ts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
            .h3ts-grid .h3ts-panel { margin-bottom: 0; }
            .h3ts-bars { margin: 0; }
            .h3ts-bars li { margin-bottom: 10px; }
            .h3ts-bar-row { display: flex; justify-content: space-between; gap: 8px; font-size: 13px; }
            .h3ts-bar-value { color: #50575e; white-space: nowrap; font-variant-numeric: tabular-nums; }
            .h3ts-bar-value em { font-style: normal; color: #646970; margin-left: 4px; }
            .h3ts-bar-track { height: 6px; background: #f0f0f1; border-radius: 3px; margin-top: 4px; overflow: hidden; }
            .h3ts-bar-fill { height: 100%; background: #2271b1; }
            .h3ts-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
            .h3ts-path { font-size: 12px; color: #646970; margin-top: 2px; }
            .h3ts-path a { color: #646970; }
            .h3ts-empty { color: #646970; margin: 0; }
            .h3ts-note { color: #646970; font-size: 12px; max-width: 760px; margin-top: 16px; }
            @media (max-width: 782px) {
                .h3ts-controls select { min-width: 0; width: 100%; }
                .h3ts-controls label { flex: 1 1 100%; }
                .h3ts-table th:nth-child(4), .h3ts-table td:nth-child(4),
                .h3ts-table th:nth-child(6), .h3ts-table td:nth-child(6) { display: none; }
            }
        </style>
        <?php
    }
}
