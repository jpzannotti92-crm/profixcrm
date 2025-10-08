<?php
require_once '../classes/InstallationWizard.php';

$wizard = new InstallationWizard();
$wizard->requireStep(4); // Requiere que el paso 4 esté completado

$errors = [];
$success = false;
$verificationResults = [];

// Procesar finalización
if ($_POST && $wizard->validateCSRF($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['complete_installation'])) {
        try {
            // Verificar instalación
            $verificationResults = $wizard->verifyInstallation();
            
            if ($verificationResults['success']) {
                // Limpiar archivos de instalación si se solicita
                if (isset($_POST['cleanup_files'])) {
                    $wizard->cleanupInstallation();
                }
                
                // Marcar instalación como completada
                $wizard->completeStep(5);
                $wizard->setConfig('installation_completed', true);
                $wizard->setConfig('installation_date', date('Y-m-d H:i:s'));
                
                $success = true;
                $wizard->setMessage('success', '¡Instalación completada exitosamente!');
            } else {
                $wizard->setMessage('error', 'Se encontraron problemas en la verificación:');
                $wizard->setMessage('error_details', $verificationResults['errors']);
            }
            
        } catch (Exception $e) {
            $errors[] = 'Error durante la finalización: ' . $e->getMessage();
            $wizard->setMessage('error', 'Error durante la finalización:');
            $wizard->setMessage('error_details', $errors);
        }
    }
}

// Obtener información de la instalación
$dbConfig = $wizard->getConfig('database') ?? [];
$systemConfig = $wizard->getConfig('system') ?? [];
$superadmin = $wizard->getConfig('superadmin') ?? [];
$installationCompleted = $wizard->getConfig('installation_completed') ?? false;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación Completada - IATrade CRM</title>
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
                    <i class="fas fa-flag-checkered text-green-600 mr-3"></i>
                    Finalizar Instalación
                </h1>
                <p class="text-gray-600">Paso 5 de 5: Verificación final y completar la instalación</p>
            </div>

            <!-- Progress Bar -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-600">Progreso de instalación</span>
                    <span class="text-sm text-gray-600">100%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-600 h-2 rounded-full" style="width: 100%"></div>
                </div>
            </div>

            <!-- Messages -->
            <?php echo $wizard->renderMessages(); ?>

            <?php if ($installationCompleted): ?>
                <!-- Installation Completed -->
                <div class="bg-white rounded-lg shadow-lg p-8 text-center">
                    <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6">
                        <i class="fas fa-check text-green-600 text-3xl"></i>
                    </div>
                    
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">¡Instalación Completada Exitosamente!</h2>
                    <p class="text-gray-600 mb-8">IATrade CRM ha sido instalado y configurado correctamente en tu servidor.</p>
                    
                    <!-- Installation Summary -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-8 text-left">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-clipboard-list text-blue-600 mr-2"></i>
                            Resumen de la Instalación
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Base de Datos</h4>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li><strong>Host:</strong> <?php echo htmlspecialchars($dbConfig['host'] ?? ''); ?></li>
                                    <li><strong>Base de datos:</strong> <?php echo htmlspecialchars($dbConfig['database'] ?? ''); ?></li>
                                    <li><strong>Usuario:</strong> <?php echo htmlspecialchars($dbConfig['username'] ?? ''); ?></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Super Administrador</h4>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li><strong>Usuario:</strong> <?php echo htmlspecialchars($superadmin['username'] ?? ''); ?></li>
                                    <li><strong>Email:</strong> <?php echo htmlspecialchars($superadmin['email'] ?? ''); ?></li>
                                    <li><strong>Nombre:</strong> <?php echo htmlspecialchars(($superadmin['first_name'] ?? '') . ' ' . ($superadmin['last_name'] ?? '')); ?></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Sistema</h4>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li><strong>Aplicación:</strong> <?php echo htmlspecialchars($systemConfig['app_name'] ?? ''); ?></li>
                                    <li><strong>Zona horaria:</strong> <?php echo htmlspecialchars($systemConfig['timezone'] ?? ''); ?></li>
                                    <li><strong>Moneda:</strong> <?php echo htmlspecialchars($systemConfig['currency'] ?? ''); ?></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Instalación</h4>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li><strong>Fecha:</strong> <?php echo date('d/m/Y H:i:s', strtotime($wizard->getConfig('installation_date') ?? 'now')); ?></li>
                                    <li><strong>Versión:</strong> 1.0.0</li>
                                    <li><strong>Estado:</strong> <span class="text-green-600 font-medium">Completada</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Next Steps -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8 text-left">
                        <h3 class="text-lg font-medium text-blue-900 mb-4">
                            <i class="fas fa-rocket text-blue-600 mr-2"></i>
                            Próximos Pasos
                        </h3>
                        
                        <ol class="text-sm text-blue-800 space-y-2 list-decimal list-inside">
                            <li>Accede al sistema con las credenciales del Super Administrador</li>
                            <li>Configura los módulos adicionales según tus necesidades</li>
                            <li>Crea usuarios adicionales y asigna roles</li>
                            <li>Configura las mesas de trabajo y equipos</li>
                            <li>Importa o crea tus primeros leads</li>
                            <li>Personaliza la configuración según tu empresa</li>
                        </ol>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="../../public/" 
                           class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Acceder al Sistema
                        </a>
                        
                        <a href="../manual/" 
                           class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-book mr-2"></i>
                            Ver Manual
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Pre-completion Form -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <!-- Verification Results -->
                    <?php if (!empty($verificationResults)): ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-search text-blue-600 mr-2"></i>
                                Resultados de Verificación
                            </h3>
                            
                            <?php if ($verificationResults['success']): ?>
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                        <span class="text-green-800 font-medium">Todas las verificaciones pasaron correctamente</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <i class="fas fa-exclamation-circle text-red-600 mr-2 mt-1"></i>
                                        <div>
                                            <span class="text-red-800 font-medium">Se encontraron problemas:</span>
                                            <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                                <?php foreach ($verificationResults['errors'] as $error): ?>
                                                    <li><?php echo htmlspecialchars($error); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Installation Summary -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-clipboard-check text-blue-600 mr-2"></i>
                            Verificación de la Instalación
                        </h3>
                        
                        <div class="bg-gray-50 rounded-lg p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="font-medium text-gray-900 mb-3">Componentes Instalados</h4>
                                    <ul class="space-y-2 text-sm">
                                        <li class="flex items-center">
                                            <i class="fas fa-check text-green-600 mr-2"></i>
                                            <span>Base de datos configurada</span>
                                        </li>
                                        <li class="flex items-center">
                                            <i class="fas fa-check text-green-600 mr-2"></i>
                                            <span>Tablas creadas</span>
                                        </li>
                                        <li class="flex items-center">
                                            <i class="fas fa-check text-green-600 mr-2"></i>
                                            <span>Datos iniciales insertados</span>
                                        </li>
                                        <li class="flex items-center">
                                            <i class="fas fa-check text-green-600 mr-2"></i>
                                            <span>Super administrador creado</span>
                                        </li>
                                    </ul>
                                </div>
                                
                                <div>
                                    <h4 class="font-medium text-gray-900 mb-3">Configuración</h4>
                                    <ul class="space-y-2 text-sm">
                                        <li class="flex items-center">
                                            <i class="fas fa-check text-green-600 mr-2"></i>
                                            <span>Archivo de configuración creado</span>
                                        </li>
                                        <li class="flex items-center">
                                            <i class="fas fa-check text-green-600 mr-2"></i>
                                            <span>Configuración del sistema guardada</span>
                                        </li>
                                        <li class="flex items-center">
                                            <i class="fas fa-check text-green-600 mr-2"></i>
                                            <span>Permisos configurados</span>
                                        </li>
                                        <li class="flex items-center">
                                            <i class="fas fa-check text-green-600 mr-2"></i>
                                            <span>Roles asignados</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Completion Form -->
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo $wizard->getCSRFToken(); ?>">
                        
                        <!-- Options -->
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-cog text-blue-600 mr-2"></i>
                                Opciones de Finalización
                            </h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="cleanup_files" 
                                           name="cleanup_files" 
                                           checked
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
                                    <div class="ml-3">
                                        <label for="cleanup_files" class="block text-sm font-medium text-gray-900">
                                            Limpiar archivos de instalación
                                        </label>
                                        <p class="text-xs text-gray-500">
                                            Recomendado: Elimina los archivos del instalador por seguridad
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Warning -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-1"></i>
                                <div class="text-sm text-yellow-800">
                                    <p class="font-medium mb-1">Importante:</p>
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>Guarda las credenciales del Super Administrador en un lugar seguro</li>
                                        <li>Asegúrate de tener una copia de seguridad de la configuración</li>
                                        <li>Una vez completada, no podrás volver a ejecutar el instalador</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="border-t pt-6">
                            <button type="submit" 
                                    name="complete_installation"
                                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-flag-checkered mr-2"></i>
                                Completar Instalación
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Navigation -->
            <?php if (!$installationCompleted): ?>
                <div class="flex justify-between mt-8">
                    <a href="../index.php?step=4" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Anterior
                    </a>
                    
                    <div class="text-sm text-gray-500">
                        Último paso de la instalación
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>