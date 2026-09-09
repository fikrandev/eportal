<?php
/**
 * Siswa App - BK API
 * Mengambil catatan dari guru BK (Tabel acad_buku_penghubung)
 */
require_once __DIR__ . '/auth_helper.php';
$siswa = siswa_auth();

$active_year = get_active_academic_year();
$year_id = $active_year ? $active_year['id'] : 0;

try {
    $stmt = db()->prepare("
        SELECT b.*, u.nama_lengkap as dicatat_nama, COALESCE(t.warna_badge, 'badge-info') as warna_badge,
               t.deskripsi as jenis_deskripsi, k.nama_kelas
        FROM acad_buku_penghubung b 
        LEFT JOIN users u ON b.dicatat_oleh = u.id
        LEFT JOIN sch_kelas k ON b.kelas_id = k.id
        LEFT JOIN acad_buku_types t ON b.jenis = t.nama_jenis
        WHERE b.student_id = ? AND (b.academic_year_id = ? OR b.academic_year_id IS NULL OR b.academic_year_id = 0)
        ORDER BY b.tanggal DESC, b.id DESC
    ");
    $stmt->execute([$siswa['id'], $year_id]);
    $catatan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format tanggal
    foreach ($catatan as &$c) {
        $c['tanggal_indo'] = format_tanggal($c['tanggal']);
    }

    json_response(200, true, 'Catatan BK loaded', $catatan);
} catch (PDOException $e) {
    json_response(500, false, 'Database Error: ' . $e->getMessage());
}
