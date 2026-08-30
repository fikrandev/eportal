<?php
/**
 * Guru App — Jurnal Mengajar API
 * CRUD operations for teaching journal
 */
require_once __DIR__ . '/../../api/config.php';

// Auth check
$token = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) $token = str_replace('Bearer ', '', $headers['Authorization']);
    elseif (isset($headers['authorization'])) $token = str_replace('Bearer ', '', $headers['authorization']);
}
if (empty($token) && isset($_GET['token'])) $token = $_GET['token'];

if (empty($token)) json_response(401, false, 'Token tidak ditemukan.');

$stmt = db()->prepare("
    SELECT u.id as user_id, u.username, u.nama_lengkap, u.role
    FROM sessions s
    JOIN users u ON s.user_id = u.id
    WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1 AND u.role = 'guru'
");
$stmt->execute([trim($token)]);
$user = $stmt->fetch();
if (!$user) json_response(401, false, 'Sesi tidak valid.');

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listJurnal($user);
        break;
    case 'list_izin':
        listIzin($user);
        break;
    case 'create_izin':
        createIzin($user);
        break;
    case 'get':
        getJurnal($user);
        break;
    case 'create':
        createJurnal($user);
        break;
    case 'update':
        updateJurnal($user);
        break;
    case 'delete':
        deleteJurnal($user);
        break;
    case 'meta':
        getJurnalMeta($user);
        break;
    case 'students':
        getStudentsByKelas($user);
        break;
    case 'wali_kelas_list':
        listJurnalWaliKelas($user);
        break;
    case 'wali_kelas_rekap':
        rekapAbsensiWaliKelas($user);
        break;
    case 'wali_kelas_daily_absen':
        dailyAbsenWaliKelas($user);
        break;
    case 'dashboard_stats':
        getDashboardStats($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List jurnal entries for teacher
 */
function listJurnal($user) {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

        $where = "j.academic_year_id = ? AND j.guru_id = ?";
        $params = [$year_id, $user['user_id']];

        if (!empty($tanggal_akhir)) {
            $where .= " AND j.tanggal BETWEEN ? AND ?";
            $params[] = $tanggal;
            $params[] = $tanggal_akhir;
        } else {
            $where .= " AND j.tanggal = ?";
            $params[] = $tanggal;
        }

        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        if ($kelas_id > 0) {
            $where .= " AND j.kelas_id = ?";
            $params[] = $kelas_id;
        }

        $jenis = isset($_GET['jenis_jurnal']) ? trim($_GET['jenis_jurnal']) : '';
        if (!empty($jenis)) {
            $where .= " AND j.jenis_jurnal = ?";
            $params[] = $jenis;
        }

        $stmt = db()->prepare("
            SELECT j.*, k.nama_kelas, m.nama_mapel
            FROM acad_jurnal j
            LEFT JOIN sch_kelas k ON j.kelas_id = k.id
            LEFT JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE $where
            ORDER BY j.tanggal DESC, j.jam_ke ASC, j.created_at DESC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response(200, true, 'Data jurnal dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get single jurnal by ID
 */
function getJurnal($user) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("
            SELECT j.*, k.nama_kelas, m.nama_mapel
            FROM acad_jurnal j
            LEFT JOIN sch_kelas k ON j.kelas_id = k.id
            LEFT JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE j.id = ? AND j.guru_id = ?
        ");
        $stmt->execute([$id, $user['user_id']]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) json_response(404, false, 'Jurnal tidak ditemukan.');

        $data['absensi'] = [];
        if ($data['jenis_jurnal'] === 'kbm' && !empty($data['kelas_id'])) {
            $jam_ke_int = 0;
            if (preg_match('/(\d+)/', $data['jam_ke'], $matches)) {
                $jam_ke_int = (int)$matches[1];
            }

            $stmtAbs = db()->prepare("
                SELECT student_id, status, keterangan 
                FROM acad_absensi 
                WHERE tanggal = ? AND kelas_id = ? AND jam_ke = ?
            ");
            $stmtAbs->execute([$data['tanggal'], $data['kelas_id'], $jam_ke_int]);
            $data['absensi'] = $stmtAbs->fetchAll(PDO::FETCH_ASSOC);
        }

        json_response(200, true, 'Data jurnal.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Create new jurnal entry
 */
function createJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $jenis_jurnal = isset($input['jenis_jurnal']) && in_array($input['jenis_jurnal'], ['kbm', 'non_kbm', 'wali_kelas']) ? $input['jenis_jurnal'] : 'kbm';
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    
    // Strict restriction: Can only fill journal for today
    if ($tanggal !== date('Y-m-d')) {
        json_response(400, false, 'Jurnal hanya bisa diisi pada hari ini.');
    }

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';

    // ==========================================
    // 1. NON-KBM ACTIVITY JOURNAL (Guru Non-Mapel)
    // ==========================================
    if ($jenis_jurnal === 'non_kbm') {
        if (empty($catatan)) {
            json_response(400, false, 'Catatan kegiatan wajib diisi.');
        }

        try {
            $stmt = db()->prepare("
                INSERT INTO acad_jurnal (guru_id, academic_year_id, jenis_jurnal, tanggal, catatan)
                VALUES (?, ?, 'non_kbm', ?, ?)
            ");
            $stmt->execute([$user['user_id'], $year_id, $tanggal, $catatan]);
            $jurnal_id = db()->lastInsertId();

            json_response(201, true, 'Jurnal kegiatan berhasil disimpan.', ['id' => $jurnal_id]);
        } catch (PDOException $e) {
            json_response(500, false, 'Server error: ' . $e->getMessage());
        }
        return;
    }

    // ==========================================
    // 2. WALI KELAS JOURNAL (Catatan Wali Kelas)
    // ==========================================
    if ($jenis_jurnal === 'wali_kelas') {
        if (empty($catatan)) {
            json_response(400, false, 'Catatan kegiatan wali kelas wajib diisi.');
        }

        // Get homeroom class ID
        $stmtWali = db()->prepare("SELECT id, nama_kelas FROM ref_kelas WHERE wali_kelas_id = ? LIMIT 1");
        $stmtWali->execute([$user['user_id']]);
        $wali = $stmtWali->fetch(PDO::FETCH_ASSOC);

        $kelas_id = null;
        if ($wali) {
            $stmtSK = db()->prepare("SELECT id FROM sch_kelas WHERE nama_kelas = ? LIMIT 1");
            $stmtSK->execute([$wali['nama_kelas']]);
            $kelas_id = $stmtSK->fetchColumn() ?: null;
        }

        try {
            $stmt = db()->prepare("
                INSERT INTO acad_jurnal (guru_id, kelas_id, academic_year_id, jenis_jurnal, tanggal, catatan)
                VALUES (?, ?, ?, 'wali_kelas', ?, ?)
            ");
            $stmt->execute([$user['user_id'], $kelas_id, $year_id, $tanggal, $catatan]);
            $jurnal_id = db()->lastInsertId();

            json_response(201, true, 'Jurnal kegiatan wali kelas berhasil disimpan.', ['id' => $jurnal_id]);
        } catch (PDOException $e) {
            json_response(500, false, 'Server error: ' . $e->getMessage());
        }
        return;
    }

    // ==========================================
    // 3. REGULAR KBM JOURNAL (Guru Mengajar)
    // ==========================================
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : 0;
    $mapel_id = isset($input['mapel_id']) ? (int)$input['mapel_id'] : 0;
    $jam_ke = isset($input['jam_ke']) ? trim($input['jam_ke']) : '';
    $tp = isset($input['tujuan_pembelajaran']) ? trim($input['tujuan_pembelajaran']) : '';
    $iptp = isset($input['indikator_tp']) ? trim($input['indikator_tp']) : '';
    $absensi = isset($input['absensi']) ? $input['absensi'] : [];

    if ($kelas_id <= 0 || $mapel_id <= 0 || empty($jam_ke)) {
        json_response(400, false, 'Kelas, Mata Pelajaran, dan Jam wajib diisi.');
    }

    try {
        // Check for duplicate
        $stmtCheck = db()->prepare("
            SELECT id FROM acad_jurnal 
            WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ? AND tanggal = ? AND jam_ke = ? AND academic_year_id = ? AND jenis_jurnal = 'kbm'
        ");
        $stmtCheck->execute([$user['user_id'], $kelas_id, $mapel_id, $tanggal, $jam_ke, $year_id]);
        if ($stmtCheck->fetch()) {
            json_response(409, false, 'Jurnal untuk jadwal ini sudah pernah diisi.');
        }

        // Compile absent list dynamically
        $absent_list = [];
        if (is_array($absensi) && count($absensi) > 0) {
            $student_ids = array_column($absensi, 'student_id');
            if (count($student_ids) > 0) {
                $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                $stmtSt = db()->prepare("SELECT id, nama FROM students WHERE id IN ($placeholders)");
                $stmtSt->execute($student_ids);
                $students_lookup = [];
                while ($row = $stmtSt->fetch()) {
                    $students_lookup[$row['id']] = $row['nama'];
                }
                
                foreach ($absensi as $a) {
                    $status = $a['status'] ?? 'H';
                    if ($status !== 'H') {
                        $name = $students_lookup[$a['student_id']] ?? '';
                        if ($name !== '') {
                            $status_label = ($status === 'S') ? 'Sakit' : (($status === 'I') ? 'Izin' : 'Alpa');
                            $absent_list[] = "$name ($status_label)";
                        }
                    }
                }
            }
        }
        $siswa_tidak_hadir = count($absent_list) > 0 ? implode("\n", $absent_list) : 'Semua Hadir';

        db()->beginTransaction();

        $stmt = db()->prepare("
            INSERT INTO acad_jurnal (guru_id, kelas_id, mapel_id, academic_year_id, jenis_jurnal, tanggal, jam_ke, tujuan_pembelajaran, indikator_tp, catatan, siswa_tidak_hadir)
            VALUES (?, ?, ?, ?, 'kbm', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['user_id'], $kelas_id, $mapel_id, $year_id, $tanggal, $jam_ke, $tp, $iptp, $catatan, $siswa_tidak_hadir
        ]);

        $jurnal_id = db()->lastInsertId();

        // Save to acad_absensi (supports merged jam_ke like "2-3" or "1,2,3")
        if (is_array($absensi) && count($absensi) > 0) {
            $jams = [];
            if (preg_match('/(\d+)\s*-\s*(\d+)/', $jam_ke, $matches)) {
                for ($j = (int)$matches[1]; $j <= (int)$matches[2]; $j++) {
                    $jams[] = $j;
                }
            } elseif (preg_match('/(\d+)/', $jam_ke, $matches)) {
                $jams[] = (int)$matches[1];
            }
            if (empty($jams)) $jams = [0];
            
            foreach ($absensi as $a) {
                $student_id = (int)($a['student_id'] ?? 0);
                $status = in_array($a['status'] ?? 'H', ['H','S','I','A']) ? $a['status'] : 'H';
                $keterangan = isset($a['keterangan']) ? trim($a['keterangan']) : '';
                
                if ($student_id > 0) {
                    foreach ($jams as $jam_int) {
                        $stmtAbs = db()->prepare("
                            INSERT INTO acad_absensi (student_id, kelas_id, academic_year_id, tanggal, jam_ke, status, keterangan, dicatat_oleh)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), dicatat_oleh = VALUES(dicatat_oleh)
                        ");
                        $stmtAbs->execute([$student_id, $kelas_id, $year_id, $tanggal, $jam_int, $status, $keterangan, $user['user_id']]);
                    }
                }
            }
        }

        db()->commit();
        json_response(201, true, 'Jurnal mengajar berhasil disimpan.', ['id' => $jurnal_id]);
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Update jurnal entry
 */
function updateJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    // Strict restriction: Can only edit today's journal
    try {
        $stmtCheckDate = db()->prepare("SELECT * FROM acad_jurnal WHERE id = ? AND guru_id = ?");
        $stmtCheckDate->execute([$id, $user['user_id']]);
        $existingJurnal = $stmtCheckDate->fetch(PDO::FETCH_ASSOC);
        if (!$existingJurnal) {
            json_response(404, false, 'Jurnal tidak ditemukan.');
        }
        if ($existingJurnal['tanggal'] !== date('Y-m-d')) {
            json_response(400, false, 'Hanya jurnal hari ini yang dapat diubah.');
        }
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }

    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';

    if ($existingJurnal['jenis_jurnal'] === 'non_kbm' || $existingJurnal['jenis_jurnal'] === 'wali_kelas') {
        if (empty($catatan)) {
            json_response(400, false, 'Catatan kegiatan wajib diisi.');
        }
        try {
            $stmt = db()->prepare("UPDATE acad_jurnal SET catatan = ? WHERE id = ? AND guru_id = ?");
            $stmt->execute([$catatan, $id, $user['user_id']]);
            json_response(200, true, 'Jurnal kegiatan berhasil diperbarui.');
        } catch (PDOException $e) {
            json_response(500, false, 'Server error: ' . $e->getMessage());
        }
        return;
    }

    // KBM update
    $tp = isset($input['tujuan_pembelajaran']) ? trim($input['tujuan_pembelajaran']) : '';
    $iptp = isset($input['indikator_tp']) ? trim($input['indikator_tp']) : '';
    $absensi = isset($input['absensi']) ? $input['absensi'] : [];

    try {
        // Compile absent list dynamically
        $absent_list = [];
        if (is_array($absensi) && count($absensi) > 0) {
            $student_ids = array_column($absensi, 'student_id');
            if (count($student_ids) > 0) {
                $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                $stmtSt = db()->prepare("SELECT id, nama FROM students WHERE id IN ($placeholders)");
                $stmtSt->execute($student_ids);
                $students_lookup = [];
                while ($row = $stmtSt->fetch()) {
                    $students_lookup[$row['id']] = $row['nama'];
                }
                
                foreach ($absensi as $a) {
                    $status = $a['status'] ?? 'H';
                    if ($status !== 'H') {
                        $name = $students_lookup[$a['student_id']] ?? '';
                        if ($name !== '') {
                            $status_label = ($status === 'S') ? 'Sakit' : (($status === 'I') ? 'Izin' : 'Alpa');
                            $absent_list[] = "$name ($status_label)";
                        }
                    }
                }
            }
        }
        $siswa_tidak_hadir = count($absent_list) > 0 ? implode("\n", $absent_list) : 'Semua Hadir';

        db()->beginTransaction();

        $stmt = db()->prepare("
            UPDATE acad_jurnal SET tujuan_pembelajaran = ?, indikator_tp = ?, catatan = ?, siswa_tidak_hadir = ?
            WHERE id = ? AND guru_id = ?
        ");
        $stmt->execute([
            $tp, $iptp, $catatan, $siswa_tidak_hadir, $id, $user['user_id']
        ]);

        // Save to acad_absensi (supports merged jam_ke like "2-3")
        if (is_array($absensi) && count($absensi) > 0) {
            $jams = [];
            $jam_str = $existingJurnal['jam_ke'] ?? '';
            if (preg_match('/(\d+)\s*-\s*(\d+)/', $jam_str, $matches)) {
                for ($j = (int)$matches[1]; $j <= (int)$matches[2]; $j++) {
                    $jams[] = $j;
                }
            } elseif (preg_match('/(\d+)/', $jam_str, $matches)) {
                $jams[] = (int)$matches[1];
            }
            if (empty($jams)) $jams = [0];
            
            $active_year = get_active_academic_year();
            $year_id = $active_year['id'] ?? 0;
            
            foreach ($absensi as $a) {
                $student_id = (int)($a['student_id'] ?? 0);
                $status = in_array($a['status'] ?? 'H', ['H','S','I','A']) ? $a['status'] : 'H';
                $keterangan = isset($a['keterangan']) ? trim($a['keterangan']) : '';
                
                if ($student_id > 0) {
                    foreach ($jams as $jam_int) {
                        $stmtAbs = db()->prepare("
                            INSERT INTO acad_absensi (student_id, kelas_id, academic_year_id, tanggal, jam_ke, status, keterangan, dicatat_oleh)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), dicatat_oleh = VALUES(dicatat_oleh)
                        ");
                        $stmtAbs->execute([$student_id, $existingJurnal['kelas_id'], $year_id, $existingJurnal['tanggal'], $jam_int, $status, $keterangan, $user['user_id']]);
                    }
                }
            }
        }

        db()->commit();
        json_response(200, true, 'Jurnal berhasil diperbarui.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Delete jurnal entry
 */
function deleteJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        // Enforce restriction: Can only delete today's journal
        $stmtCheckDate = db()->prepare("SELECT tanggal FROM acad_jurnal WHERE id = ? AND guru_id = ?");
        $stmtCheckDate->execute([$id, $user['user_id']]);
        $existingJurnal = $stmtCheckDate->fetch();
        if (!$existingJurnal) {
            json_response(404, false, 'Jurnal tidak ditemukan.');
        }
        if ($existingJurnal['tanggal'] !== date('Y-m-d')) {
            json_response(400, false, 'Hanya jurnal hari ini yang dapat dihapus.');
        }

        $stmt = db()->prepare("DELETE FROM acad_jurnal WHERE id = ? AND guru_id = ?");
        $stmt->execute([$id, $user['user_id']]);
        json_response(200, true, 'Jurnal berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get metadata for jurnal form (classes, subjects, students)
 */
function getJurnalMeta($user) {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        // Get classes this teacher teaches
        $stmt = db()->prepare("
            SELECT DISTINCT k.id, k.nama_kelas, k.tingkat
            FROM sch_distribusi d
            JOIN sch_kelas k ON d.kelas_id = k.id
            JOIN sch_guru g ON d.guru_id = g.id
            WHERE g.kode_guru = ?
            ORDER BY k.tingkat, k.nama_kelas
        ");
        $stmt->execute([$user['username']]);
        $classes = $stmt->fetchAll();

        // Get subjects this teacher teaches
        $stmt = db()->prepare("
            SELECT DISTINCT mp.id, mp.kode_mapel, mp.nama_mapel
            FROM sch_distribusi d
            JOIN sch_mapel mp ON d.mapel_id = mp.id
            JOIN sch_guru g ON d.guru_id = g.id
            WHERE g.kode_guru = ?
            ORDER BY mp.nama_mapel
        ");
        $stmt->execute([$user['username']]);
        $subjects = $stmt->fetchAll();

        // Get students for the classes
        $students = [];
        if (count($classes) > 0) {
            $classNames = [];
            foreach ($classes as $c) $classNames[$c['id']] = $c['nama_kelas'];

            $placeholders = implode(',', array_fill(0, count($classNames), '?'));
            $stmt = db()->prepare("SELECT id, nis, nama, kelas FROM students WHERE kelas IN ($placeholders) AND status = 1 ORDER BY kelas, nama");
            $stmt->execute(array_values($classNames));
            $students = $stmt->fetchAll();
        }

        json_response(200, true, 'Metadata jurnal.', [
            'classes' => $classes,
            'subjects' => $subjects,
            'students' => $students
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get students list of a class (used inside mobile Jurnal form modal)
 */
function getStudentsByKelas($user) {
    $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
    if ($kelas_id <= 0) json_response(400, false, 'Kelas wajib dipilih.');

    try {
        // Resolve class name
        $stmtK = db()->prepare("SELECT nama_kelas FROM sch_kelas WHERE id = ?");
        $stmtK->execute([$kelas_id]);
        $kelas = $stmtK->fetch(PDO::FETCH_ASSOC);
        if (!$kelas) json_response(404, false, 'Kelas tidak ditemukan.');

        $stmt = db()->prepare("SELECT id, nis, nama FROM students WHERE kelas = ? AND status = 1 ORDER BY nama");
        $stmt->execute([$kelas['nama_kelas']]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response(200, true, 'Daftar siswa berhasil dimuat.', $students);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * List journals filled by teachers in the homeroom teacher's class
 */
function listJurnalWaliKelas($user) {
    try {
        // Find homeroom class reference
        $stmtW = db()->prepare("SELECT id, tingkat, nama_kelas FROM ref_kelas WHERE wali_kelas_id = ? LIMIT 1");
        $stmtW->execute([$user['user_id']]);
        $wali = $stmtW->fetch(PDO::FETCH_ASSOC);
        
        if (!$wali) {
            json_response(403, false, 'Akses ditolak. Anda bukan wali kelas.');
        }

        // Resolve matching class ID inside sch_kelas (since journal references sch_kelas.id)
        $stmtSK = db()->prepare("SELECT id FROM sch_kelas WHERE nama_kelas = ? LIMIT 1");
        $stmtSK->execute([$wali['nama_kelas']]);
        $sch_kelas = $stmtSK->fetch(PDO::FETCH_ASSOC);
        $sch_kelas_id = $sch_kelas['id'] ?? 0;

        if (!$sch_kelas_id) {
            json_response(200, true, 'Data jurnal kosong (Kelas belum memiliki jadwal).', [
                'class_name' => $wali['nama_kelas'],
                'journals' => []
            ]);
            return;
        }

        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

        $where = "j.academic_year_id = ? AND j.kelas_id = ?";
        $params = [$year_id, $sch_kelas_id];

        if (!empty($tanggal_akhir)) {
            $where .= " AND j.tanggal BETWEEN ? AND ?";
            $params[] = $tanggal;
            $params[] = $tanggal_akhir;
        } else {
            $where .= " AND j.tanggal = ?";
            $params[] = $tanggal;
        }

        $stmt = db()->prepare("
            SELECT j.*, k.nama_kelas, m.nama_mapel, u.nama_lengkap as nama_guru
            FROM acad_jurnal j
            LEFT JOIN sch_kelas k ON j.kelas_id = k.id
            LEFT JOIN sch_mapel m ON j.mapel_id = m.id
            LEFT JOIN users u ON j.guru_id = u.id
            WHERE $where
            ORDER BY j.tanggal DESC, j.jam_ke ASC, j.created_at DESC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response(200, true, 'Jurnal kelas berhasil dimuat.', [
            'class_name' => $wali['nama_kelas'],
            'journals' => $data
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Fetch attendance stats percentage for homeroom class students
 */
function rekapAbsensiWaliKelas($user) {
    try {
        // Resolve homeroom class reference
        $stmtW = db()->prepare("SELECT id, tingkat, nama_kelas FROM ref_kelas WHERE wali_kelas_id = ? LIMIT 1");
        $stmtW->execute([$user['user_id']]);
        $wali = $stmtW->fetch(PDO::FETCH_ASSOC);
        
        if (!$wali) {
            json_response(403, false, 'Akses ditolak. Anda bukan wali kelas.');
        }

        $className = $wali['nama_kelas'];
        
        // Find matching class ID in sch_kelas
        $stmtSK = db()->prepare("SELECT id FROM sch_kelas WHERE nama_kelas = ? LIMIT 1");
        $stmtSK->execute([$className]);
        $sch_kelas = $stmtSK->fetch(PDO::FETCH_ASSOC);
        $sch_kelas_id = $sch_kelas['id'] ?? 0;

        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        // Get all active students in this class
        $stmtS = db()->prepare("SELECT id, nis, nama FROM students WHERE kelas = ? AND status = 1 ORDER BY nama");
        $stmtS->execute([$className]);
        $students = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        // Fetch attendance stats
        $stats = [];
        if ($sch_kelas_id > 0) {
            $stmtA = db()->prepare("
                SELECT student_id,
                       SUM(CASE WHEN status = 'H' THEN 1 ELSE 0 END) as hadir,
                       SUM(CASE WHEN status = 'S' THEN 1 ELSE 0 END) as sakit,
                       SUM(CASE WHEN status = 'I' THEN 1 ELSE 0 END) as izin,
                       SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) as alpha,
                       COUNT(*) as total
                FROM acad_absensi
                WHERE kelas_id = ? AND academic_year_id = ?
                GROUP BY student_id
            ");
            $stmtA->execute([$sch_kelas_id, $year_id]);
            while ($row = $stmtA->fetch(PDO::FETCH_ASSOC)) {
                $stats[$row['student_id']] = $row;
            }
        }

        // Merge and calculate percentage
        $result = [];
        foreach ($students as $s) {
            $st = $stats[$s['id']] ?? ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0, 'total' => 0];
            
            $hadir = (int)$st['hadir'];
            $sakit = (int)$st['sakit'];
            $izin = (int)$st['izin'];
            $alpha = (int)$st['alpha'];
            $total = (int)$st['total'];
            
            $persentase = ($total > 0) ? round(($hadir / $total) * 100, 1) : 100.0;
            
            $result[] = [
                'student_id' => $s['id'],
                'nis' => $s['nis'],
                'nama' => $s['nama'],
                'stats' => [
                    'hadir' => $hadir,
                    'sakit' => $sakit,
                    'izin' => $izin,
                    'alpha' => $alpha,
                    'total' => $total,
                    'persentase' => $persentase
                ]
            ];
        }

        json_response(200, true, 'Rekap absensi kelas berhasil dimuat.', [
            'class_name' => $className,
            'students' => $result
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function dailyAbsenWaliKelas($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : date('Y-m-d');
        
        // Resolve homeroom class reference
        $stmtW = db()->prepare("SELECT id, tingkat, nama_kelas FROM ref_kelas WHERE wali_kelas_id = ? LIMIT 1");
        $stmtW->execute([$user['user_id']]);
        $wali = $stmtW->fetch(PDO::FETCH_ASSOC);
        
        if (!$wali) {
            json_response(403, false, 'Akses ditolak. Anda bukan wali kelas.');
        }

        $className = $wali['nama_kelas'];
        
        // Find matching class ID in sch_kelas
        $stmtSK = db()->prepare("SELECT id FROM sch_kelas WHERE nama_kelas = ? LIMIT 1");
        $stmtSK->execute([$className]);
        $sch_kelas = $stmtSK->fetch(PDO::FETCH_ASSOC);
        $sch_kelas_id = $sch_kelas['id'] ?? 0;

        // Get all active students in this class
        $stmtS = db()->prepare("SELECT id, nis, nama FROM students WHERE kelas = ? AND status = 1 ORDER BY nama");
        $stmtS->execute([$className]);
        $students = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        // Fetch daily attendance records from acad_absensi for this class, date and jam_ke 1-10
        $attendance = [];
        if ($sch_kelas_id > 0) {
            $stmtA = db()->prepare("
                SELECT student_id, jam_ke, status
                FROM acad_absensi
                WHERE kelas_id = ? AND tanggal = ? AND jam_ke BETWEEN 1 AND 10
            ");
            $stmtA->execute([$sch_kelas_id, $tanggal]);
            $rawAbsen = $stmtA->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by student_id
            foreach ($rawAbsen as $row) {
                $student_id = (int)$row['student_id'];
                $jam = (int)$row['jam_ke'];
                $attendance[$student_id][$jam] = $row['status'];
            }
        }

        // Combine student details and attendance
        $result = [];
        foreach ($students as $student) {
            $sId = (int)$student['id'];
            $jams = [];
            for ($i = 1; $i <= 10; $i++) {
                $jams[$i] = $attendance[$sId][$i] ?? '.'; // default present is '.'
            }
            $result[] = [
                'id' => $sId,
                'nis' => $student['nis'],
                'nama' => $student['nama'],
                'jams' => $jams
            ];
        }

        json_response(200, true, 'Daily attendance data.', [
            'kelas_name' => $className,
            'tanggal' => $tanggal,
            'students' => $result
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Helper to merge consecutive periods of the same class and subject into 1 slot
 */
function mergeConsecutiveScheduleSlots($rawSchedules, $filledJurnals = []) {
    if (empty($rawSchedules)) return [];

    $merged = [];
    $current = null;

    foreach ($rawSchedules as $slot) {
        $jam = (int)$slot['jam_ke'];

        if ($current === null) {
            $current = $slot;
            $current['jam_list'] = [$jam];
            $current['jam_start'] = $jam;
            $current['jam_end'] = $jam;
            $current['total_jp'] = 1;
        } else {
            $isSameClass = ($current['kelas_id'] == $slot['kelas_id']);
            $isSameMapel = ($current['mapel_id'] == $slot['mapel_id']);
            $isConsecutive = ($jam == $current['jam_end'] + 1);

            if ($isSameClass && $isSameMapel && $isConsecutive) {
                $current['jam_list'][] = $jam;
                $current['jam_end'] = $jam;
                $current['total_jp'] += 1;
            } else {
                $merged[] = finalizeMergedScheduleSlot($current, $filledJurnals);
                $current = $slot;
                $current['jam_list'] = [$jam];
                $current['jam_start'] = $jam;
                $current['jam_end'] = $jam;
                $current['total_jp'] = 1;
            }
        }
    }

    if ($current !== null) {
        $merged[] = finalizeMergedScheduleSlot($current, $filledJurnals);
    }

    return $merged;
}

function finalizeMergedScheduleSlot($slot, $filledJurnals = []) {
    if (count($slot['jam_list']) > 1) {
        $slot['jam_ke'] = $slot['jam_start'] . '-' . $slot['jam_end'];
        $slot['nama_jam'] = 'Jam ke ' . $slot['jam_start'] . ' - ' . $slot['jam_end'];
    } else {
        $slot['jam_ke'] = (string)$slot['jam_start'];
        $slot['nama_jam'] = 'Jam ke ' . $slot['jam_start'];
    }

    $slot['jurnal_filled'] = false;
    $slot['jurnal_id'] = null;

    foreach ($filledJurnals as $jr) {
        if ($jr['kelas_id'] == $slot['kelas_id'] && $jr['mapel_id'] == $slot['mapel_id']) {
            $jrJam = trim((string)$jr['jam_ke']);
            if ($jrJam === $slot['jam_ke'] || in_array((int)$jrJam, $slot['jam_list'])) {
                $slot['jurnal_filled'] = true;
                $slot['jurnal_id'] = (int)$jr['id'];
                break;
            }
            if (preg_match('/(\d+)\s*-\s*(\d+)/', $jrJam, $m)) {
                $start = (int)$m[1];
                $end = (int)$m[2];
                foreach ($slot['jam_list'] as $jVal) {
                    if ($jVal >= $start && $jVal <= $end) {
                        $slot['jurnal_filled'] = true;
                        $slot['jurnal_id'] = (int)$jr['id'];
                        break 2;
                    }
                }
            }
        }
    }

    return $slot;
}

/**
 * Fetch stats for the modern teacher dashboard
 */
function getDashboardStats($user) {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;
        $tanggalIni = date('Y-m-d');

        // 1. Get today's teaching schedules
        $dayMap = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $dayOfWeek = (int)date('N'); // 1=Monday, 7=Sunday
        $hariIni = $dayMap[$dayOfWeek] ?? 'Senin';

        // Find sch_guru.id
        $stmtGuru = db()->prepare("SELECT id FROM sch_guru WHERE kode_guru = ?");
        $stmtGuru->execute([$user['username']]);
        $guru = $stmtGuru->fetch();
        $schedules = [];

        if ($guru) {
            $stmt = db()->prepare("
                SELECT j.id as jadwal_id,
                       k.id as kelas_id, k.nama_kelas,
                       jb.hari, jb.jam_ke, jb.tipe, jb.nama_jam,
                       m.id as mapel_id, m.nama_mapel, m.kode_mapel,
                       d.jp
                FROM sch_jadwal j
                JOIN sch_kelas k ON j.kelas_id = k.id
                JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
                JOIN sch_distribusi d ON j.distribusi_id = d.id
                JOIN sch_mapel m ON d.mapel_id = m.id
                WHERE d.guru_id = ? AND jb.hari = ?
                ORDER BY jb.jam_ke ASC
            ");
            $stmt->execute([$guru['id'], $hariIni]);
            $rawSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch today's journals
            $stmtJurnal = db()->prepare("
                SELECT jam_ke, kelas_id, mapel_id, id
                FROM acad_jurnal
                WHERE guru_id = ? AND tanggal = ? AND academic_year_id = ?
            ");
            $stmtJurnal->execute([$user['user_id'], $tanggalIni, $year_id]);
            $filledJurnals = $stmtJurnal->fetchAll(PDO::FETCH_ASSOC);

            $schedules = mergeConsecutiveScheduleSlots($rawSchedules, $filledJurnals);
        }

        // 2. Wali Kelas Stats
        $waliStats = null;
        $stmtW = db()->prepare("SELECT id, tingkat, nama_kelas FROM ref_kelas WHERE wali_kelas_id = ? LIMIT 1");
        $stmtW->execute([$user['user_id']]);
        $wali = $stmtW->fetch(PDO::FETCH_ASSOC);
        
        if ($wali) {
            $className = $wali['nama_kelas'];
            
            // Get total active students in class
            $stmtTotalS = db()->prepare("SELECT COUNT(*) FROM students WHERE kelas = ? AND status = 1");
            $stmtTotalS->execute([$className]);
            $totalStudents = (int)$stmtTotalS->fetchColumn();

            // Resolve class ID
            $stmtSK = db()->prepare("SELECT id FROM sch_kelas WHERE nama_kelas = ? LIMIT 1");
            $stmtSK->execute([$className]);
            $sch_kelas_id = (int)($stmtSK->fetchColumn() ?? 0);

            // Get how many students are marked as absent today
            $stmtAbsent = db()->prepare("
                SELECT COUNT(*) 
                FROM acad_absensi 
                WHERE kelas_id = ? AND tanggal = ? AND status IN ('S', 'I', 'A')
            ");
            $stmtAbsent->execute([$sch_kelas_id, $tanggalIni]);
            $absentCount = (int)$stmtAbsent->fetchColumn();
            
            $presentCount = max(0, $totalStudents - $absentCount);
            $attendanceRate = ($totalStudents > 0) ? round(($presentCount / $totalStudents) * 100) : 100;

            $waliStats = [
                'class_name' => $className,
                'total_students' => $totalStudents,
                'present_students' => $presentCount,
                'absent_students' => $absentCount,
                'attendance_rate' => $attendanceRate
            ];
        }

        // 3. Recent journals (last 3 entries)
        $stmtRecent = db()->prepare("
            SELECT j.*, k.nama_kelas, m.nama_mapel
            FROM acad_jurnal j
            JOIN sch_kelas k ON j.kelas_id = k.id
            JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE j.guru_id = ?
            ORDER BY j.tanggal DESC, j.jam_ke DESC
            LIMIT 3
        ");
        $stmtRecent->execute([$user['user_id']]);
        $recentJournals = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

        json_response(200, true, 'Dashboard stats.', [
            'schedules' => $schedules,
            'wali_stats' => $waliStats,
            'recent_journals' => $recentJournals
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function listIzin($user) {
    try {
        $stmt = db()->prepare("
            SELECT id, tanggal, jenis, catatan, status, created_at
            FROM acad_ketidakhadiran
            WHERE guru_id = ?
            ORDER BY tanggal DESC
        ");
        $stmt->execute([$user['user_id']]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response(200, true, 'Data izin berhasil dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function createIzin($user) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $tanggal = isset($input['tanggal']) ? trim($input['tanggal']) : '';
    $jenis = isset($input['jenis']) ? trim($input['jenis']) : '';
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';
    
    if (empty($tanggal)) json_response(400, false, 'Tanggal wajib diisi.');
    if (empty($jenis)) json_response(400, false, 'Jenis izin wajib dipilih.');
    if (!in_array($jenis, ['Sakit', 'Cuti', 'Tugas', 'Izin', 'Lainnya'])) {
        json_response(400, false, 'Jenis izin tidak valid.');
    }
    
    try {
        // Check if there is already an entry for this guru on this date
        $stmtCheck = db()->prepare("SELECT id FROM acad_ketidakhadiran WHERE guru_id = ? AND tanggal = ?");
        $stmtCheck->execute([$user['user_id'], $tanggal]);
        if ($stmtCheck->fetch()) {
            json_response(400, false, 'Anda sudah mengajukan izin pada tanggal tersebut.');
        }
        
        $stmt = db()->prepare("
            INSERT INTO acad_ketidakhadiran (guru_id, tanggal, jenis, catatan, status)
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$user['user_id'], $tanggal, $jenis, $catatan]);
        
        json_response(200, true, 'Pengajuan izin berhasil disimpan.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

