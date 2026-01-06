<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Updates</title>
    <link rel="stylesheet" href="../assets/css_file/staff_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <style>
        /* Optional: Add any page-specific styles here if needed */
    </style>
</head>
<body>
    <div class="container">
        
        <!-- NAV BAR - First child of .container (assumed to be a sidebar) -->
        <?php include '../partials/temporaryNavStaff.php'; ?>

        <!-- MAIN CONTENT - All page content wrapped here -->
        <div class="main-content">
            <h1>Updates</h1>
            <p class="subtitle">Stay informed about your tasks and activities</p>

            <div class="stats-grid">
                <div class="stat-card">Total Updates</div>
                <div class="stat-card">New Notifications</div>
                <div class="stat-card">Task Updates</div>
                <div class="stat-card">Reminders</div>
            </div>

            <div class="filter-section">
                <span class="filter-label">Filtered by Type:</span>
                <button class="filter-btn active">All</button>
                <button class="filter-btn">Task Assignments</button>
                <button class="filter-btn">Deadlines</button>
                <button class="filter-btn">Approvals</button>
            </div>

            <div class="tasks-table">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Message</th>
                            <th>Related Task</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="status-badge status-progress">New Task</span></td>
                            <td>You have been assigned to work on Bookkeeping - Corporation</td>
                            <td>Step 1: Document Review</td>
                            <td>John Doe<br>john.doe@email.com</td>
                            <td>2 hours ago</td>
                            <td><span class="status-badge status-pending">Unread</span></td>
                            <td><button class="action-btn" onclick="openModal('New Task', 'You have been assigned to work on Bookkeeping - Corporation for John Doe')">view</button></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge" style="background: #f59e0b;">Reminder</span></td>
                            <td>Task for Sarah Williams is due in 2 days</td>
                            <td>Step 3: Final Review</td>
                            <td>Sarah Williams<br>sarah.w@email.com</td>
                            <td>1 day ago</td>
                            <td><span class="status-badge status-pending">Unread</span></td>
                            <td><button class="action-btn" onclick="openModal('Deadline Reminder', 'Task for Sarah Williams is due in 2 days')">view</button></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge" style="background: #10b981;">Approved</span></td>
                            <td>Your submission has been approved by admin</td>
                            <td>Step 2: Data Entry</td>
                            <td>Mike Johnson<br>mike.j@email.com</td>
                            <td>3 days ago</td>
                            <td><span class="status-badge" style="background: #6b7280;">Read</span></td>
                            <td><button class="action-btn" onclick="openModal('Approval', 'Your submission has been approved by admin')">view</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- End of .main-content -->
    </div>

    <!-- Modal (placed outside .container to avoid layout interference) -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            
            <a href="#" class="back-link" onclick="closeModal(); return false;">← Back to Updates</a>
            
            <h2 class="modal-title" id="modalType">Update Type</h2>
            
            <div class="client-info">
                <div><strong>MESSAGE:</strong> <span id="modalMessage">______</span></div>
                <div><strong>DATE:</strong> ______</div>
            </div>

            <div class="modal-grid">
                <div class="timeline-section">
                    <div class="timeline-title">Update Details</div>
                    <!-- Update details content can be added dynamically here -->
                </div>

                <div class="actions-section">
                    <button class="modal-action-btn btn-update">MARK AS READ</button>
                    <button class="modal-action-btn btn-submit">GO TO TASK</button>
                    <button class="modal-action-btn btn-admin">DISMISS</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(type, message) {
            document.getElementById('modalType').textContent = type;
            document.getElementById('modalMessage').textContent = message;
            document.getElementById('taskModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('taskModal').classList.remove('show');
        }

        // Close modal when clicking outside the modal content
        window.onclick = function(e) {
            const modal = document.getElementById('taskModal');
            if (e.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>