<?php
require_once __DIR__ . '/auth_helper.php';
$siswa = siswa_auth();

try {
    $stmt = db()->prepare("
        SELECT jb.hari, jb.jam_ke, jb.nama_jam, m.nama_mapel, u.nama_lengkap as nama_guru
        FROM sch_jadwal j
        JOIN sch_kelas k ON j.kelas_id = k.id
        JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
        JOIN sch_distribusi d ON j.distribusi_id = d.id
        JOIN sch_mapel m ON d.mapel_id = m.id
        JOIN sch_guru g ON d.guru_id = g.id
        LEFT JOIN users u ON g.kode_guru = u.username
        WHERE k.nama_kelas = ?
        ORDER BY FIELD(jb.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jb.jam_ke ASC
    ");
    $stmt->execute([$siswa['kelas']]);
    $jadwal = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $grouped = [];
    foreach($jadwal as $j) {
        $hari = $j['hari'];
        if (!isset($grouped[$hari])) $grouped[$hari] = [];
        $grouped[$hari][] = $j;
    }
    
    json_response(200, true, 'Jadwal berhasil dimuat', $grouped);
} catch (Exception $e) {
    json_response(500, false, $e->getMessage());
}
