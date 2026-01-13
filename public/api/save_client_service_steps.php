<?php
// public/api/save_client_service_steps.php
session_start();
header('Content-Type: application/json');

// Show errors during development (remove or comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/Database.php';
require_once '../../classes/Notification.php';

try {
    $pdo = Database::getInstance()->getConnection();

    $admin_user_id = $_SESSION['user_id'] ?? null;
    if (!$admin_user_id) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $csId = (int)($_POST['client_service_id'] ?? 0);
    $deadline = $_POST['deadline'] ?? null;
    $steps = $_POST['steps'] ?? [];

    if ($csId < 1) {
        echo json_encode(['success' => false, 'error' => 'Invalid client service ID']);
        exit;
    }

    // 1. Get current requirements (to detect deletions & staff changes)
    $currentStmt = $pdo->prepare("
        SELECT requirement_id, assigned_staff_id, status 
        FROM client_service_requirements 
        WHERE client_service_id = ?
    ");
    $currentStmt->execute([$csId]);
    $existing = $currentStmt->fetchAll(PDO::FETCH_ASSOC);

    $existingById = [];
    foreach ($existing as $row) {
        $existingById[$row['requirement_id']] = $row;
    }

    $submittedIds = [];

    // 2. Process submitted steps (insert new / update existing)
    foreach ($steps as $orderStr => $item) {
        $order    = (int)$orderStr;
        $name     = trim($item['name'] ?? '');
        $staff_id = !empty($item['staff_id']) ? (int)$item['staff_id'] : null;
        $req_id   = !empty($item['requirement_id']) ? (int)$item['requirement_id'] : null;

        if ($name === '') {
            continue;
        }

        $submittedIds[] = $req_id;

        if ($req_id && isset($existingById[$req_id])) {
            // UPDATE existing requirement
            $upd = $pdo->prepare("
                UPDATE client_service_requirements 
                SET requirement_name   = ?,
                    assigned_staff_id  = ?,
                    requirement_order  = ?
                WHERE requirement_id = ? AND client_service_id = ?
            ");
            $upd->execute([$name, $staff_id, $order, $req_id, $csId]);

            // Notify only if staff changed
            $old_staff = $existingById[$req_id]['assigned_staff_id'] ?? null;
            if ($staff_id && $staff_id != $old_staff) {
                Notification::createAssignmentNotification($pdo, $staff_id, $csId, $req_id, $admin_user_id);
            }
        } else {
            // INSERT new requirement
            $ins = $pdo->prepare("
                INSERT INTO client_service_requirements 
                (client_service_id, requirement_name, requirement_order, assigned_staff_id, status)
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $ins->execute([$csId, $name, $order, $staff_id]);

            $newReqId = $pdo->lastInsertId();
            if ($staff_id) {
                Notification::createAssignmentNotification($pdo, $staff_id, $csId, $newReqId, $admin_user_id);
            }
        }
    }

    // 3. Delete removed steps — only if they were still 'pending'
    foreach ($existingById as $req_id => $info) {
        if (!in_array($req_id, array_filter($submittedIds))) {
            if ($info['status'] === 'pending') {
                $del = $pdo->prepare("DELETE FROM client_service_requirements WHERE requirement_id = ?");
                $del->execute([$req_id]);
            }
        }
    }

    // 4. Update deadline
    if ($deadline !== '') {
        $stmt = $pdo->prepare("UPDATE client_services SET deadline = ? WHERE client_service_id = ?");
        $stmt->execute([$deadline, $csId]);
    } else {
        $stmt = $pdo->prepare("UPDATE client_services SET deadline = NULL WHERE client_service_id = ?");
        $stmt->execute([$csId]);
    }

    // 5. Auto-complete overall status if ALL requirements are completed
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM client_service_requirements WHERE client_service_id = ?");
    $totalStmt->execute([$csId]);
    $total = (int)$totalStmt->fetchColumn();

    if ($total > 0) {
        $compStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM client_service_requirements 
            WHERE client_service_id = ? AND status = 'completed'
        ");
        $compStmt->execute([$csId]);
        $completedCount = (int)$compStmt->fetchColumn();

        if ($completedCount === $total) {
            $stmt = $pdo->prepare("
                UPDATE client_services 
                SET overall_status = 'completed',
                    completion_date = CURDATE()
                WHERE client_service_id = ?
            ");
            $stmt->execute([$csId]);
        }
    }

    // Success response
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}