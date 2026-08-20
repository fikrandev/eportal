<?php
require_once __DIR__ . '/api/config_perf.php';

$periode_id = isset($_GET['periode']) ? (int) $_GET['periode'] : 0;

$db = db();
$valid = false;
$row = null;
$schoolName = get_setting('nama_sekolah', 'E-Portal Sekolah');
$schoolIcon = get_setting('icon_sekolah', '');

if ($periode_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT nama_periode, tahun_ajaran, is_released
            FROM perf_periode
            WHERE id = ?
        ");
        $stmt->execute([$periode_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && $row['is_released'] == 1) {
            $valid = true;
        }
    } catch (PDOException $e) {
        $row = null;
    }
}

function h($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Rekapitulasi Kinerja - <?php echo h($schoolName); ?></title>
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
                <div class="doc-title">Dokumen Valid</div>
                <div class="verify-row"><span>Perihal</span><span>:</span><span class="value">Rekapitulasi Penilaian Kinerja Guru</span></div>
                <div class="verify-row"><span>Periode</span><span>:</span><span class="value"><?php echo h($row['nama_periode']); ?> (<?php echo h($row['tahun_ajaran']); ?>)</span></div>

                <div class="verify-footer">E-Performance - dokumen valid</div>
            <?php else: ?>
                <div class="verify-head">
                    <?php if ($schoolIcon): ?><img src="<?php echo BASE_URL . h($schoolIcon); ?>" alt="Logo"><?php endif; ?>
                    <span class="title"><?php echo h($schoolName); ?></span>
                </div>
                <div class="empty">
                    <h1>Verifikasi gagal</h1>
                    <p>Data dokumen tidak ditemukan atau periode penilaian belum dirilis / tidak valid.</p>
                </div>
                <div class="verify-footer">E-Performance</div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
