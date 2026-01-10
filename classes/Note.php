<?php
// classes/Note.php

require_once __DIR__ . '/../config/Database.php';

class Note
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Create a new note
     *
     * @param array $data Associative array with note fields
     * @return int The ID of the newly created note
     * @throws Exception
     */
    public function create(array $data): int
    {
        $required = ['created_by', 'title', 'content'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        $sql = "
            INSERT INTO notes 
            (created_by, note_type, title, content, related_client_id, 
             related_service_id, priority, due_date, is_completed, created_at)
            VALUES 
            (:created_by, :note_type, :title, :content, :related_client_id,
             :related_service_id, :priority, :due_date, :is_completed, NOW())
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':created_by'        => $data['created_by'],
            ':note_type'         => $data['note_type'] ?? 'general',
            ':title'             => trim($data['title']),
            ':content'           => trim($data['content']),
            ':related_client_id' => $data['related_client_id'] ?? null,
            ':related_service_id'=> $data['related_service_id'] ?? null,
            ':priority'          => $data['priority'] ?? 'normal',
            ':due_date'          => $data['due_date'] ?? null,
            ':is_completed'      => $data['is_completed'] ?? false
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get a single note by ID
     *
     * @param int $noteId
     * @return array|null
     */
    public function getById(int $noteId): ?array
    {
        $sql = "
            SELECT 
                n.*,
                CONCAT(s.first_name, ' ', s.last_name) AS created_by_name,
                c.first_name AS client_first_name,
                c.last_name AS client_last_name
            FROM notes n
            LEFT JOIN staff s ON n.created_by = s.staff_id
            LEFT JOIN clients c ON n.related_client_id = c.client_id
            WHERE n.note_id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $noteId]);

        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Get all notes with optional filtering
     *
     * @param array $filters Optional filters: 'priority', 'note_type', 'is_completed', 'created_by'
     * @param string $orderBy Default: priority + date
     * @param int $limit
     * @return array
     */
    public function getAll(array $filters = [], string $orderBy = 'priority', int $limit = 100): array
    {
        $sql = "
            SELECT 
                n.note_id, n.title, n.content, n.note_type, n.priority,
                n.due_date, n.is_completed, n.created_at,
                CONCAT(s.first_name, ' ', s.last_name) AS created_by_name,
                c.first_name AS client_first, c.last_name AS client_last
            FROM notes n
            LEFT JOIN staff s ON n.created_by = s.staff_id
            LEFT JOIN clients c ON n.related_client_id = c.client_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['priority'])) {
            $sql .= " AND n.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }

        if (!empty($filters['note_type'])) {
            $sql .= " AND n.note_type = :note_type";
            $params[':note_type'] = $filters['note_type'];
        }

        if (isset($filters['is_completed'])) {
            $sql .= " AND n.is_completed = :completed";
            $params[':completed'] = (int) $filters['is_completed'];
        }

        if (!empty($filters['created_by'])) {
            $sql .= " AND n.created_by = :created_by";
            $params[':created_by'] = $filters['created_by'];
        }

        // Ordering
        if ($orderBy === 'priority') {
            $sql .= " ORDER BY 
                CASE n.priority 
                    WHEN 'urgent' THEN 1
                    WHEN 'important' THEN 2
                    WHEN 'normal' THEN 3
                END,
                n.created_at DESC";
        } else {
            $sql .= " ORDER BY n.created_at DESC";
        }

        if ($limit > 0) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === ':limit') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Update an existing note
     *
     * @param int $noteId
     * @param array $data Fields to update
     * @return bool Success
     */
    public function update(int $noteId, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $allowedFields = [
            'title', 'content', 'note_type', 'priority',
            'related_client_id', 'related_service_id', 'due_date',
            'is_completed'
        ];

        $setParts = [];
        $params = [':note_id' => $noteId];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $setParts[] = "$field = :$field";
                $params[":$field"] = $value;
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $sql = "UPDATE notes SET " . implode(', ', $setParts) . " WHERE note_id = :note_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Mark note as completed / uncompleted
     */
    public function toggleCompleted(int $noteId, bool $isCompleted): bool
    {
        $sql = "UPDATE notes SET is_completed = :status WHERE note_id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':status' => $isCompleted,
            ':id' => $noteId
        ]);
    }

    /**
     * Delete a note
     *
     * @param int $noteId
     * @return bool
     */
    public function delete(int $noteId): bool
    {
        $sql = "DELETE FROM notes WHERE note_id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $noteId]);
    }
}