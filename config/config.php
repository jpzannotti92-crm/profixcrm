<?php
/**
 * Configuración generada automáticamente
 */

return [
    'database' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'spin2pay_profixcrm',
        'username' => 'spin2pay_profixadmin',
        'password' => 'Jeanpi9941991@',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ],
    
    'app' => [
        'name' => 'iaTrade CRM',
        'url' => 'https://spin2pay.com',
        'env' => 'production',
        'debug' => false,
        'timezone' => 'America/Mexico_City'
    ],
    
    'security' => [
        'key' => 'd75acec4b47c0e290b45939baa55f69d',
        'jwt_secret' => '682a60dd3c1488da2f3c48becc746fbcf1c21b51bda88331e24c7443da804462',
        'session_lifetime' => 120,
        'password_min_length' => 8
    ]
];

// Incluir constantes del sistema
require_once __DIR__ . '/constants.php';

?>