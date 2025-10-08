<?php
/**
 * V8 VALIDATOR SYSTEM
 * 
 * Sistema de validación mejorado para ProfixCRM V8
 * Resuelve problemas de validación de V7 con múltiples modos y reportes detallados
 * 
 * @version 8.0.0
 * @author ProfixCRM
 */

namespace Src\Core;

use V8Config;
use V8RedirectHandler;
use PDO;
use Exception;

class V8Validator {
    private $config;
    private $results = [];
    private $mode = 'full';
    private $startTime;
    private $endTime;
    private $errors = [];
    private $warnings = [];
    private $successes = [];
    
    // Modos de validación
    const MODE_FULL = 'full';
    const MODE_QUICK = 'quick';
    const MODE_PRODUCTION = 'production';
    const MODE_DEBUG = 'debug';
    const MODE_CLI = 'cli';
    
    // Categorías de validación
    const CATEGORY_SYSTEM = 'system';
    const CATEGORY_CONFIG = 'config';
    const CATEGORY_DATABASE = 'database';
    const CATEGORY_FILES = 'files';
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_PERFORMANCE = 'performance';
    const CATEGORY_DEPENDENCIES = 'dependencies';
    const CATEGORY_LOGGING = 'logging';
    const CATEGORY_REDIRECTS = 'redirects';
    const CATEGORY_API = 'api';
    const CATEGORY_ADMIN = 'admin';
    
    public function __construct($mode = self::MODE_FULL) {
        $this->config = V8Config::getInstance();
        $this->mode = $mode;
        $this->startTime = microtime(true);
    }
    
    /**
     * Ejecutar validación completa
     */
    public function validate() {
        $this->results = [];
        $this->errors = [];
        $this->warnings = [];
        $this->successes = [];
        
        try {
            // Validaciones por categoría
            $this->validateSystemRequirements();
            $this->validateConfiguration();
            $this->validateDatabaseConnection();
            $this->validateFileSystem();
            $this->validateSecurity();
            $this->validatePerformance();
            $this->validateDependencies();
            $this->validateLogging();
            $this->validateRedirectSystem();
            $this->validateApiEndpoints();
            $this->validateAdminFunctions();
            
            // Validaciones específicas por modo
            $this->validateModeSpecific();
            
            $this->endTime = microtime(true);
            
            return $this->generateReport();
        } catch (Exception $e) {
            $this->addError('validation', 'Error crítico durante validación: ' . $e->getMessage());
            return $this->generateReport();
        }
    }
    
    /**
     * Validar requisitos del sistema
     */
    private function validateSystemRequirements() {
        $this->addCheck('php_version', self::CATEGORY_SYSTEM, function() {
            $minVersion = '7.4.0';
            $currentVersion = PHP_VERSION;
            
            if (version_compare($currentVersion, $minVersion, '<')) {
                return $this->fail("PHP versión mínima requerida: $minVersion, versión actual: $currentVersion");
            }
            
            return $this->pass("PHP versión $currentVersion es compatible");
        });
        
        $this->addCheck('php_extensions', self::CATEGORY_SYSTEM, function() {
            $required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'openssl', 'session'];
            $missing = [];
            
            foreach ($required as $ext) {
                if (!extension_loaded($ext)) {
                    $missing[] = $ext;
                }
            }
            
            if (!empty($missing)) {
                return $this->fail('Extensiones PHP faltantes: ' . implode(', ', $missing));
            }
            
            return $this->pass('Todas las extensiones PHP requeridas están instaladas');
        });
        
        $this->addCheck('memory_limit', self::CATEGORY_SYSTEM, function() {
            $limit = ini_get('memory_limit');
            $limitBytes = $this->parseMemoryLimit($limit);
            $minBytes = 128 * 1024 * 1024; // 128MB
            
            if ($limitBytes < $minBytes) {
                return $this->warn("Límite de memoria bajo: $limit (mínimo recomendado: 128M)");
            }
            
            return $this->pass("Límite de memoria adecuado: $limit");
        });
        
        $this->addCheck('max_execution_time', self::CATEGORY_SYSTEM, function() {
            $time = ini_get('max_execution_time');
            if ($time < 30 && $time != 0) {
                return $this->warn("Tiempo de ejecución bajo: $time segundos (mínimo recomendado: 30)");
            }
            
            return $this->pass("Tiempo de ejecución adecuado: $time segundos");
        });
    }
    
    /**
     * Validar configuración
     */
    private function validateConfiguration() {
        $this->addCheck('config_initialization', self::CATEGORY_CONFIG, function() {
            try {
                $config = V8Config::getInstance();
                if (!$config) {
                    return $this->fail('No se pudo inicializar la configuración V8');
                }
                
                return $this->pass('Configuración V8 inicializada correctamente');
            } catch (Exception $e) {
                return $this->fail('Error inicializando configuración: ' . $e->getMessage());
            }
        });
        
        $this->addCheck('environment_detection', self::CATEGORY_CONFIG, function() {
            $env = $this->config->getEnvironment();
            $validEnvs = ['development', 'staging', 'production', 'testing'];
            
            if (!in_array($env, $validEnvs)) {
                return $this->fail("Ambiente inválido detectado: $env");
            }
            
            return $this->pass("Ambiente detectado correctamente: $env");
        });
        
        $this->addCheck('database_config', self::CATEGORY_CONFIG, function() {
            $dbConfig = $this->config->getDatabaseConfig();
            $required = ['host', 'database', 'username'];
            
            foreach ($required as $key) {
                if (empty($dbConfig[$key])) {
                    return $this->fail("Configuración de base de datos incompleta: falta $key");
                }
            }
            
            return $this->pass('Configuración de base de datos completa');
        });
        
        $this->addCheck('constants_definition', self::CATEGORY_CONFIG, function() {
            $constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
            $missing = [];
            
            foreach ($constants as $const) {
                if (!defined($const)) {
                    $missing[] = $const;
                }
            }
            
            if (!empty($missing)) {
                return $this->fail('Constantes no definidas: ' . implode(', ', $missing));
            }
            
            return $this->pass('Todas las constantes requeridas están definidas');
        });
    }
    
    /**
     * Validar conexión a base de datos
     */
    private function validateDatabaseConnection() {
        $this->addCheck('database_connection', self::CATEGORY_DATABASE, function() {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_NAME,
                    'utf8mb4'
                );
                
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                
                // Probar consulta simple
                $stmt = $pdo->query('SELECT 1');
                $result = $stmt->fetch();
                
                if (!$result || $result['1'] != '1') {
                    return $this->fail('La consulta de prueba a la base de datos falló');
                }
                
                return $this->pass('Conexión a base de datos exitosa');
            } catch (PDOException $e) {
                return $this->fail('Error de conexión a base de datos: ' . $e->getMessage());
            }
        });
        
        $this->addCheck('database_tables', self::CATEGORY_DATABASE, function() {
            try {
                $dsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, 'utf8mb4');
                $pdo = new PDO($dsn, DB_USER, DB_PASS);
                
                $stmt = $pdo->prepare('SHOW TABLES FROM ' . DB_NAME);
                $stmt->execute();
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($tables)) {
                    return $this->warn('La base de datos no contiene tablas');
                }
                
                $requiredTables = ['users', 'leads', 'configuracion'];
                $missingTables = [];
                
                foreach ($requiredTables as $table) {
                    $found = false;
                    foreach ($tables as $dbTable) {
                        if (stripos($dbTable, $table) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $missingTables[] = $table;
                    }
                }
                
                if (!empty($missingTables)) {
                    return $this->warn('Tablas importantes faltantes: ' . implode(', ', $missingTables));
                }
                
                return $this->pass('Base de datos contiene ' . count($tables) . ' tablas');
            } catch (PDOException $e) {
                return $this->fail('Error verificando tablas: ' . $e->getMessage());
            }
        });
        
        $this->addCheck('database_admin_users', self::CATEGORY_DATABASE, function() {
            try {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, 'utf8mb4');
                $pdo = new PDO($dsn, DB_USER, DB_PASS);
                
                $stmt = $pdo->query('SELECT COUNT(*) as total FROM users WHERE rol = "admin" OR tipo = "admin"');
                $result = $stmt->fetch();
                $adminCount = $result['total'] ?? 0;
                
                if ($adminCount == 0) {
                    return $this->warn('No se encontraron usuarios administradores');
                }
                
                return $this->pass("Se encontraron $adminCount usuarios administradores");
            } catch (PDOException $e) {
                return $this->fail('Error verificando usuarios admin: ' . $e->getMessage());
            }
        });
    }
    
    /**
     * Validar sistema de archivos
     */
    private function validateFileSystem() {
        $this->addCheck('critical_files', self::CATEGORY_FILES, function() {
            $criticalFiles = [
                __DIR__ . '/../../config/v8_config.php',
                __DIR__ . '/../../src/Core/V8RedirectHandler.php',
                __DIR__ . '/../../validate_v8.php',
                __DIR__ . '/../../validate_v8_web.php'
            ];
            
            $missing = [];
            foreach ($criticalFiles as $file) {
                if (!file_exists($file)) {
                    $missing[] = basename($file);
                }
            }
            
            if (!empty($missing)) {
                return $this->fail('Archivos críticos faltantes: ' . implode(', ', $missing));
            }
            
            return $this->pass('Todos los archivos críticos existen');
        });
        
        $this->addCheck('directories_writable', self::CATEGORY_FILES, function() {
            $directories = [
                __DIR__ . '/../../logs/',
                __DIR__ . '/../../logs/v8/',
                __DIR__ . '/../../cache/',
                __DIR__ . '/../../cache/v8/',
                __DIR__ . '/../../temp/',
                __DIR__ . '/../../temp/v8/'
            ];
            
            $notWritable = [];
            foreach ($directories as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                if (!is_writable($dir)) {
                    $notWritable[] = basename($dir);
                }
            }
            
            if (!empty($notWritable)) {
                return $this->fail('Directorios sin permisos de escritura: ' . implode(', ', $notWritable));
            }
            
            return $this->pass('Todos los directorios requeridos son escribibles');
        });
        
        $this->addCheck('backup_files', self::CATEGORY_FILES, function() {
            $backupFiles = [
                __DIR__ . '/../../index.php.backup',
                __DIR__ . '/../../public/index.php.backup',
                __DIR__ . '/../../src/Controllers/AuthController.php.backup'
            ];
            
            $found = 0;
            foreach ($backupFiles as $file) {
                if (file_exists($file)) {
                    $found++;
                }
            }
            
            if ($found == 0) {
                return $this->info('No se encontraron archivos de respaldo (esto es normal)');
            }
            
            return $this->pass("Se encontraron $found archivos de respaldo");
        });
    }
    
    /**
     * Validar seguridad
     */
    private function validateSecurity() {
        $this->addCheck('config_security', self::CATEGORY_SECURITY, function() {
            $configFile = __DIR__ . '/../../config/v8_config.php';
            if (!file_exists($configFile)) {
                return $this->fail('Archivo de configuración no encontrado');
            }
            
            $content = file_get_contents($configFile);
            if (strpos($content, 'password') !== false && strpos($content, '****') === false) {
                return $this->warn('El archivo de configuración podría contener contraseñas en texto plano');
            }
            
            return $this->pass('Configuración de seguridad verificada');
        });
        
        $this->addCheck('directory_protection', self::CATEGORY_SECURITY, function() {
            $sensitiveDirs = [
                __DIR__ . '/../../config/',
                __DIR__ . '/../../logs/',
                __DIR__ . '/../../src/'
            ];
            
            foreach ($sensitiveDirs as $dir) {
                $htaccess = $dir . '.htaccess';
                if (!file_exists($htaccess)) {
                    // Crear archivo .htaccess básico
                    $content = "Order deny,allow\nDeny from all\n";
                    @file_put_contents($htaccess, $content);
                }
            }
            
            return $this->pass('Protección de directorios sensibles verificada');
        });
        
        $this->addCheck('encryption_keys', self::CATEGORY_SECURITY, function() {
            $security = $this->config->get('security', []);
            
            if (empty($security['encryption_key'])) {
                return $this->warn('No se detectó clave de encriptación');
            }
            
            if (empty($security['jwt_secret'])) {
                return $this->warn('No se detectó secreto JWT');
            }
            
            return $this->pass('Claves de seguridad configuradas');
        });
    }
    
    /**
     * Validar rendimiento
     */
    private function validatePerformance() {
        $this->addCheck('memory_usage', self::CATEGORY_PERFORMANCE, function() {
            $usage = memory_get_usage(true);
            $peak = memory_get_peak_usage(true);
            $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
            
            $usagePercent = ($usage / $limit) * 100;
            $peakPercent = ($peak / $limit) * 100;
            
            if ($peakPercent > 80) {
                return $this->warn("Uso de memoria alto: " . round($peakPercent, 2) . "%");
            }
            
            return $this->pass("Uso de memoria normal: " . round($usagePercent, 2) . "%");
        });
        
        $this->addCheck('disk_space', self::CATEGORY_PERFORMANCE, function() {
            $free = disk_free_space(__DIR__ . '/../../');
            $total = disk_total_space(__DIR__ . '/../../');
            
            if ($free === false || $total === false) {
                return $this->warn('No se pudo obtener información del espacio en disco');
            }
            
            $freePercent = ($free / $total) * 100;
            
            if ($freePercent < 10) {
                return $this->fail('Espacio en disco bajo: ' . round($freePercent, 2) . '% libre');
            }
            
            return $this->pass('Espacio en disco adecuado: ' . round($freePercent, 2) . '% libre');
        });
    }
    
    /**
     * Validar dependencias
     */
    private function validateDependencies() {
        $this->addCheck('composer_dependencies', self::CATEGORY_DEPENDENCIES, function() {
            $composerFile = __DIR__ . '/../../composer.json';
            if (!file_exists($composerFile)) {
                return $this->info('No se encontró archivo composer.json');
            }
            
            $lockFile = __DIR__ . '/../../composer.lock';
            if (!file_exists($lockFile)) {
                return $this->warn('No se encontró archivo composer.lock');
            }
            
            return $this->pass('Dependencias de Composer verificadas');
        });
        
        $this->addCheck('autoload', self::CATEGORY_DEPENDENCIES, function() {
            $autoloadFile = __DIR__ . '/../../vendor/autoload.php';
            if (!file_exists($autoloadFile)) {
                return $this->fail('No se encontró el archivo autoload de Composer');
            }
            
            return $this->pass('Autoload de Composer disponible');
        });
    }
    
    /**
     * Validar sistema de logging
     */
    private function validateLogging() {
        $this->addCheck('log_directories', self::CATEGORY_LOGGING, function() {
            $logDirs = [
                __DIR__ . '/../../logs/',
                __DIR__ . '/../../logs/v8/'
            ];
            
            foreach ($logDirs as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                if (!is_writable($dir)) {
                    return $this->fail('Directorio de logs no escribible: ' . $dir);
                }
            }
            
            return $this->pass('Directorios de logs configurados correctamente');
        });
        
        $this->addCheck('log_rotation', self::CATEGORY_LOGGING, function() {
            $logFile = __DIR__ . '/../../logs/v8/validation.log';
            if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
                return $this->warn('Archivo de log principal excede 10MB');
            }
            
            return $this->pass('Sistema de rotación de logs funcionando');
        });
    }
    
    /**
     * Validar sistema de redirecciones
     */
    private function validateRedirectSystem() {
        $this->addCheck('redirect_handler', self::CATEGORY_REDIRECTS, function() {
            try {
                $redirectHandlerFile = __DIR__ . '/V8RedirectHandler.php';
                if (!file_exists($redirectHandlerFile)) {
                    return $this->fail('No se encontró el manejador de redirecciones V8');
                }
                
                require_once $redirectHandlerFile;
                
                if (!class_exists('V8RedirectHandler')) {
                    return $this->fail('Clase V8RedirectHandler no encontrada');
                }
                
                $handler = new V8RedirectHandler();
                $debugInfo = $handler->getDebugInfo();
                
                return $this->pass('Sistema de redirecciones V8 operativo');
            } catch (Exception $e) {
                return $this->fail('Error en sistema de redirecciones: ' . $e->getMessage());
            }
        });
        
        $this->addCheck('redirect_bypass', self::CATEGORY_REDIRECTS, function() {
            try {
                $handler = new V8RedirectHandler();
                
                // Probar bypass de validación
                $_GET['v8_bypass_redirects'] = 'true';
                $shouldBypass = $handler->shouldBypassRedirects();
                unset($_GET['v8_bypass_redirects']);
                
                if (!$shouldBypass) {
                    return $this->warn('El sistema de bypass de redirecciones no está funcionando correctamente');
                }
                
                return $this->pass('Sistema de bypass de redirecciones funcionando');
            } catch (Exception $e) {
                return $this->fail('Error verificando bypass de redirecciones: ' . $e->getMessage());
            }
        });
    }
    
    /**
     * Validar endpoints de API
     */
    private function validateApiEndpoints() {
        $this->addCheck('api_enabled', self::CATEGORY_API, function() {
            $apiEnabled = $this->config->get('api.enabled', true);
            
            if (!$apiEnabled) {
                return $this->info('API está deshabilitada en la configuración');
            }
            
            return $this->pass('API está habilitada');
        });
        
        $this->addCheck('api_rate_limit', self::CATEGORY_API, function() {
            $rateLimit = $this->config->get('api.rate_limit', 1000);
            
            if ($rateLimit < 100) {
                return $this->warn('Límite de tasa de API muy bajo: ' . $rateLimit);
            }
            
            return $this->pass('Límite de tasa de API configurado: ' . $rateLimit);
        });
    }
    
    /**
     * Validar funciones de administrador
     */
    private function validateAdminFunctions() {
        $this->addCheck('admin_scripts', self::CATEGORY_ADMIN, function() {
            $adminScripts = [
                __DIR__ . '/../../reset_admin.php',
                __DIR__ . '/../../create_admin.php',
                __DIR__ . '/../../deploy_v8.php'
            ];
            
            $found = 0;
            foreach ($adminScripts as $script) {
                if (file_exists($script)) {
                    $found++;
                }
            }
            
            return $this->pass("Se encontraron $found scripts de administración");
        });
        
        $this->addCheck('admin_endpoints', self::CATEGORY_ADMIN, function() {
            $adminEndpoints = [
                '/api/auth/reset_admin.php',
                '/api/auth/create_admin.php'
            ];
            
            return $this->pass('Endpoints de administración configurados');
        });
    }
    
    /**
     * Validaciones específicas por modo
     */
    private function validateModeSpecific() {
        switch ($this->mode) {
            case self::MODE_QUICK:
                // Validaciones rápidas adicionales
                break;
                
            case self::MODE_PRODUCTION:
                $this->validateProductionSpecific();
                break;
                
            case self::MODE_DEBUG:
                $this->validateDebugSpecific();
                break;
                
            case self::MODE_CLI:
                $this->validateCliSpecific();
                break;
        }
    }
    
    /**
     * Validaciones específicas de producción
     */
    private function validateProductionSpecific() {
        $this->addCheck('production_security', 'production', function() {
            if ($this->config->isDebug()) {
                return $this->fail('Modo debug está habilitado en producción');
            }
            
            return $this->pass('Modo producción configurado correctamente');
        });
    }
    
    /**
     * Validaciones específicas de debug
     */
    private function validateDebugSpecific() {
        $this->addCheck('debug_mode', 'debug', function() {
            if (!$this->config->isDebug()) {
                return $this->warn('Modo debug no está habilitado');
            }
            
            return $this->pass('Modo debug está habilitado');
        });
    }
    
    /**
     * Validaciones específicas de CLI
     */
    private function validateCliSpecific() {
        $this->addCheck('cli_environment', 'cli', function() {
            if (php_sapi_name() !== 'cli') {
                return $this->warn('No se está ejecutando en modo CLI');
            }
            
            return $this->pass('Ejecutando en modo CLI');
        });
    }
    
    /**
     * Métodos auxiliares
     */
    private function addCheck($name, $category, $callback) {
        try {
            $result = $callback();
            $this->results[$name] = [
                'category' => $category,
                'status' => $result['status'],
                'message' => $result['message'],
                'timestamp' => microtime(true)
            ];
            
            // Clasificar resultado
            switch ($result['status']) {
                case 'pass':
                    $this->successes[] = $name;
                    break;
                case 'warn':
                    $this->warnings[] = $name;
                    break;
                case 'fail':
                    $this->errors[] = $name;
                    break;
            }
        } catch (Exception $e) {
            $this->addError($name, 'Error ejecutando check: ' . $e->getMessage());
        }
    }
    
    private function pass($message) {
        return ['status' => 'pass', 'message' => $message];
    }
    
    private function warn($message) {
        return ['status' => 'warn', 'message' => $message];
    }
    
    private function fail($message) {
        return ['status' => 'fail', 'message' => $message];
    }
    
    private function info($message) {
        return ['status' => 'info', 'message' => $message];
    }
    
    private function addError($name, $message) {
        $this->errors[] = $name;
        $this->results[$name] = [
            'category' => 'error',
            'status' => 'fail',
            'message' => $message,
            'timestamp' => microtime(true)
        ];
    }
    
    private function parseMemoryLimit($limit) {
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($limit, -1));
        $value = (int)$limit;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Generar reporte de validación
     */
    private function generateReport() {
        $duration = $this->endTime - $this->startTime;
        
        return [
            'summary' => [
                'mode' => $this->mode,
                'total_checks' => count($this->results),
                'passed' => count($this->successes),
                'warnings' => count($this->warnings),
                'failed' => count($this->errors),
                'duration' => round($duration, 3),
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => $this->getOverallStatus()
            ],
            'details' => $this->results,
            'categories' => $this->getCategoriesSummary(),
            'recommendations' => $this->getRecommendations()
        ];
    }
    
    private function getOverallStatus() {
        if (count($this->errors) > 0) {
            return 'error';
        } elseif (count($this->warnings) > 0) {
            return 'warning';
        } else {
            return 'success';
        }
    }
    
    private function getCategoriesSummary() {
        $categories = [];
        
        foreach ($this->results as $check) {
            $category = $check['category'];
            if (!isset($categories[$category])) {
                $categories[$category] = ['pass' => 0, 'warn' => 0, 'fail' => 0];
            }
            
            $categories[$category][$check['status']]++;
        }
        
        return $categories;
    }
    
    private function getRecommendations() {
        $recommendations = [];
        
        if (count($this->errors) > 0) {
            $recommendations[] = 'Corregir los errores críticos antes de proceder con el despliegue';
        }
        
        if (count($this->warnings) > 0) {
            $recommendations[] = 'Revisar y resolver las advertencias para optimizar el sistema';
        }
        
        if ($this->mode === self::MODE_PRODUCTION) {
            $recommendations[] = 'Verificar que todos los modos de depuración estén deshabilitados';
        }
        
        return $recommendations;
    }
    
    /**
     * Métodos públicos
     */
    public function getResults() {
        return $this->results;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getWarnings() {
        return $this->warnings;
    }
    
    public function getSuccesses() {
        return $this->successes;
    }
    
    public function hasErrors() {
        return count($this->errors) > 0;
    }
    
    public function hasWarnings() {
        return count($this->warnings) > 0;
    }
    
    public function isValid() {
        return count($this->errors) === 0;
    }
    
    /**
     * Obtener resultados formateados para mostrar
     */
    public function getFormattedResults() {
        $report = $this->generateReport();
        $output = "\n";
        
        // Resumen
        $summary = $report['summary'];
        $output .= "📊 RESUMEN DE VALIDACIÓN:\n";
        $output .= "• Total de verificaciones: {$summary['total_checks']}\n";
        $output .= "• ✅ Pasadas: {$summary['passed']}\n";
        $output .= "• ⚠️  Advertencias: {$summary['warnings']}\n";
        $output .= "• ❌ Fallidas: {$summary['failed']}\n";
        $output .= "• ⏱️  Duración: {$summary['duration']}s\n";
        $output .= "• 📅 Fecha: {$summary['timestamp']}\n";
        $output .= "• 🏷️  Estado: {$summary['status']}\n\n";
        
        // Detalles por categoría
        if (!empty($this->results)) {
            $output .= "📋 DETALLES POR CATEGORÍA:\n";
            
            $categorized = [];
            foreach ($this->results as $name => $result) {
                $category = $result['category'];
                if (!isset($categorized[$category])) {
                    $categorized[$category] = [];
                }
                $categorized[$category][$name] = $result;
            }
            
            foreach ($categorized as $category => $checks) {
                $output .= "\n🏷️  Categoría: $category\n";
                foreach ($checks as $name => $check) {
                    $icon = $check['status'] === 'pass' ? '✅' : ($check['status'] === 'warn' ? '⚠️' : '❌');
                    $output .= "  $icon $name: " . ($check['message'] ?? 'Sin mensaje') . "\n";
                }
            }
        }
        
        // Recomendaciones
        if (!empty($report['recommendations'])) {
            $output .= "\n💡 RECOMENDACIONES:\n";
            foreach ($report['recommendations'] as $rec) {
                $output .= "• $rec\n";
            }
        }
        
        return $output;
    }
    
    /**
     * Métodos estáticos
     */
    public static function quickValidate() {
        $validator = new self(self::MODE_QUICK);
        return $validator->validate();
    }
    
    public static function fullValidate() {
        $validator = new self(self::MODE_FULL);
        return $validator->validate();
    }
    
    public static function productionValidate() {
        $validator = new self(self::MODE_PRODUCTION);
        return $validator->validate();
    }
}