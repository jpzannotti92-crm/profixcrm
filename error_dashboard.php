<?php
/**
 * Dashboard de Errores - iaTrade CRM
 * Interfaz web para visualizar y analizar errores HTTP 500
 */

require_once 'enhanced_error_logger.php';

// Inicializar logger
$logger = initializeErrorLogger();

// Obtener parámetros
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
$action = $_GET['action'] ?? '';

// Procesar acciones
if ($action === 'clear_logs' && $_POST['confirm'] === 'yes') {
    $cleared = $logger->clearLogs();
    $message = "Se eliminaron $cleared archivos de log.";
}

// Obtener estadísticas
$stats = $logger->getErrorStats($days);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Errores - iaTrade CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .error-type-badge { font-size: 0.8em; }
        .recent-error { border-left: 4px solid #dc3545; padding: 10px; margin: 5px 0; background: #f8f9fa; }
        .chart-container { position: relative; height: 300px; }
        .log-entry { font-family: monospace; font-size: 0.9em; background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 5px 0; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="card-title mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Dashboard de Errores
                                </h1>
                                <p class="mb-0 mt-2">Análisis de errores HTTP 500 - Últimos <?php echo $days; ?> días</p>
                            </div>
                            <div class="btn-group">
                                <a href="?days=1" class="btn btn-outline-light <?php echo $days === 1 ? 'active' : ''; ?>">1 día</a>
                                <a href="?days=7" class="btn btn-outline-light <?php echo $days === 7 ? 'active' : ''; ?>">7 días</a>
                                <a href="?days=30" class="btn btn-outline-light <?php echo $days === 30 ? 'active' : ''; ?>">30 días</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas Generales -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="display-4 text-danger mb-2">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="text-danger"><?php echo number_format($stats['total_errors']); ?></h3>
                        <p class="text-muted mb-0">Total de Errores</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="display-4 text-warning mb-2">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h3 class="text-warning"><?php echo count($stats['daily_counts']); ?></h3>
                        <p class="text-muted mb-0">Días con Errores</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="display-4 text-info mb-2">
                            <i class="fas fa-file-code"></i>
                        </div>
                        <h3 class="text-info"><?php echo count($stats['top_files']); ?></h3>
                        <p class="text-muted mb-0">Archivos Afectados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="display-4 text-success mb-2">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="text-success">
                            <?php 
                            $dailyAvg = $stats['total_errors'] > 0 && count($stats['daily_counts']) > 0 
                                ? round($stats['total_errors'] / count($stats['daily_counts']), 1) 
                                : 0;
                            echo $dailyAvg;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Promedio Diario</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Gráfico de Errores por Día -->
            <div class="col-md-8">
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>Errores por Día</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="dailyErrorsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tipos de Error -->
            <div class="col-md-4">
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-pie-chart me-2"></i>Tipos de Error</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats['error_types'])): ?>
                            <?php foreach ($stats['error_types'] as $type => $count): ?>
                                <?php 
                                $percentage = round(($count / $stats['total_errors']) * 100, 1);
                                $badgeClass = match($type) {
                                    'HTTP_500_ERROR' => 'bg-danger',
                                    'FATAL_ERROR' => 'bg-dark',
                                    'UNCAUGHT_EXCEPTION' => 'bg-warning',
                                    'DATABASE_ERROR' => 'bg-info',
                                    'API_ERROR' => 'bg-secondary',
                                    default => 'bg-primary'
                                };
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge <?php echo $badgeClass; ?> error-type-badge">
                                        <?php echo htmlspecialchars($type); ?>
                                    </span>
                                    <div>
                                        <span class="fw-bold"><?php echo $count; ?></span>
                                        <small class="text-muted">(<?php echo $percentage; ?>%)</small>
                                    </div>
                                </div>
                                <div class="progress mb-3" style="height: 6px;">
                                    <div class="progress-bar <?php echo str_replace('bg-', 'bg-', $badgeClass); ?>" 
                                         style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No hay errores en el período seleccionado</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Archivos con Más Errores -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-file-alt me-2"></i>Archivos con Más Errores</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats['top_files'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Archivo</th>
                                            <th class="text-end">Errores</th>
                                            <th class="text-end">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $topFiles = array_slice($stats['top_files'], 0, 10, true);
                                        foreach ($topFiles as $file => $count): 
                                            $percentage = round(($count / $stats['total_errors']) * 100, 1);
                                            $fileName = basename($file);
                                        ?>
                                            <tr>
                                                <td>
                                                    <code title="<?php echo htmlspecialchars($file); ?>">
                                                        <?php echo htmlspecialchars($fileName); ?>
                                                    </code>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-danger"><?php echo $count; ?></span>
                                                </td>
                                                <td class="text-end">
                                                    <small class="text-muted"><?php echo $percentage; ?>%</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No hay datos de archivos</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Errores Recientes -->
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-clock me-2"></i>Errores Recientes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats['recent_errors'])): ?>
                            <?php foreach ($stats['recent_errors'] as $error): ?>
                                <div class="recent-error">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <span class="badge bg-danger"><?php echo htmlspecialchars($error['type']); ?></span>
                                        <small class="text-muted"><?php echo htmlspecialchars($error['timestamp']); ?></small>
                                    </div>
                                    <div class="mb-1">
                                        <strong><?php echo htmlspecialchars(substr($error['message'], 0, 100)); ?></strong>
                                        <?php if (strlen($error['message']) > 100): ?>...<?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-file me-1"></i>
                                        <?php echo htmlspecialchars(basename($error['file'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No hay errores recientes</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h5><i class="fas fa-tools me-2"></i>Acciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="godaddy_config_validator.php" class="btn btn-primary btn-lg w-100 mb-2">
                                    <i class="fas fa-cogs me-2"></i>Validar Configuración
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="check_production_permissions.php" class="btn btn-info btn-lg w-100 mb-2">
                                    <i class="fas fa-shield-alt me-2"></i>Verificar Permisos
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="debug_production_errors.php" class="btn btn-warning btn-lg w-100 mb-2">
                                    <i class="fas fa-bug me-2"></i>Diagnóstico Completo
                                </a>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-danger btn-lg w-100 mb-2" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                                    <i class="fas fa-trash me-2"></i>Limpiar Logs
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Información del Sistema de Logging</h6>
                                    <ul class="mb-0">
                                        <li>Los logs se almacenan en el directorio <code>logs/</code></li>
                                        <li>Cada archivo de log tiene un tamaño máximo de 10MB</li>
                                        <li>Se mantienen máximo 10 archivos de log</li>
                                        <li>Los errores críticos también se registran en el log del sistema</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Limpiar Logs -->
    <div class="modal fade" id="clearLogsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Limpieza de Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que quieres eliminar todos los archivos de log?</p>
                    <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="post" action="?action=clear_logs" class="d-inline">
                        <input type="hidden" name="confirm" value="yes">
                        <button type="submit" class="btn btn-danger">Eliminar Logs</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gráfico de errores por día
        const dailyData = <?php echo json_encode($stats['daily_counts']); ?>;
        const labels = Object.keys(dailyData).reverse();
        const data = Object.values(dailyData).reverse();

        const ctx = document.getElementById('dailyErrorsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Errores por Día',
                    data: data,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Auto-refresh cada 5 minutos
        setTimeout(() => {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>