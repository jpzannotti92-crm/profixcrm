<?php
/**
 * FIX: Configuración de Base de Datos - ProfixCRM v6
 * Convierte variables de entorno a constantes para el sistema
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== FIX CONFIGURACIÓN BASE DE DATOS ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "=======================================\n\n";

// Cargar variables de entorno desde .env.production
$env_file = __DIR__ . '/.env.production';
if (!file_exists($env_file)) {
    echo "✗ .env.production no encontrado\n";
    exit(1);
}

// Parsear archivo .env
$env_content = file_get_contents($env_file);
$lines = explode("\n", $env_content);
$env_vars = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '#') === 0) continue;
    
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"\'');
        $env_vars[$key] = $value;
    }
}

echo "Variables encontradas en .env.production:\n";
echo "- DB_HOST: " . ($env_vars['DB_HOST'] ?? 'NO ENCONTRADO') . "\n";
echo "- DB_DATABASE: " . ($env_vars['DB_DATABASE'] ?? 'NO ENCONTRADO') . "\n";
echo "- DB_USERNAME: " . ($env_vars['DB_USERNAME'] ?? 'NO ENCONTRADO') . "\n";
echo "- DB_PASSWORD: " . (isset($env_vars['DB_PASSWORD']) ? '[CONFIGURADO]' : 'NO ENCONTRADO') . "\n\n";

// Crear archivo de configuración con constantes
$config_content = "<?php\n";
$config_content .= "/**\n";
$config_content .= " * Configuración de Base de Datos - Auto-generado\n";
$config_content .= " * Fecha: " . date('Y-m-d H:i:s') . "\n";
$config_content .= " */\n\n";

if (isset($env_vars['DB_HOST'])) {
    $config_content .= "define('DB_HOST', '" . $env_vars['DB_HOST'] . "');\n";
}
if (isset($env_vars['DB_PORT'])) {
    $config_content .= "define('DB_PORT', '" . $env_vars['DB_PORT'] . "');\n";
} else {
    $config_content .= "define('DB_PORT', '3306');\n";
}
if (isset($env_vars['DB_DATABASE'])) {
    $config_content .= "define('DB_NAME', '" . $env_vars['DB_DATABASE'] . "');\n";
}
if (isset($env_vars['DB_USERNAME'])) {
    $config_content .= "define('DB_USER', '" . $env_vars['DB_USERNAME'] . "');\n";
}
if (isset($env_vars['DB_PASSWORD'])) {
    $config_content .= "define('DB_PASS', '" . $env_vars['DB_PASSWORD'] . "');\n";
}

$config_content .= "\n";
$config_content .= "// Configuración adicional de producción\n";
$config_content .= "define('PRODUCTION_MODE', true);\n";
$config_content .= "define('APP_ENV', 'production');\n";

// Guardar archivo de configuración
$config_file = __DIR__ . '/config/database_constants.php';
if (file_put_contents($config_file, $config_content)) {
    echo "✓ Archivo de configuración creado: config/database_constants.php\n";
    
    // Probar conexión
    echo "\nPrueba de conexión:\n";
    try {
        $dsn = "mysql:host=" . $env_vars['DB_HOST'] . ";dbname=" . $env_vars['DB_DATABASE'] . ";port=" . ($env_vars['DB_PORT'] ?? '3306');
        $pdo = new PDO($dsn, $env_vars['DB_USERNAME'], $env_vars['DB_PASSWORD']);
        echo "✓ Conexión exitosa a la base de datos!\n";
        
        // Verificar tablas
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "✓ Tablas encontradas: " . count($tables) . "\n";
        
        // Verificar usuario admin
        $admin = $pdo->query("SELECT id, username, email, status FROM users WHERE username='admin' OR email='admin@iatrade.com' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            echo "✓ Usuario admin encontrado - Status: {$admin['status']}\n";
        } else {
            echo "⚠️ Usuario admin no encontrado\n";
        }
        
    } catch (PDOException $e) {
        echo "✗ Error de conexión: " . $e->getMessage() . "\n";
        echo "Código de error: " . $e->getCode() . "\n";
    }
    
    echo "\n📋 INSTRUCCIONES:\n";
    echo "1. Subir este archivo (fix_database_config.php) a producción\n";
    echo "2. Ejecutarlo: php fix_database_config.php\n";
    echo "3. Incluir el archivo generado en config/database_constants.php\n";
    echo "4. En config/config.php agregar: require_once 'database_constants.php';\n";
    
} else {
    echo "✗ Error al crear archivo de configuración\n";
}

echo "\n=======================================\n";
echo "Fix completado: " . date('Y-m-d H:i:s') . "\n";

?>