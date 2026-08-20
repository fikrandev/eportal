<?php
/**
 * E-Performance — Rekap API
 * Endpoints for generating recap reports
 */
require_once __DIR__ . '/config_perf.php';

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

function getRekapDiriSendiri() {
    perf_auth_check();
    $db = db();
    
    $periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    $ptk_id = isset($_GET['ptk_id']) ? (int)$_GET['ptk_id'] : 0;
    
    if (!$periode_id || !$ptk_id) {
        json_response(400, false, 'Periode ID dan PTK ID diperlukan.');
    }

    try {
        // 1. Get PTK Details
        $stmtPtk = $db->prepare("SELECT id, nama, jenis_ptk, niy FROM perf_ptk WHERE id = ?");
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
        
        $actualTupoksi = ($tupoksiRow && !empty($tupoksiRow['tupoksi'])) ? $tupoksiRow['tupoksi'] : 'Guru';
        $rater_str = $actualTupoksi;
        $rater_str_self = 'Diri Sendiri';
        $rater_fallback = (stripos($actualTupoksi, 'Guru') !== false || stripos($actualTupoksi, 'Wali Kelas') !== false) ? 'Guru' : $actualTupoksi;

        // 4. Fetch Questions targeting this PTK
        $stmtQ = $db->prepare("
            SELECT id as instrumen_id, kategori, pertanyaan
            FROM perf_instrumen
            WHERE periode_id = ? AND (
                (
                    (FIND_IN_SET(?, target_jabatan) > 0 OR FIND_IN_SET(?, target_jabatan) > 0)
                    AND (target_dinilai = 'Semua' OR FIND_IN_SET(?, target_dinilai) > 0)
                )
                OR
                (
                    FIND_IN_SET(?, target_jabatan) > 0 
                    AND (target_dinilai = 'Semua' OR FIND_IN_SET(?, target_dinilai) > 0 OR FIND_IN_SET(?, target_dinilai) > 0)
                )
                OR
                (FIND_IN_SET(?, target_jabatan) > 0 AND FIND_IN_SET(?, target_dinilai) > 0)
            )
            ORDER BY kategori ASC, urutan ASC, id ASC
        ");
        $stmtQ->execute([
            $periode_id, 
            $rater_str, $rater_fallback, $ptk['jenis_ptk'],
            $rater_str_self, $ptk['jenis_ptk'], $rater_str_self,
            $ptk['jenis_ptk'], $rater_str_self
        ]);
        $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

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
    perf_auth_check();
    $db = db();
    
    $periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    $ptk_id = isset($_GET['ptk_id']) ? (int)$_GET['ptk_id'] : 0;
    
    if (!$periode_id || !$ptk_id) {
        json_response(400, false, 'Periode ID dan PTK ID diperlukan.');
    }

    try {
        // 1. Get PTK Details
        $stmtPtk = $db->prepare("SELECT id, nama, jenis_ptk, niy FROM perf_ptk WHERE id = ?");
        $stmtPtk->execute([$ptk_id]);
        $ptk = $stmtPtk->fetch(PDO::FETCH_ASSOC);
        if (!$ptk) json_response(404, false, 'Data PTK tidak ditemukan.');

        // 2. Fetch Questions targeting 'Siswa'
        $stmtQ = $db->prepare("
            SELECT id as instrumen_id, kategori, pertanyaan
            FROM perf_instrumen
            WHERE periode_id = ? AND target_jabatan LIKE '%Siswa%'
            ORDER BY kategori ASC, urutan ASC, id ASC
        ");
        $stmtQ->execute([$periode_id]);
        $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

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
    perf_auth_check();
    $db = db();
    
    $periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    $ptk_id = isset($_GET['ptk_id']) ? (int)$_GET['ptk_id'] : 0;
    
    if (!$periode_id || !$ptk_id) {
        json_response(400, false, 'Periode ID dan PTK ID diperlukan.');
    }

    try {
        $stmtManualInst = $db->prepare("SELECT id FROM perf_instrumen WHERE periode_id = ? AND is_manual = 1 AND bobot = 0");
        $stmtManualInst->execute([$periode_id]);
        $tugas_tambahan = $stmtManualInst->fetchAll(PDO::FETCH_ASSOC);

        $has_tambahan = false;
        if (!empty($tugas_tambahan)) {
            $stmtManual = $db->prepare("SELECT data FROM perf_penilaian_manual WHERE periode_id = ? AND ptk_id = ? LIMIT 1");
            $stmtManual->execute([$periode_id, $ptk_id]);
            $manualRow = $stmtManual->fetch(PDO::FETCH_ASSOC);

            if ($manualRow && $manualRow['data']) {
                $manualData = json_decode($manualRow['data'], true) ?: [];
                foreach ($tugas_tambahan as $tt) {
                    $score = isset($manualData[$tt['id']]) ? (float)$manualData[$tt['id']] : 0;
                    if ($score > 0) {
                        $has_tambahan = true;
                        break;
                    }
                }
            }
        }
        
        json_response(200, true, 'Check tambahan', ['has_tambahan' => $has_tambahan]);
    } catch (PDOException $e) {
        json_response(500, false, 'Database Error: ' . $e->getMessage());
    }
}
