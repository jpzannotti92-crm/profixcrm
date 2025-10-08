<?php
/**
 * Validador de Configuración - ProfixCRM v6
 * Identifica problemas de conexión y configuración
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== VALIDADOR DE CONFIGURACIÓN PROFIXCRM V6 ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n\n";

// 1. Verificar rutas y archivos de configuración
echo "1. ANÁLISIS DE ARCHIVOS DE CONFIGURACIÓN\n";
echo "- Ruta actual: " . __DIR__ . "\n";
echo "- Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";

$config_paths = [
    __DIR__ . '/config/config.php',
    __DIR__ . '/config/database.php',
    __DIR__ . '/api/config.php',
    __DIR__ . '/.env.production',
    __DIR__ . '/.env'
];

foreach ($config_paths as $path) {
    if (file_exists($path)) {
        echo "- ✓ " . basename($path) . " existe\n";
        echo "  Tamaño: " . filesize($path) . " bytes\n";
        echo "  Modificado: " . date('Y-m-d H:i:s', filemtime($path)) . "\n";
        
        // Verificar contenido de configuración
        if (strpos($path, 'config.php') !== false) {
            $content = file_get_contents($path);
            if (strpos($content, 'DB_') !== false) {
                echo "  → Contiene definiciones DB_\n";
            } else {
                echo "  → ⚠️ No contiene definiciones DB_\n";
            }
        }
    } else {
        echo "- ✗ " . basename($path) . " NO EXISTE\n";
    }
}
echo "\n";

// 2. Verificar variables de entorno
echo "2. VARIABLES DE ENTORNO Y CONSTANTES\n";
if (file_exists(__DIR__ . '/.env.production')) {
    $env_content = file_get_contents(__DIR__ . '/.env.production');
    echo "Contenido de .env.production:\n";
    $lines = explode("\n", $env_content);
    foreach ($lines as $line) {
        if (trim($line) && !strpos($line, 'PASS')) {
            echo "- $line\n";
        } elseif (strpos($line, 'PASS')) {
            echo "- [PASSWORD HIDDEN]\n";
        }
    }
}
echo "\n";

// 3. Probar carga de configuración
echo "3. PRUEBA DE CARGA DE CONFIGURACIÓN\n";
try {
    if (file_exists(__DIR__ . '/config/config.php')) {
        require_once __DIR__ . '/config/config.php';
        echo "✓ Config cargada exitosamente\n";
        
        // Verificar constantes definidas
        $constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($constants as $const) {
            if (defined($const)) {
                echo "- $const: " . (strpos($const, 'PASS') !== false ? '[HIDDEN]' : constant($const)) . "\n";
            } else {
                echo "- $const: ⚠️ NO DEFINIDA\n";
            }
        }
    } else {
        echo "✗ No se encontró config/config.php\n";
    }
} catch (Exception $e) {
    echo "✗ Error cargando config: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Probar conexión a base de datos
echo "4. PRUEBA DE CONEXIÓN A BASE DE DATOS\n";
try {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        echo "Intentando conexión con: " . DB_USER . "@" . DB_HOST . "/" . DB_NAME . "\n";
        
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        echo "✓ Conexión exitosa\n";
        
        // Verificar tablas
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tablas encontradas: " . count($tables) . "\n";
        
        // Verificar usuario admin
        $admin = $pdo->query("SELECT id, username, email, status FROM users WHERE username='admin' OR email='admin@iatrade.com' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            echo "✓ Usuario admin encontrado:\n";
            echo "  ID: {$admin['id']}\n";
            echo "  Username: {$admin['username']}\n";
            echo "  Email: {$admin['email']}\n";
            echo "  Status: {$admin['status']}\n";
        } else {
            echo "⚠️ Usuario admin no encontrado\n";
        }
    } else {
        echo "✗ Constantes de BD no definidas\n";
    }
} catch (PDOException $e) {
    echo "✗ Error de conexión: " . $e->getMessage() . "\n";
    echo "Código de error: " . $e->getCode() . "\n";
}
echo "\n";

// 5. Verificar estructura de tablas
echo "5. VERIFICACIÓN DE TABLAS CRÍTICAS\n";
try {
    if (isset($pdo)) {
        $critical_tables = ['users', 'roles', 'permissions', 'role_permissions', 'user_roles'];
        foreach ($critical_tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✓ Tabla $table existe\n";
                
                // Contar registros
                $count = $pdo->query("SELECT COUNT(*) FROM $table WHERE deleted_at IS NULL")->fetchColumn();
                echo "  → Registros activos: $count\n";
            } else {
                echo "✗ Tabla $table NO EXISTE\n";
            }
        }
    }
} catch (Exception $e) {
    echo "✗ Error verificando tablas: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Recomendaciones
echo "6. RECOMENDACIONES\n";
$recommendations = [];

if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS')) {
    $recommendations[] = "Verificar que config/config.php defina correctamente las constantes DB_";
}

if (!isset($pdo)) {
    $recommendations[] = "Revisar credenciales de base de datos en .env.production o config.php";
}

if (!isset($admin)) {
    $recommendations[] = "Crear usuario admin usando create_admin.php";
}

if (empty($recommendations)) {
    echo "✓ Configuración parece correcta\n";
} else {
    foreach ($recommendations as $i => $rec) {
        echo ($i + 1) . ". $rec\n";
    }
}

echo "\n==============================================\n";
echo "Validación completada: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n";

// Guardar resultado
$result_file = __DIR__ . '/logs/config_validation_' . date('Y-m-d_H-i-s') . '.txt';
file_put_contents($result_file, ob_get_contents());
echo "\n[Resultado guardado en: $result_file]\n";

?>