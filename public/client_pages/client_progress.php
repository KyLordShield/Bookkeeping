<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tracking</title>
    <link rel="stylesheet" href="../assets/css_file/client_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <style>
        
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <?php include '../partials/navigation_bar.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>Progress Tracking</h1>
                <p>Monitor the status of your services</p>
            </div>

            <div class="progress-container">
                <!-- Left Card: Service Info -->
                <div class="progress-card">
                    <div class="progress-card-title">Bookkeeping Corporation</div>
                    <div class="progress-card-status">In Progress</div>
                </div>

                <!-- Right Card: Timeline -->
                <div class="progress-card">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-icon completed">✓</div>
                            <div class="timeline-content">
                                <div class="timeline-title">Submitted</div>
                                <div class="timeline-subtitle">Completed</div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-icon completed">✓</div>
                            <div class="timeline-content">
                                <div class="timeline-title">Under Review</div>
                                <div class="timeline-subtitle">Completed</div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-icon in-progress">⟳</div>
                            <div class="timeline-content">
                                <div class="timeline-title">Processing</div>
                                <div class="timeline-subtitle">In Progress</div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-icon pending">○</div>
                            <div class="timeline-content">
                                <div class="timeline-title">Completed</div>
                                <div class="timeline-subtitle">Pending</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>