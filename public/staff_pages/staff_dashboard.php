<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login_page.php');
    exit;
}

// Get the staff_id from the database using user_id
$user_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Fetch the staff_id for this user
$stmt = $db->prepare("SELECT staff_id FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data || !$user_data['staff_id']) {
    die("Error: Staff ID not found for this user");
}

$staff_id = $user_data['staff_id'];

// Handle AJAX requests for updating requirement status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_status') {
        $requirement_id = $_POST['requirement_id'] ?? 0;
        $new_status = $_POST['status'] ?? '';
        
        try {
            $stmt = $db->prepare("
                UPDATE client_service_requirements 
                SET status = ?, updated_at = NOW() 
                WHERE requirement_id = ? AND assigned_staff_id = ?
            ");
            $success = $stmt->execute([$new_status, $requirement_id, $staff_id]);
            
            // Update overall client_service status if all requirements are completed
            if ($success) {
                $stmt = $db->prepare("
                    SELECT client_service_id FROM client_service_requirements 
                    WHERE requirement_id = ?
                ");
                $stmt->execute([$requirement_id]);
                $cs = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cs) {
                    // Check if all requirements are completed
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as total,
                               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                        FROM client_service_requirements 
                        WHERE client_service_id = ?
                    ");
                    $stmt->execute([$cs['client_service_id']]);
                    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($counts['total'] > 0 && $counts['total'] == $counts['completed']) {
                        $stmt = $db->prepare("
                            UPDATE client_services 
                            SET overall_status = 'completed' 
                            WHERE client_service_id = ?
                        ");
                        $stmt->execute([$cs['client_service_id']]);
                    }
                }
            }
            
            echo json_encode(['success' => $success, 'message' => 'Status updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'update_checklist') {
        $requirement_id = $_POST['requirement_id'] ?? 0;
        $progress_data = $_POST['progress_data'] ?? '';
        
        try {
            $stmt = $db->prepare("
                UPDATE client_service_requirements 
                SET progress_data = ?, updated_at = NOW() 
                WHERE requirement_id = ? AND assigned_staff_id = ?
            ");
            $success = $stmt->execute([$progress_data, $requirement_id, $staff_id]);
            
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Get filter from URL
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter - ONLY show tasks assigned to THIS staff member
$whereClause = "WHERE csr.assigned_staff_id = ?";
$params = [$staff_id];

// Don't add additional filters for now to keep it simple
// if ($filter === 'in_progress') {
//     $whereClause .= " AND csr.status = 'in_progress'";
// } elseif ($filter === 'pending_approval') {
//     $whereClause .= " AND csr.status = 'pending_approval'";
// } elseif ($filter === 'urgent') {
//     $whereClause .= " AND cs.deadline IS NOT NULL AND DATEDIFF(cs.deadline, NOW()) <= 3 AND csr.status != 'completed'";
// }

// Fetch tasks assigned to this staff member ONLY
// GROUP BY client_service_id so we don't show duplicate rows
$query = "
    SELECT 
        cs.client_service_id,
        c.first_name, c.last_name, c.email, c.phone,
        s.service_name,
        cs.overall_status as service_status,
        cs.start_date,
        cs.deadline,
        cs.created_at as service_created_at,
        COUNT(csr.requirement_id) as total_steps,
        SUM(CASE WHEN csr.status = 'completed' THEN 1 ELSE 0 END) as completed_steps,
        GROUP_CONCAT(csr.requirement_name ORDER BY csr.requirement_order SEPARATOR ', ') as all_steps
    FROM client_service_requirements csr
    JOIN client_services cs ON csr.client_service_id = cs.client_service_id
    JOIN clients c ON cs.client_id = c.client_id
    JOIN services s ON cs.service_id = s.service_id
    $whereClause
    GROUP BY cs.client_service_id, c.first_name, c.last_name, c.email, c.phone, 
             s.service_name, cs.overall_status, cs.start_date, cs.deadline, cs.created_at
    ORDER BY 
        cs.deadline ASC,
        cs.created_at DESC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats for THIS staff member only
$stats_query = "
    SELECT 
        COUNT(DISTINCT cs.client_service_id) as total,
        COUNT(DISTINCT CASE WHEN cs.overall_status = 'in_progress' THEN cs.client_service_id END) as in_progress,
        COUNT(DISTINCT CASE WHEN cs.overall_status = 'pending_approval' THEN cs.client_service_id END) as pending_approval,
        COUNT(DISTINCT CASE WHEN cs.deadline IS NOT NULL AND DATEDIFF(cs.deadline, NOW()) <= 3 
              AND cs.overall_status != 'completed' THEN cs.client_service_id END) as urgent
    FROM client_service_requirements csr
    JOIN client_services cs ON csr.client_service_id = cs.client_service_id
    WHERE csr.assigned_staff_id = ?
";
$stmt = $db->prepare($stats_query);
$stmt->execute([$staff_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks</title>
    <link rel="stylesheet" href="../assets/css_file/staff_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    
    <!-- SweetAlert2 for nice animations -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal.show {
            display: block;
        }

        .modal-content {
            background-color: #fff;
            margin: 2% auto;
            padding: 30px;
            width: 90%;
            max-width: 900px;
            border-radius: 8px;
            position: relative;
        }

        .close-btn {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .back-link {
            color: #333;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .service-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }

        .service-info-item label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 12px;
            color: #666;
        }

        .service-info-item input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }

        .timeline-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .timeline-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .timeline-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
            color: white;
            font-weight: bold;
        }

        .timeline-dot.blue {
            background: #4A90E2;
        }

        .timeline-dot.yellow {
            background: #F5C542;
        }

        .timeline-dot.green {
            background: #7ED321;
        }

        .timeline-content h4 {
            margin: 0 0 5px 0;
        }

        .timeline-content p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .checklist-section {
            background: #f0f0f0;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .checklist-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            background: white;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .checklist-item input[type="checkbox"] {
            margin-right: 10px;
            width: 20px;
            height: 20px;
        }

        .checklist-item label {
            flex: 1;
            cursor: pointer;
        }

        .file-upload-btn {
            background: #333;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .warning-text {
            color: #E74C3C;
            font-size: 14px;
            margin-top: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .modal-action-btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }

        .btn-update {
            background: #4A90E2;
            color: white;
        }

        .btn-submit {
            background: #7ED321;
            color: white;
        }

        .btn-admin {
            background: #E74C3C;
            color: white;
        }

        .modal-action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending {
            background: #f0f0f0;
            color: #666;
        }

        .status-in_progress {
            background: #fff3cd;
            color: #856404;
        }

        .status-pending_approval {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        /* Loading animation */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.show {
            display: flex;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/temporaryNavStaff.php'; ?>

        <div class="main-content">
            <h1>My Tasks</h1>
            <p class="subtitle">Manage your assigned tasks and update their status</p>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Assigned Tasks</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['in_progress'] ?></h3>
                    <p>In Progress</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['pending_approval'] ?></h3>
                    <p>Waiting for Approval</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['urgent'] ?></h3>
                    <p>Urgent</p>
                </div>
            </div>

            <div class="filter-section">
                <span class="filter-label">Filtered by Status:</span>
                <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" onclick="filterTasks('all')">All</button>
                <button class="filter-btn <?= $filter === 'in_progress' ? 'active' : '' ?>" onclick="filterTasks('in_progress')">In Progress</button>
                <button class="filter-btn <?= $filter === 'pending_approval' ? 'active' : '' ?>" onclick="filterTasks('pending_approval')">Waiting for Approval</button>
                <button class="filter-btn <?= $filter === 'urgent' ? 'active' : '' ?>" onclick="filterTasks('urgent')">Urgent</button>
            </div>

            <div class="tasks-table">
                <table>
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Service</th>
                            <th>What to do</th>
                            <th>Status</th>
                            <th>Date Assigned</th>
                            <th>Deadline</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                No tasks assigned to you yet
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($task['first_name'] . ' ' . $task['last_name']) ?><br>
                                <small><?= htmlspecialchars($task['email']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($task['service_name']) ?></td>
                            <td>
                                <strong><?= $task['total_steps'] ?> Steps Assigned</strong><br>
                                <small style="color: #666;"><?= htmlspecialchars($task['all_steps']) ?></small>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $task['service_status'] ?>">
                                    <?= ucwords(str_replace('_', ' ', $task['service_status'])) ?>
                                </span>
                                <br>
                                <small style="color: #666; font-size: 11px;">
                                    <?= $task['completed_steps'] ?>/<?= $task['total_steps'] ?> completed
                                </small>
                            </td>
                            <td><?= $task['service_created_at'] ? date('M d, Y', strtotime($task['service_created_at'])) : ($task['start_date'] ? date('M d, Y', strtotime($task['start_date'])) : '—') ?></td>
                            <td><?= $task['deadline'] ? date('M d, Y', strtotime($task['deadline'])) : 'Not set' ?></td>
                            <td>
                                <button class="action-btn" onclick='console.log("Clicked!", <?= json_encode($task) ?>); openTaskModal(<?= json_encode($task) ?>);'>Open</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Task Detail Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            
            <a href="#" class="back-link" onclick="closeModal(); return false;">← Back to my Task</a>
            
            <div class="modal-header">
                <h2 id="modalServiceName"></h2>
            </div>

            <div class="service-info">
                <div class="service-info-item">
                    <label>SERVICE AVAILED:</label>
                    <input type="text" id="serviceAvailed" readonly>
                </div>
                <div class="service-info-item">
                    <label>CLIENT CONTACT:</label>
                    <input type="text" id="clientContact" readonly>
                </div>
                <div class="service-info-item">
                    <label>DEADLINE:</label>
                    <input type="text" id="taskDeadline" readonly>
                </div>
            </div>

            <div class="timeline-section">
                <h3>Status Timeline</h3>
                <div id="timelineContainer"></div>
            </div>

            <div class="checklist-section">
                <h3>Task Requirements</h3>
                <div id="checklistContainer"></div>
                <p class="warning-text">! Please complete all requirements before taking action</p>
            </div>

            <div class="action-buttons">
                <button class="modal-action-btn btn-update" onclick="updateStatus('in_progress')">START / UPDATE</button>
                <button class="modal-action-btn btn-submit" onclick="updateStatus('pending_approval')">SUBMIT FOR APPROVAL</button>
                <button class="modal-action-btn btn-admin" onclick="updateStatus('needs_review')">NEEDS ADMIN REVIEW</button>
            </div>
        </div>
    </div>

    <script>
        let currentRequirementId = null;
        let currentTask = null;

        function filterTasks(filter) {
            // Show loading
            document.getElementById('loadingOverlay').classList.add('show');
            
            // Navigate to filtered page
            window.location.href = '?filter=' + filter;
        }

        function openTaskModal(task) {
            currentTask = task;
            
            document.getElementById('modalServiceName').textContent = task.service_name;
            document.getElementById('serviceAvailed').value = task.service_name;
            document.getElementById('clientContact').value = task.email + (task.phone ? ' • ' + task.phone : '');
            document.getElementById('taskDeadline').value = task.deadline ? new Date(task.deadline).toLocaleDateString() : 'Not set';

            // Build timeline based on overall service status
            const timeline = document.getElementById('timelineContainer');
            let timelineHTML = `
                <div class="timeline-item">
                    <div class="timeline-dot blue">📋</div>
                    <div class="timeline-content">
                        <h4>Service Started</h4>
                        <p>Assigned by Admin</p>
                    </div>
                </div>
            `;

            if (task.service_status === 'in_progress' || task.service_status === 'pending_approval' || task.service_status === 'completed') {
                timelineHTML += `
                    <div class="timeline-item">
                        <div class="timeline-dot yellow">⏳</div>
                        <div class="timeline-content">
                            <h4>In Progress</h4>
                            <p>${task.completed_steps} of ${task.total_steps} steps completed</p>
                        </div>
                    </div>
                `;
            }

            if (task.service_status === 'completed') {
                timelineHTML += `
                    <div class="timeline-item">
                        <div class="timeline-dot green">✓</div>
                        <div class="timeline-content">
                            <h4>Completed</h4>
                            <p>All steps finished!</p>
                        </div>
                    </div>
                `;
            }

            timeline.innerHTML = timelineHTML;

            // Fetch ALL requirements for this client_service_id
            fetchAllRequirements(task.client_service_id);

            document.getElementById('taskModal').classList.add('show');
        }

        function fetchAllRequirements(client_service_id) {
            const checklist = document.getElementById('checklistContainer');
            checklist.innerHTML = '<p style="text-align: center; padding: 20px;">Loading all steps...</p>';

            // Adjust the path based on where you saved the file
            fetch(`../staff_pages/get_all_requirements.php?client_service_id=${client_service_id}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    if (data.success && data.requirements) {
                        buildRequirementsList(data.requirements);
                    } else {
                        checklist.innerHTML = '<p style="color: red;">Failed to load requirements: ' + (data.error || 'Unknown error') + '</p>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    checklist.innerHTML = '<p style="color: red;">Error loading requirements: ' + error.message + '</p>';
                });
        }

        function buildRequirementsList(requirements) {
            const checklist = document.getElementById('checklistContainer');
            let html = '';

            requirements.forEach(req => {
                const isYourTask = req.requirement_id == currentRequirementId;
                const statusClass = req.status || 'pending';
                const statusText = (req.status || 'pending').replace(/_/g, ' ').toUpperCase();
                
                let savedProgress = {};
                try {
                    savedProgress = req.progress_data ? JSON.parse(req.progress_data) : {};
                } catch(e) {
                    savedProgress = {};
                }

                const items = [
                    'Bank statement',
                    'Receipts organized', 
                    'Data Entry',
                    'Reconciliation Done',
                    'Report prepared'
                ];

                html += `
                    <div class="requirement-block" style="
                        background: ${isYourTask ? '#fffbea' : 'white'};
                        border: 2px solid ${isYourTask ? '#f59e0b' : '#ddd'};
                        border-radius: 8px;
                        padding: 15px;
                        margin-bottom: 15px;
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h4 style="margin: 0;">
                                ${isYourTask ? '🎯 ' : ''}Step ${req.requirement_order}: ${req.requirement_name}
                                ${isYourTask ? ' (YOUR TASK)' : ''}
                            </h4>
                            <span class="status-badge status-${statusClass}" style="font-size: 11px;">
                                ${statusText}
                            </span>
                        </div>
                        
                        ${isYourTask ? `
                            <div style="margin-top: 10px;">
                                ${items.map((item, idx) => {
                                    const checked = savedProgress[item] ? 'checked' : '';
                                    return `
                                        <div class="checklist-item">
                                            <input type="checkbox" id="check${idx}" ${checked} 
                                                   onchange="updateChecklist('${item}', this.checked)">
                                            <label for="check${idx}">${item}</label>
                                            <button class="file-upload-btn">📎</button>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        ` : `
                            <p style="color: #666; font-size: 14px; margin: 10px 0 0 0;">
                                Assigned to: ${req.assigned_staff_name || 'Another staff member'}
                            </p>
                        `}
                    </div>
                `;
            });

            checklist.innerHTML = html;
        }

        function closeModal() {
            document.getElementById('taskModal').classList.remove('show');
            currentRequirementId = null;
            currentTask = null;
        }

        function updateStatus(status) {
            if (!currentRequirementId) return;

            Swal.fire({
                title: 'Update Status?',
                text: 'Are you sure you want to update the status to: ' + status.replace(/_/g, ' ').toUpperCase() + '?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, update it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Updating...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData();
                    formData.append('action', 'update_status');
                    formData.append('requirement_id', currentRequirementId);
                    formData.append('status', status);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Status updated successfully!',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed',
                                text: data.message || 'Failed to update status'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while updating status'
                        });
                    });
                }
            });
        }

        function updateChecklist(item, checked) {
            if (!currentRequirementId) return;

            // Get current progress
            let progress = {};
            try {
                progress = currentTask.progress_data ? JSON.parse(currentTask.progress_data) : {};
            } catch(e) {
                progress = {};
            }

            // Update the item
            progress[item] = checked;

            const formData = new FormData();
            formData.append('action', 'update_checklist');
            formData.append('requirement_id', currentRequirementId);
            formData.append('progress_data', JSON.stringify(progress));

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update local state
                    currentTask.progress_data = JSON.stringify(progress);
                    
                    // Show toast notification
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });

                    Toast.fire({
                        icon: 'success',
                        title: checked ? 'Item checked!' : 'Item unchecked!'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: 'Could not update checklist',
                        toast: true,
                        position: 'top-end',
                        timer: 3000
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        window.onclick = function(e) {
            const modal = document.getElementById('taskModal');
            if (e.target === modal) {
                closeModal();
            }
        }

        // Check if page just loaded from filter
        window.addEventListener('load', function() {
            setTimeout(() => {
                document.getElementById('loadingOverlay').classList.remove('show');
            }, 300);
        });
    </script>
</body>
</html>