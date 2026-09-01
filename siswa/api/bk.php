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
        SELECT * FROM acad_buku_penghubung 
        WHERE student_id = ? AND academic_year_id = ?
        ORDER BY tanggal DESC
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
