<?php

namespace iaTradeCRM\Middleware;

use iaTradeCRM\Database\Connection;

class DeskAccessMiddleware
{
    private $db;

    public function __construct()
    {
        $this->db = Connection::getInstance()->getConnection();
    }

    /**
     * Obtiene las mesas asignadas a un usuario
     */
    public function getUserDesks(int $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT d.id, d.name
                FROM desks d
                INNER JOIN desk_users du ON d.id = du.desk_id
                WHERE du.user_id = ? AND d.status = 'active'
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Verifica si un usuario tiene acceso a una mesa específica
     */
    public function hasAccessToDesk(int $userId, int $deskId): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM desk_users du
                INNER JOIN desks d ON du.desk_id = d.id
                WHERE du.user_id = ? AND du.desk_id = ? AND d.status = 'active'
            ");
            $stmt->execute([$userId, $deskId]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verifica si un usuario es manager de una mesa
     */
    public function isManager(int $userId, int $deskId = null): bool
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM desks WHERE manager_id = ? AND status = 'active'";
            $params = [$userId];
            
            if ($deskId) {
                $sql .= " AND id = ?";
                $params[] = $deskId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene los IDs de mesas a las que un usuario tiene acceso
     */
    public function getAccessibleDeskIds(int $userId): array
    {
        try {
            // Obtener mesas donde es usuario asignado
            $stmt = $this->db->prepare("
                SELECT DISTINCT du.desk_id
                FROM desk_users du
                INNER JOIN desks d ON du.desk_id = d.id
                WHERE du.user_id = ? AND d.status = 'active'
                
                UNION
                
                SELECT DISTINCT d.id as desk_id
                FROM desks d
                WHERE d.manager_id = ? AND d.status = 'active'
            ");
            $stmt->execute([$userId, $userId]);
            $results = $stmt->fetchAll();
            
            return array_column($results, 'desk_id');
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Filtra una consulta SQL para incluir solo las mesas accesibles
     */
    public function addDeskFilter(string $sql, int $userId, string $deskTableAlias = 'd'): array
    {
        $accessibleDesks = $this->getAccessibleDeskIds($userId);
        
        if (empty($accessibleDesks)) {
            // Si no tiene acceso a ninguna mesa, retornar consulta que no devuelve resultados
            return [
                'sql' => $sql . " AND 1 = 0",
                'params' => []
            ];
        }
        
        $placeholders = str_repeat('?,', count($accessibleDesks) - 1) . '?';
        $filteredSql = $sql . " AND {$deskTableAlias}.id IN ({$placeholders})";
        
        return [
            'sql' => $filteredSql,
            'params' => $accessibleDesks
        ];
    }

    /**
     * Verifica si un usuario tiene un permiso específico
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM users u
                INNER JOIN user_roles ur ON u.id = ur.user_id
                INNER JOIN roles r ON ur.role_id = r.id
                INNER JOIN role_permissions rp ON r.id = rp.role_id
                INNER JOIN permissions p ON rp.permission_id = p.id
                WHERE u.id = ? AND p.name = ? AND u.status = 'active' AND r.status = 'active'
            ");
            $stmt->execute([$userId, $permission]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verifica si un usuario es administrador (tiene permisos globales)
     */
    public function isAdmin(int $userId): bool
    {
        return $this->hasPermission($userId, 'system.admin') || 
               $this->hasPermission($userId, 'system.settings');
    }
}