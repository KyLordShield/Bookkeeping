<?php
// admin_client_manage.php - Each service per client shown as separate row

session_start();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Client.php';
require_once __DIR__ . '/../../classes/Service.php';
require_once __DIR__ . '/../../classes/ServiceRequest.php';
require_once __DIR__ . '/../../classes/User.php';

$current_staff_id = $_SESSION['staff_id'] ?? 1;

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

                $service_id = (int)($_POST['service_id'] ?? 0);
                if ($service_id <= 0) {
                    throw new Exception("Please select a service.");
                }

                if (Client::emailExists($data['email'])) {
                    throw new Exception("This email is already registered.");
                }
                if (!empty($data['phone']) && Client::phoneExists($data['phone'])) {
                    throw new Exception("This phone number is already registered.");
                }

                $client_id = Client::create($data);
                if (!$client_id) throw new Exception("Failed to create client");

                Client::assignService($client_id, $service_id, $current_staff_id, null, 'pending');

                echo json_encode(['success' => true, 'client_id' => $client_id]);
                break;

            case 'add_service_to_existing':
                $client_id = (int)($_POST['client_id'] ?? 0);
                $service_id = (int)($_POST['service_id'] ?? 0);

                if ($client_id <= 0 || $service_id <= 0) {
                    throw new Exception("Invalid client or service ID");
                }

                Client::assignService($client_id, $service_id, $current_staff_id, null, 'pending');
                echo json_encode(['success' => true]);
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

                // Update service if client_service_id and new_service_id are provided
                if (isset($_POST['client_service_id']) && isset($_POST['new_service_id'])) {
                    $client_service_id = (int)$_POST['client_service_id'];
                    $new_service_id = (int)$_POST['new_service_id'];
                    
                    if ($client_service_id > 0 && $new_service_id > 0) {
                        $db = Database::getInstance()->getConnection();
                        // Update only if the service is still pending
                        $stmt = $db->prepare("
                            UPDATE client_services 
                            SET service_id = ? 
                            WHERE client_service_id = ? 
                            AND overall_status = 'pending'
                        ");
                        $stmt->execute([$new_service_id, $client_service_id]);
                    }
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
                    $clientServices = Client::getClientServices($client_id);
                    
                    // Separate pending and non-pending services
                    $client['pending_service'] = null; // Single pending service
                    $client['active_services'] = [];
                    
                    foreach ($clientServices as $cs) {
                        if ($cs['overall_status'] === 'pending') {
                            // Only store the first pending service (there should only be one)
                            if (!$client['pending_service']) {
                                $client['pending_service'] = [
                                    'client_service_id' => $cs['client_service_id'],
                                    'service_id' => $cs['service_id'],
                                    'service_name' => $cs['service_name']
                                ];
                            }
                        } else {
                            $client['active_services'][] = [
                                'service_id' => $cs['service_id'],
                                'service_name' => $cs['service_name'],
                                'status' => $cs['overall_status']
                            ];
                        }
                    }
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

// Load data - Build client-service rows
$clients = Client::getAll() ?? [];
$services = Service::getAllActive() ?? [];
$pending_requests = ServiceRequest::getAllPending() ?? [];
$pending_count = count($pending_requests);

// Build rows: each client-service combination is a separate row
$clientServiceRows = [];
foreach ($clients as $client) {
    $clientServices = Client::getClientServices($client['client_id']);
    
    if (empty($clientServices)) {
        // Client with no services - show one row with "None"
        $clientServiceRows[] = [
            'client' => $client,
            'service' => null,
            'client_service_id' => null,
            'status' => 'pending',
            'has_requirements' => false
        ];
    } else {
        // One row per service
        foreach ($clientServices as $cs) {
            $reqCount = Client::countRequirements($cs['client_service_id']);
            $clientServiceRows[] = [
                'client' => $client,
                'service' => $cs,
                'client_service_id' => $cs['client_service_id'],
                'status' => $cs['overall_status'],
                'has_requirements' => $reqCount > 0
            ];
        }
    }
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

        .cm-service-request-btn { position: relative; }
        .cm-notif-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .cm-choice-container { display: flex; gap: 20px; padding: 20px; }
        .cm-choice-card {
            flex: 1;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .cm-choice-card:hover {
            border-color: #007bff;
            background: #f8f9ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .cm-choice-card i { font-size: 48px; margin-bottom: 15px; display: block; color: #007bff; }
        .cm-choice-card h3 { margin: 10px 0 5px; font-size: 18px; }
        .cm-choice-card p { font-size: 13px; color: #666; margin: 0; }

        .cm-client-selection-list { max-height: 400px; overflow-y: auto; }
        .cm-client-selection-item {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        .cm-client-selection-item:hover { background: #f8f9fa; border-color: #007bff; }
        .cm-client-info h4 { margin: 0 0 5px; font-size: 16px; }
        .cm-client-info p { margin: 2px 0; font-size: 13px; color: #666; }
        .cm-btn-select {
            padding: 8px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        .cm-btn-select:hover { background: #0056b3; }
        .cm-search-box {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .cm-service-radio-group {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            max-height: 300px;
            overflow-y: auto;
        }
        .cm-service-radio-group label {
            display: block;
            padding: 8px;
            margin: 5px 0;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .cm-service-radio-group label:hover { background: #e9ecef; }
        .cm-service-radio-group input[type="radio"] { margin-right: 8px; }
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
                        <button class="cm-service-request-btn" onclick="openServiceRequestModal()">
                            SERVICE REQUESTS
                            <?php if ($pending_count > 0): ?>
                                <span class="cm-notif-badge"><?= $pending_count ?></span>
                            <?php endif; ?>
                        </button>
                        <button class="cm-add-client-btn" onclick="openChoiceModal()">+ ADD CLIENT</button>
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
                    <?php if (empty($clientServiceRows)): ?>
                        <div style="text-align:center;padding:50px;color:#666;">
                            <h3>No clients yet</h3>
                            <p>Accept a request or add manually to begin</p>
                        </div>
                    <?php else: ?>
                        <table class="cm-client-table">
                            <thead>
                                <tr>
                                    <th>Client Name</th>
                                    <th>Contact</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Has Requirements</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientServiceRows as $row): 
                                    $client = $row['client'];
                                    $service = $row['service'];
                                    $status = $row['status'];
                                    $hasReq = $row['has_requirements'] ? 'Yes' : 'No';
                                    $serviceName = $service ? htmlspecialchars($service['service_name']) : 'None';
                                ?>
                                    <tr data-status="<?= $status ?>">
                                        <td>
                                            <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?><br>
                                            <small><?= $client['registration_date'] ?: '—' ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($client['email']) ?><br>
                                            <?= htmlspecialchars($client['phone'] ?: '—') ?>
                                        </td>
                                        <td><?= $serviceName ?></td>
                                        <td>
                                            <span class="cm-status-badge cm-status-<?= $status ?>">
                                                <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                            </span>
                                        </td>
                                        <td><?= $hasReq ?></td>
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

    <!-- Choice Modal (New/Existing) -->
    <div id="choiceModal" class="cm-modal">
        <div class="cm-modal-content" style="max-width: 600px;">
            <div class="cm-modal-header">
                <h2>Add Client</h2>
                <button class="cm-close-modal" onclick="closeChoiceModal()">×</button>
            </div>
            <div class="cm-modal-body">
                <div class="cm-choice-container">
                    <div class="cm-choice-card" onclick="openExistingClientModal()">
                        <i>👤</i>
                        <h3>Existing Client</h3>
                        <p>Add service to existing client</p>
                    </div>
                    <div class="cm-choice-card" onclick="openAddClientModal()">
                        <i>➕</i>
                        <h3>New Client</h3>
                        <p>Create new client with account</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Existing Client Selection Modal -->
    <div id="existingClientModal" class="cm-modal">
        <div class="cm-modal-content">
            <div class="cm-modal-header">
                <h2>Select Existing Client</h2>
                <button class="cm-close-modal" onclick="closeExistingClientModal()">×</button>
            </div>
            <div class="cm-modal-body">
                <input type="text" id="existingClientSearch" class="cm-search-box" placeholder="🔍 Search by name, email, or phone...">
                
                <div class="cm-client-selection-list" id="clientSelectionList">
                    <?php foreach ($clients as $client): ?>
                        <div class="cm-client-selection-item" data-client-search="<?= strtolower($client['first_name'] . ' ' . $client['last_name'] . ' ' . $client['email'] . ' ' . $client['phone']) ?>">
                            <div class="cm-client-info">
                                <h4><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></h4>
                                <p>📧 <?= htmlspecialchars($client['email']) ?></p>
                                <p>📱 <?= htmlspecialchars($client['phone'] ?: 'No phone') ?></p>
                            </div>
                            <button class="cm-btn-select" onclick="selectExistingClient(<?= $client['client_id'] ?>, '<?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?>')">
                                Select
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Selection Modal (for existing client) -->
    <div id="serviceSelectionModal" class="cm-modal">
        <div class="cm-modal-content" style="max-width: 500px;">
            <div class="cm-modal-header">
                <h2>Select Service</h2>
                <button class="cm-close-modal" onclick="closeServiceSelectionModal()">×</button>
            </div>
            <div class="cm-modal-body">
                <p style="margin-bottom: 15px;">
                    Client: <strong id="selectedClientName"></strong>
                </p>
                <input type="hidden" id="selectedClientId">
                
                <div class="cm-service-radio-group">
                    <?php foreach ($services as $s): ?>
                        <label>
                            <input type="radio" name="selected_service" value="<?= $s['service_id'] ?>">
                            <?= htmlspecialchars($s['service_name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="cm-modal-footer">
                <button class="cm-btn-cancel" onclick="closeServiceSelectionModal()">Cancel</button>
                <button class="cm-btn-save" onclick="saveServiceToExisting()">Add Service</button>
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

                    <div class="cm-form-group" id="serviceSelectionDiv">
                        <label>Service * (select one)</label>
                        <div class="cm-service-radio-group">
                            <?php foreach ($services as $s): ?>
                                <label>
                                    <input type="radio" name="service_id" value="<?= $s['service_id'] ?>">
                                    <?= htmlspecialchars($s['service_name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="cm-form-group" id="editPendingServicesDiv" style="display: none;">
                        <label>Change Pending Service</label>
                        <input type="hidden" id="client_service_id" name="client_service_id">
                        <div class="cm-service-radio-group" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($services as $s): ?>
                                <label>
                                    <input type="radio" name="new_service_id" value="<?= $s['service_id'] ?>">
                                    <?= htmlspecialchars($s['service_name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small style="color:#666;display:block;margin-top:8px;">
                            ℹ️ You can only change a service while it's still pending.
                        </small>
                    </div>

                    <div class="cm-form-group" id="activeServicesDiv" style="display: none;">
                        <label>Active Services (cannot be modified)</label>
                        <div id="activeServicesList" style="padding: 10px; background: #f8f9fa; border-radius: 4px; min-height: 40px;">
                            <!-- Will be populated dynamically -->
                        </div>
                        <small style="color:#666;display:block;margin-top:8px;">
                            🔒 These services are in progress or completed and cannot be changed.
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
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });

        // ========== MODAL CONTROLS ==========
        function openChoiceModal() {
            document.getElementById('choiceModal').classList.add('active');
        }

        function closeChoiceModal() {
            document.getElementById('choiceModal').classList.remove('active');
        }

        function openExistingClientModal() {
            closeChoiceModal();
            document.getElementById('existingClientModal').classList.add('active');
        }

        function closeExistingClientModal() {
            document.getElementById('existingClientModal').classList.remove('active');
        }

        function selectExistingClient(clientId, clientName) {
            document.getElementById('selectedClientId').value = clientId;
            document.getElementById('selectedClientName').textContent = clientName;
            
            document.querySelectorAll('input[name="selected_service"]').forEach(radio => {
                radio.checked = false;
            });
            
            closeExistingClientModal();
            document.getElementById('serviceSelectionModal').classList.add('active');
        }

        function closeServiceSelectionModal() {
            document.getElementById('serviceSelectionModal').classList.remove('active');
        }

        async function saveServiceToExisting() {
            const clientId = document.getElementById('selectedClientId').value;
            const serviceRadio = document.querySelector('input[name="selected_service"]:checked');
            
            if (!serviceRadio) {
                Toast.fire({ icon: 'warning', title: 'Please select a service' });
                return;
            }

            const serviceId = serviceRadio.value;

            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=add_service_to_existing&client_id=${clientId}&service_id=${serviceId}`
                });
                const data = await res.json();

                if (data.success) {
                    Toast.fire({ icon: 'success', title: 'Service added successfully!' });
                    closeServiceSelectionModal();
                    setTimeout(() => {
                            location.reload();
                        }, 3600); // wait for toast to finish
                } else {
                    Swal.fire('Error', data.message || 'Failed to add service', 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Network error occurred', 'error');
            }
        }

        function openServiceRequestModal() {
            document.getElementById('serviceRequestModal').classList.add('active');
        }

        function closeServiceRequestModal() {
            document.getElementById('serviceRequestModal').classList.remove('active');
        }

        function openAddClientModal() {
            closeChoiceModal();
            document.getElementById('modalTitle').textContent = 'Add New Client';
            document.getElementById('clientForm').reset();
            document.getElementById('client_id').value = '0';
            document.querySelectorAll('input[name="service_id"]').forEach(rb => rb.checked = false);
            
            // Show only new service selection for add
            document.getElementById('serviceSelectionDiv').style.display = 'block';
            document.getElementById('editPendingServicesDiv').style.display = 'none';
            document.getElementById('activeServicesDiv').style.display = 'none';
            
            document.getElementById('clientModal').classList.add('active');
        }

        async function openEditClientModal(clientId) {
            document.getElementById('modalTitle').textContent = 'Edit Client';
            
            // Hide new service selection, show edit sections
            document.getElementById('serviceSelectionDiv').style.display = 'none';

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

                    // Handle pending service (editable - single service only)
                    const pendingServicesDiv = document.getElementById('editPendingServicesDiv');
                    const activeServicesDiv = document.getElementById('activeServicesDiv');
                    const activeServicesList = document.getElementById('activeServicesList');

                    // Uncheck all radio buttons first
                    document.querySelectorAll('input[name="new_service_id"]').forEach(rb => {
                        rb.checked = false;
                    });

                    // If there's a pending service, show it with radio buttons
                    if (data.pending_service) {
                        pendingServicesDiv.style.display = 'block';
                        document.getElementById('client_service_id').value = data.pending_service.client_service_id;
                        
                        // Pre-select the current pending service
                        const radio = document.querySelector(`input[name="new_service_id"][value="${data.pending_service.service_id}"]`);
                        if (radio) radio.checked = true;
                    } else {
                        pendingServicesDiv.style.display = 'none';
                    }

                    // Show active services (read-only)
                    if (data.active_services && data.active_services.length > 0) {
                        activeServicesDiv.style.display = 'block';
                        activeServicesList.innerHTML = data.active_services.map(s => 
                            `<div style="padding: 8px; margin: 4px 0; background: white; border-radius: 4px; border-left: 3px solid #007bff;">
                                <strong>${s.service_name}</strong> 
                                <span style="color: #666; font-size: 12px;">(${s.status.replace('_', ' ')})</span>
                            </div>`
                        ).join('');
                    } else {
                        activeServicesDiv.style.display = 'none';
                        activeServicesList.innerHTML = '';
                    }
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
            const isEdit = document.getElementById('client_id').value > 0;

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

            // Validation for Add: must select a service
            if (!isEdit) {
                const serviceRadio = document.querySelector('input[name="service_id"]:checked');
                if (!serviceRadio) {
                    Toast.fire({ icon: 'warning', title: 'Please select a service' });
                    valid = false;
                }
            }

            if (!valid) return;

            const formData = new FormData(form);
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
                        setTimeout(() => location.reload(), 3600);
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
                    setTimeout(() => location.reload(), 3600);
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
                    setTimeout(() => location.reload(), 3600);
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
                    setTimeout(() => location.reload(), 3600);
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

            // Main client search bar
            document.getElementById('clientSearch').addEventListener('input', function() {
                const term = this.value.toLowerCase().trim();
                document.querySelectorAll('.cm-client-table tbody tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });

            // Existing client search in modal
            document.getElementById('existingClientSearch').addEventListener('input', function() {
                const term = this.value.toLowerCase().trim();
                document.querySelectorAll('.cm-client-selection-item').forEach(item => {
                    const searchText = item.getAttribute('data-client-search');
                    item.style.display = searchText.includes(term) ? '' : 'none';
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