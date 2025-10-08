<?php
// Cargar .env manualmente
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(ltrim($line), '#') === 0) { continue; }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
}

// Obtener token actual
$tokenFile = 'current_token.txt';
if (!file_exists($tokenFile)) {
    echo "No se encontró el archivo de token.\n";
    exit(1);
}

$token = trim(file_get_contents($tokenFile));
echo "Token actual: " . substr($token, 0, 50) . "...\n\n";

// Simular headers de autenticación y método GET
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
$_SERVER['REQUEST_METHOD'] = 'GET';

// Incluir el archivo users.php
ob_start();
require_once 'public/api/users.php';
$response = ob_get_clean();

echo "=== RESPUESTA COMPLETA DEL API DE USUARIOS ===\n";
echo $response;
echo "\n\n";

// Decodificar la respuesta
$data = json_decode($response, true);
if ($data && isset($data['data'])) {
    echo "=== ANÁLISIS DE USUARIOS ===\n";
    if (isset($data['data']['users']) && is_array($data['data']['users'])) {
        foreach ($data['data']['users'] as $index => $user) {
            echo "Usuario " . ($index + 1) . ":\n";
            echo "  ID: " . ($user['id'] ?? 'N/A') . "\n";
            echo "  Username: " . ($user['username'] ?? 'N/A') . "\n";
            echo "  Email: " . ($user['email'] ?? 'N/A') . "\n";
            echo "  Role: " . ($user['role'] ?? 'N/A') . "\n";
            echo "  Role ID: " . ($user['role_id'] ?? 'N/A') . "\n";
            echo "  Role Name: " . ($user['role_name'] ?? 'N/A') . "\n";
            echo "  Role Display Name: " . ($user['role_display_name'] ?? 'N/A') . "\n";
            echo "  Roles (array): " . (isset($user['roles']) ? json_encode($user['roles']) : 'N/A') . "\n";
            echo "  User Roles: " . (isset($user['user_roles']) ? json_encode($user['user_roles']) : 'N/A') . "\n";
            echo "\n";
        }
    } else {
        echo "No se encontraron usuarios en la respuesta.\n";
    }
} else {
    echo "Error al decodificar la respuesta o no hay datos.\n";
    echo "Respuesta cruda: " . $response . "\n";
}