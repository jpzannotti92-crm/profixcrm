<?php
require_once __DIR__ . '/../../platform_check_bypass.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';

use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;
use IaTradeCRM\Models\Lead;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configurar autenticación y obtener usuario actual
$rbacMiddleware = new RBACMiddleware();
$request = new Request();

// Manejar autenticación
$currentUser = null;
try {
    // Usar el método handle() que internamente llama a authenticateUser()
    $authResult = $rbacMiddleware->handle($request, null, null, true);
    if ($authResult === true || (is_array($authResult) && $authResult['success'] === true)) {
        $currentUser = $request->user ?? null;
    }
} catch (Exception $e) {
    // Si no hay autenticación, usar usuario por defecto (ID 1)
    $currentUser = null;
}

// Campos disponibles del sistema para mapeo
$systemFields = [
    'first_name' => [
        'label' => 'Nombre',
        'type' => 'string',
        'required' => true,
        'description' => 'Nombre del lead'
    ],
    'last_name' => [
        'label' => 'Apellido',
        'type' => 'string',
        'required' => true,
        'description' => 'Apellido del lead'
    ],
    'email' => [
        'label' => 'Email',
        'type' => 'email',
        'required' => true,
        'description' => 'Correo electrónico del lead'
    ],
    'phone' => [
        'label' => 'Teléfono',
        'type' => 'string',
        'required' => false,
        'description' => 'Número de teléfono'
    ],
    'country' => [
        'label' => 'País',
        'type' => 'string',
        'required' => false,
        'description' => 'País de origen'
    ],
    'city' => [
        'label' => 'Ciudad',
        'type' => 'string',
        'required' => false,
        'description' => 'Ciudad de residencia'
    ],
    'source' => [
        'label' => 'Fuente',
        'type' => 'select',
        'required' => false,
        'options' => ['Website', 'Facebook', 'Google', 'Referral', 'Cold Call', 'Email Campaign'],
        'description' => 'Fuente de adquisición del lead'
    ],
    'status' => [
        'label' => 'Estado',
        'type' => 'select',
        'required' => false,
        'options' => ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'],
        'default' => 'new',
        'description' => 'Estado actual del lead'
    ],
    'assigned_to' => [
        'label' => 'Asignado a',
        'type' => 'number',
        'required' => false,
        'description' => 'ID del usuario asignado'
    ],
    'desk_id' => [
        'label' => 'Mesa',
        'type' => 'number',
        'required' => false,
        'description' => 'ID de la mesa asignada'
    ],
    'campaign' => [
        'label' => 'Campaña',
        'type' => 'string',
        'required' => false,
        'description' => 'Nombre de la campaña'
    ],
    'notes' => [
        'label' => 'Notas',
        'type' => 'text',
        'required' => false,
        'description' => 'Notas adicionales sobre el lead'
    ],
    'budget' => [
        'label' => 'Presupuesto',
        'type' => 'number',
        'required' => false,
        'description' => 'Presupuesto estimado del lead'
    ],
    'company' => [
        'label' => 'Empresa',
        'type' => 'string',
        'required' => false,
        'description' => 'Nombre de la empresa'
    ],
    'position' => [
        'label' => 'Cargo',
        'type' => 'string',
        'required' => false,
        'description' => 'Cargo en la empresa'
    ],
    'website' => [
        'label' => 'Sitio Web',
        'type' => 'url',
        'required' => false,
        'description' => 'Sitio web de la empresa'
    ],
    'linkedin' => [
        'label' => 'LinkedIn',
        'type' => 'url',
        'required' => false,
        'description' => 'Perfil de LinkedIn'
    ],
    'interest_level' => [
        'label' => 'Nivel de Interés',
        'type' => 'select',
        'required' => false,
        'options' => ['low', 'medium', 'high', 'very_high'],
        'description' => 'Nivel de interés del lead'
    ],
    'last_contact' => [
        'label' => 'Último Contacto',
        'type' => 'datetime',
        'required' => false,
        'description' => 'Fecha del último contacto'
    ]
];

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['fields'])) {
            // Obtener campos del sistema disponibles para mapeo
            echo json_encode([
                'success' => true,
                'message' => 'Campos del sistema obtenidos correctamente',
                'data' => $systemFields
            ]);
        } else {
            // Obtener historial de importaciones (demo)
            $importHistory = [
                [
                    'id' => 1,
                    'filename' => 'leads_enero_2024.xlsx',
                    'total_rows' => 150,
                    'imported_rows' => 145,
                    'failed_rows' => 5,
                    'status' => 'completed',
                    'created_at' => '2024-01-20 14:30:00',
                    'created_by' => 'Admin User',
                    'mapping' => [
                        'A' => 'first_name',
                        'B' => 'last_name',
                        'C' => 'email',
                        'D' => 'phone',
                        'E' => 'country'
                    ],
                    'errors' => [
                        ['row' => 15, 'error' => 'Email inválido: test@'],
                        ['row' => 23, 'error' => 'Nombre requerido'],
                        ['row' => 45, 'error' => 'Email duplicado'],
                        ['row' => 78, 'error' => 'Formato de teléfono inválido'],
                        ['row' => 92, 'error' => 'Email inválido: usuario@']
                    ]
                ],
                [
                    'id' => 2,
                    'filename' => 'leads_facebook_campaign.csv',
                    'total_rows' => 89,
                    'imported_rows' => 89,
                    'failed_rows' => 0,
                    'status' => 'completed',
                    'created_at' => '2024-01-19 09:15:00',
                    'created_by' => 'Marketing User',
                    'mapping' => [
                        'A' => 'first_name',
                        'B' => 'email',
                        'C' => 'phone',
                        'D' => 'source',
                        'E' => 'campaign'
                    ],
                    'errors' => []
                ],
                [
                    'id' => 3,
                    'filename' => 'leads_google_ads.xlsx',
                    'total_rows' => 234,
                    'imported_rows' => 0,
                    'failed_rows' => 0,
                    'status' => 'processing',
                    'created_at' => '2024-01-20 16:45:00',
                    'created_by' => 'Sales User',
                    'mapping' => [
                        'A' => 'first_name',
                        'B' => 'last_name',
                        'C' => 'email',
                        'D' => 'company',
                        'E' => 'budget'
                    ],
                    'errors' => []
                ]
            ];

            echo json_encode([
                'success' => true,
                'message' => 'Historial de importaciones obtenido correctamente',
                'data' => $importHistory
            ]);
        }
        break;

case 'POST':
        // Procesar importación de leads
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos o formato JSON incorrecto'
            ]);
            exit;
        }
        
        // Validar datos requeridos
        if (!isset($input['data']) || !isset($input['mapping'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Se requieren los campos: data y mapping'
            ]);
            exit;
        }
        
        $data = $input['data'];
        $mapping = $input['mapping'];
        $options = isset($input['options']) ? $input['options'] : [];
        
        // Validar mapping
        $requiredFields = ['first_name', 'last_name', 'email'];
        $mappedFields = array_values($mapping);
        
        foreach ($requiredFields as $required) {
            if (!in_array($required, $mappedFields)) {
                echo json_encode([
                    'success' => false,
                    'message' => "El campo requerido '{$required}' debe estar mapeado"
                ]);
                exit;
            }
        }
        
        // Procesar datos
        $results = [
            'total_rows' => count($data),
            'imported_rows' => 0,
            'failed_rows' => 0,
            'errors' => [],
            'imported_leads' => []
        ];
        
        // Obtener usuario actual para created_by
        $createdBy = $currentUser ? $currentUser->id : 1; // ID 1 como fallback
        
        // Procesamiento real de filas
        foreach ($data as $index => $row) {
            try {
                // Validar campos requeridos
                $leadData = [];
                foreach ($mapping as $csvColumn => $systemField) {
                    if (isset($row[$csvColumn]) && !empty($row[$csvColumn])) {
                        $leadData[$systemField] = $row[$csvColumn];
                    }
                }
                
                // Validar campos requeridos
                foreach ($requiredFields as $required) {
                    if (!isset($leadData[$required]) || empty($leadData[$required])) {
                        throw new Exception("Campo requerido faltante: {$required}");
                    }
                }
                
                // Validar email
                if (!filter_var($leadData['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Email inválido: {$leadData['email']}");
                }
                
                // Aplicar valores por defecto
                if (!isset($leadData['status'])) {
                    $leadData['status'] = 'new';
                }
                
                // Verificar si el email ya existe
                $existingLead = Lead::findByEmail($leadData['email']);
                if ($existingLead) {
                    $duplicateAction = isset($options['duplicate_action']) ? $options['duplicate_action'] : 'skip';
                    
                    if ($duplicateAction === 'skip') {
                        $results['failed_rows']++;
                        $results['errors'][] = [
                            'row' => $index + 1,
                            'error' => "Email duplicado: {$leadData['email']}",
                            'data' => $row
                        ];
                        continue;
                    } elseif ($duplicateAction === 'update') {
                        // Actualizar lead existente
                        $existingLead->setAttributes($leadData);
                        $existingLead->updated_by = $createdBy;
                        
                        if (!$existingLead->save()) {
                            throw new Exception("Error al actualizar lead existente");
                        }
                        
                        $results['imported_leads'][] = $existingLead->toArray();
                        $results['imported_rows']++;
                        continue;
                    }
                }
                
                // Crear nuevo lead
                $leadData['created_by'] = $createdBy;
                $leadData['updated_by'] = $createdBy;
                
                $lead = new Lead($leadData);
                
                if (!$lead->save()) {
                    throw new Exception("Error al guardar el lead");
                }
                
                $results['imported_leads'][] = $lead->toArray();
                $results['imported_rows']++;
                
            } catch (Exception $e) {
                $results['failed_rows']++;
                $results['errors'][] = [
                    'row' => $index + 1,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
            }
        }
        
        // Respuesta final
        echo json_encode([
            'success' => true,
            'message' => 'Importación completada',
            'data' => $results
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
        break;
}