<?php
/**
 * E-Performance — Penilaian API
 * Handle endpoint untuk isi penilaian (Phase 2)
 */
require_once __DIR__ . '/config_perf.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list_target': listTarget(); break;
    case 'get_form': getForm(); break;
    case 'submit': submitPenilaian(); break;
    case 'submit_matrix_single': submitMatrixSingle(); break;
    case 'submit_matrix_massal': submitMatrixMassal(); break;
    case 'dashboard_stats': dashboardStats(); break;
    case 'get_matrix_kepsek': getMatrixKepsek(); break;
    case 'rekap_nilai': rekapNilai(); break;
    case 'get_skor_akhir': getSkorAkhir(); break;
    default: json_response(400, false, 'Action tidak valid.');
}

function resolve_perf_ids($auth, $db) {
    $perf_user_id = $auth['user_id'];
    $ptk_id = $auth['ptk_id'] ?? 0;
    
    if (isset($auth['type']) && $auth['type'] === 'eportal') {
        $stmtPU = $db->prepare("SELECT id FROM perf_users WHERE username = ? LIMIT 1");
        $stmtPU->execute([$auth['username']]);
        $perfUser = $stmtPU->fetch(PDO::FETCH_ASSOC);
        if ($perfUser) $perf_user_id = $perfUser['id'];
        
        $stmtFind = $db->prepare("SELECT id FROM perf_ptk WHERE niy = ? LIMIT 1");
        $stmtFind->execute([$auth['username']]);
        $foundPtk = $stmtFind->fetch(PDO::FETCH_ASSOC);
        if ($foundPtk) $ptk_id = $foundPtk['id'];
    } else {
        $ptk_id = $ptk_id ?: $perf_user_id;
    }
    
    return ['perf_user_id' => $perf_user_id, 'ptk_id' => $ptk_id];
}

function check_periode_active($db, $strict = true) {
    $stmt = $db->query("SELECT id, tgl_mulai, tgl_selesai FROM perf_periode WHERE status = 'aktif' LIMIT 1");
    $aktif = $stmt->fetch();
    if (!$aktif) {
        json_response(400, false, 'Tidak ada periode aktif.');
    }
    
    if ($strict) {
        $now = date('Y-m-d');
        if (!empty($aktif['tgl_mulai']) && $now < $aktif['tgl_mulai']) {
            json_response(400, false, 'Periode penilaian belum dimulai (Mulai: ' . date('d/m/Y', strtotime($aktif['tgl_mulai'])) . ').');
        }
        if (!empty($aktif['tgl_selesai']) && $now > $aktif['tgl_selesai']) {
            json_response(400, false, 'Periode penilaian sudah ditutup (Batas: ' . date('d/m/Y', strtotime($aktif['tgl_selesai'])) . ').');
        }
    }
    
    return $aktif['id'];
}

function listTarget() {
    $auth = perf_auth_check();
    $db = db();
    
    $periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    $is_open = true;
    $tgl_msg = '';
    
    if (!$periode_id) {
        $stmtP = $db->query("SELECT id, tgl_mulai, tgl_selesai FROM perf_periode WHERE status = 'aktif' LIMIT 1");
        $aktif = $stmtP->fetch();
        if ($aktif) {
            $periode_id = $aktif['id'];
            $now = date('Y-m-d');
            if (!empty($aktif['tgl_mulai']) && $now < $aktif['tgl_mulai']) {
                $is_open = false;
                $tgl_msg = 'Belum Dimulai (Mulai: ' . date('d/m/Y', strtotime($aktif['tgl_mulai'])) . ')';
            }
            if (!empty($aktif['tgl_selesai']) && $now > $aktif['tgl_selesai']) {
                $is_open = false;
                $tgl_msg = 'Sudah Ditutup (Batas: ' . date('d/m/Y', strtotime($aktif['tgl_selesai'])) . ')';
            }
        } else {
            json_response(400, false, 'Tidak ada periode aktif.');
        }
    }

    $role = $auth['role'];
    $resolved = resolve_perf_ids($auth, $db);
    $perf_user_id = $resolved['perf_user_id'];
    $ptk_id = $resolved['ptk_id'];
    
    $actualTupoksi = 'Guru';
    if ($role !== 'siswa') {
        $stmtTupoksi = $db->prepare("SELECT tupoksi FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmtTupoksi->execute([$auth['username']]);
        $tupoksiRow = $stmtTupoksi->fetch(PDO::FETCH_ASSOC);
        if ($tupoksiRow && !empty($tupoksiRow['tupoksi'])) {
            $actualTupoksi = $tupoksiRow['tupoksi'];
        }
    }
    
    $targets = [];

    if ($role === 'siswa') {
        // Ambil ID siswa dari tabel perf_siswa berdasarkan user login
        $stmtSiswa = $db->prepare("SELECT id FROM perf_siswa WHERE perf_user_id = ? LIMIT 1");
        $stmtSiswa->execute([$perf_user_id]);
        $siswa = $stmtSiswa->fetch(PDO::FETCH_ASSOC);
        $siswa_id = $siswa ? $siswa['id'] : 0;

        $stmt = $db->prepare("
            SELECT p.id, p.nama, p.jenis_ptk, p.mata_pelajaran 
            FROM perf_ptk p
            WHERE p.status = 1 
            AND p.id IN (SELECT perf_ptk_id FROM perf_siswa_guru WHERE perf_siswa_id = ? AND periode_id = ?)
            ORDER BY p.jenis_ptk, p.nama ASC
        ");
        $stmt->execute([$siswa_id, $periode_id]);
        $ptks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ptks as $p) {
            $targets[] = [
                'target_id' => $p['id'],
                'nama' => $p['nama'],
                'jenis_ptk' => $p['jenis_ptk'],
                'mata_pelajaran' => $p['mata_pelajaran'] ?? null,
                'target_type' => 'Guru/Staf' // Dari sudut pandang siswa, ini Guru/Staf
            ];
        }
    } else if (strtolower($actualTupoksi) === 'kepala sekolah' || strtolower($actualTupoksi) === 'plt. kepala sekolah') {
        // Kepala Sekolah menilai SEMUA PTK (termasuk dirinya sendiri atau tidak? Biasanya orang lain saja)
        // Kita tampilkan semua PTK
        $stmt = $db->prepare("SELECT id, nama, jenis_ptk, mata_pelajaran FROM perf_ptk WHERE status = 1 ORDER BY nama ASC");
        $stmt->execute();
        $ptks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ptks as $p) {
            $targets[] = [
                'target_id' => $p['id'],
                'nama' => $p['nama'],
                'jenis_ptk' => $p['jenis_ptk'],
                'mata_pelajaran' => $p['mata_pelajaran'] ?? null,
                'target_type' => ($p['id'] == $perf_user_id) ? 'Diri Sendiri' : ''
            ];
        }
    } else {
        // Guru & Staf (TU, dll)
        // 1. Menilai Diri Sendiri
        $stmt = $db->prepare("SELECT id, nama, jenis_ptk, mata_pelajaran FROM perf_ptk WHERE id = ? LIMIT 1");
        $stmt->execute([$ptk_id]);
        $self = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($self) {
            $targets[] = [
                'target_id' => $self['id'],
                'nama' => $self['nama'],
                'jenis_ptk' => $self['jenis_ptk'],
                'mata_pelajaran' => $self['mata_pelajaran'] ?? null,
                'target_type' => 'Diri Sendiri'
            ];
        }
        
        // 2. Menilai 1 Teman Sejawat
        // Cek apakah sudah ada penugasan sejawat
        $stmt = $db->prepare("SELECT dinilai_ptk_id FROM perf_penugasan_sejawat WHERE periode_id = ? AND penilai_ptk_id = ?");
        $stmt->execute([$periode_id, $ptk_id]);
        $assigned = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $peer_id = null;
        if ($assigned) {
            $peer_id = $assigned['dinilai_ptk_id'];
            
            // Validasi apakah assignment masih sesuai dengan aturan sejawat saat ini
            if ($self) {
                $stmtPeer = $db->prepare("SELECT jenis_ptk FROM perf_ptk WHERE id = ?");
                $stmtPeer->execute([$peer_id]);
                $peerInfo = $stmtPeer->fetch(PDO::FETCH_ASSOC);
                
                if ($peerInfo) {
                    $stmtAturanCheck = $db->prepare("SELECT id FROM perf_aturan_sejawat WHERE periode_id = ? AND penilai_jenis = ? AND dinilai_jenis = ?");
                    $stmtAturanCheck->execute([$periode_id, $self['jenis_ptk'], $peerInfo['jenis_ptk']]);
                    
                    $aturanValid = $stmtAturanCheck->fetch() !== false;

                    // Validasi tambahan: jika tupoksi sama, tapi jumlahnya kurang dari 5, maka aturan tidak valid
                    if ($aturanValid && $self['jenis_ptk'] === $peerInfo['jenis_ptk']) {
                        $stmtCount = $db->prepare("SELECT COUNT(*) FROM perf_ptk WHERE status = 1 AND jenis_ptk = ?");
                        $stmtCount->execute([$self['jenis_ptk']]);
                        $tupoksiCount = $stmtCount->fetchColumn();
                        if ($tupoksiCount < 5) {
                            $aturanValid = false;
                        }
                    }

                    if (!$aturanValid) {
                        // Aturan tidak ada/dihapus atau melanggar aturan < 5 tupoksi sama, batalkan dan hapus penugasan
                        $db->prepare("DELETE FROM perf_penugasan_sejawat WHERE periode_id = ? AND penilai_ptk_id = ?")->execute([$periode_id, $ptk_id]);
                        $peer_id = null;
                    }
                }
            }
        }
        
        if (!$peer_id) {
            if ($self) {
                // Cek aturan sejawat untuk tupoksi penilai ini
                $stmtAturan = $db->prepare("SELECT dinilai_jenis FROM perf_aturan_sejawat WHERE periode_id = ? AND penilai_jenis = ?");
                $stmtAturan->execute([$periode_id, $self['jenis_ptk']]);
                $aturan = $stmtAturan->fetchAll(PDO::FETCH_COLUMN);

                if (count($aturan) > 0) {
                    // Cek apakah jenis_ptk sendiri ada di dalam aturan
                    if (in_array($self['jenis_ptk'], $aturan)) {
                        // Hitung jumlah PTK di tupoksi ini
                        $stmtCount = $db->prepare("SELECT COUNT(*) FROM perf_ptk WHERE status = 1 AND jenis_ptk = ?");
                        $stmtCount->execute([$self['jenis_ptk']]);
                        $tupoksiCount = $stmtCount->fetchColumn();

                        // Jika jumlah orang di tupoksinya kurang dari 5, jangan pertemukan sesama
                        if ($tupoksiCount < 5) {
                            $aturan = array_filter($aturan, function($val) use ($self) {
                                return $val !== $self['jenis_ptk'];
                            });
                        }
                    }

                    if (count($aturan) > 0) {
                        $inPlaceholders = implode(',', array_fill(0, count($aturan), '?'));
                        $sql = "SELECT id FROM perf_ptk WHERE id != ? AND status = 1 AND jenis_ptk IN ($inPlaceholders) ORDER BY RAND() LIMIT 1";
                        $stmtRandom = $db->prepare($sql);
                        $params = array_merge([$ptk_id], array_values($aturan));
                        $stmtRandom->execute($params);
                        $randomPeer = $stmtRandom->fetch(PDO::FETCH_ASSOC);

                        if ($randomPeer) {
                            $peer_id = $randomPeer['id'];
                            // Simpan penugasan
                            $stmtInsert = $db->prepare("INSERT INTO perf_penugasan_sejawat (periode_id, penilai_ptk_id, dinilai_ptk_id) VALUES (?, ?, ?)");
                            $stmtInsert->execute([$periode_id, $ptk_id, $peer_id]);
                        }
                    }
                }
                // Jika tidak ada aturan, peer_id tetap null (tidak ditugaskan teman sejawat)
            }
        }
        
        if ($peer_id) {
            $stmt = $db->prepare("SELECT id, nama, jenis_ptk, mata_pelajaran FROM perf_ptk WHERE id = ? LIMIT 1");
            $stmt->execute([$peer_id]);
            $peer = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($peer) {
                $targets[] = [
                    'target_id' => $peer['id'],
                    'nama' => $peer['nama'],
                    'jenis_ptk' => $peer['jenis_ptk'],
                    'mata_pelajaran' => $peer['mata_pelajaran'] ?? null,
                    'target_type' => 'Teman Sejawat'
                ];
            }
        }
    }
    
    // Cek progress pengisian untuk masing-masing target
    foreach ($targets as &$t) {
        $penilai_type = 'guru';
        if ($role === 'siswa') {
            $penilai_type = 'siswa';
        } else if (strtolower($actualTupoksi) === 'kepala sekolah') {
            $penilai_type = 'kepsek';
        } else if (isset($self) && $self) {
            $penilai_type = strtolower($self['jenis_ptk']);
        }
        
        // Tentukan role penilai dalam bentuk string untuk mencocokkan target_jabatan (yang sebenarnya berisi target_penilai)
        $rater_str = 'Guru';
        $rater_fallback = 'Guru';
        $rater_str_self = 'Guru';
        
        if ($role === 'siswa') {
            $rater_str = 'Siswa';
            $rater_fallback = 'Siswa';
            $rater_str_self = 'Siswa';
        } else if (strtolower($actualTupoksi) === 'kepala sekolah') {
            $rater_str = 'Kepala Sekolah';
            $rater_fallback = 'Kepala Sekolah';
            $rater_str_self = 'Kepala Sekolah';
        } else {
            if ($ptk_id == $t['target_id']) {
                $rater_str = $actualTupoksi;
                $rater_str_self = 'Diri Sendiri';
                $rater_fallback = (stripos($actualTupoksi, 'Guru') !== false || stripos($actualTupoksi, 'Wali Kelas') !== false) ? 'Guru' : $actualTupoksi;
            } else {
                $rater_str = $actualTupoksi;
                $rater_str_self = 'Teman Sejawat';
                $rater_fallback = (stripos($actualTupoksi, 'Guru') !== false || stripos($actualTupoksi, 'Wali Kelas') !== false) ? 'Guru' : $actualTupoksi;
            }
        }

        // Determine strict target for dinilai (Diri Sendiri, Teman Sejawat, or their actual PTK type)
        $strict_dinilai = '';
        if (isset($t['target_type']) && $t['target_type'] === 'Diri Sendiri') {
            $strict_dinilai = 'Diri Sendiri';
        } else if (isset($t['target_type']) && $t['target_type'] === 'Teman Sejawat') {
            $strict_dinilai = 'Teman Sejawat';
        } else {
            $strict_dinilai = $t['jenis_ptk'];
        }

        // Count total questions available for this rater (match either actual tupoksi, fallback, or Diri Sendiri)
        $stmtTotal = $db->prepare("
            SELECT COUNT(id) 
            FROM perf_instrumen 
            WHERE periode_id = ? AND is_manual = 0 AND (
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
        $stmtTotal->execute([
            $periode_id, 
            $rater_str, $rater_fallback, $t['jenis_ptk'],
            $rater_str_self, $t['jenis_ptk'], $rater_str_self,
            $t['jenis_ptk'], $rater_str_self
        ]);
        $total = (int)$stmtTotal->fetchColumn();
        
        // Count answered
        $stmtAns = $db->prepare("
            SELECT COUNT(*) 
            FROM perf_penilaian 
            WHERE periode_id = ? AND penilai_id = ? AND dinilai_ptk_id = ?
        ");
        $stmtAns->execute([$periode_id, $perf_user_id, $t['target_id']]);
        $answered = (int)$stmtAns->fetchColumn();
        
        $t['progress_answered'] = $answered;
        $t['progress_total'] = $total;
        $t['is_completed'] = ($total > 0 && $answered >= $total);
    }
    
    json_response(200, true, 'Daftar Target', [
        'targets' => $targets,
        'is_open' => $is_open,
        'msg' => $tgl_msg
    ]);
}

function getForm() {
    $auth = perf_auth_check();
    $db = db();
    
    $periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    if (!$periode_id) {
        $periode_id = check_periode_active($db, false); // Don't block getForm strictly so users can view results
    }
    
    $target_id = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;
    $target_jenis = isset($_GET['target_jenis']) ? sanitize($_GET['target_jenis']) : '';
    
    if (!$target_id) {
        json_response(400, false, 'Target tidak valid');
    }

    $role = $auth['role'];
    $resolved = resolve_perf_ids($auth, $db);
    $perf_user_id = $resolved['perf_user_id'];
    $ptk_id = $resolved['ptk_id'];
    
    $actualTupoksi = 'Guru';
    if ($role !== 'siswa') {
        $stmtTupoksi = $db->prepare("SELECT tupoksi FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmtTupoksi->execute([$auth['username']]);
        $tupoksiRow = $stmtTupoksi->fetch(PDO::FETCH_ASSOC);
        if ($tupoksiRow && !empty($tupoksiRow['tupoksi'])) {
            $actualTupoksi = $tupoksiRow['tupoksi'];
        }
    }
    
    $stmtT = $db->prepare("SELECT jenis_ptk FROM perf_ptk WHERE id = ?");
    $stmtT->execute([$target_id]);
    $target_ptk = $stmtT->fetch(PDO::FETCH_ASSOC);
    
    $target_jenis_ptk = $target_ptk ? strtolower($target_ptk['jenis_ptk']) : 'guru';

    // Tentukan penilai_type (untuk record jawaban) & rater_str (untuk filter soal di target_jabatan)
    $penilai_type = 'guru';
    $rater_str = 'Guru';
    
    $rater_fallback = 'Guru';
    $rater_str_self = 'Guru';
    $target_type_str = '';
    
    if ($role === 'siswa') {
        $penilai_type = 'siswa';
        $rater_str = 'Siswa';
        $rater_fallback = 'Siswa';
        $rater_str_self = 'Siswa';
        $target_type_str = 'Guru/Staf';
    } else if (strtolower($actualTupoksi) === 'kepala sekolah') {
        $penilai_type = 'kepsek';
        $rater_str = 'Kepala Sekolah';
        $rater_fallback = 'Kepala Sekolah';
        $rater_str_self = 'Kepala Sekolah';
        $target_type_str = '';
    } else {
        $stmt = $db->prepare("SELECT jenis_ptk FROM perf_ptk WHERE id = ? LIMIT 1");
        $stmt->execute([$ptk_id]);
        $self = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($self) {
            $penilai_type = strtolower($self['jenis_ptk']);
        }
        
        if ($ptk_id == $target_id) {
            $rater_str = $actualTupoksi;
            $rater_str_self = 'Diri Sendiri';
            $rater_fallback = (stripos($actualTupoksi, 'Guru') !== false || stripos($actualTupoksi, 'Wali Kelas') !== false) ? 'Guru' : $actualTupoksi;
            $target_type_str = 'Diri Sendiri';
        } else {
            $rater_str = $actualTupoksi;
            $rater_str_self = 'Teman Sejawat';
            $rater_fallback = (stripos($actualTupoksi, 'Guru') !== false || stripos($actualTupoksi, 'Wali Kelas') !== false) ? 'Guru' : $actualTupoksi;
            $target_type_str = 'Teman Sejawat';
        }
    }

    $actual_target_jenis = $target_ptk ? $target_ptk['jenis_ptk'] : 'Guru';

    // Determine strict target for dinilai
    $strict_dinilai = '';
    if (isset($target_type_str) && $target_type_str === 'Diri Sendiri') {
        $strict_dinilai = 'Diri Sendiri';
    } else if (isset($target_type_str) && $target_type_str === 'Teman Sejawat') {
        $strict_dinilai = 'Teman Sejawat';
    } else {
        $strict_dinilai = $actual_target_jenis;
    }

    // Ambil pertanyaan (cocokkan target_jabatan dengan tupoksi asli, fallback, ATAU 'Diri Sendiri' jika sedang menilai diri sendiri)
    $stmtQ = $db->prepare("
        SELECT id as instrumen_id, kategori, pertanyaan
        FROM perf_instrumen
        WHERE periode_id = ? AND is_manual = 0 AND (
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
        $rater_str, $rater_fallback, $actual_target_jenis,
        $rater_str_self, $actual_target_jenis, $rater_str_self,
        $actual_target_jenis, $rater_str_self
    ]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    // Ambil jawaban yang sudah ada
    $stmtA = $db->prepare("
        SELECT instrumen_id, nilai, catatan 
        FROM perf_penilaian 
        WHERE periode_id = ? AND penilai_id = ? AND dinilai_ptk_id = ?
    ");
    $stmtA->execute([$periode_id, $perf_user_id, $target_id]);
    $answersData = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    
    $answers = [];
    foreach ($answersData as $a) {
        $answers[$a['instrumen_id']] = $a;
    }

    json_response(200, true, 'Formulir Penilaian', [
        'questions' => $questions,
        'answers' => $answers,
        'penilai_type' => $penilai_type
    ]);
}

function submitPenilaian() {
    $auth = perf_auth_check();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $db = db();
    
    $input = get_input();
    $periode_id = (int)($input['periode_id'] ?? 0);
    if (!$periode_id) {
        $periode_id = check_periode_active($db, true);
    }
    
    $target_id = (int)($input['target_id'] ?? 0);
    $penilai_type = strtolower(sanitize($input['penilai_type'] ?? ''));
    if ($penilai_type === 'kepala sekolah') $penilai_type = 'kepsek';
    $answers = $input['answers'] ?? []; // format: [{instrumen_id, nilai, catatan}]
    
    if (!$target_id || empty($answers)) {
        json_response(400, false, 'Data tidak lengkap');
    }

    $resolved = resolve_perf_ids($auth, $db);
    $perf_user_id = $resolved['perf_user_id'];
    
    try {
        $db->beginTransaction();
        
        // Hapus jawaban lama untuk target ini dari penilai ini
        $stmtDel = $db->prepare("
            DELETE FROM perf_penilaian 
            WHERE periode_id = ? AND penilai_id = ? AND dinilai_ptk_id = ?
        ");
        $stmtDel->execute([$periode_id, $perf_user_id, $target_id]);
        
        // Insert jawaban baru
        $stmtIns = $db->prepare("
            INSERT INTO perf_penilaian (periode_id, penilai_type, penilai_id, dinilai_ptk_id, instrumen_id, nilai, catatan)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($answers as $ans) {
            $instrumen_id = (int)$ans['instrumen_id'];
            $nilai = (int)$ans['nilai'];
            $catatan = sanitize($ans['catatan'] ?? '');
            
            $stmtIns->execute([
                $periode_id, $penilai_type, $perf_user_id, $target_id, $instrumen_id, $nilai, $catatan
            ]);
        }
        
        $db->commit();
        json_response(200, true, 'Penilaian berhasil disimpan');
    } catch (Exception $e) {
        $db->rollBack();
        json_response(500, false, 'Gagal menyimpan penilaian: ' . $e->getMessage());
    }
}

function dashboardStats() {
    $auth = perf_auth_check();
    $db = db();
    $role = $auth['role'];
    $resolved = resolve_perf_ids($auth, $db);
    $perf_user_id = $resolved['perf_user_id'];
    $ptk_id = $resolved['ptk_id'];
    
    $actualTupoksi = 'Guru';
    if ($role !== 'siswa') {
        $stmtTupoksi = $db->prepare("SELECT tupoksi FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmtTupoksi->execute([$auth['username']]);
        $tupoksiRow = $stmtTupoksi->fetch(PDO::FETCH_ASSOC);
        if ($tupoksiRow && !empty($tupoksiRow['tupoksi'])) {
            $actualTupoksi = $tupoksiRow['tupoksi'];
        }
    }
    
    $periode_id = check_periode_active($db, false);
    
    $targets = [];
    if ($role === 'siswa') {
        $stmtSiswa = $db->prepare("SELECT id FROM perf_siswa WHERE perf_user_id = ? LIMIT 1");
        $stmtSiswa->execute([$perf_user_id]);
        $siswa = $stmtSiswa->fetch(PDO::FETCH_ASSOC);
        $siswa_id = $siswa ? $siswa['id'] : 0;

        $stmt = $db->prepare("
            SELECT id as target_id 
            FROM perf_ptk 
            WHERE status = 1 
            AND (
                jenis_ptk = 'tu' 
                OR 
                id IN (SELECT perf_ptk_id FROM perf_siswa_guru WHERE perf_siswa_id = ?)
            )
        ");
        $stmt->execute([$siswa_id]);
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if (strtolower($actualTupoksi) === 'kepala sekolah') {
        $stmt = $db->prepare("SELECT id as target_id, jenis_ptk FROM perf_ptk WHERE status = 1 AND jenis_ptk != 'Kepala Sekolah'");
        $stmt->execute();
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtSelf = $db->prepare("SELECT id as target_id, jenis_ptk FROM perf_ptk WHERE id = ?");
        $stmtSelf->execute([$ptk_id]);
        if ($selfRow = $stmtSelf->fetch(PDO::FETCH_ASSOC)) {
            $targets[] = $selfRow;
        }
        
        $stmt = $db->prepare("SELECT p.dinilai_ptk_id as target_id, pt.jenis_ptk FROM perf_penugasan_sejawat p JOIN perf_ptk pt ON p.dinilai_ptk_id = pt.id WHERE p.penilai_ptk_id = ? AND p.periode_id = ?");
        $stmt->execute([$ptk_id, $periode_id]);
        $peers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($peers as $p) {
            $targets[] = $p;
        }
    }
    
    $total_targets = count($targets);
    $selesai = 0;
    
    $penilai_type = 'guru';
    if ($role === 'siswa') $penilai_type = 'siswa';
    else if (strtolower($actualTupoksi) === 'kepala sekolah') $penilai_type = 'kepsek';
    else if (isset($self) && $self) $penilai_type = strtolower($self['jenis_ptk']);

    $rater_str = 'Guru';
    $rater_fallback = 'Guru';
    
    if ($role === 'siswa') {
        $rater_str = 'Siswa';
        $rater_fallback = 'Siswa';
    } else if (strtolower($actualTupoksi) === 'kepala sekolah') {
        $rater_str = 'Kepala Sekolah';
        $rater_fallback = 'Kepala Sekolah';
    } else {
        $rater_str = $actualTupoksi;
    }

    foreach ($targets as $t) {
        $actual_target_jenis = $t['jenis_ptk'] ?? 'Guru';
        $t_strict = $actual_target_jenis;

        if ($role !== 'siswa' && strtolower($actualTupoksi) !== 'kepala sekolah') {
            if ($ptk_id == $t['target_id']) {
                $r_str = $rater_str; // $rater_str holds tupoksi here
                $r_str_self = 'Diri Sendiri';
                $r_fallback = (stripos($rater_str, 'Guru') !== false || stripos($rater_str, 'Wali Kelas') !== false) ? 'Guru' : $rater_str;
                $t_strict = 'Diri Sendiri';
            } else {
                $r_str = $rater_str;
                $r_str_self = 'Teman Sejawat';
                $r_fallback = (stripos($rater_str, 'Guru') !== false || stripos($rater_str, 'Wali Kelas') !== false) ? 'Guru' : $rater_str;
                $t_strict = 'Teman Sejawat';
            }
        } else {
            $r_str = $rater_str;
            $r_str_self = $rater_str;
            $r_fallback = $rater_fallback;
        }
        
        $stmtTotal = $db->prepare("
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
        $stmtTotal->execute([
            $periode_id, 
            $r_str, $r_fallback, $actual_target_jenis,
            $r_str_self, $actual_target_jenis, $r_str_self,
            $actual_target_jenis, $r_str_self
        ]);
        $total_q = (int)$stmtTotal->fetchColumn();
        
        $stmtAns = $db->prepare("SELECT COUNT(*) FROM perf_penilaian WHERE periode_id = ? AND penilai_id = ? AND dinilai_ptk_id = ?");
        $stmtAns->execute([$periode_id, $perf_user_id, $t['target_id']]);
        $answered = (int)$stmtAns->fetchColumn();
        
        if ($total_q > 0 && $answered >= $total_q) {
            $selesai++;
        }
    }
    
    json_response(200, true, 'Dashboard stats', [
        'total' => $total_targets,
        'selesai' => $selesai,
        'belum' => $total_targets - $selesai
    ]);
}

// ============================================
// PHASE 3 - KEPSEK MATRIX VIEW
// ============================================

function getMatrixKepsek() {
    try {
        $auth = perf_auth_check();
        $db = db();
        
        // Strict Kepsek check
        $isKepsek = false;
        $tupoksi = strtolower(trim($auth['tupoksi'] ?? ''));
        if ($tupoksi === 'kepala sekolah' || $tupoksi === 'plt. kepala sekolah') {
            $isKepsek = true;
        } else if (isset($auth['type']) && $auth['type'] === 'perf' && strtolower($auth['role'] ?? '') === 'kepsek') {
            $isKepsek = true;
        }

        if (!$isKepsek) {
            json_response(403, false, 'Akses ditolak. Halaman ini khusus untuk Kepala Sekolah.');
        }

    $periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
    if (!$periode_id) {
        $periode_id = check_periode_active($db, false);
    }

    $resolved = resolve_perf_ids($auth, $db);
    $perf_user_id = $resolved['perf_user_id'];
    $penilai_type = 'kepsek';

    // 1. Fetch all targets except Kepala Sekolah (karena Kepsek tidak dinilai)
    $stmtT = $db->prepare("SELECT id, nama, jenis_ptk, mata_pelajaran FROM perf_ptk WHERE status = 1 AND jenis_ptk != 'Kepala Sekolah' ORDER BY jenis_ptk ASC, nama ASC");
    $stmtT->execute();
    $allTargets = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    // Group targets by tupoksi
    $tabs = [];
    $ptkIds = [];
    foreach ($allTargets as $t) {
        $jenis = $t['jenis_ptk'] ?: 'Guru';
        if (!isset($tabs[$jenis])) {
            $tabs[$jenis] = ['teachers' => [], 'questions' => []];
        }
        $tabs[$jenis]['teachers'][] = $t;
        $ptkIds[] = $t['id'];
    }

    // 2. Fetch questions for Kepsek
    $rater_str = 'Kepala Sekolah';
    $rater_fallback = 'Kepala Sekolah';
    $rater_str_self = 'Kepala Sekolah'; // Kepsek doesn't do self/peer in the same way, but just in case.

    foreach (array_keys($tabs) as $jenis) {
        $actual_target_jenis = $jenis;
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
            AND is_manual = 0
            ORDER BY kategori ASC, id ASC
        ");
        $stmtQ->execute([
            $periode_id, 
            $rater_str, $rater_fallback, $actual_target_jenis,
            $rater_str_self, $actual_target_jenis, $rater_str_self,
            $actual_target_jenis, $rater_str_self
        ]);
        $tabs[$jenis]['questions'] = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Fetch all answers submitted by this Kepsek for this periode
    $answers = [];
    $stmtA = $db->prepare("SELECT dinilai_ptk_id, instrumen_id, nilai FROM perf_penilaian WHERE periode_id = ? AND penilai_id = ? AND penilai_type = 'kepsek'");
    $stmtA->execute([$periode_id, $perf_user_id]);
    while ($row = $stmtA->fetch(PDO::FETCH_ASSOC)) {
        $key = $row['instrumen_id'] . '_' . $row['dinilai_ptk_id'];
        $answers[$key] = (int)$row['nilai'];
    }

    json_response(200, true, 'Matrix data', [
        'tabs' => $tabs,
        'answers' => $answers,
        'periode_id' => $periode_id
    ]);
    } catch (Exception $e) {
        json_response(500, false, 'Error Matrix: ' . $e->getMessage() . ' on line ' . $e->getLine());
    }
}

function submitMatrixSingle() {
    $auth = perf_auth_check();
    $db = db();

    $isKepsek = false;
    if (isset($auth['type']) && $auth['type'] === 'perf') {
        if (strtolower($auth['role'] ?? '') === 'kepsek') $isKepsek = true;
    } else {
        if (strtolower(trim($auth['tupoksi'] ?? '')) === 'kepala sekolah') $isKepsek = true;
    }

    if (!$isKepsek) {
        json_response(403, false, 'Akses ditolak.');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['target_id']) || !isset($data['instrumen_id']) || !isset($data['nilai']) || !isset($data['periode_id'])) {
        json_response(400, false, 'Data tidak lengkap.');
    }

    $periode_id = (int)$data['periode_id'];
    
    // Validasi tanggal periode (Kepsek bisa simpan meski periode ditutup, sesuai logic di get_matrix_kepsek)
    check_periode_active($db, false);
    $target_id = (int)$data['target_id'];
    $instrumen_id = (int)$data['instrumen_id'];
    $nilai = (int)$data['nilai'];

    $resolved = resolve_perf_ids($auth, $db);
    $perf_user_id = $resolved['perf_user_id'];
    $penilai_type = 'kepsek';

    try {
        $db->beginTransaction();

        $stmtDel = $db->prepare("DELETE FROM perf_penilaian WHERE periode_id = ? AND penilai_id = ? AND dinilai_ptk_id = ? AND instrumen_id = ?");
        $stmtDel->execute([$periode_id, $perf_user_id, $target_id, $instrumen_id]);

        if ($nilai > 0) {
            $stmtIns = $db->prepare("INSERT INTO perf_penilaian (periode_id, penilai_id, penilai_type, dinilai_ptk_id, instrumen_id, nilai, catatan, created_at) VALUES (?, ?, ?, ?, ?, ?, '', NOW())");
            $stmtIns->execute([$periode_id, $perf_user_id, $penilai_type, $target_id, $instrumen_id, $nilai]);
        }

        $db->commit();
        json_response(200, true, 'Auto-save sukses.');
    } catch (PDOException $e) {
        $db->rollBack();
        json_response(500, false, 'Gagal menyimpan: ' . $e->getMessage());
    }
}

function submitMatrixMassal() {
    $auth = perf_auth_check();
    $db = db();

    $isKepsek = false;
    if (isset($auth['type']) && $auth['type'] === 'perf') {
        if (strtolower($auth['role'] ?? '') === 'kepsek') $isKepsek = true;
    } else {
        if (strtolower(trim($auth['tupoksi'] ?? '')) === 'kepala sekolah') $isKepsek = true;
    }

    if (!$isKepsek) {
        json_response(403, false, 'Akses ditolak.');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['targets']) || !isset($data['instrumen_id']) || !isset($data['nilai']) || !isset($data['periode_id'])) {
        $missing = [];
        if (!$data) $missing[] = 'JSON body';
        if (isset($data) && !isset($data['targets'])) $missing[] = 'targets';
        if (isset($data) && !isset($data['instrumen_id'])) $missing[] = 'instrumen_id';
        if (isset($data) && !isset($data['nilai'])) $missing[] = 'nilai';
        if (isset($data) && !isset($data['periode_id'])) $missing[] = 'periode_id';
        json_response(400, false, 'Data tidak lengkap. Missing: ' . implode(', ', $missing));
    }

    $periode_id = (int)$data['periode_id'];
    
    // Validasi tanggal periode (Kepsek bisa simpan meski periode ditutup, sesuai logic di get_matrix_kepsek)
    check_periode_active($db, false);
    $targets = $data['targets']; // array of target_ids
    $instrumen_id = (int)$data['instrumen_id'];
    $nilai = (int)$data['nilai'];

    if (!is_array($targets) || count($targets) === 0) {
        json_response(400, false, 'Daftar guru tidak valid.');
    }

    $resolved = resolve_perf_ids($auth, $db);
    $perf_user_id = $resolved['perf_user_id'];
    $penilai_type = 'kepsek';

    try {
        $db->beginTransaction();

        $stmtDel = $db->prepare("DELETE FROM perf_penilaian WHERE periode_id = ? AND penilai_id = ? AND dinilai_ptk_id = ? AND instrumen_id = ?");
        $stmtIns = $db->prepare("INSERT INTO perf_penilaian (periode_id, penilai_id, penilai_type, dinilai_ptk_id, instrumen_id, nilai, catatan, created_at) VALUES (?, ?, ?, ?, ?, ?, '', NOW())");
        
        foreach ($targets as $target_id) {
            $target_id = (int)$target_id;
            $stmtDel->execute([$periode_id, $perf_user_id, $target_id, $instrumen_id]);
            $stmtIns->execute([$periode_id, $perf_user_id, $penilai_type, $target_id, $instrumen_id, $nilai]);
        }

        $db->commit();
        json_response(200, true, 'Penilaian masal sukses.');
    } catch (PDOException $e) {
        $db->rollBack();
        json_response(500, false, 'Gagal menyimpan: ' . $e->getMessage());
    }
}
