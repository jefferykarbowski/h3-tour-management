# Performance Testing Protocol - H3 Tour Management Optimization

## Testing Environment Setup

### Test Data Requirements
1. **Small Tour** (< 50 files, < 10MB)
2. **Medium Tour** (100-500 files, 10-50MB)
3. **Large Tour** (500-1000 files, 50-100MB)
4. **Very Large Tour** (1000+ files, 100MB+)

### Performance Metrics to Track
- **Total Operation Time** (seconds)
- **Filesystem Operation Time** (seconds)
- **Database Operation Time** (milliseconds)
- **Memory Usage** (peak and average)
- **Success Rate** (%)
- **User Feedback Response Time** (milliseconds)

## Baseline Performance Tests

### Current Implementation Benchmarks
```bash
# Run these tests before implementing optimizations

# Test 1: Small tour rename
curl -X POST "your-site.com/wp-admin/admin-ajax.php" \
  -d "action=h3tm_rename_tour&old_name=small_tour&new_name=small_renamed&nonce=YOUR_NONCE"

# Test 2: Medium tour rename
curl -X POST "your-site.com/wp-admin/admin-ajax.php" \
  -d "action=h3tm_rename_tour&old_name=medium_tour&new_name=medium_renamed&nonce=YOUR_NONCE"

# Test 3: Large tour rename (may timeout)
curl -X POST "your-site.com/wp-admin/admin-ajax.php" \
  -d "action=h3tm_rename_tour&old_name=large_tour&new_name=large_renamed&nonce=YOUR_NONCE"
```

### Expected Baseline Results
- **Small Tour**: 2-5 seconds
- **Medium Tour**: 10-20 seconds
- **Large Tour**: 30+ seconds (often timeout)
- **Very Large Tour**: Timeout failure

## Optimized Implementation Tests

### Test Suite Configuration
```php
<?php
/**
 * Performance test configuration
 */
define('H3TM_PERFORMANCE_TESTING', true);
define('H3TM_TEST_DATA_DIR', '/path/to/test/tours');

class H3TM_Performance_Tests {
    private $test_results = array();

    /**
     * Run complete test suite
     */
    public function run_all_tests() {
        $this->test_small_tour_rename();
        $this->test_medium_tour_rename();
        $this->test_large_tour_rename();
        $this->test_very_large_tour_rename();
        $this->test_concurrent_operations();
        $this->test_error_scenarios();

        return $this->test_results;
    }

    /**
     * Test small tour rename performance
     */
    private function test_small_tour_rename() {
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);

        // Perform rename operation
        $tour_manager = new H3TM_Tour_Manager_Optimized();
        $result = $tour_manager->rename_tour_optimized('small_test_tour', 'small_renamed_tour');

        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);

        $this->record_test_result('small_tour_rename', array(
            'success' => $result['success'],
            'duration' => $end_time - $start_time,
            'memory_used' => $end_memory - $start_memory,
            'error' => $result['success'] ? null : $result['error']['code']
        ));
    }

    // ... additional test methods
}
```

### Performance Benchmarks (Expected Improvements)

| Tour Size | Current Time | Optimized Time | Improvement |
|-----------|--------------|----------------|-------------|
| Small (< 50 files) | 2-5s | 1-3s | 20-40% |
| Medium (100-500 files) | 10-20s | 4-8s | 60-70% |
| Large (500-1000 files) | 30s+ (timeout) | 8-15s | 80%+ |
| Very Large (1000+ files) | Timeout failure | 15-30s | Success |

### Success Rate Targets
- **Small Tours**: 100% success rate
- **Medium Tours**: 100% success rate
- **Large Tours**: 95%+ success rate (vs. current ~30%)
- **Very Large Tours**: 90%+ success rate (vs. current 0%)

## Load Testing Protocol

### Concurrent Operation Tests
```php
/**
 * Test multiple rename operations simultaneously
 */
public function test_concurrent_renames() {
    $operations = array(
        array('old' => 'tour_1', 'new' => 'renamed_1'),
        array('old' => 'tour_2', 'new' => 'renamed_2'),
        array('old' => 'tour_3', 'new' => 'renamed_3')
    );

    $start_time = microtime(true);
    $processes = array();

    // Start operations simultaneously (in real scenario, use proper async)
    foreach ($operations as $op) {
        $processes[] = $this->start_async_rename($op['old'], $op['new']);
    }

    // Wait for completion and measure
    $results = array();
    foreach ($processes as $process) {
        $results[] = $this->wait_for_completion($process);
    }

    $total_time = microtime(true) - $start_time;

    return array(
        'total_time' => $total_time,
        'individual_results' => $results,
        'concurrency_efficiency' => $this->calculate_efficiency($results, $total_time)
    );
}
```

### Stress Testing Scenarios
1. **High Volume**: 10+ simultaneous operations
2. **Large Files**: Tours with 2000+ files
3. **Low Memory**: Test with restricted memory limits
4. **Slow Disk**: Simulate slow filesystem performance
5. **Network Issues**: Test with intermittent connectivity

## Database Performance Tests

### Query Optimization Verification
```sql
-- Test query performance for user assignment updates

-- Before optimization (individual updates)
EXPLAIN SELECT user_id, meta_value FROM wp_usermeta
WHERE meta_key = 'h3tm_tours' AND meta_value LIKE '%tour_name%';

-- After optimization (batch updates)
EXPLAIN SELECT user_id, meta_value FROM wp_usermeta
WHERE meta_key = 'h3tm_tours' AND meta_value LIKE '%tour_name%';

-- Measure query execution time
SET profiling = 1;
-- Run queries
SHOW profiles;
```

### Database Load Tests
- **User Count Impact**: Test with 10, 100, 1000+ users
- **Tour Assignment Density**: Users with 1, 10, 50+ tours
- **Concurrent Database Operations**: Multiple renames accessing DB simultaneously

## Memory and Resource Testing

### Memory Usage Monitoring
```php
/**
 * Monitor memory usage during operations
 */
class H3TM_Memory_Monitor {
    private $checkpoints = array();

    public function checkpoint($label) {
        $this->checkpoints[$label] = array(
            'memory' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'time' => microtime(true)
        );
    }

    public function get_usage_report() {
        $report = array();
        $previous = null;

        foreach ($this->checkpoints as $label => $data) {
            $delta = $previous ? $data['memory'] - $previous['memory'] : 0;
            $report[$label] = array(
                'memory' => $this->format_bytes($data['memory']),
                'peak' => $this->format_bytes($data['peak']),
                'delta' => $this->format_bytes($delta),
                'time' => $data['time']
            );
            $previous = $data;
        }

        return $report;
    }
}
```

### Resource Limit Testing
```php
// Test with various PHP limits
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 30);

// Test optimization effectiveness under constraints
$result = $this->run_constrained_test();
```

## Error Handling and Recovery Tests

### Failure Scenario Testing
1. **Disk Full**: Simulate insufficient disk space
2. **Permission Denied**: Test with restricted filesystem permissions
3. **Database Connection Loss**: Simulate DB connectivity issues
4. **PHP Timeout**: Test with very short time limits
5. **Partial Operation Failure**: Simulate interruption mid-operation

### Recovery Testing
```php
/**
 * Test operation recovery mechanisms
 */
public function test_operation_recovery() {
    // Start operation
    $operation_id = $this->start_tracked_operation();

    // Simulate interruption
    $this->simulate_interruption($operation_id);

    // Attempt recovery
    $recovery_result = $this->attempt_recovery($operation_id);

    return array(
        'recovery_successful' => $recovery_result['success'],
        'data_consistency' => $this->verify_data_consistency(),
        'cleanup_complete' => $this->verify_cleanup_complete()
    );
}
```

## Progress Tracking Performance Tests

### Real-time Updates Testing
```javascript
/**
 * Test progress tracking responsiveness
 */
function testProgressTracking() {
    const startTime = Date.now();
    let updateCount = 0;
    let totalLatency = 0;

    const tracker = new ProgressTracker();

    tracker.onUpdate = function(progress) {
        updateCount++;
        const latency = Date.now() - progress.timestamp;
        totalLatency += latency;
    };

    // Start operation and measure progress update performance
    tracker.start('test_operation_id');

    // After completion, calculate metrics
    setTimeout(() => {
        const averageLatency = totalLatency / updateCount;
        const updateFrequency = updateCount / ((Date.now() - startTime) / 1000);

        console.log({
            averageLatency: averageLatency + 'ms',
            updateFrequency: updateFrequency + ' updates/sec',
            totalUpdates: updateCount
        });
    }, 30000);
}
```

## User Experience Testing

### Response Time Measurements
- **Initial Response**: Time to first progress update
- **Progress Updates**: Frequency and consistency of updates
- **Completion Notification**: Time from operation complete to UI update
- **Error Display**: Time from error occurrence to user notification

### Usability Testing Scenarios
1. **Small Operation**: User sees immediate completion
2. **Medium Operation**: User sees steady progress updates
3. **Large Operation**: User gets warnings and detailed progress
4. **Failed Operation**: User gets clear error message and recovery options

## Reporting and Analysis

### Performance Report Template
```
# H3TM Performance Test Report

## Test Environment
- WordPress Version: 6.4.1
- PHP Version: 8.1.12
- Server: Apache/Nginx
- Database: MySQL 8.0
- Memory Limit: 256M
- Execution Time Limit: 300s

## Test Results Summary

### Rename Operation Performance
| Tour Size | Files | Before | After | Improvement |
|-----------|-------|--------|-------|-------------|
| Small     | 25    | 3.2s   | 1.8s  | 44%         |
| Medium    | 250   | 18.5s  | 6.2s  | 66%         |
| Large     | 750   | TIMEOUT| 12.1s | 100%        |

### Success Rates
- Small Tours: 100% (no change)
- Medium Tours: 100% (was 95%)
- Large Tours: 97% (was 30%)
- Very Large Tours: 92% (was 0%)

### Memory Usage
- Peak Memory: Reduced by 25%
- Average Memory: Reduced by 15%
- Memory Leaks: None detected

## Recommendations
1. Deploy optimized version to staging
2. Monitor performance in production
3. Consider further optimizations for tours with 2000+ files
```

### Automated Testing Integration
```bash
#!/bin/bash
# CI/CD performance testing script

echo "Running H3TM Performance Tests..."

# Setup test environment
wp-cli plugin install h3-tour-management
wp-cli plugin activate h3-tour-management

# Create test data
./create-test-tours.sh

# Run performance tests
wp-cli h3tm test-performance --format=json > performance-results.json

# Analyze results
./analyze-performance.sh performance-results.json

# Generate report
./generate-report.sh performance-results.json > performance-report.md
```

This comprehensive testing protocol ensures that the optimization improvements are measurable, reliable, and provide the expected performance benefits.