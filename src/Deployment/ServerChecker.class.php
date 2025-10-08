<?php
/**
 * 🔍 PROFIXCRM - VERIFICADOR DE REQUISITOS DEL SERVIDOR
 * Clase completa para verificar todos los requisitos del servidor
 * 
 * @version 1.0.0
 * @author ProfixCRM Team
 */

class ServerChecker {
    private $logger;
    private $results = [];
    private $warnings = [];
    private $errors = [];
    
    // Requisitos mínimos
    private $requirements = [
        'php' => [
            'min_version' => '8.0.0',
            'max_version' => '8.3.99',
            'required_extensions' => [
                'pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 
                'curl', 'gd', 'fileinfo', 'tokenizer', 'xml', 'ctype',
                'session', 'zip', 'zlib', 'bcmath', 'intl'
            ],
            'recommended_extensions' => [
                'redis', 'memcached', 'apcu', 'opcache', 'xdebug'
            ]
        ],
        'mysql' => [
            'min_version' => '5.7.0',
            'max_version' => '8.99.99',
            'alternative' => 'MariaDB 10.2+'
        ],
        'webserver' => [
            'supported' => ['apache', 'nginx', 'litespeed', 'caddy'],
            'modules' => [
                'apache' => ['mod_rewrite', 'mod_headers', 'mod_env', 'mod_mime'],
                'nginx' => ['rewrite', 'headers', 'fastcgi']
            ]
        ],
        'system' => [
            'min_disk_space' => 104857600, // 100MB en bytes
            'min_memory' => 134217728, // 128MB en bytes
            'required_permissions' => [
                'storage/', 'temp/', 'logs/', 'backups/', 'cache/'
            ]
        ]
    ];
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    /**
     * Verificar todos los requisitos del servidor
     */
    public function checkAllRequirements() {
        $this->logger->info('Iniciando verificación completa del servidor...');
        
        $this->results = [
            'success' => true,
            'details' => [],
            'warnings' => [],
            'errors' => []
        ];
        
        try {
            // Fase 1: Verificar PHP
            $this->checkPHPRequirements();
            
            // Fase 2: Verificar extensiones PHP
            $this->checkPHPExtensions();
            
            // Fase 3: Verificar sistema
            $this->checkSystemRequirements();
            
            // Fase 4: Verificar servidor web
            $this->checkWebServer();
            
            // Fase 5: Verificar directorios y permisos
            $this->checkDirectoriesAndPermissions();
            
            // Fase 6: Verificar conectividad de red
            $this->checkNetworkConnectivity();
            
            // Fase 7: Verificar seguridad básica
            $this->checkSecuritySettings();
            
            // Fase 8: Verificación de rendimiento
            $this->checkPerformanceSettings();
            
            // Consolidar resultados
            $this->consolidateResults();
            
            $this->logger->info('Verificación del servidor completada');
            
            return $this->results;
            
        } catch (Exception $e) {
            $this->logger->error('Error en verificación del servidor: ' . $e->getMessage());
            $this->results['success'] = false;
            $this->results['errors'][] = $e->getMessage();
            return $this->results;
        }
    }
    
    /**
     * Verificar requisitos de PHP
     */
    private function checkPHPRequirements() {
        $this->logger->info('Verificando versión de PHP...');
        
        $currentVersion = PHP_VERSION;
        $minVersion = $this->requirements['php']['min_version'];
        $maxVersion = $this->requirements['php']['max_version'];
        
        if (version_compare($currentVersion, $minVersion, '<')) {
            $this->addError("PHP versión $currentVersion es inferior a la mínima requerida ($minVersion)");
            return false;
        }
        
        if (version_compare($currentVersion, $maxVersion, '>')) {
            $this->addWarning("PHP versión $currentVersion es superior a la versión máxima probada ($maxVersion)");
        }
        
        $this->addDetail("✅ PHP versión $currentVersion es compatible");
        $this->logger->info("PHP versión $currentVersion verificada");
        
        return true;
    }
    
    /**
     * Verificar extensiones PHP
     */
    private function checkPHPExtensions() {
        $this->logger->info('Verificando extensiones PHP...');
        
        $requiredExtensions = $this->requirements['php']['required_extensions'];
        $recommendedExtensions = $this->requirements['php']['recommended_extensions'];
        
        // Verificar extensiones requeridas
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $this->addError("Extensión PHP requerida faltante: $extension");
            } else {
                $this->addDetail("✅ Extensión PHP cargada: $extension");
            }
        }
        
        // Verificar extensiones recomendadas
        foreach ($recommendedExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $this->addWarning("Extensión PHP recomendada no disponible: $extension");
            } else {
                $this->addDetail("✅ Extensión PHP recomendada: $extension");
            }
        }
        
        // Verificar configuración específica de extensiones
        $this->checkExtensionConfiguration();
        
        $this->logger->info('Extensiones PHP verificadas');
    }
    
    /**
     * Verificar configuración de extensiones específicas
     */
    private function checkExtensionConfiguration() {
        // Verificar configuración de PDO
        if (extension_loaded('pdo')) {
            $pdoDrivers = PDO::getAvailableDrivers();
            if (!in_array('mysql', $pdoDrivers)) {
                $this->addError('PDO MySQL driver no está disponible');
            } else {
                $this->addDetail('✅ PDO MySQL driver disponible');
            }
        }
        
        // Verificar configuración de OpenSSL
        if (extension_loaded('openssl')) {
            $opensslVersion = OPENSSL_VERSION_NUMBER;
            if ($opensslVersion < 0x10001000) {
                $this->addWarning('OpenSSL versión es muy antigua, se recomienda actualizar');
            } else {
                $this->addDetail('✅ OpenSSL versión es adecuada');
            }
        }
        
        // Verificar límites de PHP
        $this->checkPHPLimits();
    }
    
    /**
     * Verificar límites de PHP
     */
    private function checkPHPLimits() {
        // Tiempo de ejecución
        $maxExecutionTime = ini_get('max_execution_time');
        if ($maxExecutionTime != 0 && $maxExecutionTime < 300) {
            $this->addWarning("max_execution_time es $maxExecutionTime segundos, se recomienda 300+");
        }
        
        // Memoria límite
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        if ($memoryLimitBytes < 268435456) { // 256MB
            $this->addWarning("memory_limit es $memoryLimit, se recomienda 256M+");
        } else {
            $this->addDetail("✅ memory_limit es adecuado: $memoryLimit");
        }
        
        // Tamaño de archivo subido
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $uploadMaxFilesizeBytes = $this->convertToBytes($uploadMaxFilesize);
        if ($uploadMaxFilesizeBytes < 10485760) { // 10MB
            $this->addWarning("upload_max_filesize es $uploadMaxFilesize, se recomienda 10M+");
        }
        
        // Tamaño máximo de POST
        $postMaxSize = ini_get('post_max_size');
        $postMaxSizeBytes = $this->convertToBytes($postMaxSize);
        if ($postMaxSizeBytes < 10485760) { // 10MB
            $this->addWarning("post_max_size es $postMaxSize, se recomienda 10M+");
        }
        
        // Zona horaria
        $timezone = ini_get('date.timezone');
        if (empty($timezone)) {
            $this->addWarning("date.timezone no está configurada");
        } else {
            $this->addDetail("✅ Zona horaria configurada: $timezone");
        }
    }
    
    /**
     * Verificar requisitos del sistema
     */
    private function checkSystemRequirements() {
        $this->logger->info('Verificando requisitos del sistema...');
        
        // Verificar espacio en disco
        $freeSpace = disk_free_space('.');
        $minSpace = $this->requirements['system']['min_disk_space'];
        
        if ($freeSpace < $minSpace) {
            $this->addError('Espacio en disco insuficiente: ' . $this->formatBytes($freeSpace) . 
                          ' (mínimo requerido: ' . $this->formatBytes($minSpace) . ')');
        } else {
            $this->addDetail('✅ Espacio en disco disponible: ' . $this->formatBytes($freeSpace));
        }
        
        // Verificar memoria disponible (aproximada)
        if (function_exists('memory_get_usage')) {
            $currentMemory = memory_get_usage(true);
            $this->addDetail('✅ Memoria actual en uso: ' . $this->formatBytes($currentMemory));
        }
        
        // Verificar sistema operativo
        $os = PHP_OS;
        $supportedOS = ['Linux', 'WINNT', 'Darwin'];
        if (!in_array($os, $supportedOS)) {
            $this->addWarning("Sistema operativo no probado: $os");
        } else {
            $this->addDetail("✅ Sistema operativo soportado: $os");
        }
        
        // Verificar arquitectura
        if (PHP_INT_SIZE === 8) {
            $this->addDetail('✅ Arquitectura de 64 bits detectada');
        } else {
            $this->addWarning('Arquitectura de 32 bits detectada (se recomienda 64 bits)');
        }
    }
    
    /**
     * Verificar servidor web
     */
    private function checkWebServer() {
        $this->logger->info('Verificando servidor web...');
        
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido';
        $supportedServers = $this->requirements['webserver']['supported'];
        
        $detectedServer = 'unknown';
        foreach ($supportedServers as $server) {
            if (stripos($serverSoftware, $server) !== false) {
                $detectedServer = $server;
                break;
            }
        }
        
        if ($detectedServer === 'unknown') {
            $this->addWarning("Servidor web no reconocido o no soportado: $serverSoftware");
        } else {
            $this->addDetail("✅ Servidor web detectado: $detectedServer");
            $this->checkWebServerModules($detectedServer);
        }
        
        // Verificar configuración del servidor
        $this->checkServerConfiguration();
    }
    
    /**
     * Verificar módulos del servidor web
     */
    private function checkWebServerModules($serverType) {
        $requiredModules = $this->requirements['webserver']['modules'][$serverType] ?? [];
        
        if (empty($requiredModules)) {
            return;
        }
        
        if ($serverType === 'apache') {
            $this->checkApacheModules($requiredModules);
        } elseif ($serverType === 'nginx') {
            $this->checkNginxModules($requiredModules);
        }
    }
    
    /**
     * Verificar módulos de Apache
     */
    private function checkApacheModules($requiredModules) {
        if (!function_exists('apache_get_modules')) {
            $this->addWarning('No se pueden verificar módulos de Apache (apache_get_modules no disponible)');
            return;
        }
        
        $loadedModules = apache_get_modules();
        
        foreach ($requiredModules as $module) {
            if (!in_array($module, $loadedModules)) {
                $this->addWarning("Módulo Apache requerido no cargado: $module");
            } else {
                $this->addDetail("✅ Módulo Apache cargado: $module");
            }
        }
    }
    
    /**
     * Verificar módulos de Nginx
     */
    private function checkNginxModules($requiredModules) {
        // Para Nginx, necesitaríamos ejecutar nginx -V o verificar configuración
        // Esto es una verificación básica basada en la configuración disponible
        $this->addDetail('ℹ️ Verificación de módulos de Nginx requiere acceso al sistema');
    }
    
    /**
     * Verificar configuración del servidor
     */
    private function checkServerConfiguration() {
        // Verificar si se está usando HTTPS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                   $_SERVER['SERVER_PORT'] == 443;
        
        if (!$isHttps) {
            $this->addWarning('No se detecta HTTPS - se recomienda usar SSL/TLS en producción');
        } else {
            $this->addDetail('✅ HTTPS detectado');
        }
        
        // Verificar compresión gzip
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        if (stripos($acceptEncoding, 'gzip') === false) {
            $this->addWarning('Compresión gzip no detectada - se recomienda habilitarla');
        } else {
            $this->addDetail('✅ Compresión gzip soportada');
        }
    }
    
    /**
     * Verificar directorios y permisos
     */
    private function checkDirectoriesAndPermissions() {
        $this->logger->info('Verificando directorios y permisos...');
        
        $rootPath = dirname(__DIR__, 2);
        $requiredDirs = $this->requirements['system']['required_permissions'];
        
        foreach ($requiredDirs as $dir) {
            $fullPath = $rootPath . '/' . $dir;
            
            // Verificar existencia
            if (!is_dir($fullPath)) {
                // Intentar crear el directorio
                if (!mkdir($fullPath, 0755, true)) {
                    $this->addError("Directorio requerido no existe y no se puede crear: $dir");
                    continue;
                } else {
                    $this->addDetail("✅ Directorio creado: $dir");
                }
            }
            
            // Verificar permisos de escritura
            if (!is_writable($fullPath)) {
                $this->addError("Sin permisos de escritura en: $dir");
            } else {
                $this->addDetail("✅ Permisos de escritura en: $dir");
            }
            
            // Verificar permisos de lectura
            if (!is_readable($fullPath)) {
                $this->addError("Sin permisos de lectura en: $dir");
            }
            
            // Verificar archivo .htaccess si es Apache
            if ($this->isApache() && $dir === 'storage/') {
                $htaccessPath = $fullPath . '/.htaccess';
                if (!file_exists($htaccessPath)) {
                    $this->createHtaccess($htaccessPath);
                }
            }
        }
        
        // Verificar archivos críticos
        $this->checkCriticalFiles();
    }
    
    /**
     * Verificar archivos críticos
     */
    private function checkCriticalFiles() {
        $rootPath = dirname(__DIR__, 2);
        $criticalFiles = [
            'index.php',
            'config/config.php',
            'src/Core/Config.php',
            'api/index.php',
            'validate_v8.php',
            'deploy_v8.php'
        ];
        
        foreach ($criticalFiles as $file) {
            $filePath = $rootPath . '/' . $file;
            if (!file_exists($filePath)) {
                $this->addError("Archivo crítico faltante: $file");
            } else {
                $this->addDetail("✅ Archivo crítico presente: $file");
            }
        }
    }
    
    /**
     * Verificar conectividad de red
     */
    private function checkNetworkConnectivity() {
        $this->logger->info('Verificando conectividad de red...');
        
        // Verificar si puede resolver nombres de dominio
        $testDomains = ['google.com', 'cloudflare.com'];
        $connectivityIssues = [];
        
        foreach ($testDomains as $domain) {
            $ip = gethostbyname($domain);
            if ($ip === $domain) {
                $connectivityIssues[] = $domain;
            }
        }
        
        if (!empty($connectivityIssues)) {
            $this->addWarning('Problemas de resolución DNS detectados para: ' . implode(', ', $connectivityIssues));
        } else {
            $this->addDetail('✅ Resolución DNS funcionando correctamente');
        }
        
        // Verificar puertos comunes (si es posible)
        $this->checkCommonPorts();
    }
    
    /**
     * Verificar puertos comunes
     */
    private function checkCommonPorts() {
        $commonPorts = [
            80 => 'HTTP',
            443 => 'HTTPS',
            3306 => 'MySQL',
            25 => 'SMTP',
            587 => 'SMTP (TLS)'
        ];
        
        foreach ($commonPorts as $port => $service) {
            $connection = @fsockopen('localhost', $port, $errno, $errstr, 1);
            if ($connection) {
                fclose($connection);
                $this->addDetail("✅ Puerto $port ($service) está abierto");
            } else {
                $this->addWarning("Puerto $port ($service) no está accesible");
            }
        }
    }
    
    /**
     * Verificar configuración de seguridad
     */
    private function checkSecuritySettings() {
        $this->logger->info('Verificando configuración de seguridad...');
        
        // Verificar si display_errors está deshabilitado en producción
        $displayErrors = ini_get('display_errors');
        if ($displayErrors && $displayErrors != '0' && strtolower($displayErrors) != 'off') {
            $this->addWarning('display_errors está habilitado - se recomienda deshabilitar en producción');
        } else {
            $this->addDetail('✅ display_errors está deshabilitado');
        }
        
        // Verificar exposición de PHP
        $exposePhp = ini_get('expose_php');
        if ($exposePhp && $exposePhp != '0' && strtolower($exposePhp) != 'off') {
            $this->addWarning('expose_php está habilitado - se recomienda deshabilitar');
        } else {
            $this->addDetail('✅ expose_php está deshabilitado');
        }
        
        // Verificar open_basedir
        $openBasedir = ini_get('open_basedir');
        if (empty($openBasedir)) {
            $this->addWarning('open_basedir no está configurado - se recomienda restringir acceso a directorios');
        } else {
            $this->addDetail('✅ open_basedir está configurado');
        }
        
        // Verificar si allow_url_fopen está habilitado
        $allowUrlFopen = ini_get('allow_url_fopen');
        if ($allowUrlFopen && $allowUrlFopen != '0' && strtolower($allowUrlFopen) != 'off') {
            $this->addWarning('allow_url_fopen está habilitado - puede representar un riesgo de seguridad');
        }
        
        // Verificar directorio temporal
        $uploadTmpDir = ini_get('upload_tmp_dir');
        if (empty($uploadTmpDir)) {
            $this->addWarning('upload_tmp_dir no está configurado');
        } else {
            $this->addDetail("✅ Directorio temporal configurado: $uploadTmpDir");
        }
    }
    
    /**
     * Verificar configuración de rendimiento
     */
    private function checkPerformanceSettings() {
        $this->logger->info('Verificando configuración de rendimiento...');
        
        // Verificar OPcache
        if (extension_loaded('opcache')) {
            $opcacheEnabled = ini_get('opcache.enable');
            if ($opcacheEnabled && $opcacheEnabled != '0' && strtolower($opcacheEnabled) != 'off') {
                $this->addDetail('✅ OPcache está habilitado');
                
                // Verificar configuración de OPcache
                $opcacheMemory = ini_get('opcache.memory_consumption');
                if ($opcacheMemory < 128) {
                    $this->addWarning("opcache.memory_consumption es $opcacheMemoryMB, se recomienda 128M+");
                }
            } else {
                $this->addWarning('OPcache está deshabilitado - se recomienda habilitar para mejor rendimiento');
            }
        } else {
            $this->addWarning('OPcache no está disponible - se recomienda instalar para mejor rendimiento');
        }
        
        // Verificar realpath_cache
        $realpathCacheSize = ini_get('realpath_cache_size');
        $realpathCacheSizeBytes = $this->convertToBytes($realpath_cache_size);
        if ($realpathCacheSizeBytes < 4194304) { // 4MB
            $this->addWarning("realpath_cache_size es $realpathCacheSize, se recomienda 4M+");
        } else {
            $this->addDetail("✅ realpath_cache_size es adecuado: $realpathCacheSize");
        }
        
        // Verificar max_input_vars
        $maxInputVars = ini_get('max_input_vars');
        if ($maxInputVars < 3000) {
            $this->addWarning("max_input_vars es $maxInputVars, se recomienda 3000+ para formularios grandes");
        } else {
            $this->addDetail("✅ max_input_vars es adecuado: $maxInputVars");
        }
    }
    
    /**
     * Métodos auxiliares
     */
    
    private function addDetail($message) {
        $this->results['details'][] = $message;
    }
    
    private function addWarning($message) {
        $this->results['warnings'][] = $message;
        $this->warnings[] = $message;
    }
    
    private function addError($message) {
        $this->results['errors'][] = $message;
        $this->errors[] = $message;
        $this->results['success'] = false;
    }
    
    private function consolidateResults() {
        $totalChecks = count($this->results['details']) + count($this->warnings) + count($this->errors);
        $successRate = $totalChecks > 0 ? (count($this->results['details']) / $totalChecks) * 100 : 0;
        
        $this->results['summary'] = [
            'total_checks' => $totalChecks,
            'passed' => count($this->results['details']),
            'warnings' => count($this->warnings),
            'errors' => count($this->errors),
            'success_rate' => round($successRate, 2)
        ];
        
        // Determinar si el servidor es apto
        if (count($this->errors) === 0 && $successRate >= 80) {
            $this->results['recommendation'] = 'El servidor cumple con los requisitos mínimos para ProfixCRM';
        } elseif (count($this->errors) === 0) {
            $this->results['recommendation'] = 'El servidor es apto pero se recomienda corregir las advertencias';
        } else {
            $this->results['recommendation'] = 'El servidor NO cumple con los requisitos mínimos. Se deben corregir los errores antes de continuar.';
        }
    }
    
    private function convertToBytes($value) {
        if (is_numeric($value)) {
            return $value;
        }
        
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    private function isApache() {
        return isset($_SERVER['SERVER_SOFTWARE']) && 
               stripos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false;
    }
    
    private function createHtaccess($path) {
        $htaccessContent = "Order deny,allow\nDeny from all\n";
        file_put_contents($path, $htaccessContent);
        $this->addDetail("✅ Archivo .htaccess de seguridad creado");
    }
    
    /**
     * Métodos públicos adicionales
     */
    
    public function getPHPInfo() {
        ob_start();
        phpinfo();
        $phpInfo = ob_get_clean();
        return $phpInfo;
    }
    
    public function getSystemInfo() {
        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'loaded_extensions' => get_loaded_extensions(),
            'ini_settings' => [
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_input_vars' => ini_get('max_input_vars'),
                'date.timezone' => ini_get('date.timezone')
            ]
        ];
    }
    
    public function generateServerReport() {
        $this->checkAllRequirements();
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'server_info' => $this->getSystemInfo(),
            'requirements_check' => $this->results,
            'recommendations' => $this->generateRecommendations()
        ];
        
        return $report;
    }
    
    private function generateRecommendations() {
        $recommendations = [];
        
        // Recomendaciones basadas en los resultados
        if (!empty($this->errors)) {
            $recommendations[] = 'Corregir todos los errores antes de proceder con la instalación';
        }
        
        if (!empty($this->warnings)) {
            $recommendations[] = 'Considerar corregir las advertencias para un rendimiento óptimo';
        }
        
        // Recomendaciones generales
        $recommendations[] = 'Mantener PHP y extensiones actualizadas';
        $recommendations[] = 'Habilitar OPcache para mejorar el rendimiento';
        $recommendations[] = 'Usar HTTPS en producción';
        $recommendations[] = 'Configurar backups automáticos';
        $recommendations[] = 'Monitorear logs del sistema regularmente';
        
        return $recommendations;
    }
}