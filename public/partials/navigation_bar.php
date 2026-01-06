<!-- partials/navigation_bar.php -->
<div class="sidebar">
    <div class="logo">✓</div>
    
    <?php 
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>

    <a href="client_dashboard.php" 
       class="nav-button <?php echo ($current_page === 'client_dashboard.php') ? 'active' : ''; ?>">
        Dashboard
    </a>

    <a href="client_progress.php" 
       class="nav-button <?php echo ($current_page === 'client_progress.php') ? 'active' : ''; ?>">
        Progress
    </a>

    <a href="client_services.php" 
       class="nav-button <?php echo ($current_page === 'client_services.php') ? 'active' : ''; ?>">
        Services
    </a>


    <!-- Add more items easily -->
    <!-- <a href="client_settings.php" class="nav-button <?php echo ($current_page === 'client_settings.php') ? 'active' : ''; ?>">Settings</a> -->
</div>