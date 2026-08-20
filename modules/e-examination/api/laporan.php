<?php
/**
 * E-Examination — API Laporan & Export
 */
require_once __DIR__ . '/config_exam.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        
        // ==========================================
        // DATA HASIL UJIAN
        // ==========================================
        case 'hasil_ujian':
            exam_require_admin();
            $ujian_id = (int)($_GET['ujian_id'] ?? 0);
            if (!$ujian_id) throw new Exception('Ujian ID tidak valid', 400);

            // Fetch session status and scores for all students in the assigned class
            // Including those who haven't started
            $stmt = db()->prepare("
                SELECT s.id as student_id, s.nis, s.nama as nama_siswa, s.kelas,
                       es.id as sesi_id, es.status, es.waktu_mulai, es.waktu_selesai, es.skor
                FROM exam_ujian_kelas uk
                JOIN students s ON s.kelas = uk.kelas AND s.status = 1
                LEFT JOIN exam_sesi es ON es.student_id = s.id AND es.ujian_id = uk.ujian_id
                WHERE uk.ujian_id = ?
                ORDER BY s.kelas ASC, s.nama ASC
            ");
            $stmt->execute([$ujian_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            json_response(200, true, 'Data hasil ujian', $results);
            break;

        // ==========================================
        // DOWNLOAD EXCEL
        // ==========================================
        case 'download_excel':
            // Verifikasi token lewat GET karena ini dibuka via link <a> target="_blank"
            $token = $_GET['token'] ?? '';
            if (!$token) die('Token tidak valid');
            
            // Verifikasi token
            $stmtAuth = db()->prepare("
                SELECT u.role 
                FROM sessions s JOIN users u ON s.user_id = u.id 
                WHERE s.token = ? AND s.expired_at > NOW()
            ");
            $stmtAuth->execute([$token]);
            $auth = $stmtAuth->fetch();
            if (!$auth || !in_array($auth['role'], ['superadmin', 'user', 'guru'])) {
                die('Akses ditolak');
            }

            $ujian_id = (int)($_GET['ujian_id'] ?? 0);
            
            $stmtU = db()->prepare("SELECT judul, kelas_peserta FROM exam_ujian WHERE id = ?");
            $stmtU->execute([$ujian_id]);
            $ujian = $stmtU->fetch();

            if (!$ujian) die('Ujian tidak ditemukan');

            $stmt = db()->prepare("
                SELECT s.nis, s.nama as nama_siswa, s.kelas,
                       es.status, es.waktu_mulai, es.waktu_selesai, es.skor
                FROM exam_ujian_kelas uk
                JOIN students s ON s.kelas = uk.kelas AND s.status = 1
                LEFT JOIN exam_sesi es ON es.student_id = s.id AND es.ujian_id = uk.ujian_id
                WHERE uk.ujian_id = ?
                ORDER BY s.kelas ASC, s.nama ASC
            ");
            $stmt->execute([$ujian_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Build CSV
            $filename = "Hasil_Ujian_" . preg_replace('/[^A-Za-z0-9\-]/', '_', $ujian['judul']) . ".csv";
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Header
            fputcsv($output, ['Judul Ujian', $ujian['judul']]);
            fputcsv($output, ['Kelas Peserta', $ujian['kelas_peserta']]);
            fputcsv($output, []); // blank line
            fputcsv($output, ['No', 'NIS', 'Nama Siswa', 'Kelas', 'Status Pengerjaan', 'Waktu Mulai', 'Waktu Selesai', 'Skor (1-100)']);
            
            $no = 1;
            foreach ($results as $r) {
                $status = 'Belum Mulai';
                if ($r['status'] === 'mengerjakan') $status = 'Sedang Ujian';
                elseif ($r['status'] === 'dihentikan') $status = 'Dihentikan (Curang)';
                elseif ($r['status'] === 'selesai') $status = 'Selesai';

                $skor = $r['skor'] !== null ? round($r['skor'], 2) : '';

                fputcsv($output, [
                    $no++,
                    $r['nis'],
                    $r['nama_siswa'],
                    $r['kelas'],
                    $status,
                    $r['waktu_mulai'],
                    $r['waktu_selesai'],
                    $skor
                ]);
            }
            
            fclose($output);
            exit;

        default:
            throw new Exception('Action tidak valid', 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) $code = 500;
    json_response($code, false, $e->getMessage());
}
