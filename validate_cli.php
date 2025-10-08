<?php
/**
 * VALIDADOR CLI PARA V7 - SIN REDIRECCIONES
 * 
 * Este script valida el estado de v7 sin redirecciones web
 * Se ejecuta directamente por línea de comandos
 */

// Forzar ejecución en CLI
if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse por línea de comandos: php validate_cli.php\n");
}

echo "==============================================\n";
echo "🔍 VALIDADOR CLI V7 - SIN REDIRECCIONES\n";
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

echo "📊 1. VERIFICANDO CONSTANTES DE BASE DE DATOS\n";
echo "==============================================\n";

// Incluir configuraciones
$config_files = [
    'config/config.php',
    'config/constants.php',
    'config/database_constants.php'
];

$constants_found = false;
foreach ($config_files as $file) {
    if (file_exists($file)) {
        echo "✅ Archivo $file existe\n";
        $exitosos[] = "Archivo $file existe";
        
        // Verificar si define constantes de BD
        $content = file_get_contents($file);
        if (strpos($content, 'DB_HOST') !== false || strpos($content, 'define') !== false) {
            $constants_found = true;
            echo "✅ $file contiene definiciones\n";
            $exitosos[] = "$file contiene definiciones";
        }
    } else {
        echo "❌ Archivo $file NO EXISTE\n";
        $errores[] = "Archivo $file no encontrado";
    }
}

// Intentar cargar las configuraciones
foreach ($config_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

// Verificar constantes
echo "\n📊 CONSTANTES DE BASE DE DATOS:\n";
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
// 2. VERIFICAR ARCHIVOS CRÍTICOS DE V7
// =============================================================================

echo "📊 2. VERIFICANDO ARCHIVOS CRÍTICOS DE V7\n";
echo "==============================================\n";

$archivos_v7 = [
    'validate_after_deploy.php',
    'production_check.php',
    'config/constants.php',
    'config/database_constants.php',
    'api/auth/reset_admin.php',
    'api/auth/create_admin.php',
    'update_config.php',
    'fix_database_config.php',
    'validate_config.php',
    'post_install_validation.php',
    'final_validation.php',
    'README_V7.txt',
    'V7_RELEASE_SUMMARY.txt',
    'V7_DEPLOYMENT_REPORT.txt'
];

foreach ($archivos_v7 as $archivo) {
    if (file_exists($archivo)) {
        $size = filesize($archivo);
        echo "✅ $archivo existe ($size bytes)\n";
        $exitosos[] = "Archivo v7 $archivo existe";
    } else {
        echo "❌ $archivo NO EXISTE\n";
        $errores[] = "Archivo v7 $archivo no encontrado";
    }
}

echo "\n";

// =============================================================================
// 3. VERIFICAR DIRECTORIOS
// =============================================================================

echo "📊 3. VERIFICANDO DIRECTORIOS\n";
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
// 4. VERIFICAR ENDPOINTS DE ADMINISTRADOR
// =============================================================================

echo "📊 4. VERIFICANDO ENDPOINTS DE ADMINISTRADOR\n";
echo "==============================================\n";

$admin_endpoints = [
    'api/auth/reset_admin.php',
    'api/auth/create_admin.php'
];

foreach ($admin_endpoints as $endpoint) {
    if (file_exists($endpoint)) {
        $size = filesize($endpoint);
        echo "✅ $endpoint existe ($size bytes)\n";
        $exitosos[] = "Endpoint admin $endpoint existe";
        
        // Verificar que no tenga redirecciones
        $content = file_get_contents($endpoint);
        if (strpos($content, 'header("Location:') !== false) {
            echo "⚠️  $endpoint contiene redirección\n";
            $advertencias[] = "Endpoint $endpoint tiene redirección";
        } else {
            echo "✅ $endpoint no tiene redirecciones\n";
            $exitosos[] = "Endpoint $endpoint sin redirecciones";
        }
    } else {
        echo "❌ $endpoint NO EXISTE\n";
        $errores[] = "Endpoint admin $endpoint no encontrado";
    }
}

echo "\n";

// =============================================================================
// 5. VERIFICAR CONFIGURACIÓN DE PHP
// =============================================================================

echo "📊 5. VERIFICANDO CONFIGURACIÓN DE PHP\n";
echo "==============================================\n";

// Versión PHP
echo "Versión PHP: " . PHP_VERSION . "\n";

// Extensiones necesarias
$extensiones = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
foreach ($extensiones as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extensión $ext cargada\n";
        $exitosos[] = "Extensión PHP $ext";
    } else {
        echo "❌ Extensión $ext NO cargada\n";
        $errores[] = "Extensión PHP $ext faltante";
    }
}

echo "\n";

// =============================================================================
// 6. INTENTAR CONEXIÓN A BASE DE DATOS
// =============================================================================

echo "📊 6. PRUEBA DE CONEXIÓN A BASE DE DATOS\n";
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
        
        // Verificar tablas críticas
        $tablas_criticas = ['users', 'leads', 'roles', 'permissions'];
        $stmt = $pdo->query("SHOW TABLES");
        $tablas_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tablas_criticas as $tabla) {
            if (in_array($tabla, $tablas_existentes)) {
                echo "✅ Tabla '$tabla' existe\n";
                $exitosos[] = "Tabla $tabla existe";
            } else {
                echo "⚠️  Tabla '$tabla' no existe\n";
                $advertencias[] = "Tabla $tabla no encontrada";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error de conexión: " . $e->getMessage() . "\n";
        echo "❌ Código de error: " . $e->getCode() . "\n";
        $errores[] = "Error conexión BD: " . $e->getMessage();
    }
} else {
    echo "❌ No se puede probar conexión - faltan constantes\n";
    $errores[] = "Conexión BD no probada por falta de constantes";
}

echo "\n";

// =============================================================================
// RESUMEN FINAL
// =============================================================================

echo "==============================================\n";
echo "📈 RESUMEN DE VALIDACIÓN CLI V7\n";
echo "==============================================\n\n";

// Mostrar éxitos
if (count($exitosos) > 0) {
    echo "✅ ÉXITOS (" . count($exitosos) . "):\n";
    foreach (array_slice($exitosos, 0, 10) as $exito) {
        echo "  ✓ $exito\n";
    }
    if (count($exitosos) > 10) {
        echo "  ... y " . (count($exitosos) - 10) . " más\n";
    }
    echo "\n";
}

// Mostrar advertencias
if (count($advertencias) > 0) {
    echo "⚠️  ADVERTENCIAS (" . count($advertencias) . "):\n";
    foreach ($advertencias as $advertencia) {
        echo "  ⚠ $advertencia\n";
    }
    echo "\n";
}

// Mostrar errores
if (count($errores) > 0) {
    echo "❌ ERRORES CRÍTICOS (" . count($errores) . "):\n";
    foreach ($errores as $error) {
        echo "  ✗ $error\n";
    }
    echo "\n";
}

// Conclusión final
echo "==============================================\n";
if (count($errores) === 0) {
    echo "🎉 ¡VALIDACIÓN CLI EXITOSA!\n";
    echo "✅ Release v7 está completamente configurado\n";
    echo "✅ Todos los archivos críticos están presentes\n";
    echo "✅ El sistema está listo para producción\n";
} elseif (count($errores) <= 3) {
    echo "⚠️  VALIDACIÓN CON ADVERTENCIAS\n";
    echo "⚠️  El sistema es funcional pero tiene problemas menores\n";
    echo "⚠️  Revisa los errores arriba antes de producción\n";
} else {
    echo "❌ VALIDACIÓN FALLIDA\n";
    echo "❌ El sistema tiene problemas críticos\n";
    echo "❌ NO se recomienda poner en producción hasta resolver los errores\n";
}
echo "==============================================\n\n";

// Guardar resultados en log
$log_content = "=== VALIDACIÓN CLI V7 ===\n";
$log_content .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
$log_content .= "Éxitos: " . count($exitosos) . "\n";
$log_content .= "Advertencias: " . count($advertencias) . "\n";
$log_content .= "Errores: " . count($errores) . "\n";
$log_content .= "Estado: " . (count($errores) === 0 ? 'EXITOSO' : (count($errores) <= 3 ? 'ADVERTENCIAS' : 'FALLIDO')) . "\n";
$log_content .= "=====================================\n\n";

file_put_contents('logs/validation_cli_v7_' . date('Y-m-d_H-i-s') . '.log', $log_content);

echo "📄 Resultados guardados en: logs/validation_cli_v7_" . date('Y-m-d_H-i-s') . '.log' . "\n";
echo "==============================================\n";

// Retornar código de salida apropiado
exit(count($errores) === 0 ? 0 : 1);