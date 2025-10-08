<?php

namespace IaTradeCRM\Models;

/**
 * Modelo Permission
 * Gestiona los permisos del sistema
 */
class Permission extends BaseModel
{
    protected $table = 'permissions';
    
    protected $fillable = [
        'name', 'display_name', 'description', 'module', 'action'
    ];

    /**
     * Obtiene roles que tienen este permiso
     */
    public function getRoles(): array
    {
        $sql = "SELECT r.* FROM roles r
                INNER JOIN role_permissions rp ON r.id = rp.role_id
                WHERE rp.permission_id = ?";
        
        return Role::query($sql, [$this->id]);
    }

    /**
     * Obtiene permisos por módulo
     */
    public static function getByModule(string $module): array
    {
        return static::all(['module' => $module], 'name ASC');
    }

    /**
     * Obtiene todos los módulos
     */
    public static function getModules(): array
    {
        $sql = "SELECT DISTINCT module FROM permissions ORDER BY module";
        $instance = new static();
        $stmt = $instance->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}