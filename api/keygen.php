<?php
// api/keygen.php — generate 3 API key. HAPUS setelah dipakai!
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$keysFile = __DIR__ . '/keys.json';

$keys = [];
if (is_file($keysFile)) {
    $raw = @file_get_contents($keysFile);
    $keys = $raw ? json_decode($raw, true) : [];
    if (!is_array($keys)) $keys = [];
}

if (empty($keys['main'])) {
    $keys['main'] = [
        'key'        => bin2hex(random_bytes(32)),
        'label'      => 'main key',
        'created_at' => date('c'),
        'active'     => true,
    ];
}
if (empty($keys['mobile'])) {
    $keys['mobile'] = [
        'key'        => bin2hex(random_bytes(32)),
        'label'      => 'mobile client',
        'created_at' => date('c'),
        'active'     => true,
    ];
}
if (empty($keys['ci'])) {
    $keys['ci'] = [
        'key'        => bin2hex(random_bytes(32)),
        'label'      => 'ci/cd pipeline',
        'created_at' => date('c'),
        'active'     => true,
    ];
}

@file_put_contents(
    $keysFile,
    json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);
@chmod($keysFile, 0600);

echo "===========================================\n";
echo " API KEYS — SIMPAN DI TEMPAT AMAN\n";
echo "===========================================\n\n";

foreach ($keys as $name => $info) {
    echo "Label    : {$info['label']}\n";
    echo "Key      : {$info['key']}\n";
    echo "Created  : {$info['created_at']}\n";
    echo "Status   : " . ($info['active'] ? 'active' : 'disabled') . "\n";
    echo "-------------------------------------------\n";
}

echo "\nPENTING:\n";
echo "1. Copy key ke password manager\n";
echo "2. HAPUS api/keygen.php SEKARANG\n";
echo "3. keys.json jangan di-share\n";