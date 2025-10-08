<?php

namespace IaTradeCRM\Core;

class Router
{
    private $routes = [];
    private $middleware = [];

    /**
     * Registra una ruta GET
     */
    public function get(string $path, $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Registra una ruta POST
     */
    public function post(string $path, $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Registra una ruta PUT
     */
    public function put(string $path, $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Registra una ruta DELETE
     */
    public function delete(string $path, $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Registra una ruta PATCH
     */
    public function patch(string $path, $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Agrega una ruta al registro
     */
    private function addRoute(string $method, string $path, $handler): void
    {
        $fullPath = $this->groupPrefix . $path;
        $pattern = $this->convertToPattern($fullPath);
        
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => array_merge($this->middleware, $this->groupMiddleware)
        ];
    }

    /**
     * Convierte una ruta a patrón regex
     */
    private function convertToPattern(string $path): string
    {
        // Manejar rutas especiales que contienen caracteres problemáticos
        if (strpos($path, '@') !== false) {
            // Para rutas como /@vite/{path}, escapar el @ y otros caracteres especiales
            $pattern = str_replace('@', '\@', $path);
            $pattern = preg_quote($pattern, '/');
            $pattern = preg_replace('/\\\{([^}]+)\\\}/', '([^/]+)', $pattern);
            return '/^' . $pattern . '$/';
        }
        
        // Escapar caracteres especiales excepto {}
        $pattern = preg_quote($path, '/');
        
        // Convertir {param} a grupos de captura
        $pattern = preg_replace('/\\\{([^}]+)\\\}/', '([^/]+)', $pattern);
        
        return '/^' . $pattern . '$/';
    }

    /**
     * Agrupa rutas con prefijo y middleware
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $oldPrefix = $this->groupPrefix;
        $oldMiddleware = $this->groupMiddleware;
        
        $this->groupPrefix = $prefix;
        $this->groupMiddleware = $middleware;
        
        $callback($this);
        
        $this->groupPrefix = $oldPrefix;
        $this->groupMiddleware = $oldMiddleware;
    }

    /**
     * Agrega middleware global
     */
    public function addMiddleware($middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Despacha la solicitud
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            if (preg_match($route['pattern'], $path, $matches)) {
                // Extraer parámetros de la URL
                array_shift($matches); // Remover la coincidencia completa
                $params = $this->extractParams($route['path'], $matches);
                
                // Ejecutar middleware
                foreach ($route['middleware'] as $middleware) {
                    $result = $middleware->handle($request);
                    if ($result instanceof Response) {
                        return $result;
                    }
                }
                
                // Ejecutar handler
                return $this->executeHandler($route['handler'], $request, $params);
            }
        }
        
        // Ruta no encontrada
        return new Response(['error' => 'Route not found'], 404);
    }

    /**
     * Extrae parámetros de la URL
     */
    private function extractParams(string $routePath, array $matches): array
    {
        $params = [];
        preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);
        
        foreach ($paramNames[1] as $index => $paramName) {
            if (isset($matches[$index])) {
                $params[$paramName] = $matches[$index];
            }
        }
        
        return $params;
    }

    /**
     * Ejecuta el handler de la ruta
     */
    private function executeHandler($handler, Request $request, array $params): Response
    {
        if (is_callable($handler)) {
            $result = call_user_func($handler, ...$params);
        } elseif (is_string($handler) && strpos($handler, '@') !== false) {
            [$controllerName, $method] = explode('@', $handler);
            $controllerClass = "IaTradeCRM\\Controllers\\{$controllerName}";
            
            if (!class_exists($controllerClass)) {
                return new Response(['error' => 'Controller not found'], 500);
            }
            
            $controller = new $controllerClass($request);
            
            if (!method_exists($controller, $method)) {
                return new Response(['error' => 'Method not found'], 500);
            }
            
            $result = call_user_func_array([$controller, $method], $params);
        } else {
            return new Response(['error' => 'Invalid handler'], 500);
        }
        
        // Convertir resultado a Response si es necesario
        if (!$result instanceof Response) {
            if (is_string($result)) {
                return new Response($result);
            } elseif (is_array($result) || is_object($result)) {
                return new Response($result);
            } else {
                return new Response(['data' => $result]);
            }
        }
        
        return $result;
    }

    /**
     * Obtiene todas las rutas registradas
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}