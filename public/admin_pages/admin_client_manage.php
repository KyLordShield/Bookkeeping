<?php
// admin_client_manage.php - FULLY WORKING VERSION

session_start();

// FIXED PATHS for your structure: public/admin_pages/ -> ../../config & ../../classes
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Client.php';
require_once __DIR__ . '/../../classes/Service.php';
require_once __DIR__ . '/../../classes/ServiceRequest.php';
require_once __DIR__ . '/../../classes/User.php';

// TEMPORARILY DISABLE AUTH for testing (remove later)
// if (!User::isLoggedIn() || User::getRole() !== 'admin') {
//     header('Location: ../../login_page.php');
//     exit;
// }

$current_staff_id = 1; // Demo staff ID - replace with $_SESSION['staff_id'] later

// Handle AJAX/POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'add_client') {
        $data = [
            'first_name'     => trim($_POST['first_name'] ?? ''),
            'last_name'      => trim($_POST['last_name'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? ''),
            'company_name'   => trim($_POST['company_name'] ?? ''),
            'business_type'  => trim($_POST['business_type'] ?? ''),
            'account_status' => $_POST['account_status'] ?? 'pending',
            'registration_date' => date('Y-m-d')
        ];

        $services = array_map('intval', $_POST['services'] ?? []);

        try {
            $client_id = Client::create($data);
            if ($client_id) {
                foreach ($services as $service_id) {
                    if ($service_id > 0) {
                        Client::assignService($client_id, $service_id, $current_staff_id);
                    }
                }
                echo json_encode(['success' => true, 'client_id' => $client_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create client']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } 
    elseif ($action === 'edit_client') {
        $client_id = (int)($_POST['client_id'] ?? 0);
        if ($client_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
            exit;
        }

        $data = [
            'first_name'     => trim($_POST['first_name'] ?? ''),
            'last_name'      => trim($_POST['last_name'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? ''),
            'company_name'   => trim($_POST['company_name'] ?? ''),
            'business_type'  => trim($_POST['business_type'] ?? ''),
            'account_status' => $_POST['account_status'] ?? 'pending'
        ];

        try {
            $success = Client::update($client_id, $data);
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } 
    elseif ($action === 'create_user') {
        $client_id = (int)($_POST['client_id'] ?? 0);
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';

        $error_msg = '';
        try {
            $success = User::createClientUser($client_id, $username, $password, true, $error_msg);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Account created successfully!' : $error_msg
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } 
    elseif ($action === 'accept_request') {
        $request_id = (int)($_POST['request_id'] ?? 0);
        try {
            $success = ServiceRequest::accept($request_id, $current_staff_id);
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } 
    elseif ($action === 'reject_request') {
        $request_id = (int)($_POST['request_id'] ?? 0);
        try {
            $success = ServiceRequest::reject($request_id);
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } 
    elseif ($action === 'get_client') {
        $client_id = (int)($_POST['client_id'] ?? 0);
        $client = Client::findById($client_id);
        echo json_encode($client ?: []);
    }
    exit;
}

// Load data for display (with error handling)
try {
    $clients = Client::getAll();
    $services = Service::getAllActive();
    $pending_requests = ServiceRequest::getAllPending();
} catch (Exception $e) {
    $clients = [];
    $services = [];
    $pending_requests = [];
    error_log("Data load error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Client Management</title>
    <link rel="stylesheet" href="../assets/css_file/admin_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <style>
        /* Button alignment fix */
        .cm-header-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .cm-header-buttons button {
            white-space: nowrap;
        }
        
        /* Modal improvements */
        .cm-modal.active {
            display: flex !important;
        }
        .cm-modal {
            display: none;
        }
        
        /* Status badges */
        .cm-status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .cm-status-active { background: #d4edda; color: #155724; }
        .cm-status-pending { background: #fff3cd; color: #856404; }
        .cm-status-completed { background: #d1ecf1; color: #0c5460; }
        
        /* Table responsiveness */
        @media (max-width: 768px) {
            .cm-client-table { font-size: 14px; }
            .cm-header-buttons { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Sidebar Navigation - SAME AS WORKING PAGE -->
        <?php include '../partials/temporaryNavAdmin.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <div class="cm-client-management">
                <div class="cm-client-header">
                    <div>
                        <h1>Client Management</h1>
                        <p>View, add and manage all clients & service requests</p>
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
                    <button class="cm-filter-btn" data-filter="active">Active</button>
                    <button class="cm-filter-btn" data-filter="completed">Completed</button>
                    <input type="text" id="clientSearch" placeholder="🔍 Search by name or email..." style="margin-left:20px; padding:8px 12px; border:1px solid #ddd; border-radius:4px;">
                </div>

                <div style="padding: 0 30px; margin-top: 20px;">
                    <?php if (empty($clients)): ?>
                        <div style="text-align:center; padding:50px; color:#666;">
                            <h3>No clients yet</h3>
                            <p>Click "ADD CLIENT" to get started</p>
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
                                    $servicesNames = array_column($clientServices, 'service_name');
                                    $servicesStr = $servicesNames ? implode(', ', $servicesNames) : 'None';
                                    $pendingCount = count(array_filter($clientServices, fn($s) => $s['overall_status'] === 'pending'));
                                    $actionNeeded = $pendingCount > 0 ? "Pending ($pendingCount)" : 'None';
                                ?>
                                    <tr data-status="<?= strtolower($client['account_status']) ?>">
                                        <td>
                                            <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?><br>
                                            <small><?= $client['registration_date'] ?: '—' ?><br><?= htmlspecialchars($client['email']) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($client['email']) ?><br>
                                            <?= htmlspecialchars($client['phone'] ?: '—') ?>
                                        </td>
                                        <td><?= htmlspecialchars($servicesStr) ?></td>
                                        <td>
                                            <span class="cm-status-badge cm-status-<?= strtolower($client['account_status']) ?>">
                                                <?= ucfirst($client['account_status']) ?>
                                            </span>
                                        </td>
                                        <td><?= $actionNeeded ?></td>
                                        <td>
                                            <button class="cm-btn-edit" onclick="openEditClientModal(<?= $client['client_id'] ?>)">Edit</button>
                                            <button class="cm-btn-post-password" onclick="alert('Reset password feature coming soon!')">Reset Password</button>
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

    <!-- ========== SERVICE REQUEST MODAL ========== -->
    <div id="serviceRequestModal" class="cm-modal">
        <div class="cm-modal-content">
            <div class="cm-modal-header">
                <h2>Pending Service Requests</h2>
                <button class="cm-close-modal" onclick="closeServiceRequestModal()">×</button>
            </div>
            <div class="cm-modal-body" style="max-height: 60vh; overflow-y: auto;">
                <div class="cm-service-list">
                    <div class="cm-service-list-item cm-service-list-header">
                        <div>Client</div><div>Email</div><div>Phone</div><div>Service</div>
                        <div>Date & Time</div><div>Notes</div><div>Status</div><div>Actions</div>
                    </div>
                    <?php if (empty($pending_requests)): ?>
                        <div style="text-align:center; padding:40px; color:#666;">
                            No pending service requests
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_requests as $req): 
                            $client = Client::findById($req['client_id']);
                            $service = Service::findById($req['service_id']);
                        ?>
                            <div class="cm-service-list-item">
                                <div><?= htmlspecialchars($client['first_name'] ?? '') . ' ' . htmlspecialchars($client['last_name'] ?? '') ?></div>
                                <div><?= htmlspecialchars($client['email'] ?? '—') ?></div>
                                <div><?= htmlspecialchars($client['phone'] ?? '—') ?></div>
                                <div><?= htmlspecialchars($service['service_name'] ?? '—') ?></div>
                                <div><?= $req['preferred_date'] ?><br><?= $req['preferred_time'] ?: '—' ?></div>
                                <div><?= htmlspecialchars($req['additional_notes'] ?: 'None') ?></div>
                                <div><?= ucfirst($req['request_status']) ?></div>
                                <div>
                                    <button class="cm-btn-accept" onclick="acceptRequest(<?= $req['request_id'] ?>)" title="Accept">✓ Accept</button>
                                    <button class="cm-btn-reject" onclick="rejectRequest(<?= $req['request_id'] ?>)" title="Reject">✗ Reject</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== ADD/EDIT CLIENT MODAL ========== -->
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
                        <label>Services *</label>
                        <div class="cm-checkbox-group" style="max-height: 150px; overflow-y: auto;">
                            <?php foreach ($services as $s): ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="services[]" value="<?= $s['service_id'] ?>">
                                    <?= htmlspecialchars($s['service_name']) ?> 
                                    <small style="color:#666;">(<?= htmlspecialchars($s['service_type'] ?? 'General') ?>)</small>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="cm-form-group">
                        <label>Account Status</label>
                        <select id="account_status" name="account_status">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="cm-modal-footer">
                <button class="cm-btn-cancel" onclick="closeClientModal()">Cancel</button>
                <button class="cm-btn-save" onclick="saveClient()">Save Client</button>
            </div>
        </div>
    </div>

    <!-- ========== CREATE USER ACCOUNT MODAL ========== -->
    <div id="userModal" class="cm-modal">
        <div class="cm-modal-content">
            <div class="cm-modal-header">
                <h2>Create Client Login</h2>
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
                    <small style="color:#666; display:block; margin-top:5px;">Password will be stored as plain text (development only)</small>
                </form>
            </div>
            <div class="cm-modal-footer">
                <button class="cm-btn-cancel" onclick="closeUserModal()">Cancel</button>
                <button class="cm-btn-save" onclick="saveUser()">Create Account</button>
            </div>
        </div>
    </div>

    <script>
        // ========== ALL MODAL FUNCTIONS (COMPLETE) ==========
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

        function openEditClientModal(clientId) {
            document.getElementById('modalTitle').textContent = 'Edit Client';
            
            // Fetch client data
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_client&client_id=${clientId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.client_id) {
                    document.getElementById('client_id').value = data.client_id;
                    document.getElementById('first_name').value = data.first_name || '';
                    document.getElementById('last_name').value = data.last_name || '';
                    document.getElementById('email').value = data.email || '';
                    document.getElementById('phone').value = data.phone || '';
                    document.getElementById('company_name').value = data.company_name || '';
                    document.getElementById('business_type').value = data.business_type || '';
                    document.getElementById('account_status').value = data.account_status || 'pending';
                }
            });
            
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

        // ========== SAVE FUNCTIONS ==========
        async function saveClient() {
            const form = document.getElementById('clientForm');
            const formData = new FormData(form);
            const clientId = document.getElementById('client_id').value;
            const isEdit = parseInt(clientId) > 0;
            
            formData.append('action', isEdit ? 'edit_client' : 'add_client');

            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    alert(isEdit ? 'Client updated successfully!' : 'Client created! Now create their account.');
                    if (!isEdit) {
                        closeClientModal();
                        openUserModal(data.client_id);
                    } else {
                        closeClientModal();
                        location.reload();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to save client'));
                }
            } catch (e) {
                alert('Network error: ' + e.message);
            }
        }

        async function saveUser() {
            const pw = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const clientId = document.getElementById('user_client_id').value;

            if (!clientId) {
                alert('No client selected');
                return;
            }

            if (pw !== confirm) {
                alert("Passwords don't match!");
                return;
            }

            if (pw.length < 6) {
                alert("Password must be at least 6 characters!");
                return;
            }

            const formData = new FormData(document.getElementById('userForm'));
            formData.append('action', 'create_user');

            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    alert('✅ Client account created successfully!');
                    closeUserModal();
                    location.reload();
                } else {
                    alert('❌ ' + (data.message || 'Failed to create account'));
                }
            } catch (e) {
                alert('Network error: ' + e.message);
            }
        }

        async function acceptRequest(requestId) {
            if (!confirm('Accept this service request?')) return;
            
            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=accept_request&request_id=${requestId}`
                });
                const data = await res.json();

                if (data.success) {
                    alert('✅ Service request accepted!');
                    location.reload();
                } else {
                    alert('❌ ' + (data.message || 'Failed to accept'));
                }
            } catch (e) {
                alert('Network error');
            }
        }

        async function rejectRequest(requestId) {
            if (!confirm('Reject this service request?')) return;
            
            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=reject_request&request_id=${requestId}`
                });
                const data = await res.json();

                if (data.success) {
                    alert('✅ Service request rejected!');
                    location.reload();
                } else {
                    alert('❌ ' + (data.message || 'Failed to reject'));
                }
            } catch (e) {
                alert('Network error');
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
                    row.style.display = term === '' || text.includes(term) ? '' : 'none';
                });
            });
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('cm-modal')) {
                event.target.classList.remove('active');
            }
        }

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