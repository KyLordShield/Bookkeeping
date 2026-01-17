<?php
// ../api/get_client_service_details.php

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/Database.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['client_service_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing client_service_id parameter']);
    exit;
}

$clientServiceId = (int) $_GET['client_service_id'];

try {
    $db = Database::getInstance()->getConnection();

    // Get client service basic info
    $stmt = $db->prepare("
        SELECT 
            cs.client_service_id,
            cs.deadline,
            CONCAT(c.first_name, ' ', c.last_name) AS client_name,
            s.service_name
        FROM client_services cs
        JOIN clients c ON cs.client_id = c.client_id
        JOIN services s ON cs.service_id = s.service_id
        WHERE cs.client_service_id = ?
    ");
    $stmt->execute([$clientServiceId]);
    $serviceInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$serviceInfo) {
        echo json_encode(['success' => false, 'error' => 'Client service not found']);
        exit;
    }

    // Get requirements/steps
    $stmt = $db->prepare("
        SELECT 
            csr.requirement_id,
            csr.requirement_name,
            csr.requirement_order,
            csr.description,
            csr.assigned_staff_id,
            csr.status,
            csr.started_at,
            csr.completed_at,
            CONCAT(st.first_name, ' ', st.last_name) AS assigned_staff_name
        FROM client_service_requirements csr
        LEFT JOIN staff st ON csr.assigned_staff_id = st.staff_id
        WHERE csr.client_service_id = ?
        ORDER BY csr.requirement_order ASC
    ");
    $stmt->execute([$clientServiceId]);
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get documents/files for each requirement
    foreach ($steps as &$step) {
        $stmt = $db->prepare("
            SELECT 
                document_id,
                document_name,
                document_url,
                file_type,
                file_size_kb,
                upload_date
            FROM documents
            WHERE related_to_type = 'requirement' 
            AND related_to_id = ?
            ORDER BY upload_date DESC
        ");
        $stmt->execute([$step['requirement_id']]);
        $step['files'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'client_name' => $serviceInfo['client_name'],
        'service_name' => $serviceInfo['service_name'],
        'deadline' => $serviceInfo['deadline'],
        'steps' => $steps
    ]);

} catch (PDOException $e) {
    error_log('Database error in get_client_service_details.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Error in get_client_service_details.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred']);
}
?>