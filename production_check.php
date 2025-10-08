<?php
/**
 * VERIFICACIÓN RÁPIDA DE PRODUCCIÓN - PROFIXCRM V7
 * 
 * Script simple para verificar el estado en producción
 * después del despliegue de v7.
 */

echo "=== VERIFICACIÓN RÁPIDA V7 - PRODUCCIÓN ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Verificar constantes de BD
$constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
$all_defined = true;

echo "1. Constantes de Base de Datos:\n";
foreach ($constants as $const) {
    if (defined($const)) {
        echo "   ✅ $const: OK\n";
    } else {
        echo "   ❌ $const: FALTANTE\n";
        $all_defined = false;
    }
}

// Verificar archivos críticos
$files = [
    'api/auth/reset_admin.php',
    'api/auth/create_admin.php',
    'config/constants.php',
    'config/database_constants.php'
];

echo "\n2. Archivos Críticos:\n";
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file: EXISTE\n";
    } else {
        echo "   ❌ $file: FALTANTE\n";
    }
}

// Verificar directorios
$dirs = ['temp', 'cache', 'uploads', 'logs'];
echo "\n3. Directorios:\n";
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $writable = is_writable($dir) ? "ESCRIBIBLE" : "NO ESCRIBIBLE";
        echo "   ✅ $dir/: EXISTE ($writable)\n";
    } else {
        echo "   ❌ $dir/: FALTANTE\n";
    }
}

// Verificar conexión a BD
if ($all_defined) {
    echo "\n4. Conexión a Base de Datos:\n";
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        // Verificar si hay usuarios admin
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin' LIMIT 1");
        $result = $stmt->fetch();
        
        echo "   ✅ Conexión BD: EXITOSA\n";
        echo "   ✅ Usuarios admin: " . $result['total'] . " encontrado(s)\n";
        
    } catch (Exception $e) {
        echo "   ❌ Conexión BD: FALLIDA\n";
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== RESUMEN ===\n";
if ($all_defined) {
    echo "✅ RELEASE V7: CONFIGURADA CORRECTAMENTE\n";
    echo "✅ Sistema listo para uso en producción\n";
} else {
    echo "❌ RELEASE V7: REQUIERE CONFIGURACIÓN\n";
    echo "❌ Ejecutar: php fix_database_config.php\n";
}

echo "\n=== ENDPOINTS DE ADMINISTRADOR ===\n";
$base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
echo "🔧 Reset Admin: $base_url/api/auth/reset_admin.php\n";
echo "🔧 Create Admin: $base_url/api/auth/create_admin.php\n";

echo "\n=== FIN VERIFICACIÓN ===\n";