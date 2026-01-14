<?php
// api/get_client_service_details.php
header('Content-Type: application/json');
require_once '../../config/Database.php';

$csId = (int)($_GET['client_service_id'] ?? 0);
if ($csId < 1) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid ID']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();

    // Get service info with client and service names
    $stmt = $pdo->prepare("
        SELECT 
            cs.*, 
            CONCAT(c.first_name, ' ', c.last_name) AS client_name,
            c.first_name,
            c.last_name,
            s.service_name
        FROM client_services cs
        JOIN clients c ON cs.client_id = c.client_id
        JOIN services s ON cs.service_id = s.service_id
        WHERE cs.client_service_id = ?
    ");
    $stmt->execute([$csId]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        echo json_encode(['success' => false, 'error' => 'Client service not found']);
        exit;
    }

    // Get all requirements/steps with assigned staff info
    $stepsStmt = $pdo->prepare("
        SELECT 
            r.requirement_id,
            r.requirement_name,
            r.requirement_order,
            r.assigned_staff_id,
            r.status,
            CONCAT(s.first_name, ' ', s.last_name) AS assigned_staff_name
        FROM client_service_requirements r
        LEFT JOIN staff s ON r.assigned_staff_id = s.staff_id
        WHERE r.client_service_id = ?
        ORDER BY r.requirement_order ASC
    ");
    $stepsStmt->execute([$csId]);
    $steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'         => true,
        'client_name'     => $service['client_name'],
        'client_first_name' => $service['first_name'],
        'client_last_name'  => $service['last_name'],
        'service_name'    => $service['service_name'],
        'deadline'        => $service['deadline'],
        'overall_status'  => $service['overall_status'],
        'start_date'      => $service['start_date'],
        'completion_date' => $service['completion_date'],
        'steps'           => $steps
    ]);
    
} catch (Exception $e) {
    error_log("Get client service details error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}