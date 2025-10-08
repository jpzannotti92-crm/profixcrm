<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$info = [
    'debug' => true,
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'raw_input' => file_get_contents('php://input'),
    'post_data' => $_POST,
    'get_data' => $_GET,
    'headers' => function_exists('getallheaders') ? getallheaders() : [],
    'php' => [
        'version' => PHP_VERSION,
        'sapi' => php_sapi_name(),
        'loaded_ini' => function_exists('php_ini_loaded_file') ? php_ini_loaded_file() : null,
        'extension_dir' => ini_get('extension_dir'),
        'extensions' => get_loaded_extensions(),
        'pdo' => [
            'loaded' => extension_loaded('pdo'),
            'drivers' => class_exists('PDO') ? PDO::getAvailableDrivers() : []
        ]
    ]
];

echo json_encode($info);
?>