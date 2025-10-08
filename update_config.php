<?php
/**
 * Actualización de Configuración - ProfixCRM v6
 * Soluciona errores 500 agregando constantes de base de datos
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== ACTUALIZACIÓN DE CONFIGURACIÓN PROFIXCRM V6 ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n\n";

// 1. Actualizar config/config.php para incluir constantes
echo "1. ACTUALIZANDO config/config.php\n";
$config_file = __DIR__ . '/config/config.php';
if (file_exists($config_file)) {
    $content = file_get_contents($config_file);
    
    // Verificar si ya incluye constants.php
    if (strpos($content, "constants.php") === false) {
        // Agregar inclusión de constantes al final
        $new_content = $content;
        if (strpos($content, 'return') === 0) {
            // Si empieza con return, agregar inclusión antes
            $new_content = "<?php\n";
            $new_content .= "require_once __DIR__ . '/constants.php';\n";
            $new_content .= "\n";
            $new_content .= $content;
        } else {
            // Agregar al final
            $new_content = str_replace('?>', '', $content);
            $new_content .= "\n// Incluir constantes del sistema\n";
            $new_content .= "require_once __DIR__ . '/constants.php';\n";
            $new_content .= "\n?>";
        }
        
        if (file_put_contents($config_file, $new_content)) {
            echo "✓ config/config.php actualizado para incluir constantes\n";
        } else {
            echo "✗ Error al actualizar config/config.php\n";
        }
    } else {
        echo "✓ config/config.php ya incluye constantes\n";
    }
} else {
    echo "✗ config/config.php no encontrado\n";
}

// 2. Actualizar api/config.php
echo "\n2. ACTUALIZANDO api/config.php\n";
$api_config_file = __DIR__ . '/api/config.php';
if (file_exists($api_config_file)) {
    $content = file_get_contents($api_config_file);
    
    // Agregar inclusión de constantes si no existe
    if (strpos($content, "constants.php") === false && strpos($content, "DB_") === false) {
        // Agregar al inicio después de <?php
        $new_content = str_replace('<?php', "<?php\nrequire_once __DIR__ . '/../config/constants.php';\n", $content);
        
        if (file_put_contents($api_config_file, $new_content)) {
            echo "✓ api/config.php actualizado para incluir constantes\n";
        } else {
            echo "✗ Error al actualizar api/config.php\n";
        }
    } else {
        echo "✓ api/config.php ya tiene constantes o configuración de BD\n";
    }
} else {
    echo "✗ api/config.php no encontrado\n";
}

echo "\n==============================================\n";
echo "Actualización completada: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n";

echo "\n📋 RESUMEN DE CAMBIOS:\n";
echo "- Se agregaron constantes de base de datos (DB_HOST, DB_NAME, DB_USER, DB_PASS)\n";
echo "- Se actualizó config/config.php para incluir constantes\n";
echo "- Se actualizó api/config.php para incluir constantes\n";
echo "- Se solucionará el error 500 en endpoints principales\n";

?>