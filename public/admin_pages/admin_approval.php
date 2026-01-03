<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Queue</title>
    <link rel="stylesheet" href="../assets/css_file/admin_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <style>
        /* Override and add styles to exactly match the screenshot */
        .main-content {
            background: #7D1C19 !important;
            padding: 40px 20px;
        }

        .approval-header {
            color: white;
            text-align: left;
            margin-bottom: 30px;
        }

        .approval-header h1 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .approval-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .queue-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .submission-card {
            background: white;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .submission-card.highlighted {
            border-left: 6px solid #fbf8fcff; /* Purple highlight on the left */
        }

        .card-body {
            padding: 30px;
        }

        .info-line {
            margin-bottom: 18px;
            font-size: 15px;
            color: #333;
        }

        .info-line strong {
            display: inline-block;
            width: 120px;
            color: #000;
        }

        .documents-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            margin: 25px 0;
        }

        .documents-title {
            font-weight: bold;
            margin-bottom: 12px;
            color: #333;
            font-size: 14px;
            text-transform: uppercase;
        }

        .file-list {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #555;
            line-height: 1.8;
        }

        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            text-transform: uppercase;
            min-width: 180px;
        }

        .btn-approve {
            background: #7D1C19;
            color: white;
        }

        .btn-reject {
            background: #B0BEC5;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- NAV BAR -->
        <?php include '../partials/temporaryNavAdmin.php'; ?>

        <div class="main-content">
            <div class="queue-container">
                <div class="approval-header">
                    <h1>Approval Queue</h1>
                    <p>Review and approve staff submissions before proceeding</p>
                </div>

                <!-- First submission - highlighted (matches the top one in the picture) -->
                <div class="submission-card highlighted">
                    <div class="card-body">
                        <div class="info-line">
                            <strong>Client name</strong><br>
                            service
                        </div>
                        <div class="info-line">
                            <strong>staff:</strong>
                        </div>

                        <div class="documents-section">
                            <div class="documents-title">Documents submitted(2)</div>
                            <div class="file-list">
                                PASSPORT_SCAN_2026_01_02_1430.pdf (Submitted: Jan 02, 2026 2:30 PM)
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button class="btn btn-approve">Approve & Proceed</button>
                            <button class="btn btn-reject">Reject and Return</button>
                        </div>
                    </div>
                </div>

                <!-- Second submission - no highlight -->
                <div class="submission-card">
                    <div class="card-body">
                        <div class="info-line">
                            <strong>Client name</strong><br>
                            service
                        </div>
                        <div class="info-line">
                            <strong>staff:</strong>
                        </div>

                        <div class="documents-section">
                            <div class="documents-title">Documents submitted(2)</div>
                            <div class="file-list">
                               BIR_FORM_2316_2025.pdf (Submitted: Jan 02, 2026 11:15 AM)<br>
                                    VALID_ID_PHILHEALTH.jpg (Submitted: Jan 02, 2026 11:20 AM)
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button class="btn btn-approve">Approve & Proceed</button>
                            <button class="btn btn-reject">Reject and Return</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>