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
    $tgl_lahir = isset($input['tanggal_lahir']) ? trim($input['tanggal_lahir']) : '';

    if (empty($nis) || empty($tgl_lahir)) {
        json_response(400, false, 'NIS dan Tanggal Lahir wajib diisi.');
    }

    // Jika input berformat YYYYMMDD (8 digit), ubah menjadi YYYY-MM-DD
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $tgl_lahir, $matches)) {
        $tgl_lahir = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }

    try {
        $stmt = db()->prepare("SELECT * FROM students WHERE nis = ? AND tanggal_lahir = ? AND status = 1");
        $stmt->execute([$nis, $tgl_lahir]);
        $student = $stmt->fetch();

        if ($student) {
            // Generate a persistent stateless token
            $secret = 'SISWA_APP_SECRET_2026';
            $hash = md5($student['nis'] . $student['tanggal_lahir'] . $secret);
            $token = base64_encode($student['nis'] . ':' . $hash);
            
            // Set cookie for 10 years (Persistent session)
            setcookie('siswa_token', $token, time() + (86400 * 365 * 10), "/", "", false, true);

            json_response(200, true, 'Login berhasil.', [
                'token' => $token,
                'student' => [
                    'id' => $student['id'],
                    'nama' => $student['nama'],
                    'nis' => $student['nis'],
                    'kelas' => $student['kelas']
                ]
            ]);
        } else {
            json_response(401, false, 'NIS atau Tanggal Lahir salah, atau akun tidak aktif.');
        }
    } catch (PDOException $e) {
        json_response(500, false, 'Server Error: ' . $e->getMessage());
    }
}
