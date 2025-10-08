<?php
/**
 * RESTAURAR REDIRECCIONES
 * 
 * Este script restaura las redirecciones desde los archivos de respaldo
 */

if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse por línea de comandos: php restore_redirects.php\n");
}

echo "==============================================\n";
echo "🔄 RESTAURADOR DE REDIRECCIONES\n";
echo "==============================================\n\n";

// Buscar archivos de respaldo
$archivos_backup = glob("*.backup_*");
$archivos_backup = array_merge($archivos_backup, glob("public/*.backup_*"));
$archivos_backup = array_merge($archivos_backup, glob("src/Controllers/*.backup_*"));

if (count($archivos_backup) === 0) {
    echo "❌ No se encontraron archivos de respaldo\n";
    echo "ℹ️  Los archivos de respaldo tienen extensión .backup_YYYY-MM-DD_HH-ii-ss\n";
    exit(1);
}

echo "📁 Archivos de respaldo encontrados:\n";
foreach ($archivos_backup as $backup) {
    echo "  • $backup\n";
}
echo "\n";

// Restaurar cada archivo
foreach ($archivos_backup as $backup) {
    // Obtener el nombre original del archivo
    $original = preg_replace('/\.backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', '', $backup);
    
    if (file_exists($original)) {
        echo "🔄 Restaurando: $backup → $original\n";
        
        // Copiar el respaldo sobre el archivo original
        if (copy($backup, $original)) {
            echo "✅ Restaurado exitosamente: $original\n";
            
            // Opcionalmente eliminar el respaldo
            echo "¿Eliminar archivo de respaldo? (s/n): ";
            $respuesta = trim(fgets(STDIN));
            if (strtolower($respuesta) === 's') {
                unlink($backup);
                echo "🗑️  Archivo de respaldo eliminado\n";
            }
        } else {
            echo "❌ Error al restaurar: $original\n";
        }
    } else {
        echo "⚠️  El archivo original no existe: $original\n";
        echo "¿Deseas crearlo desde el respaldo? (s/n): ";
        $respuesta = trim(fgets(STDIN));
        if (strtolower($respuesta) === 's') {
            if (copy($backup, $original)) {
                echo "✅ Creado desde respaldo: $original\n";
            } else {
                echo "❌ Error al crear: $original\n";
            }
        }
    }
    echo "\n";
}

echo "==============================================\n";
echo "✅ ¡PROCESO DE RESTAURACIÓN COMPLETADO!\n";
echo "==============================================\n";
echo "Las redirecciones originales han sido restauradas\n";
echo "El sistema volverá a redirigir a spin2pay.com\n";
echo "==============================================\n";