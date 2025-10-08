<?php
// Importar 500 leads directamente usando PDO
header('Content-Type: text/plain');

echo "=== IMPORTACIÓN DIRECTA DE 500 LEADS (SIMPLE) ===\n\n";

// Leer los leads generados
$leadsJson = file_get_contents('500_leads.json');
$leads = json_decode($leadsJson, true);

echo "Leídos " . count($leads) . " leads del archivo JSON\n";

$imported = 0;
$errors = 0;
$duplicates = 0;
$errorDetails = [];

try {
    // Conectar directamente a la base de datos
    $host = 'localhost';
    $dbname = 'spin2pay_profixcrm';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar total actual en la base de datos
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM leads");
    $totalInDB = $countStmt->fetch()['total'];
    echo "Total actual de leads en la base de datos: $totalInDB\n\n";
    
    echo "=== INICIANDO IMPORTACIÓN ===\n";
    
    foreach ($leads as $index => $leadData) {
        $email = $leadData['email'] ?? '';
        $address = $leadData['address'] ?? '';
        $postalCode = $leadData['postal_code'] ?? '';
        
        // Saltar si no hay email
        if (empty($email)) {
            $errors++;
            echo "- Lead " . ($index + 1) . ": Sin email, saltando...\n";
            continue;
        }
        
        echo "Procesando lead " . ($index + 1) . ": " . $leadData['first_name'] . " " . $leadData['last_name'] . " - " . $leadData['email'] . "\n";
        
        try {
            // Verificar duplicados por email
        $checkStmt = $pdo->prepare("SELECT id FROM leads WHERE email = :email");
        $checkStmt->execute([':email' => $email]);
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
            $stmt = $pdo->prepare("
                INSERT INTO leads (
                    first_name, last_name, email, phone, country, city, address, postal_code,
                    company, job_title, source, campaign, status, priority, value, notes,
                    created_at, updated_at, created_by, updated_by
                ) VALUES (
                    :first_name, :last_name, :email, :phone, :country, :city, :address, :postal_code,
                    :company, :job_title, :source, :campaign, :status, :priority, :value, :notes,
                    NOW(), NOW(), 1, 1
                )
            ");
            
            $result = $stmt->execute([
                ':first_name' => $leadData['first_name'] ?? '',
                ':last_name'  => $leadData['last_name'] ?? '',
                ':email'      => $leadData['email'] ?? '',
                ':phone'      => $leadData['phone'] ?? '',
                ':country'    => $leadData['country'] ?? '',
                ':city'       => $leadData['city'] ?? '',
                ':address'    => $leadData['address'] ?? '',
                ':postal_code'=> $leadData['postal_code'] ?? '',
                ':company'    => $leadData['company'] ?? '',
                ':job_title'  => $leadData['job_title'] ?? '',
                ':source'     => $leadData['source'] ?? 'Import',
                ':campaign'   => $leadData['campaign'] ?? '',
                ':status'     => $leadData['status'] ?? 'new',
                ':priority'   => $leadData['priority'] ?? 'medium',
                ':value'      => $leadData['value'] ?? null,
                ':notes'      => $leadData['notes'] ?? ''
            ]);
            
            if ($result) {
                $leadId = $pdo->lastInsertId();
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
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM leads");
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