<?php
/**
 * E-Performance — Rekap API
 * Endpoints for generating recap reports
 */
require_once __DIR__ . '/config_perf.php';
require_once __DIR__ . '/scoring_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_diri_sendiri':
        getRekapDiriSendiri();
        break;
    case 'get_siswa':
        getRekapSiswa();
        break;
    case 'check_tambahan':
        checkTambahan();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function perf_require_rekap_access($db, $periode_id, $ptk_id) {
    $auth = perf_auth_check();
    $isAdmin = in_array($auth['role'], ['admin', 'superadmin'], true) || in_array('hasil', $auth['permissions'] ?? [], true);
    if (!$isAdmin) {
        if (empty($auth['ptk_id']) || (int)$auth['ptk_id'] !== (int)$ptk_id) {
            json_response(403, false, 'Akses ditolak. Anda hanya dapat melihat rekap penilaian Anda sendiri.');
        }
        $stmtP = $db->prepare("SELECT is_released FROM perf_periode WHERE id = ?");
        $stmtP->execute([$periode_id]);
        $isReleased = (int)$stmtP->fetchColumn();
        if ($isReleased !== 1) {
            json_response(403, false, 'Hasil penilaian periode ini belum dirilis.');
        }
    }
    return $auth;
}

function getRekapDiriSendiri() {
    $db = db();
    
    $periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    $ptk_id = isset($_GET['ptk_id']) ? (int)$_GET['ptk_id'] : 0;
    
    if (!$periode_id || !$ptk_id) {
        json_response(400, false, 'Periode ID dan PTK ID diperlukan.');
    }
    perf_require_rekap_access($db, $periode_id, $ptk_id);

    try {
        // 1. Get PTK Details
        $stmtPtk = $db->prepare("SELECT id, nama, jenis_ptk, jabatan, niy FROM perf_ptk WHERE id = ?");
        $stmtPtk->execute([$ptk_id]);
        $ptk = $stmtPtk->fetch(PDO::FETCH_ASSOC);
        if (!$ptk) json_response(404, false, 'Data PTK tidak ditemukan.');

        // 2. Get User ID (penilai_id) for this PTK
        $stmtUser = $db->prepare("SELECT id, username FROM perf_users WHERE perf_ptk_id = ? LIMIT 1");
        $stmtUser->execute([$ptk_id]);
        $perfUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if (!$perfUser) {
            // Might not have an account yet, return empty list
            json_response(200, true, 'Data Rekap Diri Sendiri', ['ptk' => $ptk, 'rekap' => []]);
            return;
        }
        $penilai_id = $perfUser['id'];

        // 3. Get Actual Tupoksi for matching questions
        $stmtTupoksi = $db->prepare("SELECT tupoksi FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmtTupoksi->execute([$perfUser['username']]);
        $tupoksiRow = $stmtTupoksi->fetch(PDO::FETCH_ASSOC);
        $actualTupoksi = ($tupoksiRow && !empty($tupoksiRow['tupoksi'])) ? $tupoksiRow['tupoksi'] : $ptk['jenis_ptk'];

        // 4. Fetch Questions targeting this PTK (including inherited Sebagai Guru questions for Waka)
        $questions = perfGetInstrumenForTarget($db, (int)$periode_id, 'self', $ptk, $actualTupoksi);

        // 5. Fetch Answers where penilai is self
        // Note: we don't strictly check penilai_type because penilai_id and dinilai_ptk_id are enough to identify "self"
        $stmtA = $db->prepare("
            SELECT instrumen_id, nilai 
            FROM perf_penilaian 
            WHERE periode_id = ? AND dinilai_ptk_id = ? AND penilai_id = ?
        ");
        $stmtA->execute([$periode_id, $ptk_id, $penilai_id]);
        $answers = [];
        while($row = $stmtA->fetch(PDO::FETCH_ASSOC)) {
            $answers[$row['instrumen_id']] = $row['nilai'];
        }

        // 6. Group and Calculate
        $grouped = [];
        foreach ($questions as $q) {
            $kat = $q['kategori'] ? trim($q['kategori']) : 'Umum';
            if (!isset($grouped[$kat])) {
                 $grouped[$kat] = [
                      'kategori' => $kat,
                      'pertanyaan' => [],
                      'total_skor' => 0,
                      'count' => 0
                 ];
            }
            // Use 0 if not answered yet, though normally it should be answered
            $skor = isset($answers[$q['instrumen_id']]) ? (int)$answers[$q['instrumen_id']] : 0;
            $grouped[$kat]['pertanyaan'][] = [
                 'teks' => $q['pertanyaan'],
                 'skor' => $skor
            ];
            $grouped[$kat]['total_skor'] += $skor;
            $grouped[$kat]['count'] += 1;
        }

        $result = [];
        foreach ($grouped as $kat => $data) {
            $rt2 = $data['count'] > 0 ? round($data['total_skor'] / $data['count'], 2) : 0;
            $konversi = round(($rt2 / 5) * 100);
            $data['rt2'] = $rt2;
            $data['konversi'] = $konversi;
            $result[] = $data;
        }

        json_response(200, true, 'Data Rekap Diri Sendiri', [
            'ptk' => $ptk,
            'rekap' => $result
        ]);

    } catch (PDOException $e) {
        json_response(500, false, 'Database Error: ' . $e->getMessage());
    }
}

function getRekapSiswa() {
    $db = db();
    
    $periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    $ptk_id = isset($_GET['ptk_id']) ? (int)$_GET['ptk_id'] : 0;
    
    if (!$periode_id || !$ptk_id) {
        json_response(400, false, 'Periode ID dan PTK ID diperlukan.');
    }
    perf_require_rekap_access($db, $periode_id, $ptk_id);

    try {
        // 1. Get PTK Details
        $stmtPtk = $db->prepare("SELECT id, nama, jenis_ptk, jabatan, niy FROM perf_ptk WHERE id = ?");
        $stmtPtk->execute([$ptk_id]);
        $ptk = $stmtPtk->fetch(PDO::FETCH_ASSOC);
        if (!$ptk) json_response(404, false, 'Data PTK tidak ditemukan.');

        // 2. Fetch Questions targeting 'Siswa' for this PTK (including inherited Guru questions for Waka)
        $questions = perfGetInstrumenForTarget($db, (int)$periode_id, 'siswa', $ptk, 'Siswa');

        if (empty($questions)) {
            json_response(200, true, 'Tidak ada instrumen untuk siswa', ['ptk' => $ptk, 'rekap' => [], 'murid_count' => 0]);
            return;
        }

        // 3. Get distinct students who assessed this PTK on these questions
        $q_ids = array_column($questions, 'instrumen_id');
        $in_q = implode(',', array_fill(0, count($q_ids), '?'));
        
        $params = array_merge([$periode_id, $ptk_id], $q_ids);
        
        $stmtPenilai = $db->prepare("
            SELECT DISTINCT penilai_id 
            FROM perf_penilaian 
            WHERE periode_id = ? AND dinilai_ptk_id = ? AND instrumen_id IN ($in_q)
            ORDER BY penilai_id ASC
        ");
        $stmtPenilai->execute($params);
        $penilai_list = $stmtPenilai->fetchAll(PDO::FETCH_COLUMN);
        
        $murid_count = count($penilai_list);
        if ($murid_count == 0) {
            json_response(200, true, 'Belum ada siswa yang menilai', ['ptk' => $ptk, 'rekap' => [], 'murid_count' => 0]);
            return;
        }

        // 4. Map penilai_id to MURID 1, MURID 2, etc.
        $murid_map = [];
        $i = 1;
        foreach ($penilai_list as $pid) {
            $murid_map[$pid] = "MURID $i";
            $i++;
        }

        // 5. Fetch all answers from these students
        $stmtA = $db->prepare("
            SELECT instrumen_id, penilai_id, nilai 
            FROM perf_penilaian 
            WHERE periode_id = ? AND dinilai_ptk_id = ? AND instrumen_id IN ($in_q)
        ");
        $stmtA->execute($params);
        
        // answers[instrumen_id][penilai_id] = nilai
        $answers = [];
        while($row = $stmtA->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($answers[$row['instrumen_id']])) $answers[$row['instrumen_id']] = [];
            $answers[$row['instrumen_id']][$row['penilai_id']] = $row['nilai'];
        }

        // 6. Group and Build Data
        $grouped = [];
        foreach ($questions as $q) {
            $kat = $q['kategori'] ? trim($q['kategori']) : 'Umum';
            if (!isset($grouped[$kat])) {
                 $grouped[$kat] = [
                      'kategori' => $kat,
                      'pertanyaan' => []
                 ];
            }
            
            $skor_murid = [];
            foreach ($penilai_list as $pid) {
                $skor_murid[$murid_map[$pid]] = isset($answers[$q['instrumen_id']][$pid]) ? (int)$answers[$q['instrumen_id']][$pid] : 0;
            }

            $grouped[$kat]['pertanyaan'][] = [
                 'teks' => $q['pertanyaan'],
                 'skor_murid' => $skor_murid // e.g. ["MURID 1" => 4, "MURID 2" => 5]
            ];
        }

        $result = [];
        foreach ($grouped as $kat => $data) {
            $result[] = $data;
        }

        json_response(200, true, 'Data Rekap Siswa', [
            'ptk' => $ptk,
            'rekap' => $result,
            'murid_count' => $murid_count,
            'murid_labels' => array_values($murid_map)
        ]);

    } catch (PDOException $e) {
        json_response(500, false, 'Database Error: ' . $e->getMessage());
    }
}

function checkTambahan() {
    $db = db();
    
    $periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    $ptk_id = isset($_GET['ptk_id']) ? (int)$_GET['ptk_id'] : 0;
    
    if (!$periode_id || !$ptk_id) {
        json_response(400, false, 'Periode ID dan PTK ID diperlukan.');
    }
    perf_require_rekap_access($db, $periode_id, $ptk_id);

    try {
        // 1. Check PTK role / jabatan
        $stmtPtk = $db->prepare("SELECT jenis_ptk, jabatan FROM perf_ptk WHERE id = ?");
        $stmtPtk->execute([$ptk_id]);
        $ptk = $stmtPtk->fetch(PDO::FETCH_ASSOC);

        $has_tambahan = false;
        if ($ptk) {
            $jabatan_full = strtolower(trim(($ptk['jabatan'] ?? '') . ' ' . ($ptk['jenis_ptk'] ?? '')));
            if (strpos($jabatan_full, 'waka') !== false || strpos($jabatan_full, 'wakil') !== false ||
                strpos($jabatan_full, 'kepala tu') !== false || strpos($jabatan_full, 'ka. tu') !== false ||
                strpos($jabatan_full, 'kepala perpus') !== false || strpos($jabatan_full, 'ka. perpus') !== false ||
                strpos($jabatan_full, 'kepala lab') !== false || strpos($jabatan_full, 'ka. lab') !== false) {
                $has_tambahan = true;
            }
        }

        // 2. Check if there are assessments in Tugas Tambahan categories in perf_penilaian
        if (!$has_tambahan) {
            $stmtCek = $db->prepare("
                SELECT COUNT(*) 
                FROM perf_penilaian p 
                JOIN perf_instrumen i ON p.instrumen_id = i.id 
                WHERE p.periode_id = ? AND p.dinilai_ptk_id = ? 
                AND TRIM(REPLACE(i.kategori, '\xc2\xa0', '')) IN ('Manajerial', 'Kepemimpinan', 'Kewirausahaan')
            ");
            $stmtCek->execute([$periode_id, $ptk_id]);
            if ($stmtCek->fetchColumn() > 0) {
                $has_tambahan = true;
            }
        }
        
        json_response(200, true, 'Check tambahan', ['has_tambahan' => $has_tambahan]);
    } catch (PDOException $e) {
        json_response(500, false, 'Database Error: ' . $e->getMessage());
    }
}
