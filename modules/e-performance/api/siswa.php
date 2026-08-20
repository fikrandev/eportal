<?php
/**
 * E-Performance — Siswa API
 * CRUD + Sampling + Generate Login
 */
require_once __DIR__ . '/config_perf.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':             listSiswa(); break;
    case 'create':           createSiswa(); break;
    case 'update':           updateSiswa(); break;
    case 'delete':           deleteSiswa(); break;
    case 'bulk_delete':      bulkDeleteSiswa(); break;
    case 'import':           importSiswa(); break;
    case 'get_portal_students': getPortalStudents(); break;
    case 'import_portal_students': importPortalStudents(); break;
    case 'generate_sampling': generateSampling(); break;
    case 'sampling_list':    samplingList(); break;
    case 'get_guru_mapping': getGuruMapping(); break;
    case 'save_guru_mapping': saveGuruMapping(); break;
    case 'list_kelas':       listKelas(); break;
    case 'list_ptk_target':  listPtkTarget(); break;
    case 'list_ptk_by_tupoksi': listPtkByTupoksi(); break;
    case 'list_siswa_by_kelas': listSiswaByKelas(); break;
    case 'save_penugasan':   savePenugasan(); break;
    case 'copy_penugasan':   copyPenugasan(); break;
    default: json_response(400, false, 'Action tidak valid.');
}

function listSiswa() {
    perf_auth_check();
    try {
        $periodeId = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
        
        // Ambil data siswa
        $stmtSiswa = db()->prepare("SELECT * FROM perf_siswa WHERE status = 1 ORDER BY kelas ASC, nama_siswa ASC");
        $stmtSiswa->execute();
        $siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);
        
        // Ambil data penugasan
        $stmtPenugasan = db()->prepare("
            SELECT psg.perf_siswa_id, p.nama, p.jenis_ptk 
            FROM perf_siswa_guru psg
            JOIN perf_ptk p ON psg.perf_ptk_id = p.id
            WHERE psg.periode_id = ?
        ");
        $stmtPenugasan->execute([$periodeId]);
        $penugasanData = $stmtPenugasan->fetchAll(PDO::FETCH_ASSOC);
        
        // Susun mapping penugasan
        $mapPenugasan = [];
        foreach ($penugasanData as $p) {
            $sid = $p['perf_siswa_id'];
            if (!isset($mapPenugasan[$sid])) $mapPenugasan[$sid] = [];
            $mapPenugasan[$sid][] = $p['nama'] . ' (' . $p['jenis_ptk'] . ')';
        }
        
        // Group by kelas
        $grouped = [];
        foreach ($siswaList as $s) {
            $k = $s['kelas'];
            if (!isset($grouped[$k])) $grouped[$k] = [];
            $s['target_penilaian'] = isset($mapPenugasan[$s['id']]) ? implode(', ', $mapPenugasan[$s['id']]) : '-';
            $grouped[$k][] = $s;
        }
        
        json_response(200, true, 'Data Siswa Grouped.', $grouped);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function createSiswa() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $nama = sanitize($input['nama_siswa'] ?? '');
    $kelas = sanitize($input['kelas'] ?? '');
    if (empty($nama) || empty($kelas)) json_response(400, false, 'Nama dan Kelas wajib diisi.');

    try {
        $stmt = db()->prepare("INSERT INTO perf_siswa (nama_siswa, kelas) VALUES (?, ?)");
        $stmt->execute([$nama, $kelas]);
        json_response(201, true, 'Siswa berhasil ditambahkan.', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function updateSiswa() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = (int)($input['id'] ?? 0);
    try {
        $stmt = db()->prepare("UPDATE perf_siswa SET nama_siswa=?, kelas=? WHERE id=?");
        $stmt->execute([sanitize($input['nama_siswa'] ?? ''), sanitize($input['kelas'] ?? ''), $id]);
        json_response(200, true, 'Siswa berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function deleteSiswa() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = (int)($input['id'] ?? 0);
    try {
        // Delete related user
        $stmt = db()->prepare("SELECT perf_user_id FROM perf_siswa WHERE id = ?");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        if ($s && $s['perf_user_id']) {
            db()->prepare("DELETE FROM perf_users WHERE id = ?")->execute([$s['perf_user_id']]);
        }
        db()->prepare("DELETE FROM perf_siswa WHERE id = ?")->execute([$id]);
        json_response(200, true, 'Siswa berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function bulkDeleteSiswa() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $ids = $input['ids'] ?? [];
    if (empty($ids)) json_response(400, false, 'Tidak ada data terpilih.');

    try {
        db()->beginTransaction();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        // Delete related users
        $stmt = db()->prepare("SELECT perf_user_id FROM perf_siswa WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $userIds = array_filter($userIds); // Remove nulls

        if (!empty($userIds)) {
            $up = implode(',', array_fill(0, count($userIds), '?'));
            db()->prepare("DELETE FROM perf_users WHERE id IN ($up)")->execute($userIds);
        }

        // Delete siswa
        db()->prepare("DELETE FROM perf_siswa WHERE id IN ($placeholders)")->execute($ids);
        
        db()->commit();
        json_response(200, true, count($ids) . ' siswa berhasil dihapus.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}


function importSiswa() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $rows = $input['data'] ?? [];
    if (empty($rows)) json_response(400, false, 'Data import kosong.');

    $inserted = 0;
    try {
        db()->beginTransaction();
        foreach ($rows as $row) {
            $nama = trim($row['Nama'] ?? $row['nama_siswa'] ?? '');
            $kelas = trim($row['Kelas'] ?? $row['kelas'] ?? '');
            if (empty($nama) || empty($kelas)) continue;
            $stmt = db()->prepare("INSERT INTO perf_siswa (nama_siswa, kelas) VALUES (?, ?)");
            $stmt->execute([$nama, $kelas]);
            $inserted++;
        }
        db()->commit();
        json_response(200, true, "$inserted siswa berhasil diimport.");
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function generateSampling() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $periodeId = (int)($input['periode_id'] ?? 0);
    $jumlah = (int)($input['jumlah'] ?? 10);
    $kelas = $input['kelas'] ?? '';

    if ($periodeId <= 0) json_response(400, false, 'Periode harus dipilih.');

    try {
        // Get eligible siswa
        $sql = "SELECT id FROM perf_siswa WHERE status = 1 AND perf_user_id IS NOT NULL";
        $params = [];
        if ($kelas) { $sql .= " AND kelas = ?"; $params[] = $kelas; }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $allSiswa = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($allSiswa) < $jumlah) $jumlah = count($allSiswa);
        if ($jumlah <= 0) json_response(400, false, 'Tidak ada siswa yang memenuhi syarat.');

        // Random pick
        $picked = array_rand(array_flip($allSiswa), $jumlah);
        if (!is_array($picked)) $picked = [$picked];

        // Clear existing sampling for this periode
        $stmt = db()->prepare("DELETE FROM perf_sampling WHERE periode_id = ?");
        $stmt->execute([$periodeId]);

        // Insert new sampling
        $stmt = db()->prepare("INSERT INTO perf_sampling (perf_siswa_id, periode_id) VALUES (?, ?)");
        foreach ($picked as $sid) {
            $stmt->execute([$sid, $periodeId]);
        }

        json_response(200, true, count($picked) . " siswa berhasil di-sampling.");
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function samplingList() {
    perf_auth_check();
    $periodeId = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    try {
        $stmt = db()->prepare("
            SELECT s.*, ps.nama_siswa, ps.kelas, ps.username
            FROM perf_sampling s
            JOIN perf_siswa ps ON s.perf_siswa_id = ps.id
            WHERE s.periode_id = ?
            ORDER BY ps.kelas, ps.nama_siswa
        ");
        $stmt->execute([$periodeId]);
        json_response(200, true, 'Sampling list.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function getPortalStudents() {
    perf_require_admin();
    try {
        $active = get_active_academic_year();
        if (!$active) {
            json_response(200, true, 'Portal Students.', [
                'students' => [],
                'classes' => [],
                'active_year' => 'Tidak Ada Tahun Pelajaran Aktif'
            ]);
            return;
        }
        
        $ayId = $active['id'];
        
        // Fetch ALL students for the active year (using academic_year_id)
        $stmt = db()->prepare("SELECT id, nama, kelas FROM students WHERE academic_year_id = ? ORDER BY kelas ASC, nama ASC");
        $stmt->execute([$ayId]);
        $students = $stmt->fetchAll();

        // Get unique classes for checkboxes
        $classes = array_unique(array_column($students, 'kelas'));
        sort($classes);

        json_response(200, true, 'Portal Students.', [
            'students' => $students,
            'classes' => $classes,
            'active_year' => ($active['tahun_ajaran'] ?? '-') . ' - ' . (($active['semester'] ?? 0) == 1 ? 'Ganjil' : 'Genap')
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}



function importPortalStudents() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $ids = $input['ids'] ?? [];
    
    if (empty($ids)) json_response(400, false, 'Tidak ada siswa yang dipilih.');

    try {
        // Fetch student details from portal (including NIS for username)
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT nis, nama, kelas, tanggal_lahir FROM students WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $students = $stmt->fetchAll();

        $inserted = 0;
        $skipped = 0;
        
        db()->beginTransaction();
        foreach ($students as $s) {
            // Check if already exists in perf_siswa (by nis)
            $check = db()->prepare("SELECT id FROM perf_siswa WHERE username = ?");
            $check->execute([$s['nis']]);
            if ($check->fetch()) {
                $skipped++;
                continue;
            }

            // Insert into perf_siswa
            $rawPw = !empty($s['tanggal_lahir']) ? date('dmY', strtotime($s['tanggal_lahir'])) : $s['nis'];
            $stmtIns = db()->prepare("INSERT INTO perf_siswa (nama_siswa, kelas, username, password_plain) VALUES (?, ?, ?, ?)");
            $stmtIns->execute([$s['nama'], $s['kelas'], $s['nis'], $rawPw]);
            $sid = db()->lastInsertId();

            // Auto-create perf_users entry (perf_users doesn't have perf_siswa_id, instead perf_siswa has perf_user_id)
            $rawPw = !empty($s['tanggal_lahir']) ? date('dmY', strtotime($s['tanggal_lahir'])) : $s['nis'];
            $hashedPw = password_hash($rawPw, PASSWORD_DEFAULT);
            $stmtUser = db()->prepare("INSERT INTO perf_users (username, password, nama_lengkap, role) VALUES (?, ?, ?, 'siswa')");
            $stmtUser->execute([$s['nis'], $hashedPw, $s['nama']]);
            $uid = db()->lastInsertId();

            // Link perf_siswa to perf_users
            db()->prepare("UPDATE perf_siswa SET perf_user_id = ? WHERE id = ?")->execute([$uid, $sid]);

            $inserted++;

        }
        db()->commit();
        
        json_response(200, true, "$inserted siswa berhasil diimport. $skipped data dilewati karena sudah ada.");
    } catch (PDOException $e) {

        db()->rollBack();
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function getGuruMapping() {
    perf_require_admin();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) json_response(400, false, 'ID Siswa tidak valid.');

    try {
        // Ambil semua PTK selain TU
        $stmt = db()->query("SELECT id, niy, nama, jenis_ptk FROM perf_ptk WHERE jenis_ptk != 'tu' AND status = 1 ORDER BY jenis_ptk, nama ASC");
        $gurus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ambil mapping yang sudah ada
        $stmtMap = db()->prepare("SELECT perf_ptk_id FROM perf_siswa_guru WHERE perf_siswa_id = ?");
        $stmtMap->execute([$id]);
        $mappedIds = $stmtMap->fetchAll(PDO::FETCH_COLUMN);

        json_response(200, true, 'Data Mapping Guru', [
            'gurus' => $gurus,
            'mapped_ids' => $mappedIds
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function saveGuruMapping() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = (int)($input['id'] ?? 0);
    $guru_ids = $input['guru_ids'] ?? [];

    if (!$id) json_response(400, false, 'ID Siswa tidak valid.');

    try {
        db()->beginTransaction();
        
        // Hapus mapping lama
        $stmtDel = db()->prepare("DELETE FROM perf_siswa_guru WHERE perf_siswa_id = ?");
        $stmtDel->execute([$id]);

        // Insert mapping baru
        if (!empty($guru_ids)) {
            $stmtIns = db()->prepare("INSERT INTO perf_siswa_guru (perf_siswa_id, perf_ptk_id) VALUES (?, ?)");
            foreach ($guru_ids as $ptk_id) {
                $stmtIns->execute([$id, (int)$ptk_id]);
            }
        }

        db()->commit();
        json_response(200, true, 'Mapping guru berhasil disimpan.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function listKelas() {
    perf_require_admin();
    try {
        $stmt = db()->query("SELECT DISTINCT kelas FROM students WHERE kelas != '' ORDER BY kelas ASC");
        json_response(200, true, 'Data Kelas.', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function listPtkTarget() {
    perf_require_admin();
    try {
        $stmt = db()->query("SELECT DISTINCT jenis_ptk FROM perf_ptk WHERE status = 1 AND jenis_ptk != '' ORDER BY jenis_ptk ASC");
        json_response(200, true, 'Data Tupoksi PTK.', $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function listPtkByTupoksi() {
    perf_require_admin();
    $tupoksi = $_GET['tupoksi'] ?? '';
    $kelas = $_GET['kelas'] ?? '';
    $periode_id = (int)($_GET['periode_id'] ?? 0);
    
    if (!$tupoksi) json_response(400, false, 'Tupoksi tidak valid.');
    
    try {
        $stmt = db()->prepare("SELECT id, nama, jenis_ptk FROM perf_ptk WHERE jenis_ptk = ? AND status = 1 ORDER BY nama ASC");
        $stmt->execute([$tupoksi]);
        $ptks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $assignedIds = [];
        if ($kelas && $periode_id) {
            $stmtA = db()->prepare("
                SELECT DISTINCT p.perf_ptk_id 
                FROM perf_siswa_guru p
                JOIN perf_siswa s ON p.perf_siswa_id = s.id
                WHERE s.kelas = ? AND p.periode_id = ?
            ");
            $stmtA->execute([$kelas, $periode_id]);
            $assignedIds = $stmtA->fetchAll(PDO::FETCH_COLUMN);
        }
        
        json_response(200, true, 'Data PTK.', [
            'ptks' => $ptks,
            'assigned_ids' => $assignedIds
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function listSiswaByKelas() {
    perf_require_admin();
    $kelas = $_GET['kelas'] ?? '';
    $periode_id = (int)($_GET['periode_id'] ?? 0);
    
    if (!$kelas) json_response(400, false, 'Kelas tidak valid.');
    
    try {
        // Ambil semua siswa di kelas tersebut DARI PORTAL
        $stmt = db()->prepare("SELECT id, nis, nama as nama_siswa FROM students WHERE kelas = ? ORDER BY nama ASC");
        $stmt->execute([$kelas]);
        $siswaList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $assignments = [];
        if ($periode_id) {
            $stmtA = db()->prepare("
                SELECT s.username as nis, p.perf_ptk_id 
                FROM perf_siswa_guru p
                JOIN perf_siswa s ON p.perf_siswa_id = s.id
                WHERE s.kelas = ? AND p.periode_id = ?
            ");
            $stmtA->execute([$kelas, $periode_id]);
            $rows = $stmtA->fetchAll(PDO::FETCH_ASSOC);
            
            $nisMap = [];
            foreach ($rows as $r) {
                $nis = $r['nis'];
                if (!isset($nisMap[$nis])) $nisMap[$nis] = [];
                $nisMap[$nis][] = $r['perf_ptk_id'];
            }
            
            foreach ($siswaList as $s) {
                if (isset($nisMap[$s['nis']])) {
                    $assignments[$s['id']] = $nisMap[$s['nis']];
                }
            }
        }
        
        json_response(200, true, 'Data Siswa by Kelas.', [
            'siswa' => $siswaList,
            'assignments' => $assignments
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function savePenugasan() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    
    $periode_id = (int)($input['periode_id'] ?? 0);
    $tupoksi = $input['tupoksi'] ?? '';
    $ptk_ids = $input['ptk_ids'] ?? []; // Array ID PTK yang dicentang
    $kelas = $input['kelas'] ?? '';
    $siswa_ids = $input['siswa_ids'] ?? []; // Array ID siswa yang dicentang
    
    if (!$periode_id || !$tupoksi || !$kelas || empty($siswa_ids)) {
        json_response(400, false, 'Data tidak lengkap. Pastikan Kelas, Tupoksi, dan minimal 1 Siswa terpilih.');
    }
    
    try {
        db()->beginTransaction();
        
        // --- AUTO IMPORT SISWA ---
        $portalSiswaIds = $siswa_ids;
        $perfSiswaIds = [];
        
        if (!empty($portalSiswaIds)) {
            $placeholdersPortal = implode(',', array_fill(0, count($portalSiswaIds), '?'));
            $stmtPortal = db()->prepare("SELECT id, nis, nama, kelas, tanggal_lahir FROM students WHERE id IN ($placeholdersPortal)");
            $stmtPortal->execute($portalSiswaIds);
            $students = $stmtPortal->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($students as $s) {
                // Check if already exists in perf_siswa
                $check = db()->prepare("SELECT id FROM perf_siswa WHERE username = ?");
                $check->execute([$s['nis']]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $perfSiswaIds[] = $existing['id'];
                } else {
                    $rawPw = !empty($s['tanggal_lahir']) ? date('dmY', strtotime($s['tanggal_lahir'])) : $s['nis'];
                    $stmtInsS = db()->prepare("INSERT INTO perf_siswa (nama_siswa, kelas, username, password_plain) VALUES (?, ?, ?, ?)");
                    $stmtInsS->execute([$s['nama'], $s['kelas'], $s['nis'], $rawPw]);
                    $sid = db()->lastInsertId();

                    $hashedPw = password_hash($rawPw, PASSWORD_DEFAULT);
                    $stmtUser = db()->prepare("INSERT INTO perf_users (username, password, nama_lengkap, role) VALUES (?, ?, ?, 'siswa')");
                    $stmtUser->execute([$s['nis'], $hashedPw, $s['nama']]);
                    $uid = db()->lastInsertId();

                    db()->prepare("UPDATE perf_siswa SET perf_user_id = ? WHERE id = ?")->execute([$uid, $sid]);
                    
                    $perfSiswaIds[] = $sid;
                }
            }
        }
        // --- END AUTO IMPORT ---

        // Ambil semua PTK yang memiliki tupoksi ini
        $stmtPtk = db()->prepare("SELECT id FROM perf_ptk WHERE jenis_ptk = ?");
        $stmtPtk->execute([$tupoksi]);
        $validPtkIds = $stmtPtk->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($validPtkIds) && !empty($perfSiswaIds)) {
            $placeholdersPtk = implode(',', array_fill(0, count($validPtkIds), '?'));
            $placeholdersSiswa = implode(',', array_fill(0, count($perfSiswaIds), '?'));
            
            // Hapus penugasan siswa yang dicentang untuk SEMUA PTK di tupoksi ini
            $delParams = array_merge([$periode_id], $validPtkIds, $perfSiswaIds);
            $stmtDel = db()->prepare("DELETE FROM perf_siswa_guru WHERE periode_id = ? AND perf_ptk_id IN ($placeholdersPtk) AND perf_siswa_id IN ($placeholdersSiswa)");
            $stmtDel->execute($delParams);
        }
        
        // Insert penugasan baru hanya untuk PTK yang dicentang
        if (!empty($ptk_ids) && !empty($perfSiswaIds)) {
            $stmtIns = db()->prepare("INSERT IGNORE INTO perf_siswa_guru (periode_id, perf_siswa_id, perf_ptk_id) VALUES (?, ?, ?)");
            foreach ($perfSiswaIds as $sid) {
                foreach ($ptk_ids as $pid) {
                    if (in_array($pid, $validPtkIds)) {
                        $stmtIns->execute([$periode_id, (int)$sid, (int)$pid]);
                    }
                }
            }
        }
        
        db()->commit();
        json_response(200, true, 'Penugasan berhasil disimpan.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function copyPenugasan() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    
    $periode_id = (int)($input['periode_id'] ?? 0);
    $source_id = (int)($input['source_siswa_id'] ?? 0);
    $target_ids = $input['target_siswa_ids'] ?? [];
    
    if (!$periode_id || !$source_id || empty($target_ids)) {
        json_response(400, false, 'Data tidak lengkap. Pilih minimal 1 siswa target.');
    }
    
    try {
        db()->beginTransaction();
        
        // --- AUTO IMPORT SISWA ---
        $portalSiswaIds = $target_ids;
        $perfSiswaIds = [];
        
        if (!empty($portalSiswaIds)) {
            $placeholdersPortal = implode(',', array_fill(0, count($portalSiswaIds), '?'));
            $stmtPortal = db()->prepare("SELECT id, nis, nama, kelas, tanggal_lahir FROM students WHERE id IN ($placeholdersPortal)");
            $stmtPortal->execute($portalSiswaIds);
            $students = $stmtPortal->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($students as $s) {
                $check = db()->prepare("SELECT id FROM perf_siswa WHERE username = ?");
                $check->execute([$s['nis']]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $perfSiswaIds[] = $existing['id'];
                } else {
                    $rawPw = !empty($s['tanggal_lahir']) ? date('dmY', strtotime($s['tanggal_lahir'])) : $s['nis'];
                    $stmtInsS = db()->prepare("INSERT INTO perf_siswa (nama_siswa, kelas, username, password_plain) VALUES (?, ?, ?, ?)");
                    $stmtInsS->execute([$s['nama'], $s['kelas'], $s['nis'], $rawPw]);
                    $sid = db()->lastInsertId();

                    $hashedPw = password_hash($rawPw, PASSWORD_DEFAULT);
                    $stmtUser = db()->prepare("INSERT INTO perf_users (username, password, nama_lengkap, role) VALUES (?, ?, ?, 'siswa')");
                    $stmtUser->execute([$s['nis'], $hashedPw, $s['nama']]);
                    $uid = db()->lastInsertId();

                    db()->prepare("UPDATE perf_siswa SET perf_user_id = ? WHERE id = ?")->execute([$uid, $sid]);
                    
                    $perfSiswaIds[] = $sid;
                }
            }
        }
        // --- END AUTO IMPORT ---

        // Ambil penugasan dari source siswa
        $stmtSource = db()->prepare("SELECT perf_ptk_id FROM perf_siswa_guru WHERE periode_id = ? AND perf_siswa_id = ?");
        $stmtSource->execute([$periode_id, $source_id]);
        $ptkIds = $stmtSource->fetchAll(PDO::FETCH_COLUMN);
        
        // Hapus penugasan target siswa untuk periode ini (overwrite)
        if (!empty($perfSiswaIds)) {
            $placeholders = implode(',', array_fill(0, count($perfSiswaIds), '?'));
            $delParams = array_merge([$periode_id], $perfSiswaIds);
            $stmtDel = db()->prepare("DELETE FROM perf_siswa_guru WHERE periode_id = ? AND perf_siswa_id IN ($placeholders)");
            $stmtDel->execute($delParams);
        }
        
        // Insert penugasan baru dari source
        if (!empty($ptkIds) && !empty($perfSiswaIds)) {
            $stmtIns = db()->prepare("INSERT IGNORE INTO perf_siswa_guru (periode_id, perf_siswa_id, perf_ptk_id) VALUES (?, ?, ?)");
            foreach ($perfSiswaIds as $tid) {
                foreach ($ptkIds as $pid) {
                    $stmtIns->execute([$periode_id, (int)$tid, (int)$pid]);
                }
            }
        }
        
        db()->commit();
        json_response(200, true, 'Penugasan berhasil disalin ke ' . count($perfSiswaIds) . ' siswa.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}
