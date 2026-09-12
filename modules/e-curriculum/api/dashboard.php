<?php
/**
 * E-Curriculum Dashboard API (Enriched & Real-Time)
 */
require_once __DIR__ . '/auth_helper.php';

// Check auth
$user = acad_auth();

$action = isset($_GET['action']) ? $_GET['action'] : 'stats';

if ($action === 'stats') {
    try {
        $pdo = db();
        $today = date('Y-m-d');
        $this_month = date('Y-m');
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        // Indonesian Day Name
        $hari_indo = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
        ];
        $today_day_num = (int)date('N');
        $today_day_name = $hari_indo[$today_day_num] ?? 'Senin';

        // 1. Core Summary Metrics
        $active_year = get_active_academic_year();
        $year_id = (int)($active_year['id'] ?? 0);
        if ($year_id > 0) {
            $total_siswa = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE academic_year_id = {$year_id} AND status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '')")->fetchColumn();
        } else {
            $total_siswa = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '')")->fetchColumn();
        }
        
        $total_guru = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'guru'")->fetchColumn();
        $total_kelas = (int)$pdo->query("SELECT COUNT(*) FROM sch_kelas")->fetchColumn();
        if ($total_kelas === 0) {
            $total_kelas = (int)$pdo->query("SELECT COUNT(*) FROM ref_kelas")->fetchColumn();
        }

        $total_mapel = (int)$pdo->query("SELECT COUNT(*) FROM sch_mapel")->fetchColumn();
        $total_jp = (int)$pdo->query("SELECT SUM(jp) FROM sch_distribusi")->fetchColumn();
        $total_distribusi = (int)$pdo->query("SELECT COUNT(*) FROM sch_distribusi")->fetchColumn();

        // 2. Jurnal Mengajar Today & Recent
        $stmtJ = $pdo->prepare("SELECT COUNT(*) FROM acad_jurnal WHERE tanggal = ?");
        $stmtJ->execute([$today]);
        $jurnal_today_count = (int)$stmtJ->fetchColumn();

        $stmtRJ = $pdo->prepare("
            SELECT j.*, u.nama_lengkap as guru_nama, u.avatar, 
                   COALESCE(k.nama_kelas, '-') as nama_kelas, 
                   COALESCE(m.nama_mapel, '-') as nama_mapel
            FROM acad_jurnal j
            JOIN users u ON j.guru_id = u.id
            LEFT JOIN sch_kelas k ON j.kelas_id = k.id
            LEFT JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE j.tanggal = ?
            ORDER BY j.jam_ke ASC, j.id DESC LIMIT 6
        ");
        $stmtRJ->execute([$today]);
        $recent_jurnals = $stmtRJ->fetchAll(PDO::FETCH_ASSOC);

        // If no jurnals today, fetch latest 6 from any recent day
        if (empty($recent_jurnals)) {
            $stmtFallbackJ = $pdo->query("
                SELECT j.*, u.nama_lengkap as guru_nama, u.avatar, 
                       COALESCE(k.nama_kelas, '-') as nama_kelas, 
                       COALESCE(m.nama_mapel, '-') as nama_mapel
                FROM acad_jurnal j
                JOIN users u ON j.guru_id = u.id
                LEFT JOIN sch_kelas k ON j.kelas_id = k.id
                LEFT JOIN sch_mapel m ON j.mapel_id = m.id
                ORDER BY j.tanggal DESC, j.id DESC LIMIT 6
            ");
            $recent_jurnals = $stmtFallbackJ->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Absensi Guru Today
        $stmtAG = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'H' THEN 1 END) as hadir,
                COUNT(CASE WHEN status = 'S' THEN 1 END) as sakit,
                COUNT(CASE WHEN status = 'I' THEN 1 END) as izin,
                COUNT(CASE WHEN status = 'A' THEN 1 END) as alpha,
                COUNT(*) as total_recorded
            FROM acad_absensi_guru
            WHERE tanggal = ?
        ");
        $stmtAG->execute([$today]);
        $absensi_guru_summary = $stmtAG->fetch(PDO::FETCH_ASSOC);

        // Guru Tidak Hadir today list
        $stmtTH = $pdo->prepare("
            SELECT g.status, g.keterangan, u.nama_lengkap as guru_nama, u.avatar
            FROM acad_absensi_guru g
            JOIN users u ON g.guru_id = u.id
            WHERE g.tanggal = ? AND g.status IN ('S', 'I', 'A')
            ORDER BY u.nama_lengkap ASC
        ");
        $stmtTH->execute([$today]);
        $guru_tidak_hadir = $stmtTH->fetchAll(PDO::FETCH_ASSOC);

        // 4. Piket Guru Today
        $stmtPiket = $pdo->prepare("
            SELECT p.*, ug.nama_lengkap as guru_piket_nama, ud.nama_lengkap as guru_diganti_nama, k.nama_kelas
            FROM acad_piket p
            JOIN users ug ON p.guru_id = ug.id
            LEFT JOIN users ud ON p.guru_diganti_id = ud.id
            LEFT JOIN sch_kelas k ON p.kelas_id = k.id
            WHERE p.tanggal = ?
            ORDER BY p.id DESC
        ");
        $stmtPiket->execute([$today]);
        $piket_today = $stmtPiket->fetchAll(PDO::FETCH_ASSOC);

        // 5. Dokumen Guru Stats & Recent
        $docStats = $pdo->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
            FROM acad_documents
        ")->fetch(PDO::FETCH_ASSOC);

        $recentDocs = $pdo->query("
            SELECT d.*, u.nama_lengkap as guru_nama
            FROM acad_documents d
            JOIN users u ON d.user_id = u.id
            ORDER BY d.created_at DESC LIMIT 4
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 6. Buku Penghubung Today & Recent
        $stmtBukuToday = $pdo->prepare("SELECT COUNT(*) FROM acad_buku_penghubung WHERE tanggal = ?");
        $stmtBukuToday->execute([$today]);
        $buku_today_count = (int)$stmtBukuToday->fetchColumn();

        $recentBuku = $pdo->query("
            SELECT b.*, s.nama as nama_siswa, s.nis, COALESCE(k.nama_kelas, s.kelas) as nama_kelas, u.nama_lengkap as dicatat_nama,
                   COALESCE(t.warna_badge, 'badge-info') as warna_badge
            FROM acad_buku_penghubung b
            JOIN students s ON b.student_id = s.id
            LEFT JOIN sch_kelas k ON b.kelas_id = k.id
            LEFT JOIN users u ON b.dicatat_oleh = u.id
            LEFT JOIN acad_buku_types t ON b.jenis = t.nama_jenis
            ORDER BY b.tanggal DESC, b.id DESC LIMIT 4
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 7. NEW: Presensi Siswa (Hari Ini & Bulan Ini)
        $stmtAbsSiswaToday = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'H' THEN 1 END) as hadir,
                COUNT(CASE WHEN status = 'S' THEN 1 END) as sakit,
                COUNT(CASE WHEN status = 'I' THEN 1 END) as izin,
                COUNT(CASE WHEN status = 'A' THEN 1 END) as alpha,
                COUNT(CASE WHEN status = 'T' THEN 1 END) as terlambat,
                COUNT(*) as total
            FROM acad_absensi
            WHERE tanggal = ?
        ");
        $stmtAbsSiswaToday->execute([$today]);
        $siswa_today = $stmtAbsSiswaToday->fetch(PDO::FETCH_ASSOC);
        $siswa_today_total = (int)($siswa_today['total'] ?? 0);
        $siswa_today_rate = $siswa_today_total > 0 ? round(((int)$siswa_today['hadir'] / $siswa_today_total) * 100, 1) : 100;

        $stmtAbsSiswaMonth = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'H' THEN 1 END) as hadir,
                COUNT(CASE WHEN status = 'S' THEN 1 END) as sakit,
                COUNT(CASE WHEN status = 'I' THEN 1 END) as izin,
                COUNT(CASE WHEN status = 'A' THEN 1 END) as alpha,
                COUNT(CASE WHEN status = 'T' THEN 1 END) as terlambat,
                COUNT(*) as total
            FROM acad_absensi
            WHERE tanggal LIKE ?
        ");
        $stmtAbsSiswaMonth->execute([$this_month . '%']);
        $siswa_month = $stmtAbsSiswaMonth->fetch(PDO::FETCH_ASSOC);
        $siswa_month_total = (int)($siswa_month['total'] ?? 0);
        $siswa_month_rate = $siswa_month_total > 0 ? round(((int)$siswa_month['hadir'] / $siswa_month_total) * 100, 1) : 100;

        // 8. NEW: Jadwal KBM Hari Ini (Live Schedule Slots)
        $stmtJadwalToday = $pdo->prepare("
            SELECT j.id, jb.hari, jb.jam_ke, jb.nama_jam, jb.tipe,
                   k.nama_kelas, m.nama_mapel, u.nama_lengkap as guru_nama
            FROM sch_jadwal j
            JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
            JOIN sch_distribusi d ON j.distribusi_id = d.id
            JOIN users u ON d.guru_id = u.id
            JOIN sch_mapel m ON d.mapel_id = m.id
            JOIN sch_kelas k ON j.kelas_id = k.id
            WHERE jb.hari = ?
            ORDER BY jb.jam_ke ASC, k.nama_kelas ASC
            LIMIT 12
        ");
        $stmtJadwalToday->execute([$today_day_name]);
        $jadwal_today = $stmtJadwalToday->fetchAll(PDO::FETCH_ASSOC);

        $is_fallback_jadwal = false;
        if (empty($jadwal_today)) {
            $stmtSampleJadwal = $pdo->query("
                SELECT j.id, jb.hari, jb.jam_ke, jb.nama_jam, jb.tipe,
                       k.nama_kelas, m.nama_mapel, u.nama_lengkap as guru_nama
                FROM sch_jadwal j
                JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
                JOIN sch_distribusi d ON j.distribusi_id = d.id
                JOIN users u ON d.guru_id = u.id
                JOIN sch_mapel m ON d.mapel_id = m.id
                JOIN sch_kelas k ON j.kelas_id = k.id
                ORDER BY jb.hari ASC, jb.jam_ke ASC
                LIMIT 12
            ");
            $jadwal_today = $stmtSampleJadwal->fetchAll(PDO::FETCH_ASSOC);
            $is_fallback_jadwal = true;
        }

        // 9. NEW: Top 5 Active Teachers (Leaderboard KBM)
        $top_teachers = $pdo->query("
            SELECT u.id, u.nama_lengkap, u.avatar, COUNT(j.id) as total_jurnal,
                   (SELECT COUNT(*) FROM sch_distribusi WHERE guru_id = u.id) as total_kelas_ajar,
                   COALESCE((SELECT SUM(jp) FROM sch_distribusi WHERE guru_id = u.id), 0) as total_jp
            FROM users u
            LEFT JOIN acad_jurnal j ON u.id = j.guru_id
            WHERE u.role = 'guru' AND u.status = 1
            GROUP BY u.id
            ORDER BY total_jurnal DESC, total_jp DESC, u.nama_lengkap ASC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 10. NEW: Perangkat Ajar By Type
        $doc_by_type = $pdo->query("
            SELECT tipe_dokumen, 
                   COUNT(*) as total,
                   COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                   COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                   COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
            FROM acad_documents
            GROUP BY tipe_dokumen
            ORDER BY total DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 11. NEW: Buku Penghubung By Type
        $buku_by_type = $pdo->query("
            SELECT b.jenis, COUNT(b.id) as total,
                   COALESCE(t.warna_badge, 'badge-info') as warna_badge
            FROM acad_buku_penghubung b
            LEFT JOIN acad_buku_types t ON b.jenis = t.nama_jenis
            GROUP BY b.jenis
            ORDER BY total DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 12. NEW: Workload Stats (Distribusi JP Mengajar Guru)
        $workload = $pdo->query("
            SELECT 
                COUNT(DISTINCT guru_id) as total_guru_mengajar,
                SUM(guru_total_jp) as total_all_jp,
                ROUND(AVG(guru_total_jp), 1) as avg_jp_per_guru,
                MAX(guru_total_jp) as max_jp
            FROM (
                SELECT guru_id, SUM(jp) as guru_total_jp
                FROM sch_distribusi
                GROUP BY guru_id
            ) as sub
        ")->fetch(PDO::FETCH_ASSOC);

        json_response(200, true, 'Ultra-informative dashboard data loaded.', [
            'summary' => [
                'total_guru' => $total_guru,
                'total_siswa' => $total_siswa,
                'total_kelas' => $total_kelas,
                'total_mapel' => $total_mapel,
                'total_distribusi' => $total_distribusi,
                'total_jp' => $total_jp,
                'jurnal_today_count' => $jurnal_today_count,
                'buku_today_count' => $buku_today_count,
                'absensi_guru' => [
                    'hadir' => (int)($absensi_guru_summary['hadir'] ?? 0),
                    'sakit' => (int)($absensi_guru_summary['sakit'] ?? 0),
                    'izin' => (int)($absensi_guru_summary['izin'] ?? 0),
                    'alpha' => (int)($absensi_guru_summary['alpha'] ?? 0),
                    'total_recorded' => (int)($absensi_guru_summary['total_recorded'] ?? 0),
                ],
                'absensi_siswa' => [
                    'today' => [
                        'hadir' => (int)($siswa_today['hadir'] ?? 0),
                        'sakit' => (int)($siswa_today['sakit'] ?? 0),
                        'izin' => (int)($siswa_today['izin'] ?? 0),
                        'alpha' => (int)($siswa_today['alpha'] ?? 0),
                        'terlambat' => (int)($siswa_today['terlambat'] ?? 0),
                        'total' => $siswa_today_total,
                        'rate' => $siswa_today_rate
                    ],
                    'month' => [
                        'hadir' => (int)($siswa_month['hadir'] ?? 0),
                        'sakit' => (int)($siswa_month['sakit'] ?? 0),
                        'izin' => (int)($siswa_month['izin'] ?? 0),
                        'alpha' => (int)($siswa_month['alpha'] ?? 0),
                        'terlambat' => (int)($siswa_month['terlambat'] ?? 0),
                        'total' => $siswa_month_total,
                        'rate' => $siswa_month_rate
                    ]
                ],
                'dokumen' => [
                    'total' => (int)($docStats['total'] ?? 0),
                    'pending' => (int)($docStats['pending'] ?? 0),
                    'approved' => (int)($docStats['approved'] ?? 0),
                    'rejected' => (int)($docStats['rejected'] ?? 0),
                ],
                'workload' => [
                    'total_guru_mengajar' => (int)($workload['total_guru_mengajar'] ?? 0),
                    'total_all_jp' => (int)($workload['total_all_jp'] ?? 0),
                    'avg_jp_per_guru' => (float)($workload['avg_jp_per_guru'] ?? 0),
                    'max_jp' => (int)($workload['max_jp'] ?? 0),
                ]
            ],
            'jadwal_today' => $jadwal_today,
            'is_fallback_jadwal' => $is_fallback_jadwal,
            'today_day_name' => $today_day_name,
            'top_teachers' => $top_teachers,
            'doc_by_type' => $doc_by_type,
            'buku_by_type' => $buku_by_type,
            'recent_jurnals' => $recent_jurnals,
            'guru_tidak_hadir' => $guru_tidak_hadir,
            'piket_today' => $piket_today,
            'recent_docs' => $recentDocs,
            'recent_buku' => $recentBuku,
            'academic_year' => $active_year,
            'server_date' => $today
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else {
    json_response(400, false, 'Action tidak valid.');
}
