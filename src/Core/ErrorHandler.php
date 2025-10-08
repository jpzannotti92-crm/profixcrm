<?php
namespace IaTradeCRM\Core;

/**
 * Sistema centralizado de manejo de errores para la aplicación
 * Proporciona respuestas consistentes y logging detallado
 */
class ErrorHandler {
    /**
     * Maneja errores de API y devuelve una respuesta JSON consistente
     * 
     * @param \Exception $e Excepción capturada
     * @param array $defaultData Datos por defecto para incluir en la respuesta
     * @param int $statusCode Código HTTP a devolver
     * @param bool $showErrorDetails Si se deben mostrar detalles del error en producción
     * @return void
     */
    public static function handleApiError(\Exception $e, array $defaultData = [], int $statusCode = 500, bool $showErrorDetails = false) {
        // Registrar el error para diagnóstico
        self::logError($e);
        
        // Establecer código de estado HTTP
        http_response_code($statusCode);
        
        // Determinar si estamos en producción
        $isProduction = getenv('APP_ENV') === 'production';
        
        // Preparar mensaje de error
        $errorMessage = $isProduction && !$showErrorDetails 
            ? 'Se produjo un error en el servidor' 
            : $e->getMessage();
        
        // Construir respuesta con estructura consistente
        $response = [
            'success' => false,
            'message' => $errorMessage,
            'data' => $defaultData,
        ];
        
        // Añadir información de paginación si es necesario
        if (isset($defaultData['pagination'])) {
            $response['pagination'] = $defaultData['pagination'];
            unset($response['data']['pagination']);
        }
        
        // En desarrollo, incluir información adicional para depuración
        if (!$isProduction || $showErrorDetails) {
            $response['debug'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            ];
        }
        
        // Enviar respuesta JSON
        echo json_encode($response);
        exit();
    }
    
    /**
     * Proporciona una respuesta de fallback para evitar errores en el frontend
     * 
     * @param string $message Mensaje amigable para el usuario
     * @param array $defaultData Datos por defecto para incluir en la respuesta
     * @param array $pagination Información de paginación
     * @return void
     */
    public static function sendFallbackResponse($message = 'No se pudieron cargar los datos', $defaultData = [], $pagination = null) {
        $response = [
            'success' => true, // Siempre true para evitar errores en el frontend
            'message' => $message,
            'data' => $defaultData
        ];
        
        if ($pagination) {
            $response['pagination'] = $pagination;
        }
        
        echo json_encode($response);
        exit();
    }
    
    /**
     * Registra errores en el sistema de logs
     * 
     * @param string $message Mensaje de error a registrar
     * @return void
     */
    public static function logError($message) {
        $logMessage = sprintf(
            "[%s] %s\n",
            date('Y-m-d H:i:s'),
            $message
        );
        
        // Guardar en archivo de log
        error_log($logMessage);
        
        // Si existe un directorio de logs personalizado, guardar allí también
        $logDir = __DIR__ . '/../../logs/errors';
        if (is_dir($logDir) || mkdir($logDir, 0755, true)) {
            $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
}