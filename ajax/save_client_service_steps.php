<?php
// api/save_client_service_steps.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Notification.php'; // if using class

$db = Database::getInstance()->getConnection();

// Check admin is logged in (adjust according to your auth system)
$admin_user_id = $_SESSION['user_id'] ?? null;
if (!$admin_user_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$cs_id     = (int)($_POST['client_service_id'] ?? 0);
$deadline  = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
$steps     = $_POST['steps'] ?? [];

if ($cs_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid client service ID']);
    exit;
}

// 1. Get current requirements to detect deletions
$currentStmt = $db->prepare("SELECT requirement_id, assigned_staff_id FROM client_service_requirements WHERE client_service_id = ?");
$currentStmt->execute([$cs_id]);
$existing = $currentStmt->fetchAll(PDO::FETCH_KEY_PAIR); // requirement_id => assigned_staff_id

$submitted_req_ids = [];

// 2. Process each submitted step
foreach ($steps as $order => $step) {
    $name     = trim($step['name'] ?? '');
    $staff_id = !empty($step['staff_id']) ? (int)$step['staff_id'] : null;
    $req_id   = !empty($step['requirement_id']) ? (int)$step['requirement_id'] : null;

    if (empty($name) || !$staff_id) {
        continue; // skip invalid steps
    }

    $submitted_req_ids[] = $req_id;

    if ($req_id && isset($existing[$req_id])) {
        // UPDATE existing requirement
        $upd = $db->prepare("
            UPDATE client_service_requirements 
            SET requirement_name = ?, 
                assigned_staff_id = ?, 
                requirement_order = ?
            WHERE requirement_id = ? AND client_service_id = ?
        ");
        $upd->execute([$name, $staff_id, (int)$order, $req_id, $cs_id]);

        // If staff changed → send new notification
        if ($existing[$req_id] != $staff_id && $staff_id) {
            Notification::createAssignmentNotification(
                $db,
                $staff_id,
                $cs_id,
                $req_id,
                $admin_user_id
            );
        }
    } else {
        // INSERT new requirement
        $ins = $db->prepare("
            INSERT INTO client_service_requirements 
            (client_service_id, requirement_name, requirement_order, assigned_staff_id, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $ins->execute([$cs_id, $name, (int)$order, $staff_id]);

        $new_req_id = $db->lastInsertId();
        Notification::createAssignmentNotification(
            $db,
            $staff_id,
            $cs_id,
            $new_req_id,
            $admin_user_id
        );
    }
}

// 3. Delete removed requirements (only if still 'pending')
foreach ($existing as $req_id => $old_staff) {
    if (!in_array($req_id, $submitted_req_ids)) {
        $check = $db->prepare("SELECT status FROM client_service_requirements WHERE requirement_id = ?");
        $check->execute([$req_id]);
        if ($check->fetchColumn() === 'pending') {
            $db->prepare("DELETE FROM client_service_requirements WHERE requirement_id = ?")
               ->execute([$req_id]);
        }
    }
}

// 4. Update deadline if provided
if ($deadline) {
    $db->prepare("UPDATE client_services SET deadline = ? WHERE client_service_id = ?")
       ->execute([$deadline, $cs_id]);
}

// 5. Auto-set overall_status to 'completed' if ALL requirements are completed
$totalStmt = $db->prepare("SELECT COUNT(*) FROM client_service_requirements WHERE client_service_id = ?");
$totalStmt->execute([$cs_id]);
$total = $totalStmt->fetchColumn();

if ($total > 0) {
    $completedStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM client_service_requirements 
        WHERE client_service_id = ? AND status = 'completed'
    ");
    $completedStmt->execute([$cs_id]);
    $completedCount = $completedStmt->fetchColumn();

    if ($completedCount == $total) {
        $db->prepare("
            UPDATE client_services 
            SET overall_status = 'completed', 
                completion_date = CURDATE()
            WHERE client_service_id = ?
        ")->execute([$cs_id]);
    }
}

echo json_encode(['success' => true]);