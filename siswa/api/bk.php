<?php
/**
 * Siswa App - BK & Buku Penghubung API
 * Mengambil catatan bimbingan konseling dan buku penghubung siswa
 */
require_once __DIR__ . '/auth_helper.php';
$siswa = siswa_auth();

try {
    $studentId = (int)$siswa['id'];
    $studentNis = (string)$siswa['nis'];

    $stmt = db()->prepare("
        SELECT b.*, 
               COALESCE(u.nama_lengkap, 'Guru BK / Wali Kelas') as dicatat_nama, 
               COALESCE(t.warna_badge, 'badge-info') as warna_badge,
               COALESCE(t.deskripsi, '') as jenis_deskripsi, 
               COALESCE(k.nama_kelas, '') as nama_kelas
        FROM acad_buku_penghubung b 
        LEFT JOIN users u ON b.dicatat_oleh = u.id
        LEFT JOIN sch_kelas k ON b.kelas_id = k.id
        LEFT JOIN acad_buku_types t ON b.jenis = t.nama_jenis
        WHERE b.student_id = ? OR b.student_id IN (SELECT id FROM students WHERE nis = ?)
        ORDER BY b.tanggal DESC, b.id DESC
    ");
    $stmt->execute([$studentId, $studentNis]);
    $catatan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($catatan as &$c) {
        $c['tanggal_indo'] = format_tanggal($c['tanggal']);
    }

    json_response(200, true, 'Catatan BK berhasil dimuat.', $catatan);
} catch (PDOException $e) {
    json_response(500, false, 'Database Error: ' . $e->getMessage());
}
