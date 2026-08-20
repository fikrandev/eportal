<?php
/**
 * E-Sarpras Public Barcode Scan Result
 * Accessible without authentication
 */
require_once __DIR__ . '/../../api/config.php';

$kode = isset($_GET['kode']) ? sanitize($_GET['kode']) : '';
$item = null;
$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');

if ($kode) {
    try {
        $stmt = db()->prepare("SELECT s.*, k.nama as kategori_nama, k.kode as kategori_kode, r.nama as ruang_nama, r.kode_ruang, b.nama as bangunan_nama, t.nama as tanah_nama
            FROM sarpras s 
            JOIN kategori_sarpras k ON s.kategori_id=k.id 
            JOIN ruang r ON s.ruang_id=r.id 
            JOIN bangunan b ON r.bangunan_id=b.id 
            JOIN tanah t ON b.tanah_id=t.id 
            WHERE s.kode_inventaris=?");
        $stmt->execute([$kode]);
        $item = $stmt->fetch();
        
        if ($item) {
            $fotoStmt = db()->prepare("SELECT foto_path FROM sarpras_foto WHERE sarpras_id=? ORDER BY urutan LIMIT 1");
            $fotoStmt->execute([$item['id']]);
            $foto = $fotoStmt->fetch();
            $item['foto_utama'] = $foto ? $foto['foto_path'] : null;
        }
    } catch (PDOException $e) {
        $item = null;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $kode ? "Detail Sarpras: $kode" : 'Scan Sarpras'; ?> — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <?php if($school_icon): ?><link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>"><?php endif; ?>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Times New Roman', Times, serif;background:#F5F7FA;color:#1A1A2E;min-height:100vh;}
        .scan-header{background:linear-gradient(135deg,#1565C0,#0D47A1);color:white;padding:20px 24px;text-align:center;}
        .scan-header h1{font-family:'Times New Roman', Times, serif;font-size:1.5rem;font-weight:700;}
        .scan-header p{font-size:.9rem;opacity:.8;margin-top:4px;}
        .scan-body{max-width:600px;margin:0 auto;padding:20px;}
        .scan-card{background:white;border-radius:20px;box-shadow:0 4px 20px rgba(0,0,0,.08);overflow:hidden;margin-bottom:20px;}
        .scan-card-header{background:#f8fafc; color:#1e293b; padding:24px; text-align:center; border-bottom:1px solid #e2e8f0; }
        .scan-card-header .kode{font-size:1.75rem;font-weight:800;letter-spacing:1px;color:#1565C0;}
        .scan-card-header .nama{font-size:1.1rem;margin-top:8px;font-weight:700;}
        .scan-foto{width:100%;height:220px;object-fit:cover;}
        .scan-info{padding:24px;}
        .scan-row{display:flex;justify-content:space-between;align-items:flex-start;padding:12px 0;border-bottom:1px solid #F0F2F5;}
        .scan-row:last-child{border:none;}
        .scan-label{font-size:.9rem;color:#64748b;font-weight:600;min-width:120px;}
        .scan-value{font-size:.95rem;font-weight:700;text-align:right;flex:1;}
        .kondisi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px;}
        .kondisi-item{text-align:center;padding:12px;border-radius:12px;}
        .kondisi-item.baik{background:#D1FAE5;color:#059669;}
        .kondisi-item.rr{background:#FEF3C7;color:#D97706;}
        .kondisi-item.rb{background:#FEE2E2;color:#DC2626;}
        .kondisi-item .num{font-size:1.5rem;font-weight:800;}
        .kondisi-item .lbl{font-size:.7rem;font-weight:700;margin-top:2px;}
        .scan-empty{text-align:center;padding:60px 24px;}
        .scan-empty h2{font-size:1.25rem;margin-bottom:8px;}
        .scan-empty p{color:#6B7280;font-size:.875rem;}
        .scan-footer{text-align:center;padding:20px;color:#9CA3AF;font-size:.75rem;}
        .print-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:#1e293b;color:white;border:none;border-radius:12px;font-weight:600;font-size:.875rem;cursor:pointer;margin-top:16px;width:100%;justify-content:center;}
        .print-btn:hover{background:#0f172a;}
        @media print{
            .scan-header,.print-btn,.scan-footer{display:none;}
            body{background:white; padding:0;}
            .scan-body{padding:0;max-width:100%;}
            .scan-card{box-shadow:none;border:1px solid #000;border-radius:0;}
            .scan-card-header{background:white; border-bottom:2px solid #000;}
            .scan-card-header .kode{color:#000;}
            .scan-row{border-bottom:1px solid #000;}
            .kondisi-item{border:1px solid #000;background:white !important;color:black !important;}
        }
    </style>
</head>
<body>
    <div class="scan-header">
        <h1>🏫 <?php echo htmlspecialchars($school_name); ?></h1>
        <p>Sistem Informasi Sarana & Prasarana</p>
    </div>
    <div class="scan-body">
        <?php if ($item): ?>
        <div class="scan-card">
            <div class="scan-card-header">
                <div class="kode"><?php echo htmlspecialchars($item['kode_inventaris']); ?></div>
                <div class="nama"><?php echo htmlspecialchars($item['nama']); ?></div>
            </div>
            <?php if ($item['foto_utama']): ?>
            <img class="scan-foto" src="<?php echo BASE_URL . $item['foto_utama']; ?>" alt="Foto Sarpras" onerror="this.style.display='none'">
            <?php endif; ?>
            <div class="scan-info">
                <div class="scan-row"><span class="scan-label">Kategori</span><span class="scan-value"><?php echo htmlspecialchars($item['kategori_nama']); ?></span></div>
                <div class="scan-row"><span class="scan-label">Merk</span><span class="scan-value"><?php echo htmlspecialchars($item['merk'] ?: '-'); ?></span></div>
                <div class="scan-row"><span class="scan-label">Jumlah</span><span class="scan-value"><?php echo $item['jumlah']; ?> Unit</span></div>
                <div class="scan-row"><span class="scan-label">Tahun Perolehan</span><span class="scan-value"><?php echo $item['tahun_perolehan']; ?></span></div>
                <div class="scan-row"><span class="scan-label">Asal Anggaran</span><span class="scan-value"><?php echo htmlspecialchars($item['asal_perolehan']); ?></span></div>
                <div class="scan-row"><span class="scan-label">Lokasi</span><span class="scan-value"><?php echo htmlspecialchars($item['ruang_nama']); ?><br><small style="color:#6B7280;font-weight:400"><?php echo htmlspecialchars($item['bangunan_nama'].' — '.$item['tanah_nama']); ?></small></span></div>
                <?php if ($item['spesifikasi']): ?>
                <div class="scan-row"><span class="scan-label">Spesifikasi</span><span class="scan-value"><?php echo htmlspecialchars($item['spesifikasi']); ?></span></div>
                <?php endif; ?>
                
                <div class="kondisi-grid">
                    <div class="kondisi-item baik">
                        <div class="num"><?php echo $item['kondisi_baik']; ?></div>
                        <div class="lbl">BAIK</div>
                    </div>
                    <div class="kondisi-item rr">
                        <div class="num"><?php echo $item['kondisi_rusak_ringan']; ?></div>
                        <div class="lbl">RUSAK RINGAN</div>
                    </div>
                    <div class="kondisi-item rb">
                        <div class="num"><?php echo $item['kondisi_rusak_berat']; ?></div>
                        <div class="lbl">RUSAK BERAT</div>
                    </div>
                </div>
                
                <button class="print-btn" onclick="window.print()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Cetak Detail
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="scan-card">
            <div class="scan-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <h2><?php echo $kode ? 'Sarpras Tidak Ditemukan' : 'Scan QR Code'; ?></h2>
                <p><?php echo $kode ? "Kode inventaris \"$kode\" tidak ditemukan dalam database." : 'Silakan scan QR Code pada label sarpras untuk melihat detail.'; ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="scan-footer">
        © <?php echo date('Y'); ?> E-Sarpras — <?php echo htmlspecialchars($school_name); ?>
    </div>
</body>
</html>
