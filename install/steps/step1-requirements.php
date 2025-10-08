<?php
/**
 * Paso 1: Verificación de Requisitos del Sistema
 */

$checker = new RequirementsChecker();
$checkResults = null;
$canProceed = false;

// Procesar formulario si se envió
if ($_POST && isset($_POST['check_requirements'])) {
    if ($wizard->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $checkResults = $checker->checkAll();
        $status = $checker->getOverallStatus();
        $canProceed = $status['can_proceed'];
        
        if ($canProceed) {
            $wizard->markStepCompleted(1);
            $wizard->addSuccess('Todos los requisitos críticos han sido verificados correctamente');
        } else {
            $wizard->addError('Hay requisitos críticos que no se cumplen. Debes solucionarlos antes de continuar.');
        }
        
        // Añadir errores y advertencias al wizard
        foreach ($checkResults['errors'] as $error) {
            $wizard->addError($error['message'], $error['solution'] . "\n\nComandos sugeridos:\n" . implode("\n", $error['commands'] ?? []));
        }
        
        foreach ($checkResults['warnings'] as $warning) {
            $wizard->addWarning($warning['message'], $warning['solution'] ?? '');
        }
    } else {
        $wizard->addError('Token de seguridad inválido. Recarga la página e intenta de nuevo.');
    }
}

// Procesar botón de continuar
if ($_POST && isset($_POST['continue_step']) && $wizard->isStepCompleted(1)) {
    if ($wizard->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $wizard->redirectToNextStep(1);
    }
}
?>

<div class="step-content">
    <div class="flex items-center mb-6">
        <div class="bg-blue-100 p-3 rounded-full mr-4">
            <i class="fas fa-check-circle text-blue-600 text-2xl"></i>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Verificación de Requisitos</h2>
            <p class="text-gray-600">Comprobamos que tu servidor cumple con todos los requisitos necesarios</p>
        </div>
    </div>

    <?php echo $wizard->renderMessages(); ?>

    <?php if (!$checkResults): ?>
    <!-- Información inicial -->
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-lg font-medium text-blue-800">¿Qué vamos a verificar?</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Versión de PHP:</strong> Mínimo PHP 7.4, recomendado PHP 8.1+</li>
                        <li><strong>Extensiones PHP:</strong> PDO, MySQL, cURL, JSON, OpenSSL, etc.</li>
                        <li><strong>Configuración PHP:</strong> Límites de memoria, tiempo de ejecución, subida de archivos</li>
                        <li><strong>Permisos de directorios:</strong> Escritura en carpetas críticas</li>
                        <li><strong>Base de datos:</strong> Conectividad MySQL/MariaDB</li>
                        <li><strong>Servidor web:</strong> Apache/Nginx con configuración adecuada</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Información del sistema actual -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-800 mb-2">
                <i class="fas fa-server mr-2"></i>Información del Sistema
            </h4>
            <div class="space-y-2 text-sm">
                <div><strong>PHP:</strong> <?php echo PHP_VERSION; ?></div>
                <div><strong>Sistema:</strong> <?php echo PHP_OS; ?></div>
                <div><strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido'; ?></div>
                <div><strong>Memoria PHP:</strong> <?php echo ini_get('memory_limit'); ?></div>
            </div>
        </div>
        
        <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-800 mb-2">
                <i class="fas fa-clock mr-2"></i>Configuración Actual
            </h4>
            <div class="space-y-2 text-sm">
                <div><strong>Tiempo máximo:</strong> <?php echo ini_get('max_execution_time'); ?>s</div>
                <div><strong>Subida máxima:</strong> <?php echo ini_get('upload_max_filesize'); ?></div>
                <div><strong>POST máximo:</strong> <?php echo ini_get('post_max_size'); ?></div>
                <div><strong>Variables máximas:</strong> <?php echo ini_get('max_input_vars'); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($checkResults): ?>
    <!-- Resultados de la verificación -->
    <div class="space-y-6">
        <!-- Resumen general -->
        <?php 
        $status = $checker->getOverallStatus();
        $statusClass = $status['can_proceed'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
        $statusIcon = $status['can_proceed'] ? 'fas fa-check-circle text-green-500' : 'fas fa-exclamation-circle text-red-500';
        $statusText = $status['can_proceed'] ? 'text-green-800' : 'text-red-800';
        ?>
        <div class="<?php echo $statusClass; ?> border rounded-lg p-4">
            <div class="flex items-center">
                <i class="<?php echo $statusIcon; ?> text-2xl mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold <?php echo $statusText; ?>">
                        <?php echo $status['can_proceed'] ? 'Sistema Compatible' : 'Requisitos No Cumplidos'; ?>
                    </h3>
                    <p class="<?php echo $statusText; ?>">
                        <?php if ($status['can_proceed']): ?>
                            Tu servidor cumple con todos los requisitos críticos para la instalación.
                        <?php else: ?>
                            Hay <?php echo $status['critical_errors']; ?> error(es) crítico(s) que deben solucionarse.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Detalles de requisitos -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- PHP Version -->
            <div class="bg-white border rounded-lg p-4">
                <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <i class="fab fa-php text-blue-600 mr-2"></i>
                    Versión de PHP
                </h4>
                <div class="flex items-center justify-between">
                    <span>Actual: <?php echo PHP_VERSION; ?></span>
                    <?php if (version_compare(PHP_VERSION, '7.4.0', '>=')): ?>
                        <i class="fas fa-check-circle text-green-500"></i>
                    <?php else: ?>
                        <i class="fas fa-times-circle text-red-500"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Extensions -->
            <div class="bg-white border rounded-lg p-4">
                <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <i class="fas fa-puzzle-piece text-purple-600 mr-2"></i>
                    Extensiones PHP
                </h4>
                <?php
                $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'mbstring', 'openssl'];
                $loadedCount = 0;
                foreach ($requiredExtensions as $ext) {
                    if (extension_loaded($ext)) $loadedCount++;
                }
                ?>
                <div class="flex items-center justify-between">
                    <span><?php echo $loadedCount; ?>/<?php echo count($requiredExtensions); ?> requeridas</span>
                    <?php if ($loadedCount === count($requiredExtensions)): ?>
                        <i class="fas fa-check-circle text-green-500"></i>
                    <?php else: ?>
                        <i class="fas fa-times-circle text-red-500"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Memory -->
            <div class="bg-white border rounded-lg p-4">
                <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <i class="fas fa-memory text-orange-600 mr-2"></i>
                    Memoria PHP
                </h4>
                <div class="flex items-center justify-between">
                    <span><?php echo ini_get('memory_limit'); ?></span>
                    <?php
                    $memoryLimit = ini_get('memory_limit');
                    $memoryBytes = $memoryLimit === '-1' ? PHP_INT_MAX : $checker->convertToBytes($memoryLimit);
                    $isMemoryOk = $memoryBytes >= $checker->convertToBytes('256M');
                    ?>
                    <?php if ($isMemoryOk): ?>
                        <i class="fas fa-check-circle text-green-500"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Database -->
            <div class="bg-white border rounded-lg p-4">
                <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <i class="fas fa-database text-green-600 mr-2"></i>
                    Base de Datos
                </h4>
                <div class="flex items-center justify-between">
                    <span>MySQL/MariaDB</span>
                    <?php if (extension_loaded('pdo_mysql') || extension_loaded('mysqli')): ?>
                        <i class="fas fa-check-circle text-green-500"></i>
                    <?php else: ?>
                        <i class="fas fa-times-circle text-red-500"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Lista detallada de errores -->
        <?php if (!empty($checkResults['errors'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <h4 class="font-semibold text-red-800 mb-3 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                Errores que deben solucionarse
            </h4>
            <div class="space-y-3">
                <?php foreach ($checkResults['errors'] as $index => $error): ?>
                <div class="bg-white border border-red-200 rounded p-3">
                    <div class="flex items-start">
                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                        <div class="flex-1">
                            <p class="font-medium text-red-800"><?php echo htmlspecialchars($error['message']); ?></p>
                            <p class="text-sm text-red-600 mt-1"><?php echo htmlspecialchars($error['solution']); ?></p>
                            <?php if (!empty($error['commands'])): ?>
                            <div class="mt-2">
                                <button type="button" 
                                        class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200"
                                        onclick="toggleErrorDetails('commands-<?php echo $index; ?>')">
                                    <i class="fas fa-terminal mr-1"></i>Ver comandos
                                </button>
                                <div id="commands-<?php echo $index; ?>" class="hidden mt-2 bg-gray-900 text-green-400 p-3 rounded text-xs font-mono">
                                    <div class="flex justify-between items-center mb-2">
                                        <span>Comandos sugeridos:</span>
                                        <button type="button" 
                                                class="text-gray-400 hover:text-white"
                                                onclick="copyToClipboard('<?php echo addslashes(implode('\n', $error['commands'])); ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <pre><?php echo htmlspecialchars(implode("\n", $error['commands'])); ?></pre>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lista de advertencias -->
        <?php if (!empty($checkResults['warnings'])): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h4 class="font-semibold text-yellow-800 mb-3 flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Advertencias (recomendaciones)
            </h4>
            <div class="space-y-2">
                <?php foreach ($checkResults['warnings'] as $warning): ?>
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-2"></i>
                    <div>
                        <p class="text-yellow-800"><?php echo htmlspecialchars($warning['message']); ?></p>
                        <?php if (!empty($warning['solution'])): ?>
                        <p class="text-sm text-yellow-600"><?php echo htmlspecialchars($warning['solution']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Botones de acción -->
    <div class="flex justify-between items-center mt-8 pt-6 border-t">
        <div>
            <?php if ($checkResults && !$canProceed): ?>
            <p class="text-sm text-red-600">
                <i class="fas fa-info-circle mr-1"></i>
                Soluciona los errores críticos y vuelve a verificar los requisitos
            </p>
            <?php endif; ?>
        </div>
        
        <div class="flex space-x-4">
            <?php if (!$checkResults || !$canProceed): ?>
            <form method="POST" class="inline">
                <input type="hidden" name="csrf_token" value="<?php echo $wizard->generateCSRFToken(); ?>">
                <button type="submit" name="check_requirements" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                    <i class="fas fa-search mr-2"></i>
                    <?php echo $checkResults ? 'Verificar de Nuevo' : 'Verificar Requisitos'; ?>
                </button>
            </form>
            <?php endif; ?>
            
            <?php if ($wizard->isStepCompleted(1)): ?>
            <form method="POST" class="inline">
                <input type="hidden" name="csrf_token" value="<?php echo $wizard->generateCSRFToken(); ?>">
                <button type="submit" name="continue_step"
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                    Continuar
                    <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>