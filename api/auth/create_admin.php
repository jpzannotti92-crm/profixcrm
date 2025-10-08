<?php
// Alias hacia el endpoint público
$publicPath = __DIR__ . '/../../public/api/auth/create_admin.php';
if (file_exists($publicPath)) {
    require_once $publicPath;
    return;
}
http_response_code(500);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Endpoint create_admin no disponible']);