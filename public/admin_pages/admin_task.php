<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management</title>
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
                <div class="page-title">Task Management</div>
                <div class="page-subtitle">Review purchases and assign staff to each step</div>
            </div>

            <div class="filter-section">
                <span class="filter-label">Filtered by Status:</span>
                <div class="filter-buttons">
                    <button class="filter-btn active">All</button>
                    <button class="filter-btn">New</button>
                    <button class="filter-btn">In progress</button>
                    <button class="filter-btn">completed</button>
                </div>
            </div>

            <div class="task-card">
                <div class="task-header">
                    <div class="task-info">
                        <div class="task-client">Client Name:</div>
                        <div class="task-type">Type of Service: Awaited</div>
                        <div class="task-status">Status:</div>
                        <div class="task-dates">Purchased: Jan 25, 2025 | Deadline: Feb 10, 2025</div>
                        <div class="task-contact">Contact:</div>
                    </div>
                    <button class="staff-assigned-btn" onclick="openModal()">Staff Assigned</button>
                </div>

                <div class="task-checklist">
                    <div class="checklist-item">
                        <div class="checkbox"></div>
                        <span>Initial Document Review<br>Assigned to: Sarah Johnson</span>
                    </div>
                    <div class="checklist-item">
                        <div class="checkbox"></div>
                        <span></span>
                    </div>
                    <div class="checklist-item">
                        <div class="checkbox"></div>
                        <span></span>
                    </div>
                </div>

                <div class="task-footer">
                    <div class="progress-label">Progress:</div>
                    <div class="progress-label"><span class="progress-count">1/3</span></div>
                </div>
            </div>

            <div class="task-card">
                <div class="task-header">
                    <div class="task-info">
                        <div class="task-client">Client Name:</div>
                        <div class="task-type">Type of Service: Awaited</div>
                        <div class="task-status">Status:</div>
                        <div class="task-dates">Purchased: Jan 25, 2025 | Deadline: Feb 10, 2025</div>
                        <div class="task-contact">Contact:</div>
                    </div>
                    <button class="staff-assigned-btn" onclick="openModal()">Staff Assigned</button>
                </div>

                <div class="task-checklist">
                    <div class="checklist-item">
                        <div class="checkbox"></div>
                        <span>Initial Document Review<br>Assigned to: Sarah Johnson</span>
                    </div>
                    <div class="checklist-item">
                        <div class="checkbox"></div>
                        <span></span>
                    </div>
                    <div class="checklist-item">
                        <div class="checkbox"></div>
                        <span></span>
                    </div>
                </div>

                <div class="task-footer">
                    <div class="progress-label">Progress:</div>
                    <div class="progress-label"><span class="progress-count">1/3</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Assignment Modal -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">×</button>
            
            <div class="modal-header">
                <div class="modal-title">Assign Staff to Steps</div>
                <div class="modal-subtitle">Client: Jane Smith | Service: HR Services</div>
            </div>

            <div class="step-item">
                <div class="step-header">
                    <div class="step-number">1</div>
                    <div class="step-info">
                        <div class="step-title">Input steps</div>
                    </div>
                </div>
                <select class="staff-select">
                    <option>--select staff--</option>
                    <option>Sarah Johnson</option>
                    <option>Mike Wilson</option>
                    <option>Emily Davis</option>
                </select>
            </div>

            <div class="step-item">
                <div class="step-header">
                    <div class="step-number">2</div>
                    <div class="step-info">
                        <div class="step-title">Consultation & Assessment</div>
                        <div class="step-subtitle">Assign Staff Member:</div>
                    </div>
                </div>
                <select class="staff-select">
                    <option>--select staff--</option>
                    <option>Sarah Johnson</option>
                    <option>Mike Wilson</option>
                    <option>Emily Davis</option>
                </select>
            </div>

            <div class="step-item">
                <div class="step-header">
                    <div class="step-number">3</div>
                    <div class="step-info">
                        <div class="step-title">Consultation & Assessment</div>
                        <div class="step-subtitle">Assign Staff Member:</div>
                    </div>
                </div>
                <select class="staff-select">
                    <option>--select staff--</option>
                    <option>Sarah Johnson</option>
                    <option>Mike Wilson</option>
                    <option>Emily Davis</option>
                </select>
            </div>

            <div class="deadline-section">
                <div class="deadline-label">Deadline:</div>
                <input type="text" class="deadline-input" placeholder="dd - yyy" value="dd - yyy">
            </div>

            <div class="frame-info">
                <span>Frame 61</span>
                <span>Frame 62</span>
            </div>

            <div class="modal-actions">
                <button class="save-btn">Save Assignments</button>
                <button class="cancel-modal-btn" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('staffModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('staffModal').classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('staffModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>