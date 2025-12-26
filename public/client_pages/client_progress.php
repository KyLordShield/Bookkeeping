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
        <!-- Sidebar will be inserted here via PHP -->
          <?php include '../partials/navigation_bar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Progress Tracking</h1>
                <p>Monitor the status of your services and upcoming consultations</p>
            </div>

            <div class="progress-container">
                <!-- First Service Card -->
                <div class="service-card">
                    <div class="service-title">Business Registration</div>
                    <div class="service-step">Step 3 of 5</div>

                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-dot dot-completed"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">Application Submitted</div>
                                <div class="timeline-description">Initial application form submitted successfully</div>
                                <div class="timeline-date">Jan 10, 2025</div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-dot dot-completed"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">Document Verification</div>
                                <div class="timeline-description">All required documents verified and approved</div>
                                <div class="timeline-date">Jan 17, 2025</div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-dot dot-inactive"></div>
                            <div class="timeline-content">
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-dot dot-inactive"></div>
                            <div class="timeline-content">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Service Card -->
                <div class="service-card">
                    <div class="service-title">Business Registration</div>
                    <div class="service-step">Step 3 of 5</div>

                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-dot dot-completed"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">Application Submitted</div>
                                <div class="timeline-description">Initial application form submitted successfully</div>
                                <div class="timeline-date">Jan 15, 2025</div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-dot dot-completed"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">Document Verification</div>
                                <div class="timeline-description">All required documents verified and approved</div>
                                <div class="timeline-date">Jan 17, 2025</div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-dot dot-inactive"></div>
                            <div class="timeline-content">
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-dot dot-inactive"></div>
                            <div class="timeline-content">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>