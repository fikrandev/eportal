<?php
/**
 * E-Sarpras Dashboard API
 * Statistics and summary data
 */
require_once __DIR__ . '/../../../api/config.php';

require_once __DIR__ . '/auth_helper.php';
$user = sp_auth();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'stats': getStats(); break;
    default: json_response(400, false, 'Invalid action');
}

function getStats() {
    try {
        $user = sp_auth();
        $stats = [];
        
        $scopeRuang = sp_scope_where($user, 'r', 'id');
        $scopeSarpras = sp_scope_where($user, 's', 'ruang_id');

        // If PJ, Tanah and Bangunan stats are based on their rooms' locations
        $scopeTanah = !empty($user['scoped_ruang_ids']) ? " AND id IN (SELECT tanah_id FROM bangunan WHERE id IN (SELECT bangunan_id FROM ruang WHERE id IN (".implode(',', array_map('intval', $user['scoped_ruang_ids']))."))) " : "";
        $scopeBangunan = !empty($user['scoped_ruang_ids']) ? " AND id IN (SELECT bangunan_id FROM ruang WHERE id IN (".implode(',', array_map('intval', $user['scoped_ruang_ids'])).")) " : "";

        // Total tanah
        $s = db()->query("SELECT COUNT(*) as total, COALESCE(SUM(luas_m2),0) as total_luas, COALESCE(SUM(harga_perolehan),0) as total_nilai FROM tanah WHERE 1=1 $scopeTanah");
        $stats['tanah'] = $s->fetch();

        // Total bangunan
        $s = db()->query("SELECT COUNT(*) as total, COALESCE(SUM(luas_m2),0) as total_luas, COALESCE(SUM(harga_perolehan),0) as total_nilai FROM bangunan WHERE 1=1 $scopeBangunan");
        $stats['bangunan'] = $s->fetch();

        // Kondisi bangunan
        $s = db()->query("SELECT kondisi, COUNT(*) as jumlah FROM bangunan WHERE 1=1 $scopeBangunan GROUP BY kondisi");
        $stats['kondisi_bangunan'] = $s->fetchAll();

        // Total ruang
        $s = db()->query("SELECT COUNT(*) as total FROM ruang r WHERE 1=1 $scopeRuang");
        $stats['ruang'] = $s->fetch();

        // Total sarpras
        $s = db()->query("SELECT COUNT(*) as total_jenis, COALESCE(SUM(jumlah),0) as total_unit, COALESCE(SUM(harga_perolehan*jumlah),0) as total_nilai, COALESCE(SUM(kondisi_baik),0) as baik, COALESCE(SUM(kondisi_rusak_ringan),0) as rusak_ringan, COALESCE(SUM(kondisi_rusak_berat),0) as rusak_berat FROM sarpras s WHERE is_hapus=0 $scopeSarpras");
        $stats['sarpras'] = $s->fetch();

        // 5 perbaikan terbaru (Excluding disposals)
        $s = db()->query("SELECT p.*, s.nama as nama_sarpras, s.kode_inventaris FROM sarpras_perbaikan p JOIN sarpras s ON p.sarpras_id=s.id WHERE p.status != 'Penghapusan' $scopeSarpras ORDER BY p.created_at DESC LIMIT 5");
        $stats['perbaikan_terbaru'] = $s->fetchAll();

        // Aset habis masa manfaat
        $currentYear = date('Y');
        $s = db()->prepare("SELECT s.*, YEAR(s.tanggal_perolehan) as tahun_perolehan, k.nama as kategori_nama, r.nama as ruang_nama FROM sarpras s JOIN kategori_sarpras k ON s.kategori_id=k.id LEFT JOIN ruang r ON s.ruang_id=r.id WHERE s.is_hapus=0 AND (YEAR(s.tanggal_perolehan) + s.masa_manfaat_tahun) <= ? $scopeSarpras ORDER BY (YEAR(s.tanggal_perolehan) + s.masa_manfaat_tahun) ASC LIMIT 10");
        $s->execute([$currentYear]);
        $stats['aset_habis_manfaat'] = $s->fetchAll();

        // Data per kategori
        $s = db()->query("SELECT k.nama as kategori, SUM(s.jumlah) as jumlah_unit, SUM(s.harga_perolehan * s.jumlah) as total_nilai FROM sarpras s JOIN kategori_sarpras k ON s.kategori_id=k.id WHERE s.is_hapus=0 $scopeSarpras GROUP BY k.id ORDER BY jumlah_unit DESC");
        $stats['per_kategori'] = $s->fetchAll();

        json_response(200, true, 'Dashboard stats', $stats);

    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}
