<?php
// Cargar .env de forma compatible con PHP 8.0
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

header('Content-Type: application/json');

try {
    $dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') .
           ';port=' . ($_ENV['DB_PORT'] ?? '3306') .
           ';dbname=' . ($_ENV['DB_DATABASE'] ?? 'iatrade_crm');
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $db = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Obtener usuario admin
    $stmt = $db->prepare("SELECT id, username, email, status FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Usuario admin no encontrado']);
        exit;
    }

    $userId = (int)$admin['id'];

    // Roles del usuario
    $stmt = $db->prepare("SELECT r.id, r.name, r.display_name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?");
    $stmt->execute([$userId]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Permisos por roles del usuario
    $stmt = $db->prepare("SELECT DISTINCT p.id, p.name, p.description FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id JOIN user_roles ur ON rp.role_id = ur.role_id WHERE ur.user_id = ? ORDER BY p.name");
    $stmt->execute([$userId]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Permisos del rol super_admin
    $stmt = $db->prepare("SELECT p.name FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id JOIN roles r ON rp.role_id = r.id WHERE r.name = 'super_admin' ORDER BY p.name");
    $stmt->execute();
    $superAdminPerms = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');

    // Comprobaciones clave
    $checkPerm = function($permName) use ($db, $userId) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM permissions p INNER JOIN role_permissions rp ON p.id = rp.permission_id INNER JOIN user_roles ur ON rp.role_id = ur.role_id WHERE ur.user_id = :user_id AND p.name = :permission_name");
        $stmt->execute(['user_id' => $userId, 'permission_name' => $permName]);
        return (int)$stmt->fetchColumn();
    };

    $checks = [
        'users.view' => $checkPerm('users.view'),
        'desks.view' => $checkPerm('desks.view'),
        'leads.view.all' => $checkPerm('leads.view.all'),
        'roles.view' => $checkPerm('roles.view'),
    ];

    echo json_encode([
        'success' => true,
        'admin' => $admin,
        'roles' => $roles,
        'permissions' => $permissions,
        'super_admin_permissions' => $superAdminPerms,
        'checks' => $checks
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>