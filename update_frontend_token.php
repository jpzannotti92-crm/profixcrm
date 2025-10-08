<?php
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Usar la misma clave que está en .env
$secretKey = $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production-2024';
$issuedAt = time();
$expirationTime = $issuedAt + (60 * 60 * 24); // 24 horas para que dure más

// Crear payload del JWT para usuario admin
$payload = [
    'iss' => 'iatrade-crm',
    'aud' => 'iatrade-crm', 
    'iat' => $issuedAt,
    'exp' => $expirationTime,
    'user_id' => 1,
    'username' => 'admin',
    'email' => 'admin@example.com',
    'roles' => ['admin'],
    'permissions' => ['all']
];

// Generar token
$jwt = JWT::encode($payload, $secretKey, 'HS256');

echo "Token JWT válido generado:\n";
echo $jwt . "\n\n";

echo "Para actualizar en el navegador, ejecuta esto en la consola del navegador:\n";
echo "localStorage.setItem('auth_token', '" . $jwt . "');\n";
echo "localStorage.setItem('user', JSON.stringify({id: 1, username: 'admin', email: 'admin@example.com', roles: ['admin'], permissions: ['all']}));\n";
echo "window.location.reload();\n";
?>