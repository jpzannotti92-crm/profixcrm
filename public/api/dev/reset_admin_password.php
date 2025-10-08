<?php
// Dev-only endpoint to reset the admin password
// WARNING: Keep this only in local/dev environments.

// Minimal .env loader (no Composer dependency required)
$envFile = __DIR__ . '/../../../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(ltrim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Parse input JSON { username?: string = 'admin', new_password: string }
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

$username = isset($input['username']) && $input['username'] !== '' ? trim($input['username']) : 'admin';
$newPassword = isset($input['new_password']) ? (string)$input['new_password'] : '';

if ($newPassword === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'new_password requerido']);
    exit();
}

// Connect to DB using PDO with .env
$host = $_ENV['DB_HOST'] ?? 'localhost';
// Fallback actualizado: spin2pay_profixcrm
$db   = $_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? 'spin2pay_profixcrm');
$user = $_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? 'root');
$pass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure column name compatibility
    $passwordField = 'password_hash';
    try {
        $colStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
        $hasHash = $colStmt && $colStmt->fetch();
        if (!$hasHash) {
            $colStmt2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'password'");
            $hasPassword = $colStmt2 && $colStmt2->fetch();
            $passwordField = $hasPassword ? 'password' : 'password_hash';
        }
    } catch (Exception $e) { /* keep default */ }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET {$passwordField} = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Usuario '{$username}' no encontrado"]);
        exit();
    }

    echo json_encode(['success' => true, 'message' => "Contraseña actualizada para {$username}"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>