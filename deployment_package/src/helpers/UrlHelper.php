<?php

namespace IaTradeCRM\Helpers;

class UrlHelper
{
    /**
     * Detecta automáticamente la URL base del servidor
     */
    public static function detectBaseUrl(): string
    {
        // Detectar protocolo
        $protocol = 'http';
        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        ) {
            $protocol = 'https';
        }
        
        // Detectar host
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        
        // For CLI/development server, use localhost without path
        if (PHP_SAPI === 'cli-server' || PHP_SAPI === 'cli') {
            return $protocol . '://' . $host;
        }
        
        // Detectar path base (en caso de que esté en un subdirectorio)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = '';
        
        if ($scriptName) {
            // Normalize Windows paths to forward slashes
            $scriptName = str_replace('\\', '/', $scriptName);
            $pathInfo = pathinfo($scriptName);
            $basePath = $pathInfo['dirname'];
            
            // Limpiar el path base
            if ($basePath === '/' || $basePath === '\\') {
                $basePath = '';
            }
            
            // Remover /public/api, /public, /api del path
            $basePath = str_replace('/public/api', '', $basePath);
            $basePath = str_replace('/public', '', $basePath);
            $basePath = str_replace('/api', '', $basePath);
        }
        
        return $protocol . '://' . $host . $basePath;
    }
    
    /**
     * Obtiene la URL del API dinámicamente
     */
    public static function getApiUrl(): string
    {
        $baseUrl = self::detectBaseUrl();
        
        // Si estamos en desarrollo local sin puerto, usar puerto específico
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (strpos($host, 'localhost') !== false && strpos($host, ':') === false) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $protocol . '://' . $host . ':8000/api';
        }
        
        return $baseUrl . '/api';
    }
    
    /**
     * Obtiene la URL del frontend dinámicamente
     */
    public static function getFrontendUrl(): string
    {
        $baseUrl = self::detectBaseUrl();
        
        // Si estamos en desarrollo local sin puerto, usar puerto específico
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (strpos($host, 'localhost') !== false && strpos($host, ':') === false) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $protocol . '://' . $host . ':3000';
        }
        
        return $baseUrl;
    }
    
    /**
     * Obtiene la URL de login dinámicamente
     */
    public static function getLoginUrl(): string
    {
        // En desarrollo local, usar el dev server en :3000 con ruta /auth/login
        if (self::isLocalDevelopment()) {
            return self::getFrontendUrl() . '/auth/login';
        }
        // En producción, usar hash router en raíz del dominio
        return self::getFrontendUrl() . '/#/auth/login';
    }
    
    /**
     * Obtiene la URL del dashboard dinámicamente
     */
    public static function getDashboardUrl(): string
    {
        return self::getFrontendUrl() . '/dashboard';
    }
    
    /**
     * Verifica si estamos en un entorno de desarrollo local
     */
    public static function isLocalDevelopment(): bool
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
    }
    
    /**
     * Obtiene configuración completa de URLs
     */
    public static function getUrlConfig(): array
    {
        return [
            'base' => self::detectBaseUrl(),
            'api' => self::getApiUrl(),
            'frontend' => self::getFrontendUrl(),
            'login' => self::getLoginUrl(),
            'dashboard' => self::getDashboardUrl(),
            'is_local' => self::isLocalDevelopment()
        ];
    }
}