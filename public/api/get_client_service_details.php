<?php
header('Content-Type: application/json');
require_once '../../config/Database.php';
require_once '../../classes/Client.php';

$csId = (int)($_GET['client_service_id'] ?? 0);
if (!$csId) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

$pdo = Database::getInstance()->getConnection();

$stmt = $pdo->prepare("
    SELECT 
        cs.*, 
        CONCAT(c.first_name, ' ', c.last_name) AS client_name,
        s.service_name
    FROM client_services cs
    JOIN clients c ON cs.client_id = c.client_id
    JOIN services s ON cs.service_id = s.service_id
    WHERE cs.client_service_id = ?
");
$stmt->execute([$csId]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}

$stepsStmt = $pdo->prepare("
    SELECT requirement_id, requirement_name, requirement_order, assigned_staff_id
    FROM client_service_requirements
    WHERE client_service_id = ?
    ORDER BY requirement_order
");
$stepsStmt->execute([$csId]);
$steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'client_name' => $service['client_name'],
    'service_name' => $service['service_name'],
    'deadline' => $service['deadline'],
    'steps' => $steps
]);