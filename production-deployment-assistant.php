<?php
/**
 * Asistente de Despliegue Automático - iaTrade CRM
 * Sistema inteligente para sincronizar cambios entre desarrollo y producción
 */

class ProductionDeploymentAssistant {
    private $devPath;
    private $prodPath;
    private $config;
    private $logFile;
    private $excludedFiles;
    private $excludedDirs;
    
    public function __construct() {
        $this->devPath = __DIR__;
        // Para este ejemplo, usaremos el mismo directorio como "producción" para demostración
        // En un entorno real, esto sería la ruta al servidor de producción
        $this->prodPath = $this->devPath; // Temporal para demostración
        $this->logFile = $this->devPath . '/logs/deployment.log';
        
        // Archivos y directorios excluidos de la sincronización
        $this->excludedFiles = [
            '.env', '.env.local', '.env.development',
            'config.php', 'database.php',
            '*.log', 'debug_*', 'test_*', 'check_*',
            '.installed', 'composer.lock'
        ];
        
        $this->excludedDirs = [
            'logs', 'storage/logs', 'storage/cache', 'storage/sessions',
            'node_modules', '.git', 'vendor', 'deploy'
        ];
        
        $this->initializeConfig();
    }
    
    private function initializeConfig() {
        $this->config = [
            'production' => [
                'url' => 'https://spin2pay.com',
                'database' => [
                    'host' => 'localhost',
                    'name' => 'spin2pay_profixcrm',
                    'username' => 'spin2pay_profixadmin',
                    'password' => 'Jeanpi9941991@'
                ],
                'ftp' => [
                    'host' => 'ftp.spin2pay.com',
                    'username' => 'jpzannotti92@spin2pay.com',
                    'port' => 21,
                    'remote_path' => '/public_html/'
                ]
            ]
        ];
    }
    
    /**
     * Detecta cambios entre desarrollo y producción
     */
    public function detectChanges() {
        $changes = [
            'modified' => [],
            'new' => [],
            'deleted' => []
        ];
        
        $this->log("🔍 Detectando cambios...");
        
        try {
            // Escanear archivos en desarrollo
            $devFiles = $this->scanDirectory($this->devPath);
            $prodFiles = $this->scanDirectory($this->prodPath);
            
            foreach ($devFiles as $file => $hash) {
                if (!isset($prodFiles[$file])) {
                    $changes['new'][] = $file;
                } elseif ($prodFiles[$file] !== $hash) {
                    $changes['modified'][] = $file;
                }
            }
            
            foreach ($prodFiles as $file => $hash) {
                if (!isset($devFiles[$file])) {
                    $changes['deleted'][] = $file;
                }
            }
            
            $this->log("✅ Cambios detectados: " . 
                      count($changes['new']) . " nuevos, " . 
                      count($changes['modified']) . " modificados, " . 
                      count($changes['deleted']) . " eliminados");
            
        } catch (Exception $e) {
            $this->log("❌ Error detectando cambios: " . $e->getMessage());
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'new' => [],
                'modified' => [],
                'deleted' => []
            ];
        }
        
        return $changes;
    }
    
    /**
     * Sincroniza cambios de desarrollo a producción
     */
    public function syncToProduction($changes = null) {
        if ($changes === null) {
            $changes = $this->detectChanges();
        }
        
        $this->log("🚀 Iniciando sincronización a producción...");
        
        // Crear backup antes de sincronizar
        $this->createBackup();
        
        $synced = 0;
        $errors = 0;
        
        // Sincronizar archivos nuevos y modificados
        foreach (array_merge($changes['new'], $changes['modified']) as $file) {
            if ($this->syncFile($file)) {
                $synced++;
            } else {
                $errors++;
            }
        }
        
        // Eliminar archivos borrados (con confirmación)
        foreach ($changes['deleted'] as $file) {
            if ($this->shouldDeleteFile($file)) {
                if ($this->deleteFile($file)) {
                    $synced++;
                } else {
                    $errors++;
                }
            }
        }
        
        // Actualizar configuraciones específicas de producción
        $this->updateProductionConfigs();
        
        $this->log("✅ Sincronización completada: $synced archivos sincronizados, $errors errores");
        
        return ['synced' => $synced, 'errors' => $errors];
    }
    
    /**
     * Sincroniza un archivo específico
     */
    private function syncFile($relativePath) {
        if ($this->isExcluded($relativePath)) {
            return true; // Archivo excluido, no es error
        }
        
        $sourcePath = $this->devPath . DIRECTORY_SEPARATOR . $relativePath;
        $destPath = $this->prodPath . DIRECTORY_SEPARATOR . $relativePath;
        
        if (!file_exists($sourcePath)) {
            $this->log("⚠️ Archivo fuente no existe: $relativePath");
            return false;
        }
        
        // Crear directorio de destino si no existe
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        if (copy($sourcePath, $destPath)) {
            $this->log("📄 Sincronizado: $relativePath");
            return true;
        } else {
            $this->log("❌ Error sincronizando: $relativePath");
            return false;
        }
    }
    
    /**
     * Actualiza configuraciones específicas de producción
     */
    private function updateProductionConfigs() {
        $this->log("⚙️ Actualizando configuraciones de producción...");
        
        // Asegurar que el archivo .env.production esté correcto
        $envProdPath = $this->prodPath . '/.env.production';
        if (!file_exists($envProdPath)) {
            $this->createProductionEnv();
        }
        
        // Asegurar que config/production.php esté correcto
        $configProdPath = $this->prodPath . '/config/production.php';
        if (!file_exists($configProdPath)) {
            $this->createProductionConfig();
        }
        
        // Crear .htaccess optimizado para producción
        $this->createProductionHtaccess();
        
        $this->log("✅ Configuraciones de producción actualizadas");
    }
    
    /**
     * Crea backup antes de sincronizar
     */
    private function createBackup() {
        $backupDir = $this->prodPath . '/backups/' . date('Y-m-d_H-i-s');
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        // Backup de archivos críticos
        $criticalFiles = [
            'config/config.php',
            'config/production.php',
            '.env.production',
            '.htaccess'
        ];
        
        foreach ($criticalFiles as $file) {
            $sourcePath = $this->prodPath . '/' . $file;
            $backupPath = $backupDir . '/' . $file;
            
            if (file_exists($sourcePath)) {
                $backupFileDir = dirname($backupPath);
                if (!is_dir($backupFileDir)) {
                    mkdir($backupFileDir, 0755, true);
                }
                copy($sourcePath, $backupPath);
            }
        }
        
        $this->log("💾 Backup creado en: $backupDir");
    }
    
    /**
     * Escanea directorio y genera hash de archivos
     */
    private function scanDirectory($path) {
        $files = [];
        
        try {
            if (!is_dir($path)) {
                throw new Exception("Directorio no encontrado: $path");
            }
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $relativePath = str_replace('\\', '/', $relativePath);
                    
                    if (!$this->isExcluded($relativePath)) {
                        $files[$relativePath] = md5_file($file->getPathname());
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log("❌ Error escaneando directorio $path: " . $e->getMessage());
            throw $e;
        }
        
        return $files;
    }
    
    /**
     * Verifica si un archivo debe ser excluido
     */
    private function isExcluded($relativePath) {
        // Verificar archivos excluidos
        foreach ($this->excludedFiles as $pattern) {
            if (fnmatch($pattern, basename($relativePath))) {
                return true;
            }
        }
        
        // Verificar directorios excluidos
        foreach ($this->excludedDirs as $dir) {
            if (strpos($relativePath, $dir . '/') === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verifica si un archivo debe ser eliminado
     */
    private function shouldDeleteFile($relativePath) {
        // No eliminar archivos críticos automáticamente
        $criticalFiles = [
            'config/production.php',
            '.env.production',
            '.htaccess'
        ];
        
        return !in_array($relativePath, $criticalFiles);
    }
    
    /**
     * Elimina un archivo de producción
     */
    private function deleteFile($relativePath) {
        $filePath = $this->prodPath . DIRECTORY_SEPARATOR . $relativePath;
        
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $this->log("🗑️ Eliminado: $relativePath");
                return true;
            } else {
                $this->log("❌ Error eliminando: $relativePath");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Crea archivo .env.production
     */
    private function createProductionEnv() {
        $envContent = '# Configuración de Producción - iaTrade CRM
APP_NAME="iaTrade CRM - Production"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://spin2pay.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=spin2pay_profixcrm
DB_USERNAME=spin2pay_profixadmin
DB_PASSWORD=Jeanpi9941991@

APP_KEY=prod_8f4e9d2a1b7c3e6f9a2d5b8c1e4f7a0b3c6e9f2a5b8d1e4f7a0b3c6e9f2a5b8d
JWT_SECRET=prod_jwt_9a8b7c6d5e4f3a2b1c9d8e7f6a5b4c3d2e1f9a8b7c6d5e4f3a2b1c9d8e7f6a5b4c3d

SESSION_LIFETIME=180
SESSION_SECURE=true
FORCE_HTTPS=true';
        
        file_put_contents($this->prodPath . '/.env.production', $envContent);
    }
    
    /**
     * Crea archivo config/production.php
     */
    private function createProductionConfig() {
        $configContent = '<?php
return [
    "database" => [
        "host" => "localhost",
        "port" => "3306",
        "name" => "spin2pay_profixcrm",
        "username" => "spin2pay_profixadmin",
        "password" => "Jeanpi9941991@",
        "charset" => "utf8mb4",
        "collation" => "utf8mb4_unicode_ci"
    ],
    "app" => [
        "name" => "iaTrade CRM - Production",
        "url" => "https://spin2pay.com",
        "env" => "production",
        "debug" => false,
        "timezone" => "America/Mexico_City"
    ],
    "security" => [
        "key" => "prod_8f4e9d2a1b7c3e6f9a2d5b8c1e4f7a0b3c6e9f2a5b8d1e4f7a0b3c6e9f2a5b8d",
        "jwt_secret" => "prod_jwt_9a8b7c6d5e4f3a2b1c9d8e7f6a5b4c3d2e1f9a8b7c6d5e4f3a2b1c9d8e7f6a5b4c3d",
        "session_lifetime" => 180,
        "password_min_length" => 8,
        "force_https" => true
    ]
];';
        
        file_put_contents($this->prodPath . '/config/production.php', $configContent);
    }
    
    /**
     * Crea .htaccess optimizado para producción
     */
    private function createProductionHtaccess() {
        $htaccessContent = '# iaTrade CRM - Configuración de Producción
RewriteEngine On

# Forzar HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Seguridad
Header always set X-Frame-Options DENY
Header always set X-Content-Type-Options nosniff
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Ocultar información del servidor
ServerTokens Prod
Header unset Server
Header unset X-Powered-By

# Compresión
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

# Cache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
</IfModule>';
        
        file_put_contents($this->prodPath . '/.htaccess', $htaccessContent);
    }
    
    /**
     * Verifica el estado de la base de datos de producción
     */
    private function checkProductionDatabase() {
        try {
            // Usar configuración del archivo .env para producción
            $envFile = $this->devPath . '/.env';
            if (!file_exists($envFile)) {
                throw new Exception("Archivo .env no encontrado");
            }
            
            $envContent = file_get_contents($envFile);
            preg_match('/DB_HOST=(.*)/', $envContent, $hostMatch);
            preg_match('/DB_DATABASE=(.*)/', $envContent, $nameMatch);
            preg_match('/DB_USERNAME=(.*)/', $envContent, $userMatch);
            preg_match('/DB_PASSWORD=(.*)/', $envContent, $passMatch);
            
            $host = trim($hostMatch[1] ?? 'localhost');
            $name = trim($nameMatch[1] ?? '');
            $user = trim($userMatch[1] ?? '');
            $pass = trim($passMatch[1] ?? '');
            
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            // Verificar si existen tablas principales
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            $hasUserTable = $stmt->rowCount() > 0;
            
            return [
                'status' => 'connected',
                'host' => $host,
                'database' => $name,
                'tables_exist' => $hasUserTable,
                'connection_time' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
                'host' => 'localhost',
                'database' => 'spin2pay_profixcrm'
            ];
        }
    }
    
    /**
     * Verifica el estado de la base de datos de desarrollo
     */
    private function checkDevelopmentDatabase() {
        try {
            // Usar configuración local de config.php para desarrollo
            $configFile = $this->devPath . '/config/config.php';
            if (!file_exists($configFile)) {
                throw new Exception("Archivo config.php no encontrado");
            }
            
            $config = include $configFile;
            $dbConfig = $config['database'];
            
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            $hasUserTable = $stmt->rowCount() > 0;
            
            return [
                'status' => 'connected',
                'host' => $dbConfig['host'],
                'database' => $dbConfig['name'],
                'tables_exist' => $hasUserTable,
                'connection_time' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
                'host' => 'localhost',
                'database' => 'iatrade_crm'
            ];
        }
    }
    
    /**
     * Verifica el estado del entorno de producción
     */
    private function checkProductionEnvironment() {
        if (!is_dir($this->prodPath)) {
            return 'not_created';
        }
        
        // Para demostración, verificamos archivos que existen en el proyecto actual
        $requiredFiles = ['.env', 'config/config.php', '.htaccess'];
        $missingFiles = [];
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($this->prodPath . '/' . $file)) {
                $missingFiles[] = $file;
            }
        }
        
        if (!empty($missingFiles)) {
            return 'incomplete';
        }
        
        return 'ready';
    }
    
    /**
     * Obtiene la fecha de la última sincronización
     */
    private function getLastSyncDate() {
        $syncFile = $this->prodPath . '/.last_sync';
        if (file_exists($syncFile)) {
            return file_get_contents($syncFile);
        }
        return 'Nunca';
    }
    
    /**
     * Registra eventos en el log
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        
        // Crear directorio de logs si no existe
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Genera reporte de estado del sistema
     */
    public function generateStatusReport() {
        try {
            $changes = $this->detectChanges();
            
            // Verificar estado de base de datos de producción
            $prodDbStatus = $this->checkProductionDatabase();
            
            // Verificar estado del entorno de producción
            $prodEnvStatus = $this->checkProductionEnvironment();
            
            $report = [
                'timestamp' => date('Y-m-d H:i:s'),
                'environments' => [
                    'development' => [
                        'path' => $this->devPath,
                        'status' => is_dir($this->devPath) ? 'active' : 'inactive',
                        'database' => $this->checkDevelopmentDatabase()
                    ],
                    'production' => [
                    'path' => $this->prodPath,
                    'status' => $prodEnvStatus,
                    'url' => $this->config['production']['url'],
                    'database' => $prodDbStatus
                ]
                ],
                'changes' => $changes,
                'total_changes' => count($changes['new']) + count($changes['modified']) + count($changes['deleted']),
                'last_sync' => $this->getLastSyncDate()
            ];
            
            return $report;
            
        } catch (Exception $e) {
            $this->log("❌ Error generando reporte: " . $e->getMessage());
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s'),
                'environments' => [
                    'development' => ['status' => 'unknown'],
                    'production' => ['status' => 'unknown']
                ],
                'changes' => ['new' => [], 'modified' => [], 'deleted' => []],
                'total_changes' => 0
            ];
        }
    }
    
    /**
     * Interfaz web para el asistente
     */
    public function renderWebInterface() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            header('Content-Type: application/json');
            
            try {
                switch ($action) {
                    case 'detect':
                        $changes = $this->detectChanges();
                        echo json_encode(['status' => 'success', 'changes' => $changes], JSON_UNESCAPED_UNICODE);
                        exit;
                        
                    case 'sync':
                        $result = $this->syncToProduction();
                        echo json_encode(['status' => 'success', 'result' => $result], JSON_UNESCAPED_UNICODE);
                        exit;
                        
                    case 'report':
                        $report = $this->generateStatusReport();
                        echo json_encode(['status' => 'success', 'report' => $report], JSON_UNESCAPED_UNICODE);
                        exit;
                        
                    default:
                        echo json_encode(['status' => 'error', 'message' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
                        exit;
                }
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        // Renderizar interfaz HTML
        include __DIR__ . '/views/deployment-assistant.html';
    }
}

// Ejecutar si se accede directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $assistant = new ProductionDeploymentAssistant();
    
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        
        try {
            switch ($_GET['api']) {
                case 'status':
                    echo json_encode($assistant->generateStatusReport(), JSON_UNESCAPED_UNICODE);
                    break;
                    
                case 'detect':
                    echo json_encode($assistant->detectChanges(), JSON_UNESCAPED_UNICODE);
                    break;
                    
                case 'sync':
                    echo json_encode($assistant->syncToProduction(), JSON_UNESCAPED_UNICODE);
                    break;
                    
                default:
                    echo json_encode(['error' => 'API endpoint not found'], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    } else {
        $assistant->renderWebInterface();
    }
}
?>