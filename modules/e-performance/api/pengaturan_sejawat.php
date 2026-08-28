<?php
/**
 * E-Performance — Pengaturan Hasil Acak Penilai (Sejawat) API
 */
require_once __DIR__ . '/config_perf.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list': listPenugasan(); break;
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
