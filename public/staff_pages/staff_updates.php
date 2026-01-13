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
    
    // Get staff_id for other queries if needed
    $stmt = $db->prepare("SELECT staff_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data || !$user_data['staff_id']) {
        die("Error: Staff ID not found");
    }
    $staff_id = $user_data['staff_id'];
    
    // ✅ FIXED: Query notifications using user_id, not staff_id
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
    $stmt->execute([$user_id]); // ✅ Changed from $staff_id to $user_id
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'mark_read') {
        $notification_id = filter_var($_POST['notification_id'] ?? 0, FILTER_VALIDATE_INT);
        
        if (!$notification_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            exit;
        }
        
        try {
            // ✅ FIXED: Use user_id, not staff_id
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
            // ✅ FIXED: Use user_id, not staff_id
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
            // ✅ FIXED: Use user_id, not staff_id
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
        .updates-container { display: flex; flex-direction: column; gap: 20px; margin-top: 30px; }
        .notification-card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.3s; border: none; position: relative; }
        .notification-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); transform: translateY(-2px); }
        .notification-card.unread { background: #fff9f0; }
        .notification-header { font-size: 28px; font-weight: bold; margin-bottom: 12px; color: #1a1a1a; }
        .notification-message { font-size: 18px; color: #333; margin-bottom: 10px; line-height: 1.6; white-space: pre-line; }
        .notification-time { font-size: 16px; color: #666; }
        .date-filter-section { display: none; }
        .no-updates { text-align: center; padding: 60px 20px; color: #9ca3af; font-size: 18px; background: white; border-radius: 12px; }
        .stats-grid { display: none; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal.show { display: block; }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 30px; width: 90%; max-width: 600px; border-radius: 8px; position: relative; }
        .close-btn { position: absolute; right: 20px; top: 20px; font-size: 28px; font-weight: bold; cursor: pointer; color: #999; }
        .close-btn:hover { color: #333; }
        .modal-title { font-size: 24px; font-weight: bold; margin-bottom: 20px; color: #1f2937; }
        .modal-info { background: #f5f5f5; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .modal-info div { margin-bottom: 10px; }
        .modal-info strong { color: #374151; display: inline-block; min-width: 100px; }
        .modal-message-full { white-space: pre-line; line-height: 1.6; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .modal-action-btn { flex: 1; padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; transition: all 0.3s; }
        .btn-mark-read { background: #3b82f6; color: white; }
        .btn-mark-read:hover { background: #2563eb; }
        .btn-go-task { background: #10b981; color: white; }
        .btn-go-task:hover { background: #059669; }
        .btn-dismiss { background: #ef4444; color: white; }
        .btn-dismiss:hover { background: #dc2626; }
        .notification-toast { position: fixed; top: 20px; right: 20px; background: #7f1d1d; color: white; padding: 20px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 10000; display: none; animation: slideIn 0.3s ease-out; max-width: 400px; }
        .notification-toast.show { display: block; }
        .notification-toast-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-weight: bold; font-size: 16px; }
        .notification-toast-icon { font-size: 24px; }
        .notification-toast-body { font-size: 14px; line-height: 1.4; }
        .notification-toast-close { position: absolute; top: 10px; right: 10px; cursor: pointer; font-size: 20px; opacity: 0.7; }
        .notification-toast-close:hover { opacity: 1; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/temporaryNavStaff.php'; ?>

        <div class="main-content">
            <h1>Updates</h1>
            <p class="subtitle">Stay informed about your tasks and activities</p>

            <div class="updates-container" id="updatesContainer">
                <?php if (empty($notifications)): ?>
                    <div class="no-updates"><p>No updates found for this date range</p></div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-card <?= $notif['is_read'] ? '' : 'unread' ?> <?= htmlspecialchars($notif['notification_type']) ?>" 
                             data-created="<?= htmlspecialchars($notif['created_at']) ?>"
                             onclick='openNotificationModal(<?= json_encode($notif, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            <div class="notification-header"><?= htmlspecialchars($notif['title']) ?></div>
                            <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                            <div class="notification-time"><?= formatRelativeTime($notif['created_at']) ?></div>
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
                <div><strong>MESSAGE:</strong> <div class="modal-message-full" id="modalMessage"></div></div>
                <div><strong>DATE:</strong> <span id="modalDate"></span></div>
                <div><strong>TYPE:</strong> <span id="modalType"></span></div>
                <div><strong>STATUS:</strong> <span id="modalStatus"></span></div>
            </div>
            <div class="modal-actions">
                <button class="modal-action-btn btn-mark-read" id="markReadBtn" onclick="markAsRead()">MARK AS READ</button>
                <button class="modal-action-btn btn-go-task" id="goToTaskBtn" style="display: none;">GO TO TASK</button>
                <button class="modal-action-btn btn-dismiss" onclick="dismissNotification()">DISMISS</button>
            </div>
        </div>
    </div>

    <div id="notificationToast" class="notification-toast">
        <span class="notification-toast-close" onclick="closeToast()">&times;</span>
        <div class="notification-toast-header">
            <span class="notification-toast-icon">🔔</span>
            <span id="toastTitle">New Notification</span>
        </div>
        <div class="notification-toast-body" id="toastBody"></div>
    </div>

    <audio id="notificationSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIGWm98OScTgwOUKvm8LZjHAY4ktfyzHksBSR3x/DdkEAKFF606euoVRQKRp/g8r5sIQUrgc7y2Yk2CBlpvfDknE4MDlCr5g==" type="audio/wav">
    </audio>

    <script>
        var currentNotificationId = null;
        var currentLinkUrl = null;
        var lastTime = <?= json_encode($latest_created) ?>;
        var totalCount = <?= $total_count ?>;
        var unreadCount = <?= $unread_count ?>;
        var todayCount = <?= $today_count ?>;
        var weekCount = <?= $week_count ?>;
        var todayStart = new Date();
        todayStart.setHours(0, 0, 0, 0);
        var weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
        var updatesContainer = document.getElementById('updatesContainer');

        console.log('Script loaded, lastTime:', lastTime);

        function escapeHtml(unsafe) {
            return String(unsafe).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function formatRelativeTime(dateStr) {
            var date = new Date(dateStr);
            var now = new Date();
            var diffMs = now - date;
            var diffMins = Math.floor(diffMs / 60000);
            var diffHours = Math.floor(diffMs / 3600000);
            var diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return diffMins + ' minute' + (diffMins !== 1 ? 's' : '') + ' ago';
            if (diffHours < 24) return diffHours + ' hour' + (diffHours !== 1 ? 's' : '') + ' ago';
            if (diffDays < 7) return diffDays + ' day' + (diffDays !== 1 ? 's' : '') + ' ago';
            
            return date.toLocaleDateString();
        }

        function createNotificationCard(notif) {
            var card = document.createElement('div');
            card.className = 'notification-card ' + (notif.is_read ? '' : 'unread ') + escapeHtml(notif.notification_type);
            card.dataset.created = notif.created_at;
            card.onclick = function() { openNotificationModal(notif); };

            var inner = '';
            if (!notif.is_read) {
                inner += '<span class="unread-badge">NEW</span>';
            }
            inner += '<div class="notification-header">' + escapeHtml(notif.title) + '</div>';
            inner += '<div class="notification-message">' + escapeHtml(notif.message) + '</div>';
            inner += '<div class="notification-time">' + formatRelativeTime(notif.created_at) + '</div>';
            
            card.innerHTML = inner;
            return card;
        }

        function checkForNewNotifications() {
            console.log('Checking for new notifications...');
            var formData = new FormData();
            formData.append('action', 'fetch_new');
            formData.append('last_time', lastTime);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                console.log('Response:', data);
                if (data.success && data.new_notifs.length > 0) {
                    console.log('Found ' + data.new_notifs.length + ' new notifications');
                    lastTime = data.new_notifs[0].created_at;

                    var newUnread = 0;
                    var newToday = 0;
                    var newWeek = 0;
                    var hasNewTask = false;
                    var firstTask = null;
                    
                    data.new_notifs.forEach(function(notif) {
                        if (!notif.is_read) newUnread++;
                        if (new Date(notif.created_at) >= todayStart) newToday++;
                        if (new Date(notif.created_at) >= weekAgo) newWeek++;
                        
                        if (notif.notification_type === 'requirement_review' && !firstTask) {
                            hasNewTask = true;
                            firstTask = notif;
                        }
                    });

                    totalCount += data.new_notifs.length;
                    unreadCount += newUnread;
                    todayCount += newToday;
                    weekCount += newWeek;

                    var noUpdates = document.querySelector('.no-updates');
                    if (noUpdates) noUpdates.remove();

                    data.new_notifs.reverse().forEach(function(notif) {
                        updatesContainer.insertBefore(createNotificationCard(notif), updatesContainer.firstChild);
                    });

                    if (hasNewTask && firstTask) {
                        console.log('Showing toast for requirement review');
                        showToastNotification(firstTask.title, firstTask.message);
                        playNotificationSound();
                        showDesktopNotification(firstTask.title, firstTask.message);
                    }
                }
            })
            .catch(function(e) { console.error('Error:', e); });
        }

        setInterval(checkForNewNotifications, 10000);

        function showToastNotification(title, message) {
            console.log('showToastNotification called:', title);
            var toast = document.getElementById('notificationToast');
            document.getElementById('toastTitle').textContent = title;
            document.getElementById('toastBody').textContent = message;
            
            toast.classList.add('show');
            
            setTimeout(function() {
                closeToast();
            }, 5000);
        }

        function closeToast() {
            document.getElementById('notificationToast').classList.remove('show');
        }

        function playNotificationSound() {
            var audio = document.getElementById('notificationSound');
            audio.play().catch(function(e) {
                console.log('Could not play sound:', e);
            });
        }

        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }

        function showDesktopNotification(title, message) {
            if ("Notification" in window && Notification.permission === "granted") {
                new Notification(title, {
                    body: message
                });
            }
        }

        function openNotificationModal(notif) {
            currentNotificationId = notif.notification_id;
            currentLinkUrl = notif.link_url;
            
            document.getElementById('modalTitle').textContent = notif.title;
            document.getElementById('modalMessage').textContent = notif.message;
            document.getElementById('modalDate').textContent = new Date(notif.created_at).toLocaleString();
            document.getElementById('modalType').textContent = notif.notification_type.replace(/_/g, ' ').toUpperCase();
            document.getElementById('modalStatus').textContent = notif.is_read ? 'Read' : 'Unread';
            
            var markReadBtn = document.getElementById('markReadBtn');
            var goToTaskBtn = document.getElementById('goToTaskBtn');
            
            markReadBtn.style.display = notif.is_read ? 'none' : 'block';
            
            if (notif.link_url) {
                goToTaskBtn.style.display = 'block';
                goToTaskBtn.onclick = function() { window.location.href = notif.link_url; };
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
            
            var formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('notification_id', currentNotificationId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Marked as Read',
                        timer: 1000,
                        showConfirmButton: false
                    }).then(function() { location.reload(); });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(function(e) { Swal.fire('Error', e.message, 'error'); });
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
            }).then(function(result) {
                if (result.isConfirmed) {
                    var formData = new FormData();
                    formData.append('action', 'dismiss');
                    formData.append('notification_id', currentNotificationId);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Dismissed',
                                timer: 1000,
                                showConfirmButton: false
                            }).then(function() { location.reload(); });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(function(e) { Swal.fire('Error', e.message, 'error'); });
                }
            });
        }

        window.onclick = function(e) {
            var modal = document.getElementById('notificationModal');
            if (e.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>