<?php
/**
 * Script de Diagnóstico Completo - ProfixCRM
 * Ejecuta todas las pruebas del sistema y genera reporte detallado
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNÓSTICO COMPLETO PROFIXCRM ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// 1. Verificar configuración básica
echo "1. CONFIGURACIÓN BÁSICA\n";
echo "- PHP Version: " . PHP_VERSION . "\n";
echo "- SAPI: " . PHP_SAPI . "\n";
echo "- Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "- Script: " . __FILE__ . "\n";
echo "- URL Actual: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "\n\n";

// 2. Verificar extensiones PHP requeridas
echo "2. EXTENSIONES PHP\n";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'openssl', 'session'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "- $ext: " . ($loaded ? "✓ Cargada" : "✗ NO CARGADA") . "\n";
}
echo "\n";

// 3. Verificar archivos críticos
echo "3. ARCHIVOS CRÍTICOS\n";
$critical_files = [
    '.env.production' => '.env.production',
    'config/config.php' => 'config/config.php',
    'config/database.php' => 'config/database.php',
    'vendor/autoload.php' => 'vendor/autoload.php',
    'api/config.php' => 'api/config.php'
];

foreach ($critical_files as $file => $path) {
    $full_path = __DIR__ . '/' . $path;
    if (file_exists($full_path)) {
        echo "- $file: ✓ Existe (" . date('Y-m-d H:i:s', filemtime($full_path)) . ")\n";
    } else {
        echo "- $file: ✗ NO EXISTE\n";
    }
}
echo "\n";

// 4. Cargar configuración y verificar base de datos
echo "4. BASE DE DATOS\n";
try {
    // Intentar cargar configuración
    if (file_exists(__DIR__ . '/config/config.php')) {
        require_once __DIR__ . '/config/config.php';
        echo "- Config cargada: ✓\n";
        
        if (defined('DB_HOST')) {
            echo "- DB Host: " . DB_HOST . "\n";
            echo "- DB Name: " . DB_NAME . "\n";
            echo "- DB User: " . DB_USER . "\n";
            
            // Probar conexión
            try {
                $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                echo "- Conexión DB: ✓ Exitosa\n";
                
                // Verificar tablas principales
                $tables = ['users', 'roles', 'permissions', 'role_permissions', 'user_roles', 'leads', 'desks'];
                foreach ($tables as $table) {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    echo "- Tabla $table: " . ($stmt->rowCount() > 0 ? "✓ Existe" : "✗ NO EXISTE") . "\n";
                }
                
                // Contar usuarios
                $user_count = $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
                echo "- Total usuarios activos: $user_count\n";
                
                // Verificar usuario admin
                $admin = $pdo->query("SELECT * FROM users WHERE username='admin' OR email='admin@iatrade.com' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($admin) {
                    echo "- Usuario admin: ✓ Existe (ID: {$admin['id']}, Status: {$admin['status']})\n";
                    echo "- Admin activo: " . ($admin['status'] === 'active' ? "✓ Sí" : "✗ NO") . "\n";
                    echo "- Admin verificado: " . ($admin['email_verified'] ? "✓ Sí" : "✗ NO") . "\n";
                } else {
                    echo "- Usuario admin: ✗ NO EXISTE\n";
                }
                
            } catch (PDOException $e) {
                echo "- Conexión DB: ✗ Error - " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "- Config: ✗ No encontrada\n";
    }
} catch (Exception $e) {
    echo "- Error cargando config: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Verificar endpoints de API
echo "5. ENDPOINTS DE API\n";
$base_url = "https://" . $_SERVER['HTTP_HOST'];
$endpoints = [
    '/api/auth/login.php' => 'Login',
    '/api/auth/verify.php' => 'Verify',
    '/api/auth/logout.php' => 'Logout',
    '/api/users.php' => 'Users',
    '/api/leads.php' => 'Leads',
    '/api/health.php' => 'Health',
    '/api/auth/reset_admin.php' => 'Reset Admin',
    '/api/auth/create_admin.php' => 'Create Admin'
];

foreach ($endpoints as $endpoint => $name) {
    $url = $base_url . $endpoint;
    $headers = @get_headers($url);
    if ($headers && strpos($headers[0], '200') !== false) {
        echo "- $name: ✓ Disponible\n";
    } else {
        echo "- $name: ✗ No disponible (" . ($headers ? $headers[0] : "Sin respuesta") . ")\n";
    }
}
echo "\n";

// 6. Verificar logs
echo "6. LOGS DEL SISTEMA\n";
$log_dirs = ['logs/errors', 'logs/debug', 'logs/production'];
foreach ($log_dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $files = glob($path . '/*.log');
        $count = count($files);
        $latest = $count > 0 ? date('Y-m-d H:i:s', filemtime(max($files))) : 'Ninguno';
        echo "- $dir: $count archivos (último: $latest)\n";
    } else {
        echo "- $dir: ✗ No existe\n";
    }
}
echo "\n";

// 7. Verificar permisos de directorios
echo "7. PERMISOS DE DIRECTORIOS\n";
$dirs = ['logs', 'storage', 'storage/cache', 'storage/sessions', 'storage/uploads', 'public/uploads'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $writable = is_writable($path);
        echo "- $dir: " . ($writable ? "✓ Escritura" : "✗ SIN ESCRITURA") . "\n";
    } else {
        echo "- $dir: ✗ No existe\n";
    }
}
echo "\n";

// 8. Verificar versión instalada
echo "8. INFORMACIÓN DE VERSIÓN\n";
if (file_exists(__DIR__ . '/.installed')) {
    $installed = file_get_contents(__DIR__ . '/.installed');
    echo "- Versión instalada: $installed\n";
} else {
    echo "- Versión instalada: Desconocida\n";
}

if (file_exists(__DIR__ . '/deploy/releases')) {
    $releases = glob(__DIR__ . '/deploy/releases/*.zip');
    echo "- Releases disponibles: " . count($releases) . "\n";
    foreach (array_slice($releases, -3) as $release) {
        echo "  - " . basename($release) . " (" . date('Y-m-d H:i:s', filemtime($release)) . ")\n";
    }
}
echo "\n";

// 9. Recomendaciones finales
echo "9. RECOMENDACIONES\n";
$recommendations = [];

if (!extension_loaded('pdo_mysql')) {
    $recommendations[] = "Instalar extensión pdo_mysql";
}

if (!file_exists(__DIR__ . '/.env.production')) {
    $recommendations[] = "Crear archivo .env.production";
}

if (!is_writable(__DIR__ . '/logs')) {
    $recommendations[] = "Dar permisos de escritura a directorio logs";
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $admin = $pdo->query("SELECT * FROM users WHERE username='admin' AND status='active' LIMIT 1")->fetch();
    if (!$admin) {
        $recommendations[] = "Crear o activar usuario admin";
    }
} catch (Exception $e) {
    $recommendations[] = "Verificar configuración de base de datos";
}

if (count($recommendations) > 0) {
    foreach ($recommendations as $i => $rec) {
        echo "- " . ($i + 1) . ". $rec\n";
    }
} else {
    echo "- ✓ Sistema parece estar configurado correctamente\n";
}

echo "\n========================================\n";
echo "Diagnóstico completado: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

// Guardar copia del diagnóstico
$diagnostic_file = __DIR__ . '/logs/diagnostic_' . date('Y-m-d_H-i-s') . '.txt';
file_put_contents($diagnostic_file, ob_get_contents());
echo "\n[Diagnóstico guardado en: $diagnostic_file]\n";

?>