<?php

namespace IaTradeCRM\Core;

class Config
{
    private static $instance = null;
    private $config = [];
    
    private function __construct()
    {
        $this->loadConfig();
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig()
    {
        // Detectar automáticamente el dominio y protocolo
        $this->config['base_url'] = $this->detectBaseUrl();
        $this->config['api_url'] = $this->config['base_url'] . '/api';
        $this->config['frontend_url'] = $this->config['base_url'];
        $this->config['login_url'] = $this->config['base_url'] . '/auth/login';
        
        // Cargar configuración desde archivo si existe
        $configFile = __DIR__ . '/../../config/config.php';
        if (file_exists($configFile)) {
            $fileConfig = include $configFile;
            if (is_array($fileConfig)) {
                $this->config = array_merge($this->config, $fileConfig);
            }
        }
    }
    
    /**
     * Detecta automáticamente la URL base del servidor
     */
    public static function detectBaseUrl(): string
    {
        // Detectar protocolo
        $protocol = 'http';
        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        ) {
            $protocol = 'https';
        }
        
        // Detectar host
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        
        // Detectar path base (en caso de que esté en un subdirectorio)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = '';
        
        if ($scriptName) {
            $pathInfo = pathinfo($scriptName);
            $basePath = $pathInfo['dirname'];
            
            // Limpiar el path base
            if ($basePath === '/' || $basePath === '\\') {
                $basePath = '';
            }
            
            // Remover /public si está presente
            $basePath = str_replace('/public', '', $basePath);
            $basePath = str_replace('\\public', '', $basePath);
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
     * Verifica si estamos en un entorno de desarrollo local
     */
    public static function isLocalDevelopment(): bool
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
    }
    
    public function get($key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
    
    public function set($key, $value)
    {
        $this->config[$key] = $value;
    }
    
    public function getBaseUrl()
    {
        return $this->get('base_url');
    }
    
    public function getLoginUrl()
    {
        return $this->get('login_url');
    }
    
    public function toArray()
    {
        return $this->config;
    }
    
    public function toJson()
    {
        return json_encode($this->config, JSON_PRETTY_PRINT);
    }
}