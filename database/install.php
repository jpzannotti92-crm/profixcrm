<?php
/**
 * Script de Instalación de Base de Datos
 * iaTrade CRM - Sistema de Gestión de Leads Forex/CFD
 * 
 * Ejecuta todas las migraciones y configura la base de datos inicial
 */

// Configuración de la base de datos
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'iatrade_crm';

try {
    // Conectar a MySQL sin especificar base de datos
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🚀 Instalación de iaTrade CRM</h2>\n";
    echo "<pre>\n";
    
    // Crear base de datos si no existe
    echo "1. Creando base de datos '$database'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "   ✅ Base de datos creada exitosamente\n\n";
    
    // Conectar a la base de datos específica
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Lista de archivos de migración en orden
    $migrations = [
        '001_create_users_table.sql',
        '002_create_desks_table.sql',
        '003_create_leads_table.sql',
        '004_create_kpis_tables.sql',
        '005_add_granted_by_to_role_permissions.sql',
        '006_backfill_granted_by_defaults.sql'
    ];
    
    echo "2. Ejecutando migraciones...\n";
    
    foreach ($migrations as $migration) {
        $filePath = __DIR__ . "/migrations/$migration";
        
        if (!file_exists($filePath)) {
            echo "   ❌ Error: No se encontró el archivo $migration\n";
            continue;
        }
        
        echo "   📄 Ejecutando $migration...\n";
        
        $sql = file_get_contents($filePath);
        
        // Dividir el archivo SQL en declaraciones individuales
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
            }
        );
        
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Ignorar errores de tablas/columnas ya existentes y duplicados para idempotencia
                    if (
                        strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate entry') === false &&
                        strpos($e->getMessage(), 'Duplicate column name') === false &&
                        strpos($e->getMessage(), 'errno: 1060') === false &&
                        strpos($e->getMessage(), 'Duplicate key name') === false
                    ) {
                        echo "      ⚠️  Advertencia: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        
        echo "   ✅ $migration completada\n";
    }
    
    echo "\n3. Configurando datos iniciales...\n";
    
    // Crear usuario administrador por defecto
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (username, email, password_hash, first_name, last_name, status) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute(['admin', 'admin@iatrade-crm.com', $adminPassword, 'Admin', 'System', 'active']);
    
    // Asignar rol de super admin
    $pdo->exec("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (1, 1)");
    
    echo "   ✅ Usuario administrador creado (admin/admin123)\n";
    
    // Asignar permisos a roles
    echo "   📋 Configurando permisos...\n";
    
    // Super Admin - todos los permisos
    $pdo->exec("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 1, id FROM permissions
    ");
    
    // Admin - casi todos los permisos excepto algunos críticos
    $pdo->exec("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 2, id FROM permissions WHERE name NOT IN ('users.delete')
    ");
    
    // Manager - permisos de gestión de leads y reportes
    $pdo->exec("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 3, id FROM permissions WHERE module IN ('leads', 'reports', 'kpis', 'desks')
    ");
    
    // Sales - permisos básicos de leads
    $pdo->exec("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT 5, id FROM permissions WHERE name IN ('leads.read', 'leads.update', 'kpis.view')
    ");
    
    echo "   ✅ Permisos configurados\n";
    
    echo "\n4. Verificando instalación...\n";
    
    // Verificar tablas creadas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedTables = [
        'users', 'roles', 'permissions', 'role_permissions', 'user_roles',
        'desks', 'desk_members', 'desk_settings',
        'leads', 'lead_status_history', 'lead_activities', 'lead_documents', 'lead_assignments',
        'campaigns', 'auto_assignment_rules',
        'daily_user_metrics', 'daily_desk_metrics', 'daily_global_metrics',
        'targets', 'alerts', 'kpi_configurations'
    ];
    
    $missingTables = array_diff($expectedTables, $tables);
    
    if (empty($missingTables)) {
        echo "   ✅ Todas las tablas fueron creadas correctamente\n";
        echo "   📊 Total de tablas: " . count($tables) . "\n";
    } else {
        echo "   ❌ Faltan las siguientes tablas: " . implode(', ', $missingTables) . "\n";
    }
    
    // Verificar datos iniciales
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    $roleCount = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM permissions");
    $permissionCount = $stmt->fetchColumn();
    
    echo "\n📈 Estadísticas de instalación:\n";
    echo "   👥 Usuarios: $userCount\n";
    echo "   🔐 Roles: $roleCount\n";
    echo "   🛡️  Permisos: $permissionCount\n";
    
    echo "\n🎉 ¡Instalación completada exitosamente!\n";
    echo "\n📋 Información de acceso:\n";
    echo "   🌐 URL: http://localhost/iatrade-crm\n";
    echo "   👤 Usuario: admin\n";
    echo "   🔑 Contraseña: admin123\n";
    echo "\n⚠️  IMPORTANTE: Cambia la contraseña del administrador después del primer acceso\n";
    echo "</pre>\n";
    
} catch (PDOException $e) {
    echo "<pre>";
    echo "❌ Error de instalación: " . $e->getMessage() . "\n";
    echo "\n🔧 Verifica que:\n";
    echo "   - XAMPP esté ejecutándose\n";
    echo "   - MySQL esté activo\n";
    echo "   - Las credenciales de base de datos sean correctas\n";
    echo "</pre>";
}
?>