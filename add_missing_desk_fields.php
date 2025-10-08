<?php
// Script para agregar columnas faltantes en las tablas desks y desk_users
// Uso: C:\xampp\php\php.exe add_missing_desk_fields.php

function alterIfMissing(PDO $pdo, string $table, array $desiredColumns) {
    try {
        $stmt = $pdo->query("DESCRIBE {$table}");
        $existing = array_map(function($c){ return $c['Field']; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "✗ No se pudo describir la tabla {$table}: " . $e->getMessage() . "\n";
        return;
    }

    foreach ($desiredColumns as $col => $ddl) {
        if (!in_array($col, $existing, true)) {
            try {
                $pdo->exec($ddl);
                echo "✓ Columna agregada en {$table}: {$col}\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                    echo "⚠ Columna ya existe en {$table}: {$col}\n";
                } else {
                    echo "✗ Error agregando {$col} en {$table}: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "• Columna presente en {$table}: {$col}\n";
        }
    }
}

try {
    // Cargar variables de entorno para no fijar iatrade_crm
    if (class_exists('Dotenv\\Dotenv')) {
        // Usar el nombre de clase con separador de espacio de nombres correcto
        $dotenv = Dotenv\Dotenv::createMutable(__DIR__);
        if (method_exists($dotenv, 'overload')) { $dotenv->overload(); } else { $dotenv->load(); }
    } else {
        $envFile = __DIR__ . '/.env';
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(ltrim($line), '#') === 0) { continue; }
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $_ENV[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
    }

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $db   = $_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? 'iatrade_crm');
    $user = $_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? 'root');
    $pass = $_ENV['DB_PASSWORD'] ?? '';
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

    $pdo = new PDO("mysql:host={$host};dbname={$db};charset={$charset}", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Conexión OK.\n";

    echo "\nVerificando tabla: desks\n";
    alterIfMissing($pdo, 'desks', [
        // Campos modernos usados por el backend actual
        'color' => "ALTER TABLE desks ADD COLUMN color VARCHAR(7) NOT NULL DEFAULT '#007bff' AFTER description",
        'max_leads' => "ALTER TABLE desks ADD COLUMN max_leads INT NOT NULL DEFAULT 1000 AFTER status",
        'auto_assign' => "ALTER TABLE desks ADD COLUMN auto_assign TINYINT(1) NOT NULL DEFAULT 0 AFTER max_leads",
        'working_hours_start' => "ALTER TABLE desks ADD COLUMN working_hours_start TIME NULL DEFAULT '09:00:00' AFTER auto_assign",
        'working_hours_end' => "ALTER TABLE desks ADD COLUMN working_hours_end TIME NULL DEFAULT '18:00:00' AFTER working_hours_start",
        'timezone' => "ALTER TABLE desks ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'UTC' AFTER working_hours_end",
        // Campos presentes en algunas migraciones
        'target_monthly' => "ALTER TABLE desks ADD COLUMN target_monthly INT NULL DEFAULT 0 AFTER timezone",
        'target_daily' => "ALTER TABLE desks ADD COLUMN target_daily INT NULL DEFAULT 0 AFTER target_monthly",
        'commission_rate' => "ALTER TABLE desks ADD COLUMN commission_rate DECIMAL(5,2) NULL DEFAULT 0 AFTER target_daily",
        'created_by' => "ALTER TABLE desks ADD COLUMN created_by INT NULL AFTER commission_rate"
    ]);

    echo "\nVerificando tabla: desk_users\n";
    alterIfMissing($pdo, 'desk_users', [
        'assigned_by' => "ALTER TABLE desk_users ADD COLUMN assigned_by INT NULL AFTER user_id",
        'is_primary' => "ALTER TABLE desk_users ADD COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER assigned_by",
        'assigned_at' => "ALTER TABLE desk_users ADD COLUMN assigned_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER is_primary"
    ]);

    echo "\nListo.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>