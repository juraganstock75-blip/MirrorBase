<?php
// tools/backfill_uid.php — HAPUS setelah selesai!
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);

$rows = $pdo->query("
    SELECT id FROM archives
    WHERE uid IS NULL OR uid = ''
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_COLUMN);

echo "Total rows tanpa uid: " . count($rows) . "\n\n";

$up = $pdo->prepare('UPDATE archives SET uid = :u WHERE id = :id');
$ok = 0;
foreach ($rows as $id) {
    try {
        $uid = generate_uid($pdo);
        $up->execute([':u' => $uid, ':id' => $id]);
        echo "id={$id} → uid={$uid}\n";
        $ok++;
        flush();
    } catch (Throwable $e) {
        echo "id={$id} GAGAL: " . $e->getMessage() . "\n";
    }
}

echo "\nDone. {$ok} rows updated.\n";
echo "HAPUS file ini sekarang!\n";