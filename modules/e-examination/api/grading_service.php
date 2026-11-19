<?php
/**
 * E-Examination — Grading Service (Objective, Psychology & Gemini AI Grading)
 * Pure library file (no direct routing side-effects) so pengerjaan.php and koreksi.php can include it safely.
 */

if (!function_exists('processGrading')) {
    /**
     * Memproses penilaian sesi ujian secara menyeluruh tanpa menahan transaksi DB saat memanggil API eksternal.
     */
    function processGrading($session_id) {
        $session_id = (int)$session_id;
        $stmtSesi = db()->prepare("SELECT s.*, u.judul, u.jenis FROM exam_sesi s JOIN exam_ujian u ON s.ujian_id = u.id WHERE s.id = ?");
        $stmtSesi->execute([$session_id]);
        $sesi = $stmtSesi->fetch(PDO::FETCH_ASSOC);

        if (!$sesi) {
            throw new Exception("Sesi $session_id tidak ditemukan", 404);
        }

        $isPsikologi = ($sesi['jenis'] === 'psikologi');

        $stmtAns = db()->prepare("
            SELECT j.id as jawaban_id, j.jawaban, j.jawaban_voice, j.skor as current_skor, j.is_koreksi_manual, j.catatan_guru,
                   s.id as soal_id, s.tipe_soal, s.bobot, s.kunci_jawaban, s.pertanyaan, s.opsi
            FROM exam_jawaban j
            JOIN exam_soal s ON j.soal_id = s.id
            WHERE j.sesi_id = ?
        ");
        $stmtAns->execute([$session_id]);
        $answers = $stmtAns->fetchAll(PDO::FETCH_ASSOC);

        $totalSkor = 0.0;
        $maxSkor = 0.0;
        $aiTasks = [];
        $objectiveUpdates = [];
        $countUraian = 0;
        $countUraianManual = 0;

        foreach ($answers as $ans) {
            $bobot = max(0.0, (float)$ans['bobot']);
            $maxSkor += $bobot;

            $tipe = $ans['tipe_soal'];
            $kunci = $ans['kunci_jawaban'];
            $jawabanSiswa = $ans['jawaban'];
            $jawabanVoice = $ans['jawaban_voice'];

            $isUraian = in_array($tipe, ['esai', 'essai', 'jawaban_singkat'], true);
            if ($isUraian) {
                $countUraian++;
            }

            // Pertahankan nilai jika sudah dikoreksi manual oleh guru
            if (!empty($ans['is_koreksi_manual'])) {
                $totalSkor += max(0.0, (float)($ans['current_skor'] ?? 0));
                if ($isUraian) {
                    $countUraianManual++;
                }
                continue;
            }

            $skorDidapat = 0.0;
            $aiKoreksiNotes = null;

            if ($isPsikologi) {
                $opsiList = json_decode((string)$ans['opsi'], true) ?: [];
                $scoreForOption = 0.0;
                foreach ($opsiList as $opt) {
                    if (isset($opt['label']) && $opt['label'] === $jawabanSiswa) {
                        $scoreForOption = max(0.0, (float)($opt['score'] ?? 0));
                        break;
                    }
                }
                $skorDidapat = $scoreForOption;
            } else {
                if ($tipe === 'pilihan_satu' || $tipe === 'benar_salah') {
                    $decodedKunci = json_decode((string)$kunci, true);
                    $expected = ($decodedKunci !== null && is_string($decodedKunci))
                        ? trim($decodedKunci, "\"' \t\n\r")
                        : trim((string)($kunci ?? ''), "\"' \t\n\r");
                    $actual = trim((string)($jawabanSiswa ?? ''), "\"' \t\n\r");
                    if ($jawabanSiswa !== null && $actual !== '' && $expected !== '' && ($actual === $expected || trim((string)$jawabanSiswa) === trim((string)($kunci ?? '')))) {
                        $skorDidapat = $bobot;
                    }
                } elseif ($tipe === 'pilihan_banyak') {
                    $jawabanArr = json_decode((string)$jawabanSiswa, true) ?: [];
                    $opsiList = json_decode((string)$ans['opsi'], true) ?: [];

                    $hasCustomScore = false;
                    $scoreMap = [];
                    foreach ($opsiList as $opt) {
                        if (is_array($opt) && array_key_exists('score', $opt)) {
                            $hasCustomScore = true;
                        }
                        if (is_array($opt) && isset($opt['label'])) {
                            $scoreMap[$opt['label']] = isset($opt['score']) ? (float)$opt['score'] : 0.0;
                        }
                    }

                    if ($hasCustomScore) {
                        if (is_array($jawabanArr)) {
                            foreach ($jawabanArr as $jawab) {
                                if (is_string($jawab) || is_int($jawab)) {
                                    if (isset($scoreMap[$jawab])) {
                                        $skorDidapat += $scoreMap[$jawab];
                                    }
                                }
                            }
                        }
                    } else {
                        $kunciArr = json_decode((string)$kunci, true) ?: [];
                        if (is_array($jawabanArr) && is_array($kunciArr) && count($kunciArr) > 0) {
                            $jSorted = $jawabanArr;
                            $kSorted = $kunciArr;
                            sort($jSorted);
                            sort($kSorted);
                            if ($jSorted == $kSorted) {
                                $skorDidapat = $bobot;
                            } else {
                                $correctPicks = count(array_intersect($jSorted, $kSorted));
                                $wrongPicks = count(array_diff($jSorted, $kSorted));
                                $net = $correctPicks - $wrongPicks;
                                if ($net > 0) {
                                    $skorDidapat = ($net / count($kSorted)) * $bobot;
                                }
                            }
                        }
                    }
                    $skorDidapat = max(0.0, min($bobot, $skorDidapat));
                } elseif ($tipe === 'menjodohkan') {
                    $jawabanObj = json_decode((string)$jawabanSiswa, true) ?: [];
                    $opsiList = json_decode((string)$ans['opsi'], true) ?: [];
                    $kunciObj = json_decode((string)$kunci, true) ?: [];

                    $hasCustomScore = false;
                    foreach ($opsiList as $opt) {
                        if (is_array($opt) && array_key_exists('score', $opt)) {
                            $hasCustomScore = true;
                            break;
                        }
                    }

                    if (is_array($jawabanObj)) {
                        if ($hasCustomScore) {
                            foreach ($opsiList as $pair) {
                                $kiri = $pair['left'] ?? '';
                                $kanan = $pair['right'] ?? '';
                                $skorKiri = $pair['score'] ?? 0;
                                if ($kiri !== '' && isset($jawabanObj[$kiri]) && $jawabanObj[$kiri] === $kanan) {
                                    $skorDidapat += (float)$skorKiri;
                                }
                            }
                        } elseif (is_array($kunciObj) && count($kunciObj) > 0) {
                            $totalPairs = count($kunciObj);
                            $correctPairs = 0;
                            if (isset($kunciObj[0]) && is_array($kunciObj[0])) {
                                foreach ($kunciObj as $pair) {
                                    $kiri = $pair['left'] ?? '';
                                    $kanan = $pair['right'] ?? '';
                                    if ($kiri !== '' && isset($jawabanObj[$kiri]) && $jawabanObj[$kiri] === $kanan) {
                                        $correctPairs++;
                                    }
                                }
                            } else {
                                foreach ($kunciObj as $k => $v) {
                                    if ($k !== '' && isset($jawabanObj[$k]) && $jawabanObj[$k] === $v) {
                                        $correctPairs++;
                                    }
                                }
                            }
                            if ($totalPairs > 0) {
                                $skorDidapat = ($correctPairs / $totalPairs) * $bobot;
                            }
                        }
                    }
                    $skorDidapat = max(0.0, min($bobot, $skorDidapat));
                } elseif ($isUraian) {
                    $hasText = ($jawabanSiswa !== null && trim((string)$jawabanSiswa) !== '');
                    $hasVoice = ($jawabanVoice !== null && trim((string)$jawabanVoice) !== '');

                    if ($hasText || $hasVoice) {
                        $aiTasks[] = [
                            'jawaban_id'    => $ans['jawaban_id'],
                            'pertanyaan'    => $ans['pertanyaan'],
                            'kunci'         => $kunci,
                            'jawaban'       => $jawabanSiswa,
                            'jawaban_voice' => $jawabanVoice,
                            'bobot'         => $bobot
                        ];
                    }
                }
            }

            $totalSkor += $skorDidapat;
            $objectiveUpdates[] = [
                'jawaban_id'  => $ans['jawaban_id'],
                'skor'        => $skorDidapat,
                'ai_feedback' => $aiKoreksiNotes
            ];
        }

        // 1) Commit penilaian objektif terlebih dahulu agar lock transaksi tidak ditahan selama call Gemini
        if (!empty($objectiveUpdates)) {
            try {
                db()->beginTransaction();
                $stmtUpdateAns = db()->prepare("UPDATE exam_jawaban SET skor = ?, ai_feedback = ? WHERE id = ?");
                foreach ($objectiveUpdates as $upd) {
                    $stmtUpdateAns->execute([$upd['skor'], $upd['ai_feedback'], $upd['jawaban_id']]);
                }
                db()->commit();
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                throw $e;
            }
        }

        // 2) Jalankan AI Grading di luar transaksi DB
        $aiResults = [];
        if (!empty($aiTasks)) {
            $aiResults = runAiGrading($aiTasks);
        }

        // 3) Commit hasil AI & nilai akhir sesi dalam transaksi singkat
        $skorAkhir = $totalSkor;
        foreach ($aiResults as $res) {
            $skorAkhir += (float)$res['skor'];
        }
        $totalSkorFinal = $skorAkhir;

        if (!$isPsikologi) {
            $skorAkhir = ($maxSkor > 0) ? ($totalSkorFinal / $maxSkor) * 100 : 0.0;
        }
        $skorAkhir = round($skorAkhir, 0);

        $statusKoreksi = ($countUraian > 0 && $countUraianManual < $countUraian) ? 'belum_dikoreksi' : 'sudah_dikoreksi';

        try {
            db()->beginTransaction();
            if (!empty($aiResults)) {
                $stmtUpdateAi = db()->prepare("UPDATE exam_jawaban SET skor = ?, ai_feedback = ? WHERE id = ? AND COALESCE(is_koreksi_manual, 0) = 0");
                foreach ($aiResults as $res) {
                    $stmtUpdateAi->execute([$res['skor'], $res['catatan'], $res['jawaban_id']]);
                }
            }
            db()->prepare("UPDATE exam_sesi SET nilai_akhir = ?, status_koreksi = ? WHERE id = ?")
                ->execute([$skorAkhir, $statusKoreksi, $session_id]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $e;
        }

        return [
            'session_id'      => $session_id,
            'total_skor_asli' => $totalSkorFinal,
            'max_skor'        => $maxSkor,
            'skor_skala_100'  => $skorAkhir,
            'ai_graded_count' => count($aiTasks)
        ];
    }
}

if (!function_exists('runAiGrading')) {
    /**
     * Menghubungi Google Gemini API untuk grading otomatis dengan batas timeout & sanitasi output.
     */
    function runAiGrading($tasks) {
        $apiKey = get_setting('gemini_api_key', '');
        if (!$apiKey) {
            $fallback = [];
            foreach ($tasks as $t) {
                $fallback[] = [
                    'jawaban_id' => $t['jawaban_id'],
                    'skor'       => 0.0,
                    'catatan'    => 'AI Grading gagal: API Key belum dikonfigurasi.'
                ];
            }
            return $fallback;
        }

        $modelRaw = (string)get_setting('gemini_model', 'gemini-2.0-flash');
        $model = preg_replace('/[^a-zA-Z0-9._-]/', '', $modelRaw) ?: 'gemini-2.0-flash';
        $voiceBaseDir = realpath(__DIR__ . '/../uploads/voice');

        $results = [];

        foreach ($tasks as $t) {
            $bobot = max(0.0, (float)($t['bobot'] ?? 0));
            $promptText = "Anda adalah Guru Ahli yang bertugas memberikan nilai untuk soal esai dan jawaban singkat siswa.\n";
            $promptText .= "Berikan penilaian yang adil dan objektif berdasarkan rubrik/kunci jawaban dan jawaban yang diberikan siswa.\n\n";
            $promptText .= "Data Soal & Jawaban:\n";
            $promptText .= "Pertanyaan: " . strip_tags((string)$t['pertanyaan']) . "\n";
            $promptText .= "Kunci/Rubrik: " . (string)($t['kunci'] ?? '') . "\n";
            $promptText .= "Jawaban Siswa (Teks): " . (!empty($t['jawaban']) ? $t['jawaban'] : '(Tidak ada jawaban teks)') . "\n";
            $promptText .= "Skor Maksimal: " . $bobot . "\n\n";
            $promptText .= "Jika siswa melampirkan rekaman suara (audio), dengarkan rekaman audio yang disertakan dan gunakan isinya untuk menilai jawaban mereka.\n";
            $promptText .= "Berikan output HANYA dalam format JSON objek (tanpa format markdown, tanpa ```json) dengan skema berikut:\n";
            $promptText .= '{"skor": <angka_desimal_dari_0_sampai_skor_maksimal>, "catatan": "<alasan_singkat_kenapa_dapat_skor_tersebut>"}' . "\n";

            $parts = [];

            if (!empty($t['jawaban_voice']) && $voiceBaseDir) {
                $relVoice = ltrim(str_replace(['\\', "\0"], ['/', ''], (string)$t['jawaban_voice']), '/');
                $candidatePath = realpath(__DIR__ . '/../' . $relVoice);
                if ($candidatePath && str_starts_with($candidatePath, $voiceBaseDir . DIRECTORY_SEPARATOR) && is_file($candidatePath)) {
                    $size = filesize($candidatePath);
                    if ($size !== false && $size > 0 && $size <= 10 * 1024 * 1024) {
                        $ext = strtolower(pathinfo($candidatePath, PATHINFO_EXTENSION));
                        $mimeType = 'audio/webm';
                        if ($ext === 'mp3') $mimeType = 'audio/mp3';
                        elseif ($ext === 'wav') $mimeType = 'audio/wav';
                        elseif ($ext === 'm4a') $mimeType = 'audio/m4a';
                        elseif ($ext === 'ogg') $mimeType = 'audio/ogg';

                        $rawAudio = file_get_contents($candidatePath);
                        if ($rawAudio !== false) {
                            $parts[] = [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data'     => base64_encode($rawAudio)
                                ]
                            ];
                        }
                    }
                }
            }

            $parts[] = ['text' => $promptText];

            $url = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($model) . ":generateContent?key=" . urlencode($apiKey);

            $payload = [
                'contents' => [
                    ['parts' => $parts]
                ],
                'generationConfig' => [
                    'temperature'      => 0.1,
                    'responseMimeType' => 'application/json'
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $curlErr = curl_errno($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $gradeInfo = null;
            if (!$curlErr && $httpCode === 200 && is_string($response)) {
                $respData = json_decode($response, true);
                if (isset($respData['candidates'][0]['content']['parts'][0]['text'])) {
                    $jsonStr = $respData['candidates'][0]['content']['parts'][0]['text'];
                    $jsonStr = preg_replace('/```json/i', '', $jsonStr);
                    $jsonStr = preg_replace('/```/', '', $jsonStr);
                    $gradeInfo = json_decode(trim($jsonStr), true);
                }
            }

            if (is_array($gradeInfo) && isset($gradeInfo['skor']) && is_numeric($gradeInfo['skor'])) {
                $clampedScore = max(0.0, min($bobot, (float)$gradeInfo['skor']));
                $results[] = [
                    'jawaban_id' => $t['jawaban_id'],
                    'skor'       => $clampedScore,
                    'catatan'    => (string)($gradeInfo['catatan'] ?? 'Dinilai oleh AI')
                ];
            } else {
                $results[] = [
                    'jawaban_id' => $t['jawaban_id'],
                    'skor'       => 0.0,
                    'catatan'    => 'AI Grading gagal: Gagal mem-parsing hasil dari Google Gemini API (Code: ' . $httpCode . ($curlErr ? ", cURL #$curlErr" : '') . ').'
                ];
            }
        }

        return $results;
    }
}
