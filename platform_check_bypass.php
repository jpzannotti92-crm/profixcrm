<?php
/**
 * Platform Check Bypass for Development Environment
 * 
 * Este archivo debe ser incluido ANTES de cargar vendor/autoload.php
 * para evitar errores de compatibilidad de versión de PHP en desarrollo.
 */

// Configurar bypass de platform check para desarrollo
if (!getenv('DEV_SKIP_PLATFORM_CHECK')) {
    // Detectar si estamos en entorno de desarrollo
    $isDevelopment = (
        // Verificar si estamos en localhost
        (isset($_SERVER['HTTP_HOST']) && (
            strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
            strpos($_SERVER['HTTP_HOST'], '::1') !== false
        )) ||
        // Verificar si estamos ejecutando desde CLI en desarrollo
        (php_sapi_name() === 'cli' && (
            strpos(__DIR__, 'xampp') !== false ||
            strpos(__DIR__, 'wamp') !== false ||
            strpos(__DIR__, 'mamp') !== false
        )) ||
        // Verificar parámetro GET explícito
        (isset($_GET['dev_skip_platform_check']) && $_GET['dev_skip_platform_check'] === '1')
    );
    
    if ($isDevelopment) {
        putenv('DEV_SKIP_PLATFORM_CHECK=1');
        $_ENV['DEV_SKIP_PLATFORM_CHECK'] = '1';
        $_SERVER['DEV_SKIP_PLATFORM_CHECK'] = '1';
    }
}