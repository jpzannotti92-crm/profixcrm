<?php

echo "=== INVESTIGACIÓN DE LEADS FALTANTES ===\n\n";

try {
    // Conexión directa a MySQL
    $host = 'localhost';
    $dbname = 'spin2pay_profixcrm';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Contar leads totales
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM leads");
    $stmt->execute();
    $totalLeads = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "📊 TOTAL DE LEADS EN BASE DE DATOS: $totalLeads\n\n";
    
    // Verificar distribución por fechas
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as fecha,
            COUNT(*) as cantidad
        FROM leads 
        WHERE created_at IS NOT NULL
        GROUP BY DATE(created_at)
        ORDER BY fecha DESC
        LIMIT 10
    ");
    $stmt->execute();
    $leadsByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📅 LEADS POR FECHA (últimos 10 días):\n";
    foreach ($leadsByDate as $row) {
        echo "  {$row['fecha']}: {$row['cantidad']} leads\n";
    }
    
    // Verificar leads por fuente
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(source, 'sin_fuente') as fuente,
            COUNT(*) as cantidad
        FROM leads 
        GROUP BY source
        ORDER BY cantidad DESC
    ");
    $stmt->execute();
    $leadsBySource = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n🌐 LEADS POR FUENTE:\n";
    foreach ($leadsBySource as $row) {
        echo "  {$row['fuente']}: {$row['cantidad']} leads\n";
    }
    
    // Verificar leads por estado
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(status, 'sin_estado') as estado,
            COUNT(*) as cantidad
        FROM leads 
        GROUP BY status
        ORDER BY cantidad DESC
    ");
    $stmt->execute();
    $leadsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📋 LEADS POR ESTADO:\n";
    foreach ($leadsByStatus as $row) {
        echo "  {$row['estado']}: {$row['cantidad']} leads\n";
    }
    
    // Verificar si hay leads sin created_by (antiguos)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM leads WHERE created_by IS NULL OR created_by = 0");
    $stmt->execute();
    $leadsWithoutCreator = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "\n⚠️  LEADS SIN created_by (antiguos): $leadsWithoutCreator\n";
    
    // Ver últimos 20 leads con información completa
    echo "\n📋 ÚLTIMOS 20 LEADS REGISTRADOS:\n";
    $stmt = $pdo->prepare("
        SELECT 
            l.id, 
            l.first_name, 
            l.last_name, 
            l.email, 
            l.phone,
            l.country,
            l.source,
            l.status, 
            l.created_at,
            l.created_by,
            COALESCE(u.username, 'sistema') as creador
        FROM leads l
        LEFT JOIN users u ON l.created_by = u.id
        ORDER BY l.id DESC
        LIMIT 20
    ");
    $stmt->execute();
    $latestLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($latestLeads as $lead) {
        echo sprintf(
            "  #%d: %s %s (%s) | %s | %s | Creado: %s | Por: %s\n",
            $lead['id'],
            $lead['first_name'],
            $lead['last_name'],
            $lead['email'],
            $lead['status'],
            $lead['source'] ?? 'sin_fuente',
            date('Y-m-d', strtotime($lead['created_at'])),
            $lead['creador']
        );
    }
    
    // Buscar posibles leads "perdidos" - con datos mínimos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM leads 
        WHERE (first_name IS NULL OR first_name = '') 
           OR (last_name IS NULL OR last_name = '') 
           OR (email IS NULL OR email = '')
    ");
    $stmt->execute();
    $incompleteLeads = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "\n⚠️  LEADS CON DATOS INCOMPLETOS: $incompleteLeads\n";
    
    // Verificar rangos de IDs
    $stmt = $pdo->prepare("SELECT MIN(id) as min_id, MAX(id) as max_id FROM leads");
    $stmt->execute();
    $idRange = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n🔢 RANGO DE IDs: {$idRange['min_id']} - {$idRange['max_id']}\n";
    echo "   Total de IDs en rango: " . ($idRange['max_id'] - $idRange['min_id'] + 1) . "\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE INVESTIGACIÓN ===\n";