<?php
/**
 * PRODUCTION CHECK CLI PARA V7 - SIN REDIRECCIONES
 * 
 * Este script realiza una verificación rápida del estado de producción
 * Se ejecuta directamente por línea de comandos
 */

// Forzar ejecución en CLI
if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse por línea de comandos: php production_check_cli.php\n");
}

echo "==============================================\n";
echo "🔍 PRODUCTION CHECK CLI V7 - VERIFICACIÓN RÁPIDA\n";
echo "==============================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Ejecutando en: " . getcwd() . "\n";
echo "==============================================\n\n";

$errores = [];
$advertencias = [];
$exitosos = [];

// =============================================================================
// 1. VERIFICAR CONSTANTES DE BASE DE DATOS
// =============================================================================

echo "📊 1. CONSTANTES DE BASE DE DATOS\n";
echo "==============================================\n";

// Cargar configuraciones
$config_files = ['config/config.php', 'config/constants.php', 'config/database_constants.php'];
foreach ($config_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

$constantes_bd = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
$todas_constantes_definidas = true;

foreach ($constantes_bd as $constante) {
    if (defined($constante)) {
        echo "✅ $constante: " . constant($constante) . "\n";
        $exitosos[] = "Constante $constante definida";
    } else {
        echo "❌ $constante: NO DEFINIDA\n";
        $errores[] = "Constante $constante no definida";
        $todas_constantes_definidas = false;
    }
}

echo "\n";

// =============================================================================
// 2. VERIFICAR ARCHIVOS CRÍTICOS
// =============================================================================

echo "📊 2. ARCHIVOS CRÍTICOS\n";
echo "==============================================\n";

$archivos_criticos = [
    'validate_after_deploy.php',
    'production_check.php',
    'config/constants.php',
    'config/database_constants.php',
    'api/auth/reset_admin.php',
    'api/auth/create_admin.php'
];

foreach ($archivos_criticos as $archivo) {
    if (file_exists($archivo)) {
        echo "✅ $archivo existe\n";
        $exitosos[] = "Archivo crítico $archivo existe";
    } else {
        echo "❌ $archivo NO EXISTE\n";
        $errores[] = "Archivo crítico $archivo no encontrado";
    }
}

echo "\n";

// =============================================================================
// 3. VERIFICAR DIRECTORIOS Y PERMISOS
// =============================================================================

echo "📊 3. DIRECTORIOS Y PERMISOS\n";
echo "==============================================\n";

$directorios = ['temp', 'cache', 'uploads', 'logs'];

foreach ($directorios as $dir) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms(__DIR__ . '/' . $dir)), -4);
        echo "✅ $dir/ existe (permisos: $perms)\n";
        $exitosos[] = "Directorio $dir existe";
        
        if (is_writable(__DIR__ . '/' . $dir)) {
            echo "✅ $dir/ es escribible\n";
            $exitosos[] = "Directorio $dir escribible";
        } else {
            echo "⚠️  $dir/ NO es escribible\n";
            $advertencias[] = "Directorio $dir no escribible";
        }
    } else {
        echo "❌ $dir/ NO EXISTE\n";
        $errores[] = "Directorio $dir no encontrado";
    }
}

echo "\n";

// =============================================================================
// 4. PRUEBA DE CONEXIÓN A BASE DE DATOS
// =============================================================================

echo "📊 4. CONEXIÓN A BASE DE DATOS\n";
echo "==============================================\n";

if ($todas_constantes_definidas) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        echo "✅ Conexión a base de datos exitosa\n";
        $exitosos[] = "Conexión BD establecida";
        
        // Verificar usuario admin
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin' LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['total'] > 0) {
            echo "✅ Usuario administrador encontrado\n";
            $exitosos[] = "Usuario admin existe";
        } else {
            echo "⚠️  No se encontró usuario administrador\n";
            $advertencias[] = "Usuario admin no encontrado";
        }
        
    } catch (Exception $e) {
        echo "❌ Error de conexión: " . $e->getMessage() . "\n";
        $errores[] = "Error conexión BD: " . $e->getMessage();
    }
} else {
    echo "❌ No se puede probar conexión - faltan constantes\n";
    $errores[] = "Conexión BD no probada por falta de constantes";
}

echo "\n";

// =============================================================================
// 5. URLs DE ADMINISTRADOR
// =============================================================================

echo "📊 5. URLs DE ADMINISTRADOR\n";
echo "==============================================\n";

$base_url = "http://localhost/profixcrm"; // URL base local
$admin_urls = [
    'reset_admin' => "$base_url/api/auth/reset_admin.php",
    'create_admin' => "$base_url/api/auth/create_admin.php",
    'health_check' => "$base_url/api/health.php",
    'validate_after_deploy' => "$base_url/validate_after_deploy.php",
    'production_check' => "$base_url/production_check.php"
];

echo "URLs de administrador disponibles:\n";
foreach ($admin_urls as $name => $url) {
    echo "📍 $name: $url\n";
}

echo "\n";

// =============================================================================
// RESUMEN FINAL
// =============================================================================

echo "==============================================\n";
echo "📈 RESUMEN DE PRODUCTION CHECK CLI\n";
echo "==============================================\n\n";

echo "✅ ÉXITOS: " . count($exitosos) . "\n";
echo "⚠️  ADVERTENCIAS: " . count($advertencias) . "\n";
echo "❌ ERRORES: " . count($errores) . "\n\n";

// Estado general
if (count($errores) === 0) {
    echo "🎉 ¡SISTEMA LISTO PARA PRODUCCIÓN!\n";
    echo "✅ Todos los componentes críticos están configurados\n";
    echo "✅ La base de datos está conectada\n";
    echo "✅ Los endpoints de admin están disponibles\n";
} elseif (count($errores) <= 2) {
    echo "⚠️  SISTEMA FUNCIONAL CON ADVERTENCIAS\n";
    echo "⚠️  Hay algunos problemas menores pero el sistema es usable\n";
} else {
    echo "❌ SISTEMA NO LISTO PARA PRODUCCIÓN\n";
    echo "❌ Se encontraron problemas críticos que deben resolverse\n";
}

echo "\n";
echo "==============================================\n";
echo "📝 INSTRUCCIONES PARA ACCEDER A LOS SCRIPTS:\n";
echo "==============================================\n";
echo "1. Ejecuta estos scripts por línea de comandos:\n";
echo "   php validate_cli.php\n";
echo "   php production_check_cli.php\n";
echo "\n";
echo "2. Si necesitas acceder por web, primero resuelve las redirecciones:\n";
echo "   - Revisa public/index.php\n";
echo "   - Revisa src/Controllers/AuthController.php\n";
echo "   - Busca header('Location:') en el código\n";
echo "\n";
echo "3. Para desactivar redirecciones temporalmente, puedes:\n";
echo "   - Renombrar el archivo con redirección\n";
echo "   - Comentar las líneas de header('Location:')\n";
echo "   - Crear un .htaccess sin redirecciones\n";
echo "==============================================\n\n";

// Guardar resultados
$log_content = "=== PRODUCTION CHECK CLI V7 ===\n";
$log_content .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
$log_content .= "Éxitos: " . count($exitosos) . "\n";
$log_content .= "Advertencias: " . count($advertencias) . "\n";
$log_content .= "Errores: " . count($errores) . "\n";
$log_content .= "Estado: " . (count($errores) === 0 ? 'LISTO' : (count($errores) <= 2 ? 'ADVERTENCIAS' : 'NO LISTO')) . "\n";
$log_content .= "=====================================\n\n";

file_put_contents('logs/production_check_cli_' . date('Y-m-d_H-i-s') . '.log', $log_content);

echo "📄 Resultados guardados en: logs/production_check_cli_" . date('Y-m-d_H-i-s') . '.log' . "\n";
echo "==============================================\n";

exit(count($errores) === 0 ? 0 : 1);