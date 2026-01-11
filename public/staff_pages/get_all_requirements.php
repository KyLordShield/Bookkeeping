<?php
session_start();
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$client_service_id = $_GET['client_service_id'] ?? 0;

if (!$client_service_id) {
    echo json_encode(['success' => false, 'error' => 'Missing client_service_id']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get ALL requirements for this service, including which staff they're assigned to
    $query = "
        SELECT 
            csr.*,
            s.first_name as staff_first_name,
            s.last_name as staff_last_name,
            CONCAT(s.first_name, ' ', s.last_name) as assigned_staff_name
        FROM client_service_requirements csr
        LEFT JOIN staff st ON csr.assigned_staff_id = st.staff_id
        LEFT JOIN users s ON st.user_id = s.user_id
        WHERE csr.client_service_id = ?
        ORDER BY csr.requirement_order ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$client_service_id]);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'requirements' => $requirements
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching requirements: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>