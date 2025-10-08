<?php

namespace IaTradeCRM\Models;

use iaTradeCRM\Database\Connection;
use PDO;

class User extends BaseModel
{
    protected $table = 'users';
    protected $fillable = [
        'username', 'email', 'password_hash', 'first_name', 'last_name',
        'phone', 'avatar', 'status', 'last_login', 'login_attempts',
        'locked_until', 'email_verified', 'email_verification_token',
        'password_reset_token', 'password_reset_expires'
    ];

    /**
     * Buscar usuario por username o email
     */
    public static function findByUsernameOrEmail($identifier)
    {
        $db = Connection::getInstance();
        
        $stmt = $db->getConnection()->prepare("
            SELECT * FROM users 
            WHERE username = :identifier OR email = :identifier 
            LIMIT 1
        ");
        
        $stmt->execute(['identifier' => $identifier]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            return null;
        }
        
        return self::hydrate($userData);
    }

    /**
     * Obtener usuario con roles
     */
    public static function findWithRoles($identifier)
    {
        $db = Connection::getInstance();
        
        $stmt = $db->getConnection()->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.username = :identifier OR u.email = :identifier
            LIMIT 1
        ");
        
        $stmt->execute(['identifier' => $identifier]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            return null;
        }
        
        return self::hydrate($userData);
    }

    /**
     * Obtener permisos del usuario
     */
    public function getPermissions()
    {
        $db = Connection::getInstance();
        
        $stmt = $db->getConnection()->prepare("
            SELECT DISTINCT p.name, p.description
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            INNER JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = :user_id
        ");
        
        $stmt->execute(['user_id' => $this->id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener roles del usuario
     */
    public function getRoles()
    {
        $db = Connection::getInstance();
        
        $stmt = $db->getConnection()->prepare("
            SELECT DISTINCT r.name, r.description
            FROM roles r
            INNER JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
        ");
        
        $stmt->execute(['user_id' => $this->id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si el usuario tiene un rol específico
     */
    public function hasRole($roleName)
    {
        $db = Connection::getInstance();
        
        $stmt = $db->getConnection()->prepare("
            SELECT COUNT(*) 
            FROM user_roles ur
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = :user_id AND r.name = :role_name
        ");
        
        $stmt->execute([
            'user_id' => $this->id,
            'role_name' => $roleName
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission($permissionName)
    {
        // Los super administradores tienen acceso total
        if ($this->isSuperAdmin()) {
            return true;
        }
        $db = Connection::getInstance();
        
        $stmt = $db->getConnection()->prepare("
            SELECT COUNT(*) 
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            INNER JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = :user_id AND p.name = :permission_name
        ");
        
        $stmt->execute([
            'user_id' => $this->id,
            'permission_name' => $permissionName
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Asignar rol al usuario
     */
    public function assignRole($roleName)
    {
        $db = Connection::getInstance();
        
        $stmt = $db->getConnection()->prepare("
            INSERT INTO user_roles (user_id, role_id) 
            SELECT :user_id, id FROM roles WHERE name = :role_name
        ");
        
        return $stmt->execute([
            'user_id' => $this->id,
            'role_name' => $roleName
        ]);
    }

    /**
     * Verificar si el usuario es super admin
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Verificar si el usuario es admin
     */
    public function isAdmin()
    {
        return $this->hasRole('super_admin') || $this->hasRole('admin');
    }

    /**
     * Verificar si el usuario es manager
     */
    public function isManager()
    {
        return $this->hasRole('super_admin') || $this->hasRole('admin') || $this->hasRole('manager');
    }

    /**
     * Obtener usuario completo con roles y permisos
     */
    public static function findCompleteUser($userId)
    {
        $db = Connection::getInstance();
        
        $stmt = $db->getConnection()->prepare("
            SELECT u.*, 
                   GROUP_CONCAT(DISTINCT r.name) as roles,
                   GROUP_CONCAT(DISTINCT p.name) as permissions
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = :user_id
            GROUP BY u.id
        ");
        
        $stmt->execute(['user_id' => $userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            return null;
        }
        
        return self::hydrate($userData);
    }

    /**
     * Obtener todos los usuarios con roles y permisos
     */
    public static function getAllWithRolesAndPermissions()
    {
        $db = Connection::getInstance();
        
        $stmt = $db->getConnection()->prepare("
            SELECT u.*, 
                   GROUP_CONCAT(DISTINCT r.name) as roles,
                   GROUP_CONCAT(DISTINCT p.name) as permissions
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ");
        
        $stmt->execute();
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = self::hydrate($row);
        }
        
        return $results;
    }

    /**
     * Crear nuevo usuario
     */
    public static function createUser($data)
    {
        // Validar datos requeridos
        $required = ['username', 'email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("El campo {$field} es requerido");
            }
        }

        // Verificar que username y email sean únicos
        if (self::findByUsernameOrEmail($data['username'])) {
            throw new \InvalidArgumentException("El username ya está en uso");
        }

        if (self::findByUsernameOrEmail($data['email'])) {
            throw new \InvalidArgumentException("El email ya está en uso");
        }

        // Hash de la contraseña
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);

        // Valores por defecto
        $data['status'] = $data['status'] ?? 'active';
        $data['email_verified'] = $data['email_verified'] ?? false;

        return self::create($data);
    }

    /**
     * Actualizar contraseña
     */
    public function updatePassword($newPassword)
    {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        return self::update($this->id, [
            'password_hash' => $passwordHash,
            'password_reset_token' => null,
            'password_reset_expires' => null
        ]);
    }

    /**
     * Obtener nombre completo
     */
    public function getFullName()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Convertir a array para API
     */
    public function toArray($includePrivate = false)
    {
        $data = [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->getFullName(),
            'phone' => $this->phone,
            'status' => $this->status,
            'email_verified' => $this->email_verified,
            'last_login' => $this->last_login,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];

        if ($includePrivate) {
            $data['login_attempts'] = $this->login_attempts;
            $data['locked_until'] = $this->locked_until;
        }

        return $data;
    }

    /**
     * Obtener desks del usuario
     */
    public function getDesks()
    {
        $db = Connection::getInstance();
        
        $stmt = $db->getConnection()->prepare("
            SELECT d.* 
            FROM desks d
            INNER JOIN desk_users du ON d.id = du.desk_id
            WHERE du.user_id = :user_id AND d.status = 'active'
        ");
        
        $stmt->execute(['user_id' => $this->id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}