<?php
header('Content-Type: text/plain; charset=utf-8');
echo "Docroot diagnostics\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'n/a') . "\n";
echo "CWD: " . getcwd() . "\n\n";

$candidates = [
  __DIR__ . '/assets/index-e48ba3e7.js',
  __DIR__ . '/assets/index-4b927e79.css',
  __DIR__ . '/../assets/index-e48ba3e7.js',
  __DIR__ . '/../assets/index-4b927e79.css'
];

foreach ($candidates as $p) {
  $exists = is_file($p) ? 'YES' : 'NO';
  echo "$p => exists: $exists\n";
}

echo "\nListing /assets under __DIR__:\n";
$assetsDir = __DIR__ . '/assets';
if (is_dir($assetsDir)) {
  foreach (glob($assetsDir . '/*') as $f) {
    echo basename($f) . " (" . (is_file($f) ? filesize($f) . ' bytes' : 'dir') . ")\n";
  }
} else {
  echo "No assets dir at " . $assetsDir . "\n";
}

echo "\nListing /assets one-level up:\n";
$assetsUp = __DIR__ . '/../assets';
if (is_dir($assetsUp)) {
  foreach (glob($assetsUp . '/*') as $f) {
    echo basename($f) . " (" . (is_file($f) ? filesize($f) . ' bytes' : 'dir') . ")\n";
  }
} else {
  echo "No assets dir at " . $assetsUp . "\n";
}

echo "\nDone.\n";
?>