<?php
require 'e:/xampp/htdocs/eportal/api/config.php';
$db = db();
try {
    $stmt = $db->prepare("
                SELECT s.id as student_id, s.nis, s.nama as nama_siswa, s.kelas,
                       es.id as sesi_id, es.status, es.waktu_mulai, es.waktu_selesai, es.nilai_akhir as skor, es.pelanggaran
                FROM exam_ujian_kelas uk
                JOIN students s ON s.kelas = uk.kelas AND s.status = 1
                LEFT JOIN exam_sesi es ON es.student_id = s.id AND es.ujian_id = uk.ujian_id
                WHERE uk.ujian_id = 1
                ORDER BY s.kelas ASC, s.nama ASC
    ");
    $stmt->execute();
    echo "Query OK\n";
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo "Query Error: " . $e->getMessage() . "\n";
}
