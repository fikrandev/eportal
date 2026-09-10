<?php
/**
 * Helper Otentikasi Siswa & Utilities
 * Multi-layer token inspection for maximum compatibility across Apache, Nginx, LiteSpeed, and iOS PWA
 */
require_once __DIR__ . '/../../api/config.php';

function get_current_siswa() {
    $token = null;

    // 1. Check direct server variables first (Apache RewriteRule, FastCGI, Nginx)
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $token = $matches[1];
        }
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s(\S+)/i', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $matches)) {
            $token = $matches[1];
        }
    }

    // 2. Check getallheaders / apache_request_headers (case-insensitive)
    if (!$token) {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        }
        if (is_array($headers)) {
            foreach ($headers as $key => $val) {
                if (strtolower($key) === 'authorization') {
                    if (preg_match('/Bearer\s(\S+)/i', $val, $matches)) {
                        $token = $matches[1];
                        break;
                    }
                }
            }
        }
    }

    // 3. Fallback to cookie
    if (!$token && !empty($_COOKIE['siswa_token'])) {
        $token = $_COOKIE['siswa_token'];
    }

    // 4. Fallback to query / post parameter
    if (!$token && !empty($_REQUEST['token'])) {
        $token = trim($_REQUEST['token']);
    }

    if (!$token) {
        return null;
    }

    // Token logic (Stateless NIS:Hash)
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

if (!function_exists('format_tanggal')) {
    function format_tanggal($dateStr) {
        if (empty($dateStr)) return '-';
        $ts = strtotime($dateStr);
        if (!$ts) return $dateStr;
        
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $days = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
        ];
        
        $dayName = $days[date('l', $ts)] ?? '';
        $m = (int)date('n', $ts);
        $monthName = $months[$m] ?? date('M', $ts);
        
        return $dayName . ', ' . date('j', $ts) . ' ' . $monthName . ' ' . date('Y', $ts);
    }
}
