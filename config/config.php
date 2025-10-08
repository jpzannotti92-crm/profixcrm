<?php
/**
 * Configuración generada automáticamente
 */

return [
    'database' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'iatrade_crm',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ],
    
    'app' => [
        'name' => 'iaTrade CRM',
        'url' => 'http://localhost:3001',
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