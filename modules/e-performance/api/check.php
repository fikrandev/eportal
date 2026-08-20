<?php
require __DIR__ . '/../../../api/config.php';
$stmt = db()->query("SELECT * FROM perf_jenis_penilai");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
