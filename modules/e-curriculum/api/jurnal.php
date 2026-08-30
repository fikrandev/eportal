<?php
/**
 * E-Curriculum Jurnal Mengajar API
 * Manages teaching journal entries
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listJurnal($user);
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
    case 'report':
        reportJurnal($user);
        break;
    case 'meta':
        getJurnalMeta($user);
        break;
    case 'teachers':
        listTeachers($user);
        break;
    case 'teacher_schedules':
        getTeacherSchedules($user);
        break;
    case 'students':
        getStudentsByKelas($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List jurnal entries for a teacher on given date/range
 */
function listJurnal($user) {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

        $isAdmin = $user['role'] === 'superadmin';
        $guru_id = $isAdmin ? (isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0) : $user['user_id'];

        $where = "j.academic_year_id = ?";
        $params = [$year_id];

        if ($guru_id > 0) {
            $where .= " AND j.guru_id = ?";
            $params[] = $guru_id;
        }
        if ($kelas_id > 0) {
            $where .= " AND j.kelas_id = ?";
            $params[] = $kelas_id;
        }
        if (!empty($tanggal_akhir)) {
            $where .= " AND j.tanggal BETWEEN ? AND ?";
            $params[] = $tanggal;
            $params[] = $tanggal_akhir;
        } else {
            $where .= " AND j.tanggal = ?";
            $params[] = $tanggal;
        }

        $stmt = db()->prepare("
            SELECT j.*, u.nama_lengkap as guru_nama, k.nama_kelas, m.nama_mapel, m.nama_mapel as kode_mapel
            FROM acad_jurnal j
            JOIN users u ON j.guru_id = u.id
            LEFT JOIN sch_kelas k ON j.kelas_id = k.id
            LEFT JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE $where
            ORDER BY j.tanggal DESC, j.jam_ke ASC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        json_response(200, true, 'Data jurnal dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function getJurnal($user) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("
            SELECT j.*, u.nama_lengkap as guru_nama, k.nama_kelas, m.nama_mapel
            FROM acad_jurnal j
            JOIN users u ON j.guru_id = u.id
            LEFT JOIN sch_kelas k ON j.kelas_id = k.id
            LEFT JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE j.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) json_response(404, false, 'Jurnal tidak ditemukan.');

        $data['absensi'] = [];
        if (!empty($data['kelas_id'])) {
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

function createJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $guru_id = ($user['role'] === 'superadmin' && isset($input['guru_id'])) ? (int)$input['guru_id'] : $user['user_id'];
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : 0;
    $mapel_id = isset($input['mapel_id']) ? (int)$input['mapel_id'] : 0;
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $jam_ke = isset($input['jam_ke']) ? trim($input['jam_ke']) : '';
    $tp = isset($input['tujuan_pembelajaran']) ? trim($input['tujuan_pembelajaran']) : '';
    $iptp = isset($input['indikator_tp']) ? trim($input['indikator_tp']) : '';
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';
    $siswa_tidak_hadir = isset($input['siswa_tidak_hadir']) ? $input['siswa_tidak_hadir'] : '';
    $absensi = isset($input['absensi']) ? $input['absensi'] : null;

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

    if ($kelas_id <= 0 || $mapel_id <= 0 || empty($jam_ke)) {
        json_response(400, false, 'Kelas, Mata Pelajaran, dan Jam wajib diisi.');
    }

    try {
        // Build absent list if absensi array is provided
        if (is_array($absensi) && count($absensi) > 0) {
            $absent_list = [];
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
            $siswa_tidak_hadir = count($absent_list) > 0 ? implode("\n", $absent_list) : 'Semua Hadir';
        }

        db()->beginTransaction();

        $stmt = db()->prepare("
            INSERT INTO acad_jurnal (guru_id, kelas_id, mapel_id, academic_year_id, jenis_jurnal, tanggal, jam_ke, tujuan_pembelajaran, indikator_tp, catatan, siswa_tidak_hadir)
            VALUES (?, ?, ?, ?, 'kbm', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $guru_id, $kelas_id, $mapel_id, $year_id, $tanggal, $jam_ke, $tp, $iptp, $catatan, 
            is_array($siswa_tidak_hadir) ? json_encode($siswa_tidak_hadir) : $siswa_tidak_hadir
        ]);

        $jurnal_id = db()->lastInsertId();

        // Save to acad_absensi
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

function updateJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    $tp = isset($input['tujuan_pembelajaran']) ? trim($input['tujuan_pembelajaran']) : '';
    $iptp = isset($input['indikator_tp']) ? trim($input['indikator_tp']) : '';
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';
    $siswa_tidak_hadir = isset($input['siswa_tidak_hadir']) ? $input['siswa_tidak_hadir'] : '';
    $absensi = isset($input['absensi']) ? $input['absensi'] : null;

    try {
        // Resolve student names for absent list if absensi array is provided
        if (is_array($absensi) && count($absensi) > 0) {
            $absent_list = [];
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
            $siswa_tidak_hadir = count($absent_list) > 0 ? implode("\n", $absent_list) : 'Semua Hadir';
        }

        db()->beginTransaction();

        // Get original details first to update acad_absensi if needed
        $stmtJ = db()->prepare("SELECT kelas_id, jam_ke, tanggal FROM acad_jurnal WHERE id = ?");
        $stmtJ->execute([$id]);
        $orig = $stmtJ->fetch();

        $stmt = db()->prepare("
            UPDATE acad_jurnal SET tujuan_pembelajaran = ?, indikator_tp = ?, catatan = ?, siswa_tidak_hadir = ? WHERE id = ?
        ");
        $stmt->execute([$tp, $iptp, $catatan,
            is_array($siswa_tidak_hadir) ? json_encode($siswa_tidak_hadir) : $siswa_tidak_hadir, $id]);

        if ($orig && is_array($absensi) && count($absensi) > 0) {
            $kelas_id = $orig['kelas_id'];
            $jam_ke = $orig['jam_ke'];
            $tanggal = $orig['tanggal'];
            $active_year = get_active_academic_year();
            $year_id = $active_year['id'] ?? 0;

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
        json_response(200, true, 'Jurnal berhasil diperbarui.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("DELETE FROM acad_jurnal WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Jurnal berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Report: Jurnal Kelas / Pimpinan / Wali
 */
function reportJurnal($user) {
    try {
        $type = isset($_GET['type']) ? $_GET['type'] : 'kelas';
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : $tanggal;
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        $where = "j.academic_year_id = ? AND j.tanggal BETWEEN ? AND ?";
        $params = [$year_id, $tanggal, $tanggal_akhir];

        if ($kelas_id > 0) {
            $where .= " AND j.kelas_id = ?";
            $params[] = $kelas_id;
        }

        if ($type === 'guru' && $user['role'] !== 'superadmin') {
            $where .= " AND j.guru_id = ?";
            $params[] = $user['user_id'];
        }

        $stmt = db()->prepare("
            SELECT j.*, u.nama_lengkap as guru_nama, k.nama_kelas, m.nama_mapel, m.nama_mapel as kode_mapel
            FROM acad_jurnal j
            JOIN users u ON j.guru_id = u.id
            LEFT JOIN sch_kelas k ON j.kelas_id = k.id
            LEFT JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE $where
            ORDER BY j.tanggal ASC, k.nama_kelas ASC, j.jam_ke ASC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        json_response(200, true, 'Laporan jurnal dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function getJurnalMeta($user) {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;
        $isAdmin = $user['role'] === 'superadmin';
        $guru_id = $isAdmin ? 0 : $user['user_id'];

        // Get classes this teacher teaches (or all for admin)
        if ($isAdmin) {
            $stmt = db()->query("SELECT id, nama_kelas, rombel as tingkat FROM sch_kelas ORDER BY rombel, nama_kelas");
        } else {
            $stmt = db()->prepare("
                SELECT DISTINCT k.id, k.nama_kelas, k.tingkat
                FROM sch_distribusi m
                JOIN sch_kelas k ON m.kelas_id = k.id
                WHERE m.guru_id = ? AND m.academic_year_id = ?
                ORDER BY k.tingkat, k.nama_kelas
            ");
            $stmt->execute([$guru_id, $year_id]);
        }
        $classes = $stmt->fetchAll();

        // Get subjects this teacher teaches (or all for admin)
        if ($isAdmin) {
            $stmt = db()->query("SELECT id, nama_mapel as kode_mapel, nama_mapel FROM sch_mapel WHERE status = 1 ORDER BY nama_mapel");
        } else {
            $stmt = db()->prepare("
                SELECT DISTINCT mp.id, mp.kode_mapel, mp.nama_mapel
                FROM sch_distribusi m
                JOIN sch_mapel mp ON m.mapel_id = mp.id
                WHERE m.guru_id = ? AND m.academic_year_id = ?
                ORDER BY mp.nama_mapel
            ");
            $stmt->execute([$guru_id, $year_id]);
        }
        $subjects = $stmt->fetchAll();

        // Get students for attendance
        $students = [];
        if (count($classes) > 0) {
            $classIds = array_column($classes, 'id');
            $classNames = [];
            foreach ($classes as $c) $classNames[$c['id']] = $c['nama_kelas'];

            // Map sch_kelas nama to students.kelas
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

function listTeachers($user) {
    try {
        $stmt = db()->query("SELECT id, nama_lengkap, username FROM users WHERE role = 'guru' AND status = 1 ORDER BY nama_lengkap ASC");
        json_response(200, true, 'Data guru dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
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

function getTeacherSchedules($user) {
    $guru_user_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
    
    if ($guru_user_id <= 0) json_response(400, false, 'Guru ID tidak valid.');
    
    try {
        $dayMap = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
        ];
        $d = new DateTime($tanggal);
        $hariIni = $dayMap[(int)$d->format('N')] ?? 'Senin';
        
        // Find username from users table for this guru_user_id
        $stmtUser = db()->prepare("SELECT username FROM users WHERE id = ?");
        $stmtUser->execute([$guru_user_id]);
        $username = $stmtUser->fetchColumn();
        if (!$username) json_response(404, false, 'Akun guru tidak ditemukan.');
        
        // Find sch_guru.id
        $stmtGuru = db()->prepare("SELECT id FROM sch_guru WHERE kode_guru = ?");
        $stmtGuru->execute([$username]);
        $guru = $stmtGuru->fetch();
        if (!$guru) {
            json_response(200, true, 'Jadwal kosong.', ['hari' => $hariIni, 'tanggal' => $tanggal, 'jadwal' => []]);
            return;
        }
        
        // Fetch schedules
        $stmt = db()->prepare("
            SELECT j.id,
                   k.id as kelas_id, k.nama_kelas, k.rombel,
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
        $rawJadwal = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check which slots already have journals
        $activeYear = get_active_academic_year();
        $yearId = $activeYear['id'] ?? 0;
        
        $stmtJurnal = db()->prepare("
            SELECT id, jam_ke, kelas_id, mapel_id
            FROM acad_jurnal
            WHERE guru_id = ? AND tanggal = ? AND academic_year_id = ?
        ");
        $stmtJurnal->execute([$guru_user_id, $tanggal, $yearId]);
        $jurnalList = $stmtJurnal->fetchAll(PDO::FETCH_ASSOC);
        
        $jadwal = mergeConsecutiveScheduleSlots($rawJadwal, $jurnalList);
        
        json_response(200, true, 'Jadwal dimuat.', [
            'hari' => $hariIni,
            'tanggal' => $tanggal,
            'jadwal' => $jadwal
        ]);
    } catch (Exception $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function getStudentsByKelas($user) {
    $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
    if ($kelas_id <= 0) json_response(400, false, 'Kelas ID tidak valid.');
    
    try {
        $stmtK = db()->prepare("SELECT nama_kelas FROM sch_kelas WHERE id = ?");
        $stmtK->execute([$kelas_id]);
        $kelas = $stmtK->fetch();
        if (!$kelas) json_response(404, false, 'Kelas tidak ditemukan.');
        
        $stmt = db()->prepare("SELECT id, nis, nama FROM students WHERE kelas = ? AND status = 1 ORDER BY nama");
        $stmt->execute([$kelas['nama_kelas']]);
        json_response(200, true, 'Siswa dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
