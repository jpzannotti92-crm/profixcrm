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
    
    echo "=== CORRIGIENDO PERMISOS CON CAMPOS VACÍOS ===\n\n";
    
    // Obtener permisos con campos vacíos
    $stmt = $pdo->query('SELECT id, name FROM permissions WHERE display_name IS NULL OR display_name = "" OR module IS NULL OR module = "" OR action IS NULL OR action = ""');
    $emptyPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Encontrados " . count($emptyPermissions) . " permisos con campos vacíos\n\n";
    
    // Mapeo de correcciones basado en el nombre del permiso
    $corrections = [
        'activities.view' => [
            'display_name' => 'Ver Actividades',
            'description' => 'Ver actividades de leads',
            'module' => 'activities',
            'action' => 'view'
        ],
        'activities.create' => [
            'display_name' => 'Crear Actividades',
            'description' => 'Crear nuevas actividades',
            'module' => 'activities',
            'action' => 'create'
        ],
        'activities.edit' => [
            'display_name' => 'Editar Actividades',
            'description' => 'Editar actividades existentes',
            'module' => 'activities',
            'action' => 'edit'
        ],
        'activities.delete' => [
            'display_name' => 'Eliminar Actividades',
            'description' => 'Eliminar actividades',
            'module' => 'activities',
            'action' => 'delete'
        ],
        'dashboard.view' => [
            'display_name' => 'Ver Dashboard',
            'description' => 'Ver panel de control',
            'module' => 'dashboard',
            'action' => 'view'
        ],
        'dashboard.stats' => [
            'display_name' => 'Ver Estadísticas',
            'description' => 'Ver estadísticas del dashboard',
            'module' => 'dashboard',
            'action' => 'stats'
        ],
        'instruments.view' => [
            'display_name' => 'Ver Instrumentos',
            'description' => 'Ver instrumentos financieros',
            'module' => 'instruments',
            'action' => 'view'
        ],
        'instruments.create' => [
            'display_name' => 'Crear Instrumentos',
            'description' => 'Crear nuevos instrumentos',
            'module' => 'instruments',
            'action' => 'create'
        ],
        'instruments.edit' => [
            'display_name' => 'Editar Instrumentos',
            'description' => 'Editar instrumentos existentes',
            'module' => 'instruments',
            'action' => 'edit'
        ],
        'instruments.delete' => [
            'display_name' => 'Eliminar Instrumentos',
            'description' => 'Eliminar instrumentos',
            'module' => 'instruments',
            'action' => 'delete'
        ],
        'trading.view' => [
            'display_name' => 'Ver Trading',
            'description' => 'Ver cuentas de trading',
            'module' => 'trading',
            'action' => 'view'
        ],
        'trading.create' => [
            'display_name' => 'Crear Cuentas',
            'description' => 'Crear cuentas de trading',
            'module' => 'trading',
            'action' => 'create'
        ],
        'trading.edit' => [
            'display_name' => 'Editar Cuentas',
            'description' => 'Editar cuentas de trading',
            'module' => 'trading',
            'action' => 'edit'
        ],
        'trading.delete' => [
            'display_name' => 'Eliminar Cuentas',
            'description' => 'Eliminar cuentas de trading',
            'module' => 'trading',
            'action' => 'delete'
        ],
        'transactions.view' => [
            'display_name' => 'Ver Transacciones',
            'description' => 'Ver depósitos y retiros',
            'module' => 'transactions',
            'action' => 'view'
        ],
        'transactions.process' => [
            'display_name' => 'Procesar Transacciones',
            'description' => 'Procesar transacciones',
            'module' => 'transactions',
            'action' => 'process'
        ],
        'transactions.approve' => [
            'display_name' => 'Aprobar Transacciones',
            'description' => 'Aprobar depósitos y retiros',
            'module' => 'transactions',
            'action' => 'approve'
        ]
    ];
    
    $updateStmt = $pdo->prepare('UPDATE permissions SET display_name = ?, description = ?, module = ?, action = ? WHERE id = ?');
    $updatedCount = 0;
    
    foreach ($emptyPermissions as $permission) {
        $name = $permission['name'];
        $id = $permission['id'];
        
        if (isset($corrections[$name])) {
            $correction = $corrections[$name];
            $updateStmt->execute([
                $correction['display_name'],
                $correction['description'],
                $correction['module'],
                $correction['action'],
                $id
            ]);
            
            echo "✅ Corregido: $name\n";
            $updatedCount++;
        } else {
            echo "⚠️  No se encontró corrección para: $name\n";
        }
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "Permisos actualizados: $updatedCount\n";
    echo "Corrección completada.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>