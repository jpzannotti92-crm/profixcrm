<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Cargar autoloader de Composer
require_once '../../vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Cargar archivos necesarios
require_once '../../src/Database/Connection.php';
require_once '../../src/Models/BaseModel.php';
require_once '../../src/Models/User.php';
require_once '../../src/Middleware/RBACMiddleware.php';
require_once '../../src/Core/Request.php';

use iaTradeCRM\Database\Connection;
use IaTradeCRM\Models\User;
use IaTradeCRM\Middleware\RBACMiddleware;
use IaTradeCRM\Core\Request;

try {
    $db = Connection::getInstance();
    
    // Inicializar middleware RBAC
    $rbacMiddleware = new RBACMiddleware();
    $request = new Request();
    
    // Debug: Mostrar información de headers
    $debugInfo = [
        'headers' => $request->getHeaders(),
        'auth_header' => $request->getHeader('authorization'),
        'server_auth' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'No encontrado',
        'cookies' => $_COOKIE,
        'method' => $_SERVER['REQUEST_METHOD']
    ];
    
    // Intentar autenticar usuario
    $authResult = $rbacMiddleware->handle($request);
    
    if ($authResult === true) {
        $currentUser = $request->user;
        
        echo json_encode([
            'success' => true,
            'message' => 'Autenticación exitosa',
            'debug' => $debugInfo,
            'user' => [
                'id' => $currentUser->id,
                'username' => $currentUser->username,
                'email' => $currentUser->email,
                'status' => $currentUser->status
            ],
            'permissions' => $currentUser->getPermissions(),
            'roles' => $currentUser->getRoles()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error de autenticación',
            'debug' => $debugInfo,
            'auth_result' => $authResult
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => $debugInfo ?? [],
        'trace' => $e->getTraceAsString()
    ]);
}
?>