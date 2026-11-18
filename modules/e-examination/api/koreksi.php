<?php
/**
 * E-Examination — API Koreksi (Otomatis & AI)
 */
require_once __DIR__ . '/config_exam.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!empty($action)) {
    try {
        switch ($action) {
        
        // ==========================================
        // KOREKSI SATU SESI UJIAN
        // ==========================================
        case 'koreksi_sesi':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $session_id = (int)($data['session_id'] ?? 0);
            if (!$session_id) throw new Exception('Session ID tidak valid', 400);

            $result = processGrading($session_id);
            json_response(200, true, 'Koreksi berhasil', $result);
            break;

        // ==========================================
        // KOREKSI MASAL SEMUA SESI SELESAI PADA UJIAN
        // ==========================================
        case 'koreksi_masal':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $ujian_id = (int)($data['ujian_id'] ?? 0);
            if (!$ujian_id) throw new Exception('Ujian ID tidak valid', 400);

            // Cari semua sesi selesai yang belum di-koreksi sepenuhnya atau butuh update
            $stmt = db()->prepare("SELECT id FROM exam_sesi WHERE ujian_id = ? AND status IN ('selesai', 'dihentikan')");
            $stmt->execute([$ujian_id]);
            $sessions = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $graded = 0;
            $errors = [];

            foreach ($sessions as $sid) {
                try {
                    processGrading($sid);
                    $graded++;
                } catch (Exception $e) {
                    $errors[] = "Session $sid: " . $e->getMessage();
                }
            }

            json_response(200, true, "$graded sesi berhasil dikoreksi", ['errors' => $errors]);
            break;

        // ==========================================
        // AMBIL DETAIL JAWABAN SISWA UNTUK KOREKSI MANUAL
        // ==========================================
        case 'get_koreksi_siswa':
            exam_require_admin_or_guru();
            $session_id = (int)($_GET['sesi_id'] ?? 0);
            if (!$session_id) throw new Exception('Sesi ID tidak valid', 400);

            // Detail sesi, ujian, dan siswa
            $stmtSesi = db()->prepare("
                SELECT s.*, 
                       u.judul as judul_ujian, u.jenis, u.durasi_menit, u.bank_soal_id,
                       st.nis, st.nama as nama_siswa, st.kelas,
                       u_guru.nama_lengkap as nama_korektor
                FROM exam_sesi s
                JOIN exam_ujian u ON s.ujian_id = u.id
                JOIN students st ON s.student_id = st.id
                LEFT JOIN users u_guru ON s.dikoreksi_oleh = u_guru.id
                WHERE s.id = ?
            ");
            $stmtSesi->execute([$session_id]);
            $sesi = $stmtSesi->fetch(PDO::FETCH_ASSOC);
            if (!$sesi) throw new Exception('Sesi ujian tidak ditemukan', 404);

            // Ambil semua jawaban dan soal untuk sesi ini
            $stmtAnswers = db()->prepare("
                SELECT j.id as jawaban_id, j.soal_id, j.urutan as urutan_soal,
                       j.jawaban, j.jawaban_voice, j.skor, j.catatan_guru, j.is_koreksi_manual, j.ai_feedback,
                       s.tipe_soal, s.pertanyaan, s.opsi, s.kunci_jawaban, s.pembahasan, s.bobot, s.gambar, s.audio
                FROM exam_jawaban j
                JOIN exam_soal s ON j.soal_id = s.id
                WHERE j.sesi_id = ?
                ORDER BY j.urutan ASC, s.id ASC
            ");
            $stmtAnswers->execute([$session_id]);
            $answers = $stmtAnswers->fetchAll(PDO::FETCH_ASSOC);

            $soalUraian = [];
            $soalPg = [];
            $totalSkorPg = 0;
            $maxSkorPg = 0;
            $totalSkorUraian = 0;
            $maxSkorUraian = 0;
            $totalBobot = 0;
            $totalSkorRaw = 0;

            foreach ($answers as $ans) {
                $bobot = (float)$ans['bobot'];
                $skor = $ans['skor'] !== null ? (float)$ans['skor'] : 0;
                $totalBobot += $bobot;
                $totalSkorRaw += $skor;

                $isUraian = in_array($ans['tipe_soal'], ['esai', 'essai', 'jawaban_singkat']);
                if ($isUraian) {
                    $maxSkorUraian += $bobot;
                    $totalSkorUraian += $skor;
                    $soalUraian[] = [
                        'jawaban_id'        => (int)$ans['jawaban_id'],
                        'soal_id'           => (int)$ans['soal_id'],
                        'urutan'            => (int)$ans['urutan_soal'],
                        'tipe_soal'         => $ans['tipe_soal'],
                        'pertanyaan'        => $ans['pertanyaan'],
                        'opsi'              => $ans['opsi'],
                        'kunci_jawaban'     => $ans['kunci_jawaban'],
                        'pembahasan'        => $ans['pembahasan'],
                        'bobot'             => $bobot,
                        'gambar'            => $ans['gambar'],
                        'audio'             => $ans['audio'],
                        'jawaban'           => $ans['jawaban'],
                        'jawaban_voice'     => $ans['jawaban_voice'],
                        'skor'              => $ans['skor'] !== null ? (float)$ans['skor'] : null,
                        'catatan_guru'      => $ans['catatan_guru'] ?? '',
                        'is_koreksi_manual' => (int)($ans['is_koreksi_manual'] ?? 0),
                        'ai_feedback'       => $ans['ai_feedback'] ?? ''
                    ];
                } else {
                    $maxSkorPg += $bobot;
                    $totalSkorPg += $skor;
                    $soalPg[] = [
                        'jawaban_id'        => (int)$ans['jawaban_id'],
                        'soal_id'           => (int)$ans['soal_id'],
                        'urutan'            => (int)$ans['urutan_soal'],
                        'tipe_soal'         => $ans['tipe_soal'],
                        'pertanyaan'        => $ans['pertanyaan'],
                        'opsi'              => $ans['opsi'],
                        'kunci_jawaban'     => $ans['kunci_jawaban'],
                        'bobot'             => $bobot,
                        'jawaban'           => $ans['jawaban'],
                        'skor'              => $skor
                    ];
                }
            }

            json_response(200, true, 'Detail koreksi siswa', [
                'sesi' => [
                    'id'               => (int)$sesi['id'],
                    'ujian_id'         => (int)$sesi['ujian_id'],
                    'judul_ujian'      => $sesi['judul_ujian'],
                    'jenis'            => $sesi['jenis'],
                    'student_id'       => (int)$sesi['student_id'],
                    'nis'              => $sesi['nis'],
                    'nama_siswa'       => $sesi['nama_siswa'],
                    'kelas'            => $sesi['kelas'],
                    'status'           => $sesi['status'],
                    'waktu_mulai'      => $sesi['waktu_mulai'],
                    'waktu_selesai'    => $sesi['waktu_selesai'],
                    'nilai_akhir'      => $sesi['nilai_akhir'] !== null ? round((float)$sesi['nilai_akhir'], 0) : null,
                    'status_koreksi'   => $sesi['status_koreksi'] ?? 'belum_dikoreksi',
                    'nama_korektor'    => $sesi['nama_korektor'] ?? null,
                    'dikoreksi_pada'   => $sesi['dikoreksi_pada'] ?? null,
                ],
                'summary' => [
                    'count_uraian'     => count($soalUraian),
                    'count_pg'         => count($soalPg),
                    'total_soal'       => count($answers),
                    'total_bobot'      => $totalBobot,
                    'total_skor_raw'   => $totalSkorRaw,
                    'skor_pg'          => $totalSkorPg,
                    'max_skor_pg'      => $maxSkorPg,
                    'skor_uraian'      => $totalSkorUraian,
                    'max_skor_uraian'  => $maxSkorUraian
                ],
                'soal_uraian' => $soalUraian,
                'soal_pg'     => $soalPg
            ]);
            break;

        // ==========================================
        // SIMPAN KOREKSI MANUAL OLEH GURU
        // ==========================================
        case 'simpan_koreksi_manual':
            $auth = exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);

            $data = json_decode(file_get_contents('php://input'), true);
            $session_id = (int)($data['sesi_id'] ?? 0);
            $scores = $data['scores'] ?? [];

            if (!$session_id) throw new Exception('Sesi ID tidak valid', 400);
            if (!is_array($scores)) throw new Exception('Data nilai tidak valid', 400);

            // Pastikan sesi ada
            $stmtSesi = db()->prepare("SELECT s.*, u.jenis FROM exam_sesi s JOIN exam_ujian u ON s.ujian_id = u.id WHERE s.id = ?");
            $stmtSesi->execute([$session_id]);
            $sesi = $stmtSesi->fetch(PDO::FETCH_ASSOC);
            if (!$sesi) throw new Exception('Sesi ujian tidak ditemukan', 404);

            db()->beginTransaction();

            $stmtUpdateAns = db()->prepare("
                UPDATE exam_jawaban 
                SET skor = ?, catatan_guru = ?, is_koreksi_manual = 1, updated_at = NOW() 
                WHERE id = ? AND sesi_id = ?
            ");

            // Ambil validasi bobot dari soal
            $stmtCheckSoal = db()->prepare("
                SELECT j.id, s.bobot, s.tipe_soal 
                FROM exam_jawaban j 
                JOIN exam_soal s ON j.soal_id = s.id 
                WHERE j.id = ? AND j.sesi_id = ?
            ");

            foreach ($scores as $sc) {
                $jawaban_id = (int)($sc['jawaban_id'] ?? 0);
                if (!$jawaban_id) continue;

                $stmtCheckSoal->execute([$jawaban_id, $session_id]);
                $itemInfo = $stmtCheckSoal->fetch(PDO::FETCH_ASSOC);
                if (!$itemInfo) continue;

                $maxBobot = (float)$itemInfo['bobot'];
                $valSkor = (float)($sc['skor'] ?? 0);
                if ($valSkor < 0) $valSkor = 0;
                if ($valSkor > $maxBobot) $valSkor = $maxBobot; // Batasi maksimal sebesar bobot soal

                $catatan = trim($sc['catatan_guru'] ?? ($sc['catatan'] ?? ''));

                $stmtUpdateAns->execute([$valSkor, $catatan ?: null, $jawaban_id, $session_id]);
            }

            // Hitung ulang total skor seluruh sesi
            $stmtTotal = db()->prepare("
                SELECT SUM(j.skor) as total_skor, SUM(s.bobot) as max_skor
                FROM exam_jawaban j
                JOIN exam_soal s ON j.soal_id = s.id
                WHERE j.sesi_id = ?
            ");
            $stmtTotal->execute([$session_id]);
            $tot = $stmtTotal->fetch(PDO::FETCH_ASSOC);

            $totalSkor = (float)($tot['total_skor'] ?? 0);
            $maxSkor = (float)($tot['max_skor'] ?? 0);

            $isPsikologi = ($sesi['jenis'] === 'psikologi');
            if ($isPsikologi) {
                $skorAkhir = round($totalSkor, 0);
            } else {
                $skorAkhir = $maxSkor > 0 ? ($totalSkor / $maxSkor) * 100 : 0;
                $skorAkhir = round($skorAkhir, 0);
            }

            // Update sesi: status_koreksi = 'sudah_dikoreksi', dikoreksi_oleh, dikoreksi_pada
            $stmtUpdateSesi = db()->prepare("
                UPDATE exam_sesi 
                SET nilai_akhir = ?, 
                    status_koreksi = 'sudah_dikoreksi', 
                    dikoreksi_oleh = ?, 
                    dikoreksi_pada = NOW() 
                WHERE id = ?
            ");
            $stmtUpdateSesi->execute([$skorAkhir, $auth['user_id'], $session_id]);

            db()->commit();

            json_response(200, true, 'Koreksi manual berhasil disimpan', [
                'sesi_id'        => $session_id,
                'total_skor'     => $totalSkor,
                'max_skor'       => $maxSkor,
                'nilai_akhir'    => $skorAkhir,
                'status_koreksi' => 'sudah_dikoreksi',
                'dikoreksi_oleh' => $auth['nama_lengkap'] ?? $auth['username'],
                'dikoreksi_pada' => date('Y-m-d H:i:s')
            ]);
            break;

        // ==========================================
        // SETTING GEMINI API KEY
        // ==========================================
        case 'get_settings':
            exam_require_admin();
            $key = get_setting('gemini_api_key', '');
            json_response(200, true, 'Setting', ['gemini_api_key' => $key ? '********' : '']);
            break;

        case 'save_settings':
            exam_require_admin();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $key = trim($data['gemini_api_key'] ?? '');
            
            if ($key && $key !== '********') {
                upsert_setting('gemini_api_key', $key, 'text', 'API Key untuk Google Gemini AI Grading');
            }
            json_response(200, true, 'Pengaturan AI berhasil disimpan');
            break;

        default:
            throw new Exception('Action tidak valid', 400);
    }
    } catch (Exception $e) {
        $code = $e->getCode() ?: 500;
        if ($code < 100 || $code >= 600) $code = 500;
        json_response($code, false, $e->getMessage());
    }
}

/**
 * Fungsi Inti untuk Memproses Penilaian Sesi Ujian
 */
function processGrading($session_id) {
    // Ambil detail sesi
    $stmtSesi = db()->prepare("SELECT s.*, u.judul, u.jenis FROM exam_sesi s JOIN exam_ujian u ON s.ujian_id = u.id WHERE s.id = ?");
    $stmtSesi->execute([$session_id]);
    $sesi = $stmtSesi->fetch();

    if (!$sesi) throw new Exception("Sesi $session_id tidak ditemukan");

    $isPsikologi = ($sesi['jenis'] === 'psikologi');

    // Ambil jawaban dan kunci
    $stmtAns = db()->prepare("
        SELECT j.id as jawaban_id, j.jawaban, j.jawaban_voice, j.skor as current_skor, j.is_koreksi_manual, j.catatan_guru,
               s.id as soal_id, s.tipe_soal, s.bobot, s.kunci_jawaban, s.pertanyaan, s.opsi
        FROM exam_jawaban j
        JOIN exam_soal s ON j.soal_id = s.id
        WHERE j.sesi_id = ?
    ");
    $stmtAns->execute([$session_id]);
    $answers = $stmtAns->fetchAll();

    $totalSkor = 0;
    $maxSkor = 0;
    $aiTasks = []; // Menyimpan soal esai yang perlu dikoreksi AI
    $countUraian = 0;
    $countUraianManual = 0;

    // Mulai transaksi untuk menyimpan nilai detail
    db()->beginTransaction();

    $stmtUpdateAns = db()->prepare("UPDATE exam_jawaban SET skor = ?, ai_feedback = ? WHERE id = ?");

    foreach ($answers as $ans) {
        $bobot = (float)$ans['bobot'];
        $maxSkor += $bobot;
        
        $tipe = $ans['tipe_soal'];
        $kunci = $ans['kunci_jawaban'];
        $jawabanSiswa = $ans['jawaban'];
        $jawabanVoice = $ans['jawaban_voice'];
        
        $isUraian = in_array($tipe, ['esai', 'essai', 'jawaban_singkat']);
        if ($isUraian) {
            $countUraian++;
        }

        // Jika soal ini sudah dikoreksi manual oleh guru, pertahankan nilainya!
        if (!empty($ans['is_koreksi_manual'])) {
            $totalSkor += (float)($ans['current_skor'] ?? 0);
            if ($isUraian) {
                $countUraianManual++;
            }
            continue;
        }
        
        $skorDidapat = 0;
        $aiKoreksiNotes = null;

        if ($isPsikologi) {
            // Psychology scoring: sum weights of selected options
            $opsiList = json_decode($ans['opsi'], true) ?: [];
            $scoreForOption = 0;
            foreach ($opsiList as $opt) {
                if (isset($opt['label']) && $opt['label'] === $jawabanSiswa) {
                    $scoreForOption = (float)($opt['score'] ?? 0);
                    break;
                }
            }
            $skorDidapat = $scoreForOption;
        } else {
            // Assessment scoring
            if ($tipe === 'pilihan_satu' || $tipe === 'benar_salah') {
                $decodedKunci = json_decode($kunci, true);
                $expected = ($decodedKunci !== null && is_string($decodedKunci)) ? $decodedKunci : trim($kunci ?? '', "\"' \t\n\r");
                $actual = trim($jawabanSiswa ?? '', "\"' \t\n\r");
                if ($jawabanSiswa !== null && ($actual === $expected || trim($jawabanSiswa) === trim($kunci ?? ''))) {
                    $skorDidapat = $bobot;
                }
            } 
            else if ($tipe === 'pilihan_banyak') {
                $jawabanArr = json_decode($jawabanSiswa, true) ?: [];
                $opsiList = json_decode($ans['opsi'], true) ?: [];
                
                // Cek apakah opsi punya field "score" (format baru)
                $hasCustomScore = false;
                $scoreMap = [];
                foreach ($opsiList as $opt) {
                    if (array_key_exists('score', $opt)) {
                        $hasCustomScore = true;
                    }
                    if (isset($opt['label'])) {
                        $scoreMap[$opt['label']] = isset($opt['score']) ? (float)$opt['score'] : 0;
                    }
                }

                if ($hasCustomScore) {
                    // Format baru: tambahkan skor dari masing-masing opsi yang dipilih
                    if (is_array($jawabanArr)) {
                        foreach ($jawabanArr as $jawab) {
                            if (isset($scoreMap[$jawab])) {
                                $skorDidapat += $scoreMap[$jawab];
                            }
                        }
                    }
                } else {
                    // Format lama (Proporsional berdasarkan kunci_jawaban)
                    $kunciArr = json_decode($kunci, true) ?: [];
                    if (is_array($jawabanArr) && is_array($kunciArr)) {
                        sort($jawabanArr);
                        sort($kunciArr);
                        if ($jawabanArr == $kunciArr) {
                            $skorDidapat = $bobot;
                        } else {
                            $correctPicks = count(array_intersect($jawabanArr, $kunciArr));
                            $wrongPicks = count(array_diff($jawabanArr, $kunciArr));
                            $net = $correctPicks - $wrongPicks;
                            if ($net > 0 && count($kunciArr) > 0) {
                                $skorDidapat = ($net / count($kunciArr)) * $bobot;
                            }
                        }
                    }
                }
            }
            else if ($tipe === 'menjodohkan') {
                $jawabanObj = json_decode($jawabanSiswa, true) ?: [];
                $opsiList = json_decode($ans['opsi'], true) ?: [];
                $kunciObj = json_decode($kunci, true) ?: [];
                
                // Cek apakah opsi punya field "score"
                $hasCustomScore = false;
                foreach ($opsiList as $opt) {
                    if (array_key_exists('score', $opt)) {
                        $hasCustomScore = true;
                        break;
                    }
                }

                if (is_array($jawabanObj)) {
                    if ($hasCustomScore) {
                        // Format Baru: Cek terhadap opsiList untuk mengambil skor
                        foreach ($opsiList as $pair) {
                            $kiri = $pair['left'] ?? '';
                            $kanan = $pair['right'] ?? '';
                            $skorKiri = $pair['score'] ?? 0;
                            
                            if (isset($jawabanObj[$kiri]) && $jawabanObj[$kiri] === $kanan) {
                                $skorDidapat += (float)$skorKiri;
                            }
                        }
                    } else {
                        // Format Lama:
                        if (is_array($kunciObj)) {
                            // Cek struktur lama: jika kunci_jawaban adalah array of objects (hasil update baru) 
                            // atau array assoc (format lama banget)
                            $totalPairs = 0;
                            $correctPairs = 0;
                            
                            // Jika format array of objects: [{left:'', right:''}]
                            if (isset($kunciObj[0]) && is_array($kunciObj[0])) {
                                $totalPairs = count($kunciObj);
                                foreach ($kunciObj as $pair) {
                                    $kiri = $pair['left'] ?? '';
                                    $kanan = $pair['right'] ?? '';
                                    if (isset($jawabanObj[$kiri]) && $jawabanObj[$kiri] === $kanan) {
                                        $correctPairs++;
                                    }
                                }
                            } else {
                                // Jika format assoc: {"A":"B"}
                                $totalPairs = count($kunciObj);
                                foreach ($kunciObj as $k => $v) {
                                    if (isset($jawabanObj[$k]) && $jawabanObj[$k] === $v) {
                                        $correctPairs++;
                                    }
                                }
                            }
                            
                            if ($totalPairs > 0) {
                                $skorDidapat = ($correctPairs / $totalPairs) * $bobot;
                            }
                        }
                    }
                }
            }
            else if (in_array($tipe, ['esai', 'essai', 'jawaban_singkat'])) {
                // Queue for AI Grading if student answered (via voice or text)
                $hasText = ($jawabanSiswa && trim($jawabanSiswa) !== '');
                $hasVoice = ($jawabanVoice && trim($jawabanVoice) !== '');
                
                if ($hasText || $hasVoice) {
                    $aiTasks[] = [
                        'jawaban_id' => $ans['jawaban_id'],
                        'pertanyaan' => $ans['pertanyaan'],
                        'kunci' => $kunci,
                        'jawaban' => $jawabanSiswa,
                        'jawaban_voice' => $jawabanVoice,
                        'bobot' => $bobot
                    ];
                    // Skor sementara 0 sampai AI memproses
                }
            }
        }

        $totalSkor += $skorDidapat;
        $stmtUpdateAns->execute([$skorDidapat, $aiKoreksiNotes, $ans['jawaban_id']]);
    }

    // Eksekusi AI Grading jika ada task
    if (!empty($aiTasks)) {
        $aiResult = runAiGrading($aiTasks);
        foreach ($aiResult as $res) {
            $totalSkor += $res['skor'];
            $stmtUpdateAns->execute([$res['skor'], $res['catatan'], $res['jawaban_id']]);
        }
    }

    // Normalisasi Skor ke skala 100 (jika maxSkor bukan 100 dan BUKAN tes psikologi)
    $skorAkhir = $totalSkor;
    if (!$isPsikologi) {
        $skorAkhir = 0;
        if ($maxSkor > 0) {
            $skorAkhir = ($totalSkor / $maxSkor) * 100;
        }
    }
    
    // Bulatkan agar selalu angka bulat sesuai permintaan (tidak ada desimal)
    $skorAkhir = round($skorAkhir, 0);

    // Tentukan status koreksi:
    // Jika ada soal uraian dan belum semua dikoreksi manual oleh guru, maka 'belum_dikoreksi'
    $statusKoreksi = 'sudah_dikoreksi';
    if ($countUraian > 0 && $countUraianManual < $countUraian) {
        $statusKoreksi = 'belum_dikoreksi';
    }

    // Simpan ke sesi
    db()->prepare("UPDATE exam_sesi SET nilai_akhir = ?, status_koreksi = ? WHERE id = ?")->execute([$skorAkhir, $statusKoreksi, $session_id]);
    
    db()->commit();

    return [
        'session_id' => $session_id,
        'total_skor_asli' => $totalSkor,
        'max_skor' => $maxSkor,
        'skor_skala_100' => $skorAkhir,
        'ai_graded_count' => count($aiTasks)
    ];
}

/**
 * Menghubungi Google Gemini API untuk grading otomatis
 */
function runAiGrading($tasks) {
    $apiKey = get_setting('gemini_api_key', '');
    if (!$apiKey) {
        // Jika tidak ada API key, berikan skor 0 dan catatan error
        $fallback = [];
        foreach ($tasks as $t) {
            $fallback[] = [
                'jawaban_id' => $t['jawaban_id'],
                'skor' => 0,
                'catatan' => 'AI Grading gagal: API Key belum dikonfigurasi.'
            ];
        }
        return $fallback;
    }

    $results = [];
    
    foreach ($tasks as $t) {
        $promptText = "Anda adalah Guru Ahli yang bertugas memberikan nilai untuk soal esai dan jawaban singkat siswa.\n";
        $promptText .= "Berikan penilaian yang adil dan objektif berdasarkan rubrik/kunci jawaban dan jawaban yang diberikan siswa.\n\n";
        $promptText .= "Data Soal & Jawaban:\n";
        $promptText .= "Pertanyaan: " . strip_tags($t['pertanyaan']) . "\n";
        $promptText .= "Kunci/Rubrik: " . $t['kunci'] . "\n";
        $promptText .= "Jawaban Siswa (Teks): " . ($t['jawaban'] ?: '(Tidak ada jawaban teks)') . "\n";
        $promptText .= "Skor Maksimal: " . $t['bobot'] . "\n\n";
        
        $promptText .= "Jika siswa melampirkan rekaman suara (audio), dengarkan rekaman audio yang disertakan dan gunakan isinya untuk menilai jawaban mereka.\n";
        $promptText .= "Berikan output HANYA dalam format JSON objek (tanpa format markdown, tanpa ```json) dengan skema berikut:\n";
        $promptText .= '{"skor": <angka_desimal_dari_0_sampai_skor_maksimal>, "catatan": "<alasan_singkat_kenapa_dapat_skor_tersebut>"}' . "\n";

        // Build Gemini Parts
        $parts = [];
        
        // Add Audio if present
        if (!empty($t['jawaban_voice'])) {
            $audioPath = __DIR__ . '/../' . $t['jawaban_voice'];
            if (file_exists($audioPath)) {
                $ext = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
                $mimeType = 'audio/webm';
                if ($ext === 'mp3') $mimeType = 'audio/mp3';
                elseif ($ext === 'wav') $mimeType = 'audio/wav';
                elseif ($ext === 'm4a') $mimeType = 'audio/m4a';
                elseif ($ext === 'ogg') $mimeType = 'audio/ogg';

                $parts[] = [
                    "inlineData" => [
                        "mimeType" => $mimeType,
                        "data" => base64_encode(file_get_contents($audioPath))
                    ]
                ];
            }
        }

        // Add Text Prompt Part
        $parts[] = ["text" => $promptText];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
        
        $payload = [
            "contents" => [
                ["parts" => $parts]
            ],
            "generationConfig" => [
                "temperature" => 0.1,
                "responseMimeType" => "application/json"
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $gradeInfo = null;

        if ($httpCode == 200) {
            $respData = json_decode($response, true);
            if (isset($respData['candidates'][0]['content']['parts'][0]['text'])) {
                $jsonStr = $respData['candidates'][0]['content']['parts'][0]['text'];
                $jsonStr = preg_replace('/```json/i', '', $jsonStr);
                $jsonStr = preg_replace('/```/i', '', $jsonStr);
                
                $gradeInfo = json_decode(trim($jsonStr), true);
            }
        }

        if (is_array($gradeInfo) && isset($gradeInfo['skor'])) {
            $results[] = [
                'jawaban_id' => $t['jawaban_id'],
                'skor' => (float)$gradeInfo['skor'],
                'catatan' => $gradeInfo['catatan'] ?? 'Dinilai oleh AI'
            ];
        } else {
            // Fallback
            $results[] = [
                'jawaban_id' => $t['jawaban_id'],
                'skor' => 0,
                'catatan' => 'AI Grading gagal: Gagal mem-parsing hasil dari Google Gemini API (Code: '.$httpCode.').'
            ];
        }
    }

    return $results;
}
