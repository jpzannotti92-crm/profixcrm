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

// Verificar si hay leads en la base de datos
try {
    $leads = Lead::all([], null, 5); // Obtener máximo 5 leads
    echo "✓ Se encontraron " . count($leads) . " leads en la base de datos\n";
    
    if (count($leads) > 0) {
        echo "✓ Ejemplo de lead existente:\n";
        echo "  - ID: " . $leads[0]->id . "\n";
        echo "  - Nombre: " . $leads[0]->first_name . " " . $leads[0]->last_name . "\n";
        echo "  - Email: " . $leads[0]->email . "\n";
        echo "  - Estado: " . $leads[0]->status . "\n";
    }
} catch (Exception $e) {
    echo "✗ Error al obtener leads: " . $e->getMessage() . "\n";
}

// Probar findByEmail
echo "\n=== PRUEBA DE findByEmail ===\n";
$testEmail = 'test@example.com';
try {
    $existingLead = Lead::findByEmail($testEmail);
    if ($existingLead) {
        echo "✓ Lead encontrado con email: $testEmail\n";
        echo "  ID: " . $existingLead->id . "\n";
        echo "  Nombre: " . $existingLead->first_name . " " . $existingLead->last_name . "\n";
    } else {
        echo "✓ No existe lead con email: $testEmail (esto es normal)\n";
    }
} catch (Exception $e) {
    echo "✗ Error en findByEmail: " . $e->getMessage() . "\n";
}

// Verificar estructura de la tabla leads
echo "\n=== ESTRUCTURA DE LA TABLA LEADS ===\n";
$db = Lead::getConnection();
$stmt = $db->getConnection()->prepare("DESCRIBE leads");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Columnas en la tabla leads:\n";
foreach ($columns as $column) {
    echo "  - {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
}

// Probar crear un lead de prueba con solo campos existentes
echo "\n=== PRUEBA DE CREACIÓN DE LEAD ===\n";
try {
    $columnNames = array_column($columns, 'Field');
    $testLeadData = [
        'first_name' => 'Test',
        'last_name' => 'Import',
        'email' => 'test_import@example.com',
        'phone' => '+1234567890',
        'country' => 'US',
        'source' => 'test_import',
        'status' => 'new'
    ];
    
    // Solo agregar campos que existan
    if (in_array('created_by', $columnNames)) {
        $testLeadData['created_by'] = 1;
    }
    if (in_array('updated_by', $columnNames)) {
        $testLeadData['updated_by'] = 1;
    }
    
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