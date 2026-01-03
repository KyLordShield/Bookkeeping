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
        <!-- NAV BAR -->
        <?php include '../partials/temporaryNavAdmin.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>Admin / Consultant Dashboard</h1>
                <p>Manage clients, approve submissions, and resolve escalations</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">24</div>
                    <div class="stat-label">Active Clients<br>+3 this week</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">7</div>
                    <div class="stat-label">Pending Approvals<br>Require attention</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">3</div>
                    <div class="stat-label">Urgent Actions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">12</div>
                    <div class="stat-label">Active Staff</div>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <div class="card-title">Recent Activity</div>
                    
                    <div class="activity-item">
                        <div class="activity-avatar"></div>
                        <div class="activity-details">
                            <div class="activity-name">John Doe</div>
                            <div class="activity-action">Submitted Bookkeeping request</div>
                            <div class="activity-time">10 mins ago</div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-avatar"></div>
                        <div class="activity-details">
                            <div class="activity-name">Jane Smith</div>
                            <div class="activity-action">Completed HR consultation</div>
                            <div class="activity-time">1 hour ago</div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-avatar"></div>
                        <div class="activity-details">
                            <div class="activity-name">Mike Johnson</div>
                            <div class="activity-action">Uploaded required documents</div>
                            <div class="activity-time">2 hours ago</div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-avatar"></div>
                        <div class="activity-details">
                            <div class="activity-name">Sarah Williams</div>
                            <div class="activity-action">Requested meeting reschedule</div>
                            <div class="activity-time">3 hours ago</div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-avatar"></div>
                        <div class="activity-details">
                            <div class="activity-name">David Brown</div>
                            <div class="activity-action">Payment received</div>
                            <div class="activity-time">5 hours ago</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">Upcoming Meetings</div>
                    
                    <div class="meeting-item">
                        <div class="meeting-name">John Doe</div>
                        <div class="meeting-date">Feb 5, 2025</div>
                        <div class="meeting-title">Bookkeeping Review</div>
                        <div class="meeting-time">10:00 AM</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>