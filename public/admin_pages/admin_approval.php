<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Notification.php';

// Debug: Check session (remove after testing)
error_log("Admin Approval Page - Session: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
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
        .main-content {
            background: #7D1C19 !important;
            padding: 40px 20px;
            min-height: 100vh;
        }

        .approval-header {
            color: white;
            text-align: left;
            margin-bottom: 30px;
            max-width: 1100px;
            margin: 0 auto 30px;
        }

        .approval-header h1 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .approval-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .queue-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .requirement-card {
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .card-header {
            background: #f8f9fa;
            padding: 18px 24px;
            border-bottom: 1px solid #e9ecef;
        }

        .client-info h3 {
            margin: 0 0 4px 0;
            font-size: 17px;
            color: #212529;
        }

        .service-name {
            color: #6c757d;
            font-size: 15px;
            margin: 0;
        }

        .card-body {
            padding: 20px 24px;
        }

        .requirement-section {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 16px;
        }

        .requirement-section h4 {
            margin: 0 0 6px 0;
            font-size: 15px;
            color: #2e7d32;
        }

        .requirement-section p {
            margin: 0;
            color: #1b5e20;
            font-size: 14px;
        }

        .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .info-row strong {
            min-width: 100px;
            color: #495057;
        }

        .info-row span {
            color: #212529;
        }

        .documents-section {
            margin: 16px 0;
        }

        .documents-header {
            font-weight: 600;
            color: black;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }

        .document-item {
            background: #ADD8E6;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .document-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .document-name {
            font-size: 13px;
            color: black;
            word-break: break-word;
        }

        .verified-badge {
            background: #d4edda;
            color: #155724;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-top: 6px;
            display: inline-block;
        }

        .notes-section {
            margin: 16px 0;
        }

        .notes-section label {
            display: block;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .notes-section textarea {
            width: 100%;
            min-height: 80px;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            resize: vertical;
            font-family: inherit;
            font-size: 14px;
        }

        .notes-section textarea:focus {
            outline: none;
            border-color: #7D1C19;
            box-shadow: 0 0 0 3px rgba(125, 28, 25, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .no-pending {
            background: white;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
            color: #6c757d;
        }

        .no-pending h3 {
            font-size: 18px;
            margin: 8px 0;
        }

        .no-pending p {
            font-size: 15px;
        }

        .no-pending svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #212529;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #6c757d;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #f8f9fa;
            color: #212529;
        }

        .modal-body {
            padding: 24px;
            overflow: auto;
            flex: 1;
        }

        .preview-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
        }

        .preview-img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .preview-iframe {
            width: 100%;
            height: 70vh;
            border: none;
            border-radius: 8px;
        }

        .preview-download {
            text-align: center;
            padding: 40px;
        }

        .preview-download a {
            display: inline-block;
            padding: 12px 24px;
            background: #7D1C19;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .preview-download a:hover {
            background: #5f1614;
        }
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