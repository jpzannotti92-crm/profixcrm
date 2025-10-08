<?php
require_once '../classes/InstallationWizard.php';

$wizard = new InstallationWizard();
$wizard->requireStep(3); // Requiere que el paso 3 esté completado

$errors = [];
$success = false;

// Procesar formulario
if ($_POST && $wizard->validateCSRF($_POST['csrf_token'] ?? '')) {
    $config = [
        'app_name' => trim($_POST['app_name'] ?? 'IATrade CRM'),
        'app_url' => trim($_POST['app_url'] ?? ''),
        'timezone' => $_POST['timezone'] ?? 'UTC',
        'currency' => $_POST['currency'] ?? 'USD',
        'date_format' => $_POST['date_format'] ?? 'Y-m-d',
        'leads_per_page' => intval($_POST['leads_per_page'] ?? 25),
        'session_timeout' => intval($_POST['session_timeout'] ?? 3600),
        'max_login_attempts' => intval($_POST['max_login_attempts'] ?? 5),
        'password_min_length' => intval($_POST['password_min_length'] ?? 8),
        'email_notifications' => isset($_POST['email_notifications']),
        'maintenance_mode' => isset($_POST['maintenance_mode'])
    ];
    
    // Validaciones
    if (empty($config['app_name'])) {
        $errors[] = 'El nombre de la aplicación es requerido';
    }
    
    if (!empty($config['app_url']) && !filter_var($config['app_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'La URL de la aplicación no es válida';
    }
    
    if ($config['leads_per_page'] < 10 || $config['leads_per_page'] > 100) {
        $errors[] = 'Los leads por página deben estar entre 10 y 100';
    }
    
    if ($config['session_timeout'] < 300 || $config['session_timeout'] > 86400) {
        $errors[] = 'El tiempo de sesión debe estar entre 5 minutos y 24 horas';
    }
    
    if ($config['max_login_attempts'] < 3 || $config['max_login_attempts'] > 10) {
        $errors[] = 'Los intentos máximos de login deben estar entre 3 y 10';
    }
    
    if ($config['password_min_length'] < 6 || $config['password_min_length'] > 20) {
        $errors[] = 'La longitud mínima de contraseña debe estar entre 6 y 20 caracteres';
    }
    
    if (empty($errors)) {
        try {
            // Obtener configuración de base de datos
            $dbConfig = $wizard->getConfig('database');
            if (!$dbConfig) {
                throw new Exception('Configuración de base de datos no encontrada');
            }
            
            // Conectar a la base de datos
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // Actualizar configuraciones en la base de datos
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (`key`, `value`, `type`, `description`, `is_public`) 
                VALUES (?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = CURRENT_TIMESTAMP
            ");
            
            $settings = [
                ['app_name', $config['app_name'], 'string', 'Nombre de la aplicación', true],
                ['app_url', $config['app_url'], 'string', 'URL base de la aplicación', true],
                ['timezone', $config['timezone'], 'string', 'Zona horaria del sistema', false],
                ['currency_default', $config['currency'], 'string', 'Moneda por defecto', true],
                ['date_format', $config['date_format'], 'string', 'Formato de fecha', false],
                ['leads_per_page', (string)$config['leads_per_page'], 'number', 'Leads por página', false],
                ['session_timeout', (string)$config['session_timeout'], 'number', 'Tiempo de sesión en segundos', false],
                ['max_login_attempts', (string)$config['max_login_attempts'], 'number', 'Intentos máximos de login', false],
                ['password_min_length', (string)$config['password_min_length'], 'number', 'Longitud mínima de contraseña', false],
                ['email_notifications', $config['email_notifications'] ? 'true' : 'false', 'boolean', 'Notificaciones por email', false],
                ['maintenance_mode', $config['maintenance_mode'] ? 'true' : 'false', 'boolean', 'Modo mantenimiento', false]
            ];
            
            foreach ($settings as $setting) {
                $stmt->execute($setting);
            }
            
            // Crear archivo de configuración
            $configContent = $wizard->generateConfigFile($dbConfig, $config);
            $configPath = dirname(__DIR__, 2) . '/config/database.php';
            
            // Crear directorio config si no existe
            $configDir = dirname($configPath);
            if (!is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }
            
            if (file_put_contents($configPath, $configContent) === false) {
                throw new Exception('No se pudo crear el archivo de configuración');
            }
            
            // Guardar configuración
            $wizard->setConfig('system', $config);
            $wizard->completeStep(4);
            $wizard->setMessage('success', 'Configuración del sistema guardada correctamente');
            $success = true;
            
        } catch (Exception $e) {
            $errors[] = 'Error al guardar la configuración: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $wizard->setMessage('error', 'Por favor corrige los siguientes errores:');
        $wizard->setMessage('error_details', $errors);
    }
}

// Obtener configuración guardada
$savedConfig = $wizard->getConfig('system') ?? [];

// Zonas horarias comunes
$timezones = [
    'UTC' => 'UTC (Tiempo Universal Coordinado)',
    'America/New_York' => 'Nueva York (EST/EDT)',
    'America/Chicago' => 'Chicago (CST/CDT)',
    'America/Denver' => 'Denver (MST/MDT)',
    'America/Los_Angeles' => 'Los Ángeles (PST/PDT)',
    'America/Mexico_City' => 'Ciudad de México (CST)',
    'America/Bogota' => 'Bogotá (COT)',
    'America/Lima' => 'Lima (PET)',
    'America/Santiago' => 'Santiago (CLT)',
    'America/Argentina/Buenos_Aires' => 'Buenos Aires (ART)',
    'Europe/London' => 'Londres (GMT/BST)',
    'Europe/Paris' => 'París (CET/CEST)',
    'Europe/Madrid' => 'Madrid (CET/CEST)',
    'Asia/Tokyo' => 'Tokio (JST)',
    'Asia/Shanghai' => 'Shanghái (CST)',
    'Asia/Dubai' => 'Dubái (GST)'
];

// Monedas comunes
$currencies = [
    'USD' => 'Dólar Estadounidense (USD)',
    'EUR' => 'Euro (EUR)',
    'GBP' => 'Libra Esterlina (GBP)',
    'JPY' => 'Yen Japonés (JPY)',
    'CAD' => 'Dólar Canadiense (CAD)',
    'AUD' => 'Dólar Australiano (AUD)',
    'CHF' => 'Franco Suizo (CHF)',
    'MXN' => 'Peso Mexicano (MXN)',
    'COP' => 'Peso Colombiano (COP)',
    'PEN' => 'Sol Peruano (PEN)',
    'CLP' => 'Peso Chileno (CLP)',
    'ARS' => 'Peso Argentino (ARS)'
];

// Formatos de fecha
$dateFormats = [
    'Y-m-d' => 'YYYY-MM-DD (2024-01-15)',
    'd/m/Y' => 'DD/MM/YYYY (15/01/2024)',
    'm/d/Y' => 'MM/DD/YYYY (01/15/2024)',
    'd-m-Y' => 'DD-MM-YYYY (15-01-2024)',
    'F j, Y' => 'Enero 15, 2024',
    'j F Y' => '15 Enero 2024'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - IATrade CRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-cogs text-blue-600 mr-3"></i>
                    Configuración del Sistema
                </h1>
                <p class="text-gray-600">Paso 4 de 5: Configura los parámetros básicos del sistema</p>
            </div>

            <!-- Progress Bar -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-600">Progreso de instalación</span>
                    <span class="text-sm text-gray-600">80%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: 80%"></div>
                </div>
            </div>

            <!-- Messages -->
            <?php echo $wizard->renderMessages(); ?>

            <!-- Main Content -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <?php if ($success): ?>
                    <!-- Success State -->
                    <div class="text-center py-8">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                            <i class="fas fa-check text-green-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">¡Configuración Guardada!</h3>
                        <p class="text-gray-600 mb-6">
                            La configuración del sistema ha sido guardada correctamente y el archivo de configuración ha sido creado.
                        </p>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-left">
                            <h4 class="font-medium text-blue-900 mb-2">Configuración aplicada:</h4>
                            <ul class="text-sm text-blue-800 space-y-1">
                                <li><strong>Aplicación:</strong> <?php echo htmlspecialchars($savedConfig['app_name'] ?? ''); ?></li>
                                <li><strong>Zona horaria:</strong> <?php echo htmlspecialchars($savedConfig['timezone'] ?? ''); ?></li>
                                <li><strong>Moneda:</strong> <?php echo htmlspecialchars($savedConfig['currency'] ?? ''); ?></li>
                                <li><strong>Archivo de configuración:</strong> config/database.php</li>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Form -->
                    <form method="POST" class="space-y-8">
                        <input type="hidden" name="csrf_token" value="<?php echo $wizard->getCSRFToken(); ?>">
                        
                        <!-- Application Settings -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-desktop text-blue-600 mr-2"></i>
                                Configuración de la Aplicación
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="app_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Nombre de la Aplicación *
                                    </label>
                                    <input type="text" 
                                           id="app_name" 
                                           name="app_name" 
                                           value="<?php echo htmlspecialchars($savedConfig['app_name'] ?? $_POST['app_name'] ?? 'IATrade CRM'); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           required>
                                    <p class="text-xs text-gray-500 mt-1">Nombre que aparecerá en la interfaz</p>
                                </div>

                                <div>
                                    <label for="app_url" class="block text-sm font-medium text-gray-700 mb-2">
                                        URL de la Aplicación
                                    </label>
                                    <input type="url" 
                                           id="app_url" 
                                           name="app_url" 
                                           value="<?php echo htmlspecialchars($savedConfig['app_url'] ?? $_POST['app_url'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="https://crm.empresa.com">
                                    <p class="text-xs text-gray-500 mt-1">URL base para enlaces y notificaciones</p>
                                </div>
                            </div>
                        </div>

                        <!-- Regional Settings -->
                        <div class="border-t pt-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-globe text-blue-600 mr-2"></i>
                                Configuración Regional
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Zona Horaria *
                                    </label>
                                    <select id="timezone" 
                                            name="timezone" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            required>
                                        <?php foreach ($timezones as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" 
                                                    <?php echo ($savedConfig['timezone'] ?? $_POST['timezone'] ?? 'UTC') === $value ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">
                                        Moneda por Defecto *
                                    </label>
                                    <select id="currency" 
                                            name="currency" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            required>
                                        <?php foreach ($currencies as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" 
                                                    <?php echo ($savedConfig['currency'] ?? $_POST['currency'] ?? 'USD') === $value ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="date_format" class="block text-sm font-medium text-gray-700 mb-2">
                                        Formato de Fecha *
                                    </label>
                                    <select id="date_format" 
                                            name="date_format" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            required>
                                        <?php foreach ($dateFormats as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" 
                                                    <?php echo ($savedConfig['date_format'] ?? $_POST['date_format'] ?? 'Y-m-d') === $value ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- System Settings -->
                        <div class="border-t pt-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-sliders-h text-blue-600 mr-2"></i>
                                Configuración del Sistema
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="leads_per_page" class="block text-sm font-medium text-gray-700 mb-2">
                                        Leads por Página
                                    </label>
                                    <input type="number" 
                                           id="leads_per_page" 
                                           name="leads_per_page" 
                                           value="<?php echo htmlspecialchars($savedConfig['leads_per_page'] ?? $_POST['leads_per_page'] ?? '25'); ?>"
                                           min="10" 
                                           max="100"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <p class="text-xs text-gray-500 mt-1">Entre 10 y 100 leads</p>
                                </div>

                                <div>
                                    <label for="session_timeout" class="block text-sm font-medium text-gray-700 mb-2">
                                        Tiempo de Sesión (segundos)
                                    </label>
                                    <select id="session_timeout" 
                                            name="session_timeout" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="1800" <?php echo ($savedConfig['session_timeout'] ?? $_POST['session_timeout'] ?? '3600') == '1800' ? 'selected' : ''; ?>>30 minutos</option>
                                        <option value="3600" <?php echo ($savedConfig['session_timeout'] ?? $_POST['session_timeout'] ?? '3600') == '3600' ? 'selected' : ''; ?>>1 hora</option>
                                        <option value="7200" <?php echo ($savedConfig['session_timeout'] ?? $_POST['session_timeout'] ?? '3600') == '7200' ? 'selected' : ''; ?>>2 horas</option>
                                        <option value="14400" <?php echo ($savedConfig['session_timeout'] ?? $_POST['session_timeout'] ?? '3600') == '14400' ? 'selected' : ''; ?>>4 horas</option>
                                        <option value="28800" <?php echo ($savedConfig['session_timeout'] ?? $_POST['session_timeout'] ?? '3600') == '28800' ? 'selected' : ''; ?>>8 horas</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="max_login_attempts" class="block text-sm font-medium text-gray-700 mb-2">
                                        Intentos Máximos de Login
                                    </label>
                                    <input type="number" 
                                           id="max_login_attempts" 
                                           name="max_login_attempts" 
                                           value="<?php echo htmlspecialchars($savedConfig['max_login_attempts'] ?? $_POST['max_login_attempts'] ?? '5'); ?>"
                                           min="3" 
                                           max="10"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <p class="text-xs text-gray-500 mt-1">Entre 3 y 10 intentos</p>
                                </div>

                                <div>
                                    <label for="password_min_length" class="block text-sm font-medium text-gray-700 mb-2">
                                        Longitud Mínima de Contraseña
                                    </label>
                                    <input type="number" 
                                           id="password_min_length" 
                                           name="password_min_length" 
                                           value="<?php echo htmlspecialchars($savedConfig['password_min_length'] ?? $_POST['password_min_length'] ?? '8'); ?>"
                                           min="6" 
                                           max="20"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <p class="text-xs text-gray-500 mt-1">Entre 6 y 20 caracteres</p>
                                </div>
                            </div>
                        </div>

                        <!-- Feature Settings -->
                        <div class="border-t pt-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-toggle-on text-blue-600 mr-2"></i>
                                Características del Sistema
                            </h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="email_notifications" 
                                           name="email_notifications" 
                                           <?php echo ($savedConfig['email_notifications'] ?? $_POST['email_notifications'] ?? true) ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="email_notifications" class="ml-2 block text-sm text-gray-900">
                                        Habilitar notificaciones por email
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="maintenance_mode" 
                                           name="maintenance_mode" 
                                           <?php echo ($savedConfig['maintenance_mode'] ?? $_POST['maintenance_mode'] ?? false) ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="maintenance_mode" class="ml-2 block text-sm text-gray-900">
                                        Activar modo mantenimiento
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="border-t pt-6">
                            <button type="submit" 
                                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i>
                                Guardar Configuración
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Navigation -->
            <div class="flex justify-between mt-8">
                <a href="../index.php?step=3" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Anterior
                </a>
                
                <?php if ($success): ?>
                    <a href="../index.php?step=5" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Siguiente
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                <?php else: ?>
                    <button type="button" 
                            disabled
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-400 cursor-not-allowed">
                        Siguiente
                        <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>