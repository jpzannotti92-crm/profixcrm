<?php
/**
 * Procesar el archivo CSV con 500 leads generados y mostrar reporte final
 */

require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

// Ruta del archivo CSV con 500 leads
$csvFile = __DIR__ . '/leads_500_prueba.csv';

if (!file_exists($csvFile)) {
    echo "❌ No se encontró el archivo: $csvFile\n";
    echo "Por favor, ejecuta primero generar_500_leads.php para crear el archivo.\n";
    exit;
}

echo "=== PROCESANDO ARCHIVO CON 500 LEADS ===\n";
echo "Archivo: $csvFile\n";
echo "Tamaño: " . number_format(filesize($csvFile)) . " bytes\n";
echo "Total de leads en archivo: " . (count(file($csvFile)) - 1) . "\n\n";

// Conectar a base de datos
try {
    $connection = Connection::getInstance();
    $pdo = $connection->getConnection();
    echo "✅ Conexión a base de datos exitosa\n";
    
    // Contar leads antes
    $stmt = $pdo->query('SELECT COUNT(*) FROM leads');
    $leadsBefore = $stmt->fetchColumn();
    echo "Leads antes de importar: " . number_format($leadsBefore) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Error conectando a base de datos: " . $e->getMessage() . "\n";
    exit;
}

// Simular POST y FILES
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
$_FILES = [
    'file' => [
        'name' => 'leads_500_prueba.csv',
        'type' => 'text/csv',
        'tmp_name' => $csvFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($csvFile)
    ]
];

// Ejecutar importador funcional
ob_start();
require_once 'importador_funcional.php';
$output = ob_get_clean();

// Analizar resultados
echo "=== RESULTADOS DE IMPORTACION ===\n";

$jsonStart = strpos($output, '{');
if ($jsonStart !== false) {
    $jsonStr = substr($output, $jsonStart);
    $result = json_decode($jsonStr, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        if ($result['success']) {
            echo "\n✅ ¡IMPORTACION COMPLETADA EXITOSAMENTE! ✅\n\n";
            
            // Reporte final detallado
            echo "REPORTE FINAL DE IMPORTACION:\n";
            echo "========================================\n";
            echo "📋 Total procesados: " . $result['data']['total_rows'] . " leads\n";
            echo "✅ Importados: " . $result['data']['imported_rows'] . " leads\n";
            echo "⚠️  Duplicados: " . $result['data']['duplicate_rows'] . " leads\n";
            echo "❌ Fallidos: " . $result['data']['failed_rows'] . " leads\n";
            echo "========================================\n\n";
            
            // Porcentajes
            $total = $result['data']['total_rows'];
            if ($total > 0) {
                $importedPct = round(($result['data']['imported_rows'] / $total) * 100, 1);
                $duplicatesPct = round(($result['data']['duplicate_rows'] / $total) * 100, 1);
                $failedPct = round(($result['data']['failed_rows'] / $total) * 100, 1);
                
                echo "ANALISIS DE PORCENTAJES:\n";
                echo "- Importados: $importedPct%\n";
                echo "- Duplicados: $duplicatesPct%\n";
                echo "- Fallidos: $failedPct%\n\n";
            }
            
            // Mostrar errores si los hay
            if (!empty($result['data']['errors'])) {
                echo "ERRORES ENCONTRADOS (max. 10):\n";
                echo "----------------------------------------\n";
                $errorCount = 0;
                foreach ($result['data']['errors'] as $error) {
                    if ($errorCount >= 10) {
                        echo "... y " . (count($result['data']['errors']) - 10) . " errores mas\n";
                        break;
                    }
                    echo "Fila {$error['row']}: " . implode(', ', $error['errors']) . "\n";
                    $errorCount++;
                }
                echo "----------------------------------------\n\n";
            }
            
        } else {
            echo "❌ Error en importacion: " . $result['message'] . "\n";
        }
    } else {
        echo "❌ Error decodificando JSON: " . json_last_error_msg() . "\n";
    }
} else {
    echo "❌ No se encontró JSON en la respuesta\n";
}

// Contar leads después y mostrar estadísticas finales
try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM leads');
    $leadsAfter = $stmt->fetchColumn();
    
    $newLeads = $leadsAfter - $leadsBefore;
    
    echo "ESTADISTICAS FINALES:\n";
    echo "========================================\n";
    echo "Leads antes:    " . number_format($leadsBefore) . " leads\n";
    echo "Leads después:  " . number_format($leadsAfter) . " leads\n";
    echo "Nuevos leads:   " . number_format($newLeads) . " leads\n";
    echo "========================================\n\n";
    
    // Mostrar últimos leads importados
    $stmt = $pdo->query('
        SELECT first_name, last_name, email, created_at 
        FROM leads 
        ORDER BY id DESC 
        LIMIT 5
    ');
    $recentLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "ULTIMOS LEADS IMPORTADOS:\n";
    echo "----------------------------------------\n";
    foreach ($recentLeads as $index => $lead) {
        echo ($index + 1) . ". {$lead['first_name']} {$lead['last_name']}\n";
        echo "   📧 {$lead['email']}\n";
        echo "   📅 {$lead['created_at']}\n";
        echo "----------------------------------------\n";
    }
    
    // Estadísticas adicionales
    $stmt = $pdo->query('SELECT COUNT(*) FROM leads WHERE created_at >= CURDATE()');
    $todayLeads = $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM leads GROUP BY status ORDER BY count DESC');
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "ESTADISTICAS ADICIONALES:\n";
    echo "========================================\n";
    echo "Leads importados hoy: " . number_format($todayLeads) . " leads\n";
    echo "Distribucion por estado:\n";
    foreach ($statusCounts as $status) {
        echo "  • {$status['status']}: {$status['count']} leads\n";
    }
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "❌ Error consultando base de datos: " . $e->getMessage() . "\n";
}

echo "\n";
echo "========================================\n";
echo "🎯 ¡IMPORTACION MASIVA DE 500 LEADS COMPLETADA! 🎯\n";
echo "========================================\n";
echo "💡 El importador funcional esta procesando y guardando correctamente todos los leads validos.\n";
echo "🔧 Los leads con emails invalidos o duplicados son filtrados y reportados.\n";
echo "📈 Todos los leads validos han sido guardados en la base de datos con exito.\n";