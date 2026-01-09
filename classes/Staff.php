<?php

require_once __DIR__ . '/../config/Database.php';

class Staff
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Get all staff members with workload stats
     */
    public function getAllStaffWithStats()
    {
        $query = "
            SELECT 
                s.staff_id,
                s.first_name,
                s.last_name,
                s.email,
                s.phone,
                s.position,
                COALESCE(active.active_count, 0) AS active_tasks_count,
                COALESCE(completed.count_completed, 0) AS completed_tasks_count
            FROM staff s
            LEFT JOIN (
                SELECT assigned_staff_id, COUNT(*) AS active_count
                FROM client_service_requirements
                WHERE status IN ('pending', 'in_progress', 'on_hold')
                GROUP BY assigned_staff_id
            ) active ON active.assigned_staff_id = s.staff_id
            LEFT JOIN (
                SELECT assigned_staff_id, COUNT(*) AS count_completed
                FROM client_service_requirements
                WHERE status = 'completed'
                GROUP BY assigned_staff_id
            ) completed ON completed.assigned_staff_id = s.staff_id
            ORDER BY s.first_name, s.last_name
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get staff by ID
     */
    public function getStaffById($staff_id)
    {
        $query = "SELECT staff_id, first_name, last_name, email, phone, position 
                  FROM staff WHERE staff_id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(['id' => $staff_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get tasks (requirements) for a staff member
     */
    public function getTasksByStaffId($staff_id, $statusFilter = null)
    {
        $query = "
            SELECT 
                r.requirement_id AS task_id,
                r.requirement_name AS task_name,
                r.status,
                cs.deadline,
                r.started_at AS status_changed_at,
                r.requirement_order,
                c.first_name AS client_first_name,
                c.last_name AS client_last_name,
                c.email AS client_email,
                srv.service_name,
                r.requirement_name AS step_name,
                r.requirement_order AS step_order
            FROM client_service_requirements r
            JOIN client_services cs ON r.client_service_id = cs.client_service_id
            JOIN clients c ON cs.client_id = c.client_id
            JOIN services srv ON cs.service_id = srv.service_id
            WHERE r.assigned_staff_id = :staff_id
        ";

        $params = ['staff_id' => $staff_id];

        if ($statusFilter && in_array($statusFilter, ['pending', 'in_progress', 'completed', 'on_hold'])) {
            $query .= " AND r.status = :status";
            $params['status'] = $statusFilter;
        }

        $query .= " ORDER BY cs.deadline ASC, r.requirement_order ASC";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Workload helpers
     */
    public function getWorkloadLevel($activeTasks)
    {
        if ($activeTasks >= 10) return 'High';
        if ($activeTasks >= 5) return 'Medium';
        if ($activeTasks >= 1) return 'Low';
        return 'None';
    }

    public function getWorkloadClass($level)
    {
        return match(strtolower($level)) {
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
            default => 'none'
        };
    }

    // ==================== NEW METHODS FOR CRUD ====================

    /**
     * Add new staff member
     */
    public function addStaff($data)
    {
        $sql = "INSERT INTO staff (first_name, last_name, email, phone, position) 
                VALUES (:first_name, :last_name, :email, :phone, :position)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'] ?? null,
            ':position' => $data['position'] ?? null
        ]);
    }

    /**
     * Update existing staff
     */
    public function updateStaff($staff_id, $data)
    {
        $sql = "UPDATE staff SET 
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                position = :position
                WHERE staff_id = :staff_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'] ?? null,
            ':position' => $data['position'] ?? null,
            ':staff_id' => $staff_id
        ]);
    }

    /**
     * Delete staff (only if no assigned tasks)
     */
    public function deleteStaff($staff_id)
    {
        // Check if has assigned tasks
        $check = $this->pdo->prepare("SELECT COUNT(*) FROM client_service_requirements WHERE assigned_staff_id = :id");
        $check->execute([':id' => $staff_id]);
        if ($check->fetchColumn() > 0) {
            return false; // Cannot delete
        }

        $stmt = $this->pdo->prepare("DELETE FROM staff WHERE staff_id = :id");
        return $stmt->execute([':id' => $staff_id]);
    }

    /**
     * Check if staff has assigned tasks
     */
    public function hasAssignedTasks($staff_id)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM client_service_requirements WHERE assigned_staff_id = :id");
        $stmt->execute([':id' => $staff_id]);
        return $stmt->fetchColumn() > 0;
    }
}