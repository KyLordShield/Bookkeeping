<?php
// client_dashboard.php - Updated to show only in-progress services
session_start();

// Redirect if not logged in as client
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    header("Location: ../../login_page.php");
    exit();
}

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Client.php';

$clientId = (int)$_SESSION['client_id'];

// Get client full name
$client = Client::findById($clientId);
$clientName = $client 
    ? htmlspecialchars($client['first_name'] . ' ' . $client['last_name'])
    : 'Client';

// Get ALL client's services for statistics
$allServices = Client::getClientServices($clientId);

// Filter only IN-PROGRESS services for display
$inProgressServices = array_filter($allServices, function($s) {
    return $s['overall_status'] === 'in_progress';
});

// Calculate totals from ALL services
$totalServices = count($allServices);
$inProgress = 0;
$completed  = 0;
foreach ($allServices as $s) {
    if ($s['overall_status'] === 'in_progress') $inProgress++;
    if ($s['overall_status'] === 'completed')   $completed++;
}

// Get upcoming APPROVED service requests
$upcomingRequests = Client::getUpcomingApprovedRequests($clientId, 8);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Dashboard</title>

    <link rel="stylesheet" href="../assets/css_file/client_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">

    <style>
        .header {
            margin-bottom: 2.5rem;
        }
        .header h1 {
            font-size: 2.3rem;
            margin-bottom: 0.5rem;
            color: #f6f7f8ff;
        }
        .header p {
            color: #bbc3ceff;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.6rem;
            margin-bottom: 3rem;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.8rem 1.5rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            border-left: 4px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        .stat-card.primary {
            border-left-color: #3b82f6;
        }
        .stat-card.warning {
            border-left-color: #f59e0b;
        }
        .stat-card.success {
            border-left-color: #10b981;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.95rem;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2.2rem;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.6rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.6rem;
            color: #1e293b;
        }

        .service-item {
            padding: 1.5rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .service-item:last-child { border-bottom: none; }
        .service-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.6rem;
        }
        .service-dates {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 0.8rem;
        }
        .service-status {
            margin: 0.8rem 0;
        }
        .status-in_progress {
            background: #fef3c7;
            color: #d97706;
            padding: 6px 12px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .next-step {
            color: #831212ff;
            font-weight: 500;
        }

        .request-item {
            padding: 1.3rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .request-item:last-child { border-bottom: none; }
        .request-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }
        .request-date, .request-time {
            color: #64748b;
            font-size: 0.95rem;
        }
        .request-badge {
            background: #10b981;
            color: white;
            font-size: 0.8rem;
            padding: 3px 9px;
            border-radius: 12px;
            margin-left: 0.7rem;
        }

        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/navigation_bar.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>Welcome back, <?= $clientName ?></h1>
                <p>Here is an overview of your services and progress</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-value"><?= $totalServices ?></div>
                    <div class="stat-label">Total Services Availed</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-value"><?= $inProgress ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-value"><?= $completed ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <div class="card-title">Your Current Services</div>

                    <?php if (empty($inProgressServices)): ?>
                        <p style="color:#64748b; text-align:center; padding:3rem 0;">
                            You have no services currently in progress.
                        </p>
                    <?php else: ?>
                        <?php foreach ($inProgressServices as $service): ?>
                            <div class="service-item">
                                <div class="service-name"><?= htmlspecialchars($service['service_name']) ?></div>
                                <div class="service-dates">
                                    Started: <?= $service['start_date'] ? date('M d, Y', strtotime($service['start_date'])) : 'N/A' ?>
                                </div>
                                <div class="service-status">
                                    <span class="status-in_progress">
                                        In Progress
                                    </span>
                                    <?php if (!empty($service['total_steps']) && $service['total_steps'] > 0): ?>
                                        <span style="color:#64748b; font-size:0.9rem; margin-left:0.8rem;">
                                            (<?= $service['completed_steps'] ?? 0 ?>/<?= $service['total_steps'] ?> steps)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="next-step">
                                    Next Step: <?= htmlspecialchars($service['next_step'] ?? 'N/A') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-title">Upcoming Approved Service Requests</div>

                    <?php if (empty($upcomingRequests)): ?>
                        <p style="color:#64748b; text-align:center; padding:3rem 0;">
                            No upcoming approved service requests in the next 14 days.
                        </p>
                    <?php else: ?>
                        <?php foreach ($upcomingRequests as $req): ?>
                            <div class="request-item">
                                <div class="request-title">
                                    <?= htmlspecialchars($req['title']) ?>
                                    <span class="request-badge">Approved</span>
                                </div>
                                <div class="request-date">
                                    <?= date('M d, Y', strtotime($req['event_date'])) ?>
                                </div>
                                <div class="request-time">
                                    <?= $req['event_time'] ? date('g:i A', strtotime($req['event_time'])) : 'Time not specified' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="../partials/client-profile-modal.js"></script>
</body>
</html>