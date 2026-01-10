<?php
// classes/Dashboard.php

require_once __DIR__ . '/../config/Database.php';

class Dashboard
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Get active clients count and new this week
     *
     * @return array ['total' => int, 'new_this_week' => int]
     */
    public function getActiveClients(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM clients 
            WHERE account_status = 'active'
        ");
        $stmt->execute();
        $total = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM clients 
            WHERE account_status = 'active' 
            AND registration_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $newThisWeek = (int) $stmt->fetchColumn();

        return ['total' => $total, 'new_this_week' => $newThisWeek];
    }

    /**
     * Get pending approvals (service requests)
     *
     * @return int
     */
    public function getPendingApprovals(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM service_requests 
            WHERE request_status = 'pending'
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get urgent actions count (urgent notes + on-hold services)
     *
     * @return int
     */
    public function getUrgentActions(): int
    {
        $urgent = 0;

        // Urgent incomplete notes
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM notes 
            WHERE priority = 'urgent' 
            AND is_completed = 0
        ");
        $stmt->execute();
        $urgent += (int) $stmt->fetchColumn();

        // On-hold client services
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM client_services 
            WHERE overall_status = 'on_hold'
        ");
        $stmt->execute();
        $urgent += (int) $stmt->fetchColumn();

        return $urgent;
    }

    /**
     * Get active staff count
     *
     * @return int
     */
    public function getActiveStaff(): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM staff");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get recent activities (fallback to notes/requests if activity_log empty)
     *
     * @param int $limit
     * @return array
     */
    public function getRecentActivities(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                al.timestamp,
                al.description,
                CONCAT(c.first_name, ' ', c.last_name) AS client_name,
                CONCAT(s.first_name, ' ', s.last_name) AS staff_name,
                al.activity_type
            FROM activity_log al
            LEFT JOIN clients c ON al.related_entity_type = 'client' AND al.related_entity_id = c.client_id
            LEFT JOIN staff s ON al.user_id IN (SELECT user_id FROM users WHERE staff_id = s.staff_id)
            ORDER BY al.timestamp DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($activities)) {
            $stmt = $this->pdo->prepare("
                SELECT 
                    n.created_at AS timestamp,
                    CONCAT('Note: ', n.title) AS description,
                    CONCAT(c.first_name, ' ', c.last_name) AS client_name,
                    CONCAT(s.first_name, ' ', s.last_name) AS staff_name,
                    'note' AS activity_type
                FROM notes n
                LEFT JOIN clients c ON n.related_client_id = c.client_id
                LEFT JOIN staff s ON n.created_by = s.staff_id
                
                UNION ALL
                
                SELECT 
                    sr.requested_at AS timestamp,
                    CONCAT('Requested service: ', se.service_name) AS description,
                    CONCAT(c.first_name, ' ', c.last_name) AS client_name,
                    NULL AS staff_name,
                    'request' AS activity_type
                FROM service_requests sr
                JOIN services se ON sr.service_id = se.service_id
                JOIN clients c ON sr.client_id = c.client_id
                
                ORDER BY timestamp DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $activities;
    }

    /**
     * Get upcoming appointments (next 7 days)
     *
     * @param int $limit
     * @return array
     */
    public function getUpcomingAppointments(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.appointment_date,
                a.appointment_time,
                CONCAT(c.first_name, ' ', c.last_name) AS client_name,
                a.appointment_type,
                se.service_name
            FROM appointments a
            JOIN clients c ON a.client_id = c.client_id
            LEFT JOIN services se ON a.service_id = se.service_id
            WHERE a.appointment_date >= CURDATE()
              AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              AND a.status IN ('scheduled', 'confirmed')
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



   /**
 * Get upcoming meetings AND pending service requests with preferred meeting time
 * Combined view for admin dashboard
 *
 * @param int $limit
 * @return array
 */
public function getUpcomingMeetingsAndRequests(int $limit = 10): array
{
    $stmt = $this->pdo->prepare("
        -- Confirmed / Scheduled Appointments
        SELECT 
            'appointment' AS type,
            a.appointment_id AS id,
            a.appointment_date AS event_date,
            a.appointment_time AS event_time,
            CONCAT(c.first_name, ' ', c.last_name) AS client_name,
            COALESCE(se.service_name, a.appointment_type, 'Consultation') AS title,
            a.status,
            a.meeting_link
        FROM appointments a
        JOIN clients c ON a.client_id = c.client_id
        LEFT JOIN services se ON a.service_id = se.service_id
        WHERE a.appointment_date >= CURDATE()
          AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
          AND a.status IN ('scheduled', 'confirmed')

        UNION ALL

        -- Only APPROVED Service Requests with preferred meeting time
        SELECT 
            'request' AS type,
            sr.request_id AS id,
            sr.preferred_date AS event_date,
            sr.preferred_time AS event_time,
            CONCAT(c.first_name, ' ', c.last_name) AS client_name,
            se.service_name AS title,
            sr.request_status AS status,
            NULL AS meeting_link
        FROM service_requests sr
        JOIN clients c ON sr.client_id = c.client_id
        JOIN services se ON sr.service_id = se.service_id
        WHERE sr.preferred_date >= CURDATE()
          AND sr.preferred_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
          AND sr.request_status = 'approved'
          AND sr.preferred_date IS NOT NULL

        ORDER BY event_date ASC, event_time ASC
        LIMIT :limit
    ");

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}