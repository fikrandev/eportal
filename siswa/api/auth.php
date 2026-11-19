<?php
/**
 * Siswa App Login API
 * Receives POST data (NIS, Tanggal Lahir)
 * Responds with JSON for SPA to handle
 */
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $nis = isset($input['nis']) ? trim($input['nis']) : '';
    $raw_tgl = isset($input['tanggal_lahir']) ? trim($input['tanggal_lahir']) : '';

    if (empty($nis) || empty($raw_tgl)) {
        json_response(400, false, 'NIS dan Tanggal Lahir wajib diisi.');
    }

    // Flexible date parsing
    $possible_dates = [];
    
    // 1. If directly YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_tgl)) {
        $possible_dates[] = $raw_tgl;
    }
    // 2. If 8 digits: YYYYMMDD or DDMMYYYY
    if (preg_match('/^(\d{8})$/', $raw_tgl)) {
        // Try as YYYYMMDD
        $possible_dates[] = substr($raw_tgl, 0, 4) . '-' . substr($raw_tgl, 4, 2) . '-' . substr($raw_tgl, 6, 2);
        // Try as DDMMYYYY
        $possible_dates[] = substr($raw_tgl, 4, 4) . '-' . substr($raw_tgl, 2, 2) . '-' . substr($raw_tgl, 0, 2);
    }
    // 3. If DD-MM-YYYY or DD/MM/YYYY
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $raw_tgl, $m)) {
        $possible_dates[] = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    // 4. If YYYY/MM/DD
    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $raw_tgl, $m)) {
        $possible_dates[] = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }
    
    // Fallback: try strtotime
    $ts = strtotime($raw_tgl);
    if ($ts) {
        $possible_dates[] = date('Y-m-d', $ts);
    }
    
    $possible_dates = array_unique(array_filter($possible_dates));
    if (empty($possible_dates)) {
        $possible_dates[] = $raw_tgl;
    }

    try {
        $student = null;
        $matched_tgl = null;
        
        $activeYear = get_active_academic_year();
        $activeYearId = (int)($activeYear['id'] ?? 0);

        foreach ($possible_dates as $tgl) {
            if ($tgl === '0000-00-00' || $tgl === '') continue;
            $sql = "SELECT * FROM students WHERE nis = ? AND tanggal_lahir = ? AND status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '')";
            $params = [$nis, $tgl];
            if ($activeYearId > 0) {
                $sql .= " ORDER BY (academic_year_id = ?) DESC, academic_year_id DESC, id DESC LIMIT 1";
                $params[] = $activeYearId;
            } else {
                $sql .= " ORDER BY academic_year_id DESC, id DESC LIMIT 1";
            }
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetch();
            if ($res) {
                $student = $res;
                $matched_tgl = $tgl;
                break;
            }
        }

        if ($student) {
            // Generate a cryptographic HMAC token and store in student_sessions
            $sig = hash_hmac('sha256', $student['nis'] . '|' . $student['tanggal_lahir'], app_secret('siswa_auth'));
            $token = base64_encode($student['nis'] . ':' . $sig);

            try {
                $stmtSess = db()->prepare("
                    INSERT INTO student_sessions (token, student_id, nis, expired_at)
                    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 365 DAY))
                    ON DUPLICATE KEY UPDATE student_id = VALUES(student_id), expired_at = VALUES(expired_at)
                ");
                $stmtSess->execute([substr($token, 0, 128), (int)$student['id'], $student['nis']]);
            } catch (Exception $e) {}

            // Set cookie with HttpOnly + SameSite=Lax
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 0) == 443);
            setcookie('siswa_token', $token, [
                'expires'  => time() + (86400 * 365),
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            $photoInfo = siswa_resolve_photo($student);

            json_response(200, true, 'Login berhasil.', [
                'token' => $token,
                'student' => [
                    'id' => (int)$student['id'],
                    'nama' => $student['nama'],
                    'nis' => $student['nis'],
                    'nisn' => $student['nisn'] ?? '',
                    'kelas' => $student['kelas'] ?? '',
                    'jenis_kelamin' => $student['jenis_kelamin'] ?? '',
                    'foto_path' => $photoInfo['foto_path'],
                    'foto_url' => $photoInfo['foto_url']
                ]
            ]);
        } else {
            json_response(401, false, 'NIS atau Tanggal Lahir tidak cocok, atau akun siswa belum aktif.');
        }
    } catch (PDOException $e) {
        json_response(500, false, 'Server Error: ' . $e->getMessage());
    }
}
