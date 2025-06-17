<?php
/**
 * Shortcodes
 */
class H3TM_Shortcodes {
    
    public function __construct() {
        add_shortcode('tour_analytics_display', array($this, 'tour_analytics_display'));
    }
    
    /**
     * Tour analytics display shortcode
     */
    public function tour_analytics_display($atts) {
        if (!is_user_logged_in()) {
            return __('You must be logged in to view this feature.', 'h3-tour-management');
        }
        
        $tours_array = get_user_meta(get_current_user_id(), 'h3tm_tours', true);
        
        if (empty($tours_array)) {
            return __('You are not assigned to any tours, please contact your administrator.', 'h3-tour-management');
        }
        
        $root = realpath($_SERVER["DOCUMENT_ROOT"]);
        require_once $root . '/vendor/autoload.php';
        
        $credentials_file = $root . '/service-account-credentials.json';
        $client = new Google_Client();
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        $client->setAuthConfig($credentials_file);
        $client->useApplicationDefaultCredentials();
        
        if ($client->isAccessTokenExpired()) {
            $client->refreshTokenWithAssertion();
        }
        
        $arrayInfo = $client->getAccessToken();
        $accesstoken = $arrayInfo['access_token'];
        
        $tour_manager = new H3TM_Tour_Manager();
        $tours = array();
        
        foreach($tours_array as $i => $tour) {
            $media_array = $tour_manager->get_tour_media($tour);
            
            $tours[$i]['title'] = $tour_manager->get_tour_title($tour);
            $tours[$i]['url'] = site_url('/' . H3TM_TOUR_DIR . '/' . rawurlencode($tour));
            $tours[$i]['media'] = $media_array;
        }
        
        $threeYearsAgo = date('Y-m-d', strtotime('-3 years'));
        
        ob_start();
        ?>
        <style>
            #app, #app h1, #app a {color: #fff}
            #app table {color: #fff; background:transparent}
            #app table th {color:#fff}
            #app table td, #app table tr {background: transparent;}
            #app table tr:nth-child(odd) {background: #000;}
            
            #app select {background:transparent; color:#fff; border:none}
            #app select option {background: #212121 }
            
            [v-cloak], [v-cloak] > * { display:none }
            [v-cloak]::before { content: "loading…" }
            
            #app .text-center {text-align: center}
            #app .row {width: 100%; display: flex}
            #app .row .col:last-child {flex-grow:1}
            #app .row .col-4 {flex-grow: 1;}
            #app .row .col-6 {width:50%; padding:5px}
            #app .flex-grow {flex-grow: 1}
            #app select {font-size:1.4em}
            
            #app .loading {width: 100%; height: 100%; font-size:1.8em; font-weight: 700; text-align:center; }
            .loading span {
                width: 100px;
                height:100px;
                position: relative;
                top: 38px;
                display: inline-block;
                background-image: url("data:image/svg+xml,%3C%3Fxml version='1.0' encoding='utf-8'%3F%3E%3Csvg xml:space='preserve' viewBox='0 0 100 100' y='0' x='0' xmlns='http://www.w3.org/2000/svg' id='圖層_1' version='1.1' style='height: 100%25; width: 100%25; background: rgba(0, 0, 0, 0) none repeat scroll 0%25 0%25; shape-rendering: auto;' width='100px' height='100px'%3E%3Cg style='transform-origin: 50%25 50%25 0px; transform: rotate(0deg) scale(0.5);' class='ldl-scale'%3E%3Cg class='ldl-ani'%3E%3Cg class='ldl-layer'%3E%3Cg class='ldl-ani' style='transform-origin: 50px 50px 0px; opacity: 1; animation: 1.11111s linear -0.648148s infinite normal forwards running blink-984b6bd1-65cc-4b11-9d16-c6cb3f273e32;'%3E%3Cpath fill='%23333' d='M89.982 48.757h-.002a40.04 40.04 0 0 0-2.246-12.003l-.125-.332a40.721 40.721 0 0 0-.574-1.517l-.334-.774a33.907 33.907 0 0 0-.487-1.112l-.078-.168c-.166-.355-.344-.705-.531-1.075l-.187-.367c-.13-.249-.266-.495-.404-.744l-1.44-2.406a38.537 38.537 0 0 0-.666-1.001l-1.74-2.292L59.423 62.63h28.506l.406-1.328c.048-.151.095-.303.137-.451a40.634 40.634 0 0 0 .717-2.97 36.2 36.2 0 0 0 .187-.96c.112-.631.202-1.266.284-1.905l.042-.314c.029-.208.057-.415.077-.619.107-1.026.174-1.96.201-2.843l.02-1.137-.018-1.346z' style='fill: rgb(193, 39, 45);'%3E%3C/path%3E%3C/g%3E%3C/g%3E%3Cg class='ldl-layer'%3E%3Cg class='ldl-ani' style='transform-origin: 50px 50px 0px; opacity: 1; animation: 1.11111s linear -0.740741s infinite normal forwards running blink-984b6bd1-65cc-4b11-9d16-c6cb3f273e32;'%3E%3Cpath fill='%23333' d='M58.024 89.163l1.838-.429c.358-.091.714-.187 1.073-.288.58-.165 1.156-.346 1.728-.536l.178-.059c.285-.094.569-.189.847-.29.607-.22 1.204-.463 1.797-.711l.27-.11c.199-.081.398-.161.598-.251a41.757 41.757 0 0 0 2.686-1.315l.21-.115c.214-.116.404-.225.584-.331l1.229-.729a40.14 40.14 0 0 0 4.94-3.618 40.837 40.837 0 0 0 2.129-1.971l.121-.113a40.584 40.584 0 0 0 4.588-5.474l.121-.175c.208-.303.411-.61.688-1.037l2.006-3.501c.161-.315.324-.641.412-.844l1.029-2.795H43.77l14.254 24.692z' style='fill: rgb(193, 39, 45);'%3E%3C/path%3E%3C/g%3E%3C/g%3E%3Cg class='ldl-layer'%3E%3Cg class='ldl-ani' style='transform-origin: 50px 50px 0px; opacity: 1; animation: 1.11111s linear -0.833333s infinite normal forwards running blink-984b6bd1-65cc-4b11-9d16-c6cb3f273e32;'%3E%3Cpath fill='%23333' d='M20.094 76.527l.955 1.023c.106.115.21.229.318.341a41.011 41.011 0 0 0 2.12 2.022l.196.174c.208.186.418.37.631.549.49.412.995.806 1.503 1.192l.244.19c.169.131.337.263.51.388A40.702 40.702 0 0 0 28.934 84l1.219.724c.187.11.376.219.584.334l.337.178c.759.407 1.536.776 2.445 1.194.106.054.213.107.311.149a39.841 39.841 0 0 0 8.692 2.698l.157.032c.622.115 1.244.217 1.859.303l.564.066c.501.063 1.002.125 1.499.168l.183.017c.391.03.78.054 1.269.08l.359.017c.272.011.542.018.91.024l.697.009.854-.014c.246-.005.495-.011.736-.021l.432-.02c.391-.021.78-.045 1.321-.088l2.649-.483L34.347 51.84 20.094 76.527zm26.815 11.795v.002-.002z' style='fill: rgb(193, 39, 45);'%3E%3C/path%3E%3C/g%3E%3C/g%3E%3Cg class='ldl-layer'%3E%3Cg class='ldl-ani' style='transform-origin: 50px 50px 0px; opacity: 1; animation: 1.11111s linear -0.925926s infinite normal forwards running blink-984b6bd1-65cc-4b11-9d16-c6cb3f273e32;'%3E%3Cpath fill='%23333' d='M79.906 23.463l-.961-1.029a13.335 13.335 0 0 0-.326-.349 39.14 39.14 0 0 0-2.12-2.021l-.116-.103c-.228-.202-.454-.404-.685-.598a38.415 38.415 0 0 0-1.539-1.219l-.234-.183c-.16-.125-.32-.249-.489-.373a40.813 40.813 0 0 0-2.371-1.601l-2.148-1.239a40.129 40.129 0 0 0-11.527-4.055 39.84 39.84 0 0 0-3.868-.537 1.465 1.465 0 0 0-.22-.023l-2.452-.123-.05 1.115-.127-1.118-1.544.003c-.246.005-.494.011-.75.021l-.424.021a37.51 37.51 0 0 0-1.174.077l-2.908.296 21.78 37.725 14.253-24.687z' style='fill: rgb(193, 39, 45);'%3E%3C/path%3E%3C/g%3E%3C/g%3E%3Cg class='ldl-layer'%3E%3Cg class='ldl-ani' style='transform-origin: 50px 50px 0px; opacity: 1; animation: 1.11111s linear -1.01852s infinite normal forwards running blink-984b6bd1-65cc-4b11-9d16-c6cb3f273e32;'%3E%3Cpath fill='%23333' d='M41.974 10.828l-1.372.32c-.157.035-.314.069-.468.109a41.654 41.654 0 0 0-2.807.826l-.161.054a42.89 42.89 0 0 0-.85.291c-.613.222-1.221.468-1.819.718l-.263.107c-.195.08-.389.158-.581.244-.936.417-1.776.827-2.576 1.254l-2.137 1.235c-3.491 2.154-6.615 4.828-9.369 8.043-.285.334-.561.667-.844 1.022l-.214.276a36.63 36.63 0 0 0-.7.918l-.308.427c-.198.278-.386.54-.605.866l-1.832 2.984-.127.228c-.103.184-.204.37-.312.575l-.254.489c-.172.341-.343.682-.507 1.026l-1.207 2.676H56.23L41.974 10.828z' style='fill: rgb(193, 39, 45);'%3E%3C/path%3E%3C/g%3E%3C/g%3E%3Cg class='ldl-layer'%3E%3Cg class='ldl-ani' style='transform-origin: 50px 50px 0px; opacity: 1; animation: 1.11111s linear -1.11111s infinite normal forwards running blink-984b6bd1-65cc-4b11-9d16-c6cb3f273e32;'%3E%3Cpath fill='%23333' d='M12.071 37.361l-.404 1.322c-.048.155-.097.309-.139.457a39.505 39.505 0 0 0-.691 2.848l-.038.18c-.062.302-.124.604-.175.903a40.32 40.32 0 0 0-.284 1.906l-.042.309c-.029.208-.057.417-.077.62a38.802 38.802 0 0 0-.201 2.854l-.014.376c-.006.234-.006.441-.006.649l.02 1.444c.13 4.087.884 8.123 2.247 12.01l.137.37c.18.492.361.984.554 1.459l.281.653c.177.421.355.841.545 1.245l.08.172c.166.35.34.697.513 1.037l1.401 2.514c.136.226.273.454.432.705l.217.335c.216.331.43.661.663.993l1.678 2.412 21.81-37.774H12.071z' style='fill: rgb(193, 39, 45);'%3E%3C/path%3E%3C/g%3E%3C/g%3E%3Cmetadata xmlns:d='https://loading.io/stock/'%3E%3Cd:name%3Eshutter%3C/d:name%3E%3Cd:tags%3Eaperture,hole,camera,dslr,lens,exposure,lightness,fan,shot,shutter,camera%3C/d:tags%3E%3Cd:license%3Eby%3C/d:license%3E%3Cd:slug%3Exfkaqd%3C/d:slug%3E%3C/metadata%3E%3C/g%3E%3C/g%3E%3Cstyle id='blink-984b6bd1-65cc-4b11-9d16-c6cb3f273e32' data-anikit=''%3E@keyframes blink-984b6bd1-65cc-4b11-9d16-c6cb3f273e32 %7B 0%25 %7B opacity: 1; %7D 49.75%25 %7B opacity: 1; %7D 50.25%25 %7B opacity: 0; %7D 99.5%25 %7B opacity: 0; %7D 100%25 %7B opacity: 1; %7D%0A%7D%3C/style%3E%3C!-- %5Bldio%5D generated by https://loading.io/ --%3E%3C/svg%3E");
            }
            
            .google-visualization-table .gradient {background: none;}
            
            #app .data-row {font-size: 1.4em; margin-top: 20px; font-weight: 700}
            #app .data-row span {font-size: 2em; line-height: 2}
            
            .google-visualization-table-page-prev,
            .google-visualization-table-page-next,
            a.google-visualization-table-page-number.current,
            a.google-visualization-table-page-number {padding: 0 10px; font-size: 14px}
            
            .google-visualization-table-div-page [role="button"] {
                display: inline-block;
                cursor: pointer;
                margin-top: 2px;
                margin-bottom: 2px;
                font-family: "Arial Unicode MS", Arial, Helvetica;
                font-size: 14px;
                line-height: 21px;
            }
        </style>
        
        <script>
            (function (w, d, s, g, js, fs) {
                g = w.gapi || (w.gapi = {});
                g.analytics = {
                    q: [], ready: function (f) {
                        this.q.push(f);
                    }
                };
                js = d.createElement(s);
                fs = d.getElementsByTagName(s)[0];
                js.src = 'https://apis.google.com/js/platform.js';
                fs.parentNode.insertBefore(js, fs);
                js.onload = function () {
                    g.load('analytics');
                };
            }(window, document, 'script'));
        </script>
        
        <div id="app" class="tour-analytics" :key="componentKey" v-cloak>
            <div class="row">
                <div class="flex-grow">
                    <h1>{{selectedTour.title}}</h1>
                    <a :href="selectedTour.url">{{selectedTour.url}}</a>
                </div>
                <div>
                    <select name="startDate" id="startDate" v-model="startDate" @change="forceRerender()">
                        <option value="<?php echo $threeYearsAgo; ?>">All Time</option>
                        <option value="90daysAgo">90 Days</option>
                        <option value="60daysAgo">60 Days</option>
                        <option value="30daysAgo">Month</option>
                        <option value="7daysAgo">Week</option>
                        <option value="yesterday">Day</option>
                    </select>
                    
                    <select v-model="selectedTour" @change="forceRerender()" v-if="tours.length > 1">
                        <option v-for="tour in tours" v-bind:value="tour">
                            {{ tour.title }}
                        </option>
                    </select>
                </div>
            </div>
            
            <div class="row text-center">
                <div class="col"><img :src="selectedTour.url + '/thumbnail.png'" :alt="selectedTour.title"></div>
                <div class="col">
                    <div id="new_visitors_pie"></div>
                    
                    <div class="row data-row" v-if="(sessions != 0 || users != 0 || timeAverage != 0) && pieChartLoaded">
                        <div class="col-4">Total Visits <br><span>{{sessions}}</span></div>
                        <div class="col-4">Total Users<br><span>{{users}}</span></div>
                        <div class="col-4">Time Average<br><span>{{timeAverage}}</span></div>
                    </div>
                    <div class="loading" v-else>
                        <span></span>
                        Loading Please Wait...
                    </div>
                </div>
            </div>
            
            <div id="sessions_line_chart"></div>
            <div id="source_data_table"></div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/vue@2.6.14"></script>
        <script src="https://unpkg.com/vue-cookies@1.7.4/vue-cookies.js"></script>
        <script>
            let app = new Vue({
                el: '#app',
                data: {
                    selectedTour: [],
                    sessions: 0,
                    users: 0,
                    timeAverage: 0,
                    pieChartLoaded: false,
                    componentKey: 0,
                    startDate: '30daysAgo',
                    tours: <?php echo json_encode($tours); ?>,
                },
                watch: {
                    selectedTour: function () {
                        this.$cookies.set("selectedTour", this.selectedTour, -1)
                    }
                },
                created: function () {
                    if (this.tours.length === 1) {
                        this.selectedTour = this.tours[0]
                    } else {
                        if (this.$cookies.get('selectedTour')) {
                            this.selectedTour = this.$cookies.get('selectedTour')
                        } else {
                            this.selectedTour = this.tours[0]
                        }
                    }
                    
                    let _this = this
                    
                    gapi.analytics.ready(function () {
                        gapi.analytics.auth.authorize({
                            'serverAuth': {
                                'access_token': '<?php echo $accesstoken; ?>'
                            }
                        })
                        
                        _this.getAnalytics(_this)
                    })
                },
                methods: {
                    forceRerender() {
                        this.getAnalytics(this)
                        this.componentKey += 1
                    },
                    
                    getAnalytics(_this) {
                        let tourPageTitle = _this.selectedTour.title
                        
                        let sessions_report = new gapi.analytics.report.Data({
                            query: {
                                'ids': 'ga:491286260',
                                'start-date': _this.startDate,
                                'end-date': 'today',
                                'metrics': 'ga:sessions, ga:users, ga:avgSessionDuration',
                                'filters': 'ga:pageTitle==' + tourPageTitle.trim(),
                            }
                        })
                        sessions_report.on('success', function (response) {
                            _this.sessions = response.totalsForAllResults['ga:sessions']
                            _this.users = response.totalsForAllResults['ga:users']
                            _this.timeAverage = new Date(response.totalsForAllResults['ga:avgSessionDuration'] * 1000).toISOString().substr(14, 5)
                        })
                        sessions_report.execute()
                        
                        let sessions_line_chart_config = new gapi.analytics.googleCharts.DataChart({
                            query: {
                                'ids': 'ga:491286260',
                                'start-date': _this.startDate,
                                'end-date': 'today',
                                'metrics': 'ga:sessions,ga:users',
                                'filters': 'ga:pageTitle==' + tourPageTitle.trim(),
                                'dimensions': 'ga:date'
                            },
                            chart: {
                                'container': 'sessions_line_chart',
                                'type': 'LINE',
                                'options': {
                                    'animation': {
                                        startup: true,
                                        duration: 2000,
                                        easing: 'out',
                                    },
                                    backgroundColor: '#212121',
                                    colors: ['#c1272d', '#ffffff'],
                                    width: '100%',
                                    legend: {
                                        textStyle: {color: '#ffffff'}
                                    },
                                    titleTextStyle: {
                                        color: '#ffffff'
                                    },
                                    hAxis: {
                                        textStyle: {
                                            color: '#fff',
                                        },
                                    },
                                    vAxis: {
                                        textStyle: {
                                            color: '#fff',
                                        },
                                    },
                                }
                            }
                        })
                        sessions_line_chart_config.execute()
                        
                        let new_visitors_pie_config = new gapi.analytics.googleCharts.DataChart({
                            query: {
                                'ids': 'ga:491286260',
                                'start-date': _this.startDate,
                                'end-date': 'today',
                                'dimensions': 'ga:userType',
                                'metrics': 'ga:sessions',
                                'filters': 'ga:pageTitle==' + tourPageTitle.trim(),
                            },
                            chart: {
                                'container': 'new_visitors_pie',
                                'type': 'PIE',
                                'options': {
                                    'animation': {
                                        startup: true,
                                        duration: 2000,
                                        easing: 'out',
                                    },
                                    height: '140px',
                                    backgroundColor: '#212121',
                                    legend: {textStyle: {color: '#fff'}},
                                    pieSliceTextStyle: {color: '#212121'},
                                    colors: ['#c1272d', '#ffffff']
                                }
                            }
                        })
                        new_visitors_pie_config.on('success', function (response) {
                            _this.pieChartLoaded = true
                        })
                        new_visitors_pie_config.execute()
                        
                        let source_data_table_config = new gapi.analytics.googleCharts.DataChart({
                            reportType: 'ga',
                            query: {
                                'ids': 'ga:491286260',
                                'dimensions': 'ga:fullReferrer',
                                'metrics': 'ga:sessions,ga:users',
                                'start-date': _this.startDate,
                                'end-date': 'today',
                                'filters': 'ga:pageTitle==' + tourPageTitle.trim(),
                            },
                            chart: {
                                type: 'TABLE',
                                container: 'source_data_table',
                                page: 'enable',
                                sort: 'enable',
                                sortColumn: 1,
                                sortAscending: false,
                                width: '100%',
                                'animation': {
                                    startup: true,
                                    duration: 2000,
                                    easing: 'out',
                                },
                                allowHtml: true,
                            }
                        })
                        
                        source_data_table_config.on('success', function(response) {
                            jQuery('#source_data_table thead th:first').text('Source')
                        })
                        
                        source_data_table_config.execute()
                    }
                }
            })
        </script>
        <?php
        return ob_get_clean();
    }
}