<?php
/**
 * E-Sarpras Inventaris Item API
 */
require_once __DIR__ . '/../../../api/config.php';

require_once __DIR__ . '/auth_helper.php';

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'list': listSarpras(); break;
    case 'get': getSarpras(); break;
    case 'create': createSarpras(); break;
    case 'update': updateSarpras(); break;
    case 'delete': deleteSarpras(); break;
    case 'upload-foto': uploadFoto(); break;
    case 'delete-foto': deleteFoto(); break;
    case 'barcode-data': barcodeData(); break;
    case 'search': searchSarpras(); break;
    case 'copy_to_ruang': copyToRuang(); break;
    case 'import_koleksi_buku': importKoleksiBuku(); break;
    case 'import_buku': importBuku(); break;
    case 'template_buku': templateBuku(); break;
    default: json_response(400, false, 'Invalid action');
}

function templateBuku() {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Template_Import_Buku.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Judul Buku', 'Pengarang', 'Penerbit', 'Tahun Perolehan', 'Klasifikasi Buku', 'Jumlah', 'Sumber Dana', 'Harga']);
    fputcsv($output, ['Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', '2019', 'Fiksi', '10', 'BOS', '75000']);
    fclose($output);
    exit;
}

function syncWarehouseRecords($grup) {
    if (!$grup) return;
    db()->exec("
        INSERT INTO sarpras (
            ruang_id, kategori_id, nama, kode_inventaris, merk, spesifikasi, jumlah,
            kondisi_baik, kondisi_rusak_ringan, kondisi_rusak_berat, tanggal_perolehan,
            harga_perolehan, asal_perolehan, masa_manfaat_tahun, keterangan, judul_buku,
            pengarang, penerbit, grup_pintasan, no_polisi, no_bpkb, alamat, kepemilikan, jenis_sarana
        )
        SELECT
            NULL, src.kategori_id, src.nama, src.kode_inventaris, src.merk, src.spesifikasi, 0,
            0, 0, 0, src.tanggal_perolehan, src.harga_perolehan, src.asal_perolehan,
            src.masa_manfaat_tahun, src.keterangan, src.judul_buku, src.pengarang,
            src.penerbit, src.grup_pintasan, src.no_polisi, src.no_bpkb, src.alamat,
            src.kepemilikan, src.jenis_sarana
        FROM sarpras src
        INNER JOIN (
            SELECT MIN(id) AS id, kode_inventaris
            FROM sarpras
            WHERE is_hapus = 0 AND grup_pintasan = '$grup' AND ruang_id IS NOT NULL
            GROUP BY kode_inventaris
        ) sample ON sample.id = src.id
        LEFT JOIN sarpras warehouse
            ON warehouse.kode_inventaris = src.kode_inventaris
            AND warehouse.grup_pintasan = '$grup'
            AND warehouse.is_hapus = 0
            AND warehouse.ruang_id IS NULL
        WHERE warehouse.id IS NULL
    ");
}

function listSarpras() {
    $user = sp_auth();
    $ruangId = (int)($_GET['ruang_id'] ?? 0);
    $kategoriId = (int)($_GET['kategori_id'] ?? 0);
    $search = $_GET['search'] ?? '';
    $kondisi = $_GET['kondisi'] ?? '';
    $page = max(1,(int)($_GET['page'] ?? 1));
    $perPage = min(50,max(1,(int)($_GET['per_page'] ?? 20)));
    $grup = $_GET['grup'] ?? '';
    $excludeGrup = $_GET['exclude_grup'] ?? '';

    if ($grup && $ruangId === 0) {
        syncWarehouseRecords($grup);
    }
    
    $where = "WHERE s.is_hapus = 0";
    
    // Enforcement for PJ
    $allowNull = (isset($_GET['ruang_id']) && (int)$_GET['ruang_id'] === 0);
    $where .= sp_scope_where($user, 's', 'ruang_id', $allowNull);

    $params = [];
    if ($ruangId > 0) { 
        $where .= " AND s.ruang_id=?"; 
        $params[] = $ruangId; 
    } elseif (isset($_GET['ruang_id']) && (int)$_GET['ruang_id'] === 0) {
        $where .= " AND s.ruang_id IS NULL";
    }

    if ($grup) { $where .= " AND s.grup_pintasan=?"; $params[] = $grup; }
    if ($excludeGrup) { $where .= " AND (s.grup_pintasan IS NULL OR s.grup_pintasan != ?)"; $params[] = $excludeGrup; }
    
    $katIds = $_GET['kategori_ids'] ?? '';
    if ($kategoriId > 0) { $where .= " AND s.kategori_id=?"; $params[] = $kategoriId; }
    elseif ($katIds) {
        $ids = array_filter(array_map('intval', explode(',', $katIds)));
        if ($ids) {
            $in = str_repeat('?,', count($ids) - 1) . '?';
            $where .= " AND s.kategori_id IN ($in)";
            $params = array_merge($params, $ids);
        }
    }
    if ($search) { $where .= " AND (s.nama LIKE ? OR s.kode_inventaris LIKE ? OR s.merk LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($kondisi === 'baik') { $where .= " AND s.kondisi_baik > 0"; }
    elseif ($kondisi === 'rusak_ringan') { $where .= " AND s.kondisi_rusak_ringan > 0"; }
    elseif ($kondisi === 'rusak_berat') { $where .= " AND s.kondisi_rusak_berat > 0"; }
    
    $countWhere = $where;
    if ($grup && $ruangId == 0) {
        $countWhere .= " AND s.ruang_id IS NULL";
    }

    $countStmt = db()->prepare("SELECT COUNT(*) as total FROM sarpras s $countWhere");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    $offset = ($page - 1) * $perPage;
    
    // Custom query for warehouse view to include Terpakai/Sisa calculations
    if ($grup && $ruangId == 0) {
        $stmt = db()->prepare("SELECT s.*, k.nama as kategori_nama, k.kode as kategori_kode, 
            (SELECT SUM(jumlah) FROM sarpras WHERE kode_inventaris = s.kode_inventaris AND is_hapus=0) as total_batch,
            (SELECT SUM(jumlah) FROM sarpras WHERE kode_inventaris = s.kode_inventaris AND ruang_id IS NOT NULL AND is_hapus=0) as terpakai_batch,
            (SELECT COUNT(*) FROM sarpras_foto WHERE sarpras_id=s.id) as jumlah_foto,
            (SELECT foto_path FROM sarpras_foto WHERE sarpras_id=s.id ORDER BY urutan LIMIT 1) as foto_utama
            FROM sarpras s 
            JOIN kategori_sarpras k ON s.kategori_id=k.id 
            $where AND s.ruang_id IS NULL
            ORDER BY k.kode ASC, s.nama ASC LIMIT $perPage OFFSET $offset");
    } else {
        $stmt = db()->prepare("SELECT s.*, k.nama as kategori_nama, k.kode as kategori_kode, k.nama as jenis_kategori, r.nama as ruang_nama, r.kode_ruang, b.nama as bangunan_nama, t.nama as tanah_nama,
            (SELECT COUNT(*) FROM sarpras_foto WHERE sarpras_id=s.id) as jumlah_foto,
            (SELECT foto_path FROM sarpras_foto WHERE sarpras_id=s.id ORDER BY urutan LIMIT 1) as foto_utama
            FROM sarpras s 
            JOIN kategori_sarpras k ON s.kategori_id=k.id 
            LEFT JOIN ruang r ON s.ruang_id=r.id 
            LEFT JOIN bangunan b ON r.bangunan_id=b.id 
            LEFT JOIN tanah t ON b.tanah_id=t.id 
            $where ORDER BY k.kode ASC, s.nama ASC LIMIT $perPage OFFSET $offset");
    }
    $stmt->execute($params);
    
    json_response(200, true, 'OK', [
        'data' => $stmt->fetchAll(),
        'total' => (int)$total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage)
    ]);
}

function searchSarpras() {
    $user = sp_auth();
    $q = $_GET['q'] ?? '';
    if (strlen($q) < 2) json_response(200, true, 'OK', []);
    
    $where = "WHERE (s.nama LIKE ? OR s.kode_inventaris LIKE ?) AND s.is_hapus=0" . sp_scope_where($user, 's', 'ruang_id');
    $stmt = db()->prepare("SELECT s.id, s.nama, s.kode_inventaris, s.jumlah, r.nama as ruang_nama FROM sarpras s LEFT JOIN ruang r ON s.ruang_id=r.id $where LIMIT 20");
    $stmt->execute(["%$q%", "%$q%"]);
    json_response(200, true, 'OK', $stmt->fetchAll());
}

function getSarpras() {
    $user = sp_auth();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    
    $stmt = db()->prepare("SELECT s.*, k.nama as kategori_nama, k.kode as kategori_kode, r.nama as ruang_nama, r.kode_ruang, b.nama as bangunan_nama, b.id as bangunan_id, t.nama as tanah_nama, t.id as tanah_id
        FROM sarpras s JOIN kategori_sarpras k ON s.kategori_id=k.id LEFT JOIN ruang r ON s.ruang_id=r.id LEFT JOIN bangunan b ON r.bangunan_id=b.id LEFT JOIN tanah t ON b.tanah_id=t.id WHERE s.id=?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if (!$data) json_response(404, false, 'Tidak ditemukan');

    // Scoping check for PJ
    if (!empty($user['scoped_ruang_ids']) && !in_array($data['ruang_id'], $user['scoped_ruang_ids'])) {
        json_response(403, false, 'Anda tidak memiliki akses ke aset di ruangan ini');
    }
    
    // Photos
    $f = db()->prepare("SELECT * FROM sarpras_foto WHERE sarpras_id=? ORDER BY urutan"); $f->execute([$id]);
    $data['fotos'] = $f->fetchAll();
    
    // Periodik history
    $p = db()->prepare("SELECT p.*, u.nama_lengkap as updated_by_name FROM sarpras_periodik p JOIN users u ON p.updated_by=u.id WHERE p.sarpras_id=? ORDER BY p.tahun DESC, FIELD(p.periode,'Q4','Q3','Q2','Q1')");
    $p->execute([$id]);
    $data['periodik'] = $p->fetchAll();
    
    // Perbaikan history
    $pb = db()->prepare("SELECT * FROM sarpras_perbaikan WHERE sarpras_id=? ORDER BY tanggal DESC LIMIT 10");
    $pb->execute([$id]);
    $data['perbaikan'] = $pb->fetchAll();
    
    json_response(200, true, 'OK', $data);
}

function getNextKodeInventaris($kategori_id, $tahun) {
    $stmt = db()->prepare("SELECT kode FROM kategori_sarpras WHERE id=?");
    $stmt->execute([$kategori_id]);
    $k = $stmt->fetch();
    $kat_kode = $k ? $k['kode'] : 'INV';
    
    $transactionStarted = false;
    if (!db()->inTransaction()) {
        db()->beginTransaction();
        $transactionStarted = true;
    }
    
    $s = db()->prepare("SELECT last_seq FROM sequence_tracker WHERE kategori_kode=? AND tahun=? FOR UPDATE");
    $s->execute([$kat_kode, $tahun]);
    $seq = $s->fetch();
    if ($seq) {
        $next = $seq['last_seq'] + 1;
        db()->prepare("UPDATE sequence_tracker SET last_seq=? WHERE kategori_kode=? AND tahun=?")->execute([$next, $kat_kode, $tahun]);
    } else {
        $next = 1;
        db()->prepare("INSERT INTO sequence_tracker (kategori_kode, tahun, last_seq) VALUES (?,?,?)")->execute([$kat_kode, $tahun, $next]);
    }
    
    if ($transactionStarted) db()->commit();
    return $kat_kode . '-' . $tahun . '-' . sprintf('%03d', $next);
}

function processFotoUpload($sarpras_id) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $r = handle_upload($_FILES['foto'], 'sarpras/items/');
        if ($r['success']) {
            if (filesize($r['full_path']) > 500*1024) compress_image($r['full_path'], $r['full_path'], 500);
            db()->prepare("INSERT INTO sarpras_foto (sarpras_id,foto_path,urutan) VALUES (?,?,(SELECT COALESCE(MAX(x.urutan),0)+1 FROM sarpras_foto x WHERE x.sarpras_id=?))")->execute([$sarpras_id, $r['path'], $sarpras_id]);
        }
    }
}

function createSarpras() {
    $user = sp_auth(); sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input();
    
    $ruang_id = !empty($d['ruang_id']) ? (int)$d['ruang_id'] : null;

    // Enforcement for PJ
    if (!empty($user['scoped_ruang_ids'])) {
        if ($ruang_id > 0 && !in_array($ruang_id, $user['scoped_ruang_ids'])) {
            json_response(403, false, 'Anda hanya diperbolehkan menambah aset ke ruangan yang Anda kelola');
        }
    }

    $source_id = (int)($d['source_id'] ?? 0);
    $jumlah = max(1, (int)($d['jumlah'] ?? 1));
    $baik = (int)($d['kondisi_baik'] ?? 0);
    $rr = (int)($d['kondisi_rusak_ringan'] ?? 0);
    $rb = (int)($d['kondisi_rusak_berat'] ?? 0);

    // Validation: Kondisi must equal Jumlah
    if (($baik + $rr + $rb) !== $jumlah) {
        json_response(400, false, "Total kondisi ($baik+$rr+$rb) harus sama dengan Jumlah ($jumlah)");
    }

    if ($source_id > 0 && $ruang_id > 0) {
        // DISTRIBUTION LOGIC
        $source = db()->prepare("SELECT * FROM sarpras WHERE id = ? AND ruang_id IS NULL");
        $source->execute([$source_id]);
        $sData = $source->fetch();
        if (!$sData) json_response(404, false, 'Barang sumber di gudang tidak ditemukan');
        if ($sData['jumlah'] < $jumlah) json_response(400, false, "Stok tidak mencukupi. Sisa gudang: {$sData['jumlah']}");

        if ($sData['jumlah'] == $jumlah) {
            // Move entire record
            $stmt = db()->prepare("UPDATE sarpras SET ruang_id=?, kondisi_baik=?, kondisi_rusak_ringan=?, kondisi_rusak_berat=? WHERE id=?");
            $stmt->execute([$ruang_id, $baik, $rr, $rb, $source_id]);
            json_response(200, true, 'Barang berhasil dipindahkan ke ruangan', ['id' => $source_id]);
        } else {
            // Split record
            db()->beginTransaction();
            try {
                // Decrement warehouse original
                db()->prepare("UPDATE sarpras SET 
                    jumlah = jumlah - ?, 
                    kondisi_baik = GREATEST(0, kondisi_baik - ?),
                    kondisi_rusak_ringan = GREATEST(0, kondisi_rusak_ringan - ?),
                    kondisi_rusak_berat = GREATEST(0, kondisi_rusak_berat - ?) 
                    WHERE id = ?")
                  ->execute([$jumlah, $baik, $rr, $rb, $source_id]);
                
                // Create new room record (clone)
                $cols = "ruang_id,kategori_id,nama,kode_inventaris,merk,spesifikasi,jumlah,kondisi_baik,kondisi_rusak_ringan,kondisi_rusak_berat,tanggal_perolehan,harga_perolehan,asal_perolehan,masa_manfaat_tahun,keterangan,judul_buku,pengarang,penerbit,grup_pintasan,no_polisi,no_bpkb,alamat,kepemilikan,jenis_sarana";
                $newStmt = db()->prepare("INSERT INTO sarpras ($cols) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $newStmt->execute([
                    $ruang_id, $sData['kategori_id'], $sData['nama'], $sData['kode_inventaris'], $sData['merk'], $sData['spesifikasi'],
                    $jumlah, $baik, $rr, $rb, $sData['tanggal_perolehan'], $sData['harga_perolehan'], $sData['asal_perolehan'],
                    $sData['masa_manfaat_tahun'], $sData['keterangan'], $sData['judul_buku'], $sData['pengarang'], $sData['penerbit'], $sData['grup_pintasan'],
                    $sData['no_polisi'], $sData['no_bpkb'], $sData['alamat'], $sData['kepemilikan'], $sData['jenis_sarana']
                ]);
                $newId = db()->lastInsertId();
                db()->commit();
                json_response(201, true, 'Barang berhasil didistribusikan ke ruangan', ['id' => $newId]);
            } catch (Exception $e) {
                db()->rollBack();
                json_response(500, false, 'Gagal distribusi: '.$e->getMessage());
            }
        }
    }

    // REGULAR CREATE LOGIC
    $kategori_id = (int)($d['kategori_id'] ?? 0);
    $jenis_sarana = sanitize($d['jenis_sarana'] ?? null);
    
    // Auto-create/assign Kategori Inventaris for Buku if omitted
    if ($kategori_id <= 0 && ($d['grup_pintasan'] ?? '') === 'buku') {
        $check = db()->query("SELECT id FROM kategori_sarpras WHERE nama LIKE '%buku%' OR nama LIKE '%pustaka%'")->fetch();
        if ($check) {
            $kategori_id = $check['id'];
        } else {
            db()->query("INSERT INTO kategori_sarpras (nama, kode, jenis) VALUES ('Koleksi Buku', 'BK', 'sarana')");
            $kategori_id = db()->lastInsertId();
        }
    }
    
    $nama = sanitize($d['nama'] ?? '');
    $tahun_perolehan = sanitize($d['tanggal_perolehan'] ?? date('Y-m-d'));
    $tahun_str = explode('-', $tahun_perolehan)[0];
    if (strlen($tahun_str) !== 4) $tahun_str = date('Y'); // Fix year bug
    
    $kode = sanitize($d['kode_inventaris'] ?? '');
    if (empty($kode) || $kode === 'Otomatis' || $kode === 'otomatis') {
        $kode = getNextKodeInventaris($kategori_id, $tahun_str);
    }

    if (!$kategori_id || !$nama || !$kode) json_response(400, false, 'Kategori, nama, dan kode wajib diisi');
    
    try {
        $cols = "ruang_id,kategori_id,nama,kode_inventaris,merk,spesifikasi,jumlah,kondisi_baik,kondisi_rusak_ringan,kondisi_rusak_berat,tanggal_perolehan,harga_perolehan,asal_perolehan,masa_manfaat_tahun,keterangan,judul_buku,pengarang,penerbit,grup_pintasan,no_polisi,no_bpkb,alamat,kepemilikan,jenis_sarana";
        $stmt = db()->prepare("INSERT INTO sarpras ($cols) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $ruang_id, $kategori_id, $nama, $kode, sanitize($d['merk']??''), sanitize($d['spesifikasi']??''), $jumlah, $baik, $rr, $rb, $tahun_perolehan, floatval($d['harga_perolehan']??0), sanitize($d['asal_perolehan']??'APBD'), (int)($d['masa_manfaat_tahun']??5), sanitize($d['keterangan']??''), 
            sanitize($d['judul_buku']??null), sanitize($d['pengarang']??null), sanitize($d['penerbit']??null), sanitize($d['grup_pintasan']??null),
            sanitize($d['no_polisi']??''), sanitize($d['no_bpkb']??''), sanitize($d['alamat']??''), sanitize($d['kepemilikan']??''), sanitize($d['jenis_sarana']??null)
        ]);
        $newId = db()->lastInsertId();
        processFotoUpload($newId);
        json_response(201, true, 'Sarpras berhasil ditambahkan', ['id' => $newId, 'kode_inventaris' => $kode]);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: '.$e->getMessage());
    }
}

function updateSarpras() {
    $user = sp_auth(); sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input(); $id = (int)($d['id'] ?? 0);
    $ruang_id = !empty($d['ruang_id']) ? (int)$d['ruang_id'] : null;
    $kategori_id = (int)($d['kategori_id'] ?? 0);
    if ($kategori_id <= 0 && ($d['grup_pintasan'] ?? '') === 'buku') {
        $check = db()->query("SELECT id FROM kategori_sarpras WHERE nama LIKE '%buku%' OR nama LIKE '%pustaka%'")->fetch();
        if ($check) {
            $kategori_id = $check['id'];
        } else {
            db()->query("INSERT INTO kategori_sarpras (nama, kode, jenis) VALUES ('Koleksi Buku', 'BK', 'sarana')");
            $kategori_id = db()->lastInsertId();
        }
        $d['kategori_id'] = $kategori_id;
    }
    if ($id <= 0) json_response(400, false, 'ID tidak valid');

    // Scoping check for PJ (check existing record)
    if (!empty($user['scoped_ruang_ids'])) {
        $check = db()->prepare("SELECT ruang_id FROM sarpras WHERE id=?");
        $check->execute([$id]);
        $existing = $check->fetch();
        if ($existing && !in_array($existing['ruang_id'], $user['scoped_ruang_ids'])) {
            json_response(403, false, 'Anda tidak memiliki akses untuk mengubah aset ini');
        }
        // Also check new room if changed
        if ($ruang_id > 0 && !in_array($ruang_id, $user['scoped_ruang_ids'])) {
            json_response(403, false, 'Anda hanya boleh memindahkan aset ke ruangan yang Anda kelola');
        }
    }

    try {
        $set = "ruang_id=?,kategori_id=?,nama=?,kode_inventaris=?,merk=?,spesifikasi=?,jumlah=?,kondisi_baik=?,kondisi_rusak_ringan=?,kondisi_rusak_berat=?,tanggal_perolehan=?,harga_perolehan=?,asal_perolehan=?,masa_manfaat_tahun=?,keterangan=?,judul_buku=?,pengarang=?,penerbit=?,grup_pintasan=?,no_polisi=?,no_bpkb=?,alamat=?,kepemilikan=?,jenis_sarana=?";
        $stmt = db()->prepare("UPDATE sarpras SET $set WHERE id=?");
        $stmt->execute([
            $ruang_id, (int)($d['kategori_id']??0), sanitize($d['nama']??''), sanitize($d['kode_inventaris']??''), sanitize($d['merk']??''), sanitize($d['spesifikasi']??''), max(1,(int)($d['jumlah']??1)), (int)($d['kondisi_baik']??0), (int)($d['kondisi_rusak_ringan']??0), (int)($d['kondisi_rusak_berat']??0), sanitize($d['tanggal_perolehan']??date('Y-m-d')), floatval($d['harga_perolehan']??0), sanitize($d['asal_perolehan']??'APBD'), (int)($d['masa_manfaat_tahun']??5), sanitize($d['keterangan']??''), sanitize($d['judul_buku']??null), sanitize($d['pengarang']??null), sanitize($d['penerbit']??null), sanitize($d['grup_pintasan']??null),
            sanitize($d['no_polisi']??''), sanitize($d['no_bpkb']??''), sanitize($d['alamat']??''), sanitize($d['kepemilikan']??''), sanitize($d['jenis_sarana']??null), $id
        ]);
        
        processFotoUpload($id);
        
        json_response(200, true, 'Sarpras berhasil diperbarui');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'uk_kode_inventaris') !== false) json_response(400, false, 'Kode inventaris sudah digunakan');
        json_response(500, false, 'Error: '.$e->getMessage());
    }
}

function deleteSarpras() {
    $user = sp_auth(); sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input(); $id = (int)($d['id'] ?? 0);

    // Scoping check for PJ
    if (!empty($user['scoped_ruang_ids'])) {
        $check = db()->prepare("SELECT ruang_id FROM sarpras WHERE id=?");
        $check->execute([$id]);
        $existing = $check->fetch();
        if ($existing && !in_array($existing['ruang_id'], $user['scoped_ruang_ids'])) {
            json_response(403, false, 'Anda tidak memiliki akses untuk menghapus aset ini');
        }
    }

    try { db()->prepare("DELETE FROM sarpras WHERE id=?")->execute([$id]); json_response(200, true, 'Sarpras dihapus'); }
    catch (PDOException $e) { json_response(500, false, 'Error: '.$e->getMessage()); }
}

function uploadFoto() {
    $user = sp_auth(); sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');
    $id = (int)($_POST['sarpras_id'] ?? 0);
    $cnt = db()->prepare("SELECT COUNT(*) as t FROM sarpras_foto WHERE sarpras_id=?"); $cnt->execute([$id]);
    if ($cnt->fetch()['t'] >= 5) json_response(400, false, 'Maksimal 5 foto');
    if (!isset($_FILES['foto'])) json_response(400, false, 'File required');
    $r = handle_upload($_FILES['foto'], 'sarpras/items/');
    if (!$r['success']) json_response(400, false, $r['message']);
    if (filesize($r['full_path']) > 500*1024) compress_image($r['full_path'], $r['full_path'], 500);
    db()->prepare("INSERT INTO sarpras_foto (sarpras_id,foto_path,keterangan,urutan) VALUES (?,?,?,(SELECT COALESCE(MAX(x.urutan),0)+1 FROM sarpras_foto x WHERE x.sarpras_id=?))")->execute([$id, $r['path'], sanitize($_POST['keterangan']??''), $id]);
    json_response(201, true, 'Foto uploaded', ['path' => $r['path']]);
}

function deleteFoto() {
    $user = sp_auth(); sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');
    $d = get_input(); $fid = (int)($d['foto_id'] ?? 0);
    $f = db()->prepare("SELECT foto_path FROM sarpras_foto WHERE id=?"); $f->execute([$fid]); $fd=$f->fetch();
    if ($fd) { $p=UPLOAD_DIR.'../'.$fd['foto_path']; if(file_exists($p)) unlink($p); }
    db()->prepare("DELETE FROM sarpras_foto WHERE id=?")->execute([$fid]);
    json_response(200, true, 'Foto dihapus');
}

function barcodeData() {
    $user = sp_auth();
    $ruangId = (int)($_GET['ruang_id'] ?? 0);
    $sarprasId = (int)($_GET['sarpras_id'] ?? 0);
    
    $where = "WHERE 1=1";
    $where .= sp_scope_where($user, 's', 'ruang_id');
    $params = [];
    if ($ruangId > 0) { $where .= " AND s.ruang_id=?"; $params[] = $ruangId; }
    if ($sarprasId > 0) { $where .= " AND s.id=?"; $params[] = $sarprasId; }
    
    $stmt = db()->prepare("SELECT s.id, s.nama, s.kode_inventaris, s.jumlah, s.merk, r.nama as ruang_nama, r.kode_ruang, b.nama as bangunan_nama, t.nama as tanah_nama
        FROM sarpras s JOIN kategori_sarpras k ON s.kategori_id=k.id JOIN ruang r ON s.ruang_id=r.id JOIN bangunan b ON r.bangunan_id=b.id JOIN tanah t ON b.tanah_id=t.id $where ORDER BY k.kode ASC, s.nama ASC");
    $stmt->execute($params);
    json_response(200, true, 'OK', $stmt->fetchAll());
}

function copyToRuang() {
    $user = sp_auth(); sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');
    $d = get_input();
    $source_ruang = (int)($d['source_ruang_id'] ?? 0);
    $target_ruang = (int)($d['target_ruang_id'] ?? 0);
    $item_ids = $d['item_ids'] ?? [];
    
    if (!$source_ruang || !$target_ruang || empty($item_ids)) json_response(400, false, 'Data tidak lengkap');
    
    // Scoping check for PJ
    if (!empty($user['scoped_ruang_ids'])) {
        if (!in_array($target_ruang, $user['scoped_ruang_ids'])) {
            json_response(403, false, 'Anda hanya boleh menyalin aset ke ruangan yang Anda kelola');
        }
    }
    
    try {
        db()->beginTransaction();
        $in = str_repeat('?,', count($item_ids) - 1) . '?';
        $getStmt = db()->prepare("SELECT * FROM sarpras WHERE ruang_id=? AND id IN ($in)");
        $params = array_merge([$source_ruang], $item_ids);
        $getStmt->execute($params);
        $items = $getStmt->fetchAll();
        
        $insertStmt = db()->prepare("INSERT INTO sarpras (ruang_id,kategori_id,nama,kode_inventaris,merk,spesifikasi,jumlah,kondisi_baik,kondisi_rusak_ringan,kondisi_rusak_berat,tanggal_perolehan,harga_perolehan,asal_perolehan,masa_manfaat_tahun,keterangan,judul_buku,pengarang,penerbit,grup_pintasan,no_polisi,no_bpkb,alamat,kepemilikan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $updateWstmt = db()->prepare("UPDATE sarpras SET 
            jumlah = jumlah - ?, 
            kondisi_baik = GREATEST(0, kondisi_baik - ?),
            kondisi_rusak_ringan = GREATEST(0, kondisi_rusak_ringan - ?),
            kondisi_rusak_berat = GREATEST(0, kondisi_rusak_berat - ?) 
            WHERE id = ?");
        
        $copiedCount = 0;
        foreach ($items as $item) {
            // Find Warehouse record for this code to deduct stock
            $wS = db()->prepare("SELECT id, jumlah FROM sarpras WHERE kode_inventaris = ? AND ruang_id IS NULL LIMIT 1");
            $wS->execute([$item['kode_inventaris']]);
            $w = $wS->fetch();
            
            if ($w) {
                // Deduct from warehouse
                $updateWstmt->execute([$item['jumlah'], $item['kondisi_baik'], $item['kondisi_rusak_ringan'], $item['kondisi_rusak_berat'], $w['id']]);
            }

            $insertStmt->execute([
                $target_ruang, $item['kategori_id'], $item['nama'], $item['kode_inventaris'], $item['merk'], $item['spesifikasi'], 
                $item['jumlah'], $item['kondisi_baik'], $item['kondisi_rusak_ringan'], $item['kondisi_rusak_berat'], 
                $item['tanggal_perolehan'], $item['harga_perolehan'], $item['asal_perolehan'], $item['masa_manfaat_tahun'], 
                ($item['keterangan'] ?? '') . ' (Disalin)', $item['judul_buku'], $item['pengarang'], $item['penerbit'], $item['grup_pintasan'],
                $item['no_polisi'], $item['no_bpkb'], $item['alamat'], $item['kepemilikan']
            ]);
            $copiedCount++;
        }
        db()->commit();
        json_response(200, true, "Berhasil menyalin $copiedCount item ke ruang saat ini.");
    } catch (Exception $e) {
        db()->rollBack();
        json_response(500, false, 'Gagal salin: '.$e->getMessage());
    }
}

function importKoleksiBuku() {
    $user = sp_auth();
    sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');

    $d = get_input();
    $ruangId = (int)($d['ruang_id'] ?? 0);
    $itemIds = $d['item_ids'] ?? [];
    if (!is_array($itemIds)) $itemIds = [$itemIds];
    $itemIds = array_values(array_filter(array_map('intval', $itemIds)));

    if ($ruangId <= 0) json_response(400, false, 'Ruang tujuan tidak valid');
    
    // Scoping check for PJ
    if (!empty($user['scoped_ruang_ids']) && !in_array($ruangId, $user['scoped_ruang_ids'])) {
        json_response(403, false, 'Anda hanya boleh mengimport ke ruangan yang Anda kelola');
    }
    if (!$itemIds) json_response(400, false, 'Pilih minimal satu buku');

    $roomStmt = db()->prepare("SELECT id, nama, jenis_ruang FROM ruang WHERE id=? LIMIT 1");
    $roomStmt->execute([$ruangId]);
    $room = $roomStmt->fetch();
    if (!$room) json_response(404, false, 'Ruang tidak ditemukan');
    $roomName = trim((string)($room['nama'] ?? ''));
    $roomType = trim((string)($room['jenis_ruang'] ?? ''));
    if (!preg_match('/perpustak/i', $roomType) && !preg_match('/perpustak/i', $roomName)) {
        json_response(400, false, 'Import buku hanya bisa dilakukan ke ruang Perpustakaan');
    }

    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $params = array_merge([$ruangId], $itemIds);

    try {
        db()->beginTransaction();

        $checkStmt = db()->prepare("
            SELECT *
            FROM sarpras
            WHERE ruang_id IS NULL AND grup_pintasan='buku' AND id IN ($placeholders)
            FOR UPDATE
        ");
        $checkStmt->execute($itemIds);
        $sourceItems = $checkStmt->fetchAll();
        $validIds = array_map(static fn($row) => (int)$row['id'], $sourceItems);

        if (!$validIds) {
            db()->rollBack();
            json_response(400, false, 'Tidak ada buku dari Koleksi Buku yang bisa diimport');
        }

        $insertCols = "ruang_id,kategori_id,nama,kode_inventaris,merk,spesifikasi,jumlah,kondisi_baik,kondisi_rusak_ringan,kondisi_rusak_berat,tanggal_perolehan,harga_perolehan,asal_perolehan,masa_manfaat_tahun,keterangan,judul_buku,pengarang,penerbit,grup_pintasan,no_polisi,no_bpkb,alamat,kepemilikan,jenis_sarana";
        $insertStmt = db()->prepare("INSERT INTO sarpras ($insertCols) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $zeroPlaceholders = implode(',', array_fill(0, count($validIds), '?'));
        $resetStmt = db()->prepare("
            UPDATE sarpras
            SET jumlah = 0,
                kondisi_baik = 0,
                kondisi_rusak_ringan = 0,
                kondisi_rusak_berat = 0
            WHERE id IN ($zeroPlaceholders)
        ");

        foreach ($sourceItems as $item) {
            $jumlah = max(0, (int)($item['jumlah'] ?? 0));
            if ($jumlah <= 0) {
                continue;
            }

            $baik = min($jumlah, max(0, (int)($item['kondisi_baik'] ?? 0)));
            $rr = min(max(0, $jumlah - $baik), max(0, (int)($item['kondisi_rusak_ringan'] ?? 0)));
            $rb = max(0, $jumlah - $baik - $rr);

            $insertStmt->execute([
                $ruangId,
                $item['kategori_id'],
                $item['nama'],
                $item['kode_inventaris'],
                $item['merk'],
                $item['spesifikasi'],
                $jumlah,
                $baik,
                $rr,
                $rb,
                $item['tanggal_perolehan'],
                $item['harga_perolehan'],
                $item['asal_perolehan'],
                $item['masa_manfaat_tahun'],
                $item['keterangan'],
                $item['judul_buku'],
                $item['pengarang'],
                $item['penerbit'],
                $item['grup_pintasan'],
                $item['no_polisi'],
                $item['no_bpkb'],
                $item['alamat'],
                $item['kepemilikan'],
                $item['jenis_sarana']
            ]);
        }

        $resetStmt->execute($validIds);

        db()->commit();
        json_response(200, true, 'Berhasil mengimport ' . count($validIds) . ' buku ke ruang perpustakaan');
    } catch (Exception $e) {
        if (db()->inTransaction()) db()->rollBack();
        json_response(500, false, 'Gagal import buku: ' . $e->getMessage());
    }
}

function importBuku() {
    $user = sp_auth(); sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');
    $ruang_id = (int)($_POST['ruang_id'] ?? 0);
    
    // Enforcement for PJ
    if (!empty($user['scoped_ruang_ids'])) {
        if ($ruang_id > 0 && !in_array($ruang_id, $user['scoped_ruang_ids'])) {
            json_response(403, false, 'Anda hanya diperbolehkan mengimport aset ke ruangan yang Anda kelola');
        }
    }
    
    // Find category ID for "Buku" or "BKS"
    $cStmt = db()->prepare("SELECT id FROM kategori_sarpras WHERE kode='BKS' OR nama LIKE '%Buku%' LIMIT 1");
    $cStmt->execute();
    $cat = $cStmt->fetch();
    if (!$cat) json_response(400, false, 'Kategori Buku belum terdaftar di Data Referensi');
    $kategoriId = $cat['id'];
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_response(400, false, 'File CSV tidak valid');
    }
    
    $file = fopen($_FILES['file']['tmp_name'], 'r');
    if (!$file) json_response(400, false, 'Gagal membaca CSV');
    
    // Skip header line
    fgetcsv($file);
    
    $insertStmt = db()->prepare("INSERT INTO sarpras (ruang_id,kategori_id,nama,kode_inventaris,judul_buku,pengarang,penerbit,jumlah,kondisi_baik,tanggal_perolehan,harga_perolehan,asal_perolehan,jenis_sarana,grup_pintasan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    
    $imported = 0;
    try {
        db()->beginTransaction();
        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            // CSV columns: 0:Judul Buku, 1:Pengarang, 2:Penerbit, 3:Tahun Perolehan, 4:Klasifikasi Buku, 5:Jumlah, 6:Sumber Dana, 7:Harga
            $judul = sanitize($data[0] ?? '');
            if (!$judul) continue;
            $pengarang = sanitize($data[1] ?? '');
            $penerbit = sanitize($data[2] ?? '');
            $tahun_perolehan = sanitize($data[3] ?? date('Y'));
            $klasifikasi = sanitize($data[4] ?? '-');
            $jumlah = max(1, (int)($data[5] ?? 1));
            $sumber = sanitize($data[6] ?? 'BOS');
            $harga = floatval($data[7] ?? 0);
            
            $tgl_perolehan = $tahun_perolehan . '-01-01'; // Default date matching year
            $kode_baru = getNextKodeInventaris($kategoriId, $tahun_perolehan);
            
            $insertStmt->execute([
                $ruangId, $kategoriId, $judul, $kode_baru, $judul, $pengarang, $penerbit,
                $jumlah, $jumlah, $tgl_perolehan, $harga, $sumber, $klasifikasi, 'buku'
            ]);
            $imported++;
        }
        fclose($file);
        db()->commit();
        json_response(200, true, "Berhasil import $imported data buku");
    } catch (PDOException $e) {
        db()->rollBack();
        fclose($file);
        json_response(500, false, 'Gagal import: ' . $e->getMessage());
    }
}
