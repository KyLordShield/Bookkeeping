<?php
ob_start(); // Start output buffering
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production

session_start();
require_once __DIR__ . '/../../config/Database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login_page.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT staff_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data || !$user_data['staff_id']) {
        die("Error: Staff ID not found");
    }
    $staff_id = $user_data['staff_id'];
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Cloudinary Configuration
define('CLOUDINARY_CLOUD_NAME', 'dyr0rok0l'); // Your Cloudinary cloud name
define('CLOUDINARY_API_KEY', '564255156769188'); // Your API key
define('CLOUDINARY_API_SECRET', '0TqAR76L8fEgKOuvDI4mbpVtw5c'); // Your API secret

// Function to upload file to Cloudinary
function uploadToCloudinary($file, $filename) {
    $cloud_name = CLOUDINARY_CLOUD_NAME;
    $api_key = CLOUDINARY_API_KEY;
    $api_secret = CLOUDINARY_API_SECRET;
    
    if (empty($api_secret)) {
        throw new Exception('Cloudinary credentials not set');
    }
    
    $timestamp = time();
    $folder = 'Requirements';
    
    // CRITICAL: Include folder in signature calculation
    $signature = sha1("folder=$folder&timestamp=$timestamp" . $api_secret);
    
    $url = "https://api.cloudinary.com/v1_1/$cloud_name/auto/upload";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'file' => new \CURLFile($file['tmp_name'], $file['type'], $filename),
            'api_key' => $api_key,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => $folder
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Failed to upload to Cloudinary: ' . $response);
    }
    
    $result = json_decode($response, true);
    
    return [
        'public_id' => $result['public_id'],
        'url' => $result['secure_url']
    ];
}
// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['requirement_file'])) {
    header('Content-Type: application/json');
    
    $requirement_id = filter_var($_POST['requirement_id'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!$requirement_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid requirement ID']);
        exit;
    }
    
    $file = $_FILES['requirement_file'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload error']);
        exit;
    }
    
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 10MB)']);
        exit;
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    
    try {
        // Upload to Cloudinary
        $filename = 'req_' . $requirement_id . '_' . time() . '_' . $file['name'];
        $cloudinaryFile = uploadToCloudinary($file, $filename);
        
        // Insert into documents table (assuming you added cloud_storage_id column)
        $stmt = $db->prepare("
            INSERT INTO documents 
            (uploaded_by, related_to_type, related_to_id, document_name, document_url, cloud_storage_provider, cloud_storage_id, file_type, file_size_kb) 
            VALUES (?, 'requirement', ?, ?, ?, 'Cloudinary', ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $requirement_id,
            $file['name'],
            $cloudinaryFile['url'],
            $cloudinaryFile['public_id'],
            $file['type'],
            (int)($file['size'] / 1024) // Convert to KB
        ]);
        
        // Get the new document ID for response (optional, but useful)
        $document_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'File uploaded to Cloudinary successfully', 
            'file' => [
                'document_id' => $document_id,
                'filename' => $file['name'],
                'public_id' => $cloudinaryFile['public_id'],
                'url' => $cloudinaryFile['url'],
                'size' => $file['size'],
                'uploaded_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()]);
    }
    exit;
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    header('Content-Type: application/json');
    
    $requirement_id = filter_var($_POST['requirement_id'] ?? 0, FILTER_VALIDATE_INT);
    $public_id = $_POST['public_id'] ?? '';
    
    if (!$requirement_id || !$public_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    try {
        // Verify the requirement is assigned to this staff (for security)
        $stmt = $db->prepare("SELECT 1 FROM client_service_requirements WHERE requirement_id = ? AND assigned_staff_id = ?");
        $stmt->execute([$requirement_id, $staff_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            exit;
        }
        
        // Delete from documents table
        $stmt = $db->prepare("DELETE FROM documents WHERE related_to_type = 'requirement' AND related_to_id = ? AND cloud_storage_id = ?");
        $stmt->execute([$requirement_id, $public_id]);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'File not found in database']);
            exit;
        }
        
        // Delete from Cloudinary
        $cloud_name = CLOUDINARY_CLOUD_NAME;
        $api_key = CLOUDINARY_API_KEY;
        $api_secret = CLOUDINARY_API_SECRET;
        
        $timestamp = time();
        $signature = sha1("public_id=$public_id&timestamp=$timestamp" . $api_secret);
        
        $url = "https://api.cloudinary.com/v1_1/$cloud_name/destroy";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'public_id' => $public_id,
                'api_key' => $api_key,
                'timestamp' => $timestamp,
                'signature' => $signature
            ]
        ]);
        curl_exec($ch);
        curl_close($ch);
        
        echo json_encode(['success' => true, 'message' => 'File deleted']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle status updates (no changes needed, but added check for files using documents table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_status') {
        $requirement_id = filter_var($_POST['requirement_id'] ?? 0, FILTER_VALIDATE_INT);
        $new_status = $_POST['status'] ?? '';
        
        if (!$requirement_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid requirement ID']);
            exit;
        }
        
        $allowed_statuses = ['in_progress', 'approval_pending', 'completed'];
        if (!in_array($new_status, $allowed_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("SELECT status FROM client_service_requirements WHERE requirement_id = ? AND assigned_staff_id = ?");
            $stmt->execute([$requirement_id, $staff_id]);
            $current_req = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current_req) {
                echo json_encode(['success' => false, 'message' => 'Not authorized']);
                exit;
            }
            
            // Validation: must have at least one file before submitting for approval (query documents table)
            if ($new_status === 'approval_pending') {
                $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE related_to_type = 'requirement' AND related_to_id = ?");
                $stmt->execute([$requirement_id]);
                if ($stmt->fetchColumn() === 0) {
                    echo json_encode(['success' => false, 'message' => 'Please upload at least one file before submitting']);
                    exit;
                }
            }
            
            if ($new_status === 'completed' && $current_req['status'] !== 'approved') {
                echo json_encode(['success' => false, 'message' => 'Can only complete after admin approval']);
                exit;
            }
            
            $stmt = $db->prepare("UPDATE client_service_requirements SET status = ? WHERE requirement_id = ? AND assigned_staff_id = ?");
            $stmt->execute([$new_status, $requirement_id, $staff_id]);
            
            // Check if all requirements completed
            if ($new_status === 'completed') {
                $stmt = $db->prepare("SELECT client_service_id FROM client_service_requirements WHERE requirement_id = ?");
                $stmt->execute([$requirement_id]);
                $cs = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cs) {
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                        FROM client_service_requirements WHERE client_service_id = ?
                    ");
                    $stmt->execute([$cs['client_service_id']]);
                    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($counts['total'] > 0 && $counts['total'] == $counts['completed']) {
                        $stmt = $db->prepare("UPDATE client_services SET overall_status = 'completed' WHERE client_service_id = ?");
                        $stmt->execute([$cs['client_service_id']]);
                    }
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Status updated']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Fetch tasks
try {
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
            SUM(CASE WHEN csr.status = 'completed' THEN 1 ELSE 0 END) as completed_steps
        FROM client_service_requirements csr
        JOIN client_services cs ON csr.client_service_id = cs.client_service_id
        JOIN clients c ON cs.client_id = c.client_id
        JOIN services s ON cs.service_id = s.service_id
        WHERE csr.assigned_staff_id = ?
        GROUP BY cs.client_service_id
        ORDER BY cs.deadline ASC, cs.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$staff_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats_query = "
        SELECT 
            COUNT(DISTINCT cs.client_service_id) as total,
            COUNT(DISTINCT CASE WHEN cs.overall_status = 'in_progress' THEN cs.client_service_id END) as in_progress,
            COUNT(DISTINCT CASE WHEN cs.overall_status = 'approval_pending' THEN cs.client_service_id END) as pending,
            COUNT(DISTINCT CASE WHEN cs.deadline IS NOT NULL AND DATEDIFF(cs.deadline, NOW()) <= 3 
                  AND cs.overall_status != 'completed' THEN cs.client_service_id END) as urgent
        FROM client_service_requirements csr
        JOIN client_services cs ON csr.client_service_id = cs.client_service_id
        WHERE csr.assigned_staff_id = ?
    ";
    $stmt = $db->prepare($stats_query);
    $stmt->execute([$staff_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks</title>
    <link rel="stylesheet" href="../assets/css_file/staff_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Note Modal Styles */
.note-modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); overflow-y: auto; }
.note-modal.show { display: block; }
.note-modal-content { background-color: #fff; margin: 3% auto; padding: 35px; width: 90%; max-width: 600px; border-radius: 12px; position: relative; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
.note-modal-title { font-size: 20px; font-weight: bold; margin-bottom: 25px; color: #202124; }
.note-form-group { margin-bottom: 20px; }
.note-form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #5f6368; font-size: 14px; }
.note-form-input, .note-form-textarea, .note-form-select { width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 6px; font-size: 14px; transition: border-color 0.2s; }
.note-form-input:focus, .note-form-textarea:focus, .note-form-select:focus { outline: none; border-color: #1a73e8; }
.note-form-textarea { resize: vertical; min-height: 120px; font-family: inherit; }
.note-modal-buttons { display: flex; gap: 12px; margin-top: 30px; }
.note-btn { flex: 1; padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s; }
.note-btn-save { background: #1a73e8; color: white; }
.note-btn-save:hover { background: #1557b0; }
.note-btn-cancel { background: #f1f3f4; color: #5f6368; }
.note-btn-cancel:hover { background: #e8eaed; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal.show { display: block; }
        .modal-content { background-color: #fff; margin: 2% auto; padding: 30px; width: 90%; max-width: 900px; border-radius: 8px; position: relative; }
        .close-btn { position: absolute; right: 20px; top: 20px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .service-info { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0 30px 0; padding: 15px; background: #f5f5f5; border-radius: 5px; }
        .service-info-item label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; color: #666; }
        .service-info-item input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white; }
        .requirement-block { background: #fffbea; border: 2px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 15px; }
        .requirement-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .status-badge { padding: 6px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .status-approval_pending { background: #f0f0f0; color: #666; }
        .status-in_progress { background: #fff3cd; color: #856404; }
        .status-approved { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .upload-section { background: white; padding: 20px; border-radius: 8px; margin: 15px 0; border: 1px solid #e0e0e0; }
        .upload-section h4 { margin: 0 0 15px 0; font-size: 14px; color: #5f6368; }
        .file-upload-area { border: 2px dashed #dadce0; border-radius: 8px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.2s; background: #fafafa; }
        .file-upload-area:hover { border-color: #1a73e8; background: #f1f3f4; }
        .file-upload-area.dragover { border-color: #1a73e8; background: #e8f0fe; }
        .upload-icon { font-size: 48px; margin-bottom: 10px; opacity: 0.7; }
        .upload-text { color: #5f6368; font-size: 14px; margin: 5px 0; }
        .upload-subtext { color: #80868b; font-size: 12px; }
        .uploaded-files-list { margin-top: 15px; }
        .uploaded-file-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 8px; transition: all 0.2s; }
        .uploaded-file-item:hover { background: #f1f3f4; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .file-info { display: flex; align-items: center; gap: 12px; flex: 1; }
        .file-icon { font-size: 24px; }
        .file-details { flex: 1; }
        .file-name { font-size: 14px; color: #202124; font-weight: 500; margin-bottom: 2px; }
        .file-meta { font-size: 12px; color: #5f6368; }
        .file-actions { display: flex; gap: 8px; }
        .file-action-btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; transition: all 0.2s; }
        .btn-view { background: #e8f0fe; color: #1a73e8; }
        .btn-view:hover { background: #d2e3fc; }
        .btn-download { background: #d4edda; color: #155724; }
        .btn-download:hover { background: #c3e6cb; }
        .btn-remove { background: #fce8e6; color: #d93025; }
        .btn-remove:hover { background: #f6aea9; }
        .add-file-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: white; border: 1px solid #dadce0; border-radius: 4px; color: #1a73e8; font-size: 14px; cursor: pointer; transition: all 0.2s; margin-top: 10px; }
        .add-file-btn:hover { background: #f8f9fa; border-color: #1a73e8; }
        .action-buttons { display: flex; gap: 15px; margin-top: 20px; }
        .modal-action-btn { flex: 1; padding: 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 14px; transition: all 0.3s; }
        .btn-update { background: #4A90E2; color: white; }
        .btn-update:hover:not(:disabled) { background: #357ABD; }
        .btn-submit { background: #7ED321; color: white; }
        .btn-submit:hover:not(:disabled) { background: #6BB91C; }
        .btn-admin { background: #E74C3C; color: white; }
        .modal-action-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .loading-overlay.show { display: flex; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
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
                    <h3><?= $stats['pending'] ?></h3>
                    <p>Waiting for Approval</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['urgent'] ?></h3>
                    <p>Urgent</p>
                </div>
            </div>

            <div class="tasks-table">
                <table>
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Date Assigned</th>
                            <th>Deadline</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
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
                                <span class="status-badge status-<?= htmlspecialchars($task['service_status']) ?>">
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $task['service_status']))) ?>
                                </span>
                                <br>
                                <small style="color: #666; font-size: 11px;">
                                    <?= $task['completed_steps'] ?>/<?= $task['total_steps'] ?> completed
                                </small>
                            </td>
                            <td><?= $task['service_created_at'] ? date('M d, Y', strtotime($task['service_created_at'])) : '—' ?></td>
                            <td><?= $task['deadline'] ? date('M d, Y', strtotime($task['deadline'])) : 'Not set' ?></td>
                            <td>
                                <button class="action-btn" onclick='openTaskModal(<?= htmlspecialchars(json_encode($task), ENT_QUOTES, 'UTF-8') ?>);'>Open</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div id="taskModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            
            <h2 id="modalServiceName"></h2>

            <div class="service-info">
                <div class="service-info-item">
                    <label>SERVICE:</label>
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

            <div id="requirementsContainer"></div>
        </div>
    </div>
                            
    <script>
        const currentStaffId = <?= json_encode($staff_id) ?>;
        let currentTask = null;

        function openTaskModal(task) {
            currentTask = task;
            document.getElementById('modalServiceName').textContent = task.service_name;
            document.getElementById('serviceAvailed').value = task.service_name;
            document.getElementById('clientContact').value = task.email + (task.phone ? ' • ' + task.phone : '');
            document.getElementById('taskDeadline').value = task.deadline ? new Date(task.deadline).toLocaleDateString() : 'Not set';

            fetchRequirements(task.client_service_id);
            document.getElementById('taskModal').classList.add('show');
        }

        function fetchRequirements(client_service_id) {
            const container = document.getElementById('requirementsContainer');
            container.innerHTML = '<p style="text-align: center; padding: 20px;">Loading...</p>';

            fetch(`get_all_requirements.php?client_service_id=${client_service_id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.requirements) {
                        buildRequirements(data.requirements);
                    } else {
                        container.innerHTML = '<p style="color: red;">Failed to load requirements: ' + (data.message || 'Unknown error') + '</p>';
                    }
                })
                .catch(e => {
                    container.innerHTML = '<p style="color: red;">Error: ' + e.message + '</p>';
                });
        }

        function buildRequirements(requirements) {
            const container = document.getElementById('requirementsContainer');
            let html = '';

            requirements.forEach(req => {
                const isYourTask = req.assigned_staff_id == currentStaffId;
                if (!isYourTask) return;

                const statusClass = req.status || 'approval_pending';
                
                const files = req.documents || [];
                
                const hasFiles = files.length > 0;

                html += `
                    <div class="requirement-block" data-req-id="${req.requirement_id}">
                        <div class="requirement-header">
                            <h3>🎯 ${escapeHtml(req.requirement_name)}</h3>
                            <span class="status-badge status-${statusClass}">
                                ${escapeHtml((req.status || 'approval_pending').replace(/_/g, ' ').toUpperCase())}
                            </span>
                        </div>

                        <div class="upload-section">
                            <h4>Your work</h4>
                            
                            ${hasFiles ? `
                                <div class="uploaded-files-list">
                                    ${files.map(file => `
                                        <div class="uploaded-file-item">
                                            <div class="file-info">
                                                <div class="file-icon">${getFileIcon(file.filename)}</div>
                                                <div class="file-details">
                                                    <div class="file-name">${escapeHtml(file.filename)}</div>
                                                    <div class="file-meta">
                                                        ${formatFileSize(file.size)} • ${formatDate(file.uploaded_at)}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn btn-view" onclick="previewFile('${escapeHtml(file.url)}', '${escapeHtml(file.filename)}')">
                                                    👁️ Preview
                                                </button>
                                                <button class="file-action-btn btn-download" onclick="downloadFile('${escapeHtml(file.url)}', '${escapeHtml(file.filename)}')">
    📥 Download
</button>
                                                <button class="file-action-btn btn-remove" onclick="deleteFile(${req.requirement_id}, '${escapeHtml(file.public_id)}')">
                                                    🗑️ Remove
                                                </button>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                                <button class="add-file-btn" onclick="document.getElementById('fileInput${req.requirement_id}').click()">
                                    ➕ Add another file
                                </button>
                            ` : `
                                <div class="file-upload-area" id="uploadArea${req.requirement_id}" 
                                     onclick="document.getElementById('fileInput${req.requirement_id}').click()"
                                     ondrop="handleDrop(event, ${req.requirement_id})"
                                     ondragover="handleDragOver(event)"
                                     ondragleave="handleDragLeave(event)">
                                    <div class="upload-icon">📁</div>
                                    <p class="upload-text">Click to browse or drag files here</p>
                                    <p class="upload-subtext">PDF, DOC, DOCX, JPG, PNG (Max 10MB)</p>
                                </div>
                            `}
                            
                            <input type="file" id="fileInput${req.requirement_id}" style="display:none" 
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                   onchange="handleFileUpload(this, ${req.requirement_id})">
                        </div>

                        <div class="action-buttons">
                            <button class="modal-action-btn btn-update" 
                                    onclick="updateStatus('in_progress', ${req.requirement_id})" 
                                    ${req.status === 'in_progress' ? 'disabled' : ''}>
                                UPDATE STATUS
                            </button>
                            <button class="modal-action-btn btn-submit" 
                                    onclick="updateStatus('approval_pending', ${req.requirement_id})" 
                                    ${!hasFiles || req.status === 'approval_pending' ? 'disabled' : ''}>
                                SUBMIT FOR APPROVAL
                            </button>
                            <button class="modal-action-btn btn-admin" 
        onclick="openAdminNoteModal(${req.requirement_id}, '${escapeHtml(req.requirement_name)}')"
        ${req.status === 'approval_pending' ? '' : 'disabled'}>
    NEEDS ADMIN ACTION
</button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html || '<p>No tasks assigned to you</p>';
        }

        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const icons = {
                'pdf': '📄',
                'doc': '📝',
                'docx': '📝',
                'jpg': '🖼️',
                'jpeg': '🖼️',
                'png': '🖼️'
            };
            return icons[ext] || '📎';
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = now - date;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);
            
            if (minutes < 1) return 'Just now';
            if (minutes < 60) return minutes + ' min ago';
            if (hours < 24) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
            if (days < 7) return days + ' day' + (days > 1 ? 's' : '') + ' ago';
            
            return date.toLocaleDateString();
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.currentTarget.classList.add('dragover');
        }

        function handleDragLeave(e) {
            e.currentTarget.classList.remove('dragover');
        }

        function handleDrop(e, reqId) {
            e.preventDefault();
            e.currentTarget.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const input = document.getElementById('fileInput' + reqId);
                input.files = files;
                handleFileUpload(input, reqId);
            }
        }

        function handleFileUpload(input, reqId) {
            const file = input.files[0];
            if (!file) return;

            // Show preview first
            showFilePreview(file, reqId);
        }

        function showFilePreview(file, reqId) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                let previewContent = '';
                const fileType = file.type;
                
                if (fileType.startsWith('image/')) {
                    previewContent = `
                        <div style="max-height: 400px; overflow: auto; margin: 20px 0;">
                            <img src="${e.target.result}" style="max-width: 100%; border-radius: 8px;" />
                        </div>
                    `;
                } else if (fileType === 'application/pdf') {
                    previewContent = `
                        <div style="padding: 20px; background: #f5f5f5; border-radius: 8px; text-align: center;">
                            <div style="font-size: 64px; margin-bottom: 10px;">📄</div>
                            <p style="margin: 0; color: #666;">PDF Document</p>
                            <p style="margin: 5px 0; font-size: 14px; font-weight: bold;">${file.name}</p>
                            <p style="margin: 0; font-size: 12px; color: #999;">${formatFileSize(file.size)}</p>
                        </div>
                    `;
                } else {
                    previewContent = `
                        <div style="padding: 20px; background: #f5f5f5; border-radius: 8px; text-align: center;">
                            <div style="font-size: 64px; margin-bottom: 10px;">📝</div>
                            <p style="margin: 0; color: #666;">Document</p>
                            <p style="margin: 5px 0; font-size: 14px; font-weight: bold;">${file.name}</p>
                            <p style="margin: 0; font-size: 12px; color: #999;">${formatFileSize(file.size)}</p>
                        </div>
                    `;
                }
                
                Swal.fire({
                    title: 'Upload this file?',
                    html: previewContent,
                    showCancelButton: true,
                    confirmButtonText: 'Yes, upload it',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#1a73e8',
                    cancelButtonColor: '#666',
                    width: '600px'
                }).then(result => {
                    if (result.isConfirmed) {
                        uploadFile(file, reqId);
                    } else {
                        document.getElementById('fileInput' + reqId).value = '';
                    }
                });
            };
            
            if (file.type.startsWith('image/')) {
                reader.readAsDataURL(file);
            } else {
                reader.onload({target: {result: null}});
            }
        }
        
        
        function uploadFile(file, reqId) {
            const formData = new FormData();
            formData.append('requirement_file', file);
            formData.append('requirement_id', reqId);

            Swal.fire({
                title: 'Uploading...',
                text: 'Please wait while we upload your file',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Uploaded!',
                        text: 'File uploaded successfully',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        fetchRequirements(currentTask.client_service_id);
                    });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
                document.getElementById('fileInput' + reqId).value = '';
            })
            .catch(e => {
                Swal.fire('Error', 'Upload failed: ' + e.message, 'error');
                document.getElementById('fileInput' + reqId).value = '';
            });
        }

        function previewFile(url, filename) {
            const ext = filename.split('.').pop().toLowerCase();
            
            let content = '';
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                content = `
                    <div style="max-height: 500px; overflow: auto;">
                        <img src="${url}" style="max-width: 100%; border-radius: 8px;" />
                    </div>
                `;
            } else if (ext === 'pdf') {
                content = `
                    <iframe src="${url}" style="width: 100%; height: 500px; border: none; border-radius: 8px;"></iframe>
                `;
            } else {
                content = `
                    <div style="padding: 40px; text-align: center; background: #f5f5f5; border-radius: 8px;">
                        <div style="font-size: 64px; margin-bottom: 20px;">📄</div>
                        <p style="font-size: 16px; margin: 10px 0;">Cannot preview this file type</p>
                        <p style="font-size: 14px; color: #666;">${filename}</p>
                        <a href="${url}" target="_blank" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #1a73e8; color: white; text-decoration: none; border-radius: 4px;">
                            Download File
                        </a>
                    </div>
                `;
            }
            
            Swal.fire({
                title: filename,
                html: content,
                width: '800px',
                showCloseButton: true,
                showConfirmButton: false,
                footer: `<a href="${url}" target="_blank" style="color: #1a73e8;">Download File</a>`
            });
        }
        
        function downloadFile(url, filename) {
    // Force download by adding Cloudinary transformation
    url = url.replace('/upload/', '/upload/fl_attachment/');
    
    // Create a temporary link and click it
    fetch(url)
        .then(resp => resp.blob())
        .then(blob => {
            const blobUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(blobUrl);
        })
        .catch(e => {
            // Fallback: open in new tab
            window.open(url, '_blank');
        });
}

        function deleteFile(reqId, publicId) {
            Swal.fire({
                title: 'Delete this file?',
                text: 'This will remove it from permanently',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d93025',
                confirmButtonText: 'Yes, delete it'
            }).then(result => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_file');
                    formData.append('requirement_id', reqId);
                    formData.append('public_id', publicId);

                    Swal.fire({
                        title: 'Deleting...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: 'File has been removed',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                fetchRequirements(currentTask.client_service_id);
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(e => {
                        Swal.close();
                        Swal.fire('Error', 'Delete failed: ' + e.message, 'error');
                    });
                }
            });
        }

        function updateStatus(status, reqId) {
            Swal.fire({
                title: 'Submit Requirements?',
                text: 'Change status to: ' + status.replace(/_/g, ' ').toUpperCase(),
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, submit!'
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Updating...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    const formData = new FormData();
                    formData.append('action', 'update_status');
                    formData.append('requirement_id', reqId);
                    formData.append('status', status);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Failed', data.message, 'error');
                        }
                    })
                    .catch(e => {
                        Swal.fire('Error', e.message, 'error');
                    });
                }
            });
        }

        function closeModal() {
            document.getElementById('taskModal').classList.remove('show');
        }

        function escapeHtml(text) {
            const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
            return String(text || '').replace(/[&<>"']/g, m => map[m]);
        }

        window.onclick = e => {
            if (e.target === document.getElementById('taskModal')) closeModal();
        }

        window.addEventListener('load', () => {
            setTimeout(() => document.getElementById('loadingOverlay').classList.remove('show'), 300);
        });

        function openAdminNoteModal(reqId, reqName) {
    // Get client info from currentTask
    const clientName = currentTask ? `${currentTask.first_name} ${currentTask.last_name}` : 'Client';
    
    document.getElementById('noteRequirementId').value = reqId;
    document.getElementById('noteClientId').value = currentTask?.client_id || '';
    document.getElementById('noteTitle').value = `Action Required: ${reqName} - ${clientName}`;
    document.getElementById('noteContent').value = '';
    document.getElementById('noteType').value = 'client_note';
    document.getElementById('notePriority').value = 'important';
    document.getElementById('noteDueDate').value = '';
    document.getElementById('adminNoteModal').classList.add('show');
}

function closeNoteModal() {
    document.getElementById('adminNoteModal').classList.remove('show');
    document.getElementById('adminNoteForm').reset();
}

function submitAdminNote(event) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('action', 'create_admin_note');
    formData.append('title', document.getElementById('noteTitle').value);
    formData.append('content', document.getElementById('noteContent').value);
    formData.append('note_type', document.getElementById('noteType').value);
    formData.append('priority', document.getElementById('notePriority').value);
    formData.append('related_client_id', document.getElementById('noteClientId').value);
    formData.append('due_date', document.getElementById('noteDueDate').value);
    formData.append('requirement_id', document.getElementById('noteRequirementId').value);
    
    Swal.fire({
        title: 'Sending...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    fetch('submit_admin_note.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sent!',
                text: 'Admin has been notified',
                timer: 2000,
                showConfirmButton: false
            });
            closeNoteModal();
        } else {
            Swal.fire('Error', data.message || 'Failed to send note', 'error');
        }
    })
    .catch(e => {
        Swal.close();
        Swal.fire('Error', 'Failed to send: ' + e.message, 'error');
    });
}
    </script>
    <!-- Admin Note Modal -->
<div id="adminNoteModal" class="note-modal">
    <div class="note-modal-content">
        <span class="close-btn" onclick="closeNoteModal()">&times;</span>
        
        <div class="note-modal-title">REQUEST ADMIN ACTION</div>
        
        <form id="adminNoteForm" onsubmit="submitAdminNote(event)">
            <input type="hidden" id="noteRequirementId" name="requirement_id">
            <input type="hidden" id="noteClientId" name="related_client_id">
            
            <div class="note-form-group">
                <label class="note-form-label">Title *</label>
                <input type="text" id="noteTitle" class="note-form-input" required>
            </div>
            
            <div class="note-form-group">
                <label class="note-form-label">Content *</label>
                <textarea id="noteContent" class="note-form-textarea" required 
                          placeholder="Describe what admin action is needed..."></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="note-form-group">
                    <label class="note-form-label">Type</label>
                    <select id="noteType" class="note-form-select">
                        <option value="general">General</option>
                        <option value="reminder">Reminder</option>
                        <option value="compliance">Compliance</option>
                        <option value="client_note" selected>Client-Specific</option>
                    </select>
                </div>
                
                <div class="note-form-group">
                    <label class="note-form-label">Priority</label>
                    <select id="notePriority" class="note-form-select">
                        <option value="normal">Normal</option>
                        <option value="important" selected>Important</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            
            <div class="note-form-group">
                <label class="note-form-label">Due Date (optional)</label>
                <input type="date" id="noteDueDate" class="note-form-input">
            </div>
            
            <div class="note-modal-buttons">
                <button type="submit" class="note-btn note-btn-save">Save Note</button>
                <button type="button" class="note-btn note-btn-cancel" onclick="closeNoteModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>