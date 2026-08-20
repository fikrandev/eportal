<?php
/**
 * E-Sarpras Kategori, Perbaikan, Penyusutan, Periodik, Laporan, Roles API
 * Combined into one file for efficiency
 */
require_once __DIR__ . '/../../../api/config.php';

require_once __DIR__ . '/auth_helper.php';

$entity = $_GET['entity'] ?? '';
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? '';

function global_kop_surat_path() {
    $global = trim((string) get_setting('kop_surat', ''));
    if ($global !== '') return $global;
    $legacy = trim((string) get_setting('sarpras_kop_surat', ''));
    return $legacy !== '' ? 'uploads/sarpras/kop/' . $legacy : '';
}

function global_kepala_sekolah($default = '') {
    $value = trim((string) get_setting('kepala_sekolah', ''));
    if ($value !== '') return $value;
    return trim((string) get_setting('sarpras_kepala_sekolah', $default));
}

function report_clean_keterangan($value) {
    $text = trim((string)$value);
    if ($text === '') return '-';
    $text = preg_replace('/\(?\s*disalin\s*\)?/i', '', $text);
    $text = preg_replace('/\s{2,}/', ' ', trim((string)$text));
    return $text !== '' ? $text : '-';
}

switch ($entity) {
    case 'kategori': handleKategori($action); break;
    case 'perbaikan': handlePerbaikan($action); break;
    case 'penyusutan': handlePenyusutan($action); break;
    case 'periodik': handlePeriodik($action); break;
    case 'laporan': handleLaporan($action); break;
    case 'roles': handleRoles($action); break;
    case 'settings': handleSettings($action); break;
    case 'penghapusan': handlePenghapusan($action); break;
    case 'peminjaman': handlePeminjaman($action); break;
    default: json_response(400, false, 'Invalid entity');
}

// ==================== KATEGORI ====================
function handleKategori($action) {
    switch ($action) {
        case 'list':
            sp_auth();
            $stmt = db()->query("SELECT k.*, (SELECT COUNT(*) FROM sarpras WHERE kategori_id=k.id AND is_hapus=0) as jumlah_sarpras FROM kategori_sarpras k ORDER BY k.kode ASC, k.nama ASC");
            json_response(200, true, 'OK', $stmt->fetchAll());
            break;
        case 'create':
            $user = sp_auth(); sp_require_any($user, ['referensi_manage'], 'Akses ditolak');
            $d = get_input();
            try {
                db()->prepare("INSERT INTO kategori_sarpras (nama,kode,jenis,keterangan) VALUES (?,?,?,?)")->execute([sanitize($d['nama']??''), strtoupper(sanitize($d['kode']??'')), sanitize($d['jenis']??'sarana'), sanitize($d['keterangan']??'')]);
                json_response(201, true, 'Kategori ditambahkan');
            } catch (PDOException $e) { json_response(500, false, 'Error: '.$e->getMessage()); }
            break;
        case 'update':
            $user = sp_auth(); sp_require_any($user, ['referensi_manage'], 'Akses ditolak');
            $d = get_input();
            try {
                db()->prepare("UPDATE kategori_sarpras SET nama=?,kode=?,jenis=?,keterangan=? WHERE id=?")->execute([sanitize($d['nama']??''), strtoupper(sanitize($d['kode']??'')), sanitize($d['jenis']??'sarana'), sanitize($d['keterangan']??''), (int)($d['id']??0)]);
                json_response(200, true, 'Kategori diperbarui');
            } catch (PDOException $e) { json_response(500, false, 'Error: '.$e->getMessage()); }
            break;
        case 'delete':
            $user = sp_auth(); sp_require_any($user, ['referensi_manage'], 'Akses ditolak');
            $d = get_input();
            $id = (int)($d['id']??0);
            
            // Check active sarpras
            $cnt = db()->prepare("SELECT COUNT(*) as t FROM sarpras WHERE kategori_id=? AND is_hapus=0"); 
            $cnt->execute([$id]);
            if ($cnt->fetch()['t'] > 0) json_response(400, false, 'Kategori tidak dapat dihapus karena masih digunakan oleh item Sarpras yang aktif.');
            
            // Check master sarpras
            try {
                $cnt2 = db()->prepare("SELECT COUNT(*) as t FROM master_sarpras WHERE kategori_id=?"); 
                $cnt2->execute([$id]);
                if ($cnt2->fetch()['t'] > 0) json_response(400, false, 'Kategori tidak dapat dihapus karena masih digunakan di Data Master Sarpras.');
            } catch (Exception $e) {
                // Ignore if table doesn't exist
            }
            
            try {
                db()->beginTransaction();
                
                // Hard delete associated soft-deleted sarpras items to satisfy FK constraint
                db()->prepare("DELETE FROM sarpras WHERE kategori_id=? AND is_hapus=1")->execute([$id]);
                
                // Delete the category itself
                db()->prepare("DELETE FROM kategori_sarpras WHERE id=?")->execute([$id]);
                
                db()->commit();
                json_response(200, true, 'Kategori berhasil dihapus');
            } catch (PDOException $e) {
                db()->rollBack();
                json_response(400, false, 'Gagal menghapus kategori. Kategori ini terhubung ke data lain yang tidak bisa dihapus.');
            }
            break;
    }
}

// ==================== PERBAIKAN ====================
function handlePerbaikan($action) {
    $user = sp_auth();
    sp_require_any($user, ['perbaikan_manage'], 'Akses ditolak');

    switch ($action) {
        case 'list':
            $status = $_GET['status'] ?? '';
            $where = "WHERE s.is_hapus = 0 AND p.status != 'Penghapusan'"; $params = [];
            $where .= sp_scope_where($user, 's', 'ruang_id');
            if ($status) { $where .= " AND p.status=?"; $params[] = $status; }
            $stmt = db()->prepare("SELECT p.*, s.nama as sarpras_nama, s.kode_inventaris, r.nama as ruang_nama, u.nama_lengkap as pelapor FROM sarpras_perbaikan p JOIN sarpras s ON p.sarpras_id=s.id LEFT JOIN ruang r ON s.ruang_id=r.id LEFT JOIN users u ON p.updated_by=u.id $where ORDER BY p.created_at DESC");
            $stmt->execute($params);
            json_response(200, true, 'OK', $stmt->fetchAll());
            break;
        case 'create':
            $d = get_input();
            $sid = (int)($d['sarpras_id']??0);
            $status = sanitize($d['status']??'Diajukan');
            if (!$sid) json_response(400, false, 'ID Barang tidak valid');
            
            try {
                $stmt = db()->prepare("INSERT INTO sarpras_perbaikan (sarpras_id,tanggal,tanggal_selesai,deskripsi,biaya,vendor,no_spk,status,alasan_batal,catatan,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $sid, 
                    !empty($d['tanggal']) ? $d['tanggal'] : date('Y-m-d'), 
                    !empty($d['tanggal_selesai']) ? $d['tanggal_selesai'] : null, 
                    sanitize($d['deskripsi']??''), 
                    floatval($d['biaya']??0), 
                    sanitize($d['vendor']??''), 
                    sanitize($d['no_spk']??''), 
                    $status, 
                    sanitize($d['alasan_batal']??''), 
                    sanitize($d['catatan']??''), 
                    $user['user_id']
                ]);
                $newId = db()->lastInsertId();
                processBuktiBayar($newId);
                
                // Add to Log
                db()->prepare("INSERT INTO sarpras_perbaikan_log (perbaikan_id, tanggal, status, catatan, updated_by) VALUES (?,?,?,?,?)")->execute([
                    $newId,
                    !empty($d['tanggal']) ? $d['tanggal'] : date('Y-m-d'),
                    $status,
                    sanitize($d['catatan']??'Baru diajukan'),
                    $user['user_id']
                ]);

                if ($status === 'Penghapusan') {
                    db()->prepare("UPDATE sarpras SET is_hapus=1, tanggal_hapus=? WHERE id=?")->execute([date('Y-m-d'), $sid]);
                }
                
                json_response(201, true, 'Perbaikan dicatat', ['id' => $newId]);
            } catch (PDOException $e) { json_response(500, false, 'Error Simpan: '.$e->getMessage()); }
            break;
        case 'update':
            $d = get_input();
            $id = (int)($d['id']??0);
            $sid = (int)($d['sarpras_id']??0);
            $status = sanitize($d['status']??'Diajukan');
            if (!$id) json_response(400, false, 'ID Perbaikan tidak valid');
            
            try {
                $stmt = db()->prepare("UPDATE sarpras_perbaikan SET tanggal=?,tanggal_selesai=?,deskripsi=?,biaya=?,vendor=?,no_spk=?,status=?,alasan_batal=?,catatan=?,updated_by=? WHERE id=?");
                $stmt->execute([
                    !empty($d['tanggal']) ? $d['tanggal'] : date('Y-m-d'), 
                    !empty($d['tanggal_selesai']) ? $d['tanggal_selesai'] : null, 
                    sanitize($d['deskripsi']??''), 
                    floatval($d['biaya']??0), 
                    sanitize($d['vendor']??''), 
                    sanitize($d['no_spk']??''), 
                    $status, 
                    sanitize($d['alasan_batal']??''), 
                    sanitize($d['catatan']??''), 
                    $user['user_id'], 
                    $id
                ]);
                processBuktiBayar($id);
                
                // Add to Log
                db()->prepare("INSERT INTO sarpras_perbaikan_log (perbaikan_id, tanggal, status, catatan, updated_by) VALUES (?,?,?,?,?)")->execute([
                    $id,
                    !empty($d['tanggal']) ? $d['tanggal'] : date('Y-m-d'),
                    $status,
                    sanitize($d['catatan']??'Update status'),
                    $user['user_id']
                ]);

                if ($status === 'Penghapusan') {
                    db()->prepare("UPDATE sarpras SET is_hapus=1, tanggal_hapus=? WHERE id=?")->execute([date('Y-m-d'), $sid]);
                }
                
                json_response(200, true, 'Perbaikan diperbarui');
            } catch (PDOException $e) { json_response(500, false, 'Error Update: '.$e->getMessage()); }
            break;
        case 'delete':
            $d = get_input();
            db()->prepare("DELETE FROM sarpras_perbaikan WHERE id=?")->execute([(int)($d['id']??0)]);
            db()->prepare("DELETE FROM sarpras_perbaikan_log WHERE perbaikan_id=?")->execute([(int)($d['id']??0)]);
            json_response(200, true, 'Perbaikan dihapus');
            break;
        case 'log':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = db()->prepare("SELECT l.*, u.nama_lengkap as updated_by_name FROM sarpras_perbaikan_log l LEFT JOIN users u ON l.updated_by = u.id WHERE l.perbaikan_id = ? ORDER BY l.created_at DESC");
            $stmt->execute([$id]);
            json_response(200, true, 'OK', $stmt->fetchAll());
            break;
    }
}

function processBuktiBayar($id) {
    if (!isset($_FILES['bukti_bayar']) || $_FILES['bukti_bayar']['error'] !== UPLOAD_ERR_OK) return;
    $ext = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'])) return;
    
    $dir = UPLOAD_DIR . 'sarpras/bukti_bayar/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    $filename = 'bukti_' . $id . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], $dir . $filename)) {
        db()->prepare("UPDATE sarpras_perbaikan SET bukti_bayar=? WHERE id=?")->execute([$filename, $id]);
    }
}

// ==================== PENYUSUTAN ====================
function handlePenyusutan($action) {
    $user = sp_auth();
    sp_require_any($user, ['report_view'], 'Akses ditolak');

    switch ($action) {
        case 'list':
        case 'excel':
        case 'pdf':
            $bulan = (int)($_GET['bulan'] ?? date('n'));
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            
            $stmt = db()->query("
                SELECT s.id, s.kode_inventaris, s.nama as sarpras_nama, s.tanggal_perolehan as tahun_perolehan, 
                       s.harga_perolehan, s.jumlah, s.masa_manfaat_tahun,
                       k.nama as kategori_nama, r.nama as ruang_nama
                FROM sarpras s 
                JOIN kategori_sarpras k ON s.kategori_id=k.id 
                LEFT JOIN ruang r ON s.ruang_id=r.id 
                WHERE s.is_hapus=0
                " . sp_scope_where($user, 's', 'ruang_id') . "
                ORDER BY s.nama
            ");
            $items = $stmt->fetchAll();
            $results = [];
            
            $t_perolehan = 0; $t_beban = 0; $t_akumulasi = 0; $t_buku = 0;
            
            foreach ($items as $item) {
                // Harga Dasar
                $hargaTotal = floatval($item['harga_perolehan']) * intval($item['jumlah']);
                if ($hargaTotal <= 0) continue; // Skip jika tidak ada harga
                
                $masaTahun = intval($item['masa_manfaat_tahun'] ?? 0);
                $thnBeli = (int)date('Y', strtotime($item['tahun_perolehan'] ?? $tahun));
                
                $totalBulanManfaat = $masaTahun * 12;
                $bebanBulanan = ($totalBulanManfaat > 0) ? ($hargaTotal / $totalBulanManfaat) : 0;
                
                // Selisih bulan dari Jan (Tahun Beli) ke Bulan (Tahun Filter)
                $bulanBerlalu = (($tahun - $thnBeli) * 12) + ($bulan - 1);
                
                if ($bulanBerlalu < 0) {
                    $akumulasi = 0; $bebanBulanIni = 0; $nilaiBuku = $hargaTotal;
                } else if ($bulanBerlalu >= $totalBulanManfaat) {
                    $akumulasi = $hargaTotal; $bebanBulanIni = 0; $nilaiBuku = 0;
                } else {
                    $akumulasi = $bebanBulanan * $bulanBerlalu;
                    $bebanBulanIni = $bebanBulanan;
                    $nilaiBuku = $hargaTotal - $akumulasi;
                }
                
                $bebanBulanIni = round($bebanBulanIni); $akumulasi = round($akumulasi); $nilaiBuku = round($nilaiBuku);
                if ($nilaiBuku < 0) $nilaiBuku = 0;
                
                $t_perolehan += $hargaTotal; $t_beban += $bebanBulanIni; $t_akumulasi += $akumulasi; $t_buku += $nilaiBuku;
                
                $results[] = [
                    'sarpras_nama' => $item['sarpras_nama'],
                    'kode_inventaris' => $item['kode_inventaris'],
                    'kategori_nama' => $item['kategori_nama'],
                    'ruang_nama' => $item['ruang_nama'] ?? 'Gudang',
                    'tahun_perolehan' => $thnBeli,
                    'masa_manfaat_tahun' => $masaTahun,
                    'nilai_perolehan' => $hargaTotal,
                    'beban_penyusutan' => $bebanBulanIni,
                    'akumulasi_penyusutan' => $akumulasi,
                    'nilai_buku' => $nilaiBuku
                ];
            }
            
            if ($action === 'list') {
                json_response(200, true, 'OK', ['items' => $results, 'summary' => ['total_item' => count($results), 'total_perolehan' => $t_perolehan, 'total_beban' => $t_beban, 'total_akumulasi' => $t_akumulasi, 'total_nilai_buku' => $t_buku], 'bulan' => $bulan, 'tahun' => $tahun]);
            } else {
                $school_name = get_setting('nama_sekolah', 'Sekolah');
                $school_kepala = global_kepala_sekolah('.............................');
                $school_waka = get_setting('sarpras_waka_sarpras', '.............................');
                $kopSurat = global_kop_surat_path();
                $months = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                $periodStr = strtoupper($months[$bulan]) . ' ' . $tahun;

                $isExcel = ($action === 'excel');
                if ($isExcel) {
                    header("Content-Type: application/vnd.ms-excel");
                    header("Content-Disposition: attachment; filename=\"Laporan_Penyusutan_{$bulan}_{$tahun}.xls\"");
                }

                $css = '
                <style>
                    * { margin:0; padding:0; box-sizing:border-box; }
                    body { font-family: "Times New Roman", Times, serif; font-size: 10pt; color: #000; padding: 20px; background: white; line-height: 1.3; }
                    .kop-surat { width: 100%; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
                    .kop-surat img { max-width: 100%; height: auto; }
                    .header { width: 100%; text-align: center; margin-bottom: 20px; }
                    .header h1 { font-size: 14pt; margin: 0; text-transform: uppercase; }
                    .header p { font-size: 11pt; margin: 5px 0 0; font-weight: bold; }
                    .title { text-align: center; margin-bottom: 20px; text-decoration: underline; font-weight: bold; font-size: 12pt; text-transform: uppercase; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                    th, td { border: 1px solid #000; padding: 5px 8px; }
                    th { background: #f0f0f0; text-transform: uppercase; font-size: 9pt; }
                    .right { text-align: right; }
                    .center { text-align: center; }
                    .bold { font-weight: bold; }
                    .ttd-wrapper { display: flex; justify-content: space-between; margin-top: 40px; page-break-inside: avoid; }
                    .ttd { text-align: center; width: 250px; }
                    .ttd .label { margin-bottom: 70px; font-weight: bold; }
                    .ttd .nama { font-weight: bold; text-decoration: underline; }
                    .footer-note { font-style: italic; font-size: 9pt; color: #333; margin-top: 10px; }
                    @media print { .btn-print { display:none; } @page { margin: 10mm; } }
                    .btn-print { position:fixed; top:20px; right:20px; background:#1e293b; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; }
                </style>';

                echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Laporan Penyusutan</title>{$css}</head><body>";
                if (!$isExcel) echo '<button class="btn-print" onclick="window.print()">Cetak Laporan</button>';
                
                if ($kopSurat) {
                    echo '<div class="kop-surat"><img src="../../../' . htmlspecialchars($kopSurat) . '"></div>';
                }
                
                echo "<div class='header'><h1>" . htmlspecialchars($school_name) . "</h1><p>E-SARPRAS — SISTEM INFORMASI SARANA & PRASARANA</p></div>";
                echo "<div class='title'>LAPORAN PENYUSUTAN ASET TETAP BERJALAN<br>PERIODE {$periodStr}</div>";

                echo "<table><thead><tr>
                    <th>No</th><th>Kode Inventaris</th><th>Nama Barang</th>
                    <th>Thn Beli</th><th>Nilai Perolehan</th>
                    <th>Beban Bulanan</th><th>Akumulasi</th><th>Nilai Buku</th>
                </tr></thead><tbody>";

                foreach($results as $i=>$r) {
                    echo "<tr>
                        <td class='center'>".($i+1)."</td>
                        <td class='center'>{$r['kode_inventaris']}</td>
                        <td>{$r['sarpras_nama']}</td>
                        <td class='center'>{$r['tahun_perolehan']}</td>
                        <td class='right'>".number_format($r['nilai_perolehan'],0,',','.')."</td>
                        <td class='right'>".number_format($r['beban_penyusutan'],0,',','.')."</td>
                        <td class='right'>".number_format($r['akumulasi_penyusutan'],0,',','.')."</td>
                        <td class='right' style='font-weight:bold'>".number_format($r['nilai_buku'],0,',','.')."</td>
                    </tr>";
                }

                echo "</tbody><tfoot><tr class='bold' style='background:#f0f0f0'>
                    <td colspan='4' class='center'>TOTAL KESELURUHAN</td>
                    <td class='right'>".number_format($t_perolehan,0,',','.')."</td>
                    <td class='right'>".number_format($t_beban,0,',','.')."</td>
                    <td class='right'>".number_format($t_akumulasi,0,',','.')."</td>
                    <td class='right'>".number_format($t_buku,0,',','.')."</td>
                </tr></tfoot></table>";

                echo '<div class="ttd-wrapper">';
                echo '  <div class="ttd"><div class="label">MENGETAHUI,<br>WAKA. SARANA PRASARANA</div><div class="nama">'.$school_waka.'</div></div>';
                echo '  <div class="ttd"><div class="label">PALU, '.date('d F Y').'<br>MENGETAHUI,<br>KEPALA SEKOLAH</div><div class="nama">'.$school_kepala.'</div></div>';
                echo '</div>';
                
                echo '<p class="footer-note">Data telah diperbarui pertanggal ' . date('d F Y') . '</p>';

                if (!$isExcel) echo "<script>window.print();</script>";
                echo "</body></html>";
                exit;
            }
            break;
    }
}

// ==================== PERIODIK ====================
function handlePeriodik($action) {
    $user = sp_auth();
    sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');

    switch ($action) {
        case 'list':
            $periode = $_GET['periode'] ?? '';
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            $params = [$tahun];
            $periodeWhere = '';
            if ($periode) { $periodeWhere = " AND sp.periode=?"; $params[] = $periode; }
            
            $where = "WHERE s.is_hapus=0" . sp_scope_where($user, 's', 'ruang_id');
            $stmt = db()->prepare("SELECT s.id, s.nama, s.kode_inventaris, s.jumlah, s.kondisi_baik, s.kondisi_rusak_ringan, s.kondisi_rusak_berat, r.nama as ruang_nama, sp.id as periodik_id, sp.periode, sp.kondisi_baik as p_baik, sp.kondisi_rusak_ringan as p_rr, sp.kondisi_rusak_berat as p_rb, sp.catatan as p_catatan
                FROM sarpras s 
                LEFT JOIN ruang r ON s.ruang_id=r.id 
                LEFT JOIN sarpras_periodik sp ON sp.sarpras_id=s.id AND sp.tahun=? $periodeWhere
                $where
                ORDER BY r.nama, s.nama");
            $stmt->execute($params);
            json_response(200, true, 'OK', $stmt->fetchAll());
            break;
            
        case 'save':
            $d = get_input();
            $items = $d['items'] ?? [];
            $periode = sanitize($d['periode'] ?? '');
            $tahun = (int)($d['tahun'] ?? date('Y'));
            if (!in_array($periode, ['Q1','Q2','Q3','Q4'])) json_response(400, false, 'Periode tidak valid');
            foreach ($items as $item) {
                db()->prepare("INSERT INTO sarpras_periodik (sarpras_id,periode,tahun,kondisi_baik,kondisi_rusak_ringan,kondisi_rusak_berat,catatan,updated_by) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE kondisi_baik=VALUES(kondisi_baik), kondisi_rusak_ringan=VALUES(kondisi_rusak_ringan), kondisi_rusak_berat=VALUES(kondisi_rusak_berat), catatan=VALUES(catatan), updated_by=VALUES(updated_by)")->execute([(int)$item['sarpras_id'], $periode, $tahun, (int)($item['kondisi_baik']??0), (int)($item['kondisi_rusak_ringan']??0), (int)($item['kondisi_rusak_berat']??0), sanitize($item['catatan']??''), $user['user_id']]);
                db()->prepare("UPDATE sarpras SET kondisi_baik=?, kondisi_rusak_ringan=?, kondisi_rusak_berat=? WHERE id=?")->execute([(int)($item['kondisi_baik']??0), (int)($item['kondisi_rusak_ringan']??0), (int)($item['kondisi_rusak_berat']??0), (int)$item['sarpras_id']]);
            }
            json_response(200, true, "Data periodik disimpan");
            break;
    }
}


// ==================== ROLES ====================
function handleRoles($action) {
    $user = sp_auth();
    sp_require_any($user, ['roles_manage'], 'Akses ditolak');
    
    switch ($action) {
        case 'list_roles': // Get all custom roles
            $stmt = db()->query("SELECT r.*, (SELECT GROUP_CONCAT(permission_key) FROM sarpras_role_permissions WHERE role_id=r.id) as permissions FROM sarpras_roles_def r ORDER BY r.is_locked DESC, r.nama ASC");
            json_response(200, true, 'OK', $stmt->fetchAll());
            break;

        case 'save_role': // Create/Update custom role
            $d = get_input();
            $id = (int)($d['id'] ?? 0);
            $nama = sanitize($d['nama'] ?? '');
            $perms = $d['permissions'] ?? []; // Array of keys

            if (!$nama) json_response(400, false, 'Nama role wajib diisi');

            try {
                db()->beginTransaction();
                if ($id > 0) {
                    $stmt = db()->prepare("UPDATE sarpras_roles_def SET nama=?, deskripsi=? WHERE id=? AND is_locked=0");
                    $stmt->execute([$nama, sanitize($d['deskripsi']??''), $id]);
                } else {
                    $stmt = db()->prepare("INSERT INTO sarpras_roles_def (nama, deskripsi) VALUES (?,?)");
                    $stmt->execute([$nama, sanitize($d['deskripsi']??'')]);
                    $id = db()->lastInsertId();
                }

                // Sync permissions
                db()->prepare("DELETE FROM sarpras_role_permissions WHERE role_id=?")->execute([$id]);
                $stmt_p = db()->prepare("INSERT INTO sarpras_role_permissions (role_id, permission_key) VALUES (?,?)");
                foreach ($perms as $p) {
                    $stmt_p->execute([$id, sanitize($p)]);
                }

                db()->commit();
                json_response(200, true, 'Role berhasil disimpan');
            } catch (Exception $e) { if(db()->inTransaction()) db()->rollBack(); json_response(500, false, $e->getMessage()); }
            break;

        case 'delete_role':
            $id = (int)(get_input()['id'] ?? 0);
            $check = db()->prepare("SELECT is_locked FROM sarpras_roles_def WHERE id=?"); $check->execute([$id]);
            if ($check->fetchColumn()) json_response(400, false, 'Role sistem tidak dapat dihapus');
            db()->prepare("DELETE FROM sarpras_roles_def WHERE id=?")->execute([$id]);
            json_response(200, true, 'Role dihapus');
            break;

        case 'accounts_list': // List users with their sarpras access
            $stmt = db()->query("
                SELECT u.id, u.username, u.nama_lengkap, u.nik, u.role as portal_role, 
                       sr.role as sarpras_role_legacy, sr.custom_role_id, rd.nama as custom_role_nama,
                       pj.nama as pj_name
                FROM users u 
                LEFT JOIN sarpras_roles sr ON u.id = sr.user_id 
                LEFT JOIN sarpras_roles_def rd ON sr.custom_role_id = rd.id
                LEFT JOIN sarpras_pj pj ON u.id = pj.user_id
                WHERE u.status = 1 AND u.role NOT IN ('siswa', 'orangtua')
                ORDER BY u.nama_lengkap ASC
            ");
            json_response(200, true, 'OK', $stmt->fetchAll());
            break;

        case 'assign_account': // Assign role to user
            $d = get_input();
            $uid = (int)($d['user_id'] ?? 0);
            $rid = (int)($d['role_id'] ?? 0); // custom_role_id
            if ($uid <= 0) json_response(400, false, 'User tidak valid');

            if ($rid <= 0) {
                db()->prepare("DELETE FROM sarpras_roles WHERE user_id=?")->execute([$uid]);
                json_response(200, true, 'Akses user berhasil dicabut');
            }
             
            // Get base role name from def
            $stmt_r = db()->prepare("
                SELECT rd.nama, GROUP_CONCAT(rp.permission_key) as permissions
                FROM sarpras_roles_def rd
                LEFT JOIN sarpras_role_permissions rp ON rp.role_id = rd.id
                WHERE rd.id=?
                GROUP BY rd.id, rd.nama
            ");
            $stmt_r->execute([$rid]);
            $roleRow = $stmt_r->fetch();
            $rname = $roleRow['nama'] ?? '';
            if (!$rname) json_response(404, false, 'Role tidak ditemukan');
            $rolePermissions = sp_normalize_permissions(explode(',', (string) ($roleRow['permissions'] ?? '')));
            $base_role = sp_has_any(['permissions' => $rolePermissions], ['roles_manage', 'settings_manage']) ? 'admin_sarpras' : 'operator_sarpras';

            db()->prepare("INSERT INTO sarpras_roles (user_id, custom_role_id, role) VALUES (?,?,?) ON DUPLICATE KEY UPDATE custom_role_id=?, role=?")
              ->execute([$uid, $rid, $base_role, $rid, $base_role]);
            json_response(200, true, 'Akses user berhasil diperbarui');
            break;

        case 'generate_pj_accounts':
            // 1. Get PJs who don't have accounts linked
            $stmt = db()->query("SELECT * FROM sarpras_pj WHERE user_id IS NULL OR user_id = 0");
            $pjs = $stmt->fetchAll();
            $count = 0;
            $logs = [];

            // Get PJ Role ID
            $pj_role_id = db()->query("SELECT id FROM sarpras_roles_def WHERE nama='Penanggung Jawab Ruangan'")->fetchColumn();
            if (!$pj_role_id) json_response(400, false, 'Role Penanggung Jawab tidak ditemukan. Jalankan migrasi.');

            foreach ($pjs as $pj) {
                // Generate username: pj_ + name sanitized
                $username = 'pj_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $pj['nama']));
                // Check if username exists, if so append unique
                $check = db()->prepare("SELECT COUNT(*) FROM users WHERE username=?"); $check->execute([$username]);
                if ($check->fetchColumn() > 0) $username .= rand(10, 99);

                $password = '12345678';
                $hash = password_hash($password, PASSWORD_BCRYPT);

                try {
                    db()->beginTransaction();
                    // Insert into portal users
                    $stmt_u = db()->prepare("INSERT INTO users (username, password, nama_lengkap, role, status) VALUES (?,?,?,?,?)");
                    $stmt_u->execute([$username, $hash, $pj['nama'], 'guru', 1]);
                    $new_uid = db()->lastInsertId();

                    // Link to sarpras_pj
                    db()->prepare("UPDATE sarpras_pj SET user_id=? WHERE id=?")->execute([$new_uid, $pj['id']]);

                    // Assign role
                    db()->prepare("INSERT INTO sarpras_roles (user_id, custom_role_id, role) VALUES (?,?,?)")
                      ->execute([$new_uid, $pj_role_id, 'operator_sarpras']);

                    db()->commit();
                    $count++;
                    $logs[] = "{$pj['nama']} -> User: $username / Pass: $password";
                } catch (Exception $e) { if(db()->inTransaction()) db()->rollBack(); }
            }
            json_response(200, true, "Berhasil membuat $count akun otomatis.", $logs);
            break;
    }
}

// ==================== PRINT HTML LAPORAN ====================
function handlePrintLaporan($type) {
    if ($type === 'perbaikan-log') {
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-d');
        $stmt = db()->prepare("SELECT p.*, s.nama as sarpras_nama, s.kode_inventaris FROM sarpras_perbaikan p JOIN sarpras s ON p.sarpras_id=s.id WHERE p.tanggal BETWEEN ? AND ? ORDER BY p.tanggal ASC");
        $stmt->execute([$start, $end]);
        $data = $stmt->fetchAll();
        
        $school = get_setting('nama_sekolah', 'E-Portal Sekolah');
        $kopSurat = global_kop_surat_path();
        
        $tbody = ''; $total = 0;
        foreach($data as $i=>$r) {
            $total += floatval($r['biaya']);
            $tbody .= "<tr><td style='text-align:center'>".($i+1)."</td><td>{$r['tanggal']}</td><td>{$r['sarpras_nama']} ({$r['kode_inventaris']})</td><td>{$r['deskripsi']}</td><td style='text-align:right'>Rp ".number_format($r['biaya'],0,',','.')."</td><td style='text-align:center'>{$r['status']}</td></tr>";
        }
        
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Log Perbaikan</title>
        <style>
            @page { size: A4; margin: 15mm 20mm; }
            body { font-family: 'Times New Roman', serif; font-size: 12pt; color: #000; margin: 0; padding: 20px; }
            .kop-img { width: 100%; max-height: 120px; object-fit: contain; }
            .kop-line { margin-bottom: 20px; padding-bottom: 5px; }
            table.data { width: 100%; border-collapse: collapse; margin-top: 15px; }
            table.data th, table.data td { border: 1px solid #000; padding: 6px 8px; font-size: 11pt; }
            table.data th { background: #f0f0f0; }
            @media print { body { padding: 0; } }
        </style></head><body>";
        
        if ($kopSurat) {
            echo "<div class='kop-line'><img class='kop-img' src='../../../" . htmlspecialchars($kopSurat) . "'></div>";
        } else {
            echo "<div class='kop-line' style='text-align:center'><h2 style='margin:0'>{$school}</h2><p style='margin:5px 0'>Laporan Riwayat Perbaikan</p></div>";
        }
        
        echo "<h3 style='text-align:center; margin:20px 0'>LAPORAN RIWAYAT PERBAIKAN</h3>";
        echo "<p>Periode: {$start} s/d {$end}</p>";
        echo "<table class='data'><thead><tr><th>No</th><th>Tanggal</th><th>Barang</th><th>Deskripsi</th><th>Biaya</th><th>Status</th></tr></thead><tbody>{$tbody}</tbody>";
        echo "<tfoot><tr style='font-weight:bold'><td colspan='4' style='text-align:right'>TOTAL BIAYA</td><td style='text-align:right'>Rp ".number_format($total,0,',','.')."</td><td></td></tr></tfoot></table>";
        echo "<script>window.print();</script></body></html>";
        exit;
        
    } elseif ($type === 'berita-acara') {
        $id = (int)($_GET['id'] ?? 0);
        $baId = (int)($_GET['ba_id'] ?? 0);
        
        $items = [];
        $nomorBa = '................';
        $tanggalDoc = date('Y-m-d');
        
        if ($baId) {
            $stmt = db()->prepare("SELECT p.*, s.nama as sarpras_nama, s.kode_inventaris, s.jumlah, ba.nomor_ba, ba.tanggal_ba FROM sarpras_perbaikan p JOIN sarpras s ON p.sarpras_id=s.id JOIN sarpras_berita_acara ba ON p.berita_acara_id=ba.id WHERE p.berita_acara_id=?");
            $stmt->execute([$baId]);
            $items = $stmt->fetchAll();
            if (empty($items)) die("Data Berita Acara tidak ditemukan");
            $nomorBa = $items[0]['nomor_ba'];
            $tanggalDoc = $items[0]['tanggal_ba'];
        } else {
            $stmt = db()->prepare("SELECT p.*, s.nama as sarpras_nama, s.kode_inventaris, s.jumlah FROM sarpras_perbaikan p JOIN sarpras s ON p.sarpras_id=s.id WHERE p.id=?");
            $stmt->execute([$id]);
            $r = $stmt->fetch();
            if (!$r) die("Data tidak ditemukan");
            $items = [$r];
            $tanggalDoc = $r['tanggal'];
        }

        $school = get_setting('nama_sekolah', 'E-Portal Sekolah');
        $kopSurat = global_kop_surat_path();
        $kepalaSekolah = global_kepala_sekolah('.............................');
        $wakaSarpras = get_setting('sarpras_waka_sarpras', '.............................');
        
        // Terbilang tanggal
        $tgl = strtotime($tanggalDoc);
        $hariArr = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        $bulanArr = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $hariNama = $hariArr[date('w', $tgl)];
        $tanggalNum = date('j', $tgl);
        $bulanNama = $bulanArr[(int)date('n', $tgl)];
        $tahunNum = date('Y', $tgl);
        
        $terbilangArr = ['','Satu','Dua','Tiga','Empat','Lima','Enam','Tujuh','Delapan','Sembilan','Sepuluh',
            'Sebelas','Dua Belas','Tiga Belas','Empat Belas','Lima Belas','Enam Belas','Tujuh Belas',
            'Delapan Belas','Sembilan Belas','Dua Puluh','Dua Puluh Satu','Dua Puluh Dua','Dua Puluh Tiga',
            'Dua Puluh Empat','Dua Puluh Lima','Dua Puluh Enam','Dua Puluh Tujuh','Dua Puluh Delapan',
            'Dua Puluh Sembilan','Tiga Puluh','Tiga Puluh Satu'];
        $tglTerbilang = $terbilangArr[$tanggalNum] ?? $tanggalNum;
        
        function terbilangTahun($tahun) {
            $ribuan = floor($tahun / 1000);
            $sisa = $tahun - ($ribuan * 1000);
            $ratusan = floor($sisa / 100);
            $sisa2 = $sisa - ($ratusan * 100);
            $puluhan = floor($sisa2 / 10);
            $satuan = $sisa2 % 10;
            $arr = ['','Satu','Dua','Tiga','Empat','Lima','Enam','Tujuh','Delapan','Sembilan'];
            $result = '';
            if ($ribuan == 2) $result .= 'Dua Ribu ';
            elseif ($ribuan == 1) $result .= 'Seribu ';
            if ($ratusan > 0) $result .= $arr[$ratusan] . ' Ratus ';
            if ($puluhan == 1) {
                if ($satuan == 0) $result .= 'Sepuluh';
                elseif ($satuan == 1) $result .= 'Sebelas';
                else $result .= $arr[$satuan] . ' Belas';
            } else {
                if ($puluhan > 0) $result .= $arr[$puluhan] . ' Puluh ';
                if ($satuan > 0) $result .= $arr[$satuan];
            }
            return trim($result);
        }
        $tahunTerbilang = terbilangTahun((int)$tahunNum);
        
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Berita Acara Penghapusan Barang</title>
        <style>
            @page { size: A4; margin: 10mm 15mm; }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Times New Roman', serif; font-size: 11pt; color: #000; padding: 10px 20px; line-height: 1.1; }
            .kop-wrapper { text-align: center; margin-bottom: 2px; }
            .kop-img { max-width: 100%; max-height: 120px; }
            .kop-line { margin-bottom: 10px; padding-bottom: 2px; }
            .judul { text-align: center; margin-bottom: 10px; }
            .judul h3 { font-size: 12pt; letter-spacing: 1px; margin-bottom: 1px; }
            .judul .sub { font-size: 11pt; text-decoration: underline; font-weight: bold; margin-bottom: 1px; }
            .judul .nomor { font-size: 10pt; }
            .isi { text-align: justify; margin-bottom: 8px; }
            .identitas { margin: 8px 0; }
            .identitas table td { padding: 1px 0; vertical-align: top; font-size: 11pt; }
            .identitas table td:first-child { width: 100px; }
            .identitas table td:nth-child(2) { width: 12px; text-align: center; }
            table.barang { width: 100%; border-collapse: collapse; margin: 8px 0; }
            table.barang th, table.barang td { border: 1px solid #000; padding: 4px 8px; font-size: 10pt; }
            table.barang th { text-align: center; }
            table.barang td:first-child { text-align: center; width: 40px; }
            table.barang td:last-child { text-align: center; width: 80px; }
            ol { margin-left: 25px; margin-bottom: 10px; }
            ol li { margin-bottom: 2px; }
            .ttd-wrapper { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 80px; }
            .ttd { text-align: center; width: 250px; }
            .ttd .label { font-weight: bold; font-size: 10pt; margin-bottom: 60px; min-height: 35px; display: flex; align-items: center; justify-content: center; flex-direction: column; }
            .ttd .nama { font-weight: bold; text-decoration: underline; font-size: 10pt; }
            @media print { body { padding: 0; } }
        </style></head><body>";
        
        if ($kopSurat) {
            echo "<div class='kop-wrapper'><img class='kop-img' src='../../../" . htmlspecialchars($kopSurat) . "'></div>";
            echo "<div class='kop-line'></div>";
        } else {
            echo "<div class='kop-line' style='text-align:center; padding-bottom:10px'><h2 style='margin:0 0 5px 0; font-size:16pt'>{$school}</h2></div>";
        }
        
        echo "<div class='judul'><h3>BERITA ACARA</h3><div class='sub'>SERAH TERIMA BARANG INVENTARIS</div><div class='nomor'>Nomor : {$nomorBa}</div></div>";
        echo "<div class='isi'><p>Pada hari ini, {$hariNama} tanggal {$tglTerbilang} bulan {$bulanNama} tahun {$tahunTerbilang}, kami yang bertanda tangan di bawah ini:</p></div>";
        echo "<div class='identitas'><table><tr><td>Nama</td><td>:</td><td>{$wakaSarpras}</td></tr><tr><td>Jabatan</td><td>:</td><td>Wakil Kepala Sekolah Sarana dan Prasarana</td></tr><tr><td>Unit</td><td>:</td><td>SMA Wachid Hasyim 1 Surabaya</td></tr></table></div>";
        echo "<div class='isi'><p>berdasarkan hasil pengecekan dinyatakan bahwa alat tersebut sudah tidak bisa diperbaiki dan lebih baik dilakukan penghapusan.</p><p>adapun barang tersebut adalah sebagai berikut:</p></div>";
        
        echo "<table class='barang'><thead><tr><th>No</th><th>Nama Barang</th><th>Jumlah</th></tr></thead><tbody>";
        foreach($items as $idx => $item) {
            echo "<tr><td>" . ($idx + 1) . "</td><td>{$item['sarpras_nama']}</td><td>{$item['jumlah']}</td></tr>";
        }
        echo "</tbody></table>";
        
        echo "<div class='isi'><p>Dengan ditandatanganinya Berita Acara ini, maka barang-barang tersebut di atas:</p><ol><li>Telah dikeluarkan dari daftar inventaris aktif sekolah.</li><li>Akan dipindahtangankan/dimusnahkan/dijual sesuai dengan prosedur yang berlaku.</li></ol><p>Demikian Berita Acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p></div>";
        echo "<div class='ttd-wrapper'><div class='ttd'><div class='label'>MENGETAHUI/MENYETUJUI<br>KEPALA SEKOLAH</div><div class='nama'>{$kepalaSekolah}</div></div><div class='ttd'><div class='label'>WAKA. SARPRAS</div><div class='nama'>{$wakaSarpras}</div></div></div>";
        echo "<script>window.print();</script></body></html>";
        exit;
        
    } else {
        die("Report Type Unknown");
    }
}

// ==================== SETTINGS SARPRAS ====================
function handleSettings($action) {
    $user = sp_auth();
    sp_require_any($user, ['settings_manage'], 'Akses ditolak');
    switch ($action) {
        case 'get':
            $kopSurat = global_kop_surat_path();
            $kepalaSekolah = global_kepala_sekolah('');
            $wakaSarpras = get_setting('sarpras_waka_sarpras', '');
            json_response(200, true, 'OK', [
                'kop_surat' => $kopSurat,
                'kepala_sekolah' => $kepalaSekolah,
                'waka_sarpras' => $wakaSarpras
            ]);
            break;
        case 'save':
            $d = get_input();
            
            if (isset($d['waka_sarpras'])) {
                upsert_setting('sarpras_waka_sarpras', sanitize($d['waka_sarpras']), 'text', 'Nama waka sarpras untuk surat');
            }
            
            json_response(200, true, 'Pengaturan disimpan');
            break;
    }
}

// ==================== LAPORAN ====================
function handleLaporan($action) {
    $user = sp_auth();
    sp_require_any($user, ['report_view'], 'Akses ditolak');
    $type = $_GET['type'] ?? '';

    // Legacy: action=print routes by type param  
    if ($action === 'print' && in_array($type, ['perbaikan-log', 'berita-acara'])) {
        handlePrintLaporan($type);
        return;
    }
    // For action=print with our new types, just use $type below

    // Common school info for header
    $school_name = get_setting('nama_sekolah', 'SMA Wachid Hasyim 1 Surabaya');
    $kopSurat = global_kop_surat_path();
    
    $headerHtml = '';
    if ($kopSurat) {
        // Use a relative path similar to Berita Acara for better compatibility if BASE_URL has issues in some environments
        $headerHtml .= '<div class="kop-surat"><img src="../../../' . htmlspecialchars($kopSurat) . '"></div>';
    }

    $headerHtml .= '
    <div class="school-title">
        <h2>' . htmlspecialchars($school_name) . '</h2>
        <p>E-Sarpras — SISTEM INFORMASI SARANA & PRASARANA</p>
    </div>';

    // Common CSS for all reports
    $css = '
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: "Times New Roman", Times, serif; font-size: 11pt; color: #000; padding: 20px 30px; background: white; line-height: 1.3; }
        
        /* Header Style */
        .kop-surat { width: 100%; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        .kop-surat img { max-width: 100%; height: auto; }
        
        .school-title { text-align: center; margin-bottom: 20px; }
        .school-title h2 { font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 0; }
        .school-title p { font-size: 11pt; font-weight: bold; text-transform: uppercase; margin: 4px 0 0; }

        .identitas-section { margin-bottom: 15px; }
        .identitas-section h3 { font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-bottom: 8px; border-bottom: 1px solid #000; display: inline-block; }
        .identitas-grid { display: grid; grid-template-columns: 140px 10px 1fr; gap: 4px; }
        .identitas-grid div { font-size: 11pt; font-weight: bold; }

        .report-section-title { font-size: 12pt; font-weight: bold; text-transform: uppercase; margin: 15px 0 10px; text-decoration: underline; }

        table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 20px; }
        th { 
            background: #f2f2f2; 
            border: 1px solid #000; 
            padding: 6px 4px; 
            font-size: 10pt; 
            font-weight: bold; 
            text-align: center; 
            text-transform: uppercase;
        }
        td { border: 1px solid #000; padding: 4px 6px; font-size: 10pt; vertical-align: middle; }
        
        .sub-row { background: #f9f9f9; font-weight: bold; }
        .group-row { background: #f2f2f2; font-weight: bold; font-size: 10pt; }
        
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        
        /* Signatures */
        .ttd-wrapper { display: flex; justify-content: space-between; margin-top: 30px; page-break-inside: avoid; }
        .ttd { text-align: center; width: 250px; }
        .ttd .label { margin-bottom: 60px; font-weight: bold; }
        .ttd .nama { font-weight: bold; text-decoration: underline; }
        
        .ttd-center { display: flex; justify-content: center; margin-top: 10px; page-break-inside: avoid; }
        
        .sp-report-page { position: relative; min-height: 285mm; page-break-after: always; padding-bottom: 60px; box-sizing: border-box; }
        
        .footer-note { position: absolute; bottom: 10px; left: 0; right: 0; font-style: italic; font-size: 9pt; color: #666; border-top: 1px solid #f2f2f2; padding-top: 5px; }
        
        @media print { 
            body { padding: 0; } 
            @page { size: 210mm 330mm; margin: 15mm; }
            .btn-print { display:none; }
        }
        .btn-print { position:fixed; top:15px; right:20px; background:#1e293b; color:white; border:none; padding:10px 22px; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; z-index:999; box-shadow:0 4px 12px rgba(0,0,0,0.2); }
    </style>';

    // Header and CSS are now ready in $headerHtml and $css

    switch ($type) {
        // ============================
        // REKAP KONDISI BARANG
        // ============================
        case 'rekap-kondisi':
            // ... (keep existing logic or slightly adjust if needed, but focus on room detail first)
            // For now, I'll update detail-per-ruang strictly as requested.
            // I'll skip re-implementing rekap-kondisi to keep the diff clean if not asked.
            // But since the CSS changed, I should ensure it still looks okay.
            // Actually, I'll just keep the original logic for other reports but wrap them in the new style.
            
            // ... (Existing rekap-kondisi logic)
            $stmt = db()->query("
                SELECT 
                    r.id as ruang_id, r.nama as ruang_nama, r.kode_ruang,
                    b.nama as bangunan_nama, t.nama as tanah_nama,
                    COUNT(s.id) as total_item,
                    COALESCE(SUM(s.jumlah),0) as total_unit,
                    COALESCE(SUM(s.kondisi_baik),0) as total_baik,
                    COALESCE(SUM(s.kondisi_rusak_ringan),0) as total_rr,
                    COALESCE(SUM(s.kondisi_rusak_berat),0) as total_rb
                FROM ruang r
                JOIN bangunan b ON r.bangunan_id = b.id
                JOIN tanah t ON b.tanah_id = t.id
                LEFT JOIN sarpras s ON s.ruang_id = r.id
                WHERE 1=1 " . sp_scope_where($user, 'r', 'id') . "
                GROUP BY r.id
                ORDER BY t.nama, b.nama, r.nama
            ");
            $data = $stmt->fetchAll();
            $grandItem = 0; $grandUnit = 0; $grandBaik = 0; $grandRr = 0; $grandRb = 0;
            $rows = ''; $no = 1;
            foreach ($data as $d) {
                $grandItem += $d['total_item']; $grandUnit += $d['total_unit']; $grandBaik += $d['total_baik'];
                $grandRr += $d['total_rr']; $grandRb += $d['total_rb'];
                $rows .= '<tr><td class="center">'.$no++.'</td><td>'.$d['ruang_nama'].'</td><td class="center">'.$d['kode_ruang'].'</td><td class="right">'.$d['total_item'].'</td><td class="right">'.$d['total_unit'].'</td><td class="right">'.$d['total_baik'].'</td><td class="right">'.$d['total_rr'].'</td><td class="right">'.$d['total_rb'].'</td></tr>';
            }
            $school_kepala = trim(global_kepala_sekolah('')) ?: '.............................';
            $school_waka = trim(get_setting('sarpras_waka_sarpras', '')) ?: '.............................';
            
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Rekap Kondisi</title>'.$css.'</head><body>';
            echo '<button class="btn-print" onclick="window.print()">🖨️ Cetak</button>';
            echo '<div class="sp-report-page">';
            echo $headerHtml;
            echo '<div class="report-section-title" style="text-align:center">LAPORAN REKAP KONDISI BARANG</div>';
            echo '<table><thead><tr><th>NO</th><th>RUANG / LOKASI</th><th>KODE</th><th>JML ITEM</th><th>TOTAL UNIT</th><th>BAIK</th><th>RR</th><th>RB</th></tr></thead><tbody>'.$rows.'</tbody></table>';
            
            echo '<div class="ttd-wrapper">';
            echo '  <div class="ttd"><div class="label">MENGETAHUI,<br>WAKA. SARANA PRASARANA</div><div class="nama">'.$school_waka.'</div></div>';
            echo '  <div class="ttd"><div class="label">PALU, '.date('d F Y').'<br>PETUGAS INVENTARIS</div><div class="nama">.............................</div></div>';
            echo '</div>';
            echo '<div class="ttd-center">';
            echo '  <div class="ttd"><div class="label">MENGETAHUI,<br>KEPALA SEKOLAH</div><div class="nama">'.$school_kepala.'</div></div>';
            echo '</div>';
            
            echo '</div>';
            
            echo '<p class="footer-note">Data telah diperbarui pertanggal ' . date('d F Y') . '</p>';
            echo '</div>'; // End sp-report-page
            echo '</body></html>';
            exit;

        // ============================
        // REKAP NILAI ASET
        // ============================
        case 'rekap-nilai':
            // (Same as above, wrap existing rekap-nilai logic in the new style)
            // For brevity, I'll focus on the requested detail-per-ruang.
            $scope = sp_scope_where($user, 's', 'ruang_id');
            $stmt = db()->query("SELECT k.id, k.nama as kategori, k.kode, COALESCE(SUM(s.jumlah),0) as total_unit, COALESCE(SUM(s.harga_perolehan * s.jumlah),0) as total_perolehan FROM kategori_sarpras k LEFT JOIN sarpras s ON s.kategori_id = k.id AND s.is_hapus=0 $scope GROUP BY k.id ORDER BY k.kode");
            $data = $stmt->fetchAll();
            $rows = ''; $no = 1; $grandUnit = 0; $grandNilai = 0;
            foreach ($data as $d) {
                $grandUnit += $d['total_unit']; $grandNilai += $d['total_perolehan'];
                $rows .= '<tr><td class="center">'.$no++.'</td><td>'.$d['kategori'].'</td><td class="center">'.$d['kode'].'</td><td class="right">'.number_format($d['total_unit']).'</td><td class="right">'.number_format($d['total_perolehan'], 0, ',', '.').'</td></tr>';
            }
            $school_kepala = trim(global_kepala_sekolah('')) ?: '.............................';
            $school_waka = trim(get_setting('sarpras_waka_sarpras', '')) ?: '.............................';

            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Rekap Nilai Aset</title>'.$css.'</head><body>';
            echo '<button class="btn-print" onclick="window.print()">🖨️ Cetak</button>';
            echo '<div class="sp-report-page">';
            echo $headerHtml;
            echo '<div class="report-section-title" style="text-align:center">LAPORAN REKAP NILAI ASET</div>';
            echo '<table><thead><tr><th>NO</th><th>KATEGORI</th><th>KODE</th><th>TOTAL UNIT</th><th>NILAI PEROLEHAN (RP)</th></tr></thead><tbody>'.$rows.'</tbody><tfoot><tr class="group-row"><td colspan="3" class="center">TOTAL</td><td class="right">'.number_format($grandUnit).'</td><td class="right">'.number_format($grandNilai, 0, ',', '.').'</td></tr></tfoot></table>';
            
            echo '<div class="ttd-wrapper">';
            echo '  <div class="ttd"><div class="label">MENGETAHUI,<br>WAKA. SARANA PRASARANA</div><div class="nama">'.$school_waka.'</div></div>';
            echo '  <div class="ttd"><div class="label">PALU, '.date('d F Y').'<br>PETUGAS INVENTARIS</div><div class="nama">.............................</div></div>';
            echo '</div>';
            echo '<div class="ttd-center">';
            echo '  <div class="ttd"><div class="label">MENGETAHUI,<br>KEPALA SEKOLAH</div><div class="nama">'.$school_kepala.'</div></div>';
            echo '</div>';

            echo '<p class="footer-note">Data telah diperbarui pertanggal ' . date('d F Y') . '</p>';
            echo '</div>'; // End sp-report-page
            echo '</body></html>';
            exit;

        // ============================
        // DETAIL PER RUANG (REFINED)
        // ============================
        case 'detail-per-ruang':
            $ruang_id = (int)($_GET['ruang_id'] ?? 0);
            $roomsQuery = "
                SELECT r.*, pj.nama as pj_nama, b.nama as bangunan_nama, t.nama as tanah_nama
                FROM ruang r 
                LEFT JOIN sarpras_pj pj ON r.pj_id = pj.id
                JOIN bangunan b ON r.bangunan_id = b.id 
                JOIN tanah t ON b.tanah_id = t.id
            ";
            $where = " WHERE 1=1";
            $params = [];
            if ($ruang_id > 0) {
                $where .= " AND r.id = ?";
                $params[] = $ruang_id;
            }
            $where .= sp_scope_where($user, 'r', 'id');
            $roomsQuery .= $where . " ORDER BY t.nama, b.nama, r.nama";
            
            $stmt = db()->prepare($roomsQuery);
            $stmt->execute($params);
            $rooms = $stmt->fetchAll();

            $school_kepala = trim(global_kepala_sekolah('')) ?: '.............................';
            $school_waka = trim(get_setting('sarpras_waka_sarpras', '')) ?: '.............................';

            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Daftar Inventaris Ruang</title>' . $css . '</head><body>';
            echo '<button class="btn-print" onclick="window.print()">🖨️ Cetak</button>';

            foreach ($rooms as $room) {
                // Fetch items for this room grouped by category
                $stmt = db()->prepare("
                    SELECT s.*, k.nama as kategori_nama, k.kode as kategori_kode
                    FROM sarpras s 
                    JOIN kategori_sarpras k ON s.kategori_id = k.id
                    WHERE s.ruang_id = ? 
                    ORDER BY k.kode ASC, s.nama ASC
                ");
                $stmt->execute([$room['id']]);
                $items = $stmt->fetchAll();

                if (empty($items)) continue;

                echo '<div class="sp-report-page">'; // Start Room Page
                echo $headerHtml;

                // Identitas Seksi
                echo '<div class="identitas-section">';
                echo '<h3>IDENTITAS RUANG / KELAS</h3>';
                echo '<div class="identitas-grid">';
                echo '<div>Kode Ruang</div><div>:</div><div>' . $room['kode_ruang'] . '</div>';
                echo '<div>Nama Ruang</div><div>:</div><div>' . $room['nama'] . '</div>';
                echo '<div>Penanggung Jawab</div><div>:</div><div>' . ($room['pj_nama'] ?: '-') . '</div>';
                echo '<div>Lokasi / Bangunan</div><div>:</div><div>' . $room['bangunan_nama'] . ' (' . $room['tanah_nama'] . ')</div>';
                echo '</div>';
                echo '</div>';

                echo '<div class="report-section-title">DAFTAR INVENTARIS RUANG</div>';

                echo '<table>';
                echo '<thead>';
                echo '<tr>';
                echo '<th rowspan="2" style="width:30px">NO</th>';
                echo '<th rowspan="2">NAMA BARANG / MERK SPESIFIKASI</th>';
                echo '<th rowspan="2" style="width:100px">KODE INVENTARIS</th>';
                echo '<th rowspan="2" style="width:40px">JML</th>';
                echo '<th colspan="2">KONDISI</th>';
                echo '<th rowspan="2" style="width:80px">KET</th>';
                echo '</tr>';
                echo '<tr><th style="width:40px">B</th><th style="width:40px">R</th></tr>';
                echo '</thead>';
                echo '<tbody>';

                // Separate items into Inventaris and Non-Inventaris
                $nonInventaris = [];
                $inventaris = [];
                foreach ($items as $item) {
                    if ($item['kategori_kode'] === 'D') {
                        $nonInventaris[$item['kategori_nama']][] = $item;
                    } else {
                        $inventaris[$item['kategori_nama']][] = $item;
                    }
                }

                $globalNo = 1;

                // II. BARANG INVENTARIS
                if (!empty($inventaris)) {
                    echo '<tr class="group-row"><td class="center">I</td><td colspan="6">BARANG INVENTARIS</td></tr>';
                    foreach ($inventaris as $catName => $catItems) {
                        echo '<tr class="sub-row"><td></td><td colspan="6">' . $catName . '</td></tr>';
                        foreach ($catItems as $s) {
                            $rsk = $s['kondisi_rusak_ringan'] + $s['kondisi_rusak_berat'];
                            echo '<tr>';
                            echo '<td class="center">' . $globalNo++ . '</td>';
                            echo '<td>' . $s['nama'] . ($s['merk'] ? ' - ' . $s['merk'] : '') . '</td>';
                            echo '<td class="center">' . $s['kode_inventaris'] . '</td>';
                            echo '<td class="center">' . $s['jumlah'] . '</td>';
                            echo '<td class="center">' . $s['kondisi_baik'] . '</td>';
                            echo '<td class="center">' . $rsk . '</td>';
                            echo '<td>' . report_clean_keterangan($s['keterangan'] ?? '') . '</td>';
                            echo '</tr>';
                        }
                    }
                }

                // I. BARANG NON-INVENTARIS
                if (!empty($nonInventaris)) {
                    echo '<tr class="group-row"><td class="center">II</td><td colspan="6">BARANG NON-INVENTARIS</td></tr>';
                    foreach ($nonInventaris as $catName => $catItems) {
                        echo '<tr class="sub-row"><td></td><td colspan="6">' . $catName . '</td></tr>';
                        foreach ($catItems as $s) {
                            $rsk = $s['kondisi_rusak_ringan'] + $s['kondisi_rusak_berat'];
                            echo '<tr>';
                            echo '<td class="center">' . $globalNo++ . '</td>';
                            echo '<td>' . $s['nama'] . ($s['merk'] ? ' - ' . $s['merk'] : '') . '</td>';
                            echo '<td class="center">' . $s['kode_inventaris'] . '</td>';
                            echo '<td class="center">' . $s['jumlah'] . '</td>';
                            echo '<td class="center">' . $s['kondisi_baik'] . '</td>';
                            echo '<td class="center">' . $rsk . '</td>';
                            echo '<td>' . report_clean_keterangan($s['keterangan'] ?? '') . '</td>';
                            echo '</tr>';
                        }
                    }
                }

                echo '</tbody>';
                echo '</table>';
                
                echo '<div class="ttd-wrapper">';
                echo '  <div class="ttd"><div class="label">MENGETAHUI,<br>WAKA. SARANA PRASARANA</div><div class="nama">'.$school_waka.'</div></div>';
                echo '  <div class="ttd"><div class="label">PALU, '.date('d F Y').'<br>PENANGGUNG JAWAB RUANG</div><div class="nama">'.($room['pj_nama'] ?: '.............................').'</div></div>';
                echo '</div>';
                echo '<div class="ttd-center">';
                echo '  <div class="ttd"><div class="label">MENGETAHUI,<br>KEPALA SEKOLAH</div><div class="nama">'.$school_kepala.'</div></div>';
                echo '</div>';
                
                echo '<p class="footer-note">Data telah diperbarui pertanggal ' . date('d F Y') . '</p>';
                echo '</div>'; // End sp-report-page
            }

            echo '</body></html>';
            exit;

        default:
            json_response(400, false, 'Tipe laporan tidak valid');
    }
}

// ==================== PENGHAPUSAN (BATCH BA) ====================
function handlePenghapusan($action) {
    $user = sp_auth();
    sp_require_any($user, ['penghapusan_manage', 'perbaikan_manage'], 'Akses ditolak');

    switch ($action) {
        case 'list':
            // Get all items with status Penghapusan, joined with BA info if available
            $where = "WHERE p.status = 'Penghapusan'" . sp_scope_where($user, 's', 'ruang_id');
            $stmt = db()->query("
                SELECT p.*, s.nama as sarpras_nama, s.kode_inventaris, s.jumlah,
                       ba.nomor_ba, ba.tanggal_ba, ba.id as ba_id
                FROM sarpras_perbaikan p 
                JOIN sarpras s ON p.sarpras_id = s.id 
                LEFT JOIN sarpras_berita_acara ba ON p.berita_acara_id = ba.id
                $where
                ORDER BY ba.id DESC, p.tanggal DESC
            ");
            $data = $stmt->fetchAll();
            
            // Count pending (items without BA)
            $pending = 0;
            foreach($data as $r) if(!$r['ba_id']) $pending++;
            
            json_response(200, true, 'OK', ['items' => $data, 'count_pending' => $pending]);
            break;

        case 'generate-ba':
            $d = get_input();
            $nomorBa = sanitize($d['nomor_ba'] ?? '');
            $tanggalBa = sanitize($d['tanggal_ba'] ?? date('Y-m-d'));
            
            if (!$nomorBa) json_response(400, false, 'Nomor Berita Acara harus diisi');

            // Find all items that don't have BA yet
            $stmt = db()->query("SELECT id FROM sarpras_perbaikan WHERE status='Penghapusan' AND berita_acara_id IS NULL");
            $items = $stmt->fetchAll();
            
            if (empty($items)) json_response(400, false, 'Tidak ada data penghapusan baru untuk dibuatkan Berita Acara');

            try {
                $db = db();
                $db->beginTransaction();

                // 1. Create BA record
                $ins = $db->prepare("INSERT INTO sarpras_berita_acara (nomor_ba, tanggal_ba, tahun_ba, updated_by) VALUES (?,?,?,?)");
                $ins->execute([$nomorBa, $tanggalBa, (int)date('Y', strtotime($tanggalBa)), $user['user_id']]);
                $ba_id = $db->lastInsertId();

                // 2. Link items
                $upd = $db->prepare("UPDATE sarpras_perbaikan SET berita_acara_id = ? WHERE id = ?");
                foreach($items as $item) {
                    $upd->execute([$ba_id, $item['id']]);
                }

                $db->commit();
                json_response(200, true, 'Berita Acara berhasil dibuat untuk ' . count($items) . ' barang.');
            } catch (Exception $e) {
                $db->rollBack();
                json_response(500, false, 'Gagal membuat BA: ' . $e->getMessage());
            }
            break;
            
        case 'delete-ba':
            // Logic to unlink items and delete BA record if needed
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) json_response(400, false, 'ID BA tidak valid');
            
            db()->prepare("UPDATE sarpras_perbaikan SET berita_acara_id = NULL WHERE berita_acara_id = ?")->execute([$id]);
            db()->prepare("DELETE FROM sarpras_berita_acara WHERE id = ?")->execute([$id]);
            json_response(200, true, 'Berita Acara dibatalkan, items kembali ke status pending');
            break;

        case 'delete-history':
            $id = (int)($_GET['id'] ?? 0);
            $baId = (int)($_GET['ba_id'] ?? 0);
            
            try {
                $db = db();
                $db->beginTransaction();
                
                if ($baId > 0) {
                    // Restore all items in this BA
                    $stmt = $db->prepare("SELECT sarpras_id FROM sarpras_perbaikan WHERE berita_acara_id = ?");
                    $stmt->execute([$baId]);
                    $items = $stmt->fetchAll();
                    foreach ($items as $item) {
                        $db->prepare("UPDATE sarpras SET is_hapus = 0, tanggal_hapus = NULL WHERE id = ?")->execute([$item['sarpras_id']]);
                    }
                    
                    // Delete records
                    $db->prepare("DELETE FROM sarpras_perbaikan_log WHERE perbaikan_id IN (SELECT id FROM sarpras_perbaikan WHERE berita_acara_id = ?)")->execute([$baId]);
                    $db->prepare("DELETE FROM sarpras_perbaikan WHERE berita_acara_id = ?")->execute([$baId]);
                    $db->prepare("DELETE FROM sarpras_berita_acara WHERE id = ?")->execute([$baId]);
                    
                    $msg = "Berita Acara dan semua riwayat terkait berhasil dihapus permanen.";
                } elseif ($id > 0) {
                    // Restore single item
                    $stmt = $db->prepare("SELECT sarpras_id FROM sarpras_perbaikan WHERE id = ?");
                    $stmt->execute([$id]);
                    $sid = $stmt->fetchColumn();
                    if ($sid) {
                        $db->prepare("UPDATE sarpras SET is_hapus = 0, tanggal_hapus = NULL WHERE id = ?")->execute([$sid]);
                    }
                    
                    // Delete record
                    $db->prepare("DELETE FROM sarpras_perbaikan WHERE id = ?")->execute([$id]);
                    $db->prepare("DELETE FROM sarpras_perbaikan_log WHERE perbaikan_id = ?")->execute([$id]);
                    
                    $msg = "Riwayat penghapusan item berhasil dihapus permanen.";
                } else {
                    json_response(400, false, 'ID tidak valid');
                }
                
                $db->commit();
                json_response(200, true, $msg);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                json_response(500, false, 'Error: ' . $e->getMessage());
            }
            break;
    }
}

// ==================== PEMINJAMAN ====================
function handlePeminjaman($action) {
    $user = sp_auth();
    sp_require_any($user, ['peminjaman_manage'], 'Akses ditolak');

    switch ($action) {
        case 'list':
            $status = sanitize($_GET['status'] ?? '');
            $search = sanitize($_GET['search'] ?? '');
            
            $where = "WHERE 1=1" . sp_scope_where($user, 's', 'ruang_id');
            $params = [];
            if ($status) {
                $where .= " AND p.status = ?";
                $params[] = $status;
            }
            if ($search) {
                $where .= " AND (p.nama_peminjam LIKE ? OR s.nama LIKE ? OR s.kode_inventaris LIKE ? OR p.nama_kegiatan LIKE ?)";
                $sParam = "%$search%";
                $params[] = $sParam;
                $params[] = $sParam;
                $params[] = $sParam;
                $params[] = $sParam;
            }
            
            $stmt = db()->prepare("
                SELECT p.*, s.nama as sarpras_nama, s.kode_inventaris, r.nama as ruang_nama
                FROM sarpras_peminjaman p
                JOIN sarpras s ON p.sarpras_id = s.id
                LEFT JOIN ruang r ON s.ruang_id = r.id
                $where
                ORDER BY p.status ASC, p.tanggal_pinjam DESC, p.created_at DESC
            ");
            $stmt->execute($params);
            
            $data = $stmt->fetchAll();
            json_response(200, true, 'OK', $data);
            break;
            
        case 'create':
            $d = get_input();
            
            // Validate
            $sarprasId = (int)($d['sarpras_id'] ?? 0);
            $namaPeminjam = sanitize($d['nama_peminjam'] ?? '');
            if (!$sarprasId || !$namaPeminjam) json_response(400, false, 'Barang dan Nama Peminjam wajib diisi');
            
            try {
                $q = "INSERT INTO sarpras_peminjaman 
                      (user_id, nama_peminjam, jabatan, no_hp, asal_unit, nama_kegiatan, sarpras_id, jumlah, kondisi_sebelum, tanggal_pinjam, tanggal_kembali_rencana, created_by, updated_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                db()->prepare($q)->execute([
                    !empty($d['user_id']) ? (int)$d['user_id'] : null,
                    $namaPeminjam,
                    sanitize($d['jabatan'] ?? ''),
                    sanitize($d['no_hp'] ?? ''),
                    sanitize($d['asal_unit'] ?? ''),
                    sanitize($d['nama_kegiatan'] ?? ''),
                    $sarprasId,
                    (int)($d['jumlah'] ?? 1),
                    sanitize($d['kondisi_sebelum'] ?? ''),
                    sanitize($d['tanggal_pinjam'] ?? date('Y-m-d')),
                    !empty($d['tanggal_kembali_rencana']) ? sanitize($d['tanggal_kembali_rencana']) : null,
                    $user['user_id'],
                    $user['user_id']
                ]);
                $lastId = db()->lastInsertId();
                json_response(201, true, 'Data peminjaman berhasil dicatat', ['id' => $lastId]);
            } catch (PDOException $e) {
                json_response(500, false, 'Error: ' . $e->getMessage());
            }
            break;
            
        case 'update':
            // Can be used to return item
            $d = get_input();
            
            $id = (int)($d['id'] ?? 0);
            if (!$id) json_response(400, false, 'ID tidak valid');
            
            try {
                $q = "UPDATE sarpras_peminjaman 
                      SET tanggal_kembali_aktual = ?, kondisi_sesudah = ?, catatan = ?, status = 'Dikembalikan', updated_by = ? 
                      WHERE id = ?";
                db()->prepare($q)->execute([
                    sanitize($d['tanggal_kembali_aktual'] ?? date('Y-m-d')),
                    sanitize($d['kondisi_sesudah'] ?? ''),
                    sanitize($d['catatan'] ?? ''),
                    $user['user_id'],
                    $id
                ]);
                json_response(200, true, 'Barang berhasil dikembalikan');
            } catch (PDOException $e) {
                json_response(500, false, 'Error: ' . $e->getMessage());
            }
            break;
            
        case 'delete':
            $d = get_input();
            try {
                db()->prepare("DELETE FROM sarpras_peminjaman WHERE id=?")->execute([(int)($d['id'] ?? 0)]);
                json_response(200, true, 'Data peminjaman dihapus');
            } catch (PDOException $e) {
                json_response(500, false, 'Error: ' . $e->getMessage());
            }
            break;
            
        case 'print':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) die("ID Invalid");
            
            // Get Peminjaman data
            $stmt = db()->prepare("
                SELECT p.*, s.nama as sarpras_nama, s.kode_inventaris, r.nama as ruang_nama
                FROM sarpras_peminjaman p
                JOIN sarpras s ON p.sarpras_id = s.id
                LEFT JOIN ruang r ON s.ruang_id = r.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $pData = $stmt->fetch();
            if (!$pData) die("Data peminjaman tidak ditemukan");
            
            // Get settings for Kop and Signature
            $kop = global_kop_surat_path();
            $namaWaka = get_setting('sarpras_waka_sarpras', '..........................');
            
            // HTML untuk Print
            $jenisSurat = ($pData['status'] == 'Dikembalikan') ? "SURAT BUKTI PENGEMBALIAN BARANG" : "SURAT BUKTI PEMINJAMAN BARANG";
            
            $html = "<!DOCTYPE html><html><head><title>Cetak Peminjaman</title>";
            $html .= "<style>
                body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; max-width: 800px; margin: 0 auto; line-height: 1.4; color: #000; }
                .sp-print-kop { text-align: center; margin-bottom: 20px; padding-bottom: 10px; }
                .sp-print-kop img { max-width: 100%; max-height: 120px; }
                .sp-print-header { text-align: center; margin-bottom: 20px; }
                .sp-print-header h2 { margin: 0; font-size: 14pt; font-weight: bold; text-transform: uppercase; text-decoration: underline; }
                .sp-print-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                .sp-print-table td { padding: 4px; vertical-align: top; }
                .sp-print-table td:first-child { width: 30%; }
                .sp-print-table td:nth-child(2) { width: 5%; text-align: center; }
                .sp-signature-container { margin-top: 40px; display: table; width: 100%; }
                .sp-signature-box { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; }
                .sp-signature-space { height: 70px; }
                @media print { 
                    body { padding: 0; max-width: 100%; background: none; margin: 0.5cm; } 
                    @page { size: A4 portrait; margin: 1.5cm; } 
                    .no-print { display: none; }
                }
            </style>";
            $html .= "</head><body onload='window.print()'>";
            
            // Render KOP if exists
            if ($kop) {
                $kopPath = '../../../' . $kop;
                if (file_exists(__DIR__ . '/../../../' . $kop)) {
                    $html .= "<div class='sp-print-kop'><img src='{$kopPath}'></div>";
                } else {
                    $html .= "<div class='sp-print-kop' style='border-bottom:2px solid #000; padding: 10px 0;'>Header Sekolah</div>";
                }
            } else {
                $html .= "<div class='sp-print-kop' style='border-bottom:2px solid #000; padding: 10px 0;'>Header Sekolah</div>";
            }
            
            $html .= "<div class='sp-print-header'><h2>{$jenisSurat}</h2></div>";
            
            $html .= "<p>Yang bertanda tangan di bawah ini:</p>";
            $html .= "<table class='sp-print-table'>";
            $html .= "<tr><td>Nama</td><td>:</td><td>{$pData['nama_peminjam']}</td></tr>";
            $html .= "<tr><td>Jabatan</td><td>:</td><td>" . ($pData['jabatan'] ?: '-') . "</td></tr>";
            $html .= "<tr><td>No. HP</td><td>:</td><td>" . ($pData['no_hp'] ?: '-') . "</td></tr>";
            $html .= "<tr><td>Asal Unit</td><td>:</td><td>" . ($pData['asal_unit'] ?: '-') . "</td></tr>";
            $html .= "</table>";
            
            $txt1 = ($pData['status'] == 'Dikembalikan') ? "Telah mengembalikan" : "Dengan ini meminjam";
            
            $html .= "<p>{$txt1} Barang/Aset Sekolah dengan rincian sebagai berikut:</p>";
            $html .= "<table class='sp-print-table'>";
            $html .= "<tr><td>Nama Barang</td><td>:</td><td><b>{$pData['sarpras_nama']}</b></td></tr>";
            $html .= "<tr><td>Kode Inventaris</td><td>:</td><td>{$pData['kode_inventaris']}</td></tr>";
            $html .= "<tr><td>Lokasi / Ruang</td><td>:</td><td>" . ($pData['ruang_nama'] ?: '-') . "</td></tr>";
            $html .= "<tr><td>Jumlah</td><td>:</td><td>{$pData['jumlah']} Unit</td></tr>";
            $html .= "<tr><td>Keperluan Kegiatan</td><td>:</td><td>" . ($pData['nama_kegiatan'] ?: '-') . "</td></tr>";
            if ($pData['status'] == 'Dikembalikan') {
                $html .= "<tr><td>Tanggal Pinjam</td><td>:</td><td>" . date('d/m/Y', strtotime($pData['tanggal_pinjam'])) . "</td></tr>";
                $html .= "<tr><td>Tanggal Kembali Aktual</td><td>:</td><td>" . date('d/m/Y', strtotime($pData['tanggal_kembali_aktual'])) . "</td></tr>";
                $html .= "<tr><td>Kondisi Dikembalikan</td><td>:</td><td>" . nl2br($pData['kondisi_sesudah'] ?: 'Baik') . "</td></tr>";
            } else {
                $html .= "<tr><td>Tanggal Pinjam</td><td>:</td><td>" . date('d/m/Y', strtotime($pData['tanggal_pinjam'])) . "</td></tr>";
                if ($pData['tanggal_kembali_rencana']) $html .= "<tr><td>Rencana Kembali</td><td>:</td><td>" . date('d/m/Y', strtotime($pData['tanggal_kembali_rencana'])) . "</td></tr>";
                $html .= "<tr><td>Kondisi Dipinjam</td><td>:</td><td>" . nl2br($pData['kondisi_sebelum'] ?: 'Baik') . "</td></tr>";
            }
            $html .= "</table>";
            
            $txt2 = ($pData['status'] == 'Dikembalikan') ? "Demikian bukti pengembalian ini dibuat untuk digunakan sebagaimana mestinya." : "Demikian berita acara peminjaman ini dibuat. Peminjam bertanggung jawab atas kondisi barang tersebut selama masa peminjaman.";
            $html .= "<p>{$txt2}</p>";
            
            $todayDate = date('j F Y');
            $mon = array('January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember');
            $todayDate = strtr($todayDate, $mon);
            
            $html .= "<div class='sp-signature-container'>";
            $html .= "<div class='sp-signature-box'>";
            $html .= "Mengetahui,<br>Wakil Kepala Sarpras<br><div class='sp-signature-space'></div><b>" . htmlspecialchars($namaWaka) . "</b>";
            $html .= "</div>";
            $html .= "<div class='sp-signature-box'>";
            $html .= "Palu, {$todayDate}<br>Yang ".($pData['status'] == 'Dikembalikan' ? 'mengembalikan' : 'meminjam')."<br><div class='sp-signature-space'></div><b>{$pData['nama_peminjam']}</b>";
            $html .= "</div>";
            $html .= "</div>";
            
            $html .= "</body></html>";
            
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
    }
}
