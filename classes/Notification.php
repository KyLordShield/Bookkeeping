<?php

class Notification {

    /**
     * Creates a notification when a staff member is assigned to a task step.
     *
     * @param PDO $pdo Database connection
     * @param int $user_id The user_id of the staff member (from users table)
     * @param int $cs_id client_service_id
     * @param int $req_id requirement_id (the specific step)
     * @param int $created_by The admin/staff who made the assignment (for auditing)
     * @return bool Success or failure
     */
    public static function createAssignmentNotification($pdo, $user_id, $cs_id, $req_id, $created_by) {
        if (!$pdo instanceof PDO) {
            error_log("Notification: Invalid PDO object provided");
            return false;
        }

        if (empty($user_id)) {
            error_log("Notification: No user_id provided for assignment notification");
            return false;
        }

        $title   = "New Task Assigned";
        $message = "You have been assigned to step #{$req_id} in client service #{$cs_id}.";

        // Relative path to staff's notification/update page
        // Adjust query parameters to match how staff_updates.php handles display/filtering
        $link = "../staff_pages/staff_updates.php?tab=notifications&req_id={$req_id}&cs_id={$cs_id}";

        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, notification_type, title, message, link_url, created_at)
                VALUES (?, 'task_assignment', ?, ?, ?, NOW())
            ");

            $success = $stmt->execute([$user_id, $title, $message, $link]);

            if (!$success) {
                error_log("Notification insert failed: " . implode(", ", $stmt->errorInfo()));
            }

            return $success;
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }
}