<?php


class V8Config {
    private static $instance = null;
    private $config = [];
    private $environment;
    private $redirectMode;
    
    
    const REDIRECT_ENABLED = 'enabled';
    const REDIRECT_DISABLED = 'disabled';
    const REDIRECT_SMART = 'smart';
    const REDIRECT_DEVELOPMENT = 'development';
    
    
    const ENV_DEVELOPMENT = 'development';
    const ENV_TESTING = 'testing';
    const ENV_STAGING = 'staging';
    const ENV_PRODUCTION = 'production';
    
    
    const VALIDATION_PATHS = [
        'validate_after_deploy.php',
        'production_check.php',
        'validate_cli.php',
        'validate_v8.php',
        'validate_v8_web.php',
        'validate_v8_web_ajax.php',
        'reset_admin.php',
        'create_admin.php',
        'deploy_v8.php'
    ];
    
    
    private function __construct() {
        $this->environment = $this->detectEnvironment();
        $this->redirectMode = $this->determineRedirectMode();
        $this->loadConfiguration();
    }
    
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    
    private function detectEnvironment() {
        
        if (getenv('APP_ENV')) {
            return getenv('APP_ENV');
        }
        
        
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            return self::ENV_DEVELOPMENT;
        }
        
        if (strpos($host, 'staging') !== false || strpos($host, 'test') !== false) {
            return self::ENV_STAGING;
        }
        
        if ($this->isProductionEnvironment()) {
            return self::ENV_PRODUCTION;
        }
        
        return self::ENV_DEVELOPMENT;
    }
    
    
    private function isProductionEnvironment() {
        $indicators = [
            'SERVER_ADDR' => ['production.server.com', 'profixcrm.com'],
            'HTTP_HOST' => ['profixcrm.com', 'www.profixcrm.com'],
            'SERVER_NAME' => ['profixcrm.com', 'www.profixcrm.com']
        ];
        
        foreach ($indicators as $key => $values) {
            if (isset($_SERVER[$key]) && in_array($_SERVER[$key], $values)) {
                return true;
            }
        }
        
        return false;
    }
    
    
    private function determineRedirectMode() {
        
        if ($this->environment === self::ENV_DEVELOPMENT) {
            return self::REDIRECT_DEVELOPMENT;
        }
        
        
        if ($this->environment === self::ENV_PRODUCTION) {
            return self::REDIRECT_SMART;
        }
        
        
        return self::REDIRECT_ENABLED;
    }
    
    
    private function loadConfiguration() {
        
        $baseConfig = $this->loadBaseConfig();
        
        
        $envConfig = $this->loadEnvironmentConfig();
        
        
        $envVars = $this->loadEnvVariables();
        
        
        $this->config = $this->mergeConfig($baseConfig, $envConfig);
        $this->config = $this->mergeConfig($this->config, $envVars);
        
        
        $this->validateConfiguration();
        
        
        $this->setCompatibilityConstants();
    }
    
    
    private function loadBaseConfig() {
        return [
            'app' => [
                'name' => 'ProfixCRM V8',
                'version' => '8.0.0',
                'environment' => $this->environment,
                'debug' => $this->environment !== self::ENV_PRODUCTION,
                'url' => $this->getBaseUrl(),
                'timezone' => 'America/Mexico_City',
                'locale' => 'es',
                'fallback_locale' => 'en',
                'key' => $this->generateEncryptionKey(),
                'cipher' => 'AES-256-CBC'
            ],
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'host' => 'localhost',
                        'port' => '3306',
                        'database' => 'profixcrm',
                        'username' => 'root',
                        'password' => '',
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                        'prefix' => '',
                        'strict' => true,
                        'engine' => null
                    ]
                ]
            ],
            'logging' => [
                'default' => 'daily',
                'channels' => [
                    'daily' => [
                        'driver' => 'daily',
                        'path' => __DIR__ . '/../logs/v8/laravel.log',
                        'level' => $this->getLogLevel(),
                        'days' => 14
                    ],
                    'single' => [
                        'driver' => 'single',
                        'path' => __DIR__ . '/../logs/v8/laravel.log',
                        'level' => $this->getLogLevel()
                    ]
                ]
            ],
            'mail' => [
                'driver' => 'smtp',
                'host' => 'smtp.mailtrap.io',
                'port' => 2525,
                'encryption' => null,
                'username' => null,
                'password' => null,
                'from' => [
                    'address' => 'hello@example.com',
                    'name' => 'ProfixCRM V8'
                ]
            ],
            'jwt' => [
                'secret' => $this->generateJwtSecret(),
                'ttl' => 60,
                'refresh_ttl' => 20160,
                'algo' => 'HS256'
            ],
            'redirection' => [
                'mode' => $this->redirectMode,
                'smart_paths' => self::VALIDATION_PATHS,
                'development_bypass' => true,
                'log_redirects' => $this->environment === self::ENV_DEVELOPMENT
            ],
            'security' => [
                'csrf_protection' => true,
                'encryption_key' => $this->generateEncryptionKey(),
                'session_lifetime' => 120,
                'session_expire_on_close' => false,
                'session_encrypt' => true,
                'session_files' => __DIR__ . '/../storage/sessions',
                'cache_driver' => 'file',
                'cache_path' => __DIR__ . '/../storage/cache'
            ],
            'performance' => [
                'cache_enabled' => $this->environment === self::ENV_PRODUCTION,
                'minify_html' => $this->environment === self::ENV_PRODUCTION,
                'gzip_compression' => $this->environment === self::ENV_PRODUCTION,
                'cdn_enabled' => false,
                'lazy_loading' => true
            ]
        ];
    }
    
    
    private function loadEnvironmentConfig() {
        $envFile = __DIR__ . '/environments/' . $this->environment . '.php';
        
        if (file_exists($envFile)) {
            return require $envFile;
        }
        
        return [];
    }
    
    
    private function loadEnvVariables() {
        $envFile = __DIR__ . '/../.env';
        $config = [];
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                
                $this->setNestedValue($config, str_replace('_', '.', $key), $value);
            }
        }
        
        return $config;
    }
    
    
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $port = $_SERVER['SERVER_PORT'] ?? 80;
        
        $url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        
        if (($protocol === 'http' && $port != 80) || ($protocol === 'https' && $port != 443)) {
            $url .= ':' . $port;
        }
        
        return $url;
    }
    
    
    private function generateEncryptionKey() {
        return base64_encode(random_bytes(32));
    }
    
    
    private function generateJwtSecret() {
        return bin2hex(random_bytes(64));
    }
    
    
    private function getLogLevel() {
        $levels = [
            self::ENV_DEVELOPMENT => 'debug',
            self::ENV_TESTING => 'info',
            self::ENV_STAGING => 'warning',
            self::ENV_PRODUCTION => 'error'
        ];
        
        return $levels[$this->environment] ?? 'info';
    }
    
    
    private function validateConfiguration() {
        
        $required = [
            'app.name',
            'app.version',
            'app.environment',
            'database.connections.mysql.host',
            'database.connections.mysql.database'
        ];
        
        foreach ($required as $key) {
            if (!$this->getNestedValue($this->config, $key)) {
                throw new Exception("Configuración requerida faltante: $key");
            }
        }
    }
    
    
    private function setCompatibilityConstants() {
        
        if (!defined('DB_HOST')) {
            define('DB_HOST', $this->config['database']['connections']['mysql']['host']);
        }
        if (!defined('DB_NAME')) {
            define('DB_NAME', $this->config['database']['connections']['mysql']['database']);
        }
        if (!defined('DB_USER')) {
            define('DB_USER', $this->config['database']['connections']['mysql']['username']);
        }
        if (!defined('DB_PASS')) {
            define('DB_PASS', $this->config['database']['connections']['mysql']['password']);
        }
        
        
        if (!defined('APP_VERSION')) {
            define('APP_VERSION', $this->config['app']['version']);
        }
        if (!defined('APP_ENVIRONMENT')) {
            define('APP_ENVIRONMENT', $this->config['app']['environment']);
        }
        if (!defined('APP_DEBUG')) {
            define('APP_DEBUG', $this->config['app']['debug']);
        }
    }
    
    
    private function mergeConfig($base, $override) {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeConfig($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        
        return $base;
    }
    
    
    private function getNestedValue($array, $key) {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    
    private function setNestedValue(&$array, $key, $value) {
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $this->castValue($value);
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }
    
    
    private function castValue($value) {
        if ($value === 'true' || $value === '1') {
            return true;
        } elseif ($value === 'false' || $value === '0') {
            return false;
        } elseif (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        return $value;
    }
    
    
    public function getEnvironment() {
        return $this->environment;
    }
    
    public function getRedirectMode() {
        return $this->redirectMode;
    }
    
    public function getDatabaseConfig() {
        return $this->config['database']['connections']['mysql'];
    }
    
    public function getAllConfig() {
        return $this->config;
    }
    
    public function get($key, $default = null) {
        return $this->getNestedValue($this->config, $key) ?? $default;
    }
    
    public function has($key) {
        return $this->getNestedValue($this->config, $key) !== null;
    }
    
    public function isDebug() {
        return $this->config['app']['debug'];
    }
    
    public function isProduction() {
        return $this->environment === self::ENV_PRODUCTION;
    }
    
    public function isDevelopment() {
        return $this->environment === self::ENV_DEVELOPMENT;
    }
    
    
    public function isValidationPath($path) {
        $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
        
        foreach (self::VALIDATION_PATHS as $validationPath) {
            if ($scriptName === $validationPath || strpos($path, $validationPath) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    
    public function shouldBypassRedirects() {
        
        $currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($this->isValidationPath($currentPath)) {
            return true;
        }
        
        
        switch ($this->redirectMode) {
            case self::REDIRECT_DISABLED:
                return true;
            case self::REDIRECT_DEVELOPMENT:
                return $this->environment === self::ENV_DEVELOPMENT;
            case self::REDIRECT_SMART:
                return $this->isDevelopmentRequest();
            default:
                return false;
        }
    }
    
    
    private function isDevelopmentRequest() {
        $indicators = [
            'REMOTE_ADDR' => ['127.0.0.1', '::1'],
            'HTTP_HOST' => ['localhost', '127.0.0.1'],
            'SERVER_NAME' => ['localhost', '127.0.0.1']
        ];
        
        foreach ($indicators as $key => $values) {
            if (isset($_SERVER[$key]) && in_array($_SERVER[$key], $values)) {
                return true;
            }
        }
        
        return false;
    }
}


try {
    $v8Config = V8Config::getInstance();
} catch (Exception $e) {
    error_log("Error inicializando V8Config: " . $e->getMessage());
    
    
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', 'profixcrm');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', '');
}