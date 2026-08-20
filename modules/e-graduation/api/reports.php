<?php
/**
 * E-Graduation reports API.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/simple_pdf.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'meta':
        reportMeta(grad_auth());
        break;
    case 'status':
        reportStatus(grad_auth());
        break;
    case 'download-skl':
        downloadSkl(grad_auth());
        break;
    case 'download-leger':
        downloadLeger(grad_auth());
        break;
    case 'student-download-skl':
        downloadStudentSkl();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function downloadStudentSkl()
{
    try {
        @ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
        $studentAuth = grad_student_auth_for_reports();
        $yearId = (int) $studentAuth['academic_year_id'];
        $studentId = (int) $studentAuth['student_id'];
        $stmt = db()->prepare("
            SELECT s.*, l.letter_number, l.graduation_date, l.signing_date, l.headmaster_name, l.headmaster_niy, l.headmaster_position
            FROM students s
            LEFT JOIN grad_student_letters l ON l.student_id = s.id AND l.academic_year_id = s.academic_year_id
            WHERE s.id = ? AND s.academic_year_id = ?
        ");
        $stmt->execute([$studentId, $yearId]);
        $student = $stmt->fetch();
        if (!$student) {
            http_response_code(404);
            echo 'Data siswa tidak ditemukan.';
            exit;
        }

        $announcement = getSklSetting($yearId);
        $mode = announcementMode($announcement);
        if ($mode !== 'published') {
            http_response_code(403);
            echo 'SKL belum dapat diunduh sebelum pengumuman kelulusan dibuka.';
            exit;
        }

        $school = get_setting('nama_sekolah', 'E-Portal Sekolah');
        $active = get_active_academic_year();
        $scores = getScoresMap($yearId);
        $pdf = new GraduationSimplePdf();
        renderSklPdfPage($pdf, $student, $school, $active, $announcement, $yearId, $scores);
        $content = $pdf->output();
        $filename = 'SKL-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $student['nis']) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'Server error: ' . $e->getMessage();
        exit;
    }
}

function grad_student_auth_for_reports()
{
    $token = sanitize($_GET['student_token'] ?? $_GET['token'] ?? '');
    if ($token === '') {
        http_response_code(401);
        echo 'Token siswa diperlukan.';
        exit;
    }
    $stmt = db()->prepare("
        SELECT a.id as account_id, a.student_id, a.academic_year_id
        FROM grad_student_sessions ss
        JOIN grad_student_accounts a ON a.id = ss.account_id
        WHERE ss.token = ? AND ss.expired_at > NOW() AND a.status = 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(401);
        echo 'Sesi siswa telah berakhir.';
        exit;
    }
    return $row;
}

function announcementMode($setting)
{
    $status = $setting['announcement_status'] ?? 'not_set';
    $at = $setting['announcement_at'] ?? null;
    if ($status === 'published') return 'published';
    if ($status === 'scheduled' && $at) {
        return strtotime($at) <= time() ? 'published' : 'countdown';
    }
    return 'not_set';
}

function reportMeta($user)
{
    try {
        $yearId = grad_active_year_id();
        json_response(200, true, 'Meta laporan berhasil dimuat.', [
            'classes' => getClasses($yearId, $user),
            'students' => getStudents($yearId, $user),
            'academic_year' => get_active_academic_year()
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function reportStatus($user)
{
    $kelas = sanitize($_GET['kelas'] ?? '');
    if ($kelas === '') {
        json_response(400, false, 'Pilih kelas terlebih dahulu.');
    }

    try {
        $yearId = grad_active_year_id();
        grad_require_class_access($user, $kelas);
        $totalStudents = countStudentsByClass($yearId, $kelas);
        $subjects = getSubjectsForClass($yearId, $kelas);
        $rows = [];

        foreach ($subjects as $subject) {
            $stmt = db()->prepare("
                SELECT COUNT(DISTINCT sc.student_id)
                FROM grad_student_scores sc
                JOIN students st ON st.id = sc.student_id
                WHERE sc.academic_year_id = ?
                  AND sc.subject_id = ?
                  AND st.kelas = ?
                  AND sc.nilai_akhir IS NOT NULL
            ");
            $stmt->execute([$yearId, $subject['id'], $kelas]);
            $filled = (int) $stmt->fetchColumn();
            $status = 'Belum Diisi';
            if ($totalStudents > 0 && $filled >= $totalStudents) {
                $status = 'Lengkap';
            } elseif ($filled > 0) {
                $status = 'Proses';
            }

            $rows[] = [
                'subject_id' => $subject['id'],
                'kode_mapel' => $subject['kode_mapel'],
                'nama_mapel' => $subject['nama_mapel'],
                'group_nama' => $subject['group_nama'],
                'group_tipe' => $subject['group_tipe'],
                'kelas' => $subject['kelas'],
                'total_siswa' => $totalStudents,
                'terisi' => $filled,
                'kurang' => max(0, $totalStudents - $filled),
                'status' => $status
            ];
        }

        json_response(200, true, 'Status pengisian nilai berhasil dimuat.', [
            'kelas' => $kelas,
            'total_siswa' => $totalStudents,
            'items' => $rows
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function downloadSkl($user)
{
    try {
        @ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $yearId = grad_active_year_id();
        $scope = sanitize($_GET['scope'] ?? 'student');
        $students = resolveStudentsForScope($yearId, $scope, $user);
        if (!$students) {
            http_response_code(404);
            echo 'Data siswa tidak ditemukan. (scope=' . $scope . ', yearId=' . $yearId . ')';
            exit;
        }

        $school = get_setting('nama_sekolah', 'E-Portal Sekolah');
        $headNameSetting = get_setting('kepala_sekolah', '');
        $active = get_active_academic_year();
        $setting = getSklSetting($yearId);
        $scores = getScoresMap($yearId);
        $kopPath = absoluteLocalPath(($setting['kop_image'] ?? '') ?: get_setting('kop_surat', ''));
        $subjectsCache = [];
        $pdf = new GraduationSimplePdf();
        foreach ($students as $student) {
            renderSklPdfPage($pdf, $student, $school, $active, $setting, $yearId, $scores, $kopPath, $headNameSetting, $subjectsCache);
        }
        $content = $pdf->output();

        $filename = 'SKL-' . date('Ymd-His') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo 'Server error: ' . $e->getMessage();
        exit;
    }
}

function renderSklPdfPage($pdf, $student, $school, $active, $setting, $yearId, $scores, $kopPath = null, $headNameSetting = null, &$subjectsCache = [])
{
    $pdf->addPage();
    $m = 42.52; // Side margins 1.5 cm
    $topMargin = 14.17; // Top margin 0.5 cm
    $w = $pdf->width();
    $contentW = $w - ($m * 2);
    $y = $topMargin;

    if ($kopPath === null) {
        $kopPath = absoluteLocalPath(($setting['kop_image'] ?? '') ?: get_setting('kop_surat', ''));
    }
    if ($kopPath) {
        $dims = $pdf->imageDimensions($kopPath);
        $kopH = 76;
        if ($dims) {
            $kopH = $contentW * $dims['height'] / max(1, $dims['width']);
        }
        if ($pdf->image($kopPath, $m, $y, $contentW, 0)) {
            $y += $kopH + 6;
        }
    } else {
        $pdf->text($m, $y + 18, $school, 18, 'F2', 'center', $contentW);
        $pdf->text($m, $y + 36, 'SURAT KETERANGAN LULUS', 13, 'F1', 'center', $contentW);
        $y += 54;
    }

    // Garis pemisah bawah kop dinonaktifkan agar bisa menyatu penuh dengan gambar kop surat.
    $y += 24;

    $dots = '.........................';
    $letter = trim((string) ($student['letter_number'] ?? ''));
    $graduationDate = indoDate($student['graduation_date']);
    $signingDate = indoDate($student['signing_date'] ?: $student['graduation_date']);
    $headName = ($headNameSetting !== null && $headNameSetting !== '') ? $headNameSetting : (get_setting('kepala_sekolah', '') ?: ($student['headmaster_name'] ?: 'Kepala Sekolah'));
    $headPosition = $student['headmaster_position'] ?: 'Kepala Sekolah';
    $year = $active['tahun_ajaran'] ?? '-';
    $decisionSettingNumber = trim((string) ($setting['decision_number'] ?? ''));
    $decisionNumber = $decisionSettingNumber !== '' ? $decisionSettingNumber : ($letter !== '' ? $letter : $dots);
    $decisionDate = !empty($setting['decision_date']) ? indoDate($setting['decision_date']) : $dots;
    $decisionAbout = firstFilled($setting['decision_about'] ?? '', $dots);
    $city = firstFilled($setting['skl_city'] ?? '', 'Kota Surabaya');
    $npsn = firstFilled(get_setting('npsn_sekolah', get_setting('npsn', '')), '20532108');
    $province = firstFilled(get_setting('provinsi_sekolah', get_setting('provinsi', '')), 'Jawa Timur');

    // Margin khusus area atas saja, supaya seimbang tanpa mengubah jarak blok bawah.
    $upperInset = 6;
    $upperX = $m + $upperInset;
    $upperW = $contentW - ($upperInset * 2);

    $title = 'SURAT KETERANGAN LULUS';
    // Gunakan center alignment bawaan PDF untuk posisi teks yang akurat.
    $pdf->text($m, $y, $title, 15, 'F2', 'center', $contentW);
    // Garis bawah judul: gunakan textWidth yang sama persis dengan centering teks.
    $tw = $pdf->textWidth($title, 15);
    $titleLineX1 = $m + max(0, ($contentW - $tw) / 2) - 2;
    $titleLineX2 = $titleLineX1 + $tw + 1;
    $pdf->line($titleLineX1, $y + 3, $titleLineX2, $y + 3, .95);
    $y += 17;
    $numberLine = $letter !== '' ? ('Nomor : ' . $letter) : 'Nomor :';
    $pdf->text($m, $y, $numberLine, 12, 'F1', 'center', $contentW);
    $y += 31;

    $introLine1 = 'Yang bertanda tangan di bawah ini Kepala ' . $school . ', Nomor Pokok Sekolah Nasional:';
    $introLine2 = $npsn . ', ' . $city . ' Provinsi ' . $province . ', dengan ini menerangkan bahwa :';
    $introX = $m;
    $introFontSize = 13.25;
    while ($introFontSize > 9.5 && $pdf->textWidth($introLine1, $introFontSize) > $contentW) {
        $introFontSize -= 0.25;
    }
    $pdf->justifyLine($introX, $y, $introLine1, $contentW, $introFontSize, 'F1');
    $y += 14;
    $pdf->text($introX, $y, $introLine2, 12, 'F1', 'left');
    $y += 14;
    $y += 10;

    $labelW = 178;
    $colonW = 14;
    $rows = [
        ['nama', (string) ($student['nama'] ?? ''), false],
        ['tempat dan tanggal lahir', formatBirthPlaceDate($student), false],
        ['nomor induk siswa', $student['nis'], false],
        ['nomor induk siswa nasional', $student['nisn'], false],
        ['kelas', $student['kelas'], false],
    ];
    foreach ($rows as $row) {
        $pdf->text($m, $y, $row[0], 12, 'F1');
        $pdf->text($m + $labelW, $y, ':', 12, 'F1');
        $pdf->text($m + $labelW + $colonW, $y, $row[1], 12, $row[2] ? 'F2' : 'F1');
        $y += 14;
    }
    $y += 12;

    $decisionDateLabel = ($graduationDate && $graduationDate !== '-') ? $graduationDate : $decisionDate;
    $decisionAboutLabel = 'Penetapan Kelulusan';
    $decisionLine1 = 'Berdasarkan Keputusan Kepala ' . $school . ' Nomor : ' . $decisionNumber;
    $decisionLine2Prefix = 'Tanggal : ' . $decisionDateLabel . ', Tentang ' . $decisionAboutLabel . ', maka yang bersangkutan dinyatakan ';
    $decisionLine2Bold = 'LULUS';
    $decisionLine3 = 'dengan hasil sebagai berikut:';
    // Samakan ukuran font blok keputusan agar konsisten.
    $decisionFontSize = 12.0;
    while ($decisionFontSize > 10.0 && $pdf->textWidth($decisionLine1, $decisionFontSize) > $contentW) {
        $decisionFontSize -= 0.25;
    }
    $pdf->justifyLine($m, $y, $decisionLine1, $contentW, $decisionFontSize, 'F1');
    $y += 14;
    $line2PrefixW = $pdf->textWidth($decisionLine2Prefix, $decisionFontSize);
    $line2BoldW = $pdf->textWidth($decisionLine2Bold, $decisionFontSize);
    $line2TotalW = $line2PrefixW + $line2BoldW;
    while ($decisionFontSize > 10.0 && $line2TotalW > $contentW) {
        $decisionFontSize -= 0.25;
        $line2PrefixW = $pdf->textWidth($decisionLine2Prefix, $decisionFontSize);
        $line2BoldW = $pdf->textWidth($decisionLine2Bold, $decisionFontSize);
        $line2TotalW = $line2PrefixW + $line2BoldW;
    }
    // Distribusikan jarak antar kata agar rata kiri-kanan pas di margin.
    $decisionLine2Full = trim($decisionLine2Prefix . $decisionLine2Bold);
    renderDistributedSpacingLineWithBoldWord($pdf, $m, $y, $contentW, $decisionLine2Full, $decisionLine2Bold, $decisionFontSize, 0.0);
    $y += 14;
    $pdf->text($m, $y, $decisionLine3, $decisionFontSize, 'F1', 'left');
    $y += 16;

    $y = renderSklPdfSubjectTable($pdf, $yearId, $student, $scores, $y, $subjectsCache);
    $y += 13;

    $footerLine1 = 'Surat Keterangan ini bersifat sementara sampai dikeluarkannya Ijazah Tahun Pelajaran ' . $year . '.';
    $footerLine1Size = 12.0;
    $pdf->text($m, $y, $footerLine1, $footerLine1Size, 'F1', 'left');
    $y += 14;
    $footerLine2A = 'Demikian Surat Keterangan ini diberikan agar dapat dipergunakan sebagaimana mestinya. Apabila';
    $footerLine2B = 'di kemudian hari terdapat kekeliruan, maka akan dilakukan perbaikan,';
    $footerLine2Size = 12.0;
    // Pakai lebar konten penuh dan algoritma gap adaptif agar tetap rata tanpa melewati margin.
    renderDistributedSpacingLineWithBoldWord($pdf, $m, $y, $contentW, $footerLine2A, '', $footerLine2Size, 0.0);
    $y += 14;
    $pdf->text($m, $y, $footerLine2B, $footerLine2Size, 'F1', 'left');
    $y += 14;
    $y += 25;

    $photoPath = absoluteLocalPath($student['foto_path'] ?? '');
    $photoMaxW = 85; // 3 cm ≈ 85 pt
    $photoW = $photoMaxW;
    $photoH = $photoMaxW * 4 / 3; // default 4:3 ratio fallback
    if ($photoPath) {
        $imgSize = @getimagesize($photoPath);
        if ($imgSize && $imgSize[0] > 0 && $imgSize[1] > 0) {
            $photoW = $photoMaxW;
            $photoH = ($imgSize[1] / $imgSize[0]) * $photoW;
        }
    }
    $pdf->rect($m + 75, $y, $photoW, $photoH);
    if ($photoPath) {
        $pdf->image($photoPath, $m + 75, $y, $photoW, $photoH);
    } else {
        $pdf->text($m + 75, $y + ($photoH / 2) + 4, 'Foto Berwarna', 11, 'F1', 'center', $photoW);
    }

    $signX = $w - $m - 230;
    $signDateLabel = ($signingDate && $signingDate !== '-') ? $signingDate : '................';
    $pdf->text($signX, $y + 12, $city . ', ' . $signDateLabel, 12, 'F1');
    $pdf->text($signX, $y + 29, 'Kepala Sekolah,', 12, 'F1');

    $verifyUrl = verificationUrl($student, $yearId);
    $qrPath = qrImagePathLocal($verifyUrl);
    $qrSize = 68;
    $qrTop = $y + 48;
    $nameY = $qrTop + $qrSize + 16;
    if ($qrPath) {
        $pdf->image($qrPath, $signX, $qrTop, $qrSize, $qrSize);
        @unlink($qrPath);
    } else {
        $pdf->rect($signX, $qrTop, $qrSize, $qrSize);
        $pdf->text($signX, $qrTop + 38, 'QR', 14, 'F2', 'center', $qrSize);
    }
    $pdf->text($signX, $nameY, $headName, 12, 'F1');
    $pdf->line($signX, $nameY + 3, $signX + max(78, $pdf->textWidth($headName, 12)), $nameY + 3, .6);
}

function renderSklPdfSubjectTable($pdf, $yearId, $student, $scores, $y, &$subjectsCache = [])
{
    $kelas = $student['kelas'];
    if (!isset($subjectsCache[$kelas])) {
        $subjectsCache[$kelas] = getSubjectsForClass($yearId, $kelas);
    }
    $subjects = $subjectsCache[$kelas];
    $tableW = 420;
    $x = ($pdf->width() - $tableW) / 2;
    $noW = 42;
    $scoreW = 54;
    $mapelW = $tableW - $noW - $scoreW;
    $rowH = 14;

    $pdf->rect($x, $y, $noW, $rowH);
    $pdf->rect($x + $noW, $y, $mapelW, $rowH);
    $pdf->rect($x + $noW + $mapelW, $y, $scoreW, $rowH);
    $pdf->text($x, $y + 10, 'No', 11, 'F1', 'center', $noW);
    $pdf->text($x + $noW, $y + 10, 'Mata Pelajaran', 11, 'F1', 'center', $mapelW);
    $pdf->text($x + $noW + $mapelW, $y + 10, 'Nilai', 11, 'F1', 'center', $scoreW);
    $y += $rowH;

    if (!$subjects) {
        $pdf->rect($x, $y, $tableW, $rowH);
        $pdf->text($x, $y + 10, 'Belum ada mata pelajaran untuk kelas ini.', 11, 'F1', 'center', $tableW);
        return $y + $rowH;
    }

    $sections = [
        'wajib' => ['title' => 'A. Kelompok Mata Pelajaran Wajib', 'items' => []],
        'pilihan' => ['title' => 'B. Kelompok Mata Pelajaran Pilihan', 'items' => []],
        'lainnya' => ['title' => 'C. Muatan Lokal', 'items' => []]
    ];
    foreach ($subjects as $subject) {
        $type = strtolower(trim((string) ($subject['group_tipe'] ?? '')));
        if ($type === 'wajib') {
            $sections['wajib']['items'][] = $subject;
        } elseif ($type === 'pilihan') {
            $sections['pilihan']['items'][] = $subject;
        } else {
            $sections['lainnya']['items'][] = $subject;
        }
    }

    $sum = 0;
    $count = 0;
    foreach ($sections as $section) {
        if (!$section['items']) {
            continue;
        }
        $title = $section['title'];
        $pdf->rect($x, $y, $tableW, $rowH);
        $pdf->text($x + 6, $y + 10, $title, 11, 'F2');
        $y += $rowH;

        foreach ($section['items'] as $idx => $subject) {
            $key = $student['id'] . ':' . $subject['id'];
            $score = $scores[$key] ?? '';
            if ($score !== '' && $score !== null) {
                $sum += (float) $score;
                $count++;
            }
            $pdf->rect($x, $y, $noW, $rowH);
            $pdf->rect($x + $noW, $y, $mapelW, $rowH);
            $pdf->rect($x + $noW + $mapelW, $y, $scoreW, $rowH);
            $pdf->text($x, $y + 10, (string) ($idx + 1), 11, 'F1', 'center', $noW);
            $pdf->text($x + $noW + 8, $y + 10, $subject['nama_mapel'], 11, 'F1');
            $pdf->text($x + $noW + $mapelW, $y + 10, formatScore($score), 11, 'F1', 'center', $scoreW);
            $y += $rowH;
        }
    }

    $average = (isset($student['rata_rata']) && $student['rata_rata'] !== '' && $student['rata_rata'] !== null) 
        ? number_format((float) $student['rata_rata'], 2, ',', '.') 
        : ($count > 0 ? number_format($sum / $count, 2, ',', '.') : '');
    $pdf->rect($x, $y, $noW + $mapelW, $rowH);
    $pdf->rect($x + $noW + $mapelW, $y, $scoreW, $rowH);
    $pdf->text($x, $y + 10, 'Rata-rata', 11, 'F1', 'right', $noW + $mapelW - 8);
    $pdf->text($x + $noW + $mapelW, $y + 10, $average, 11, 'F1', 'center', $scoreW);
    return $y + $rowH;
}

function downloadLeger($user)
{
    try {
        $yearId = grad_active_year_id();
        $scope = sanitize($_GET['scope'] ?? 'class');
        $kelas = sanitize($_GET['kelas'] ?? '');
        $students = resolveStudentsForScope($yearId, $scope === 'all' ? 'all' : 'class', $user);
        if (!$students) {
            http_response_code(404);
            echo 'Data siswa tidak ditemukan.';
            exit;
        }

        $subjects = [];
        if ($scope === 'all') {
            $subjects = getAllSubjects($yearId, $user);
        } else {
            grad_require_class_access($user, $kelas);
            $subjects = getSubjectsForClass($yearId, $kelas);
        }
        $scores = getScoresMap($yearId);
        $filename = 'Leger-Nilai-Akhir-' . date('Ymd-His') . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>";
        echo "table{border-collapse:collapse;width:100%} th,td{border:1px solid #222;padding:6px;font-family:Arial,sans-serif;font-size:12px} th{background:#e8f1ff}.center{text-align:center}.left{text-align:left}.title{font-size:16px;font-weight:bold;border:none}";
        echo "</style></head><body>";
        echo "<table>";
        echo "<tr><td class='title' colspan='" . (5 + max(1, count($subjects))) . "'>LEGER NILAI AKHIR</td></tr>";
        echo "<tr><td colspan='" . (5 + max(1, count($subjects))) . "'>Kelas: " . h($scope === 'all' ? 'Semua Kelas' : $kelas) . "</td></tr>";
        echo "<tr><th>No</th><th>NIS</th><th>NISN</th><th>Nama</th><th>Kelas</th>";
        foreach ($subjects as $subject) {
            echo "<th>" . h($subject['nama_mapel']) . "</th>";
        }
        if (!count($subjects)) {
            echo "<th>Belum ada mapel</th>";
        }
        echo "</tr>";

        foreach ($students as $idx => $student) {
            echo "<tr><td class='center'>" . ($idx + 1) . "</td><td>" . h($student['nis']) . "</td><td>" . h($student['nisn']) . "</td><td>" . h($student['nama']) . "</td><td>" . h($student['kelas']) . "</td>";
            foreach ($subjects as $subject) {
                $key = $student['id'] . ':' . $subject['id'];
                echo "<td class='center'>" . h($scores[$key] ?? '') . "</td>";
            }
            if (!count($subjects)) {
                echo "<td></td>";
            }
            echo "</tr>";
        }

        echo "</table></body></html>";
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'Server error: ' . $e->getMessage();
        exit;
    }
}

function resolveStudentsForScope($yearId, $scope, $user)
{
    $params = [$yearId];
    $where = 'WHERE a.academic_year_id = ? AND a.status = 1';

    if ($scope === 'student') {
        $studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
        if ($studentId <= 0) return [];
        $where .= ' AND s.id = ?';
        $params[] = $studentId;
    } elseif ($scope === 'class') {
        $kelas = sanitize($_GET['kelas'] ?? '');
        if ($kelas === '') return [];
        grad_require_class_access($user, $kelas);
        $where .= ' AND s.kelas = ?';
        $params[] = $kelas;
    }

    if (empty($user['can_manage_graduation'])) {
        if (empty($user['scoped_classes'])) return [];
        $placeholders = implode(',', array_fill(0, count($user['scoped_classes']), '?'));
        $where .= " AND s.kelas IN ($placeholders)";
        $params = array_merge($params, $user['scoped_classes']);
    }

    $stmt = db()->prepare("
        SELECT s.*, l.letter_number, l.graduation_date, l.signing_date, l.headmaster_name, l.headmaster_niy, l.headmaster_position, s.rata_rata
        FROM grad_student_accounts a
        INNER JOIN students s ON s.id = a.student_id
        LEFT JOIN grad_student_letters l ON l.student_id = s.id AND l.academic_year_id = a.academic_year_id
        {$where}
        ORDER BY s.kelas ASC, s.no_urut ASC, s.nama ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getClasses($yearId, $user = null)
{
    $params = [$yearId];
    $where = "a.academic_year_id = ? AND s.kelas <> '' AND a.status = 1";
    if ($user && empty($user['can_manage_graduation'])) {
        if (empty($user['scoped_classes'])) return [];
        $placeholders = implode(',', array_fill(0, count($user['scoped_classes']), '?'));
        $where .= " AND s.kelas IN ($placeholders)";
        $params = array_merge($params, $user['scoped_classes']);
    }
    $stmt = db()->prepare("
        SELECT s.kelas, COUNT(a.id) as total_siswa 
        FROM grad_student_accounts a
        JOIN students s ON s.id = a.student_id
        WHERE {$where} 
        GROUP BY s.kelas 
        ORDER BY s.kelas ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getStudents($yearId, $user = null)
{
    $params = [$yearId];
    $where = "a.academic_year_id = ? AND a.status = 1";
    if ($user && empty($user['can_manage_graduation'])) {
        if (empty($user['scoped_classes'])) return [];
        $placeholders = implode(',', array_fill(0, count($user['scoped_classes']), '?'));
        $where .= " AND s.kelas IN ($placeholders)";
        $params = array_merge($params, $user['scoped_classes']);
    }
    $stmt = db()->prepare("
        SELECT s.id, s.nis, s.nisn, s.nama, s.kelas 
        FROM grad_student_accounts a
        JOIN students s ON s.id = a.student_id
        WHERE {$where} 
        ORDER BY s.kelas ASC, s.no_urut ASC, s.nama ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function countStudentsByClass($yearId, $kelas)
{
    $stmt = db()->prepare("
        SELECT COUNT(a.id) 
        FROM grad_student_accounts a
        JOIN students s ON s.id = a.student_id
        WHERE a.academic_year_id = ? AND a.status = 1 AND s.kelas = ?
    ");
    $stmt->execute([$yearId, $kelas]);
    return (int) $stmt->fetchColumn();
}

function getSubjectsForClass($yearId, $kelas)
{
    $stmt = db()->prepare("
        SELECT s.*, g.kode as group_kode, g.nama as group_nama, g.tipe as group_tipe
        FROM grad_subjects s
        JOIN grad_subject_groups g ON g.id = s.group_id
        WHERE s.academic_year_id = ?
          AND (g.tipe <> 'pilihan' OR FIND_IN_SET(REPLACE(?, ' ', ''), REPLACE(COALESCE(s.kelas, ''), ' ', '')) > 0)
        ORDER BY g.urutan ASC, g.kode ASC, s.urutan ASC, s.nama_mapel ASC
    ");
    $stmt->execute([$yearId, $kelas]);
    return $stmt->fetchAll();
}

function getAllSubjects($yearId, $user = null)
{
    if ($user && empty($user['can_manage_graduation'])) {
        $all = [];
        foreach ($user['scoped_classes'] ?? [] as $kelas) {
            foreach (getSubjectsForClass($yearId, $kelas) as $subject) {
                $all[$subject['id']] = $subject;
            }
        }
        return array_values($all);
    }

    $stmt = db()->prepare("
        SELECT s.*, g.nama as group_nama, g.tipe as group_tipe
        FROM grad_subjects s
        JOIN grad_subject_groups g ON g.id = s.group_id
        WHERE s.academic_year_id = ?
        ORDER BY g.urutan ASC, g.kode ASC, s.urutan ASC, s.nama_mapel ASC
    ");
    $stmt->execute([$yearId]);
    return $stmt->fetchAll();
}

function getScoresMap($yearId)
{
    $stmt = db()->prepare("SELECT student_id, subject_id, nilai_akhir FROM grad_student_scores WHERE academic_year_id = ?");
    $stmt->execute([$yearId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['student_id'] . ':' . $row['subject_id']] = $row['nilai_akhir'];
    }
    return $map;
}

function getSklSetting($yearId)
{
    $stmt = db()->prepare("SELECT * FROM grad_letter_settings WHERE academic_year_id = ?");
    $stmt->execute([$yearId]);
    $setting = $stmt->fetch() ?: [];
    $centralKop = get_setting('kop_surat', '');
    if ($centralKop !== '') {
        $setting['kop_image'] = $centralKop;
    }
    $centralHeadmaster = get_setting('kepala_sekolah', '');
    if ($centralHeadmaster !== '') {
        $setting['headmaster_name'] = $centralHeadmaster;
    }
    return $setting;
}

function sklGroupTitle($group)
{
    if (($group['tipe'] ?? '') === 'wajib') {
        return 'Kelompok Mata Pelajaran Wajib';
    }
    if (($group['tipe'] ?? '') === 'pilihan') {
        return 'Kelompok Mata Pelajaran Pilihan';
    }
    return trim((string) ($group['nama'] ?? '')) ?: 'Muatan Lokal';
}

function imageDataUri($relativePath)
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '') return '';

    $root = realpath(__DIR__ . '/../../../');
    $file = realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\')));
    if (!$root || !$file || strpos($file, $root) !== 0 || !is_file($file)) {
        return '';
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif'
    ];
    if (!isset($mimes[$ext])) {
        return '';
    }

    return 'data:' . $mimes[$ext] . ';base64,' . base64_encode(file_get_contents($file));
}

function absoluteLocalPath($relativePath)
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '') return '';

    $root = realpath(__DIR__ . '/../../../');
    $file = realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\')));
    if (!$root || !$file || strpos($file, $root) !== 0 || !is_file($file)) {
        return '';
    }
    return $file;
}

function firstFilled($value, $fallback)
{
    $value = trim((string) $value);
    return $value !== '' ? $value : $fallback;
}

function estimatePdfTextWidth($text, $size)
{
    $text = (string) ($text ?? '');
    if ($text === '') return 0;

    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $width = 0.0;
    foreach ($chars as $ch) {
        if ($ch === ' ') {
            $factor = 0.22;
        } elseif ($ch === '.') {
            $factor = 0.20;
        } elseif ($ch === ':' || $ch === ';' || $ch === ',') {
            $factor = 0.21;
        } elseif ($ch === '(' || $ch === ')') {
            $factor = 0.25;
        } elseif (strlen($ch) === 1 && ctype_digit($ch)) {
            $factor = 0.46;
        } elseif (strlen($ch) === 1 && ctype_alpha($ch)) {
            $factor = (strtoupper($ch) === $ch) ? 0.50 : 0.43;
        } else {
            $factor = 0.47;
        }
        $width += $size * $factor;
    }
    return $width;
}

function renderJustifiedLineWithBoldWord($pdf, $x, $y, $boxWidth, $line, $boldWord, $size)
{
    $line = (string) ($line ?? '');
    $boldWord = (string) ($boldWord ?? '');
    if ($line === '') {
        return;
    }

    $pdf->justifyLine($x, $y, $line, $boxWidth, $size, 'F1');
    if ($boldWord === '' || strpos($line, $boldWord) === false) {
        return;
    }

    $plainWidth = $pdf->textWidth($line, $size);
    $spaces = substr_count($line, ' ');
    $extra = max(0.0, $boxWidth - $plainWidth);
    $wordSpacing = $spaces > 0 ? ($extra / $spaces) : 0.0;

    $prefix = strstr($line, $boldWord, true);
    $prefixSpaces = substr_count($prefix, ' ');
    $prefixWidth = $pdf->textWidth($prefix, $size) + ($prefixSpaces * $wordSpacing);

    $pdf->text($x + $prefixWidth, $y, $boldWord, $size, 'F2', 'left');
}

function renderDistributedSpacingLineWithBoldWord($pdf, $x, $y, $boxWidth, $line, $boldWord, $size, $rightPadding = 0.0)
{
    $line = trim((string) ($line ?? ''));
    $boldWord = trim((string) ($boldWord ?? ''));
    if ($line === '') {
        return;
    }

    $words = preg_split('/\s+/u', $line, -1, PREG_SPLIT_NO_EMPTY);
    if (!$words || count($words) === 1) {
        $font = ($boldWord !== '' && $line === $boldWord) ? 'F2' : 'F1';
        $pdf->text($x, $y, $line, $size, $font, 'left');
        return;
    }

    // Kasus utama SKL: kata bold ada di akhir ("... dinyatakan LULUS").
    // Kunci kata bold tepat di ujung kanan, sisanya diratakan di tengah.
    $lastWord = $words[count($words) - 1];
    if ($boldWord !== '' && $lastWord === $boldWord) {
        $prefixWords = array_slice($words, 0, -1);
        $boldW = $pdf->textWidth($boldWord, $size);
        $gapCount = count($prefixWords); // gap antarkata prefix + gap sebelum kata bold
        $minGap = max(0.5, $pdf->textWidth(' ', $size) * 0.22);

        $prefixWordsW = 0.0;
        foreach ($prefixWords as $w) {
            $prefixWordsW += $pdf->textWidth($w, $size);
        }

        $availableForPrefix = max(0.0, $boxWidth - max(0.0, (float) $rightPadding) - $boldW);
        $gap = $gapCount > 0 ? (($availableForPrefix - $prefixWordsW) / $gapCount) : 0.0;
        $gap = max($minGap, $gap);

        $cursorX = $x;
        foreach ($prefixWords as $w) {
            $pdf->text($cursorX, $y, $w, $size, 'F1', 'left');
            $cursorX += $pdf->textWidth($w, $size) + $gap;
        }

        $boldX = $x + $boxWidth - max(0.0, (float) $rightPadding) - $boldW;
        $pdf->text($boldX, $y, $boldWord, $size, 'F2', 'left');
        return;
    }

    $gapCount = count($words) - 1;
    $minGap = max(0.5, $pdf->textWidth(' ', $size) * 0.22);
    $wordsW = 0.0;
    foreach ($words as $word) {
        $wordsW += $pdf->textWidth($word, $size);
    }
    $availableW = max(0.0, $boxWidth - max(0.0, (float) $rightPadding));
    $gap = $gapCount > 0 ? (($availableW - $wordsW) / $gapCount) : 0.0;
    $gap = max($minGap, $gap);

    $cursorX = $x;
    $lastIndex = count($words) - 1;
    foreach ($words as $idx => $word) {
        $font = ($boldWord !== '' && $word === $boldWord) ? 'F2' : 'F1';
        $pdf->text($cursorX, $y, $word, $size, $font, 'left');
        $cursorX += $pdf->textWidth($word, $size);
        if ($idx < $lastIndex) {
            $cursorX += $gap;
        }
    }
}

function renderRightAlignedLineWithBoldEnding($pdf, $x, $y, $boxWidth, $prefix, $boldWord, $size)
{
    $prefix = (string) ($prefix ?? '');
    $boldWord = (string) ($boldWord ?? '');
    $full = trim($prefix . $boldWord);
    if ($full === '') {
        return;
    }

    $fontSize = (float) $size;
    $prefixW = $pdf->textWidth($prefix, $fontSize);
    $boldW = $pdf->textWidth($boldWord, $fontSize);
    $totalW = $prefixW + $boldW;

    while ($fontSize > 10.0 && $totalW > $boxWidth) {
        $fontSize -= 0.25;
        $prefixW = $pdf->textWidth($prefix, $fontSize);
        $boldW = $pdf->textWidth($boldWord, $fontSize);
        $totalW = $prefixW + $boldW;
    }

    $startX = $x + max(0, $boxWidth - $totalW);
    $pdf->text($startX, $y, $prefix, $fontSize, 'F1', 'left');
    $pdf->text($startX + $prefixW, $y, $boldWord, $fontSize, 'F2', 'left');
}

function formatBirthPlaceDate($student)
{
    $place = trim((string) ($student['tempat_lahir'] ?? ''));
    $date = indoDate($student['tanggal_lahir'] ?? null, true);
    if ($place !== '' && $date !== '-') {
        return $place . ', ' . $date;
    }
    if ($place !== '') {
        return $place;
    }
    return $date !== '-' ? $date : '';
}

function formatScore($score)
{
    if ($score === '' || $score === null) return '';
    $num = (float) $score;
    return number_format($num, 2, ',', '.');
}

function verificationUrl($student, $yearId)
{
    $sid = (int) ($student['id'] ?? 0);
    $sig = graduationVerifySignature($sid, $yearId, $student['letter_number'] ?? '');
    // Gunakan endpoint lebih pendek agar payload QR lebih ringan dan mudah discan.
    return absoluteBaseUrl() . 'modules/e-graduation/v.php?c=' . rawurlencode($sid . '.' . (int) $yearId . '.' . $sig);
}

function qrImageUrl($url)
{
    $remote = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=0&data=' . rawurlencode($url);
    if (function_exists('curl_init')) {
        $ch = curl_init($remote);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $data = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status === 200 && $data) {
            return 'data:image/png;base64,' . base64_encode($data);
        }
    }
    return $remote;
}

function qrImagePath($url)
{
    // Prioritaskan generator eksternal (lebih kompatibel untuk scanner HP),
    // fallback ke generator lokal jika server tanpa internet.
    $data = qrRemotePngData($url);
    if (!$data) {
        // Scale besar agar modul QR tegas saat dikonversi ke JPEG/PDF.
        $data = graduation_qr_png($url, 8);
    }

    if (!$data) return '';
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eportal-qr-' . bin2hex(random_bytes(6)) . '.png';
    file_put_contents($path, $data);
    return $path;
}

/**
 * Fast local-only QR path — no external API calls.
 * Used for batch PDF generation to avoid N HTTP requests.
 */
function qrImagePathLocal($url)
{
    require_once __DIR__ . '/phpqrcode.php';
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eportal-qr-' . bin2hex(random_bytes(6)) . '.png';
    // Gunakan ECC Level M (lebih lega/tidak terlalu padat) dan ukuran pixel besar (10) 
    // agar saat diconvert ke JPEG oleh PDF tidak rusak karena artefak kompresi.
    QRcode::png($url, $path, 'M', 10, 2);
    return $path;
}

function qrRemotePngData($url)
{
    $remote = 'https://api.qrserver.com/v1/create-qr-code/?size=420x420&margin=12&ecc=H&data=' . rawurlencode($url);
    $data = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($remote);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status === 200 && is_string($resp) && strlen($resp) > 128) {
            $data = $resp;
        }
    } elseif (ini_get('allow_url_fopen')) {
        $resp = @file_get_contents($remote);
        if (is_string($resp) && strlen($resp) > 128) {
            $data = $resp;
        }
    }

    // Validasi sederhana PNG signature.
    if ($data !== '' && substr($data, 0, 8) === "\x89PNG\x0D\x0A\x1A\x0A") {
        return $data;
    }
    return '';
}

function graduationVerifySignature($studentId, $yearId, $letterNumber)
{
    $secret = DB_NAME . '|' . DB_USER . '|' . DB_PASS;
    return substr(hash_hmac('sha256', (int) $studentId . '|' . (int) $yearId . '|' . (string) $letterNumber, $secret), 0, 24);
}

function absoluteBaseUrl()
{
    // Jika BASE_URL sudah full URL (karena BASE_URL_OVERRIDE), pakai langsung.
    if (preg_match('#^https?://#i', BASE_URL)) {
        return rtrim(BASE_URL, '/') . '/';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '';
    $host = $forwardedHost !== '' ? trim(explode(',', $forwardedHost)[0]) : ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . BASE_URL;
}

function h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function indoDate($date, $padDay = false)
{
    if (!$date) return '-';
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($date);
    if (!$ts) return '-';
    $dayFormat = $padDay ? 'd' : 'j';
    return date($dayFormat, $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
