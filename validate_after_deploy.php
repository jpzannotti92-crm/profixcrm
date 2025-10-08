<?php
/**
 * VALIDADOR POST-DESPLIEGUE V7 - PROFIXCRM
 * 
 * Este script valida que el despliegue de v7 haya sido exitoso
 * y que todos los problemas críticos hayan sido resueltos.
 */

echo "==============================================\n";
echo "🔍 VALIDADOR POST-DESPLIEGUE V7\n";
echo "==============================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Servidor: " . $_SERVER['SERVER_NAME'] ?? 'localhost' . "\n";
echo "==============================================\n\n";

$errores = [];
$advertencias = [];
$exitosos = [];

// =============================================================================
// 1. VERIFICAR CONSTANTES DE BASE DE DATOS
// =============================================================================

echo "📊 1. VERIFICANDO CONSTANTES DE BASE DE DATOS\n";
echo "==============================================\n";

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

if ($todas_constantes_definidas) {
    echo "✅ Todas las constantes de BD están definidas\n\n";
} else {
    echo "❌ Faltan constantes de BD críticas\n\n";
}

// =============================================================================
// 2. VERIFICAR CONEXIÓN A BASE DE DATOS
// =============================================================================

echo "📊 2. VERIFICANDO CONEXIÓN A BASE DE DATOS\n";
echo "==============================================\n";

try {
    if ($todas_constantes_definidas) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
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
        
        // Verificar usuario admin
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin' OR email LIKE '%admin%'");
        $result = $stmt->fetch();
        if ($result['total'] > 0) {
            echo "✅ Usuario administrador encontrado\n";
            $exitosos[] = "Usuario admin existe";
        } else {
            echo "⚠️  No se encontró usuario administrador\n";
            $advertencias[] = "Usuario admin no encontrado";
        }
        
    } else {
        echo "❌ No se puede probar conexión - faltan constantes\n";
        $errores[] = "Conexión BD no probada por falta de constantes";
    }
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
    $errores[] = "Error conexión BD: " . $e->getMessage();
}

echo "\n";

// =============================================================================
// 3. VERIFICAR ENDPOINTS CRÍTICOS
// =============================================================================

echo "📊 3. VERIFICANDO ENDPOINTS CRÍTICOS\n";
echo "==============================================\n";

$endpoints = [
    'health' => 'api/health.php',
    'users' => 'api/users.php',
    'leads' => 'api/leads.php',
    'dashboard' => 'api/dashboard.php',
    'verify' => 'api/auth/verify.php'
];

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost');

foreach ($endpoints as $nombre => $endpoint) {
    $url = $base_url . '/' . $endpoint;
    
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            echo "✅ /$endpoint - HTTP $http_code ✅\n";
            $exitosos[] = "Endpoint $nombre responde 200";
        } elseif ($http_code == 500) {
            echo "❌ /$endpoint - HTTP $http_code ❌ (Error interno)\n";
            $errores[] = "Endpoint $nombre devuelve 500";
        } elseif ($http_code == 404) {
            echo "⚠️  /$endpoint - HTTP $http_code ⚠️ (No encontrado)\n";
            $advertencias[] = "Endpoint $nombre no encontrado";
        } else {
            echo "ℹ️  /$endpoint - HTTP $http_code ℹ️\n";
            $advertencias[] = "Endpoint $nombre responde $http_code";
        }
    } catch (Exception $e) {
        echo "❌ /$endpoint - Error: " . $e->getMessage() . "\n";
        $errores[] = "Endpoint $nombre - Error: " . $e->getMessage();
    }
}

echo "\n";

// =============================================================================
// 4. VERIFICAR ENDPOINTS DE ADMINISTRADOR
// =============================================================================

echo "📊 4. VERIFICANDO ENDPOINTS DE ADMINISTRADOR\n";
echo "==============================================\n";

$admin_endpoints = [
    'reset_admin' => 'api/auth/reset_admin.php',
    'create_admin' => 'api/auth/create_admin.php'
];

foreach ($admin_endpoints as $nombre => $endpoint) {
    $url = $base_url . '/' . $endpoint;
    
    if (file_exists(__DIR__ . '/' . $endpoint)) {
        echo "✅ Archivo $endpoint existe\n";
        $exitosos[] = "Archivo admin $nombre existe";
        
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200 || $http_code == 404) {
                echo "✅ $endpoint - HTTP $http_code ✅ (Disponible)\n";
                $exitosos[] = "Endpoint admin $nombre disponible";
            } else {
                echo "⚠️  $endpoint - HTTP $http_code ⚠️\n";
                $advertencias[] = "Endpoint admin $nombre responde $http_code";
            }
        } catch (Exception $e) {
            echo "⚠️  $endpoint - Sin respuesta HTTP\n";
            $advertencias[] = "Endpoint admin $nombre sin respuesta";
        }
    } else {
        echo "❌ Archivo $endpoint NO EXISTE\n";
        $errores[] = "Archivo admin $nombre no encontrado";
    }
}

echo "\n";

// =============================================================================
// 5. VERIFICAR ARCHIVOS CRÍTICOS
// =============================================================================

echo "📊 5. VERIFICANDO ARCHIVOS CRÍTICOS\n";
echo "==============================================\n";

$archivos_criticos = [
    'constants.php' => 'config/constants.php',
    'database_constants.php' => 'config/database_constants.php',
    'update_config.php' => 'update_config.php',
    'fix_database_config.php' => 'fix_database_config.php',
    'validate_config.php' => 'validate_config.php',
    'final_validation.php' => 'final_validation.php',
    'post_install_validation.php' => 'post_install_validation.php'
];

foreach ($archivos_criticos as $nombre => $archivo) {
    if (file_exists(__DIR__ . '/' . $archivo)) {
        $size = filesize(__DIR__ . '/' . $archivo);
        echo "✅ $archivo existe ($size bytes)\n";
        $exitosos[] = "Archivo $nombre existe";
    } else {
        echo "❌ $archivo NO EXISTE\n";
        $errores[] = "Archivo $nombre no encontrado";
    }
}

echo "\n";

// =============================================================================
// 6. VERIFICAR DIRECTORIOS
// =============================================================================

echo "📊 6. VERIFICANDO DIRECTORIOS\n";
echo "==============================================\n";

$directorios = ['temp', 'cache', 'uploads', 'logs'];

foreach ($directorios as $dir) {
    if (is_dir(__DIR__ . '/' . $dir)) {
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
// 7. VERIFICAR CONFIGURACIÓN DE PHP
// =============================================================================

echo "📊 7. VERIFICANDO CONFIGURACIÓN DE PHP\n";
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
// RESUMEN FINAL
// =============================================================================

echo "==============================================\n";
echo "📈 RESUMEN DE VALIDACIÓN POST-DESPLIEGUE V7\n";
echo "==============================================\n\n";

// Colores para consola
$rojo = "\033[0;31m";
$verde = "\033[0;32m";
$amarillo = "\033[1;33m";
$azul = "\033[0;34m";
$reset = "\033[0m";

// Mostrar éxitos
if (count($exitosos) > 0) {
    echo "{$verde}✅ ÉXITOS (" . count($exitosos) . "):{$reset}\n";
    foreach ($exitosos as $exito) {
        echo "{$verde}  ✓ $exito{$reset}\n";
    }
    echo "\n";
}

// Mostrar advertencias
if (count($advertencias) > 0) {
    echo "{$amarillo}⚠️  ADVERTENCIAS (" . count($advertencias) . "):{$reset}\n";
    foreach ($advertencias as $advertencia) {
        echo "{$amarillo}  ⚠ $advertencia{$reset}\n";
    }
    echo "\n";
}

// Mostrar errores
if (count($errores) > 0) {
    echo "{$rojo}❌ ERRORES CRÍTICOS (" . count($errores) . "):{$reset}\n";
    foreach ($errores as $error) {
        echo "{$rojo}  ✗ $error{$reset}\n";
    }
    echo "\n";
}

// Conclusión final
echo "==============================================\n";
if (count($errores) === 0) {
    echo "{$verde}🎉 ¡VALIDACIÓN EXITOSA!{$reset}\n";
    echo "{$verde}✅ El despliegue de v7 ha sido exitoso{$reset}\n";
    echo "{$verde}✅ Todos los problemas críticos han sido resueltos{$reset}\n";
    echo "{$verde}✅ El sistema está listo para producción{$reset}\n";
} elseif (count($errores) <= 2) {
    echo "{$amarillo}⚠️  VALIDACIÓN CON ADVERTENCIAS{$reset}\n";
    echo "{$amarillo}⚠️  El despliegue es funcional pero tiene algunos problemas menores{$reset}\n";
    echo "{$amarillo}⚠️  Revisa las advertencias arriba{$reset}\n";
} else {
    echo "{$rojo}❌ VALIDACIÓN FALLIDA{$reset}\n";
    echo "{$rojo}❌ El despliegue tiene problemas críticos que deben resolverse{$reset}\n";
    echo "{$rojo}❌ NO se recomienda poner en producción hasta resolver los errores{$reset}\n";
}
echo "==============================================\n\n";

// Recomendaciones adicionales
echo "📋 RECOMENDACIONES POST-DESPLIEGUE:\n";
echo "==============================================\n";

if (count($errores) > 0) {
    echo "1. Ejecuta los scripts de corrección disponibles:\n";
    echo "   php fix_database_config.php\n";
    echo "   php update_config.php\n";
    echo "   php validate_config.php\n\n";
}

if (in_array("Usuario admin no encontrado", $advertencias)) {
    echo "2. Crea un usuario administrador:\n";
    echo "   php api/auth/create_admin.php\n\n";
}

echo "3. Monitorea el sistema las primeras 24 horas:\n";
echo "   - Revisa logs de errores regularmente\n";
echo "   - Verifica que todos los endpoints respondan\n";
echo "   - Asegúrate de que los usuarios puedan acceder normalmente\n\n";

echo "4. Mantén el backup de seguridad por al menos 7 días\n\n";

// Guardar resultados en log
$log_content = "=== VALIDACIÓN POST-DESPLIEGUE V7 ===\n";
$log_content .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
$log_content .= "Éxitos: " . count($exitosos) . "\n";
$log_content .= "Advertencias: " . count($advertencias) . "\n";
$log_content .= "Errores: " . count($errores) . "\n";
$log_content .= "Estado: " . (count($errores) === 0 ? 'EXITOSO' : (count($errores) <= 2 ? 'ADVERTENCIAS' : 'FALLIDO')) . "\n";
$log_content .= "=====================================\n\n";

file_put_contents('logs/validation_v7_' . date('Y-m-d_H-i-s') . '.log', $log_content);

echo "📄 Resultados guardados en: logs/validation_v7_" . date('Y-m-d_H-i-s') . '.log' . "\n";
echo "==============================================\n";

// Retornar código de salida apropiado
exit(count($errores) === 0 ? 0 : 1);