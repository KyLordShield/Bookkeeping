<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../../config/Database.php';

function formatRelativeTime($dateStr) {
    $date = new DateTime($dateStr);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days == 0) {
        if ($diff->h == 0) {
            if ($diff->i == 0) return 'Just now';
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    
    if ($diff->days < 7) {
        return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
    }
    
    return $date->format('M d, Y');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login_page.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance()->getConnection();
    
    // Get staff_id
    $stmt = $db->prepare("SELECT staff_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data || !$user_data['staff_id']) {
        die("Error: Staff ID not found");
    }
    $staff_id = $user_data['staff_id'];
    
    // Fetch all notifications for this user (newest first)
    $notifications_query = "
        SELECT 
            n.notification_id,
            n.user_id,
            n.notification_type,
            n.title,
            n.message,
            n.is_read,
            n.link_url,
            n.created_at,
            n.read_at
        FROM notifications n
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
    ";
    
    $stmt = $db->prepare($notifications_query);
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    $total_count = count($notifications);
    $unread_count = 0;
    $today_count = 0;
    $week_count = 0;
    
    $today_start = date('Y-m-d 00:00:00');
    $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
    
    foreach ($notifications as $notif) {
        if (!$notif['is_read']) $unread_count++;
        if ($notif['created_at'] >= $today_start) $today_count++;
        if ($notif['created_at'] >= $week_ago) $week_count++;
    }

    $latest_created = $notifications ? $notifications[0]['created_at'] : date('Y-m-d H:i:s', strtotime('-1 minute'));
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'mark_read') {
        $notification_id = filter_var($_POST['notification_id'] ?? 0, FILTER_VALIDATE_INT);
        
        if (!$notification_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Marked as read']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'dismiss') {
        $notification_id = filter_var($_POST['notification_id'] ?? 0, FILTER_VALIDATE_INT);
        
        if (!$notification_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Notification dismissed']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'fetch_new') {
        $last_time = $_POST['last_time'] ?? '1970-01-01 00:00:00';
        
        $new_query = "
            SELECT 
                n.notification_id,
                n.user_id,
                n.notification_type,
                n.title,
                n.message,
                n.is_read,
                n.link_url,
                n.created_at,
                n.read_at
            FROM notifications n
            WHERE n.user_id = ? AND n.created_at > ?
            ORDER BY n.created_at DESC
        ";
        
        try {
            $stmt = $db->prepare($new_query);
            $stmt->execute([$user_id, $last_time]);
            $new_notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'new_notifs' => $new_notifs]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Updates</title>
    <link rel="stylesheet" href="../assets/css_file/staff_pages.css">
    <link rel="stylesheet" href="../assets/css_file/navigation_bar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .updates-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 30px;
        }

        .notification-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
            border-left: 4px solid #3b82f6;
            position: relative;
        }

        .notification-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }

        .notification-card.unread {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        .notification-card.task_assignment {
            border-left-color: #3b82f6;
        }

        .notification-card.deadline_reminder {
            border-left-color: #f59e0b;
        }

        .notification-card.approval_status {
            border-left-color: #10b981;
        }

        .notification-card.general {
            border-left-color: #6b7280;
        }

        .notification-header {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #1f2937;
        }

        .notification-message {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .notification-time {
            font-size: 14px;
            color: #9ca3af;
        }

        .unread-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .date-filter-section {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .date-filter-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .date-filter-btn:hover {
            background: #f3f4f6;
        }

        .date-filter-btn.active {
            background: #7f1d1d;
            color: white;
            border-color: #7f1d1d;
        }

        .custom-date-inputs {
            display: none;
            gap: 10px;
            align-items: center;
        }

        .custom-date-inputs.show {
            display: flex;
        }

        .custom-date-inputs input[type="date"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .apply-date-btn {
            padding: 8px 16px;
            background: #7f1d1d;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .apply-date-btn:hover {
            background: #991b1b;
        }

        .filter-label {
            font-weight: 600;
            color: #374151;
        }

        .no-updates {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
            font-size: 18px;
            background: white;
            border-radius: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 32px;
            font-weight: bold;
            color: #7f1d1d;
            margin: 0 0 5px 0;
        }

        .stat-card p {
            color: #6b7280;
            margin: 0;
            font-size: 14px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal.show {
            display: block;
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
        }

        .close-btn {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }

        .close-btn:hover {
            color: #333;
        }

        .modal-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #1f2937;
        }

        .modal-info {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .modal-info div {
            margin-bottom: 10px;
        }

        .modal-info strong {
            color: #374151;
            display: inline-block;
            min-width: 100px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-action-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-mark-read {
            background: #3b82f6;
            color: white;
        }

        .btn-mark-read:hover {
            background: #2563eb;
        }

        .btn-go-task {
            background: #10b981;
            color: white;
        }

        .btn-go-task:hover {
            background: #059669;
        }

        .btn-dismiss {
            background: #ef4444;
            color: white;
        }

        .btn-dismiss:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/temporaryNavStaff.php'; ?>

        <div class="main-content">
            <h1>Updates</h1>
            <p class="subtitle">Stay informed about your tasks and activities</p>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3 id="totalCount"><?= $total_count ?></h3>
                    <p>Total Updates</p>
                </div>
                <div class="stat-card">
                    <h3 id="unreadCount"><?= $unread_count ?></h3>
                    <p>Unread</p>
                </div>
                <div class="stat-card">
                    <h3 id="todayCount"><?= $today_count ?></h3>
                    <p>Today</p>
                </div>
                <div class="stat-card">
                    <h3 id="weekCount"><?= $week_count ?></h3>
                    <p>This Week</p>
                </div>
            </div>

            <div class="date-filter-section">
                <span class="filter-label">Filter by Date:</span>
                <button class="date-filter-btn active" onclick="filterByDate('all')">All Time</button>
                <button class="date-filter-btn" onclick="filterByDate('today')">Today</button>
                <button class="date-filter-btn" onclick="filterByDate('week')">Last 7 Days</button>
                <button class="date-filter-btn" onclick="filterByDate('month')">Last 30 Days</button>
                <button class="date-filter-btn" onclick="filterByDate('custom')">Custom Range</button>
                
                <div class="custom-date-inputs" id="customDateInputs">
                    <input type="date" id="startDate">
                    <span>to</span>
                    <input type="date" id="endDate">
                    <button class="apply-date-btn" onclick="applyCustomDate()">Apply</button>
                </div>
            </div>

            <div class="updates-container" id="updatesContainer">
                <?php if (empty($notifications)): ?>
                    <div class="no-updates">
                        <p>No updates found for this date range</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-card <?= $notif['is_read'] ? '' : 'unread' ?> <?= htmlspecialchars($notif['notification_type']) ?>" 
                             data-created="<?= htmlspecialchars($notif['created_at']) ?>"
                             onclick='openNotificationModal(<?= json_encode($notif, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            
                            <?php if (!$notif['is_read']): ?>
                                <span class="unread-badge">NEW</span>
                            <?php endif; ?>
                            
                            <div class="notification-header">
                                <?= htmlspecialchars($notif['title']) ?>
                            </div>
                            <div class="notification-message">
                                <?= htmlspecialchars($notif['message']) ?>
                            </div>
                            <div class="notification-time">
                                <?= formatRelativeTime($notif['created_at']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            
            <h2 class="modal-title" id="modalTitle"></h2>
            
            <div class="modal-info">
                <div><strong>MESSAGE:</strong> <span id="modalMessage"></span></div>
                <div><strong>DATE:</strong> <span id="modalDate"></span></div>
                <div><strong>TYPE:</strong> <span id="modalType"></span></div>
                <div><strong>STATUS:</strong> <span id="modalStatus"></span></div>
            </div>

            <div class="modal-actions">
                <button class="modal-action-btn btn-mark-read" id="markReadBtn" onclick="markAsRead()">
                    MARK AS READ
                </button>
                <button class="modal-action-btn btn-go-task" id="goToTaskBtn" style="display: none;">
                    GO TO TASK
                </button>
                <button class="modal-action-btn btn-dismiss" onclick="dismissNotification()">
                    DISMISS
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentNotificationId = null;
        let currentLinkUrl = null;
        let lastTime = '<?= $latest_created ?>';
        let totalCount = <?= $total_count ?>;
        let unreadCount = <?= $unread_count ?>;
        let todayCount = <?= $today_count ?>;
        let weekCount = <?= $week_count ?>;
        const todayStart = new Date();
        todayStart.setHours(0, 0, 0, 0);
        const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
        const updatesContainer = document.getElementById('updatesContainer');

        function escapeHtml(unsafe) {
            return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                         .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function formatRelativeTime(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} minute${diffMins !== 1 ? 's' : ''} ago`;
            if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
            if (diffDays < 7) return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
            
            return date.toLocaleDateString();
        }

        function createNotificationCard(notif) {
            const card = document.createElement('div');
            card.className = `notification-card ${notif.is_read ? '' : 'unread'} ${escapeHtml(notif.notification_type)}`;
            card.dataset.created = notif.created_at;
            card.onclick = () => openNotificationModal(notif);

            let inner = '';
            if (!notif.is_read) {
                inner += '<span class="unread-badge">NEW</span>';
            }
            inner += `
                <div class="notification-header">${escapeHtml(notif.title)}</div>
                <div class="notification-message">${escapeHtml(notif.message)}</div>
                <div class="notification-time">${formatRelativeTime(notif.created_at)}</div>
            `;
            card.innerHTML = inner;
            return card;
        }

        function checkForNewNotifications() {
            const formData = new FormData();
            formData.append('action', 'fetch_new');
            formData.append('last_time', lastTime);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.new_notifs.length > 0) {
                    // Update lastTime to the newest
                    lastTime = data.new_notifs[0].created_at;

                    // Calculate increments
                    let newUnread = 0;
                    let newToday = 0;
                    let newWeek = 0;
                    data.new_notifs.forEach(notif => {
                        if (!notif.is_read) newUnread++;
                        if (new Date(notif.created_at) >= todayStart) newToday++;
                        if (new Date(notif.created_at) >= weekAgo) newWeek++;
                    });

                    totalCount += data.new_notifs.length;
                    unreadCount += newUnread;
                    todayCount += newToday;
                    weekCount += newWeek;

                    // Update stats DOM
                    document.getElementById('totalCount').textContent = totalCount;
                    document.getElementById('unreadCount').textContent = unreadCount;
                    document.getElementById('todayCount').textContent = todayCount;
                    document.getElementById('weekCount').textContent = weekCount;

                    // Remove no-updates if present
                    const noUpdates = document.querySelector('.no-updates');
                    if (noUpdates) noUpdates.remove();

                    // Prepend new cards (reverse to add in correct order for top-to-bottom)
                    data.new_notifs.reverse().forEach(notif => {
                        updatesContainer.prepend(createNotificationCard(notif));
                    });

                    // Re-apply current filter
                    const activeBtn = document.querySelector('.date-filter-btn.active');
                    const customInputs = document.getElementById('customDateInputs');
                    if (customInputs.classList.contains('show')) {
                        applyCustomDate();
                    } else if (activeBtn) {
                        const range = activeBtn.getAttribute('onclick').match(/'([^']+)'/)[1];
                        filterByDate(range);
                    }
                }
            })
            .catch(e => console.error('Error fetching new notifications:', e));
        }

        // Poll every 10 seconds
        setInterval(checkForNewNotifications, 10000);

        function openNotificationModal(notif) {
            currentNotificationId = notif.notification_id;
            currentLinkUrl = notif.link_url;
            
            document.getElementById('modalTitle').textContent = notif.title;
            document.getElementById('modalMessage').textContent = notif.message;
            document.getElementById('modalDate').textContent = new Date(notif.created_at).toLocaleString();
            document.getElementById('modalType').textContent = notif.notification_type.replace(/_/g, ' ').toUpperCase();
            document.getElementById('modalStatus').textContent = notif.is_read ? 'Read' : 'Unread';
            
            const markReadBtn = document.getElementById('markReadBtn');
            const goToTaskBtn = document.getElementById('goToTaskBtn');
            
            if (notif.is_read) {
                markReadBtn.style.display = 'none';
            } else {
                markReadBtn.style.display = 'block';
            }
            
            if (notif.link_url) {
                goToTaskBtn.style.display = 'block';
                goToTaskBtn.onclick = () => window.location.href = notif.link_url;
            } else {
                goToTaskBtn.style.display = 'none';
            }
            
            document.getElementById('notificationModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('notificationModal').classList.remove('show');
            currentNotificationId = null;
            currentLinkUrl = null;
        }

        function markAsRead() {
            if (!currentNotificationId) return;
            
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('notification_id', currentNotificationId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Marked as Read',
                        timer: 1000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(e => {
                Swal.fire('Error', e.message, 'error');
            });
        }

        function dismissNotification() {
            if (!currentNotificationId) return;
            
            Swal.fire({
                title: 'Dismiss this notification?',
                text: 'This will permanently remove it',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, dismiss it'
            }).then(result => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'dismiss');
                    formData.append('notification_id', currentNotificationId);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Dismissed',
                                timer: 1000,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(e => {
                        Swal.fire('Error', e.message, 'error');
                    });
                }
            });
        }

        function filterByDate(range) {
            document.querySelectorAll('.date-filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            const now = new Date();
            const cards = document.querySelectorAll('.notification-card');
            
            if (range === 'custom') {
                document.getElementById('customDateInputs').classList.add('show');
                return;
            } else {
                document.getElementById('customDateInputs').classList.remove('show');
            }

            cards.forEach(card => {
                const createdDate = new Date(card.dataset.created);
                let show = false;

                if (range === 'all') {
                    show = true;
                } else if (range === 'today') {
                    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    show = createdDate >= todayStart;
                } else if (range === 'week') {
                    const weekAgo = new Date(now - 7 * 24 * 60 * 60 * 1000);
                    show = createdDate >= weekAgo;
                } else if (range === 'month') {
                    const monthAgo = new Date(now - 30 * 24 * 60 * 60 * 1000);
                    show = createdDate >= monthAgo;
                }

                card.style.display = show ? 'block' : 'none';
            });

            checkIfEmpty();
        }

        function applyCustomDate() {
            const startDate = new Date(document.getElementById('startDate').value);
            const endDate = new Date(document.getElementById('endDate').value);
            endDate.setHours(23, 59, 59);

            if (!startDate || !endDate) {
                Swal.fire('Error', 'Please select both start and end dates', 'error');
                return;
            }

            const cards = document.querySelectorAll('.notification-card');
            cards.forEach(card => {
                const createdDate = new Date(card.dataset.created);
                const show = createdDate >= startDate && createdDate <= endDate;
                card.style.display = show ? 'block' : 'none';
            });

            checkIfEmpty();
        }

        function checkIfEmpty() {
            const visibleCards = Array.from(document.querySelectorAll('.notification-card')).filter(c => c.style.display !== 'none');
            
            if (visibleCards.length === 0 && !updatesContainer.querySelector('.no-updates')) {
                const noUpdatesDiv = document.createElement('div');
                noUpdatesDiv.className = 'no-updates';
                noUpdatesDiv.innerHTML = '<p>No updates found for this date range</p>';
                updatesContainer.appendChild(noUpdatesDiv);
            } else if (visibleCards.length > 0) {
                const noUpdatesDiv = updatesContainer.querySelector('.no-updates');
                if (noUpdatesDiv) noUpdatesDiv.remove();
            }
        }

        window.onclick = function(e) {
            const modal = document.getElementById('notificationModal');
            if (e.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>