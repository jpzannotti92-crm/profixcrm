<?php
/**
 * V8 VALIDATOR WEB AJAX
 * 
 * Endpoint AJAX para validación V8
 * 
 * @version 8.0.0
 * @author ProfixCRM
 */

// Headers para AJAX
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Prevenir redirecciones
$_SERVER['SCRIPT_NAME'] = basename(__FILE__);
$_SERVER['REQUEST_METHOD'] = 'AJAX';

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en JSON

// Validar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'Método no permitido',
        'message' => 'Este endpoint solo acepta solicitudes POST'
    ]);
    exit;
}

// Obtener datos JSON
try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }
    
    $mode = $input['mode'] ?? 'full';
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Solicitud inválida',
        'message' => $e->getMessage()
    ]);
    exit;
}

// Validar modo
$validModes = ['full', 'quick', 'production', 'debug', 'cli'];
if (!in_array($mode, $validModes)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Modo inválido',
        'message' => 'Modo debe ser uno de: ' . implode(', ', $validModes)
    ]);
    exit;
}

// Cargar configuración V8
try {
    $configFile = __DIR__ . '/config/v8_config.php';
    
    if (!file_exists($configFile)) {
        // Crear configuración mínima para AJAX
        if (!class_exists('V8Config')) {
            class V8Config {
                private static $instance;
                private $config = [];
                
                private function __construct() {
                    $this->config = [
                        'environment' => 'development',
                        'redirect_mode' => 'intelligent',
                        'debug' => true,
                        'database' => [
                            'host' => $_ENV['DB_HOST'] ?? $_ENV['DB_SERVER'] ?? 'localhost',
                            'database' => $_ENV['DB_NAME'] ?? $_ENV['DB_DATABASE'] ?? 'profixcrm',
                            'username' => $_ENV['DB_USER'] ?? $_ENV['DB_USERNAME'] ?? 'root',
                            'password' => $_ENV['DB_PASS'] ?? $_ENV['DB_PASSWORD'] ?? '',
                            'charset' => 'utf8mb4',
                            'port' => '3306'
                        ]
                    ];
                }
                
                public static function getInstance() {
                    if (!self::$instance) {
                        self::$instance = new self();
                    }
                    return self::$instance;
                }
                
                public function getEnvironment() {
                    return $this->config['environment'];
                }
                
                public function getDatabaseConfig() {
                    return $this->config['database'];
                }
                
                public function getAllConfig() {
                    return $this->config;
                }
            }
        }
    } else {
        require_once $configFile;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de configuración',
        'message' => 'No se pudo cargar la configuración V8: ' . $e->getMessage()
    ]);
    exit;
}

// Cargar V8Validator
try {
    $validatorFile = __DIR__ . '/src/Core/V8Validator.php';
    
    if (!file_exists($validatorFile)) {
        throw new Exception('V8Validator no encontrado');
    }
    
    require_once $validatorFile;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de validador',
        'message' => 'No se pudo cargar el validador V8: ' . $e->getMessage()
    ]);
    exit;
}

// Ejecutar validación
try {
    // Establecer límite de tiempo según el modo
    $timeLimits = [
        'quick' => 30,
        'cli' => 60,
        'production' => 120,
        'debug' => 180,
        'full' => 300
    ];
    
    $timeLimit = $timeLimits[$mode] ?? 120;
    set_time_limit($timeLimit);
    
    // Crear y ejecutar validador
    $validator = new \Src\Core\V8Validator($mode);
    $results = $validator->validate();
    
    // Agregar información adicional para AJAX
    $results['ajax_info'] = [
        'mode' => $mode,
        'execution_time' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4),
        'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ];
    
    // Enviar respuesta
    http_response_code(200);
    echo json_encode($results, JSON_PRETTY_PRINT);
    
    // Guardar log en segundo plano (no bloqueante)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Guardar log de forma asíncrona
    saveValidationLog($results, $mode);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error durante la validación',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}

/**
 * Guardar log de validación
 */
function saveValidationLog($results, $mode) {
    try {
        $logDir = __DIR__ . '/logs/v8/';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'mode' => $mode,
            'summary' => $results['summary'],
            'environment' => $results['environment'],
            'php_version' => $results['php_version'],
            'execution_time' => $results['performance']['total_time'] ?? 'unknown'
        ];
        
        $logFile = $logDir . 'validation_ajax_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
        
        // También guardar un log resumido diario
        $dailyLogFile = $logDir . 'validation_daily_' . date('Y-m-d') . '.json';
        $dailyLog = [];
        
        if (file_exists($dailyLogFile)) {
            $dailyLog = json_decode(file_get_contents($dailyLogFile), true) ?: [];
        }
        
        $dailyLog[] = $logData;
        
        // Mantener solo los últimos 100 registros del día
        if (count($dailyLog) > 100) {
            $dailyLog = array_slice($dailyLog, -100);
        }
        
        file_put_contents($dailyLogFile, json_encode($dailyLog, JSON_PRETTY_PRINT));
        
    } catch (Exception $e) {
        // Silenciar errores de log para no afectar la respuesta
        error_log("Error guardando log V8: " . $e->getMessage());
    }
}