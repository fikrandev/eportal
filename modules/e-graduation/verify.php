<?php
/**
 * Public SKL verification page.
 */
require_once __DIR__ . '/../../api/config.php';

$studentId = isset($_GET['sid']) ? (int) $_GET['sid'] : 0;
$yearId = isset($_GET['y']) ? (int) $_GET['y'] : 0;
$sig = sanitize($_GET['sig'] ?? '');
if (!empty($_GET['c'])) {
    $parts = explode('.', sanitize($_GET['c']));
    if (count($parts) === 3) {
        $studentId = (int) $parts[0];
        $yearId = (int) $parts[1];
        $sig = $parts[2];
    }
}
$row = null;
$valid = false;
$schoolName = get_setting('nama_sekolah', 'E-Portal Sekolah');
$schoolIcon = get_setting('icon_sekolah', '');

if ($studentId > 0 && $yearId > 0 && $sig !== '') {
    try {
        $stmt = db()->prepare("
            SELECT
                s.id,
                s.nis,
                s.nisn,
                s.nama,
                s.kelas,
                s.tempat_lahir,
                s.tanggal_lahir,
                ay.tahun_ajaran,
                ay.semester,
                l.letter_number,
                l.graduation_date,
                l.signing_date,
                l.headmaster_name,
                l.headmaster_niy,
                l.headmaster_position
            FROM students s
            JOIN academic_years ay ON ay.id = s.academic_year_id
            LEFT JOIN grad_student_letters l ON l.student_id = s.id AND l.academic_year_id = s.academic_year_id
            WHERE s.id = ? AND s.academic_year_id = ?
        ");
        $stmt->execute([$studentId, $yearId]);
        $row = $stmt->fetch();
        if ($row) {
            $expected = graduation_verify_signature($studentId, $yearId, $row['letter_number'] ?? '');
            $valid = hash_equals($expected, $sig);
        }
    } catch (PDOException $e) {
        $row = null;
    }
}

function graduation_verify_signature($studentId, $yearId, $letterNumber)
{
    $secret = DB_NAME . '|' . DB_USER . '|' . DB_PASS;
    return substr(hash_hmac('sha256', (int) $studentId . '|' . (int) $yearId . '|' . (string) $letterNumber, $secret), 0, 24);
}

function h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function indo_date($date, $padDay = false)
{
    if (!$date) return '-';
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($date);
    if (!$ts) return '-';
    $dayFormat = $padDay ? 'd' : 'j';
    return date($dayFormat, $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi SKL - <?php echo h($schoolName); ?></title>
    <?php if ($schoolIcon): ?><link rel="icon" href="<?php echo BASE_URL . h($schoolIcon); ?>"><?php endif; ?>
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
        .verify-block-title{font-size:16px;margin-top:12px;font-weight:400}
        .verify-footer{margin-top:12px;color:#64748b;text-align:right;font-size:11px}
        .empty{padding:34px 22px;text-align:center;color:#667085}
        .empty h1{font-size:20px;color:#991b1b;margin-bottom:8px}
        @media(max-width:768px){
            body{padding:10px;background:#f6f8fc}
            .verify-card{padding:12px 14px}
            .verify-row{grid-template-columns:150px 12px 1fr;font-size:14px;line-height:1.34}
            .verify-head{font-size:13px;padding:8px 10px;letter-spacing:.8px}
            .verify-head img{width:20px;height:20px}
        }
        @media(max-width:480px){
            body{padding:0;background:#fff}
            .verify-wrap{max-width:none}
            .verify-card{border:0;min-height:100vh;padding:12px}
            .verify-row{grid-template-columns:118px 10px 1fr;font-size:13px}
            .doc-title,.verify-block-title{font-size:14px}
            .verify-head{font-size:12px;padding:8px 8px;gap:8px}
            .verify-head img{width:18px;height:18px}
        }
    </style>
</head>
<body>
    <main class="verify-wrap">
        <section class="verify-card">
            <?php if ($valid && $row): ?>
                <div class="verify-head">
                    <?php if ($schoolIcon): ?><img src="<?php echo BASE_URL . h($schoolIcon); ?>" alt="Logo"><?php endif; ?>
                    <span class="title"><?php echo h($schoolName); ?></span>
                </div>
                <div class="doc-title">Dokumen Sign</div>
                <div class="verify-row"><span>Nomor Surat</span><span>:</span><span class="value"><?php echo h($row['letter_number'] ?: '-'); ?></span></div>
                <div class="verify-row"><span>Tanggal Surat</span><span>:</span><span class="value"><?php echo h(indo_date($row['signing_date'] ?: $row['graduation_date'])); ?></span></div>
                <div class="verify-row strong"><span>Penandatangan</span><span>:</span><span class="value"><?php echo h($row['headmaster_name'] ?: '-'); ?></span></div>
                <div class="verify-row strong"><span></span><span></span><span class="value"><?php echo h($row['headmaster_position'] ?: 'Kepala Sekolah'); ?> <?php echo h($schoolName); ?></span></div>
                <div class="verify-row strong"><span>Perihal</span><span>:</span><span class="value">Surat Keterangan Lulus <?php echo h($schoolName); ?></span></div>
                <div class="verify-row strong"><span></span><span></span><span class="value">Tahun Pelajaran <?php echo h($row['tahun_ajaran']); ?></span></div>

                <div class="verify-block-title">Identitas Penerima</div>
                <div class="verify-row strong"><span>Nama</span><span>:</span><span class="value"><?php echo h($row['nama']); ?></span></div>
                <div class="verify-row strong"><span>Tempat dan Tgl. Lahir</span><span>:</span><span class="value"><?php echo h(($row['tempat_lahir'] ? $row['tempat_lahir'] . ', ' : '') . indo_date($row['tanggal_lahir'], true)); ?></span></div>
                <div class="verify-row strong"><span>NIS</span><span>:</span><span class="value"><?php echo h($row['nis']); ?></span></div>
                <div class="verify-row strong"><span>NISN</span><span>:</span><span class="value"><?php echo h($row['nisn']); ?></span></div>
                <div class="verify-row strong"><span>Lampiran</span><span>:</span><span class="value">---</span></div>
                <div class="verify-footer">E-Graduation - dokumen valid</div>
            <?php else: ?>
                <div class="verify-head">
                    <?php if ($schoolIcon): ?><img src="<?php echo BASE_URL . h($schoolIcon); ?>" alt="Logo"><?php endif; ?>
                    <span class="title"><?php echo h($schoolName); ?></span>
                </div>
                <div class="empty">
                    <h1>Verifikasi gagal</h1>
                    <p>Data dokumen tidak ditemukan atau kode verifikasi tidak sesuai.</p>
                </div>
                <div class="verify-footer">E-Graduation</div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
