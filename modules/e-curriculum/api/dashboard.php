<?php
/**
 * E-Curriculum Dashboard API
 */
require_once __DIR__ . '/auth_helper.php';

// Check auth
$user = acad_auth();

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'stats') {
    try {
        // Active academic year
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        // Count classes
        $stmt = db()->query("SELECT COUNT(*) as total FROM sch_kelas");
        $total_kelas = $stmt->fetch()['total'] ?? 0;

        // Count subjects
        $stmt = db()->query("SELECT COUNT(*) as total FROM sch_mapel");
        $total_mapel = $stmt->fetch()['total'] ?? 0;

        // Count assignments
        $stmt = db()->query("SELECT COUNT(*) as total FROM sch_distribusi");
        $total_mengajar = $stmt->fetch()['total'] ?? 0;

        // Count teachers
        $stmt = db()->query("SELECT COUNT(*) as total FROM users WHERE role = 'guru' AND status = 1");
        $total_guru = $stmt->fetch()['total'] ?? 0;

        // Count students
        $stmt = db()->query("SELECT COUNT(*) as total FROM students WHERE status = 1");
        $total_siswa = $stmt->fetch()['total'] ?? 0;

        // Jurnal hari ini
        $today = date('Y-m-d');
        $stmt = db()->prepare("SELECT COUNT(*) as total FROM acad_jurnal WHERE tanggal = ?");
        $stmt->execute([$today]);
        $jurnal_hari_ini = $stmt->fetch()['total'] ?? 0;

        // Ketidakhadiran hari ini
        $stmt = db()->prepare("SELECT COUNT(*) as total FROM acad_ketidakhadiran WHERE tanggal = ?");
        $stmt->execute([$today]);
        $tidak_hadir_hari_ini = $stmt->fetch()['total'] ?? 0;

        json_response(200, true, 'Statistics loaded.', [
            'total_kelas' => (int)$total_kelas,
            'total_mapel' => (int)$total_mapel,
            'total_mengajar' => (int)$total_mengajar,
            'total_guru' => (int)$total_guru,
            'total_siswa' => (int)$total_siswa,
            'jurnal_hari_ini' => (int)$jurnal_hari_ini,
            'tidak_hadir_hari_ini' => (int)$tidak_hadir_hari_ini,
            'academic_year' => $active_year
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else {
    json_response(400, false, 'Action tidak valid.');
}
