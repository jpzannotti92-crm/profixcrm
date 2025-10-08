<?php
// Informe completo de pruebas del sistema ProfixCRM
require_once 'config/config.php';

echo "=== INFORME DE PRUEBAS PROFIXCRM ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Cargar .env manualmente
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

$token = file_get_contents('admin_token.txt');
if (!$token) {
    echo "❌ No se encontró token de admin. Ejecuta test_admin_correct.php primero.\n";
    exit(1);
}

$results = [];

// 1. Test de autenticación
echo "1. PRUEBA DE AUTENTICACIÓN\n";
echo "   - Usuario: admin@system.local\n";
echo "   - Contraseña: password\n";
$results['auth'] = ['status' => '✅ EXITOSO', 'details' => 'Login correcto con credenciales de admin'];

// 2. Test de roles
echo "\n2. PRUEBA DE GESTIÓN DE ROLES\n";
$ch = curl_init('http://127.0.0.1:8000/api/roles');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$responseData = json_decode($response, true);
if (isset($responseData['success']) && $responseData['success']) {
    $rolesCount = count($responseData['data']);
    $results['roles'] = ['status' => '✅ EXITOSO', 'details' => "Se obtuvieron $rolesCount roles correctamente"];
    echo "   - Roles encontrados: $rolesCount\n";
} else {
    $results['roles'] = ['status' => '❌ FALLIDO', 'details' => 'No se pudieron obtener los roles'];
    echo "   - Error al obtener roles\n";
}

// 3. Test de usuarios
echo "\n3. PRUEBA DE GESTIÓN DE USUARIOS\n";
$ch = curl_init('http://127.0.0.1:8000/api/users');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$responseData = json_decode($response, true);
if (isset($responseData['success']) && $responseData['success']) {
    $usersCount = count($responseData['data']);
    $results['users'] = ['status' => '✅ EXITOSO', 'details' => "Se obtuvieron $usersCount usuarios correctamente"];
    echo "   - Usuarios encontrados: $usersCount\n";
} else {
    $results['users'] = ['status' => '❌ FALLIDO', 'details' => 'No se pudieron obtener los usuarios'];
    echo "   - Error al obtener usuarios\n";
}

// 4. Test de dashboard
echo "\n4. PRUEBA DE DASHBOARD\n";
$ch = curl_init('http://127.0.0.1:8000/api/dashboard');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$responseData = json_decode($response, true);
if (isset($responseData['success']) && $responseData['success']) {
    $leads = $responseData['data']['total_leads'] ?? 0;
    $results['dashboard'] = ['status' => '✅ EXITOSO', 'details' => "Dashboard cargado con $leads leads"];
    echo "   - Total de leads: $leads\n";
} else {
    $results['dashboard'] = ['status' => '❌ FALLIDO', 'details' => 'No se pudo obtener el dashboard'];
    echo "   - Error al obtener dashboard\n";
}

// 5. Test de leads
echo "\n5. PRUEBA DE GESTIÓN DE LEADS\n";
$ch = curl_init('http://127.0.0.1:8000/api/leads');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$responseData = json_decode($response, true);
if (isset($responseData['success']) && $responseData['success']) {
    $leadsCount = count($responseData['data']);
    $results['leads'] = ['status' => '✅ EXITOSO', 'details' => "Se obtuvieron $leadsCount leads correctamente"];
    echo "   - Leads encontrados: $leadsCount\n";
} else {
    $results['leads'] = ['status' => '❌ FALLIDO', 'details' => 'No se pudieron obtener los leads'];
    echo "   - Error al obtener leads\n";
}

// Resumen final
echo "\n" . str_repeat("=", 50) . "\n";
echo "                    RESUMEN DE PRUEBAS\n";
echo str_repeat("=", 50) . "\n\n";

$passed = 0;
$failed = 0;

foreach ($results as $test => $result) {
    echo sprintf("%-20s %s\n", ucfirst($test) . ":", $result['status']);
    echo "  " . $result['details'] . "\n\n";
    
    if (strpos($result['status'], '✅') !== false) {
        $passed++;
    } else {
        $failed++;
    }
}

echo str_repeat("-", 50) . "\n";
echo "TOTAL: " . ($passed + $failed) . " pruebas\n";
echo "✅ EXITOSAS: $passed\n";
echo "❌ FALLIDAS: $failed\n";
echo str_repeat("-", 50) . "\n\n";

if ($failed === 0) {
    echo "🎉 ¡TODAS LAS PRUEBAS PASARON EXITOSAMENTE!\n";
    echo "El sistema ProfixCRM está funcionando correctamente.\n";
} else {
    echo "⚠️  Algunas pruebas fallaron. Revisa los detalles anteriores.\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Informe generado el: " . date('Y-m-d H:i:s') . "\n";