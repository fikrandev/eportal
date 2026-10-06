<?php
/**
 * E-Performance — Pengaturan Hasil Acak Penilai (Sejawat) API
 */
require_once __DIR__ . '/config_perf.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list': listPenugasan(); break;
    case 'generate': generatePenugasan(); break;
    case 'reset': resetPenugasan(); break;
    case 'delete': deletePenugasan(); break;
    case 'move': movePenugasan(); break;
    default: json_response(400, false, 'Action tidak valid.');
}

function movePenugasan() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $new_target_id = isset($input['new_target_id']) ? (int)$input['new_target_id'] : 0;
    
    if (!$id || !$new_target_id) json_response(400, false, 'Data tidak lengkap.');
    
    $db = db();
    
    $stmtCek = $db->prepare("SELECT periode_id, penilai_ptk_id, dinilai_ptk_id FROM perf_penugasan_sejawat WHERE id = ?");
    $stmtCek->execute([$id]);
    $penugasan = $stmtCek->fetch(PDO::FETCH_ASSOC);
    
    if (!$penugasan) {
        json_response(404, false, 'Penugasan tidak ditemukan.');
    }

    if ($penugasan['dinilai_ptk_id'] == $new_target_id) {
        json_response(400, false, 'Target penugasan sama dengan sebelumnya.');
    }

    if ($penugasan['penilai_ptk_id'] == $new_target_id) {
        json_response(400, false, 'Tidak dapat menugaskan guru untuk menilai dirinya sendiri.');
    }

    // Pastikan target baru valid
    $stmtTarget = $db->prepare("SELECT id FROM perf_ptk WHERE id = ? AND status = 1");
    $stmtTarget->execute([$new_target_id]);
    if (!$stmtTarget->fetchColumn()) {
        json_response(404, false, 'Guru target tidak ditemukan.');
    }
    
    try {
        $db->beginTransaction();
        
        // Hapus nilai lama karena targetnya berubah
        $stmtDelNilai = $db->prepare("
            DELETE FROM perf_penilaian 
            WHERE periode_id = ? AND penilai_id = ? AND dinilai_ptk_id = ? AND penilai_type = 'guru'
        ");
        $stmtDelNilai->execute([
            $penugasan['periode_id'], 
            $penugasan['penilai_ptk_id'], 
            $penugasan['dinilai_ptk_id']
        ]);

        // Pindahkan target
        $stmtUpdate = $db->prepare("UPDATE perf_penugasan_sejawat SET dinilai_ptk_id = ? WHERE id = ?");
        $stmtUpdate->execute([$new_target_id, $id]);
        
        $db->commit();
        json_response(200, true, 'Penugasan berhasil dipindahkan.');
    } catch (Exception $e) {
        $db->rollBack();
        json_response(500, false, 'Terjadi kesalahan sistem: ' . $e->getMessage());
    }
}

function listPenugasan() {
    perf_require_admin();
    
    $periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    if (!$periode_id) json_response(400, false, 'Periode ID wajib diisi.');

    $db = db();
    
    // Ambil daftar semua guru (target yang dinilai) dan hitung berapa orang yang menilai mereka (selain diri sendiri)
    $sql = "
        SELECT 
            p.id as target_id,
            p.nama as target_nama,
            p.jenis_ptk as target_jenis,
            COUNT(ps.id) as jumlah_penilai
        FROM perf_ptk p
        LEFT JOIN perf_penugasan_sejawat ps ON p.id = ps.dinilai_ptk_id AND ps.periode_id = ?
        WHERE p.status = 1
        GROUP BY p.id
        ORDER BY p.nama ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$periode_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil detail siapa saja yang menilai mereka
    $sqlDetail = "
        SELECT 
            ps.id,
            ps.dinilai_ptk_id,
            t.id as penilai_id,
            t.nama as penilai_nama,
            t.jenis_ptk as penilai_jenis
        FROM perf_penugasan_sejawat ps
        JOIN perf_ptk t ON ps.penilai_ptk_id = t.id
        WHERE ps.periode_id = ?
    ";
    $stmtDetail = $db->prepare($sqlDetail);
    $stmtDetail->execute([$periode_id]);
    $details = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

    // Grouping details in PHP by target
    $detailMap = [];
    foreach ($details as $d) {
        $target_id = $d['dinilai_ptk_id'];
        if (!isset($detailMap[$target_id])) {
            $detailMap[$target_id] = [];
        }
        $detailMap[$target_id][] = [
            'id' => $d['id'],
            'penilai_id' => $d['penilai_id'],
            'penilai_nama' => $d['penilai_nama'],
            'penilai_jenis' => $d['penilai_jenis']
        ];
    }

    foreach ($data as &$row) {
        $row['detail_penilai'] = isset($detailMap[$row['target_id']]) ? $detailMap[$row['target_id']] : [];
    }

    json_response(200, true, 'Data berhasil dimuat', $data);
}

function deletePenugasan() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    if (!$id) json_response(400, false, 'ID Penugasan wajib diisi.');
    
    $db = db();
    
    $stmtCek = $db->prepare("SELECT periode_id, penilai_ptk_id, dinilai_ptk_id FROM perf_penugasan_sejawat WHERE id = ?");
    $stmtCek->execute([$id]);
    $penugasan = $stmtCek->fetch(PDO::FETCH_ASSOC);
    
    if (!$penugasan) {
        json_response(404, false, 'Penugasan tidak ditemukan.');
    }
    
    try {
        $db->beginTransaction();
        
        // Hapus penugasan
        $stmtDel = $db->prepare("DELETE FROM perf_penugasan_sejawat WHERE id = ?");
        $stmtDel->execute([$id]);
        
        // Hapus nilai (jika sudah dinilai)
        $stmtDelNilai = $db->prepare("
            DELETE FROM perf_penilaian 
            WHERE periode_id = ? AND penilai_id = ? AND dinilai_ptk_id = ? AND penilai_type = 'guru'
        ");
        $stmtDelNilai->execute([
            $penugasan['periode_id'], 
            $penugasan['penilai_ptk_id'], 
            $penugasan['dinilai_ptk_id']
        ]);
        
        $db->commit();
        json_response(200, true, 'Penugasan berhasil dihapus.');
    } catch (Exception $e) {
        $db->rollBack();
        json_response(500, false, 'Terjadi kesalahan sistem: ' . $e->getMessage());
    }
}

function generatePenugasan() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    
    $input = get_input();
    $periode_id = isset($input['periode_id']) ? (int)$input['periode_id'] : 0;
    if (!$periode_id && isset($_GET['periode_id'])) {
        $periode_id = (int)$_GET['periode_id'];
    }
    
    if (!$periode_id) json_response(400, false, 'Periode ID wajib diisi.');
    
    $mode = isset($input['mode']) ? $input['mode'] : 'normal'; // 'normal' | 'force'
    
    $db = db();
    
    // 1. Cek periode
    $stmtPeriode = $db->prepare("SELECT id, nama_periode FROM perf_periode WHERE id = ?");
    $stmtPeriode->execute([$periode_id]);
    $periode = $stmtPeriode->fetch(PDO::FETCH_ASSOC);
    if (!$periode) {
        json_response(404, false, 'Periode tidak ditemukan.');
    }
    
    // 2. Ambil aturan sejawat untuk periode ini
    $stmtAturan = $db->prepare("SELECT penilai_jenis, dinilai_jenis FROM perf_aturan_sejawat WHERE periode_id = ?");
    $stmtAturan->execute([$periode_id]);
    $aturanRows = $stmtAturan->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($aturanRows)) {
        json_response(400, false, 'Belum ada aturan sejawat yang disetting untuk periode ini. Silakan buat aturan siapa menilai siapa di menu Buat Penilaian -> tab Aturan Sejawat.');
    }
    
    // Group aturan: penilai_jenis => [dinilai_jenis, ...]
    $aturanMap = [];
    foreach ($aturanRows as $row) {
        $pj = trim($row['penilai_jenis']);
        $dj = trim($row['dinilai_jenis']);
        if (!isset($aturanMap[$pj])) {
            $aturanMap[$pj] = [];
        }
        if (!in_array($dj, $aturanMap[$pj])) {
            $aturanMap[$pj][] = $dj;
        }
    }
    
    // 3. Ambil semua PTK yang aktif
    $stmtPtk = $db->prepare("SELECT id, nama, jenis_ptk FROM perf_ptk WHERE status = 1 ORDER BY id ASC");
    $stmtPtk->execute();
    $allPtk = $stmtPtk->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allPtk)) {
        json_response(400, false, 'Tidak ada data PTK aktif.');
    }
    
    // Map PTK by id and by jenis_ptk
    $ptkById = [];
    $ptkByJenis = [];
    foreach ($allPtk as $ptk) {
        $ptkById[$ptk['id']] = $ptk;
        $j = trim($ptk['jenis_ptk']);
        if (!isset($ptkByJenis[$j])) {
            $ptkByJenis[$j] = [];
        }
        $ptkByJenis[$j][] = (int)$ptk['id'];
    }
    
    // 4. Deteksi penilaian yang sudah diisi agar nilainya aman (kecuali mode force)
    $preservedAssignments = []; // penilai_id => dinilai_id
    $targetCounts = []; // target_id => count
    $toDeleteIds = [];
    
    // Ambil penugasan saat ini di database
    $stmtExisting = $db->prepare("SELECT id, penilai_ptk_id, dinilai_ptk_id FROM perf_penugasan_sejawat WHERE periode_id = ?");
    $stmtExisting->execute([$periode_id]);
    $existingAssignments = $stmtExisting->fetchAll(PDO::FETCH_ASSOC);
    
    if ($mode === 'force') {
        foreach ($existingAssignments as $ea) {
            $toDeleteIds[] = (int)$ea['id'];
        }
    } else {
        // Cek siapa yang sudah mulai mengisi nilai di perf_penilaian
        $stmtEvaluated = $db->prepare("
            SELECT DISTINCT penilai_id 
            FROM perf_penilaian 
            WHERE periode_id = ? AND penilai_type = 'guru'
        ");
        $stmtEvaluated->execute([$periode_id]);
        $evaluatedPenilaiIds = $stmtEvaluated->fetchAll(PDO::FETCH_COLUMN);
        $evaluatedSet = array_flip(array_map('intval', $evaluatedPenilaiIds));
        
        foreach ($existingAssignments as $ea) {
            $penilaiId = (int)$ea['penilai_ptk_id'];
            $dinilaiId = (int)$ea['dinilai_ptk_id'];
            
            if (isset($evaluatedSet[$penilaiId])) {
                // Pertahankan penugasan yang nilainya sudah terisi
                $preservedAssignments[$penilaiId] = $dinilaiId;
                $targetCounts[$dinilaiId] = ($targetCounts[$dinilaiId] ?? 0) + 1;
            } else {
                // Penugasan yang belum diisi boleh diacak ulang
                $toDeleteIds[] = (int)$ea['id'];
            }
        }
    }
    
    // Inisialisasi targetCounts untuk seluruh PTK
    foreach ($allPtk as $ptk) {
        $tId = (int)$ptk['id'];
        if (!isset($targetCounts[$tId])) {
            $targetCounts[$tId] = 0;
        }
    }
    
    // 5. Tentukan siapa saja penilai yang perlu diacak
    $penilaiToAssign = [];
    foreach ($allPtk as $ptk) {
        $pId = (int)$ptk['id'];
        $pJ = trim($ptk['jenis_ptk']);
        
        // Lewati jika sudah dipertahankan
        if (isset($preservedAssignments[$pId])) {
            continue;
        }
        
        // Cek apakah jenis_ptk ini memiliki aturan sejawat
        if (isset($aturanMap[$pJ]) && !empty($aturanMap[$pJ])) {
            $penilaiToAssign[] = $ptk;
        }
    }
    
    // Acak urutan penilai agar distribusi merata
    shuffle($penilaiToAssign);
    
    $newAssignments = []; // penilai_id => dinilai_id
    $skippedCount = 0;
    
    foreach ($penilaiToAssign as $penilai) {
        $pId = (int)$penilai['id'];
        $pJ = trim($penilai['jenis_ptk']);
        $allowedTargetJenis = $aturanMap[$pJ];
        
        // Kumpulkan semua kandidat yang valid (bukan diri sendiri)
        $candidates = [];
        foreach ($allowedTargetJenis as $tJenis) {
            if (isset($ptkByJenis[$tJenis])) {
                foreach ($ptkByJenis[$tJenis] as $cId) {
                    if ($cId !== $pId) { // Tidak boleh menilai diri sendiri!
                        $candidates[] = $cId;
                    }
                }
            }
        }
        
        $candidates = array_values(array_unique($candidates));
        
        if (empty($candidates)) {
            $skippedCount++;
            continue;
        }
        
        // Pilih kandidat dengan jumlah penilai paling sedikit (load balance merata)
        $minCount = PHP_INT_MAX;
        foreach ($candidates as $cId) {
            $cCount = $targetCounts[$cId] ?? 0;
            if ($cCount < $minCount) {
                $minCount = $cCount;
            }
        }
        
        $bestCandidates = [];
        foreach ($candidates as $cId) {
            if (($targetCounts[$cId] ?? 0) === $minCount) {
                $bestCandidates[] = $cId;
            }
        }
        
        // Acak di antara kandidat yang bebannya paling seimbang
        shuffle($bestCandidates);
        $chosenTargetId = $bestCandidates[0];
        
        $newAssignments[$pId] = $chosenTargetId;
        $targetCounts[$chosenTargetId] = ($targetCounts[$chosenTargetId] ?? 0) + 1;
    }
    
    try {
        $db->beginTransaction();
        
        // Hapus penugasan lama yang di-replace
        if (!empty($toDeleteIds)) {
            $placeholders = implode(',', array_fill(0, count($toDeleteIds), '?'));
            $db->prepare("DELETE FROM perf_penugasan_sejawat WHERE id IN ($placeholders)")->execute($toDeleteIds);
        }
        
        // Insert penugasan baru
        $stmtInsert = $db->prepare("
            INSERT INTO perf_penugasan_sejawat (periode_id, penilai_ptk_id, dinilai_ptk_id) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($newAssignments as $pId => $tId) {
            $stmtInsert->execute([$periode_id, $pId, $tId]);
        }
        
        $db->commit();
        
        $totalBerhasil = count($newAssignments);
        $totalPreserved = count($preservedAssignments);
        
        $msg = "Pengacakan penilai berhasil! Sebanyak {$totalBerhasil} penugasan teman sejawat berhasil diacak.";
        if ($totalPreserved > 0) {
            $msg .= " ({$totalPreserved} penugasan yang nilainya sudah terisi tetap dipertahankan).";
        }
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} PTK dilewati karena tidak ada rekan lain di tupoksi target).";
        }
        
        json_response(200, true, $msg, [
            'total_generated' => $totalBerhasil,
            'total_preserved' => $totalPreserved,
            'total_skipped' => $skippedCount
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        json_response(500, false, 'Terjadi kesalahan sistem saat menyimpan pengacakan: ' . $e->getMessage());
    }
}

function resetPenugasan() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    
    $input = get_input();
    $periode_id = isset($input['periode_id']) ? (int)$input['periode_id'] : 0;
    if (!$periode_id && isset($_GET['periode_id'])) {
        $periode_id = (int)$_GET['periode_id'];
    }
    if (!$periode_id) json_response(400, false, 'Periode ID wajib diisi.');
    
    $db = db();
    try {
        $db->beginTransaction();
        
        // Hapus nilai sejawat untuk periode ini
        $stmtDelNilai = $db->prepare("DELETE FROM perf_penilaian WHERE periode_id = ? AND penilai_type = 'guru'");
        $stmtDelNilai->execute([$periode_id]);
        
        // Hapus penugasan sejawat untuk periode ini
        $stmtDel = $db->prepare("DELETE FROM perf_penugasan_sejawat WHERE periode_id = ?");
        $stmtDel->execute([$periode_id]);
        
        $db->commit();
        json_response(200, true, 'Seluruh penugasan dan nilai teman sejawat untuk periode ini telah direset.');
    } catch (Exception $e) {
        $db->rollBack();
        json_response(500, false, 'Terjadi kesalahan saat mereset penugasan: ' . $e->getMessage());
    }
}
