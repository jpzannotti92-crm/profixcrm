<?php
require_once __DIR__ . '/platform_check_bypass.php';
require_once __DIR__ . '/vendor/autoload.php';

use IaTradeCRM\Models\Lead;

echo "=== DIAGNÓSTICO DE IMPORTACIÓN DE LEADS ===\n\n";

// Verificar conexión a base de datos
try {
    $lead = new Lead();
    echo "✓ Conexión a base de datos establecida\n";
} catch (Exception $e) {
    echo "✗ Error de conexión: " . $e->getMessage() . "\n";
    exit;
}

// Verificar tabla de leads
try {
    $sql = "SELECT COUNT(*) as total FROM leads";
    $db = Lead::getConnection();
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $total = $stmt->fetchColumn();
    echo "✓ Total de leads en la base de datos: " . $total . "\n";
} catch (Exception $e) {
    echo "✗ Error al contar leads: " . $e->getMessage() . "\n";
}

// Verificar estructura de la tabla
echo "\n=== ESTRUCTURA DE LA TABLA LEADS ===\n";
try {
    $sql = "DESCRIBE leads";
    $db = Lead::getConnection();
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        echo "Campo: {$field['Field']} - Tipo: {$field['Type']} - Null: {$field['Null']} - Key: {$field['Key']}\n";
    }
} catch (Exception $e) {
    echo "✗ Error al obtener estructura: " . $e->getMessage() . "\n";
}

// Probar findByEmail
echo "\n=== PRUEBA DE findByEmail ===\n";
$testEmail = 'test@example.com';
$existingLead = Lead::findByEmail($testEmail);
if ($existingLead) {
    echo "✓ Lead encontrado con email: $testEmail\n";
    echo "  ID: " . $existingLead->id . "\n";
    echo "  Nombre: " . $existingLead->first_name . " " . $existingLead->last_name . "\n";
} else {
    echo "✓ No existe lead con email: $testEmail (esto es normal)\n";
}

// Probar crear un lead de prueba
echo "\n=== PRUEBA DE CREACIÓN DE LEAD ===\n";
try {
    $testLeadData = [
        'first_name' => 'Test',
        'last_name' => 'Import',
        'email' => 'test_import@example.com',
        'phone' => '+1234567890',
        'country' => 'US',
        'source' => 'test_import',
        'status' => 'new',
        'created_by' => 1,
        'updated_by' => 1
    ];
    
    $newLead = new Lead($testLeadData);
    if ($newLead->save()) {
        echo "✓ Lead de prueba creado exitosamente\n";
        echo "  ID: " . $newLead->id . "\n";
        
        // Verificar que se guardó correctamente
        $savedLead = Lead::find($newLead->id);
        if ($savedLead) {
            echo "✓ Lead verificado en base de datos\n";
            echo "  Email: " . $savedLead->email . "\n";
        } else {
            echo "✗ Lead no encontrado después de guardar\n";
        }
        
        // Limpiar - eliminar lead de prueba
        Lead::delete($newLead->id);
        echo "✓ Lead de prueba eliminado\n";
        
    } else {
        echo "✗ Error al guardar lead de prueba\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error al crear lead de prueba: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";