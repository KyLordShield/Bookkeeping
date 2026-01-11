<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$client_service_id = isset($_GET['client_service_id']) ? intval($_GET['client_service_id']) : 0;

if ($client_service_id === 0) {
    die(json_encode(['success' => false, 'error' => 'Invalid client_service_id']));
}

try {
    $db = Database::getInstance()->getConnection();
    
    $query = "
        SELECT 
            requirement_id,
            client_service_id,
            requirement_name,
            requirement_order,
            description,
            assigned_staff_id,
            status,
            started_at,
            completed_at
        FROM client_service_requirements
        WHERE client_service_id = :cs_id
        ORDER BY requirement_order ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':cs_id', $client_service_id, PDO::PARAM_INT);
    $stmt->execute();
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'requirements' => $requirements,
        'count' => count($requirements)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;
?>