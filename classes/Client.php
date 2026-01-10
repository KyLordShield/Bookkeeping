<?php

require_once __DIR__ . '/../config/Database.php';

class Client {

    private static function db() {
        return Database::getInstance()->getConnection();
    }

    public static function getAll() {
        $stmt = self::db()->query("SELECT * FROM clients ORDER BY last_name, first_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findById($id) {
        $stmt = self::db()->prepare("SELECT * FROM clients WHERE client_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array $data) {
        $sql = "INSERT INTO clients (first_name, last_name, email, phone, company_name, business_type, account_status, registration_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = self::db()->prepare($sql);
        $stmt->execute([
            $data['first_name'], $data['last_name'], $data['email'], $data['phone'] ?? null,
            $data['company_name'] ?? null, $data['business_type'] ?? null,
            $data['account_status'] ?? 'pending', $data['registration_date']
        ]);
        return self::db()->lastInsertId();
    }

    public static function update($id, array $data) {
        $sql = "UPDATE clients SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                company_name = ?, business_type = ?, account_status = ? 
                WHERE client_id = ?";
        $stmt = self::db()->prepare($sql);
        return $stmt->execute([
            $data['first_name'], $data['last_name'], $data['email'], $data['phone'] ?? null,
            $data['company_name'] ?? null, $data['business_type'] ?? null,
            $data['account_status'] ?? 'pending', $id
        ]);
    }

    // === THE ONLY assignService method (with optional parameters) ===
    public static function assignService($client_id, $service_id, $created_by, $request_id = null, $initial_status = 'pending') {
        $stmt = self::db()->prepare("
            INSERT INTO client_services 
            (client_id, service_id, request_id, overall_status, start_date, created_by) 
            VALUES (?, ?, ?, ?, CURDATE(), ?)
        ");
        return $stmt->execute([$client_id, $service_id, $request_id, $initial_status, $created_by]);
    }

    public static function getClientServices($client_id) {
        $stmt = self::db()->prepare("
            SELECT cs.*, s.service_name 
            FROM client_services cs
            JOIN services s ON cs.service_id = s.service_id
            WHERE cs.client_id = ?
            ORDER BY cs.start_date DESC
        ");
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // NEW: Count requirements for a specific client_service
    public static function countRequirements($client_service_id) {
        $stmt = self::db()->prepare("
            SELECT COUNT(*) 
            FROM client_service_requirements 
            WHERE client_service_id = ?
        ");
        $stmt->execute([$client_service_id]);
        return (int) $stmt->fetchColumn();
    }

    // Email & Phone uniqueness
    public static function emailExists($email, $excludeClientId = 0) {
        $stmt = self::db()->prepare("SELECT COUNT(*) FROM clients WHERE email = ? AND client_id != ?");
        $stmt->execute([$email, $excludeClientId]);
        return $stmt->fetchColumn() > 0;
    }

    public static function phoneExists($phone, $excludeClientId = 0) {
        if (empty($phone)) return false;
        $stmt = self::db()->prepare("SELECT COUNT(*) FROM clients WHERE phone = ? AND client_id != ?");
        $stmt->execute([$phone, $excludeClientId]);
        return $stmt->fetchColumn() > 0;
    }

    public static function deleteAllServices($client_id) {
        $stmt = self::db()->prepare("DELETE FROM client_services WHERE client_id = ?");
        return $stmt->execute([$client_id]);
    }

    public static function setAllServicesOnHold($client_id) {
        $stmt = self::db()->prepare("UPDATE client_services SET overall_status = 'on_hold' WHERE client_id = ?");
        return $stmt->execute([$client_id]);
    }



/**
 * Get upcoming appointments + approved service requests for this specific client
 * (mirrors admin logic but client-specific)
 */
public function getUpcomingEventsForClient(int $clientId, int $limit = 8): array
{
    $stmt = $this->pdo->prepare("
        -- Client's confirmed/scheduled appointments
        SELECT 
            'appointment' AS type,
            a.appointment_id AS id,
            a.appointment_date AS event_date,
            a.appointment_time AS event_time,
            COALESCE(se.service_name, a.appointment_type, 'Consultation') AS title,
            a.appointment_type
        FROM appointments a
        LEFT JOIN services se ON a.service_id = se.service_id
        WHERE a.client_id = :client_id
          AND a.appointment_date >= CURDATE()
          AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
          AND a.status IN ('scheduled', 'confirmed')

        UNION ALL

        -- Client's APPROVED service requests with preferred date/time
        SELECT 
            'request' AS type,
            sr.request_id AS id,
            sr.preferred_date AS event_date,
            sr.preferred_time AS event_time,
            se.service_name AS title,
            NULL AS appointment_type
        FROM service_requests sr
        JOIN services se ON sr.service_id = se.service_id
        WHERE sr.client_id = :client_id
          AND sr.preferred_date >= CURDATE()
          AND sr.preferred_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
          AND sr.request_status = 'approved'
          AND sr.preferred_date IS NOT NULL

        ORDER BY event_date ASC, event_time ASC
        LIMIT :limit
    ");

    $stmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get upcoming approved service requests with preferred date/time
 * Only for this specific client
 */


public static function getUpcomingApprovedRequests($clientId, $limit = 8): array {
    $stmt = self::db()->prepare("
        SELECT 
            sr.request_id AS id,
            sr.preferred_date AS event_date,
            sr.preferred_time AS event_time,
            se.service_name AS title
        FROM service_requests sr
        JOIN services se ON sr.service_id = se.service_id
        WHERE sr.client_id = ?
          AND sr.preferred_date >= CURDATE()
          AND sr.preferred_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
          AND sr.request_status = 'approved'
          AND sr.preferred_date IS NOT NULL
        ORDER BY sr.preferred_date ASC, sr.preferred_time ASC
        LIMIT ?
    ");

    $stmt->execute([$clientId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
  
}