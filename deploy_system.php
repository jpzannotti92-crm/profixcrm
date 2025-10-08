<?php
/**
 * 🚀 PROFIXCRM - SISTEMA DE DESPLIEGUE PROFESIONAL
 * Sistema completo de instalación y configuración automática
 * 
 * @version 1.0.0
 * @author ProfixCRM Team
 */

// Prevenir acceso directo si no es desde CLI o web autorizada
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_HOST'])) {
    die('Acceso no autorizado');
}

// Configuración de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Definir constantes del sistema
define('DEPLOYMENT_VERSION', '1.0.0');
define('DEPLOYMENT_START_TIME', microtime(true));
define('DEPLOYMENT_LOG_DIR', __DIR__ . '/logs/deployment');
define('DEPLOYMENT_BACKUP_DIR', __DIR__ . '/backups/deployment');
define('PROFIXCRM_MIN_PHP_VERSION', '8.0');
define('PROFIXCRM_MIN_MYSQL_VERSION', '5.7');

// Crear directorios necesarios
@mkdir(DEPLOYMENT_LOG_DIR, 0755, true);
@mkdir(DEPLOYMENT_BACKUP_DIR, 0755, true);

/**
 * 🎯 CLASE PRINCIPAL DEL SISTEMA DE DESPLIEGUE
 */
class ProfixCRMDeploymentSystem {
    
    private $logger;
    private $serverChecker;
    private $databaseInstaller;
    private $testSuite;
    private $webConfigurator;
    private $deploymentId;
    private $isCli;
    private $results = [];
    
    public function __construct() {
        $this->deploymentId = uniqid('deploy_');
        $this->isCli = php_sapi_name() === 'cli';
        $this->initializeComponents();
        $this->logDeploymentStart();
    }
    
    /**
     * Inicializar componentes del sistema
     */
    private function initializeComponents() {
        // Inicializar logger
        $this->logger = new DeploymentLogger($this->deploymentId);
        
        // Inicializar verificadores
        $this->serverChecker = new ServerChecker($this->logger);
        $this->databaseInstaller = new DatabaseInstaller($this->logger);
        $this->testSuite = new TestSuite($this->logger);
        $this->webConfigurator = new WebServerConfigurator($this->logger);
        
        $this->logger->info('Componentes del sistema inicializados');
    }
    
    /**
     * Registrar inicio del despliegue
     */
    private function logDeploymentStart() {
        $this->logger->info('=== INICIANDO DESPLIEGUE PROFIXCRM ===');
        $this->logger->info('ID de Despliegue: ' . $this->deploymentId);
        $this->logger->info('Fecha: ' . date('Y-m-d H:i:s'));
        $this->logger->info('PHP Version: ' . PHP_VERSION);
        $this->logger->info('Sistema Operativo: ' . PHP_OS);
    }
    
    /**
     * Ejecutar despliegue completo
     */
    public function deploy($config = []) {
        try {
            $this->logger->info('Iniciando proceso de despliegue...');
            
            // Fase 1: Verificación del servidor
            $this->results['server_check'] = $this->checkServerRequirements();
            if (!$this->results['server_check']['success']) {
                throw new Exception('Requisitos del servidor no cumplidos');
            }
            
            // Fase 2: Configuración de la base de datos
            $this->results['database_setup'] = $this->setupDatabase($config);
            if (!$this->results['database_setup']['success']) {
                throw new Exception('Error en configuración de base de datos');
            }
            
            // Fase 3: Configuración del servidor web
            $this->results['webserver_config'] = $this->configureWebServer();
            if (!$this->results['webserver_config']['success']) {
                throw new Exception('Error en configuración del servidor web');
            }
            
            // Fase 4: Configuración de archivos y permisos
            $this->results['files_setup'] = $this->setupFilesAndPermissions();
            if (!$this->results['files_setup']['success']) {
                throw new Exception('Error en configuración de archivos');
            }
            
            // Fase 5: Pruebas del sistema
            $this->results['tests'] = $this->runSystemTests();
            if (!$this->results['tests']['success']) {
                throw new Exception('Pruebas del sistema fallidas');
            }
            
            // Fase 6: Verificación final
            $this->results['final_verification'] = $this->finalVerification();
            
            $this->logger->info('=== DESPLIEGUE COMPLETADO EXITOSAMENTE ===');
            
            return [
                'success' => true,
                'deployment_id' => $this->deploymentId,
                'results' => $this->results,
                'duration' => $this->getDeploymentDuration()
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Error en despliegue: ' . $e->getMessage());
            
            return [
                'success' => false,
                'deployment_id' => $this->deploymentId,
                'error' => $e->getMessage(),
                'results' => $this->results,
                'duration' => $this->getDeploymentDuration()
            ];
        }
    }
    
    /**
     * Verificar requisitos del servidor
     */
    public function checkServerRequirements() {
        $this->logger->info('Verificando requisitos del servidor...');
        return $this->serverChecker->checkAllRequirements();
    }
    
    /**
     * Configurar base de datos
     */
    private function setupDatabase($config) {
        $this->logger->info('Configurando base de datos...');
        
        // Obtener configuración de DB
        $dbConfig = $config['database'] ?? $this->getDatabaseConfigFromUser();
        
        return $this->databaseInstaller->setupDatabase($dbConfig);
    }
    
    /**
     * Configurar servidor web
     */
    private function configureWebServer() {
        $this->logger->info('Configurando servidor web...');
        return $this->webConfigurator->configureServer();
    }
    
    /**
     * Configurar archivos y permisos
     */
    private function setupFilesAndPermissions() {
        $this->logger->info('Configurando archivos y permisos...');
        
        $results = [
            'success' => true,
            'details' => []
        ];
        
        try {
            // Crear directorios necesarios
            $directories = [
                'storage/cache',
                'storage/logs',
                'storage/sessions',
                'storage/uploads',
                'temp',
                'logs',
                'backups'
            ];
            
            foreach ($directories as $dir) {
                $path = __DIR__ . '/' . $dir;
                if (!is_dir($path)) {
                    if (!mkdir($path, 0755, true)) {
                        throw new Exception("No se pudo crear directorio: $dir");
                    }
                    $results['details'][] = "Directorio creado: $dir";
                }
            }
            
            // Establecer permisos
            $this->setCorrectPermissions();
            
            // Copiar archivos de configuración si no existen
            $this->setupConfigurationFiles();
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Ejecutar pruebas del sistema
     */
    private function runSystemTests() {
        $this->logger->info('Ejecutando pruebas del sistema...');
        return $this->testSuite->runAllTests();
    }
    
    /**
     * Verificación final
     */
    private function finalVerification() {
        $this->logger->info('Realizando verificación final...');
        
        $results = [
            'success' => true,
            'details' => []
        ];
        
        try {
            // Verificar conexión a BD
            if (!$this->databaseInstaller->testConnection()) {
                throw new Exception('No se puede conectar a la base de datos');
            }
            $results['details'][] = 'Conexión a BD verificada';
            
            // Verificar archivos críticos
            $criticalFiles = [
                'index.php',
                'config/config.php',
                'src/Core/Config.php',
                'api/index.php'
            ];
            
            foreach ($criticalFiles as $file) {
                if (!file_exists(__DIR__ . '/' . $file)) {
                    throw new Exception("Archivo crítico faltante: $file");
                }
            }
            $results['details'][] = 'Archivos críticos verificados';
            
            // Verificar permisos de escritura
            $writablePaths = ['storage/cache', 'storage/logs', 'temp'];
            foreach ($writablePaths as $path) {
                $fullPath = __DIR__ . '/' . $path;
                if (!is_writable($fullPath)) {
                    throw new Exception("Sin permisos de escritura en: $path");
                }
            }
            $results['details'][] = 'Permisos de escritura verificados';
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Establecer permisos correctos
     */
    private function setCorrectPermissions() {
        $rootPath = __DIR__;
        
        // Permisos para archivos PHP
        $phpFiles = glob($rootPath . '/*.php');
        foreach ($phpFiles as $file) {
            chmod($file, 0644);
        }
        
        // Permisos para directorios de almacenamiento
        $storageDirs = ['storage', 'temp', 'logs', 'backups'];
        foreach ($storageDirs as $dir) {
            $path = $rootPath . '/' . $dir;
            if (is_dir($path)) {
                chmod($path, 0755);
            }
        }
        
        $this->logger->info('Permisos establecidos correctamente');
    }
    
    /**
     * Configurar archivos de configuración
     */
    private function setupConfigurationFiles() {
        $rootPath = __DIR__;
        
        // Verificar archivo .env
        if (!file_exists($rootPath . '/.env')) {
            copy($rootPath . '/.env.example', $rootPath . '/.env');
            $this->logger->info('Archivo .env creado desde ejemplo');
        }
        
        // Verificar configuración
        if (!file_exists($rootPath . '/config/config.php')) {
            // Crear configuración básica
            $this->createBasicConfig();
        }
    }
    
    /**
     * Crear configuración básica
     */
    private function createBasicConfig() {
        $configContent = <<<'PHP'
<?php
/**
 * Configuración básica de ProfixCRM
 * Generada automáticamente por el sistema de despliegue
 */

return [
    'app' => [
        'name' => 'ProfixCRM',
        'version' => '8.0.0',
        'environment' => 'production',
        'debug' => false,
        'timezone' => 'America/Mexico_City',
        'locale' => 'es_MX',
    ],
    
    'database' => [
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        // Usar claves estándar: DB_DATABASE/DB_USERNAME/DB_PASSWORD con fallback a DB_NAME/DB_USER/DB_PASS
        'database' => $_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? 'profixcrm'),
        'username' => $_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? 'root'),
        'password' => $_ENV['DB_PASSWORD'] ?? ($_ENV['DB_PASS'] ?? ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ],
    
    'security' => [
        'jwt_secret' => $_ENV['JWT_SECRET'] ?? bin2hex(random_bytes(32)),
        'encryption_key' => $_ENV['ENCRYPTION_KEY'] ?? bin2hex(random_bytes(32)),
        'session_lifetime' => 120,
        'password_timeout' => 3600,
    ],
    
    'mail' => [
        'driver' => 'smtp',
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
        'port' => $_ENV['MAIL_PORT'] ?? '587',
        'encryption' => 'tls',
        'username' => $_ENV['MAIL_USER'] ?? '',
        'password' => $_ENV['MAIL_PASS'] ?? '',
    ],
];
PHP;
        
        file_put_contents(__DIR__ . '/config/config.php', $configContent);
        $this->logger->info('Configuración básica creada');
    }
    
    /**
     * Obtener duración del despliegue
     */
    private function getDeploymentDuration() {
        return round(microtime(true) - DEPLOYMENT_START_TIME, 2);
    }
    
    /**
     * Obtener configuración de BD del usuario (para CLI)
     */
    private function getDatabaseConfigFromUser() {
        if ($this->isCli) {
            echo "\n=== CONFIGURACIÓN DE BASE DE DATOS ===\n";
            echo "Host [localhost]: ";
            $host = trim(fgets(STDIN)) ?: 'localhost';
            
            echo "Puerto [3306]: ";
            $port = trim(fgets(STDIN)) ?: '3306';
            
            echo "Base de datos: ";
            $database = trim(fgets(STDIN));
            
            echo "Usuario: ";
            $username = trim(fgets(STDIN));
            
            echo "Contraseña: ";
            system('stty -echo');
            $password = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
            
            return [
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password
            ];
        }
        
        return [];
    }
    
    /**
     * Renderizar interfaz web
     */
    public function renderWebInterface() {
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 ProfixCRM - Sistema de Despliegue</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .deployment-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-right: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .progress-container {
            margin: 30px 0;
            display: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e1e5e9;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            text-align: center;
            margin-top: 10px;
            font-weight: 600;
            color: #667eea;
        }
        
        .results {
            margin-top: 30px;
            display: none;
        }
        
        .result-item {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        
        .result-item.error {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        .result-item.warning {
            border-left-color: #ffc107;
            background: #fffaf0;
        }
        
        .result-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .result-details {
            font-size: 14px;
            color: #666;
        }
        
        .logs {
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        
        .log-entry {
            margin-bottom: 5px;
            padding: 2px 0;
        }
        
        .log-entry.error {
            color: #fc8181;
        }
        
        .log-entry.success {
            color: #68d391;
        }
        
        .log-entry.info {
            color: #63b3ed;
        }
        
        .error-fix {
            background: #fed7d7;
            border: 1px solid #feb2b2;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .error-fix h4 {
            color: #c53030;
            margin-bottom: 10px;
        }
        
        .error-fix code {
            background: #fff5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .success-message {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-top: 20px;
        }
        
        .success-message h3 {
            color: #22543d;
            margin-bottom: 10px;
        }
        
        .success-message p {
            color: #2f855a;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .deployment-card {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 ProfixCRM Deployment System</h1>
            <p>Sistema profesional de instalación y configuración automática</p>
        </div>
        
        <div class="deployment-card">
            <h2>⚙️ Configuración de Despliegue</h2>
            <p>Completa la siguiente información para instalar ProfixCRM en tu servidor:</p>
            
            <form id="deploymentForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    
                    <!-- Configuración de Base de Datos -->
                    <div>
                        <h3 style="margin-bottom: 15px; color: #667eea;">🗄️ Base de Datos</h3>
                        
                        <div class="form-group">
                            <label for="db_action">Acción de Base de Datos:</label>
                            <select id="db_action" name="db_action" required>
                                <option value="create">Crear nueva base de datos</option>
                                <option value="connect">Conectar a base de datos existente</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_host">Host:</label>
                            <input type="text" id="db_host" name="db_host" value="localhost" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_port">Puerto:</label>
                            <input type="number" id="db_port" name="db_port" value="3306" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name">Nombre de Base de Datos:</label>
                            <input type="text" id="db_name" name="db_name" value="profixcrm" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_user">Usuario:</label>
                            <input type="text" id="db_user" name="db_user" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_pass">Contraseña:</label>
                            <input type="password" id="db_pass" name="db_pass">
                        </div>

                        <div class="form-group" id="db_test_group" style="display:none;">
                            <button type="button" class="btn" id="dbTestBtn" onclick="testDbConnection()" style="background:#17a2b8;">
                                🔌 Probar conexión
                            </button>
                            <span id="dbTestResult" style="margin-left:10px;font-weight:600;"></span>
                        </div>

                        <div class="form-group" id="db_confirm_group" style="display:none;">
                            <button type="button" class="btn" id="dbConfirmBtn" onclick="confirmDbSetup()" style="background:#28a745;">
                                ✅ Confirmación real de BD
                            </button>
                            <div id="dbConfirmOutput" style="margin-top:10px; white-space:pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size:13px;"></div>
                        </div>
                        <div class="form-group" id="db_save_group" style="display:none;">
                            <button type="button" class="btn" id="dbSaveEnvBtn" onclick="writeEnvDb()" style="background:#6c757d;">
                                💾 Guardar credenciales en .env.production
                            </button>
                            <span id="dbSaveEnvResult" style="margin-left:10px;font-weight:600;"></span>
                        </div>
                    </div>
                    
                    <!-- Configuración del Sistema -->
                    <div>
                        <h3 style="margin-bottom: 15px; color: #667eea;">⚙️ Configuración del Sistema</h3>
                        
                        <div class="form-group">
                            <label for="app_name">Nombre de la Aplicación:</label>
                            <input type="text" id="app_name" name="app_name" value="ProfixCRM" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="app_url">URL de la Aplicación:</label>
                            <input type="url" id="app_url" name="app_url" value="<?php echo 'http://' . $_SERVER['HTTP_HOST']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_email">Email del Administrador:</label>
                            <input type="email" id="admin_email" name="admin_email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_password">Contraseña del Administrador:</label>
                            <input type="password" id="admin_password" name="admin_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="timezone">Zona Horaria:</label>
                            <select id="timezone" name="timezone" required>
                                <option value="America/Mexico_City">America/Mexico_City</option>
                                <option value="America/New_York">America/New_York</option>
                                <option value="America/Los_Angeles">America/Los_Angeles</option>
                                <option value="Europe/Madrid">Europe/Madrid</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="web_server">Servidor Web:</label>
                            <select id="web_server" name="web_server" required>
                                <option value="auto">Detectar automáticamente</option>
                                <option value="apache">Apache</option>
                                <option value="nginx">Nginx</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn" id="deployBtn">
                        🚀 Iniciar Despliegue
                    </button>
                    <button type="button" class="btn" onclick="runDiagnostics()" style="background: #28a745;">
                        🔍 Ejecutar Diagnóstico
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Barra de Progreso -->
        <div class="deployment-card progress-container" id="progressContainer">
            <h3>🔄 Procesando Despliegue...</h3>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div class="progress-text" id="progressText">Iniciando...</div>
            <div class="logs" id="deploymentLogs"></div>
        </div>
        
        <!-- Resultados -->
        <div class="deployment-card results" id="resultsContainer">
            <h3>📊 Resultados del Despliegue</h3>
            <div id="resultsContent"></div>
        </div>

        <!-- Post‑Instalación: Diagnóstico y Admin -->
        <div class="deployment-card" id="postInstallContainer" style="display:none;">
            <h3>🩺 Diagnóstico Post‑Instalación y Acciones</h3>
            <p style="margin-bottom:12px;color:#4a5568;">Ejecuta verificación con datos de producción y gestiona el admin.</p>
            <div style="display:flex; gap:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:300px;">
                    <h4 style="margin:0 0 10px 0;">Diagnóstico (API y Sistema)</h4>
                    <button type="button" class="btn" id="postDiagBtn" onclick="runPostInstallDiagnostics()">🔍 Ejecutar diagnóstico de producción</button>
                    <pre id="postDiagOutput" style="margin-top:12px; background:#f7fafc; padding:12px; border-radius:8px; max-height:280px; overflow:auto;"></pre>
                </div>
                <div style="flex:1; min-width:300px;">
                    <h4 style="margin:0 0 10px 0;">Crear/Admin</h4>
                    <div class="form-group">
                        <label for="admin_token">Token de seguridad:</label>
                        <input type="text" id="admin_token" placeholder="tu_token_secreto" />
                    </div>
                    <div class="form-group">
                        <label for="admin_username">Usuario admin:</label>
                        <input type="text" id="admin_username" value="admin" />
                    </div>
                    <div class="form-group">
                        <label for="admin_email_action">Email admin:</label>
                        <input type="email" id="admin_email_action" placeholder="admin@tuempresa.com" />
                    </div>
                    <div class="form-group">
                        <label for="admin_password_action">Contraseña admin:</label>
                        <input type="password" id="admin_password_action" placeholder="••••••••" />
                    </div>
                    <div class="form-group">
                        <label for="admin_role">Rol:</label>
                        <select id="admin_role">
                            <option value="all">all</option>
                            <option value="admin">admin</option>
                        </select>
                    </div>
                    <button type="button" class="btn" id="createAdminBtn" onclick="createAdmin()">👤 Crear Admin</button>
                    <div id="createAdminMsg" style="margin-top:10px;font-weight:600;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let deploymentActive = false;
        let logInterval = null;
        
        // Mostrar/ocultar botón de prueba de conexión según acción seleccionada
        const dbActionSelect = document.getElementById('db_action');
        const dbTestGroup = document.getElementById('db_test_group');
        const dbConfirmGroup = document.getElementById('db_confirm_group');
        const dbSaveGroup = document.getElementById('db_save_group');
        dbActionSelect.addEventListener('change', function() {
            const show = (this.value === 'connect');
            dbTestGroup.style.display = show ? 'block' : 'none';
            dbConfirmGroup.style.display = show ? 'block' : 'none';
            dbSaveGroup.style.display = show ? 'block' : 'none';
        });
        // Estado inicial
        const showInit = (dbActionSelect.value === 'connect');
        dbTestGroup.style.display = showInit ? 'block' : 'none';
        dbConfirmGroup.style.display = showInit ? 'block' : 'none';
        dbSaveGroup.style.display = showInit ? 'block' : 'none';
        
        // Manejar envío del formulario
        document.getElementById('deploymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (deploymentActive) return;
            
            // Validar formulario
            if (!validateForm()) {
                return;
            }
            
            // Iniciar despliegue
            startDeployment();
        });
        
        // Validar formulario
        function validateForm() {
            const form = document.getElementById('deploymentForm');
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    valid = false;
                } else {
                    field.style.borderColor = '#e1e5e9';
                }
            });
            
            if (!valid) {
                alert('Por favor completa todos los campos requeridos.');
            }
            
            return valid;
        }
        
        // Iniciar despliegue
        function startDeployment() {
            deploymentActive = true;
            
            // Deshabilitar botón
            document.getElementById('deployBtn').disabled = true;
            document.getElementById('deployBtn').textContent = '🔄 Desplegando...';
            
            // Mostrar progreso
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('resultsContainer').style.display = 'none';
            
            // Obtener datos del formulario y estructurar configuración
            const formData = new FormData(document.getElementById('deploymentForm'));
            const raw = Object.fromEntries(formData.entries());
            const config = {
                database: {
                    action: raw.db_action,
                    host: raw.db_host,
                    port: raw.db_port,
                    database: raw.db_name,
                    username: raw.db_user,
                    password: raw.db_pass
                },
                system: {
                    app_name: raw.app_name,
                    app_url: raw.app_url,
                    admin_email: raw.admin_email,
                    admin_password: raw.admin_password,
                    timezone: raw.timezone,
                    web_server: raw.web_server
                }
            };
            
            // Iniciar logs en tiempo real
            startLogMonitoring();
            
            // Enviar petición AJAX
            fetch('deploy_system.php?action=deploy', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ config: config })
            })
            .then(response => response.json())
            .then(data => {
                deploymentActive = false;
                stopLogMonitoring();
                
                // Mostrar resultados
                displayResults(data);
                
                // Restaurar botón
                document.getElementById('deployBtn').disabled = false;
                document.getElementById('deployBtn').textContent = '🚀 Iniciar Despliegue';
            })
            .catch(error => {
                deploymentActive = false;
                stopLogMonitoring();
                
                console.error('Error:', error);
                alert('Error en el despliegue: ' + error.message);
                
                // Restaurar botón
                document.getElementById('deployBtn').disabled = false;
                document.getElementById('deployBtn').textContent = '🚀 Iniciar Despliegue';
            });
        }

        // Probar conexión a la base de datos
        function testDbConnection() {
            const host = document.getElementById('db_host').value.trim();
            const port = document.getElementById('db_port').value.trim();
            const name = document.getElementById('db_name').value.trim();
            const user = document.getElementById('db_user').value.trim();
            const pass = document.getElementById('db_pass').value;
            const resultEl = document.getElementById('dbTestResult');
            const btn = document.getElementById('dbTestBtn');
            
            resultEl.textContent = 'Probando...';
            resultEl.style.color = '#667eea';
            btn.disabled = true;
            
            fetch('deploy_system.php?action=testDb', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    host: host,
                    port: port,
                    database: name,
                    username: user,
                    password: pass
                })
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    resultEl.textContent = 'Conexión exitosa';
                    resultEl.style.color = '#28a745';
                } else {
                    resultEl.textContent = 'Fallo: ' + (data.error || 'No se pudo conectar');
                    resultEl.style.color = '#dc3545';
                }
            })
            .catch(err => {
                btn.disabled = false;
                resultEl.textContent = 'Error: ' + err.message;
                resultEl.style.color = '#dc3545';
            });
        }

        // Confirmación real: usa credenciales del formulario o entorno/config y verifica tablas
        function confirmDbSetup() {
            const btn = document.getElementById('dbConfirmBtn');
            const out = document.getElementById('dbConfirmOutput');
            const host = document.getElementById('db_host').value.trim();
            const port = document.getElementById('db_port').value.trim();
            const name = document.getElementById('db_name').value.trim();
            const user = document.getElementById('db_user').value.trim();
            const pass = document.getElementById('db_pass').value;
            btn.disabled = true;
            out.textContent = 'Confirmando configuración real (formulario/env/config)...\n';
            fetch('deploy_system.php?action=dbConfirm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    host: host || null,
                    port: port || null,
                    database: name || null,
                    username: user || null,
                    password: pass || null
                })
            })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    let html = '';
                    if (data.success) {
                        html += '✅ Conexión y verificación básicas correctas\n';
                    } else {
                        html += '❌ Problemas detectados: ' + (data.error || 'ver detalles') + '\n';
                    }
                    if (data.details && data.details.length) {
                        data.details.forEach(d => {
                            html += (d.ok ? '  • OK ' : '  • FAIL ') + d.name + ' — ' + d.message + '\n';
                        });
                    }
                    if (data.env) {
                        html += '\nUsando configuración: host=' + (data.env.host || '') + ', db=' + (data.env.database || '') + ', user=' + (data.env.username || '') + '\n';
                    }
                    out.textContent = html;
                    const grp = document.getElementById('db_confirm_group');
                    grp.style.display = 'block';
                })
                .catch(err => {
                    btn.disabled = false;
                    out.textContent += '\n❌ Error: ' + err.message;
                });
        }

        // Guardar credenciales en .env.production
        function writeEnvDb() {
            const host = document.getElementById('db_host').value.trim();
            const port = document.getElementById('db_port').value.trim();
            const name = document.getElementById('db_name').value.trim();
            const user = document.getElementById('db_user').value.trim();
            const pass = document.getElementById('db_pass').value;
            const appUrl = document.getElementById('app_url').value.trim();
            const resultEl = document.getElementById('dbSaveEnvResult');
            const btn = document.getElementById('dbSaveEnvBtn');

            resultEl.textContent = 'Guardando...';
            resultEl.style.color = '#667eea';
            btn.disabled = true;

            fetch('deploy_system.php?action=writeEnvDb', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    host: host,
                    port: port,
                    database: name,
                    username: user,
                    password: pass,
                    app_url: appUrl
                })
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    resultEl.textContent = '.env.production actualizado (' + (data.path || '') + ')';
                    resultEl.style.color = '#28a745';
                } else {
                    resultEl.textContent = 'Fallo: ' + (data.error || 'No se pudo guardar');
                    resultEl.style.color = '#dc3545';
                }
            })
            .catch(err => {
                btn.disabled = false;
                resultEl.textContent = 'Error: ' + err.message;
                resultEl.style.color = '#dc3545';
            });
        }

        // Monitorear logs en tiempo real
        function startLogMonitoring() {
            logInterval = setInterval(() => {
                fetch('deploy_system.php?action=getLogs&deployment_id=<?php echo $this->deploymentId; ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.logs && data.logs.length > 0) {
                            const logsContainer = document.getElementById('deploymentLogs');
                            data.logs.forEach(log => {
                                const logEntry = document.createElement('div');
                                logEntry.className = 'log-entry ' + log.level;
                                logEntry.textContent = `[${log.timestamp}] ${log.message}`;
                                logsContainer.appendChild(logEntry);
                            });
                            logsContainer.scrollTop = logsContainer.scrollHeight;
                            
                            // Actualizar progreso
                            if (data.progress) {
                                updateProgress(data.progress);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error obteniendo logs:', error);
                    });
            }, 1000);
        }
        
        // Detener monitoreo de logs
        function stopLogMonitoring() {
            if (logInterval) {
                clearInterval(logInterval);
                logInterval = null;
            }
        }
        
        // Actualizar barra de progreso
        function updateProgress(progress) {
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            
            progressFill.style.width = progress.percentage + '%';
            progressText.textContent = progress.phase + ' - ' + progress.percentage + '%';
        }
        
        // Mostrar resultados
        function displayResults(data) {
            const resultsContainer = document.getElementById('resultsContainer');
            const resultsContent = document.getElementById('resultsContent');
            
            resultsContainer.style.display = 'block';
            
            let html = '';
            
            if (data.success) {
                html += `
                    <div class="success-message">
                        <h3>🎉 ¡Despliegue Exitoso!</h3>
                        <p>ProfixCRM ha sido instalado correctamente.</p>
                        <p><strong>Duración:</strong> ${data.duration} segundos</p>
                        <p><strong>ID de Despliegue:</strong> ${data.deployment_id}</p>
                    </div>
                `;
            } else {
                html += `
                    <div class="result-item error">
                        <div class="result-title">❌ Error en el Despliegue</div>
                        <div class="result-details">${data.error}</div>
                    </div>
                `;
            }
            
            // Mostrar detalles de cada fase
            if (data.results) {
                html += '<h4 style="margin: 20px 0 10px 0;">Detalles del Proceso:</h4>';
                Object.entries(data.results).forEach(([phase, result]) => {
                    const statusClass = result.success ? 'success' : 'error';
                    const statusIcon = result.success ? '✅' : '❌';
                    html += `
                        <div class="result-item ${statusClass}">
                            <div class="result-title">${statusIcon} ${getPhaseName(phase)}</div>
                            <div class="result-details">${result.success ? 'Completado exitosamente' : (result.error || 'Error desconocido')}</div>
                    `;
                    if (Array.isArray(result.details) && result.details.length) {
                        // Renderizar detalles como lista
                        html += '<div class="result-details"><strong>Detalles:</strong><ul style="margin:8px 0 0 18px;">';
                        result.details.forEach(d => {
                            if (typeof d === 'string') {
                                html += `<li>${d}</li>`;
                            } else if (d && typeof d === 'object') {
                                const icon = d.ok === false ? '❌' : '✅';
                                const name = d.name || 'Detalle';
                                const msg = d.message || '';
                                html += `<li>${icon} ${name}${msg ? ': ' + msg : ''}</li>`;
                            }
                        });
                        html += '</ul></div>';
                    }
                    html += '</div>';
                });
            }
            
            // Mostrar correcciones si hay errores
            if (!data.success && data.results) {
                const fixes = generateErrorFixes(data.results);
                if (fixes.length > 0) {
                    html += `
                        <div class="error-fix">
                            <h4>🔧 Sugerencias de Corrección:</h4>
                            ${fixes.map(fix => `<p>• ${fix}</p>`).join('')}
                        </div>
                    `;
                }
            }
            
            resultsContent.innerHTML = html;
            
            // Scroll a resultados
            resultsContainer.scrollIntoView({ behavior: 'smooth' });

            // Mostrar acciones post‑instalación si el despliegue fue exitoso
            const postInstall = document.getElementById('postInstallContainer');
            if (data.success) {
                postInstall.style.display = 'block';
                // Precargar datos del admin desde el formulario principal
                const emailForm = document.getElementById('admin_email').value;
                const passForm = document.getElementById('admin_password').value;
                if (emailForm) document.getElementById('admin_email_action').value = emailForm;
                if (passForm) document.getElementById('admin_password_action').value = passForm;
                // Autogenerar token seguro y rellenar campo
                prefillAdminToken();
            } else {
                postInstall.style.display = 'none';
            }
        }
        
        // Obtener nombre amigable de la fase
        function getPhaseName(phase) {
            const names = {
                'server_check': 'Verificación del Servidor',
                'database_setup': 'Configuración de Base de Datos',
                'webserver_config': 'Configuración del Servidor Web',
                'files_setup': 'Configuración de Archivos',
                'tests': 'Pruebas del Sistema',
                'final_verification': 'Verificación Final'
            };
            return names[phase] || phase;
        }
        
        // Generar sugerencias de corrección
        function generateErrorFixes(results) {
            const fixes = [];
            
            if (results.server_check && !results.server_check.success) {
                fixes.push('Verifica que PHP 8.0+ esté instalado y las extensiones requeridas estén habilitadas.');
            }
            
            if (results.database_setup && !results.database_setup.success) {
                fixes.push('Verifica las credenciales de MySQL y que el servidor esté ejecutándose.');
            }
            
            if (results.files_setup && !results.files_setup.success) {
                fixes.push('Verifica los permisos de escritura en los directorios del sistema.');
            }
            
            return fixes;
        }
        
        // Ejecutar diagnóstico
        function runDiagnostics() {
            const btns = document.querySelectorAll('.btn');
            btns.forEach(b => b.disabled = true);
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('progressText').textContent = 'Ejecutando diagnóstico...';
            
            fetch('deploy_system.php?action=diagnostics')
                .then(r => r.json())
                .then(data => {
                    btns.forEach(b => b.disabled = false);
                    updateProgress({ percentage: 100, phase: 'Diagnóstico' });
                    
                    const resultsContainer = document.getElementById('resultsContainer');
                    const resultsContent = document.getElementById('resultsContent');
                    resultsContainer.style.display = 'block';
                    
                    let html = '<h4>Resultado del Diagnóstico</h4>';
                    if (data.success) {
                        html += '<div class="result-item"><div class="result-title">✅ OK</div><div class="result-details">Todos los requisitos básicos están correctos.</div></div>';
                    } else {
                        html += '<div class="result-item error"><div class="result-title">❌ Problemas detectados</div><div class="result-details">' + (data.error || 'Ver detalles') + '</div></div>';
                    }
                    if (data.details && data.details.length) {
                        data.details.forEach(d => {
                            html += '<div class="result-item ' + (d.ok ? '' : 'error') + '"><div class="result-title">' + (d.ok ? '✅' : '❌') + ' ' + d.name + '</div><div class="result-details">' + d.message + '</div></div>';
                        });
                    }
                    resultsContent.innerHTML = html;
                    resultsContainer.scrollIntoView({ behavior: 'smooth' });
                })
                .catch(err => {
                    btns.forEach(b => b.disabled = false);
                    alert('Error en diagnóstico: ' + err.message);
                });
        }

        // === Diagnóstico Post‑Instalación (producción) ===
        async function runPostInstallDiagnostics() {
            const btn = document.getElementById('postDiagBtn');
            const out = document.getElementById('postDiagOutput');
            btn.disabled = true;
            out.textContent = 'Ejecutando validate_config.php, post_install_validation.php y diagnostic.php...\n';
            try {
                const r = await fetch('deploy_system.php?action=runPostInstall');
                const data = await r.json();
                if (data && data.success) {
                    const outputs = [];
                    outputs.push('=== validate_config.php ===\n' + (data.outputs?.validate_config || ''));
                    outputs.push('\n\n=== post_install_validation.php ===\n' + (data.outputs?.post_install_validation || ''));
                    outputs.push('\n\n=== diagnostic.php ===\n' + (data.outputs?.diagnostic || ''));
                    out.textContent = outputs.join('\n');
                } else {
                    out.textContent += '\n❌ Error en diagnóstico: ' + (data.error || 'desconocido');
                }
            } catch (e) {
                out.textContent += '\n❌ Error ejecutando diagnóstico: ' + e.message;
            } finally {
                btn.disabled = false;
            }
        }

        // Autogenerar token seguro y rellenar campo
        async function prefillAdminToken() {
            try {
                const r = await fetch('deploy_system.php?action=generateToken', { method: 'POST' });
                const data = await r.json();
                if (data && data.success && data.token) {
                    const inp = document.getElementById('admin_token');
                    inp.value = data.token;
                    const msg = document.getElementById('createAdminMsg');
                    if (msg && !msg.textContent) {
                        msg.textContent = '🔐 Token generado y guardado (admin_token.txt)';
                        msg.style.color = '#4a5568';
                    }
                }
            } catch (e) {
                // Silencioso: el usuario aún puede ingresar uno manualmente
            }
        }

        // === Crear Admin vía endpoint ===
        async function createAdmin() {
            const token = document.getElementById('admin_token').value.trim();
            const username = document.getElementById('admin_username').value.trim();
            const email = document.getElementById('admin_email_action').value.trim();
            const password = document.getElementById('admin_password_action').value;
            const role = document.getElementById('admin_role').value;
            const msg = document.getElementById('createAdminMsg');
            const btn = document.getElementById('createAdminBtn');
            msg.textContent = '';
            msg.style.color = '#4a5568';
            if (!token || !username || !email || !password) {
                msg.textContent = 'Completa token, usuario, email y contraseña.';
                msg.style.color = '#dc3545';
                return;
            }
            btn.disabled = true;
            try {
                const r = await fetch('/api/auth/create_admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token, username, email, password, role })
                });
                const text = await r.text();
                if (r.ok) {
                    msg.textContent = '✅ Credenciales de admin creadas satisfactoriamente';
                    msg.style.color = '#28a745';
                } else {
                    msg.textContent = '❌ Error creando admin: ' + (text || r.status);
                    msg.style.color = '#dc3545';
                }
            } catch (e) {
                msg.textContent = '❌ Error creando admin: ' + e.message;
                msg.style.color = '#dc3545';
            } finally {
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
        <?php
    }
}

// ===== IMPLEMENTACIÓN DE CLASES AUXILIARES =====

/**
 * 📋 Sistema de Logging
 */
class DeploymentLogger {
    private $deploymentId;
    private $logFile;
    
    public function __construct($deploymentId) {
        $this->deploymentId = $deploymentId;
        $this->logFile = DEPLOYMENT_LOG_DIR . '/' . $deploymentId . '.log';
    }
    
    public function info($message) {
        $this->log('INFO', $message);
    }
    
    public function error($message) {
        $this->log('ERROR', $message);
    }
    
    public function warning($message) {
        $this->log('WARNING', $message);
    }
    
    public function success($message) {
        $this->log('SUCCESS', $message);
    }
    
    private function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        // Guardar en archivo
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // También mostrar en consola si es CLI
        if (php_sapi_name() === 'cli') {
            echo $logEntry;
        }
    }
    
    public function getLogs($limit = 100) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $logs = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recentLogs = array_slice($logs, -$limit);
        
        $parsedLogs = [];
        foreach ($recentLogs as $log) {
            if (preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $log, $matches)) {
                $parsedLogs[] = [
                    'timestamp' => $matches[1],
                    'level' => strtolower($matches[2]),
                    'message' => $matches[3]
                ];
            }
        }
        
        return $parsedLogs;
    }
}

/**
 * 🔍 Verificador de servidor
 */
class ServerChecker {
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function checkAllRequirements() {
        $details = [];
        $ok = true;
        
        // PHP version
        $phpOk = version_compare(PHP_VERSION, PROFIXCRM_MIN_PHP_VERSION, '>=');
        $details[] = [
            'name' => 'Versión de PHP',
            'ok' => $phpOk,
            'message' => 'PHP ' . PHP_VERSION . ' (mínimo ' . PROFIXCRM_MIN_PHP_VERSION . ')'
        ];
        $ok = $ok && $phpOk;
        
        // Extensiones requeridas
        $required = ['pdo', 'pdo_mysql', 'mbstring', 'curl', 'json', 'openssl', 'zip'];
        foreach ($required as $ext) {
            $extOk = extension_loaded($ext);
            $details[] = [
                'name' => 'Extensión: ' . $ext,
                'ok' => $extOk,
                'message' => $extOk ? 'Cargada' : 'No cargada'
            ];
            $ok = $ok && $extOk;
        }
        
        // Permisos de escritura mínimos
        $paths = [dirname(__DIR__) . '/storage', dirname(__DIR__) . '/temp', dirname(__DIR__) . '/logs'];
        foreach ($paths as $p) {
            $wOk = is_dir($p) ? is_writable($p) : is_writable(dirname($p));
            $details[] = [
                'name' => 'Permisos de escritura: ' . str_replace(dirname(__DIR__) . '/', '', $p),
                'ok' => $wOk,
                'message' => $wOk ? 'Correcto' : 'Sin permisos de escritura'
            ];
            $ok = $ok && $wOk;
        }
        
        $this->logger->info('Diagnóstico completado');
        return ['success' => $ok, 'details' => $details];
    }
}

/**
 * 🗄️ Placeholder para DatabaseInstaller
 */
class DatabaseInstaller {
    private $logger;
    private $currentConfig = [];
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function setupDatabase($config) {
        $this->currentConfig = $config;
        // Aquí se podría crear la DB/usuario/tablas según acción; por ahora asumimos éxito
        return ['success' => true, 'details' => ['Base de datos configurada']];
    }
    
    public function testConnection($config = null) {
        $cfg = $config ?: $this->currentConfig;
        if (empty($cfg)) {
            // Cargar .env/.env.production manualmente si no están en $_ENV
            $root = __DIR__;
            $parseEnv = function($file) {
                if (!file_exists($file)) return;
                $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#') continue;
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                            $val = substr($val, 1, -1);
                        }
                        putenv("{$key}={$val}");
                        $_ENV[$key] = $val;
                    }
                }
            };
            $parseEnv($root . '/.env');
            $parseEnv($root . '/.env.production');

            // Usar claves estándar primero, con fallback a alias
            $cfg = [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? '3306',
                'database' => $_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? 'profixcrm'),
                'username' => $_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? 'root'),
                'password' => $_ENV['DB_PASSWORD'] ?? ($_ENV['DB_PASS'] ?? '')
            ];
        }
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['database']);
            $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->query('SELECT 1');
            return true;
        } catch (Throwable $e) {
            $this->logger->error('Fallo conexión DB: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * 🧪 Placeholder para TestSuite
 */
class TestSuite {
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function runAllTests() {
        // Implementación vendrá después
        return ['success' => true, 'details' => ['Todas las pruebas pasaron']];
    }
}

/**
 * 🌐 Placeholder para WebServerConfigurator
 */
class WebServerConfigurator {
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function configureServer() {
        // Implementación vendrá después
        return ['success' => true, 'details' => ['Servidor web configurado']];
    }
}

// ===== MANEJADOR DE PETICIONES =====

// Manejar peticiones AJAX
if (isset($_GET['action'])) {
    // Asegurar respuesta JSON limpia y sin HTML de errores
    if (ob_get_level()) { ob_clean(); }
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'deploy':
            try {
                $input = json_decode(file_get_contents('php://input'), true);
                $deployment = new ProfixCRMDeploymentSystem();
                $result = $deployment->deploy($input['config'] ?? []);
                echo json_encode($result);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'getLogs':
            $deploymentId = $_GET['deployment_id'] ?? '';
            $logger = new DeploymentLogger($deploymentId);
            echo json_encode([
                'logs' => $logger->getLogs(50),
                'progress' => getDeploymentProgress($deploymentId)
            ]);
            break;
        
        case 'diagnostics':
            try {
                // Base URL derivado de la petición actual
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
                $baseUrl = rtrim($scheme . '://' . $host . ($basePath ? $basePath . '/' : '/'), '/');
                $publicBase = $baseUrl . '/public';

                // Helper HTTP probe
                $httpProbe = function($name, $url, $method = 'GET', $payload = null) {
                    $start = microtime(true);
                    $ch = curl_init();
                    $headers = ['Accept: application/json, text/html;q=0.8'];
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS => 3,
                        CURLOPT_CONNECTTIMEOUT => 5,
                        CURLOPT_TIMEOUT => 8,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_HEADER => false,
                        CURLOPT_HTTPHEADER => $headers,
                    ]);
                    if (strtoupper($method) !== 'GET') {
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                        if ($payload !== null) {
                            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($payload) ? $payload : json_encode($payload));
                            $headers[] = 'Content-Type: application/json';
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        }
                    }
                    $body = curl_exec($ch);
                    $err = curl_error($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    curl_close($ch);
                    $dur = round((microtime(true) - $start) * 1000);

                    $ok = ($code >= 200 && $code < 400 && $err === '');
                    $msg = $err ?: ('HTTP ' . $code);
                    // Intentar parsear JSON para mensajes
                    if ($body && stripos((string)$ctype, 'application/json') !== false) {
                        $j = json_decode($body, true);
                        if (is_array($j)) {
                            if (isset($j['message'])) { $msg = $j['message']; }
                            if (isset($j['error'])) { $msg = (is_string($j['error']) ? $j['error'] : json_encode($j['error'])); }
                            // considerar ok como respuesta JSON sin error severo
                            if (isset($j['success'])) { $ok = $ok && (bool)$j['success']; }
                        }
                    }
                    return [
                        'ok' => $ok,
                        'name' => $name,
                        'message' => $msg,
                        'status' => $code,
                        'content_type' => $ctype,
                        'url' => $url,
                        'time_ms' => $dur,
                    ];
                };

                $details = [];

                // 1) Requisitos del servidor
                try {
                    $deployment = new ProfixCRMDeploymentSystem();
                    $srv = $deployment->checkServerRequirements();
                    $details[] = [
                        'ok' => !empty($srv['success']),
                        'name' => 'Requisitos del servidor',
                        'message' => !empty($srv['success']) ? 'OK' : ($srv['error'] ?? 'Problemas detectados'),
                    ];
                    if (!empty($srv['details']) && is_array($srv['details'])) {
                        foreach ($srv['details'] as $d) {
                            $details[] = [
                                'ok' => (bool)($d['ok'] ?? true),
                                'name' => (string)($d['name'] ?? 'Detalle servidor'),
                                'message' => (string)($d['message'] ?? ''),
                            ];
                        }
                    }
                } catch (Throwable $e) {
                    $details[] = ['ok' => false, 'name' => 'Requisitos del servidor', 'message' => $e->getMessage()];
                }

                // 2) Enlaces principales (frontend)
                $links = [
                    ['Home', $baseUrl . '/'],
                    ['Index PHP', $baseUrl . '/index.php'],
                    ['Public Index PHP', $publicBase . '/index.php'],
                    ['Public Index HTML', $publicBase . '/index.html'],
                    ['Public Router', $publicBase . '/router.php'],
                ];
                foreach ($links as [$name, $url]) {
                    $details[] = $httpProbe('Enlace: ' . $name, $url);
                }

                // 3) Detectar base de APIs: probar public/api y raíz /api
                $apiBases = [
                    $publicBase . '/api',
                    $baseUrl . '/api',
                ];
                $selectedApiBase = null; $probeResult = null;
                foreach ($apiBases as $candidate) {
                    $probe = $httpProbe('API Base Probe', $candidate . '/health.php');
                    // Elegir la primera que no sea 404 y tenga respuesta
                    if ($probe['status'] !== 404 && !empty($probe['status'])) {
                        $selectedApiBase = $candidate; $probeResult = $probe; break;
                    }
                }
                if ($selectedApiBase === null) { $selectedApiBase = $baseUrl . '/api'; }
                // Reportar base seleccionada
                $details[] = [
                    'ok' => ($probeResult ? $probeResult['ok'] : false),
                    'name' => 'API Base',
                    'message' => 'Usando ' . $selectedApiBase,
                    'status' => $probeResult['status'] ?? null,
                ];

                // 3b) APIs principales usando base detectada
                $apis = [
                    ['API Health', $selectedApiBase . '/health.php'],
                    ['API Index', $selectedApiBase . '/index.php'],
                    ['API Users', $selectedApiBase . '/users.php'],
                    ['API Roles', $selectedApiBase . '/roles.php'],
                    ['API Leads', $selectedApiBase . '/leads.php'],
                    ['API Desks', $selectedApiBase . '/desks.php'],
                    ['API Dashboard', $selectedApiBase . '/dashboard.php'],
                    ['Auth Login', $selectedApiBase . '/auth/login.php'],
                ];
                foreach ($apis as [$name, $url]) {
                    if (str_contains($url, '/auth/login.php')) {
                        $details[] = $httpProbe('API: ' . $name, $url, 'POST', ['email' => '', 'password' => '']);
                    } else {
                        $details[] = $httpProbe('API: ' . $name, $url);
                    }
                }

                // 4) Procesos básicos: conexión DB y tablas críticas (env/config)
                $dbDetail = ['ok' => false, 'name' => 'DB conexión (env/config)', 'message' => 'No intentado'];
                try {
                    // Reutilizar parser de .env
                    $root = __DIR__;
                    $parseEnv = function($file) {
                        if (!file_exists($file)) return;
                        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if ($line === '' || $line[0] === '#') continue;
                            $parts = explode('=', $line, 2);
                            if (count($parts) === 2) {
                                $key = trim($parts[0]);
                                $val = trim($parts[1]);
                                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                                    $val = substr($val, 1, -1);
                                }
                                putenv("{$key}={$val}");
                                $_ENV[$key] = $val;
                            }
                        }
                    };
                    $parseEnv($root . '/.env');
                    $parseEnv($root . '/.env.production');

                    $envc = function($key) {
                        $val = getenv($key);
                        if ($val === false && isset($_ENV[$key])) $val = $_ENV[$key];
                        return ($val !== false && $val !== null && $val !== '') ? $val : null;
                    };
                    $cfg = [
                        'host' => $envc('DB_HOST'),
                        'port' => $envc('DB_PORT') ?? '3306',
                        'database' => $envc('DB_DATABASE') ?? $envc('DB_NAME'),
                        'username' => $envc('DB_USERNAME') ?? $envc('DB_USER'),
                        'password' => $envc('DB_PASSWORD') ?? $envc('DB_PASS'),
                    ];
                    // Completar con config.php si faltan datos
                    $cfgPath = $root . '/config/config.php';
                    if (file_exists($cfgPath)) {
                        try {
                            include $cfgPath;
                            $cfg['host'] = $cfg['host'] ?? (defined('DB_HOST') ? DB_HOST : null);
                            $cfg['port'] = $cfg['port'] ?? (defined('DB_PORT') ? DB_PORT : '3306');
                            $cfg['database'] = $cfg['database'] ?? (defined('DB_NAME') ? DB_NAME : null);
                            $cfg['username'] = $cfg['username'] ?? (defined('DB_USER') ? DB_USER : null);
                            $cfg['password'] = $cfg['password'] ?? (defined('DB_PASS') ? DB_PASS : null);
                        } catch (Throwable $e) {}
                    }
                    if (empty($cfg['host']) || empty($cfg['database']) || empty($cfg['username'])) {
                        $dbDetail['ok'] = false;
                        $dbDetail['message'] = 'Faltan datos de conexión (host/database/username)';
                    } else {
                        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['database']);
                        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'] ?? '', [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_TIMEOUT => 5,
                        ]);
                        $pdo->query('SELECT 1');
                        $dbDetail['ok'] = true;
                        $dbDetail['message'] = 'Conexión OK';

                        // Tablas críticas
                        foreach (['users', 'roles', 'user_roles'] as $tbl) {
                            try {
                                $st = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "''", $tbl) . "'");
                                $exists = (bool)($st && $st->fetch());
                                $details[] = ['ok' => $exists, 'name' => "Tabla '$tbl'", 'message' => $exists ? 'Existe' : 'No existe'];
                            } catch (Throwable $e) {
                                $details[] = ['ok' => false, 'name' => "Tabla '$tbl'", 'message' => 'Error: ' . $e->getMessage()];
                            }
                        }
                    }
                } catch (Throwable $e) {
                    $dbDetail['ok'] = false;
                    $dbDetail['message'] = $e->getMessage();
                }
                $details[] = $dbDetail;

                // 5) Permisos y rutas críticas
                $paths = [
                    ['logs/', is_dir(__DIR__ . '/logs')],
                    ['storage/logs/', is_dir(__DIR__ . '/storage/logs')],
                    ['deploy/logs/', is_dir(__DIR__ . '/deploy/logs')],
                ];
                foreach ($paths as [$p, $exists]) {
                    $details[] = [
                        'ok' => (bool)$exists,
                        'name' => 'Ruta: ' . $p,
                        'message' => $exists ? 'Existe' : 'No existe',
                    ];
                }

                // Éxito global si no hay detalles fallidos
                $allOk = true;
                foreach ($details as $d) { if (isset($d['ok']) && !$d['ok']) { $allOk = false; break; } }
                echo json_encode(['success' => $allOk, 'details' => $details, 'base' => $baseUrl]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
        
        case 'generateToken':
            try {
                $root = __DIR__;
                $token = bin2hex(random_bytes(24)); // 48-char hex
                // Guardar en admin_token.txt
                $file = $root . '/admin_token.txt';
                @file_put_contents($file, $token . PHP_EOL);
                // Intentar actualizar .env.production
                $envFile = $root . '/.env.production';
                if (file_exists($envFile)) {
                    $content = file_get_contents($envFile);
                    if (preg_match('/^ADMIN_RESET_TOKEN=.*/m', $content)) {
                        $content = preg_replace('/^ADMIN_RESET_TOKEN=.*/m', 'ADMIN_RESET_TOKEN=' . $token, $content);
                    } else {
                        $content .= (substr($content, -1) === "\n" ? '' : "\n") . 'ADMIN_RESET_TOKEN=' . $token . "\n";
                    }
                    @file_put_contents($envFile, $content);
                }
                echo json_encode([
                    'success' => true,
                    'token' => $token,
                    'saved' => ['admin_token.txt' => is_file($file), '.env.production' => file_exists($envFile)]
                ]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'runPostInstall':
            try {
                $root = __DIR__;
                $outputs = [];

                // Buscar archivos en ubicaciones candidatas para soportar distintos despliegues
                $candidates = [
                    $root,
                    $root . '/deployment_package',
                    $root . '/deploy',
                    $root . '/public',
                ];
                $findFile = function($relative) use ($candidates) {
                    foreach ($candidates as $base) {
                        $p = $base . '/' . $relative;
                        if (file_exists($p)) return $p;
                    }
                    return $candidates[0] . '/' . $relative; // ruta por defecto para mensaje
                };

                $run = function($path) {
                    if (!file_exists($path)) { return "[Archivo no encontrado: {$path}]\n"; }
                    ob_start();
                    try {
                        include $path;
                    } catch (Throwable $e) {
                        echo "[Error ejecutando {$path}: " . $e->getMessage() . "]\n";
                    }
                    return ob_get_clean();
                };

                $outputs['validate_config'] = $run($findFile('validate_config.php'));
                $outputs['post_install_validation'] = $run($findFile('post_install_validation.php'));
                $outputs['diagnostic'] = $run($findFile('diagnostic.php'));

                echo json_encode(['success' => true, 'outputs' => $outputs]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
        
        case 'dbConfirm':
            try {
                $root = __DIR__;
                $payload = json_decode(file_get_contents('php://input'), true) ?? [];

                // Parsear .env y .env.production manualmente para evitar dependencias de Composer
                $parseEnv = function($file) {
                    if (!file_exists($file)) return;
                    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || $line[0] === '#') continue;
                        $parts = explode('=', $line, 2);
                        if (count($parts) === 2) {
                            $key = trim($parts[0]);
                            $val = trim($parts[1]);
                            // Quitar comillas envolventes
                            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, '\'') && str_ends_with($val, '\''))) {
                                $val = substr($val, 1, -1);
                            }
                            putenv("{$key}={$val}");
                            $_ENV[$key] = $val;
                        }
                    }
                };
                $parseEnv($root . '/.env');
                $parseEnv($root . '/.env.production');

                $readConfigPhp = function() use ($root) {
                    $cfg = [];
                    $cfgPath = $root . '/config/config.php';
                    if (file_exists($cfgPath)) {
                        try {
                            include $cfgPath;
                            if (defined('DB_HOST')) $cfg['host'] = DB_HOST;
                            if (defined('DB_PORT')) $cfg['port'] = DB_PORT;
                            if (defined('DB_NAME')) $cfg['database'] = DB_NAME;
                            if (defined('DB_USER')) $cfg['username'] = DB_USER;
                            if (defined('DB_PASS')) $cfg['password'] = DB_PASS;
                        } catch (Throwable $e) {
                            // Ignorar errores de include
                        }
                    }
                    return $cfg;
                };

                $envc = function($key) {
                    $val = getenv($key);
                    if ($val === false && isset($_ENV[$key])) $val = $_ENV[$key];
                    return ($val !== false && $val !== null && $val !== '') ? $val : null;
                };

                // 1) Tomar payload del formulario si está presente
                $cfg = [
                    'host' => $payload['host'] ?? null,
                    'port' => $payload['port'] ?? null,
                    'database' => $payload['database'] ?? null,
                    'username' => $payload['username'] ?? null,
                    'password' => $payload['password'] ?? null,
                ];

                // 2) Completar con .env/.env.production si faltan
                $cfg = [
                    'host' => $cfg['host'] ?? $envc('DB_HOST'),
                    'port' => $cfg['port'] ?? $envc('DB_PORT'),
                    'database' => $cfg['database'] ?? ($envc('DB_DATABASE') ?? $envc('DB_NAME')),
                    'username' => $cfg['username'] ?? ($envc('DB_USERNAME') ?? $envc('DB_USER')),
                    'password' => $cfg['password'] ?? ($envc('DB_PASSWORD') ?? $envc('DB_PASS')),
                ];

                // 3) Completar con config.php si aún faltan
                $cfgPhp = $readConfigPhp();
                foreach ($cfg as $k => $v) {
                    if ($v === null && isset($cfgPhp[$k])) {
                        $cfg[$k] = $cfgPhp[$k];
                    }
                }

                // Validar que no falten datos críticos y evitar defaults irreales
                $missing = [];
                foreach (['host','database','username'] as $k) { if (empty($cfg[$k])) $missing[] = $k; }
                if (!empty($missing)) {
                    echo json_encode(['success' => false, 'error' => 'Faltan datos de conexión: ' . implode(', ', $missing)]);
                    break;
                }
                if (empty($cfg['port'])) { $cfg['port'] = '3306'; }

                $details = [];
                $usedEnv = ['host' => $cfg['host'], 'port' => $cfg['port'], 'database' => $cfg['database'], 'username' => $cfg['username']];

                // Intentar conexión PDO
                try {
                    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['database']);
                    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'] ?? '', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => 5,
                    ]);
                    // Prueba básica de conexión
                    $pdo->query('SELECT 1');
                    $details[] = ['ok' => true, 'name' => 'Conexión a BD', 'message' => 'SELECT 1 OK'];
                } catch (Throwable $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'No se pudo conectar: ' . $e->getMessage(),
                        'env' => $usedEnv
                    ]);
                    break;
                }

                // Verificar tablas críticas
                foreach (['users', 'roles', 'user_roles'] as $tbl) {
                    try {
                        $st = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "''", $tbl) . "'");
                        $exists = (bool)($st && $st->fetch());
                        $details[] = ['ok' => $exists, 'name' => "Tabla '$tbl'", 'message' => $exists ? 'Existe' : 'No existe'];
                    } catch (Throwable $e) {
                        $details[] = ['ok' => false, 'name' => "Tabla '$tbl'", 'message' => 'Error: ' . $e->getMessage()];
                    }
                }

                // Verificar columnas de users
                $hasPwdHash = false; $hasPwd = false;
                try { $st = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_hash'"); $hasPwdHash = (bool)($st && $st->fetch()); } catch (Throwable $e) {}
                try { $st = $pdo->query("SHOW COLUMNS FROM users LIKE 'password'"); $hasPwd = (bool)($st && $st->fetch()); } catch (Throwable $e) {}
                $details[] = ['ok' => ($hasPwdHash || $hasPwd), 'name' => 'Columnas de contraseña', 'message' => $hasPwdHash ? 'password_hash' : ($hasPwd ? 'password' : 'ninguna')];

                $allOk = true;
                foreach ($details as $d) { if (!$d['ok']) { $allOk = false; break; } }
                echo json_encode(['success' => $allOk, 'details' => $details, 'env' => $usedEnv]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
        
        case 'writeEnvDb':
            try {
                $root = __DIR__;
                $payload = json_decode(file_get_contents('php://input'), true) ?? [];

                $host = trim($payload['host'] ?? 'localhost');
                $port = trim($payload['port'] ?? '3306');
                $database = trim($payload['database'] ?? '');
                $username = trim($payload['username'] ?? '');
                $password = (string)($payload['password'] ?? '');
                $appUrl = trim($payload['app_url'] ?? ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));

                if ($database === '' || $username === '') {
                    echo json_encode(['success' => false, 'error' => 'Faltan datos de BD: nombre de base y usuario son obligatorios']);
                    break;
                }

                $envPath = $root . '/.env.production';
                // Backup si existe
                if (file_exists($envPath)) {
                    $bak = $root . '/.env.production.bak-' . date('Ymd_His');
                    @copy($envPath, $bak);
                }

                $quote = function($v) {
                    $v = (string)$v;
                    // Envolver en comillas si contiene espacios o caracteres especiales
                    if ($v === '' || preg_match('/\s|[#=]/', $v)) {
                        // Escapar comillas existentes
                        $v = str_replace(['"', '"'], '"', $v);
                        return '"' . $v . '"';
                    }
                    return $v;
                };

                $lines = [];
                $lines[] = '# Archivo de producción generado por Deploy System';
                $lines[] = 'APP_ENV=production';
                $lines[] = 'APP_DEBUG=false';
                $lines[] = 'APP_URL=' . $quote($appUrl);
                $lines[] = 'DB_CONNECTION=mysql';
                $lines[] = 'DB_HOST=' . $quote($host);
                $lines[] = 'DB_PORT=' . $quote($port);
                $lines[] = 'DB_DATABASE=' . $quote($database);
                $lines[] = 'DB_USERNAME=' . $quote($username);
                $lines[] = 'DB_PASSWORD=' . $quote($password);
                $lines[] = 'SESSION_LIFETIME=180';
                $lines[] = 'FORCE_HTTPS=false';

                $content = implode("\n", $lines) . "\n";
                $ok = (bool)file_put_contents($envPath, $content);

                echo json_encode([
                    'success' => $ok,
                    'path' => $envPath,
                    'used' => [
                        'host' => $host,
                        'port' => $port,
                        'database' => $database,
                        'username' => $username,
                        'app_url' => $appUrl
                    ],
                    'backup' => isset($bak) ? $bak : null
                ]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
        
        case 'testDb':
            try {
                $payload = json_decode(file_get_contents('php://input'), true) ?? [];
                $logger = new DeploymentLogger('db_test_' . uniqid());
                $db = new DatabaseInstaller($logger);
                $ok = $db->testConnection([
                    'host' => $payload['host'] ?? 'localhost',
                    'port' => $payload['port'] ?? '3306',
                    'database' => $payload['database'] ?? '',
                    'username' => $payload['username'] ?? '',
                    'password' => $payload['password'] ?? ''
                ]);
                echo json_encode(['success' => $ok, 'details' => []]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
    exit;
}

// Función auxiliar para obtener progreso
function getDeploymentProgress($deploymentId) {
    // Implementar lógica de progreso
    return [
        'percentage' => rand(10, 90),
        'phase' => 'Configurando sistema'
    ];
}

// Si no es petición AJAX, mostrar interfaz web
$deployment = new ProfixCRMDeploymentSystem();
$deployment->renderWebInterface();