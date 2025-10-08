<?php
require_once 'vendor/autoload.php';

try {
    // Conexión directa con PDO
    $pdo = new PDO('mysql:host=localhost;dbname=spin2pay_profixcrm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DESKS DEL USUARIO LEADAGENT (ID: 7) ===\n";
    
    // Verificar desks asignados
    $stmt = $pdo->prepare("
        SELECT d.id, d.name, d.description, d.status, du.is_primary
        FROM desks d
        INNER JOIN desk_users du ON d.id = du.desk_id
        WHERE du.user_id = ? AND d.status = 'active'
    ");
    $stmt->execute([7]);
    $desks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($desks)) {
        echo "⚠️ El usuario leadagent no tiene ningún desk asignado.\n";
        echo "\n=== DESKS DISPONIBLES ===\n";
        
        // Mostrar todos los desks disponibles
        $allDesks = $pdo->query("SELECT id, name, description, status FROM desks WHERE status = 'active' ORDER BY id");
        $availableDesks = $allDesks->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($availableDesks)) {
            foreach ($availableDesks as $desk) {
                echo "ID: {$desk['id']} - {$desk['name']} ({$desk['description']})\n";
            }
        } else {
            echo "No hay desks disponibles en el sistema.\n";
        }
    } else {
        echo "✓ El usuario leadagent tiene acceso a los siguientes desks:\n";
        foreach ($desks as $desk) {
            echo "ID: {$desk['id']} - {$desk['name']} ({$desk['description']}) - Principal: " . ($desk['is_primary'] ? 'Sí' : 'No') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}