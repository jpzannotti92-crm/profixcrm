<?php
/**
 * Validación Post-Instalación - ProfixCRM v6
 * Verifica que la instalación se completó correctamente
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== VALIDACIÓN POST-INSTALACIÓN PROFIXCRM V6 ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n\n";

$results = [];

// 1. Verificar archivos de Release v6
echo "1. VERIFICACIÓN DE ARCHIVOS DE V6\n";
$v6_files = [
    'public/api/auth/reset_admin.php',
    'public/api/auth/create_admin.php',
    'api/auth/reset_admin.php',
    'api/auth/create_admin.php',
    'diagnostic.php',
    'test_api.php',
    'validate_config.php',
    'post_install_validation.php'
];

foreach ($v6_files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✓ $file existe\n";
        $results['files'][$file] = 'ok';
    } else {
        echo "✗ $file NO EXISTE\n";
        $results['files'][$file] = 'missing';
    }
}
echo "\n";

// 2. Verificar endpoints de administrador
echo "2. VERIFICACIÓN DE ENDPOINTS DE ADMINISTRADOR\n";
$base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$endpoints = [
    '/api/auth/reset_admin.php',
    '/api/auth/create_admin.php',
    '/public/api/auth/reset_admin.php',
    '/public/api/auth/create_admin.php'
];

foreach ($endpoints as $endpoint) {
    $url = $base_url . $endpoint;
    $headers = @get_headers($url);
    
    if ($headers && strpos($headers[0], '200') !== false) {
        echo "✓ $endpoint - HTTP 200\n";
        $results['endpoints'][$endpoint] = 'ok';
    } elseif ($headers && strpos($headers[0], '404') !== false) {
        echo "✗ $endpoint - HTTP 404 (No encontrado)\n";
        $results['endpoints'][$endpoint] = '404';
    } else {
        echo "⚠️ $endpoint - " . ($headers ? $headers[0] : "Sin respuesta") . "\n";
        $results['endpoints'][$endpoint] = 'error';
    }
}
echo "\n";

// 3. Verificar configuración de base de datos
echo "3. VERIFICACIÓN DE CONFIGURACIÓN DE BASE DE DATOS\n";
try {
    // Intentar cargar configuración
    if (file_exists(__DIR__ . '/config/config.php')) {
        require_once __DIR__ . '/config/config.php';
        echo "✓ Config cargada\n";
        $results['config'] = 'ok';
        
        // Verificar conexión
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
            echo "✓ Constantes DB definidas\n";
            $results['db_constants'] = 'ok';
            
            try {
                $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                echo "✓ Conexión BD exitosa\n";
                $results['db_connection'] = 'ok';
                
                // Verificar usuario admin
                $admin = $pdo->query("SELECT id, username, email, status FROM users WHERE username='admin' OR email='admin@iatrade.com' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($admin) {
                    echo "✓ Usuario admin encontrado (Status: {$admin['status']})\n";
                    $results['admin_user'] = 'ok';
                } else {
                    echo "⚠️ Usuario admin no encontrado\n";
                    $results['admin_user'] = 'missing';
                }
                
                // Verificar usuario jpzannotti92
                $jpzan = $pdo->query("SELECT id, username, email, status FROM users WHERE username='jpzannotti92' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($jpzan) {
                    echo "✓ Usuario jpzannotti92 encontrado (Status: {$jpzan['status']})\n";
                    $results['jpzan_user'] = 'ok';
                } else {
                    echo "⚠️ Usuario jpzannotti92 no encontrado\n";
                    $results['jpzan_user'] = 'missing';
                }
                
            } catch (PDOException $e) {
                echo "✗ Error conexión BD: " . $e->getMessage() . "\n";
                $results['db_connection'] = 'error';
            }
        } else {
            echo "✗ Constantes DB incompletas\n";
            $results['db_constants'] = 'incomplete';
        }
    } else {
        echo "✗ Config no encontrada\n";
        $results['config'] = 'missing';
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    $results['config'] = 'error';
}
echo "\n";

// 4. Verificar API endpoints principales
echo "4. VERIFICACIÓN DE ENDPOINTS PRINCIPALES\n";
$main_endpoints = [
    '/api/health.php',
    '/api/auth/login.php',
    '/api/auth/verify.php',
    '/api/users.php',
    '/api/leads.php',
    '/api/dashboard.php'
];

foreach ($main_endpoints as $endpoint) {
    $url = $base_url . $endpoint;
    $headers = @get_headers($url);
    
    if ($headers && strpos($headers[0], '200') !== false) {
        echo "✓ $endpoint - HTTP 200\n";
        $results['main_endpoints'][$endpoint] = 'ok';
    } elseif ($headers && strpos($headers[0], '500') !== false) {
        echo "✗ $endpoint - HTTP 500 (Error interno)\n";
        $results['main_endpoints'][$endpoint] = '500';
    } else {
        echo "⚠️ $endpoint - " . ($headers ? $headers[0] : "Sin respuesta") . "\n";
        $results['main_endpoints'][$endpoint] = 'error';
    }
}
echo "\n";

// 5. Verificar permisos de directorios
echo "5. VERIFICACIÓN DE PERMISOS DE DIRECTORIOS\n";
$dirs = ['logs', 'uploads', 'temp', 'cache'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        if (is_writable($path)) {
            echo "✓ $dir/ - Permisos $perms (Escribible)\n";
            $results['dirs'][$dir] = 'writable';
        } else {
            echo "⚠️ $dir/ - Permisos $perms (No escribible)\n";
            $results['dirs'][$dir] = 'not_writable';
        }
    } else {
        echo "✗ $dir/ - No existe\n";
        $results['dirs'][$dir] = 'missing';
    }
}
echo "\n";

// 6. Resumen final
echo "6. RESUMEN DE VALIDACIÓN\n";
echo "==============================================\n";

// Contar errores
$errors = 0;
$warnings = 0;

foreach ($results as $category => $items) {
    if (is_array($items)) {
        foreach ($items as $item => $status) {
            if ($status === 'error' || $status === 'missing' || $status === '404' || $status === '500') {
                $errors++;
            } elseif ($status === 'warning' || $status === 'not_writable') {
                $warnings++;
            }
        }
    }
}

echo "✓ Archivos verificados: " . count($results['files']) . "\n";
echo "✓ Endpoints verificados: " . count($results['endpoints']) . "\n";
echo "✓ Endpoints principales: " . count($results['main_endpoints']) . "\n";
echo "✓ Errores encontrados: $errors\n";
echo "✓ Advertencias: $warnings\n";

if ($errors === 0 && $warnings === 0) {
    echo "\n🎉 ¡VALIDACIÓN EXITOSA! Todo está listo para producción.\n";
} elseif ($errors === 0) {
    echo "\n⚠️ Validación con advertencias. Revisar los puntos marcados.\n";
} else {
    echo "\n❌ Validación con errores. Se requieren correcciones antes de producción.\n";
}

echo "\n==============================================\n";
echo "Validación completada: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n";

// Guardar resultados
$result_file = __DIR__ . '/logs/post_install_validation_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($result_file, json_encode($results, JSON_PRETTY_PRINT));
echo "\n[Resultados guardados en: $result_file]\n";

?>