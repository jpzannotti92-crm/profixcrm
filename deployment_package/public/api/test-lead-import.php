<?php
header('Content-Type: application/json');

// Simple test without includes
echo json_encode([
    'success' => true,
    'message' => 'Test endpoint working',
    'timestamp' => date('Y-m-d H:i:s')
]);