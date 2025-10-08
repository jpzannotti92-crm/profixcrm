<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Database/Connection.php';
require_once __DIR__ . '/src/Models/BaseModel.php';
require_once __DIR__ . '/src/Models/User.php';
require_once __DIR__ . '/src/Middleware/RBACMiddleware.php';
require_once __DIR__ . '/src/Core/Request.php';

use iaTradeCRM\Database\Connection;
use App\Models\User;
use App\Middleware\RBACMiddleware;
use App\Core\Request;

echo "=== DEBUG ROLES AUTHENTICATION ===\n";

// Simular headers de autorización
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJpYXRyYWRlLWNybSIsImF1ZCI6ImlhdHJhZGUtY3JtIiwiaWF0IjoxNzU4NzQ2NTU0LCJleHAiOjE3NTg4MzI5NTQsInVzZXJfaWQiOjEsInVzZXJuYW1lIjoiYWRtaW4iLCJlbWFpbCI6ImFkbWluQGV4YW1wbGUuY29tIiwicm9sZXMiOlsiYWRtaW4iXSwicGVybWlzc2lvbnMiOlsiYWxsIl19.72_BM-K7fW8UqRsz4nDDFKykpYxP8vi_Kk0WzSkgz_s';

try {
    // Inicializar middleware RBAC
    $rbacMiddleware = new RBACMiddleware();
    $request = new Request();
    
    echo "1. Middleware inicializado\n";
    
    // Autenticar usuario
    $authResult = $rbacMiddleware->handle($request);
    
    if ($authResult !== true) {
        echo "2. Autenticación falló: " . json_encode($authResult) . "\n";
        exit();
    }
    
    echo "2. Autenticación exitosa\n";
    
    $currentUser = $request->user;
    
    if (!$currentUser) {
        echo "3. Usuario no encontrado en request\n";
        exit();
    }
    
    echo "3. Usuario encontrado: " . $currentUser->username . "\n";
    
    // Verificar permiso roles.view
    $hasPermission = $currentUser->hasPermission('roles.view');
    echo "4. Permiso 'roles.view': " . ($hasPermission ? 'SÍ' : 'NO') . "\n";
    
    if (!$hasPermission) {
        echo "5. Usuario no tiene permiso 'roles.view'\n";
        
        // Debug adicional
        echo "   - ID Usuario: " . $currentUser->id . "\n";
        echo "   - Username: " . $currentUser->username . "\n";
        echo "   - Email: " . $currentUser->email . "\n";
        
        // Verificar roles
        $db = Connection::getInstance();
        $stmt = $db->prepare("
            SELECT r.name, r.display_name 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$currentUser->id]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   - Roles: " . json_encode($roles) . "\n";
        
        // Verificar permisos directos
        $stmt = $db->prepare("
            SELECT p.name, p.display_name 
            FROM role_permissions rp 
            JOIN permissions p ON rp.permission_id = p.id 
            JOIN user_roles ur ON rp.role_id = ur.role_id 
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$currentUser->id]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   - Permisos: " . json_encode($permissions) . "\n";
    } else {
        echo "5. Usuario tiene permiso 'roles.view' - OK\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}