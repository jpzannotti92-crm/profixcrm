<?php
// Simple CLI diagnostic runner: login to get token, then call diag_rbac_check.php
function http_request($method, $url, $headers = [], $body = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if (!empty($headers)) {
        $hdrs = [];
        foreach ($headers as $k => $v) { $hdrs[] = $k . ': ' . $v; }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $resp, $err];
}

// 1) Login - probar múltiples rutas posibles
$loginUrls = [
    'http://localhost/profixcrm/public/api/login.php',
    'http://localhost/profixcrm/api/login.php',
    'http://localhost/public/api/login.php',
    'http://localhost/api/login.php'
];
$token = null;
$lastResp = '';
$lastErr = '';
foreach ($loginUrls as $url) {
    [$status, $resp, $err] = http_request('POST', $url, [
        'Content-Type' => 'application/json'
    ], json_encode(['username' => 'admin', 'password' => 'password']));
    $lastResp = $resp; $lastErr = $err;
    $data = json_decode($resp, true);
    if ($err) { continue; }
    if ($data && !empty($data['success']) && !empty($data['token'])) {
        $token = $data['token'];
        break;
    }
}
if (!$token) {
    fwrite(STDERR, "Login failed. Last response: $lastResp\n");
    if ($lastErr) fwrite(STDERR, "Error: $lastErr\n");
    exit(1);
}

// 2) Call diag_rbac_check
// 2) Call diag_rbac_check - probar múltiples rutas
$diagUrls = [
    'http://localhost/profixcrm/public/api/diag_rbac_check.php',
    'http://localhost/profixcrm/api/diag_rbac_check.php',
    'http://localhost/public/api/diag_rbac_check.php',
    'http://localhost/api/diag_rbac_check.php'
];
$dResp = null; $dErr = '';
foreach ($diagUrls as $dUrl) {
    [$dStatus, $resp2, $err2] = http_request('GET', $dUrl, [
        'Authorization' => 'Bearer ' . $token
    ]);
    if ($err2) { $dErr = $err2; continue; }
    if ($resp2) { $dResp = $resp2; break; }
}
if (!$dResp) {
    fwrite(STDERR, "Diag failed. Error: $dErr\n");
    exit(1);
}

echo $dResp;
?>