<?php
/**
 * Student Card View - E-Xam Card
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/../api/xam_helper.php';

$tokenStr = $_GET['token'] ?? '';
$parts = explode('.', $tokenStr);
if (count($parts) !== 2) {
    die('Akses tidak valid.');
}

$payloadJson = base64_decode($parts[0]);
$expectedSig = hash_hmac('sha256', $payloadJson, DB_NAME);
if (!hash_equals($expectedSig, $parts[1])) {
    die('Tanda tangan tidak valid.');
}

$payload = json_decode($payloadJson, true);
if (!$payload || $payload['exp'] < time()) {
    die('Sesi telah berakhir. Silakan login kembali.');
}

$studentId = (int) $payload['student_id'];
$examId = (int) $payload['exam_id'];

// Fetch data
$stmt = db()->prepare("
    SELECT s.id as student_id, s.nama, s.nis, s.kelas, s.foto_path, xs.ruang_ujian, xs.username, xs.password_plain, xs.status,
           e.id as exam_id, e.exam_name, e.card_template, e.academic_year_id as exam_year_id,
           es.letter_manual_no, es.letter_code, es.letter_date, es.sign_date,
           es.headmaster_name, es.headmaster_user_id,
           COALESCE(ay_exam.tahun_ajaran, ay_student.tahun_ajaran, ay_active.tahun_ajaran) as tahun_ajaran
    FROM xam_exam_students xs
    JOIN xam_exams e ON e.id = xs.exam_id
    JOIN students s ON s.id = xs.student_id
    LEFT JOIN academic_years ay_exam ON ay_exam.id = e.academic_year_id
    LEFT JOIN academic_years ay_student ON ay_student.id = s.academic_year_id
    LEFT JOIN academic_years ay_active ON ay_active.is_active = 1
    LEFT JOIN xam_exam_settings es ON es.exam_id = e.id
    WHERE xs.exam_id = ? AND xs.student_id = ?
");
$stmt->execute([$examId, $studentId]);
$data = $stmt->fetch();

if (!$data) {
    // Fallback: match by student_id or any exam_students record for student
    $stmt = db()->prepare("
        SELECT s.id as student_id, s.nama, s.nis, s.kelas, s.foto_path, xs.ruang_ujian, xs.username, xs.password_plain, xs.status,
               e.id as exam_id, e.exam_name, e.card_template, e.academic_year_id as exam_year_id,
               es.letter_manual_no, es.letter_code, es.letter_date, es.sign_date,
               es.headmaster_name, es.headmaster_user_id,
               COALESCE(ay_exam.tahun_ajaran, ay_student.tahun_ajaran, ay_active.tahun_ajaran) as tahun_ajaran
        FROM students s
        LEFT JOIN xam_exam_students xs ON xs.student_id = s.id AND xs.exam_id = ?
        LEFT JOIN xam_exams e ON e.id = ?
        LEFT JOIN academic_years ay_exam ON ay_exam.id = e.academic_year_id
        LEFT JOIN academic_years ay_student ON ay_student.id = s.academic_year_id
        LEFT JOIN academic_years ay_active ON ay_active.is_active = 1
        LEFT JOIN xam_exam_settings es ON es.exam_id = e.id
        WHERE s.id = ?
    ");
    $stmt->execute([$examId, $examId, $studentId]);
    $data = $stmt->fetch();
}

if (!$data || ($data['status'] ?? '') !== 'OKE') {
    die('Data tidak tersedia atau status kartu ditangguhkan.');
}

// Ensure latest student class & photo for this student's NIS
if (!empty($data['nis'])) {
    $latest = xam_get_latest_student_info($data['nis'], (int)($data['exam_year_id'] ?? 0));
    if ($latest) {
        if (!empty($latest['kelas'])) {
            $data['kelas'] = $latest['kelas'];
        }
        if (!empty($latest['foto_path'])) {
            $data['foto_path'] = $latest['foto_path'];
        }
    }
}

$root = realpath(__DIR__ . '/../../../') ?: dirname(dirname(dirname(__DIR__)));

// Resolve template
$templateFile = xam_resolve_template_path($data['card_template'] ?? '', $examId);
$hasTemplate = ($templateFile !== '' && file_exists($templateFile));
$templateWebUrl = '';
if ($hasTemplate) {
    $rel = str_replace(['\\', $root], ['/', ''], $templateFile);
    $templateWebUrl = BASE_URL . ltrim($rel, '/');
}

// Resolve photo
$fotoPath = $data['foto_path'] ?? '';
$hasPhoto = false;
$photoWebUrl = '';
if ($fotoPath !== '') {
    $cleanPhoto = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($fotoPath, '/\\'));
    if (file_exists($root . DIRECTORY_SEPARATOR . $cleanPhoto)) {
        $hasPhoto = true;
        $photoWebUrl = BASE_URL . str_replace('\\', '/', $cleanPhoto);
    }
}

// Resolve headmaster name
$headmasterName = trim((string) ($data['headmaster_name'] ?? ''));
if ($headmasterName === '' && !empty($data['headmaster_user_id'])) {
    $stmtKS = db()->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
    $stmtKS->execute([$data['headmaster_user_id']]);
    $headmasterName = $stmtKS->fetchColumn() ?: '';
}
if ($headmasterName === '') {
    $headmasterName = get_setting('kepala_sekolah', 'Kepala Sekolah');
}

$school_name = get_setting('nama_sekolah', 'SMAS Wachid Hasyim 1 Surabaya');
$school_icon = get_setting('icon_sekolah', '');
$school_address = get_setting('alamat_sekolah', 'Jl. Sidotopo Wetan Baru No. 37 Telp. 0313764756 Surabaya');

$letterNo = xam_compose_letter_number($data['letter_manual_no'], $data['letter_code'], $data['letter_date']);
$targetStudentId = (int) ($data['student_id'] ?? $studentId);
$qrSig = xam_verify_signature($targetStudentId, $examId, $letterNo);
$verifyUrl = absoluteBaseUrl() . "modules/e-xam-card/v.php?c=" . urlencode($targetStudentId . '.' . $examId . '.' . $qrSig);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kartu Peserta - <?php echo htmlspecialchars($data['nama']); ?></title>
    <style>
        :root { --bg: #f1f5f9; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Times New Roman', Times, serif; background: var(--bg); padding: 20px; display: flex; flex-direction: column; align-items: center; min-height: 100vh; color: #000; }
        
        .top-nav { width: 100%; max-width: 650px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; font-family: Arial, sans-serif; }
        .btn-back { display: flex; align-items: center; gap: 8px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; }

        .card-wrapper {
            width: 100%;
            max-width: 650px;
            overflow: hidden;
            margin: 0 auto;
            position: relative;
        }

        /* Card Container */
        .card-container {
            width: 650px;
            height: 520px;
            position: absolute;
            top: 0;
            left: 0;
            border: 2px solid #000;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform-origin: top left;
        }
        
        /* Card Background */
        .card-background {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            <?php if ($hasTemplate): ?>
            background-image: url('<?php echo $templateWebUrl; ?>');
            background-size: 100% 100%;
            background-repeat: no-repeat;
            <?php else: ?>
            background: #fff;
            <?php endif; ?>
            z-index: 0;
        }

        .kop-area {
            position: absolute;
            top: 4%; left: 5%; right: 5%; height: 18%;
            display: <?php echo $hasTemplate ? 'none' : 'flex'; ?>;
            align-items: center; z-index: 1;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
        }
        .kop-logo { height: 100%; aspect-ratio: 1; margin-right: 3%; }
        .kop-text { flex: 1; text-align: center; }
        .kop-text h4 { font-size: 14px; font-weight: 700; margin-bottom: 2px; }
        .kop-text h1 { font-size: 24px; font-weight: 800; color: #1e3a8a; margin-bottom: 2px; }
        .kop-text p { font-size: 11px; }

        /* Absolute Percentage Content Area */
        .card-content {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 2;
        }

        .title-section { position: absolute; top: <?php echo $hasTemplate ? '29%' : '26%'; ?>; width: 100%; text-align: center; }
        .title-section h2 { font-size: 24px; font-weight: 800; margin: 0; }
        .title-section h3 { font-size: 20px; font-weight: 800; margin: 4px 0; }
        .title-section p { font-size: 16px; font-weight: 800; margin: 0; }

        .card-line { position: absolute; top: 43%; left: 3.5%; right: 3.5%; border-top: 1px solid #000; }

        .student-info { position: absolute; top: 48%; left: 3.5%; width: 90%; max-width: 90%; }
        .info-row { display: flex; margin-bottom: 8px; font-size: 18px; font-weight: 700; line-height: 1.2; align-items: baseline; }
        .info-label { width: 22%; flex-shrink: 0; }
        .info-sep { width: 3%; flex-shrink: 0; }
        .info-val { flex: 1; min-width: 0; word-break: break-word; overflow-wrap: anywhere; }

        .photo-area { position: absolute; bottom: 5.5%; left: 18.5%; height: 22.5%; display: inline-block; border: 1px solid #000; padding: 2px; background: #fff; }
        .photo-area img { height: 100%; width: auto; object-fit: contain; display: block; }

        .sign-area { position: absolute; bottom: 3.5%; right: 3.5%; width: 35%; text-align: center; display: flex; flex-direction: column; align-items: center; }
        .sign-text { font-size: 14px; font-weight: 400; margin-bottom: 4px; }
        .sign-qr { margin: 4px 0; width: 32%; aspect-ratio: 1; }
        .sign-qr img { width: 100%; height: 100%; border: 1px solid #eee; background: #fff; padding: 2px; }
        .sign-name { font-size: 16px; font-weight: 800; text-decoration: underline; margin-top: 4px; }

        /* Buttons */
        .dl-container { width: 100%; max-width: 650px; margin-top: 24px; display: flex; gap: 12px; }
        .btn-dl { 
            flex: 2; padding: 16px 20px; background: #0f172a; color: #fff; border: none; border-radius: 12px; 
            font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.2s; text-decoration: none; font-family: Arial, sans-serif;
        }
        .btn-dl:hover { background: #000; transform: translateY(-1px); }
        .btn-dl svg { width: 18px; height: 18px; }

        .btn-print {
            flex: 1; padding: 16px 20px; background: #0f766e; color: #fff; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 15px rgba(15,118,110,0.2); transition: all 0.2s; text-decoration: none; font-family: Arial, sans-serif;
        }
        .btn-print:hover { background: #0d9488; transform: translateY(-1px); }
        .btn-print svg { width: 18px; height: 18px; }

        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                display: block !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            .top-nav, .dl-container {
                display: none !important;
            }
            .card-wrapper {
                max-width: none !important;
                width: 650px !important;
                height: 520px !important;
                margin: 20px auto !important;
                overflow: visible !important;
            }
            .card-container {
                position: relative !important;
                transform: none !important;
                box-shadow: none !important;
                border: 2px solid #000 !important;
                page-break-inside: avoid !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .card-background {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="<?php echo BASE_URL; ?>#/dashboard" class="btn-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            Logout
        </a>
        <div style="font-weight: 800; color: #0f172a;">PRATINJAU KARTU UJIAN</div>
    </div>

    <div class="card-wrapper">
        <div class="card-container">
            <div class="card-background"></div>

            <div class="kop-area">
                <div class="kop-logo">
                    <?php if ($school_icon && file_exists($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($school_icon, '/\\')))): ?>
                        <img src="<?php echo BASE_URL . ltrim(str_replace('\\', '/', $school_icon), '/'); ?>" style="width:100%; height:100%; object-fit:contain;">
                    <?php else: ?>
                        <div style="width:100%; height:100%; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#cbd5e1;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg></div>
                    <?php endif; ?>
                </div>
                <div class="kop-text">
                    <h4>YAYASAN WACHID HASYIM</h4>
                    <h1><?php echo htmlspecialchars($school_name); ?></h1>
                    <p><?php echo htmlspecialchars($school_address); ?></p>
                </div>
            </div>

            <div class="card-content">
                <div class="title-section">
                    <h2>KARTU PESERTA</h2>
                    <h3><?php echo strtoupper(htmlspecialchars($data['exam_name'])); ?></h3>
                    <p style="display: inline-block; border-bottom: 1px solid #000; padding-bottom: 2px; width: max-content;">TAHUN PELAJARAN <?php echo htmlspecialchars($data['tahun_ajaran']); ?></p>
                </div>
                
                <div class="card-line" style="display: none;"></div>

                <div class="student-info">
                    <div class="info-row">
                        <div class="info-label">NAMA</div>
                        <div class="info-sep">:</div>
                        <div class="info-val"><?php echo strtoupper(htmlspecialchars($data['nama'])); ?> / <?php echo htmlspecialchars($data['kelas']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">USERNAME</div>
                        <div class="info-sep">:</div>
                        <div class="info-val"><?php echo htmlspecialchars($data['username']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">PASSWORD</div>
                        <div class="info-sep">:</div>
                        <div class="info-val"><?php echo htmlspecialchars($data['password_plain']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">RUANGAN</div>
                        <div class="info-sep">:</div>
                        <div class="info-val"><?php echo htmlspecialchars($data['ruang_ujian'] ?: '-'); ?></div>
                    </div>
                </div>
                
                <?php if ($hasPhoto): ?>
                <div class="photo-area">
                    <img src="<?php echo $photoWebUrl; ?>" alt="Foto Siswa">
                </div>
                <?php endif; ?>
                
                <div class="sign-area">
                    <div class="sign-text">Surabaya, <?php echo xam_indo_date($data['sign_date'] ?: date('Y-m-d')); ?></div>
                    <div class="sign-text">Kepala Sekolah,</div>
                    <div class="sign-qr">
                        <img src="<?php echo BASE_URL; ?>modules/e-xam-card/api/qr.php?data=<?php echo urlencode($verifyUrl); ?>&size=4" alt="QR Code">
                    </div>
                    <div class="sign-name"><?php echo htmlspecialchars($headmasterName); ?></div>
                </div>
            </div>
        </div>
    </div> <!-- end card-wrapper -->

    <div class="dl-container">
        <button onclick="downloadPdf()" class="btn-dl" id="btnDl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            <span>Unduh Kartu Ujian Resmi (PDF)</span>
        </button>
        <button onclick="window.print()" class="btn-print" id="btnPrint">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            <span>Cetak Langsung</span>
        </button>
    </div>

    <script src="<?php echo BASE_URL; ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <script>
        function scaleCard() {
            const wrapper = document.querySelector('.card-wrapper');
            const card = document.querySelector('.card-container');
            if (!wrapper || !card) return;
            
            const wWidth = wrapper.clientWidth;
            if (wWidth < 650 && wWidth > 0) {
                const scale = wWidth / 650;
                card.style.transform = `scale(${scale})`;
                wrapper.style.height = `${520 * scale}px`;
            } else {
                card.style.transform = 'scale(1)';
                wrapper.style.height = '520px';
            }
        }
        
        window.addEventListener('resize', scaleCard);
        window.addEventListener('DOMContentLoaded', scaleCard);
        scaleCard();

        function downloadPdf() {
            const btn = $('#btnDl');
            const original = btn.html();
            btn.prop('disabled', true).html('Memproses...');

            const url = '<?php echo BASE_URL; ?>modules/e-xam-card/api/reports.php?action=download-card&exam_id=<?php echo $examId; ?>&scope=student&student_id=<?php echo $targetStudentId; ?>&token=<?php echo addslashes($_GET['token']); ?>&st=1';
            
            fetch(url)
                .then(res => {
                    if(!res.ok) throw new Error('Gagal download');
                    return res.blob();
                })
                .then(blob => {
                    const link = document.createElement('a');
                    link.href = window.URL.createObjectURL(blob);
                    link.download = 'Kartu-Ujian-<?php echo addslashes($data['nama']); ?>.pdf';
                    link.click();
                    btn.prop('disabled', false).html(original);
                })
                .catch(err => {
                    alert('Gagal mengunduh kartu. Silakan hubungi admin.');
                    console.error(err);
                    btn.prop('disabled', false).html(original);
                });
        }
    </script>
</body>
</html>
