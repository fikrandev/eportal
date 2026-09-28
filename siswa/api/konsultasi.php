<?php
require_once __DIR__ . '/auth_helper.php';
$siswa = siswa_auth();

try {
    $guruWaliName = $siswa['guru_wali'];
    if (!$guruWaliName) {
        json_response(200, true, 'Tidak ada guru wali', []);
    }
    
    $baseName = trim(preg_replace('/,.*$/', '', $guruWaliName));
    $baseName = trim(preg_replace('/^(Drs\.|Dra\.|Ir\.|H\.|Hj\.)\s*/i', '', $baseName));
    
    $stmt = db()->prepare("SELECT id FROM users WHERE nama_lengkap LIKE ? AND (role = 'guru' OR role = 'waka' OR role = 'kepsek') LIMIT 1");
    $stmt->execute(['%'.$baseName.'%']);
    $guruId = $stmt->fetchColumn();
    
    if (!$guruId) {
        json_response(200, true, 'Guru wali tidak ditemukan', []);
    }
    
    $stmt2 = db()->prepare("SELECT id, tanggal, catatan FROM acad_jurnal WHERE jenis_jurnal = 'guru_wali' AND guru_id = ? ORDER BY tanggal DESC");
    $stmt2->execute([$guruId]);
    $konsultasi = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($konsultasi as &$k) {
        $k['tanggal_indo'] = format_tanggal($k['tanggal']);
    }
    
    json_response(200, true, 'Konsultasi dimuat', $konsultasi);
} catch (Exception $e) {
    json_response(500, false, $e->getMessage());
}
