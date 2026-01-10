<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/Database.php';

/*
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
*/

$pdo = Database::getInstance()->getConnection();

$csId = (int)($_POST['client_service_id'] ?? 0);
$deadline = $_POST['deadline'] ?? null;
$steps = $_POST['steps'] ?? [];

if (!$csId || empty($steps)) {
    echo json_encode(['success' => false, 'error' => 'Missing required data']);
    exit;
}

// 1. Update deadline
$pdo->prepare("UPDATE client_services SET deadline = ? WHERE client_service_id = ?")
    ->execute([$deadline ?: null, $csId]);

// 2. Delete old requirements (you can also do soft-delete or diff update)
$pdo->prepare("DELETE FROM client_service_requirements WHERE client_service_id = ?")
    ->execute([$csId]);

// 3. Insert new ones
$stmt = $pdo->prepare("
    INSERT INTO client_service_requirements 
    (client_service_id, requirement_name, requirement_order, assigned_staff_id, status)
    VALUES (?, ?, ?, ?, 'pending')
");

$hasAnyAssignment = false;

foreach ($steps as $order => $item) {
    $name = trim($item['name'] ?? '');
    $staff = !empty($item['staff_id']) ? (int)$item['staff_id'] : null;

    if ($name === '') continue;

    $stmt->execute([$csId, $name, $order, $staff]);

    if ($staff) $hasAnyAssignment = true;
}

// 4. Update overall status of client_service
$newStatus = $hasAnyAssignment ? 'in_progress' : 'pending';
$pdo->prepare("UPDATE client_services SET overall_status = ? WHERE client_service_id = ?")
    ->execute([$newStatus, $csId]);

echo json_encode(['success' => true]);