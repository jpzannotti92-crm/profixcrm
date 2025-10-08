<?php
/**
 * Script para actualizar URLs hardcodeadas en archivos de prueba
 */

require_once __DIR__ . '/src/helpers/UrlHelper.php';

use App\Helpers\UrlHelper;

// Obtener configuración dinámica
$urlConfig = UrlHelper::getUrlConfig();
$apiUrl = $urlConfig['api'];
$frontendUrl = $urlConfig['frontend'];

echo "Configuración dinámica detectada:\n";
echo "API URL: $apiUrl\n";
echo "Frontend URL: $frontendUrl\n\n";

// Lista de archivos a actualizar
$filesToUpdate = [
    'test_leads_access.php',
    'test_bulk_assign_with_token.php',
    'test_api_get_lead.php',
    'test_apis_fixed.php',
    'test_permissions_endpoint.php',
    'public/api/test_frontend_real.php',
    'test_api_assignment.php',
    'test_api_get_lead_debug.php',
    'public/api/test_create_simple.php',
    'test_real_login.php',
    'test_leads_simple_api.php',
    'test_api_with_auth.php',
    'test_activities_api.php',
    'test_roles.php',
    'test_bulk_assign_api.php',
    'test_users_api.php',
    'test_roles_simple.php',
    'public/api/simple_login_test.php',
    'public/api/test_login_simple.php',
    'debug_permissions_api.php',
    'test_permissions_curl.php',
    'test_permissions_direct.php',
    'test_activity_api_direct.php',
    'debug_verify_endpoint.php',
    'debug_frontend_permissions.php',
    'public/api/test_frontend_login.php',
    'public/api/test_create_user.php',
    'test_permissions_api.php',
    'test_permissions.php',
    'test_final_leads_access.php',
    'test_complete_login_flow.php',
    'debug_profile_response.php',
    'test_api_get_lead_with_auth.php',
    'test_user_permissions.php',
    'test_permissions_frontend.php',
    'test_auth_debug.php',
    'test_activity_api_with_token.php',
    'test_both_endpoints.php',
    'test_frontend_permissions.php',
    'test_leads_api.php'
];

$htmlFilesToUpdate = [
    'public/test_frontend_login_direct.html',
    'test_react_permissions.html',
    'frontend/test_auth_call.html',
    'test_frontend_permissions.html',
    'test_frontend_real_login.html'
];

$updatedFiles = 0;
$errors = [];

// Actualizar archivos PHP
foreach ($filesToUpdate as $file) {
    $filePath = __DIR__ . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "Archivo no encontrado: $file\n";
        continue;
    }
    
    try {
        $content = file_get_contents($filePath);
        $originalContent = $content;
        
        // Reemplazar URLs hardcodeadas
        $content = str_replace('http://localhost:8000/api', $apiUrl, $content);
        $content = str_replace('http://localhost:8000', $apiUrl, $content);
        $content = str_replace('http://localhost:3000', $frontendUrl, $content);
        
        // Solo escribir si hubo cambios
        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "✓ Actualizado: $file\n";
            $updatedFiles++;
        } else {
            echo "- Sin cambios: $file\n";
        }
        
    } catch (Exception $e) {
        $errors[] = "Error actualizando $file: " . $e->getMessage();
        echo "✗ Error: $file - " . $e->getMessage() . "\n";
    }
}

// Actualizar archivos HTML
foreach ($htmlFilesToUpdate as $file) {
    $filePath = __DIR__ . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "Archivo HTML no encontrado: $file\n";
        continue;
    }
    
    try {
        $content = file_get_contents($filePath);
        $originalContent = $content;
        
        // Reemplazar URLs hardcodeadas en JavaScript
        $content = str_replace("'http://localhost:8000/api'", "'$apiUrl'", $content);
        $content = str_replace('"http://localhost:8000/api"', '"' . $apiUrl . '"', $content);
        $content = str_replace("'http://localhost:8000'", "'$apiUrl'", $content);
        $content = str_replace('"http://localhost:8000"', '"' . $apiUrl . '"', $content);
        $content = str_replace("'http://localhost:3000'", "'$frontendUrl'", $content);
        $content = str_replace('"http://localhost:3000"', '"' . $frontendUrl . '"', $content);
        
        // Solo escribir si hubo cambios
        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "✓ Actualizado HTML: $file\n";
            $updatedFiles++;
        } else {
            echo "- Sin cambios HTML: $file\n";
        }
        
    } catch (Exception $e) {
        $errors[] = "Error actualizando HTML $file: " . $e->getMessage();
        echo "✗ Error HTML: $file - " . $e->getMessage() . "\n";
    }
}

// Actualizar archivos de configuración específicos
$configFiles = [
    'frontend/.env.production' => [
        'VITE_API_URL=http://localhost:8000/api' => "VITE_API_URL=$apiUrl",
        'VITE_BASE_URL=http://localhost:8000' => "VITE_BASE_URL=" . $urlConfig['base']
    ]
];

foreach ($configFiles as $file => $replacements) {
    $filePath = __DIR__ . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "Archivo de configuración no encontrado: $file\n";
        continue;
    }
    
    try {
        $content = file_get_contents($filePath);
        $originalContent = $content;
        
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        
        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "✓ Actualizado config: $file\n";
            $updatedFiles++;
        } else {
            echo "- Sin cambios config: $file\n";
        }
        
    } catch (Exception $e) {
        $errors[] = "Error actualizando config $file: " . $e->getMessage();
        echo "✗ Error config: $file - " . $e->getMessage() . "\n";
    }
}

echo "\n=== RESUMEN ===\n";
echo "Archivos actualizados: $updatedFiles\n";
echo "Errores: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrores encontrados:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}

echo "\n✓ Proceso completado. Todos los archivos de prueba ahora usan URLs dinámicas.\n";