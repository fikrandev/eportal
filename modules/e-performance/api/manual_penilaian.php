<?php
require_once __DIR__ . '/config_perf.php';

// Auth check
$auth = perf_auth_check();

$action = $_GET['action'] ?? '';
$periode_id = $_REQUEST['periode_id'] ?? null;
$ptk_id = $_REQUEST['ptk_id'] ?? null;

if ($action === 'load' || $action === 'save') {
    if (!$periode_id || !$ptk_id) {
        json_response(400, false, 'Parameter periode_id dan ptk_id diperlukan.');
    }
} else {
    if (!$periode_id && $action !== 'submit_matrix_single' && $action !== 'submit_matrix_massal') {
        json_response(400, false, 'Parameter periode_id diperlukan.');
    }
}

if ($action === 'load') {
    try {
        // Get PTK jenis_ptk
        $stmtPtk = db()->prepare("SELECT jenis_ptk FROM perf_ptk WHERE id = ?");
        $stmtPtk->execute([$ptk_id]);
        $ptk = $stmtPtk->fetch(PDO::FETCH_ASSOC);
        $jenis_ptk = $ptk ? trim($ptk['jenis_ptk']) : '';

        // Get manual instrumen
        $stmtInst = db()->prepare("SELECT id, kategori, pertanyaan, target_dinilai FROM perf_instrumen WHERE periode_id = ? AND is_manual = 1");
        $stmtInst->execute([$periode_id]);
        $instrumenAll = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach($instrumenAll as $row) {
            $targets = array_map('trim', explode(',', $row['target_dinilai']));
            if (in_array('Semua', $targets) || in_array($jenis_ptk, $targets)) {
                $kat = trim($row['kategori']);
                if(!isset($grouped[$kat])) $grouped[$kat] = [];
                // Hapus target_dinilai dari respons untuk meminimalkan data
                unset($row['target_dinilai']);
                $grouped[$kat][] = $row;
            }
        }

        // Get saved data
        $stmt = db()->prepare("SELECT data FROM perf_penilaian_manual WHERE periode_id = ? AND ptk_id = ? LIMIT 1");
        $stmt->execute([$periode_id, $ptk_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $savedData = [];
        if ($row && $row['data']) {
            $savedData = json_decode($row['data'], true);
        }
        
        json_response(200, true, 'Data ditemukan', ['instrumen' => $grouped, 'saved' => $savedData]);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else if ($action === 'save') {
    try {
        $dataRaw = file_get_contents('php://input');
        $data = json_decode($dataRaw, true);
        
        if (!$data || !isset($data['payload'])) {
            json_response(400, false, 'Payload data tidak valid.');
        }
        
        $payloadJson = json_encode($data['payload']);
        
        $stmt = db()->prepare("
            INSERT INTO perf_penilaian_manual (periode_id, ptk_id, data) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE data = ?, updated_at = NOW()
        ");
        
        if ($stmt->execute([$periode_id, $ptk_id, $payloadJson, $payloadJson])) {
            json_response(200, true, 'Data input manual berhasil disimpan.');
        } else {
            $err = $stmt->errorInfo();
            json_response(500, false, 'Gagal menyimpan data. ' . print_r($err, true));
        }
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else if ($action === 'get_matrix_manual') {
    try {
        $perms = $auth['permissions'] ?? [];
        if ($auth['role'] !== 'admin' && !in_array('penilaian', $perms) && !in_array('hasil', $perms)) {
            json_response(403, false, 'Akses ditolak.');
        }

        // 1. Fetch all targets (teachers)
        $stmtT = db()->prepare("SELECT id, nama, jenis_ptk, mata_pelajaran FROM perf_ptk WHERE status = 1 ORDER BY jenis_ptk ASC, nama ASC");
        $stmtT->execute();
        $allTargets = $stmtT->fetchAll(PDO::FETCH_ASSOC);

        // Group targets by tupoksi
        $tabs = [];
        foreach ($allTargets as $t) {
            $jenis = $t['jenis_ptk'] ?: 'Guru';
            if (!isset($tabs[$jenis])) {
                $tabs[$jenis] = ['teachers' => [], 'questions' => []];
            }
            $tabs[$jenis]['teachers'][] = $t;
        }

        // 2. Fetch all manual questions
        $stmtQ = db()->prepare("
            SELECT id as instrumen_id, kategori, pertanyaan, target_dinilai, tipe_jawaban, skor_ya, skor_tidak
            FROM perf_instrumen
            WHERE periode_id = ? AND is_manual = 1
            ORDER BY kategori ASC, id ASC
        ");
        $stmtQ->execute([$periode_id]);
        $allQuestions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

        // Map questions to tabs
        foreach ($allQuestions as $q) {
            $targetsStr = trim($q['target_dinilai'] ?? 'Semua');
            $targets = array_map('trim', explode(',', $targetsStr));
            
            foreach ($tabs as $jenis => &$tabData) {
                if ($targetsStr === 'Semua' || in_array('Semua', $targets) || in_array($jenis, $targets)) {
                    $tabData['questions'][] = $q;
                }
            }
        }

        // 3. Fetch all manual answers for this periode
        $answers = [];
        $stmtA = db()->prepare("SELECT ptk_id, data FROM perf_penilaian_manual WHERE periode_id = ?");
        $stmtA->execute([$periode_id]);
        while ($row = $stmtA->fetch(PDO::FETCH_ASSOC)) {
            if ($row['data']) {
                $jsonData = json_decode($row['data'], true);
                if (is_array($jsonData)) {
                    foreach ($jsonData as $qId => $val) {
                        $key = $qId . '_' . $row['ptk_id'];
                        $answers[$key] = (float)$val;
                    }
                }
            }
        }

        json_response(200, true, 'Matrix data', [
            'tabs' => $tabs,
            'answers' => $answers,
            'periode_id' => $periode_id
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else if ($action === 'submit_matrix_single') {
    try {
        $perms = $auth['permissions'] ?? [];
        if ($auth['role'] !== 'admin' && !in_array('penilaian', $perms) && !in_array('hasil', $perms)) {
            json_response(403, false, 'Akses ditolak.');
        }

        $dataRaw = file_get_contents('php://input');
        $data = json_decode($dataRaw, true);
        
        if (!$data || !isset($data['target_id']) || !isset($data['instrumen_id']) || !isset($data['nilai']) || !isset($data['periode_id'])) {
            $missing = [];
            if (!$data) $missing[] = 'JSON body';
            if (isset($data) && !isset($data['target_id'])) $missing[] = 'target_id';
            if (isset($data) && !isset($data['instrumen_id'])) $missing[] = 'instrumen_id';
            if (isset($data) && !isset($data['nilai'])) $missing[] = 'nilai';
            if (isset($data) && !isset($data['periode_id'])) $missing[] = 'periode_id';
            json_response(400, false, 'Data tidak lengkap. Missing: ' . implode(', ', $missing));
        }

        $p_id = (int)$data['periode_id'];
        $t_id = (int)$data['target_id'];
        $i_id = (int)$data['instrumen_id'];
        $val = (float)$data['nilai'];

        // Get current data for this ptk
        $stmt = db()->prepare("SELECT data FROM perf_penilaian_manual WHERE periode_id = ? AND ptk_id = ? LIMIT 1");
        $stmt->execute([$p_id, $t_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $savedData = [];
        if ($row && $row['data']) {
            $savedData = json_decode($row['data'], true) ?: [];
        }

        // Update single value
        if ($val >= 0) {
            $savedData[$i_id] = $val;
        } else {
            unset($savedData[$i_id]);
        }
        $payloadJson = json_encode($savedData);

        // Save back
        $stmtSave = db()->prepare("
            INSERT INTO perf_penilaian_manual (periode_id, ptk_id, data) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE data = ?, updated_at = NOW()
        ");
        
        if ($stmtSave->execute([$p_id, $t_id, $payloadJson, $payloadJson])) {
            json_response(200, true, 'Auto-save manual sukses.');
        } else {
            json_response(500, false, 'Gagal auto-save data.');
        }
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else if ($action === 'submit_matrix_massal') {
    try {
        $perms = $auth['permissions'] ?? [];
        if ($auth['role'] !== 'admin' && !in_array('penilaian', $perms) && !in_array('hasil', $perms)) {
            json_response(403, false, 'Akses ditolak.');
        }

        $dataRaw = file_get_contents('php://input');
        $data = json_decode($dataRaw, true);
        
        if (!$data || !isset($data['targets']) || !isset($data['instrumen_id']) || !isset($data['nilai']) || !isset($data['periode_id'])) {
            $missing = [];
            if (!$data) $missing[] = 'JSON body';
            if (isset($data) && !isset($data['targets'])) $missing[] = 'targets';
            if (isset($data) && !isset($data['instrumen_id'])) $missing[] = 'instrumen_id';
            if (isset($data) && !isset($data['nilai'])) $missing[] = 'nilai';
            if (isset($data) && !isset($data['periode_id'])) $missing[] = 'periode_id';
            json_response(400, false, 'Data tidak lengkap. Missing: ' . implode(', ', $missing));
        }

        $p_id = (int)$data['periode_id'];
        $targets = $data['targets']; // array of ptk_id
        $i_id = (int)$data['instrumen_id'];
        $val = (float)$data['nilai'];

        if (!is_array($targets) || count($targets) === 0) {
            json_response(400, false, 'Daftar guru tidak valid.');
        }

        db()->beginTransaction();

        $stmtGet = db()->prepare("SELECT data FROM perf_penilaian_manual WHERE periode_id = ? AND ptk_id = ? LIMIT 1");
        $stmtSave = db()->prepare("
            INSERT INTO perf_penilaian_manual (periode_id, ptk_id, data) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE data = ?, updated_at = NOW()
        ");

        foreach ($targets as $t_id) {
            $t_id = (int)$t_id;
            
            $stmtGet->execute([$p_id, $t_id]);
            $row = $stmtGet->fetch(PDO::FETCH_ASSOC);

            $savedData = [];
            if ($row && $row['data']) {
                $savedData = json_decode($row['data'], true) ?: [];
            }

            if ($val >= 0) {
                $savedData[$i_id] = $val;
            } else {
                unset($savedData[$i_id]);
            }
            $payloadJson = json_encode($savedData);

            $stmtSave->execute([$p_id, $t_id, $payloadJson, $payloadJson]);
        }

        db()->commit();
        json_response(200, true, 'Terapkan semua sukses.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else {
    json_response(400, false, 'Aksi tidak valid.');
}

