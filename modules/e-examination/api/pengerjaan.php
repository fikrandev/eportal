<?php
/**
 * E-Examination — API Pengerjaan Ujian & Student Auth
 */
require_once __DIR__ . '/../../api/config.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

session_start();

try {
    switch ($action) {
        
        // ==========================================
        // STUDENT AUTH
        // ==========================================
        case 'login':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $username = sanitize($data['username'] ?? '');
            $password = $data['password'] ?? '';

            if (!$username || !$password) throw new Exception('NIS dan Password wajib diisi', 400);

            // Fetch from students
            $stmt = db()->prepare("SELECT id, nis, nama, kelas, tanggal_lahir FROM students WHERE nis = ? AND status = 1 LIMIT 1");
            $stmt->execute([$username]);
            $student = $stmt->fetch();

            if (!$student) throw new Exception('NIS tidak ditemukan atau tidak aktif', 404);

            $dbDob = $student['tanggal_lahir'] ?? '';
            $expectedPassword = $dbDob ? date('dmY', strtotime($dbDob)) : '';

            if ($password !== $expectedPassword && $password !== $student['nis']) {
                throw new Exception('Password salah', 401);
            }

            $_SESSION['exam_student'] = [
                'id' => $student['id'],
                'nis' => $student['nis'],
                'nama' => $student['nama'],
                'kelas' => $student['kelas']
            ];

            json_response(200, true, 'Login berhasil');
            break;

        case 'logout':
            unset($_SESSION['exam_student']);
            json_response(200, true, 'Logout berhasil');
            break;

        // ==========================================
        // START EXAM (TOKEN VERIFICATION)
        // ==========================================
        case 'start':
            if (!isset($_SESSION['exam_student'])) throw new Exception('Silakan login kembali', 401);
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $ujian_id = (int)($data['ujian_id'] ?? 0);
            $token = strtoupper(trim($data['token'] ?? ''));

            if (!$ujian_id || !$token) throw new Exception('Ujian ID dan Token wajib diisi', 400);
            
            $student = $_SESSION['exam_student'];

            // 1. Verify Ujian and Token
            $stmt = db()->prepare("
                SELECT u.*, uk.kelas 
                FROM exam_ujian u
                JOIN exam_ujian_kelas uk ON uk.ujian_id = u.id
                WHERE u.id = ? AND u.status = 'aktif' AND uk.kelas = ?
            ");
            $stmt->execute([$ujian_id, $student['kelas']]);
            $ujian = $stmt->fetch();

            if (!$ujian) throw new Exception('Ujian tidak ditemukan, tidak aktif, atau bukan untuk kelas Anda', 404);
            if ($ujian['token'] !== $token) throw new Exception('TOKEN SALAH. Silakan periksa kembali.', 401);

            // 2. Check existing session
            $stmtSesi = db()->prepare("SELECT * FROM exam_sesi WHERE ujian_id = ? AND student_id = ?");
            $stmtSesi->execute([$ujian_id, $student['id']]);
            $sesi = $stmtSesi->fetch();

            if ($sesi) {
                if (in_array($sesi['status'], ['selesai', 'dihentikan'])) {
                    throw new Exception('Anda sudah menyelesaikan atau dihentikan dari ujian ini.', 403);
                }
                // Resume
                json_response(200, true, 'Melanjutkan ujian', ['session_id' => $sesi['id']]);
            } else {
                // New Session
                try {
                    db()->beginTransaction();
                    $stmtIns = db()->prepare("
                        INSERT INTO exam_sesi (ujian_id, student_id, waktu_mulai, status)
                        VALUES (?, ?, NOW(), 'mengerjakan')
                    ");
                    $stmtIns->execute([$ujian_id, $student['id']]);
                    $session_id = db()->lastInsertId();

                    // Generate Soal mapping (handle acak_soal and acak_opsi)
                    $stmtSoal = db()->prepare("SELECT id, tipe_soal, opsi FROM exam_soal WHERE bank_soal_id = ?");
                    $stmtSoal->execute([$ujian['bank_soal_id']]);
                    $soalList = $stmtSoal->fetchAll();

                    if ($ujian['acak_soal']) shuffle($soalList);

                    $stmtAns = db()->prepare("INSERT INTO exam_jawaban (sesi_id, soal_id, urutan, opsi_acak) VALUES (?, ?, ?, ?)");
                    
                    foreach ($soalList as $idx => $s) {
                        $opsiAcak = null;
                        if ($ujian['acak_opsi'] && in_array($s['tipe_soal'], ['pilihan_satu', 'pilihan_banyak'])) {
                            $opsiArr = json_decode($s['opsi'], true);
                            if (is_array($opsiArr)) {
                                $labels = array_column($opsiArr, 'label');
                                shuffle($labels);
                                $opsiAcak = json_encode($labels);
                            }
                        }
                        $stmtAns->execute([$session_id, $s['id'], $idx + 1, $opsiAcak]);
                    }

                    db()->commit();
                    json_response(200, true, 'Ujian dimulai', ['session_id' => $session_id]);
                } catch (Exception $e) {
                    db()->rollBack();
                    throw $e;
                }
            }
            break;

        // ==========================================
        // FETCH SOAL & JAWABAN
        // ==========================================
        case 'get_soal':
            if (!isset($_SESSION['exam_student'])) throw new Exception('Silakan login kembali', 401);
            $session_id = (int)($_GET['session_id'] ?? 0);
            if (!$session_id) throw new Exception('Sesi tidak valid', 400);

            // Verify session belongs to user
            $stmt = db()->prepare("
                SELECT s.*, u.judul as nama_ujian, u.durasi_menit, u.acak_opsi, u.bank_soal_id 
                FROM exam_sesi s
                JOIN exam_ujian u ON s.ujian_id = u.id
                WHERE s.id = ? AND s.student_id = ?
            ");
            $stmt->execute([$session_id, $_SESSION['exam_student']['id']]);
            $sesi = $stmt->fetch();

            if (!$sesi) throw new Exception('Sesi tidak ditemukan', 404);
            if ($sesi['status'] !== 'mengerjakan') throw new Exception('Ujian sudah selesai atau dihentikan', 403);

            // Calculate remaining time
            $startTime = strtotime($sesi['waktu_mulai']);
            $endTime = $startTime + ($sesi['durasi_menit'] * 60);
            $remainingSeconds = $endTime - time();

            if ($remainingSeconds <= 0) {
                // Auto submit if time is up
                db()->prepare("UPDATE exam_sesi SET status = 'selesai', waktu_selesai = NOW() WHERE id = ?")->execute([$session_id]);
                throw new Exception('Waktu ujian telah habis', 403);
            }

            // Fetch answers and questions
            $stmtAns = db()->prepare("
                SELECT j.id as jawaban_id, j.urutan, j.jawaban, j.opsi_acak, j.ragu_ragu,
                       s.id as soal_id, s.tipe_soal, s.pertanyaan, s.opsi, s.gambar, s.audio, s.bobot
                FROM exam_jawaban j
                JOIN exam_soal s ON j.soal_id = s.id
                WHERE j.sesi_id = ?
                ORDER BY j.urutan ASC
            ");
            $stmtAns->execute([$session_id]);
            $soalList = $stmtAns->fetchAll();

            // Format for frontend
            $formattedSoal = [];
            foreach ($soalList as $s) {
                $opsi = json_decode($s['opsi'], true) ?: [];
                
                // Shuffle options based on opsi_acak map if present
                if ($s['opsi_acak'] && in_array($s['tipe_soal'], ['pilihan_satu', 'pilihan_banyak'])) {
                    $acakOrder = json_decode($s['opsi_acak'], true) ?: [];
                    if (!empty($acakOrder)) {
                        $opsiMap = [];
                        foreach ($opsi as $o) $opsiMap[$o['label']] = $o;
                        $opsiBaru = [];
                        foreach ($acakOrder as $l) {
                            if (isset($opsiMap[$l])) {
                                // Ganti labelnya secara virtual untuk UI (A, B, C...) tapi aslinya tetap value lama?
                                // Atau biarkan label asli, UI yang render berdasarkan urutan array
                                $opsiBaru[] = $opsiMap[$l];
                            }
                        }
                        $opsi = $opsiBaru;
                    }
                }

                $formattedSoal[] = [
                    'jawaban_id' => $s['jawaban_id'],
                    'urutan' => $s['urutan'],
                    'ragu_ragu' => (bool)$s['ragu_ragu'],
                    'jawaban' => $s['jawaban'], // JSON string for multiple choices/matching
                    'soal' => [
                        'id' => $s['soal_id'],
                        'tipe_soal' => $s['tipe_soal'],
                        'pertanyaan' => $s['pertanyaan'],
                        'gambar' => $s['gambar'],
                        'audio' => $s['audio'],
                        'opsi' => $opsi
                    ]
                ];
            }

            json_response(200, true, 'Soal berhasil dimuat', [
                'session_id' => $session_id,
                'ujian_judul' => $sesi['nama_ujian'],
                'remaining_seconds' => $remainingSeconds,
                'soal_list' => $formattedSoal
            ]);
            break;

        // ==========================================
        // SAVE JAWABAN
        // ==========================================
        case 'save_jawaban':
            if (!isset($_SESSION['exam_student'])) throw new Exception('Silakan login', 401);
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $jawaban_id = (int)($data['jawaban_id'] ?? 0);
            $session_id = (int)($data['session_id'] ?? 0);
            $jawaban = $data['jawaban'] ?? null; // Can be string, array, or null
            $ragu_ragu = isset($data['ragu_ragu']) ? (int)$data['ragu_ragu'] : 0;

            if (!$jawaban_id || !$session_id) throw new Exception('Data tidak valid', 400);

            // Verify session
            $stmtSesi = db()->prepare("SELECT status FROM exam_sesi WHERE id = ? AND student_id = ?");
            $stmtSesi->execute([$session_id, $_SESSION['exam_student']['id']]);
            $status = $stmtSesi->fetchColumn();

            if ($status !== 'mengerjakan') throw new Exception('Ujian tidak aktif', 403);

            // Format jawaban as JSON if array
            $jawabanVal = is_array($jawaban) ? json_encode($jawaban) : $jawaban;

            $stmt = db()->prepare("UPDATE exam_jawaban SET jawaban = ?, ragu_ragu = ? WHERE id = ? AND sesi_id = ?");
            $stmt->execute([$jawabanVal, $ragu_ragu, $jawaban_id, $session_id]);

            json_response(200, true, 'Jawaban disimpan');
            break;

        // ==========================================
        // SUBMIT EXAM
        // ==========================================
        case 'submit':
            if (!isset($_SESSION['exam_student'])) throw new Exception('Silakan login', 401);
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $session_id = (int)($data['session_id'] ?? 0);
            if (!$session_id) throw new Exception('Data tidak valid', 400);

            $stmt = db()->prepare("UPDATE exam_sesi SET status = 'selesai', waktu_selesai = NOW() WHERE id = ? AND student_id = ? AND status = 'mengerjakan'");
            $stmt->execute([$session_id, $_SESSION['exam_student']['id']]);

            if ($stmt->rowCount() > 0) {
                // Background processing could be triggered here for auto-grading
                json_response(200, true, 'Ujian berhasil diselesaikan');
            } else {
                json_response(400, false, 'Gagal menyelesaikan ujian atau ujian sudah selesai');
            }
            break;

        // ==========================================
        // REPORT CHEATING
        // ==========================================
        case 'report_cheat':
            if (!isset($_SESSION['exam_student'])) throw new Exception('Silakan login', 401);
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $session_id = (int)($data['session_id'] ?? 0);
            $cheat_type = $data['type'] ?? 'unknown'; // blur, minimize, etc

            if (!$session_id) throw new Exception('Data tidak valid', 400);

            $stmt = db()->prepare("INSERT INTO exam_cheat_log (sesi_id, tipe_pelanggaran) VALUES (?, ?)");
            $stmt->execute([$session_id, $cheat_type]);

            // Check total violations
            $stmtCount = db()->prepare("SELECT COUNT(*) FROM exam_cheat_log WHERE sesi_id = ?");
            $stmtCount->execute([$session_id]);
            $totalViolations = $stmtCount->fetchColumn();

            // Stop exam if >= 3 violations
            if ($totalViolations >= 3) {
                db()->prepare("UPDATE exam_sesi SET status = 'dihentikan', waktu_selesai = NOW() WHERE id = ? AND student_id = ?")->execute([$session_id, $_SESSION['exam_student']['id']]);
                json_response(200, true, 'Ujian dihentikan', ['action' => 'stop', 'violations' => $totalViolations]);
            } else {
                json_response(200, true, 'Pelanggaran dicatat', ['action' => 'warn', 'violations' => $totalViolations]);
            }
            break;

        default:
            throw new Exception('Action tidak valid', 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) $code = 500;
    json_response($code, false, $e->getMessage());
}
