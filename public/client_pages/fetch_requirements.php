<?php
require_once __DIR__ . '/../../config/Database.php';

$id = (int)($_GET['id'] ?? 0);
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT requirement_name, status
    FROM client_service_requirements
    WHERE client_service_id = ?
    ORDER BY requirement_order
");
$stmt->execute([$id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
