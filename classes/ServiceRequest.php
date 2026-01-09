<?php

require_once __DIR__ . '/../config/Database.php';

class ServiceRequest {

    private static function db() {
        return Database::getInstance()->getConnection();
    }

    public static function getAllPending() {
        $stmt = self::db()->query("
            SELECT * FROM service_requests 
            WHERE request_status = 'pending' 
            ORDER BY requested_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function accept($request_id, $staff_id) {
        $stmt = self::db()->prepare("
            UPDATE service_requests 
            SET request_status = 'approved', 
                approved_by = ?, 
                approved_at = NOW()
            WHERE request_id = ?
        ");
        return $stmt->execute([$staff_id, $request_id]);
    }

    public static function reject($request_id) {
        $stmt = self::db()->prepare("
            UPDATE service_requests 
            SET request_status = 'rejected'
            WHERE request_id = ?
        ");
        return $stmt->execute([$request_id]);
    }
}