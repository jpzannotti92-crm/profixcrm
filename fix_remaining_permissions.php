<?php
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASS']
    );
    
    echo "=== CORRIGIENDO PERMISOS RESTANTES ===\n\n";
    
    // Correcciones para los permisos restantes
    $corrections = [
        'user_permissions.view' => [
            'display_name' => 'Ver Permisos de Usuario',
            'description' => 'Ver permisos asignados a usuarios',
            'module' => 'user_permissions',
            'action' => 'view'
        ],
        'user_permissions.edit' => [
            'display_name' => 'Editar Permisos de Usuario',
            'description' => 'Editar permisos de usuarios',
            'module' => 'user_permissions',
            'action' => 'edit'
        ],
        'import.leads' => [
            'display_name' => 'Importar Leads',
            'description' => 'Importar leads desde archivos',
            'module' => 'import',
            'action' => 'leads'
        ],
        'export.leads' => [
            'display_name' => 'Exportar Leads',
            'description' => 'Exportar leads a archivos',
            'module' => 'export',
            'action' => 'leads'
        ],
        'system.database' => [
            'display_name' => 'Gestión de Base de Datos',
            'description' => 'Acceso a gestión de base de datos',
            'module' => 'system',
            'action' => 'database'
        ],
        'system.maintenance' => [
            'display_name' => 'Mantenimiento del Sistema',
            'description' => 'Acceso a herramientas de mantenimiento',
            'module' => 'system',
            'action' => 'maintenance'
        ],
        'instruments.edit' => [
            'display_name' => 'Editar Instrumentos',
            'description' => 'Editar instrumentos financieros',
            'module' => 'instruments',
            'action' => 'edit'
        ]
    ];
    
    $updateStmt = $pdo->prepare('UPDATE permissions SET display_name = ?, description = ?, module = ?, action = ? WHERE name = ?');
    $updatedCount = 0;
    
    foreach ($corrections as $name => $correction) {
        $result = $updateStmt->execute([
            $correction['display_name'],
            $correction['description'],
            $correction['module'],
            $correction['action'],
            $name
        ]);
        
        if ($updateStmt->rowCount() > 0) {
            echo "✅ Corregido: $name\n";
            $updatedCount++;
        } else {
            echo "⚠️  No se encontró o ya estaba correcto: $name\n";
        }
    }
    
    echo "\n=== VERIFICACIÓN FINAL ===\n";
    
    // Verificar que no queden permisos con campos vacíos
    $stmt = $pdo->query('SELECT COUNT(*) as empty_count FROM permissions WHERE display_name IS NULL OR display_name = "" OR module IS NULL OR module = "" OR action IS NULL OR action = ""');
    $emptyCount = $stmt->fetch()['empty_count'];
    
    echo "Permisos con campos vacíos restantes: $emptyCount\n";
    echo "Permisos actualizados en esta ejecución: $updatedCount\n";
    
    if ($emptyCount == 0) {
        echo "🎉 ¡Todos los permisos han sido corregidos!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>