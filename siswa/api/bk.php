<?php
/**
 * Siswa App - BK & Buku Penghubung API
 * Mengambil catatan bimbingan konseling dan buku penghubung siswa
 */
require_once __DIR__ . '/auth_helper.php';
$siswa = siswa_auth();

// 1. Ensure tables exist before executing query
try {
    db()->exec("CREATE TABLE IF NOT EXISTS `acad_buku_types` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `nama_jenis` varchar(100) NOT NULL,
        `deskripsi` varchar(255) DEFAULT NULL,
        `warna_badge` varchar(50) NOT NULL DEFAULT 'badge-info',
        `urutan` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_nama_jenis` (`nama_jenis`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $countTypes = (int)db()->query("SELECT COUNT(*) FROM `acad_buku_types`")->fetchColumn();
    if ($countTypes === 0) {
        $defaultTypes = [
            ['Keterlambatan', 'Catatan siswa terlambat masuk sekolah', 'badge-warning', 1],
            ['Pelanggaran', 'Catatan pelanggaran tata tertib sekolah', 'badge-danger', 2],
            ['Prestasi', 'Penghargaan / prestasi akademik & non-akademik', 'badge-success', 3],
            ['Screening', 'Catatan screening kesehatan / perilaku siswa', 'badge-info', 4],
            ['Konsultasi', 'Bimbingan dan konseling siswa', 'badge-primary', 5]
        ];
        $stmtBT = db()->prepare("INSERT IGNORE INTO `acad_buku_types` (`nama_jenis`, `deskripsi`, `warna_badge`, `urutan`) VALUES (?,?,?,?)");
        foreach ($defaultTypes as $dbt) {
            $stmtBT->execute($dbt);
        }
    }

    db()->exec("CREATE TABLE IF NOT EXISTS `acad_buku_penghubung` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `student_id` int(11) unsigned NOT NULL COMMENT 'References students.id',
        `kelas_id` int(10) unsigned NOT NULL DEFAULT 0,
        `academic_year_id` int(11) unsigned NOT NULL DEFAULT 0,
        `jenis` varchar(100) NOT NULL DEFAULT 'Konsultasi',
        `tanggal` date NOT NULL,
        `catatan` text NOT NULL,
        `dicatat_oleh` int(11) unsigned DEFAULT NULL COMMENT 'user_id guru / bk',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_buku_student` (`student_id`),
        KEY `idx_buku_kelas` (`kelas_id`),
        KEY `idx_buku_tanggal` (`tanggal`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Exception $e) {
    // Continue
}

try {
    $studentId = (int)$siswa['id'];
    $studentNis = (string)$siswa['nis'];

    $stmt = db()->prepare("
        SELECT b.*, 
               COALESCE(u.nama_lengkap, 'Guru BK / Wali Kelas') as dicatat_nama, 
               COALESCE(t.warna_badge, 'badge-info') as warna_badge,
               COALESCE(t.deskripsi, '') as jenis_deskripsi, 
               COALESCE(k.nama_kelas, '') as nama_kelas
        FROM acad_buku_penghubung b 
        LEFT JOIN users u ON b.dicatat_oleh = u.id
        LEFT JOIN sch_kelas k ON b.kelas_id = k.id
        LEFT JOIN acad_buku_types t ON b.jenis = t.nama_jenis
        WHERE b.student_id = ? OR b.student_id = ? OR b.student_id IN (SELECT id FROM students WHERE nis = ?)
        ORDER BY b.tanggal DESC, b.id DESC
    ");
    $stmt->execute([$studentId, $studentNis, $studentNis]);
    $catatan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format tanggal
    foreach ($catatan as &$c) {
        $c['tanggal_indo'] = format_tanggal($c['tanggal']);
    }

    json_response(200, true, 'Catatan BK berhasil dimuat.', $catatan);
} catch (PDOException $e) {
    json_response(500, false, 'Database Error: ' . $e->getMessage());
}
