<?php
require 'e:/xampp/htdocs/eportal/api/config.php';
require 'e:/xampp/htdocs/eportal/modules/e-absen/api/auth_helper.php';

$db = db();
$log_uid = "12840";
$log_timestamp = "2026-09-01 07:00:00";
$mesin_id = 1;
$log_state = 0;
$log_type = 1;

try {
    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;
    echo "Year ID: $year_id\n";

    $db->beginTransaction();
    $stmtInsert = $db->prepare("
        INSERT IGNORE INTO absen_logs (mesin_id, mesin_pin, waktu_absen, status_absen, verify_type)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([$mesin_id, $log_uid, $log_timestamp, $log_state, $log_type]);
    
    echo "Inserted rows in absen_logs: " . $stmtInsert->rowCount() . "\n";

    if ($stmtInsert->rowCount() > 0) {
        $stmtSiswa = $db->prepare("
            SELECT id, nama, no_hp_ortu, kelas 
            FROM students 
            WHERE TRIM(LEADING '0' FROM nis) = TRIM(LEADING '0' FROM ?) AND status = 1 
            LIMIT 1
        ");
        $stmtSiswa->execute([$log_uid]);
        $siswa = $stmtSiswa->fetch();
        
        if ($siswa) {
            echo "Student found: " . $siswa['nama'] . ", Kelas: " . $siswa['kelas'] . "\n";
            $stmtKelas = $db->prepare("SELECT id FROM sch_kelas WHERE nama_kelas = ? LIMIT 1");
            $stmtKelas->execute([$siswa['kelas']]);
            $kelasId = $stmtKelas->fetchColumn() ?: 0;
            echo "Kelas ID: $kelasId\n";
            
            if ($kelasId > 0 && $year_id > 0) {
                $tanggal_absen = date('Y-m-d', strtotime($log_timestamp));
                echo "Attempting to insert into acad_absensi for date: $tanggal_absen\n";
                $stmtAbsensi = $db->prepare("
                    INSERT INTO acad_absensi (student_id, kelas_id, academic_year_id, tanggal, jam_ke, status, keterangan, dicatat_oleh)
                    VALUES (?, ?, ?, ?, 0, 'H', 'Hadir via Fingerprint', NULL)
                    ON DUPLICATE KEY UPDATE status = 'H', keterangan = 'Hadir via Fingerprint'
                ");
                $success = $stmtAbsensi->execute([$siswa['id'], $kelasId, $year_id, $tanggal_absen]);
                echo "Acad_absensi insert success: " . ($success ? 'true' : 'false') . "\n";
            } else {
                echo "Skipped acad_absensi insert because KelasId ($kelasId) or YearId ($year_id) is 0.\n";
            }
        } else {
            echo "Student NOT found for UID: $log_uid\n";
        }
    }
    
    $db->commit();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
