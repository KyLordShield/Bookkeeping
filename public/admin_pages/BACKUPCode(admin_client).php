<?php
// admin_client_manage.php - FIXED: All modals/buttons/filters/search now work

session_start();

// Paths (from public/admin_pages/ → ../../ → root)
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Client.php';
require_once __DIR__ . '/../../classes/Service.php';
require_once __DIR__ . '/../../classes/ServiceRequest.php';
require_once __DIR__ . '/../../classes/User.php';

// TEMP: disable auth during development (uncomment when ready)
// if (!User::isLoggedIn() || User::getRole() !== 'admin') {
//     header('Location: ../../login_page.php');
//     exit;
// }

$current_staff_id = $_SESSION['staff_id'] ?? 1; // fallback for testing

// Handle all POST/AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_client':
                $data = [
                    'first_name'     => trim($_POST['first_name'] ?? ''),
                    'last_name'      => trim($_POST['last_name'] ?? ''),
                    'email'          => trim($_POST['email'] ?? ''),
                    'phone'          => trim($_POST['phone'] ?? ''),
                    'company_name'   => trim($_POST['company_name'] ?? ''),
                    'business_type'  => trim($_POST['business_type'] ?? ''),
                    'registration_date' => date('Y-m-d')
                ];

                if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
                    throw new Exception("First Name, Last Name and Email are required.");
                }

                $services = array_filter(array_map('intval', $_POST['services'] ?? []));
                if (empty($services)) {
                    throw new Exception("Please select at least one service.");
                }

                if (Client::emailExists($data['email'])) {
                    throw new Exception("This email is already registered.");
                }
                if (!empty($data['phone']) && Client::phoneExists($data['phone'])) {
                    throw new Exception("This phone number is already registered.");
                }

                $client_id = Client::create($data);
                if (!$client_id) throw new Exception("Failed to create client");

                foreach ($services as $sid) {
                    Client::assignService($client_id, $sid, $current_staff_id, null, 'pending');
                }

                echo json_encode(['success' => true, 'client_id' => $client_id]);
                break;

            case 'edit_client':
                $client_id = (int)($_POST['client_id'] ?? 0);
                if ($client_id <= 0) throw new Exception("Invalid client ID");

                $data = [
                    'first_name'     => trim($_POST['first_name'] ?? ''),
                    'last_name'      => trim($_POST['last_name'] ?? ''),
                    'email'          => trim($_POST['email'] ?? ''),
                    'phone'          => trim($_POST['phone'] ?? ''),
                    'company_name'   => trim($_POST['company_name'] ?? ''),
                    'business_type'  => trim($_POST['business_type'] ?? ''),
                ];

                if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
                    throw new Exception("First Name, Last Name and Email are required.");
                }

                if (Client::emailExists($data['email'], $client_id)) {
                    throw new Exception("Email already in use.");
                }
                if (!empty($data['phone']) && Client::phoneExists($data['phone'], $client_id)) {
                    throw new Exception("Phone already in use.");
                }

                Client::update($client_id, $data);

                // Update services
                Client::deleteAllServices($client_id);
                $services = array_filter(array_map('intval', $_POST['services'] ?? []));
                foreach ($services as $sid) {
                    Client::assignService($client_id, $sid, $current_staff_id);
                }

                // Manual override only for on_hold
                if (isset($_POST['account_status']) && $_POST['account_status'] === 'on_hold') {
                    Client::setAllServicesOnHold($client_id);
                }

                echo json_encode(['success' => true]);
                break;

            case 'create_user':
                $client_id = (int)($_POST['client_id'] ?? 0);
                $username  = trim($_POST['username'] ?? '');
                $password  = $_POST['password'] ?? '';

                if ($client_id <= 0 || empty($username) || strlen($password) < 6) {
                    throw new Exception("Invalid input: username or password too short");
                }

                $error_msg = '';
                $success = User::createClientUser($client_id, $username, $password, true, $error_msg);

                echo json_encode([
                    'success' => $success,
                    'message' => $success ? 'Account created successfully!' : $error_msg
                ]);
                break;

            case 'accept_request':
                $request_id = (int)($_POST['request_id'] ?? 0);
                if ($request_id <= 0) throw new Exception("Invalid request ID");

                if (!ServiceRequest::accept($request_id, $current_staff_id)) {
                    throw new Exception("Failed to accept request");
                }

                $req = ServiceRequest::getById($request_id);
                if ($req) {
                    Client::assignService(
                        $req['client_id'],
                        $req['service_id'],
                        $current_staff_id,
                        $request_id,
                        'pending'
                    );
                }

                echo json_encode(['success' => true]);
                break;

            case 'reject_request':
                $request_id = (int)($_POST['request_id'] ?? 0);
                $success = ServiceRequest::reject($request_id);
                echo json_encode(['success' => $success]);
                break;

            case 'get_client':
                $client_id = (int)($_POST['client_id'] ?? 0);
                $client = Client::findById($client_id);
                if ($client) {
                    $client['current_services'] = array_column(
                        Client::getClientServices($client_id),
                        'service_id'
                    );
                }
                echo json_encode($client ?: []);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Load data
$clients          = Client::getAll() ?? [];
$services         = Service::getAllActive() ?? [];
$pending_requests = ServiceRequest::getAllPending() ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Client Management</title>
    <link rel="stylesheet" href="../assets/css_file/admin_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        input[required], select[required] { border-left: 3px solid #e74c3c; }
        input:invalid:focus { box-shadow: 0 0 5px rgba(231,76,60,0.5); }
        .cm-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .cm-modal.active { display: flex; }
        .cm-modal-content { background: white; width: 90%; max-width: 700px; border-radius: 8px; overflow: hidden; }
        .cm-modal-header { padding: 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
        .cm-close-modal { font-size: 28px; cursor: pointer; }
        .cm-modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
        .cm-modal-footer { padding: 15px; background: #f8f9fa; border-top: 1px solid #ddd; text-align: right; }
        .cm-btn-cancel, .cm-btn-save { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px; }
        .cm-btn-cancel { background: #6c757d; color: white; }
        .cm-btn-save { background: #28a745; color: white; }
        .cm-status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .cm-status-pending     { background:#fff3cd; color:#856404; }
        .cm-status-in_progress { background:#cce5ff; color:#004085; }
        .cm-status-completed   { background:#d4edda; color:#155724; }
        .cm-status-on_hold     { background:#fdfd96; color:#333; }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/temporaryNavAdmin.php'; ?>

        <div class="main-content">
            <div class="cm-client-management">
                <div class="cm-client-header">
                    <div>
                        <h1>Client Management</h1>
                        <p>View and manage clients & service requests</p>
                    </div>
                    <div class="cm-header-buttons">
                        <button class="cm-service-request-btn" onclick="openServiceRequestModal()">SERVICE REQUESTS</button>
                        <button class="cm-add-client-btn" onclick="openAddClientModal()">+ ADD CLIENT</button>
                    </div>
                </div>

                <div class="cm-filter-section">
                    <label>Filter:</label>
                    <button class="cm-filter-btn active" data-filter="all">All</button>
                    <button class="cm-filter-btn" data-filter="pending">Pending</button>
                    <button class="cm-filter-btn" data-filter="in_progress">In Progress</button>
                    <button class="cm-filter-btn" data-filter="completed">Completed</button>
                    <button class="cm-filter-btn" data-filter="on_hold">On Hold</button>
                    <input type="text" id="clientSearch" placeholder="🔍 Search by name or email..." style="margin-left:20px;padding:8px 12px;border:1px solid #ddd;border-radius:4px;">
                </div>

                <div style="padding: 0 30px; margin-top: 20px;">
                    <?php if (empty($clients)): ?>
                        <div style="text-align:center;padding:50px;color:#666;">
                            <h3>No clients yet</h3>
                            <p>Accept a request or add manually to begin</p>
                        </div>
                    <?php else: ?>
                        <table class="cm-client-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Service(s)</th>
                                    <th>Status</th>
                                    <th>Action Needed</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client):
                                    $clientServices = Client::getClientServices($client['client_id']);
                                    $servicesNames  = array_column($clientServices, 'service_name');
                                    $servicesStr    = $servicesNames ? implode(', ', $servicesNames) : 'None';

                                    // Dynamic status calculation
                                    $effectiveStatus = 'pending';
                                    $hasRequirements = false;
                                    $allCompleted = true;
                                    $anyInProgress = false;

                                    foreach ($clientServices as $cs) {
                                        $reqCount = Client::countRequirements($cs['client_service_id']);
                                        if ($reqCount > 0) $hasRequirements = true;

                                        if ($cs['overall_status'] === 'in_progress') $anyInProgress = true;
                                        if ($cs['overall_status'] !== 'completed') $allCompleted = false;
                                    }

                                    if ($hasRequirements) {
                                        if ($allCompleted) $effectiveStatus = 'completed';
                                        elseif ($anyInProgress) $effectiveStatus = 'in_progress';
                                    }

                                    $pendingCount = count(array_filter($clientServices, fn($s) => $s['overall_status'] === 'pending'));
                                    $actionNeeded = $pendingCount > 0 ? "Pending steps ($pendingCount)" : 'None';
                                ?>
                                    <tr data-status="<?= $effectiveStatus ?>">
                                        <td>
                                            <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?><br>
                                            <small><?= $client['registration_date'] ?: '—' ?><br><?= htmlspecialchars($client['email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($client['email']) ?><br><?= htmlspecialchars($client['phone'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($servicesStr) ?></td>
                                        <td>
                                            <span class="cm-status-badge cm-status-<?= $effectiveStatus ?>">
                                                <?= ucfirst($effectiveStatus) ?>
                                            </span>
                                        </td>
                                        <td><?= $actionNeeded ?></td>
                                        <td>
                                            <button class="cm-btn-edit" onclick="openEditClientModal(<?= $client['client_id'] ?>)">Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Requests Modal -->
    <div id="serviceRequestModal" class="cm-modal">
        <div class="cm-modal-content">
            <div class="cm-modal-header">
                <h2>Pending Service Requests</h2>
                <button class="cm-close-modal" onclick="closeServiceRequestModal()">×</button>
            </div>
            <div class="cm-modal-body" style="max-height:60vh;overflow-y:auto;">
                <div class="cm-service-list">
                    <div class="cm-service-list-item cm-service-list-header">
                        <div>Client</div><div>Email</div><div>Phone</div><div>Service</div>
                        <div>Date & Time</div><div>Notes</div><div>Status</div><div>Actions</div>
                    </div>
                    <?php if (empty($pending_requests)): ?>
                        <div style="text-align:center;padding:40px;color:#666;">No pending requests</div>
                    <?php else: ?>
                        <?php foreach ($pending_requests as $req): 
                            $client = Client::findById($req['client_id']);
                            $service = Service::findById($req['service_id']);
                        ?>
                            <div class="cm-service-list-item">
                                <div><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name'] ?? '') ?></div>
                                <div><?= htmlspecialchars($client['email'] ?? '—') ?></div>
                                <div><?= htmlspecialchars($client['phone'] ?? '—') ?></div>
                                <div><?= htmlspecialchars($service['service_name'] ?? '—') ?></div>
                                <div><?= $req['preferred_date'] ?><br><?= $req['preferred_time'] ?: '—' ?></div>
                                <div><?= htmlspecialchars($req['additional_notes'] ?: 'None') ?></div>
                                <div><?= ucfirst($req['request_status']) ?></div>
                                <div>
                                    <button class="cm-btn-accept" onclick="acceptRequest(<?= $req['request_id'] ?>)">✓ Accept</button>
                                    <button class="cm-btn-reject" onclick="rejectRequest(<?= $req['request_id'] ?>)">✗ Reject</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Client Modal -->
    <div id="clientModal" class="cm-modal">
        <div class="cm-modal-content">
            <div class="cm-modal-header">
                <h2 id="modalTitle">Add New Client</h2>
                <button class="cm-close-modal" onclick="closeClientModal()">×</button>
            </div>
            <div class="cm-modal-body">
                <form id="clientForm">
                    <input type="hidden" id="client_id" name="client_id" value="0">

                    <div class="cm-form-row">
                        <div class="cm-form-group">
                            <label>First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="cm-form-group">
                            <label>Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>

                    <div class="cm-form-row">
                        <div class="cm-form-group">
                            <label>Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="cm-form-group">
                            <label>Phone</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                    </div>

                    <div class="cm-form-row">
                        <div class="cm-form-group">
                            <label>Company</label>
                            <input type="text" id="company_name" name="company_name">
                        </div>
                        <div class="cm-form-group">
                            <label>Business Type</label>
                            <input type="text" id="business_type" name="business_type">
                        </div>
                    </div>

                    <div class="cm-form-group">
                        <label>Services * (at least one required)</label>
                        <div class="cm-checkbox-group" style="max-height:180px;overflow-y:auto;">
                            <?php foreach ($services as $s): ?>
                                <label style="display:block;margin:6px 0;">
                                    <input type="checkbox" name="services[]" value="<?= $s['service_id'] ?>">
                                    <?= htmlspecialchars($s['service_name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="cm-form-group">
                        <label>Manual Override (optional)</label>
                        <select id="account_status" name="account_status">
                            <option value="">Auto-calculate</option>
                            <option value="on_hold">Force On Hold</option>
                        </select>
                        <small style="color:#666;display:block;margin-top:5px;">
                            Status is auto-calculated from requirements.<br>
                            Only "On Hold" can be manually forced.
                        </small>
                    </div>
                </form>
            </div>
            <div class="cm-modal-footer">
                <button class="cm-btn-cancel" onclick="closeClientModal()">Cancel</button>
                <button class="cm-btn-save" onclick="saveClient()">Save</button>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div id="userModal" class="cm-modal">
        <div class="cm-modal-content">
            <div class="cm-modal-header">
                <h2>Create Client Account</h2>
                <button class="cm-close-modal" onclick="closeUserModal()">×</button>
            </div>
            <div class="cm-modal-body">
                <form id="userForm">
                    <input type="hidden" id="user_client_id" name="client_id">
                    <div class="cm-form-group">
                        <label>Username *</label>
                        <input type="text" id="username" name="username" required autocomplete="off">
                    </div>
                    <div class="cm-form-row">
                        <div class="cm-form-group">
                            <label>Password *</label>
                            <input type="password" id="password" name="password" required minlength="6">
                        </div>
                        <div class="cm-form-group">
                            <label>Confirm Password *</label>
                            <input type="password" id="confirm_password" required minlength="6">
                        </div>
                    </div>
                </form>
            </div>
            <div class="cm-modal-footer">
                <button class="cm-btn-cancel" onclick="closeUserModal()">Cancel</button>
                <button class="cm-btn-save" onclick="saveUser()">Create Account</button>
            </div>
        </div>
    </div>

    <script>
        // SweetAlert Toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });

        // ========== MODAL CONTROLS ==========
        function openServiceRequestModal() {
            document.getElementById('serviceRequestModal').classList.add('active');
        }

        function closeServiceRequestModal() {
            document.getElementById('serviceRequestModal').classList.remove('active');
        }

        function openAddClientModal() {
            document.getElementById('modalTitle').textContent = 'Add New Client';
            document.getElementById('clientForm').reset();
            document.getElementById('client_id').value = '0';
            document.querySelectorAll('input[name="services[]"]').forEach(cb => cb.checked = false);
            document.getElementById('clientModal').classList.add('active');
        }

        async function openEditClientModal(clientId) {
            document.getElementById('modalTitle').textContent = 'Edit Client';

            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=get_client&client_id=${clientId}`
                });
                const data = await res.json();

                if (data && data.client_id) {
                    document.getElementById('client_id').value = data.client_id;
                    document.getElementById('first_name').value = data.first_name || '';
                    document.getElementById('last_name').value = data.last_name || '';
                    document.getElementById('email').value = data.email || '';
                    document.getElementById('phone').value = data.phone || '';
                    document.getElementById('company_name').value = data.company_name || '';
                    document.getElementById('business_type').value = data.business_type || '';
                    document.getElementById('account_status').value = '';

                    document.querySelectorAll('input[name="services[]"]').forEach(cb => {
                        cb.checked = data.current_services?.includes(parseInt(cb.value)) || false;
                    });
                }
            } catch (err) {
                Toast.fire({ icon: 'error', title: 'Failed to load client data' });
            }

            document.getElementById('clientModal').classList.add('active');
        }

        function closeClientModal() {
            document.getElementById('clientModal').classList.remove('active');
        }

        function openUserModal(clientId) {
            document.getElementById('user_client_id').value = clientId;
            document.getElementById('userForm').reset();
            document.getElementById('userModal').classList.add('active');
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
        }

        // ========== SAVE CLIENT ==========
        async function saveClient() {
            const form = document.getElementById('clientForm');

            let valid = true;
            ['first_name', 'last_name', 'email'].forEach(id => {
                const el = document.getElementById(id);
                if (!el.value.trim()) {
                    el.style.borderColor = '#e74c3c';
                    valid = false;
                } else {
                    el.style.borderColor = '';
                }
            });

            const services = document.querySelectorAll('input[name="services[]"]:checked');
            if (services.length === 0) {
                Toast.fire({ icon: 'warning', title: 'Please select at least one service' });
                valid = false;
            }

            if (!valid) return;

            const formData = new FormData(form);
            const isEdit = document.getElementById('client_id').value > 0;
            formData.append('action', isEdit ? 'edit_client' : 'add_client');

            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    Toast.fire({
                        icon: 'success',
                        title: isEdit ? 'Client updated!' : 'Client created!'
                    });

                    if (!isEdit) {
                        closeClientModal();
                        openUserModal(data.client_id);
                    } else {
                        closeClientModal();
                        location.reload();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to save client'
                    });
                }
            } catch (err) {
                Swal.fire('Error', 'Network error occurred', 'error');
            }
        }

        // ========== CREATE USER ==========
        async function saveUser() {
            const pw = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;

            if (pw !== confirm) {
                Swal.fire('Error', "Passwords don't match!", 'error');
                return;
            }

            if (pw.length < 6) {
                Swal.fire('Error', 'Password must be at least 6 characters!', 'error');
                return;
            }

            const formData = new FormData(document.getElementById('userForm'));
            formData.append('action', 'create_user');

            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    Toast.fire({ icon: 'success', title: 'Account created successfully!' });
                    closeUserModal();
                    location.reload();
                } else {
                    Swal.fire('Error', data.message || 'Failed to create account', 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Network error occurred', 'error');
            }
        }

        // ========== ACCEPT / REJECT REQUESTS ==========
        async function acceptRequest(id) {
            const result = await Swal.fire({
                title: 'Accept Request?',
                text: "This will create a client service record",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, accept',
                cancelButtonText: 'No'
            });

            if (!result.isConfirmed) return;

            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=accept_request&request_id=${id}`
                });
                const data = await res.json();

                if (data.success) {
                    Toast.fire({ icon: 'success', title: 'Request accepted!' });
                    location.reload();
                } else {
                    Swal.fire('Error', data.message || 'Failed to accept request', 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Network error occurred', 'error');
            }
        }

        async function rejectRequest(id) {
            const result = await Swal.fire({
                title: 'Reject Request?',
                text: "This action cannot be undone",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, reject',
                cancelButtonText: 'No'
            });

            if (!result.isConfirmed) return;

            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=reject_request&request_id=${id}`
                });
                const data = await res.json();

                if (data.success) {
                    Toast.fire({ icon: 'success', title: 'Request rejected!' });
                    location.reload();
                } else {
                    Swal.fire('Error', data.message || 'Failed to reject request', 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Network error occurred', 'error');
            }
        }

        // ========== FILTER & SEARCH ==========
        document.addEventListener('DOMContentLoaded', function() {
            // Filter buttons
            document.querySelectorAll('.cm-filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.cm-filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const filter = this.dataset.filter;
                    document.querySelectorAll('.cm-client-table tbody tr').forEach(row => {
                        row.style.display = (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
                    });
                });
            });

            // Search bar
            document.getElementById('clientSearch').addEventListener('input', function() {
                const term = this.value.toLowerCase().trim();
                document.querySelectorAll('.cm-client-table tbody tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('cm-modal')) {
                event.target.classList.remove('active');
            }
        };

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.cm-modal.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>