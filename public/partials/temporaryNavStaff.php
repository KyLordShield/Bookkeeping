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

    <a href="staff_updates.php"
       class="nav-button <?php echo ($current_page === 'staff_updates.php') ? 'active' : ''; ?>">
        Update
    </a>

    
</div>
