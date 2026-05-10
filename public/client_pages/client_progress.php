<?php
session_start();

require_once __DIR__ . '/../../classes/Client.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../config/Database.php';

// Redirect if not logged in as client
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    header("Location: ../../login_page.php");
    exit();
}

$client_id = $_SESSION['client_id'];

// Get all client services
$services = Client::getClientServices($client_id);

// Selected service: from URL or first available
$selected_id = isset($_GET['service']) && is_numeric($_GET['service']) 
    ? (int)$_GET['service'] 
    : (!empty($services) ? $services[0]['client_service_id'] : 0);

$db = Database::getInstance()->getConnection();

function getRequirements(int $client_service_id): array {
    global $db;
    $stmt = $db->prepare("
        SELECT 
            requirement_name,
            status,
            requirement_order
        FROM client_service_requirements
        WHERE client_service_id = ?
        ORDER BY requirement_order ASC
    ");
    $stmt->execute([$client_service_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function computeProgressState(array $service): array {
    $count = Client::countRequirements($service['client_service_id']);

    if ($service['overall_status'] === 'completed') {
        return ['submitted'=>'completed', 'review'=>'completed', 'processing'=>'completed', 'completed'=>'completed'];
    }

    if ($service['overall_status'] === 'on_hold') {
        return ['submitted'=>'completed', 'review'=>'completed', 'processing'=>'on_hold', 'completed'=>'pending'];
    }

    if ($count === 0) {
        return ['submitted'=>'completed', 'review'=>'in-progress', 'processing'=>'pending', 'completed'=>'pending'];
    }

    // Default realistic flow
    return ['submitted'=>'completed', 'review'=>'completed', 'processing'=>'in-progress', 'completed'=>'pending'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Service Progress</title>
    <link rel="stylesheet" href="../assets/css_file/client_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">

    <style>
        .progress-page-header {
            font-size: 1.75rem;
            font-weight: 600;
            color: #f8fafc;
            letter-spacing: -0.02em;
            margin: 0 0 0.25rem;
        }

        .progress-container {
            display: flex;
            gap: 28px;
            margin-top: 28px;
            min-height: 500px;
        }

        /* ── LEFT SIDEBAR ─────────────────────────────────────── */
        .service-sidebar {
            width: 340px;
            min-width: 340px;
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            height: fit-content;
            position: sticky;
            top: 24px;
            align-self: flex-start;
        }

        .sidebar-title {
            margin: 0 0 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 1.05rem;
            font-weight: 600;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .service-item {
            display: block;
            padding: 14px 16px;
            margin-bottom: 8px;
            border-radius: 6px;
            text-decoration: none;
            color: #334155;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: border-color 0.15s ease, background 0.15s ease;
            font-weight: 500;
            font-size: 0.9375rem;
        }

        .service-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .service-item.active {
            background: #0f172a;
            color: #f8fafc;
            border-color: #0f172a;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
        }

        .service-item .status {
            font-size: 0.75rem;
            margin-top: 6px;
            display: block;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            opacity: 0.92;
        }

        .service-item.active .status {
            color: #cbd5e1;
        }

        /* ── RIGHT CONTENT ────────────────────────────────────── */
        .progress-main {
            flex: 1;
            background: #ffffff;
            border-radius: 8px;
            padding: 32px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }

        .service-title {
            margin: 0 0 12px;
            color: #0f172a;
            font-size: 1.35rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .meta-info {
            color: #64748b;
            margin-bottom: 28px;
            font-size: 0.9375rem;
        }

        .meta-info strong {
            color: #334155;
            font-weight: 600;
        }

        .timeline {
            margin: 28px 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }

        .timeline-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        /* Formal markers: no emoji — shape indicates state */
        .timeline-marker {
            width: 12px;
            height: 12px;
            min-width: 12px;
            margin-top: 6px;
            border-radius: 2px;
            box-sizing: border-box;
        }

        .timeline-marker.status-completed {
            background: #475569;
            border: 2px solid #475569;
        }

        .timeline-marker.status-in-progress {
            background: #ffffff;
            border: 2px solid #334155;
        }

        .timeline-marker.status-pending {
            background: #f8fafc;
            border: 2px solid #cbd5e1;
        }

        .timeline-marker.status-on-hold {
            background: #f1f5f9;
            border: 2px dashed #94a3b8;
        }

        .timeline-body {
            flex: 1;
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px 16px;
        }

        .timeline-body strong {
            color: #0f172a;
            font-weight: 600;
            font-size: 1rem;
        }

        .status-pill {
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #64748b;
            white-space: nowrap;
        }

        .status-pill.pill-completed {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #334155;
        }

        .status-pill.pill-in-progress {
            background: #ffffff;
            border-color: #334155;
            color: #0f172a;
        }

        .status-pill.pill-pending {
            background: #fafafa;
            border-color: #e2e8f0;
            color: #64748b;
        }

        .status-pill.pill-on-hold {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #475569;
        }

        .timeline-current-note {
            width: 100%;
            margin-top: 4px;
            font-size: 0.8125rem;
            color: #64748b;
            font-weight: 500;
        }

        .view-process-btn {
            margin-top: 24px;
            padding: 11px 22px;
            background: #0f172a;
            color: #f8fafc;
            border: 1px solid #0f172a;
            border-radius: 6px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
        }

        .view-process-btn:hover {
            background: #1e293b;
            border-color: #1e293b;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1000;
        }

        .modal-content {
            background: #ffffff;
            max-width: 540px;
            margin: 10vh auto;
            border-radius: 8px;
            padding: 28px 32px 32px;
            position: relative;
            border: 1px solid #e2e8f0;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
        }

        .close-btn {
            position: absolute;
            top: 16px;
            right: 20px;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            color: #64748b;
            font-weight: 400;
        }

        .close-btn:hover {
            color: #0f172a;
        }

        .modal-header {
            margin: 0 0 20px 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: #0f172a;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .req-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .req-item {
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            font-size: 0.9375rem;
        }

        .req-item:last-child { border-bottom: none; }

        .req-name {
            color: #334155;
            font-weight: 500;
        }

        .req-status {
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }

        .req-status.complete {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #334155;
        }

        .req-status.awaiting {
            background: #ffffff;
            border-color: #cbd5e1;
            color: #64748b;
        }

        .empty-hint {
            padding: 40px 20px;
            text-align: center;
            color: #64748b;
            font-size: 0.9375rem;
        }

        @media (max-width: 992px) {
            .progress-container {
                flex-direction: column;
            }
            .service-sidebar {
                width: 100%;
                position: static;
                margin-bottom: 24px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <?php include '../partials/navigation_bar.php'; ?>

    <div class="main-content">
        <h1 class="progress-page-header">Service progress</h1>
        <p style="margin:0 0 8px;color:#cbd5e1;font-size:0.95rem;">Track stages for each engagement.</p>

        <div class="progress-container">

            <!-- LEFT - Service List -->
            <div class="service-sidebar">
                <h3 class="sidebar-title">My Services</h3>

                <?php if (empty($services)): ?>
                    <div class="empty-hint">
                        <p style="margin:0 0 8px;font-weight:600;color:#475569;">No services on file</p>
                        <small>Approved engagements will be listed here.</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($services as $srv): 
                        $isActive = ($srv['client_service_id'] === $selected_id);
                    ?>
                    <a href="?service=<?= $srv['client_service_id'] ?>" 
                       class="service-item <?= $isActive ? 'active' : '' ?>">
                        <?= htmlspecialchars($srv['service_name']) ?>
                        <span class="status">
                            <?= ucfirst($srv['overall_status']) ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- RIGHT - Progress Details -->
            <div class="progress-main">
                <?php if ($selected_id > 0 && !empty($services)):
                    $current = null;
                    foreach ($services as $s) {
                        if ($s['client_service_id'] === $selected_id) {
                            $current = $s;
                            break;
                        }
                    }

                    if ($current):
                        $state = computeProgressState($current);
                        $requirements = getRequirements($selected_id);
                ?>

                    <h2 class="service-title"><?= htmlspecialchars($current['service_name']) ?></h2>

                    <div class="meta-info">
                        Status: <strong><?= ucfirst($current['overall_status']) ?></strong>
                        <?php if (!empty($current['deadline'])): ?>
                             • Deadline: <?= date('M d, Y', strtotime($current['deadline'])) ?>
                        <?php endif; ?>
                    </div>

                    <div class="timeline">
                        <?php
                        $steps = [
                            'submitted'  => 'Application Submitted',
                            'review'     => 'Under Review',
                            'processing' => 'Processing',
                            'completed'  => 'Completed'
                        ];
                        foreach ($steps as $key => $label):
                            $status = $state[$key] ?? 'pending';
                            $markerClass = 'status-' . str_replace('_', '-', $status);
                            $pillClass = match ($status) {
                                'completed' => 'pill-completed',
                                'in-progress' => 'pill-in-progress',
                                'on_hold' => 'pill-on-hold',
                                default => 'pill-pending',
                            };
                            $statusLabel = match ($status) {
                                'completed' => 'Complete',
                                'in-progress' => 'In progress',
                                'on_hold' => 'On hold',
                                default => 'Pending',
                            };
                        ?>
                            <div class="timeline-item">
                                <div class="timeline-marker <?= htmlspecialchars($markerClass) ?>" title="<?= htmlspecialchars($statusLabel) ?>" aria-hidden="true"></div>
                                <div class="timeline-body">
                                    <strong><?= htmlspecialchars($label) ?></strong>
                                    <span class="status-pill <?= htmlspecialchars($pillClass) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                    <?php if ($status === 'in-progress'): ?>
                                        <span class="timeline-current-note">Current stage.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($state['processing'] === 'in-progress' && !empty($requirements)): ?>
                        <button type="button" class="view-process-btn" onclick="openModal()">
                            View process details
                        </button>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-hint" style="padding:80px 20px;">
                        <p style="margin:0;font-weight:500;">Select a service from the list to view progress.</p>
                    </div>
                <?php endif; ?>

                <?php else: ?>
                    <div class="empty-hint" style="padding:80px 20px;">
                        <p style="margin:0;">You do not have any services to display yet.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal" id="processModal">
    <div class="modal-content">
        <button type="button" class="close-btn" onclick="closeModal()" aria-label="Close">&times;</button>
        <h2 class="modal-header">Process requirements</h2>

        <ul class="req-list" id="requirementsList"></ul>

        <button class="view-process-btn" style="width:100%; margin-top:24px;" onclick="closeModal()">
            Close
        </button>
    </div>
</div>

<script>
function openModal() {
    const modal = document.getElementById('processModal');
    const list = document.getElementById('requirementsList');
    list.innerHTML = '';

    <?php if (!empty($requirements) && $selected_id > 0): ?>
        <?php foreach ($requirements as $req): ?>
            <?php
                $done = ($req['status'] ?? '') === 'completed';
                $reqLabel = $done ? 'Complete' : 'Awaiting';
                $reqClass = $done ? 'complete' : 'awaiting';
            ?>
            list.innerHTML += `
                <li class="req-item">
                    <span class="req-name"><?= htmlspecialchars($req['requirement_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="req-status <?= $reqClass ?>"><?= $reqLabel ?></span>
                </li>`;
        <?php endforeach; ?>
    <?php else: ?>
        list.innerHTML = '<li class="empty-hint" style="padding:24px;">No detailed steps are available yet.</li>';
    <?php endif; ?>

    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('processModal').style.display = 'none';
}

window.onclick = function(e) {
    if (e.target.id === 'processModal') {
        closeModal();
    }
}
</script>
<script src="../partials/client-profile-modal.js"></script>
</body>
</html>