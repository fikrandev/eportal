<?php
/**
 * Siswa App - Dashboard API
 */
require_once __DIR__ . '/auth_helper.php';
$siswa = siswa_auth();

$active_year = get_active_academic_year();
$year_id = $active_year ? $active_year['id'] : 0;
$month = date('m');
$year = date('Y');

// Calculate stats for current month
// 1. Kehadiran (Hadir)
$stmtHadir = db()->prepare("
    SELECT COUNT(*) FROM acad_absensi 
    WHERE student_id = ? AND academic_year_id = ? 
    AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND status = 'H' AND jam_ke = 0
");
$stmtHadir->execute([$siswa['id'], $year_id, $month, $year]);
$hadir = $stmtHadir->fetchColumn();

// 2. Izin
$stmtIzin = db()->prepare("
    SELECT COUNT(*) FROM acad_absensi 
    WHERE student_id = ? AND academic_year_id = ? 
    AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND status = 'I' AND jam_ke = 0
");
$stmtIzin->execute([$siswa['id'], $year_id, $month, $year]);
$izin = $stmtIzin->fetchColumn();

// 3. Sakit
$stmtSakit = db()->prepare("
    SELECT COUNT(*) FROM acad_absensi 
    WHERE student_id = ? AND academic_year_id = ? 
    AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND status = 'S' AND jam_ke = 0
");
$stmtSakit->execute([$siswa['id'], $year_id, $month, $year]);
$sakit = $stmtSakit->fetchColumn();

// 4. Alfa
$stmtAlfa = db()->prepare("
    SELECT COUNT(*) FROM acad_absensi 
    WHERE student_id = ? AND academic_year_id = ? 
    AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND status = 'A' AND jam_ke = 0
");
$stmtAlfa->execute([$siswa['id'], $year_id, $month, $year]);
$alfa = $stmtAlfa->fetchColumn();

json_response(200, true, 'Dashboard loaded', [
    'hadir' => $hadir,
    'izin' => $izin,
    'sakit' => $sakit,
    'alfa' => $alfa
]);
