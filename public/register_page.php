<?php
// register_client.php
session_start();
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/User.php';

$errors = [];
$success = false;
$alertMessage = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $business_type = trim($_POST['business_type'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif (!preg_match('/@gmail\.com$/i', $email)) {
        $errors[] = "Only Gmail addresses are accepted.";
    }
    if (empty($phone)) {
        $errors[] = "Mobile number is required.";
    }
    if (empty($company_name)) {
        $errors[] = "Company name is required.";
    }
    if (empty($business_type)) {
        $errors[] = "Business type is required.";
    }
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check for duplicates
    if (empty($errors)) {
        if (Client::emailExists($email)) {
            $alertMessage = "Email is already registered!";
            $alertType = "error";
        } elseif (Client::phoneExists($phone)) {
            $alertMessage = "Mobile number is already registered!";
            $alertType = "error";
        } else {
            // If no errors, proceed with registration
            try {
                // Create client record
                $clientData = [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'phone' => $phone,
                    'company_name' => $company_name,
                    'business_type' => $business_type,
                    'account_status' => 'pending',
                    'registration_date' => date('Y-m-d')
                ];

                $client_id = Client::create($clientData);

                if ($client_id) {
                    // Create user account
                    $error = '';
                    $result = User::createClientUser($client_id, $username, $password, true, $error);
                    
                    if ($result) {
                        $success = true;
                        $alertMessage = "Registration successful! Redirecting to login page...";
                        $alertType = "success";
                    } else {
                        $alertMessage = $error;
                        $alertType = "error";
                    }
                } else {
                    $alertMessage = "Failed to create client account. Please try again.";
                    $alertType = "error";
                }
            } catch (Exception $e) {
                $alertMessage = "System error: " . $e->getMessage();
                $alertType = "error";
            }
        }
    } else {
        $alertMessage = implode("\n", $errors);
        $alertType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Client Service Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        .left-section {
            flex: 1;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .left-section img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .right-section {
            flex: 1;
            background: linear-gradient(135deg, #8B0000, #B22222);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 40px 80px 40px;
            overflow-y: auto;
            position: relative;
        }

        .form-wrapper {
            width: 100%;
            max-width: 480px;
            position: relative;
            overflow: hidden;
        }

        .form-slider {
            display: flex;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            width: 200%;
        }

        .form-step {
            width: 50%;
            flex-shrink: 0;
            color: white;
        }

        .form-slider.step-2 {
            transform: translateX(-50%);
        }

        h1 {
            font-size: 2.2rem;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .subtitle {
            font-size: 0.9rem;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .step-indicator {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .step-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transition: all 0.3s;
        }

        .step-dot.active {
            background: white;
            width: 30px;
            border-radius: 6px;
        }

        .form-row {
            display: flex;
            gap: 12px;
            margin-bottom: 14px;
        }

        .form-group {
            flex: 1;
            margin-bottom: 14px;
        }

        .form-group.full-width {
            width: 100%;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 10px 12px;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            background: white;
            color: #333;
            transition: box-shadow 0.3s;
        }

        input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #000;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #333;
        }

        .btn-back {
            background: transparent;
            border: 2px solid white;
            margin-top: 8px;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .login-link {
            position: fixed;
            bottom: 20px;
            left: 74%;
            transform: translateX(-50%);
            text-align: center;
            font-size: 0.9rem;
            color: white;
            white-space: nowrap;
            background: rgba(0, 0, 0, 0.3);
            padding: 10px 20px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .login-link a {
            color: white;
            text-decoration: underline;
            font-weight: 600;
        }

        /* Modal Alert Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s;
        }

        .modal-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .modal-icon.success {
            color: #28a745;
        }

        .modal-icon.error {
            color: #dc3545;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
        }

        .modal-message {
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 20px;
            white-space: pre-line;
        }

        .modal-btn {
            padding: 10px 30px;
            background: #8B0000;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .modal-btn:hover {
            background: #B22222;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { 
                opacity: 0;
                transform: translateY(-20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 968px) {
            body {
                flex-direction: column;
            }

            .left-section {
                display: none;
            }

            .right-section {
                flex: none;
                min-height: 100vh;
            }

            .login-link {
                left: 50%;
                transform: translateX(-50%);
            }
        }

        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            h1 {
                font-size: 1.8rem;
            }

            .right-section {
                padding: 30px 20px 80px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="left-section">
        <img src="assets/images/Register.png" alt="Registration">
    </div>
    
    <div class="right-section">
        <div class="form-wrapper">
            <form method="POST" action="" id="registrationForm">
                <div class="form-slider" id="formSlider">
                    <!-- STEP 1: Personal & Business Info -->
                    <div class="form-step">
                        <h1>Create Account</h1>
                        <p class="subtitle">Sign up to track your services online</p>
                        
                        <div class="step-indicator">
                            <div class="step-dot active"></div>
                            <div class="step-dot"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" id="first_name" required 
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" id="last_name" required 
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label>Gmail Address</label>
                            <input type="email" name="email" id="email" required 
                                   placeholder="example@gmail.com"
                                   pattern="[a-zA-Z0-9._%+-]+@gmail\.com$"
                                   title="Please enter a valid Gmail address"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label>Mobile Number</label>
                            <input type="tel" name="phone" id="phone" required 
                                   pattern="[0-9]{10,15}"
                                   title="Please enter a valid phone number (10-15 digits)"
                                   placeholder="e.g., 09123456789"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label>Company Name</label>
                            <input type="text" name="company_name" id="company_name" required 
                                   value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label>Business Type</label>
                            <input type="text" name="business_type" id="business_type" required 
                                   placeholder="e.g., Sole Proprietorship, Corporation, LLC"
                                   value="<?php echo htmlspecialchars($_POST['business_type'] ?? ''); ?>">
                        </div>

                        <button type="button" class="btn" id="nextBtn">Next</button>
                    </div>

                    <!-- STEP 2: Account Credentials -->
                    <div class="form-step">
                        <h1>Account Setup</h1>
                        <p class="subtitle">Create your login credentials</p>
                        
                        <div class="step-indicator">
                            <div class="step-dot"></div>
                            <div class="step-dot active"></div>
                        </div>

                        <div class="form-group full-width">
                            <label>Username</label>
                            <input type="text" name="username" id="username" required 
                                   minlength="4"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label>Password</label>
                            <input type="password" name="password" id="password" required minlength="6">
                        </div>

                        <div class="form-group full-width">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" required minlength="6">
                        </div>

                        <button type="submit" class="btn">Register</button>
                        <button type="button" class="btn btn-back" id="backBtn">Back</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="login-link">
            Already have an account? <a href="login_page.php">Log in</a>
        </div>
    </div>

    <!-- Modal Alert -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-content">
            <div class="modal-icon" id="modalIcon"></div>
            <div class="modal-title" id="modalTitle"></div>
            <div class="modal-message" id="modalMessage"></div>
            <button class="modal-btn" id="modalBtn">OK</button>
        </div>
    </div>

    <script>
        const formSlider = document.getElementById('formSlider');
        const nextBtn = document.getElementById('nextBtn');
        const backBtn = document.getElementById('backBtn');
        const modalOverlay = document.getElementById('modalOverlay');
        const modalIcon = document.getElementById('modalIcon');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalBtn = document.getElementById('modalBtn');

        // Phone input - only allow numbers
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Gmail validation on input
        document.getElementById('email').addEventListener('blur', function(e) {
            const email = this.value.trim();
            if (email && !email.match(/@gmail\.com$/i)) {
                showModal('error', 'Invalid Email', 'Please enter a valid Gmail address (e.g., example@gmail.com)');
                this.value = '';
            }
        });

        // Show modal function
        function showModal(type, title, message, redirect = false) {
            modalIcon.className = 'modal-icon ' + type;
            modalIcon.textContent = type === 'success' ? '✓' : '✕';
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            modalOverlay.classList.add('show');

            if (redirect) {
                setTimeout(() => {
                    window.location.href = 'login_page.php';
                }, 2000);
            }
        }

        // Close modal
        modalBtn.addEventListener('click', function() {
            modalOverlay.classList.remove('show');
        });

        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) {
                modalOverlay.classList.remove('show');
            }
        });

        // Validation for step 1
        function validateStep1() {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const companyName = document.getElementById('company_name').value.trim();
            const businessType = document.getElementById('business_type').value.trim();

            if (!firstName || !lastName || !email || !phone || !companyName || !businessType) {
                showModal('error', 'Incomplete Form', 'Please fill in all fields before proceeding.');
                return false;
            }

            // Gmail validation
            if (!email.match(/@gmail\.com$/i)) {
                showModal('error', 'Invalid Email', 'Please enter a valid Gmail address (e.g., example@gmail.com)');
                return false;
            }

            // Phone validation
            if (!phone.match(/^[0-9]{10,15}$/)) {
                showModal('error', 'Invalid Phone', 'Please enter a valid phone number (10-15 digits).');
                return false;
            }

            return true;
        }

        nextBtn.addEventListener('click', function() {
            if (validateStep1()) {
                formSlider.classList.add('step-2');
            }
        });

        backBtn.addEventListener('click', function() {
            formSlider.classList.remove('step-2');
        });

        // Show PHP alert if exists
        <?php if (!empty($alertMessage)): ?>
            showModal(
                '<?php echo $alertType; ?>', 
                '<?php echo $alertType === "success" ? "Success!" : "Registration Failed"; ?>', 
                <?php echo json_encode($alertMessage); ?>,
                <?php echo $success ? 'true' : 'false'; ?>
            );
            <?php if (!empty($errors) && isset($_POST['username'])): ?>
                formSlider.classList.add('step-2');
            <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>