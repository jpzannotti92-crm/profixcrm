<?php
// Bootstrap global para endpoints de API
try {
    require_once __DIR__ . '/../../enhanced_error_logger.php';
} catch (Throwable $e) {
    // Fallback silencioso si no está disponible
}

// Configuración coherente de errores
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Ruta de log de PHP centralizada
$errorLogPath = __DIR__ . '/../../storage/logs/php_errors.log';
if (!is_dir(dirname($errorLogPath))) {
    @mkdir(dirname($errorLogPath), 0755, true);
}
ini_set('error_log', $errorLogPath);

// CORS y preflight centralizado para evitar pantallas blancas en navegadores
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}

// Responder preflight OPTIONS de forma temprana
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}
?>