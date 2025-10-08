<?php
/**
 * Depuración detallada del importador mejorado
 */

// Crear archivo CSV temporal con pocos datos para prueba
$testData = 'first_name,last_name,email,phone,country,city,company,job_title
Juan,Pérez,juan.perez@test.com,+34123456789,España,Madrid,TechCorp,Developer
María,Gómez,maria.gomez@test.com,+34987654321,España,Barcelona,WebCo,Designer
Carlos,López,carlos.lopez@test.com,+34567891234,España,Valencia,AppInc,Manager';

$tempFile = tempnam(sys_get_temp_dir(), 'debug_leads_') . '.csv';
file_put_contents($tempFile, $testData);

echo "=== ARCHIVO DE PRUEBA CREADO ===\n";
echo "Ruta: $tempFile\n";
echo "Contenido:\n$testData\n\n";

// Preparar datos POST
$_POST = [
    'mapping' => json_encode([
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'email' => 'email',
        'phone' => 'phone',
        'country' => 'country',
        'city' => 'city',
        'company' => 'company',
        'job_title' => 'job_title'
    ])
];

// Crear array $_FILES
$_FILES = [
    'file' => [
        'name' => 'debug_test.csv',
        'type' => 'text/csv',
        'tmp_name' => $tempFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempFile)
    ]
];

$_SERVER['REQUEST_METHOD'] = 'POST';

echo "=== CONFIGURACIÓN DE PRUEBA ===\n";
echo "POST: " . json_encode($_POST, JSON_PRETTY_PRINT) . "\n";
echo "FILES: " . json_encode($_FILES, JSON_PRETTY_PRINT) . "\n\n";

// Verificar conexión a base de datos
try {
    require_once __DIR__ . '/../../src/Database/Connection.php';
    
    use iaTradeCRM\Database\Connection;
    
    $connection = Connection::getInstance();
    $pdo = $connection->getConnection();
    
    echo "✅ Conexión a base de datos exitosa\n";
    
    // Contar leads antes
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM leads');
    $before = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Leads antes de importar: {$before['total']}\n\n";
    
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
    exit;
}

// Incluir el importador con debugging
echo "=== EJECUTANDO IMPORTADOR ===\n";

// Capturar todos los errores y advertencias
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
$originalErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "⚠️  Error detectado: [$errno] $errstr en $errfile:$errline\n";
    return true;
});

try {
    require_once 'importador_mejorado.php';
} catch (Exception $e) {
    echo "❌ Excepción capturada: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

$output = ob_get_clean();
restore_error_handler();

echo "=== SALIDA DEL IMPORTADOR ===\n";
echo "Raw output: $output\n\n";

// Analizar resultado
$result = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✅ JSON decodificado correctamente\n";
    echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
    echo "Message: {$result['message']}\n";
    echo "Data: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n\n";
    
    // Contar leads después
    try {
        $stmt = $pdo->query('SELECT COUNT(*) as total FROM leads');
        $after = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "📊 RESULTADO FINAL:\n";
        echo "Leads antes: {$before['total']}\n";
        echo "Leads después: {$after['total']}\n";
        echo "Leads nuevos: " . ($after['total'] - $before['total']) . "\n";
        
        // Verificar últimos leads insertados
        if ($after['total'] > $before['total']) {
            echo "\n=== ÚLTIMOS LEADS INSERTADOS ===\n";
            $stmt = $pdo->query('SELECT id, first_name, last_name, email FROM leads ORDER BY id DESC LIMIT 5');
            $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($recent as $lead) {
                echo "- [{$lead['id']}] {$lead['first_name']} {$lead['last_name']} ({$lead['email']})\n";
            }
        }
        
    } catch (Exception $e) {
        echo "Error al verificar leads: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ Error decodificando JSON: " . json_last_error_msg() . "\n";
}

// Limpiar
unlink($tempFile);
echo "\n=== Archivo temporal eliminado ===\n";