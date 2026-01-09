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
            $data['account_status'], $data['registration_date']
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
            $data['account_status'], $id
        ]);
    }

    public static function assignService($client_id, $service_id, $created_by) {
        $sql = "INSERT INTO client_services 
                (client_id, service_id, request_id, overall_status, start_date, created_by) 
                VALUES (?, ?, NULL, 'pending', CURDATE(), ?)";
        $stmt = self::db()->prepare($sql);
        return $stmt->execute([$client_id, $service_id, $created_by]);
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
}