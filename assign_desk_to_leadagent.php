<?php
require_once 'vendor/autoload.php';

try {
    // Conexión directa con PDO
    $pdo = new PDO('mysql:host=localhost;dbname=spin2pay_profixcrm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ASIGNANDO DESK AL USUARIO LEADAGENT ===\n";
    
    $userId = 7; // leadagent
    $deskId = 4; // Test Desk
    $assignedBy = 1; // admin
    
    // Verificar si ya está asignado
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM desk_users WHERE user_id = ? AND desk_id = ?");
    $checkStmt->execute([$userId, $deskId]);
    $exists = $checkStmt->fetchColumn() > 0;
    
    if ($exists) {
        echo "✓ El usuario ya está asignado a este desk.\n";
    } else {
        // Obtener columnas de desk_users
        $columnsStmt = $pdo->query("SHOW COLUMNS FROM desk_users");
        $columns = array_map(function($col) { return $col['Field']; }, $columnsStmt->fetchAll(PDO::FETCH_ASSOC));
        
        // Construir query dinámica
        $fields = ['desk_id', 'user_id'];
        $values = [$deskId, $userId];
        $placeholders = ['?', '?'];
        
        if (in_array('assigned_by', $columns)) {
            $fields[] = 'assigned_by';
            $values[] = $assignedBy;
            $placeholders[] = '?';
        }
        
        if (in_array('assigned_at', $columns)) {
            $fields[] = 'assigned_at';
            $values[] = date('Y-m-d H:i:s');
            $placeholders[] = '?';
        }
        
        if (in_array('is_primary', $columns)) {
            $fields[] = 'is_primary';
            $values[] = 1;
            $placeholders[] = '?';
        }
        
        $sql = "INSERT INTO desk_users (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            echo "✓ Desk asignado exitosamente al usuario leadagent.\n";
            echo "   Desk: Test Desk (ID: $deskId)\n";
            echo "   Usuario: leadagent (ID: $userId)\n";
        } else {
            echo "❌ Error al asignar el desk.\n";
        }
    }
    
    // Verificar la asignación
    echo "\n=== VERIFICANDO ASIGNACIÓN ===\n";
    $verifyStmt = $pdo->prepare("
        SELECT d.id, d.name, d.description, du.is_primary
        FROM desks d
        INNER JOIN desk_users du ON d.id = du.desk_id
        WHERE du.user_id = ? AND d.status = 'active'
    ");
    $verifyStmt->execute([$userId]);
    $assignedDesks = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($assignedDesks)) {
        echo "✓ El usuario ahora tiene acceso a:\n";
        foreach ($assignedDesks as $desk) {
            echo "   - {$desk['name']} (ID: {$desk['id']}) - Principal: " . ($desk['is_primary'] ? 'Sí' : 'No') . "\n";
        }
    } else {
        echo "⚠️ El usuario aún no tiene desks asignados.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}