<?php
/**
 * API para gestión de leads
 */

require_once 'database.php';

header('Content-Type: application/json');

// Verificar autenticación
$user = verifyToken();

// Obtener conexión a la base de datos
$pdo = getDbConnection();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

try {
    switch ($method) {
        case 'GET':
            // Obtener leads
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 20);
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $desk_id = $_GET['desk_id'] ?? '';
            
            $offset = ($page - 1) * $limit;
            
            // Construir query base
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(l.first_name LIKE :search OR l.last_name LIKE :search OR l.email LIKE :search OR l.phone LIKE :search)";
                $params['search'] = "%{$search}%";
            }
            
            if (!empty($status)) {
                $whereConditions[] = "l.status = :status";
                $params['status'] = $status;
            }
            
            if (!empty($desk_id)) {
                $whereConditions[] = "l.desk_id = :desk_id";
                $params['desk_id'] = $desk_id;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Query para obtener leads
            $sql = "
                SELECT 
                    l.*,
                    d.name as desk_name,
                    u.first_name as assigned_user_first_name,
                    u.last_name as assigned_user_last_name
                FROM leads l
                LEFT JOIN desks d ON l.desk_id = d.id
                LEFT JOIN users u ON l.assigned_user_id = u.id
                {$whereClause}
                ORDER BY l.created_at DESC
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $pdo->prepare($sql);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $leads = $stmt->fetchAll();
            
            // Contar total de leads
            $countSql = "
                SELECT COUNT(*) as total
                FROM leads l
                LEFT JOIN desks d ON l.desk_id = d.id
                LEFT JOIN users u ON l.assigned_user_id = u.id
                {$whereClause}
            ";
            
            $countStmt = $pdo->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue(":{$key}", $value);
            }
            $countStmt->execute();
            $total = (int) $countStmt->fetchColumn();
            
            successResponse([
                'leads' => $leads,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'POST':
            // Crear nuevo lead
            $input = json_decode(file_get_contents('php://input'), true);
            
            $requiredFields = ['first_name', 'last_name', 'email'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    errorResponse("El campo {$field} es requerido", 400);
                    exit;
                }
            }
            
            $sql = "
                INSERT INTO leads (
                    first_name, last_name, email, phone, country, 
                    status, source, desk_id, assigned_user_id, created_at
                ) VALUES (
                    :first_name, :last_name, :email, :phone, :country,
                    :status, :source, :desk_id, :assigned_user_id, NOW()
                )
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'phone' => $input['phone'] ?? null,
                'country' => $input['country'] ?? null,
                'status' => $input['status'] ?? 'new',
                'source' => $input['source'] ?? 'manual',
                'desk_id' => $input['desk_id'] ?? null,
                'assigned_user_id' => $input['assigned_user_id'] ?? null
            ]);
            
            $leadId = $pdo->lastInsertId();
            
            // Registrar actividad
            $activitySql = "
                INSERT INTO lead_activities (
                    lead_id, user_id, type, description, created_at
                ) VALUES (
                    :lead_id, :user_id, 'created', 'Lead creado', NOW()
                )
            ";
            
            $activityStmt = $pdo->prepare($activitySql);
            $activityStmt->execute([
                'lead_id' => $leadId,
                'user_id' => $user['user_id']
            ]);
            
            successResponse(['id' => $leadId], 'Lead creado correctamente');
            break;
            
        case 'PUT':
            // Actualizar lead
            $leadId = $_GET['id'] ?? null;
            if (!$leadId) {
                errorResponse('ID de lead requerido', 400);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $updateFields = [];
            $params = ['id' => $leadId];
            
            $allowedFields = [
                'first_name', 'last_name', 'email', 'phone', 'country',
                'status', 'source', 'desk_id', 'assigned_user_id'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "{$field} = :{$field}";
                    $params[$field] = $input[$field];
                }
            }
            
            if (empty($updateFields)) {
                errorResponse('No hay campos para actualizar', 400);
                exit;
            }
            
            $sql = "
                UPDATE leads 
                SET " . implode(', ', $updateFields) . ", updated_at = NOW()
                WHERE id = :id
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() > 0) {
                // Registrar actividad
                $activitySql = "
                    INSERT INTO lead_activities (
                        lead_id, user_id, type, description, created_at
                    ) VALUES (
                        :lead_id, :user_id, 'updated', 'Lead actualizado', NOW()
                    )
                ";
                
                $activityStmt = $pdo->prepare($activitySql);
                $activityStmt->execute([
                    'lead_id' => $leadId,
                    'user_id' => $user['user_id']
                ]);
                
                successResponse(null, 'Lead actualizado correctamente');
            } else {
                errorResponse('Lead no encontrado', 404);
            }
            break;
            
        case 'DELETE':
            // Eliminar lead
            $leadId = $_GET['id'] ?? null;
            if (!$leadId) {
                errorResponse('ID de lead requerido', 400);
                exit;
            }
            
            $sql = "DELETE FROM leads WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $leadId]);
            
            if ($stmt->rowCount() > 0) {
                successResponse(null, 'Lead eliminado correctamente');
            } else {
                errorResponse('Lead no encontrado', 404);
            }
            break;
            
        default:
            errorResponse('Método no permitido', 405);
            break;
    }
    
} catch (PDOException $e) {
    errorResponse('Error de base de datos: ' . $e->getMessage(), 500);
}