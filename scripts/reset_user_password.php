<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database/Connection.php';

use Dotenv\Dotenv;
use iaTradeCRM\Database\Connection;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if ($argc < 3) {
    fwrite(STDERR, "Uso: php scripts/reset_user_password.php <username> <new_password>\n");
    exit(1);
}

$username = $argv[1];
$newPassword = $argv[2];

try {
    $db = Connection::getInstance()->getConnection();

    // Detectar columna existente
    $passwordField = 'password_hash';
    try {
        $colStmt = $db->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
        $hasHash = $colStmt && $colStmt->fetch();
        if (!$hasHash) {
            $colStmt2 = $db->query("SHOW COLUMNS FROM users LIKE 'password'");
            $hasPassword = $colStmt2 && $colStmt2->fetch();
            $passwordField = $hasPassword ? 'password' : 'password_hash';
        }
    } catch (Exception $e) {
        $passwordField = 'password_hash';
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET {$passwordField} = ? WHERE username = ?");
    $result = $stmt->execute([$hash, $username]);

    if (!$result) {
        throw new RuntimeException('No se pudo actualizar la contraseña');
    }

    // Verificar actualización
    $verifyStmt = $db->prepare("SELECT id, {$passwordField} AS pwd FROM users WHERE username = ?");
    $verifyStmt->execute([$username]);
    $row = $verifyStmt->fetch();
    if (!$row) {
        throw new RuntimeException('Usuario no encontrado tras actualización');
    }

    $ok = password_verify($newPassword, $row['pwd']);
    fwrite(STDOUT, $ok ? "✓ Contraseña actualizada y verificada\n" : "✗ Falló verificación de contraseña\n");
    exit($ok ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}