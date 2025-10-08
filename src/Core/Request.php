<?php

namespace IaTradeCRM\Core;

class Request
{
    private $data;
    private $headers;
    private $method;
    private $uri;
    public $user; // Usuario autenticado (establecido por middleware)

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->headers = $this->parseHeaders();
        $this->data = $this->parseData();
    }

    /**
     * Parsea la ruta de la URL
     */
    private function parsePath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remover query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        // Normalizar la ruta
        $path = rtrim($path, '/');
        return $path === '' ? '/' : $path;
    }

    /**
     * Parsea los headers HTTP
     */
    private function parseHeaders(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($header)] = $value;
            }
        }
        
        // Headers especiales
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }
        
        return $headers;
    }

    /**
     * Parsea los datos de la solicitud
     */
    private function parseData(): array
    {
        $data = [];
        
        if ($this->method === 'GET') {
            return $_GET;
        }
        
        if ($this->method === 'POST') {
            $data = $_POST;
        }
        
        // Para PUT, PATCH, DELETE, etc.
        $contentType = $this->getHeader('content-type', '');
        
        if (strpos($contentType, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = array_merge($data, $decoded);
            }
        } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str(file_get_contents('php://input'), $parsed);
            $data = array_merge($data, $parsed);
        }
        
        return $data;
    }

    /**
     * Obtiene el método HTTP
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Obtiene la ruta
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Obtiene la query string
     */
    public function getQueryString(): string
    {
        return $this->queryString;
    }

    /**
     * Obtiene un parámetro de query
     */
    public function getQuery(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        
        return $this->query[$key] ?? $default;
    }

    /**
     * Obtiene todos los datos
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Obtiene un dato específico
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Verifica si existe un dato
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Obtiene todos los headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Obtiene un header específico
     */
    public function getHeader(string $name, $default = null)
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Verifica si existe un header
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * Obtiene archivos subidos
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Obtiene un archivo específico
     */
    public function getFile(string $name): ?array
    {
        return $this->files[$name] ?? null;
    }

    /**
     * Verifica si hay archivos subidos
     */
    public function hasFiles(): bool
    {
        return !empty($this->files);
    }

    /**
     * Obtiene la IP del cliente
     */
    public function getClientIp(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Para X-Forwarded-For, tomar la primera IP
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Obtiene el User-Agent
     */
    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Verifica si la solicitud es AJAX
     */
    public function isAjax(): bool
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Verifica si la solicitud es JSON
     */
    public function isJson(): bool
    {
        return strpos($this->getHeader('content-type', ''), 'application/json') !== false;
    }

    /**
     * Verifica si la solicitud es segura (HTTPS)
     */
    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               $this->getHeader('x-forwarded-proto') === 'https';
    }

    /**
     * Obtiene la URL completa
     */
    public function getUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $_SERVER['REQUEST_URI'];
    }

    /**
     * Obtiene la URL base
     */
    public function getBaseUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    /**
     * Valida los datos de entrada
     */
    public function validate(array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $this->get($field);
            $ruleList = is_string($rule) ? explode('|', $rule) : $rule;
            
            foreach ($ruleList as $singleRule) {
                $error = $this->validateField($field, $value, $singleRule);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }
        
        return $errors;
    }

    /**
     * Valida un campo específico
     */
    private function validateField(string $field, $value, string $rule): ?string
    {
        [$ruleName, $parameter] = explode(':', $rule . ':');
        
        switch ($ruleName) {
            case 'required':
                if (empty($value)) {
                    return "El campo {$field} es requerido";
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "El campo {$field} debe ser un email válido";
                }
                break;
                
            case 'min':
                if (!empty($value) && strlen($value) < (int)$parameter) {
                    return "El campo {$field} debe tener al menos {$parameter} caracteres";
                }
                break;
                
            case 'max':
                if (!empty($value) && strlen($value) > (int)$parameter) {
                    return "El campo {$field} no puede tener más de {$parameter} caracteres";
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    return "El campo {$field} debe ser numérico";
                }
                break;
                
            case 'in':
                $options = explode(',', $parameter);
                if (!empty($value) && !in_array($value, $options)) {
                    return "El campo {$field} debe ser uno de: " . implode(', ', $options);
                }
                break;
        }
        
        return null;
    }

    /**
     * Obtiene solo los campos especificados
     */
    public function only(array $fields): array
    {
        return array_intersect_key($this->data, array_flip($fields));
    }

    /**
     * Obtiene todos los campos excepto los especificados
     */
    public function except(array $fields): array
    {
        return array_diff_key($this->data, array_flip($fields));
    }
}