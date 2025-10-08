<?php
// Production-only front controller to avoid impacting local development.
// Redirects root to /auth/login and serves SPA index.html for /auth/login.
// Delegates the rest to index.php.

// Normalize requested path
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Root: servir la SPA directamente (evita bucles por hash no enviado al servidor)
if ($requestUri === '/' || $requestUri === '/index.php' || $requestUri === '/public/' || $requestUri === '/public/index.php') {
    include __DIR__ . '/index.html';
    exit;
}

// /auth/login: servir SPA
if ($requestUri === '/auth/login' || $requestUri === '/public/auth/login') {
    include __DIR__ . '/index.html';
    exit;
}

// Delegate all other handling to the main controller
require __DIR__ . '/index.php';

?>