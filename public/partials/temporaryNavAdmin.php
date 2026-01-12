<!-- partials/navigation_bar.php -->
<div class="sidebar">
    <div class="logo">✓</div>

    <?php 
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>

    <a href="admin_dashboard.php" class="nav-button <?= $current_page === 'admin_dashboard.php' ? 'active' : '' ?>">Overview</a>
    <a href="admin_task.php" class="nav-button <?= $current_page === 'admin_task.php' ? 'active' : '' ?>">Task</a>
    <a href="admin_client_manage.php" class="nav-button <?= $current_page === 'admin_client_manage.php' ? 'active' : '' ?>">Client Management</a>
    <a href="admin_approval.php" class="nav-button <?= $current_page === 'admin_approval.php' ? 'active' : '' ?>">Approvals</a>
    <a href="admin_staff_manage.php" class="nav-button <?= $current_page === 'admin_staff_manage.php' ? 'active' : '' ?>">Staff Management</a>
    <a href="admin_note.php" class="nav-button <?= $current_page === 'admin_note.php' ? 'active' : '' ?>">Notes</a>
    <a href="admin_user.php" class="nav-button <?= $current_page === 'admin_user.php' ? 'active' : '' ?>">Users Account</a>

    <!-- LOGOUT -->
    <form method="POST" action="../logout.php" class="logout-form">
        <button type="submit" class="nav-button logout-btn">
            Logout
        </button>
    </form>
</div>
