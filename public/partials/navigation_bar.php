<!-- partials/navigation_bar.php -->
<div class="sidebar">
<div class="logo">
        <img src="../assets/images/logo.png" alt="Company Logo">
    </div>
    
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

    <button
    type="button"
    id="openProfileModal"
    class="nav-button profile-btn"
>
    Profile
</button>


        <!-- LOGOUT -->
    <form method="POST" action="../logout.php" class="logout-form">
        <button type="submit" class="logout-btn" title="Logout">
            <img src="../assets/images/logout.png" alt="Logout">
        </button>
    </form>

    
</div>

<!-- Profile Modal – MOVED OUTSIDE the sidebar (this fixes the overlay/blank issue) -->
<div id="profileModal" class="prof-modal">
    <div class="prof-modal-content">
        <span class="prof-close" id="closeProfileModal">×</span>
        
        <h2>My Profile</h2>

        <?php
        if (!isset($_SESSION['client_id'])) {
            echo '<p style="color:red;">Session error — please log in again.</p>';
        } else {
            $clientId = (int)$_SESSION['client_id'];
            $client = Client::findById($clientId);

            if (!$client) {
                echo '<p style="color:red;">Client record not found.</p>';
            } else {
        ?>
                <!-- View mode -->
                <div id="profileView" class="prof-view">
                    <p><strong>Name:</strong> <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($client['email']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($client['phone'] ?: '—') ?></p>
                    <p><strong>Company:</strong> <?= htmlspecialchars($client['company_name'] ?: '—') ?></p>
                    <p><strong>Business Type:</strong> <?= htmlspecialchars($client['business_type'] ?: '—') ?></p>
                    <p><strong>Account Status:</strong> <?= ucfirst($client['account_status']) ?></p>
                    <p><strong>Registered:</strong> <?= $client['registration_date'] ?: '—' ?></p>

                    <button type="button" id="editProfileBtn" class="prof-btn-edit">Edit Profile</button>
                </div>

                <!-- Edit mode -->
                <form id="profileEditForm" method="POST" action="update_profile.php" style="display:none;">
                    <input type="hidden" name="client_id" value="<?= $clientId ?>">

                    <div class="prof-form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($client['first_name']) ?>" required>
                    </div>

                    <div class="prof-form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($client['last_name']) ?>" required>
                    </div>

                    <div class="prof-form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($client['email']) ?>" required>
                    </div>

                    <div class="prof-form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                    </div>

                    <div class="prof-form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" value="<?= htmlspecialchars($client['company_name'] ?? '') ?>">
                    </div>

                    <div class="prof-form-group">
                        <label>Business Type</label>
                        <input type="text" name="business_type" value="<?= htmlspecialchars($client['business_type'] ?? '') ?>">
                    </div>

                    <div class="prof-form-actions">
                        <button type="submit" name="update_profile" class="prof-btn-save">Save Changes</button>
                        <button type="button" id="cancelEditBtn" class="prof-btn-cancel">Cancel</button>
                    </div>
                </form>
        <?php
            }
        }
        ?>
    </div>
</div>