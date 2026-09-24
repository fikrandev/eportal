<?php
require 'e:/xampp/htdocs/eportal/api/config.php';
$db = db();

// Fetch all distinct categories that have been answered in perf_penilaian
$stmt = $db->query("
    SELECT i.target_jabatan, i.kategori, COUNT(p.id) as count_jawaban
    FROM perf_penilaian p
    JOIN perf_instrumen i ON p.instrumen_id = i.id
    GROUP BY i.target_jabatan, i.kategori
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Daftar kategori yang ada jawabannya di tabel perf_penilaian:\n";
foreach ($results as $r) {
    echo "- Jabatan: " . $r['target_jabatan'] . " | Kategori: " . $r['kategori'] . " | Jml Jawaban: " . $r['count_jawaban'] . "\n";
}

echo "\nLogic hardcode di cetak_lampiran.php:\n";
echo "Guru BK punya Kategori Khusus (Kepercayaan, Kreativitas) tapi jika hanya di-filter 'guru' maka yang masuk cuma: Pedagogik, Kepribadian, Sosial, Profesional.\n";

