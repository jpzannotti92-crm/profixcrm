<?php

namespace iaTradeCRM\Database;

use PDO;
use Exception;

/**
 * Clase para manejar compatibilidad entre diferentes versiones de MySQL
 * y configuraciones de sql_mode, especialmente ONLY_FULL_GROUP_BY
 */
class MySQLCompatibility
{
    private $pdo;
    private $version;
    private $sqlMode;
    private $hasOnlyFullGroupBy;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->detectMySQLConfiguration();
    }
    
    /**
     * Detecta la configuración de MySQL automáticamente
     */
    private function detectMySQLConfiguration()
    {
        try {
            // Obtener versión de MySQL
            $stmt = $this->pdo->query("SELECT VERSION() as version");
            $this->version = $stmt->fetchColumn();
            
            // Obtener sql_mode actual
            $stmt = $this->pdo->query("SELECT @@sql_mode as sql_mode");
            $this->sqlMode = $stmt->fetchColumn();
            
            // Verificar si ONLY_FULL_GROUP_BY está activo
            $this->hasOnlyFullGroupBy = strpos($this->sqlMode, 'ONLY_FULL_GROUP_BY') !== false;
            
        } catch (Exception $e) {
            // Valores por defecto en caso de error
            $this->version = '5.7.0';
            $this->sqlMode = '';
            $this->hasOnlyFullGroupBy = false;
        }
    }
    
    /**
     * Genera una consulta de login compatible con la configuración actual de MySQL
     */
    public function getCompatibleLoginQuery($joinCondition = null)
    {
        // Construir condiciones dinámicas según disponibilidad de la columna is_primary
        $hasPrimaryInJoin = is_string($joinCondition) && stripos($joinCondition, 'is_primary') !== false;
        $deskUsersWhere = $hasPrimaryInJoin ? 'du.is_primary = 1' : '1=1';
        $deskUsersJoin = $joinCondition ?: 'u.id = du.user_id';
        
        if ($this->hasOnlyFullGroupBy) {
            // Consulta compatible con ONLY_FULL_GROUP_BY
            return "
                SELECT u.id,
                       u.username,
                       u.email,
                       u.password_hash,
                       u.first_name,
                       u.last_name,
                       u.phone,
                       u.avatar,
                       u.status,
                       u.created_at,
                       u.updated_at,
                       u.last_login,
                       u.email_verified,
                       u.login_attempts,
                       u.locked_until,
                       u.email_verification_token,
                       u.password_reset_token,
                       u.password_reset_expires,
                       desk_info.desk_id,
                       desk_info.desk_name,
                       desk_info.supervisor_first_name,
                       desk_info.supervisor_last_name,
                       role_info.roles,
                       role_info.role_names,
                       permission_info.permissions
                FROM users u
                LEFT JOIN (
                    SELECT du.user_id,
                           d.id as desk_id,
                           d.name as desk_name,
                           supervisor.first_name as supervisor_first_name,
                           supervisor.last_name as supervisor_last_name
                    FROM desk_users du
                    LEFT JOIN desks d ON du.desk_id = d.id
                    LEFT JOIN users supervisor ON d.manager_id = supervisor.id
                    WHERE {$deskUsersWhere}
                ) desk_info ON u.id = desk_info.user_id
                LEFT JOIN (
                    SELECT ur.user_id,
                           GROUP_CONCAT(DISTINCT r.name) as roles,
                           GROUP_CONCAT(DISTINCT r.display_name) as role_names
                    FROM user_roles ur
                    LEFT JOIN roles r ON ur.role_id = r.id
                    GROUP BY ur.user_id
                ) role_info ON u.id = role_info.user_id
                LEFT JOIN (
                    SELECT ur.user_id,
                           GROUP_CONCAT(DISTINCT p.name) as permissions
                    FROM user_roles ur
                    LEFT JOIN role_permissions rp ON ur.role_id = rp.role_id
                    LEFT JOIN permissions p ON rp.permission_id = p.id
                    GROUP BY ur.user_id
                ) permission_info ON u.id = permission_info.user_id
                WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
            ";
        } else {
            // Consulta tradicional para versiones más antiguas o sin ONLY_FULL_GROUP_BY
            // Completamente compatible con ONLY_FULL_GROUP_BY
            return "
                SELECT u.id,
                       u.username,
                       u.email,
                       u.password_hash,
                       u.first_name,
                       u.last_name,
                       u.phone,
                       u.avatar,
                       u.status,
                       u.created_at,
                       u.updated_at,
                       u.last_login,
                       u.email_verified,
                       u.login_attempts,
                       u.locked_until,
                       u.email_verification_token,
                       u.password_reset_token,
                       u.password_reset_expires,
                       d.name as desk_name,
                       d.id as desk_id,
                       supervisor.first_name as supervisor_first_name,
                       supervisor.last_name as supervisor_last_name,
                       GROUP_CONCAT(DISTINCT r.name) as roles,
                       GROUP_CONCAT(DISTINCT r.display_name) as role_names,
                       GROUP_CONCAT(DISTINCT p.name) as permissions
                FROM users u
                LEFT JOIN desk_users du ON {$deskUsersJoin}
                LEFT JOIN desks d ON du.desk_id = d.id
                LEFT JOIN users supervisor ON d.manager_id = supervisor.id
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                LEFT JOIN permissions p ON rp.permission_id = p.id
                WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
                GROUP BY u.id, u.username, u.email, u.password_hash, u.first_name, u.last_name, 
                         u.phone, u.avatar, u.status, u.created_at, u.updated_at, u.last_login, 
                         u.email_verified, u.login_attempts, u.locked_until, u.email_verification_token,
                         u.password_reset_token, u.password_reset_expires,
                         d.id, d.name, 
                         supervisor.id, supervisor.first_name, supervisor.last_name
            ";
        }
    }
    
    /**
     * Obtiene información sobre la configuración actual
     */
    public function getConfigurationInfo()
    {
        return [
            'version' => $this->version,
            'sql_mode' => $this->sqlMode,
            'has_only_full_group_by' => $this->hasOnlyFullGroupBy,
            'compatibility_mode' => $this->hasOnlyFullGroupBy ? 'strict' : 'legacy'
        ];
    }
    
    /**
     * Verifica si una consulta es compatible con la configuración actual
     */
    public function isQueryCompatible($query)
    {
        if (!$this->hasOnlyFullGroupBy) {
            return true; // Sin restricciones
        }
        
        // Verificaciones básicas para ONLY_FULL_GROUP_BY
        $hasGroupBy = stripos($query, 'GROUP BY') !== false;
        $hasAggregate = preg_match('/\b(COUNT|SUM|AVG|MIN|MAX|GROUP_CONCAT)\s*\(/i', $query);
        
        if ($hasGroupBy && $hasAggregate) {
            // Necesita verificación más detallada
            return $this->validateGroupByQuery($query);
        }
        
        return true;
    }
    
    /**
     * Valida una consulta GROUP BY para compatibilidad con ONLY_FULL_GROUP_BY
     */
    private function validateGroupByQuery($query)
    {
        // Implementación básica - en producción se podría hacer más sofisticada
        try {
            $stmt = $this->pdo->prepare("EXPLAIN $query");
            return true; // Si no hay error en EXPLAIN, probablemente es válida
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Sugiere una consulta alternativa si la actual no es compatible
     */
    public function suggestCompatibleQuery($originalQuery)
    {
        if ($this->isQueryCompatible($originalQuery)) {
            return $originalQuery;
        }
        
        // Aquí se podrían implementar transformaciones automáticas
        // Por ahora, retorna la consulta original con un comentario
        return "-- ADVERTENCIA: Esta consulta puede no ser compatible con ONLY_FULL_GROUP_BY\n" . $originalQuery;
    }
    
    /**
     * Configura temporalmente el sql_mode para una sesión
     */
    public function setTemporarySqlMode($mode = null)
    {
        try {
            if ($mode === null) {
                // Modo compatible por defecto
                $mode = str_replace('ONLY_FULL_GROUP_BY,', '', $this->sqlMode);
                $mode = str_replace(',ONLY_FULL_GROUP_BY', '', $mode);
                $mode = str_replace('ONLY_FULL_GROUP_BY', '', $mode);
            }
            
            $this->pdo->exec("SET SESSION sql_mode = '$mode'");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Restaura el sql_mode original
     */
    public function restoreOriginalSqlMode()
    {
        try {
            $this->pdo->exec("SET SESSION sql_mode = '{$this->sqlMode}'");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}