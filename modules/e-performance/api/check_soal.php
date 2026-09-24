<?php
require 'e:/xampp/htdocs/eportal/api/config.php';
$db = db();

echo "1. Distinct Target Jabatan (di perf_instrumen):\n";
$stmt1 = $db->query("SELECT DISTINCT target_jabatan FROM perf_instrumen");
while($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {
    echo "- " . $row['target_jabatan'] . "\n";
}

echo "\n2. Distinct Target Dinilai (di perf_instrumen):\n";
$stmt2 = $db->query("SELECT DISTINCT target_dinilai FROM perf_instrumen");
while($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "- " . $row['target_dinilai'] . "\n";
}

echo "\n3. Mapping Kategori ke Target Dinilai:\n";
$stmt3 = $db->query("SELECT target_dinilai, kategori, COUNT(*) as jumlah_soal FROM perf_instrumen GROUP BY target_dinilai, kategori");
while($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    echo "- Target: " . $row['target_dinilai'] . " | Kategori: " . $row['kategori'] . " | Jumlah Soal: " . $row['jumlah_soal'] . "\n";
}

echo "\n4. Contoh Soal per Target:\n";
$stmt4 = $db->query("SELECT target_dinilai, kategori, pertanyaan FROM perf_instrumen LIMIT 10");
while($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
    echo "- [" . $row['target_dinilai'] . "] (" . $row['kategori'] . ") " . substr($row['pertanyaan'], 0, 50) . "...\n";
}
