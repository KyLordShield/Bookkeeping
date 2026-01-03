<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes</title>
    <link rel="stylesheet" href="../assets/css_file/admin_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <style>
        
    </style>
</head>
<body>
    <div class="container">
        <!-- NAV BAR -->
        <?php include '../partials/temporaryNavAdmin.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <div class="header-left">
                    <div class="page-title">Notes</div>
                    <div class="page-subtitle">Manage internal notes and reminders</div>
                </div>
                <button class="add-note-btn" onclick="openNoteModal()">add note</button>
            </div>

            <div class="notes-grid">
                <div class="note-card">
                    <div class="note-header">
                        <div class="note-title">Compliance Update - Feb 2025</div>
                        <div class="note-date">Jan 25, 2025</div>
                    </div>
                    <div class="note-content">
                        New regulations effective Feb 1st. All clients need to be notified about updated compliance requirements.
                    </div>
                    <div class="note-footer">
                        <div class="share-icon"></div>
                        <span class="note-visibility">shared with staff</span>
                    </div>
                </div>

                <div class="note-card empty"></div>
            </div>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div id="noteModal" class="modal">
        <div class="modal-content">
            <div class="modal-title">ADD NEW NOTE</div>
            
            <form id="noteForm">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-input" placeholder="note title...">
                </div>

                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea class="form-textarea"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Visibility</label>
                    <input type="text" class="form-input" placeholder="by admin only, by staff or by client">
                </div>

                <div class="modal-actions">
                    <button type="button" class="save-note-btn" onclick="saveNote()">Add note</button>
                    <button type="button" class="cancel-note-btn" onclick="closeNoteModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openNoteModal() {
            document.getElementById('noteModal').classList.add('active');
        }

        function closeNoteModal() {
            document.getElementById('noteModal').classList.remove('active');
            document.getElementById('noteForm').reset();
        }

        function saveNote() {
            // Add your save logic here
            closeNoteModal();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('noteModal');
            if (event.target === modal) {
                closeNoteModal();
            }
        }
    </script>
</body>
</html>