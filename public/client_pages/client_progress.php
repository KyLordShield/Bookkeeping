<?php
session_start();

require_once __DIR__ . '/../../classes/Client.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../config/Database.php';

if (!User::isLoggedIn() || User::getRole() !== 'client') {
    header('Location: ../login_page.php');
    exit;
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
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.09);
            height: fit-content;
            position: sticky;
            top: 24px;
            align-self: flex-start;
        }

        .sidebar-title {
            margin: 0 0 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
            font-size: 1.25rem;
            color: #1f2937;
        }

        .service-item {
            display: block;
            padding: 14px 18px;
            margin-bottom: 10px;
            border-radius: 8px;
            text-decoration: none;
            color: #1f2937;
            background: #f9fafb;
            border: 1px solid #e2e8f0;
            transition: all 0.18s ease;
            font-weight: 500;
        }

        .service-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateX(3px);
        }

        .service-item.active {
            background: #000000ff;
            color: white;
            border-color: #121312ff;
            box-shadow: 0 3px 12px rgba(46,125,50,0.28);
        }

        .service-item .status {
            font-size: 0.82rem;
            margin-top: 5px;
            display: block;
            opacity: 0.95;
        }

        /* ── RIGHT CONTENT ────────────────────────────────────── */
        .progress-main {
            flex: 1;
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.09);
        }

        .service-title {
            margin: 0 0 24px;
            color: #111827;
        }

        .meta-info {
            color: #4b5563;
            margin-bottom: 32px;
            font-size: 0.98rem;
        }

        .timeline {
            margin: 32px 0;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 14px 0;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            color: white;
        }

        .status-completed   { background: #22c55e; }
        .status-in-progress  { background: #f59e0b; }
        .status-pending     { background: #9ca3af; }
        .status-on-hold     { background: #ef4444; }

        .view-process-btn {
            margin-top: 28px;
            padding: 12px 24px;
            background: #0b0c0bff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.05rem;
            cursor: pointer;
            transition: background .2s;
        }

        .view-process-btn:hover {
            background: #5a0f12ff;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            max-width: 540px;
            margin: 10vh auto;
            border-radius: 12px;
            padding: 32px;
            position: relative;
        }

        .close-btn {
            position: absolute;
            top: 18px;
            right: 24px;
            font-size: 28px;
            cursor: pointer;
            color: #6b7280;
        }

        .modal-header {
            margin: 0 0 24px 0;
        }

        .req-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .req-item {
            padding: 14px 0;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 1.08rem;
        }

        .req-item:last-child { border-bottom: none; }

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
        <h1>My Service Progress</h1>

        <div class="progress-container">

            <!-- LEFT - Service List -->
            <div class="service-sidebar">
                <h3 class="sidebar-title">My Services</h3>

                <?php if (empty($services)): ?>
                    <div style="padding:40px 20px; text-align:center; color:#6b7280;">
                        <p>No active services yet</p>
                        <small>When your service requests are approved, they will appear here.</small>
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
                            $class = "status-$status";
                            $icon = match($status) {
                                'completed'    => '✓',
                                'in-progress'  => '⟳',
                                'on_hold'      => '!',
                                default        => '○'
                            };
                        ?>
                            <div class="timeline-item">
                                <div class="timeline-icon <?= $class ?>"><?= $icon ?></div>
                                <div>
                                    <strong><?= $label ?></strong>
                                    <?php if ($status === 'in-progress'): ?>
                                        <span style="color:#f59e0b; font-weight:500;"> (Current Stage)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($state['processing'] === 'in-progress' && !empty($requirements)): ?>
                        <button class="view-process-btn" onclick="openModal()">
                            View Process Details →
                        </button>
                    <?php endif; ?>

                <?php else: ?>
                    <div style="padding:80px 20px; text-align:center; color:#6b7280;">
                        <p>Select a service from the list to view its progress</p>
                    </div>
                <?php endif; ?>

                <?php else: ?>
                    <div style="padding:80px 20px; text-align:center; color:#6b7280;">
                        <p>You don't have any active services yet.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal" id="processModal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">×</span>
        <h2 class="modal-header">Process Requirements</h2>

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
            list.innerHTML += `
                <li class="req-item">
                    <span style="font-size:1.5rem;">
                        <?= $req['status'] === 'completed' ? '✅' : '⏳' ?>
                    </span>
                    <span><?= htmlspecialchars(addslashes($req['requirement_name'])) ?></span>
                </li>`;
        <?php endforeach; ?>
    <?php else: ?>
        list.innerHTML = '<li style="padding:30px; color:#6b7280; text-align:center;">No detailed steps available yet.</li>';
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
</body>
</html>