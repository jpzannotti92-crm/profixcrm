<?php
// Crea tablas mínimas necesarias para login en la BD activa
$env = __DIR__ . '/.env';
$cfg = [
  'host' => 'localhost',
  // Fallback actualizado: spin2pay_profixcrm
  'db' => 'spin2pay_profixcrm',
  'user' => 'root',
  'pass' => ''
];
if (is_file($env)) {
  foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(ltrim($line), '#') === 0) continue;
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
      $k = trim($parts[0]);
      $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
      if ($k === 'DB_HOST') $cfg['host'] = $v;
      if ($k === 'DB_DATABASE' || $k === 'DB_NAME') $cfg['db'] = $v;
      if ($k === 'DB_USERNAME' || $k === 'DB_USER') $cfg['user'] = $v;
      if ($k === 'DB_PASSWORD') $cfg['pass'] = $v;
    }
  }
}

try {
  $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['db']};charset=utf8mb4", $cfg['user'], $cfg['pass']);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  echo "Conectado a {$cfg['db']}\n";

  // Desks
  $pdo->exec("CREATE TABLE IF NOT EXISTS desks (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    name VARCHAR(100) NOT NULL,\n    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Columnas adicionales requeridas en desks
  try { $col = $pdo->query("SHOW COLUMNS FROM desks LIKE 'manager_id'"); $exists = $col && $col->fetch(); } catch (Exception $e) { $exists = false; }
  if (!$exists) { try { $pdo->exec("ALTER TABLE desks ADD COLUMN manager_id INT DEFAULT NULL"); } catch (Exception $e) { /* ignore */ } }
  try { $col = $pdo->query("SHOW COLUMNS FROM desks LIKE 'status'"); $exists = $col && $col->fetch(); } catch (Exception $e) { $exists = false; }
  if (!$exists) { try { $pdo->exec("ALTER TABLE desks ADD COLUMN status VARCHAR(20) DEFAULT 'active'"); } catch (Exception $e) { /* ignore */ } }
  try { $col = $pdo->query("SHOW COLUMNS FROM desks LIKE 'color'"); $exists = $col && $col->fetch(); } catch (Exception $e) { $exists = false; }
  if (!$exists) { try { $pdo->exec("ALTER TABLE desks ADD COLUMN color VARCHAR(7) DEFAULT '#007bff'"); } catch (Exception $e) { /* ignore */ } }

  // Desk users
  $pdo->exec("CREATE TABLE IF NOT EXISTS desk_users (\n    desk_id INT NOT NULL,\n    user_id INT NOT NULL,\n    PRIMARY KEY (desk_id, user_id)\n  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Asegurar columna is_primary si no existe
  $hasIsPrimary = false;
  try {
    $col = $pdo->query("SHOW COLUMNS FROM desk_users LIKE 'is_primary'");
    $hasIsPrimary = $col && $col->fetch() ? true : false;
  } catch (Exception $e) { $hasIsPrimary = false; }
  if (!$hasIsPrimary) {
    try { $pdo->exec("ALTER TABLE desk_users ADD COLUMN is_primary TINYINT(1) DEFAULT 1"); } catch (Exception $e) { /* ignore */ }
  }

  // Asegurar columnas estándar en desk_users para compatibilidad (role, assigned_by, assigned_at)
  try { $col = $pdo->query("SHOW COLUMNS FROM desk_users LIKE 'role'"); $exists = $col && $col->fetch(); } catch (Exception $e) { $exists = false; }
  if (!$exists) { try { $pdo->exec("ALTER TABLE desk_users ADD COLUMN role VARCHAR(20) DEFAULT 'member'"); } catch (Exception $e) { /* ignore */ } }
  try { $col = $pdo->query("SHOW COLUMNS FROM desk_users LIKE 'assigned_by'"); $exists = $col && $col->fetch(); } catch (Exception $e) { $exists = false; }
  if (!$exists) { try { $pdo->exec("ALTER TABLE desk_users ADD COLUMN assigned_by INT DEFAULT NULL"); } catch (Exception $e) { /* ignore */ } }
  try { $col = $pdo->query("SHOW COLUMNS FROM desk_users LIKE 'assigned_at'"); $exists = $col && $col->fetch(); } catch (Exception $e) { $exists = false; }
  if (!$exists) { try { $pdo->exec("ALTER TABLE desk_users ADD COLUMN assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) { /* ignore */ } }

  // Roles
  $pdo->exec("CREATE TABLE IF NOT EXISTS roles (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    name VARCHAR(100) NOT NULL UNIQUE,\n    description VARCHAR(255) DEFAULT NULL,\n    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Asegurar display_name en roles para compatibilidad
  try { $col = $pdo->query("SHOW COLUMNS FROM roles LIKE 'display_name'"); $exists = $col && $col->fetch(); } catch (Exception $e) { $exists = false; }
  if (!$exists) { try { $pdo->exec("ALTER TABLE roles ADD COLUMN display_name VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) { /* ignore */ } }

  // Permissions
  $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    code VARCHAR(100) NOT NULL UNIQUE,\n    name VARCHAR(100) NOT NULL,\n    description VARCHAR(255) DEFAULT NULL\n  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // User roles
  $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (\n    user_id INT NOT NULL,\n    role_id INT NOT NULL,\n    PRIMARY KEY (user_id, role_id)\n  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Role permissions
  $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (\n    role_id INT NOT NULL,\n    permission_id INT NOT NULL,\n    granted_by INT DEFAULT NULL,\n    PRIMARY KEY (role_id, permission_id)\n  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Seeds
  $pdo->exec("INSERT IGNORE INTO desks (id, name) VALUES (1, 'Default Desk')");
  if ($hasIsPrimary) {
    $pdo->exec("INSERT IGNORE INTO desk_users (desk_id, user_id, is_primary) VALUES (1, 1, 1)");
  } else {
    $pdo->exec("INSERT IGNORE INTO desk_users (desk_id, user_id) VALUES (1, 1)");
  }
  $pdo->exec("INSERT IGNORE INTO roles (id, name, description) VALUES (1, 'admin', 'Administrador')");
  // Si existe display_name, establecerlo para el rol admin
  try { $pdo->exec("UPDATE roles SET display_name = 'Administrador' WHERE id = 1"); } catch (Exception $e) { /* ignore */ }
  $pdo->exec("INSERT IGNORE INTO permissions (id, code, name) VALUES (1, 'dashboard_access', 'Acceso al dashboard')");
  $pdo->exec("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (1, 1)");
  $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id, granted_by) VALUES (1, 1, 1)");

  // Asegurar columnas requeridas en users para el login (phone, department, position, settings)
  $ensureUserCol = function(string $name, string $definition) use ($pdo) {
    try {
      $col = $pdo->query("SHOW COLUMNS FROM users LIKE '" . $name . "'");
      $exists = $col && $col->fetch() ? true : false;
    } catch (Exception $e) { $exists = false; }
    if (!$exists) {
      try { $pdo->exec("ALTER TABLE users ADD COLUMN " . $definition); } catch (Exception $e) { /* ignore */ }
    }
  };

  $ensureUserCol('phone', "phone VARCHAR(20) DEFAULT NULL");
  $ensureUserCol('department', "department VARCHAR(100) DEFAULT NULL");
  $ensureUserCol('position', "position VARCHAR(100) DEFAULT NULL");
  // JSON no es requerido; usamos TEXT para compatibilidad
  $ensureUserCol('settings', "settings TEXT DEFAULT NULL");

  // Columnas adicionales requeridas por endpoints de login y usuarios
  $ensureUserCol('avatar', "avatar VARCHAR(255) DEFAULT NULL");
  // Usamos VARCHAR para status por compatibilidad con distintos motores
  $ensureUserCol('status', "status VARCHAR(20) DEFAULT 'active'");
  $ensureUserCol('last_login', "last_login TIMESTAMP NULL DEFAULT NULL");
  $ensureUserCol('login_attempts', "login_attempts INT DEFAULT 0");
  $ensureUserCol('locked_until', "locked_until TIMESTAMP NULL DEFAULT NULL");
  $ensureUserCol('email_verified', "email_verified TINYINT(1) DEFAULT 0");
  $ensureUserCol('email_verification_token', "email_verification_token VARCHAR(255) DEFAULT NULL");
  $ensureUserCol('password_reset_token', "password_reset_token VARCHAR(255) DEFAULT NULL");
  $ensureUserCol('password_reset_expires', "password_reset_expires TIMESTAMP NULL DEFAULT NULL");
  $ensureUserCol('created_at', "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
  $ensureUserCol('updated_at', "updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

  echo "Esquema mínimo creado y semillas aplicadas.\n";
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
  exit(1);
}
?>