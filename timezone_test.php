<?php
// Set timezone for Malaysia
date_default_timezone_set("Asia/Kuala_Lumpur");

echo "<h2>Timezone Testing</h2>";
echo "<hr>";

echo "<strong>PHP Configuration:</strong><br>";
echo "Default timezone: " . date_default_timezone_get() . "<br>";
echo "Current PHP time: " . date('Y-m-d H:i:s') . "<br>";
echo "Current PHP timestamp: " . time() . "<br>";
echo "<br>";

// Test database connection and timezone
include 'includes/db.php';

echo "<strong>Database Configuration:</strong><br>";

// Check MySQL timezone
$timezone_result = $conn->query("SELECT @@session.time_zone as session_tz, @@global.time_zone as global_tz, NOW() as current_time");
if ($timezone_result) {
    $timezone_data = $timezone_result->fetch_assoc();
    echo "MySQL Session Timezone: " . $timezone_data['session_tz'] . "<br>";
    echo "MySQL Global Timezone: " . $timezone_data['global_tz'] . "<br>";
    echo "MySQL NOW(): " . $timezone_data['current_time'] . "<br>";
} else {
    echo "Error checking MySQL timezone: " . $conn->error . "<br>";
}

echo "<br>";

// Test with actual log data
$log_result = $conn->query("SELECT timestamp FROM user_logs ORDER BY timestamp DESC LIMIT 1");
if ($log_result && $log_result->num_rows > 0) {
    $log_data = $log_result->fetch_assoc();
    echo "<strong>Latest Log Entry:</strong><br>";
    echo "Raw timestamp: " . $log_data['timestamp'] . "<br>";
    echo "Formatted with PHP: " . date('M j, Y g:i:s A', strtotime($log_data['timestamp'])) . "<br>";
} else {
    echo "No log entries found.<br>";
}

echo "<br>";
echo "<strong>Server Information:</strong><br>";
echo "Server time: " . $_SERVER['REQUEST_TIME'] . " (" . date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']) . ")<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Check if running on localhost vs remote server
if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    echo "Running on: <span style='color: green;'>Localhost</span><br>";
} else {
    echo "Running on: <span style='color: blue;'>Remote Server (" . $_SERVER['HTTP_HOST'] . ")</span><br>";
}

?>