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
if ($payload['exp'] < time()) {
    die('Sesi telah berakhir. Silakan login kembali.');
}

$studentId = (int) $payload['student_id'];
$examId = (int) $payload['exam_id'];

// Fetch data
$stmt = db()->prepare("
    SELECT s.nama, s.nis, s.kelas, s.foto_path, xs.ruang_ujian, xs.username, xs.password_plain, xs.status,
           e.exam_name, e.card_template, es.letter_manual_no, es.letter_code, es.letter_date, es.sign_date,
           es.headmaster_name, es.headmaster_user_id, ay.tahun_ajaran
    FROM students s
    JOIN academic_years ay ON ay.id = s.academic_year_id
    JOIN xam_exams e ON e.id = ?
    JOIN xam_exam_students xs ON xs.exam_id = e.id AND xs.student_id = ?
    LEFT JOIN xam_exam_settings es ON es.exam_id = e.id
    WHERE s.id = ?
");
$stmt->execute([$examId, $studentId, $studentId]);
$data = $stmt->fetch();

if (!$data || $data['status'] !== 'OKE') {
    die('Data tidak tersedia atau status kartu ditangguhkan.');
}

$school_name = get_setting('nama_sekolah', 'SMAS Wachid Hasyim 1 Surabaya');
$school_icon = get_setting('icon_sekolah', '');
$school_address = get_setting('alamat_sekolah', 'Jl. Sidotopo Wetan Baru No. 37 Telp. 0313764756 Surabaya');
$school_web = get_setting('website_sekolah', 'sma-waha1.sch.id');
$school_email = get_setting('email_sekolah', 'sma.waha1@gmail.com');

$letterNo = xam_compose_letter_number($data['letter_manual_no'], $data['letter_code'], $data['letter_date']);
$qrSig = xam_verify_signature($studentId, $examId, $letterNo);
$verifyUrl = absoluteBaseUrl() . "modules/e-xam-card/v.php?c=" . urlencode(base64_encode($studentId . '.' . $examId . '.' . $qrSig));
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
            background-image: url('<?php echo BASE_URL . ltrim($data['card_template'], '/\\'); ?>');
            background-size: 100% 100%;
            z-index: 0;
        }

        .kop-area {
            position: absolute;
            top: 4%; left: 5%; right: 5%; height: 18%;
            display: flex; align-items: center; z-index: 1;
            display: <?php echo $data['card_template'] ? 'none' : 'flex'; ?>;
        }
        .kop-logo { height: 100%; aspect-ratio: 1; margin-right: 3%; }
        .kop-text { flex: 1; text-align: center; }
        .kop-text h4 { font-size: 14px; font-weight: 700; margin-bottom: 2px; }
        .kop-text h1 { font-size: 26px; font-weight: 800; color: #2e57b8; margin-bottom: 2px; }
        .kop-text p { font-size: 12px; }

        /* Absolute Percentage Content Area */
        .card-content {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 2;
        }

        .title-section { position: absolute; top: 29%; width: 100%; text-align: center; }
        .title-section h2 { font-size: 24px; font-weight: 800; margin: 0; }
        .title-section h3 { font-size: 20px; font-weight: 800; margin: 4px 0; }
        .title-section p { font-size: 16px; font-weight: 800; margin: 0; }

        .card-line { position: absolute; top: 43%; left: 3.5%; right: 3.5%; border-top: 1px solid #000; }

        .student-info { position: absolute; top: 48%; left: 3.5%; width: 90%; }
        .info-row { display: flex; margin-bottom: 8px; font-size: 18px; font-weight: 700; line-height: 1.2; }
        .info-label { width: 20%; }
        .info-sep { width: 4%; }
        .info-val { flex: 1; }

        .photo-area { position: absolute; bottom: 5.5%; left: 18.5%; height: 22.5%; display: inline-block; border: 1px solid #000; padding: 2px; background: #fff; }
        .photo-area img { height: 100%; width: auto; object-fit: contain; display: block; }

        .sign-area { position: absolute; bottom: 3.5%; right: 3.5%; width: 35%; text-align: center; display: flex; flex-direction: column; align-items: center; }
        .sign-text { font-size: 14px; font-weight: 400; margin-bottom: 5px; }
        .sign-qr { margin: 5px 0; width: 30%; aspect-ratio: 1; }
        .sign-qr img { width: 100%; height: 100%; border: 1px solid #eee; background: #fff; padding: 2px; }
        .sign-name { font-size: 16px; font-weight: 800; text-decoration: underline; margin-top: 5px; }

        /* Button */
        .dl-container { width: 100%; max-width: 650px; margin-top: 30px; }
        .btn-dl { 
            width: 100%; padding: 18px; background: #0f172a; color: #fff; border: none; border-radius: 12px; 
            font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.2s; text-decoration: none; font-family: Arial, sans-serif;
        }
        .btn-dl:hover { background: #000; transform: translateY(-1px); }
        .btn-dl svg { width: 20px; height: 20px; }
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
                <?php if ($school_icon): ?>
                    <img src="<?php echo BASE_URL . $school_icon; ?>" style="width:100%; height:100%; object-fit:contain;">
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
            
            <?php if (!empty($data['foto_path'])): ?>
            <div class="photo-area">
                <img src="<?php echo BASE_URL . ltrim($data['foto_path'], '/\\'); ?>" alt="Foto Siswa">
            </div>
            <?php endif; ?>
            
            <div class="sign-area">
                <div class="sign-text">Surabaya, <?php echo xam_indo_date($data['sign_date'] ?: date('Y-m-d')); ?></div>
                <div class="sign-text">Kepala Sekolah,</div>
                <div class="sign-qr">
                    <img src="<?php echo BASE_URL; ?>modules/e-xam-card/api/qr.php?data=<?php echo urlencode($verifyUrl); ?>&size=4" alt="QR Code">
                </div>
                <div class="sign-name"><?php echo htmlspecialchars($data['headmaster_name'] ?: 'Kepala Sekolah'); ?></div>
            </div>
        </div>
    </div>
    </div> <!-- end card-wrapper -->

    <div class="dl-container">
        <button onclick="downloadPdf()" class="btn-dl" id="btnDl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            <span>Unduh Kartu Ujian Resmi (PDF)</span>
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
        // Also run immediately just in case
        scaleCard();

        function downloadPdf() {
            const btn = $('#btnDl');
            const original = btn.html();
            btn.prop('disabled', true).html('Memproses...');

            const url = '<?php echo BASE_URL; ?>modules/e-xam-card/api/reports.php?action=download-card&exam_id=<?php echo $examId; ?>&scope=student&student_id=<?php echo $studentId; ?>&token=<?php echo addslashes($_GET['token']); ?>&st=1';
            
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
