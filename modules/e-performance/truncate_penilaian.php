<?php
require_once __DIR__ . '/api/config_perf.php';
try {
    $db = db();
    $stmt = $db->query("TRUNCATE TABLE perf_penilaian");
    echo "Berhasil mengosongkan tabel perf_penilaian.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
