<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Notification.php';

// Debug: Check session (remove after testing)
error_log("Admin Approval Page - Session: " . print_r($_SESSION, true));

//Auth check:
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login_page.php");
    exit();
}


$admin_user_id = $_SESSION['user_id'];

$pdo = Database::getInstance()->getConnection();

// Debug: Check if notification table exists and structure
try {
    $testQuery = $pdo->query("SHOW COLUMNS FROM notifications");
    $columns = $testQuery->fetchAll(PDO::FETCH_COLUMN);
    error_log("Notifications table columns: " . implode(", ", $columns));
} catch (Exception $e) {
    error_log("Error checking notifications table: " . $e->getMessage());
}

// Fetch all pending approvals
$stmt = $pdo->prepare("
    SELECT 
        r.requirement_id,
        r.requirement_name,
        r.requirement_order,
        r.notes as requirement_notes,
        cs.client_service_id,
        c.client_id,
        c.first_name AS client_first_name,
        c.last_name AS client_last_name,
        s.service_name,
        st.staff_id,
        st.first_name AS staff_first_name,
        st.last_name AS staff_last_name,
        r.started_at
    FROM client_service_requirements r
    JOIN client_services cs ON r.client_service_id = cs.client_service_id
    JOIN clients c ON cs.client_id = c.client_id
    JOIN services s ON cs.service_id = s.service_id
    LEFT JOIN staff st ON r.assigned_staff_id = st.staff_id
    WHERE r.status = 'approval_pending'
    ORDER BY r.started_at DESC, r.requirement_id DESC
");
$stmt->execute();
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Queue</title>
    <link rel="stylesheet" href="../assets/css_file/admin_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
       
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/temporaryNavAdmin.php'; ?>

        <div class="main-content">
            <div class="approval-header">
                <h1>Approval Queue</h1>
                <p>Review and approve staff submissions</p>
            </div>

            <div class="queue-container">
                <?php
                if (empty($pending)) {
                    echo '<div class="no-pending">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3>All Clear!</h3>
                            <p>No pending approvals at the moment</p>
                          </div>';
                } else {
                    foreach ($pending as $index => $req) {
                        $req_id = $req['requirement_id'];

                        // Fetch documents
                        $uploadStmt = $pdo->prepare("
                            SELECT 
                                document_id,
                                document_name AS original_name,
                                document_url AS file_path,
                                upload_date AS uploaded_at,
                                file_type
                            FROM documents
                            WHERE related_to_type = 'requirement'
                              AND related_to_id = ?
                            ORDER BY upload_date DESC
                        ");
                        $uploadStmt->execute([$req_id]);
                        $uploads = $uploadStmt->fetchAll(PDO::FETCH_ASSOC);

                        $isUrgent = $index === 0; // First one is urgent
                        ?>
                        <div class="requirement-card" data-req-id="<?= $req_id ?>" data-staff-id="<?= $req['staff_id'] ?>" data-cs-id="<?= $req['client_service_id'] ?>">
                            <div class="card-header">
                                <div class="client-info">
                                    <h3><?= htmlspecialchars($req['client_first_name'] . ' ' . $req['client_last_name']) ?></h3>
                                    <p class="service-name"><?= htmlspecialchars($req['service_name']) ?></p>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="requirement-section">
                                    <h4><?= htmlspecialchars($req['requirement_name']) ?></h4>
                                    <p>All documents verified and complete. Ready to submit to client.</p>
                                </div>

                                <div class="info-row">
                                    <strong>Staff:</strong>
                                    <span><?= htmlspecialchars($req['staff_first_name'] . ' ' . $req['staff_last_name']) ?></span>
                                </div>

                                <div class="documents-section">
                                    <div class="documents-header">Documents Submitted (<?= count($uploads) ?>)</div>
                                    <div class="documents-grid">
                                        <?php if (empty($uploads)): ?>
                                            <div style="grid-column: 1/-1; text-align: center; padding: 20px; color: #6c757d;">
                                                No documents uploaded
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($uploads as $up): 
                                                $ext = strtolower(pathinfo($up['original_name'], PATHINFO_EXTENSION) ?: ($up['file_type'] ?? ''));
                                            ?>
                                                <div class="document-item" 
                                                     data-path="<?= htmlspecialchars($up['file_path']) ?>"
                                                     data-type="<?= htmlspecialchars($ext) ?>"
                                                     data-name="<?= htmlspecialchars($up['original_name']) ?>">
                                                    <div class="document-name"><?= htmlspecialchars($up['original_name']) ?></div>
                                                    <span class="verified-badge">Verified by staff</span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="notes-section">
                                    <label>Admin Review Notes (Optional)</label>
                                    <textarea class="admin-notes" placeholder="Add any notes or instructions before approving..."></textarea>
                                </div>

                                <div class="action-buttons">
                                    <button class="btn btn-reject" data-action="rejected">
                                        Reject & Return
                                    </button>
                                    <button class="btn btn-approve" data-action="completed">
                                        Approve & Proceed
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <!-- File Preview Modal -->
    <div id="previewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="previewTitle">Document Preview</h3>
                <button class="modal-close" onclick="closePreviewModal()">×</button>
            </div>
            <div class="modal-body">
                <div id="previewContent" class="preview-container"></div>
            </div>
        </div>
    </div>

    <script>
        // File preview
        document.querySelectorAll('.document-item').forEach(item => {
            item.addEventListener('click', () => {
                const path = item.dataset.path;
                const type = item.dataset.type.toLowerCase();
                const name = item.dataset.name;
                const content = document.getElementById('previewContent');
                
                document.getElementById('previewTitle').textContent = name;
                content.innerHTML = '';

                if (['jpg','jpeg','png','gif','webp'].includes(type)) {
                    content.innerHTML = `<img src="${path}" class="preview-img" alt="Preview">`;
                } else if (type === 'pdf') {
                    content.innerHTML = `<iframe src="${path}" class="preview-iframe"></iframe>`;
                } else {
                    content.innerHTML = `<div class="preview-download">
                        <p style="margin-bottom: 20px; color: #6c757d;">Cannot preview this file type</p>
                        <a href="${path}" download>Download File</a>
                    </div>`;
                }

                document.getElementById('previewModal').classList.add('active');
            });
        });

        function closePreviewModal() {
            document.getElementById('previewModal').classList.remove('active');
        }

        // Approve/Reject handlers
        document.querySelectorAll('.btn-approve, .btn-reject').forEach(btn => {
            btn.addEventListener('click', function() {
                const card = this.closest('.requirement-card');
                const reqId = card.dataset.reqId;
                const staffId = card.dataset.staffId;
                const csId = card.dataset.csId;
                const action = this.dataset.action;
                const notes = card.querySelector('.admin-notes').value.trim();

                const actionText = action === 'completed' ? 'approve' : 'reject';
                const actionColor = action === 'completed' ? '#28a745' : '#dc3545';

                Swal.fire({
                    title: `${action === 'completed' ? 'Approve' : 'Reject'} this submission?`,
                    text: action === 'completed' 
                        ? 'The requirement will be marked as completed and staff will be notified.'
                        : 'The requirement will be sent back to staff for revision.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: actionColor,
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: `Yes, ${actionText} it!`,
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'Processing...',
                            text: 'Please wait',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Disable buttons
                        card.querySelectorAll('.btn').forEach(b => b.disabled = true);

                        fetch('../api/update_requirement_status.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                requirement_id: reqId,
                                staff_id: staffId,
                                cs_id: csId,
                                status: action,
                                admin_notes: notes
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: action === 'completed' ? 'Approved!' : 'Rejected!',
                                    text: data.message || `Requirement ${actionText}ed successfully`,
                                    timer: 2000,
                                    showConfirmButton: false
                                });

                                card.style.opacity = '0';
                                card.style.transform = 'scale(0.95)';
                                card.style.transition = 'all 0.3s';
                                
                                setTimeout(() => {
                                    card.remove();

                                    if (!document.querySelector('.requirement-card')) {
                                        document.querySelector('.queue-container').innerHTML = `
                                            <div class="no-pending">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <h3>All Clear!</h3>
                                                <p>No pending approvals at the moment</p>
                                            </div>`;
                                    }
                                }, 2000);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Update Failed',
                                    text: data.error || 'Unknown error occurred',
                                    confirmButtonColor: '#7D1C19'
                                });
                                card.querySelectorAll('.btn').forEach(b => b.disabled = false);
                            }
                        })
                        .catch(err => {
                            console.error('Fetch error:', err);
                            Swal.fire({
                                icon: 'error',
                                title: 'Network Error',
                                text: 'Could not connect to server. Please check your connection.',
                                confirmButtonColor: '#7D1C19'
                            });
                            card.querySelectorAll('.btn').forEach(b => b.disabled = false);
                        });
                    }
                });
            });
        });

        // Close modal on outside click
        document.getElementById('previewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreviewModal();
            }
        });
    </script>
</body>
</html>