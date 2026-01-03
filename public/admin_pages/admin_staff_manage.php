<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management</title>
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
                <div class="page-title">Staff Management</div>
                <div class="page-subtitle">Monitor staff performance and workload</div>
            </div>

            <div class="staff-grid">
                <div class="staff-card">
                    <div class="staff-header">
                        <div class="staff-avatar"></div>
                        <div class="staff-info">
                            <div class="staff-name">Sarah Johnson</div>
                            <div class="staff-email">sarah.j@approvative.com</div>
                        </div>
                    </div>
                    <div class="staff-details">
                        <div class="staff-detail-row">
                            <span class="detail-label">Contact:</span>
                            <span class="detail-value">09000000000</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Active Tasks:</span>
                            <span class="detail-value">5</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Completed Tasks:</span>
                            <span class="detail-value">23</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Workload:</span>
                            <span class="detail-value">Medium</span>
                        </div>
                    </div>
                    <button class="view-details-btn" onclick="openStaffModal()">View Task Details</button>
                </div>

                <div class="staff-card">
                    <div class="staff-header">
                        <div class="staff-avatar"></div>
                        <div class="staff-info">
                            <div class="staff-name">Sarah Johnson</div>
                            <div class="staff-email">sarah.j@approvative.com</div>
                        </div>
                    </div>
                    <div class="staff-details">
                        <div class="staff-detail-row">
                            <span class="detail-label">Contact:</span>
                            <span class="detail-value">09000000000</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Active Tasks:</span>
                            <span class="detail-value">5</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Completed Tasks:</span>
                            <span class="detail-value">23</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Workload:</span>
                            <span class="detail-value">Medium</span>
                        </div>
                    </div>
                    <button class="view-details-btn" onclick="openStaffModal()">View Task Details</button>
                </div>

                <div class="staff-card">
                    <div class="staff-header">
                        <div class="staff-avatar"></div>
                        <div class="staff-info">
                            <div class="staff-name">Sarah Johnson</div>
                            <div class="staff-email">sarah.j@approvative.com</div>
                        </div>
                    </div>
                    <div class="staff-details">
                        <div class="staff-detail-row">
                            <span class="detail-label">Contact:</span>
                            <span class="detail-value">09000000000</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Active Tasks:</span>
                            <span class="detail-value">5</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Completed Tasks:</span>
                            <span class="detail-value">23</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Workload:</span>
                            <span class="detail-value">Medium</span>
                        </div>
                    </div>
                    <button class="view-details-btn" onclick="openStaffModal()">View Task Details</button>
                </div>

                <div class="staff-card">
                    <div class="staff-header">
                        <div class="staff-avatar"></div>
                        <div class="staff-info">
                            <div class="staff-name">Sarah Johnson</div>
                            <div class="staff-email">sarah.j@approvative.com</div>
                        </div>
                    </div>
                    <div class="staff-details">
                        <div class="staff-detail-row">
                            <span class="detail-label">Contact:</span>
                            <span class="detail-value">09000000000</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Active Tasks:</span>
                            <span class="detail-value">12</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Completed Tasks:</span>
                            <span class="detail-value">23</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Workload:</span>
                            <span class="detail-value">High</span>
                        </div>
                    </div>
                    <button class="view-details-btn" onclick="openStaffModal()">View Task Details</button>
                </div>

                <div class="staff-card">
                    <div class="staff-header">
                        <div class="staff-avatar"></div>
                        <div class="staff-info">
                            <div class="staff-name">Sarah Johnson</div>
                            <div class="staff-email">sarah.j@approvative.com</div>
                        </div>
                    </div>
                    <div class="staff-details">
                        <div class="staff-detail-row">
                            <span class="detail-label">Contact:</span>
                            <span class="detail-value">09000000000</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Active Tasks:</span>
                            <span class="detail-value">2</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Completed Tasks:</span>
                            <span class="detail-value">23</span>
                        </div>
                        <div class="staff-detail-row">
                            <span class="detail-label">Workload:</span>
                            <span class="detail-value">Low</span>
                        </div>
                    </div>
                    <button class="view-details-btn" onclick="openStaffModal()">View Task Details</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Task Details Modal -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeStaffModal()">×</button>
            
            <div class="modal-staff-header">
                <div class="modal-staff-avatar"></div>
                <div class="modal-staff-info">
                    <div class="modal-staff-name">Sarah Johnson</div>
                    <div class="modal-staff-email">sarah.j@approvative.com</div>
                </div>
            </div>

            <div class="status-tabs">
                <button class="status-tab">PENDING</button>
                <button class="status-tab">IN PROGRESS</button>
                <button class="status-tab">COMPLETED</button>
            </div>

            <div class="filter-section-modal">
                <span class="filter-label">Filtered by Status:</span>
                <div class="filter-buttons">
                    <button class="filter-btn active">All</button>
                    <button class="filter-btn">Pending</button>
                    <button class="filter-btn">In progress</button>
                    <button class="filter-btn">completed</button>
                </div>
            </div>

            <table class="tasks-table">
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Service</th>
                        <th>Assigned step</th>
                        <th>status</th>
                        <th>Date assigned</th>
                        <th>Deadline</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="task-client-name">John Doe</div>
                            <div class="task-client-email">john.doe@email.com</div>
                        </td>
                        <td>Bookkeeping corporation</td>
                        <td>
                            <div class="task-step">Step 1:<br>Document<br>Review</div>
                        </td>
                        <td>
                            <span class="status-badge status-in-progress">In progress</span>
                        </td>
                        <td>jan 10</td>
                        <td>jan 10</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="task-client-name">John Doe</div>
                            <div class="task-client-email">john.doe@email.com</div>
                        </td>
                        <td>Bookkeeping corporation</td>
                        <td>
                            <div class="task-step">Step 2: Data<br>Entry</div>
                        </td>
                        <td>
                            <span class="status-badge status-in-progress">In progress</span>
                        </td>
                        <td>march 10</td>
                        <td>march 10</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="task-client-name">John Doe</div>
                            <div class="task-client-email">john.doe@email.com</div>
                        </td>
                        <td>Bookkeeping corporation</td>
                        <td>
                            <div class="task-step">Step 1:<br>Document<br>Review</div>
                        </td>
                        <td>
                            <span class="status-badge status-pending">Pending</span>
                        </td>
                        <td>may 2</td>
                        <td>may 2</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function openStaffModal() {
            document.getElementById('staffModal').classList.add('active');
        }

        function closeStaffModal() {
            document.getElementById('staffModal').classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('staffModal');
            if (event.target === modal) {
                closeStaffModal();
            }
        }
    </script>
</body>
</html>