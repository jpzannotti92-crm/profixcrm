<?php

namespace IaTradeCRM\Middleware;

use IaTradeCRM\Core\Request;
use IaTradeCRM\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    private $jwtSecret;

    public function __construct()
    {
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production-2024';
    }

    /**
     * Manejar la verificación de autenticación
     */
    public function handle(Request $request)
    {
        try {
            $token = $this->extractTokenFromRequest($request);
            
            if (!$token) {
                return false;
            }

            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // Verificar que el usuario aún existe y está activo
            $user = User::find($decoded->user_id);
            
            if (!$user || $user->status !== 'active') {
                return false;
            }

            // Agregar usuario al request para uso posterior
            $request->user = $user;
            
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Extraer token del request
     */
    private function extractTokenFromRequest(Request $request)
    {
        $authHeader = $request->getHeader('Authorization');
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // También verificar en cookies si no está en header
        if (isset($_COOKIE['auth_token'])) {
            return $_COOKIE['auth_token'];
        }

        // Verificar en localStorage (para SPA)
        if (isset($_SESSION['auth_token'])) {
            return $_SESSION['auth_token'];
        }

        return null;
    }
}