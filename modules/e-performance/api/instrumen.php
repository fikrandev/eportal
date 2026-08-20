<?php
/**
 * E-Performance — Instrumen & Penilai API
 */
require_once __DIR__ . '/config_perf.php';

$auth = perf_auth_check();
$role = $auth['role'];

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$db = db();

try {
    switch ($action) {
        case 'list_pertanyaan':
            if ($method !== 'GET') throw new Exception('Method not allowed', 405);
            $periode_id = (int)($_GET['periode_id'] ?? 0);
            $is_manual = isset($_GET['is_manual']) ? (int)$_GET['is_manual'] : 0;
            $stmt = $db->prepare("SELECT * FROM perf_instrumen WHERE periode_id = ? AND is_manual = ? ORDER BY kategori, id");
            $stmt->execute([$periode_id, $is_manual]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(200, true, 'Data pertanyaan', $data);
            break;

        case 'create_pertanyaan':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            
            $kategori = trim($input['kategori'] ?? '');
            $pertanyaan = trim($input['pertanyaan'] ?? '');
            $target_penilai = $input['target_penilai'] ?? [];
            $target_dinilai = $input['target_dinilai'] ?? [];
            $periode_id = (int)($input['periode_id'] ?? 0);
            
            if (!$periode_id) throw new Exception('Periode belum dipilih', 400);
            if (!$kategori || !$pertanyaan) {
                throw new Exception('Kategori dan pertanyaan harus diisi', 400);
            }
            if (empty($target_penilai)) {
                throw new Exception('Pilih minimal satu penilai', 400);
            }
            if (empty($target_dinilai)) {
                throw new Exception('Pilih minimal satu target dinilai', 400);
            }
            
            $target_jabatan_str = is_array($target_penilai) ? implode(',', $target_penilai) : $target_penilai;
            $target_dinilai_str = is_array($target_dinilai) ? implode(',', $target_dinilai) : $target_dinilai;
            $is_manual = isset($input['is_manual']) ? (int)$input['is_manual'] : 0;
            $tipe_jawaban = $input['tipe_jawaban'] ?? 'angka';
            $skor_ya = isset($input['skor_ya']) && $input['skor_ya'] !== '' ? (float)$input['skor_ya'] : 100;
            $skor_tidak = isset($input['skor_tidak']) && $input['skor_tidak'] !== '' ? (float)$input['skor_tidak'] : 0;
            
            $stmt = $db->prepare("INSERT INTO perf_instrumen (periode_id, kode, kategori, target_jabatan, target_dinilai, pertanyaan, is_manual, tipe_jawaban, skor_ya, skor_tidak) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$periode_id, 'P'.time(), $kategori, $target_jabatan_str, $target_dinilai_str, $pertanyaan, $is_manual, $tipe_jawaban, $skor_ya, $skor_tidak]);
            
            json_response(200, true, 'Pertanyaan berhasil disimpan');
            break;

        case 'delete_pertanyaan':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            $id = $input['id'] ?? 0;
            
            // Check if already assessed
            $chk = $db->prepare("SELECT COUNT(*) FROM perf_penilaian WHERE instrumen_id = ?");
            $chk->execute([$id]);
            if ($chk->fetchColumn() > 0) {
                throw new Exception('Pertanyaan ini sudah dinilai oleh penilai sehingga tidak dapat dihapus', 400);
            }
            
            $stmt = $db->prepare("DELETE FROM perf_instrumen WHERE id = ?");
            $stmt->execute([$id]);
            
            json_response(200, true, 'Pertanyaan berhasil dihapus');
            break;

        case 'bulk_delete_pertanyaan':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            $ids = $input['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                throw new Exception('Tidak ada pertanyaan yang dipilih', 400);
            }

            $success = 0;
            $failed = 0;
            $failed_reasons = [];

            $chk = $db->prepare("SELECT COUNT(*) FROM perf_penilaian WHERE instrumen_id = ?");
            $del = $db->prepare("DELETE FROM perf_instrumen WHERE id = ?");
            
            foreach ($ids as $id) {
                $chk->execute([$id]);
                if ($chk->fetchColumn() > 0) {
                    $failed++;
                    continue;
                }
                $del->execute([$id]);
                $success++;
            }
            
            $msg = "Berhasil menghapus $success pertanyaan.";
            if ($failed > 0) {
                $msg .= " $failed gagal dihapus karena sudah digunakan dalam penilaian.";
            }
            
            json_response(200, true, $msg, ['success' => $success, 'failed' => $failed]);
            break;

        case 'import_pertanyaan':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            $target_penilai = $input['target_penilai'] ?? [];
            $target_dinilai = $input['target_dinilai'] ?? [];
            $periode_id = (int)($input['periode_id'] ?? 0);
            
            if (!$periode_id) throw new Exception('Periode belum dipilih', 400);
            if (empty($target_penilai)) throw new Exception('Pilih minimal satu penilai', 400);
            if (empty($target_dinilai)) throw new Exception('Pilih minimal satu dinilai', 400);
            
            $target_jabatan_str = is_array($target_penilai) ? implode(',', $target_penilai) : $target_penilai;
            $target_dinilai_str = is_array($target_dinilai) ? implode(',', $target_dinilai) : $target_dinilai;
            $is_manual = isset($input['is_manual']) ? (int)$input['is_manual'] : 0;
            
            $data = $input['data'] ?? [];
            if (empty($data)) throw new Exception('Tidak ada data yang valid untuk diimpor.', 400);
            
            $success_count = 0;
            foreach ($data as $row) {
                // Header must match 'Kategori Penilaian' and 'Pertanyaan'
                $kategori = trim($row['Kategori Penilaian'] ?? $row['kategori_penilaian'] ?? $row['Kategori'] ?? '');
                $pertanyaan = trim($row['Pertanyaan'] ?? $row['pertanyaan'] ?? '');
                
                if ($kategori && $pertanyaan) {
                    $tipe_jawaban = trim($row['Tipe Isian'] ?? $row['tipe_isian'] ?? 'angka');
                    $tipe_jawaban = (strtolower($tipe_jawaban) === 'ya/tidak' || strtolower($tipe_jawaban) === 'ya_tidak') ? 'ya_tidak' : 'angka';
                    $skor_ya = isset($row['Skor Ya']) && $row['Skor Ya'] !== '' ? (float)$row['Skor Ya'] : 100;
                    $skor_tidak = isset($row['Skor Tidak']) && $row['Skor Tidak'] !== '' ? (float)$row['Skor Tidak'] : 0;

                    $stmt = $db->prepare("INSERT INTO perf_instrumen (periode_id, kode, kategori, target_jabatan, target_dinilai, pertanyaan, is_manual, tipe_jawaban, skor_ya, skor_tidak) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$periode_id, 'P'.time().$success_count, $kategori, $target_jabatan_str, $target_dinilai_str, $pertanyaan, $is_manual, $tipe_jawaban, $skor_ya, $skor_tidak]);
                    $success_count++;
                }
            }
            
            json_response(200, true, "Berhasil mengimpor $success_count pertanyaan.");
            break;

        case 'list_penilai':
            if ($method !== 'GET') throw new Exception('Method not allowed', 405);
            $periode_id = (int)($_GET['periode_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM perf_jenis_penilai WHERE periode_id = ? ORDER BY id");
            $stmt->execute([$periode_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(200, true, 'Data penilai', $data);
            break;

        case 'list_dinilai':
            if ($method !== 'GET') throw new Exception('Method not allowed', 405);
            $stmt = $db->query("SELECT DISTINCT jenis_ptk FROM perf_ptk WHERE status = 1 ORDER BY jenis_ptk ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data[] = ['jenis_ptk' => 'Teman Sejawat'];
            $data[] = ['jenis_ptk' => 'Diri Sendiri'];
            json_response(200, true, 'Data dinilai', $data);
            break;

        case 'create_penilai':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            
            $jenis_penilai = trim($input['jenis_penilai'] ?? '');
            $periode_id = (int)($input['periode_id'] ?? 0);
            
            if (!$periode_id) throw new Exception('Periode belum dipilih', 400);
            if (!$jenis_penilai) {
                throw new Exception('Penilai harus diisi', 400);
            }
            
            $stmt = $db->prepare("INSERT INTO perf_jenis_penilai (periode_id, jenis_penilai, target_dinilai) VALUES (?, ?, ?)");
            $stmt->execute([$periode_id, $jenis_penilai, 'Semua PTK']); 
            
            json_response(200, true, 'Penilai berhasil disimpan');
            break;

        case 'delete_penilai':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            $id = $input['id'] ?? 0;
            
            $stmt = $db->prepare("DELETE FROM perf_jenis_penilai WHERE id = ?");
            $stmt->execute([$id]);
            
            json_response(200, true, 'Penilai berhasil dihapus');
            break;

        case 'list_tupoksi':
            if ($method !== 'GET') throw new Exception('Method not allowed', 405);
            // Fetch distinct jenis_ptk from perf_ptk (sebelumnya dari sarpras_referensi)
            $stmt = $db->query("SELECT DISTINCT jenis_ptk as nama FROM perf_ptk WHERE status = 1 ORDER BY jenis_ptk ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(200, true, 'Data tupoksi', $data);
            break;

        case 'list_progress':
            if ($method !== 'GET') throw new Exception('Method not allowed', 405);
            $periode_id = (int)($_GET['periode_id'] ?? 0);
            
            // 1. Get all assigned penilai roles for this period
            $stmt = $db->prepare("SELECT jenis_penilai FROM perf_jenis_penilai WHERE periode_id = ?");
            $stmt->execute([$periode_id]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $progress_data = [];
            
            foreach ($roles as $role) {
                $role_fallback = (stripos($role, 'Guru') !== false || stripos($role, 'Wali Kelas') !== false) ? 'Guru' : $role;
                
                // Get total questions for this role in this period
                $stmtQ = $db->prepare("SELECT COUNT(*) FROM perf_instrumen WHERE (FIND_IN_SET(?, target_jabatan) > 0 OR FIND_IN_SET(?, target_jabatan) > 0) AND periode_id = ? AND is_manual = 0");
                $stmtQ->execute([$role, $role_fallback, $periode_id]);
                $total_questions = (int)$stmtQ->fetchColumn();
                
                if (strtolower($role) === 'siswa') {
                    // Fetch from perf_siswa
                    $stmtS = $db->query("SELECT id, perf_user_id, username, nama_siswa as nama FROM perf_siswa WHERE status = 1");
                    $users = $stmtS->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($users as $u) {
                        $p_id = (int)$u['perf_user_id'];
                        
                        $stmtTargets = $db->prepare("
                            SELECT jenis_ptk, COUNT(id) as jml 
                            FROM perf_ptk 
                            WHERE status = 1 AND (
                                jenis_ptk = 'tu' 
                                OR id IN (SELECT perf_ptk_id FROM perf_siswa_guru WHERE perf_siswa_id = ?)
                            )
                            GROUP BY jenis_ptk
                        ");
                        $stmtTargets->execute([$u['id']]);
                        $target_groups = $stmtTargets->fetchAll(PDO::FETCH_ASSOC);

                        $stmtQ_dyn = $db->prepare("
                            SELECT COUNT(id) FROM perf_instrumen WHERE periode_id = ? AND is_manual = 0 AND (
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
                        ");

                        $total_expected = 0;
                        foreach ($target_groups as $tg) {
                            $t_jenis = $tg['jenis_ptk'] ?: 'Guru';
                            $stmtQ_dyn->execute([
                                $periode_id,
                                'Siswa', 'Siswa', $t_jenis,
                                'Siswa', $t_jenis, 'Siswa',
                                $t_jenis, 'Siswa'
                            ]);
                            $q_per_target = (int)$stmtQ_dyn->fetchColumn();
                            $total_expected += ($q_per_target * $tg['jml']);
                        }
                        
                        if ($total_expected === 0) $total_expected = 1;
                        $stmtAns = $db->prepare("SELECT COUNT(*) FROM perf_penilaian WHERE penilai_id = ? AND periode_id = ?");
                        $stmtAns->execute([$p_id, $periode_id]);
                        $answered = (int)$stmtAns->fetchColumn();
                        
                        $progress_data[] = [
                            'id' => $u['id'],
                            'penilai_id' => $p_id,
                            'penilai_type' => 'siswa',
                            'nama' => $u['nama'],
                            'role' => 'Siswa',
                            'username' => $u['username'],
                            'answered' => $answered,
                            'total' => $total_expected,
                            'percentage' => round(($answered / $total_expected) * 100)
                        ];
                    }
                } else if (strtolower($role) === 'kepsek' || strtolower($role) === 'kepala sekolah') {
                    // Hitung target expected dengan iterasi masing-masing jenis PTK agar akurat (mirip dashboardStats)
                    $stmtTargets = $db->prepare("SELECT jenis_ptk, COUNT(id) as jml FROM perf_ptk WHERE status = 1 AND jenis_ptk != 'Kepala Sekolah' GROUP BY jenis_ptk");
                    $stmtTargets->execute();
                    $target_groups = $stmtTargets->fetchAll(PDO::FETCH_ASSOC);
                    
                    $stmtQ_dyn = $db->prepare("
                        SELECT COUNT(id) FROM perf_instrumen WHERE periode_id = ? AND is_manual = 0 AND (
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
                    ");
                    
                    $rater_str = 'Kepala Sekolah';
                    $rater_fallback = 'Kepala Sekolah';
                    $rater_str_self = 'Kepala Sekolah';
                    
                    $total_expected = 0;
                    foreach ($target_groups as $tg) {
                        $t_jenis = $tg['jenis_ptk'] ?: 'Guru';
                        $stmtQ_dyn->execute([
                            $periode_id,
                            $rater_str, $rater_fallback, $t_jenis,
                            $rater_str_self, $t_jenis, $rater_str_self,
                            $t_jenis, $rater_str_self
                        ]);
                        $q_per_target = (int)$stmtQ_dyn->fetchColumn();
                        $total_expected += ($q_per_target * $tg['jml']);
                    }
                    
                    if ($total_expected === 0) $total_expected = 1;
                    
                    $stmtU = $db->prepare("SELECT id as portal_id, username, nama_lengkap as nama FROM users WHERE tupoksi = ? AND status = 1");
                    $stmtU->execute([$role]);
                    $users = $stmtU->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($users as $u) {
                        // Cari id dari tabel perf_users
                        $stmtPU = $db->prepare("SELECT id FROM perf_users WHERE username = ? LIMIT 1");
                        $stmtPU->execute([$u['username']]);
                        $perfUser = $stmtPU->fetch(PDO::FETCH_ASSOC);
                        
                        $answered = 0;
                        if ($perfUser) {
                            $perf_user_id = $perfUser['id'];
                            $stmtAns = $db->prepare("SELECT COUNT(*) FROM perf_penilaian WHERE penilai_id = ? AND periode_id = ?");
                            $stmtAns->execute([$perf_user_id, $periode_id]);
                            $answered = (int)$stmtAns->fetchColumn();
                        }
                        
                        $progress_data[] = [
                            'id' => $u['portal_id'],
                            'penilai_id' => $perf_user_id ?? 0,
                            'penilai_type' => 'kepsek',
                            'nama' => $u['nama'],
                            'role' => $role,
                            'username' => $u['username'],
                            'answered' => $answered,
                            'total' => $total_expected,
                            'percentage' => round(($answered / $total_expected) * 100)
                        ];
                    }
                } else {
                    // Guru / Staf
                    $stmtU = $db->prepare("SELECT id as portal_id, username, nama_lengkap as nama FROM users WHERE tupoksi = ? AND status = 1");
                    $stmtU->execute([$role]);
                    $users = $stmtU->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($users as $u) {
                        $total_expected = 0;
                        $rater_str = $role;
                        $rater_fallback = (stripos($role, 'Guru') !== false || stripos($role, 'Wali Kelas') !== false) ? 'Guru' : $role;
                        
                        // 1. Diri Sendiri
                        $stmtSelf = $db->prepare("
                            SELECT COUNT(id) FROM perf_instrumen WHERE periode_id = ? AND is_manual = 0 AND (
                                (
                                    (FIND_IN_SET(?, target_jabatan) > 0 OR FIND_IN_SET(?, target_jabatan) > 0)
                                    AND (target_dinilai = 'Semua' OR FIND_IN_SET(?, target_dinilai) > 0)
                                )
                                OR
                                (
                                    FIND_IN_SET('Diri Sendiri', target_jabatan) > 0 
                                    AND (target_dinilai = 'Semua' OR FIND_IN_SET(?, target_dinilai) > 0 OR FIND_IN_SET('Diri Sendiri', target_dinilai) > 0)
                                )
                                OR
                                (FIND_IN_SET(?, target_jabatan) > 0 AND FIND_IN_SET('Diri Sendiri', target_dinilai) > 0)
                            )
                        ");
                        $stmtSelf->execute([
                            $periode_id, 
                            $rater_str, $rater_fallback, $role,
                            $role,
                            $role
                        ]);
                        $total_expected += (int)$stmtSelf->fetchColumn();
                        
                        // 2. Teman Sejawat
                        $stmtPeerList = $db->prepare("SELECT p.jenis_ptk FROM perf_penugasan_sejawat ps JOIN perf_ptk p ON p.id = ps.dinilai_ptk_id WHERE ps.penilai_ptk_id = (SELECT id FROM perf_ptk WHERE niy = ? LIMIT 1) AND ps.periode_id = ?");
                        $stmtPeerList->execute([$u['username'], $periode_id]);
                        $peers = $stmtPeerList->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($peers as $peer) {
                            $stmtPeerQ = $db->prepare("
                                SELECT COUNT(id) FROM perf_instrumen WHERE periode_id = ? AND is_manual = 0 AND (
                                    (
                                        (FIND_IN_SET(?, target_jabatan) > 0 OR FIND_IN_SET(?, target_jabatan) > 0)
                                        AND (target_dinilai = 'Semua' OR FIND_IN_SET(?, target_dinilai) > 0)
                                    )
                                    OR
                                    (
                                        FIND_IN_SET('Teman Sejawat', target_jabatan) > 0 
                                        AND (target_dinilai = 'Semua' OR FIND_IN_SET(?, target_dinilai) > 0 OR FIND_IN_SET('Teman Sejawat', target_dinilai) > 0)
                                    )
                                    OR
                                    (FIND_IN_SET(?, target_jabatan) > 0 AND FIND_IN_SET('Teman Sejawat', target_dinilai) > 0)
                                )
                            ");
                            $stmtPeerQ->execute([
                                $periode_id, 
                                $rater_str, $rater_fallback, $peer['jenis_ptk'],
                                $peer['jenis_ptk'],
                                $peer['jenis_ptk']
                            ]);
                            $total_expected += (int)$stmtPeerQ->fetchColumn();
                        }
                        
                        if ($total_expected === 0) $total_expected = 1;

                        // Cari id dari tabel perf_users berdasarkan username (karena penilai_id menyimpan perf_users.id)
                        $stmtPU = $db->prepare("SELECT id FROM perf_users WHERE username = ? LIMIT 1");
                        $stmtPU->execute([$u['username']]);
                        $perfUser = $stmtPU->fetch(PDO::FETCH_ASSOC);
                        
                        $answered = 0;
                        if ($perfUser) {
                            $perf_user_id = $perfUser['id'];
                            // penilai_type bervariasi (bisa strtolower(jenis_ptk)), jadi hapus pengecekan penilai_type yang hardcode
                            $stmtAns = $db->prepare("SELECT COUNT(*) FROM perf_penilaian WHERE penilai_id = ? AND periode_id = ?");
                            $stmtAns->execute([$perf_user_id, $periode_id]);
                            $answered = (int)$stmtAns->fetchColumn();
                        }
                        
                        $progress_data[] = [
                            'id' => $u['portal_id'],
                            'penilai_id' => $perf_user_id ?? 0,
                            'penilai_type' => 'guru',
                            'nama' => $u['nama'],
                            'role' => $role,
                            'username' => $u['username'],
                            'answered' => $answered,
                            'total' => $total_expected,
                            'percentage' => round(($answered / $total_expected) * 100)
                        ];
                    }
                }
            }
            
            json_response(200, true, 'Data progress penilai', $progress_data);
            break;

        case 'reset_penilaian':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $raw = file_get_contents("php://input");
            $input = json_decode($raw, true);
            $penilai_id = (int)($input['penilai_id'] ?? 0);
            $penilai_type = $input['penilai_type'] ?? '';
            $periode_id = (int)($input['periode_id'] ?? 0);
            
            if (!$penilai_id || !$penilai_type || !$periode_id) {
                file_put_contents(__DIR__ . '/debug_reset.txt', "RAW: $raw\nINPUT: " . print_r($input, true) . "\nID: $penilai_id, TYPE: $penilai_type, PERIODE: $periode_id");
                throw new Exception('Data tidak lengkap', 400);
            }
            
            $stmt = $db->prepare("DELETE FROM perf_penilaian WHERE penilai_id = ? AND penilai_type = ? AND periode_id = ?");
            $stmt->execute([$penilai_id, $penilai_type, $periode_id]);
            $deleted = $stmt->rowCount();
            
            json_response(200, true, "Berhasil menghapus $deleted penilaian.");
            break;

        case 'list_laporan':
            if ($method !== 'GET') throw new Exception('Method not allowed', 405);
            $periode_id = (int)($_GET['periode_id'] ?? 0);
            
            // Get active evaluator roles
            $stmtR = $db->prepare("SELECT jenis_penilai FROM perf_jenis_penilai WHERE periode_id = ?");
            $stmtR->execute([$periode_id]);
            $active_roles = $stmtR->fetchAll(PDO::FETCH_COLUMN);
            $active_roles_lower = array_map('strtolower', $active_roles);
            
            $has_siswa = in_array('siswa', $active_roles_lower);
            $has_kepsek = in_array('kepala sekolah', $active_roles_lower) || in_array('kepsek', $active_roles_lower);
            $has_guru = false;
            foreach ($active_roles_lower as $r) {
                if ($r !== 'siswa' && $r !== 'kepala sekolah' && $r !== 'kepsek') {
                    $has_guru = true;
                    break;
                }
            }

            // Get total potential evaluators
            $total_siswa = 0;
            if ($has_siswa) {
                $stmtS = $db->query("SELECT COUNT(id) FROM perf_siswa WHERE status = 1");
                $total_siswa = (int)$stmtS->fetchColumn();
            }

            $total_kepsek = 0;
            if ($has_kepsek) {
                $stmtK = $db->query("SELECT COUNT(id) FROM users WHERE tupoksi = 'Kepala Sekolah' AND status = 1");
                $total_kepsek = (int)$stmtK->fetchColumn();
            }

            // Get all PTK
            $stmtP = $db->query("SELECT id, nama, jenis_ptk FROM perf_ptk WHERE status = 1 ORDER BY nama ASC");
            $ptks = $stmtP->fetchAll(PDO::FETCH_ASSOC);

            // Pre-compile statement to check targets
            $stmtChk = $db->prepare("
                SELECT COUNT(id) FROM perf_instrumen WHERE periode_id = ? AND is_manual = 0 AND (
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
            ");

            $laporan_data = [];
            foreach ($ptks as $p) {
                $total_expected = 0;
                $ptk_id = $p['id'];
                $jenis_ptk = $p['jenis_ptk'];

                if (strtolower($jenis_ptk) === 'kepala sekolah') {
                    // Kepsek only evaluated by Kepsek
                    $rater_str = 'Kepala Sekolah';
                    $rater_fallback = 'Kepala Sekolah';
                    $stmtChk->execute([$periode_id, $rater_str, $rater_fallback, $jenis_ptk, $rater_str, $jenis_ptk, $rater_str, $jenis_ptk, $rater_str]);
                    if ($stmtChk->fetchColumn() > 0) {
                        $total_expected += $total_kepsek;
                    }
                } else {
                    // Guru / Staf
                    if ($has_siswa) {
                        $rater_str = 'Siswa';
                        $rater_fallback = 'Siswa';
                        $stmtChk->execute([$periode_id, $rater_str, $rater_fallback, $jenis_ptk, $rater_str, $jenis_ptk, $rater_str, $jenis_ptk, $rater_str]);
                        if ($stmtChk->fetchColumn() > 0) {
                            $jp = strtolower($jenis_ptk);
                            if ($jp === 'tu') {
                                $total_expected += $total_siswa;
                            } else {
                                $stmtS = $db->prepare("SELECT COUNT(*) FROM perf_siswa_guru WHERE perf_ptk_id = ? AND periode_id = ?");
                                $stmtS->execute([$ptk_id, $periode_id]);
                                $total_expected += (int)$stmtS->fetchColumn();
                            }
                        }
                    }
                    
                    if ($has_kepsek) {
                        $rater_str = 'Kepala Sekolah';
                        $rater_fallback = 'Kepala Sekolah';
                        $stmtChk->execute([$periode_id, $rater_str, $rater_fallback, $jenis_ptk, $rater_str, $jenis_ptk, $rater_str, $jenis_ptk, $rater_str]);
                        if ($stmtChk->fetchColumn() > 0) {
                            $total_expected += $total_kepsek;
                        }
                    }
                    
                    if ($has_guru) {
                        // Diri Sendiri
                        $rater_str = 'Diri Sendiri';
                        $rater_fallback = 'Diri Sendiri';
                        $stmtChk->execute([$periode_id, $rater_str, $rater_fallback, $jenis_ptk, $rater_str, $jenis_ptk, $rater_str, $jenis_ptk, $rater_str]);
                        if ($stmtChk->fetchColumn() > 0) {
                            $total_expected += 1;
                        }
                        
                        // Peers
                        $rater_str = 'Teman Sejawat';
                        $rater_fallback = 'Teman Sejawat';
                        $stmtChk->execute([$periode_id, $rater_str, $rater_fallback, $jenis_ptk, $rater_str, $jenis_ptk, $rater_str, $jenis_ptk, $rater_str]);
                        if ($stmtChk->fetchColumn() > 0) {
                            $stmtPeer = $db->prepare("SELECT COUNT(*) FROM perf_penugasan_sejawat WHERE dinilai_ptk_id = ? AND periode_id = ?");
                            $stmtPeer->execute([$ptk_id, $periode_id]);
                            $total_expected += (int)$stmtPeer->fetchColumn();
                        }
                    }
                }

                if ($total_expected === 0) $total_expected = 1;

                // Total people who have answered for this PTK
                $stmtAns = $db->prepare("SELECT COUNT(DISTINCT penilai_id, penilai_type) FROM perf_penilaian WHERE dinilai_ptk_id = ? AND periode_id = ?");
                $stmtAns->execute([$ptk_id, $periode_id]);
                $answered = (int)$stmtAns->fetchColumn();

                $laporan_data[] = [
                    'id' => $ptk_id,
                    'nama' => $p['nama'],
                    'jenis_ptk' => $p['jenis_ptk'],
                    'answered' => $answered,
                    'total' => $total_expected,
                    'percentage' => round(($answered / $total_expected) * 100)
                ];
            }

            json_response(200, true, 'Data laporan penilaian', $laporan_data);
            break;

        case 'copy_from_period':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            $from_id = (int)($input['from_periode_id'] ?? 0);
            $to_id = (int)($input['to_periode_id'] ?? 0);
            
            if (!$from_id || !$to_id) throw new Exception('Periode asal dan tujuan harus dipilih', 400);
            if ($from_id === $to_id) throw new Exception('Periode asal dan tujuan tidak boleh sama', 400);
            
            // Check if to_id already has data
            $chk1 = $db->prepare("SELECT COUNT(*) FROM perf_instrumen WHERE periode_id = ?");
            $chk1->execute([$to_id]);
            if ($chk1->fetchColumn() > 0) throw new Exception('Periode tujuan sudah memiliki data instrumen. Kosongkan dulu untuk menyalin.', 400);
            
            // Copy penilai
            $stmt1 = $db->prepare("INSERT INTO perf_jenis_penilai (periode_id, jenis_penilai, target_dinilai) SELECT ?, jenis_penilai, target_dinilai FROM perf_jenis_penilai WHERE periode_id = ?");
            $stmt1->execute([$to_id, $from_id]);
            
            // Copy instrumen
            // We give them new codes just in case
            $stmt2 = $db->prepare("SELECT * FROM perf_instrumen WHERE periode_id = ?");
            $stmt2->execute([$from_id]);
            $questions = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            $ins = $db->prepare("INSERT INTO perf_instrumen (periode_id, kode, kategori, target_jabatan, pertanyaan, urutan, bobot, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $c = 0;
            foreach ($questions as $q) {
                $ins->execute([
                    $to_id,
                    'P'.time().$c,
                    $q['kategori'],
                    $q['target_jabatan'],
                    $q['pertanyaan'],
                    $q['urutan'],
                    $q['bobot'],
                    $q['status']
                ]);
                $c++;
            }
            
            json_response(200, true, "Berhasil menyalin $c pertanyaan dan pengaturan penilai.");
            break;

        case 'list_aturan_sejawat':
            if ($method !== 'GET') throw new Exception('Method not allowed', 405);
            $periode_id = (int)($_GET['periode_id'] ?? 0);
            $stmt = $db->prepare("SELECT penilai_jenis, GROUP_CONCAT(dinilai_jenis SEPARATOR '||') as dinilai_list FROM perf_aturan_sejawat WHERE periode_id = ? GROUP BY penilai_jenis");
            $stmt->execute([$periode_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(200, true, 'Data aturan sejawat', $data);
            break;

        case 'create_aturan_sejawat':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            $penilai = $input['penilai'] ?? [];
            $dinilai = $input['dinilai'] ?? [];
            $periode_id = (int)($input['periode_id'] ?? 0);
            
            if (!$periode_id) throw new Exception('Periode belum dipilih', 400);
            if (empty($penilai)) throw new Exception('Pilih minimal satu penilai', 400);
            if (empty($dinilai)) throw new Exception('Pilih minimal satu target dinilai', 400);
            
            $stmt = $db->prepare("INSERT INTO perf_aturan_sejawat (periode_id, penilai_jenis, dinilai_jenis) VALUES (?, ?, ?)");
            $inserted = 0;
            foreach ($penilai as $p) {
                // Hapus aturan lama untuk penilai ini di periode ini agar bisa ditimpa (opsional, tapi lebih baik append saja seperti sebelumnya, namun ini menghindari duplikat)
                foreach ($dinilai as $d) {
                    $chk = $db->prepare("SELECT id FROM perf_aturan_sejawat WHERE periode_id=? AND penilai_jenis=? AND dinilai_jenis=?");
                    $chk->execute([$periode_id, $p, $d]);
                    if (!$chk->fetch()) {
                        $stmt->execute([$periode_id, $p, $d]);
                        $inserted++;
                    }
                }
            }
            
            json_response(200, true, "Berhasil menambahkan $inserted aturan sejawat");
            break;

        case 'delete_aturan_sejawat':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            $penilai_jenis = $input['penilai_jenis'] ?? '';
            $periode_id = (int)($input['periode_id'] ?? 0);
            
            $stmt = $db->prepare("DELETE FROM perf_aturan_sejawat WHERE periode_id = ? AND penilai_jenis = ?");
            $stmt->execute([$periode_id, $penilai_jenis]);
            
            json_response(200, true, 'Aturan berhasil dihapus');
            break;

        default:
            throw new Exception('Aksi tidak valid', 400);
    }
} catch (Exception $e) {
    $code = (is_numeric($e->getCode()) && $e->getCode() >= 400) ? $e->getCode() : 500;
    json_response($code, false, $e->getMessage());
}
