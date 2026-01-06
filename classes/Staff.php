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
     * Get all staff members with their current workload stats
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
            SELECT 
                assigned_to,
                COUNT(*) AS active_count
            FROM tasks
            WHERE status IN ('pending', 'in_progress', 'waiting_for_approval', 'urgent', 'on_hold')
            GROUP BY assigned_to
        ) active ON active.assigned_to = s.staff_id
        LEFT JOIN (
            SELECT 
                assigned_to,
                COUNT(*) AS count_completed
            FROM tasks
            WHERE status = 'completed'
            GROUP BY assigned_to
        ) completed ON completed.assigned_to = s.staff_id
        ORDER BY s.first_name, s.last_name
    ";

    $stmt = $this->pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
}

    /**
     * Determine workload level based on active tasks
     */
    public function getWorkloadLevel($activeTasks)
    {
        if ($activeTasks >= 10) return 'High';
        if ($activeTasks >= 5) return 'Medium';
        if ($activeTasks >= 1) return 'Low';
        return 'None';
    }

    /**
     * Get color class for workload badge
     */
    public function getWorkloadClass($level)
    {
        return match(strtolower($level)) {
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
            default => 'none'
        };
    }

    /**
     * Get all tasks for a specific staff member with client and service details
     */
    public function getTasksByStaffId($staff_id, $statusFilter = null)
    {
        $query = "
            SELECT 
                t.task_id,
                t.task_name,
                t.status,
                t.deadline,
                t.status_changed_at,
                c.first_name AS client_first_name,
                c.last_name AS client_last_name,
                c.email AS client_email,
                srv.service_name,
                ss.step_name,
                ss.step_order
            FROM tasks t
            JOIN client_services cs ON t.client_service_id = cs.client_service_id
            JOIN clients c ON cs.client_id = c.client_id
            JOIN services srv ON cs.service_id = srv.service_id
            LEFT JOIN service_steps ss ON t.step_id = ss.step_id
            WHERE t.assigned_to = :staff_id
        ";

        $params = ['staff_id' => $staff_id];

        if ($statusFilter && in_array($statusFilter, ['pending', 'in_progress', 'completed', 'on_hold', 'waiting_for_approval', 'urgent'])) {
            $query .= " AND t.status = :status";
            $params['status'] = $statusFilter;
        }

        $query .= " ORDER BY t.deadline ASC, ss.step_order ASC";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get staff by ID (for modal header)
     */
    public function getStaffById($staff_id)
    {
        $query = "SELECT staff_id, first_name, last_name, email, phone FROM staff WHERE staff_id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(['id' => $staff_id]);
        return $stmt->fetch();
    }
}