<?php
// Bootstrap de errores y logger global para evitar pantallas blancas
try {
    require_once __DIR__ . '/enhanced_error_logger.php';
} catch (Throwable $e) {
    // Fallback silencioso si el logger no está disponible
}

// Configuración de errores coherente
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Configurar ruta de log de PHP
$errorLogPath = __DIR__ . '/storage/logs/php_errors.log';
if (!is_dir(dirname($errorLogPath))) {
    @mkdir(dirname($errorLogPath), 0755, true);
}
ini_set('error_log', $errorLogPath);
/**
 * Punto de Entrada Principal - iaTrade CRM
 * Detecta automáticamente si el sistema necesita configuración
 */

// Definir rutas importantes
define('PROJECT_ROOT', __DIR__);
define('DEPLOY_DIR', PROJECT_ROOT . '/deploy');
define('CONFIG_DIR', PROJECT_ROOT . '/config');
define('INSTALL_LOCK_FILE', DEPLOY_DIR . '/.installed');

// Enfoque sin .htaccess: servir assets directamente desde /public/assets (preferido)
// con fallback a /frontend/dist/assets si existiera.
try {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (strpos($requestPath, '/assets/') === 0) {
        $assetRelative = substr($requestPath, strlen('/assets/'));
        $candidatePaths = [
            PROJECT_ROOT . '/public/assets/' . $assetRelative,
            PROJECT_ROOT . '/frontend/dist/assets/' . $assetRelative,
        ];
        foreach ($candidatePaths as $assetFile) {
            if (is_file($assetFile)) {
                $ext = strtolower(pathinfo($assetFile, PATHINFO_EXTENSION));
                $mime = 'application/octet-stream';
                if ($ext === 'css') $mime = 'text/css';
                elseif ($ext === 'js') $mime = 'application/javascript';
                elseif (in_array($ext, ['png','jpg','jpeg','gif','ico'])) $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                elseif ($ext === 'svg') $mime = 'image/svg+xml';
                elseif ($ext === 'woff') $mime = 'font/woff';
                elseif ($ext === 'woff2') $mime = 'font/woff2';
                header('Content-Type: ' . $mime);
                readfile($assetFile);
                exit;
            }
        }
    }
} catch (Throwable $e) {
    // No interrumpir flujo por errores de assets
}

// Servir directamente la SPA cuando se visita la raíz: preferir /public/index.html
try {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($path === '/' || $path === '/index.php') {
        $frontCandidates = [
            PROJECT_ROOT . '/public/index.html',
            PROJECT_ROOT . '/frontend/dist/index.html',
        ];
        foreach ($frontCandidates as $frontIndex) {
            if (is_file($frontIndex)) {
                header('Content-Type: text/html; charset=utf-8');
                readfile($frontIndex);
                exit;
            }
        }
        // Si no existe el build, mantener flujo normal
    }
} catch (Throwable $e) {
    // Continuar con flujo normal si falla
}

/**
 * Verificar si el sistema está configurado
 */
function isSystemConfigured() {
    // Verificar archivo de bloqueo de instalación (en raíz o en deploy)
    $installed = file_exists(PROJECT_ROOT . '/.installed') || file_exists(INSTALL_LOCK_FILE);
    if (!$installed) {
        return false;
    }
    
    // Verificar archivos de configuración críticos
    $requiredFiles = [
        CONFIG_DIR . '/config.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            return false;
        }
    }
    
    // Verificar configuración de base de datos
    try {
        $config = include CONFIG_DIR . '/config.php';
        $dbConf = $config['database'] ?? [];
        if (empty($dbConf['host']) || empty($dbConf['name'])) {
            return false;
        }
        
        // Probar conexión rápida
        $dsn = "mysql:host={$dbConf['host']};dbname={$dbConf['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConf['username'] ?? '', $dbConf['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3
        ]);
        
        // Verificar que existan tablas básicas
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if (!$stmt->fetch()) {
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Detectar si es una nueva instalación
 */
function isNewInstallation() {
    // Si no existe el directorio de configuración
    if (!is_dir(CONFIG_DIR)) {
        return true;
    }
    
    // Si no existen archivos críticos del sistema
    $criticalFiles = [
        PROJECT_ROOT . '/public/index.php',
        PROJECT_ROOT . '/install/sql/install.sql'
    ];
    
    foreach ($criticalFiles as $file) {
        if (!file_exists($file)) {
            return false; // Instalación incompleta, no nueva
        }
    }
    
    return !isSystemConfigured();
}

// Verificar si se debe mostrar confirmación de limpieza
if (isSystemConfigured() && file_exists(__DIR__ . '/deploy') && !file_exists(__DIR__ . '/storage/.cleanup_done')) {
    // Mostrar notificación de limpieza disponible
    $showCleanupNotification = true;
}

// Lógica principal de redirección
if (isNewInstallation()) {
    // Nueva instalación - intentar asistente de deploy si existe, con fallbacks

    // Auto-despliegue: si hay un ZIP de paquete en raíz o en deploy/releases, extraer y arrancar instalador
    try {
        // Buscar ZIP más reciente válido
        $findLatestZip = function(array $dirs) {
            $candidates = [];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) continue;
                $files = glob($dir . '/*.zip');
                foreach ($files as $f) {
                    // Priorizar paquetes que contengan "profixcrm" y "v8"
                    $name = strtolower(basename($f));
                    $score = 0;
                    if (strpos($name, 'profixcrm') !== false) $score += 2;
                    if (strpos($name, 'v8') !== false) $score += 2;
                    if (strpos($name, 'update') !== false || strpos($name, 'installer') !== false) $score += 1;
                    $candidates[] = [
                        'path' => $f,
                        'mtime' => filemtime($f),
                        'score' => $score
                    ];
                }
            }
            if (empty($candidates)) return null;
            // Ordenar por score y fecha
            usort($candidates, function($a, $b) {
                if ($a['score'] === $b['score']) return $b['mtime'] <=> $a['mtime'];
                return $b['score'] <=> $a['score'];
            });
            return $candidates[0]['path'];
        };

        $zipPath = $findLatestZip([PROJECT_ROOT, DEPLOY_DIR . '/releases']);
        if ($zipPath && class_exists('ZipArchive')) {
            // Registrar intento de auto-despliegue
            $autoLog = PROJECT_ROOT . '/logs/production/autodeploy.log';
            @mkdir(dirname($autoLog), 0755, true);
            @file_put_contents($autoLog, date('Y-m-d H:i:s') . " - Detectado ZIP: " . basename($zipPath) . "\n", FILE_APPEND);

            $zip = new ZipArchive();
            if ($zip->open($zipPath) === true) {
                // Extraer en directorio temporal primero
                $tempDir = PROJECT_ROOT . '/temp/v8/autodeploy_' . date('Ymd_His');
                @mkdir($tempDir, 0755, true);
                $zip->extractTo($tempDir);
                $zip->close();

                // Detectar raíz del paquete (si viene dentro de una carpeta única)
                $entries = array_values(array_filter(scandir($tempDir), function($e) {
                    return $e !== '.' && $e !== '..';
                }));
                $packageRoot = $tempDir;
                if (count($entries) === 1 && is_dir($tempDir . '/' . $entries[0])) {
                    $packageRoot = $tempDir . '/' . $entries[0];
                }

                // Si el paquete parece completo (tiene api/src/public/vendor), copiar a raíz
                $expectedDirs = ['api','src','public','vendor'];
                $hasExpected = true;
                foreach ($expectedDirs as $d) {
                    if (!is_dir($packageRoot . '/' . $d)) { $hasExpected = false; break; }
                }
                if ($hasExpected) {
                    // Copiado recursivo seguro (sin sobrescribir .htaccess si ya existe)
                    $copyRecursive = function($src, $dst) use (&$copyRecursive) {
                        if (is_dir($src)) {
                            @mkdir($dst, 0755, true);
                            $items = scandir($src);
                            foreach ($items as $item) {
                                if ($item === '.' || $item === '..') continue;
                                $s = $src . '/' . $item;
                                $d = $dst . '/' . $item;
                                if (is_dir($s)) {
                                    $copyRecursive($s, $d);
                                } else {
                                    // No sobrescribir .htaccess existente en raíz
                                    if (basename($d) === '.htaccess' && file_exists($d)) continue;
                                    @copy($s, $d);
                                }
                            }
                        } else {
                            @copy($src, $dst);
                        }
                    };
                    $copyRecursive($packageRoot, PROJECT_ROOT);
                    @file_put_contents($autoLog, date('Y-m-d H:i:s') . " - Paquete copiado a raíz desde $packageRoot\n", FILE_APPEND);
                } else {
                    @file_put_contents($autoLog, date('Y-m-d H:i:s') . " - Paquete extraído en $tempDir (estructura no estándar)\n", FILE_APPEND);
                }

                // Redirigir al sistema de despliegue para continuar la instalación
                if (file_exists(PROJECT_ROOT . '/deploy_system.php')) {
                    header('Location: /deploy_system.php');
                    exit;
                } elseif (file_exists(PROJECT_ROOT . '/install/index.php')) {
                    header('Location: /install/index.php');
                    exit;
                }
            } else {
                @file_put_contents($autoLog, date('Y-m-d H:i:s') . " - No se pudo abrir ZIP: $zipPath\n", FILE_APPEND);
            }
        }
    } catch (Throwable $e) {
        // Fallo silencioso del autodeploy, continuar con flujo normal
    }

    // Crear .htaccess mínimo si falta para evitar 404 por rutas
    if (!file_exists(__DIR__ . '/.htaccess')) {
        $htaccess = <<<'HTA'
# ProfixCRM fallback .htaccess
Options -Indexes

<IfModule mod_rewrite.c>
RewriteEngine On
DirectoryIndex index.php

# Servir archivos y directorios existentes tal cual
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Enrutar todo lo demás a index.php
RewriteRule ^ index.php [L]
</IfModule>

# Proteger archivos sensibles
<FilesMatch "^(\.env|composer\.json|composer\.lock|admin_token\.txt|current_token\.txt)$">
    Require all denied
</FilesMatch>
HTA;
        @file_put_contents(__DIR__ . '/.htaccess', $htaccess);
    }

    // Prioridad de redirección: setup_wizard -> instalar_simple -> public/deploy-assistant
    if (file_exists(__DIR__ . '/deploy/setup_wizard.php')) {
// REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA -         header('Location: /deploy/setup_wizard.php');
        exit;
    } elseif (file_exists(__DIR__ . '/deploy/instalar_simple.php')) {
// REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA -         header('Location: /deploy/instalar_simple.php');
        exit;
    } elseif (file_exists(__DIR__ . '/public/deploy-assistant.php')) {
// REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA -         header('Location: /public/deploy-assistant.php');
        exit;
    } else {
        // Fallback absoluto: mostrar ayuda básica
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>iaTrade CRM - Instalación inicial</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="bg-light">
        <div class="container py-5">
            <div class="alert alert-warning">
                No se encontró la carpeta <code>deploy</code> en el servidor ni el asistente web en <code>/public</code>.
            </div>
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Siguientes pasos</h3>
                    <ol>
                        <li>Sube la carpeta <code>deploy</code> al <strong>document root</strong> (ej: <code>public_html</code>).</li>
                        <li>Accede a <code>/deploy/instalar_simple.php</code> para completar la instalación.</li>
                        <li>Si prefieres, sube solo <code>/public/deploy-assistant.php</code> y visita esa URL.</li>
                        <li>Si el servidor devuelve 404, confirma que el dominio apunta al directorio con este <code>index.php</code>.</li>
                    </ol>
                </div>
            </div>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
} elseif (!isSystemConfigured()) {
    // Sistema parcialmente configurado - mostrar página de error/recuperación
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>iaTrade CRM - Configuración Incompleta</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-card {
                background: white;
                border-radius: 20px;
                padding: 3rem;
                box-shadow: 0 25px 50px rgba(0,0,0,0.15);
                text-align: center;
                max-width: 500px;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
            <h3 class="mt-3">Configuración Incompleta</h3>
            <p class="text-muted">El sistema no está completamente configurado. Esto puede deberse a una instalación interrumpida o archivos de configuración dañados.</p>
            <div class="mt-4">
                <a href="/deploy/setup_wizard.php" class="btn btn-primary me-2">
                    <i class="fas fa-redo me-2"></i>Reconfigurar Sistema
                </a>
                <a href="/deploy/rollback.php" class="btn btn-outline-secondary">
                    <i class="fas fa-history me-2"></i>Restaurar Backup
                </a>
            </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    </body>
    </html>
    <?php
    exit;
} elseif (isset($showCleanupNotification) && $showCleanupNotification) {
    // Sistema configurado pero con archivos de deploy - mostrar opción de limpieza
    showCleanupNotification();
    exit;
} else {
    // Sistema configurado correctamente: servir directamente la SPA construida (frontend/dist)
    // Esto evita depender de .htaccess para redirecciones.
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = preg_match('/localhost|127\.0\.0\.1/', $host);
    if ($isLocal) {
        // En local, mantener redirección al dev server
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $loginUrl = $protocol . '://localhost:3000/auth/login';
// REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA - // REDIRECCIÓN TEMPORALMENTE DESACTIVADA -         header('Location: ' . $loginUrl);
        exit;
    }
    // En producción, servir el index.html del build de React
    $spaIndex = PROJECT_ROOT . '/frontend/dist/index.html';
    if (is_file($spaIndex)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($spaIndex);
        exit;
    }
    // Fallback: delegar al controlador de /public si el build no existe
    require PROJECT_ROOT . '/public/index.php';
    exit;
}

/**
 * Mostrar notificación de limpieza
 */
function showCleanupNotification() {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sistema Instalado - iaTrade CRM</title>
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
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .container {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                padding: 40px;
                max-width: 600px;
                width: 100%;
                text-align: center;
            }

            .success-icon {
                width: 80px;
                height: 80px;
                background: #00b894;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 30px;
                font-size: 40px;
                color: white;
            }

            h1 {
                color: #2d3436;
                margin-bottom: 20px;
                font-size: 28px;
            }

            .description {
                color: #636e72;
                margin-bottom: 30px;
                line-height: 1.6;
                font-size: 16px;
            }

            .cleanup-notice {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 10px;
                padding: 20px;
                margin: 30px 0;
                text-align: left;
            }

            .cleanup-title {
                color: #856404;
                font-weight: bold;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .cleanup-text {
                color: #856404;
                line-height: 1.5;
            }

            .button-group {
                display: flex;
                gap: 15px;
                justify-content: center;
                margin-top: 30px;
            }

            .btn {
                padding: 15px 30px;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
            }

            .btn-primary {
                background: #74b9ff;
                color: white;
            }

            .btn-primary:hover {
                background: #0984e3;
                transform: translateY(-2px);
            }

            .btn-warning {
                background: #fdcb6e;
                color: #2d3436;
            }

            .btn-warning:hover {
                background: #e17055;
                color: white;
                transform: translateY(-2px);
            }

            @media (max-width: 768px) {
                .container {
                    padding: 30px 20px;
                }
                
                .button-group {
                    flex-direction: column;
                }
                
                .btn {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-icon">✅</div>
            
            <h1>¡Instalación Completada!</h1>
            
            <p class="description">
                El sistema iaTrade CRM se ha instalado correctamente y está listo para usar. 
                Todos los componentes están funcionando perfectamente.
            </p>

            <div class="cleanup-notice">
                <div class="cleanup-title">
                    🧹 Limpieza Recomendada
                </div>
                <div class="cleanup-text">
                    Para mejorar la seguridad, se recomienda eliminar los archivos de instalación 
                    que ya no son necesarios. Esta acción es opcional pero recomendada.
                </div>
            </div>

            <div class="button-group">
                <a href="<?php 
                    // Detectar URL base dinámicamente
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    
                    // Si estamos en desarrollo local, usar puerto específico para frontend
                    if (strpos($host, 'localhost') !== false && strpos($host, ':') === false) {
                        echo $protocol . '://' . $host . ':3000/auth/login';
                    } else {
                        echo $protocol . '://' . $host . '/auth/login';
                    }
                ?>" class="btn-primary">Ir al Sistema Principal</a>
                <a href="deploy/cleanup_confirmation.php" class="btn btn-warning">
                    Limpiar Archivos de Instalación
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>