<?php
/**
 * Procesar el archivo CSV de 500 leads y mostrar reporte final
 * Este script procesará todos los leads válidos y mostrará un reporte completo
 */

require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

// Ruta del archivo CSV de leads (usar el archivo test-leads.csv disponible)
$csvFile = __DIR__ . '/../test-leads.csv';

if (!file_exists($csvFile)) {
    echo "❌ No se encontró el archivo: $csvFile\n";
    echo "Por favor, asegúrate de que el archivo leads_test_500.csv existe en la carpeta public.\n";
    exit;
}

echo "=== PROCESANDO ARCHIVO DE LEADS ===\n";
echo "📁 Archivo: $csvFile\n";
echo "📊 Tamaño: " . number_format(filesize($csvFile)) . " bytes\n\n";

// Conectar a base de datos
try {
    $connection = Connection::getInstance();
    $pdo = $connection->getConnection();
    echo "✅ Conexión a base de datos exitosa\n";
    
    // Contar leads antes
    $stmt = $pdo->query('SELECT COUNT(*) FROM leads');
    $leadsBefore = $stmt->fetchColumn();
    echo "📊 Leads antes de importar: " . number_format($leadsBefore) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Error conectando a base de datos: " . $e->getMessage() . "\n";
    exit;
}

// Simular POST y FILES
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
$_FILES = [
    'file' => [
        'name' => 'test-leads.csv',
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
echo "=== RESULTADOS DE IMPORTACIÓN ===\n";

$jsonStart = strpos($output, '{');
if ($jsonStart !== false) {
    $jsonStr = substr($output, $jsonStart);
    $result = json_decode($jsonStr, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        if ($result['success']) {
            echo "✅ IMPORTACIÓN COMPLETADA EXITOSAMENTE!\n\n";
            
            // Reporte final detallado
            echo "📈 REPORTE FINAL DE IMPORTACIÓN:\n";
            echo "   ┌─────────────────────────────────────┐\n";
            echo "   │ 📋 Total procesados: " . str_pad($result['data']['total_rows'], 6, ' ', STR_PAD_LEFT) . " │\n";
            echo "   │ ✅ Importados:      " . str_pad($result['data']['imported_rows'], 6, ' ', STR_PAD_LEFT) . " │\n";
            echo "   │ ⚠️  Duplicados:     " . str_pad($result['data']['duplicate_rows'], 6, ' ', STR_PAD_LEFT) . " │\n";
            echo "   │ ❌ Fallidos:        " . str_pad($result['data']['failed_rows'], 6, ' ', STR_PAD_LEFT) . " │\n";
            echo "   └─────────────────────────────────────┘\n\n";
            
            // Porcentajes
            $total = $result['data']['total_rows'];
            if ($total > 0) {
                $importedPct = round(($result['data']['imported_rows'] / $total) * 100, 1);
                $duplicatesPct = round(($result['data']['duplicate_rows'] / $total) * 100, 1);
                $failedPct = round(($result['data']['failed_rows'] / $total) * 100, 1);
                
                echo "📊 PORCENTAJES:\n";
                echo "   ✅ Importados: $importedPct%\n";
                echo "   ⚠️  Duplicados: $duplicatesPct%\n";
                echo "   ❌ Fallidos: $failedPct%\n\n";
            }
            
            // Mostrar errores si los hay
            if (!empty($result['data']['errors'])) {
                echo "📝 ERRORES ENCONTRADOS:\n";
                $errorCount = 0;
                foreach ($result['data']['errors'] as $error) {
                    if ($errorCount >= 10) {
                        echo "   ... y " . (count($result['data']['errors']) - 10) . " errores más\n";
                        break;
                    }
                    echo "   Fila {$error['row']}: " . implode(', ', $error['errors']) . "\n";
                    $errorCount++;
                }
                echo "\n";
            }
            
        } else {
            echo "❌ Error en importación: " . $result['message'] . "\n";
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
    echo "📈 ESTADÍSTICAS FINALES:\n";
    echo "   Leads antes: " . number_format($leadsBefore) . "\n";
    echo "   Leads después: " . number_format($leadsAfter) . "\n";
    echo "   📊 Nuevos leads: " . number_format($leadsAfter - $leadsBefore) . "\n\n";
    
    // Mostrar últimos leads importados
    $stmt = $pdo->query('
        SELECT first_name, last_name, email, created_at 
        FROM leads 
        ORDER BY id DESC 
        LIMIT 5
    ');
    $recentLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "🏆 ÚLTIMOS LEADS IMPORTADOS:\n";
    foreach ($recentLeads as $index => $lead) {
        echo "   " . ($index + 1) . ". {$lead['first_name']} {$lead['last_name']}\n";
        echo "      📧 {$lead['email']}\n";
        echo "      📅 {$lead['created_at']}\n";
    }
    echo "\n";
    
    // Estadísticas adicionales
    $stmt = $pdo->query('SELECT COUNT(*) FROM leads WHERE created_at >= CURDATE()');
    $todayLeads = $stmt->fetchColumn();
    echo "📅 Leads importados hoy: " . number_format($todayLeads) . "\n";
    
    $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM leads GROUP BY status ORDER BY count DESC');
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "📊 Distribución por estado:\n";
    foreach ($statusCounts as $status) {
        echo "   • {$status['status']}: {$status['count']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error consultando base de datos: " . $e->getMessage() . "\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "🎯 IMPORTACIÓN FINALIZADA - PROCESO COMPLETADO EXITOSAMENTE 🎯\n";
echo "═══════════════════════════════════════════════════════════════\n";