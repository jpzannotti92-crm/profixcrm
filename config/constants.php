<?php
/**
 * Constantes del Sistema - ProfixCRM v6
 * Define las constantes necesarias para el funcionamiento
 */

// Cargar configuración principal
$config = require __DIR__ . '/config.php';

// Definir constantes de base de datos desde la configuración
if (isset($config['database'])) {
    define('DB_HOST', $config['database']['host']);
    define('DB_PORT', $config['database']['port']);
    define('DB_NAME', $config['database']['name']);
    define('DB_USER', $config['database']['username']);
    define('DB_PASS', $config['database']['password']);
}

// Si hay .env.production, sobrescribir con esos valores
$env_file = __DIR__ . '/../.env.production';
if (file_exists($env_file)) {
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
    
    // Sobrescribir con valores de producción
    if (isset($env_vars['DB_HOST'])) {
        define('DB_HOST', $env_vars['DB_HOST']);
    }
    if (isset($env_vars['DB_DATABASE'])) {
        define('DB_NAME', $env_vars['DB_DATABASE']);
    }
    if (isset($env_vars['DB_USERNAME'])) {
        define('DB_USER', $env_vars['DB_USERNAME']);
    }
    if (isset($env_vars['DB_PASSWORD'])) {
        define('DB_PASS', $env_vars['DB_PASSWORD']);
    }
}

// Constantes adicionales
define('PRODUCTION_MODE', true);
define('APP_ENV', 'production');

?>