<?php
/**
 * E-Sarpras Master Sarpras API
 * Katalog jenis/tipe aset untuk dijadikan referensi saat input inventaris
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':   listMaster(); break;
    case 'create': createMaster(); break;
    case 'update': updateMaster(); break;
    case 'delete': deleteMaster(); break;
    case 'template-csv': templateMasterCsv(); break;
    case 'import-csv': importMasterCsv(); break;
    default: json_response(400, false, 'Invalid action');
}

function templateMasterCsv() {
    sp_auth();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Template_Import_Master_Sarpras.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['nama', 'kategori_kode', 'kode', 'masa_manfaat_tahun']);
    fputcsv($output, ['Kursi Guru', 'MEB', 'MEB.1', '5']);
    fputcsv($output, ['Meja Siswa', 'MEB', '', '5']);
    fputcsv($output, ['LCD Proyektor', 'ELK', 'ELK.7', '4']);
    fclose($output);
    exit;
}

function importMasterCsv() {
    $user = sp_auth();
    sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_response(400, false, 'File CSV tidak valid');
    }

    $handle = fopen($_FILES['file']['tmp_name'], 'r');
    if (!$handle) {
        json_response(400, false, 'Gagal membaca file CSV');
    }

    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        json_response(400, false, 'File CSV kosong');
    }

    $delimiter = detect_csv_delimiter($firstLine);
    rewind($handle);

    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        fclose($handle);
        json_response(400, false, 'Header CSV tidak ditemukan');
    }

    $headers = array_map('normalize_import_header', $headers);
    $requiredHeaders = ['nama', 'kategori_kode'];
    foreach ($requiredHeaders as $header) {
        if (!in_array($header, $headers, true)) {
            fclose($handle);
            json_response(400, false, 'Header wajib tidak ditemukan: ' . $header);
        }
    }

    $categories = db()->query("SELECT id, kode FROM kategori_sarpras")->fetchAll(PDO::FETCH_ASSOC);
    $categoryMap = [];
    foreach ($categories as $category) {
        $categoryMap[strtoupper((string) $category['kode'])] = [
            'id' => (int) $category['id'],
            'kode' => strtoupper((string) $category['kode'])
        ];
    }

    $insertStmt = db()->prepare("
        INSERT INTO master_sarpras (
            kategori_id, nama, kode, satuan, merk_default, spesifikasi_default, masa_manfaat_default, keterangan, harga_perolehan, tanggal_perolehan
        ) VALUES (?, ?, ?, 'Unit', '', '', ?, '', 0, NULL)
    ");

    $imported = 0;
    $skipped = 0;
    $errors = [];
    $generatedCodes = [];
    $lineNumber = 1;

    try {
        db()->beginTransaction();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;
            if (row_is_empty($row)) {
                continue;
            }

            $data = map_import_row($headers, $row);
            $nama = sanitize($data['nama'] ?? '');
            $kategoriKode = strtoupper(sanitize($data['kategori_kode'] ?? ''));
            $kode = sanitize($data['kode'] ?? '');
            $masaManfaat = max(1, (int) ($data['masa_manfaat_tahun'] ?? 5));

            if ($nama === '' || $kategoriKode === '') {
                $skipped++;
                $errors[] = "Baris {$lineNumber}: nama atau kategori_kode kosong";
                continue;
            }

            if (!isset($categoryMap[$kategoriKode])) {
                $skipped++;
                $errors[] = "Baris {$lineNumber}: kategori_kode {$kategoriKode} tidak ditemukan";
                continue;
            }

            $kategoriId = $categoryMap[$kategoriKode]['id'];
            if ($kode === '') {
                if (!isset($generatedCodes[$kategoriKode])) {
                    $generatedCodes[$kategoriKode] = next_master_code_seed($kategoriKode);
                }
                $generatedCodes[$kategoriKode]++;
                $kode = $kategoriKode . '.' . $generatedCodes[$kategoriKode];
            }

            $insertStmt->execute([$kategoriId, $nama, $kode, $masaManfaat]);
            $imported++;
        }

        db()->commit();
        fclose($handle);

        $message = "Berhasil import {$imported} data master sarpras";
        if ($skipped > 0) {
            $message .= " dengan {$skipped} baris dilewati";
        }

        json_response(200, true, $message, [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10)
        ]);
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        fclose($handle);
        json_response(500, false, 'Gagal import: ' . $e->getMessage());
    }
}

function listMaster() {
    sp_auth();
    $kat = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : 0;
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $where = [];
    $params = [];
    if ($kat) { $where[] = 'm.kategori_id = ?'; $params[] = $kat; }
    if ($search) { $where[] = 'm.nama LIKE ?'; $params[] = "%$search%"; }

    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = db()->prepare("
        SELECT m.*, k.nama as kategori_nama, k.kode as kategori_kode
        FROM master_sarpras m
        JOIN kategori_sarpras k ON m.kategori_id = k.id
        $whereStr
        ORDER BY k.kode ASC, m.nama ASC
    ");
    $stmt->execute($params);
    json_response(200, true, 'OK', $stmt->fetchAll());
}

function createMaster() {
    $user = sp_auth();
    sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');

    $d = get_input();
    $nama = sanitize($d['nama'] ?? '');
    $kat  = (int)($d['kategori_id'] ?? 0);
    if (!$nama || !$kat) json_response(400, false, 'Nama dan kategori wajib diisi');

    $kode = sanitize($d['kode'] ?? '');
    $harga = $d['harga_perolehan'] ? floatval(str_replace(['Rp', '.', ' '], '', $d['harga_perolehan'])) : 0;
    $tgl = sanitize($d['tanggal_perolehan'] ?? null);

    try {
        db()->prepare("INSERT INTO master_sarpras (kategori_id, nama, kode, satuan, merk_default, spesifikasi_default, masa_manfaat_default, keterangan, harga_perolehan, tanggal_perolehan) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$kat, $nama, $kode, sanitize($d['satuan'] ?? 'Unit'), sanitize($d['merk_default'] ?? ''), sanitize($d['spesifikasi_default'] ?? ''), (int)($d['masa_manfaat_default'] ?? 5), sanitize($d['keterangan'] ?? ''), $harga, $tgl]);
        json_response(201, true, 'Master sarpras berhasil ditambahkan', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) { json_response(500, false, $e->getMessage()); }
}

function updateMaster() {
    $user = sp_auth();
    sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');

    $d = get_input();
    $id = (int)($d['id'] ?? 0);
    $nama = sanitize($d['nama'] ?? '');
    if (!$id || !$nama) json_response(400, false, 'Data tidak lengkap');

    $kode = sanitize($d['kode'] ?? '');
    $harga = $d['harga_perolehan'] ? floatval(str_replace(['Rp', '.', ' '], '', $d['harga_perolehan'])) : 0;
    $tgl = sanitize($d['tanggal_perolehan'] ?? null);

    try {
        db()->prepare("UPDATE master_sarpras SET kategori_id=?, nama=?, kode=?, satuan=?, merk_default=?, spesifikasi_default=?, masa_manfaat_default=?, keterangan=?, harga_perolehan=?, tanggal_perolehan=? WHERE id=?")
            ->execute([(int)($d['kategori_id']??0), $nama, $kode, sanitize($d['satuan']??'Unit'), sanitize($d['merk_default']??''), sanitize($d['spesifikasi_default']??''), (int)($d['masa_manfaat_default']??5), sanitize($d['keterangan']??''), $harga, $tgl, $id]);
        json_response(200, true, 'Master sarpras berhasil diupdate');
    } catch (PDOException $e) { json_response(500, false, $e->getMessage()); }
}

function deleteMaster() {
    $user = sp_auth();
    sp_require_any($user, ['sarpras_manage'], 'Akses ditolak');

    $d = get_input();
    $id = (int)($d['id'] ?? 0);
    try {
        db()->prepare("DELETE FROM master_sarpras WHERE id=?")->execute([$id]);
        json_response(200, true, 'Data berhasil dihapus');
    } catch (PDOException $e) { json_response(500, false, $e->getMessage()); }
}

function detect_csv_delimiter($line) {
    $commaCount = substr_count($line, ',');
    $semicolonCount = substr_count($line, ';');
    return $semicolonCount > $commaCount ? ';' : ',';
}

function normalize_import_header($value) {
    $value = trim((string) $value);
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim($value, '_');
}

function row_is_empty($row) {
    foreach ((array) $row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }
    return true;
}

function map_import_row($headers, $row) {
    $mapped = [];
    foreach ($headers as $index => $header) {
        $mapped[$header] = isset($row[$index]) ? trim((string) $row[$index]) : '';
    }
    return $mapped;
}

function next_master_code_seed($kategoriKode) {
    $stmt = db()->prepare("SELECT kode FROM master_sarpras WHERE kode LIKE ? ORDER BY id DESC");
    $stmt->execute([$kategoriKode . '.%']);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $max = 0;
    foreach ($rows as $code) {
        if (preg_match('/^' . preg_quote($kategoriKode, '/') . '\.(\d+)$/', (string) $code, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }
    return $max;
}
