<?php
require_once '../classes/InstallationWizard.php';
require_once '../classes/DatabaseInstaller.php';

$wizard = new InstallationWizard();
$wizard->requireStep(1); // Requiere que el paso 1 esté completado

$dbInstaller = new DatabaseInstaller();
$errors = [];
$success = false;
$testResult = null;

// Procesar formulario
if ($_POST && $wizard->validateCSRF($_POST['csrf_token'] ?? '')) {
    $config = [
        'host' => trim($_POST['db_host'] ?? ''),
        'port' => trim($_POST['db_port'] ?? '3306'),
        'database' => trim($_POST['db_name'] ?? ''),
        'username' => trim($_POST['db_user'] ?? ''),
        'password' => $_POST['db_password'] ?? '',
        'charset' => 'utf8mb4'
    ];
    
    // Validar campos requeridos
    if (empty($config['host'])) $errors[] = 'El host de la base de datos es requerido';
    if (empty($config['database'])) $errors[] = 'El nombre de la base de datos es requerido';
    if (empty($config['username'])) $errors[] = 'El usuario de la base de datos es requerido';
    
    if (empty($errors)) {
        if (isset($_POST['test_connection'])) {
            // Solo probar conexión
            $testResult = $dbInstaller->testConnection($config);
            if ($testResult['success']) {
                $wizard->setMessage('success', 'Conexión exitosa a la base de datos');
            } else {
                $wizard->setMessage('error', 'Error de conexión: ' . $testResult['message']);
            }
        } elseif (isset($_POST['install_database'])) {
            // Instalar base de datos
            $result = $dbInstaller->installDatabase($config);
            if ($result['success']) {
                // Guardar configuración
                $wizard->setConfig('database', $config);
                $wizard->completeStep(2);
                $wizard->setMessage('success', 'Base de datos instalada correctamente');
                $success = true;
            } else {
                $wizard->setMessage('error', 'Error en la instalación: ' . $result['message']);
                if (!empty($result['details'])) {
                    $wizard->setMessage('error_details', $result['details']);
                }
            }
        }
    } else {
        $wizard->setMessage('error', 'Por favor corrige los siguientes errores:');
        $wizard->setMessage('error_details', $errors);
    }
}

// Obtener configuración guardada
$savedConfig = $wizard->getConfig('database') ?? [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Base de Datos - IATrade CRM</title>
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
                    <i class="fas fa-database text-blue-600 mr-3"></i>
                    Configuración de Base de Datos
                </h1>
                <p class="text-gray-600">Paso 2 de 5: Configura la conexión a la base de datos</p>
            </div>

            <!-- Progress Bar -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-600">Progreso de instalación</span>
                    <span class="text-sm text-gray-600">40%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: 40%"></div>
                </div>
            </div>

            <!-- Messages -->
            <?php echo $wizard->renderMessages(); ?>

            <!-- Main Content -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <form method="POST" class="space-y-6" x-data="{ 
                    showPassword: false,
                    testing: false,
                    installing: false
                }">
                    <input type="hidden" name="csrf_token" value="<?php echo $wizard->getCSRFToken(); ?>">
                    
                    <!-- Database Configuration -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="db_host" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-server text-gray-400 mr-2"></i>
                                Host de la Base de Datos *
                            </label>
                            <input type="text" 
                                   id="db_host" 
                                   name="db_host" 
                                   value="<?php echo htmlspecialchars($savedConfig['host'] ?? 'localhost'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="localhost"
                                   required>
                            <p class="text-xs text-gray-500 mt-1">Dirección del servidor de base de datos</p>
                        </div>

                        <div>
                            <label for="db_port" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-plug text-gray-400 mr-2"></i>
                                Puerto
                            </label>
                            <input type="number" 
                                   id="db_port" 
                                   name="db_port" 
                                   value="<?php echo htmlspecialchars($savedConfig['port'] ?? '3306'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="3306">
                            <p class="text-xs text-gray-500 mt-1">Puerto del servidor MySQL (por defecto: 3306)</p>
                        </div>

                        <div>
                            <label for="db_name" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-database text-gray-400 mr-2"></i>
                                Nombre de la Base de Datos *
                            </label>
                            <input type="text" 
                                   id="db_name" 
                                   name="db_name" 
                                   value="<?php echo htmlspecialchars($savedConfig['database'] ?? 'iatrade_crm'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="iatrade_crm"
                                   required>
                            <p class="text-xs text-gray-500 mt-1">Nombre de la base de datos (se creará si no existe)</p>
                        </div>

                        <div>
                            <label for="db_user" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user text-gray-400 mr-2"></i>
                                Usuario de la Base de Datos *
                            </label>
                            <input type="text" 
                                   id="db_user" 
                                   name="db_user" 
                                   value="<?php echo htmlspecialchars($savedConfig['username'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="root"
                                   required>
                            <p class="text-xs text-gray-500 mt-1">Usuario con permisos para crear bases de datos</p>
                        </div>

                        <div class="md:col-span-2">
                            <label for="db_password" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock text-gray-400 mr-2"></i>
                                Contraseña de la Base de Datos
                            </label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" 
                                       id="db_password" 
                                       name="db_password" 
                                       value="<?php echo htmlspecialchars($savedConfig['password'] ?? ''); ?>"
                                       class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Contraseña del usuario">
                                <button type="button" 
                                        @click="showPassword = !showPassword"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-gray-400"></i>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Contraseña del usuario de base de datos</p>
                        </div>
                    </div>

                    <!-- Test Connection -->
                    <div class="border-t pt-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">
                                <i class="fas fa-network-wired text-blue-600 mr-2"></i>
                                Probar Conexión
                            </h3>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                Recomendamos probar la conexión antes de proceder con la instalación.
                            </p>
                        </div>

                        <button type="submit" 
                                name="test_connection"
                                :disabled="testing"
                                @click="testing = true"
                                class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                            <i class="fas fa-plug mr-2"></i>
                            <span x-show="!testing">Probar Conexión</span>
                            <span x-show="testing">Probando...</span>
                        </button>

                        <?php if ($testResult): ?>
                            <div class="mt-4 p-4 rounded-lg <?php echo $testResult['success'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
                                <div class="flex items-center">
                                    <i class="fas <?php echo $testResult['success'] ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600'; ?> mr-2"></i>
                                    <span class="text-sm <?php echo $testResult['success'] ? 'text-green-800' : 'text-red-800'; ?>">
                                        <?php echo htmlspecialchars($testResult['message']); ?>
                                    </span>
                                </div>
                                <?php if (!empty($testResult['details'])): ?>
                                    <div class="mt-2 text-xs <?php echo $testResult['success'] ? 'text-green-700' : 'text-red-700'; ?>">
                                        <?php if (is_array($testResult['details'])): ?>
                                            <ul class="list-disc list-inside">
                                                <?php foreach ($testResult['details'] as $detail): ?>
                                                    <li><?php echo htmlspecialchars($detail); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($testResult['details']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Install Database -->
                    <?php if ($testResult && $testResult['success']): ?>
                        <div class="border-t pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">
                                    <i class="fas fa-download text-green-600 mr-2"></i>
                                    Instalar Base de Datos
                                </h3>
                            </div>
                            
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                <p class="text-sm text-yellow-800">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>Importante:</strong> Este proceso creará las tablas y datos iniciales. 
                                    Si la base de datos ya existe, se sobrescribirán los datos existentes.
                                </p>
                            </div>

                            <button type="submit" 
                                    name="install_database"
                                    :disabled="installing"
                                    @click="installing = true"
                                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-50">
                                <i class="fas fa-download mr-2"></i>
                                <span x-show="!installing">Instalar Base de Datos</span>
                                <span x-show="installing">Instalando...</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Navigation -->
            <div class="flex justify-between mt-8">
                <a href="../index.php?step=1" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Anterior
                </a>
                
                <?php if ($success): ?>
                    <a href="../index.php?step=3" 
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

            <!-- Help Section -->
            <div class="mt-8 bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    <i class="fas fa-question-circle text-blue-600 mr-2"></i>
                    ¿Necesitas ayuda?
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Configuración típica de XAMPP:</h4>
                        <ul class="space-y-1">
                            <li><strong>Host:</strong> localhost</li>
                            <li><strong>Puerto:</strong> 3306</li>
                            <li><strong>Usuario:</strong> root</li>
                            <li><strong>Contraseña:</strong> (vacía)</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Errores comunes:</h4>
                        <ul class="space-y-1">
                            <li>• Verificar que MySQL esté ejecutándose</li>
                            <li>• Comprobar usuario y contraseña</li>
                            <li>• Verificar permisos del usuario</li>
                            <li>• Revisar configuración del firewall</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>