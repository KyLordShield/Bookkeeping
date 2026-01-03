<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management</title>
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
                <div class="page-title">Client Management</div>
                <div class="page-subtitle">View and manage all clients</div>
            </div>

         <div class="filter-section">
            <div class="filter-left">
                <span class="filter-label">Filter:</span>
                <div class="filter-buttons">
                    <button class="filter-btn active">All</button>
                    <button class="filter-btn">with account</button>
                    <button class="filter-btn">No account</button>
                </div>
            </div>

            <a href="add_client.php" class="add-client-btn">+ Add Client</a>
        </div>



            <div class="client-table-container">
                <table class="client-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Service</th>
                            <th>status</th>
                            <th>Account type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="client-name">John Doe</div>
                                <div class="client-email">john.doe@email.com</div>
                            </td>
                            <td>09000000000</td>
                            <td>Bookkeeping corporation</td>
                            <td>
                                <span class="status-badge status-active">active</span>
                            </td>
                            <td>with account</td>
                            <td>
                                <button class="action-btn">Reset Password</button>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="client-name">John Doe</div>
                                <div class="client-email">john.doe@email.com</div>
                            </td>
                            <td>09000000000</td>
                            <td>Bookkeeping corporation</td>
                            <td>
                                <span class="status-badge status-active">active</span>
                            </td>
                            <td>with account</td>
                            <td>
                                <button class="action-btn">Reset Password</button>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="client-name">John Doe</div>
                                <div class="client-email">john.doe@email.com</div>
                            </td>
                            <td>09000000000</td>
                            <td>Bookkeeping corporation</td>
                            <td>
                                <span class="status-badge status-completed">completed</span>
                            </td>
                            <td>No account</td>
                            <td>
                                <button class="action-btn">Prefer phone communication</button>
                                <a href="#" class="action-link">View Details</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>