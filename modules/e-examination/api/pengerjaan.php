<?php
/**
 * E-Examination — API Pengerjaan Ujian & Student Auth
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/grading_service.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Validasi sesi siswa, token aktif, dan status kunci proktor, lalu lepaskan lock file session.
 */
function exam_student_session($checkLock = true) {
    if (empty($_SESSION['exam_student']['id'])) {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        throw new Exception('Silakan login kembali', 401);
    }
    $student = $_SESSION['exam_student'];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $stmtLock = db()->prepare("SELECT is_locked, lock_reason, session_token FROM exam_student_login WHERE student_id = ?");
    $stmtLock->execute([(int)$student['id']]);
    $loginRow = $stmtLock->fetch(PDO::FETCH_ASSOC);

    if ($loginRow) {
        if (!empty($loginRow['session_token']) && !empty($student['session_token'])) {
            if (!hash_equals((string)$loginRow['session_token'], (string)$student['session_token'])) {
                throw new Exception('Sesi login Anda telah digantikan oleh login lain. Silakan login kembali.', 401);
            }
        }
        if ($checkLock && (int)$loginRow['is_locked'] === 1) {
            $reason = !empty($loginRow['lock_reason']) ? " ({$loginRow['lock_reason']})" : "";
            throw new Exception("Akun Anda dikunci{$reason}. Hubungi Proktor untuk melakukan Reset Login.", 403);
        }
    }

    return $student;
}

try {
    switch ($action) {
        
        // ==========================================
        // STUDENT AUTH
        // ==========================================
        case 'login':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $login_type = sanitize($data['login_type'] ?? 'nis_dob');
            $exam_id = isset($data['exam_id']) ? (int)$data['exam_id'] : 0;
            $student = null;

            // Jika ada exam_id, pastikan login_type sesuai metode_login ujian
            if ($exam_id > 0) {
                $stmtExamCheck = db()->prepare("SELECT id, judul, metode_login FROM exam_ujian WHERE id = ?");
                $stmtExamCheck->execute([$exam_id]);
                $examObj = $stmtExamCheck->fetch(PDO::FETCH_ASSOC);
                if ($examObj && !empty($examObj['metode_login'])) {
                    if ($examObj['metode_login'] !== $login_type) {
                        $methodName = $examObj['metode_login'] === 'examcard' ? 'Kartu Ujian (E-xam Card)' : 'NIS & Tanggal Lahir';
                        throw new Exception("Ujian ini mewajibkan login menggunakan {$methodName}.", 400);
                    }
                }
            }

            if ($login_type === 'examcard') {
                $username = trim($data['username'] ?? '');
                $password = trim($data['password'] ?? '');

                if (!$username || !$password) {
                    throw new Exception('Username dan Password Kartu Ujian wajib diisi', 400);
                }

                // Check in xam_exam_students joined with active xam_exams and students
                $stmtCard = db()->prepare("
                    SELECT xs.id as card_id, xs.student_id, xs.username, xs.password_plain, xs.password_hash, xs.status as card_status, xs.suspension_note,
                           s.id, s.nis, s.nama, s.kelas, s.tanggal_lahir, s.status as student_status, s.status_siswa
                    FROM xam_exam_students xs
                    JOIN xam_exams xe ON xe.id = xs.exam_id AND xe.status = 1
                    JOIN students s ON s.id = xs.student_id
                    WHERE xs.username = ?
                    ORDER BY xs.id DESC
                ");
                $stmtCard->execute([$username]);
                $cardRows = $stmtCard->fetchAll(PDO::FETCH_ASSOC);

                if (!$cardRows) {
                    throw new Exception('Akun Kartu Ujian (Username) tidak ditemukan atau kartu ujian tidak aktif', 404);
                }

                $matchedCard = null;
                foreach ($cardRows as $row) {
                    $passPlain = (string)($row['password_plain'] ?? '');
                    $passHash = (string)($row['password_hash'] ?? '');
                    
                    $isMatch = false;
                    if ($passPlain !== '' && $password === $passPlain) {
                        $isMatch = true;
                    } elseif (!empty($passHash) && password_verify($password, $passHash)) {
                        $isMatch = true;
                    }

                    if ($isMatch) {
                        if (($row['card_status'] ?? '') === 'DITANGGUHKAN') {
                            $note = $row['suspension_note'] ? " ({$row['suspension_note']})" : "";
                            throw new Exception("Status Kartu Ujian Anda DITANGGUHKAN{$note}. Harap hubungi panitia ujian/keuangan.", 403);
                        }
                        $matchedCard = $row;
                        break;
                    }
                }

                if (!$matchedCard) {
                    throw new Exception('Password Kartu Ujian salah. Silakan periksa kartu ujian Anda.', 401);
                }

                if ((isset($matchedCard['student_status']) && (int)$matchedCard['student_status'] === 0) || (!empty($matchedCard['status_siswa']) && $matchedCard['status_siswa'] !== 'Aktif')) {
                    throw new Exception('Akun siswa tidak aktif', 403);
                }

                $cardRow = $matchedCard;

                $student = [
                    'id' => $cardRow['id'],
                    'nis' => $cardRow['nis'],
                    'nama' => $cardRow['nama'],
                    'kelas' => $cardRow['kelas'],
                    'login_type' => 'examcard',
                    'card_username' => $username,
                    'card_id' => $cardRow['card_id']
                ];
            } else {
                // NIS & Tanggal Lahir (or Password)
                $nis = sanitize($data['nis'] ?? $data['username'] ?? '');
                $dob = trim($data['dob'] ?? $data['password'] ?? '');

                if (!$nis || !$dob) {
                    throw new Exception('NIS dan Tanggal Lahir wajib diisi', 400);
                }

                $stmt = db()->prepare("
                    SELECT id, nis, nama, kelas, tanggal_lahir, status, status_siswa 
                    FROM students 
                    WHERE nis = ? 
                    ORDER BY (status_siswa = 'Aktif') DESC, academic_year_id DESC 
                    LIMIT 1
                ");
                $stmt->execute([$nis]);
                $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$studentRow) {
                    throw new Exception('NIS tidak ditemukan', 404);
                }

                if ((isset($studentRow['status']) && (int)$studentRow['status'] === 0) || (!empty($studentRow['status_siswa']) && $studentRow['status_siswa'] !== 'Aktif')) {
                    throw new Exception('Akun siswa tidak aktif', 403);
                }

                $dbDob = $studentRow['tanggal_lahir'] ?? '';
                if (!$dbDob) {
                    throw new Exception('Tanggal lahir siswa belum terdaftar. Hubungi proktor atau admin.', 401);
                }

                $matched = false;
                $ts = strtotime($dbDob);
                $dob_dmY = $ts ? date('dmY', $ts) : '';
                $dob_Ymd = $ts ? date('Y-m-d', $ts) : '';
                $dob_dmy_dashed = $ts ? date('d-m-Y', $ts) : '';
                $dob_dmy_slashed = $ts ? date('d/m/Y', $ts) : '';

                $cleanInput = str_replace(['-', '/', ' '], '', $dob);
                $cleanExpected = str_replace(['-', '/', ' '], '', $dob_dmY);

                if ($dob === $dob_dmY || $dob === $dob_Ymd || $dob === $dob_dmy_dashed || $dob === $dob_dmy_slashed || ($cleanExpected !== '' && $cleanInput === $cleanExpected)) {
                    $matched = true;
                }

                if (!$matched) {
                    throw new Exception('Tanggal lahir salah. Format: DDMMYYYY (contoh: 12052005)', 401);
                }

                $student = [
                    'id' => $studentRow['id'],
                    'nis' => $studentRow['nis'],
                    'nama' => $studentRow['nama'],
                    'kelas' => $studentRow['kelas'],
                    'login_type' => 'nis_dob'
                ];
            }

            // ===== CHECK PROCTOR LOCK STATUS =====
            try {
                $stmtLock = db()->prepare("SELECT is_locked, lock_reason FROM exam_student_login WHERE student_id = ?");
                $stmtLock->execute([$student['id']]);
                $lockInfo = $stmtLock->fetch();

                if ($lockInfo && (int)$lockInfo['is_locked'] === 1) {
                    $reason = $lockInfo['lock_reason'] ? " ({$lockInfo['lock_reason']})" : "";
                    throw new Exception("Akun Anda terkunci{$reason} karena keluar dari aplikasi ujian. Harap hubungi Proktor ujian untuk melakukan Reset Login.", 403);
                }
            } catch (Exception $e) {
                if ($e->getCode() === 403) throw $e;
            }

            session_regenerate_id(true);
            $sessToken = generate_token(32);
            $student['session_token'] = $sessToken;
            $_SESSION['exam_student'] = $student;

            // Record login status in exam_student_login
            try {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $stmtLog = db()->prepare("
                    INSERT INTO exam_student_login (student_id, status, is_locked, lock_reason, session_token, ip_address, user_agent, login_at, last_heartbeat)
                    VALUES (?, 'logged_in', 0, NULL, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        status = 'logged_in',
                        is_locked = 0,
                        lock_reason = NULL,
                        session_token = VALUES(session_token),
                        ip_address = VALUES(ip_address),
                        user_agent = VALUES(user_agent),
                        login_at = NOW(),
                        last_heartbeat = NOW()
                ");
                $stmtLog->execute([$student['id'], $sessToken, $ip, $ua]);
            } catch (Exception $e) {}

            $publicStudent = $student;
            unset($publicStudent['session_token']);
            json_response(200, true, 'Login berhasil', ['student' => $publicStudent]);
            break;

        case 'logout':
            if (isset($_SESSION['exam_student'])) {
                $studentId = $_SESSION['exam_student']['id'];
                try {
                    // If student was in exam, lock their account on logout
                    $stmtCheck = db()->prepare("SELECT status FROM exam_student_login WHERE student_id = ?");
                    $stmtCheck->execute([$studentId]);
                    $currStatus = $stmtCheck->fetchColumn();

                    $isLocked = ($currStatus === 'mengerjakan') ? 1 : 0;
                    $lockReason = $isLocked ? 'Logout saat ujian sedang berlangsung' : null;

                    db()->prepare("
                        UPDATE exam_student_login 
                        SET status = 'logged_out', is_locked = ?, lock_reason = ?, updated_at = NOW() 
                        WHERE student_id = ?
                    ")->execute([$isLocked, $lockReason, $studentId]);
                } catch (Exception $e) {}
            }
            unset($_SESSION['exam_student']);
            json_response(200, true, 'Logout berhasil');
            break;

        case 'lock_student':
            // Endpoint called via beacon or API when student leaves exam page (requires active student session)
            $studentId = (int)($_SESSION['exam_student']['id'] ?? 0);
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $reason = sanitize($data['reason'] ?? 'Keluar dari aplikasi ujian');

            if ($studentId > 0) {
                try {
                    db()->prepare("
                        UPDATE exam_student_login 
                        SET is_locked = 1, status = 'logged_out', lock_reason = ?, updated_at = NOW() 
                        WHERE student_id = ?
                    ")->execute([$reason, $studentId]);
                } catch (Exception $e) {}
                unset($_SESSION['exam_student']);
            }
            json_response(200, true, 'Akun siswa telah dikunci');
            break;

        case 'heartbeat':
            $student = exam_student_session(false);
            $studentId = (int)$student['id'];
            $data = json_decode(file_get_contents('php://input'), true) ?: $_GET;
            $sessionId = (int)($data['session_id'] ?? 0);
            $remaining = (int)($data['remaining_seconds'] ?? 0);

            $serverRemaining = null;
            $sessionStatus = null;
            $isLocked = 0;

            try {
                db()->prepare("
                    UPDATE exam_student_login 
                    SET last_heartbeat = NOW() 
                    WHERE student_id = ?
                ")->execute([$studentId]);

                $stmtL = db()->prepare("SELECT is_locked FROM exam_student_login WHERE student_id = ?");
                $stmtL->execute([$studentId]);
                $isLocked = (int)$stmtL->fetchColumn();

                if ($sessionId > 0) {
                    $stmtS = db()->prepare("
                        SELECT s.status, s.waktu_mulai, u.durasi_menit
                        FROM exam_sesi s
                        JOIN exam_ujian u ON s.ujian_id = u.id
                        WHERE s.id = ? AND s.student_id = ?
                    ");
                    $stmtS->execute([$sessionId, $studentId]);
                    $sRow = $stmtS->fetch(PDO::FETCH_ASSOC);
                    if ($sRow) {
                        $sessionStatus = $sRow['status'];
                        $endTime = strtotime($sRow['waktu_mulai']) + ((int)$sRow['durasi_menit'] * 60);
                        $serverRemaining = max(0, $endTime - time());
                        if ($remaining > 0) {
                            db()->prepare("UPDATE exam_sesi SET sisa_detik = ? WHERE id = ? AND student_id = ?")->execute([$serverRemaining, $sessionId, $studentId]);
                        }
                    }
                }
            } catch (Exception $e) {}

            json_response(200, true, 'Heartbeat OK', [
                'remaining_seconds' => $serverRemaining,
                'session_status'    => $sessionStatus,
                'is_locked'         => $isLocked
            ]);
            break;

        // ==========================================
        // START EXAM (TOKEN VERIFICATION)
        // ==========================================
        case 'start':
            $student = exam_student_session(true);
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $ujian_id = (int)($data['ujian_id'] ?? 0);
            $token = strtoupper(trim($data['token'] ?? ''));

            if (!$ujian_id || !$token) throw new Exception('Ujian ID dan Token wajib diisi', 400);

            // 1. Verify Ujian and Token and Class Access
            $stmt = db()->prepare("
                SELECT u.*, uk.kelas 
                FROM exam_ujian u
                JOIN exam_ujian_kelas uk ON uk.ujian_id = u.id
                WHERE u.id = ? AND u.status = 'aktif' AND uk.kelas = ?
            ");
            $stmt->execute([$ujian_id, $student['kelas']]);
            $ujian = $stmt->fetch();

            if (!$ujian) throw new Exception('Ujian tidak ditemukan, tidak aktif, atau bukan untuk kelas Anda (' . htmlspecialchars($student['kelas']) . ')', 404);
            if (trim($ujian['token']) !== $token) throw new Exception('TOKEN SALAH. Silakan periksa token ujian.', 401);

            // Optional: Check if student has exam card suspension ONLY if ujian requires examcard or student logged in via examcard
            $metodeLoginUjian = $ujian['metode_login'] ?? 'nis_dob';
            $studentLoginType = $student['login_type'] ?? 'nis_dob';

            if ($metodeLoginUjian === 'examcard' && $studentLoginType !== 'examcard') {
                throw new Exception('Ujian ini mewajibkan login menggunakan Kartu Ujian (E-xam Card). Silakan logout dan login kembali menggunakan Kartu Ujian.', 403);
            }

            if ($studentLoginType === 'examcard' && !empty($student['card_id'])) {
                try {
                    $stmtCheckSuspended = db()->prepare("
                        SELECT status, suspension_note 
                        FROM xam_exam_students 
                        WHERE id = ? 
                        LIMIT 1
                    ");
                    $stmtCheckSuspended->execute([$student['card_id']]);
                    $susp = $stmtCheckSuspended->fetch();
                    if ($susp && $susp['status'] === 'DITANGGUHKAN') {
                        $note = $susp['suspension_note'] ? " ({$susp['suspension_note']})" : "";
                        throw new Exception("Status Kartu Ujian Anda ditangguhkan{$note}. Harap hubungi panitia ujian/keuangan.", 403);
                    }
                } catch (Exception $e) {
                    if ($e->getCode() === 403) throw $e;
                }
            }

            // 2. Check existing session
            $stmtSesi = db()->prepare("SELECT * FROM exam_sesi WHERE ujian_id = ? AND student_id = ?");
            $stmtSesi->execute([$ujian_id, $student['id']]);
            $sesi = $stmtSesi->fetch();

            if ($sesi) {
                if (in_array($sesi['status'], ['selesai', 'dihentikan'])) {
                    throw new Exception('Anda sudah menyelesaikan atau dihentikan dari ujian ini.', 403);
                }
                // Resume
                try {
                    db()->prepare("UPDATE exam_student_login SET status = 'mengerjakan', ujian_id = ?, sesi_id = ?, is_locked = 0, last_heartbeat = NOW() WHERE student_id = ?")->execute([$ujian_id, $sesi['id'], $student['id']]);
                } catch (Exception $e) {}

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
                    $stmtSoal = db()->prepare("SELECT id, tipe_soal, opsi FROM exam_soal WHERE bank_soal_id = ? AND status = 1 ORDER BY urutan ASC, id ASC");
                    $stmtSoal->execute([$ujian['bank_soal_id']]);
                    $soalList = $stmtSoal->fetchAll();

                    if ($ujian['acak_soal']) shuffle($soalList);

                    if (!empty($soalList)) {
                        $insertRows = [];
                        $insertParams = [];
                        foreach ($soalList as $idx => $s) {
                            $opsiAcak = null;
                            if ($ujian['acak_opsi'] && in_array($s['tipe_soal'], ['pilihan_satu', 'pilihan_banyak'], true)) {
                                $opsiArr = json_decode((string)$s['opsi'], true);
                                if (is_array($opsiArr)) {
                                    $labels = array_column($opsiArr, 'label');
                                    shuffle($labels);
                                    $opsiAcak = json_encode($labels);
                                }
                            }
                            $insertRows[] = '(?, ?, ?, ?)';
                            array_push($insertParams, $session_id, $s['id'], $idx + 1, $opsiAcak);
                        }
                        $stmtAns = db()->prepare("INSERT INTO exam_jawaban (sesi_id, soal_id, urutan, opsi_acak) VALUES " . implode(', ', $insertRows));
                        $stmtAns->execute($insertParams);
                    }

                    // Update login status
                    db()->prepare("UPDATE exam_student_login SET status = 'mengerjakan', ujian_id = ?, sesi_id = ?, is_locked = 0, last_heartbeat = NOW() WHERE student_id = ?")->execute([$ujian_id, $session_id, $student['id']]);

                    db()->commit();
                    json_response(200, true, 'Ujian dimulai', ['session_id' => $session_id]);
                } catch (PDOException $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    if ((string)$e->getCode() === '23000') {
                        $stmtSesi->execute([$ujian_id, $student['id']]);
                        $existing = $stmtSesi->fetch();
                        if ($existing && !in_array($existing['status'], ['selesai', 'dihentikan'], true)) {
                            json_response(200, true, 'Melanjutkan ujian', ['session_id' => $existing['id']]);
                        }
                    }
                    throw $e;
                } catch (Exception $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    throw $e;
                }
            }
            break;

        // ==========================================
        // FETCH SOAL & JAWABAN
        // ==========================================
        case 'get_soal':
            $student = exam_student_session(true);
            $session_id = (int)($_GET['session_id'] ?? 0);
            if (!$session_id) throw new Exception('Sesi tidak valid', 400);

            // Verify session belongs to user
            $stmt = db()->prepare("
                SELECT s.*, u.judul as nama_ujian, u.durasi_menit, u.acak_opsi, u.bank_soal_id 
                FROM exam_sesi s
                JOIN exam_ujian u ON s.ujian_id = u.id
                WHERE s.id = ? AND s.student_id = ?
            ");
            $stmt->execute([$session_id, $student['id']]);
            $sesi = $stmt->fetch();

            if (!$sesi) throw new Exception('Sesi tidak ditemukan', 404);
            if ($sesi['status'] !== 'mengerjakan') throw new Exception('Ujian sudah selesai atau dihentikan', 403);

            // Track mengerjakan state without resetting is_locked
            try {
                db()->prepare("UPDATE exam_student_login SET status = 'mengerjakan', ujian_id = ?, sesi_id = ?, last_heartbeat = NOW() WHERE student_id = ? AND is_locked = 0")->execute([$sesi['ujian_id'], $session_id, $student['id']]);
            } catch (Exception $e) {}

            // Calculate remaining time
            $startTime = strtotime($sesi['waktu_mulai']);
            $endTime = $startTime + ($sesi['durasi_menit'] * 60);
            $remainingSeconds = $endTime - time();

            if ($remainingSeconds <= 0) {
                // Auto submit if time is up
                db()->prepare("UPDATE exam_sesi SET status = 'selesai', waktu_selesai = NOW() WHERE id = ?")->execute([$session_id]);
                try {
                    db()->prepare("UPDATE exam_student_login SET status = 'selesai', is_locked = 0 WHERE student_id = ?")->execute([$student['id']]);
                } catch (Exception $e) {}
                try {
                    processGrading($session_id);
                } catch (Exception $e) {}
                throw new Exception('Waktu ujian telah habis', 403);
            }

            // Fetch answers and questions
            $stmtAns = db()->prepare("
                SELECT j.id as jawaban_id, j.urutan, j.jawaban, j.jawaban_voice, j.opsi_acak, j.is_ragu AS ragu_ragu,
                       s.id as soal_id, s.tipe_soal, s.pertanyaan, s.opsi, s.gambar, s.audio, s.audio_play_limit, s.bobot
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
                                $opsiBaru[] = $opsiMap[$l];
                            }
                        }
                        $opsi = $opsiBaru;
                    }
                }

                // Normalize option properties so both 'text' and 'teks' always exist
                if (is_array($opsi) && in_array($s['tipe_soal'], ['pilihan_satu', 'pilihan_banyak', 'benar_salah'])) {
                    foreach ($opsi as &$optItem) {
                        if (is_array($optItem)) {
                            $t = $optItem['text'] ?? $optItem['teks'] ?? $optItem['isi'] ?? '';
                            $optItem['text'] = $t;
                            $optItem['teks'] = $t;
                        }
                    }
                    unset($optItem);
                }
                
                if ($s['tipe_soal'] === 'menjodohkan') {
                    $kiri = [];
                    $kanan = [];
                    foreach ($opsi as $opt) {
                        if (isset($opt['left']) && isset($opt['right'])) {
                            $kiri[] = $opt['left'];
                            $kanan[] = $opt['right'];
                        }
                    }
                    // Acak pasangan kanan agar siswa harus mencocokkan
                    if ($s['opsi_acak']) {
                        shuffle($kanan);
                    } else {
                        // Walaupun tidak diset acak, kanan sebaiknya diacak sedikit atau biarkan original jika guru mau
                        // Default kita acak agar logis
                        shuffle($kanan);
                    }
                    $opsi = ['kiri' => $kiri, 'kanan' => $kanan];
                }

                $formattedSoal[] = [
                    'jawaban_id' => $s['jawaban_id'],
                    'urutan' => $s['urutan'],
                    'ragu_ragu' => (bool)$s['ragu_ragu'],
                    'jawaban' => $s['jawaban'], // JSON string for multiple choices/matching
                    'jawaban_voice' => $s['jawaban_voice'] ?? null,
                    'soal' => [
                        'id' => $s['soal_id'],
                        'tipe_soal' => $s['tipe_soal'],
                        'pertanyaan' => $s['pertanyaan'],
                        'gambar' => $s['gambar'],
                        'audio' => $s['audio'],
                        'audio_play_limit' => (int)($s['audio_play_limit'] ?? 0),
                        'opsi' => $opsi
                    ]
                ];
            }

            // Count existing violations for this session
            $stmtViol = db()->prepare("SELECT COUNT(*) FROM exam_cheat_log WHERE sesi_id = ?");
            $stmtViol->execute([$session_id]);
            $currentViolations = (int)$stmtViol->fetchColumn();

            json_response(200, true, 'Soal berhasil dimuat', [
                'session_id' => $session_id,
                'ujian_judul' => $sesi['nama_ujian'],
                'remaining_seconds' => $remainingSeconds,
                'soal_list' => $formattedSoal,
                'violations' => $currentViolations
            ]);
            break;

        // ==========================================
        // SAVE JAWABAN
        // ==========================================
        case 'save_jawaban':
            $student = exam_student_session(true);
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $jawaban_id = (int)($data['jawaban_id'] ?? 0);
            $session_id = (int)($data['session_id'] ?? 0);
            $jawaban = $data['jawaban'] ?? null; // Can be string, array, or null
            $ragu_ragu = isset($data['ragu_ragu']) ? (int)$data['ragu_ragu'] : 0;

            if (!$jawaban_id || !$session_id) throw new Exception('Data tidak valid', 400);

            // Verify session, exam active state, and remaining time (+30s grace period)
            $stmtSesi = db()->prepare("
                SELECT s.status, s.waktu_mulai, u.status as ujian_status, u.durasi_menit
                FROM exam_sesi s
                JOIN exam_ujian u ON s.ujian_id = u.id
                WHERE s.id = ? AND s.student_id = ?
            ");
            $stmtSesi->execute([$session_id, $student['id']]);
            $sesiRow = $stmtSesi->fetch(PDO::FETCH_ASSOC);

            if (!$sesiRow || $sesiRow['status'] !== 'mengerjakan' || $sesiRow['ujian_status'] !== 'aktif') {
                throw new Exception('Ujian tidak aktif', 403);
            }
            $deadline = strtotime($sesiRow['waktu_mulai']) + ((int)$sesiRow['durasi_menit'] * 60) + 30;
            if (time() > $deadline) {
                throw new Exception('Waktu pengerjaan ujian telah habis', 403);
            }

            // Format jawaban as JSON if array
            $jawabanVal = is_array($jawaban) ? json_encode($jawaban) : $jawaban;

            $stmt = db()->prepare("UPDATE exam_jawaban SET jawaban = ?, is_ragu = ? WHERE id = ? AND sesi_id = ?");
            $stmt->execute([$jawabanVal, $ragu_ragu, $jawaban_id, $session_id]);

            json_response(200, true, 'Jawaban disimpan');
            break;

        // ==========================================
        // SUBMIT EXAM
        // ==========================================
        case 'submit':
            $student = exam_student_session(true);
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $session_id = (int)($data['session_id'] ?? 0);
            if (!$session_id) throw new Exception('Data tidak valid', 400);

            $stmt = db()->prepare("UPDATE exam_sesi SET status = 'selesai', waktu_selesai = NOW() WHERE id = ? AND student_id = ? AND status IN ('mengerjakan', 'berlangsung')");
            $stmt->execute([$session_id, $student['id']]);

            if ($stmt->rowCount() > 0) {
                try {
                    db()->prepare("UPDATE exam_student_login SET status = 'selesai', is_locked = 0, lock_reason = NULL, updated_at = NOW() WHERE student_id = ?")->execute([$student['id']]);
                } catch (Exception $e) {}

                // Auto-grade objective questions & set initial status_koreksi
                try {
                    processGrading($session_id);
                } catch (Exception $e) {}

                json_response(200, true, 'Ujian berhasil diselesaikan');
            } else {
                // Check if session exists but is already finished
                $stmtCheck = db()->prepare("SELECT status FROM exam_sesi WHERE id = ? AND student_id = ?");
                $stmtCheck->execute([$session_id, $student['id']]);
                $sesiStatus = $stmtCheck->fetchColumn();
                
                if ($sesiStatus === 'selesai' || $sesiStatus === 'dihentikan') {
                    try {
                        db()->prepare("UPDATE exam_student_login SET status = 'selesai', is_locked = 0, lock_reason = NULL, updated_at = NOW() WHERE student_id = ?")->execute([$student['id']]);
                    } catch (Exception $e) {}
                    json_response(200, true, 'Ujian sudah diselesaikan sebelumnya');
                } else {
                    json_response(400, false, 'Gagal menyelesaikan ujian');
                }
            }
            break;

        case 'report_cheat':
            $student = exam_student_session(false);
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $session_id = (int)($data['session_id'] ?? 0);
            $cheat_type = $data['type'] ?? 'unknown'; // blur, minimize, etc
 
            if (!$session_id) throw new Exception('Data tidak valid', 400);
 
            // Verify session belongs to this student and is still active
            $stmtSesiCheck = db()->prepare("SELECT status FROM exam_sesi WHERE id = ? AND student_id = ?");
            $stmtSesiCheck->execute([$session_id, $student['id']]);
            $sesiStatus = $stmtSesiCheck->fetchColumn();

            if ($sesiStatus !== 'mengerjakan') {
                json_response(200, true, 'Sesi ujian sudah berakhir', ['action' => 'already_stopped', 'violations' => 0]);
                break;
            }

            $stmt = db()->prepare("INSERT INTO exam_cheat_log (sesi_id, jenis) VALUES (?, ?)");
            $stmt->execute([$session_id, $cheat_type]);
 
            // Check total violations for THIS session only
            $stmtCount = db()->prepare("SELECT COUNT(*) FROM exam_cheat_log WHERE sesi_id = ?");
            $stmtCount->execute([$session_id]);
            $totalViolations = (int)$stmtCount->fetchColumn();

            // Also update pelanggaran counter on exam_sesi
            try {
                db()->prepare("UPDATE exam_sesi SET pelanggaran = ? WHERE id = ?")->execute([$totalViolations, $session_id]);
            } catch (Exception $e) {}
 
            // Stop exam if >= 3 violations
            if ($totalViolations >= 3) {
                db()->prepare("UPDATE exam_sesi SET status = 'dihentikan', waktu_selesai = NOW(), pelanggaran = ? WHERE id = ? AND student_id = ?")->execute([$totalViolations, $session_id, $student['id']]);
                try {
                    db()->prepare("UPDATE exam_student_login SET status = 'logged_out', is_locked = 1, lock_reason = 'Ujian dihentikan karena 3 kali pelanggaran anti-cheat', updated_at = NOW() WHERE student_id = ?")->execute([$student['id']]);
                } catch (Exception $e) {}
                try {
                    processGrading($session_id);
                } catch (Exception $e) {}
                json_response(200, true, 'Ujian dihentikan', ['action' => 'stop', 'violations' => $totalViolations]);
            } else {
                json_response(200, true, 'Pelanggaran dicatat', ['action' => 'warn', 'violations' => $totalViolations]);
            }
            break;

        case 'upload_voice':
            $student = exam_student_session(true);
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);

            $session_id = (int)($_POST['session_id'] ?? 0);
            $jawaban_id = (int)($_POST['jawaban_id'] ?? 0);

            if (!$session_id || !$jawaban_id) throw new Exception('Sesi atau Jawaban tidak valid', 400);

            // Verify session, active state & remaining deadline
            $stmtSesi = db()->prepare("
                SELECT s.status, s.waktu_mulai, u.status as ujian_status, u.durasi_menit
                FROM exam_sesi s
                JOIN exam_ujian u ON s.ujian_id = u.id
                WHERE s.id = ? AND s.student_id = ?
            ");
            $stmtSesi->execute([$session_id, $student['id']]);
            $sesiRow = $stmtSesi->fetch(PDO::FETCH_ASSOC);

            if (!$sesiRow || $sesiRow['status'] !== 'mengerjakan' || $sesiRow['ujian_status'] !== 'aktif') {
                throw new Exception('Ujian tidak aktif', 403);
            }
            $deadline = strtotime($sesiRow['waktu_mulai']) + ((int)$sesiRow['durasi_menit'] * 60) + 30;
            if (time() > $deadline) {
                throw new Exception('Waktu pengerjaan ujian telah habis', 403);
            }

            if (!isset($_FILES['voice']) || $_FILES['voice']['error'] !== UPLOAD_ERR_OK) {
                $errCode = $_FILES['voice']['error'] ?? 'none';
                throw new Exception("File voice tidak terkirim atau gagal diupload (Error code: {$errCode}).", 400);
            }

            $file = $_FILES['voice'];
            if (!is_uploaded_file($file['tmp_name'])) {
                throw new Exception('Sumber upload audio tidak valid.', 400);
            }
            if ((int)$file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Ukuran rekaman suara maksimal 5 MB.', 400);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'webm';
            if (!in_array($ext, ['webm', 'mp3', 'wav', 'ogg', 'm4a'], true)) {
                throw new Exception('Format audio tidak didukung. Gunakan: webm, mp3, wav, ogg, m4a.', 400);
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
            if ($finfo) finfo_close($finfo);
            $allowedMimes = [
                'audio/webm', 'video/webm', 'audio/mpeg', 'audio/mp3', 'audio/wav',
                'audio/x-wav', 'audio/vnd.wave', 'audio/ogg', 'application/ogg',
                'audio/mp4', 'audio/m4a', 'audio/x-m4a', 'application/octet-stream'
            ];
            if ($mime && !in_array($mime, $allowedMimes, true) && strpos($mime, 'audio/') !== 0) {
                throw new Exception('File bukan rekaman audio yang valid (' . $mime . ').', 400);
            }

            // Verify jawaban row exists for session and unlink old voice if any
            $stmtOldVoice = db()->prepare("SELECT jawaban_voice FROM exam_jawaban WHERE id = ? AND sesi_id = ?");
            $stmtOldVoice->execute([$jawaban_id, $session_id]);
            $oldVoice = $stmtOldVoice->fetchColumn();
            if ($oldVoice === false) {
                throw new Exception('Item jawaban tidak ditemukan dalam sesi ini.', 404);
            }

            $uploadDir = __DIR__ . '/../uploads/voice/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
                @chmod($uploadDir, 0777);
            }
            if (!is_writable($uploadDir)) {
                @chmod($uploadDir, 0777);
            }

            $filename = 'voice_' . $session_id . '_' . $jawaban_id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $filepath = $uploadDir . $filename;

            $moved = @move_uploaded_file($file['tmp_name'], $filepath);

            if (!$moved) {
                throw new Exception('Gagal menyimpan file audio di server. Pastikan folder modules/e-examination/uploads memiliki izin tulis.', 500);
            }
            @chmod($filepath, 0664);

            if (!empty($oldVoice)) {
                safe_unlink_in_dir('modules/e-examination/' . ltrim((string)$oldVoice, '/'), 'modules/e-examination/uploads/voice');
            }

            $relativePath = 'uploads/voice/' . $filename;

            // Update database column `jawaban_voice` in `exam_jawaban`
            $stmt = db()->prepare("UPDATE exam_jawaban SET jawaban_voice = ? WHERE id = ? AND sesi_id = ?");
            $stmt->execute([$relativePath, $jawaban_id, $session_id]);

            json_response(200, true, 'Voice answer uploaded successfully', [
                'path' => $relativePath,
                'url' => BASE_URL . 'modules/e-examination/' . $relativePath
            ]);
            break;

        default:
            throw new Exception('Action tidak valid', 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) $code = 500;
    json_response($code, false, $e->getMessage());
}
