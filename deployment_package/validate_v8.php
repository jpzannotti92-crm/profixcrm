#!/usr/bin/env php
<?php
/**
 * V8 VALIDATOR CLI
 * 
 * Script de validación completo para ProfixCRM V8
 * Resuelve todos los problemas de redirección de V7
 * 
 * USO: php validate_v8.php [modo]
 * MODOS: full, quick, production, debug, cli
 * 
 * @version 8.0.0
 * @author ProfixCRM
 */

// Prevenir redirecciones
$_SERVER['SCRIPT_NAME'] = basename(__FILE__);
$_SERVER['REQUEST_METHOD'] = 'CLI';

echo "🚀 Iniciando V8 Validator CLI...\n\n";

// Detectar modo
$mode = $argv[1] ?? 'full';
$validModes = ['full', 'quick', 'production', 'debug', 'cli'];

if (!in_array($mode, $validModes)) {
    echo "❌ Modo inválido: $mode\n";
    echo "✅ Modos válidos: " . implode(', ', $validModes) . "\n";
    exit(1);
}

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');

// Cargar configuración V8
$configFile = __DIR__ . '/config/v8_config.php';
if (file_exists($configFile)) {
    require_once $configFile;
    echo "✅ Configuración V8 cargada\n";
} else {
    echo "⚠️  Configuración V8 no encontrada, usando valores por defecto\n";
    
    // Crear configuración mínima
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
                        'host' => $_ENV['DB_HOST'] ?? 'localhost',
                        'database' => $_ENV['DB_NAME'] ?? 'profixcrm',
                        'username' => $_ENV['DB_USER'] ?? 'root',
                        'password' => $_ENV['DB_PASS'] ?? '',
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
}

// Cargar V8Validator
$validatorFile = __DIR__ . '/src/Core/V8Validator.php';
if (file_exists($validatorFile)) {
    require_once $validatorFile;
    echo "✅ V8Validator cargado\n";
} else {
    echo "❌ V8Validator no encontrado en: $validatorFile\n";
    exit(1);
}

// Información del sistema
echo "\n📋 INFORMACIÓN DEL SISTEMA:\n";
echo "• PHP Version: " . PHP_VERSION . "\n";
echo "• Memory Limit: " . ini_get('memory_limit') . "\n";
echo "• Max Execution Time: " . ini_get('max_execution_time') . "s\n";
echo "• Operating System: " . PHP_OS . "\n";
echo "• Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'CLI') . "\n";
echo "• Current Directory: " . getcwd() . "\n";
echo "• Validation Mode: $mode\n\n";

// Ejecutar validación
try {
    echo "🔄 Ejecutando validación en modo: $mode...\n\n";
    
    $validator = new Src\Core\V8Validator($mode);
    $results = $validator->validate();
    
    echo $validator->getFormattedResults();
    
    // Guardar log
    $logDir = __DIR__ . '/logs/v8/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . 'validation_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($logFile, json_encode($results, JSON_PRETTY_PRINT));
    
    echo "\n💾 Log guardado en: $logFile\n";
    
    // Resumen final
    $summary = $results['summary'];
    
    if ($summary['errors'] > 0) {
        echo "\n🔥 VALIDACIÓN COMPLETADA CON ERRORES CRÍTICOS\n";
        exit(2);
    } elseif ($summary['failed'] > 0) {
        echo "\n⚠️  VALIDACIÓN COMPLETADA CON FALLAS\n";
        exit(1);
    } else {
        echo "\n🎉 VALIDACIÓN EXITOSA\n";
        exit(0);
    }
    
} catch (\Exception $e) {
    echo "\n🔥 ERROR CRÍTICO DURANTE VALIDACIÓN:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(3);
}

echo "\n✅ V8 Validator CLI finalizado\n";