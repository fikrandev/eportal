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
    // Fetch Calendar Data (Dates and their statuses)
    $calendar_data = [];
    $stmtCal = db()->prepare("
        SELECT tanggal, status 
        FROM acad_absensi 
        WHERE (student_id = ? OR student_id = ?) 
        AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
    ");
    $stmtCal->execute([$studentId, $studentNis, $month, $year]);
    foreach ($stmtCal->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $calendar_data[$row['tanggal']] = $row['status'];
    }
    
    // Fallback to absen_logs if no manual H or T
    $hasHadir = in_array('H', $calendar_data) || in_array('T', $calendar_data);
    if (!$hasHadir) {
        try {
            $stmtLogsCal = db()->prepare("
                SELECT DATE(waktu_absen) as tgl, MIN(TIME(waktu_absen)) as waktu 
                FROM absen_logs 
                WHERE (mesin_pin = ? OR mesin_pin = ?) 
                AND MONTH(waktu_absen) = ? AND YEAR(waktu_absen) = ?
                GROUP BY DATE(waktu_absen)
            ");
            $stmtLogsCal->execute([$cleanPin, $studentNis, $month, $year]);
            $waktu_terlambat = get_setting('waktu_terlambat_siswa', '06:30:00');
            if (strlen($waktu_terlambat) === 5) $waktu_terlambat .= ':00';
            foreach ($stmtLogsCal->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!isset($calendar_data[$row['tgl']])) {
                    $calendar_data[$row['tgl']] = ($row['waktu'] > $waktu_terlambat) ? 'T' : 'H';
                }
            }
        } catch (Exception $e) {}
    }
    
    // Add approved Izin/Sakit from acad_izin_siswa if not in acad_absensi
    try {
        $stmtIzinCal = db()->prepare("
            SELECT tanggal, jenis 
            FROM acad_izin_siswa 
            WHERE (student_id = ? OR student_id = ?) 
            AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND (status = 'Disetujui' OR status = 'Approved')
        ");
        $stmtIzinCal->execute([$studentId, $studentNis, $month, $year]);
        foreach ($stmtIzinCal->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($calendar_data[$row['tanggal']])) {
                $calendar_data[$row['tanggal']] = ($row['jenis'] === 'Sakit') ? 'S' : 'I';
            }
        }
    } catch (Exception $e) {}
    
    // Recalculate totals from calendar_data to ensure consistency
    $hadir = 0; $terlambat = 0; $izin = 0; $sakit = 0; $alfa = 0;
    foreach ($calendar_data as $date => $st) {
        if ($st === 'H') $hadir++;
        else if ($st === 'T') $terlambat++;
        else if ($st === 'I') $izin++;
        else if ($st === 'S') $sakit++;
        else if ($st === 'A') $alfa++;
    }

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
    // Fetch Active Exams (CBT) for the student's class
    $active_exams = [];
    if (!empty($siswa['kelas'])) {
        try {
            $stmtExams = db()->prepare("
                SELECT u.id, u.judul, u.jenis_ujian, u.durasi_menit, u.metode_login, u.status, b.judul as nama_bank_soal
                FROM exam_ujian u
                JOIN exam_bank_soal b ON u.bank_soal_id = b.id
                JOIN exam_ujian_kelas uk ON uk.ujian_id = u.id
                WHERE u.status = 'aktif' AND uk.kelas = ?
                ORDER BY u.created_at DESC
            ");
            $stmtExams->execute([$siswa['kelas']]);
            $active_exams = $stmtExams->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }

    // Check if any exam card is configured in E-Xam Card module (xam_exams with status = 1)
    $global_cbt_active = false;
    try {
        $activeYear = get_active_academic_year();
        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $stmtXam = db()->prepare("
            SELECT COUNT(*) 
            FROM xam_exams 
            WHERE status = 1 AND academic_year_id = ?
        ");
        $stmtXam->execute([$activeYearId]);
        $global_cbt_active = ($stmtXam->fetchColumn() > 0);
    } catch (Exception $e) {}

    json_response(200, true, 'Dashboard loaded', [
        'hadir' => $hadir,
        'terlambat' => $terlambat,
        'izin' => $izin,
        'sakit' => $sakit,
        'alfa' => $alfa,
        'wali_kelas' => $wali_kelas,
        'guru_wali' => $guru_wali,
        'active_exams' => $active_exams,
        'calendar_data' => $calendar_data,
        'global_cbt_active' => $global_cbt_active
    ]);
} catch (PDOException $e) {
    json_response(500, false, 'Database Error: ' . $e->getMessage());
}

