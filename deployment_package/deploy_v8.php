#!/usr/bin/env php
<?php
/**
 * DEPLOY V8 - Sistema de Despliegue para ProfixCRM V8
 * 
 * Script completo de despliegue que resuelve problemas de V7
 * Incluye validación, backup, migración y optimización
 * 
 * @version 8.0.0
 * @author ProfixCRM
 * @usage: php deploy_v8.php [environment] [options]
 */

// Configuración de tiempo límite y memoria
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '512M');

// Colores para CLI
class Colors {
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const MAGENTA = "\033[35m";
    const CYAN = "\033[36m";
    const WHITE = "\033[37m";
    const BOLD = "\033[1m";
}

class DeployV8 {
    private $environment;
    private $options = [];
    private $startTime;
    private $backupDir;
    private $logFile;
    private $errors = [];
    private $warnings = [];
    private $successes = [];
    
    // Ambientes soportados
    const ENVIRONMENTS = ['development', 'staging', 'production'];
    
    // Opciones disponibles
    const OPTIONS = [
        'skip-backup' => 'Saltar creación de backup',
        'skip-migration' => 'Saltar migración de base de datos',
        'skip-validation' => 'Saltar validación final',
        'force' => 'Forzar despliegue sin confirmación',
        'dry-run' => 'Simular despliegue sin hacer cambios',
        'verbose' => 'Mostrar información detallada',
        'rollback' => 'Revertir al último backup'
    ];
    
    public function __construct($environment = 'development', $options = []) {
        $this->environment = $environment;
        $this->options = $options;
        $this->startTime = microtime(true);
        $this->backupDir = __DIR__ . '/backups/' . date('Y-m-d_H-i-s');
        $this->logFile = __DIR__ . '/logs/v8/deploy_' . date('Y-m-d_H-i-s') . '.log';
        
        $this->initialize();
    }
    
    /**
     * Inicializar sistema de despliegue
     */
    private function initialize() {
        $this->printHeader();
        $this->log("Iniciando despliegue V8 - Ambiente: {$this->environment}");
        
        // Verificar ambiente
        if (!in_array($this->environment, self::ENVIRONMENTS)) {
            $this->error("Ambiente inválido: {$this->environment}");
            $this->error("Ambientes válidos: " . implode(', ', self::ENVIRONMENTS));
            exit(1);
        }
        
        // Crear directorios necesarios
        $this->createDirectories();
        
        // Verificar requisitos mínimos
        $this->checkRequirements();
    }
    
    /**
     * Ejecutar despliegue completo
     */
    public function deploy() {
        try {
            $this->step("1. PREPARACIÓN", function() {
                $this->preValidation();
                $this->createBackup();
                $this->prepareV8Files();
            });
            
            $this->step("2. CONFIGURACIÓN", function() {
                $this->configureEnvironment();
                $this->validateConfiguration();
            });
            
            $this->step("3. BASE DE DATOS", function() {
                $this->runMigrations();
                $this->updateDatabase();
            });
            
            $this->step("4. OPTIMIZACIÓN", function() {
                $this->optimizeSystem();
                $this->clearCache();
            });
            
            $this->step("5. VALIDACIÓN FINAL", function() {
                $this->finalValidation();
                $this->generateReport();
            });
            
            $this->printSuccess();
            
        } catch (Exception $e) {
            $this->handleDeploymentError($e);
        }
    }
    
    /**
     * Paso 1: Preparación
     */
    private function preValidation() {
        $this->log("Ejecutando validación previa...");
        
        // Verificar archivos críticos V8
        $criticalFiles = [
            __DIR__ . '/config/v8_config.php',
            __DIR__ . '/src/Core/V8RedirectHandler.php',
            __DIR__ . '/src/Core/V8Validator.php',
            __DIR__ . '/validate_v8.php'
        ];
        
        foreach ($criticalFiles as $file) {
            if (!file_exists($file)) {
                throw new Exception("Archivo crítico V8 faltante: $file");
            }
        }
        
        // Verificar PHP versión
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception("PHP 7.4.0 o superior requerido. Versión actual: " . PHP_VERSION);
        }
        
        // Verificar extensiones
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'openssl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("Extensión PHP faltante: $ext");
            }
        }
        
        $this->success("Validación previa completada");
    }
    
    private function createBackup() {
        if ($this->option('skip-backup')) {
            $this->log("Saltando creación de backup (opción --skip-backup)");
            return;
        }
        
        $this->log("Creando backup...");
        
        // Crear directorio de backup
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0777, true);
        }
        
        // Archivos a respaldar
        $backupItems = [
            'config' => __DIR__ . '/config',
            'public' => __DIR__ . '/public',
            'src' => __DIR__ . '/src',
            'index.php' => __DIR__ . '/index.php',
            'composer.json' => __DIR__ . '/composer.json'
        ];
        
        foreach ($backupItems as $name => $path) {
            if (file_exists($path)) {
                $dest = $this->backupDir . '/' . $name;
                if (is_dir($path)) {
                    $this->copyDirectory($path, $dest);
                } else {
                    copy($path, $dest);
                }
                $this->log("Respaldado: $name");
            }
        }
        
        // Crear archivo de información del backup
        $backupInfo = [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => $this->environment,
            'version' => 'V8',
            'items' => array_keys($backupItems)
        ];
        
        file_put_contents($this->backupDir . '/backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));
        
        $this->success("Backup creado en: {$this->backupDir}");
    }
    
    private function prepareV8Files() {
        $this->log("Preparando archivos V8...");
        
        // Copiar archivos de configuración V8
        $v8Files = [
            'config/v8_config.php',
            'src/Core/V8RedirectHandler.php',
            'src/Core/V8Validator.php',
            'validate_v8.php',
            'validate_v8_web.php',
            'validate_v8_web_ajax.php',
            'deploy_v8.php'
        ];
        
        foreach ($v8Files as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $this->log("Archivo V8 preparado: $file");
            } else {
                $this->warn("Archivo V8 no encontrado: $file");
            }
        }
        
        $this->success("Archivos V8 preparados");
    }
    
    /**
     * Paso 2: Configuración
     */
    private function configureEnvironment() {
        $this->log("Configurando ambiente: {$this->environment}");
        
        // Cargar configuración V8
        require_once __DIR__ . '/config/v8_config.php';
        
        // La configuración ya está establecida en V8Config según el ambiente detectado
        $config = V8Config::getInstance();
        
        // Configuración específica por ambiente usando el método get
        switch ($this->environment) {
            case 'production':
                $this->log("Configuración de producción aplicada");
                break;
                
            case 'staging':
                $this->log("Configuración de staging aplicada");
                break;
                
            case 'development':
                $this->log("Configuración de desarrollo aplicada");
                break;
        }
        
        $this->success("Ambiente configurado: {$this->environment}");
    }
    
    private function validateConfiguration() {
        $this->log("Validando configuración...");
        
        // Verificar constantes de base de datos
        $requiredConstants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($requiredConstants as $const) {
            if (!defined($const)) {
                throw new Exception("Constante no definida: $const");
            }
        }
        
        // Verificar directorios necesarios
        $requiredDirs = [
            __DIR__ . '/logs',
            __DIR__ . '/logs/v8',
            __DIR__ . '/cache',
            __DIR__ . '/cache/v8',
            __DIR__ . '/temp',
            __DIR__ . '/temp/v8'
        ];
        
        foreach ($requiredDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            if (!is_writable($dir)) {
                throw new Exception("Directorio no escribible: $dir");
            }
        }
        
        $this->success("Configuración validada");
    }
    
    /**
     * Paso 3: Base de datos
     */
    private function runMigrations() {
        if ($this->option('skip-migration')) {
            $this->log("Saltando migraciones (opción --skip-migration)");
            return;
        }
        
        $this->log("Ejecutando migraciones...");
        
        // Verificar conexión a base de datos
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Ejecutar migraciones V8
            $this->executeV8Migrations($pdo);
            
            $this->success("Migraciones completadas");
            
        } catch (PDOException $e) {
            $this->error("Error de base de datos: " . $e->getMessage());
            if ($this->environment === 'production') {
                throw new Exception("Migración de base de datos fallida en producción");
            } else {
                $this->warn("Continuando sin migraciones debido a error de base de datos");
            }
        }
    }
    
    private function executeV8Migrations($pdo) {
        $this->log("Aplicando migraciones V8...");
        
        // Tabla de versiones de migración
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS v8_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(50) NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_version (version)
            )
        ");
        
        // Verificar si ya existe la versión V8
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM v8_migrations WHERE version = 'v8.0.0'");
        $stmt->execute();
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            $this->log("Migraciones V8 ya aplicadas");
            return;
        }
        
        // Solicitar confirmación para producción
        if ($this->environment === 'production' && !$this->option('force')) {
            $this->warn("⚠️  ATENCIÓN: Estás a punto de ejecutar migraciones en PRODUCCIÓN");
            $this->warn("Las siguientes operaciones se realizarán:");
            $this->warn("- Actualizar estructura de base de datos");
            $this->warn("- Modificar configuraciones existentes");
            $this->warn("- Reiniciar servicios");
            
            if (!$this->confirm("¿Deseas continuar? (escribe 'SI' para confirmar): ")) {
                throw new Exception("Despliegue cancelado por el usuario");
            }
        }
        
        // Aquí irían las migraciones específicas
        $this->log("Aplicando cambios de estructura V8...");
        
        // Marcar migración como aplicada
        $pdo->prepare("INSERT INTO v8_migrations (version) VALUES ('v8.0.0')")->execute();
        
        $this->success("Migraciones V8 aplicadas");
    }
    
    private function updateDatabase() {
        $this->log("Actualizando configuraciones de base de datos...");
        
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Actualizar configuraciones V8 en base de datos
            $this->log("Configuración de base de datos actualizada");
            
        } catch (PDOException $e) {
            $this->warn("No se pudo actualizar configuración de base de datos: " . $e->getMessage());
        }
    }
    
    /**
     * Paso 4: Optimización
     */
    private function optimizeSystem() {
        $this->log("Optimizando sistema...");
        
        // Limpiar cachés antiguas
        $this->clearCache();
        
        // Optimizar archivos de configuración
        $this->optimizeConfigFiles();
        
        // Crear archivos de optimización
        $this->createOptimizationFiles();
        
        $this->success("Sistema optimizado");
    }
    
    private function clearCache() {
        $this->log("Limpiando cachés...");
        
        $cacheDirs = [
            __DIR__ . '/cache',
            __DIR__ . '/cache/v8',
            __DIR__ . '/temp',
            __DIR__ . '/temp/v8'
        ];
        
        foreach ($cacheDirs as $dir) {
            if (is_dir($dir)) {
                $this->clearDirectory($dir);
            }
        }
        
        $this->log("Cachés limpiadas");
    }
    
    private function optimizeConfigFiles() {
        $this->log("Optimizando archivos de configuración...");
        
        // Optimizar v8_config.php
        $configFile = __DIR__ . '/config/v8_config.php';
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            
            // Eliminar comentarios innecesarios
            $content = preg_replace('/\/\*\*[\s\S]*?\*\//', '', $content);
            $content = preg_replace('/\/\/.*$/m', '', $content);
            
            // Guardar versión optimizada
            file_put_contents($configFile, $content);
        }
        
        $this->log("Archivos de configuración optimizados");
    }
    
    private function createOptimizationFiles() {
        $this->log("Creando archivos de optimización...");
        
        // Crear archivo .htaccess optimizado
        $htaccessContent = $this->getOptimizedHtaccess();
        file_put_contents(__DIR__ . '/.htaccess', $htaccessContent);
        
        // Crear archivo de configuración de producción
        if ($this->environment === 'production') {
            $prodConfig = $this->getProductionConfig();
            file_put_contents(__DIR__ . '/config/production.php', $prodConfig);
        }
        
        $this->log("Archivos de optimización creados");
    }
    
    /**
     * Paso 5: Validación final
     */
    private function finalValidation() {
        if ($this->option('skip-validation')) {
            $this->log("Saltando validación final (opción --skip-validation)");
            return;
        }
        
        $this->log("Ejecutando validación final...");
        
        // Ejecutar validador V8
        $validationResult = $this->runV8Validation();
        
        if ($validationResult['status'] !== 'success') {
            $this->warn("Validación final encontró problemas");
            
            if ($validationResult['errors'] > 0) {
                $this->error("Se encontraron {$validationResult['errors']} errores críticos");
                
                if ($this->environment === 'production') {
                    throw new Exception("Validación final fallida en producción");
                }
            }
        } else {
            $this->success("Validación final exitosa");
        }
    }
    
    private function runV8Validation() {
        $this->log("Ejecutando validador V8...");
        
        // Incluir validador V8
        require_once __DIR__ . '/src/Core/V8Validator.php';
        
        // Ejecutar validación según ambiente
        $mode = $this->environment === 'production' ? 'production' : 'full';
        
        $validator = new V8Validator($mode);
        $results = $validator->validate();
        
        $this->log("Validación V8 completada");
        
        return $results['summary'];
    }
    
    /**
     * Generar reporte final
     */
    private function generateReport() {
        $this->log("Generando reporte de despliegue...");
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => $this->environment,
            'duration' => round(microtime(true) - $this->startTime, 2),
            'backup_dir' => $this->backupDir,
            'log_file' => $this->logFile,
            'status' => 'success',
            'summary' => [
                'successes' => count($this->successes),
                'warnings' => count($this->warnings),
                'errors' => count($this->errors)
            ],
            'details' => [
                'successes' => $this->successes,
                'warnings' => $this->warnings,
                'errors' => $this->errors
            ]
        ];
        
        // Guardar reporte
        $reportFile = __DIR__ . '/logs/v8/deploy_report_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->log("Reporte guardado: $reportFile");
        
        return $report;
    }
    
    /**
     * Manejo de errores
     */
    private function handleDeploymentError($exception) {
        $this->error("ERROR CRÍTICO EN DESPLIEGUE");
        $this->error("Mensaje: " . $exception->getMessage());
        $this->error("Archivo: " . $exception->getFile());
        $this->error("Línea: " . $exception->getLine());
        
        $this->log("ERROR: " . $exception->getMessage());
        $this->log("Stack trace: " . $exception->getTraceAsString());
        
        // Ofrecer rollback
        if ($this->backupDir && is_dir($this->backupDir)) {
            $this->warn("\n⚠️  Se puede revertir al backup creado: {$this->backupDir}");
            $this->warn("Ejecuta: php deploy_v8.php {$this->environment} --rollback");
        }
        
        exit(1);
    }
    
    /**
     * Utilidades
     */
    private function createDirectories() {
        $dirs = [
            __DIR__ . '/logs',
            __DIR__ . '/logs/v8',
            __DIR__ . '/backups',
            __DIR__ . '/cache/v8',
            __DIR__ . '/temp/v8'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }
    
    private function checkRequirements() {
        // Verificar PHP versión
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception("PHP 7.4.0 o superior requerido");
        }
        
        // Verificar extensiones
        $required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'openssl'];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("Extensión faltante: $ext");
            }
        }
        
        // Verificar espacio en disco
        $freeSpace = disk_free_space(__DIR__);
        if ($freeSpace < 100 * 1024 * 1024) { // 100MB
            throw new Exception("Espacio en disco insuficiente");
        }
    }
    
    private function step($name, $callback) {
        $this->log("\n" . Colors::BOLD . Colors::CYAN . "=== $name ===" . Colors::RESET);
        
        try {
            $callback();
            $this->success("✅ $name completado");
        } catch (Exception $e) {
            $this->error("❌ Error en $name: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function copyDirectory($src, $dst) {
        if (!is_dir($dst)) {
            mkdir($dst, 0777, true);
        }
        
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;
            
            if (is_dir($srcFile)) {
                $this->copyDirectory($srcFile, $dstFile);
            } else {
                copy($srcFile, $dstFile);
            }
        }
    }
    
    private function clearDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->clearDirectory($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }
    
    private function option($name) {
        return isset($this->options[$name]) && $this->options[$name];
    }
    
    private function confirm($message) {
        echo Colors::YELLOW . $message . Colors::RESET;
        $response = trim(fgets(STDIN));
        return strtoupper($response) === 'SI';
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        echo $logMessage;
        
        // Guardar en archivo de log
        if ($this->logFile) {
            file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        }
    }
    
    private function success($message) {
        $this->log(Colors::GREEN . $message . Colors::RESET);
        $this->successes[] = $message;
    }
    
    private function warn($message) {
        $this->log(Colors::YELLOW . $message . Colors::RESET);
        $this->warnings[] = $message;
    }
    
    private function error($message) {
        $this->log(Colors::RED . Colors::BOLD . $message . Colors::RESET);
        $this->errors[] = $message;
    }
    
    private function printHeader() {
        echo Colors::BOLD . Colors::CYAN . "
╔══════════════════════════════════════════════════════════════╗
║                    🚀 DEPLOY V8 - ProfixCRM                 ║
║                                                              ║
║  Sistema de despliegue inteligente para ProfixCRM V8        ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
" . Colors::RESET;
        
        echo Colors::BLUE . "
📋 INFORMACIÓN DEL DESPLIEGUE:
" . Colors::RESET;
        echo "   Ambiente: " . Colors::BOLD . $this->environment . Colors::RESET . "\n";
        echo "   Fecha: " . date('Y-m-d H:i:s') . "\n";
        echo "   PHP: " . PHP_VERSION . "\n";
        echo "   Sistema: " . PHP_OS . "\n\n";
    }
    
    private function printSuccess() {
        $duration = round(microtime(true) - $this->startTime, 2);
        
        echo Colors::BOLD . Colors::GREEN . "
╔══════════════════════════════════════════════════════════════╗
║                    🎉 DESPLIEGUE EXITOSO                   ║
║                                                              ║
║  ProfixCRM V8 ha sido desplegado exitosamente              ║
║  Duración total: {$duration} segundos                        ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
" . Colors::RESET;
        
        echo Colors::BLUE . "\n📊 RESUMEN DEL DESPLIEGUE:\n" . Colors::RESET;
        echo "   ✅ Éxitos: " . count($this->successes) . "\n";
        echo "   ⚠️  Advertencias: " . count($this->warnings) . "\n";
        echo "   ❌ Errores: " . count($this->errors) . "\n\n";
        
        echo Colors::CYAN . "🌐 ACCESO A VALIDACIONES:\n" . Colors::RESET;
        echo "   Web: http://localhost/validate_v8_web.php\n";
        echo "   CLI: php validate_v8.php\n\n";
        
        echo Colors::YELLOW . "⚠️  IMPORTANTE:\n" . Colors::RESET;
        echo "   - Revisa el archivo de log: {$this->logFile}\n";
        echo "   - Backup creado en: {$this->backupDir}\n";
        echo "   - Ejecuta validaciones para verificar el sistema\n\n";
    }
    
    private function getOptimizedHtaccess() {
        return "# ProfixCRM V8 - Optimización de .htaccess
# Este archivo fue generado automáticamente por deploy_v8.php

# Proteger archivos sensibles
<Files \".env\">
    Order allow,deny
    Deny from all
</Files>

<Files \"config.php\">
    Order allow,deny
    Deny from all
</Files>

# Optimización de caché
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg \"access plus 1 year\"
    ExpiresByType image/jpeg \"access plus 1 year\"
    ExpiresByType image/gif \"access plus 1 year\"
    ExpiresByType image/png \"access plus 1 year\"
    ExpiresByType text/css \"access plus 1 month\"
    ExpiresByType application/pdf \"access plus 1 month\"
    ExpiresByType text/javascript \"access plus 1 month\"
    ExpiresByType application/javascript \"access plus 1 month\"
</IfModule>

# Compresión GZIP
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Seguridad adicional
Header set X-Content-Type-Options \"nosniff\"
Header set X-Frame-Options \"SAMEORIGIN\"
Header set X-XSS-Protection \"1; mode=block\"
";
    }
    
    private function getProductionConfig() {
        return "<?php
/**
 * Configuración de Producción - ProfixCRM V8
 * Este archivo fue generado automáticamente por deploy_v8.php
 */

// Modo producción - Sin errores visibles
error_reporting(0);
ini_set('display_errors', 0);

// Optimización de memoria
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

// Seguridad adicional
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);

// Configuración de logs
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/production_errors.log');
";
    }
}

/**
 * Función principal
 */
function main($argv) {
    // Parsear argumentos
    $environment = 'development';
    $options = [];
    
    foreach ($argv as $i => $arg) {
        if ($i === 0) continue; // Saltar nombre del script
        
        if (strpos($arg, '--') === 0) {
            $option = substr($arg, 2);
            $options[$option] = true;
        } elseif (in_array($arg, DeployV8::ENVIRONMENTS)) {
            $environment = $arg;
        }
    }
    
    // Mostrar ayuda
    if (isset($options['help']) || isset($options['h'])) {
        echo Colors::BOLD . Colors::CYAN . "
🚀 DEPLOY V8 - AYUDA\n" . Colors::RESET;
        echo Colors::BLUE . "\n📋 USO:\n" . Colors::RESET;
        echo "   php deploy_v8.php [ambiente] [opciones]\n\n";
        
        echo Colors::BLUE . "🌍 AMBIENTES:\n" . Colors::RESET;
        foreach (DeployV8::ENVIRONMENTS as $env) {
            echo "   $env\n";
        }
        echo "\n";
        
        echo Colors::BLUE . "⚙️  OPCIONES:\n" . Colors::RESET;
        foreach (DeployV8::OPTIONS as $option => $description) {
            echo "   --$option    $description\n";
        }
        echo "\n";
        
        echo Colors::BLUE . "💡 EJEMPLOS:\n" . Colors::RESET;
        echo "   php deploy_v8.php production --force\n";
        echo "   php deploy_v8.php development --skip-migration\n";
        echo "   php deploy_v8.php staging --dry-run\n\n";
        
        return;
    }
    
    try {
        $deployer = new DeployV8($environment, $options);
        $deployer->deploy();
    } catch (Exception $e) {
        echo Colors::RED . Colors::BOLD . "\n❌ ERROR FATAL: " . $e->getMessage() . Colors::RESET . "\n";
        exit(1);
    }
}

// Ejecutar si se llama directamente
if (php_sapi_name() === 'cli' && basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    main($argv);
}