<?php
/**
 * Funciones auxiliares para iaTrade CRM
 */

if (!function_exists('env')) {
    /**
     * Obtiene una variable de entorno
     */
    function env(string $key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('config')) {
    /**
     * Obtiene un valor de configuración
     */
    function config(string $key, $default = null)
    {
        static $config = [];
        
        if (empty($config)) {
            $config = require __DIR__ . '/../config/database.php';
        }
        
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}

if (!function_exists('response')) {
    /**
     * Crea una respuesta HTTP
     */
    function response($data = null, int $status = 200): \IaTradeCRM\Core\Response
    {
        return new \IaTradeCRM\Core\Response($data, $status);
    }
}

if (!function_exists('request')) {
    /**
     * Obtiene la instancia de Request actual
     */
    function request(): \IaTradeCRM\Core\Request
    {
        static $request = null;
        
        if ($request === null) {
            $request = new \IaTradeCRM\Core\Request();
        }
        
        return $request;
    }
}

if (!function_exists('now')) {
    /**
     * Obtiene la fecha y hora actual
     */
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('formatMoney')) {
    /**
     * Formatea un valor monetario
     */
    function formatMoney(float $amount, string $currency = '$'): string
    {
        return $currency . number_format($amount, 2);
    }
}

if (!function_exists('formatPercent')) {
    /**
     * Formatea un porcentaje
     */
    function formatPercent(float $value): string
    {
        return number_format($value, 2) . '%';
    }
}

if (!function_exists('sanitize')) {
    /**
     * Sanitiza una cadena
     */
    function sanitize(string $string): string
    {
        return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generateToken')) {
    /**
     * Genera un token aleatorio
     */
    function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}

if (!function_exists('isValidEmail')) {
    /**
     * Valida un email
     */
    function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}