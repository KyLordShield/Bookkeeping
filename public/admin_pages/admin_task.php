<?php
session_start();
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
        }
        .btn-pending { background: #6c757d; color: white; }
        .btn-inprogress { background: #0d6efd; color: white; }
        .btn-completed { background: #198754; color: white; }

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
            max-width: 720px;
            border-radius: 12px;
            padding: 24px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .step-row {
            display: grid;
            grid-template-columns: 50px 1fr 240px;
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
        No client services found
        <?= !empty($search) ? ' matching “' . htmlspecialchars($search) . '”' : '' ?>.
    </div>
<?php endif; ?>


        <?php foreach ($tasks as $task): 
            $isPending = $task['overall_status'] === 'pending';
            $hasSteps = $task['total_steps'] > 0;
            $progress = $task['total_steps'] > 0 ? round(($task['completed_steps'] / $task['total_steps']) * 100) : 0;
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

                <button 
                    class="staff-assigned-btn <?= $isPending ? 'btn-pending' : 'btn-inprogress' ?>"
                    data-cs-id="<?= $task['client_service_id'] ?>">
                    <?= $isPending ? 'Assign Staff' : 'Manage Staff' ?>
                </button>
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

            <div class="modal-actions" style="margin-top:32px; text-align:right;">
                <button type="button" id="cancelModalBtn">Cancel</button>
                <button type="submit" class="save-btn" style="padding:10px 24px;">Save Assignments</button>
            </div>
        </form>
    </div>
</div>

<script>
console.log("admin_task script loaded");

const staffList = <?= json_encode(
    array_map(function($s) {
        return [
            'id' => (int)$s['staff_id'],
            'name' => trim($s['first_name'] . ' ' . $s['last_name'])
        ];
    }, $allStaff),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?> || [];

console.log("Staff members loaded:", staffList.length);

function openAssignModal(csId) {
    console.log("Opening modal for client_service_id:", csId);
    document.getElementById('modalClientServiceId').value = csId;
    document.getElementById('assignModal').classList.add('active');
    loadClientServiceData(csId);
}

function closeAssignModal() {
    console.log("Closing modal");
    document.getElementById('assignModal').classList.remove('active');
}

async function loadClientServiceData(csId) {
    console.log("Attempting to load data for csId:", csId);
    
    try {
        const response = await fetch(`../api/get_client_service_details.php?client_service_id=${csId}`);
        console.log("Fetch status:", response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'Failed to load data',
                toast: true,
                position: 'top-end',
                timer: 4000
            });
            return;
        }

        document.getElementById('modalClientServiceInfo').innerHTML = 
            `Client: <strong>${data.client_name || 'Unknown'}</strong> | Service: <strong>${data.service_name || 'Unknown'}</strong>`;
        
        document.getElementById('modalDeadline').value = data.deadline || '';
        
        const container = document.getElementById('stepsContainer');
        container.innerHTML = '';
        
        if (data.steps && Array.isArray(data.steps) && data.steps.length > 0) {
            data.steps.forEach(step => {
                addStepRow(step.requirement_order, step.requirement_name || '', step.assigned_staff_id);
            });
        } else {
            addNewStepRow();
        }
    } catch (error) {
        console.error("Load error:", error);
        Swal.fire({
            icon: 'error',
            title: 'Connection Error',
            text: 'Failed to load service details',
            toast: true,
            position: 'top-end',
            timer: 5000
        });
    }
}

function addNewStepRow() {
    const order = document.querySelectorAll('.step-row').length + 1;
    addStepRow(order, '', null);
}

function addStepRow(order, name = '', staffId = null) {
    const container = document.getElementById('stepsContainer');
    if (!container) {
        console.error("Steps container not found!");
        return;
    }

    const row = document.createElement('div');
    row.className = 'step-row';

    let options = '<option value="">-- select staff --</option>';
    staffList.forEach(s => {
        const selected = (s.id == staffId) ? ' selected' : '';
        options += `<option value="${s.id}"${selected}>${s.name}</option>`;
    });

    row.innerHTML = `
        <div class="step-circle">${order}</div>
        <input type="text" name="steps[${order}][name]" class="step-name-input"
               placeholder="Enter step name (e.g. Document review)"
               value="${(name || '').replace(/"/g, '&quot;').replace(/\\/g, '\\\\')}" required>
        <select name="steps[${order}][staff_id]" class="staff-select" required>
            ${options}
        </select>
        <input type="hidden" name="steps[${order}][order]" value="${order}">
    `;

    container.appendChild(row);
    console.log(`Added step row #${order}`);
}

document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM ready - attaching events");

    document.querySelectorAll('.staff-assigned-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const csId = btn.getAttribute('data-cs-id');
            if (csId) openAssignModal(csId);
        });
    });

    document.querySelector('.modal-close')?.addEventListener('click', closeAssignModal);

    document.getElementById('assignModal')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) closeAssignModal();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.getElementById('assignModal')?.classList.contains('active')) {
            closeAssignModal();
        }
    });

    document.getElementById('addStepBtn')?.addEventListener('click', addNewStepRow);

    document.getElementById('cancelModalBtn')?.addEventListener('click', closeAssignModal);

    document.getElementById('assignStepsForm').addEventListener('submit', async function(e) {
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
                    text: 'Assignments have been updated successfully.',
                    toast: true,
                    position: 'top-end',
                    timer: 4000,
                    timerProgressBar: true
                });

                closeAssignModal();

                setTimeout(() => location.reload(), 1200);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: result.error || 'Something went wrong while saving.',
                    toast: true,
                    timer: 5000
                });
            }
        } catch (err) {
            console.error('Save error:', err);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to connect to server. Please try again.',
                toast: true,
                timer: 5000
            });
        }
    });
});

console.log("Script initialization completed");
</script>
</body>
</html>