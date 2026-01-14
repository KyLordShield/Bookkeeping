<?php
session_start();

require_once '../../config/cloudinary.php';
require_once '../../classes/Staff.php';

$allowed_roles = ['staff', 'admin'];  

if (!isset($_SESSION['role']) || 
    !in_array($_SESSION['role'], $allowed_roles) || 
    !isset($_SESSION['staff_id'])) 
{
    
    header("Location: ../login.php");
    exit;
}

$staffId = (int)$_SESSION['staff_id'];
$staffObj = new Staff($cloudinary);
$staff = $staffObj->getStaffById($staffId);

if (!$staff) {
    // Handle case where staff not found
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Staff profile not found.'];
    header("Location: ../dashboard.php"); // Redirect to dashboard or appropriate page
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $position   = trim($_POST['position'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'First name, last name, and email are required.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid email format.'];
        } else {
            try {
                $data = [
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'phone'      => $phone ?: null,
                    'position'   => $position ?: null
                ];

                $staffObj->updateStaff($staffId, $data);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated successfully!'];
                // Refresh staff data
                $staff = $staffObj->getStaffById($staffId);
            } catch (Exception $e) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            } catch (PDOException $e) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getCode() == 23000 
                    ? 'Email already exists. Please use a different email.'
                    : 'Database error occurred.'];
            }
        }
    }

    header("Location: staff_profile.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile</title>
    <link rel="stylesheet" href="../assets/css_file/admin_pages.css"> <!-- Reuse if applicable, or adjust path -->
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #7D1C19;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .main-content {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 2em;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .page-subtitle {
            font-size: 1.1em;
            color: #666;
        }

        .profile-section {
            display: flex;
            align-items: center;
            margin-bottom: 40px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: #e0e0e0;
            background-size: cover;
            background-position: center;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: transform 0.2s;
            margin-right: 30px;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 1.8em;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .profile-detail {
            font-size: 1em;
            margin-bottom: 8px;
            color: #555;
        }

        .edit-form {
            max-width: 600px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #777;
            font-size: 0.9em;
        }

        .profile-preview-container {
            margin-top: 10px;
            text-align: center;
        }

        .current-profile-preview {
            max-width: 140px;
            border-radius: 12px;
            border: 1px solid #ccc;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .current-profile-preview:hover {
            transform: scale(1.05);
        }

        .save-btn {
            background: #27ae60;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.3s;
        }

        .save-btn:hover {
            background: #219d53;
        }

        .swal2-popup.preview-popup {
            width: 95vw !important;
            max-width: 1100px !important;
            padding: 1.5rem !important;
        }

        .swal2-popup .swal2-image {
            max-width: 100% !important;
            max-height: 85vh !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.3);
        }

        @media (max-width: 768px) {
            .profile-section {
                flex-direction: column;
                align-items: flex-start;
            }

            .profile-avatar {
                margin-right: 0;
                margin-bottom: 20px;
            }

            .swal2-popup.preview-popup {
                width: 98vw !important;
                padding: 0.8rem !important;
            }

            .swal2-popup .swal2-image {
                max-height: 70vh !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/temporaryNavStaff.php'; ?> <!-- Assume a staff navigation include, adjust as needed -->

        <div class="main-content">
            <div class="page-header">
                <div class="page-title">My Profile</div>
                <div class="page-subtitle">View and update your personal information</div>
            </div>

            <div class="profile-section">
                <?php
                $avatarUrl = !empty($staff['profile_picture']) ? htmlspecialchars($staff['profile_picture']) : '../assets/default-avatar.png';
                ?>
                <div class="profile-avatar" 
                     style="background-image: url('<?= $avatarUrl ?>');"
                     onclick="previewImage('<?= $avatarUrl ?>', '<?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>')">
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></div>
                    <div class="profile-detail"><strong>Email:</strong> <?= htmlspecialchars($staff['email']) ?></div>
                    <div class="profile-detail"><strong>Phone:</strong> <?= htmlspecialchars($staff['phone'] ?? 'N/A') ?></div>
                    <div class="profile-detail"><strong>Position:</strong> <?= htmlspecialchars($staff['position'] ?? 'N/A') ?></div>
                </div>
            </div>

            <h2 style="font-size: 1.5em; font-weight: 600; margin-bottom: 20px;">Edit Profile</h2>

            <form method="POST" class="edit-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="staff_id" value="<?= $staffId ?>">

                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($staff['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($staff['last_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($staff['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($staff['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" value="<?= htmlspecialchars($staff['position'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Profile Picture</label>
                    <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small>(Optional - JPG, PNG, GIF, WebP - max 5MB)</small>

                    <div class="profile-preview-container" id="profilePreviewContainer" style="display: none;">
                        <img id="profilePreview" class="current-profile-preview" src="" alt="Preview">
                        <small id="previewText">New selected picture (preview)</small>
                    </div>
                </div>

                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        function previewImage(src, name = 'Profile Picture') {
            Swal.fire({
                title: name,
                imageUrl: src,
                imageAlt: name,
                imageWidth: 700,
                imageHeight: 600,
                imageClass: 'swal2-image',
                showConfirmButton: true,
                confirmButtonText: 'Close',
                confirmButtonColor: '#3498db',
                background: '#fff',
                padding: '1.5rem',
                customClass: {
                    popup: 'preview-popup'
                }
            });
        }

        document.querySelector('input[type="file"]')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const previewImg = document.getElementById('profilePreview');
                    previewImg.src = ev.target.result;
                    document.getElementById('profilePreviewContainer').style.display = 'block';
                    document.getElementById('previewText').textContent = 'New selected picture (preview)';
                };
                reader.readAsDataURL(file);
            }
        });

        document.querySelector('form')?.addEventListener('submit', function(e) {
            const fileInput = document.querySelector('input[type="file"]');
            if (fileInput && fileInput.files.length > 0) {
                e.preventDefault();
                Swal.fire({
                    title: 'Uploading picture...',
                    html: 'Please wait while we upload the profile picture to the cloud.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                this.submit();
            }
        });

        <?php if (isset($_SESSION['flash'])): 
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
        ?>
            Swal.fire({
                icon: '<?= $flash['type'] ?>',
                title: '<?= addslashes($flash['message']) ?>',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        <?php endif; ?>
    </script>
</body>
</html>