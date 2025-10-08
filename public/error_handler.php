<?php
// Manejador de errores 404/403 compatible con SPA y API
// Objetivo: evitar páginas de error y servir SPA o JSON según la ruta

// Detectar código de error original si viene de ErrorDocument
$status = 500;
if (isset($_SERVER['REDIRECT_STATUS'])) {
    $status = (int)$_SERVER['REDIRECT_STATUS'];
} elseif (http_response_code()) {
    $status = http_response_code();
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isApi = strpos($uri, '/api/') === 0;

// CORS básico para API
if ($isApi) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status ?: 404);
    echo json_encode([
        'ok' => false,
        'error' => $status === 403 ? 'Forbidden' : 'Not Found',
        'status' => $status,
        'path' => $uri,
        'message' => $status === 403
            ? 'El acceso fue denegado. Verifique credenciales o permisos.'
            : 'Endpoint no encontrado. Verifique la ruta o método.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// SPA fallback: servir index.html para rutas no-API
$spaIndex = __DIR__ . '/index.html';
if (is_file($spaIndex)) {
    header('Content-Type: text/html; charset=UTF-8');
    // No cambiar el código: algunos navegadores/UPstreams requieren conservar 404/403
    // La SPA se encargará de mostrar su propia pantalla de error/404
    readfile($spaIndex);
    exit;
}

// Fallback textual si no existe la SPA
http_response_code($status ?: 404);
header('Content-Type: text/plain; charset=UTF-8');
echo ($status === 403 ? '403 Forbidden' : '404 Not Found') . "\n";
echo 'Ruta: ' . $uri . "\n";
echo 'Sube el build del frontend a public/index.html para servir la SPA.';
exit;