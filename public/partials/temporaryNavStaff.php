<!-- partials/navigation_bar.php -->
<div class="sidebar">
    <div class="logo">✓</div>

    <?php 
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>

    <a href="staff_dashboard.php"
       class="nav-button <?php echo ($current_page === 'staff_dashboard.php') ? 'active' : ''; ?>">
        Overview
    </a>

    <a href="admin_task.php"
       class="nav-button <?php echo ($current_page === 'admin_task.php') ? 'active' : ''; ?>">
        Update
    </a>

    
</div>
