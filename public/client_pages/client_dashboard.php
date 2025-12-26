<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Dashboard</title>
    
    <!-- Global styles that apply to all pages -->
    <style>
        
    </style>

    <!-- Include the sidebar-specific CSS -->
     <link rel="stylesheet" href="../assets/css_file/client_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    
</head>
<body>
    <div class="container">
        <?php include '../partials/navigation_bar.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>Welcome back, USER</h1>
                <p>Here is an overview of your services and progress</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-label">Total Service Availed</div>
                </div>
                <div class="stat-card">
                    <div class="spinner"></div>
                    <div class="stat-label">In progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✓</div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <div class="card-title">Current Service Progress</div>
                    
                    <div class="service-item">
                        <div class="service-name">Business Registration</div>
                        <div class="service-dates">Started: Jan 10, 2025</div>
                        <div class="service-dates">Progress</div>
                        <div class="service-status">
                            <span class="status-progress">in progress</span>
                        </div>
                        <div class="next-step">Next Step: Permanent Verification</div>
                    </div>

                    <div class="divider"></div>

                    <div class="service-item">
                        <div class="service-name">Tax Consultation</div>
                        <div class="service-dates">Completed: Jan 15, 2025</div>
                        <div class="service-dates">Progress</div>
                        <div class="service-status">
                            <span class="status-completed">completed</span>
                        </div>
                        <div class="next-step">Next Step: N/A</div>
                    </div>

                    <div class="divider"></div>

                    <div class="service-item">
                        <div class="service-name">Legal Advisory</div>
                        <div class="service-dates">Started: Jan 20, 2025</div>
                        <div class="service-dates">Progress</div>
                        <div class="service-status">
                            <span class="status-progress">in progress</span>
                        </div>
                        <div class="next-step">Next Step: Initial Consultation Scheduled</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">Upcoming Appointments</div>
                    
                    <div class="appointment">
                        <div class="appointment-title">Consultation Session</div>
                        <div class="appointment-date">Jan 28, 2025</div>
                        <div class="appointment-time">10:00 AM</div>
                        <div class="appointment-type">Video Call</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>