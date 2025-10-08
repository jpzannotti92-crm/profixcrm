<?php
/**
 * Sistema de Logging Mejorado para Errores HTTP 500
 * iaTrade CRM - Sistema de Gestión de Leads Forex/CFD
 * 
 * Este sistema proporciona logging avanzado para diagnosticar
 * errores HTTP 500 en el servidor de producción GoDaddy.
 */

class EnhancedErrorLogger {
    
    private $logDir;
    private $maxLogSize;
    private $maxLogFiles;
    private $isGoDaddy;
    
    public function __construct($logDir = 'logs', $maxLogSize = 10485760, $maxLogFiles = 10) {
        $this->logDir = $logDir;
        $this->maxLogSize = $maxLogSize; // 10MB por defecto
        $this->maxLogFiles = $maxLogFiles;
        $this->isGoDaddy = $this->detectGoDaddyEnvironment();
        
        $this->initializeLogDirectory();
        $this->setupErrorHandlers();
    }
    
    /**
     * Detectar si estamos en entorno GoDaddy
     */
    private function detectGoDaddyEnvironment() {
        $indicators = [
            'SERVER_SOFTWARE' => ['GoDaddy', 'Apache'],
            'DOCUMENT_ROOT' => ['/home/', '/var/www/'],
            'HTTP_HOST' => ['.godaddy.com', '.secureserver.net']
        ];
        
        foreach ($indicators as $var => $patterns) {
            if (isset($_SERVER[$var])) {
                foreach ($patterns as $pattern) {
                    if (stripos($_SERVER[$var], $pattern) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Inicializar directorio de logs
     */
    private function initializeLogDirectory() {
        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0755, true)) {
                error_log("No se pudo crear el directorio de logs: {$this->logDir}");
                $this->logDir = sys_get_temp_dir();
            }
        }
        
        // Crear archivo .htaccess para proteger los logs
        $htaccessPath = $this->logDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "Order Deny,Allow\nDeny from all\n");
        }
    }
    
    /**
     * Configurar manejadores de errores
     */
    private function setupErrorHandlers() {
        // Manejador de errores PHP
        set_error_handler([$this, 'handleError']);
        
        // Manejador de excepciones no capturadas
        set_exception_handler([$this, 'handleException']);
        
        // Manejador de errores fatales
        register_shutdown_function([$this, 'handleFatalError']);
    }
    
    /**
     * Manejar errores PHP
     */
    public function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorData = [
            'type' => 'PHP_ERROR',
            'severity' => $this->getSeverityName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'context' => $this->getContextInfo(),
            'stack_trace' => $this->getStackTrace()
        ];
        
        $this->logError($errorData);
        
        // No interferir con el manejo normal de errores
        return false;
    }
    
    /**
     * Manejar excepciones no capturadas
     */
    public function handleException($exception) {
        $errorData = [
            'type' => 'UNCAUGHT_EXCEPTION',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'context' => $this->getContextInfo(),
            'stack_trace' => $exception->getTraceAsString()
        ];
        
        $this->logError($errorData);
        
        // Enviar respuesta HTTP 500
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Internal Server Error',
                'message' => 'Se ha producido un error interno del servidor',
                'error_id' => $this->generateErrorId()
            ]);
        }
    }
    
    /**
     * Manejar errores fatales
     */
    public function handleFatalError() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorData = [
                'type' => 'FATAL_ERROR',
                'severity' => $this->getSeverityName($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'context' => $this->getContextInfo(),
                'stack_trace' => 'Fatal error - no stack trace available'
            ];
            
            $this->logError($errorData);
        }
    }
    
    /**
     * Registrar error HTTP 500 específico
     */
    public function logHttp500Error($endpoint, $additionalData = []) {
        $errorData = [
            'type' => 'HTTP_500_ERROR',
            'endpoint' => $endpoint,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'context' => $this->getContextInfo(),
            'additional_data' => $additionalData,
            'stack_trace' => $this->getStackTrace()
        ];
        
        $this->logError($errorData);
    }
    
    /**
     * Registrar error de base de datos
     */
    public function logDatabaseError($query, $error, $additionalData = []) {
        $errorData = [
            'type' => 'DATABASE_ERROR',
            'query' => $query,
            'error' => $error,
            'context' => $this->getContextInfo(),
            'additional_data' => $additionalData,
            'stack_trace' => $this->getStackTrace()
        ];
        
        $this->logError($errorData);
    }
    
    /**
     * Registrar error de API
     */
    public function logApiError($endpoint, $error, $requestData = null) {
        $errorData = [
            'type' => 'API_ERROR',
            'endpoint' => $endpoint,
            'error' => $error,
            'request_data' => $requestData,
            'context' => $this->getContextInfo(),
            'stack_trace' => $this->getStackTrace()
        ];
        
        $this->logError($errorData);
    }
    
    /**
     * Método principal para registrar errores
     */
    private function logError($errorData) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'error_id' => $this->generateErrorId(),
            'environment' => $this->isGoDaddy ? 'GoDaddy Production' : 'Local Development',
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        $logEntry = array_merge($logEntry, $errorData);
        
        // Escribir al archivo de log
        $this->writeToLogFile($logEntry);
        
        // Si es un error crítico, también escribir al log de sistema
        if (in_array($errorData['type'], ['FATAL_ERROR', 'HTTP_500_ERROR', 'UNCAUGHT_EXCEPTION'])) {
            error_log("iaTrade CRM Critical Error: " . json_encode($logEntry));
        }
    }
    
    /**
     * Escribir entrada al archivo de log
     */
    private function writeToLogFile($logEntry) {
        $logFile = $this->getLogFileName();
        
        // Rotar logs si es necesario
        $this->rotateLogsIfNeeded($logFile);
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n" . str_repeat('-', 80) . "\n";
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Obtener nombre del archivo de log
     */
    private function getLogFileName() {
        $date = date('Y-m-d');
        return $this->logDir . "/iatrade_errors_{$date}.log";
    }
    
    /**
     * Rotar logs si exceden el tamaño máximo
     */
    private function rotateLogsIfNeeded($logFile) {
        if (file_exists($logFile) && filesize($logFile) > $this->maxLogSize) {
            $timestamp = date('Y-m-d_H-i-s');
            $rotatedFile = str_replace('.log', "_{$timestamp}.log", $logFile);
            rename($logFile, $rotatedFile);
            
            // Limpiar logs antiguos
            $this->cleanOldLogs();
        }
    }
    
    /**
     * Limpiar logs antiguos
     */
    private function cleanOldLogs() {
        $logFiles = glob($this->logDir . '/iatrade_errors_*.log');
        
        if (count($logFiles) > $this->maxLogFiles) {
            // Ordenar por fecha de modificación
            usort($logFiles, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Eliminar los más antiguos
            $filesToDelete = array_slice($logFiles, 0, count($logFiles) - $this->maxLogFiles);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Obtener información del contexto
     */
    private function getContextInfo() {
        return [
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => $this->getClientIpAddress(),
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'Direct',
            'session_id' => session_id() ?: 'No session',
            'user_id' => $_SESSION['user_id'] ?? 'Anonymous',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown'
        ];
    }
    
    /**
     * Obtener dirección IP del cliente
     */
    private function getClientIpAddress() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Obtener stack trace
     */
    private function getStackTrace() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $formattedTrace = [];
        
        foreach ($trace as $i => $frame) {
            $file = $frame['file'] ?? 'Unknown';
            $line = $frame['line'] ?? 'Unknown';
            $function = $frame['function'] ?? 'Unknown';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            
            $formattedTrace[] = "#{$i} {$file}({$line}): {$class}{$type}{$function}()";
        }
        
        return implode("\n", $formattedTrace);
    }
    
    /**
     * Obtener nombre de severidad
     */
    private function getSeverityName($severity) {
        $severities = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        
        return $severities[$severity] ?? 'UNKNOWN';
    }
    
    /**
     * Generar ID único para el error
     */
    private function generateErrorId() {
        return 'ERR_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
    }
    
    /**
     * Obtener estadísticas de errores
     */
    public function getErrorStats($days = 7) {
        $stats = [
            'total_errors' => 0,
            'error_types' => [],
            'daily_counts' => [],
            'top_files' => [],
            'recent_errors' => []
        ];
        
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $logFiles = glob($this->logDir . '/iatrade_errors_*.log');
        
        foreach ($logFiles as $logFile) {
            $content = file_get_contents($logFile);
            $entries = explode(str_repeat('-', 80), $content);
            
            foreach ($entries as $entry) {
                $entry = trim($entry);
                if (empty($entry)) continue;
                
                $errorData = json_decode($entry, true);
                if (!$errorData || !isset($errorData['timestamp'])) continue;
                
                if ($errorData['timestamp'] < $startDate) continue;
                
                $stats['total_errors']++;
                
                // Contar por tipo
                $type = $errorData['type'] ?? 'UNKNOWN';
                $stats['error_types'][$type] = ($stats['error_types'][$type] ?? 0) + 1;
                
                // Contar por día
                $date = substr($errorData['timestamp'], 0, 10);
                $stats['daily_counts'][$date] = ($stats['daily_counts'][$date] ?? 0) + 1;
                
                // Contar por archivo
                $file = $errorData['file'] ?? 'Unknown';
                $stats['top_files'][$file] = ($stats['top_files'][$file] ?? 0) + 1;
                
                // Errores recientes
                if (count($stats['recent_errors']) < 10) {
                    $stats['recent_errors'][] = [
                        'timestamp' => $errorData['timestamp'],
                        'type' => $type,
                        'message' => $errorData['message'] ?? 'No message',
                        'file' => $file
                    ];
                }
            }
        }
        
        // Ordenar estadísticas
        arsort($stats['error_types']);
        arsort($stats['top_files']);
        krsort($stats['daily_counts']);
        
        return $stats;
    }
    
    /**
     * Limpiar todos los logs
     */
    public function clearLogs() {
        $logFiles = glob($this->logDir . '/iatrade_errors_*.log');
        $cleared = 0;
        
        foreach ($logFiles as $file) {
            if (unlink($file)) {
                $cleared++;
            }
        }
        
        return $cleared;
    }
}

// Función de conveniencia para inicializar el logger
function initializeErrorLogger() {
    global $errorLogger;
    
    if (!isset($errorLogger)) {
        $errorLogger = new EnhancedErrorLogger();
    }
    
    return $errorLogger;
}

// Funciones de conveniencia para logging
function logHttp500Error($endpoint, $additionalData = []) {
    $logger = initializeErrorLogger();
    $logger->logHttp500Error($endpoint, $additionalData);
}

function logDatabaseError($query, $error, $additionalData = []) {
    $logger = initializeErrorLogger();
    $logger->logDatabaseError($query, $error, $additionalData);
}

function logApiError($endpoint, $error, $requestData = null) {
    $logger = initializeErrorLogger();
    $logger->logApiError($endpoint, $error, $requestData);
}

// Auto-inicializar si se incluye este archivo
if (!defined('ENHANCED_ERROR_LOGGER_NO_AUTO_INIT')) {
    initializeErrorLogger();
}

?>