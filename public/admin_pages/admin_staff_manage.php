<?php
require_once '../../classes/Staff.php';

$staffObj = new Staff();
$allStaff = $staffObj->getAllStaffWithStats();


$modalStaff = null;
$modalTasks = [];

if (isset($_GET['view_staff'])) {
    $viewId = (int)$_GET['view_staff'];
    $modalStaff = $staffObj->getStaffById($viewId);
    $statusFilter = $_GET['status'] ?? null;
    $modalTasks = $staffObj->getTasksByStaffId($viewId, $statusFilter);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management</title>
    <link rel="stylesheet" href="../assets/css_file/admin_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <style>
        .workload-high { color: #e74c3c; font-weight: bold; }
        .workload-medium { color: #f39c12; font-weight: bold; }
        .workload-low { color: #27ae60; font-weight: bold; }
        .workload-none { color: #7f8c8d; }
        
        .status-pending { background: #f1c40f; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .status-in-progress { background: #3498db; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .status-completed { background: #27ae60; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
    </style>
</head>
<body>
    <div class="container">
        <!-- NAV BAR -->
        <?php include '../partials/temporaryNavAdmin.php'; ?>

        <div class="main-content">
            <div class="page-header">
                <div class="page-title">Staff Management</div>
                <div class="page-subtitle">Monitor staff performance and workload</div>
            </div>

            <div class="staff-grid">
                <?php if (empty($allStaff)): ?>
                    <p>No staff members found.</p>
                <?php else: ?>
                    <?php foreach ($allStaff as $staff): 
                        $fullName = $staff['first_name'] . ' ' . $staff['last_name'];
                        $workload = $staffObj->getWorkloadLevel($staff['active_tasks_count']);
                        $workloadClass = $staffObj->getWorkloadClass($workload);
                    ?>
                        <div class="staff-card">
                            <div class="staff-header">
                                <div class="staff-avatar"></div>
                                <div class="staff-info">
                                    <div class="staff-name"><?= htmlspecialchars($fullName) ?></div>
                                    <div class="staff-email"><?= htmlspecialchars($staff['email']) ?></div>
                                </div>
                            </div>
                            <div class="staff-details">
                                <div class="staff-detail-row">
                                    <span class="detail-label">Contact:</span>
                                    <span class="detail-value"><?= htmlspecialchars($staff['phone'] ?? 'N/A') ?></span>
                                </div>
                                <div class="staff-detail-row">
                                    <span class="detail-label">Active Tasks:</span>
                                    <span class="detail-value"><?= $staff['active_tasks_count'] ?></span>
                                </div>
                                <div class="staff-detail-row">
                                    <span class="detail-label">Completed Tasks:</span>
                                    <span class="detail-value"><?= $staff['completed_tasks_count'] ?></span>
                                </div>
                                <div class="staff-detail-row">
                                    <span class="detail-label">Workload:</span>
                                    <span class="detail-value workload-<?= $workloadClass ?>"><?= $workload ?></span>
                                </div>
                            </div>
                            <button class="view-details-btn" onclick="openStaffModal(<?= $staff['staff_id'] ?>)">
                                View Task Details
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Staff Task Details Modal -->
    <div id="staffModal" class="staff-manage-modal">
        <div class="staff-manage-modal-content">
            <button class="modal-close" onclick="closeStaffModal()">×</button>
            
            <div class="modal-staff-header">
                <div class="modal-staff-avatar"></div>
                <div class="modal-staff-info">
                    <div class="modal-staff-name" id="modalStaffName">Loading...</div>
                    <div class="modal-staff-email" id="modalStaffEmail">Loading...</div>
                </div>
            </div>

                <div class="status-tabs">
                    <button class="status-tab active" onclick="filterTasks('all')">ALL</button>
                    <button class="status-tab" onclick="filterTasks('pending')">PENDING</button>
                    <button class="status-tab" onclick="filterTasks('in_progress')">IN PROGRESS</button>
                    <button class="status-tab" onclick="filterTasks('completed')">COMPLETED</button>
                </div>
                <div class="table-wrapper">
                <table class="tasks-table">
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Service</th>
                            <th>Assigned Step</th>
                            <th>Status</th>
                            <th>Date Assigned</th>
                            <th>Deadline</th>
                        </tr>
                    </thead>
                    <tbody id="modalTasksBody">
                        <tr><td colspan="6">Click "View Task Details" on a staff card to load tasks.</td></tr>
                    </tbody>
                </table>
                </div>
        </div>
    </div>

    <script>
        let currentStaffId = null;

        function openStaffModal(staffId) {
            currentStaffId = staffId;
            const modal = document.getElementById('staffModal');
            modal.classList.add('active');

            // Load staff tasks via page reload with query params (simple approach)
            // For better UX, you could switch to AJAX later
            window.location.href = `?view_staff=${staffId}`;
        }

        function filterTasks(status) {
            if (!currentStaffId) return;
            let url = `?view_staff=${currentStaffId}`;
            if (status !== 'all') {
                url += `&status=${status}`;
            }
            window.location.href = url;
        }

        function closeStaffModal() {
            document.getElementById('staffModal').classList.remove('active');
            // Optional: clear URL params
            // history.replaceState({}, '', window.location.pathname);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('staffModal');
            if (event.target === modal) {
                closeStaffModal();
            }
        }

        // Auto-load modal content if opened via URL
        <?php if ($modalStaff): ?>
            document.getElementById('modalStaffName').textContent = "<?= htmlspecialchars($modalStaff['first_name'] . ' ' . $modalStaff['last_name']) ?>";
            document.getElementById('modalStaffEmail').textContent = "<?= htmlspecialchars($modalStaff['email']) ?>";

            const tasksBody = document.getElementById('modalTasksBody');
            tasksBody.innerHTML = '';

            <?php if (empty($modalTasks)): ?>
                tasksBody.innerHTML = '<tr><td colspan="6">No tasks found.</td></tr>';
            <?php else: ?>
                <?php foreach ($modalTasks as $task): 
                    $clientName = $task['client_first_name'] . ' ' . $task['client_last_name'];
                    $stepDisplay = $task['step_name'] ? "Step " . $task['step_order'] . ": " . $task['step_name'] : 'General Task';
                    $statusClass = str_replace('_', '-', $task['status']);
                    $assignedDate = $task['status_changed_at'] ? date('M d', strtotime($task['status_changed_at'])) : 'N/A';
                    $deadlineDate = $task['deadline'] ? date('M d', strtotime($task['deadline'])) : 'No deadline';
                ?>
                    tasksBody.innerHTML += `
                        <tr>
                            <td>
                                <div class="task-client-name"><?= htmlspecialchars($clientName) ?></div>
                                <div class="task-client-email"><?= htmlspecialchars($task['client_email']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($task['service_name']) ?></td>
                            <td><div class="task-step"><?= nl2br(htmlspecialchars($stepDisplay)) ?></div></td>
                            <td><span class="status-badge status-<?= $statusClass ?>"><?= ucwords(str_replace('_', ' ', $task['status'])) ?></span></td>
                            <td><?= $assignedDate ?></td>
                            <td><?= $deadlineDate ?></td>
                        </tr>
                    `;
                <?php endforeach; ?>
            <?php endif; ?>

            document.getElementById('staffModal').classList.add('active');
        <?php endif; ?>
    </script>
</body>
</html>