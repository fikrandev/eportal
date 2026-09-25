<?php
/**
 * E-Xam Card reports API.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/xam_helper.php';
require_once __DIR__ . '/../../e-graduation/api/simple_pdf.php';

// Check for student token first (bypass admin auth if valid student token is present)
$isStudent = false;
$studentToken = $_GET['token'] ?? '';
if ($studentToken !== '') {
    $parts = explode('.', $studentToken);
    if (count($parts) === 2) {
        $payloadJson = base64_decode($parts[0]);
        $expectedSig = hash_hmac('sha256', $payloadJson, DB_NAME);
        if (hash_equals($expectedSig, $parts[1])) {
            $payload = json_decode($payloadJson, true);
            if ($payload && $payload['exp'] > time()) {
                $isStudent = true;
                // Force scope to student and set IDs from token to prevent tampering
                $_GET['scope'] = 'student';
                $_GET['student_id'] = (int) $payload['student_id'];
                $_GET['exam_id'] = (int) $payload['exam_id'];

                // Verify status is OKE
                $stmtChk = db()->prepare("SELECT status FROM xam_exam_students WHERE exam_id = ? AND student_id = ?");
                $stmtChk->execute([(int)$payload['exam_id'], (int)$payload['student_id']]);
                $stRow = $stmtChk->fetch();
                if ($stRow && ($stRow['status'] ?? '') !== 'OKE') {
                    http_response_code(403);
                    die('Status kartu ujian Anda ditangguhkan. Silakan hubungi pihak sekolah.');
                }
            }
        }
    }
}

if (!$isStudent) {
    xam_auth();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'recap':
        recap();
        break;
    case 'download-card':
        downloadCard();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function recap()
{
    $user = xam_auth();
    $examId = (int) ($_GET['exam_id'] ?? 0);
    if ($examId <= 0) {
        json_response(400, false, 'Pilih ujian terlebih dahulu.');
    }

    $exam = examDetail($examId);
    if (!$exam) {
        json_response(404, false, 'Ujian tidak ditemukan.');
    }

    $sql = "
        SELECT s.kelas,
               COUNT(*) as total_siswa,
               SUM(CASE WHEN xs.status = 'OKE' THEN 1 ELSE 0 END) as total_oke,
               SUM(CASE WHEN xs.status = 'DITANGGUHKAN' THEN 1 ELSE 0 END) as total_ditangguhkan
        FROM xam_exam_students xs
        JOIN students s ON s.id = xs.student_id
        WHERE xs.exam_id = ?
    ";
    
    $params = [$examId];

    if ($user['is_teacher']) {
        if (!$user['managed_class']) {
            json_response(403, false, 'Akses ditolak. Anda tidak memiliki kelas yang dikelola.');
        }
        $sql .= " AND s.kelas = ?";
        $params[] = $user['managed_class'];
    }

    $sql .= " GROUP BY s.kelas ORDER BY s.kelas ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    json_response(200, true, 'Rekap status ujian berhasil dimuat.', [
        'exam' => $exam,
        'items' => $rows,
        'debug' => [
            'is_teacher' => $user['is_teacher'] ?? false,
            'role' => $user['role'] ?? 'unknown',
            'managed_class' => $user['managed_class'] ?? 'unknown'
        ]
    ]);
}

function downloadCard()
{
    $examId = (int) ($_GET['exam_id'] ?? 0);
    $scope = sanitize($_GET['scope'] ?? 'student');
    $kelas = sanitize($_GET['kelas'] ?? '');
    $studentId = (int) ($_GET['student_id'] ?? 0);

    if ($examId <= 0) {
        http_response_code(400);
        echo 'Pilih ujian terlebih dahulu.';
        exit;
    }

    $exam = examDetail($examId);
    if (!$exam) {
        http_response_code(404);
        echo 'Ujian tidak ditemukan.';
        exit;
    }

    $students = reportStudents($examId, $scope, $kelas, $studentId);
    if (!$students) {
        http_response_code(404);
        echo 'Data siswa untuk kartu ujian tidak ditemukan.';
        exit;
    }

    $setting = xam_exam_setting($examId);
    $schoolName = get_setting('nama_sekolah', 'SMAS Wachid Hasyim 1 Surabaya');
    $schoolAddress = get_setting('alamat_sekolah', 'Jl. Sidotopo Wetan Baru No. 37 Telp. 0313764756 Surabaya');
    $schoolIcon = get_setting('icon_sekolah', '');
    $activeYear = get_active_academic_year();
    
    $letterNumber = xam_compose_letter_number(
        $setting['letter_manual_no'] ?? '',
        $setting['letter_code'] ?? 'I04.1/SMA.WH1',
        $setting['letter_date'] ?? date('Y-m-d')
    );
    
    $headmasterName = trim((string) ($setting['headmaster_name'] ?? ''));
    if ($headmasterName === '' && !empty($setting['headmaster_user_name'])) {
        $headmasterName = $setting['headmaster_user_name'];
    }
    if ($headmasterName === '') {
        $headmasterName = get_setting('kepala_sekolah', 'Kepala Sekolah');
    }

    $pdf = new GraduationSimplePdf();
    
    // Konfigurasi Grid (F4: 210x330mm)
    // Kartu: 100x80mm
    $cardW = 283.46; // 100mm
    $cardH = 226.77; // 80mm
    $cols = 2;
    $rows = 4;
    $marginX = (595.27 - ($cardW * $cols)) / 2;
    $marginY = (935.43 - ($cardH * $rows)) / 2;

    $count = 0;
    foreach ($students as $student) {
        if ($count % ($cols * $rows) === 0) {
            $pdf->addPage();
        }

        $col = $count % $cols;
        $row = floor(($count % ($cols * $rows)) / $cols);
        
        $x = $marginX + ($col * $cardW);
        $y = $marginY + ($row * $cardH);

        renderExamCard($pdf, $x, $y, $cardW, $cardH, $student, $exam, $schoolName, $schoolAddress, $schoolIcon, $activeYear, $setting, $letterNumber, $headmasterName);
        $count++;
    }

    $content = $pdf->output();
    $file = 'Kartu-Ujian-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $exam['exam_name']) . '-' . date('Ymd-His') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

function renderExamCard($pdf, $x, $y, $cardW, $cardH, $student, $exam, $schoolName, $schoolAddress, $schoolIcon, $activeYear, $setting, $letterNumber, $headmasterName)
{
    $root = realpath(__DIR__ . '/../../../') ?: dirname(dirname(dirname(__DIR__)));
    $templatePath = xam_resolve_template_path($exam['card_template'] ?? '', (int)($exam['id'] ?? 0));
    $useTemplate = ($templatePath !== '' && file_exists($templatePath));

    $kopH = 56.69;  // 2cm ruang kop template
    $contentTop = $y + $kopH;

    if ($useTemplate) {
        // Background Template (berisi KOP di 2cm atas)
        $pdf->image($templatePath, $x, $y, $cardW, $cardH);
    } else {
        // Fallback KOP jika tidak ada file template
        $pdf->rect($x, $y, $cardW, $cardH);
        
        // Logo Sekolah jika ada
        $hasIcon = false;
        if (!empty($schoolIcon)) {
            $iconFullPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($schoolIcon, '/\\'));
            if (file_exists($iconFullPath)) {
                $hasIcon = true;
                $pdf->image($iconFullPath, $x + 10, $y + 6, 0, 38);
            }
        }

        // Teks KOP
        $textX = $hasIcon ? ($x + 45) : ($x + 10);
        $textW = $hasIcon ? ($cardW - 55) : ($cardW - 20);
        $pdf->text($textX, $y + 12, 'YAYASAN WACHID HASYIM', 8, 'F2', 'center', $textW);
        $pdf->text($textX, $y + 24, strtoupper($schoolName), 11, 'F2', 'center', $textW);
        $pdf->text($textX, $y + 35, $schoolAddress, 6, 'F1', 'center', $textW);
        
        // Garis pemisah KOP
        $pdf->line($x + 8, $y + 44, $x + $cardW - 8, $y + 44, 1.2);
        $pdf->line($x + 8, $y + 46, $x + $cardW - 8, $y + 46, 0.4);
    }
    
    // --- JUDUL (center, 3 baris) ---
    $pdf->text($x, $contentTop + 15, 'KARTU PESERTA', 10, 'F2', 'center', $cardW);
    $examTitle = strtoupper((string) ($exam['exam_name'] ?? 'UJIAN'));
    $pdf->text($x, $contentTop + 27, $examTitle, 8, 'F2', 'center', $cardW);
    
    // Prioritas tahun ajaran: dari tahun aktif global, atau fallback ke data ujian
    $tahun = !empty($activeYear['tahun_ajaran']) ? $activeYear['tahun_ajaran'] : (!empty($exam['tahun_ajaran']) ? $exam['tahun_ajaran'] : '-');
    $subtitle = 'TAHUN PELAJARAN ' . $tahun;
    $pdf->text($x, $contentTop + 37, $subtitle, 7, 'F2', 'center', $cardW);
    
    // Garis bawah setelah judul (panjangnya sesuai teks)
    $subW = $pdf->textWidth($subtitle, 7);
    $subW += 10;
    $subX = $x + ($cardW - $subW) / 2;
    $pdf->line($subX, $contentTop + 41, $subX + $subW, $contentTop + 41, 0.5);
    
    // --- PRE-CALCULATE Y POSITIONS ---
    $bottomY = $y + $cardH;
    $footerY = $bottomY - 10;
    $qrSize = 30;
    $qrY = $footerY - $qrSize - 10;

    // --- FOTO SISWA ---
    $photoH = 51.02; // 1.8cm tinggi
    $photoX = $x + 8 + 42.52; // digeser 1.5cm ke kanan
    $photoY = ($footerY + 2) - $photoH - 5;
    
    $resolvedPhoto = xam_resolve_student_photo($student['foto_path'] ?? '', $student['nis'] ?? '');
    if ($resolvedPhoto && file_exists($resolvedPhoto['full_path'])) {
        $dims = $pdf->imageDimensions($resolvedPhoto['full_path']);
        if ($dims) {
            $photoW = $photoH * $dims['width'] / max(1, $dims['height']);
            $pdf->rect($photoX - 1, $photoY - 1, $photoW + 2, $photoH + 2);
            $pdf->image($resolvedPhoto['full_path'], $photoX, $photoY, 0, $photoH);
        }
    } else {
        // Kotak FOTO 3X4 jika belum ada file foto
        $photoW = 38.26; // rasio 3:4
        $pdf->rect($photoX, $photoY, $photoW, $photoH);
        $pdf->text($photoX, $photoY + ($photoH / 2) + 2, 'FOTO 3X4', 6, 'F1', 'center', $photoW);
    }

    // --- BIODATA (4 baris) ---
    $labelX = $x + 8;
    $colonX = $x + 68;
    $valueX = $x + 74;
    $bioY = $contentTop + 55;
    $fs = 8;
    $lh = 13;

    // Baris 1: NAMA / KELAS
    $namaKelas = strtoupper($student['nama'] ?? '-') . ' / ' . ($student['kelas'] ?? '-');
    $pdf->text($labelX, $bioY, 'NAMA', $fs, 'F2');
    $pdf->text($colonX, $bioY, ':', $fs, 'F2');
    $pdf->text($valueX, $bioY, $namaKelas, $fs, 'F2');
    $bioY += $lh;

    // Baris 2: USERNAME
    $pdf->text($labelX, $bioY, 'USERNAME', $fs, 'F2');
    $pdf->text($colonX, $bioY, ':', $fs, 'F2');
    $pdf->text($valueX, $bioY, ($student['username'] ?? '-'), $fs, 'F2');
    $bioY += $lh;

    // Baris 3: PASSWORD
    $password = ($student['status'] ?? 'DITANGGUHKAN') === 'OKE' ? ($student['password_plain'] ?? '-') : 'DITANGGUHKAN';
    $pdf->text($labelX, $bioY, 'PASSWORD', $fs, 'F2');
    $pdf->text($colonX, $bioY, ':', $fs, 'F2');
    $pdf->text($valueX, $bioY, $password, $fs, 'F2');
    $bioY += $lh;

    // Baris 4: RUANGAN
    $pdf->text($labelX, $bioY, 'RUANGAN', $fs, 'F2');
    $pdf->text($colonX, $bioY, ':', $fs, 'F2');
    $pdf->text($valueX, $bioY, ($student['ruang_ujian'] ?: '-'), $fs, 'F2');

    // --- TANDA TANGAN (sudut kanan bawah) ---
    $signBoxW = 100;
    $signX = $x + $cardW - $signBoxW - 10;

    // Tanggal (center) - di atas "Kepala Sekolah,"
    $city = 'Surabaya, ' . xam_indo_date($setting['sign_date'] ?? date('Y-m-d'));
    $pdf->text($signX, $qrY - 20, $city, 7, 'F1', 'center', $signBoxW);

    // "Kepala Sekolah," (center) - di atas QR
    $pdf->text($signX, $qrY - 10, 'Kepala Sekolah,', 7, 'F1', 'center', $signBoxW);

    // QR Code
    $targetStudentId = (int) ($student['student_id'] ?? $student['id'] ?? 0);
    $sig = xam_verify_signature($targetStudentId, $exam['id'], $letterNumber);
    $qrUrl = absoluteBaseUrl() . 'modules/e-xam-card/v.php?c=' . rawurlencode($targetStudentId . '.' . $exam['id'] . '.' . $sig);
    $qrFile = qrImagePathLocal($qrUrl);
    if ($qrFile && file_exists($qrFile)) {
        $qrX = $signX + ($signBoxW - $qrSize) / 2;
        $pdf->image($qrFile, $qrX, $qrY, $qrSize, $qrSize);
        @unlink($qrFile);
    }

    // Nama Kepsek (center, underline) - paling bawah
    $pdf->text($signX, $footerY, $headmasterName, 8, 'F2', 'center', $signBoxW);
    $nameW = $pdf->textWidth($headmasterName, 8);
    $lineX = $signX + ($signBoxW - $nameW) / 2;
    $pdf->line($lineX, $footerY + 2, $lineX + $nameW, $footerY + 2, 0.5);
}

function qrImagePathLocal($url)
{
    $qrLib = __DIR__ . '/../../e-graduation/api/phpqrcode.php';
    if (!file_exists($qrLib)) return '';
    require_once $qrLib;
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xam-qr-' . bin2hex(random_bytes(6)) . '.png';
    QRcode::png($url, $path, 'M', 10, 2);
    return $path;
}

function examDetail($examId)
{
    $stmt = db()->prepare('
        SELECT e.*, ay.tahun_ajaran, ay.semester 
        FROM xam_exams e 
        LEFT JOIN academic_years ay ON ay.id = e.academic_year_id 
        WHERE e.id = ? 
        LIMIT 1
    ');
    $stmt->execute([(int) $examId]);
    return $stmt->fetch();
}

function reportStudents($examId, $scope, $kelas, $studentId)
{
    $params = [$examId];
    $where = 'WHERE xs.exam_id = ?';

    if ($scope === 'student') {
        if ($studentId <= 0) return [];
        $where .= ' AND (xs.student_id = ? OR s.id = ?)';
        $params[] = $studentId;
        $params[] = $studentId;
    } elseif ($scope === 'class') {
        if ($kelas === '') return [];
        $where .= ' AND s.kelas = ?';
        $params[] = $kelas;
    }

    $stmt = db()->prepare("
        SELECT xs.*, s.nis, s.nisn, s.nama, s.kelas, s.no_urut, s.foto_path
        FROM xam_exam_students xs
        JOIN students s ON s.id = xs.student_id
        $where
        ORDER BY s.kelas ASC, s.no_urut ASC, s.nama ASC
    ");
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    // Check for latest class and photo for each student
    foreach ($students as &$stu) {
        if (!empty($stu['nis'])) {
            $latest = xam_get_latest_student_info($stu['nis']);
            if ($latest) {
                if (!empty($latest['nama'])) {
                    $stu['nama'] = $latest['nama'];
                }
                if (!empty($latest['kelas'])) {
                    $stu['kelas'] = $latest['kelas'];
                }
                if (!empty($latest['foto_path'])) {
                    $stu['foto_path'] = $latest['foto_path'];
                }
                if (!empty($latest['nisn'])) {
                    $stu['nisn'] = $latest['nisn'];
                }
            }
        }
    }
    unset($stu);

    return $students;
}
