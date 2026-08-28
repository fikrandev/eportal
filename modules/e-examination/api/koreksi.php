<?php
/**
 * E-Examination — API Koreksi (Otomatis & AI)
 */
require_once __DIR__ . '/config_exam.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

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
        SELECT j.id as jawaban_id, j.jawaban, j.jawaban_voice, s.id as soal_id, s.tipe_soal, s.bobot, s.kunci_jawaban, s.pertanyaan, s.opsi
        FROM exam_jawaban j
        JOIN exam_soal s ON j.soal_id = s.id
        WHERE j.sesi_id = ?
    ");
    $stmtAns->execute([$session_id]);
    $answers = $stmtAns->fetchAll();

    $totalSkor = 0;
    $maxSkor = 0;
    $aiTasks = []; // Menyimpan soal esai yang perlu dikoreksi AI

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
                if ($jawabanSiswa !== null && trim($jawabanSiswa) === trim($kunci)) {
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
            else if ($tipe === 'jawaban_singkat' || $tipe === 'essai') {
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

    // Simpan ke sesi
    db()->prepare("UPDATE exam_sesi SET nilai_akhir = ? WHERE id = ?")->execute([$skorAkhir, $session_id]);
    
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
