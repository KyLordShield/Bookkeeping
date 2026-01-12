<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $note_type = $_POST['note_type'] ?? 'client_note';
    $priority = $_POST['priority'] ?? 'normal';
    $related_client_id = !empty($_POST['related_client_id']) ? (int)$_POST['related_client_id'] : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $created_by = $_SESSION['staff_id'] ?? null;
    
    if (!$created_by) {
        throw new Exception("Staff ID not found in session");
    }
    
    if (empty($title) || empty($content)) {
        throw new Exception("Title and content are required");
    }
    
    // Direct database insert matching admin_note.php structure
    $stmt = $db->prepare("
        INSERT INTO notes 
        (title, content, note_type, priority, related_client_id, due_date, created_by, is_completed, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    
    $stmt->execute([
        $title,
        $content,
        $note_type,
        $priority,
        $related_client_id,
        $due_date,
        $created_by
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Note created successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}