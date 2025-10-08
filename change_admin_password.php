<?php
// Cambia la contraseña del usuario admin de forma segura
$new = $argv[1] ?? 'ChangeMe!2025';
$user = $argv[2] ?? 'admin';

$env = __DIR__ . '/.env';
$cfg = ['host' => 'localhost', 'db' => 'iatrade_crm', 'user' => 'root', 'pass' => ''];
if (is_file($env)) {
  foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(ltrim($line), '#') === 0) continue;
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
      $k = trim($parts[0]);
      $v = trim($parts[1], " \t\n\r\0\x0B\"'" );
      if ($k === 'DB_HOST') $cfg['host'] = $v;
      if ($k === 'DB_DATABASE' || $k === 'DB_NAME') $cfg['db'] = $v;
      if ($k === 'DB_USERNAME' || $k === 'DB_USER') $cfg['user'] = $v;
      if ($k === 'DB_PASSWORD') $cfg['pass'] = $v;
    }
  }
}

try {
  $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['db']};charset=utf8mb4", $cfg['user'], $cfg['pass']);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $hash = password_hash($new, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
  $stmt->execute([$hash, $user]);

  echo "Contraseña actualizada para {$user}.\n";
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
  exit(1);
}
?>