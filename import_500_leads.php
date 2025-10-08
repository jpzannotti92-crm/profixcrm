<?php
// Importar 500 leads usando el endpoint de importación masiva
header('Content-Type: text/plain');

echo "=== IMPORTACIÓN DE 500 LEADS ===\n\n";

// Leer los leads generados
$leadsJson = file_get_contents('500_leads.json');
$leads = json_decode($leadsJson, true);

echo "Leídos " . count($leads) . " leads del archivo JSON\n";

// Preparar datos para el endpoint
$data = [
    'leads' => $leads,
    'skipDuplicates' => true
];

// Convertir a JSON
$jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

echo "Enviando a endpoint de importación masiva...\n";

// Configurar cURL
$ch = curl_init('http://localhost/profixcrm/public/api/test_mass_import.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);

// Ejecutar la solicitud
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "\n=== RESPUESTA DEL SERVIDOR ===\n";
echo "Código HTTP: $httpCode\n";

if ($error) {
    echo "Error cURL: $error\n";
}

if ($response) {
    echo "Respuesta:\n";
    echo $response;
    
    // Intentar decodificar JSON
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo "\n\n=== RESUMEN DE IMPORTACIÓN ===\n";
        echo "Importados: " . ($responseData['imported'] ?? 'N/A') . "\n";
        echo "Errores: " . ($responseData['errors'] ?? 'N/A') . "\n";
        echo "Duplicados: " . ($responseData['duplicates'] ?? 'N/A') . "\n";
        echo "Total procesados: " . ($responseData['total'] ?? 'N/A') . "\n";
        echo "Total en BD: " . ($responseData['total_in_db'] ?? 'N/A') . "\n";
        
        if (!empty($responseData['error_details'])) {
            echo "\nDetalles de errores:\n";
            foreach ($responseData['error_details'] as $error) {
                echo "- $error\n";
            }
        }
    }
} else {
    echo "No se recibió respuesta del servidor\n";
}

echo "\n=== FIN DE IMPORTACIÓN ===\n";
?>