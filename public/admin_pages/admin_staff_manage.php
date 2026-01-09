<?php
session_start(); // Must be at the very top

require_once '../../classes/Staff.php';

$staffObj = new Staff();
$allStaff = $staffObj->getAllStaffWithStats();

$modalStaff = null;
$modalTasks = [];
$editStaffId = null;

// ==================== PROCESS FORM SUBMISSIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'First name, last name, and email are required.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid email format.'];
        } else {
            try {
                $data = [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'phone' => $phone ?: null,
                    'position' => $position ?: null
                ];

                if ($action === 'add') {
                    $staffObj->addStaff($data);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Staff member added successfully!'];
                } elseif ($action === 'edit') {
                    $staff_id = (int)$_POST['staff_id'];
                    $staffObj->updateStaff($staff_id, $data);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Staff member updated successfully!'];
                }
            } catch (PDOException $e) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getCode() == 23000 
                    ? 'Email already exists. Please use a different email.'
                    : 'Database error occurred.'];
            }
        }
    } elseif ($action === 'delete') {
        $staff_id = (int)$_POST['staff_id'];
        if ($staffObj->hasAssignedTasks($staff_id)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Cannot delete: This staff member has assigned tasks.'];
        } else {
            $staffObj->deleteStaff($staff_id);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Staff member deleted successfully!'];
        }
    }

    // Always redirect to clean URL after POST
    header("Location: admin_staff_manage.php");
    exit;
}

// Handle task view modal
if (isset($_GET['view_staff'])) {
    $viewId = (int)$_GET['view_staff'];
    $modalStaff = $staffObj->getStaffById($viewId);
    $statusFilter = $_GET['status'] ?? null;
    $modalTasks = $staffObj->getTasksByStaffId($viewId, $statusFilter);
}

// Handle edit modal (instant open with pre-filled data)
if (isset($_GET['edit_staff'])) {
    $editStaffId = (int)$_GET['edit_staff'];
    $modalStaff = $staffObj->getStaffById($editStaffId);
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

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .workload-high { color: #e74c3c; font-weight: bold; }
        .workload-medium { color: #f39c12; font-weight: bold; }
        .workload-low { color: #27ae60; font-weight: bold; }
        .workload-none { color: #7f8c8d; }

        .status-pending { background: #f1c40f; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .status-in-progress { background: #3498db; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .status-completed { background: #27ae60; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .status-on-hold { background: #95a5a6; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }

        .status-tabs { display: flex; margin: 20px 0 15px 0; border-bottom: 2px solid #ecf0f1; }
        .status-tab { padding: 12px 24px; background: #ecf0f1; border: none; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .status-tab:hover { background: #d5dbdb; }
        .status-tab.active { background: #3498db; color: white; }

        .add-staff-btn { background: #27ae60; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 1em; margin-bottom: 20px; }
        .add-staff-btn:hover { background: #219a52; }

        .edit-btn { background: #f39c12; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px; }
        .delete-btn { background: #e74c3c; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1em; }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/temporaryNavAdmin.php'; ?>

        <div class="main-content">
            <div class="page-header">
                <div class="page-title">Staff Management</div>
                <div class="page-subtitle">Monitor staff performance and workload</div>
            </div>

            <button class="add-staff-btn" onclick="openAddEditModal('add')">+ Add New Staff</button>

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
                                    <span class="detail-label">Position:</span>
                                    <span class="detail-value"><?= htmlspecialchars($staff['position'] ?? 'N/A') ?></span>
                                </div>
                                <div class="staff-detail-row">
                                    <span class="detail-label">Active Tasks:</span>
                                    <span class="detail-value"><?= $staff['active_tasks_count'] ?></span>
                                </div>
                                <div class="staff-detail-row">
                                    <span class="detail-label">Workload:</span>
                                    <span class="detail-value workload-<?= $workloadClass ?>"><?= $workload ?></span>
                                </div>
                            </div>
                            <div style="margin-top: 15px; display: flex; gap: 8px;">
                                <button class="view-details-btn" onclick="openStaffModal(<?= $staff['staff_id'] ?>)">
                                    View Tasks
                                </button>
                                <button class="edit-btn" onclick="openAddEditModal('edit', <?= $staff['staff_id'] ?>)">
                                    Edit
                                </button>
                                <button class="delete-btn" onclick="deleteStaff(<?= $staff['staff_id'] ?>)">
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Task View Modal -->
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
                <button class="status-tab active" data-status="all" onclick="filterTasks('all')">ALL</button>
                <button class="status-tab" data-status="pending" onclick="filterTasks('pending')">PENDING</button>
                <button class="status-tab" data-status="in_progress" onclick="filterTasks('in_progress')">IN PROGRESS</button>
                <button class="status-tab" data-status="completed" onclick="filterTasks('completed')">COMPLETED</button>
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
                        <tr><td colspan="6">Click "View Tasks" to load.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Staff Modal -->
    <div id="addEditModal" class="staff-manage-modal">
        <div class="staff-manage-modal-content" style="max-width: 600px;">
            <button class="modal-close" onclick="closeAddEditModal()">×</button>
            <h2 id="modalTitle">Add New Staff</h2>
            <form method="POST" id="staffForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="staff_id" id="formStaffId">

                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" id="formFirstName" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" id="formLastName" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="formEmail" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="formPhone">
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" id="formPosition">
                </div>

                <button type="submit" style="background:#27ae60; color:white; padding:12px 24px; border:none; border-radius:8px; cursor:pointer;">
                    Save Staff
                </button>
            </form>
        </div>
    </div>

    <script>
        let currentStaffId = null;

        function openStaffModal(staffId) {
            currentStaffId = staffId;
            window.location.href = `admin_staff_manage.php?view_staff=${staffId}`;
        }

        function filterTasks(status) {
            if (!currentStaffId) {
                const params = new URLSearchParams(window.location.search);
                currentStaffId = params.get('view_staff');
            }
            if (!currentStaffId) return;
            let url = `admin_staff_manage.php?view_staff=${currentStaffId}`;
            if (status !== 'all') url += `&status=${status}`;
            window.location.href = url;
        }

        function closeStaffModal() {
            document.getElementById('staffModal').classList.remove('active');
        }

        function openAddEditModal(mode, staffId = null) {
            const modal = document.getElementById('addEditModal');
            const title = document.getElementById('modalTitle');

            if (mode === 'add') {
                title.textContent = 'Add New Staff';
                document.getElementById('formAction').value = 'add';
                document.getElementById('staffForm').reset();
                document.getElementById('formStaffId').value = '';
            } else if (mode === 'edit' && staffId) {
                title.textContent = 'Edit Staff';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('formStaffId').value = staffId;
                window.location.href = `admin_staff_manage.php?edit_staff=${staffId}`;
                return;
            }
            modal.classList.add('active');
        }

        function closeAddEditModal() {
            document.getElementById('addEditModal').classList.remove('active');
        }

        function deleteStaff(staffId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This staff member will be permanently deleted!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="staff_id" value="${staffId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        window.onclick = function(event) {
            const taskModal = document.getElementById('staffModal');
            const editModal = document.getElementById('addEditModal');
            if (event.target === taskModal) closeStaffModal();
            if (event.target === editModal) closeAddEditModal();
        }

        // Auto-load task modal
        <?php if (isset($_GET['view_staff']) && $modalStaff): ?>
            document.getElementById('modalStaffName').textContent = <?= json_encode($modalStaff['first_name'] . ' ' . $modalStaff['last_name']) ?>;
            document.getElementById('modalStaffEmail').textContent = <?= json_encode($modalStaff['email']) ?>;

            const tasksBody = document.getElementById('modalTasksBody');
            tasksBody.innerHTML = '';
            <?php if (empty($modalTasks)): ?>
                tasksBody.innerHTML = '<tr><td colspan="6">No tasks found.</td></tr>';
            <?php else: ?>
                <?php foreach ($modalTasks as $task): 
                    $clientName = $task['client_first_name'] . ' ' . $task['client_last_name'];
                    $stepDisplay = $task['step_name'] ? "Step " . $task['step_order'] . ": " . $task['step_name'] : 'General Task';
                    $statusClass = str_replace('_', '-', $task['status']);
                    $assignedDate = $task['status_changed_at'] ? date('M d, Y', strtotime($task['status_changed_at'])) : 'N/A';
                    $deadlineDate = $task['deadline'] ? date('M d, Y', strtotime($task['deadline'])) : 'No deadline';
                ?>
                    tasksBody.innerHTML += `
                        <tr>
                            <td><div class="task-client-name">${<?= json_encode($clientName) ?>}</div>
                                <div class="task-client-email">${<?= json_encode($task['client_email']) ?>}</div></td>
                            <td>${<?= json_encode($task['service_name']) ?>}</td>
                            <td>${<?= json_encode($stepDisplay) ?>}</td>
                            <td><span class="status-badge status-<?= $statusClass ?>">${<?= json_encode(ucwords(str_replace('_', ' ', $task['status']))) ?>}</span></td>
                            <td>${<?= json_encode($assignedDate) ?>}</td>
                            <td>${<?= json_encode($deadlineDate) ?>}</td>
                        </tr>`;
                <?php endforeach; ?>
            <?php endif; ?>

            document.getElementById('staffModal').classList.add('active');

            const currentFilter = <?= json_encode($statusFilter ?? 'all') ?>;
            document.querySelectorAll('.status-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.dataset.status === currentFilter || (currentFilter === 'all' && tab.dataset.status === 'all')) {
                    tab.classList.add('active');
                }
            });
        <?php endif; ?>

        // Auto-fill and open edit modal
        <?php if ($editStaffId && $modalStaff): ?>
            document.getElementById('modalTitle').textContent = 'Edit Staff';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formStaffId').value = <?= $editStaffId ?>;
            document.getElementById('formFirstName').value = <?= json_encode($modalStaff['first_name']) ?>;
            document.getElementById('formLastName').value = <?= json_encode($modalStaff['last_name']) ?>;
            document.getElementById('formEmail').value = <?= json_encode($modalStaff['email']) ?>;
            document.getElementById('formPhone').value = <?= json_encode($modalStaff['phone'] ?? '') ?>;
            document.getElementById('formPosition').value = <?= json_encode($modalStaff['position'] ?? '') ?>;
            document.getElementById('addEditModal').classList.add('active');
        <?php endif; ?>

        // Show SweetAlert2 flash message
        <?php if (isset($_SESSION['flash'])): 
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
        ?>
            Swal.fire({
                icon: '<?= $flash['type'] ?>',
                title: '<?= addslashes($flash['message']) ?>',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        <?php endif; ?>
    </script>
</body>
</html>