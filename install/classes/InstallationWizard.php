<?php

/**
 * Clase principal del asistente de instalación
 * Maneja el flujo de instalación y coordina todos los componentes
 */
class InstallationWizard
{
    private $steps = [
        1 => 'requirements',
        2 => 'database',
        3 => 'superadmin',
        4 => 'configuration',
        5 => 'complete'
    ];

    private $errors = [];
    private $warnings = [];
    private $success = [];

    public function __construct()
    {
        // Inicializar sesión si no existe
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Inicializar datos de instalación en sesión
        if (!isset($_SESSION['installation_data'])) {
            $_SESSION['installation_data'] = [
                'completed_steps' => [],
                'database_config' => [],
                'superadmin_config' => [],
                'app_config' => []
            ];
        }
    }

    /**
     * Obtiene el paso actual
     */
    public function getCurrentStep()
    {
        return $_GET['step'] ?? 1;
    }

    /**
     * Verifica si un paso está completado
     */
    public function isStepCompleted($step)
    {
        return in_array($step, $_SESSION['installation_data']['completed_steps']);
    }

    /**
     * Marca un paso como completado
     */
    public function markStepCompleted($step)
    {
        if (!in_array($step, $_SESSION['installation_data']['completed_steps'])) {
            $_SESSION['installation_data']['completed_steps'][] = $step;
        }
    }

    /**
     * Verifica si se puede acceder a un paso
     */
    public function canAccessStep($step)
    {
        if ($step == 1) return true;
        
        // Para acceder a un paso, el anterior debe estar completado
        return $this->isStepCompleted($step - 1);
    }

    /**
     * Redirige al siguiente paso
     */
    public function redirectToNextStep($currentStep)
    {
        $nextStep = $currentStep + 1;
        if ($nextStep <= count($this->steps)) {
            header("Location: index.php?step=" . $nextStep);
            exit;
        }
    }

    /**
     * Redirige a un paso específico
     */
    public function redirectToStep($step)
    {
        if ($this->canAccessStep($step)) {
            header("Location: index.php?step=" . $step);
            exit;
        }
    }

    /**
     * Añade un error
     */
    public function addError($message, $details = null)
    {
        $this->errors[] = [
            'message' => $message,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Añade una advertencia
     */
    public function addWarning($message, $details = null)
    {
        $this->warnings[] = [
            'message' => $message,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Añade un mensaje de éxito
     */
    public function addSuccess($message, $details = null)
    {
        $this->success[] = [
            'message' => $message,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Obtiene todos los errores
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Obtiene todas las advertencias
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Obtiene todos los mensajes de éxito
     */
    public function getSuccess()
    {
        return $this->success;
    }

    /**
     * Verifica si hay errores
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Verifica si hay advertencias
     */
    public function hasWarnings()
    {
        return !empty($this->warnings);
    }

    /**
     * Limpia todos los mensajes
     */
    public function clearMessages()
    {
        $this->errors = [];
        $this->warnings = [];
        $this->success = [];
    }

    /**
     * Guarda configuración en sesión
     */
    public function saveConfig($section, $data)
    {
        $_SESSION['installation_data'][$section] = array_merge(
            $_SESSION['installation_data'][$section] ?? [],
            $data
        );
    }

    /**
     * Obtiene configuración de sesión
     */
    public function getConfig($section, $key = null)
    {
        if ($key) {
            return $_SESSION['installation_data'][$section][$key] ?? null;
        }
        return $_SESSION['installation_data'][$section] ?? [];
    }

    /**
     * Genera un token CSRF
     */
    public function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verifica el token CSRF
     */
    public function verifyCSRFToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Renderiza mensajes de estado
     */
    public function renderMessages()
    {
        $html = '';

        // Errores
        if ($this->hasErrors()) {
            foreach ($this->errors as $index => $error) {
                $html .= '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">';
                $html .= '<div class="flex items-center">';
                $html .= '<i class="fas fa-exclamation-circle mr-2"></i>';
                $html .= '<strong class="font-bold">Error: </strong>';
                $html .= '<span class="block sm:inline">' . htmlspecialchars($error['message']) . '</span>';
                if ($error['details']) {
                    $html .= '<button type="button" class="ml-2 text-red-600 hover:text-red-800" onclick="toggleErrorDetails(\'error-details-' . $index . '\')">';
                    $html .= '<i class="fas fa-info-circle"></i> Detalles';
                    $html .= '</button>';
                }
                $html .= '</div>';
                if ($error['details']) {
                    $html .= '<div id="error-details-' . $index . '" class="hidden mt-2 p-3 bg-red-50 rounded text-sm">';
                    $html .= '<pre class="whitespace-pre-wrap">' . htmlspecialchars($error['details']) . '</pre>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
        }

        // Advertencias
        if ($this->hasWarnings()) {
            foreach ($this->warnings as $index => $warning) {
                $html .= '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4" role="alert">';
                $html .= '<div class="flex items-center">';
                $html .= '<i class="fas fa-exclamation-triangle mr-2"></i>';
                $html .= '<strong class="font-bold">Advertencia: </strong>';
                $html .= '<span class="block sm:inline">' . htmlspecialchars($warning['message']) . '</span>';
                if ($warning['details']) {
                    $html .= '<button type="button" class="ml-2 text-yellow-600 hover:text-yellow-800" onclick="toggleErrorDetails(\'warning-details-' . $index . '\')">';
                    $html .= '<i class="fas fa-info-circle"></i> Detalles';
                    $html .= '</button>';
                }
                $html .= '</div>';
                if ($warning['details']) {
                    $html .= '<div id="warning-details-' . $index . '" class="hidden mt-2 p-3 bg-yellow-50 rounded text-sm">';
                    $html .= '<pre class="whitespace-pre-wrap">' . htmlspecialchars($warning['details']) . '</pre>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
        }

        // Éxitos
        if (!empty($this->success)) {
            foreach ($this->success as $success) {
                $html .= '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">';
                $html .= '<div class="flex items-center">';
                $html .= '<i class="fas fa-check-circle mr-2"></i>';
                $html .= '<strong class="font-bold">Éxito: </strong>';
                $html .= '<span class="block sm:inline">' . htmlspecialchars($success['message']) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }

        return $html;
    }

    /**
     * Limpia la instalación (para reiniciar)
     */
    public function resetInstallation()
    {
        unset($_SESSION['installation_data']);
        unset($_SESSION['csrf_token']);
        $this->clearMessages();
    }
    
    /**
     * Verifica que la instalación esté completa y funcionando
     */
    public function verifyInstallation() {
        $results = [
            'success' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        try {
            // Verificar conexión a base de datos
            $dbConfig = $this->getConfig('database');
            if (!$dbConfig) {
                $results['errors'][] = 'Configuración de base de datos no encontrada';
                $results['success'] = false;
                return $results;
            }
            
            $pdo = new PDO(
                "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4",
                $dbConfig['username'],
                $dbConfig['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Verificar tablas principales
            $requiredTables = ['users', 'roles', 'permissions', 'role_permissions', 'user_roles'];
            foreach ($requiredTables as $table) {
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                if (!$stmt->fetch()) {
                    $results['errors'][] = "Tabla requerida '{$table}' no encontrada";
                    $results['success'] = false;
                }
            }
            
            // Verificar superadmin
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = 1");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $results['errors'][] = 'Usuario superadmin no encontrado';
                $results['success'] = false;
            }
            
            // Verificar roles
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE name = 'superadmin'");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $results['errors'][] = 'Rol superadmin no encontrado';
                $results['success'] = false;
            }
            
            // Verificar archivo de configuración
            $configPath = dirname(__DIR__, 2) . '/config/database.php';
            if (!file_exists($configPath)) {
                $results['warnings'][] = 'Archivo de configuración no encontrado';
            }
            
        } catch (Exception $e) {
            $results['errors'][] = 'Error de verificación: ' . $e->getMessage();
            $results['success'] = false;
        }
        
        return $results;
    }
    
    /**
     * Limpia los archivos de instalación
     */
    public function cleanupInstallation() {
        $installDir = __DIR__ . '/..';
        
        // Lista de archivos y directorios a eliminar
        $filesToDelete = [
            $installDir . '/index.php',
            $installDir . '/steps',
            $installDir . '/classes',
            $installDir . '/sql'
        ];
        
        foreach ($filesToDelete as $path) {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->deleteDirectory($path);
            }
        }
        
        // Crear archivo de bloqueo
        file_put_contents($installDir . '/.installed', date('Y-m-d H:i:s'));
    }
    
    /**
     * Elimina un directorio recursivamente
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}