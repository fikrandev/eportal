<?php
require_once __DIR__ . '/../../api/config.php';

$guru_id = isset($_GET['guru_id']) ? (int) $_GET['guru_id'] : 0;
$dari = $_GET['dari'] ?? '';
$sampai = $_GET['sampai'] ?? '';

$db = db();
$valid = false;
$guru = null;
$jurnal_count = 0;
$schoolName = get_setting('nama_sekolah', 'E-Portal Sekolah');
$schoolIcon = get_setting('icon_sekolah', '');

if ($guru_id > 0 && !empty($dari) && !empty($sampai)) {
    try {
        // Get teacher info
        $stmtGuru = $db->prepare("SELECT nama_lengkap, username, role FROM users WHERE id = ? AND status = 1");
        $stmtGuru->execute([$guru_id]);
        $guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);
        
        if ($guru) {
            // Get active academic year
            $active_year = get_active_academic_year();
            $year_id = $active_year['id'] ?? 0;
            
            // Count matching journal entries
            $stmtCount = $db->prepare("
                SELECT COUNT(*) 
                FROM acad_jurnal 
                WHERE guru_id = ? AND academic_year_id = ? AND tanggal BETWEEN ? AND ?
            ");
            $stmtCount->execute([$guru_id, $year_id, $dari, $sampai]);
            $jurnal_count = (int)$stmtCount->fetchColumn();
            
            if ($jurnal_count > 0) {
                $valid = true;
            }
        }
    } catch (PDOException $e) {
        $valid = false;
    }
}

function h($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDateIndo($dateStr) {
    if (empty($dateStr)) return '';
    $d = new DateTime($dateStr);
    $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $d->format('j') . ' ' . $months[$d->format('n') - 1] . ' ' . $d->format('Y');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Jurnal Mengajar - <?php echo h($schoolName); ?></title>
    <?php if ($schoolIcon): ?><link rel="icon" href="<?php echo BASE_URL . h($schoolIcon); ?>"><?php endif; ?>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;background:#eef2f7;color:#172033;font-family:Arial,Helvetica,sans-serif;padding:28px 14px}
        .verify-wrap{max-width:760px;margin:0 auto}
        .verify-card{background:#fff;border:2px solid #222;box-shadow:0 10px 30px rgba(15,23,42,.10);padding:14px 22px 16px}
        .verify-head{display:flex;align-items:center;justify-content:center;gap:10px;background:#1565c0;color:#fff;padding:9px 18px;margin-bottom:4px;text-align:center;font-size:15px;font-weight:900;letter-spacing:1.2px;text-transform:uppercase}
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
            <?php if ($valid && $guru): ?>
                <div class="verify-head">
                    <?php if ($schoolIcon): ?><img src="<?php echo BASE_URL . h($schoolIcon); ?>" alt="Logo"><?php endif; ?>
                    <span class="title"><?php echo h($schoolName); ?></span>
                </div>
                <div class="doc-title">Dokumen Valid</div>
                <div class="verify-row"><span>Perihal</span><span>:</span><span class="value">Laporan Jurnal Mengajar Kelas</span></div>
                <div class="verify-row"><span>Rentang Tanggal</span><span>:</span><span class="value"><?php echo h(formatDateIndo($dari)); ?> s.d. <?php echo h(formatDateIndo($sampai)); ?></span></div>
                <div class="verify-row"><span>Jumlah Jurnal</span><span>:</span><span class="value"><?php echo h($jurnal_count); ?> Entri KBM Terverifikasi</span></div>

                <div class="verify-block-title">Identitas Pendidik</div>
                <div class="verify-row strong"><span>Nama Lengkap</span><span>:</span><span class="value"><?php echo h($guru['nama_lengkap']); ?></span></div>
                <div class="verify-row strong"><span>NIK / Username</span><span>:</span><span class="value"><?php echo h($guru['username']); ?></span></div>
                <div class="verify-row strong"><span>Peran</span><span>:</span><span class="value"><?php echo h(ucfirst($guru['role'])); ?></span></div>
                
                <div class="verify-footer">E-Curriculum - dokumen valid</div>
            <?php else: ?>
                <div class="verify-head">
                    <?php if ($schoolIcon): ?><img src="<?php echo BASE_URL . h($schoolIcon); ?>" alt="Logo"><?php endif; ?>
                    <span class="title"><?php echo h($schoolName); ?></span>
                </div>
                <div class="empty">
                    <h1>Verifikasi gagal</h1>
                    <p>Data dokumen tidak ditemukan atau jurnal tidak valid / belum diinput.</p>
                </div>
                <div class="verify-footer">E-Curriculum</div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
