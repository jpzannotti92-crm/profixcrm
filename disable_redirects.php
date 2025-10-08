<?php
/**
 * DESACTIVAR REDIRECCIONES TEMPORALMENTE
 * 
 * Este script desactiva las redirecciones para permitir acceso a validaciones
 */

if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse por línea de comandos: php disable_redirects.php\n");
}

echo "==============================================\n";
echo "🔄 DESACTIVADOR DE REDIRECCIONES\n";
echo "==============================================\n\n";

// Archivos que suelen tener redirecciones
$archivos_con_redirects = [
    'public/index.php',
    'src/Controllers/AuthController.php',
    'index.php',
    'auth.php',
    'login.php'
];

$redirecciones_encontradas = [];

foreach ($archivos_con_redirects as $archivo) {
    if (file_exists($archivo)) {
        echo "📁 Analizando: $archivo\n";
        
        $contenido = file_get_contents($archivo);
        $lineas = explode("\n", $contenido);
        $tiene_redirects = false;
        
        foreach ($lineas as $num_linea => $linea) {
            if (preg_match('/header\s*\(\s*[\'"]Location:/i', $linea)) {
                $tiene_redirects = true;
                $numero = $num_linea + 1;
                echo "  ⚠️  Línea $numero: " . trim($linea) . "\n";
                $redirecciones_encontradas[] = [
                    'archivo' => $archivo,
                    'linea' => $numero,
                    'contenido' => trim($linea)
                ];
            }
        }
        
        if (!$tiene_redirects) {
            echo "  ✅ Sin redirecciones encontradas\n";
        }
        echo "\n";
    } else {
        echo "  ❌ Archivo no encontrado\n\n";
    }
}

// Crear respaldos y archivos sin redirecciones
if (count($redirecciones_encontradas) > 0) {
    echo "==============================================\n";
    echo "🛡️  CREANDO RESPALDOS Y VERSIONES SIN REDIRECCIONES\n";
    echo "==============================================\n\n";
    
    foreach ($redirecciones_encontradas as $redirect) {
        $archivo = $redirect['archivo'];
        
        // Crear respaldo
        $backup_file = $archivo . '.backup_' . date('Y-m-d_H-i-s');
        copy($archivo, $backup_file);
        echo "✅ Respaldo creado: $backup_file\n";
        
        // Crear versión sin redirecciones
        $contenido = file_get_contents($archivo);
        $lineas = explode("\n", $contenido);
        $nuevo_contenido = [];
        
        foreach ($lineas as $num_linea => $linea) {
            if (preg_match('/header\s*\(\s*[\'"]Location:/i', $linea)) {
                // Comentar la línea de redirección
                $nuevo_contenido[] = "// REDIRECCIÓN TEMPORALMENTE DESACTIVADA - " . $linea;
                echo "  📝 Línea " . ($num_linea + 1) . " comentada en $archivo\n";
            } else {
                $nuevo_contenido[] = $linea;
            }
        }
        
        // Guardar archivo modificado
        file_put_contents($archivo, implode("\n", $nuevo_contenido));
        echo "✅ Redirecciones desactivadas en: $archivo\n\n";
    }
    
    echo "==============================================\n";
    echo "🎉 ¡REDIRECCIONES DESACTIVADAS TEMPORALMENTE!\n";
    echo "==============================================\n";
    echo "Ahora puedes acceder a:\n";
    echo "• http://localhost/profixcrm/validate_after_deploy.php\n";
    echo "• http://localhost/profixcrm/production_check.php\n";
    echo "• http://localhost/profixcrm/api/auth/reset_admin.php\n";
    echo "• http://localhost/profixcrm/api/auth/create_admin.php\n";
    echo "\n";
    echo "Para restaurar las redirecciones:\n";
    echo "1. Ejecuta: php restore_redirects.php\n";
    echo "2. O restaura manualmente los archivos .backup_*\n";
    echo "==============================================\n";
    
} else {
    echo "==============================================\n";
    echo "✅ NO SE ENCONTRARON REDIRECCIONES\n";
    echo "==============================================\n";
    echo "El problema puede estar en:\n";
    echo "• Configuración de .htaccess\n";
    echo "• Configuración de Apache/Nginx\n";
    echo "• Variables de entorno\n";
    echo "• JavaScript del frontend\n";
    echo "\n";
    echo "Verifica también estos archivos:\n";
    echo "• .htaccess\n";
    echo "• .env\n";
    echo "• frontend/src/config/api.js\n";
    echo "==============================================\n";
}