<?php
session_start();
//Auth check:
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../login_page.php");
    exit();
}

require_once '../../config/cloudinary.php';
require_once '../../classes/Staff.php';

$staffObj = new Staff($cloudinary);

$allStaff = $staffObj->getAllStaffWithStats();

$modalStaff = null;
$modalTasks = [];
$editStaffId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $position   = trim($_POST['position'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'First name, last name, and email are required.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid email format.'];
        } else {
            try {
                $data = [
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'phone'      => $phone ?: null,
                    'position'   => $position ?: null
                ];

                if ($action === 'add') {
                    $staffObj->addStaff($data);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Staff member added successfully!'];
                } elseif ($action === 'edit') {
                    $staff_id = (int)$_POST['staff_id'];
                    $staffObj->updateStaff($staff_id, $data);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Staff member updated successfully!'];
                }
            } catch (Exception $e) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
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

    header("Location: admin_staff_manage.php");
    exit;
}

if (isset($_GET['view_staff'])) {
    $viewId = (int)$_GET['view_staff'];
    $modalStaff = $staffObj->getStaffById($viewId);
    $statusFilter = $_GET['status'] ?? null;
    $modalTasks = $staffObj->getTasksByStaffId($viewId, $statusFilter);
}

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

        .add-staff-btn { background: #050505ff; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 1em; margin-bottom: 20px; }
        .add-staff-btn:hover { background: #030303ff; }

        .edit-btn { background: #f39c12; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px; }
        .delete-btn { background: #e74c3c; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1em; }

        .staff-avatar, .modal-staff-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background-color: #e0e0e0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .staff-avatar:hover, .modal-staff-avatar:hover {
            transform: scale(1.08);
        }

        .modal-staff-avatar {
            width: 110px;
            height: 110px;
            cursor: pointer;
        }

        .current-profile-preview {
            margin-top: 10px;
            max-width: 140px;
            border-radius: 12px;
            border: 1px solid #ccc;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .current-profile-preview:hover {
            transform: scale(1.05);
        }

        .profile-preview-container {
            margin-top: 8px;
            text-align: center;
        }

        .swal2-popup.preview-popup {
    width: 95vw !important;           /* Force modal to almost full viewport width */
    max-width: 1100px !important;     /* Cap at very large size for big screens */
    padding: 1.5rem !important;
}

.swal2-popup .swal2-image {
    max-width: 100% !important;       /* Allow image to fill the modal width */
    max-height: 85vh !important;      /* Prevent overflow vertically */
    width: auto !important;
    height: auto !important;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.3);
}

@media (max-width: 768px) {
    .swal2-popup.preview-popup {
        width: 98vw !important;
        padding: 0.8rem !important;
    }
    .swal2-popup .swal2-image {
        max-height: 70vh !important;
    }
}
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
                        $avatarUrl = !empty($staff['profile_picture']) ? htmlspecialchars($staff['profile_picture']) : '../assets/default-avatar.png';
                    ?>
                        <div class="staff-card">
                            <div class="staff-header">
                                <div class="staff-avatar" 
                                     style="background-image: url('<?= $avatarUrl ?>');"
                                     onclick="previewImage('<?= $avatarUrl ?>', '<?= htmlspecialchars($fullName) ?>')">
                                </div>
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
                <?php 
                $modalAvatar = !empty($modalStaff['profile_picture']) ? htmlspecialchars($modalStaff['profile_picture']) : '../assets/default-avatar.png';
                ?>
                <div class="modal-staff-avatar" 
                     style="background-image: url('<?= $modalAvatar ?>');"
                     onclick="previewImage('<?= $modalAvatar ?>', '<?= htmlspecialchars($modalStaff['first_name'] . ' ' . $modalStaff['last_name']) ?>')">
                </div>
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

            <div class="profile-preview-container" style="text-align:center; margin-bottom:20px;">
                <img id="editModalAvatar" class="modal-staff-avatar" 
                     src="../assets/default-avatar.png" 
                     alt="Profile Picture" 
                     onclick="previewImage(this.src, document.getElementById('modalTitle').textContent)">
            </div>

            <form method="POST" id="staffForm" enctype="multipart/form-data">
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
                <div class="form-group">
                    <label>Profile Picture</label>
                    <input type="file" name="profile_picture" id="formProfilePicture" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small>(Optional - JPG, PNG, GIF, WebP - max 5MB)</small>

                    <div class="profile-preview-container" id="profilePreviewContainer" style="display: none;">
                        <img id="profilePreview" class="current-profile-preview" src="" alt="Preview">
                        <small id="previewText">New selected picture (preview)</small>
                    </div>
                </div>

                <button type="submit" id="saveStaffBtn" style="background:#27ae60; color:white; padding:12px 24px; border:none; border-radius:8px; cursor:pointer;">
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

        function closeAddEditModal() {
            document.getElementById('addEditModal').classList.remove('active');
            document.getElementById('profilePreviewContainer').style.display = 'none';
        }

        function openAddEditModal(mode, staffId = null) {
            const modal = document.getElementById('addEditModal');
            const title = document.getElementById('modalTitle');
            const avatarImg = document.getElementById('editModalAvatar');

            if (mode === 'add') {
                title.textContent = 'Add New Staff';
                document.getElementById('formAction').value = 'add';
                document.getElementById('staffForm').reset();
                document.getElementById('formStaffId').value = '';
                document.getElementById('profilePreviewContainer').style.display = 'none';
                avatarImg.src = '../assets/default-avatar.png';
            } else if (mode === 'edit' && staffId) {
                title.textContent = 'Edit Staff';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('formStaffId').value = staffId;
                window.location.href = `admin_staff_manage.php?edit_staff=${staffId}`;
                return;
            }
            modal.classList.add('active');
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

        function previewImage(src, name = 'Profile Picture') {
            Swal.fire({
                title: name,
                imageUrl: src,
                imageAlt: name,
                imageWidth: 700,
                imageHeight: 600,
                imageClass: 'swal2-image',
                showConfirmButton: true,
                confirmButtonText: 'Close',
                confirmButtonColor: '#3498db',
                background: '#fff',
                padding: '1.5rem',
                customClass: {
                    popup: 'preview-popup'
                }
            });
        }

        window.onclick = function(event) {
            const taskModal = document.getElementById('staffModal');
            const editModal = document.getElementById('addEditModal');
            if (event.target === taskModal) closeStaffModal();
            if (event.target === editModal) closeAddEditModal();
        }

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

        <?php if ($editStaffId && $modalStaff): 
            $currentAvatar = !empty($modalStaff['profile_picture']) ? $modalStaff['profile_picture'] : '../assets/default-avatar.png';
        ?>
            document.getElementById('modalTitle').textContent = 'Edit Staff';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formStaffId').value = <?= $editStaffId ?>;
            document.getElementById('formFirstName').value = <?= json_encode($modalStaff['first_name']) ?>;
            document.getElementById('formLastName').value = <?= json_encode($modalStaff['last_name']) ?>;
            document.getElementById('formEmail').value = <?= json_encode($modalStaff['email']) ?>;
            document.getElementById('formPhone').value = <?= json_encode($modalStaff['phone'] ?? '') ?>;
            document.getElementById('formPosition').value = <?= json_encode($modalStaff['position'] ?? '') ?>;

            const editAvatar = document.getElementById('editModalAvatar');
            editAvatar.src = <?= json_encode($currentAvatar) ?>;

            document.getElementById('addEditModal').classList.add('active');
        <?php endif; ?>

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

        document.getElementById('formProfilePicture')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const previewImg = document.getElementById('profilePreview');
                    previewImg.src = ev.target.result;
                    document.getElementById('profilePreviewContainer').style.display = 'block';
                    document.getElementById('previewText').textContent = 'New selected picture (preview)';
                };
                reader.readAsDataURL(file);
            }
        });

        document.getElementById('staffForm')?.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('formProfilePicture');
            if (fileInput && fileInput.files.length > 0) {
                e.preventDefault();
                Swal.fire({
                    title: 'Uploading picture...',
                    html: 'Please wait while we upload the profile picture to the cloud.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                this.submit();
            }
        });
    </script>
</body>
</html>