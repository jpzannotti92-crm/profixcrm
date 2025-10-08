<?php
/**
 * Despliegue de Release v6 a Producción
 * Script para subir y aplicar Release v6
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== DESPLIEGUE DE RELEASE V6 A PRODUCCIÓN ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n\n";

// Configuración de producción
$production_server = "spin2pay.com";
$production_user = "spin2pay";
$production_path = "/home/spin2pay/public_html";
$local_zip = "deploy/releases/spin2pay_v6.zip";

// 1. Verificar que el archivo v6 existe
echo "1. VERIFICANDO ARCHIVO V6\n";
if (file_exists($local_zip)) {
    echo "✓ Archivo $local_zip encontrado (" . filesize($local_zip) . " bytes)\n";
} else {
    echo "✗ Archivo $local_zip NO ENCONTRADO\n";
    echo "Por favor, asegúrate de que spin2pay_v6.zip esté en deploy/releases/\n";
    exit(1);
}

// 2. Generar comandos para subir a producción
echo "\n2. COMANDOS PARA SUBIR A PRODUCCIÓN\n";
echo "==============================================\n";
echo "Ejecuta estos comandos en tu terminal local:\n";
echo "\n# Subir archivo v6 al servidor\n";
echo "scp $local_zip $production_user@$production_server:/tmp/\n";
echo "\n# Conectar al servidor\n";
echo "ssh $production_user@$production_server\n";
echo "\n# En el servidor, hacer backup y descomprimir:\n";
echo "cd $production_path\n";
echo "tar -czf backup_pre_v6_$(date +%Y%m%d_%H%M%S).tar.gz .\n";
echo "cd /tmp\n";
echo "unzip -o spin2pay_v6.zip\n";
echo "cp -r spin2pay_v6/* $production_path/\n";
echo "cp -r spin2pay_v6/.[^.]* $production_path/ 2>/dev/null || true\n";
echo "cd $production_path\n";
echo "chmod -R 755 .\n";
echo "chmod -R 777 logs/ uploads/ temp/ cache/ 2>/dev/null || true\n";
echo "\n# Limpiar archivos temporales\n";
echo "rm -rf /tmp/spin2pay_v6*\n";
echo "==============================================\n";

// 3. Verificar archivos críticos en v6
echo "\n3. ARCHIVOS CRÍTICOS EN V6\n";
$zip = new ZipArchive();
if ($zip->open($local_zip) === TRUE) {
    $critical_files = [
        'api/auth/reset_admin.php',
        'api/auth/create_admin.php',
        'config/constants.php',
        'validate_config.php',
        'post_install_validation.php',
        'update_config.php',
        'fix_database_config.php'
    ];
    
    foreach ($critical_files as $file) {
        $found = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strpos($filename, $file) !== false) {
                echo "✓ $file encontrado\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "✗ $file NO ENCONTRADO\n";
        }
    }
    $zip->close();
} else {
    echo "✗ No se pudo abrir el archivo ZIP\n";
}

// 4. Post-instalación
echo "\n4. POST-INSTALACIÓN EN PRODUCCIÓN\n";
echo "==============================================\n";
echo "Después de subir v6, ejecuta estos comandos:\n";
echo "\n# 1. Actualizar configuración de base de datos\n";
echo "cd $production_path\n";
echo "php update_config.php\n";
echo "\n# 2. Verificar configuración\n";
echo "php validate_config.php\n";
echo "\n# 3. Validar instalación\n";
echo "php post_install_validation.php\n";
echo "\n# 4. Crear directorios necesarios\n";
echo "mkdir -p temp cache\n";
echo "chmod 777 temp cache\n";
echo "\n# 5. Probar endpoints\n";
echo "curl -X POST https://spin2pay.com/api/auth/login.php -H 'Content-Type: application/json' -d '{\"username\":\"admin\",\"password\":\"admin123\"}'\n";
echo "curl https://spin2pay.com/api/health.php\n";
echo "==============================================\n";

// 5. Verificación final
echo "\n5. VERIFICACIÓN FINAL\n";
echo "Después de la instalación, verifica:\n";
echo "- ✓ No más errores 500 en /api/users.php, /api/leads.php, /api/dashboard.php\n";
echo "- ✓ Endpoints /api/auth/reset_admin.php y /api/auth/create_admin.php disponibles (200/404)\n";
echo "- ✓ Conexión a base de datos funcionando\n";
echo "- ✓ Usuario admin creado o accesible\n";
echo "- ✓ Directorios temp/ y cache/ creados con permisos correctos\n";

echo "\n==============================================\n";
echo "Script de despliegue generado: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n";

?>