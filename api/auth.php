<?php
/**
 * E-Portal Authentication API
 * Handles login, logout, session verification
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        handleCheck();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * Handle Login
 */
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $username = isset($input['username']) ? sanitize($input['username']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $intended_module = isset($input['intended_module']) ? $input['intended_module'] : '';

    if (empty($username) || empty($password)) {
        json_response(400, false, 'Username dan password harus diisi.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            // Check Student Login (fallback)
            $stmtSiswa = db()->prepare("
                SELECT id, nama, kelas, academic_year_id, tanggal_lahir 
                FROM students 
                WHERE nis = ? AND status = 1
                LIMIT 1
            ");
            $stmtSiswa->execute([$username]);
            $student = $stmtSiswa->fetch();

            if ($student) {
                $dbDob = $student['tanggal_lahir'] ?? '';
                $expectedPassword = $dbDob ? date('dmY', strtotime($dbDob)) : '';
                
                if ($password === $expectedPassword) {
                    // Check if intended module is e-performance
                    if (strpos($intended_module, 'e-performance') !== false) {
                        require_once __DIR__ . '/../modules/e-performance/api/config_perf.php';
                        $stmtPerf = db()->prepare("SELECT id, username, nama_lengkap FROM perf_users WHERE username = ? AND role = 'siswa' AND status = 1 LIMIT 1");
                        $stmtPerf->execute([$username]);
                        $perfUser = $stmtPerf->fetch();
                        
                        if ($perfUser) {
                            $perfToken = perf_generate_token($perfUser['id']);
                            json_response(200, true, 'Login Siswa E-Performance Berhasil', [
                                'is_student_perf' => true,
                                'nama' => $perfUser['nama_lengkap'],
                                'redirect_url' => 'modules/e-performance/index.php?token=' . $perfToken . '&mode=perf'
                            ]);
                        } else {
                            json_response(401, false, 'Anda belum terdaftar di modul E-Performance.');
                        }
                    }

                    // Password matches. Find active exam for E-Xam Card
                    $stmtExam = db()->prepare("
                        SELECT e.id as exam_id, xs.status, xs.suspension_note
                        FROM xam_exams e
                        JOIN xam_exam_classes ec ON ec.exam_id = e.id
                        JOIN xam_exam_students xs ON xs.exam_id = e.id AND xs.student_id = ?
                        WHERE e.academic_year_id = ? AND e.status = 1 AND ec.kelas = ?
                        ORDER BY e.created_at DESC
                        LIMIT 1
                    ");
                    $stmtExam->execute([$student['id'], $student['academic_year_id'], $student['kelas']]);
                    $examInfo = $stmtExam->fetch();

                    if (!$examInfo) {
                        json_response(404, false, 'Belum ada jadwal ujian aktif untuk kelas Anda.');
                    }

                    // Generate temporary token for card viewing
                    $tokenData = [
                        'student_id' => $student['id'],
                        'exam_id' => $examInfo['exam_id'],
                        'exp' => time() + 3600 // 1 hour
                    ];
                    $token = base64_encode(json_encode($tokenData)) . '.' . hash_hmac('sha256', json_encode($tokenData), DB_NAME);

                    json_response(200, true, 'Login Siswa Berhasil', [
                        'is_student' => true,
                        'nama' => $student['nama'],
                        'kelas' => $student['kelas'],
                        'status_ujian' => $examInfo['status'],
                        'suspension_note' => $examInfo['suspension_note'],
                        'redirect_url' => 'modules/e-xam-card/student/view.php?token=' . $token
                    ]);
                }
            }

            // Check perf_users for custom roles (E-Performance)
            $stmtPerfCustom = db()->prepare("SELECT id, username, nama_lengkap, password FROM perf_users WHERE username = ? AND status = 1 LIMIT 1");
            $stmtPerfCustom->execute([$username]);
            $perfCustomUser = $stmtPerfCustom->fetch();
            
            if ($perfCustomUser && password_verify($password, $perfCustomUser['password'])) {
                require_once __DIR__ . '/../modules/e-performance/api/config_perf.php';
                $perfToken = perf_generate_token($perfCustomUser['id']);
                json_response(200, true, 'Login E-Performance Berhasil', [
                    'is_student_perf' => true, // Re-use this flag for frontend redirection logic
                    'nama' => $perfCustomUser['nama_lengkap'],
                    'redirect_url' => 'modules/e-performance/index.php?token=' . $perfToken . '&mode=perf'
                ]);
            }

            // Authentication completely failed
            json_response(401, false, 'Username/NIS atau password salah.');
        }

        // Generate token
        $token = generate_token();
        $expiredAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);

        // Clean only expired sessions (keep valid sessions for other tabs/modules)
        $stmt = db()->prepare("DELETE FROM sessions WHERE expired_at < NOW()");
        $stmt->execute();

        // Create new session
        $stmt = db()->prepare("
            INSERT INTO sessions (user_id, token, ip_address, user_agent, expired_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $token,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiredAt
        ]);

        // Update last login
        $stmt = db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        // Get school settings
        $namaSekolah = get_setting('nama_sekolah', 'E-Portal Sekolah');
        $iconSekolah = get_setting('icon_sekolah', '');
        $kepalaSekolah = get_setting('kepala_sekolah', '');
        $kopSurat = get_setting('kop_surat', '');
        $activeAcademicYear = get_active_academic_year();

        json_response(200, true, 'Login berhasil!', [
            'token'        => $token,
            'user'         => [
                'id'            => $user['id'],
                'username'      => $user['username'],
                'nama_lengkap'  => $user['nama_lengkap'],
                'role'          => $user['role'],
                'avatar'        => $user['avatar']
            ],
            'school' => [
                'nama' => $namaSekolah,
                'icon' => $iconSekolah,
                'kepala_sekolah' => $kepalaSekolah,
                'kop_surat' => $kopSurat
            ],
            'academic_year' => $activeAcademicYear
        ]);

    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Handle Logout
 */
function handleLogout() {
    $token = '';
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    } elseif (isset($_GET['token'])) {
        $token = $_GET['token'];
    }

    if (!empty($token)) {
        try {
            $stmt = db()->prepare("DELETE FROM sessions WHERE token = ?");
            $stmt->execute([$token]);
        } catch (PDOException $e) {
            // Silent fail
        }
    }

    json_response(200, true, 'Logout berhasil.');
}

/**
 * Handle Session Check
 */
function handleCheck() {
    $user = auth_check();
    
    // Get school settings
    $namaSekolah = get_setting('nama_sekolah', 'E-Portal Sekolah');
    $iconSekolah = get_setting('icon_sekolah', '');
    $kepalaSekolah = get_setting('kepala_sekolah', '');
    $kopSurat = get_setting('kop_surat', '');
    $activeAcademicYear = get_active_academic_year();

    json_response(200, true, 'Sesi aktif.', [
        'user'   => $user,
        'school' => [
            'nama' => $namaSekolah,
            'icon' => $iconSekolah,
            'kepala_sekolah' => $kepalaSekolah,
            'kop_surat' => $kopSurat
        ],
        'academic_year' => $activeAcademicYear
    ]);
}
