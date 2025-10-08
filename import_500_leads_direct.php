<?php
// Importar 500 leads directamente a la base de datos
require_once __DIR__ . '/config/config.php';

use iaTradeCRM\Database\Connection;

header('Content-Type: text/plain');

echo "=== IMPORTACIÓN DIRECTA DE 500 LEADS ===\n\n";

// Leer los leads generados
$leadsJson = file_get_contents('500_leads.json');
$leads = json_decode($leadsJson, true);

echo "Leídos " . count($leads) . " leads del archivo JSON\n";

$imported = 0;
$errors = 0;
$duplicates = 0;
$errorDetails = [];

try {
    $db = Connection::getInstance()->getConnection();
    
    // Verificar total actual en la base de datos
    $countStmt = $db->query("SELECT COUNT(*) as total FROM leads");
    $totalInDB = $countStmt->fetch()['total'];
    echo "Total actual de leads en la base de datos: $totalInDB\n\n";
    
    echo "=== INICIANDO IMPORTACIÓN ===\n";
    
    foreach ($leads as $index => $leadData) {
        echo "Procesando lead " . ($index + 1) . ": " . $leadData['first_name'] . " " . $leadData['last_name'] . " - " . $leadData['email'] . "\n";
        
        try {
            // Verificar duplicados por email
            $checkStmt = $db->prepare("SELECT id FROM leads WHERE email = ?");
            $checkStmt->execute([$leadData['email']]);
            if ($checkStmt->fetch()) {
                $duplicates++;
                echo "  ❌ Lead duplicado encontrado\n";
                continue;
            }
            
            // Validar campos requeridos
            if (empty($leadData['first_name']) || empty($leadData['last_name'])) {
                $errors++;
                $errorDetails[] = "Lead " . ($index + 1) . ": Nombre y apellido son requeridos";
                echo "  ❌ Lead sin nombre/apellido\n";
                continue;
            }
            
            // Insertar lead con created_by y updated_by
            $stmt = $db->prepare("
                INSERT INTO leads (
                    first_name, last_name, email, phone, country, city, 
                    company, job_title, source, campaign, status, priority, 
                    value, notes, assigned_to, desk_id, created_by, updated_by, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )
            ");
            
            $result = $stmt->execute([
                $leadData['first_name'] ?? '',
                $leadData['last_name'] ?? '',
                $leadData['email'] ?? '',
                $leadData['phone'] ?? '',
                $leadData['country'] ?? '',
                $leadData['city'] ?? '',
                $leadData['company'] ?? '',
                $leadData['job_title'] ?? '',
                $leadData['source'] ?? 'Import',
                $leadData['campaign'] ?? '',
                $leadData['status'] ?? 'new',
                $leadData['priority'] ?? 'medium',
                $leadData['value'] ?? null,
                $leadData['notes'] ?? '',
                null, // assigned_to
                null,  // desk_id
                1,     // created_by (admin)
                1      // updated_by (admin)
            ]);
            
            if ($result) {
                $leadId = $db->lastInsertId();
                $imported++;
                echo "  ✅ Lead importado exitosamente con ID: $leadId\n";
            } else {
                $errors++;
                $errorInfo = $stmt->errorInfo();
                $errorDetails[] = "Lead " . ($index + 1) . ": " . $errorInfo[2];
                echo "  ❌ Error SQL: " . $errorInfo[2] . "\n";
            }
            
        } catch (Exception $e) {
            $errors++;
            $errorDetails[] = "Lead " . ($index + 1) . ": " . $e->getMessage();
            echo "  ❌ Excepción: " . $e->getMessage() . "\n";
        }
    }
    
    // Verificar total final en la base de datos
    $countStmt = $db->query("SELECT COUNT(*) as total FROM leads");
    $finalTotalInDB = $countStmt->fetch()['total'];
    
    echo "\n=== RESUMEN DE IMPORTACIÓN ===\n";
    echo "Importados: $imported\n";
    echo "Errores: $errors\n";
    echo "Duplicados: $duplicates\n";
    echo "Total procesados: " . count($leads) . "\n";
    echo "Total inicial en BD: $totalInDB\n";
    echo "Total final en BD: $finalTotalInDB\n";
    
    if (!empty($errorDetails)) {
        echo "\nDetalles de errores:\n";
        foreach ($errorDetails as $error) {
            echo "- $error\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE IMPORTACIÓN ===\n";
?>