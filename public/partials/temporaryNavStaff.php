<!-- partials/temporaryNavStaff.php -->
<div class="sidebar">
<div class="logo">
        <img src="../assets/images/logo.png" alt="Company Logo">
    </div>

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

    <!-- Profile button (link to page, NOT modal) -->
    <a href="staff_profile.php"
       class="nav-button profile-btn <?php echo ($current_page === 'staff_profile.php') ? 'active' : ''; ?>">
        Profile
    </a>

        <!-- LOGOUT -->
    <form method="POST" action="../logout.php" class="logout-form">
        <button type="submit" class="logout-btn" title="Logout">
            <img src="../assets/images/logout.png" alt="Logout">
        </button>
    </form>
</div>
