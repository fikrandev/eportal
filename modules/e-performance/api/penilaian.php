<?php
/**
 * E-Performance — Penilaian API
 * Handle endpoint untuk isi penilaian (Phase 2)
 */
require_once __DIR__ . '/config_perf.php';
require_once __DIR__ . '/scoring_helper.php';

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
    
    $actualTupoksi = !empty($auth['tupoksi']) ? $auth['tupoksi'] : '';
    if ($role !== 'siswa') {
        $stmtTupoksi = $db->prepare("SELECT tupoksi FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmtTupoksi->execute([$auth['username']]);
        $tupoksiRow = $stmtTupoksi->fetch(PDO::FETCH_ASSOC);
        if ($tupoksiRow && !empty($tupoksiRow['tupoksi'])) {
            $actualTupoksi = $tupoksiRow['tupoksi'];
        } elseif ($actualTupoksi === '' && $ptk_id > 0) {
            $stmtPtkTup = $db->prepare("SELECT jenis_ptk FROM perf_ptk WHERE id = ? LIMIT 1");
            $stmtPtkTup->execute([$ptk_id]);
            $ptkJenisVal = $stmtPtkTup->fetchColumn();
            if ($ptkJenisVal) $actualTupoksi = $ptkJenisVal;
        }
        if ($actualTupoksi === '') $actualTupoksi = 'Guru';
    }
    $isKepsekRater = perf_is_kepsek_rater($auth, $actualTupoksi);
    
    $targets = [];

    if ($role === 'siswa') {
        // Ambil ID siswa dari tabel perf_siswa berdasarkan user login
        $stmtSiswa = $db->prepare("SELECT id, kelas, username FROM perf_siswa WHERE perf_user_id = ? LIMIT 1");
        $stmtSiswa->execute([$perf_user_id]);
        $siswa = $stmtSiswa->fetch(PDO::FETCH_ASSOC);
        $siswa_id = $siswa ? (int)$siswa['id'] : 0;
        $siswa_kelas = $siswa ? trim($siswa['kelas']) : '';

        // Jika kelas di perf_siswa kosong, coba cari dari tabel students
        if (!$siswa_kelas && $siswa && !empty($siswa['username'])) {
            $stmtClass = $db->prepare("SELECT kelas FROM students WHERE nis = ? LIMIT 1");
            $stmtClass->execute([$siswa['username']]);
            $siswa_kelas = trim((string)$stmtClass->fetchColumn());
            if ($siswa_kelas) {
                $db->prepare("UPDATE perf_siswa SET kelas = ? WHERE id = ?")->execute([$siswa_kelas, $siswa_id]);
            }
        }

        $stmt = $db->prepare("
            SELECT p.id, p.nama, p.jenis_ptk, p.mata_pelajaran 
            FROM perf_ptk p
            WHERE p.status = 1 
            AND p.id IN (SELECT perf_ptk_id FROM perf_siswa_guru WHERE perf_siswa_id = ? AND periode_id = ?)
            ORDER BY p.jenis_ptk, p.nama ASC
        ");
        $stmt->execute([$siswa_id, $periode_id]);
        $ptks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // AUTO-FALLBACK: Jika belum ada target di perf_siswa_guru tapi siswa punya kelas,
        // otomatis sinkronkan guru pengajar kelasnya dari sch_jadwal secara real-time!
        if (empty($ptks) && $siswa_id && $siswa_kelas) {
            $sqlJadwalSiswa = "
                SELECT DISTINCT 
                    COALESCE(pp.id, pp_by_name.id) as perf_ptk_id
                FROM sch_jadwal sj
                JOIN sch_distribusi sd ON sj.distribusi_id = sd.id
                JOIN sch_kelas sk ON sj.kelas_id = sk.id
                JOIN sch_guru sg ON sd.guru_id = sg.id
                LEFT JOIN perf_ptk pp ON (sg.kode_guru = pp.niy AND pp.niy != '' AND pp.status = 1)
                LEFT JOIN perf_ptk pp_by_name ON (LOWER(TRIM(sg.nama_guru)) = LOWER(TRIM(pp_by_name.nama)) AND pp_by_name.status = 1)
                WHERE sk.nama_kelas = ? AND COALESCE(pp.id, pp_by_name.id) IS NOT NULL
            ";
            $stmtJS = $db->prepare($sqlJadwalSiswa);
            $stmtJS->execute([$siswa_kelas]);
            $guruIds = $stmtJS->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($guruIds)) {
                $stmtIns = $db->prepare("INSERT IGNORE INTO perf_siswa_guru (periode_id, perf_siswa_id, perf_ptk_id) VALUES (?, ?, ?)");
                foreach ($guruIds as $gid) {
                    $stmtIns->execute([$periode_id, $siswa_id, (int)$gid]);
                }

                // Query ulang setelah di-insert
                $stmt->execute([$siswa_id, $periode_id]);
                $ptks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        foreach ($ptks as $p) {
            $targets[] = [
                'target_id' => $p['id'],
                'nama' => $p['nama'],
                'jenis_ptk' => $p['jenis_ptk'],
                'mata_pelajaran' => $p['mata_pelajaran'] ?? null,
                'target_type' => 'Guru/Staf' // Dari sudut pandang siswa, ini Guru/Staf
            ];
        }
    } else if ($isKepsekRater) {
        // Kepala Sekolah menilai SEMUA PTK (selain Kepala Sekolah sendiri)
        $stmt = $db->prepare("SELECT id, nama, jenis_ptk, jabatan, mata_pelajaran FROM perf_ptk WHERE status = 1 AND jenis_ptk != 'Kepala Sekolah' ORDER BY nama ASC");
        $stmt->execute();
        $ptks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ptks as $p) {
            $targets[] = [
                'target_id' => $p['id'],
                'nama' => $p['nama'],
                'jenis_ptk' => $p['jenis_ptk'],
                'jabatan' => $p['jabatan'] ?? '',
                'mata_pelajaran' => $p['mata_pelajaran'] ?? null,
                'target_type' => ($p['id'] == $ptk_id) ? 'Diri Sendiri' : ''
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
                $stmtPeer = $db->prepare("SELECT id, jenis_ptk, status FROM perf_ptk WHERE id = ?");
                $stmtPeer->execute([$peer_id]);
                $peerInfo = $stmtPeer->fetch(PDO::FETCH_ASSOC);
                
                if ($peerInfo) {
                    if ($peerInfo['status'] != 1 || $peer_id == $ptk_id) {
                        $db->prepare("DELETE FROM perf_penugasan_sejawat WHERE periode_id = ? AND penilai_ptk_id = ?")->execute([$periode_id, $ptk_id]);
                        $peer_id = null;
                    } else {
                        $stmtAturanCheck = $db->prepare("SELECT id FROM perf_aturan_sejawat WHERE periode_id = ? AND TRIM(penilai_jenis) = ? AND TRIM(dinilai_jenis) = ?");
                        $stmtAturanCheck->execute([$periode_id, trim($self['jenis_ptk']), trim($peerInfo['jenis_ptk'])]);
                        $aturanValid = $stmtAturanCheck->fetch() !== false;

                        // Validasi tambahan: jika tupoksi sama, tapi jumlahnya kurang dari 2, maka aturan tidak valid (tidak bisa menilai diri sendiri)
                        if ($aturanValid && trim($self['jenis_ptk']) === trim($peerInfo['jenis_ptk'])) {
                            $stmtCount = $db->prepare("SELECT COUNT(*) FROM perf_ptk WHERE status = 1 AND TRIM(jenis_ptk) = ?");
                            $stmtCount->execute([trim($self['jenis_ptk'])]);
                            $tupoksiCount = (int)$stmtCount->fetchColumn();
                            if ($tupoksiCount < 2) {
                                $aturanValid = false;
                            }
                        }

                        if (!$aturanValid) {
                            $db->prepare("DELETE FROM perf_penugasan_sejawat WHERE periode_id = ? AND penilai_ptk_id = ?")->execute([$periode_id, $ptk_id]);
                            $peer_id = null;
                        }
                    }
                } else {
                    $db->prepare("DELETE FROM perf_penugasan_sejawat WHERE periode_id = ? AND penilai_ptk_id = ?")->execute([$periode_id, $ptk_id]);
                    $peer_id = null;
                }
            }
        }
        
        if (!$peer_id) {
            if ($self) {
                // Cek aturan sejawat untuk tupoksi penilai ini
                $stmtAturan = $db->prepare("SELECT dinilai_jenis FROM perf_aturan_sejawat WHERE periode_id = ? AND TRIM(penilai_jenis) = ?");
                $stmtAturan->execute([$periode_id, trim($self['jenis_ptk'])]);
                $aturan = $stmtAturan->fetchAll(PDO::FETCH_COLUMN);

                if (count($aturan) > 0) {
                    $trimmedAturan = array_map('trim', $aturan);
                    // Cek apakah jenis_ptk sendiri ada di dalam aturan
                    if (in_array(trim($self['jenis_ptk']), $trimmedAturan)) {
                        // Hitung jumlah PTK di tupoksi ini
                        $stmtCount = $db->prepare("SELECT COUNT(*) FROM perf_ptk WHERE status = 1 AND TRIM(jenis_ptk) = ?");
                        $stmtCount->execute([trim($self['jenis_ptk'])]);
                        $tupoksiCount = (int)$stmtCount->fetchColumn();

                        // Jika jumlah orang di tupoksinya kurang dari 2, jangan pertemukan sesama
                        if ($tupoksiCount < 2) {
                            $aturan = array_filter($aturan, function($val) use ($self) {
                                return trim($val) !== trim($self['jenis_ptk']);
                            });
                        }
                    }

                    if (count($aturan) > 0) {
                        $inPlaceholders = implode(',', array_fill(0, count($aturan), '?'));
                        // Prioritaskan rekan yang belum banyak dinilai (load balance merata)
                        $sql = "SELECT p.id 
                                FROM perf_ptk p 
                                LEFT JOIN perf_penugasan_sejawat ps ON p.id = ps.dinilai_ptk_id AND ps.periode_id = ?
                                WHERE p.id != ? AND p.status = 1 AND p.jenis_ptk IN ($inPlaceholders) 
                                GROUP BY p.id
                                ORDER BY COUNT(ps.id) ASC, RAND() 
                                LIMIT 1";
                        $stmtRandom = $db->prepare($sql);
                        $params = array_merge([$periode_id, $ptk_id], array_values($aturan));
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
    $allPeriodInstrumen = perfLoadPeriodInstrumen($db, (int)$periode_id);

    foreach ($targets as &$t) {
        if ($role === 'siswa') {
            $raterMode = 'siswa';
        } elseif ($isKepsekRater) {
            $raterMode = 'kepsek';
        } elseif ($ptk_id == $t['target_id']) {
            $raterMode = 'self';
        } else {
            $raterMode = 'peer';
        }

        $matchedQuestions = perfGetInstrumenForTarget(
            $db,
            (int)$periode_id,
            $raterMode,
            ['jenis_ptk' => $t['jenis_ptk'], 'jabatan' => $t['jabatan'] ?? ''],
            $actualTupoksi,
            $allPeriodInstrumen
        );
        $total = count($matchedQuestions);

        // Count answered among matched questions
        $answered = 0;
        if ($total > 0) {
            $qIds = array_map(fn($q) => (int)$q['instrumen_id'], $matchedQuestions);
            $inQ = implode(',', $qIds);
            $stmtAns = $db->prepare("
                SELECT COUNT(DISTINCT instrumen_id) 
                FROM perf_penilaian 
                WHERE periode_id = ? AND penilai_id = ? AND dinilai_ptk_id = ? AND instrumen_id IN ($inQ)
            ");
            $stmtAns->execute([$periode_id, $perf_user_id, $t['target_id']]);
            $answered = (int)$stmtAns->fetchColumn();
        }
        
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
    
    $actualTupoksi = !empty($auth['tupoksi']) ? $auth['tupoksi'] : '';
    if ($role !== 'siswa') {
        $stmtTupoksi = $db->prepare("SELECT tupoksi FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmtTupoksi->execute([$auth['username']]);
        $tupoksiRow = $stmtTupoksi->fetch(PDO::FETCH_ASSOC);
        if ($tupoksiRow && !empty($tupoksiRow['tupoksi'])) {
            $actualTupoksi = $tupoksiRow['tupoksi'];
        } elseif ($actualTupoksi === '' && $ptk_id > 0) {
            $stmtPtkTup = $db->prepare("SELECT jenis_ptk FROM perf_ptk WHERE id = ? LIMIT 1");
            $stmtPtkTup->execute([$ptk_id]);
            $ptkJenisVal = $stmtPtkTup->fetchColumn();
            if ($ptkJenisVal) $actualTupoksi = $ptkJenisVal;
        }
        if ($actualTupoksi === '') $actualTupoksi = 'Guru';
    }
    $isKepsekRater = perf_is_kepsek_rater($auth, $actualTupoksi);
    
    $stmtT = $db->prepare("SELECT jenis_ptk, jabatan FROM perf_ptk WHERE id = ?");
    $stmtT->execute([$target_id]);
    $target_ptk = $stmtT->fetch(PDO::FETCH_ASSOC);

    $penilai_type = 'guru';

    if ($role === 'siswa') {
        $penilai_type = 'siswa';
        $raterMode = 'siswa';
    } elseif ($isKepsekRater) {
        $penilai_type = 'kepsek';
        $raterMode = 'kepsek';
    } else {
        $stmt = $db->prepare("SELECT jenis_ptk FROM perf_ptk WHERE id = ? LIMIT 1");
        $stmt->execute([$ptk_id]);
        $self = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($self) {
            $penilai_type = strtolower($self['jenis_ptk']);
        }
        $raterMode = ($ptk_id == $target_id) ? 'self' : 'peer';
    }

    $questions = perfGetInstrumenForTarget(
        $db,
        (int)$periode_id,
        $raterMode,
        $target_ptk ?: ['jenis_ptk' => $target_jenis ?: 'Guru', 'jabatan' => ''],
        $actualTupoksi
    );

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
    
    $actualTupoksi = !empty($auth['tupoksi']) ? $auth['tupoksi'] : '';
    if ($role !== 'siswa') {
        $stmtTupoksi = $db->prepare("SELECT tupoksi FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmtTupoksi->execute([$auth['username']]);
        $tupoksiRow = $stmtTupoksi->fetch(PDO::FETCH_ASSOC);
        if ($tupoksiRow && !empty($tupoksiRow['tupoksi'])) {
            $actualTupoksi = $tupoksiRow['tupoksi'];
        } elseif ($actualTupoksi === '' && $ptk_id > 0) {
            $stmtPtkTup = $db->prepare("SELECT jenis_ptk FROM perf_ptk WHERE id = ? LIMIT 1");
            $stmtPtkTup->execute([$ptk_id]);
            $ptkJenisVal = $stmtPtkTup->fetchColumn();
            if ($ptkJenisVal) $actualTupoksi = $ptkJenisVal;
        }
        if ($actualTupoksi === '') $actualTupoksi = 'Guru';
    }
    $isKepsekRater = perf_is_kepsek_rater($auth, $actualTupoksi);
    
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
    } else if ($isKepsekRater) {
        $stmt = $db->prepare("SELECT id as target_id, jenis_ptk, jabatan FROM perf_ptk WHERE status = 1 AND jenis_ptk != 'Kepala Sekolah'");
        $stmt->execute();
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtSelf = $db->prepare("SELECT id as target_id, jenis_ptk, jabatan FROM perf_ptk WHERE id = ?");
        $stmtSelf->execute([$ptk_id]);
        if ($selfRow = $stmtSelf->fetch(PDO::FETCH_ASSOC)) {
            $targets[] = $selfRow;
        }
        
        $stmt = $db->prepare("SELECT p.dinilai_ptk_id as target_id, pt.jenis_ptk, pt.jabatan FROM perf_penugasan_sejawat p JOIN perf_ptk pt ON p.dinilai_ptk_id = pt.id WHERE p.penilai_ptk_id = ? AND p.periode_id = ?");
        $stmt->execute([$ptk_id, $periode_id]);
        $peers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($peers as $p) {
            $targets[] = $p;
        }
    }
    
    $total_targets = count($targets);
    $selesai = 0;
    $allPeriodInstrumen = perfLoadPeriodInstrumen($db, (int)$periode_id);

    foreach ($targets as $t) {
        if ($role === 'siswa') {
            $raterMode = 'siswa';
        } elseif ($isKepsekRater) {
            $raterMode = 'kepsek';
        } elseif ($ptk_id == $t['target_id']) {
            $raterMode = 'self';
        } else {
            $raterMode = 'peer';
        }

        $matchedQ = perfGetInstrumenForTarget(
            $db,
            (int)$periode_id,
            $raterMode,
            ['jenis_ptk' => $t['jenis_ptk'] ?? 'Guru', 'jabatan' => $t['jabatan'] ?? ''],
            $actualTupoksi,
            $allPeriodInstrumen
        );
        $total_q = count($matchedQ);

        $answered = 0;
        if ($total_q > 0) {
            $qIds = array_map(fn($q) => (int)$q['instrumen_id'], $matchedQ);
            $inQ = implode(',', $qIds);
            $stmtAns = $db->prepare("SELECT COUNT(DISTINCT instrumen_id) FROM perf_penilaian WHERE periode_id = ? AND penilai_id = ? AND dinilai_ptk_id = ? AND instrumen_id IN ($inQ)");
            $stmtAns->execute([$periode_id, $perf_user_id, $t['target_id']]);
            $answered = (int)$stmtAns->fetchColumn();
        }
        
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
        
        // Strict Kepsek check (Wakil Kepala Sekolah / Kepala TU dsb. ditolak dari matriks Kepsek)
        $isKepsek = perf_is_kepsek_rater($auth);

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
    $stmtT = $db->prepare("SELECT id, niy, nama, jenis_ptk, jabatan, mata_pelajaran FROM perf_ptk WHERE status = 1 AND jenis_ptk != 'Kepala Sekolah' ORDER BY jenis_ptk ASC, nama ASC");
    $stmtT->execute();
    $allTargets = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    // Group targets by tupoksi
    $rawGroups = [];
    foreach ($allTargets as $t) {
        $jenis = $t['jenis_ptk'] ?: 'Guru';
        if (!isset($rawGroups[$jenis])) {
            $rawGroups[$jenis] = [];
        }
        $rawGroups[$jenis][] = $t;
    }

    // 2. Fetch questions for Kepsek (with automatic inheritance of Guru competencies for Wakil Kepala Sekolah)
    $allPeriodInstrumen = perfLoadPeriodInstrumen($db, (int)$periode_id);
    $tabs = [];

    foreach ($rawGroups as $jenis => $teachers) {
        $samplePtk = $teachers[0];
        $flags = getPtkRoleFlags($samplePtk);
        $allQuestions = perfGetInstrumenForTarget($db, (int)$periode_id, 'kepsek', $samplePtk, 'Kepala Sekolah', $allPeriodInstrumen);

        $mainQuestions = [];
        $tambahanQuestions = [];
        foreach ($allQuestions as $q) {
            $cleanQ = [
                'instrumen_id' => $q['instrumen_id'],
                'kategori'     => $q['norm_kategori'] ?: $q['kategori'],
                'pertanyaan'   => $q['pertanyaan'],
            ];
            if (!empty($q['is_tambahan'])) {
                $tambahanQuestions[] = $cleanQ;
            } else {
                $mainQuestions[] = $cleanQ;
            }
        }

        // Jika memiliki soal utama (Sebagai Guru / Staf) SEKALIGUS soal Tugas Tambahan (seperti Wakil Kepala Sekolah / Kepala TU),
        // pisahkan menjadi 2 tab yang jelas agar Kepala Sekolah tidak melewatkan penilaian sebagai Guru!
        if (!empty($mainQuestions) && !empty($tambahanQuestions)) {
            $roleLabel = $flags['is_staff'] ? 'Sebagai Staf' : 'Sebagai Guru';
            $tabs["{$jenis} ({$roleLabel})"] = [
                'teachers'  => $teachers,
                'questions' => $mainQuestions,
            ];
            $tabs["{$jenis} (Tugas Tambahan)"] = [
                'teachers'  => $teachers,
                'questions' => $tambahanQuestions,
            ];
        } else {
            $tabs[$jenis] = [
                'teachers'  => $teachers,
                'questions' => !empty($mainQuestions) ? $mainQuestions : $tambahanQuestions,
            ];
        }
    }

    // 3. Fetch all answers submitted by Kepsek for this periode
    $answers = [];
    $stmtA = $db->prepare("SELECT dinilai_ptk_id, instrumen_id, nilai FROM perf_penilaian WHERE periode_id = ? AND (penilai_id = ? OR penilai_type = 'kepsek')");
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

    $isKepsek = perf_is_kepsek_rater($auth);

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

    $isKepsek = perf_is_kepsek_rater($auth);

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
