<?php
// admin_note.php - Enhanced Admin Notes Management with SweetAlert2
session_start();
//Auth check:
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Note.php';

$noteObj = new Note();
$pdo = Database::getInstance()->getConnection();

// Handle POST actions
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_note' || $action === 'edit_note') {
            $data = [
                'title'             => trim($_POST['title'] ?? ''),
                'content'           => trim($_POST['content'] ?? ''),
                'note_type'         => $_POST['note_type'] ?? 'general',
                'priority'          => $_POST['priority'] ?? 'normal',
                'related_client_id' => !empty($_POST['related_client_id']) ? (int)$_POST['related_client_id'] : null,
                'due_date'          => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            ];

            if ($action === 'add_note') {
                $data['created_by'] = $_SESSION['staff_id'] ?? null;
                if (!$data['created_by']) throw new Exception("Staff ID not found in session");
                $noteObj->create($data);
                $flash = ['type' => 'success', 'message' => 'Note created successfully!'];
            } else if ($action === 'edit_note' && !empty($_POST['note_id'])) {
                $noteObj->update((int)$_POST['note_id'], $data);
                $flash = ['type' => 'success', 'message' => 'Note updated successfully!'];
            }
        }
        else if ($action === 'toggle_complete' && !empty($_POST['note_id'])) {
            $note = $noteObj->getById((int)$_POST['note_id']);
            if ($note) {
                $noteObj->toggleCompleted((int)$_POST['note_id'], !$note['is_completed']);
                $flash = [
                    'type'    => 'info',
                    'message' => $note['is_completed'] ? 'Note marked as incomplete' : 'Note marked as completed!'
                ];
            }
        }
        else if ($action === 'delete_note' && !empty($_POST['note_id'])) {
            $noteObj->delete((int)$_POST['note_id']);
            $flash = ['type' => 'success', 'message' => 'Note deleted successfully!'];
        }
    } catch (Exception $e) {
        $flash = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
    }

    if ($flash) {
        $_SESSION['flash'] = $flash;
    }
    header("Location: admin_note.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes Management</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="../assets/css_file/admin_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">

    <style>
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.6rem;
            margin-top: 2rem;
        }
        .note-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            padding: 1.6rem;
            position: relative;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .note-card:hover { transform: translateY(-5px); box-shadow: 0 10px 24px rgba(0,0,0,0.14); }

        .note-priority {
            position: absolute;
            top: 14px; right: 14px;
            padding: 5px 12px;
            border-radius: 14px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .priority-urgent    { background:#fee2e2; color:#dc2626; }
        .priority-important { background:#fef3c7; color:#d97706; }
        .priority-normal    { background:#e5e7eb; color:#4b5563; }

        .note-completed {
            background: #f3f4f6;
            opacity: 0.78;
        }
        .note-completed::after {
            content: "COMPLETED";
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-8deg);
            font-size: 2.4rem;
            font-weight: bold;
            color: #10b98177;
            pointer-events: none;
        }

        .note-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.8rem;
            justify-content: flex-end;
        }
        .btn-sm {
            padding: 0.4rem 0.9rem;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-complete   { background:#10b981; color:white; border:none; }
        .btn-incomplete { background:#6b7280; color:white; border:none; }
        .btn-edit       { background:#3b82f6; color:white; border:none; }
        .btn-delete     { background:#ef4444; color:white; border:none; }

        .filter-bar {
            margin: 1.5rem 0;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
    </style>
</head>
<body>

<div class="container">
    <?php include '../partials/temporaryNavAdmin.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="header-left">
                <div class="page-title">Notes</div>
                <div class="page-subtitle">Manage reminders, compliance & internal notes</div>
            </div>
            <button class="add-note-btn" onclick="openNoteModal()">+ New Note</button>
        </div>

        <!-- Search & Filter -->
        <div class="filter-bar">
            <form method="GET" style="flex:1; display:flex; gap:0.8rem;">
                <input type="text" name="search" class="form-input" 
                       placeholder="Search title or content..." 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="flex:1;">
                <select name="priority" class="form-input" style="width:160px;">
                    <option value="">All Priorities</option>
                    <option value="urgent"    <?= ($_GET['priority']??'')==='urgent'?'selected':'' ?>>Urgent</option>
                    <option value="important" <?= ($_GET['priority']??'')==='important'?'selected':'' ?>>Important</option>
                    <option value="normal"    <?= ($_GET['priority']??'')==='normal'?'selected':'' ?>>Normal</option>
                </select>
                <button type="submit" class="btn-primary">Filter</button>
            </form>
        </div>

        <div class="notes-grid">
            <?php
            $search = trim($_GET['search'] ?? '');
            $priorityFilter = $_GET['priority'] ?? '';

            $filters = [];
            if ($search !== '') $filters['search'] = $search;
            if ($priorityFilter !== '' && in_array($priorityFilter, ['normal','important','urgent'])) {
                $filters['priority'] = $priorityFilter;
            }

            $notes = $noteObj->getAll($filters);

            if ($search !== '') {
                $searchLower = strtolower($search);
                $notes = array_filter($notes, function($note) use ($searchLower) {
                    return str_contains(strtolower($note['title']), $searchLower) ||
                           str_contains(strtolower($note['content']), $searchLower);
                });
            }

            if (empty($notes)): ?>
                <div class="note-card empty" style="grid-column: 1 / -1; text-align:center; padding:3rem;">
                    <p>No notes found<?= $search ? " matching your search" : "" ?>.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                    <div class="note-card <?= $note['is_completed'] ? 'note-completed' : '' ?>">
                        <div class="note-header">
                            <div class="note-title"><?= htmlspecialchars($note['title']) ?></div>
                            <div class="note-date"><?= date('M d, Y', strtotime($note['created_at'])) ?></div>
                        </div>
                        <div class="note-content">
                            <?= nl2br(htmlspecialchars($note['content'])) ?>
                        </div>
                        <div class="note-footer">
                            <div class="note-meta">
                                <small>By: <?= htmlspecialchars($note['created_by_name'] ?? '—') ?></small>
                                <?php if (!empty($note['client_first'])): ?>
                                    <small> • Client: <?= htmlspecialchars($note['client_first'].' '.$note['client_last']) ?></small>
                                <?php endif; ?>
                            </div>
                            <span class="note-priority priority-<?= $note['priority'] ?>">
                                <?= ucfirst($note['priority']) ?>
                            </span>
                        </div>

                        <div class="note-actions">
                            <button class="btn-sm <?= $note['is_completed'] ? 'btn-incomplete' : 'btn-complete' ?>" 
                                    onclick="toggleComplete(<?= $note['note_id'] ?>, <?= $note['is_completed'] ? 'true' : 'false' ?>)">
                                <?= $note['is_completed'] ? 'Mark Incomplete' : 'Mark Complete' ?>
                            </button>
                            <button class="btn-sm btn-edit" onclick="editNote(<?= $note['note_id'] ?>)">Edit</button>
                            <button class="btn-sm btn-delete" onclick="deleteNote(<?= $note['note_id'] ?>)">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Note Modal (Add/Edit) -->
<div id="noteModal" class="modal">
    <div class="modal-content">
        <div class="modal-title" id="modalTitle">ADD NEW NOTE</div>

        <form id="noteForm" method="POST" action="">
            <input type="hidden" name="action" id="formAction" value="add_note">
            <input type="hidden" name="note_id" id="editNoteId" value="">

            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" name="title" id="noteTitle" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Content *</label>
                <textarea name="content" id="noteContent" class="form-textarea" rows="7" required></textarea>
            </div>

            <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:1.2rem;">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="note_type" id="noteType" class="form-input">
                        <option value="general">General</option>
                        <option value="reminder">Reminder</option>
                        <option value="compliance">Compliance</option>
                        <option value="client_note">Client-Specific</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" id="notePriority" class="form-input">
                        <option value="normal">Normal</option>
                        <option value="important">Important</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Related Client (optional)</label>
                <select name="related_client_id" id="relatedClient" class="form-input">
                    <option value="">— None —</option>
                    <?php
                    $clients = $pdo->query("SELECT client_id, first_name, last_name FROM clients ORDER BY last_name, first_name LIMIT 300")->fetchAll();
                    foreach ($clients as $c) {
                        echo "<option value='{$c['client_id']}'>{$c['first_name']} {$c['last_name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Due Date (optional)</label>
                <input type="date" name="due_date" id="dueDate" class="form-input">
            </div>

            <div class="modal-actions">
                <button type="submit" class="save-note-btn" id="saveBtn">Save Note</button>
                <button type="button" class="cancel-note-btn" onclick="closeNoteModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// SweetAlert2 flash message (top-right toast)
<?php if (isset($_SESSION['flash'])): 
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
?>
Swal.fire({
    icon: '<?= $flash['type'] ?>',
    title: '<?= addslashes($flash['message']) ?>',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});
<?php endif; ?>

// Modal controls
function openNoteModal() {
    document.getElementById('noteModal').classList.add('active');
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.remove('active');
    document.getElementById('noteForm').reset();
    document.getElementById('modalTitle').textContent = "ADD NEW NOTE";
    document.getElementById('formAction').value = "add_note";
    document.getElementById('editNoteId').value = "";
    document.getElementById('saveBtn').textContent = "Save Note";
}

function editNote(noteId) {
    fetch(`get_note.php?id=${noteId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalTitle').textContent = "EDIT NOTE";
                document.getElementById('formAction').value = "edit_note";
                document.getElementById('editNoteId').value = noteId;
                document.getElementById('saveBtn').textContent = "Update Note";

                document.getElementById('noteTitle').value = data.title || '';
                document.getElementById('noteContent').value = data.content || '';
                document.getElementById('noteType').value = data.note_type || 'general';
                document.getElementById('notePriority').value = data.priority || 'normal';
                document.getElementById('relatedClient').value = data.related_client_id || '';
                document.getElementById('dueDate').value = data.due_date || '';

                openNoteModal();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.error || 'Could not load note data',
                    timer: 5000
                });
            }
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'Failed to load',
                text: 'Could not load note data. Please try again.',
                timer: 5000
            });
        });
}

function toggleComplete(noteId, currentStatus) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Mark this note as ${currentStatus ? 'incomplete' : 'completed'}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'toggle_complete');
            formData.append('note_id', noteId);

            fetch('', { method: 'POST', body: formData })
                .then(() => location.reload());
        }
    });
}

function deleteNote(noteId) {
    Swal.fire({
        title: 'Delete note?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete_note');
            formData.append('note_id', noteId);

            fetch('', { method: 'POST', body: formData })
                .then(() => location.reload());
        }
    });
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeNoteModal();
    }
}
</script>
</body>
</html>