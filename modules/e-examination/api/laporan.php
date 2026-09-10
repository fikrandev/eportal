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
            exam_require_admin_or_guru();
            $ujian_id = (int)($_GET['ujian_id'] ?? 0);
            if (!$ujian_id) throw new Exception('Ujian ID tidak valid', 400);

            // Fetch exam details first
            $stmtU = db()->prepare("SELECT judul, jenis, bank_soal_id FROM exam_ujian WHERE id = ?");
            $stmtU->execute([$ujian_id]);
            $ujian = $stmtU->fetch();
            if (!$ujian) throw new Exception('Ujian tidak ditemukan', 404);
            $isPsikologi = ($ujian['jenis'] === 'psikologi');

            // Fetch session status and scores for all students in the assigned class
            $activeYear = get_active_academic_year();
            $yearId = (int)($activeYear['id'] ?? 0);

            $yearFilter = $yearId > 0 ? "AND (s.academic_year_id = {$yearId} OR s.academic_year_id IS NULL OR s.academic_year_id = 0)" : "";

            $stmt = db()->prepare("
                SELECT s.id as student_id, s.nis, s.nama as nama_siswa, s.kelas,
                       es.id as sesi_id, es.status, es.waktu_mulai, es.waktu_selesai, es.nilai_akhir as skor, es.pelanggaran
                FROM exam_ujian_kelas uk
                JOIN students s ON s.kelas COLLATE utf8mb4_unicode_ci = uk.kelas COLLATE utf8mb4_unicode_ci 
                               AND s.status = 1 
                               AND (s.status_siswa = 'Aktif' OR s.status_siswa IS NULL OR s.status_siswa = '')
                               {$yearFilter}
                LEFT JOIN exam_sesi es ON es.student_id = s.id AND es.ujian_id = uk.ujian_id
                WHERE uk.ujian_id = ?
                ORDER BY s.kelas ASC, s.nama ASC
            ");
            $stmt->execute([$ujian_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Resolve psychology profiles if relevant
            if ($isPsikologi) {
                $stmtH = db()->prepare("SELECT * FROM exam_psikologi_hasil WHERE bank_soal_id = ?");
                $stmtH->execute([$ujian['bank_soal_id']]);
                $profiles = $stmtH->fetchAll();
                
                foreach ($results as &$r) {
                    $r['psikologi_hasil'] = null;
                    $r['psikologi_deskripsi'] = null;
                    if ($r['skor'] !== null) {
                        $score = (float)$r['skor'];
                        foreach ($profiles as $p) {
                            if ($score >= (float)$p['rentang_min'] && $score <= (float)$p['rentang_max']) {
                                $r['psikologi_hasil'] = $p['kode_hasil'];
                                $r['psikologi_deskripsi'] = $p['deskripsi'];
                                break;
                            }
                        }
                    }
                }
            }

            json_response(200, true, 'Data hasil ujian', [
                'ujian' => $ujian,
                'results' => $results
            ]);
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
            
            $stmtU = db()->prepare("
                SELECT u.judul, u.jenis, u.bank_soal_id,
                       (SELECT GROUP_CONCAT(kelas SEPARATOR ', ') FROM exam_ujian_kelas WHERE ujian_id = u.id) as kelas_peserta
                FROM exam_ujian u 
                WHERE u.id = ?
            ");
            $stmtU->execute([$ujian_id]);
            $ujian = $stmtU->fetch();

            if (!$ujian) die('Ujian tidak ditemukan');

            $isPsikologi = ($ujian['jenis'] === 'psikologi');

            $activeYear = get_active_academic_year();
            $yearId = (int)($activeYear['id'] ?? 0);
            $yearFilter = $yearId > 0 ? "AND (s.academic_year_id = {$yearId} OR s.academic_year_id IS NULL OR s.academic_year_id = 0)" : "";

            $stmt = db()->prepare("
                SELECT s.nis, s.nama as nama_siswa, s.kelas,
                       es.id as sesi_id, es.status, es.waktu_mulai, es.waktu_selesai, es.nilai_akhir as skor, es.pelanggaran
                FROM exam_ujian_kelas uk
                JOIN students s ON s.kelas COLLATE utf8mb4_unicode_ci = uk.kelas COLLATE utf8mb4_unicode_ci 
                               AND s.status = 1 
                               AND (s.status_siswa = 'Aktif' OR s.status_siswa IS NULL OR s.status_siswa = '')
                               {$yearFilter}
                LEFT JOIN exam_sesi es ON es.student_id = s.id AND es.ujian_id = uk.ujian_id
                WHERE uk.ujian_id = ?
                ORDER BY s.kelas ASC, s.nama ASC
            ");
            $stmt->execute([$ujian_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch psychology range profiles
            $profiles = [];
            if ($isPsikologi) {
                $stmtH = db()->prepare("SELECT * FROM exam_psikologi_hasil WHERE bank_soal_id = ?");
                $stmtH->execute([$ujian['bank_soal_id']]);
                $profiles = $stmtH->fetchAll();
            }

            // Build CSV
            $filename = "Hasil_Ujian_" . preg_replace('/[^A-Za-z0-9\-]/', '_', $ujian['judul']) . ".csv";
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Header
            fputcsv($output, ['Judul Ujian', $ujian['judul']]);
            fputcsv($output, ['Kelas Peserta', $ujian['kelas_peserta']]);
            fputcsv($output, ['Jenis Ujian', $isPsikologi ? 'Tes Psikologi' : 'Tes Penilaian']);
            fputcsv($output, []); // blank line
            
            // Ambil semua soal dari bank soal ujian ini
            $stmtSoal = db()->prepare("SELECT id, pertanyaan FROM exam_soal WHERE bank_soal_id = ? ORDER BY urutan ASC, id ASC");
            $stmtSoal->execute([$ujian['bank_soal_id']]);
            $soalList = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);

            // Ambil semua jawaban untuk seluruh sesi ujian ini agar tidak query di dalam loop
            $stmtAllAns = db()->prepare("
                SELECT j.sesi_id, j.soal_id, j.jawaban, j.skor
                FROM exam_jawaban j
                JOIN exam_sesi s ON j.sesi_id = s.id
                WHERE s.ujian_id = ?
            ");
            $stmtAllAns->execute([$ujian_id]);
            $allAns = $stmtAllAns->fetchAll(PDO::FETCH_ASSOC);
            $ansMap = [];
            foreach ($allAns as $ans) {
                $ansMap[$ans['sesi_id']][$ans['soal_id']] = $ans;
            }

            $headers = ['No', 'NIS', 'Nama Siswa', 'Kelas', 'Status Pengerjaan', 'Waktu Mulai', 'Waktu Selesai'];
            if ($isPsikologi) {
                $headers[] = 'Skor Raw';
                $headers[] = 'Hasil Psikologi';
                $headers[] = 'Deskripsi';
            } else {
                $headers[] = 'Total Nilai (Bulat)';
            }

            foreach ($soalList as $i => $soal) {
                $headers[] = "Soal " . ($i + 1) . " (Jawaban)";
                $headers[] = "Soal " . ($i + 1) . " (Skor)";
            }

            fputcsv($output, $headers);
            
            $no = 1;
            foreach ($results as $r) {
                $status = 'Belum Mulai';
                if (in_array($r['status'], ['mengerjakan', 'berlangsung'])) $status = 'Sedang Ujian';
                elseif (in_array($r['status'], ['dihentikan', 'didiskualifikasi'])) $status = 'Dihentikan (Curang)';
                elseif ($r['status'] === 'selesai') $status = 'Selesai';

                // Bulatkan skor ke angka integer (tanpa desimal)
                $skor = $r['skor'] !== null ? round($r['skor'], 0) : '';

                $row = [
                    $no++,
                    $r['nis'],
                    $r['nama_siswa'],
                    $r['kelas'],
                    $status,
                    $r['waktu_mulai'],
                    $r['waktu_selesai'],
                    $skor
                ];

                if ($isPsikologi) {
                    $hasil = '';
                    $deskripsi = '';
                    if ($r['skor'] !== null) {
                        $score = (float)$r['skor'];
                        foreach ($profiles as $p) {
                            if ($score >= (float)$p['rentang_min'] && $score <= (float)$p['rentang_max']) {
                                $hasil = $p['kode_hasil'];
                                $deskripsi = $p['deskripsi'];
                                break;
                            }
                        }
                    }
                    $row[] = $hasil;
                    $row[] = $deskripsi;
                }

                // Tambahkan jawaban dan skor tiap soal untuk peserta ini
                $sesi_id = $r['sesi_id'] ?? null;
                foreach ($soalList as $soal) {
                    $jawabanSiswa = '-';
                    $skorSoal = '0';
                    if ($sesi_id && isset($ansMap[$sesi_id][$soal['id']])) {
                        $ansData = $ansMap[$sesi_id][$soal['id']];
                        $jawabanSiswa = $ansData['jawaban'] ?: '-';
                        $skorSoal = round((float)$ansData['skor'], 0);
                    }
                    $row[] = $jawabanSiswa;
                    $row[] = $skorSoal;
                }

                fputcsv($output, $row);
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
