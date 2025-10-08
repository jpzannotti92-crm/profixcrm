<?php
/**
 * Validador de Configuración Específico para GoDaddy
 * iaTrade CRM - Sistema de Gestión de Leads Forex/CFD
 * 
 * Este script valida configuraciones específicas para el entorno GoDaddy
 * y proporciona diagnósticos detallados para resolver errores HTTP 500.
 */

// Configurar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Cargar autoloader si existe
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

// Cargar correcciones específicas de GoDaddy
if (file_exists('deploy/godaddy_fix.php')) {
    require_once 'deploy/godaddy_fix.php';
}

/**
 * Detectar si estamos en entorno GoDaddy
 */
function isGoDaddyEnvironment() {
    $indicators = [
        'SERVER_SOFTWARE' => ['GoDaddy', 'Apache'],
        'DOCUMENT_ROOT' => ['/home/', '/var/www/'],
        'HTTP_HOST' => ['.godaddy.com', '.secureserver.net']
    ];
    
    $score = 0;
    foreach ($indicators as $var => $patterns) {
        if (isset($_SERVER[$var])) {
            foreach ($patterns as $pattern) {
                if (stripos($_SERVER[$var], $pattern) !== false) {
                    $score++;
                    break;
                }
            }
        }
    }
    
    return $score >= 1;
}

/**
 * Verificar configuración de base de datos para GoDaddy
 */
function validateDatabaseConfig() {
    $results = [
        'config_loaded' => false,
        'env_loaded' => false,
        'connection_test' => false,
        'host_valid' => false,
        'credentials_valid' => false,
        'database_exists' => false,
        'tables_exist' => false,
        'errors' => [],
        'recommendations' => []
    ];
    
    try {
        // Verificar archivo .env
        if (file_exists('.env')) {
            $results['env_loaded'] = true;
            $envContent = file_get_contents('.env');
            
            // Verificar variables críticas
            $requiredVars = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
            foreach ($requiredVars as $var) {
                if (strpos($envContent, $var) === false) {
                    $results['errors'][] = "Variable de entorno faltante: $var";
                }
            }
        } else {
            $results['errors'][] = "Archivo .env no encontrado";
        }
        
        // Cargar configuración de base de datos
        if (file_exists('config/database.php')) {
            $dbConfig = include 'config/database.php';
            $results['config_loaded'] = true;
            
            $connection = $dbConfig['connections']['mysql'];
            
            // Validar host para GoDaddy
            $host = $connection['host'];
            if (in_array($host, ['localhost', '127.0.0.1'])) {
                $results['recommendations'][] = "En GoDaddy, el host de BD suele ser diferente a 'localhost'. Verifica en tu panel de control.";
            } else {
                $results['host_valid'] = true;
            }
            
            // Intentar conexión
            try {
                $dsn = "mysql:host={$connection['host']};port={$connection['port']};charset={$connection['charset']}";
                $pdo = new PDO($dsn, $connection['username'], $connection['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 10
                ]);
                
                $results['credentials_valid'] = true;
                
                // Verificar si la base de datos existe
                $stmt = $pdo->query("SHOW DATABASES LIKE '{$connection['database']}'");
                if ($stmt->rowCount() > 0) {
                    $results['database_exists'] = true;
                    
                    // Conectar a la base de datos específica
                    $dsn = "mysql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']};charset={$connection['charset']}";
                    $pdo = new PDO($dsn, $connection['username'], $connection['password'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    
                    $results['connection_test'] = true;
                    
                    // Verificar tablas críticas
                    $criticalTables = ['users', 'leads', 'roles', 'permissions'];
                    $existingTables = [];
                    
                    foreach ($criticalTables as $table) {
                        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                        if ($stmt->rowCount() > 0) {
                            $existingTables[] = $table;
                        }
                    }
                    
                    if (count($existingTables) === count($criticalTables)) {
                        $results['tables_exist'] = true;
                    } else {
                        $missingTables = array_diff($criticalTables, $existingTables);
                        $results['errors'][] = "Tablas faltantes: " . implode(', ', $missingTables);
                    }
                    
                } else {
                    $results['errors'][] = "La base de datos '{$connection['database']}' no existe";
                }
                
            } catch (PDOException $e) {
                $results['errors'][] = "Error de conexión: " . $e->getMessage();
                
                // Diagnósticos específicos para errores comunes en GoDaddy
                if (strpos($e->getMessage(), 'Access denied') !== false) {
                    $results['recommendations'][] = "Verifica las credenciales de BD en tu panel de GoDaddy";
                } elseif (strpos($e->getMessage(), "Can't connect") !== false) {
                    $results['recommendations'][] = "Verifica el host de BD. En GoDaddy suele ser diferente a 'localhost'";
                } elseif (strpos($e->getMessage(), 'timeout') !== false) {
                    $results['recommendations'][] = "Problema de conectividad. Contacta al soporte de GoDaddy";
                }
            }
            
        } else {
            $results['errors'][] = "Archivo config/database.php no encontrado";
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "Error general: " . $e->getMessage();
    }
    
    return $results;
}

/**
 * Verificar configuración de PHP para GoDaddy
 */
function validatePHPConfig() {
    $results = [
        'version' => PHP_VERSION,
        'version_compatible' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'extensions' => [],
        'settings' => [],
        'errors' => [],
        'recommendations' => []
    ];
    
    // Verificar extensiones críticas
    $requiredExtensions = [
        'pdo' => 'PDO para base de datos',
        'pdo_mysql' => 'PDO MySQL driver',
        'mbstring' => 'Manipulación de strings multibyte',
        'openssl' => 'Encriptación SSL',
        'json' => 'Manipulación JSON',
        'curl' => 'Peticiones HTTP',
        'fileinfo' => 'Información de archivos',
        'zip' => 'Compresión de archivos'
    ];
    
    foreach ($requiredExtensions as $ext => $description) {
        $loaded = extension_loaded($ext);
        $results['extensions'][$ext] = [
            'loaded' => $loaded,
            'description' => $description
        ];
        
        if (!$loaded) {
            $results['errors'][] = "Extensión faltante: $ext ($description)";
        }
    }
    
    // Verificar configuraciones importantes
    $importantSettings = [
        'memory_limit' => ['current' => ini_get('memory_limit'), 'recommended' => '256M'],
        'max_execution_time' => ['current' => ini_get('max_execution_time'), 'recommended' => '300'],
        'upload_max_filesize' => ['current' => ini_get('upload_max_filesize'), 'recommended' => '20M'],
        'post_max_size' => ['current' => ini_get('post_max_size'), 'recommended' => '25M'],
        'max_input_vars' => ['current' => ini_get('max_input_vars'), 'recommended' => '3000']
    ];
    
    foreach ($importantSettings as $setting => $config) {
        $results['settings'][$setting] = $config;
        
        // Convertir valores para comparación
        $current = $config['current'];
        $recommended = $config['recommended'];
        
        if ($setting === 'memory_limit' || $setting === 'upload_max_filesize' || $setting === 'post_max_size') {
            $currentBytes = convertToBytes($current);
            $recommendedBytes = convertToBytes($recommended);
            
            if ($currentBytes < $recommendedBytes) {
                $results['recommendations'][] = "Aumentar $setting de $current a $recommended";
            }
        } elseif (is_numeric($current) && is_numeric($recommended)) {
            if ((int)$current < (int)$recommended) {
                $results['recommendations'][] = "Aumentar $setting de $current a $recommended";
            }
        }
    }
    
    return $results;
}

/**
 * Convertir valores de memoria a bytes
 */
function convertToBytes($value) {
    $value = trim($value);
    $last = strtolower($value[strlen($value)-1]);
    $value = (int)$value;
    
    switch($last) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }
    
    return $value;
}

/**
 * Verificar configuración de archivos críticos
 */
function validateCriticalFiles() {
    $results = [
        'files' => [],
        'errors' => [],
        'recommendations' => []
    ];
    
    $criticalFiles = [
        '.htaccess' => 'Configuración de Apache',
        'public/.htaccess' => 'Configuración de directorio público',
        'public/index.php' => 'Punto de entrada principal',
        'public/api/users.php' => 'Endpoint de usuarios',
        'public/api/leads.php' => 'Endpoint de leads',
        'vendor/autoload.php' => 'Autoloader de Composer'
    ];
    
    foreach ($criticalFiles as $file => $description) {
        $exists = file_exists($file);
        $readable = $exists ? is_readable($file) : false;
        
        $results['files'][$file] = [
            'exists' => $exists,
            'readable' => $readable,
            'description' => $description
        ];
        
        if (!$exists) {
            $results['errors'][] = "Archivo faltante: $file ($description)";
        } elseif (!$readable) {
            $results['errors'][] = "Archivo no legible: $file";
        }
    }
    
    return $results;
}

/**
 * Probar endpoints críticos
 */
function testCriticalEndpoints() {
    $results = [
        'endpoints' => [],
        'errors' => [],
        'recommendations' => []
    ];
    
    $baseUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
    $endpoints = [
        '/api/users' => 'Endpoint de usuarios',
        '/api/leads' => 'Endpoint de leads',
        '/api/config.php' => 'Configuración de API'
    ];
    
    foreach ($endpoints as $endpoint => $description) {
        $url = $baseUrl . $endpoint;
        $result = [
            'url' => $url,
            'description' => $description,
            'status' => 'unknown',
            'response_code' => null,
            'error' => null
        ];
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'User-Agent: GoDaddy-Config-Validator/1.0'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $result['response_code'] = $httpCode;
            
            if ($error) {
                $result['error'] = $error;
                $result['status'] = 'error';
                $results['errors'][] = "Error en $endpoint: $error";
            } elseif ($httpCode === 500) {
                $result['status'] = 'error';
                $results['errors'][] = "Error HTTP 500 en $endpoint";
            } elseif ($httpCode >= 200 && $httpCode < 300) {
                $result['status'] = 'ok';
            } elseif ($httpCode === 401 || $httpCode === 403) {
                $result['status'] = 'auth_required';
            } else {
                $result['status'] = 'warning';
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $result['status'] = 'error';
            $results['errors'][] = "Excepción en $endpoint: " . $e->getMessage();
        }
        
        $results['endpoints'][$endpoint] = $result;
    }
    
    return $results;
}

// Ejecutar todas las validaciones
$isGoDaddy = isGoDaddyEnvironment();
$dbValidation = validateDatabaseConfig();
$phpValidation = validatePHPConfig();
$filesValidation = validateCriticalFiles();
$endpointsValidation = testCriticalEndpoints();

// Calcular puntuación general
$totalChecks = 0;
$passedChecks = 0;

// Contar checks de base de datos
$dbChecks = ['config_loaded', 'env_loaded', 'connection_test', 'database_exists', 'tables_exist'];
foreach ($dbChecks as $check) {
    $totalChecks++;
    if ($dbValidation[$check]) $passedChecks++;
}

// Contar checks de PHP
$totalChecks++; // Versión
if ($phpValidation['version_compatible']) $passedChecks++;

foreach ($phpValidation['extensions'] as $ext) {
    $totalChecks++;
    if ($ext['loaded']) $passedChecks++;
}

// Contar checks de archivos
foreach ($filesValidation['files'] as $file) {
    $totalChecks++;
    if ($file['exists'] && $file['readable']) $passedChecks++;
}

// Contar checks de endpoints
foreach ($endpointsValidation['endpoints'] as $endpoint) {
    $totalChecks++;
    if ($endpoint['status'] === 'ok' || $endpoint['status'] === 'auth_required') $passedChecks++;
}

$score = $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100) : 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validador de Configuración GoDaddy - iaTrade CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .status-auth_required { color: #17a2b8; }
        .progress-bar { transition: width 0.3s ease; }
        .score-circle { width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; margin: 0 auto; }
        .score-excellent { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .score-good { background: linear-gradient(135deg, #ffc107, #fd7e14); color: white; }
        .score-poor { background: linear-gradient(135deg, #dc3545, #e83e8c); color: white; }
        .system-info { background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; }
        .recommendation { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .error-item { background: #ffebee; border-left: 4px solid #f44336; padding: 10px; margin: 5px 0; border-radius: 4px; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h1 class="card-title mb-0">
                            <i class="fas fa-cogs me-2"></i>
                            Validador de Configuración GoDaddy
                        </h1>
                        <p class="mb-0 mt-2">Diagnóstico completo para resolver errores HTTP 500 en producción</p>
                    </div>
                    <div class="card-body">
                        
                        <!-- Puntuación General -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="score-circle <?php echo $score >= 80 ? 'score-excellent' : ($score >= 60 ? 'score-good' : 'score-poor'); ?>">
                                    <?php echo $score; ?>%
                                </div>
                                <p class="text-center mt-2 mb-0">Puntuación General</p>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card bg-info text-white">
                                            <div class="card-body text-center">
                                                <h4><?php echo $passedChecks; ?>/<?php echo $totalChecks; ?></h4>
                                                <p class="mb-0">Checks Pasados</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card <?php echo $isGoDaddy ? 'bg-success' : 'bg-warning'; ?> text-white">
                                            <div class="card-body text-center">
                                                <h4><?php echo $isGoDaddy ? 'SÍ' : 'NO'; ?></h4>
                                                <p class="mb-0">GoDaddy Detectado</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card <?php echo $dbValidation['connection_test'] ? 'bg-success' : 'bg-danger'; ?> text-white">
                                            <div class="card-body text-center">
                                                <h4><?php echo $dbValidation['connection_test'] ? 'OK' : 'ERROR'; ?></h4>
                                                <p class="mb-0">Base de Datos</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card <?php echo $phpValidation['version_compatible'] ? 'bg-success' : 'bg-danger'; ?> text-white">
                                            <div class="card-body text-center">
                                                <h4><?php echo $phpValidation['version']; ?></h4>
                                                <p class="mb-0">PHP Version</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Información del Entorno -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-server me-2"></i>Información del Entorno</h5>
                            </div>
                            <div class="card-body">
                                <div class="system-info">
                                    <div><strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido'; ?></div>
                                    <div><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Desconocido'; ?></div>
                                    <div><strong>Host:</strong> <?php echo $_SERVER['HTTP_HOST'] ?? 'Desconocido'; ?></div>
                                    <div><strong>Usuario del Sistema:</strong> <?php echo get_current_user(); ?></div>
                                    <div><strong>Directorio Temporal:</strong> <?php echo sys_get_temp_dir(); ?></div>
                                    <div><strong>Entorno GoDaddy:</strong> <?php echo $isGoDaddy ? 'Detectado' : 'No detectado'; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Validación de Base de Datos -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-database me-2"></i>Configuración de Base de Datos</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Estado de Configuración:</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-<?php echo $dbValidation['env_loaded'] ? 'check status-ok' : 'times status-error'; ?>"></i> Archivo .env cargado</li>
                                            <li><i class="fas fa-<?php echo $dbValidation['config_loaded'] ? 'check status-ok' : 'times status-error'; ?>"></i> Configuración de BD cargada</li>
                                            <li><i class="fas fa-<?php echo $dbValidation['host_valid'] ? 'check status-ok' : 'exclamation-triangle status-warning'; ?>"></i> Host de BD válido</li>
                                            <li><i class="fas fa-<?php echo $dbValidation['credentials_valid'] ? 'check status-ok' : 'times status-error'; ?>"></i> Credenciales válidas</li>
                                            <li><i class="fas fa-<?php echo $dbValidation['database_exists'] ? 'check status-ok' : 'times status-error'; ?>"></i> Base de datos existe</li>
                                            <li><i class="fas fa-<?php echo $dbValidation['tables_exist'] ? 'check status-ok' : 'times status-error'; ?>"></i> Tablas críticas existen</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if (!empty($dbValidation['errors'])): ?>
                                            <h6>Errores Encontrados:</h6>
                                            <?php foreach ($dbValidation['errors'] as $error): ?>
                                                <div class="error-item">
                                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($dbValidation['recommendations'])): ?>
                                            <h6>Recomendaciones:</h6>
                                            <?php foreach ($dbValidation['recommendations'] as $rec): ?>
                                                <div class="recommendation">
                                                    <i class="fas fa-lightbulb me-2"></i><?php echo htmlspecialchars($rec); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Validación de PHP -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fab fa-php me-2"></i>Configuración de PHP</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Extensiones PHP:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Extensión</th>
                                                        <th>Estado</th>
                                                        <th>Descripción</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($phpValidation['extensions'] as $ext => $info): ?>
                                                        <tr>
                                                            <td><code><?php echo $ext; ?></code></td>
                                                            <td>
                                                                <i class="fas fa-<?php echo $info['loaded'] ? 'check status-ok' : 'times status-error'; ?>"></i>
                                                                <?php echo $info['loaded'] ? 'Cargada' : 'Faltante'; ?>
                                                            </td>
                                                            <td><?php echo $info['description']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Configuraciones PHP:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Configuración</th>
                                                        <th>Actual</th>
                                                        <th>Recomendado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($phpValidation['settings'] as $setting => $config): ?>
                                                        <tr>
                                                            <td><?php echo $setting; ?></td>
                                                            <td><code><?php echo $config['current']; ?></code></td>
                                                            <td><code><?php echo $config['recommended']; ?></code></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <?php if (!empty($phpValidation['recommendations'])): ?>
                                            <h6 class="mt-3">Recomendaciones PHP:</h6>
                                            <?php foreach ($phpValidation['recommendations'] as $rec): ?>
                                                <div class="recommendation">
                                                    <i class="fas fa-lightbulb me-2"></i><?php echo htmlspecialchars($rec); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Validación de Archivos -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-file-code me-2"></i>Archivos Críticos</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Archivo</th>
                                                <th>Estado</th>
                                                <th>Descripción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($filesValidation['files'] as $file => $info): ?>
                                                <tr>
                                                    <td><code><?php echo $file; ?></code></td>
                                                    <td>
                                                        <?php if ($info['exists'] && $info['readable']): ?>
                                                            <i class="fas fa-check status-ok"></i> OK
                                                        <?php elseif ($info['exists']): ?>
                                                            <i class="fas fa-exclamation-triangle status-warning"></i> No legible
                                                        <?php else: ?>
                                                            <i class="fas fa-times status-error"></i> No existe
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $info['description']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Prueba de Endpoints -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-globe me-2"></i>Prueba de Endpoints</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Endpoint</th>
                                                <th>Estado</th>
                                                <th>Código HTTP</th>
                                                <th>Descripción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($endpointsValidation['endpoints'] as $endpoint => $info): ?>
                                                <tr>
                                                    <td><code><?php echo $endpoint; ?></code></td>
                                                    <td>
                                                        <i class="fas fa-<?php 
                                                            echo $info['status'] === 'ok' ? 'check status-ok' : 
                                                                ($info['status'] === 'auth_required' ? 'lock status-auth_required' : 
                                                                ($info['status'] === 'warning' ? 'exclamation-triangle status-warning' : 'times status-error')); 
                                                        ?>"></i>
                                                        <?php echo ucfirst(str_replace('_', ' ', $info['status'])); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($info['response_code']): ?>
                                                            <span class="badge bg-<?php echo $info['response_code'] < 300 ? 'success' : ($info['response_code'] < 400 ? 'warning' : 'danger'); ?>">
                                                                <?php echo $info['response_code']; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $info['description']; ?>
                                                        <?php if ($info['error']): ?>
                                                            <br><small class="text-danger"><?php echo htmlspecialchars($info['error']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Recomendaciones Finales -->
                        <?php if ($score < 80): ?>
                            <div class="card">
                                <div class="card-header bg-warning">
                                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Acciones Recomendadas</h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <h6>Para resolver errores HTTP 500 en GoDaddy:</h6>
                                        <ol>
                                            <li><strong>Verifica la configuración de base de datos</strong> en tu panel de GoDaddy</li>
                                            <li><strong>Asegúrate de que todas las extensiones PHP</strong> estén habilitadas</li>
                                            <li><strong>Revisa los permisos de archivos</strong> usando el File Manager</li>
                                            <li><strong>Contacta al soporte de GoDaddy</strong> si los problemas persisten</li>
                                        </ol>
                                    </div>
                                    
                                    <?php if (!empty($endpointsValidation['errors'])): ?>
                                        <div class="alert alert-danger">
                                            <h6>Errores Críticos en Endpoints:</h6>
                                            <ul class="mb-0">
                                                <?php foreach ($endpointsValidation['errors'] as $error): ?>
                                                    <li><?php echo htmlspecialchars($error); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle me-2"></i>¡Configuración Excelente!</h5>
                                <p class="mb-0">Tu configuración de GoDaddy parece estar correcta. Si aún experimentas errores HTTP 500, revisa los logs del servidor o contacta al soporte técnico.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Botones de Acción -->
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <a href="check_production_permissions.php" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-shield-alt me-2"></i>Verificar Permisos
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="debug_production_errors.php" class="btn btn-info btn-lg w-100">
                                    <i class="fas fa-bug me-2"></i>Diagnóstico Completo
                                </a>
                            </div>
                            <div class="col-md-4">
                                <button onclick="window.location.reload()" class="btn btn-secondary btn-lg w-100">
                                    <i class="fas fa-sync-alt me-2"></i>Verificar Nuevamente
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>