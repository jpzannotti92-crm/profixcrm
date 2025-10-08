<?php
// No cargar Composer aquí para evitar incompatibilidades en PHP 8.0
require_once __DIR__ . '/../../src/Database/Connection.php';

use iaTradeCRM\Database\Connection;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = Connection::getInstance()->getConnection();
} catch (Exception $e) {
    // Para GET, devolver datos vacíos en lugar de 500
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success' => true,
            'message' => 'No hay leads disponibles aún',
            'data' => [],
            'stats' => [
                'new_leads' => 0,
                'qualified_leads' => 0,
                'converted_leads' => 0
            ],
            'pagination' => [
                'current_page' => (int)($_GET['page'] ?? 1),
                'per_page' => (int)($_GET['limit'] ?? 50),
                'total' => 0,
                'total_pages' => 0
            ]
        ]);
        exit();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            // Verificar si se solicita un lead específico por ID
            if (isset($_GET['id'])) {
                $leadId = (int)$_GET['id'];
                
                // Obtener lead específico
                $stmt = $db->prepare("\n                    SELECT l.*, \n                           CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,\n                           d.name as desk_name\n                    FROM leads l\n                    LEFT JOIN users u ON l.assigned_to = u.id\n                    LEFT JOIN desks d ON l.desk_id = d.id\n                    WHERE l.id = ?\n                ");
                $stmt->execute([$leadId]);
                $lead = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lead) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Lead obtenido exitosamente',
                        'data' => $lead
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Lead no encontrado'
                    ]);
                }
                return;
            }
            
            // Obtener lista de leads simple
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 50); // Aumentar límite por defecto
            $offset = ($page - 1) * $limit;
            
            // Contar total de leads
            $countStmt = $db->query("SELECT COUNT(*) as total FROM leads");
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Obtener estadísticas por estado
            $statsStmt = $db->query("\n                SELECT \n                    status,\n                    COUNT(*) as count\n                FROM leads \n                GROUP BY status\n            ");
            $statsResults = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats = [
                'new_leads' => 0,
                'qualified_leads' => 0,
                'converted_leads' => 0
            ];
            
            foreach ($statsResults as $stat) {
                switch ($stat['status']) {
                    case 'new':
                        $stats['new_leads'] = (int)$stat['count'];
                        break;
                    case 'qualified':
                        $stats['qualified_leads'] = (int)$stat['count'];
                        break;
                    case 'converted':
                    case 'closed_won':
                        $stats['converted_leads'] += (int)$stat['count'];
                        break;
                }
            }
            
            // Obtener leads con paginación (nota: evitar placeholders en LIMIT/OFFSET con emulación desactivada)
            // Valores enteros seguros ya que han sido casteados arriba
            $limitInt = (int)$limit;
            $offsetInt = (int)$offset;
            $sql = "\n                SELECT l.*, \n                       CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,\n                       d.name as desk_name\n                FROM leads l\n                LEFT JOIN users u ON l.assigned_to = u.id\n                LEFT JOIN desks d ON l.desk_id = d.id\n                ORDER BY l.created_at DESC\n                LIMIT {$limitInt} OFFSET {$offsetInt}\n            ";
            $stmt = $db->query($sql);
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPages = ceil($total / $limit);
            
            echo json_encode([
                'success' => true,
                'message' => 'Leads obtenidos exitosamente',
                'data' => $leads,
                'stats' => $stats,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => (int)$total,
                    'total_pages' => $totalPages
                ]
            ]);
            
        } catch (Exception $e) {
            // Si la tabla 'leads' no existe, devolver respuesta vacía en lugar de 500
            $msg = $e->getMessage();
            if (strpos($msg, "42S02") !== false || stripos($msg, "Table '" ) !== false && stripos($msg, "leads" ) !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'No hay leads disponibles aún',
                    'data' => [],
                    'stats' => [
                        'new_leads' => 0,
                        'qualified_leads' => 0,
                        'converted_leads' => 0
                    ],
                    'pagination' => [
                        'current_page' => (int)($_GET['page'] ?? 1),
                        'per_page' => (int)($_GET['limit'] ?? 50),
                        'total' => 0,
                        'total_pages' => 0
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener leads: ' . $e->getMessage()
                ]);
            }
        }
        break;
        
    case 'POST':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Datos inválidos'
                ]);
                break;
            }
            
            // Construir inserción dinámica según columnas existentes
            $columnsStmt = $db->query("SHOW COLUMNS FROM leads");
            $existingColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN, 0);

            // Mapeo de datos de entrada a columnas de la tabla
            $fieldMap = [
                'first_name'   => $input['first_name'] ?? '',
                'last_name'    => $input['last_name'] ?? '',
                'email'        => $input['email'] ?? '',
                'phone'        => $input['phone'] ?? '',
                'country'      => $input['country'] ?? '',
                'city'         => $input['city'] ?? '',
                'company'      => $input['company'] ?? '',
                'job_title'    => $input['position'] ?? '', // Mapear position a job_title
                'source'       => $input['source'] ?? 'Website',
                'status'       => $input['status'] ?? 'new',
                'priority'     => $input['interest_level'] ?? 'medium', // Mapear interest_level a priority
                'value'        => $input['budget'] ?? null, // Mapear budget a value
                'notes'        => $input['notes'] ?? '',
                'assigned_to'  => $input['assigned_to'] ?? null,
                'desk_id'      => $input['desk_id'] ?? null,
            ];

            $insertFields = [];
            $insertPlaceholders = [];
            $insertValues = [];

            foreach ($fieldMap as $column => $value) {
                if (in_array($column, $existingColumns, true)) {
                    $insertFields[] = $column;
                    $insertPlaceholders[] = '?';
                    $insertValues[] = $value;
                }
            }

            // Campos de auditoría si existen
            if (in_array('created_at', $existingColumns, true)) {
                $insertFields[] = 'created_at';
                $insertPlaceholders[] = 'NOW()';
            }
            if (in_array('updated_at', $existingColumns, true)) {
                $insertFields[] = 'updated_at';
                $insertPlaceholders[] = 'NOW()';
            }

            if (empty($insertFields)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontraron columnas válidas para insertar en la tabla leads'
                ]);
                break;
            }

            $sql = 'INSERT INTO leads (' . implode(', ', $insertFields) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
            $stmt = $db->prepare($sql);
            $result = $stmt->execute($insertValues);
            
            if ($result) {
                $leadId = $db->lastInsertId();
                
                // Crear notificación para nuevo lead
                try {
                    require_once 'notifications.php';
                    $leadName = trim(($input['first_name'] ?? '') . ' ' . ($input['last_name'] ?? ''));
                    $leadEmail = $input['email'] ?? '';
                    notifyNewLead($db, $leadId, $leadName, $leadEmail);
                } catch (Exception $e) {
                    // Log error but don't fail the lead creation
                    error_log("Error creating notification: " . $e->getMessage());
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Lead creado exitosamente',
                    'data' => ['id' => $leadId]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al crear el lead'
                ]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear lead: ' . $e->getMessage()
            ]);
        }
        break;
}
?>