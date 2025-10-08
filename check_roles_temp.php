<?php
require_once 'vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_DATABASE'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== ROLES EXISTENTES ===" . PHP_EOL;
$stmt = $db->query('SELECT id, name, description FROM roles ORDER BY id');
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($roles as $rol) {
    echo "ID: " . $rol['id'] . " - Nombre: " . $rol['name'] . " - Descripción: " . $rol['description'] . PHP_EOL;
}

echo "\n=== USUARIO ADMIN ===" . PHP_EOL;
$stmt = $db->query('SELECT u.id, u.username, u.email, r.name as role_name FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.id WHERE u.username = "admin"');
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if ($admin) {
    echo "Usuario: " . $admin['username'] . " - Email: " . $admin['email'] . " - Rol: " . ($admin['role_name'] ?? 'Sin rol') . PHP_EOL;
} else {
    echo "No se encontró el usuario admin" . PHP_EOL;
}