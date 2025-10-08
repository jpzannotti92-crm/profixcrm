<?php
/**
 * V8 REDIRECT HANDLER
 * 
 * Manejador inteligente de redirecciones para ProfixCRM V8
 * Resuelve problemas de redirección de V7 con lógica inteligente
 * 
 * @version 8.0.0
 * @author ProfixCRM
 */

class V8RedirectHandler {
    private $config;
    private $bypassMode = false;
    private $validationPaths = [];
    
    // Modos de operación
    const MODE_INTELLIGENT = 'intelligent';
    const MODE_DISABLED = 'disabled';
    const MODE_FORCED = 'forced';
    const MODE_DEVELOPMENT = 'development';
    
    // Tipos de redirección
    const TYPE_LOGIN = 'login';
    const TYPE_DASHBOARD = 'dashboard';
    const TYPE_HOME = 'home';
    const TYPE_ERROR = 'error';
    const TYPE_MAINTENANCE = 'maintenance';
    
    public function __construct() {
        $this->config = V8Config::getInstance();
        $this->initialize();
    }
    
    /**
     * Inicializar manejador
     */
    private function initialize() {
        // Configurar rutas de validación
        $this->validationPaths = V8Config::VALIDATION_PATHS;
        
        // Detectar si estamos en modo bypass
        $this->detectBypassMode();
        
        // Registrar manejadores de error
        $this->registerErrorHandlers();
    }
    
    /**
     * Detectar modo bypass
     */
    private function detectBypassMode() {
        // Verificar parámetros de URL
        if (isset($_GET['v8_bypass_redirects']) && $_GET['v8_bypass_redirects'] === 'true') {
            $this->bypassMode = true;
            return;
        }
        
        // Verificar parámetros POST
        if (isset($_POST['v8_bypass_redirects']) && $_POST['v8_bypass_redirects'] === 'true') {
            $this->bypassMode = true;
            return;
        }
        
        // Verificar cabeceras personalizadas
        if (isset($_SERVER['HTTP_X_V8_BYPASS_REDIRECTS']) && $_SERVER['HTTP_X_V8_BYPASS_REDIRECTS'] === 'true') {
            $this->bypassMode = true;
            return;
        }
        
        // Verificar configuración
        if ($this->config->shouldBypassRedirects()) {
            $this->bypassMode = true;
            return;
        }
        
        // Verificar IP de desarrollo
        if ($this->isDevelopmentIP()) {
            $this->bypassMode = true;
            return;
        }
        
        // Verificar rutas de validación
        $currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($this->isValidationPath($currentPath)) {
            $this->bypassMode = true;
            return;
        }
    }
    
    /**
     * Verificar si es IP de desarrollo
     */
    private function isDevelopmentIP() {
        $remoteIP = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $developmentIPs = [
            '127.0.0.1',
            '::1',
            'localhost'
        ];
        
        return in_array($remoteIP, $developmentIPs);
    }
    
    /**
     * Verificar si es ruta de validación
     */
    private function isValidationPath($path) {
        $basename = basename($path);
        return in_array($basename, $this->validationPaths);
    }
    
    /**
     * Registrar manejadores de error
     */
    private function registerErrorHandlers() {
        // Solo en desarrollo o cuando esté habilitado
        if ($this->config->isDebug() || $this->bypassMode) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
            ini_set('display_errors', '0');
        }
    }
    
    /**
     * Verificar si debe redirigir
     */
    public function shouldRedirect($type = self::TYPE_LOGIN) {
        // Siempre bypass en modo bypass
        if ($this->bypassMode) {
            return false;
        }
        
        // Verificar modo de redirección
        $mode = $this->config->getRedirectMode();
        
        switch ($mode) {
            case self::MODE_DISABLED:
                return false;
                
            case self::MODE_DEVELOPMENT:
                return !$this->config->isDevelopment();
                
            case self::MODE_FORCED:
                return true;
                
            case self::MODE_INTELLIGENT:
            default:
                return $this->shouldIntelligentRedirect($type);
        }
    }
    
    /**
     * Lógica de redirección inteligente
     */
    private function shouldIntelligentRedirect($type) {
        // No redirigir en desarrollo
        if ($this->config->isDevelopment()) {
            return false;
        }
        
        // Verificar si el usuario está autenticado
        if ($this->isUserAuthenticated()) {
            // Usuario autenticado
            if ($type === self::TYPE_LOGIN) {
                // No redirigir a login si ya está autenticado
                return false;
            }
            
            if ($type === self::TYPE_DASHBOARD) {
                // Permitir redirigir al dashboard
                return true;
            }
        } else {
            // Usuario no autenticado
            if ($type === self::TYPE_LOGIN) {
                // Redirigir al login
                return true;
            }
            
            if ($type === self::TYPE_DASHBOARD) {
                // No redirigir al dashboard si no está autenticado
                return false;
            }
        }
        
        // Verificar mantenimiento
        if ($this->isMaintenanceMode()) {
            return $type === self::TYPE_MAINTENANCE;
        }
        
        // Verificar errores
        if ($type === self::TYPE_ERROR) {
            return true;
        }
        
        // Por defecto, no redirigir
        return false;
    }
    
    /**
     * Verificar si el usuario está autenticado
     */
    private function isUserAuthenticated() {
        // Verificar sesión solo si no se han enviado headers
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            return true;
        }
        
        // Verificar cookies
        if (isset($_COOKIE['remember_token'])) {
            return $this->validateRememberToken($_COOKIE['remember_token']);
        }
        
        // Verificar token JWT
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $this->validateJWTToken($_SERVER['HTTP_AUTHORIZATION']);
        }
        
        return false;
    }
    
    /**
     * Validar token de recuerdo
     */
    private function validateRememberToken($token) {
        // Implementar validación de token de recuerdo
        // Por ahora, retornar false
        return false;
    }
    
    /**
     * Validar token JWT
     */
    private function validateJWTToken($authHeader) {
        // Implementar validación JWT
        // Por ahora, retornar false
        return false;
    }
    
    /**
     * Verificar modo mantenimiento
     */
    private function isMaintenanceMode() {
        // Verificar archivo de mantenimiento
        $maintenanceFile = __DIR__ . '/../../maintenance.php';
        if (file_exists($maintenanceFile)) {
            return true;
        }
        
        // Verificar configuración
        return $this->config->get('app.maintenance', false);
    }
    
    /**
     * Obtener URL de redirección
     */
    public function getRedirectUrl($type = self::TYPE_LOGIN, $params = []) {
        $baseUrl = $this->config->get('app.url', 'http://localhost');
        
        $paths = [
            self::TYPE_LOGIN => '/auth/login',
            self::TYPE_DASHBOARD => '/dashboard',
            self::TYPE_HOME => '/',
            self::TYPE_ERROR => '/error',
            self::TYPE_MAINTENANCE => '/maintenance'
        ];
        
        $path = $paths[$type] ?? $paths[self::TYPE_LOGIN];
        
        // Construir URL
        $url = rtrim($baseUrl, '/') . $path;
        
        // Agregar parámetros
        if (!empty($params)) {
            $query = http_build_query($params);
            $url .= '?' . $query;
        }
        
        return $url;
    }
    
    /**
     * Ejecutar redirección
     */
    public function redirect($type = self::TYPE_LOGIN, $params = [], $immediate = true) {
        if (!$this->shouldRedirect($type)) {
            return false;
        }
        
        $url = $this->getRedirectUrl($type, $params);
        
        if ($immediate) {
            $this->performRedirect($url);
        }
        
        return $url;
    }
    
    /**
     * Realizar redirección HTTP
     */
    private function performRedirect($url) {
        // Limpiar buffer de salida
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        // Establecer cabeceras
        header('HTTP/1.1 302 Found');
        header('Location: ' . $url);
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        
        // Opcional: agregar cabeceras de seguridad
        if ($this->config->isProduction()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
        }
        
        // Terminar ejecución
        exit();
    }
    
    /**
     * Redirección con JavaScript fallback
     */
    public function redirectWithFallback($type = self::TYPE_LOGIN, $params = []) {
        $url = $this->getRedirectUrl($type, $params);
        
        // Intentar redirección HTTP normal
        if (!headers_sent()) {
            $this->performRedirect($url);
            return;
        }
        
        // Fallback con JavaScript
        echo '<script type="text/javascript">';
        echo 'window.location.href = ' . json_encode($url) . ';';
        echo '</script>';
        
        // Fallback con meta refresh
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '">';
        echo '</noscript>';
        
        exit();
    }
    
    /**
     * Verificar si debe bypassar redirecciones
     */
    public function shouldBypassRedirects() {
        return $this->bypassMode;
    }
    
    /**
     * Activar modo bypass temporal
     */
    public function enableBypassMode() {
        $this->bypassMode = true;
    }
    
    /**
     * Desactivar modo bypass temporal
     */
    public function disableBypassMode() {
        $this->bypassMode = false;
    }
    
    /**
     * Obtener información de depuración
     */
    public function getDebugInfo() {
        return [
            'bypass_mode' => $this->bypassMode,
            'environment' => $this->config->getEnvironment(),
            'redirect_mode' => $this->config->getRedirectMode(),
            'current_path' => $_SERVER['SCRIPT_NAME'] ?? '',
            'is_validation_path' => $this->isValidationPath($_SERVER['SCRIPT_NAME'] ?? ''),
            'is_development_ip' => $this->isDevelopmentIP(),
            'is_user_authenticated' => $this->isUserAuthenticated(),
            'is_maintenance_mode' => $this->isMaintenanceMode()
        ];
    }
    
    /**
     * Limpiar todas las redirecciones pendientes
     */
    public function clearPendingRedirects() {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        // Limpiar cabeceras de redirección si existen
        if (!headers_sent()) {
            header_remove('Location');
            header_remove('Refresh');
        }
    }
    
    /**
     * Método estático para redirección rápida
     */
    public static function redirectTo($type = self::TYPE_LOGIN, $params = []) {
        $handler = new self();
        return $handler->redirect($type, $params);
    }
    
    /**
     * Método estático para verificar si debe redirigir
     */
    public static function shouldRedirectTo($type = self::TYPE_LOGIN) {
        $handler = new self();
        return $handler->shouldRedirect($type);
    }
    
    /**
     * Método estático para obtener URL de redirección
     */
    public static function getRedirectUrlFor($type = self::TYPE_LOGIN, $params = []) {
        $handler = new self();
        return $handler->getRedirectUrl($type, $params);
    }
}

// Función auxiliar para redirecciones rápidas
function v8_redirect($type = V8RedirectHandler::TYPE_LOGIN, $params = []) {
    return V8RedirectHandler::redirectTo($type, $params);
}

function v8_should_redirect($type = V8RedirectHandler::TYPE_LOGIN) {
    return V8RedirectHandler::shouldRedirectTo($type);
}

function v8_redirect_url($type = V8RedirectHandler::TYPE_LOGIN, $params = []) {
    return V8RedirectHandler::getRedirectUrlFor($type, $params);
}