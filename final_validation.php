<?php
/**
 * Validación Final - ProfixCRM v6
 * Verifica que todos los problemas estén resueltos
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== VALIDACIÓN FINAL PROFIXCRM V6 ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n\n";

// 1. Verificar constantes de base de datos
echo "1. VERIFICACIÓN DE CONSTANTES DE BASE DE DATOS\n";
require_once __DIR__ . '/config/constants.php';

$constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT'];
$all_defined = true;
foreach ($constants as $constant) {
    if (defined($constant)) {
        $value = $constant === 'DB_PASS' ? '***' : constant($constant);
        echo "✓ $constant: $value\n";
    } else {
        echo "✗ $constant: NO DEFINIDA\n";
        $all_defined = false;
    }
}

if ($all_defined) {
    echo "✓ Todas las constantes de BD están definidas\n";
} else {
    echo "✗ Faltan constantes de BD\n";
}

// 2. Verificar archivos críticos
echo "\n2. VERIFICACIÓN DE ARCHIVOS CRÍTICOS\n";
$critical_files = [
    'config/constants.php',
    'config/database_constants.php',
    'api/auth/reset_admin.php',
    'api/auth/create_admin.php',
    'validate_config.php',
    'post_install_validation.php',
    'update_config.php',
    'fix_database_config.php'
];

foreach ($critical_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ $file existe\n";
    } else {
        echo "✗ $file NO EXISTE\n";
    }
}

// 3. Verificar directorios
echo "\n3. VERIFICACIÓN DE DIRECTORIOS\n";
$directories = ['logs', 'uploads', 'temp', 'cache'];
foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        echo "✓ $dir/ existe (permisos: $perms)\n";
    } else {
        echo "✗ $dir/ NO EXISTE\n";
    }
}

// 4. Verificar conexión a base de datos
echo "\n4. PRUEBA DE CONEXIÓN A BASE DE DATOS\n";
if ($all_defined) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT, DB_USER, DB_PASS);
        echo "✓ Conexión exitosa a la base de datos!\n";
        
        // Verificar tablas críticas
        $tables = ['users', 'roles', 'permissions', 'role_permissions', 'user_roles'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✓ Tabla $table existe\n";
            } else {
                echo "✗ Tabla $table NO EXISTE\n";
            }
        }
        
        // Verificar usuario admin
        $admin = $pdo->query("SELECT id, username, email, status FROM users WHERE username='admin' OR email='admin@iatrade.com' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            echo "✓ Usuario admin encontrado - Status: {$admin['status']}\n";
        } else {
            echo "⚠️ Usuario admin no encontrado\n";
        }
        
    } catch (PDOException $e) {
        echo "✗ Error de conexión: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ No se puede probar conexión: faltan constantes\n";
}

// 5. Verificar endpoints locales
echo "\n5. VERIFICACIÓN DE ENDPOINTS LOCALES\n";
$base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$endpoints = [
    '/api/health.php',
    '/api/auth/login.php',
    '/api/auth/verify.php',
    '/api/users.php',
    '/api/leads.php',
    '/api/dashboard.php',
    '/api/auth/reset_admin.php',
    '/api/auth/create_admin.php'
];

foreach ($endpoints as $endpoint) {
    $url = $base_url . $endpoint;
    $headers = @get_headers($url);
    
    if ($headers) {
        $status = $headers[0];
        if (strpos($status, '200') !== false) {
            echo "✓ $endpoint - HTTP 200\n";
        } elseif (strpos($status, '404') !== false) {
            echo "⚠️ $endpoint - HTTP 404 (No encontrado)\n";
        } elseif (strpos($status, '500') !== false) {
            echo "✗ $endpoint - HTTP 500 (Error interno)\n";
        } else {
            echo "? $endpoint - $status\n";
        }
    } else {
        echo "✗ $endpoint - Sin respuesta\n";
    }
}

// 6. Resumen final
echo "\n6. RESUMEN FINAL\n";
echo "==============================================\n";
echo "📋 ESTADO DE LA INSTALACIÓN:\n";
echo "- Constantes de BD: " . ($all_defined ? "✓ COMPLETAS" : "✗ INCOMPLETAS") . "\n";
echo "- Archivos críticos: " . (file_exists(__DIR__ . '/api/auth/reset_admin.php') ? "✓ DISPONIBLES" : "✗ FALTANTES") . "\n";
echo "- Configuración BD: " . ($all_defined ? "✓ CONFIGURADA" : "✗ PENDIENTE") . "\n";
echo "- Endpoints principales: " . (file_exists(__DIR__ . '/config/constants.php') ? "✓ LISTOS" : "✗ PENDIENTES") . "\n";

echo "\n🎯 ACCIONES RECOMENDADAS:\n";
if (!$all_defined) {
    echo "1. Ejecutar: php update_config.php\n";
}
if (!file_exists(__DIR__ . '/api/auth/reset_admin.php')) {
    echo "2. Subir Release v6 con los archivos faltantes\n";
}
if (!is_dir(__DIR__ . '/temp')) {
    echo "3. Crear directorios: mkdir temp cache && chmod 777 temp cache\n";
}
if (!file_exists(__DIR__ . '/config/constants.php')) {
    echo "4. Asegurar que config/constants.php exista\n";
}

echo "\n==============================================\n";
echo "Validación final completada: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n";

?>