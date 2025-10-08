<?php
/**
 * IATrade CRM - Asistente de Instalación
 * Sistema de instalación paso a paso para el despliegue en producción
 */

session_start();

// Configuración de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir archivos necesarios
require_once __DIR__ . '/classes/InstallationWizard.php';
require_once __DIR__ . '/classes/RequirementsChecker.php';
require_once __DIR__ . '/classes/DatabaseInstaller.php';
require_once __DIR__ . '/classes/UserSetup.php';

$wizard = new InstallationWizard();
$currentStep = $_GET['step'] ?? 1;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IATrade CRM - Asistente de Instalación</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .step-indicator {
            transition: all 0.3s ease;
        }
        .step-active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .step-completed {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .step-pending {
            background: #e5e7eb;
            color: #6b7280;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">
                <i class="fas fa-cogs text-blue-600 mr-3"></i>
                IATrade CRM
            </h1>
            <p class="text-xl text-gray-600">Asistente de Instalación y Despliegue</p>
        </div>

        <!-- Progress Steps -->
        <div class="flex justify-center mb-8">
            <div class="flex items-center space-x-4">
                <?php
                $steps = [
                    1 => ['icon' => 'fas fa-check-circle', 'title' => 'Requisitos'],
                    2 => ['icon' => 'fas fa-database', 'title' => 'Base de Datos'],
                    3 => ['icon' => 'fas fa-user-shield', 'title' => 'Superadmin'],
                    4 => ['icon' => 'fas fa-cog', 'title' => 'Configuración'],
                    5 => ['icon' => 'fas fa-flag-checkered', 'title' => 'Finalizar']
                ];

                foreach ($steps as $stepNum => $stepInfo):
                    $stepClass = 'step-pending';
                    if ($stepNum < $currentStep) {
                        $stepClass = 'step-completed';
                    } elseif ($stepNum == $currentStep) {
                        $stepClass = 'step-active';
                    }
                ?>
                <div class="flex flex-col items-center">
                    <div class="step-indicator <?php echo $stepClass; ?> w-12 h-12 rounded-full flex items-center justify-center text-white font-bold mb-2">
                        <?php if ($stepNum < $currentStep): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            <?php echo $stepNum; ?>
                        <?php endif; ?>
                    </div>
                    <span class="text-sm font-medium text-gray-600"><?php echo $stepInfo['title']; ?></span>
                </div>
                <?php if ($stepNum < count($steps)): ?>
                    <div class="w-16 h-1 bg-gray-300 mt-6"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <?php
                $step = $_GET['step'] ?? 1;
                $stepFile = "steps/step{$step}-";
                
                switch($step) {
                    case 1:
                        $stepFile .= 'requirements.php';
                        break;
                    case 2:
                        $stepFile .= 'database.php';
                        break;
                    case 3:
                        $stepFile .= 'superadmin.php';
                        break;
                    case 4:
                        $stepFile .= 'configuration.php';
                        break;
                    case 5:
                        $stepFile .= 'complete.php';
                        break;
                    default:
                        $stepFile = 'steps/step1-requirements.php';
                        $step = 1;
                }
                
                if (file_exists($stepFile)) {
                    include $stepFile;
                } else {
                    echo '<div class="text-center py-8">';
                    echo '<i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>';
                    echo '<h2 class="text-xl font-semibold text-gray-900 mb-2">Paso no encontrado</h2>';
                    echo '<p class="text-gray-600">El archivo del paso solicitado no existe.</p>';
                    echo '<a href="index.php" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Volver al inicio</a>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-gray-500">
                <i class="fas fa-info-circle mr-2"></i>
                ¿Necesitas ayuda? Consulta el 
                <a href="manual.php" class="text-blue-600 hover:underline" target="_blank">Manual de Instalación</a>
            </p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        // Función para mostrar/ocultar detalles de errores
        function toggleErrorDetails(id) {
            const element = document.getElementById(id);
            element.classList.toggle('hidden');
        }

        // Función para copiar comandos al portapapeles
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Mostrar notificación de éxito
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                notification.textContent = 'Comando copiado al portapapeles';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 3000);
            });
        }
    </script>
</body>
</html>