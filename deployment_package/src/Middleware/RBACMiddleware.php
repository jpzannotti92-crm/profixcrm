<?php

namespace IaTradeCRM\Middleware;

use IaTradeCRM\Core\Request;
use IaTradeCRM\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class RBACMiddleware
{
    private $jwtSecret;

    public function __construct($db = null)
    {
        // Intentar obtener el secreto desde variables de entorno
        $secret = $_ENV['JWT_SECRET'] ?? null;
        if (!$secret || $secret === '') {
            // Cargar .env.production como fallback si existe en servidores de producción
            $envProd = __DIR__ . '/../../.env.production';
            if (is_file($envProd)) {
                $lines = @file($envProd, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        if (strpos(ltrim($line), '#') === 0) { continue; }
                        $parts = explode('=', $line, 2);
                        if (count($parts) === 2) {
                            $k = trim($parts[0]);
                            $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
                            $_ENV[$k] = $v;
                            putenv("$k=$v");
                        }
                    }
                    $secret = $_ENV['JWT_SECRET'] ?? null;
                }
            }
        }
        $this->jwtSecret = $secret ?: 'password';
    }

    /**
     * Verificar autenticación y autorización
     */
    public function handle(Request $request, $requiredPermission = null, $requiredRole = null, $returnMode = false)
    {
        try {
            // 1. Verificar autenticación
            $user = $this->authenticateUser($request);
            
            if (!$user) {
                if ($returnMode) {
                    return [
                        'success' => false,
                        'status' => 401,
                        'message' => 'Token de autenticación requerido',
                        'error_code' => 'UNAUTHORIZED'
                    ];
                }
                return $this->unauthorizedResponse('Token de autenticación requerido');
            }

            // 2. Agregar usuario al request
            $request->user = $user;

            // 3. Verificar permisos específicos si se requieren
            if ($requiredPermission && !$this->checkPermission($user, $requiredPermission)) {
                if ($returnMode) {
                    return [
                        'success' => false,
                        'status' => 403,
                        'message' => 'No tienes permisos para realizar esta acción',
                        'error_code' => 'FORBIDDEN'
                    ];
                }
                return $this->forbiddenResponse('No tienes permisos para realizar esta acción');
            }

            // 4. Verificar roles específicos si se requieren
            if ($requiredRole && !$this->checkRole($user, $requiredRole)) {
                if ($returnMode) {
                    return [
                        'success' => false,
                        'status' => 403,
                        'message' => 'No tienes el rol necesario para esta acción',
                        'error_code' => 'FORBIDDEN'
                    ];
                }
                return $this->forbiddenResponse('No tienes el rol necesario para esta acción');
            }

            return true;

        } catch (\Exception $e) {
            if ($returnMode) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Token inválido: ' . $e->getMessage(),
                    'error_code' => 'UNAUTHORIZED'
                ];
            }
            return $this->unauthorizedResponse('Token inválido: ' . $e->getMessage());
        }
    }

    /**
     * Autenticar usuario desde token JWT
     */
    private function authenticateUser(Request $request)
    {
        try {
            $token = $this->extractTokenFromRequest($request);
            
            if (!$token) {
                // Sin token: no autenticado
                return null;
            }

            // Decodificar JWT con Firebase si está disponible; de lo contrario, usar fallback simple
            if (class_exists('Firebase\\JWT\\JWT') && class_exists('Firebase\\JWT\\Key')) {
                $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            } else {
                $decoded = $this->simpleJwtDecode($token, $this->jwtSecret);
            }
            
            // Verificar que el usuario aún existe y está activo
            $user = User::find($decoded->user_id);
            
            if (!$user || $user->status !== 'active') {
                return null;
            }

            // Cargar roles y permisos en tiempo real desde la base de datos
            // para garantizar que los cambios de permisos sean efectivos inmediatamente
            try {
                $dbRoles = $user->getRoles(); // array de [ name, description ]
                $user->roles = array_values(array_unique(array_map(function($r){
                    return is_array($r) && isset($r['name']) ? $r['name'] : (is_string($r) ? $r : null);
                }, $dbRoles)));

                $dbPerms = $user->getPermissions(); // array de [ name, description ]
                $user->permissions = array_values(array_unique(array_map(function($p){
                    return is_array($p) && isset($p['name']) ? $p['name'] : (is_string($p) ? $p : null);
                }, $dbPerms)));
            } catch (\Exception $e) {
                // Si falla la carga desde DB, como fallback usar claims del token si existen
                if (isset($decoded->roles)) {
                    $user->roles = is_array($decoded->roles) ? $decoded->roles : [$decoded->roles];
                }
                if (isset($decoded->permissions)) {
                    $user->permissions = is_array($decoded->permissions) ? $decoded->permissions : [$decoded->permissions];
                }
            }

            return $user;
        } catch (\Exception $e) {
            // Log del error pero no fallar completamente
            error_log('Error en autenticación: ' . $e->getMessage());
            // No crear usuarios "guest" en endpoints críticos, devolver null
            // para que el flujo de autorización responda 401 y evitemos falsos 403 por falta de token
            return null;
        }
    }

    /**
     * Fallback mínimo para decodificar JWT HS256 sin librería externa
     */
    private function simpleJwtDecode($jwt, $secret)
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) { throw new \Exception('Formato de token inválido'); }

        $header = $this->base64url_decode($parts[0]);
        $payload = $this->base64url_decode($parts[1]);
        $signature = $parts[2];

        $headerJson = json_decode($header, true);
        if (!$headerJson || ($headerJson['alg'] ?? '') !== 'HS256') {
            throw new \Exception('Algoritmo no soportado');
        }

        $expected = $this->base64url_encode(hash_hmac('sha256', $parts[0] . '.' . $parts[1], $secret, true));
        if (!hash_equals($expected, $signature)) {
            throw new \Exception('Firma inválida');
        }

        $claims = json_decode($payload, true);
        if (!$claims) { throw new \Exception('Payload inválido'); }
        if (isset($claims['exp']) && time() >= (int)$claims['exp']) { throw new \Exception('Token expirado'); }

        // Convertir a objeto similar al de Firebase\JWT
        return (object)$claims;
    }

    private function base64url_decode($data)
    {
        $replaced = strtr($data, '-_', '+/');
        $padded = str_pad($replaced, strlen($replaced) + (4 - strlen($replaced) % 4) % 4, '=');
        return base64_decode($padded);
    }

    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     */
    private function checkPermission($user, $permission)
    {
        // Super admin debe tener acceso total sin necesidad de permisos explícitos
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        // Si el token incluía roles, respetar super_admin
        if (property_exists($user, 'roles') && is_array($user->roles) && in_array('super_admin', $user->roles, true)) {
            return true;
        }

        // Si el token incluía permisos, respetarlos directamente
        if (property_exists($user, 'permissions') && is_array($user->permissions) && in_array($permission, $user->permissions, true)) {
            return true;
        }

        // En caso contrario, verificar el permiso explícito por roles
        return $user->hasPermission($permission);
    }

    /**
     * Verificar si el usuario tiene un rol específico
     */
    private function checkRole($user, $role)
    {
        return $user->hasRole($role);
    }

    /**
     * Verificar múltiples permisos (OR logic)
     */
    public function checkAnyPermission($user, $permissions)
    {
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar múltiples permisos (AND logic)
     */
    public function checkAllPermissions($user, $permissions)
    {
        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Verificar si el usuario puede acceder a un lead específico
     */
    public function canAccessLead($user, $leadId)
    {
        // Super admin y admin pueden ver todos los leads
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        // Verificar permisos específicos en lugar de roles
        if ($user->hasPermission('leads.view.all')) {
            return true;
        }

        if ($user->hasPermission('leads.view.desk')) {
            return $this->isLeadInUserDesk($user, $leadId);
        }

        if ($user->hasPermission('leads.view.assigned')) {
            return $this->isLeadAssignedToUser($user, $leadId);
        }

        return false;
    }

    /**
     * Verificar si un lead está en la mesa del usuario
     */
    private function isLeadInUserDesk($user, $leadId)
    {
        $userDesks = $user->getDesks();
        $deskIds = array_column($userDesks, 'id');

        if (empty($deskIds)) {
            return false;
        }

        $db = \IaTradeCRM\Database\Connection::getInstance();
        $stmt = $db->prepare("SELECT desk_id FROM leads WHERE id = ?");
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();

        return $lead && in_array($lead['desk_id'], $deskIds);
    }

    /**
     * Verificar si un lead está asignado al usuario
     */
    private function isLeadAssignedToUser($user, $leadId)
    {
        $db = \IaTradeCRM\Database\Connection::getInstance();
        $stmt = $db->prepare("SELECT assigned_to FROM leads WHERE id = ?");
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();

        return $lead && $lead['assigned_to'] == $user->id;
    }

    /**
     * Obtener filtros de leads basados en los permisos específicos del usuario
     */
    public function getLeadsFilters($user)
    {
        // Verificar permisos específicos en lugar de roles
        if ($user->hasPermission('leads.view.all')) {
            // Usuario puede ver todos los leads
            return [];
        }
        
        if ($user->hasPermission('leads.view.desk')) {
            // Usuario puede ver leads de su escritorio/mesa
            $userDesks = $user->getDesks();
            if (!empty($userDesks)) {
                $deskIds = array_column($userDesks, 'id');
                return ['desk_ids' => $deskIds];
            }
        }
        
        if ($user->hasPermission('leads.view.assigned')) {
            // Usuario solo puede ver sus leads asignados
            return ['assigned_to' => $user->id];
        }
        
        // Por defecto, no mostrar leads si no tiene permisos específicos
        return ['assigned_to' => -1];
    }
    

    
    /**
     * Obtener información detallada de acceso del usuario
     */
    public function getUserAccessInfo($user)
    {
        return [
            'user_id' => $user->id,
            'username' => $user->username,
            'roles' => $user->getRoles(),
            'permissions' => $user->getPermissions(),
            'desks' => $user->getDesks(),
            'access_level' => [
                'is_super_admin' => $user->isSuperAdmin(),
                'is_admin' => $user->isAdmin(),
                'is_manager' => $user->isManager(),
                'has_sales_role' => $user->hasRole('sales')
            ],
            'leads_filters' => $this->getLeadsFilters($user)
        ];
    }

    /**
     * Extraer token del request
     */
    private function extractTokenFromRequest(Request $request)
    {
        // Primero intentar desde header Authorization
        $authHeader = $request->getHeader('authorization');
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // También verificar en headers directamente (para compatibilidad)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            // Normalizar claves a minúsculas para evitar problemas de casing
            $hn = [];
            foreach ($headers as $k => $v) { $hn[strtolower($k)] = $v; }
            if (isset($hn['authorization'])) {
                if (preg_match('/Bearer\s+(.*)$/i', $hn['authorization'], $matches)) {
                    return $matches[1];
                }
            }
            // Soportar X-Auth-Token como alternativa
            if (isset($hn['x-auth-token']) && !empty($hn['x-auth-token'])) {
                return $hn['x-auth-token'];
            }
        }

        // Verificar directamente en $_SERVER
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
                return $matches[1];
            }
        }
        // Fallback común en algunos servidores/proxies
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $matches)) {
                return $matches[1];
            }
        }
        // Header alternativo
        if (isset($_SERVER['HTTP_X_AUTH_TOKEN']) && !empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
            return $_SERVER['HTTP_X_AUTH_TOKEN'];
        }

        // Verificar en cookies
        if (isset($_COOKIE['auth_token'])) {
            return $_COOKIE['auth_token'];
        }

        // Verificar en parámetros (POST/GET) como último recurso
        // Primero intentar desde el objeto Request (soporta JSON en el body)
        if (method_exists($request, 'get')) {
            $bodyToken = $request->get('token');
            if (!empty($bodyToken)) { return $bodyToken; }
        }
        // Como fallback, revisar superglobales
        if (isset($_POST['token']) && $_POST['token']) {
            return $_POST['token'];
        }
        if (isset($_GET['token']) && $_GET['token']) {
            return $_GET['token'];
        }

        return null;
    }

    /**
     * Respuesta de no autorizado
     */
    private function unauthorizedResponse($message = 'No autorizado')
    {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED'
        ]);
        exit();
    }

    /**
     * Respuesta de prohibido
     */
    private function forbiddenResponse($message = 'Acceso prohibido')
    {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN'
        ]);
        exit();
    }

    /**
     * Método estático para uso rápido en endpoints
     */
    public static function requirePermission($permission)
    {
        $middleware = new self();
        $request = new Request();
        
        return $middleware->handle($request, $permission);
    }

    /**
     * Método estático para requerir rol
     */
    public static function requireRole($role)
    {
        $middleware = new self();
        $request = new Request();
        
        return $middleware->handle($request, null, $role);
    }

    /**
     * Obtener usuario actual autenticado
     */
    public static function getCurrentUser()
    {
        $middleware = new self();
        $request = new Request();
        
        try {
            $user = $middleware->authenticateUser($request);
            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }
}