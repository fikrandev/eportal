<?php
/**
 * Siswa App Login API
 * Receives POST data (NIS, Tanggal Lahir)
 * Responds with JSON for SPA to handle
 */
require_once __DIR__ . '/../../api/config.php';

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
    elseif (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw_tgl, $m)) {
        $possible_dates[] = $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    elseif (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $raw_tgl, $m)) {
        $possible_dates[] = $m[3] . '-' . $m[2] . '-' . $m[1];
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
        
        foreach ($possible_dates as $tgl) {
            $stmt = db()->prepare("SELECT * FROM students WHERE nis = ? AND tanggal_lahir = ? AND status = 1 LIMIT 1");
            $stmt->execute([$nis, $tgl]);
            $res = $stmt->fetch();
            if ($res) {
                $student = $res;
                $matched_tgl = $tgl;
                break;
            }
        }

        if ($student) {
            // Generate a persistent stateless token
            $secret = 'SISWA_APP_SECRET_2026';
            $hash = md5($student['nis'] . $student['tanggal_lahir'] . $secret);
            $token = base64_encode($student['nis'] . ':' . $hash);
            
            // Set cookie for 10 years (Persistent session)
            setcookie('siswa_token', $token, time() + (86400 * 365 * 10), "/", "", false, false);

            json_response(200, true, 'Login berhasil.', [
                'token' => $token,
                'student' => [
                    'id' => (int)$student['id'],
                    'nama' => $student['nama'],
                    'nis' => $student['nis'],
                    'kelas' => $student['kelas'] ?? '',
                    'jenis_kelamin' => $student['jenis_kelamin'] ?? ''
                ]
            ]);
        } else {
            json_response(401, false, 'NIS atau Tanggal Lahir tidak cocok, atau akun siswa belum aktif.');
        }
    } catch (PDOException $e) {
        json_response(500, false, 'Server Error: ' . $e->getMessage());
    }
}
