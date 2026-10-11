<?php
require_once __DIR__ . '/../api/config.php';
$pdo = db();
foreach (['tanah_foto', 'bangunan_foto', 'ruang_foto', 'sarpras_foto', 'perbaikan_foto'] as $tbl) {
    $stmt = $pdo->query("SELECT foto_path FROM $tbl LIMIT 3");
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "$tbl rows: " . count($rows) . "\n";
    foreach ($rows as $r) {
        $clean = ltrim(str_replace('\\', '/', $r), '/');
        $exists = file_exists(__DIR__ . '/../' . $clean);
        echo "   $r -> exists? " . ($exists ? 'YES' : 'NO') . "\n";
    }
}
$stmt = $pdo->query("SELECT foto_path FROM students WHERE foto_path IS NOT NULL AND foto_path != '' LIMIT 3");
$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "students rows with foto: " . count($rows) . "\n";
foreach ($rows as $r) {
    $clean = ltrim(str_replace('\\', '/', $r), '/');
    $exists = file_exists(__DIR__ . '/../' . $clean);
    echo "   $r -> exists? " . ($exists ? 'YES' : 'NO') . "\n";
}
