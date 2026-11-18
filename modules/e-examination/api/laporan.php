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
            $ujian = $stmtU->fetch(PDO::FETCH_ASSOC);
            if (!$ujian) throw new Exception('Ujian tidak ditemukan', 404);
            $isPsikologi = ($ujian['jenis'] === 'psikologi');

            // Cek apakah ujian memiliki soal uraian / esai / jawaban singkat
            $stmtCountUraian = db()->prepare("
                SELECT COUNT(*) 
                FROM exam_soal 
                WHERE bank_soal_id = ? AND tipe_soal IN ('esai', 'essai', 'jawaban_singkat')
            ");
            $stmtCountUraian->execute([$ujian['bank_soal_id']]);
            $countUraian = (int)$stmtCountUraian->fetchColumn();

            // Fetch session status and scores for all students in the assigned class
            $activeYear = get_active_academic_year();
            $yearId = (int)($activeYear['id'] ?? 0);

            $yearFilter = $yearId > 0 ? "AND (s.academic_year_id = {$yearId} OR s.academic_year_id IS NULL OR s.academic_year_id = 0)" : "";

            $stmt = db()->prepare("
                SELECT s.id as student_id, s.nis, s.nama as nama_siswa, s.kelas,
                       es.id as sesi_id, es.status, es.waktu_mulai, es.waktu_selesai, es.nilai_akhir as skor, es.pelanggaran,
                       es.status_koreksi, es.dikoreksi_oleh, es.dikoreksi_pada,
                       u_guru.nama_lengkap as nama_korektor
                FROM exam_ujian_kelas uk
                JOIN students s ON s.kelas COLLATE utf8mb4_unicode_ci = uk.kelas COLLATE utf8mb4_unicode_ci 
                               AND s.status = 1 
                               AND (s.status_siswa = 'Aktif' OR s.status_siswa IS NULL OR s.status_siswa = '')
                               {$yearFilter}
                LEFT JOIN exam_sesi es ON es.student_id = s.id AND es.ujian_id = uk.ujian_id
                LEFT JOIN users u_guru ON es.dikoreksi_oleh = u_guru.id
                WHERE uk.ujian_id = ?
                ORDER BY s.kelas ASC, s.nama ASC
            ");
            $stmt->execute([$ujian_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch breakdown per sesi
            $stmtBreakdown = db()->prepare("
                SELECT 
                    j.sesi_id,
                    SUM(CASE WHEN s.tipe_soal NOT IN ('esai', 'essai', 'jawaban_singkat') THEN j.skor ELSE 0 END) as skor_pg,
                    SUM(CASE WHEN s.tipe_soal NOT IN ('esai', 'essai', 'jawaban_singkat') THEN s.bobot ELSE 0 END) as max_pg,
                    SUM(CASE WHEN s.tipe_soal IN ('esai', 'essai', 'jawaban_singkat') THEN j.skor ELSE 0 END) as skor_uraian,
                    SUM(CASE WHEN s.tipe_soal IN ('esai', 'essai', 'jawaban_singkat') THEN s.bobot ELSE 0 END) as max_uraian,
                    COUNT(CASE WHEN s.tipe_soal IN ('esai', 'essai', 'jawaban_singkat') THEN 1 END) as total_uraian,
                    COUNT(CASE WHEN s.tipe_soal IN ('esai', 'essai', 'jawaban_singkat') AND j.is_koreksi_manual = 1 THEN 1 END) as uraian_dinilai
                FROM exam_jawaban j
                JOIN exam_soal s ON j.soal_id = s.id
                JOIN exam_sesi es ON j.sesi_id = es.id
                WHERE es.ujian_id = ?
                GROUP BY j.sesi_id
            ");
            $stmtBreakdown->execute([$ujian_id]);
            $breakdownList = $stmtBreakdown->fetchAll(PDO::FETCH_ASSOC);
            $breakdownMap = [];
            foreach ($breakdownList as $b) {
                $breakdownMap[$b['sesi_id']] = $b;
            }

            // Resolve psychology profiles if relevant
            $profiles = [];
            if ($isPsikologi) {
                $stmtH = db()->prepare("SELECT * FROM exam_psikologi_hasil WHERE bank_soal_id = ?");
                $stmtH->execute([$ujian['bank_soal_id']]);
                $profiles = $stmtH->fetchAll();
            }

            $totalSelesai = 0;
            $totalPerluKoreksi = 0;
            $totalSudahDikoreksi = 0;
            $totalSkorSelesai = 0;

            foreach ($results as &$r) {
                $sesiId = $r['sesi_id'];
                $r['has_uraian'] = ($countUraian > 0);
                $r['count_uraian'] = $countUraian;
                
                if ($sesiId && isset($breakdownMap[$sesiId])) {
                    $b = $breakdownMap[$sesiId];
                    $r['skor_pg'] = (float)$b['skor_pg'];
                    $r['max_pg'] = (float)$b['max_pg'];
                    $r['skor_uraian'] = (float)$b['skor_uraian'];
                    $r['max_uraian'] = (float)$b['max_uraian'];
                    $r['total_uraian'] = (int)$b['total_uraian'];
                    $r['uraian_dinilai'] = (int)$b['uraian_dinilai'];
                } else {
                    $r['skor_pg'] = 0;
                    $r['max_pg'] = 0;
                    $r['skor_uraian'] = 0;
                    $r['max_uraian'] = 0;
                    $r['total_uraian'] = $countUraian;
                    $r['uraian_dinilai'] = 0;
                }

                // Normalisasi status_koreksi jika belum diset
                if (empty($r['status_koreksi'])) {
                    $r['status_koreksi'] = ($countUraian > 0) ? 'belum_dikoreksi' : 'sudah_dikoreksi';
                }

                if ($r['status'] === 'selesai') {
                    $totalSelesai++;
                    if ($r['skor'] !== null) {
                        $totalSkorSelesai += (float)$r['skor'];
                    }

                    if ($countUraian > 0) {
                        if ($r['status_koreksi'] === 'sudah_dikoreksi') {
                            $totalSudahDikoreksi++;
                        } else {
                            $totalPerluKoreksi++;
                        }
                    } else {
                        $totalSudahDikoreksi++;
                    }
                }

                $r['psikologi_hasil'] = null;
                $r['psikologi_deskripsi'] = null;
                if ($isPsikologi && $r['skor'] !== null) {
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
            unset($r);

            $rataRata = $totalSelesai > 0 ? round($totalSkorSelesai / $totalSelesai, 1) : 0;

            json_response(200, true, 'Data hasil ujian', [
                'ujian' => $ujian,
                'summary' => [
                    'total_peserta'         => count($results),
                    'total_selesai'         => $totalSelesai,
                    'total_perlu_koreksi'   => $totalPerluKoreksi,
                    'total_sudah_dikoreksi' => $totalSudahDikoreksi,
                    'rata_rata'             => $rataRata,
                    'count_uraian'          => $countUraian
                ],
                'results' => $results
            ]);
            break;

        // ==========================================
        // DOWNLOAD EXCEL (CSV)
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

            // Cek apakah ada soal uraian
            $stmtCountUraian = db()->prepare("SELECT COUNT(*) FROM exam_soal WHERE bank_soal_id = ? AND tipe_soal IN ('esai', 'essai', 'jawaban_singkat')");
            $stmtCountUraian->execute([$ujian['bank_soal_id']]);
            $countUraian = (int)$stmtCountUraian->fetchColumn();

            $activeYear = get_active_academic_year();
            $yearId = (int)($activeYear['id'] ?? 0);
            $yearFilter = $yearId > 0 ? "AND (s.academic_year_id = {$yearId} OR s.academic_year_id IS NULL OR s.academic_year_id = 0)" : "";

            $stmt = db()->prepare("
                SELECT s.nis, s.nama as nama_siswa, s.kelas,
                       es.id as sesi_id, es.status, es.waktu_mulai, es.waktu_selesai, es.nilai_akhir as skor, es.pelanggaran,
                       es.status_koreksi, es.dikoreksi_pada, u_guru.nama_lengkap as nama_korektor
                FROM exam_ujian_kelas uk
                JOIN students s ON s.kelas COLLATE utf8mb4_unicode_ci = uk.kelas COLLATE utf8mb4_unicode_ci 
                               AND s.status = 1 
                               AND (s.status_siswa = 'Aktif' OR s.status_siswa IS NULL OR s.status_siswa = '')
                               {$yearFilter}
                LEFT JOIN exam_sesi es ON es.student_id = s.id AND es.ujian_id = uk.ujian_id
                LEFT JOIN users u_guru ON es.dikoreksi_oleh = u_guru.id
                WHERE uk.ujian_id = ?
                ORDER BY s.kelas ASC, s.nama ASC
            ");
            $stmt->execute([$ujian_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch breakdown per sesi
            $stmtBreakdown = db()->prepare("
                SELECT 
                    j.sesi_id,
                    SUM(CASE WHEN s.tipe_soal NOT IN ('esai', 'essai', 'jawaban_singkat') THEN j.skor ELSE 0 END) as skor_pg,
                    SUM(CASE WHEN s.tipe_soal NOT IN ('esai', 'essai', 'jawaban_singkat') THEN s.bobot ELSE 0 END) as max_pg,
                    SUM(CASE WHEN s.tipe_soal IN ('esai', 'essai', 'jawaban_singkat') THEN j.skor ELSE 0 END) as skor_uraian,
                    SUM(CASE WHEN s.tipe_soal IN ('esai', 'essai', 'jawaban_singkat') THEN s.bobot ELSE 0 END) as max_uraian
                FROM exam_jawaban j
                JOIN exam_soal s ON j.soal_id = s.id
                JOIN exam_sesi es ON j.sesi_id = es.id
                WHERE es.ujian_id = ?
                GROUP BY j.sesi_id
            ");
            $stmtBreakdown->execute([$ujian_id]);
            $breakdownList = $stmtBreakdown->fetchAll(PDO::FETCH_ASSOC);
            $breakdownMap = [];
            foreach ($breakdownList as $b) {
                $breakdownMap[$b['sesi_id']] = $b;
            }

            // Fetch psychology range profiles
            $profiles = [];
            if ($isPsikologi) {
                $stmtH = db()->prepare("SELECT * FROM exam_psikologi_hasil WHERE bank_soal_id = ?");
                $stmtH->execute([$ujian['bank_soal_id']]);
                $profiles = $stmtH->fetchAll();
            }

            // Ambil semua soal dari bank soal ujian ini
            $stmtSoal = db()->prepare("SELECT id, tipe_soal, pertanyaan, bobot FROM exam_soal WHERE bank_soal_id = ? ORDER BY urutan ASC, id ASC");
            $stmtSoal->execute([$ujian['bank_soal_id']]);
            $soalList = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);

            // Ambil semua jawaban untuk seluruh sesi ujian ini
            $stmtAllAns = db()->prepare("
                SELECT j.sesi_id, j.soal_id, j.jawaban, j.jawaban_voice, j.skor, j.catatan_guru, j.is_koreksi_manual
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

            // Build CSV
            $filename = "Hasil_Ujian_" . preg_replace('/[^A-Za-z0-9\-]/', '_', $ujian['judul']) . ".csv";
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            // Write UTF-8 BOM so Excel opens indonesian characters properly
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header Info
            fputcsv($output, ['Judul Ujian', $ujian['judul']]);
            fputcsv($output, ['Kelas Peserta', $ujian['kelas_peserta']]);
            fputcsv($output, ['Jenis Ujian', $isPsikologi ? 'Tes Psikologi' : 'Tes Penilaian']);
            fputcsv($output, ['Soal Uraian', $countUraian > 0 ? "Ya ($countUraian Soal)" : 'Tidak']);
            fputcsv($output, []); // blank line

            $headers = ['No', 'NIS', 'Nama Siswa', 'Kelas', 'Status Pengerjaan', 'Status Skoring', 'Waktu Mulai', 'Waktu Selesai'];
            if ($isPsikologi) {
                $headers[] = 'Skor Raw';
                $headers[] = 'Hasil Psikologi';
                $headers[] = 'Deskripsi';
            } else {
                if ($countUraian > 0) {
                    $headers[] = 'Skor PG';
                    $headers[] = 'Skor Uraian';
                }
                $headers[] = 'Nilai Akhir (0-100)';
                if ($countUraian > 0) {
                    $headers[] = 'Korektor';
                    $headers[] = 'Waktu Koreksi';
                }
            }

            foreach ($soalList as $i => $soal) {
                $tipeLabel = in_array($soal['tipe_soal'], ['esai', 'essai', 'jawaban_singkat']) ? 'Uraian' : 'PG';
                $headers[] = "Soal " . ($i + 1) . " [$tipeLabel] (Jawaban)";
                $headers[] = "Soal " . ($i + 1) . " (Skor/" . round($soal['bobot'], 1) . ")";
                if (in_array($soal['tipe_soal'], ['esai', 'essai', 'jawaban_singkat'])) {
                    $headers[] = "Soal " . ($i + 1) . " (Catatan Guru)";
                }
            }

            fputcsv($output, $headers);
            
            $no = 1;
            foreach ($results as $r) {
                $status = 'Belum Mulai';
                if (in_array($r['status'], ['mengerjakan', 'berlangsung'])) $status = 'Sedang Ujian';
                elseif (in_array($r['status'], ['dihentikan', 'didiskualifikasi'])) $status = 'Dihentikan (Curang)';
                elseif ($r['status'] === 'selesai') $status = 'Selesai';

                $statusSkoring = '-';
                if ($r['status'] === 'selesai') {
                    if ($countUraian > 0) {
                        $statusSkoring = ($r['status_koreksi'] === 'sudah_dikoreksi') ? 'Sudah Dikoreksi' : 'Belum Dikoreksi (Perlu Koreksi)';
                    } else {
                        $statusSkoring = 'Otomatis';
                    }
                }

                // Bulatkan skor ke angka integer (tanpa desimal)
                $skor = $r['skor'] !== null ? round($r['skor'], 0) : '';

                $sesi_id = $r['sesi_id'] ?? null;
                $skorPg = ($sesi_id && isset($breakdownMap[$sesi_id])) ? round($breakdownMap[$sesi_id]['skor_pg'], 1) : '-';
                $skorUraian = ($sesi_id && isset($breakdownMap[$sesi_id])) ? round($breakdownMap[$sesi_id]['skor_uraian'], 1) : '-';

                $row = [
                    $no++,
                    $r['nis'],
                    $r['nama_siswa'],
                    $r['kelas'],
                    $status,
                    $statusSkoring,
                    $r['waktu_mulai'] ?: '-',
                    $r['waktu_selesai'] ?: '-'
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
                } else {
                    if ($countUraian > 0) {
                        $row[] = $skorPg;
                        $row[] = $skorUraian;
                    }
                    $row[] = $skor;
                    if ($countUraian > 0) {
                        $row[] = $r['nama_korektor'] ?: '-';
                        $row[] = $r['dikoreksi_pada'] ?: '-';
                    }
                }

                // Tambahkan jawaban dan skor tiap soal untuk peserta ini
                foreach ($soalList as $soal) {
                    $jawabanSiswa = '-';
                    $skorSoal = '0';
                    $catatanSoal = '-';
                    $isUraianSoal = in_array($soal['tipe_soal'], ['esai', 'essai', 'jawaban_singkat']);

                    if ($sesi_id && isset($ansMap[$sesi_id][$soal['id']])) {
                        $ansData = $ansMap[$sesi_id][$soal['id']];
                        $jawabanSiswa = $ansData['jawaban'] ?: ($ansData['jawaban_voice'] ? '[Rekaman Suara]' : '-');
                        $skorSoal = $ansData['skor'] !== null ? round((float)$ansData['skor'], 1) : '0';
                        $catatanSoal = $ansData['catatan_guru'] ?: '-';
                    }

                    $row[] = $jawabanSiswa;
                    $row[] = $skorSoal;
                    if ($isUraianSoal) {
                        $row[] = $catatanSoal;
                    }
                }

                fputcsv($output, $row);
            }
            
            fclose($output);
            exit;

        // ==========================================
        // CETAK LAPORAN (PRINTABLE VIEW)
        // ==========================================
        case 'cetak_laporan':
            $token = $_GET['token'] ?? '';
            if (!$token) die('Token tidak valid');
            
            $stmtAuth = db()->prepare("
                SELECT u.id, u.nama_lengkap, u.role 
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
                SELECT u.*, 
                       (SELECT GROUP_CONCAT(kelas SEPARATOR ', ') FROM exam_ujian_kelas WHERE ujian_id = u.id) as kelas_peserta
                FROM exam_ujian u 
                WHERE u.id = ?
            ");
            $stmtU->execute([$ujian_id]);
            $ujian = $stmtU->fetch();
            if (!$ujian) die('Ujian tidak ditemukan');

            $school_name = get_setting('nama_sekolah', 'E-Portal School');
            $school_address = get_setting('alamat_sekolah', 'Surabaya, Jawa Timur');
            $school_icon = get_setting('icon_sekolah', '');

            // Cek soal uraian
            $stmtCountUraian = db()->prepare("SELECT COUNT(*) FROM exam_soal WHERE bank_soal_id = ? AND tipe_soal IN ('esai', 'essai', 'jawaban_singkat')");
            $stmtCountUraian->execute([$ujian['bank_soal_id']]);
            $countUraian = (int)$stmtCountUraian->fetchColumn();

            $activeYear = get_active_academic_year();
            $yearId = (int)($activeYear['id'] ?? 0);
            $yearFilter = $yearId > 0 ? "AND (s.academic_year_id = {$yearId} OR s.academic_year_id IS NULL OR s.academic_year_id = 0)" : "";

            $stmt = db()->prepare("
                SELECT s.nis, s.nama as nama_siswa, s.kelas,
                       es.id as sesi_id, es.status, es.waktu_mulai, es.waktu_selesai, es.nilai_akhir as skor,
                       es.status_koreksi, u_guru.nama_lengkap as nama_korektor
                FROM exam_ujian_kelas uk
                JOIN students s ON s.kelas COLLATE utf8mb4_unicode_ci = uk.kelas COLLATE utf8mb4_unicode_ci 
                               AND s.status = 1 
                               AND (s.status_siswa = 'Aktif' OR s.status_siswa IS NULL OR s.status_siswa = '')
                               {$yearFilter}
                LEFT JOIN exam_sesi es ON es.student_id = s.id AND es.ujian_id = uk.ujian_id
                LEFT JOIN users u_guru ON es.dikoreksi_oleh = u_guru.id
                WHERE uk.ujian_id = ?
                ORDER BY s.kelas ASC, s.nama ASC
            ");
            $stmt->execute([$ujian_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch breakdown per sesi
            $stmtBreakdown = db()->prepare("
                SELECT 
                    j.sesi_id,
                    SUM(CASE WHEN s.tipe_soal NOT IN ('esai', 'essai', 'jawaban_singkat') THEN j.skor ELSE 0 END) as skor_pg,
                    SUM(CASE WHEN s.tipe_soal IN ('esai', 'essai', 'jawaban_singkat') THEN j.skor ELSE 0 END) as skor_uraian
                FROM exam_jawaban j
                JOIN exam_soal s ON j.soal_id = s.id
                JOIN exam_sesi es ON j.sesi_id = es.id
                WHERE es.ujian_id = ?
                GROUP BY j.sesi_id
            ");
            $stmtBreakdown->execute([$ujian_id]);
            $breakdownMap = [];
            foreach ($stmtBreakdown->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $breakdownMap[$b['sesi_id']] = $b;
            }

            // Hitung statistik
            $totalSiswa = count($results);
            $totalSelesai = 0;
            $totalNilai = 0;
            $nilaiTertinggi = null;
            $nilaiTerendah = null;

            foreach ($results as $r) {
                if ($r['status'] === 'selesai' && $r['skor'] !== null) {
                    $totalSelesai++;
                    $val = (float)$r['skor'];
                    $totalNilai += $val;
                    if ($nilaiTertinggi === null || $val > $nilaiTertinggi) $nilaiTertinggi = $val;
                    if ($nilaiTerendah === null || $val < $nilaiTerendah) $nilaiTerendah = $val;
                }
            }
            $rataRata = $totalSelesai > 0 ? round($totalNilai / $totalSelesai, 1) : 0;
            if ($nilaiTertinggi === null) $nilaiTertinggi = 0;
            if ($nilaiTerendah === null) $nilaiTerendah = 0;

            // Render printable HTML
            header('Content-Type: text/html; charset=UTF-8');
            ?>
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <title>Laporan Hasil Ujian - <?php echo htmlspecialchars($ujian['judul']); ?></title>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; margin: 20px; color: #1e293b; background: #fff; font-size: 13px; }
                    .header-kop { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
                    .header-kop h2 { margin: 0 0 4px 0; font-size: 18px; text-transform: uppercase; letter-spacing: 0.5px; }
                    .header-kop p { margin: 2px 0; font-size: 12px; color: #475569; }
                    .meta-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 16px; font-size: 12px; }
                    .meta-item { display: flex; }
                    .meta-label { width: 140px; font-weight: bold; color: #475569; }
                    .stats-bar { display: flex; gap: 12px; margin-bottom: 16px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
                    .stat-box { flex: 1; text-align: center; }
                    .stat-val { font-size: 16px; font-weight: bold; color: #2563eb; }
                    .stat-lbl { font-size: 11px; color: #64748b; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 12px; }
                    th, td { border: 1px solid #cbd5e1; padding: 6px 8px; }
                    th { background: #f1f5f9; font-weight: 600; text-align: left; }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .badge-status { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; }
                    .badge-green { background: #dcfce7; color: #166534; }
                    .badge-yellow { background: #fef3c7; color: #92400e; }
                    .badge-gray { background: #f1f5f9; color: #475569; }
                    .ttd-box { display: flex; justify-content: flex-end; margin-top: 30px; }
                    .ttd-inner { width: 220px; text-align: center; }
                    @media print {
                        .no-print { display: none !important; }
                        body { margin: 0; }
                    }
                </style>
            </head>
            <body>
                <div class="no-print" style="margin-bottom:16px; text-align:right;">
                    <button onclick="window.print()" style="padding:8px 16px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">🖨️ Cetak / Simpan PDF</button>
                    <button onclick="window.close()" style="padding:8px 16px; background:#e2e8f0; border:none; border-radius:6px; cursor:pointer; margin-left:8px;">Tutup</button>
                </div>

                <div class="header-kop">
                    <h2><?php echo htmlspecialchars($school_name); ?></h2>
                    <p><?php echo htmlspecialchars($school_address); ?></p>
                    <h3 style="margin: 10px 0 0 0; text-decoration: underline;">REKAPITULASI HASIL UJIAN</h3>
                </div>

                <div class="meta-grid">
                    <div class="meta-item"><span class="meta-label">Judul Ujian:</span><span><?php echo htmlspecialchars($ujian['judul']); ?></span></div>
                    <div class="meta-item"><span class="meta-label">Kelas Peserta:</span><span><?php echo htmlspecialchars($ujian['kelas_peserta'] ?: '-'); ?></span></div>
                    <div class="meta-item"><span class="meta-label">Durasi Ujian:</span><span><?php echo (int)$ujian['durasi_menit']; ?> Menit</span></div>
                    <div class="meta-item"><span class="meta-label">Tanggal Cetak:</span><span><?php echo date('d/m/Y H:i'); ?></span></div>
                </div>

                <div class="stats-bar">
                    <div class="stat-box"><div class="stat-val"><?php echo $totalSiswa; ?></div><div class="stat-lbl">Total Peserta</div></div>
                    <div class="stat-box"><div class="stat-val"><?php echo $totalSelesai; ?></div><div class="stat-lbl">Sudah Selesai</div></div>
                    <div class="stat-box"><div class="stat-val"><?php echo $rataRata; ?></div><div class="stat-lbl">Rata-rata Nilai</div></div>
                    <div class="stat-box"><div class="stat-val" style="color:#16a34a;"><?php echo round($nilaiTertinggi, 0); ?></div><div class="stat-lbl">Nilai Tertinggi</div></div>
                    <div class="stat-box"><div class="stat-val" style="color:#dc2626;"><?php echo round($nilaiTerendah, 0); ?></div><div class="stat-lbl">Nilai Terendah</div></div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width:30px;" class="text-center">No</th>
                            <th style="width:90px;">NIS</th>
                            <th>Nama Siswa</th>
                            <th style="width:80px;">Kelas</th>
                            <th style="width:90px;" class="text-center">Status</th>
                            <?php if ($countUraian > 0): ?>
                            <th style="width:110px;" class="text-center">Status Skoring</th>
                            <th style="width:65px;" class="text-center">Skor PG</th>
                            <th style="width:75px;" class="text-center">Skor Uraian</th>
                            <?php endif; ?>
                            <th style="width:80px;" class="text-center">Nilai Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                        <tr><td colspan="<?php echo $countUraian > 0 ? 9 : 6; ?>" class="text-center">Tidak ada data peserta</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($results as $r): 
                                $sesiId = $r['sesi_id'];
                                $skorPg = ($sesiId && isset($breakdownMap[$sesiId])) ? round($breakdownMap[$sesiId]['skor_pg'], 1) : '-';
                                $skorUraian = ($sesiId && isset($breakdownMap[$sesiId])) ? round($breakdownMap[$sesiId]['skor_uraian'], 1) : '-';
                                $skorAkhir = $r['skor'] !== null ? round((float)$r['skor'], 0) : '-';
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($r['nis']); ?></td>
                                <td><strong><?php echo htmlspecialchars($r['nama_siswa']); ?></strong></td>
                                <td><?php echo htmlspecialchars($r['kelas']); ?></td>
                                <td class="text-center">
                                    <?php if ($r['status'] === 'selesai'): ?>
                                        <span class="badge-status badge-green">Selesai</span>
                                    <?php elseif ($r['status'] === 'mengerjakan' || $r['status'] === 'berlangsung'): ?>
                                        <span class="badge-status badge-yellow">Sedang Ujian</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-gray">Belum Mulai</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($countUraian > 0): ?>
                                <td class="text-center">
                                    <?php if ($r['status'] === 'selesai'): ?>
                                        <?php if ($r['status_koreksi'] === 'sudah_dikoreksi'): ?>
                                            <span class="badge-status badge-green">✓ Sudah Dikoreksi</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-yellow">⚠ Belum Skoring</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo $skorPg; ?></td>
                                <td class="text-center"><?php echo $skorUraian; ?></td>
                                <?php endif; ?>
                                <td class="text-center" style="font-weight:bold; font-size:13px;"><?php echo $skorAkhir; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="ttd-box">
                    <div class="ttd-inner">
                        <p>Surabaya, <?php echo date('d F Y'); ?><br>Guru Pengampu,</p>
                        <div style="height:60px;"></div>
                        <p><strong><?php echo htmlspecialchars($auth['nama_lengkap'] ?: 'Guru Mata Pelajaran'); ?></strong></p>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;

        default:
            throw new Exception('Action tidak valid', 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) $code = 500;
    json_response($code, false, $e->getMessage());
}
