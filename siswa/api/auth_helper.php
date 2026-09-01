<?php
/**
 * Helper Otentikasi Siswa
 */
require_once __DIR__ . '/../../api/config.php';

function get_current_siswa() {
    $token = null;

    // Check Authorization header first
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $matches = [];
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }

    // Fallback to cookie
    if (!$token && isset($_COOKIE['siswa_token'])) {
        $token = $_COOKIE['siswa_token'];
    }

    if (!$token) {
        return null;
    }

    // Check token logic (Stateless)
    $decoded = base64_decode($token);
    if (!$decoded || strpos($decoded, ':') === false) return null;
    
    list($nis, $hash) = explode(':', $decoded, 2);
    
    try {
        $stmt = db()->prepare("SELECT * FROM students WHERE nis = ? AND status = 1");
        $stmt->execute([$nis]);
        $student = $stmt->fetch();
        
        if ($student) {
            $secret = 'SISWA_APP_SECRET_2026';
            $expectedHash = md5($student['nis'] . $student['tanggal_lahir'] . $secret);
            if ($hash === $expectedHash) {
                return $student;
            }
        }
        return null;
    } catch (PDOException $e) {
        return null;
    }
}

function siswa_auth() {
    $siswa = get_current_siswa();
    if (!$siswa) {
        json_response(401, false, 'Silakan login kembali (Sesi Berakhir).');
    }
    return $siswa;
}
