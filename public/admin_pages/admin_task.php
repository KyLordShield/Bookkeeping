<?php
session_start();
//Auth check:
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login_page.php");
    exit();
}

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Client.php';
require_once __DIR__ . '/../../classes/Service.php';
require_once __DIR__ . '/../../classes/Staff.php';

$staffObj = new Staff();
$allStaff = $staffObj->getAllStaffWithStats();

$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($statusFilter === 'new') {
    $where[] = "cs.overall_status = 'pending'";
} elseif ($statusFilter === 'in_progress') {
    $where[] = "cs.overall_status = 'in_progress'";
} elseif ($statusFilter === 'completed') {
    $where[] = "cs.overall_status = 'completed'";
}

if ($search !== '') {
    $where[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR c.email LIKE ? OR s.service_name LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$query = "
    SELECT 
        cs.client_service_id,
        cs.overall_status,
        cs.start_date,
        cs.deadline,
        c.first_name,
        c.last_name,
        c.email,
        c.phone,
        s.service_name,
        (SELECT COUNT(*) FROM client_service_requirements WHERE client_service_id = cs.client_service_id) AS total_steps,
        (SELECT COUNT(*) FROM client_service_requirements WHERE client_service_id = cs.client_service_id AND status = 'completed') AS completed_steps
    FROM client_services cs
    JOIN clients c ON cs.client_id = c.client_id
    JOIN services s ON cs.service_id = s.service_id
    $whereClause
    ORDER BY 
        CASE cs.overall_status 
            WHEN 'pending' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'on_hold' THEN 4
        END,
        cs.start_date DESC
";

$stmt = Database::getInstance()->getConnection()->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management - Admin</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="../assets/css_file/admin_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">

    <style>
        .task-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            padding: 20px;
        }
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .task-info { flex: 1; }
        .task-client { font-weight: bold; font-size: 1.2em; margin-bottom: 4px; }
        .task-type, .task-status, .task-dates, .task-contact { color: #555; margin: 3px 0; }
        .staff-assigned-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9em;
            cursor: pointer;
            border: none;
            color: white;
        }
        .btn-pending    { background: #6c757d; }
        .btn-inprogress { background: #0d6efd; }
        .btn-completed  { background: #198754; }

        .progress-container {
            margin-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9em;
            color: #666;
        }
        .progress-bar {
            flex: 1;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin: 0 12px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #9b0a0aff;
            transition: width 0.4s ease;
        }

        .modal { 
            display: none; 
            position: fixed; inset: 0; 
            background: rgba(0,0,0,0.5); 
            align-items: center; 
            justify-content: center; 
            z-index: 1000;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 760px;
            border-radius: 12px;
            padding: 24px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .step-row {
            display: grid;
            grid-template-columns: 50px 1fr 220px 100px;
            gap: 16px;
            align-items: center;
            margin-bottom: 16px;
        }
        .step-circle {
            width: 36px; height: 36px;
            background: #050505ff; color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold;
        }
        .step-name-input, .staff-select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
        }
        .remove-step-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
        }
        .filter-btn.active {
            background: #050505ff !important;
            color: white !important;
        }
        .add-step-btn {
            padding: 8px 16px;
            background: #adababff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .save-btn {
            padding: 10px 24px;
            background: #871919ff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .modal-header { margin-bottom: 20px; }
        .modal-title { font-size: 1.4em; font-weight: bold; }
        .modal-subtitle { color: #555; margin-top: 6px; }
        .modal-actions { margin-top: 32px; text-align: right; }
        .modal-close {
            position: absolute;
            top: 12px;
            right: 16px;
            font-size: 28px;
            cursor: pointer;
            color: #aaa;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../partials/temporaryNavAdmin.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="page-title">Task Management</div>
            <div class="page-subtitle">Review client services and assign staff to each step</div>
        </div>

        <div style="margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <span class="filter-label" style="font-weight:500;">Filter by Status:</span>
                <div class="filter-buttons" style="display:inline-flex; margin-left:12px; gap:8px;">
                    <a href="?status=all"       class="filter-btn <?= $statusFilter==='all'?'active':'' ?>">All</a>
                    <a href="?status=new"       class="filter-btn <?= $statusFilter==='new'?'active':'' ?>">New</a>
                    <a href="?status=in_progress" class="filter-btn <?= $statusFilter==='in_progress'?'active':'' ?>">In Progress</a>
                    <a href="?status=completed" class="filter-btn <?= $statusFilter==='completed'?'active':'' ?>">Completed</a>
                </div>
            </div>

            <form method="get" style="display:flex; gap:8px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <input type="search" name="search" placeholder="Search client / email / service..." 
                       value="<?= htmlspecialchars($search) ?>" 
                       style="padding:9px 14px; width:300px; border-radius:6px; border:1px solid #ccc;">
                <button type="submit" style="padding:9px 18px;">Search</button>
            </form>
        </div>

        <?php if (empty($tasks)): ?>
            <div style="text-align:center; padding:80px 0; color:#777; font-style:italic;">
                No client services found<?= !empty($search) ? ' matching “' . htmlspecialchars($search) . '”' : '' ?>.
            </div>
        <?php endif; ?>

        <?php foreach ($tasks as $task): 
            $isCompleted = $task['overall_status'] === 'completed';
            $isPending   = $task['overall_status'] === 'pending';
            $hasSteps    = $task['total_steps'] > 0;
            $progress    = $hasSteps ? round(($task['completed_steps'] / $task['total_steps']) * 100) : 0;
        ?>
        <div class="task-card">
            <div class="task-header">
                <div class="task-info">
                    <div class="task-client">
                        <?= htmlspecialchars($task['first_name'] . ' ' . $task['last_name']) ?>
                    </div>
                    <div class="task-type">
                        Service: <?= htmlspecialchars($task['service_name']) ?>
                    </div>
                    <div class="task-status">
                        Status: <strong><?= ucfirst(str_replace('_', ' ', $task['overall_status'])) ?></strong>
                    </div>
                    <div class="task-dates">
                        Started: <?= $task['start_date'] ? date('M d, Y', strtotime($task['start_date'])) : '—' ?> 
                        | Deadline: <?= $task['deadline'] ? date('M d, Y', strtotime($task['deadline'])) : 'Not set' ?>
                    </div>
                    <div class="task-contact">
                        <?= htmlspecialchars($task['email']) ?> 
                        <?= $task['phone'] ? ' • ' . htmlspecialchars($task['phone']) : '' ?>
                    </div>
                </div>

                <?php if ($isCompleted): ?>
                    <button 
                        class="staff-assigned-btn btn-completed"
                        data-cs-id="<?= $task['client_service_id'] ?>"
                        data-action="view">
                        View Details
                    </button>
                <?php else: ?>
                    <button 
                        class="staff-assigned-btn <?= $isPending ? 'btn-pending' : 'btn-inprogress' ?>"
                        data-cs-id="<?= $task['client_service_id'] ?>"
                        data-action="edit">
                        <?= $isPending ? 'Assign Staff' : 'Manage Steps' ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($hasSteps): ?>
            <div class="progress-container">
                <div>Progress:</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                </div>
                <div style="font-weight:500;">
                    <?= $task['completed_steps'] ?>/<?= $task['total_steps'] ?>
                </div>
            </div>
            <?php else: ?>
            <div style="color:#888; font-style:italic; margin-top:12px;">
                No steps/requirements assigned yet
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Edit / Assign Modal -->
<div id="assignModal" class="modal">
    <div class="modal-content">
        <button class="modal-close">×</button>
        
        <div class="modal-header">
            <div class="modal-title">Assign Staff & Define Steps</div>
            <div class="modal-subtitle" id="modalClientServiceInfo"></div>
        </div>

        <form id="assignStepsForm" method="post" action="../api/save_client_service_steps.php">
            <input type="hidden" name="client_service_id" id="modalClientServiceId">

            <div class="step-list" id="stepsContainer"></div>

            <div style="margin: 24px 0;">
                <button type="button" class="add-step-btn" id="addStepBtn">+ Add Another Step</button>
            </div>

            <div style="margin:20px 0;">
                <label style="font-weight:500; display:block; margin-bottom:6px;">Deadline</label>
                <input type="date" name="deadline" id="modalDeadline" style="padding:8px; width:200px;">
            </div>

            <div class="modal-actions">
                <button type="button" id="cancelModalBtn">Cancel</button>
                <button type="submit" class="save-btn">Save Assignments</button>
            </div>
        </form>
    </div>
</div>

<!-- View Only Modal (for completed services) -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <button class="modal-close">×</button>
        
        <div class="modal-header">
            <div class="modal-title">Service Requirements (View Only)</div>
            <div class="modal-subtitle" id="viewModalClientServiceInfo"></div>
        </div>

        <div class="step-list" id="viewStepsContainer" style="margin: 20px 0;"></div>

        <div style="margin:20px 0;">
            <label style="font-weight:500; display:block; margin-bottom:6px;">Deadline</label>
            <div id="viewDeadline" style="padding:8px; font-weight:500; color:#333;"></div>
        </div>

        <div class="modal-actions">
            <button type="button" id="closeViewModalBtn">Close</button>
        </div>
    </div>
</div>

<script>
const staffList = <?= json_encode(
    array_map(function($s) {
        return [
            'id'   => (int)$s['staff_id'],
            'name' => trim($s['first_name'] . ' ' . $s['last_name'])
        ];
    }, $allStaff),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?> || [];

function openAssignModal(csId) {
    document.getElementById('modalClientServiceId').value = csId;
    document.getElementById('assignModal').classList.add('active');
    loadClientServiceData(csId, 'edit');
}

function openViewModal(csId) {
    document.getElementById('viewModal').classList.add('active');
    loadClientServiceData(csId, 'view');
}

async function loadClientServiceData(csId, mode = 'edit') {
    try {
        const response = await fetch(`../api/get_client_service_details.php?client_service_id=${csId}`);
        if (!response.ok) throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
        
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Failed to load data');

        const clientInfo = `Client: <strong>${data.client_name || 'Unknown'}</strong> | Service: <strong>${data.service_name || 'Unknown'}</strong>`;
        
        if (mode === 'edit') {
            document.getElementById('modalClientServiceInfo').innerHTML = clientInfo;
            document.getElementById('modalDeadline').value = data.deadline || '';

            const container = document.getElementById('stepsContainer');
            container.innerHTML = '';

            if (data.steps && data.steps.length > 0) {
                data.steps.forEach(step => {
                    addStepRow(
                        step.requirement_order,
                        step.requirement_name || '',
                        step.assigned_staff_id || null,
                        step.requirement_id || null,
                        step.status || 'pending'
                    );
                });
            } else {
                addNewStepRow();
            }
        } else { // view mode
            document.getElementById('viewModalClientServiceInfo').innerHTML = clientInfo;
            document.getElementById('viewDeadline').textContent = data.deadline || 'Not set';

            const container = document.getElementById('viewStepsContainer');
            container.innerHTML = '';

            if (data.steps && data.steps.length > 0) {
                data.steps.forEach(step => {
                    const statusColor = 
                        step.status === 'completed'    ? '#198754' :
                        step.status === 'in_progress'  ? '#0d6efd' :
                        step.status === 'on_hold'      ? '#ffc107' : '#6c757d';

                    const row = document.createElement('div');
                    row.className = 'step-row';
                    row.style.gridTemplateColumns = '50px 1fr 220px 140px';
                    row.innerHTML = `
                        <div class="step-circle">${step.requirement_order}</div>
                        <div style="font-weight:500;">${step.requirement_name}</div>
                        <div>${step.assigned_staff_name || '—'}</div>
                        <div style="text-align:center; font-weight:600; color:${statusColor};">
                            ${step.status.replace('_', ' ').toUpperCase()}
                        </div>
                    `;
                    container.appendChild(row);
                });
            } else {
                container.innerHTML = '<p style="text-align:center; color:#777; padding:20px;">No requirements defined for this service.</p>';
            }
        }
    } catch (error) {
        console.error('Load error details:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load service details. Please try again.',
            toast: true,
            position: 'top-end',
            timer: 4000
        });
    }
}

function addNewStepRow() {
    const order = document.querySelectorAll('#stepsContainer .step-row').length + 1;
    addStepRow(order, '', null, null, 'pending');
}

function addStepRow(order, name = '', staffId = null, reqId = null, status = 'pending') {
    const container = document.getElementById('stepsContainer');
    const row = document.createElement('div');
    row.className = 'step-row';
    row.dataset.reqId = reqId || '';

    let options = '<option value="">-- select staff --</option>';
    staffList.forEach(s => {
        const selected = (s.id == staffId) ? ' selected' : '';
        options += `<option value="${s.id}"${selected}>${s.name}</option>`;
    });

    const canRemove = (status === 'pending');
    const removeBtn = canRemove 
        ? `<button type="button" class="remove-step-btn">Remove</button>` 
        : '';

    row.innerHTML = `
        <div class="step-circle">${order}</div>
        <input type="text" name="steps[${order}][name]" class="step-name-input"
               placeholder="Enter step name (e.g. Document review)"
               value="${(name || '').replace(/"/g, '&quot;').replace(/\\/g, '\\\\')}" required>
        <select name="steps[${order}][staff_id]" class="staff-select" required>
            ${options}
        </select>
        ${removeBtn}
        <input type="hidden" name="steps[${order}][order]" value="${order}">
        <input type="hidden" name="steps[${order}][requirement_id]" value="${reqId || ''}">
    `;

    container.appendChild(row);

    if (canRemove) {
        row.querySelector('.remove-step-btn').addEventListener('click', () => {
            Swal.fire({
                title: 'Remove this step?',
                text: "This cannot be undone and is only allowed for pending steps.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, remove'
            }).then((result) => {
                if (result.isConfirmed) {
                    row.remove();
                    renumberSteps();
                }
            });
        });
    }
}

function renumberSteps() {
    document.querySelectorAll('#stepsContainer .step-row').forEach((row, index) => {
        const num = index + 1;
        row.querySelector('.step-circle').textContent = num;
        const prefix = `steps[${num}]`;
        row.querySelector('input[name*="][name]"]').name          = `${prefix}[name]`;
        row.querySelector('select').name                          = `${prefix}[staff_id]`;
        row.querySelector('input[name*="][order]"]').name         = `${prefix}[order]`;
        row.querySelector('input[name*="][order]"]').value        = num;
        const reqInput = row.querySelector('input[name*="][requirement_id]"]');
        if (reqInput) reqInput.name = `${prefix}[requirement_id]`;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.staff-assigned-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const csId = btn.dataset.csId;
            const action = btn.dataset.action;
            if (action === 'view') {
                openViewModal(csId);
            } else {
                openAssignModal(csId);
            }
        });
    });

    document.querySelectorAll('.modal-close').forEach(el => {
        el.addEventListener('click', () => {
            el.closest('.modal').classList.remove('active');
        });
    });

    document.getElementById('assignModal')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) e.target.classList.remove('active');
    });

    document.getElementById('viewModal')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) e.target.classList.remove('active');
    });

    document.getElementById('addStepBtn')?.addEventListener('click', addNewStepRow);

    document.getElementById('cancelModalBtn')?.addEventListener('click', () => {
        document.getElementById('assignModal').classList.remove('active');
    });

    document.getElementById('closeViewModalBtn')?.addEventListener('click', () => {
        document.getElementById('viewModal').classList.remove('active');
    });

    document.getElementById('assignStepsForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        try {
            const response = await fetch(this.action, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: 'Assignments updated successfully.',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    timerProgressBar: true
                });
                document.getElementById('assignModal').classList.remove('active');
                setTimeout(() => location.reload(), 1200);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: result.error || 'Could not save changes.',
                    toast: true,
                    timer: 5000
                });
            }
        } catch (err) {
            console.error('Save error:', err);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to connect to server. Check console for details.',
                toast: true,
                timer: 5000
            });
        }
    });
});
</script>
</body>
</html>