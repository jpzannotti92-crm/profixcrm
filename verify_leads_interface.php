<?php

echo "=== VERIFICACIÓN DE LEADS EN LA INTERFAZ WEB ===\n\n";

try {
    // Conexión directa a MySQL
    $host = 'localhost';
    $dbname = 'spin2pay_profixcrm';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener leads recientes
    $stmt = $pdo->prepare("
        SELECT l.id, l.first_name, l.last_name, l.email, l.status, l.created_at, l.created_by,
               u.username as creator_username
        FROM leads l
        LEFT JOIN users u ON l.created_by = u.id
        WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY l.id DESC
    ");
    $stmt->execute();
    $recentLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Leads creados en la última hora: " . count($recentLeads) . "\n\n";
    
    foreach ($recentLeads as $lead) {
        echo "ID: {$lead['id']}\n";
        echo "Nombre: {$lead['first_name']} {$lead['last_name']}\n";
        echo "Email: {$lead['email']}\n";
        echo "Estado: {$lead['status']}\n";
        echo "Creado por: {$lead['creator_username']} (ID: {$lead['created_by']})\n";
        echo "Fecha de creación: {$lead['created_at']}\n";
        echo "---\n";
    }
    
    // Verificar si hay algún problema con los leads
    echo "\n=== VERIFICACIÓN DE POSIBLES PROBLEMAS ===\n";
    
    // Leads sin created_by
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM leads WHERE created_by IS NULL OR created_by = 0");
    $stmt->execute();
    $nullCreatedBy = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Leads sin created_by: $nullCreatedBy\n";
    
    // Leads sin updated_by
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM leads WHERE updated_by IS NULL OR updated_by = 0");
    $stmt->execute();
    $nullUpdatedBy = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Leads sin updated_by: $nullUpdatedBy\n";
    
    // Verificar usuarios existentes
    $stmt = $pdo->prepare("SELECT id, username, email FROM users LIMIT 5");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== USUARIOS DISPONIBLES ===\n";
    foreach ($users as $user) {
        echo "ID: {$user['id']} - Username: {$user['username']} - Email: {$user['email']}\n";
    }
    
    // Verificar si hay leads asignados a usuarios que no existen
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM leads l 
        LEFT JOIN users u ON l.created_by = u.id 
        WHERE l.created_by IS NOT NULL AND l.created_by != 0 AND u.id IS NULL
    ");
    $stmt->execute();
    $invalidCreatedBy = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "\nLeads con created_by inválido: $invalidCreatedBy\n";
    
    // Verificar últimos 10 leads con información completa
    echo "\n=== ÚLTIMOS 10 LEADS ===\n";
    $stmt = $pdo->prepare("
        SELECT l.id, l.first_name, l.last_name, l.email, l.status, l.created_at, l.created_by,
               u.username as creator_username
        FROM leads l
        LEFT JOIN users u ON l.created_by = u.id
        ORDER BY l.id DESC
        LIMIT 10
    ");
    $stmt->execute();
    $latestLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($latestLeads as $lead) {
        echo "ID: {$lead['id']} | {$lead['first_name']} {$lead['last_name']} | {$lead['email']} | ";
        echo "Status: {$lead['status']} | Creado por: {$lead['creator_username']} ({$lead['created_by']})\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN ===\n";