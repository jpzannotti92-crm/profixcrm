<?php

namespace IaTradeCRM\Middleware;

use IaTradeCRM\Core\Request;

class CorsMiddleware
{
    /**
     * Manejar CORS
     */
    public function handle(Request $request)
    {
        // Configurar headers CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400'); // 24 horas

        // Manejar preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        return true;
    }
}