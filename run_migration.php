<?php
/**
 * Runner genérico de migraciones SQL
 * Ejecuta todos los archivos .sql ubicados en database/migrations en orden lexicográfico
 */
// Carga mínima de .env sin Composer (compatibilidad PHP 8.0)
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(ltrim($line), '#') === 0) { continue; }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1], " \t\n\r\0\x0B\"'");
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
// Fallback actualizado: spin2pay_profixcrm
$dbname = $_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? 'spin2pay_profixcrm');
$username = $_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? 'root');
$password = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conectado a la base de datos exitosamente.\n";

    // Verificar tablas base requeridas; si faltan, ejecutar el instalador completo
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $hasUsers = (bool)$stmt->fetchColumn();
    } catch (Exception $e) { $hasUsers = false; }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'desk_users'");
        $hasDeskUsers = (bool)$stmt->fetchColumn();
    } catch (Exception $e) { $hasDeskUsers = false; }

    if (!$hasUsers || !$hasDeskUsers) {
        echo "\nCargando esquema completo desde install/sql/install.sql...\n";
        $installPath = __DIR__ . '/install/sql/install.sql';
        if (!is_file($installPath)) {
            echo "   ❌ No se encontró install/sql/install.sql\n";
        } else {
            $sql = file_get_contents($installPath);
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function ($stmt) { return !empty($stmt) && !preg_match('/^\s*--/', $stmt); }
            );
            $ok = 0; $warn = 0;
            foreach ($statements as $statement) {
                if ($statement === '') continue;
                try { $pdo->exec($statement); $ok++; }
                catch (PDOException $e) {
                    $msg = $e->getMessage();
                    if (
                        strpos($msg, 'already exists') !== false ||
                        strpos($msg, 'Duplicate entry') !== false ||
                        strpos($msg, 'Duplicate column name') !== false ||
                        strpos($msg, 'errno: 1060') !== false ||
                        strpos($msg, 'Duplicate key name') !== false ||
                        strpos($msg, 'Cannot add foreign key constraint') !== false
                    ) { echo "   ⚠️  $msg\n"; $warn++; }
                    else { throw $e; }
                }
            }
            echo "   ✅ Statements OK: $ok, ⚠️ warnings: $warn\n";
        }

        // Garantizar creación mínima de la tabla users si aún no existe
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            $hasUsersAfter = (bool)$stmt->fetchColumn();
        } catch (Exception $e) { $hasUsersAfter = false; }

        if (!$hasUsersAfter) {
            echo "   ➕ Creando tabla 'users' mínima...\n";
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `users` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `username` varchar(50) NOT NULL,
                  `email` varchar(100) NOT NULL,
                  `password_hash` varchar(255) NOT NULL,
                  `first_name` varchar(50) NOT NULL,
                  `last_name` varchar(50) NOT NULL,
                  `avatar` varchar(255) DEFAULT NULL,
                  `status` enum('active','inactive','suspended') DEFAULT 'active',
                  `last_login` timestamp NULL DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `username` (`username`),
                  UNIQUE KEY `email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            echo "   ✅ Tabla 'users' creada\n";

            // Insertar admin por defecto para pruebas
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['admin', 'admin@system.local', $adminPassword, 'Admin', 'System', 'active']);
            echo "   👤 Usuario admin creado (admin/admin123)\n";
        }
        // Garantizar 'desks' y 'desk_users' mínimos para el login
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'desks'");
            $hasDesks = (bool)$stmt->fetchColumn();
        } catch (Exception $e) { $hasDesks = false; }

        if (!$hasDesks) {
            echo "   ➕ Creando tabla 'desks' mínima...\n";
            $pdo->exec("\n                CREATE TABLE IF NOT EXISTS `desks` (\n                  `id` int(11) NOT NULL AUTO_INCREMENT,\n                  `name` varchar(100) NOT NULL,\n                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n                  PRIMARY KEY (`id`)\n                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n            ");
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'desk_users'");
            $hasDeskUsersAfter = (bool)$stmt->fetchColumn();
        } catch (Exception $e) { $hasDeskUsersAfter = false; }

        if (!$hasDeskUsersAfter) {
            echo "   ➕ Creando tabla 'desk_users' mínima...\n";
            $pdo->exec("\n                CREATE TABLE IF NOT EXISTS `desk_users` (\n                  `desk_id` int(11) NOT NULL,\n                  `user_id` int(11) NOT NULL,\n                  PRIMARY KEY (`desk_id`,`user_id`)\n                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n            ");
        }

        // Semillas mínimas
        $pdo->exec("INSERT IGNORE INTO desks (id, name) VALUES (1, 'Default Desk')");
        $pdo->exec("INSERT IGNORE INTO desk_users (desk_id, user_id) VALUES (1, 1)");

        // Tablas mínimas de roles y permisos para evitar errores en el login
        try { $stmt = $pdo->query("SHOW TABLES LIKE 'roles'"); $hasRoles = (bool)$stmt->fetchColumn(); } catch (Exception $e) { $hasRoles = false; }
        if (!$hasRoles) {
            echo "   ➕ Creando tabla 'roles' mínima...\n";
            $pdo->exec("\n                CREATE TABLE IF NOT EXISTS `roles` (\n                  `id` int(11) NOT NULL AUTO_INCREMENT,\n                  `name` varchar(100) NOT NULL,\n                  `description` varchar(255) DEFAULT NULL,\n                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n                  PRIMARY KEY (`id`),\n                  UNIQUE KEY `name` (`name`)\n                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n            ");
        }

        try { $stmt = $pdo->query("SHOW TABLES LIKE 'permissions'"); $hasPerms = (bool)$stmt->fetchColumn(); } catch (Exception $e) { $hasPerms = false; }
        if (!$hasPerms) {
            echo "   ➕ Creando tabla 'permissions' mínima...\n";
            $pdo->exec("\n                CREATE TABLE IF NOT EXISTS `permissions` (\n                  `id` int(11) NOT NULL AUTO_INCREMENT,\n                  `code` varchar(100) NOT NULL,\n                  `name` varchar(100) NOT NULL,\n                  `description` varchar(255) DEFAULT NULL,\n                  PRIMARY KEY (`id`),\n                  UNIQUE KEY `code` (`code`)\n                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n            ");
        }

        try { $stmt = $pdo->query("SHOW TABLES LIKE 'user_roles'"); $hasUserRoles = (bool)$stmt->fetchColumn(); } catch (Exception $e) { $hasUserRoles = false; }
        if (!$hasUserRoles) {
            echo "   ➕ Creando tabla 'user_roles' mínima...\n";
            $pdo->exec("\n                CREATE TABLE IF NOT EXISTS `user_roles` (\n                  `user_id` int(11) NOT NULL,\n                  `role_id` int(11) NOT NULL,\n                  PRIMARY KEY (`user_id`,`role_id`)\n                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n            ");
        }

        try { $stmt = $pdo->query("SHOW TABLES LIKE 'role_permissions'"); $hasRolePerms = (bool)$stmt->fetchColumn(); } catch (Exception $e) { $hasRolePerms = false; }
        if (!$hasRolePerms) {
            echo "   ➕ Creando tabla 'role_permissions' mínima...\n";
            $pdo->exec("\n                CREATE TABLE IF NOT EXISTS `role_permissions` (\n                  `role_id` int(11) NOT NULL,\n                  `permission_id` int(11) NOT NULL,\n                  `granted_by` int(11) DEFAULT NULL,\n                  PRIMARY KEY (`role_id`,`permission_id`)\n                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n            ");
        }

        // Semillas mínimas de rol admin y permisos básicos
        $pdo->exec("INSERT IGNORE INTO roles (id, name, description) VALUES (1, 'admin', 'Administrador del sistema')");
        $pdo->exec("INSERT IGNORE INTO permissions (id, code, name) VALUES (1, 'dashboard_access', 'Acceso al dashboard')");
        $pdo->exec("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (1, 1)");
        $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id, granted_by) VALUES (1, 1, 1)");
    }

    $migrationsDir = __DIR__ . '/database/migrations';
    if (!is_dir($migrationsDir)) {
        throw new Exception('Directorio de migraciones no encontrado: ' . $migrationsDir);
    }

    // Obtener lista de archivos .sql ordenados
    $files = glob($migrationsDir . '/*.sql');
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    if (empty($files)) {
        echo "No se encontraron archivos de migración .sql en database/migrations\n";
        exit(0);
    }

    echo "Ejecutando migraciones:\n";
    foreach ($files as $file) {
        $name = basename($file);
        echo "\n📄 $name\n";

        $sql = file_get_contents($file);
        if ($sql === false) {
            echo "   ❌ No se pudo leer el archivo\n";
            continue;
        }

        // Dividir por ';' respetando líneas de comentario simples
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function ($stmt) {
                return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
            }
        );

        $ok = 0; $warn = 0;
        foreach ($statements as $statement) {
            if ($statement === '') continue;
            try {
                $pdo->exec($statement);
                $ok++;
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                // Ignorar errores típicos de idempotencia
                if (
                    strpos($msg, 'already exists') !== false ||
                    strpos($msg, 'Duplicate entry') !== false ||
                    strpos($msg, 'Duplicate column name') !== false ||
                    strpos($msg, 'errno: 1060') !== false ||
                    strpos($msg, 'Duplicate key name') !== false ||
                    strpos($msg, 'Cannot add foreign key constraint') !== false // En caso de que ya exista con otro nombre
                ) {
                    echo "   ⚠️  " . $msg . "\n";
                    $warn++;
                } else {
                    throw $e;
                }
            }
        }

        echo "   ✅ Statements OK: $ok, ⚠️ warnings: $warn\n";
    }

    echo "\n🎉 Migraciones ejecutadas.\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>