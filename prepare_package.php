<?php
/**
 * Script simplificado para preparar paquete de despliegue - ProfixCRM v8
 * 
 * USO:
 *   php prepare_package.php
 */

echo "🚀 Preparando paquete de despliegue ProfixCRM v8...\n\n";

// Configuración
$deploymentDir = __DIR__ . '/deployment_package';
$logFile = __DIR__ . '/logs/deployment_' . date('Y-m-d_H-i-s') . '.log';

// Archivos y carpetas que SE DEBEN subir
$productionFiles = [
    // Carpetas principales
    'config',
    'src', 
    'public',
    'api',
    'vendor',
    'views',
    'storage',
    'temp',
    'logs',
    'cache',
    'backups',
    
    // Archivos individuales importantes
    'index.php',
    'validate_v8.php',
    'deploy_v8.php',
    '.htaccess',
    'composer.json',
    'composer.lock',
    'favicon.ico'
];

// Archivos que NO deben subirse
$excludedPatterns = [
    // Archivos de prueba
    'test_*.php',
    'debug_*.php', 
    'check_*.php',
    'fix_*.php',
    'add_*.php',
    'prepare_*.php',
    'deploy_production.php',
    
    // Archivos de datos
    '*.csv',
    '*.json',
    '*.txt',
    '*.backup_*',
    '*.sql',
    
    // Documentación
    '*.md',
    'README*',
    'docs/',
    'deployment_package/',
    
    // Archivos del sistema
    '.env',
    '.env.*',
    '.installed',
    'node_modules/',
    '.git/',
    '.gitignore',
    
    // Archivos temporales
    '*.tmp',
    '*.temp',
    '*.log',
    '*.cache'
];

// Función para verificar si un archivo debe ser excluido
function shouldExclude($file, $patterns) {
    $filename = basename($file);
    
    foreach ($patterns as $pattern) {
        if (fnmatch($pattern, $filename)) {
            return true;
        }
        if (fnmatch($pattern, $file)) {
            return true;
        }
    }
    
    return false;
}

// Función para copiar directorio recursivamente
function copyDirectory($source, $dest, $excludedPatterns) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $success = true;
    foreach ($iterator as $item) {
        $destPath = $dest . '/' . $iterator->getSubPathName();
        
        if ($item->isDir()) {
            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }
        } else {
            // Verificar si el archivo debe ser excluido
            if (shouldExclude($item->getPathname(), $excludedPatterns)) {
                continue;
            }
            
            if (!copy($item->getPathname(), $destPath)) {
                $success = false;
                echo "   Error copiando: " . $item->getPathname() . "\n";
            }
        }
    }
    
    return $success;
}

// Función para limpiar directorio
function cleanDirectory($dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                cleanDirectory($file);
                rmdir($file);
            } else {
                unlink($file);
            }
        }
    }
}

// Crear directorio de despliegue
if (!is_dir($deploymentDir)) {
    mkdir($deploymentDir, 0755, true);
}

// Limpiar directorio anterior
echo "🧹 Limpiando directorio anterior...\n";
cleanDirectory($deploymentDir);

$copied = 0;
$errors = 0;

// Copiar carpetas y archivos principales
echo "📦 Copiando archivos...\n";
foreach ($productionFiles as $item) {
    if (file_exists($item)) {
        if (is_dir($item)) {
            echo "📁 Copiando carpeta: $item\n";
            if (copyDirectory($item, $deploymentDir . '/' . $item, $excludedPatterns)) {
                $copied++;
            } else {
                $errors++;
            }
        } elseif (is_file($item)) {
            echo "📄 Copiando archivo: $item\n";
            if (!shouldExclude($item, $excludedPatterns)) {
                if (copy($item, $deploymentDir . '/' . $item)) {
                    $copied++;
                } else {
                    $errors++;
                }
            }
        }
    } else {
        echo "⚠️  No encontrado: $item\n";
    }
}

// Crear carpetas necesarias adicionales
$additionalDirs = [
    'logs/v8',
    'storage/cache',
    'temp/v8',
    'cache/v8'
];

echo "📁 Creando carpetas adicionales...\n";
foreach ($additionalDirs as $dir) {
    $fullPath = $deploymentDir . '/' . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
        echo "✅ Creada: $dir\n";
    }
}

// Crear archivo de instrucciones
$instructions = <<<INSTRUCCIONES
🚀 INSTRUCCIONES DE SUBIDA - PROFIXCRM V8
=====================================

📋 PASOS PARA SUBIR A PRODUCCIÓN:

1️⃣  SUBIR ARCHIVOS
   • Sube TODO el contenido de este paquete a tu servidor
   • Mantén la estructura de carpetas intacta
   • El directorio raíz debe contener index.php

2️⃣  CONFIGURAR PERMISOS (IMPORTANTE)
   chmod 755 -R logs/
   chmod 755 -R storage/
   chmod 755 -R temp/
   chmod 755 -R cache/
   chmod 644 config/v8_config.php

3️⃣  CONFIGURAR BASE DE DATOS
   • Crea una base de datos MySQL
   • Actualiza config/v8_config.php con tus credenciales
   • Importa tu respaldo de la versión V7 si aplica

4️⃣  VALIDAR INSTALACIÓN
   • Accede a: https://tudominio.com/validate_v8.php
   • Debe mostrar "VALIDACIÓN COMPLETADA"
   • Si hay errores, corrígelos antes de continuar

5️⃣  ACTIVAR MODO PRODUCCIÓN
   • En config/v8_config.php cambia APP_ENV=production
   • Desactiva el modo debug

⚠️  ARCHIVOS IMPORTANTES:
   • validate_v8.php → Para validar tu instalación
   • deploy_v8.php → Para ejecutar migraciones
   • config/v8_config.php → Configuración principal

📞 EN CASO DE PROBLEMAS:
   1. Verifica los logs en logs/v8/
   2. Ejecuta validate_v8.php para diagnosticar
   3. Revisa los permisos de carpetas
   4. Contacta soporte técnico

INSTRUCCIONES;

file_put_contents($deploymentDir . '/INSTRUCCIONES_SUBIDA.txt', $instructions);

// Crear archivo ZIP
echo "\n📦 Creando archivo ZIP...\n";
$zipFile = 'profixcrm_v8_deployment_' . date('Y-m-d_H-i-s') . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($deploymentDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($deploymentDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    
    $zip->close();
    echo "✅ Archivo ZIP creado: $zipFile\n";
} else {
    echo "❌ Error creando archivo ZIP\n";
}

echo "\n📊 RESUMEN DE PREPARACIÓN:\n";
echo "✅ Elementos copiados: $copied\n";
echo "❌ Errores: $errors\n";
echo "📂 Paquete creado en: $deploymentDir/\n";
echo "📋 Instrucciones en: $deploymentDir/INSTRUCCIONES_SUBIDA.txt\n";
if (file_exists($zipFile)) {
    echo "📦 Archivo ZIP: $zipFile\n";
}

echo "\n🎉 ¡Paquete de despliegue preparado exitosamente!\n";
echo "📤 Ahora puedes subir el contenido de '$deploymentDir/' a tu servidor de producción.\n";