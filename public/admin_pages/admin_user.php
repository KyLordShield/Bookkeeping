<?php
// admin_user_management.php
session_start();
require_once __DIR__ . '/../../config/Database.php';

//Auth check:
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login_page.php");
    exit();
}
$db = Database::getInstance()->getConnection();
$alertMessage = '';
$alertType = '';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE Staff User
    if ($action === 'create_staff') {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $position = trim($_POST['position']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        try {
            $db->beginTransaction();

            // Create staff record
            $stmt = $db->prepare("INSERT INTO staff (first_name, last_name, email, phone, position) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $email, $phone, $position]);
            $staff_id = $db->lastInsertId();

            // Create user account
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, staff_id, is_admin, status) VALUES (?, ?, ?, FALSE, 'active')");
            $stmt->execute([$username, $password, $staff_id]);

            $db->commit();
            $alertMessage = "Staff account created successfully!";
            $alertType = "success";
        } catch (Exception $e) {
            $db->rollBack();
            $alertMessage = "Error: " . $e->getMessage();
            $alertType = "error";
        }
    }

    // UPDATE Staff
    if ($action === 'update_staff') {
        $staff_id = $_POST['staff_id'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $position = trim($_POST['position']);

        try {
            $stmt = $db->prepare("UPDATE staff SET first_name=?, last_name=?, email=?, phone=?, position=? WHERE staff_id=?");
            $stmt->execute([$first_name, $last_name, $email, $phone, $position, $staff_id]);
            $alertMessage = "Staff information updated successfully!";
            $alertType = "success";
        } catch (Exception $e) {
            $alertMessage = "Error: " . $e->getMessage();
            $alertType = "error";
        }
    }

    // UPDATE Client
    if ($action === 'update_client') {
        $client_id = $_POST['client_id'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $company_name = trim($_POST['company_name']);
        $business_type = trim($_POST['business_type']);
        $account_status = $_POST['account_status'];

        try {
            $stmt = $db->prepare("UPDATE clients SET first_name=?, last_name=?, email=?, phone=?, company_name=?, business_type=?, account_status=? WHERE client_id=?");
            $stmt->execute([$first_name, $last_name, $email, $phone, $company_name, $business_type, $account_status, $client_id]);
            $alertMessage = "Client information updated successfully!";
            $alertType = "success";
        } catch (Exception $e) {
            $alertMessage = "Error: " . $e->getMessage();
            $alertType = "error";
        }
    }

    // RESET PASSWORD
    if ($action === 'reset_password') {
        $user_id = $_POST['user_id'];
        $new_password = $_POST['new_password'];

        try {
            $stmt = $db->prepare("UPDATE users SET password_hash=? WHERE user_id=?");
            $stmt->execute([$new_password, $user_id]);
            $alertMessage = "Password reset successfully!";
            $alertType = "success";
        } catch (Exception $e) {
            $alertMessage = "Error: " . $e->getMessage();
            $alertType = "error";
        }
    }

    // DELETE User
    if ($action === 'delete_user') {
        $user_id = $_POST['user_id'];

        try {
            $stmt = $db->prepare("DELETE FROM users WHERE user_id=?");
            $stmt->execute([$user_id]);
            $alertMessage = "User account deleted successfully!";
            $alertType = "success";
        } catch (Exception $e) {
            $alertMessage = "Error: " . $e->getMessage();
            $alertType = "error";
        }
    }

    // TOGGLE USER STATUS
    if ($action === 'toggle_status') {
        $user_id = $_POST['user_id'];
        $new_status = $_POST['new_status'];

        try {
            $stmt = $db->prepare("UPDATE users SET status=? WHERE user_id=?");
            $stmt->execute([$new_status, $user_id]);
            $alertMessage = "User status updated successfully!";
            $alertType = "success";
        } catch (Exception $e) {
            $alertMessage = "Error: " . $e->getMessage();
            $alertType = "error";
        }
    }
}

// Get search and view type
$viewType = $_GET['view'] ?? 'staff';
$search = $_GET['search'] ?? '';

// Fetch Staff Users
$staffUsers = [];
if ($viewType === 'staff') {
    $sql = "SELECT u.user_id, u.username, u.status, u.last_login, s.staff_id, s.first_name, s.last_name, s.email, s.phone, s.position
            FROM users u
            JOIN staff s ON u.staff_id = s.staff_id
            WHERE (s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR u.username LIKE ?)
            ORDER BY s.last_name, s.first_name";
    $stmt = $db->prepare($sql);
    $searchParam = "%$search%";
    $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
    $staffUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Client Users
$clientUsers = [];
if ($viewType === 'client') {
    $sql = "SELECT u.user_id, u.username, u.status, u.last_login, c.client_id, c.first_name, c.last_name, c.email, c.phone, c.company_name, c.business_type, c.account_status
            FROM users u
            JOIN clients c ON u.client_id = c.client_id
            WHERE (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR u.username LIKE ? OR c.company_name LIKE ?)
            ORDER BY c.last_name, c.first_name";
    $stmt = $db->prepare($sql);
    $searchParam = "%$search%";
    $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    $clientUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #7D1C19, #7D1C19);
            min-height: 100vh;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Main content area */
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 160px;   /* SAME AS SIDEBAR WIDTH */
            overflow-x: hidden;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 25px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .header h1 {
            color: #7D1C19;
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 0.95rem;
        }

        .controls {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
        }

        .tab-btn {
            padding: 10px 25px;
            background: #f5f5f5;
            border: 2px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
            text-decoration: none;
        }

        .tab-btn.active {
            background: #7D1C19;
            color: white;
            border-color: #7D1C19;
        }

        .tab-btn:hover {
            background: #B22222;
            color: white;
            border-color: #B22222;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
        }

        .btn-create {
            padding: 10px 25px;
            background: #000;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-create:hover {
            background: #333;
        }

        /* Table Container - Fixed for sidebar */
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-wrapper {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: #7D1C19;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            font-size: 0.85rem;
            vertical-align: middle;
        }

        tr:hover {
            background: #f9f9f9;
        }

        /* Column widths */
        th:nth-child(1), td:nth-child(1) { width: 150px; } /* Name */
        th:nth-child(2), td:nth-child(2) { width: 180px; } /* Email */
        th:nth-child(3), td:nth-child(3) { width: 120px; } /* Phone */
        th:nth-child(4), td:nth-child(4) { width: 130px; } /* Position/Company */
        th:nth-child(5), td:nth-child(5) { width: 130px; } /* Username/Business */
        th:nth-child(6), td:nth-child(6) { width: 100px; } /* Status */
        th:nth-child(7), td:nth-child(7) { width: 110px; } /* Last Login/Account Status */
        th:nth-child(8), td:nth-child(8) { width: 100px; } /* User Status (client only) */
        th:last-child, td:last-child { 
            width: 300px;
            min-width: 300px;
        } /* Actions */

        /* Truncate long text */
        td {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        td:last-child {
            max-width: none;
            overflow: visible;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #cfe2ff;
            color: #084298;
        }

        .action-btns {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: flex-start;
        }

        .btn-action {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-edit {
            background: #ffc107;
            color: #000;
        }

        .btn-edit:hover {
            background: #e0a800;
        }

        .btn-password {
            background: #17a2b8;
            color: white;
        }

        .btn-password:hover {
            background: #138496;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-toggle {
            background: #6c757d;
            color: white;
        }

        .btn-toggle:hover {
            background: #5a6268;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s;
            overflow-y: auto;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideDown 0.3s;
            margin: 20px;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #8B0000;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-submit {
            flex: 1;
            padding: 12px;
            background: #8B0000;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .btn-submit:hover {
            background: #B22222;
        }

        .btn-cancel {
            flex: 1;
            padding: 12px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        /* Alert Modal */
        .alert-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .alert-modal.show {
            display: flex;
        }

        .alert-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        .alert-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .alert-icon.success {
            color: #28a745;
        }

        .alert-icon.error {
            color: #dc3545;
        }

        .alert-icon.warning {
            color: #ffc107;
        }

        .alert-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
        }

        .alert-buttons button {
            flex: 1;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .btn-confirm {
            background: #8B0000;
            color: white;
        }

        .btn-confirm:hover {
            background: #B22222;
        }

        .btn-cancel-alert {
            background: #6c757d;
            color: white;
        }

        .btn-cancel-alert:hover {
            background: #5a6268;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 1.1rem;
        }

        /* Responsive adjustments */
        @media (max-width: 1400px) {
            .main-content {
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: 100%;
            }

            .tab-buttons {
                flex-direction: column;
            }

            .table-container {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Navigation Sidebar -->
        <?php include '../partials/temporaryNavAdmin.php'; ?>

        <main class="main-content">
            <div class="container">
                <div class="header">
                    <h1>User Management</h1>
                    <p>Manage staff and client user accounts</p>
                </div>

                <div class="controls">
                    <div class="tab-buttons">
                        <a href="?view=staff" class="tab-btn <?= $viewType === 'staff' ? 'active' : '' ?>">Staff Accounts</a>
                        <a href="?view=client" class="tab-btn <?= $viewType === 'client' ? 'active' : '' ?>">Client Accounts</a>
                    </div>

                    <div class="search-box">
                        <form method="GET" action="">
                            <input type="hidden" name="view" value="<?= $viewType ?>">
                            <input type="text" name="search" placeholder="Search by name, email, or username..." value="<?= htmlspecialchars($search) ?>">
                        </form>
                    </div>

                    <?php if ($viewType === 'staff'): ?>
                        <button class="btn-create" onclick="openCreateStaffModal()">+ Create Staff Account</button>
                    <?php endif; ?>
                </div>

                <div class="table-container">
                    <div class="table-wrapper">
                        <?php if ($viewType === 'staff'): ?>
                            <?php if (empty($staffUsers)): ?>
                                <div class="no-results">No staff accounts found.</div>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Position</th>
                                            <th>Username</th>
                                            <th>Status</th>
                                            <th>Last Login</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($staffUsers as $user): ?>
                                            <tr>
                                                <td title="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
                                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                                </td>
                                                <td title="<?= htmlspecialchars($user['email']) ?>">
                                                    <?= htmlspecialchars($user['email']) ?>
                                                </td>
                                                <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                                <td title="<?= htmlspecialchars($user['position'] ?? 'N/A') ?>">
                                                    <?= htmlspecialchars($user['position'] ?? 'N/A') ?>
                                                </td>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= $user['status'] ?>">
                                                        <?= ucfirst($user['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never' ?></td>
                                                <td>
                                                    <div class="action-btns">
                                                        <button class="btn-action btn-edit" onclick='editStaff(<?= json_encode($user) ?>)'>Edit</button>
                                                        <button class="btn-action btn-password" onclick="openPasswordModal(<?= $user['user_id'] ?>)">Reset</button>
                                                        <button class="btn-action btn-toggle" onclick="toggleStatus(<?= $user['user_id'] ?>, '<?= $user['status'] ?>')">
                                                            <?= $user['status'] === 'active' ? 'Disable' : 'Enable' ?>
                                                        </button>
                                        
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                        <?php else: // CLIENT VIEW ?>
                            <?php if (empty($clientUsers)): ?>
                                <div class="no-results">No client accounts found.</div>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Company</th>
                                            <th>Business Type</th>
                                            <th>Username</th>
                                            <th>Account Status</th>
                                            <th>User Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clientUsers as $user): ?>
                                            <tr>
                                                <td title="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
                                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                                </td>
                                                <td title="<?= htmlspecialchars($user['email']) ?>">
                                                    <?= htmlspecialchars($user['email']) ?>
                                                </td>
                                                <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                                <td title="<?= htmlspecialchars($user['company_name'] ?? 'N/A') ?>">
                                                    <?= htmlspecialchars($user['company_name'] ?? 'N/A') ?>
                                                </td>
                                                <td title="<?= htmlspecialchars($user['business_type'] ?? 'N/A') ?>">
                                                    <?= htmlspecialchars($user['business_type'] ?? 'N/A') ?>
                                                </td>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= $user['account_status'] ?>">
                                                        <?= ucfirst($user['account_status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?= $user['status'] ?>">
                                                        <?= ucfirst($user['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-btns">
                                                        <button class="btn-action btn-edit" onclick='editClient(<?= json_encode($user) ?>)'>Edit</button>
                                                        <button class="btn-action btn-password" onclick="openPasswordModal(<?= $user['user_id'] ?>)">Reset</button>
                                                        <button class="btn-action btn-toggle" onclick="toggleStatus(<?= $user['user_id'] ?>, '<?= $user['status'] ?>')">
                                                            <?= $user['status'] === 'active' ? 'Disable' : 'Enable' ?>
                                                        </button>
                                                    
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Create Staff Modal -->
            <div class="modal" id="createStaffModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Create Staff Account</h2>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_staff">
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone">
                        </div>
                        <div class="form-group">
                            <label>Position</label>
                            <input type="text" name="position" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required minlength="6">
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="btn-submit">Create Account</button>
                            <button type="button" class="btn-cancel" onclick="closeModal('createStaffModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Staff Modal -->
            <div class="modal" id="editStaffModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Edit Staff Information</h2>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_staff">
                        <input type="hidden" name="staff_id" id="edit_staff_id">
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" id="edit_staff_first_name" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" id="edit_staff_last_name" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="edit_staff_email" required>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" id="edit_staff_phone">
                        </div>
                        <div class="form-group">
                            <label>Position</label>
                            <input type="text" name="position" id="edit_staff_position" required>
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="btn-submit">Update</button>
                            <button type="button" class="btn-cancel" onclick="closeModal('editStaffModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Client Modal -->
            <div class="modal" id="editClientModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Edit Client Information</h2>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_client">
                        <input type="hidden" name="client_id" id="edit_client_id">
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" id="edit_client_first_name" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" id="edit_client_last_name" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="edit_client_email" required>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" id="edit_client_phone">
                        </div>
                        <div class="form-group">
                            <label>Company Name</label>
                            <input type="text" name="company_name" id="edit_client_company">
                        </div>
                        <div class="form-group">
                            <label>Business Type</label>
                            <input type="text" name="business_type" id="edit_client_business_type">
                        </div>
                        <div class="form-group">
                            <label>Account Status</label>
                            <select name="account_status" id="edit_client_account_status">
                                <option value="pending">Pending</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="btn-submit">Update</button>
                            <button type="button" class="btn-cancel" onclick="closeModal('editClientModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Reset Modal -->
            <div class="modal" id="passwordModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Reset Password</h2>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="password_user_id">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required minlength="6">
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="btn-submit">Reset Password</button>
                            <button type="button" class="btn-cancel" onclick="closeModal('passwordModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Alert Modal -->
            <div class="alert-modal" id="alertModal">
                <div class="alert-content">
                    <div class="alert-icon" id="alertIcon"></div>
                    <h3 id="alertTitle"></h3>
                    <p id="alertMessage"></p>
                    <div id="alertButtons"></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Store pending action for confirmation
        let pendingAction = null;

        function openCreateStaffModal() {
            document.getElementById('createStaffModal').classList.add('show');
        }

        function editStaff(user) {
            document.getElementById('edit_staff_id').value = user.staff_id;
            document.getElementById('edit_staff_first_name').value = user.first_name;
            document.getElementById('edit_staff_last_name').value = user.last_name;
            document.getElementById('edit_staff_email').value = user.email;
            document.getElementById('edit_staff_phone').value = user.phone || '';
            document.getElementById('edit_staff_position').value = user.position || '';
            document.getElementById('editStaffModal').classList.add('show');
        }

        function editClient(user) {
            document.getElementById('edit_client_id').value = user.client_id;
            document.getElementById('edit_client_first_name').value = user.first_name;
            document.getElementById('edit_client_last_name').value = user.last_name;
            document.getElementById('edit_client_email').value = user.email;
            document.getElementById('edit_client_phone').value = user.phone || '';
            document.getElementById('edit_client_company').value = user.company_name || '';
            document.getElementById('edit_client_business_type').value = user.business_type || '';
            document.getElementById('edit_client_account_status').value = user.account_status;
            document.getElementById('editClientModal').classList.add('show');
        }

        function openPasswordModal(userId) {
            document.getElementById('password_user_id').value = userId;
            document.getElementById('passwordModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function showAlert(type, title, message) {
            const iconMap = {
                'success': '✓',
                'error': '✕',
                'warning': '⚠'
            };
            
            document.getElementById('alertIcon').className = 'alert-icon ' + type;
            document.getElementById('alertIcon').textContent = iconMap[type] || '!';
            document.getElementById('alertTitle').textContent = title;
            document.getElementById('alertMessage').textContent = message;
            document.getElementById('alertButtons').innerHTML = '<button class="btn-submit" onclick="closeAlert()" style="margin-top: 20px; width: 100%;">OK</button>';
            document.getElementById('alertModal').classList.add('show');
        }

        function showConfirmAlert(type, title, message, onConfirm) {
            const iconMap = {
                'success': '✓',
                'error': '✕',
                'warning': '⚠'
            };
            
            document.getElementById('alertIcon').className = 'alert-icon ' + type;
            document.getElementById('alertIcon').textContent = iconMap[type] || '!';
            document.getElementById('alertTitle').textContent = title;
            document.getElementById('alertMessage').textContent = message;
            document.getElementById('alertButtons').innerHTML = `
                <div class="alert-buttons">
                    <button class="btn-confirm" onclick="confirmAction()">Confirm</button>
                    <button class="btn-cancel-alert" onclick="closeAlert()">Cancel</button>
                </div>
            `;
            
            pendingAction = onConfirm;
            document.getElementById('alertModal').classList.add('show');
        }

        function confirmAction() {
            if (pendingAction) {
                pendingAction();
                pendingAction = null;
            }
            closeAlert();
        }

        function closeAlert() {
            document.getElementById('alertModal').classList.remove('show');
            pendingAction = null;
        }

        function toggleStatus(userId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const actionText = newStatus === 'active' ? 'enable' : 'disable';
            
            showConfirmAlert(
                'warning',
                actionText.charAt(0).toUpperCase() + actionText.slice(1) + ' User Account?',
                'Are you sure you want to ' + actionText + ' this user account?',
                function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="${userId}">
                        <input type="hidden" name="new_status" value="${newStatus}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            );
        }

        function deleteUser(userId) {
            showConfirmAlert(
                'error',
                'Delete User Account?',
                'Are you sure you want to DELETE this user account? This action cannot be undone!',
                function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="${userId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            );
        }

        // Show PHP alerts
        <?php if (!empty($alertMessage)): ?>
            showAlert(
                '<?= $alertType ?>',
                '<?= $alertType === "success" ? "Success!" : "Error" ?>',
                <?= json_encode($alertMessage) ?>
            );
        <?php endif; ?>

        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal') || event.target.classList.contains('alert-modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>