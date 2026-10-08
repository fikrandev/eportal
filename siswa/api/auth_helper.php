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
        $activeYear = get_active_academic_year();
        $activeYearId = (int)($activeYear['id'] ?? 0);

        $sql = "SELECT * FROM students WHERE nis = ? AND status = 1";
        $params = [$nis];
        if ($activeYearId > 0) {
            $sql .= " ORDER BY (academic_year_id = ?) DESC, academic_year_id DESC, id DESC LIMIT 1";
            $params[] = $activeYearId;
        } else {
            $sql .= " ORDER BY academic_year_id DESC, id DESC LIMIT 1";
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
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

/**
 * Resolve student photo on disk and return valid relative path and web URL.
 * Checks student's foto_path and candidate photo upload paths by NIS.
 */
function siswa_resolve_photo($student) {
    if (!$student || !is_array($student)) {
        return ['foto_path' => '', 'foto_url' => ''];
    }

    $fotoPath = trim((string)($student['foto_path'] ?? ''));
    $nis = trim((string)($student['nis'] ?? ''));
    $root = realpath(__DIR__ . '/../../') ?: dirname(dirname(__DIR__));
    $normRoot = str_replace('\\', '/', $root);

    // 1. Direct path check from database
    if (!empty($fotoPath)) {
        $clean = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($fotoPath, '/\\'));
        $full = $root . DIRECTORY_SEPARATOR . $clean;
        if (file_exists($full)) {
            $normFull = str_replace('\\', '/', realpath($full) ?: $full);
            $rel = (strpos($normFull, $normRoot) === 0) ? substr($normFull, strlen($normRoot)) : $clean;
            $rel = ltrim(str_replace('\\', '/', $rel), '/');
            return [
                'foto_path' => $rel,
                'foto_url' => BASE_URL . $rel
            ];
        }
    }

    // 2. Candidate paths by NIS in common folders
    if (!empty($nis)) {
        $cleanNis = preg_replace('/[^A-Za-z0-9_-]/', '', $nis);
        $candidates = [
            'uploads/students/photos/' . $cleanNis . '.jpg',
            'uploads/students/photos/' . $cleanNis . '.png',
            'uploads/students/photos/' . $cleanNis . '.jpeg',
            'uploads/students/photos/' . $cleanNis . '.webp',
            'uploads/students/' . $cleanNis . '.jpg',
            'uploads/students/' . $cleanNis . '.png',
            'uploads/students/' . $cleanNis . '.jpeg',
            'uploads/students/' . $cleanNis . '.webp',
            'uploads/students/graduation/' . $cleanNis . '.png',
            'uploads/students/graduation/' . $cleanNis . '.jpg',
        ];

        foreach ($candidates as $cand) {
            $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cand);
            if (file_exists($full)) {
                // Auto-sync / heal database if path was incorrect
                if (!empty($student['id']) && $fotoPath !== $cand) {
                    try {
                        $upStmt = db()->prepare("UPDATE students SET foto_path = ? WHERE id = ?");
                        $upStmt->execute([$cand, (int)$student['id']]);
                    } catch (Exception $e) {}
                }
                return [
                    'foto_path' => $cand,
                    'foto_url' => BASE_URL . $cand
                ];
            }
        }
    }

    return [
        'foto_path' => '',
        'foto_url' => ''
    ];
}

