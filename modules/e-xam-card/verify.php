<?php
/**
 * Public E-Xam Card verification page.
 */
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/api/xam_helper.php';

$studentId = 0;
$examId = 0;
$sig = '';

if (!empty($_GET['c'])) {
    $parts = explode('.', sanitize($_GET['c']));
    if (count($parts) === 3) {
        $studentId = (int) $parts[0];
        $examId = (int) $parts[1];
        $sig = $parts[2];
    }
}

$valid = false;
$data = null;
$schoolName = get_setting('nama_sekolah', 'E-Portal Sekolah');
$schoolIcon = get_setting('icon_sekolah', '');

if ($studentId > 0 && $examId > 0 && $sig !== '') {
    try {
        // Fetch student and exam data
        $stmt = db()->prepare("
            SELECT 
                s.id as student_id, s.nama, s.nis, s.nisn, s.kelas,
                e.id as exam_id, e.exam_name,
                es.letter_code, es.letter_manual_no, es.letter_date, es.sign_date, es.headmaster_user_id,
                u.nama_lengkap as headmaster_name
            FROM students s
            JOIN xam_exams e ON e.id = ?
            LEFT JOIN xam_exam_settings es ON es.exam_id = e.id
            LEFT JOIN users u ON u.id = es.headmaster_user_id
            WHERE s.id = ? AND s.status = 1
        ");
        $stmt->execute([$examId, $studentId]);
        $data = $stmt->fetch();

        if ($data) {
            $letterNo = xam_compose_letter_number($data['letter_manual_no'], $data['letter_code'], $data['letter_date']);
            $expected = xam_verify_signature($studentId, $examId, $letterNo);
            $valid = hash_equals($expected, $sig);
        }
    } catch (PDOException $e) {
        $data = null;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Kartu Ujian - <?php echo xam_h($schoolName); ?></title>
    <?php if ($schoolIcon): ?><link rel="icon" href="<?php echo BASE_URL . xam_h($schoolIcon); ?>"><?php endif; ?>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;background:#eef2f7;color:#172033;font-family:Arial,Helvetica,sans-serif;padding:28px 14px}
        .verify-wrap{max-width:760px;margin:0 auto}
        .verify-card{background:#fff;border:2px solid #222;box-shadow:0 10px 30px rgba(15,23,42,.10);padding:14px 22px 16px}
        .verify-head{display:flex;align-items:center;justify-content:center;gap:10px;background:#52a9df;color:#fff;padding:9px 18px;margin-bottom:4px;text-align:center;font-size:15px;font-weight:900;letter-spacing:1.2px;text-transform:uppercase}
        .verify-head img{width:24px;height:24px;object-fit:contain;border-radius:50%;background:#fff;padding:2px;flex:0 0 auto}
        .verify-head .title{display:inline-block;line-height:1.2}
        .doc-title{font-size:16px;border-bottom:1px solid #222;padding:2px 0 4px;margin-bottom:2px}
        .verify-row{display:grid;grid-template-columns:200px 18px 1fr;align-items:start;font-size:16px;line-height:1.28;color:#2b2b2b}
        .verify-row span{overflow-wrap:anywhere}
        .verify-row .value{font-style:italic}
        .verify-row.strong .value{font-style:normal}
        .verify-footer{margin-top:12px;color:#64748b;text-align:right;font-size:11px}
        .empty{padding:34px 22px;text-align:center;color:#667085}
        .empty h1{font-size:20px;color:#991b1b;margin-bottom:8px}
        @media(max-width:768px){
            body{padding:10px;background:#f6f8fc}
            .verify-card{padding:12px 14px}
            .verify-row{grid-template-columns:150px 12px 1fr;font-size:14px;line-height:1.34}
            .verify-head{font-size:13px;padding:8px 10px;letter-spacing:.8px}
        }
        @media(max-width:480px){
            body{padding:0;background:#fff}
            .verify-wrap{max-width:none}
            .verify-card{border:0;min-height:100vh;padding:12px}
            .verify-row{grid-template-columns:118px 10px 1fr;font-size:13px}
            .verify-head{font-size:12px;padding:8px 8px;gap:8px}
        }
    </style>
</head>
<body>
    <main class="verify-wrap">
        <section class="verify-card">
            <?php if ($valid && $data): ?>
                <div class="verify-head">
                    <?php if ($schoolIcon): ?><img src="<?php echo BASE_URL . xam_h($schoolIcon); ?>" alt="Logo"><?php endif; ?>
                    <span class="title"><?php echo xam_h($schoolName); ?></span>
                </div>
                <div class="doc-title">Dokumen Sign</div>
                
                <?php 
                $letterNo = xam_compose_letter_number($data['letter_manual_no'], $data['letter_code'], $data['letter_date']);
                ?>
                <div class="verify-row"><span>Nomor Surat</span><span>:</span><span class="value"><?php echo xam_h($letterNo ?: '-'); ?></span></div>
                <div class="verify-row"><span>Tanggal Surat</span><span>:</span><span class="value"><?php echo xam_indo_date($data['sign_date']); ?></span></div>
                <div class="verify-row strong"><span>Penandatangan</span><span>:</span><span class="value"><?php echo xam_h($data['headmaster_name'] ?: '-'); ?></span></div>
                <div class="verify-row strong"><span></span><span></span><span class="value">Kepala <?php echo xam_h($schoolName); ?></span></div>
                <div class="verify-row strong"><span>Perihal</span><span>:</span><span class="value"><?php echo xam_h($data['exam_name']); ?></span></div>
                <div class="verify-row strong"><span>Lampiran</span><span>:</span><span class="value">---</span></div>

                <div style="margin-top:20px; font-size:16px; border-bottom:1px solid #222; padding-bottom:4px;">Identitas Peserta</div>
                <div class="verify-row strong"><span>Nama Siswa</span><span>:</span><span class="value"><?php echo xam_h($data['nama']); ?></span></div>
                <div class="verify-row strong"><span>NIS / NISN</span><span>:</span><span class="value"><?php echo xam_h($data['nis'] . ' / ' . $data['nisn']); ?></span></div>
                <div class="verify-row strong"><span>Kelas</span><span>:</span><span class="value"><?php echo xam_h($data['kelas']); ?></span></div>

                <div class="verify-footer">E-Xam Card - dokumen valid</div>
            <?php else: ?>
                <div class="verify-head">
                    <?php if ($schoolIcon): ?><img src="<?php echo BASE_URL . xam_h($schoolIcon); ?>" alt="Logo"><?php endif; ?>
                    <span class="title"><?php echo xam_h($schoolName); ?></span>
                </div>
                <div class="empty">
                    <h1>Verifikasi gagal</h1>
                    <p>Data dokumen tidak ditemukan atau kode verifikasi tidak sesuai.</p>
                </div>
                <div class="verify-footer">E-Xam Card</div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
