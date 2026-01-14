<?php
// classes/Dashboard.php - Final simplified version: Recent activities based on requirement statuses & timestamps (no logs)

require_once __DIR__ . '/../config/Database.php';

class Dashboard
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

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

    public function getUrgentActions(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM client_service_requirements 
            WHERE status = 'approval_pending'
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function getActiveStaff(): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM staff");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Recent activities – Based on current statuses + timestamps in client_service_requirements
     * - approval_pending → new submission awaiting approval (use started_at or fallback to client_services.created_at)
     * - in_progress → processing started
     * - completed → recently completed
     * - rejected/on_hold → optional (added for completeness)
     * Staff name from assigned_staff_id (submitter/processor) or completed_by
     */
    public function getRecentActivities(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare("
            -- 1. New submissions awaiting approval
            SELECT 
                COALESCE(csr.started_at, cs.created_at) AS timestamp,
                CONCAT(
                    COALESCE(CONCAT(s.first_name, ' ', s.last_name, ' submitted '), 'A staff submitted '),
                    COALESCE(csr.requirement_name, 'a requirement'),
                    ' for approval for client ', c.first_name, ' ', c.last_name
                ) AS description
            FROM client_service_requirements csr
            JOIN client_services cs ON csr.client_service_id = cs.client_service_id
            JOIN clients c ON cs.client_id = c.client_id
            LEFT JOIN staff s ON csr.assigned_staff_id = s.staff_id
            WHERE csr.status = 'approval_pending'

            UNION ALL

            -- 2. Requirements in progress / approved & being processed
            SELECT 
                csr.started_at AS timestamp,
                CONCAT(
                    COALESCE(CONCAT(s.first_name, ' ', s.last_name, ' started processing '), 'Processing started for '),
                    COALESCE(csr.requirement_name, 'a requirement'),
                    ' for client ', c.first_name, ' ', c.last_name
                ) AS description
            FROM client_service_requirements csr
            JOIN client_services cs ON csr.client_service_id = cs.client_service_id
            JOIN clients c ON cs.client_id = c.client_id
            LEFT JOIN staff s ON csr.assigned_staff_id = s.staff_id
            WHERE csr.status = 'in_progress'
              AND csr.started_at IS NOT NULL

            UNION ALL

            -- 3. Completed requirements
            SELECT 
                csr.completed_at AS timestamp,
                CONCAT(
                    COALESCE(CONCAT(s.first_name, ' ', s.last_name, ' completed '), 'Completed '),
                    COALESCE(csr.requirement_name, 'a requirement'),
                    ' for client ', c.first_name, ' ', c.last_name
                ) AS description
            FROM client_service_requirements csr
            JOIN client_services cs ON csr.client_service_id = cs.client_service_id
            JOIN clients c ON cs.client_id = c.client_id
            LEFT JOIN staff s ON csr.completed_by = s.staff_id
            WHERE csr.status = 'completed'
              AND csr.completed_at IS NOT NULL

            UNION ALL

            -- 4. Rejected requirements (optional – remove if not needed)
            SELECT 
                COALESCE(csr.completed_at, csr.started_at) AS timestamp,
                CONCAT(
                    COALESCE(CONCAT(s.first_name, ' ', s.last_name, ' rejected '), 'Rejected '),
                    COALESCE(csr.requirement_name, 'a requirement'),
                    ' for client ', c.first_name, ' ', c.last_name
                ) AS description
            FROM client_service_requirements csr
            JOIN client_services cs ON csr.client_service_id = cs.client_service_id
            JOIN clients c ON cs.client_id = c.client_id
            LEFT JOIN staff s ON csr.assigned_staff_id = s.staff_id
            WHERE csr.status = 'rejected'

            UNION ALL

            -- 5. On hold requirements (optional)
            SELECT 
                COALESCE(csr.started_at, cs.created_at) AS timestamp,
                CONCAT(
                    COALESCE(CONCAT(s.first_name, ' ', s.last_name, ' put on hold '), 'On hold: '),
                    COALESCE(csr.requirement_name, 'a requirement'),
                    ' for client ', c.first_name, ' ', c.last_name
                ) AS description
            FROM client_service_requirements csr
            JOIN client_services cs ON csr.client_service_id = cs.client_service_id
            JOIN clients c ON cs.client_id = c.client_id
            LEFT JOIN staff s ON csr.assigned_staff_id = s.staff_id
            WHERE csr.status = 'on_hold'

            UNION ALL

            -- 6. Service requests
            SELECT 
                sr.requested_at AS timestamp,
                CONCAT(
                    'New service request: ', se.service_name,
                    ' from client ', c.first_name, ' ', c.last_name,
                    ' (status: ', sr.request_status, ')'
                ) AS description
            FROM service_requests sr
            JOIN services se ON sr.service_id = se.service_id
            JOIN clients c ON sr.client_id = c.client_id

            UNION ALL

            -- 7. Notes
            SELECT 
                n.created_at AS timestamp,
                CONCAT(
                    COALESCE(CONCAT(s.first_name, ' ', s.last_name, ' added note: '), 'Note added: '),
                    n.title,
                    COALESCE(CONCAT(' for client ', c.first_name, ' ', c.last_name), '')
                ) AS description
            FROM notes n
            LEFT JOIN clients c ON n.related_client_id = c.client_id
            LEFT JOIN staff s ON n.created_by = s.staff_id

            ORDER BY timestamp DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUpcomingMeetingsAndRequests(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            -- Appointments
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

            -- Approved service requests
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