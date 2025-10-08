<?php
// Cargar .env manualmente
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(ltrim($line), '#') === 0) { continue; }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
}

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

echo "=== DEBUG AUTHENTICATION ISSUE ===\n";

// Get token from current_token.txt
$token = file_get_contents('current_token.txt');
echo "Token from file: " . substr($token, 0, 50) . "...\n";

// Simulate headers
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . trim($token);
echo "Authorization header set: " . $_SERVER['HTTP_AUTHORIZATION'] . "\n";

try {
    // Initialize middleware
    $rbacMiddleware = new RBACMiddleware();
    $request = new Request();
    
    echo "1. Middleware initialized\n";
    
    // Test token extraction using reflection to access private method
    $reflection = new ReflectionClass($rbacMiddleware);
    $method = $reflection->getMethod('extractTokenFromRequest');
    $method->setAccessible(true);
    $extractedToken = $method->invoke($rbacMiddleware, $request);
    echo "2. Extracted token: " . ($extractedToken ? substr($extractedToken, 0, 50) . "..." : "NULL") . "\n";
    
    // Authenticate user
    $authResult = $rbacMiddleware->handle($request, 'users.view', null, true);
    
    if ($authResult !== true) {
        echo "3. Authentication failed: " . json_encode($authResult, JSON_PRETTY_PRINT) . "\n";
        
        // Check if it's a permission issue
        if (isset($authResult['status']) && $authResult['status'] === 403) {
            echo "4. Permission denied - checking user permissions...\n";
            
            // Try to get user info
            $user = User::find(1); // admin user
            if ($user) {
                echo "5. Found user: " . $user->username . "\n";
                echo "6. User permissions: " . json_encode($user->getPermissions(), JSON_PRETTY_PRINT) . "\n";
                echo "7. User roles: " . json_encode($user->getRoles(), JSON_PRETTY_PRINT) . "\n";
            }
        }
        exit();
    }
    
    echo "3. Authentication successful!\n";
    
    $currentUser = $request->user;
    
    if (!$currentUser) {
        echo "4. User not found in request\n";
        exit();
    }
    
    echo "4. User found: " . $currentUser->username . "\n";
    echo "5. User permissions: " . json_encode($currentUser->permissions, JSON_PRETTY_PRINT) . "\n";
    echo "6. User roles: " . json_encode($currentUser->roles, JSON_PRETTY_PRINT) . "\n";
    
    // Check if user has the required permission
    $hasPermission = $currentUser->hasPermission('users.view');
    echo "7. Has 'users.view' permission: " . ($hasPermission ? "YES" : "NO") . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}