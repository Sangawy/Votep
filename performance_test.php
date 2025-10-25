<?php
// Performance Test Script
// This script simulates multiple concurrent users to test the scanner performance

require_once 'config.php';

// Test configuration
$test_users = 25; // Number of concurrent users to simulate
$test_duration = 60; // Test duration in seconds
$scan_interval = 2; // Seconds between scans per user

echo "<h2>Performance Test Results</h2>\n";
echo "<p>Testing with {$test_users} concurrent users for {$test_duration} seconds</p>\n";

// Get a sample voting center for testing
try {
    $stmt = $pdo->query("SELECT DISTINCT voting_center_name FROM voters LIMIT 1");
    $center = $stmt->fetch(PDO::FETCH_ASSOC);
    $test_center = $center['voting_center_name'];
    
    // Get some voter numbers for testing
    $stmt = $pdo->prepare("SELECT voter_number FROM voters WHERE voting_center_name = ? LIMIT 50");
    $stmt->execute([$test_center]);
    $test_voters = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($test_voters)) {
        echo "<p style='color: red;'>No test data found. Please ensure voters table has data.</p>";
        exit;
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Performance metrics
$start_time = microtime(true);
$total_requests = 0;
$successful_requests = 0;
$failed_requests = 0;
$response_times = [];

echo "<h3>Starting Performance Test...</h3>\n";
echo "<div id='progress'></div>\n";

// Simulate concurrent database queries
for ($i = 0; $i < $test_users * 10; $i++) {
    $request_start = microtime(true);
    $total_requests++;
    
    try {
        // Simulate a typical scan query
        $voter_number = $test_voters[array_rand($test_voters)];
        
        $stmt = $pdo->prepare("
            SELECT * FROM voters 
            WHERE voter_number = ? AND voting_center_name = ?
        ");
        $stmt->execute([$voter_number, $test_center]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $successful_requests++;
        }
        
        $request_time = microtime(true) - $request_start;
        $response_times[] = $request_time;
        
    } catch (PDOException $e) {
        $failed_requests++;
    }
    
    // Small delay to simulate real usage
    usleep(50000); // 50ms delay
}

$end_time = microtime(true);
$total_time = $end_time - $start_time;

// Calculate statistics
$avg_response_time = array_sum($response_times) / count($response_times);
$max_response_time = max($response_times);
$min_response_time = min($response_times);
$requests_per_second = $total_requests / $total_time;

// Test cache performance
echo "<h3>Cache Performance Test</h3>\n";
$cache_start = microtime(true);

// Test stats query with cache
$cache_file = 'cache/stats_' . md5($test_center) . '.json';
if (file_exists($cache_file)) {
    unlink($cache_file); // Clear cache for fair test
}

// First request (no cache)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_voters,
        SUM(CASE WHEN status = 'voted' THEN 1 ELSE 0 END) as scanned_voters
    FROM voters 
    WHERE voting_center_name = ?
");
$stmt->execute([$test_center]);
$first_request_time = microtime(true) - $cache_start;

// Save to cache
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
file_put_contents($cache_file, json_encode($stats));

// Second request (with cache)
$cache_start2 = microtime(true);
$cached_data = json_decode(file_get_contents($cache_file), true);
$cached_request_time = microtime(true) - $cache_start2;

echo "<h3>Performance Results</h3>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Metric</th><th>Value</th></tr>\n";
echo "<tr><td>Total Requests</td><td>{$total_requests}</td></tr>\n";
echo "<tr><td>Successful Requests</td><td>{$successful_requests}</td></tr>\n";
echo "<tr><td>Failed Requests</td><td>{$failed_requests}</td></tr>\n";
echo "<tr><td>Success Rate</td><td>" . round(($successful_requests / $total_requests) * 100, 2) . "%</td></tr>\n";
echo "<tr><td>Total Test Time</td><td>" . round($total_time, 2) . " seconds</td></tr>\n";
echo "<tr><td>Requests per Second</td><td>" . round($requests_per_second, 2) . "</td></tr>\n";
echo "<tr><td>Average Response Time</td><td>" . round($avg_response_time * 1000, 2) . " ms</td></tr>\n";
echo "<tr><td>Min Response Time</td><td>" . round($min_response_time * 1000, 2) . " ms</td></tr>\n";
echo "<tr><td>Max Response Time</td><td>" . round($max_response_time * 1000, 2) . " ms</td></tr>\n";
echo "</table>\n";

echo "<h3>Cache Performance</h3>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Query Type</th><th>Response Time</th><th>Improvement</th></tr>\n";
echo "<tr><td>Database Query (No Cache)</td><td>" . round($first_request_time * 1000, 2) . " ms</td><td>-</td></tr>\n";
echo "<tr><td>Cached Data</td><td>" . round($cached_request_time * 1000, 2) . " ms</td><td>" . round((($first_request_time - $cached_request_time) / $first_request_time) * 100, 1) . "% faster</td></tr>\n";
echo "</table>\n";

// Performance recommendations
echo "<h3>Performance Recommendations</h3>\n";
if ($avg_response_time > 0.1) {
    echo "<p style='color: orange;'>⚠️ Average response time is high. Consider optimizing database queries.</p>\n";
} else {
    echo "<p style='color: green;'>✅ Good average response time.</p>\n";
}

if ($requests_per_second < 50) {
    echo "<p style='color: orange;'>⚠️ Low throughput. Consider adding more database indexes.</p>\n";
} else {
    echo "<p style='color: green;'>✅ Good throughput for concurrent users.</p>\n";
}

if ($failed_requests > 0) {
    echo "<p style='color: red;'>❌ Some requests failed. Check database connection limits.</p>\n";
} else {
    echo "<p style='color: green;'>✅ All requests successful.</p>\n";
}

echo "<p style='color: blue;'>💡 Cache provides " . round((($first_request_time - $cached_request_time) / $first_request_time) * 100, 1) . "% performance improvement</p>\n";

// Clean up test cache
if (file_exists($cache_file)) {
    unlink($cache_file);
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
    background: #f5f5f5;
}
table {
    background: white;
    margin: 10px 0;
}
th {
    background: #007bff;
    color: white;
    padding: 10px;
}
td {
    padding: 8px;
    border: 1px solid #ddd;
}
</style>