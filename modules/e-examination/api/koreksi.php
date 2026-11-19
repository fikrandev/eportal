<?php
/**
 * E-Examination — API Koreksi (Otomatis & AI)
 */
require_once __DIR__ . '/config_exam.php';
require_once __DIR__ . '/grading_service.php';

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
            $auth = exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $session_id = (int)($data['session_id'] ?? 0);
            if (!$session_id) throw new Exception('Session ID tidak valid', 400);

            exam_require_sesi_access($session_id, $auth);
            $result = processGrading($session_id);
            json_response(200, true, 'Koreksi berhasil', $result);
            break;

        // ==========================================
        // KOREKSI MASAL SEMUA SESI SELESAI PADA UJIAN
        // ==========================================
        case 'koreksi_masal':
            $auth = exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $ujian_id = (int)($data['ujian_id'] ?? 0);
            if (!$ujian_id) throw new Exception('Ujian ID tidak valid', 400);

            exam_require_ujian_access($ujian_id, $auth);

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
            $auth = exam_require_admin_or_guru();
            $session_id = (int)($_GET['sesi_id'] ?? 0);
            if (!$session_id) throw new Exception('Sesi ID tidak valid', 400);

            exam_require_sesi_access($session_id, $auth);

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

            exam_require_sesi_access($session_id, $auth);

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
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        $code = (int)($e->getCode() ?: 500);
        if ($code < 100 || $code >= 600) $code = 500;
        json_response($code, false, $e->getMessage());
    }
}

