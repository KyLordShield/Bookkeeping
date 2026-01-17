<?php
/**
 * hash_passwords.php
 * Converts plain text passwords into bcrypt hashes
 * Ignores already-hashed passwords
 */

require_once '../config/Database.php'; // Use your existing Database singleton

try {
    $pdo = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("DB Error: " . $e->getMessage());
}

// ==========================
// FETCH USERS
// ==========================
$stmt = $pdo->query("SELECT user_id, username, password_hash FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updatedUsers = [];

/**
 * Check if password is already hashed
 */
function isHashed($password) {
    return preg_match('/^\$2y\$|\$2a\$|\$argon2i\$|\$argon2id\$/', $password);
}

foreach ($users as $row) {
    $userId   = $row['user_id'];
    $username = $row['username'];
    $password = trim($row['password_hash']);

    // 🔴 Only hash if not hashed
    if (!isHashed($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $update = $pdo->prepare(
            "UPDATE users SET password_hash = :hash WHERE user_id = :id"
        );
        $update->execute([
            ':hash' => $hashed,
            ':id'   => $userId
        ]);

        $updatedUsers[] = [
            'user_id'  => $userId,
            'username' => $username,
            'old_pass' => $password
        ];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Hash Migration</title>
    <style>
        body { font-family: Arial; background: #f4f6f8; padding: 40px; }
        .box { background: #fff; padding: 25px; max-width: 750px; margin: auto; border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        .success { background: #e8f5e9; padding: 12px; border-left: 5px solid #2e7d32; margin-bottom: 15px; }
        .warn { background: #fff3cd; padding: 12px; border-left: 5px solid #ff9800; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; }
        th { background: #f1f1f1; }
    </style>
</head>
<body>

<div class="box">
    <h2>✅ Password Hashing Complete</h2>

    <div class="success">
        Plain-text passwords were successfully converted to secure hashes.
    </div>

    <div class="warn">
        ⚠ DELETE THIS FILE IMMEDIATELY AFTER USE.
    </div>

    <?php if (!empty($updatedUsers)): ?>
        <h3>Updated Users</h3>
        <table>
            <tr>
                <th>User ID</th>
                <th>Username</th>
            </tr>
            <?php foreach ($updatedUsers as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['user_id']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p><strong>No plain-text passwords found.</strong></p>
    <?php endif; ?>
</div>

</body>
</html>
