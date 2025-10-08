<?php

namespace IaTradeCRM\Models;

/**
 * Modelo Role
 * Gestiona los roles del sistema
 */
class Role extends BaseModel
{
    protected $table = 'roles';
    
    protected $fillable = [
        'name', 'display_name', 'description', 'level'
    ];

    /**
     * Obtiene los permisos del rol
     */
    public function getPermissions(): array
    {
        $sql = "SELECT p.* FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?";
        
        return Permission::query($sql, [$this->id]);
    }

    /**
     * Asigna un permiso al rol
     */
    public function assignPermission(int $permissionId): bool
    {
        $existing = $this->db->query(
            "SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?",
            [$this->id, $permissionId]
        )->fetch();

        if ($existing) {
            return true;
        }

        return $this->db->insert('role_permissions', [
            'role_id' => $this->id,
            'permission_id' => $permissionId
        ]) > 0;
    }

    /**
     * Remueve un permiso del rol
     */
    public function removePermission(int $permissionId): bool
    {
        $sql = "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?";
        $stmt = $this->db->query($sql, [$this->id, $permissionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Obtiene usuarios con este rol
     */
    public function getUsers(): array
    {
        $sql = "SELECT u.* FROM users u
                INNER JOIN user_roles ur ON u.id = ur.user_id
                WHERE ur.role_id = ?";
        
        return User::query($sql, [$this->id]);
    }
}