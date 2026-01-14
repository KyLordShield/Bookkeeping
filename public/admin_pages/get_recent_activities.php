<?php
// get_recent_activities.php - Returns JSON for recent activities
session_start();

// Add your auth check here if needed

require_once __DIR__ . '/../../classes/Dashboard.php';

$dashboard = new Dashboard();
$activities = $dashboard->getRecentActivities(8);  // same limit

header('Content-Type: application/json');
echo json_encode($activities);
?>