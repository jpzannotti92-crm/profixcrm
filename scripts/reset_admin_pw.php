<?php
// CLI script: reset admin password using DB connection
require_once __DIR__ . '/../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

$username = $argc >= 2 ? $argv[1] : 'admin';
$newPassword = $argc >= 3 ? $argv[2] : 'admin12345!';

try {
    $db = Connection::getInstance()->getConnection();

    // Detect password column
    $passwordField = 'password_hash';
    try {
        $colStmt = $db->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
        $hasHash = $colStmt && $colStmt->fetch();
        if (!$hasHash) {
            $colStmt2 = $db->query("SHOW COLUMNS FROM users LIKE 'password'");
            $hasPassword = $colStmt2 && $colStmt2->fetch();
            $passwordField = $hasPassword ? 'password' : 'password_hash';
        }
    } catch (Exception $e) { /* keep default */ }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET {$passwordField} = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);

    if ($stmt->rowCount() === 0) {
        fwrite(STDERR, "Usuario '{$username}' no encontrado\n");
        exit(1);
    }

    echo "Contraseña actualizada para {$username}\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
?>