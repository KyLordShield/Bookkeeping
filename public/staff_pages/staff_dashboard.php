<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks</title>
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
            <h1>My Tasks</h1>
            <p class="subtitle">Manage your assigned tasks and update their status</p>

            <div class="stats-grid">
                <div class="stat-card">Total assigned Task</div>
                <div class="stat-card">In Progress</div>
                <div class="stat-card">Waiting for Approval</div>
                <div class="stat-card">Urgent</div>
            </div>

            <div class="filter-section">
                <span class="filter-label">Filtered by Status:</span>
                <button class="filter-btn active">All</button>
                <button class="filter-btn">In Progress</button>
                <button class="filter-btn">Waiting for Approval</button>
                <button class="filter-btn">Urgent</button>
            </div>

            <div class="tasks-table">
                <table>
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Service</th>
                            <th>what to do</th>
                            <th>status</th>
                            <th>Date assigned</th>
                            <th>Deadline</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>John Doe<br>john.doe@email.com</td>
                            <td>Bookkeeping corporation</td>
                            <td>Step 1: Document Review</td>
                            <td><span class="status-badge status-progress">in progress</span></td>
                            <td>jan 18</td>
                            <td>Jan 18</td>
                            <td><button class="action-btn" onclick="openModal('John Doe', 'Bookkeeping corporation')">open</button></td>
                        </tr>
                        <tr>
                            <td>John Doe<br>john.doe@email.com</td>
                            <td>Bookkeeping corporation</td>
                            <td>Step 2: Data Entry</td>
                            <td><span class="status-badge status-progress">in progress</span></td>
                            <td>march 19</td>
                            <td>march 19</td>
                            <td><button class="action-btn" onclick="openModal('John Doe', 'Bookkeeping corporation')">open</button></td>
                        </tr>
                        <tr>
                            <td>John Doe<br>john.doe@email.com</td>
                            <td>Bookkeeping corporation</td>
                            <td>Step 1: Document Review</td>
                            <td><span class="status-badge status-pending">Pending</span></td>
                            <td>may 2</td>
                            <td>may 2</td>
                            <td><button class="action-btn" onclick="openModal('John Doe', 'Bookkeeping corporation')">open</button></td>
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
            
            <a href="#" class="back-link" onclick="closeModal(); return false;">← Back to my Task</a>
            
            <h2 class="modal-title" id="modalService">Service Name</h2>
            
            <div class="client-info">
                <div><strong>CLIENT NAME:</strong> <span id="modalClientName">______</span></div>
                <div><strong>DUE:</strong> ______</div>
            </div>

            <div class="modal-grid">
                <div class="timeline-section">
                    <div class="timeline-title">Status Timeline</div>
                    <!-- Timeline content can be added dynamically here -->
                </div>

                <div class="actions-section">
                    <button class="modal-action-btn btn-update">UPDATE STATUS</button>
                    <button class="modal-action-btn btn-submit">SUBMIT FOR APPROVAL</button>
                    <button class="modal-action-btn btn-admin">NEEDS ADMIN ACTION</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(clientName, service) {
            document.getElementById('modalClientName').textContent = clientName;
            document.getElementById('modalService').textContent = service;
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