<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Client.php';

// Redirect if not logged in as client
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    header("Location: ../../login_page.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| HANDLE AJAX MEETING REQUEST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'submit_meeting'
) {

    header('Content-Type: application/json');

    try {
        $db = Database::getInstance()->getConnection();

        // 2️⃣ GET CLIENT ID FROM USERS TABLE
        $stmt = $db->prepare("
            SELECT client_id 
            FROM users 
            WHERE user_id = ? AND client_id IS NOT NULL
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode([
                'success' => false,
                'message' => 'Only client accounts can request meetings'
            ]);
            exit;
        }

        $client_id = $user['client_id']; // ✅ CORRECT CLIENT ID

        // 3️⃣ COLLECT & SANITIZE INPUT
        $service_name      = trim($_POST['service_name'] ?? '');
        $preferred_date    = trim($_POST['preferred_date'] ?? '');
        $preferred_time    = trim($_POST['preferred_time'] ?? '');
        $additional_notes  = trim($_POST['additional_notes'] ?? '');

        if (empty($service_name) || empty($preferred_date) || empty($preferred_time)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
            exit;
        }

        // 4️⃣ GET SERVICE ID
        $stmt = $db->prepare("
            SELECT service_id 
            FROM services 
            WHERE service_name = ? AND is_active = 1
        ");
        $stmt->execute([$service_name]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$service) {
            echo json_encode(['success' => false, 'message' => 'Invalid service selected']);
            exit;
        }

        $service_id = $service['service_id'];

        // 5️⃣ DATE VALIDATION
        $date = DateTime::createFromFormat('Y-m-d', $preferred_date);
        if (!$date || $date->format('Y-m-d') !== $preferred_date) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit;
        }

        $today = new DateTime('today');
        if ($date < $today) {
            echo json_encode(['success' => false, 'message' => 'Please select a future date']);
            exit;
        }

        // 6️⃣ TIME VALIDATION (9AM–6PM)
        $time = DateTime::createFromFormat('H:i', $preferred_time);
        if (!$time) {
            echo json_encode(['success' => false, 'message' => 'Invalid time format']);
            exit;
        }

        $hour = (int) $time->format('H');
        if ($hour < 9 || $hour >= 18) {
            echo json_encode([
                'success' => false,
                'message' => 'Please select a time during business hours (9:00 AM – 6:00 PM)'
            ]);
            exit;
        }

        // 7️⃣ INSERT SERVICE REQUEST
        $stmt = $db->prepare("
            INSERT INTO service_requests 
                (client_id, service_id, preferred_date, preferred_time, additional_notes, request_status)
            VALUES
                (?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $client_id,
            $service_id,
            $preferred_date,
            $preferred_time,
            $additional_notes
        ]);

        echo json_encode([
            'success'    => true,
            'message'    => 'Meeting request submitted successfully! We will contact you soon.',
            'request_id' => $db->lastInsertId()
        ]);
        exit;

    } catch (Exception $e) {
        error_log('MEETING REQUEST ERROR: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| FETCH SERVICES FROM DATABASE
|--------------------------------------------------------------------------
*/
try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT service_id, service_name, service_type, description, is_active 
        FROM services 
        WHERE is_active = 1
        ORDER BY service_name ASC
    ");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('FETCH SERVICES ERROR: ' . $e->getMessage());
    $services = [];
}

/*
|--------------------------------------------------------------------------
| SERVICE IMAGES MAPPING
|--------------------------------------------------------------------------
*/
function getServiceImage($serviceName) {
    // Normalize the service name for matching
    $normalized = strtolower(trim($serviceName));
    
    // Check for keywords in service name
    if (strpos($normalized, 'bookkeeping') !== false && strpos($normalized, 'single') !== false) {
        return 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=400&h=300&fit=crop';
    }
    if (strpos($normalized, 'bookkeeping') !== false && strpos($normalized, 'corporation') !== false) {
        return 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=400&h=300&fit=crop';
    }
    if (strpos($normalized, 'human resource') !== false || strpos($normalized, 'hr') !== false) {
        return 'https://images.unsplash.com/photo-1521737711867-e3b97375f902?w=400&h=300&fit=crop';
    }
    if (strpos($normalized, 'business registration') !== false || strpos($normalized, 'registration') !== false) {
        return 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=400&h=300&fit=crop';
    }
    if (strpos($normalized, 'renewal') !== false || strpos($normalized, 'compliance') !== false) {
        return 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=400&h=300&fit=crop';
    }
    
    // Default image for unmatched services
    return 'https://images.unsplash.com/photo-1557804506-669a67965ba0?w=400&h=300&fit=crop';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services</title>
    <link rel="stylesheet" href="../assets/css_file/client_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <style>
        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }

        .success-message {
            background-color: #efe;
            color: #3c3;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }

        .service-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }

        .service-card {
            overflow: hidden;
        }

        .service-content {
            padding: 20px;
        }

        .service-card-title {
            margin-top: 15px;
        }

        .no-services {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <?php include '../partials/navigation_bar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Our Services</h1>
                <p>Browse and select the services you need for your business</p>
            </div>

            <div class="services-grid">
                <?php if (empty($services)): ?>
                    <div class="no-services">
                        <p>No services available at the moment. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                        <div class="service-card" data-category="<?php echo htmlspecialchars($service['service_type']); ?>">
                            <img src="<?php echo getServiceImage($service['service_name']); ?>" 
                                 alt="<?php echo htmlspecialchars($service['service_name']); ?>" 
                                 class="service-image">
                            <div class="service-content">
                                <div class="service-card-title"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                <div class="service-description">
                                    <?php 
                                    $desc = htmlspecialchars($service['description']);
                                    echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                    ?>
                                </div>
                                <div class="service-price-section">
                                    <button class="select-service-btn" 
                                            onclick="openMeetingModal('<?php echo htmlspecialchars($service['service_name'], ENT_QUOTES); ?>')">
                                        Request Meeting
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Request Meeting Modal -->
    <div id="meetingModal" class="modal-overlay">
        <div class="modal-window">
            <div class="modal-header">
                <h2>Request Meeting</h2>
                <span class="close-btn" onclick="closeMeetingModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div class="service-name">
                    Service: <strong id="modalServiceName">Bookkeeping – Corporation</strong>
                </div>

                <label for="preferredDate">Preferred Date</label>
                <input type="date" id="preferredDate" name="preferredDate" required>

                <label for="preferredTime">Preferred Time</label>
                <input type="time" id="preferredTime" name="preferredTime" required>
                <p class="business-hours">Business hours: 9:00 AM - 6:00 PM</p>

                <label for="additionalNotes">Additional Notes?</label>
                <textarea id="additionalNotes" placeholder="Any specific requirements or questions..." rows="4"></textarea>
                
                <div id="errorMessage" class="error-message"></div>
                <div id="successMessage" class="success-message"></div>
            </div>

            <div class="modal-footer">
                <button class="submit-request-btn" id="submitBtn" onclick="submitMeetingRequest()">Submit Request</button>
            </div>
        </div>
    </div>

    <script>
        // Open the Request Meeting modal
        function openMeetingModal(serviceName) {
            document.getElementById('modalServiceName').textContent = serviceName;
            document.getElementById('meetingModal').classList.add('active');
            
            // Reset form
            document.getElementById('preferredDate').value = '';
            document.getElementById('preferredTime').value = '';
            document.getElementById('additionalNotes').value = '';
            document.getElementById('errorMessage').style.display = 'none';
            document.getElementById('successMessage').style.display = 'none';
            
            // Set min date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('preferredDate').setAttribute('min', today);
        }

        // Close modal
        function closeMeetingModal() {
            document.getElementById('meetingModal').classList.remove('active');
        }

        // Submit meeting request via AJAX
        function submitMeetingRequest() {
            const service = document.getElementById('modalServiceName').textContent;
            const date = document.getElementById('preferredDate').value;
            const time = document.getElementById('preferredTime').value;
            const notes = document.getElementById('additionalNotes').value;
            const errorMsg = document.getElementById('errorMessage');
            const successMsg = document.getElementById('successMessage');
            const submitBtn = document.getElementById('submitBtn');

            // Hide previous messages
            errorMsg.style.display = 'none';
            successMsg.style.display = 'none';

            // Validate fields
            if (!date || !time) {
                errorMsg.textContent = 'Please select both date and time.';
                errorMsg.style.display = 'block';
                return;
            }

            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';

            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'submit_meeting');
            formData.append('service_name', service);
            formData.append('preferred_date', date);
            formData.append('preferred_time', time);
            formData.append('additional_notes', notes);

            // Send AJAX request to the same page
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successMsg.textContent = data.message;
                    successMsg.style.display = 'block';
                    
                    // Close modal after 2 seconds
                    setTimeout(() => {
                        closeMeetingModal();
                    }, 2000);
                } else {
                    errorMsg.textContent = data.message;
                    errorMsg.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorMsg.textContent = 'An error occurred. Please try again.';
                errorMsg.style.display = 'block';
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Request';
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('meetingModal');
            if (event.target === modal) {
                closeMeetingModal();
            }
        }
    </script>
    <script src="../partials/client-profile-modal.js"></script>
</body>
</html>