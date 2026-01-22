<?php
// admin_dashboard.php - Updated Frontend Dashboard (January 2026)
session_start();

//Auth check:
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login_page.php");
    exit();
}


require_once __DIR__ . '/../../classes/Dashboard.php';

$dashboard = new Dashboard();

// Fetch dashboard statistics
$activeClientsData = $dashboard->getActiveClients();
$activeClients     = $activeClientsData['total'];
$newThisWeek       = $activeClientsData['new_this_week'];

$pendingApprovals  = $dashboard->getPendingApprovals();

// Updated: Urgent Actions now counts pending requirement approvals
// Make sure getUrgentActions() in Dashboard.php returns the count of pending requirements
$urgentCount       = $dashboard->getUrgentActions();

$activeStaff       = $dashboard->getActiveStaff();

// Recent activities - now driven by activity_log table (full descriptive messages)
$recentActivities = $dashboard->getRecentActivities(8);

// Combined upcoming events: Confirmed appointments + Approved service requests
$upcomingEvents = $dashboard->getUpcomingMeetingsAndRequests(10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin / Consultant Dashboard</title>
    <link rel="stylesheet" href="../assets/css_file/admin_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    
    <style>
       
    </style>
</head>
<body>

<div class="container">
    <?php include '../partials/temporaryNavAdmin.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Admin / Consultant Dashboard</h1>
            <p>Overview of clients, requests, meetings and urgent actions</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($activeClients) ?></div>
                <div class="stat-label">Active Clients<br><small>+<?= $newThisWeek ?> this week</small></div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($pendingApprovals) ?></div>
                <div class="stat-label">Pending Approvals<br><small>Require attention</small></div>
            </div>
            <div class="stat-card <?= $urgentCount > 0 ? 'urgent-highlight' : '' ?>">
                <div class="stat-value"><?= number_format($urgentCount) ?></div>
                <div class="stat-label">Urgent Actions<br><small>Pending requirement approvals</small></div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($activeStaff) ?></div>
                <div class="stat-label">Active Staff</div>
            </div>
        </div>

        <div class="content-grid">
            <!-- Recent Activity -->
<div class="card">
    <div class="card-title">Recent Activity</div>
    
    <div id="recent-activity-list">
        <?php if (empty($recentActivities)): ?>
            <p style="color:#64748b; text-align:center; padding:2rem 0;">
                No recent activity recorded yet.
            </p>
        <?php else: ?>
            <?php foreach ($recentActivities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-avatar"></div>
                    <div class="activity-details">
                        <div class="activity-description">
                            <?= htmlspecialchars($activity['description']) ?>
                        </div>
                        <div class="activity-time">
                            <?= date('M d, Y g:i A', strtotime($activity['timestamp'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
            <!-- Upcoming Meetings & Approved Requests -->
            <div class="card">
                <div class="card-title">Upcoming Meetings & Approved Requests</div>
                
                <?php if (empty($upcomingEvents)): ?>
                    <p style="color:#64748b; text-align:center; padding:2rem 0;">
                        No upcoming meetings or approved requests scheduled in the next 14 days.
                    </p>
                <?php else: ?>
                    <?php foreach ($upcomingEvents as $event): ?>
                        <div class="event-item <?= $event['type'] === 'request' ? 'request-item' : '' ?>">
                            <div class="event-name">
                                <?= htmlspecialchars($event['client_name']) ?>
                                <?php if ($event['type'] === 'request'): ?>
                                    <span class="request-badge">Approved Request</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="event-date">
                                <?= date('M d, Y', strtotime($event['event_date'])) ?>
                            </div>
                            
                            <div class="event-title">
                                <?= htmlspecialchars($event['title']) ?>
                                <span class="status-badge <?= $event['type'] === 'appointment' ? 'status-scheduled' : 'status-approved' ?>">
                                    <?= ucfirst($event['status']) ?>
                                </span>
                            </div>
                            
                            <div class="event-time">
                                <?= $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'Time not specified' ?>
                            </div>

                            <?php if ($event['type'] === 'appointment' && !empty($event['meeting_link'])): ?>
                                <div style="margin-top:0.6rem;">
                                    <a href="<?= htmlspecialchars($event['meeting_link']) ?>" target="_blank" 
                                       style="color:#2563eb; font-size:0.9rem; text-decoration:none;">
                                        → Join Meeting
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateRecentActivity() {
    fetch('get_recent_activities.php')  // adjust path if needed
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('recent-activity-list');
            container.innerHTML = '';  // clear old

            if (data.length === 0) {
                container.innerHTML = '<p style="color:#64748b; text-align:center; padding:2rem 0;">No recent activity recorded yet.</p>';
                return;
            }

            data.forEach(activity => {
                const item = document.createElement('div');
                item.className = 'activity-item';
                item.innerHTML = `
                    <div class="activity-avatar"></div>
                    <div class="activity-details">
                        <div class="activity-description">
                            ${activity.description}
                        </div>
                        <div class="activity-time">
                            ${new Date(activity.timestamp).toLocaleString('en-US', {
                                month: 'short', day: 'numeric', year: 'numeric',
                                hour: 'numeric', minute: '2-digit', hour12: true
                            })}
                        </div>
                    </div>
                `;
                container.appendChild(item);
            });
        })
        .catch(err => console.error('Error updating activity:', err));
}

// Initial load already from PHP, then poll every 15 seconds
setInterval(updateRecentActivity, 1000);  // 15 seconds – adjust to 10000 for 10s

// Optional: Update on page focus (if tab inactive)
window.addEventListener('focus', updateRecentActivity);
</script>
</body>
</html>