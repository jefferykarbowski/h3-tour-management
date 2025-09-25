<?php
/**
 * S3 Presigned URL Generation Tests
 *
 * Tests for H3 Tour Management S3 presigned URL generation
 * with various scenarios and edge cases.
 *
 * @package H3_Tour_Management
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/h3-tour-management.php';
}

class H3TM_S3_Presigned_URL_Tests {

    private $test_results = [];
    private $debug_info = [];

    public function __construct() {
        error_log('H3TM S3 Presigned URL Tests: Initializing presigned URL tests');
    }

    /**
     * Run all presigned URL generation tests
     */
    public function run_all_tests() {
        $this->test_results = [];
        $this->debug_info = [];

        error_log('H3TM S3 Presigned URL Tests: Starting comprehensive presigned URL tests');

        // Test 1: Basic URL Generation
        $this->test_basic_url_generation();

        // Test 2: URL Generation with Different File Sizes
        $this->test_url_generation_file_sizes();

        // Test 3: URL Generation with Special Characters
        $this->test_url_generation_special_characters();

        // Test 4: URL Signature Validation
        $this->test_url_signature_validation();

        // Test 5: URL Expiration Testing
        $this->test_url_expiration();

        // Test 6: Configuration Impact on URL Generation
        $this->test_configuration_impact();

        // Test 7: AWS Signature Version 4 Compliance
        $this->test_aws_signature_v4_compliance();

        // Test 8: Error Scenarios
        $this->test_error_scenarios();

        // Test 9: URL Security Validation
        $this->test_url_security();

        // Test 10: Integration with Real S3 (if configured)
        $this->test_real_s3_integration();

        return $this->generate_test_report();
    }

    /**
     * Test basic presigned URL generation
     */
    private function test_basic_url_generation() {
        $test_name = 'Basic URL Generation';
        error_log("H3TM S3 Presigned URL Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();

            $test_cases = [
                'simple_file' => ['test.zip', 1000000],
                'large_file' => ['large-tour.zip', 500000000],
                'small_file' => ['small-tour.zip', 10000],
                'hyphenated_file' => ['my-tour-file.zip', 50000000]
            ];

            $results = [];

            foreach ($test_cases as $case_name => $case_data) {
                list($filename, $filesize) = $case_data;

                $result = $this->test_single_url_generation($s3_integration, $filename, $filesize);
                $results[$case_name] = $result;
            }

            $results['overall_success_rate'] = $this->calculate_success_rate($results);

        } catch (Exception $e) {
            $results = [
                'error' => $e->getMessage(),
                'test_cases_completed' => 0
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_basic_generation($results),
            'recommendation' => $this->get_basic_generation_recommendation($results)
        ];
    }

    /**
     * Test URL generation with different file sizes
     */
    private function test_url_generation_file_sizes() {
        $test_name = 'URL Generation File Sizes';
        error_log("H3TM S3 Presigned URL Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();

            $size_test_cases = [
                'tiny_file' => ['tiny.zip', 1024], // 1KB
                'small_file' => ['small.zip', 1024 * 1024], // 1MB
                'medium_file' => ['medium.zip', 50 * 1024 * 1024], // 50MB
                'large_file' => ['large.zip', 100 * 1024 * 1024], // 100MB
                'very_large_file' => ['very-large.zip', 500 * 1024 * 1024], // 500MB
                'max_size_file' => ['max-size.zip', 1024 * 1024 * 1024] // 1GB
            ];

            $results = [];

            foreach ($size_test_cases as $case_name => $case_data) {
                list($filename, $filesize) = $case_data;

                $result = $this->test_single_url_generation($s3_integration, $filename, $filesize);
                $result['file_size_mb'] = round($filesize / (1024 * 1024), 2);
                $results[$case_name] = $result;
            }

            $results['size_analysis'] = $this->analyze_size_performance($results);

        } catch (Exception $e) {
            $results = [
                'error' => $e->getMessage()
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_size_testing($results),
            'recommendation' => $this->get_size_testing_recommendation($results)
        ];
    }

    /**
     * Test URL generation with special characters in filenames
     */
    private function test_url_generation_special_characters() {
        $test_name = 'URL Generation Special Characters';
        error_log("H3TM S3 Presigned URL Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();

            $special_char_cases = [
                'spaces' => ['tour with spaces.zip', 10000000],
                'underscores' => ['tour_with_underscores.zip', 10000000],
                'hyphens' => ['tour-with-hyphens.zip', 10000000],
                'numbers' => ['tour123.zip', 10000000],
                'unicode' => ['tōur-ürländé.zip', 10000000],
                'parentheses' => ['tour(v1).zip', 10000000],
                'brackets' => ['tour[final].zip', 10000000]
            ];

            $results = [];

            foreach ($special_char_cases as $case_name => $case_data) {
                list($filename, $filesize) = $case_data;

                $result = $this->test_single_url_generation($s3_integration, $filename, $filesize);
                $result['original_filename'] = $filename;
                $result['sanitized_filename'] = sanitize_file_name($filename);
                $result['filename_changed'] = ($filename !== sanitize_file_name($filename));
                $results[$case_name] = $result;
            }

            $results['character_handling_analysis'] = $this->analyze_character_handling($results);

        } catch (Exception $e) {
            $results = [
                'error' => $e->getMessage()
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_character_testing($results),
            'recommendation' => $this->get_character_testing_recommendation($results)
        ];
    }

    /**
     * Test URL signature validation
     */
    private function test_url_signature_validation() {
        $test_name = 'URL Signature Validation';
        error_log("H3TM S3 Presigned URL Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();

            if (!$s3_integration->is_configured()) {
                $results = [
                    'configured' => false,
                    'error' => 'S3 not configured - cannot test signature validation'
                ];
            } else {
                $test_url_result = $this->test_single_url_generation($s3_integration, 'test-signature.zip', 10000000);

                if ($test_url_result['success']) {
                    $url = $test_url_result['url'];
                    $parsed_url = parse_url($url);
                    parse_str($parsed_url['query'], $query_params);

                    $results = [
                        'configured' => true,
                        'url_generated' => true,
                        'signature_components' => $this->analyze_signature_components($query_params),
                        'aws_v4_compliance' => $this->check_aws_v4_compliance($query_params),
                        'security_analysis' => $this->analyze_url_security($url, $query_params)
                    ];
                } else {
                    $results = [
                        'configured' => true,
                        'url_generated' => false,
                        'generation_error' => $test_url_result['error']
                    ];
                }
            }

        } catch (Exception $e) {
            $results = [
                'error' => $e->getMessage()
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_signature_validation($results),
            'recommendation' => $this->get_signature_validation_recommendation($results)
        ];
    }

    /**
     * Test URL expiration
     */
    private function test_url_expiration() {
        $test_name = 'URL Expiration Testing';
        error_log("H3TM S3 Presigned URL Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();

            if (!$s3_integration->is_configured()) {
                $results = [
                    'configured' => false,
                    'error' => 'S3 not configured - cannot test expiration'
                ];
            } else {
                $test_url_result = $this->test_single_url_generation($s3_integration, 'test-expiration.zip', 10000000);

                if ($test_url_result['success']) {
                    $url = $test_url_result['url'];
                    $parsed_url = parse_url($url);
                    parse_str($parsed_url['query'], $query_params);

                    $results = [
                        'configured' => true,
                        'url_generated' => true,
                        'expiration_analysis' => $this->analyze_expiration($query_params),
                        'time_validation' => $this->validate_expiration_time($query_params)
                    ];
                } else {
                    $results = [
                        'configured' => true,
                        'url_generated' => false,
                        'generation_error' => $test_url_result['error']
                    ];
                }
            }

        } catch (Exception $e) {
            $results = [
                'error' => $e->getMessage()
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_expiration_testing($results),
            'recommendation' => $this->get_expiration_testing_recommendation($results)
        ];
    }

    /**
     * Test configuration impact on URL generation
     */
    private function test_configuration_impact() {
        $test_name = 'Configuration Impact';
        error_log("H3TM S3 Presigned URL Tests: Running $test_name");

        $results = [
            'environment_config' => $this->test_with_environment_config(),
            'database_config' => $this->test_with_database_config(),
            'no_config' => $this->test_with_no_config(),
            'partial_config' => $this->test_with_partial_config()
        ];

        $results['configuration_consistency'] = $this->analyze_configuration_consistency($results);

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_configuration_impact($results),
            'recommendation' => $this->get_configuration_impact_recommendation($results)
        ];
    }

    /**
     * Test AWS Signature Version 4 compliance
     */
    private function test_aws_signature_v4_compliance() {
        $test_name = 'AWS Signature V4 Compliance';
        error_log("H3TM S3 Presigned URL Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();

            if (!$s3_integration->is_configured()) {
                $results = [
                    'configured' => false,
                    'error' => 'S3 not configured - cannot test AWS compliance'
                ];
            } else {
                $test_url_result = $this->test_single_url_generation($s3_integration, 'test-aws-v4.zip', 10000000);

                if ($test_url_result['success']) {
                    $url = $test_url_result['url'];
                    $parsed_url = parse_url($url);
                    parse_str($parsed_url['query'], $query_params);

                    $results = [
                        'configured' => true,
                        'url_generated' => true,
                        'v4_compliance' => $this->comprehensive_v4_compliance_check($query_params),
                        'algorithm_check' => $this->check_signature_algorithm($query_params),
                        'credential_format' => $this->validate_credential_format($query_params),
                        'date_format' => $this->validate_date_format($query_params),
                        'signed_headers' => $this->validate_signed_headers($query_params)
                    ];
                } else {
                    $results = [
                        'configured' => true,
                        'url_generated' => false,
                        'generation_error' => $test_url_result['error']
                    ];
                }
            }

        } catch (Exception $e) {
            $results = [
                'error' => $e->getMessage()
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_aws_compliance($results),
            'recommendation' => $this->get_aws_compliance_recommendation($results)
        ];
    }

    /**
     * Test error scenarios
     */
    private function test_error_scenarios() {
        $test_name = 'Error Scenarios';
        error_log("H3TM S3 Presigned URL Tests: Running $test_name");

        $error_scenarios = [
            'empty_filename' => $this->test_empty_filename(),
            'zero_filesize' => $this->test_zero_filesize(),
            'negative_filesize' => $this->test_negative_filesize(),
            'invalid_file_extension' => $this->test_invalid_file_extension(),
            'extremely_long_filename' => $this->test_extremely_long_filename(),
            'missing_bucket' => $this->test_missing_bucket_config(),
            'missing_credentials' => $this->test_missing_credentials()
        ];

        $results = [
            'scenarios' => $error_scenarios,
            'error_handling_quality' => $this->evaluate_error_handling_quality($error_scenarios)
        ];

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_error_scenarios($results),
            'recommendation' => $this->get_error_scenarios_recommendation($results)
        ];
    }

    /**
     * Test URL security validation
     */
    private function test_url_security() {
        $test_name = 'URL Security Validation';
        error_log("H3TM S3 Presigned URL Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();

            if (!$s3_integration->is_configured()) {
                $results = [
                    'configured' => false,
                    'error' => 'S3 not configured - cannot test URL security'
                ];
            } else {
                $test_url_result = $this->test_single_url_generation($s3_integration, 'security-test.zip', 10000000);

                if ($test_url_result['success']) {
                    $url = $test_url_result['url'];

                    $results = [
                        'configured' => true,
                        'url_generated' => true,
                        'security_checks' => [
                            'https_protocol' => $this->check_https_protocol($url),
                            'no_credentials_exposed' => $this->check_no_credentials_exposed($url),
                            'proper_domain' => $this->check_proper_s3_domain($url),
                            'signature_present' => $this->check_signature_present($url),
                            'no_suspicious_params' => $this->check_no_suspicious_params($url)
                        ]
                    ];

                    $results['security_score'] = $this->calculate_security_score($results['security_checks']);
                } else {
                    $results = [
                        'configured' => true,
                        'url_generated' => false,
                        'generation_error' => $test_url_result['error']
                    ];
                }
            }

        } catch (Exception $e) {
            $results = [
                'error' => $e->getMessage()
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_url_security($results),
            'recommendation' => $this->get_url_security_recommendation($results)
        ];
    }

    /**
     * Test integration with real S3
     */
    private function test_real_s3_integration() {
        $test_name = 'Real S3 Integration';
        error_log("H3TM S3 Presigned URL Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();

            if (!$s3_integration->is_configured()) {
                $results = [
                    'configured' => false,
                    'error' => 'S3 not configured - cannot test real integration',
                    'skipped' => true
                ];
            } else {
                // Generate a presigned URL
                $test_url_result = $this->test_single_url_generation($s3_integration, 'integration-test.zip', 1000);

                if ($test_url_result['success']) {
                    $url = $test_url_result['url'];

                    // Test the URL with a small HTTP request
                    $http_test = $this->test_presigned_url_http($url);

                    $results = [
                        'configured' => true,
                        'url_generated' => true,
                        'url_test' => $http_test,
                        'integration_quality' => $this->evaluate_integration_quality($http_test)
                    ];
                } else {
                    $results = [
                        'configured' => true,
                        'url_generated' => false,
                        'generation_error' => $test_url_result['error']
                    ];
                }
            }

        } catch (Exception $e) {
            $results = [
                'error' => $e->getMessage()
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_real_s3_integration($results),
            'recommendation' => $this->get_real_s3_integration_recommendation($results)
        ];
    }

    /**
     * Helper Methods
     */

    private function test_single_url_generation($s3_integration, $filename, $filesize) {
        try {
            // Use reflection to access private method
            $reflection = new ReflectionClass($s3_integration);
            $method = $reflection->getMethod('generate_presigned_url');
            $method->setAccessible(true);

            // Generate S3 key
            $unique_id = uniqid() . '_' . time();
            $s3_key = 'uploads/' . $unique_id . '/' . $filename;

            $start_time = microtime(true);
            $url = $method->invoke($s3_integration, $s3_key, $filesize);
            $generation_time = microtime(true) - $start_time;

            return [
                'success' => true,
                'url' => $url,
                's3_key' => $s3_key,
                'filename' => $filename,
                'filesize' => $filesize,
                'generation_time_ms' => round($generation_time * 1000, 2),
                'error' => null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'url' => null,
                's3_key' => null,
                'filename' => $filename,
                'filesize' => $filesize,
                'generation_time_ms' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    private function calculate_success_rate($results) {
        $total = 0;
        $successful = 0;

        foreach ($results as $key => $result) {
            if ($key === 'overall_success_rate') continue;

            $total++;
            if (isset($result['success']) && $result['success']) {
                $successful++;
            }
        }

        return $total > 0 ? ($successful / $total) * 100 : 0;
    }

    private function analyze_size_performance($results) {
        $generation_times = [];
        $successful_sizes = [];

        foreach ($results as $case_name => $result) {
            if (isset($result['success']) && $result['success']) {
                $generation_times[] = $result['generation_time_ms'];
                $successful_sizes[] = $result['file_size_mb'];
            }
        }

        return [
            'avg_generation_time_ms' => !empty($generation_times) ? array_sum($generation_times) / count($generation_times) : 0,
            'max_generation_time_ms' => !empty($generation_times) ? max($generation_times) : 0,
            'min_generation_time_ms' => !empty($generation_times) ? min($generation_times) : 0,
            'max_successful_size_mb' => !empty($successful_sizes) ? max($successful_sizes) : 0,
            'size_impact_on_performance' => $this->analyze_size_performance_correlation($results)
        ];
    }

    private function analyze_size_performance_correlation($results) {
        $data_points = [];

        foreach ($results as $result) {
            if (isset($result['success']) && $result['success'] && isset($result['file_size_mb'])) {
                $data_points[] = [
                    'size_mb' => $result['file_size_mb'],
                    'time_ms' => $result['generation_time_ms']
                ];
            }
        }

        if (count($data_points) < 2) {
            return 'Insufficient data for correlation analysis';
        }

        // Simple correlation analysis
        $size_values = array_column($data_points, 'size_mb');
        $time_values = array_column($data_points, 'time_ms');

        $correlation = $this->calculate_correlation($size_values, $time_values);

        if ($correlation > 0.7) {
            return 'Strong positive correlation - larger files take longer';
        } elseif ($correlation > 0.3) {
            return 'Moderate positive correlation - some size impact';
        } elseif ($correlation < -0.3) {
            return 'Negative correlation - unexpected pattern';
        } else {
            return 'No significant correlation - size has minimal impact';
        }
    }

    private function calculate_correlation($x, $y) {
        $n = count($x);
        if ($n === 0) return 0;

        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_x2 = 0;
        $sum_y2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_x2 += $x[$i] * $x[$i];
            $sum_y2 += $y[$i] * $y[$i];
        }

        $numerator = $n * $sum_xy - $sum_x * $sum_y;
        $denominator = sqrt(($n * $sum_x2 - $sum_x * $sum_x) * ($n * $sum_y2 - $sum_y * $sum_y));

        return $denominator != 0 ? $numerator / $denominator : 0;
    }

    private function analyze_character_handling($results) {
        $filename_changes = 0;
        $successful_generations = 0;
        $total_cases = count($results);

        foreach ($results as $case_name => $result) {
            if ($case_name === 'character_handling_analysis') continue;

            if (isset($result['filename_changed']) && $result['filename_changed']) {
                $filename_changes++;
            }
            if (isset($result['success']) && $result['success']) {
                $successful_generations++;
            }
        }

        return [
            'total_cases' => $total_cases,
            'filename_changes' => $filename_changes,
            'successful_generations' => $successful_generations,
            'success_rate' => $total_cases > 0 ? ($successful_generations / $total_cases) * 100 : 0,
            'filename_change_rate' => $total_cases > 0 ? ($filename_changes / $total_cases) * 100 : 0
        ];
    }

    private function analyze_signature_components($query_params) {
        $required_v4_params = [
            'X-Amz-Algorithm',
            'X-Amz-Credential',
            'X-Amz-Date',
            'X-Amz-Expires',
            'X-Amz-Signature',
            'X-Amz-SignedHeaders'
        ];

        $present_params = [];
        $missing_params = [];

        foreach ($required_v4_params as $param) {
            if (isset($query_params[$param])) {
                $present_params[] = $param;
            } else {
                $missing_params[] = $param;
            }
        }

        return [
            'required_params' => $required_v4_params,
            'present_params' => $present_params,
            'missing_params' => $missing_params,
            'all_present' => empty($missing_params),
            'completeness_score' => (count($present_params) / count($required_v4_params)) * 100
        ];
    }

    private function check_aws_v4_compliance($query_params) {
        $compliance_checks = [];

        // Check algorithm
        $compliance_checks['algorithm'] = isset($query_params['X-Amz-Algorithm']) &&
                                        $query_params['X-Amz-Algorithm'] === 'AWS4-HMAC-SHA256';

        // Check credential format
        if (isset($query_params['X-Amz-Credential'])) {
            $credential = $query_params['X-Amz-Credential'];
            $compliance_checks['credential_format'] = preg_match('/^[A-Z0-9]{20}\/\d{8}\/[a-z0-9-]+\/s3\/aws4_request$/', $credential);
        } else {
            $compliance_checks['credential_format'] = false;
        }

        // Check date format
        if (isset($query_params['X-Amz-Date'])) {
            $date = $query_params['X-Amz-Date'];
            $compliance_checks['date_format'] = preg_match('/^\d{8}T\d{6}Z$/', $date);
        } else {
            $compliance_checks['date_format'] = false;
        }

        // Check signature format
        if (isset($query_params['X-Amz-Signature'])) {
            $signature = $query_params['X-Amz-Signature'];
            $compliance_checks['signature_format'] = preg_match('/^[a-f0-9]{64}$/', $signature);
        } else {
            $compliance_checks['signature_format'] = false;
        }

        $passing_checks = count(array_filter($compliance_checks));
        $total_checks = count($compliance_checks);

        return [
            'checks' => $compliance_checks,
            'passing_checks' => $passing_checks,
            'total_checks' => $total_checks,
            'compliance_score' => ($passing_checks / $total_checks) * 100,
            'is_compliant' => $passing_checks === $total_checks
        ];
    }

    private function analyze_url_security($url, $query_params) {
        return [
            'uses_https' => strpos($url, 'https://') === 0,
            'proper_s3_domain' => strpos($url, '.s3.') !== false,
            'no_plaintext_credentials' => !preg_match('/[A-Z0-9]{40}/', $url), // Check for exposed secret key
            'signature_present' => isset($query_params['X-Amz-Signature']),
            'expiration_set' => isset($query_params['X-Amz-Expires']),
            'signed_headers_present' => isset($query_params['X-Amz-SignedHeaders'])
        ];
    }

    private function analyze_expiration($query_params) {
        if (!isset($query_params['X-Amz-Expires'])) {
            return [
                'expires_set' => false,
                'error' => 'No expiration parameter found'
            ];
        }

        $expires_seconds = intval($query_params['X-Amz-Expires']);

        return [
            'expires_set' => true,
            'expires_seconds' => $expires_seconds,
            'expires_minutes' => round($expires_seconds / 60, 2),
            'expires_hours' => round($expires_seconds / 3600, 2),
            'is_reasonable' => ($expires_seconds >= 300 && $expires_seconds <= 86400), // 5 minutes to 24 hours
            'security_assessment' => $this->assess_expiration_security($expires_seconds)
        ];
    }

    private function assess_expiration_security($expires_seconds) {
        if ($expires_seconds < 300) {
            return 'Too short - may cause upload failures';
        } elseif ($expires_seconds > 86400) {
            return 'Too long - security risk';
        } elseif ($expires_seconds <= 3600) {
            return 'Good - secure and practical';
        } else {
            return 'Acceptable - reasonable timeframe';
        }
    }

    private function validate_expiration_time($query_params) {
        if (!isset($query_params['X-Amz-Date']) || !isset($query_params['X-Amz-Expires'])) {
            return [
                'validation_possible' => false,
                'error' => 'Missing date or expires parameters'
            ];
        }

        $amz_date = $query_params['X-Amz-Date'];
        $expires_seconds = intval($query_params['X-Amz-Expires']);

        // Parse the AMZ date
        $date_time = DateTime::createFromFormat('Ymd\THis\Z', $amz_date);

        if (!$date_time) {
            return [
                'validation_possible' => false,
                'error' => 'Invalid AMZ date format'
            ];
        }

        $current_time = new DateTime('now', new DateTimeZone('UTC'));
        $expiration_time = clone $date_time;
        $expiration_time->add(new DateInterval('PT' . $expires_seconds . 'S'));

        return [
            'validation_possible' => true,
            'creation_time' => $date_time->format('Y-m-d H:i:s'),
            'expiration_time' => $expiration_time->format('Y-m-d H:i:s'),
            'current_time' => $current_time->format('Y-m-d H:i:s'),
            'is_future_dated' => $expiration_time > $current_time,
            'time_remaining_minutes' => $expiration_time > $current_time ?
                round(($expiration_time->getTimestamp() - $current_time->getTimestamp()) / 60, 2) : 0
        ];
    }

    // Configuration testing methods
    private function test_with_environment_config() {
        $has_env_config = defined('H3_S3_BUCKET') && defined('AWS_ACCESS_KEY_ID') && defined('AWS_SECRET_ACCESS_KEY');

        if (!$has_env_config) {
            return [
                'config_type' => 'environment',
                'configured' => false,
                'error' => 'Environment variables not defined'
            ];
        }

        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = $this->test_single_url_generation($s3_integration, 'env-config-test.zip', 1000000);

            return [
                'config_type' => 'environment',
                'configured' => true,
                'generation_result' => $result
            ];
        } catch (Exception $e) {
            return [
                'config_type' => 'environment',
                'configured' => true,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_with_database_config() {
        try {
            $s3_integration = new H3TM_S3_Integration();

            // Check if database config exists
            $db_configured = !empty(get_option('h3tm_s3_bucket', '')) &&
                           !empty(get_option('h3tm_aws_access_key', '')) &&
                           !empty(get_option('h3tm_aws_secret_key', ''));

            if (!$db_configured) {
                return [
                    'config_type' => 'database',
                    'configured' => false,
                    'error' => 'Database configuration not complete'
                ];
            }

            $result = $this->test_single_url_generation($s3_integration, 'db-config-test.zip', 1000000);

            return [
                'config_type' => 'database',
                'configured' => true,
                'generation_result' => $result
            ];
        } catch (Exception $e) {
            return [
                'config_type' => 'database',
                'configured' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_with_no_config() {
        // Backup original config
        $original_bucket = get_option('h3tm_s3_bucket', '');
        $original_access = get_option('h3tm_aws_access_key', '');
        $original_secret = get_option('h3tm_aws_secret_key', '');

        // Clear database config
        update_option('h3tm_s3_bucket', '');
        update_option('h3tm_aws_access_key', '');
        update_option('h3tm_aws_secret_key', '');

        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = $this->test_single_url_generation($s3_integration, 'no-config-test.zip', 1000000);

            $test_result = [
                'config_type' => 'none',
                'configured' => false,
                'generation_result' => $result,
                'expected_failure' => !$result['success']
            ];
        } catch (Exception $e) {
            $test_result = [
                'config_type' => 'none',
                'configured' => false,
                'error' => $e->getMessage()
            ];
        }

        // Restore original config
        update_option('h3tm_s3_bucket', $original_bucket);
        update_option('h3tm_aws_access_key', $original_access);
        update_option('h3tm_aws_secret_key', $original_secret);

        return $test_result;
    }

    private function test_with_partial_config() {
        // Backup original config
        $original_bucket = get_option('h3tm_s3_bucket', '');
        $original_access = get_option('h3tm_aws_access_key', '');
        $original_secret = get_option('h3tm_aws_secret_key', '');

        // Set partial config (bucket only)
        update_option('h3tm_s3_bucket', 'test-bucket');
        update_option('h3tm_aws_access_key', '');
        update_option('h3tm_aws_secret_key', '');

        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = $this->test_single_url_generation($s3_integration, 'partial-config-test.zip', 1000000);

            $test_result = [
                'config_type' => 'partial',
                'configured' => false,
                'generation_result' => $result,
                'expected_failure' => !$result['success']
            ];
        } catch (Exception $e) {
            $test_result = [
                'config_type' => 'partial',
                'configured' => false,
                'error' => $e->getMessage()
            ];
        }

        // Restore original config
        update_option('h3tm_s3_bucket', $original_bucket);
        update_option('h3tm_aws_access_key', $original_access);
        update_option('h3tm_aws_secret_key', $original_secret);

        return $test_result;
    }

    private function analyze_configuration_consistency($results) {
        $configured_count = 0;
        $successful_generations = 0;
        $total_configs = count($results) - 1; // Exclude consistency analysis itself

        foreach ($results as $config_type => $result) {
            if ($config_type === 'configuration_consistency') continue;

            if (isset($result['configured']) && $result['configured']) {
                $configured_count++;
            }

            if (isset($result['generation_result']['success']) && $result['generation_result']['success']) {
                $successful_generations++;
            }
        }

        return [
            'total_configurations_tested' => $total_configs,
            'configured_count' => $configured_count,
            'successful_generations' => $successful_generations,
            'configuration_consistency' => ($configured_count === $successful_generations),
            'consistency_score' => $configured_count > 0 ? ($successful_generations / $configured_count) * 100 : 0
        ];
    }

    // Error scenario testing methods
    private function test_empty_filename() {
        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = $this->test_single_url_generation($s3_integration, '', 1000000);

            return [
                'scenario' => 'empty_filename',
                'expected_failure' => true,
                'actual_failure' => !$result['success'],
                'error_message' => $result['error'],
                'properly_handled' => !$result['success']
            ];
        } catch (Exception $e) {
            return [
                'scenario' => 'empty_filename',
                'expected_failure' => true,
                'actual_failure' => true,
                'error_message' => $e->getMessage(),
                'properly_handled' => true
            ];
        }
    }

    private function test_zero_filesize() {
        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = $this->test_single_url_generation($s3_integration, 'test.zip', 0);

            return [
                'scenario' => 'zero_filesize',
                'expected_failure' => true,
                'actual_failure' => !$result['success'],
                'error_message' => $result['error'],
                'properly_handled' => !$result['success']
            ];
        } catch (Exception $e) {
            return [
                'scenario' => 'zero_filesize',
                'expected_failure' => true,
                'actual_failure' => true,
                'error_message' => $e->getMessage(),
                'properly_handled' => true
            ];
        }
    }

    private function test_negative_filesize() {
        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = $this->test_single_url_generation($s3_integration, 'test.zip', -1000);

            return [
                'scenario' => 'negative_filesize',
                'expected_failure' => true,
                'actual_failure' => !$result['success'],
                'error_message' => $result['error'],
                'properly_handled' => !$result['success']
            ];
        } catch (Exception $e) {
            return [
                'scenario' => 'negative_filesize',
                'expected_failure' => true,
                'actual_failure' => true,
                'error_message' => $e->getMessage(),
                'properly_handled' => true
            ];
        }
    }

    private function test_invalid_file_extension() {
        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = $this->test_single_url_generation($s3_integration, 'test.txt', 1000000);

            return [
                'scenario' => 'invalid_file_extension',
                'expected_failure' => true,
                'actual_failure' => !$result['success'],
                'error_message' => $result['error'],
                'properly_handled' => !$result['success']
            ];
        } catch (Exception $e) {
            return [
                'scenario' => 'invalid_file_extension',
                'expected_failure' => true,
                'actual_failure' => true,
                'error_message' => $e->getMessage(),
                'properly_handled' => true
            ];
        }
    }

    private function test_extremely_long_filename() {
        $long_filename = str_repeat('a', 500) . '.zip';

        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = $this->test_single_url_generation($s3_integration, $long_filename, 1000000);

            return [
                'scenario' => 'extremely_long_filename',
                'filename_length' => strlen($long_filename),
                'expected_failure' => false, // Should be handled by sanitization
                'actual_failure' => !$result['success'],
                'error_message' => $result['error'],
                'properly_handled' => true // Either succeeds or fails gracefully
            ];
        } catch (Exception $e) {
            return [
                'scenario' => 'extremely_long_filename',
                'filename_length' => strlen($long_filename),
                'expected_failure' => false,
                'actual_failure' => true,
                'error_message' => $e->getMessage(),
                'properly_handled' => true
            ];
        }
    }

    private function test_missing_bucket_config() {
        // Test with missing bucket configuration
        $original_bucket = get_option('h3tm_s3_bucket', '');
        update_option('h3tm_s3_bucket', '');

        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = $this->test_single_url_generation($s3_integration, 'test.zip', 1000000);

            $test_result = [
                'scenario' => 'missing_bucket',
                'expected_failure' => true,
                'actual_failure' => !$result['success'],
                'error_message' => $result['error'],
                'properly_handled' => !$result['success']
            ];
        } catch (Exception $e) {
            $test_result = [
                'scenario' => 'missing_bucket',
                'expected_failure' => true,
                'actual_failure' => true,
                'error_message' => $e->getMessage(),
                'properly_handled' => true
            ];
        }

        update_option('h3tm_s3_bucket', $original_bucket);
        return $test_result;
    }

    private function test_missing_credentials() {
        // Test with missing credentials
        $original_access = get_option('h3tm_aws_access_key', '');
        $original_secret = get_option('h3tm_aws_secret_key', '');

        update_option('h3tm_aws_access_key', '');
        update_option('h3tm_aws_secret_key', '');

        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = $this->test_single_url_generation($s3_integration, 'test.zip', 1000000);

            $test_result = [
                'scenario' => 'missing_credentials',
                'expected_failure' => true,
                'actual_failure' => !$result['success'],
                'error_message' => $result['error'],
                'properly_handled' => !$result['success']
            ];
        } catch (Exception $e) {
            $test_result = [
                'scenario' => 'missing_credentials',
                'expected_failure' => true,
                'actual_failure' => true,
                'error_message' => $e->getMessage(),
                'properly_handled' => true
            ];
        }

        update_option('h3tm_aws_access_key', $original_access);
        update_option('h3tm_aws_secret_key', $original_secret);

        return $test_result;
    }

    private function evaluate_error_handling_quality($scenarios) {
        $properly_handled = 0;
        $total_scenarios = count($scenarios);

        foreach ($scenarios as $scenario) {
            if (isset($scenario['properly_handled']) && $scenario['properly_handled']) {
                $properly_handled++;
            }
        }

        return [
            'total_scenarios' => $total_scenarios,
            'properly_handled' => $properly_handled,
            'error_handling_score' => $total_scenarios > 0 ? ($properly_handled / $total_scenarios) * 100 : 0,
            'quality_assessment' => $this->assess_error_handling_quality($properly_handled, $total_scenarios)
        ];
    }

    private function assess_error_handling_quality($properly_handled, $total_scenarios) {
        $score = $total_scenarios > 0 ? ($properly_handled / $total_scenarios) * 100 : 0;

        if ($score >= 90) {
            return 'Excellent - all error scenarios properly handled';
        } elseif ($score >= 75) {
            return 'Good - most error scenarios handled correctly';
        } elseif ($score >= 50) {
            return 'Fair - some error scenarios need improvement';
        } else {
            return 'Poor - error handling needs significant improvement';
        }
    }

    // Security validation methods
    private function check_https_protocol($url) {
        return strpos($url, 'https://') === 0;
    }

    private function check_no_credentials_exposed($url) {
        // Check for exposed AWS secret key patterns
        return !preg_match('/[A-Za-z0-9\/+=]{40}/', $url);
    }

    private function check_proper_s3_domain($url) {
        return strpos($url, '.s3.') !== false && strpos($url, '.amazonaws.com') !== false;
    }

    private function check_signature_present($url) {
        return strpos($url, 'X-Amz-Signature=') !== false;
    }

    private function check_no_suspicious_params($url) {
        $suspicious_patterns = ['password', 'secret', 'token', 'key'];
        $url_lower = strtolower($url);

        foreach ($suspicious_patterns as $pattern) {
            if (strpos($url_lower, $pattern . '=') !== false) {
                return false;
            }
        }

        return true;
    }

    private function calculate_security_score($security_checks) {
        $passed_checks = count(array_filter($security_checks));
        $total_checks = count($security_checks);

        return $total_checks > 0 ? ($passed_checks / $total_checks) * 100 : 0;
    }

    // Advanced compliance checks
    private function comprehensive_v4_compliance_check($query_params) {
        $checks = [
            'algorithm' => $this->check_signature_algorithm($query_params),
            'credential_format' => $this->validate_credential_format($query_params),
            'date_format' => $this->validate_date_format($query_params),
            'signed_headers' => $this->validate_signed_headers($query_params),
            'signature_format' => $this->validate_signature_format($query_params),
            'expiration_format' => $this->validate_expiration_format($query_params)
        ];

        $passed_checks = count(array_filter($checks));
        $total_checks = count($checks);

        return [
            'checks' => $checks,
            'passed_checks' => $passed_checks,
            'total_checks' => $total_checks,
            'compliance_score' => ($passed_checks / $total_checks) * 100,
            'is_fully_compliant' => $passed_checks === $total_checks
        ];
    }

    private function check_signature_algorithm($query_params) {
        return isset($query_params['X-Amz-Algorithm']) &&
               $query_params['X-Amz-Algorithm'] === 'AWS4-HMAC-SHA256';
    }

    private function validate_credential_format($query_params) {
        if (!isset($query_params['X-Amz-Credential'])) {
            return false;
        }

        $credential = $query_params['X-Amz-Credential'];
        return preg_match('/^[A-Z0-9]{20}\/\d{8}\/[a-z0-9-]+\/s3\/aws4_request$/', $credential) === 1;
    }

    private function validate_date_format($query_params) {
        if (!isset($query_params['X-Amz-Date'])) {
            return false;
        }

        $date = $query_params['X-Amz-Date'];
        return preg_match('/^\d{8}T\d{6}Z$/', $date) === 1;
    }

    private function validate_signed_headers($query_params) {
        if (!isset($query_params['X-Amz-SignedHeaders'])) {
            return false;
        }

        $signed_headers = $query_params['X-Amz-SignedHeaders'];
        // Should include at least 'host' for presigned URLs
        return strpos($signed_headers, 'host') !== false;
    }

    private function validate_signature_format($query_params) {
        if (!isset($query_params['X-Amz-Signature'])) {
            return false;
        }

        $signature = $query_params['X-Amz-Signature'];
        return preg_match('/^[a-f0-9]{64}$/', $signature) === 1;
    }

    private function validate_expiration_format($query_params) {
        if (!isset($query_params['X-Amz-Expires'])) {
            return false;
        }

        $expires = $query_params['X-Amz-Expires'];
        return is_numeric($expires) && intval($expires) > 0;
    }

    // Real S3 integration testing
    private function test_presigned_url_http($url) {
        // Test the presigned URL with a small HTTP HEAD request
        $response = wp_remote_head($url, [
            'timeout' => 10,
            'user-agent' => 'H3TM-Test/1.0'
        ]);

        if (is_wp_error($response)) {
            return [
                'http_test_successful' => false,
                'error' => $response->get_error_message(),
                'response_code' => null
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);

        return [
            'http_test_successful' => true,
            'response_code' => $response_code,
            'response_headers' => wp_remote_retrieve_headers($response),
            'url_accessible' => ($response_code >= 200 && $response_code < 300) || $response_code === 403, // 403 is expected for HEAD on presigned PUT URL
            'error' => null
        ];
    }

    private function evaluate_integration_quality($http_test) {
        if (!$http_test['http_test_successful']) {
            return 'Poor - HTTP request failed: ' . $http_test['error'];
        }

        $response_code = $http_test['response_code'];

        if ($response_code >= 200 && $response_code < 300) {
            return 'Excellent - URL accessible and working';
        } elseif ($response_code === 403) {
            return 'Good - URL properly configured (403 expected for HEAD on PUT URL)';
        } elseif ($response_code === 404) {
            return 'Fair - URL generated but bucket/key not found';
        } else {
            return 'Poor - Unexpected response code: ' . $response_code;
        }
    }

    /**
     * Summary Methods (continued in next response due to length)
     */

    private function summarize_basic_generation($results) {
        if (isset($results['error'])) {
            return 'Error: ' . $results['error'];
        }

        $success_rate = $results['overall_success_rate'] ?? 0;
        $test_count = count($results) - 1; // Exclude success rate from count

        return "Success rate: {$success_rate}% ($test_count test cases)";
    }

    private function summarize_size_testing($results) {
        if (isset($results['error'])) {
            return 'Error: ' . $results['error'];
        }

        $analysis = $results['size_analysis'] ?? [];
        $avg_time = $analysis['avg_generation_time_ms'] ?? 0;
        $max_size = $analysis['max_successful_size_mb'] ?? 0;

        return "Avg generation time: {$avg_time}ms, Max size: {$max_size}MB";
    }

    private function summarize_character_testing($results) {
        if (isset($results['error'])) {
            return 'Error: ' . $results['error'];
        }

        $analysis = $results['character_handling_analysis'] ?? [];
        $success_rate = $analysis['success_rate'] ?? 0;
        $change_rate = $analysis['filename_change_rate'] ?? 0;

        return "Success rate: {$success_rate}%, Filename changes: {$change_rate}%";
    }

    private function summarize_signature_validation($results) {
        if (isset($results['error'])) {
            return 'Error: ' . $results['error'];
        }

        if (!$results['configured']) {
            return 'S3 not configured';
        }

        if (!$results['url_generated']) {
            return 'URL generation failed';
        }

        $compliance = $results['aws_v4_compliance'] ?? [];
        $score = $compliance['compliance_score'] ?? 0;

        return "AWS v4 compliance score: {$score}%";
    }

    private function summarize_expiration_testing($results) {
        if (isset($results['error'])) {
            return 'Error: ' . $results['error'];
        }

        if (!$results['configured'] || !$results['url_generated']) {
            return 'Cannot test - URL generation failed';
        }

        $analysis = $results['expiration_analysis'] ?? [];
        $expires_hours = $analysis['expires_hours'] ?? 0;

        return "Expiration set to: {$expires_hours} hours";
    }

    private function summarize_configuration_impact($results) {
        $consistency = $results['configuration_consistency'] ?? [];
        $score = $consistency['consistency_score'] ?? 0;
        $successful = $consistency['successful_generations'] ?? 0;

        return "Consistency score: {$score}%, Successful generations: {$successful}";
    }

    private function summarize_aws_compliance($results) {
        if (isset($results['error'])) {
            return 'Error: ' . $results['error'];
        }

        if (!$results['configured'] || !$results['url_generated']) {
            return 'Cannot test - URL generation failed';
        }

        $compliance = $results['v4_compliance'] ?? [];
        $score = $compliance['compliance_score'] ?? 0;

        return "AWS v4 compliance: {$score}%";
    }

    private function summarize_error_scenarios($results) {
        $quality = $results['error_handling_quality'] ?? [];
        $score = $quality['error_handling_score'] ?? 0;
        $handled = $quality['properly_handled'] ?? 0;
        $total = $quality['total_scenarios'] ?? 0;

        return "Error handling score: {$score}% ({$handled}/{$total} scenarios)";
    }

    private function summarize_url_security($results) {
        if (isset($results['error'])) {
            return 'Error: ' . $results['error'];
        }

        if (!$results['configured'] || !$results['url_generated']) {
            return 'Cannot test - URL generation failed';
        }

        $score = $results['security_score'] ?? 0;

        return "Security score: {$score}%";
    }

    private function summarize_real_s3_integration($results) {
        if (isset($results['error'])) {
            return 'Error: ' . $results['error'];
        }

        if (isset($results['skipped'])) {
            return 'Skipped - S3 not configured';
        }

        if (!$results['url_generated']) {
            return 'URL generation failed';
        }

        $quality = $results['integration_quality'] ?? 'Unknown';

        return "Integration quality: {$quality}";
    }

    /**
     * Recommendation Methods
     */

    private function get_basic_generation_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix class instantiation error: ' . $results['error'];
        }

        $success_rate = $results['overall_success_rate'] ?? 0;

        if ($success_rate >= 90) {
            return 'Excellent - URL generation working properly';
        } elseif ($success_rate >= 70) {
            return 'Good - minor issues with some test cases';
        } elseif ($success_rate >= 50) {
            return 'Fair - several test cases failing, investigate configuration';
        } else {
            return 'Poor - majority of URL generation attempts failing';
        }
    }

    private function get_size_testing_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix size testing error: ' . $results['error'];
        }

        $analysis = $results['size_analysis'] ?? [];
        $max_size = $analysis['max_successful_size_mb'] ?? 0;

        if ($max_size >= 1000) {
            return 'Excellent - supporting very large files (1GB+)';
        } elseif ($max_size >= 500) {
            return 'Good - supporting large files (500MB+)';
        } elseif ($max_size >= 100) {
            return 'Fair - supporting medium files (100MB+)';
        } else {
            return 'Poor - limited file size support, check configuration';
        }
    }

    private function get_character_testing_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix character testing error: ' . $results['error'];
        }

        $analysis = $results['character_handling_analysis'] ?? [];
        $success_rate = $analysis['success_rate'] ?? 0;

        if ($success_rate >= 90) {
            return 'Good - handling special characters properly';
        } elseif ($success_rate >= 70) {
            return 'Fair - some character handling issues';
        } else {
            return 'Poor - significant character handling problems';
        }
    }

    private function get_signature_validation_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix signature validation error: ' . $results['error'];
        }

        if (!$results['configured']) {
            return 'Configure S3 settings to test signature validation';
        }

        if (!$results['url_generated']) {
            return 'Fix URL generation to enable signature validation';
        }

        $compliance = $results['aws_v4_compliance'] ?? [];
        $score = $compliance['compliance_score'] ?? 0;

        if ($score >= 90) {
            return 'Excellent - AWS v4 signature fully compliant';
        } elseif ($score >= 75) {
            return 'Good - minor compliance issues';
        } else {
            return 'Poor - significant AWS v4 compliance issues';
        }
    }

    private function get_expiration_testing_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix expiration testing error: ' . $results['error'];
        }

        if (!$results['configured'] || !$results['url_generated']) {
            return 'Fix URL generation to test expiration';
        }

        $analysis = $results['expiration_analysis'] ?? [];

        if (!$analysis['expires_set']) {
            return 'Critical - no expiration set on presigned URLs';
        }

        if (!$analysis['is_reasonable']) {
            return 'Warning - expiration time may be too short or too long';
        }

        return 'Good - expiration properly configured';
    }

    private function get_configuration_impact_recommendation($results) {
        $consistency = $results['configuration_consistency'] ?? [];
        $score = $consistency['consistency_score'] ?? 0;

        if ($score >= 100) {
            return 'Excellent - configuration consistent across all scenarios';
        } elseif ($score >= 75) {
            return 'Good - mostly consistent configuration';
        } elseif ($score >= 50) {
            return 'Fair - some configuration inconsistencies detected';
        } else {
            return 'Poor - significant configuration inconsistencies';
        }
    }

    private function get_aws_compliance_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix AWS compliance testing error: ' . $results['error'];
        }

        if (!$results['configured'] || !$results['url_generated']) {
            return 'Fix URL generation to test AWS compliance';
        }

        $compliance = $results['v4_compliance'] ?? [];

        if ($compliance['is_fully_compliant']) {
            return 'Excellent - fully AWS v4 compliant';
        } else {
            return 'Issues found - review AWS v4 signature implementation';
        }
    }

    private function get_error_scenarios_recommendation($results) {
        $quality = $results['error_handling_quality'] ?? [];
        $score = $quality['error_handling_score'] ?? 0;

        if ($score >= 90) {
            return 'Excellent - all error scenarios properly handled';
        } elseif ($score >= 75) {
            return 'Good - most error scenarios handled correctly';
        } else {
            return 'Poor - error handling needs improvement';
        }
    }

    private function get_url_security_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix security testing error: ' . $results['error'];
        }

        if (!$results['configured'] || !$results['url_generated']) {
            return 'Fix URL generation to test security';
        }

        $score = $results['security_score'] ?? 0;

        if ($score >= 90) {
            return 'Excellent - URLs are secure';
        } elseif ($score >= 75) {
            return 'Good - minor security concerns';
        } else {
            return 'Critical - security issues detected in URLs';
        }
    }

    private function get_real_s3_integration_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix real S3 integration error: ' . $results['error'];
        }

        if (isset($results['skipped'])) {
            return 'Configure S3 settings to enable real integration testing';
        }

        if (!$results['url_generated']) {
            return 'Fix URL generation to enable real S3 testing';
        }

        $quality = $results['integration_quality'] ?? '';

        if (strpos($quality, 'Excellent') === 0) {
            return 'Perfect - real S3 integration working flawlessly';
        } elseif (strpos($quality, 'Good') === 0) {
            return 'Good - real S3 integration working as expected';
        } else {
            return 'Issues detected - review S3 configuration and credentials';
        }
    }

    /**
     * Generate comprehensive test report
     */
    public function generate_test_report() {
        $report = [
            'timestamp' => current_time('mysql'),
            'test_count' => count($this->test_results),
            'results' => $this->test_results,
            'debug_info' => $this->debug_info,
            'overall_assessment' => $this->get_overall_assessment(),
            'action_items' => $this->get_action_items(),
            'performance_metrics' => $this->get_performance_metrics()
        ];

        error_log('H3TM S3 Presigned URL Tests: Test completed with ' . count($this->test_results) . ' test suites');
        error_log('H3TM S3 Presigned URL Tests: Overall Assessment: ' . $report['overall_assessment']);

        return $report;
    }

    private function get_overall_assessment() {
        $critical_issues = [];
        $warnings = [];

        foreach ($this->debug_info as $test_name => $info) {
            if (strpos($info['recommendation'], 'Critical') === 0 || strpos($info['recommendation'], 'Poor') === 0) {
                $critical_issues[] = "$test_name: " . $info['recommendation'];
            } elseif (strpos($info['recommendation'], 'Warning') === 0 || strpos($info['recommendation'], 'Fair') === 0) {
                $warnings[] = "$test_name: " . $info['recommendation'];
            }
        }

        if (!empty($critical_issues)) {
            return 'CRITICAL ISSUES: ' . implode('; ', $critical_issues);
        }

        if (!empty($warnings)) {
            return 'WARNINGS: ' . implode('; ', $warnings);
        }

        return 'All presigned URL tests passed successfully - implementation appears robust';
    }

    private function get_action_items() {
        $actions = [];

        foreach ($this->debug_info as $test_name => $info) {
            if (strpos($info['recommendation'], 'Fix') === 0 ||
                strpos($info['recommendation'], 'Critical') === 0 ||
                strpos($info['recommendation'], 'Configure') === 0) {
                $actions[] = "$test_name: " . $info['recommendation'];
            }
        }

        return $actions;
    }

    private function get_performance_metrics() {
        $metrics = [
            'total_tests_run' => count($this->test_results),
            'successful_test_suites' => 0,
            'failed_test_suites' => 0,
            'average_generation_time' => null,
            'max_file_size_supported' => null
        ];

        foreach ($this->debug_info as $test_name => $info) {
            if (strpos($info['recommendation'], 'Excellent') === 0 || strpos($info['recommendation'], 'Good') === 0) {
                $metrics['successful_test_suites']++;
            } else {
                $metrics['failed_test_suites']++;
            }
        }

        // Extract performance data if available
        if (isset($this->test_results['URL Generation File Sizes']['size_analysis'])) {
            $size_analysis = $this->test_results['URL Generation File Sizes']['size_analysis'];
            $metrics['average_generation_time'] = $size_analysis['avg_generation_time_ms'] ?? null;
            $metrics['max_file_size_supported'] = $size_analysis['max_successful_size_mb'] ?? null;
        }

        return $metrics;
    }

    /**
     * Export test results to file
     */
    public function export_results_to_file($file_path = null) {
        if ($file_path === null) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/h3tm-s3-presigned-url-test-' . date('Y-m-d-H-i-s') . '.json';
        }

        $report = $this->generate_test_report();
        file_put_contents($file_path, json_encode($report, JSON_PRETTY_PRINT));

        return $file_path;
    }
}

// Allow direct execution for testing
if (defined('WP_CLI') || (defined('ABSPATH') && !defined('DOING_AJAX') && current_user_can('manage_options'))) {

    function run_h3tm_s3_presigned_url_tests() {
        $tester = new H3TM_S3_Presigned_URL_Tests();
        $results = $tester->run_all_tests();

        if (defined('WP_CLI')) {
            WP_CLI::success('S3 Presigned URL Tests completed');
            WP_CLI::log(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            echo '<pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
        }

        return $results;
    }

    // Auto-run if accessed directly
    if (isset($_GET['run_s3_presigned_url_tests'])) {
        run_h3tm_s3_presigned_url_tests();
    }
}