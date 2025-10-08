<?php
/**
 * Script de Verificación Rápida de Sincronización
 * Herramienta ligera para verificar el estado de sincronización entre desarrollo y producción
 */

// Configuración de rutas
$devPath = __DIR__;
$prodPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'iatrade-crm-production';

// Colores para terminal
$colors = [
    'green' => "\033[32m",
    'red' => "\033[31m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'reset' => "\033[0m"
];

function colorText($text, $color, $colors) {
    return $colors[$color] . $text . $colors['reset'];
}

function printHeader() {
    global $colors;
    echo "\n";
    echo colorText("========================================", 'blue', $colors) . "\n";
    echo colorText("   VERIFICACIÓN DE SINCRONIZACIÓN", 'blue', $colors) . "\n";
    echo colorText("        iaTrade CRM - Sync Check", 'blue', $colors) . "\n";
    echo colorText("========================================", 'blue', $colors) . "\n\n";
}

function checkEnvironments($devPath, $prodPath, $colors) {
    echo colorText("🔍 Verificando entornos...", 'blue', $colors) . "\n";
    
    if (!is_dir($devPath)) {
        echo colorText("❌ Entorno de desarrollo no encontrado: $devPath", 'red', $colors) . "\n";
        return false;
    }
    
    if (!is_dir($prodPath)) {
        echo colorText("❌ Entorno de producción no encontrado: $prodPath", 'red', $colors) . "\n";
        return false;
    }
    
    echo colorText("✅ Entorno de desarrollo: OK", 'green', $colors) . "\n";
    echo colorText("✅ Entorno de producción: OK", 'green', $colors) . "\n\n";
    
    return true;
}

function scanFiles($path, $excludedDirs = [], $excludedFiles = []) {
    $files = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relativePath = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);
            
            // Verificar exclusiones
            $excluded = false;
            
            // Verificar directorios excluidos
            foreach ($excludedDirs as $dir) {
                if (strpos($relativePath, $dir . '/') === 0) {
                    $excluded = true;
                    break;
                }
            }
            
            // Verificar archivos excluidos
            if (!$excluded) {
                foreach ($excludedFiles as $pattern) {
                    if (fnmatch($pattern, basename($relativePath))) {
                        $excluded = true;
                        break;
                    }
                }
            }
            
            if (!$excluded) {
                $files[$relativePath] = [
                    'hash' => md5_file($file->getPathname()),
                    'size' => filesize($file->getPathname()),
                    'modified' => filemtime($file->getPathname())
                ];
            }
        }
    }
    
    return $files;
}

function compareFiles($devFiles, $prodFiles, $colors) {
    $changes = [
        'new' => [],
        'modified' => [],
        'deleted' => [],
        'identical' => 0
    ];
    
    // Comparar archivos de desarrollo con producción
    foreach ($devFiles as $file => $devInfo) {
        if (!isset($prodFiles[$file])) {
            $changes['new'][] = $file;
        } elseif ($prodFiles[$file]['hash'] !== $devInfo['hash']) {
            $changes['modified'][] = [
                'file' => $file,
                'dev_modified' => date('Y-m-d H:i:s', $devInfo['modified']),
                'prod_modified' => date('Y-m-d H:i:s', $prodFiles[$file]['modified'])
            ];
        } else {
            $changes['identical']++;
        }
    }
    
    // Buscar archivos eliminados
    foreach ($prodFiles as $file => $prodInfo) {
        if (!isset($devFiles[$file])) {
            $changes['deleted'][] = $file;
        }
    }
    
    return $changes;
}

function printSummary($changes, $colors) {
    echo colorText("📊 RESUMEN DE CAMBIOS", 'blue', $colors) . "\n";
    echo colorText("==================", 'blue', $colors) . "\n";
    
    $totalChanges = count($changes['new']) + count($changes['modified']) + count($changes['deleted']);
    
    if ($totalChanges === 0) {
        echo colorText("✅ Los entornos están sincronizados", 'green', $colors) . "\n";
        echo colorText("📄 Archivos idénticos: " . $changes['identical'], 'green', $colors) . "\n";
    } else {
        echo colorText("⚠️  Se encontraron $totalChanges cambios pendientes", 'yellow', $colors) . "\n";
        echo colorText("📄 Archivos idénticos: " . $changes['identical'], 'green', $colors) . "\n";
        
        if (count($changes['new']) > 0) {
            echo colorText("📄 Archivos nuevos: " . count($changes['new']), 'blue', $colors) . "\n";
        }
        
        if (count($changes['modified']) > 0) {
            echo colorText("✏️  Archivos modificados: " . count($changes['modified']), 'yellow', $colors) . "\n";
        }
        
        if (count($changes['deleted']) > 0) {
            echo colorText("🗑️  Archivos eliminados: " . count($changes['deleted']), 'red', $colors) . "\n";
        }
    }
    
    echo "\n";
}

function printDetails($changes, $colors, $showDetails = false) {
    if (!$showDetails) {
        echo colorText("💡 Usa --details para ver la lista completa de cambios", 'blue', $colors) . "\n\n";
        return;
    }
    
    echo colorText("📋 DETALLES DE CAMBIOS", 'blue', $colors) . "\n";
    echo colorText("====================", 'blue', $colors) . "\n";
    
    if (count($changes['new']) > 0) {
        echo colorText("\n📄 ARCHIVOS NUEVOS:", 'blue', $colors) . "\n";
        foreach ($changes['new'] as $file) {
            echo colorText("  + $file", 'green', $colors) . "\n";
        }
    }
    
    if (count($changes['modified']) > 0) {
        echo colorText("\n✏️  ARCHIVOS MODIFICADOS:", 'yellow', $colors) . "\n";
        foreach ($changes['modified'] as $change) {
            echo colorText("  ~ " . $change['file'], 'yellow', $colors) . "\n";
            echo "    Dev: " . $change['dev_modified'] . " | Prod: " . $change['prod_modified'] . "\n";
        }
    }
    
    if (count($changes['deleted']) > 0) {
        echo colorText("\n🗑️  ARCHIVOS ELIMINADOS:", 'red', $colors) . "\n";
        foreach ($changes['deleted'] as $file) {
            echo colorText("  - $file", 'red', $colors) . "\n";
        }
    }
    
    echo "\n";
}

function checkCriticalFiles($prodPath, $colors) {
    echo colorText("🔍 Verificando archivos críticos de producción...", 'blue', $colors) . "\n";
    
    $criticalFiles = [
        '.env.production' => 'Configuración de entorno',
        'config/production.php' => 'Configuración PHP',
        '.htaccess' => 'Configuración Apache',
        'index.php' => 'Archivo principal'
    ];
    
    $missing = [];
    
    foreach ($criticalFiles as $file => $description) {
        $filePath = $prodPath . DIRECTORY_SEPARATOR . $file;
        if (file_exists($filePath)) {
            echo colorText("✅ $description: OK", 'green', $colors) . "\n";
        } else {
            echo colorText("❌ $description: FALTANTE", 'red', $colors) . "\n";
            $missing[] = $file;
        }
    }
    
    if (count($missing) > 0) {
        echo colorText("\n⚠️  Archivos críticos faltantes:", 'yellow', $colors) . "\n";
        foreach ($missing as $file) {
            echo colorText("  - $file", 'red', $colors) . "\n";
        }
        echo colorText("\n💡 Ejecuta el asistente de despliegue para crear estos archivos", 'blue', $colors) . "\n";
    }
    
    echo "\n";
}

// Función principal
function main() {
    global $devPath, $prodPath, $colors;
    
    // Verificar argumentos
    $showDetails = in_array('--details', $argv ?? []);
    $jsonOutput = in_array('--json', $argv ?? []);
    
    if ($jsonOutput) {
        // Salida JSON para integración con otros sistemas
        $excludedDirs = ['logs', 'storage/logs', 'storage/cache', 'storage/sessions', 'node_modules', '.git', 'vendor', 'deploy', 'backups'];
        $excludedFiles = ['.env', '.env.local', '.env.development', 'config.php', 'database.php', '*.log', 'debug_*', 'test_*', 'check_*', '.installed', 'composer.lock'];
        
        $devFiles = scanFiles($devPath, $excludedDirs, $excludedFiles);
        $prodFiles = scanFiles($prodPath, $excludedDirs, $excludedFiles);
        $changes = compareFiles($devFiles, $prodFiles, $colors);
        
        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'dev_path' => $devPath,
            'prod_path' => $prodPath,
            'total_changes' => count($changes['new']) + count($changes['modified']) + count($changes['deleted']),
            'identical_files' => $changes['identical'],
            'changes' => [
                'new' => $changes['new'],
                'modified' => array_column($changes['modified'], 'file'),
                'deleted' => $changes['deleted']
            ],
            'sync_needed' => (count($changes['new']) + count($changes['modified']) + count($changes['deleted'])) > 0
        ];
        
        echo json_encode($result, JSON_PRETTY_PRINT);
        return;
    }
    
    printHeader();
    
    if (!checkEnvironments($devPath, $prodPath, $colors)) {
        exit(1);
    }
    
    echo colorText("🔄 Escaneando archivos...", 'blue', $colors) . "\n";
    
    // Configurar exclusiones
    $excludedDirs = ['logs', 'storage/logs', 'storage/cache', 'storage/sessions', 'node_modules', '.git', 'vendor', 'deploy', 'backups'];
    $excludedFiles = ['.env', '.env.local', '.env.development', 'config.php', 'database.php', '*.log', 'debug_*', 'test_*', 'check_*', '.installed', 'composer.lock'];
    
    $devFiles = scanFiles($devPath, $excludedDirs, $excludedFiles);
    $prodFiles = scanFiles($prodPath, $excludedDirs, $excludedFiles);
    
    echo colorText("✅ Escaneo completado", 'green', $colors) . "\n";
    echo "   Desarrollo: " . count($devFiles) . " archivos\n";
    echo "   Producción: " . count($prodFiles) . " archivos\n\n";
    
    echo colorText("🔍 Comparando archivos...", 'blue', $colors) . "\n";
    $changes = compareFiles($devFiles, $prodFiles, $colors);
    
    printSummary($changes, $colors);
    printDetails($changes, $colors, $showDetails);
    checkCriticalFiles($prodPath, $colors);
    
    // Sugerencias
    $totalChanges = count($changes['new']) + count($changes['modified']) + count($changes['deleted']);
    
    if ($totalChanges > 0) {
        echo colorText("🚀 ACCIONES SUGERIDAS:", 'blue', $colors) . "\n";
        echo colorText("===================", 'blue', $colors) . "\n";
        echo colorText("1. Ejecutar: deploy-to-production.bat", 'yellow', $colors) . "\n";
        echo colorText("2. O usar: php production-deployment-assistant.php", 'yellow', $colors) . "\n";
        echo colorText("3. O abrir: http://localhost:3001/production-deployment-assistant.php", 'yellow', $colors) . "\n\n";
    } else {
        echo colorText("🎉 ¡Todo está sincronizado! No se requieren acciones.", 'green', $colors) . "\n\n";
    }
}

// Ejecutar si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    main();
}
?>