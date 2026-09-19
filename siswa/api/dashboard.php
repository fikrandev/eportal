<?php
/**
 * Siswa App - Dashboard API
 */
require_once __DIR__ . '/auth_helper.php';
$siswa = siswa_auth();

$month = date('m');
$year = date('Y');

$studentId = (int)$siswa['id'];
$studentNis = (string)$siswa['nis'];
$cleanPin = ltrim($studentNis, '0');

try {
    // 1. Kehadiran (Hadir) dari acad_absensi
    $stmtHadir = db()->prepare("
        SELECT COUNT(DISTINCT tanggal) FROM acad_absensi 
        WHERE (student_id = ? OR student_id = ?) 
        AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND status = 'H'
    ");
    $stmtHadir->execute([$studentId, $studentNis, $month, $year]);
    $hadir = (int)$stmtHadir->fetchColumn();

    // Jika belum ada input guru di acad_absensi, hitung dari absen_logs mesin
    if ($hadir === 0) {
        $stmtLogs = db()->prepare("
            SELECT COUNT(DISTINCT DATE(waktu_absen)) FROM absen_logs 
            WHERE (mesin_pin = ? OR mesin_pin = ?) 
            AND MONTH(waktu_absen) = ? AND YEAR(waktu_absen) = ?
        ");
        $stmtLogs->execute([$cleanPin, $studentNis, $month, $year]);
        $hadir = (int)$stmtLogs->fetchColumn();
    }

    // 2. Izin (dari acad_absensi atau acad_izin_siswa yang Disetujui/Approved)
    $stmtIzin = db()->prepare("
        SELECT COUNT(DISTINCT tanggal) FROM acad_absensi 
        WHERE (student_id = ? OR student_id = ?) 
        AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND status = 'I'
    ");
    $stmtIzin->execute([$studentId, $studentNis, $month, $year]);
    $izin = (int)$stmtIzin->fetchColumn();

    if ($izin === 0) {
        try {
            $stmtIzinApp = db()->prepare("
                SELECT COUNT(DISTINCT tanggal) FROM acad_izin_siswa 
                WHERE (student_id = ? OR student_id = ?) 
                AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND (status = 'Disetujui' OR status = 'Approved') AND jenis = 'Izin'
            ");
            $stmtIzinApp->execute([$studentId, $studentNis, $month, $year]);
            $izin = (int)$stmtIzinApp->fetchColumn();
        } catch (Exception $e) {}
    }

    // 3. Sakit
    $stmtSakit = db()->prepare("
        SELECT COUNT(DISTINCT tanggal) FROM acad_absensi 
        WHERE (student_id = ? OR student_id = ?) 
        AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND status = 'S'
    ");
    $stmtSakit->execute([$studentId, $studentNis, $month, $year]);
    $sakit = (int)$stmtSakit->fetchColumn();

    if ($sakit === 0) {
        try {
            $stmtSakitApp = db()->prepare("
                SELECT COUNT(DISTINCT tanggal) FROM acad_izin_siswa 
                WHERE (student_id = ? OR student_id = ?) 
                AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND (status = 'Disetujui' OR status = 'Approved') AND jenis = 'Sakit'
            ");
            $stmtSakitApp->execute([$studentId, $studentNis, $month, $year]);
            $sakit = (int)$stmtSakitApp->fetchColumn();
        } catch (Exception $e) {}
    }

    // 4. Alfa
    $stmtAlfa = db()->prepare("
        SELECT COUNT(DISTINCT tanggal) FROM acad_absensi 
        WHERE (student_id = ? OR student_id = ?) 
        AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND status = 'A'
    ");
    $stmtAlfa->execute([$studentId, $studentNis, $month, $year]);
    $alfa = (int)$stmtAlfa->fetchColumn();

    // Fetch Wali Kelas and Guru Wali
    $guru_wali = $siswa['guru_wali'] ?? '-';
    $wali_kelas = '-';
    
    if (!empty($siswa['kelas'])) {
        try {
            $stmtWali = db()->prepare("
                SELECT u.nama_lengkap 
                FROM acad_kelas k
                JOIN users u ON k.wali_id = u.id
                WHERE k.nama_kelas = ?
            ");
            $stmtWali->execute([$siswa['kelas']]);
            $nama_wali = $stmtWali->fetchColumn();
            if ($nama_wali) {
                $wali_kelas = $nama_wali;
            } else {
                // Try ref_kelas
                $stmtWali2 = db()->prepare("
                    SELECT u.nama_lengkap 
                    FROM ref_kelas k
                    JOIN users u ON k.wali_kelas_id = u.id
                    WHERE k.nama_kelas = ?
                ");
                $stmtWali2->execute([$siswa['kelas']]);
                $nama_wali2 = $stmtWali2->fetchColumn();
                if ($nama_wali2) {
                    $wali_kelas = $nama_wali2;
                }
            }
        } catch (Exception $e) {}
    }

    json_response(200, true, 'Dashboard loaded', [
        'hadir' => $hadir,
        'izin' => $izin,
        'sakit' => $sakit,
        'alfa' => $alfa,
        'wali_kelas' => $wali_kelas,
        'guru_wali' => $guru_wali
    ]);
} catch (PDOException $e) {
    json_response(500, false, 'Database Error: ' . $e->getMessage());
}

