<?php
// get_note.php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Note.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

$noteObj = new Note();
$note = $noteObj->getById($id);

if ($note) {
    echo json_encode([
        'success' => true,
        'title' => $note['title'],
        'content' => $note['content'],
        'note_type' => $note['note_type'],
        'priority' => $note['priority'],
        'related_client_id' => $note['related_client_id'],
        'due_date' => $note['due_date']
    ]);
} else {
    echo json_encode(['success' => false]);
}