<?php

require_once __DIR__ . '/../config/Database.php';

class Service {

    private static function db() {
        return Database::getInstance()->getConnection();
    }

    public static function getAllActive() {
        $stmt = self::db()->query("SELECT * FROM services WHERE is_active = 1 ORDER BY service_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findById($id) {
        $stmt = self::db()->prepare("SELECT * FROM services WHERE service_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}