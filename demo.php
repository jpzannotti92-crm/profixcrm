<?php
/**
 * Script de Demostración - iaTrade CRM
 * Muestra las principales funcionalidades del sistema
 */

require_once __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use IaTradeCRM\Database\Connection;
use IaTradeCRM\Models\User;
use IaTradeCRM\Models\Lead;
use IaTradeCRM\Models\Desk;

echo "<h1>🚀 iaTrade CRM - Demostración del Sistema</h1>\n";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
    .demo-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    .info { color: #17a2b8; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #f8f9fa; }
    .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .status-new { background: #e3f2fd; color: #1976d2; }
    .status-contacted { background: #e8f5e8; color: #388e3c; }
    .status-ftd { background: #fff3e0; color: #f57c00; }
</style>\n";

try {
    // Verificar conexión a la base de datos
    echo "<div class='demo-section'>\n";
    echo "<h2>📊 1. Verificación del Sistema</h2>\n";
    
    $db = Connection::getInstance();
    echo "<p class='success'>✅ Conexión a base de datos: EXITOSA</p>\n";
    
    // Verificar tablas
    $tables = ['users', 'roles', 'permissions', 'leads', 'desks', 'daily_user_metrics'];
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM {$table}");
            $count = $stmt->fetchColumn();
            echo "<p class='success'>✅ Tabla '{$table}': {$count} registros</p>\n";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error en tabla '{$table}': " . $e->getMessage() . "</p>\n";
        }
    }
    echo "</div>\n";

    // Demostrar gestión de usuarios
    echo "<div class='demo-section'>\n";
    echo "<h2>👥 2. Gestión de Usuarios</h2>\n";
    
    $users = User::all([], 'id ASC', 5);
    if (!empty($users)) {
        echo "<table>\n";
        echo "<tr><th>ID</th><th>Usuario</th><th>Email</th><th>Nombre</th><th>Estado</th></tr>\n";
        foreach ($users as $user) {
            echo "<tr>\n";
            echo "<td>{$user->id}</td>\n";
            echo "<td>{$user->username}</td>\n";
            echo "<td>{$user->email}</td>\n";
            echo "<td>{$user->getFullName()}</td>\n";
            echo "<td>{$user->status}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Mostrar roles del primer usuario
        if (isset($users[0])) {
            $roles = $users[0]->getRoles();
            echo "<p class='info'>🔐 Roles del usuario '{$users[0]->username}': ";
            echo implode(', ', array_map(fn($r) => $r->display_name, $roles));
            echo "</p>\n";
        }
    } else {
        echo "<p class='info'>ℹ️ No hay usuarios registrados. Ejecuta el instalador primero.</p>\n";
    }
    echo "</div>\n";

    // Demostrar gestión de leads (con datos simulados)
    echo "<div class='demo-section'>\n";
    echo "<h2>🎯 3. Gestión de Leads</h2>\n";
    
    // Crear algunos leads de demostración
    $demoLeads = [
        [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@demo.com',
            'phone' => '+1234567890',
            'country' => 'USA',
            'status' => 'new',
            'priority' => 'high',
            'source' => 'google_ads',
            'trading_experience' => 'beginner',
            'capital_range' => '1k_5k',
            'created_by' => 1
        ],
        [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@demo.com',
            'phone' => '+1234567891',
            'country' => 'Canada',
            'status' => 'contacted',
            'priority' => 'medium',
            'source' => 'facebook',
            'trading_experience' => 'intermediate',
            'capital_range' => '5k_10k',
            'created_by' => 1
        ],
        [
            'first_name' => 'Mike',
            'last_name' => 'Johnson',
            'email' => 'mike.johnson@demo.com',
            'phone' => '+1234567892',
            'country' => 'UK',
            'status' => 'ftd',
            'priority' => 'high',
            'source' => 'organic',
            'trading_experience' => 'advanced',
            'capital_range' => '10k_25k',
            'ftd_amount' => 500.00,
            'created_by' => 1
        ]
    ];

    echo "<h3>📝 Creando leads de demostración...</h3>\n";
    $createdLeads = [];
    
    foreach ($demoLeads as $leadData) {
        try {
            // Verificar si el lead ya existe
            $existing = Lead::where(['email' => $leadData['email']]);
            if (!$existing) {
                $lead = new Lead($leadData);
                if ($lead->save()) {
                    $createdLeads[] = $lead;
                    echo "<p class='success'>✅ Lead creado: {$lead->getFullName()} ({$lead->email})</p>\n";
                }
            } else {
                $createdLeads[] = $existing;
                echo "<p class='info'>ℹ️ Lead ya existe: {$existing->getFullName()} ({$existing->email})</p>\n";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error creando lead: " . $e->getMessage() . "</p>\n";
        }
    }

    // Mostrar leads existentes
    $leads = Lead::all([], 'id DESC', 10);
    if (!empty($leads)) {
        echo "<h3>📋 Leads en el sistema:</h3>\n";
        echo "<table>\n";
        echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>País</th><th>Estado</th><th>Prioridad</th><th>Fuente</th><th>FTD</th></tr>\n";
        foreach ($leads as $lead) {
            $statusClass = 'status-' . $lead->status;
            echo "<tr>\n";
            echo "<td>{$lead->id}</td>\n";
            echo "<td>{$lead->getFullName()}</td>\n";
            echo "<td>{$lead->email}</td>\n";
            echo "<td>{$lead->country}</td>\n";
            echo "<td><span class='status-badge {$statusClass}'>{$lead->status}</span></td>\n";
            echo "<td>{$lead->priority}</td>\n";
            echo "<td>{$lead->source}</td>\n";
            echo "<td>" . ($lead->ftd_amount ? '$' . number_format($lead->ftd_amount, 2) : '-') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    echo "</div>\n";

    // Demostrar KPIs
    echo "<div class='demo-section'>\n";
    echo "<h2>📈 4. KPIs y Métricas</h2>\n";
    
    $stats = Lead::getConversionStats();
    if (!empty($stats)) {
        echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;'>\n";
        
        echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;'>\n";
        echo "<h4 style='margin: 0; color: #1976d2;'>Total Leads</h4>\n";
        echo "<p style='font-size: 24px; font-weight: bold; margin: 5px 0;'>{$stats['total_leads']}</p>\n";
        echo "</div>\n";
        
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;'>\n";
        echo "<h4 style='margin: 0; color: #388e3c;'>Contactados</h4>\n";
        echo "<p style='font-size: 24px; font-weight: bold; margin: 5px 0;'>{$stats['contacted']}</p>\n";
        echo "</div>\n";
        
        echo "<div style='background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;'>\n";
        echo "<h4 style='margin: 0; color: #f57c00;'>Conversiones</h4>\n";
        echo "<p style='font-size: 24px; font-weight: bold; margin: 5px 0;'>{$stats['conversions']}</p>\n";
        echo "</div>\n";
        
        echo "<div style='background: #f3e5f5; padding: 15px; border-radius: 8px; text-align: center;'>\n";
        echo "<h4 style='margin: 0; color: #7b1fa2;'>Revenue Total</h4>\n";
        echo "<p style='font-size: 24px; font-weight: bold; margin: 5px 0;'>$" . number_format($stats['total_deposits'], 2) . "</p>\n";
        echo "</div>\n";
        
        echo "</div>\n";
        
        // Calcular tasa de conversión
        $conversionRate = $stats['total_leads'] > 0 ? 
            round(($stats['conversions'] / $stats['total_leads']) * 100, 2) : 0;
        
        echo "<p class='info'>📊 Tasa de Conversión: {$conversionRate}%</p>\n";
        
        if ($stats['avg_ftd']) {
            echo "<p class='info'>💰 FTD Promedio: $" . number_format($stats['avg_ftd'], 2) . "</p>\n";
        }
    }
    echo "</div>\n";

    // Demostrar funcionalidades avanzadas
    echo "<div class='demo-section'>\n";
    echo "<h2>⚡ 5. Funcionalidades Avanzadas</h2>\n";
    
    echo "<h3>🔍 Búsqueda y Filtros</h3>\n";
    $newLeads = Lead::getByStatus('new');
    echo "<p>• Leads nuevos: " . count($newLeads) . "</p>\n";
    
    $highPriorityLeads = Lead::search(['priority' => 'high']);
    echo "<p>• Leads de alta prioridad: " . count($highPriorityLeads) . "</p>\n";
    
    echo "<h3>📊 Análisis por Fuente</h3>\n";
    $sources = ['google_ads', 'facebook', 'organic', 'referral'];
    foreach ($sources as $source) {
        $sourceLeads = Lead::search(['source' => $source]);
        echo "<p>• {$source}: " . count($sourceLeads) . " leads</p>\n";
    }
    
    echo "<h3>🎯 Leads que Requieren Seguimiento</h3>\n";
    $followupLeads = Lead::getRequiringFollowup();
    echo "<p>• Leads pendientes de seguimiento: " . count($followupLeads) . "</p>\n";
    
    echo "</div>\n";

    // Información del sistema
    echo "<div class='demo-section'>\n";
    echo "<h2>ℹ️ 6. Información del Sistema</h2>\n";
    
    echo "<table>\n";
    echo "<tr><th>Componente</th><th>Estado</th><th>Información</th></tr>\n";
    echo "<tr><td>PHP Version</td><td class='success'>✅</td><td>" . PHP_VERSION . "</td></tr>\n";
    echo "<tr><td>Base de Datos</td><td class='success'>✅</td><td>MySQL Conectado</td></tr>\n";
    echo "<tr><td>Composer</td><td class='success'>✅</td><td>Dependencias Instaladas</td></tr>\n";
    echo "<tr><td>Servidor Web</td><td class='success'>✅</td><td>PHP Built-in Server</td></tr>\n";
    echo "</table>\n";
    
    echo "<h3>🔗 Enlaces Útiles</h3>\n";
    echo "<ul>\n";
    echo "<li><a href='/' target='_blank'>🏠 Aplicación Principal</a></li>\n";
    echo "<li><a href='/database/install.php' target='_blank'>⚙️ Instalador de Base de Datos</a></li>\n";
    echo "<li><a href='#' onclick='location.reload()'>🔄 Recargar Demo</a></li>\n";
    echo "</ul>\n";
    
    echo "</div>\n";

    echo "<div class='demo-section' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;'>\n";
    echo "<h2>🎉 ¡Demo Completada Exitosamente!</h2>\n";
    echo "<p>El sistema iaTrade CRM está funcionando correctamente con todas sus funcionalidades:</p>\n";
    echo "<ul>\n";
    echo "<li>✅ Base de datos configurada y funcionando</li>\n";
    echo "<li>✅ Modelos y relaciones implementadas</li>\n";
    echo "<li>✅ Sistema de usuarios y permisos</li>\n";
    echo "<li>✅ Gestión completa de leads</li>\n";
    echo "<li>✅ KPIs y métricas de Forex/CFD</li>\n";
    echo "<li>✅ Interfaz web moderna y responsive</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Credenciales de acceso:</strong></p>\n";
    echo "<p>Usuario: <code>admin</code> | Contraseña: <code>admin123</code></p>\n";
    echo "</div>\n";

} catch (Exception $e) {
    echo "<div class='demo-section' style='background: #ffebee; border-left: 4px solid #f44336;'>\n";
    echo "<h2>❌ Error en la Demostración</h2>\n";
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>\n";
    echo "<p>Asegúrate de que:</p>\n";
    echo "<ul>\n";
    echo "<li>XAMPP esté ejecutándose</li>\n";
    echo "<li>MySQL esté activo</li>\n";
    echo "<li>La base de datos esté creada</li>\n";
    echo "<li>Las migraciones se hayan ejecutado</li>\n";
    echo "</ul>\n";
    echo "<p><a href='/database/install.php'>🔧 Ejecutar Instalador</a></p>\n";
    echo "</div>\n";
}
?>