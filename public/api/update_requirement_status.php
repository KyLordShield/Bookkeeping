<?php
// api/update_requirement_status.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Notification.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session");
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check if admin (if is_admin exists in session)
if (isset($_SESSION['is_admin']) && !$_SESSION['is_admin']) {
    error_log("User is not admin");
    echo json_encode(['success' => false, 'error' => 'Not authorized as admin']);
    exit;
}

$admin_user_id = $_SESSION['user_id'];

// Validate required parameters
if (!isset($_POST['requirement_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$requirement_id = (int) $_POST['requirement_id'];
$new_status = $_POST['status']; // 'completed' or 'rejected'
$staff_id = isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : null;
$cs_id = isset($_POST['cs_id']) ? (int) $_POST['cs_id'] : null;
$admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';

// Validate status
if (!in_array($new_status, ['completed', 'rejected'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->beginTransaction();

    // Get admin's staff_id from user_id (may be NULL for admin-only accounts)
    $staffIdStmt = $pdo->prepare("SELECT staff_id FROM users WHERE user_id = ?");
    $staffIdStmt->execute([$admin_user_id]);
    $adminStaffData = $staffIdStmt->fetch(PDO::FETCH_ASSOC);
    $admin_staff_id = $adminStaffData['staff_id'] ?? null;

    // Get requirement details including staff assignment
    $stmt = $pdo->prepare("
        SELECT 
            r.requirement_id,
            r.requirement_name,
            r.assigned_staff_id,
            cs.client_service_id,
            cs.client_id,
            c.first_name as client_first_name,
            c.last_name as client_last_name,
            s.service_name
        FROM client_service_requirements r
        JOIN client_services cs ON r.client_service_id = cs.client_service_id
        JOIN clients c ON cs.client_id = c.client_id
        JOIN services s ON cs.service_id = s.service_id
        WHERE r.requirement_id = ? AND r.status = 'approval_pending'
    ");
    $stmt->execute([$requirement_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        throw new Exception('Requirement not found or not pending approval');
    }

    $assigned_staff_id = $req['assigned_staff_id'];

    // Update requirement status
    $updateStmt = $pdo->prepare("
        UPDATE client_service_requirements 
        SET status = ?, 
            completed_at = ?,
            completed_by = ?,
            notes = ?
        WHERE requirement_id = ?
    ");

    $completed_at = ($new_status === 'completed') ? date('Y-m-d H:i:s') : null;
    
    // Use admin_staff_id if available, otherwise NULL
    $updateStmt->execute([
        $new_status,
        $completed_at,
        $admin_staff_id, // Can be NULL for admin-only accounts
        $admin_notes,
        $requirement_id
    ]);

    // Log the status change (only if admin has staff_id)
    if ($admin_staff_id) {
        $logStmt = $pdo->prepare("
            INSERT INTO requirement_progress_log 
            (requirement_id, previous_status, new_status, changed_by, notes)
            VALUES (?, 'approval_pending', ?, ?, ?)
        ");
        $logStmt->execute([$requirement_id, $new_status, $admin_staff_id, $admin_notes]);
    }

    // Get staff user_id for notification
    if ($assigned_staff_id) {
        $userStmt = $pdo->prepare("SELECT user_id FROM users WHERE staff_id = ? LIMIT 1");
        $userStmt->execute([$assigned_staff_id]);
        $staff_user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($staff_user) {
            $staff_user_id = $staff_user['user_id'];
            
            // Create notification based on action
            if ($new_status === 'completed') {
                $title = "Requirement Approved ✓";
                $message = !empty($admin_notes) 
                    ? "Your submission for '{$req['requirement_name']}' has been approved.\n\nAdmin Note: {$admin_notes}"
                    : "Your submission for '{$req['requirement_name']}' has been approved. Great work!";
            } else {
                $title = "Requirement Needs Revision";
                $message = !empty($admin_notes)
                    ? "Your submission for '{$req['requirement_name']}' needs revision.\n\nReason: {$admin_notes}"
                    : "Your submission for '{$req['requirement_name']}' has been rejected. Please review and resubmit.";
            }
            
            $link = "../staff_pages/staff_updates.php?tab=notifications&req_id={$requirement_id}&cs_id={$req['client_service_id']}";
            
            $notifResult = Notification::createRequirementReviewNotification(
                $pdo,
                $staff_user_id,
                $req['client_service_id'],
                $requirement_id,
                $title,
                $message,
                $link,
                $admin_user_id
            );
            
            if (!$notifResult) {
                error_log("WARNING: Failed to create notification for staff user_id: {$staff_user_id}");
            }
        }
    }

    // Check if all requirements are completed for this service
    $statusCheckStmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM client_service_requirements
        WHERE client_service_id = ?
    ");
    $statusCheckStmt->execute([$req['client_service_id']]);
    $statusCheck = $statusCheckStmt->fetch(PDO::FETCH_ASSOC);

    // Update overall service status if all requirements are completed
    if ($statusCheck['total'] > 0 && $statusCheck['total'] == $statusCheck['completed']) {
        $updateServiceStmt = $pdo->prepare("
            UPDATE client_services 
            SET overall_status = 'completed',
                completion_date = CURDATE()
            WHERE client_service_id = ?
        ");
        $updateServiceStmt->execute([$req['client_service_id']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $new_status === 'completed' ? 'Requirement approved successfully' : 'Requirement rejected successfully',
        'requirement_id' => $requirement_id,
        'new_status' => $new_status
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Requirement approval error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>