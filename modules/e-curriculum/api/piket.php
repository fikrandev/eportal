<?php
/**
 * E-Curriculum Piket Guru API
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listPiket($user);
        break;
    case 'save':
        savePiket($user);
        break;
    case 'delete':
        deletePiket($user);
        break;
    case 'rekap':
        rekapPiket($user);
        break;
    case 'available_guru':
        availableGuru($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function listPiket($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

        $where = "1=1";
        $params = [];

        if (!empty($tanggal_akhir)) {
            $where .= " AND p.tanggal BETWEEN ? AND ?";
            $params[] = $tanggal;
            $params[] = $tanggal_akhir;
        } else {
            $where .= " AND p.tanggal = ?";
            $params[] = $tanggal;
        }

        $stmt = db()->prepare("
            SELECT p.*, 
                   u1.nama_lengkap as guru_piket_nama,
                   u2.nama_lengkap as guru_diganti_nama,
                   k.nama_kelas
            FROM acad_piket p
            JOIN users u1 ON p.guru_id = u1.id
            LEFT JOIN users u2 ON p.guru_diganti_id = u2.id
            LEFT JOIN sch_kelas k ON p.kelas_id = k.id
            WHERE $where
            ORDER BY p.tanggal DESC, u1.nama_lengkap ASC
        ");
        $stmt->execute($params);
        json_response(200, true, 'Data piket dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function savePiket($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    acad_require_admin($user);

    $input = get_input();
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $guru_id = isset($input['guru_id']) ? (int)$input['guru_id'] : 0;
    $guru_diganti_id = isset($input['guru_diganti_id']) ? (int)$input['guru_diganti_id'] : null;
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : null;
    $jam_ke = isset($input['jam_ke']) ? trim($input['jam_ke']) : null;
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';

    if ($guru_id <= 0) json_response(400, false, 'Guru piket wajib dipilih.');

    try {
        $stmt = db()->prepare("
            INSERT INTO acad_piket (tanggal, guru_id, guru_diganti_id, kelas_id, jam_ke, catatan)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tanggal, $guru_id, $guru_diganti_id ?: null, $kelas_id ?: null, $jam_ke, $catatan]);
        json_response(201, true, 'Piket berhasil ditambahkan.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deletePiket($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    acad_require_admin($user);

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("DELETE FROM acad_piket WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Piket berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function rekapPiket($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-01');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');

        $stmt = db()->prepare("
            SELECT u.nama_lengkap as guru_nama,
                   COUNT(*) as total_piket
            FROM acad_piket p
            JOIN users u ON p.guru_id = u.id
            WHERE p.tanggal BETWEEN ? AND ?
            GROUP BY u.id, u.nama_lengkap
            ORDER BY total_piket DESC
        ");
        $stmt->execute([$tanggal, $tanggal_akhir]);
        json_response(200, true, 'Rekap piket dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function availableGuru($user) {
    $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

    try {
        // 1. Day of week in Indonesian
        $dayMap = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu'
        ];
        $d = new DateTime($tanggal);
        $dayOfWeek = (int)$d->format('N');
        $hariIni = $dayMap[$dayOfWeek] ?? 'Senin';

        // 2. Get all active teachers
        $allGuru = db()->query("
            SELECT id, nama_lengkap, username 
            FROM users 
            WHERE role = 'guru' AND status = 1 
            ORDER BY nama_lengkap ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Get absent teachers on this date from acad_ketidakhadiran
        $stmtAbsent = db()->prepare("
            SELECT k.guru_id, k.jenis, k.catatan, k.status, u.nama_lengkap, u.username
            FROM acad_ketidakhadiran k
            JOIN users u ON k.guru_id = u.id
            WHERE k.tanggal = ? AND (k.status IS NULL OR k.status IN ('Pending', 'Approved', 'Disetujui'))
        ");
        $stmtAbsent->execute([$tanggal]);
        $absentRecords = $stmtAbsent->fetchAll(PDO::FETCH_ASSOC);
        
        $absentInfoMap = [];
        $absentGuruIds = [];
        foreach ($absentRecords as $ar) {
            $gid = (int)$ar['guru_id'];
            $absentGuruIds[] = $gid;
            $ket = trim(($ar['jenis'] ?? '') . ($ar['catatan'] ? ' (' . $ar['catatan'] . ')' : ''));
            $absentInfoMap[$gid] = $ket ?: 'Izin / Tidak Hadir';
        }

        // 4. Get schedule data on $hariIni from sch_jadwal
        $jadwalRows = [];
        try {
            $stmtJadwal = db()->prepare("
                SELECT 
                    j.id as jadwal_id,
                    sg.id as sch_guru_id,
                    sg.kode_guru,
                    sg.nama_guru,
                    k.id as kelas_id,
                    k.nama_kelas,
                    jb.jam_ke,
                    jb.nama_jam,
                    m.nama_mapel
                FROM sch_jadwal j
                JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
                JOIN sch_distribusi d ON j.distribusi_id = d.id
                JOIN sch_guru sg ON d.guru_id = sg.id
                JOIN sch_kelas k ON j.kelas_id = k.id
                JOIN sch_mapel m ON d.mapel_id = m.id
                WHERE jb.hari = ?
                ORDER BY sg.nama_guru, k.nama_kelas, jb.jam_ke ASC
            ");
            $stmtJadwal->execute([$hariIni]);
            $jadwalRows = $stmtJadwal->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $ej) {
            // Safe fallback if schedule tables not initialized
            $jadwalRows = [];
        }

        // Match sch_guru to users table
        $userByUsername = [];
        $userByName = [];
        foreach ($allGuru as $g) {
            $userByUsername[strtolower(trim($g['username']))] = (int)$g['id'];
            $cleanName = preg_replace('/[^a-z0-9]/', '', strtolower($g['nama_lengkap']));
            $userByName[$cleanName] = (int)$g['id'];
        }

        $teacherClasses = [];
        $scheduledGuruIds = [];
        $teacherJpCount = [];

        foreach ($jadwalRows as $row) {
            $matchedUserId = null;
            $kodeLower = strtolower(trim($row['kode_guru']));
            if (isset($userByUsername[$kodeLower])) {
                $matchedUserId = $userByUsername[$kodeLower];
            } else {
                $cleanSchName = preg_replace('/[^a-z0-9]/', '', strtolower($row['nama_guru']));
                if (isset($userByName[$cleanSchName])) {
                    $matchedUserId = $userByName[$cleanSchName];
                } else {
                    foreach ($allGuru as $ag) {
                        $agClean = preg_replace('/[^a-z0-9]/', '', strtolower($ag['nama_lengkap']));
                        if ($agClean && (strpos($cleanSchName, $agClean) !== false || strpos($agClean, $cleanSchName) !== false)) {
                            $matchedUserId = (int)$ag['id'];
                            break;
                        }
                    }
                }
            }

            if ($matchedUserId) {
                $scheduledGuruIds[$matchedUserId] = true;
                $teacherJpCount[$matchedUserId] = ($teacherJpCount[$matchedUserId] ?? 0) + 1;

                if (!isset($teacherClasses[$matchedUserId])) {
                    $teacherClasses[$matchedUserId] = [];
                }
                $kId = (int)$row['kelas_id'];
                if (!isset($teacherClasses[$matchedUserId][$kId])) {
                    $teacherClasses[$matchedUserId][$kId] = [
                        'kelas_id' => $kId,
                        'nama_kelas' => $row['nama_kelas'],
                        'mapel' => $row['nama_mapel'],
                        'jam_list' => []
                    ];
                }
                $teacherClasses[$matchedUserId][$kId]['jam_list'][] = $row['jam_ke'];
            }
        }

        // Format classes per teacher
        $formattedTeacherClasses = [];
        foreach ($teacherClasses as $gid => $classes) {
            $formattedTeacherClasses[$gid] = [];
            foreach ($classes as $c) {
                sort($c['jam_list'], SORT_NUMERIC);
                $jamStr = implode(', ', $c['jam_list']);
                if (count($c['jam_list']) > 1) {
                    $first = reset($c['jam_list']);
                    $last = end($c['jam_list']);
                    if ((int)$last - (int)$first + 1 === count($c['jam_list'])) {
                        $jamStr = $first . '-' . $last;
                    }
                }
                $formattedTeacherClasses[$gid][] = [
                    'kelas_id' => $c['kelas_id'],
                    'nama_kelas' => $c['nama_kelas'],
                    'mapel' => $c['mapel'],
                    'jam_ke' => $jamStr
                ];
            }
        }

        // 5. Categorize teachers
        $piketCandidates = []; // Guru bebas jadwal hari ini & tidak absen (Rekomendasi Utama Piket)
        $busyTeachers = [];    // Guru yang ada jam mengajar hari ini
        $absentTeachers = [];  // Guru yang izin/sakit hari ini

        foreach ($allGuru as $g) {
            $gid = (int)$g['id'];
            $isAbsent = in_array($gid, $absentGuruIds);
            $hasSchedule = isset($scheduledGuruIds[$gid]);
            $jpCount = $teacherJpCount[$gid] ?? 0;
            $myClasses = $formattedTeacherClasses[$gid] ?? [];

            $info = [
                'id' => $gid,
                'nama_lengkap' => $g['nama_lengkap'],
                'username' => $g['username'],
                'has_schedule' => $hasSchedule,
                'is_absent' => $isAbsent,
                'total_jp' => $jpCount,
                'alasan_absen' => $absentInfoMap[$gid] ?? '',
                'classes' => $myClasses
            ];

            if ($isAbsent) {
                $absentTeachers[] = $info;
            }

            if (!$hasSchedule && !$isAbsent) {
                $piketCandidates[] = $info;
            } else if ($hasSchedule) {
                $busyTeachers[] = $info;
            }
        }

        // 6. Get all classes list for fallback
        $allKelas = [];
        try {
            $stmtKelas = db()->query("
                SELECT id, nama_kelas, rombel 
                FROM sch_kelas 
                ORDER BY rombel ASC, nama_kelas ASC
            ");
            $allKelas = $stmtKelas->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $ek) {}

        if (empty($allKelas)) {
            try {
                $stmtRef = db()->query("SELECT id, nama_kelas, tingkat as rombel FROM ref_kelas ORDER BY tingkat ASC, nama_kelas ASC");
                $allKelas = $stmtRef->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $er) {}
        }

        json_response(200, true, 'Data guru dan jadwal piket dimuat.', [
            'hari' => $hariIni,
            'tanggal' => $tanggal,
            'piket_candidates' => $piketCandidates,
            'busy_teachers' => $busyTeachers,
            'absent_teachers' => $absentTeachers,
            'all_teachers' => $allGuru,
            'teacher_classes' => $formattedTeacherClasses,
            'all_classes' => $allKelas,
            'available' => !empty($piketCandidates) ? $piketCandidates : $allGuru,
            'absent' => $absentTeachers
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
