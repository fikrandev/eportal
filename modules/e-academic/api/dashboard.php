<?php
/**
 * E-Academic Dashboard API
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
        $stmt = db()->query("SELECT COUNT(*) as total FROM acad_kelas");
        $total_kelas = $stmt->fetch()['total'] ?? 0;

        // Count subjects
        $stmt = db()->query("SELECT COUNT(*) as total FROM acad_mapel");
        $total_mapel = $stmt->fetch()['total'] ?? 0;

        // Count assignments
        $total_mengajar = 0;
        if ($year_id) {
            $stmt = db()->prepare("SELECT COUNT(*) as total FROM acad_mengajar WHERE academic_year_id = ?");
            $stmt->execute([$year_id]);
            $total_mengajar = $stmt->fetch()['total'] ?? 0;
        }

        // Count teachers
        $stmt = db()->query("SELECT COUNT(*) as total FROM users WHERE role = 'guru' AND status = 1");
        $total_guru = $stmt->fetch()['total'] ?? 0;

        json_response(200, true, 'Statistics loaded.', [
            'total_kelas' => (int)$total_kelas,
            'total_mapel' => (int)$total_mapel,
            'total_mengajar' => (int)$total_mengajar,
            'total_guru' => (int)$total_guru,
            'academic_year' => $active_year
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else {
    json_response(400, false, 'Action tidak valid.');
}
