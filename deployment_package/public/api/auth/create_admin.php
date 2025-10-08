<?php
// Endpoint seguro para crear/activar un usuario admin (o super_admin) en producción
// Requiere token: ADMIN_RESET_TOKEN en .env.production o archivo admin_token.txt en raíz

// CORS básico y JSON
if (!headers_sent()) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['ORIGIN'] ?? '*');
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['success' => true, 'message' => 'OK']);
    exit;
}

// Cargar autoload y entorno
$root = dirname(__DIR__, 3); // .../public/api/auth -> raíz del proyecto

// Composer autoload (robusto)
$autoloadPaths = [
    $root . '/vendor/autoload.php',
    $root . '/deploy/vendor/autoload.php',
];
foreach ($autoloadPaths as $ap) {
    if (file_exists($ap)) { require_once $ap; break; }
}

// Dotenv
try {
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable($root);
        $dotenv->safeLoad();
        // Intentar .env.production si existe
        if (file_exists($root . '/.env.production')) {
            $dotenvProd = Dotenv\Dotenv::createImmutable($root, '.env.production');
            $dotenvProd->safeLoad();
        }
    }
} catch (Throwable $e) {
    // Continuar sin bloquear
}

// Helpers de compatibilidad
function envc($key, $default = null) {
    $val = getenv($key);
    if ($val === false && isset($_ENV[$key])) $val = $_ENV[$key];
    return ($val !== false && $val !== null && $val !== '') ? $val : $default;
}

function readAdminToken($root) {
    $tokenEnv = envc('ADMIN_RESET_TOKEN');
    if ($tokenEnv) return trim($tokenEnv);
    $file = $root . '/admin_token.txt';
    if (file_exists($file)) {
        $c = trim(@file_get_contents($file));
        if ($c !== '') return $c;
    }
    return null;
}

function getDbConnection($root) {
    // Intentar clase Connection del proyecto
    try {
        if (!class_exists('iaTradeCRM\\Database\\Connection')) {
            $connPath = $root . '/src/Database/Connection.php';
            if (file_exists($connPath)) require_once $connPath;
        }
        if (class_exists('iaTradeCRM\\Database\\Connection')) {
            return iaTradeCRM\Database\Connection::getInstance()->getConnection();
        }
    } catch (Throwable $e) {
        // Continuar con PDO directo
    }

    // Fallback: PDO directo por variables de entorno/config
    $dbHost = envc('DB_HOST', 'localhost');
    $dbPort = envc('DB_PORT', '3306');
    $dbName = envc('DB_DATABASE', envc('DB_NAME', 'spin2pay_profixcrm'));
    $dbUser = envc('DB_USERNAME', envc('DB_USER', 'root'));
    $dbPass = envc('DB_PASSWORD', envc('DB_PASS', ''));

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $pdo;
}

function jsonBody() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($ok, $message, $extra = []) {
    $payload = array_merge(['success' => $ok, 'message' => $message], $extra);
    echo json_encode($payload);
    exit;
}

try {
    // Validar token
    $tokenProvided = null;
    $input = jsonBody();
    if (isset($input['token'])) $tokenProvided = trim((string)$input['token']);
    if (!$tokenProvided && isset($_GET['token'])) $tokenProvided = trim((string)$_GET['token']);

    $adminToken = readAdminToken($root);
    if (!$adminToken) {
        respond(false, 'Token de seguridad no configurado (ADMIN_RESET_TOKEN o admin_token.txt)');
    }
    if (!$tokenProvided || !hash_equals($adminToken, $tokenProvided)) {
        respond(false, 'Token inválido');
    }

    // Datos de usuario
    $username = $input['username'] ?? ($_GET['username'] ?? null);
    $email = $input['email'] ?? ($_GET['email'] ?? null);
    $password = $input['password'] ?? ($_GET['password'] ?? null);
    $role = strtolower(trim((string)($input['role'] ?? ($_GET['role'] ?? 'admin'))));
    $firstName = $input['first_name'] ?? ($_GET['first_name'] ?? 'Admin');
    $lastName = $input['last_name'] ?? ($_GET['last_name'] ?? 'User');

    if (!$username || !$email || !$password) {
        respond(false, 'Faltan campos: username, email y password son obligatorios');
    }
    $assignAll = ($role === 'all');
    if (!$assignAll && !in_array($role, ['admin', 'super_admin'], true)) $role = 'admin';

    $pdo = getDbConnection($root);

    // Detectar columna de contraseña
    $hasPwdHash = false; $hasPwd = false;
    try {
        $st = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
        $hasPwdHash = (bool)($st && $st->fetch());
    } catch (Throwable $e) {}
    try {
        $st = $pdo->query("SHOW COLUMNS FROM users LIKE 'password'");
        $hasPwd = (bool)($st && $st->fetch());
    } catch (Throwable $e) {}
    if (!$hasPwdHash && !$hasPwd) {
        respond(false, 'La tabla users no tiene columnas de contraseña válidas (password_hash/password)');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Verificar si existe
    $st = $pdo->prepare("SELECT id, status FROM users WHERE username = ? OR email = ? LIMIT 1");
    $st->execute([$username, $email]);
    $existing = $st->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Actualizar contraseña y activar
        if ($hasPwdHash) {
            $sql = "UPDATE users SET password_hash = ?, status = 'active', email_verified = 1, deleted_at = NULL, updated_at = NOW() WHERE id = ?";
            $upd = $pdo->prepare($sql);
            $upd->execute([$hash, (int)$existing['id']]);
        } else {
            $sql = "UPDATE users SET password = ?, status = 'active', email_verified = 1, deleted_at = NULL, updated_at = NOW() WHERE id = ?";
            $upd = $pdo->prepare($sql);
            $upd->execute([$password, (int)$existing['id']]);
        }
        $userId = (int)$existing['id'];
        $action = 'updated';
    } else {
        // Crear usuario
        if ($hasPwdHash) {
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, status, email_verified, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', 1, NOW(), NOW())";
            $ins = $pdo->prepare($sql);
            $ins->execute([$username, $email, $hash, $firstName, $lastName]);
        } else {
            $sql = "INSERT INTO users (username, email, password, first_name, last_name, status, email_verified, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', 1, NOW(), NOW())";
            $ins = $pdo->prepare($sql);
            $ins->execute([$username, $email, $password, $firstName, $lastName]);
        }
        $userId = (int)$pdo->lastInsertId();
        $action = 'created';
    }

    // Asegurar rol(es) y asignación
    $assignedRoleNames = [];
    if ($assignAll) {
        $roles = [];
        try {
            $rs = $pdo->query("SELECT id, name FROM roles");
            $roles = $rs ? $rs->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $roles = [];
        }
        if (!$roles || count($roles) === 0) {
            // Crear roles básicos si la tabla está vacía
            $insRole = $pdo->prepare("INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, 1)");
            $insRole->execute(['super_admin', 'Super Administrador', 'Acceso completo al sistema', 1]);
            $insRole->execute(['admin', 'Administrador', 'Administrador del sistema', 1]);
            $rs = $pdo->query("SELECT id, name FROM roles");
            $roles = $rs ? $rs->fetchAll(PDO::FETCH_ASSOC) : [];
        }
        foreach ($roles as $r) {
            $rid = (int)$r['id'];
            $rname = (string)$r['name'];
            $st = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?");
            $st->execute([$userId, $rid]);
            $hasRole = ((int)$st->fetchColumn()) > 0;
            if (!$hasRole) {
                $insUR = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
                $insUR->execute([$userId, $rid]);
            }
            $assignedRoleNames[] = $rname;
        }
    } else {
        $st = $pdo->prepare("SELECT id, name FROM roles WHERE name = ? LIMIT 1");
        $st->execute([$role]);
        $roleRow = $st->fetch(PDO::FETCH_ASSOC);
        if (!$roleRow) {
            // Crear rol mínimo si no existe
            $insRole = $pdo->prepare("INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, 1)");
            $displayName = $role === 'super_admin' ? 'Super Administrador' : 'Administrador';
            $desc = $role === 'super_admin' ? 'Acceso completo al sistema' : 'Administrador del sistema';
            $insRole->execute([$role, $displayName, $desc]);
            $roleId = (int)$pdo->lastInsertId();
            $roleName = $role;
        } else {
            $roleId = (int)$roleRow['id'];
            $roleName = (string)$roleRow['name'];
        }
        // Asignar rol si no está
        $st = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?");
        $st->execute([$userId, $roleId]);
        $hasRole = ((int)$st->fetchColumn()) > 0;
        if (!$hasRole) {
            $insUR = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
            $insUR->execute([$userId, $roleId]);
        }
        $assignedRoleNames[] = $roleName;
    }

    respond(true, $assignAll ? "Usuario {$action} y todos los roles asignados" : "Usuario {$action} y rol '{$role}' asegurado", [
        'user' => [ 'id' => $userId, 'username' => $username, 'email' => $email ],
        'roles' => $assignedRoleNames
    ]);

} catch (Throwable $e) {
    respond(false, 'Error: ' . $e->getMessage());
}