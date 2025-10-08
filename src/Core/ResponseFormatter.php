<?php
namespace IaTradeCRM\Core;

/**
 * Formateador de respuestas para APIs
 * Garantiza una estructura consistente en todas las respuestas
 */
class ResponseFormatter {
    /**
     * Formatea una respuesta exitosa
     * 
     * @param array $data Datos a incluir en la respuesta
     * @param string $message Mensaje descriptivo
     * @param array|null $pagination Información de paginación
     * @return array Respuesta formateada
     */
    public static function success($data = [], $message = 'Operación exitosa', $pagination = null) {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
        
        if ($pagination) {
            $response['pagination'] = $pagination;
        }
        
        return $response;
    }
    
    /**
     * Formatea una respuesta de error
     * 
     * @param string $message Mensaje de error
     * @param array $data Datos adicionales
     * @param int $statusCode Código HTTP a devolver
     * @param array|null $pagination Información de paginación
     * @return array Respuesta formateada
     */
    public static function error($message = 'Ha ocurrido un error', $data = [], $statusCode = 500, $pagination = null) {
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'message' => $message,
            'data' => $data
        ];
        
        if ($pagination) {
            $response['pagination'] = $pagination;
        }
        
        return $response;
    }
    
    /**
     * Formatea una respuesta de fallback para UI
     * Siempre devuelve success=true para evitar errores en el frontend
     * 
     * @param string $message Mensaje para el usuario
     * @param array $data Datos por defecto
     * @param array|null $pagination Información de paginación
     * @return array Respuesta formateada
     */
    public static function fallback($message = 'No se pudieron cargar los datos', $data = [], $pagination = null) {
        if (!$pagination && isset($data['pagination'])) {
            $pagination = $data['pagination'];
            unset($data['pagination']);
        }
        
        if (!$pagination) {
            $pagination = [
                'page' => 1,
                'limit' => 25,
                'total' => 0,
                'pages' => 0
            ];
        }
        
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination
        ];
    }
    
    /**
     * Envía una respuesta JSON y termina la ejecución
     * 
     * @param array $response Datos de respuesta
     * @return void
     */
    public static function send($response) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    /**
     * Envía una respuesta de éxito y termina la ejecución
     * 
     * @param array $data Datos a incluir en la respuesta
     * @param string $message Mensaje descriptivo
     * @param array|null $pagination Información de paginación
     * @return void
     */
    public static function sendSuccess($data = [], $message = 'Operación exitosa', $pagination = null) {
        self::send(self::success($data, $message, $pagination));
    }
    
    /**
     * Envía una respuesta de error y termina la ejecución
     * 
     * @param string $message Mensaje de error
     * @param array $data Datos adicionales
     * @param int $statusCode Código HTTP a devolver
     * @param array|null $pagination Información de paginación
     * @return void
     */
    public static function sendError($message = 'Ha ocurrido un error', $data = [], $statusCode = 500, $pagination = null) {
        self::send(self::error($message, $data, $statusCode, $pagination));
    }
    
    /**
     * Envía una respuesta de fallback y termina la ejecución
     * 
     * @param string $message Mensaje para el usuario
     * @param array $data Datos por defecto
     * @param array|null $pagination Información de paginación
     * @return void
     */
    public static function sendFallback($message = 'No se pudieron cargar los datos', $data = [], $pagination = null) {
        self::send(self::fallback($message, $data, $pagination));
    }
}