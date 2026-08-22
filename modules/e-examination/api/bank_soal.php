<?php
/**
 * E-Examination — Bank Soal API
 * CRUD: Mapel, Bank Soal, Soal, Import
 */
require_once __DIR__ . '/config_exam.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ==========================================
        // MAPEL (Mata Pelajaran)
        // ==========================================
        case 'list_mapel':
            exam_auth();
            $stmt = db()->query("SELECT * FROM exam_mapel WHERE status = 1 ORDER BY nama_mapel ASC");
            json_response(200, true, '', $stmt->fetchAll());
            break;

        case 'create_mapel':
            exam_require_admin();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $nama = trim($data['nama_mapel'] ?? '');
            $kode = trim($data['kode'] ?? '');
            if (empty($nama)) throw new Exception('Nama mapel wajib diisi', 400);

            $stmt = db()->prepare("INSERT INTO exam_mapel (nama_mapel, kode) VALUES (?, ?)");
            $stmt->execute([$nama, $kode ?: null]);
            json_response(201, true, 'Mata pelajaran berhasil ditambahkan', ['id' => db()->lastInsertId()]);
            break;

        case 'update_mapel':
            exam_require_admin();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $id = (int)($data['id'] ?? 0);
            $nama = trim($data['nama_mapel'] ?? '');
            if (!$id || empty($nama)) throw new Exception('Data tidak valid', 400);

            $stmt = db()->prepare("UPDATE exam_mapel SET nama_mapel = ?, kode = ? WHERE id = ?");
            $stmt->execute([$nama, trim($data['kode'] ?? '') ?: null, $id]);
            json_response(200, true, 'Mata pelajaran berhasil diperbarui');
            break;

        case 'delete_mapel':
            exam_require_admin();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);

            // Check if mapel used in bank soal
            $check = db()->prepare("SELECT COUNT(*) FROM exam_bank_soal WHERE mapel_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                throw new Exception('Mapel ini masih digunakan oleh bank soal. Hapus bank soal terlebih dahulu.', 400);
            }

            $stmt = db()->prepare("DELETE FROM exam_mapel WHERE id = ?");
            $stmt->execute([$id]);
            json_response(200, true, 'Mata pelajaran berhasil dihapus');
            break;

        // ==========================================
        // BANK SOAL
        // ==========================================
        case 'list_bank':
            exam_require_admin_or_guru();
            $user = exam_auth();
            
            $where = "b.status = 1";
            $params = [];

            // Filter by mapel
            if (!empty($_GET['mapel_id'])) {
                $where .= " AND b.mapel_id = ?";
                $params[] = (int)$_GET['mapel_id'];
            }
            // Filter by jenis
            if (!empty($_GET['jenis'])) {
                $where .= " AND b.jenis = ?";
                $params[] = $_GET['jenis'];
            }
            // Filter by kelas
            if (!empty($_GET['kelas'])) {
                $where .= " AND b.kelas = ?";
                $params[] = $_GET['kelas'];
            }
            // Guru can only see own bank soal
            if ($user['is_guru'] && !$user['is_admin']) {
                $where .= " AND b.created_by = ?";
                $params[] = $user['user_id'];
            }

            $sql = "
                SELECT b.*, m.nama_mapel, 
                       (SELECT COUNT(*) FROM exam_soal WHERE bank_soal_id = b.id AND status = 1) as jumlah_soal,
                       u.nama_lengkap as created_by_name
                FROM exam_bank_soal b
                LEFT JOIN exam_mapel m ON m.id = b.mapel_id
                LEFT JOIN users u ON u.id = b.created_by
                WHERE $where
                ORDER BY b.updated_at DESC
            ";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            json_response(200, true, '', $stmt->fetchAll());
            break;

        case 'get_bank':
            exam_require_admin_or_guru();
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);

            $stmt = db()->prepare("
                SELECT b.*, m.nama_mapel,
                       (SELECT COUNT(*) FROM exam_soal WHERE bank_soal_id = b.id AND status = 1) as jumlah_soal
                FROM exam_bank_soal b
                LEFT JOIN exam_mapel m ON m.id = b.mapel_id
                WHERE b.id = ?
            ");
            $stmt->execute([$id]);
            $bank = $stmt->fetch();
            if (!$bank) throw new Exception('Bank soal tidak ditemukan', 404);
            json_response(200, true, '', $bank);
            break;

        case 'create_bank':
            $user = exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();

            $mapel_id = (int)($data['mapel_id'] ?? 0);
            $judul = trim($data['judul'] ?? '');
            $jenis = $data['jenis'] ?? 'penilaian';
            $kategori = trim($data['kategori_ujian'] ?? '');
            $tahun = trim($data['tahun_ajaran'] ?? '');
            $semester = $data['semester'] ?? '1';
            $kelas = trim($data['kelas'] ?? '');

            if (!$mapel_id || empty($judul)) {
                throw new Exception('Mapel dan judul wajib diisi', 400);
            }

            $stmt = db()->prepare("
                INSERT INTO exam_bank_soal (mapel_id, judul, jenis, kategori_ujian, tahun_ajaran, semester, kelas, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$mapel_id, $judul, $jenis, $kategori ?: null, $tahun ?: null, $semester, $kelas ?: null, $user['user_id']]);
            json_response(201, true, 'Bank soal berhasil dibuat', ['id' => db()->lastInsertId()]);
            break;

        case 'update_bank':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);

            $stmt = db()->prepare("
                UPDATE exam_bank_soal SET 
                    mapel_id = ?, judul = ?, jenis = ?, kategori_ujian = ?,
                    tahun_ajaran = ?, semester = ?, kelas = ?
                WHERE id = ?
            ");
            $stmt->execute([
                (int)($data['mapel_id'] ?? 0),
                trim($data['judul'] ?? ''),
                $data['jenis'] ?? 'penilaian',
                trim($data['kategori_ujian'] ?? '') ?: null,
                trim($data['tahun_ajaran'] ?? '') ?: null,
                $data['semester'] ?? '1',
                trim($data['kelas'] ?? '') ?: null,
                $id
            ]);
            json_response(200, true, 'Bank soal berhasil diperbarui');
            break;

        case 'delete_bank':
            exam_require_admin();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);

            // Check if bank used in ujian
            $check = db()->prepare("SELECT COUNT(*) FROM exam_ujian WHERE bank_soal_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                throw new Exception('Bank soal ini masih digunakan oleh ujian. Hapus ujian terlebih dahulu.', 400);
            }

            // Delete soal first
            db()->prepare("DELETE FROM exam_soal WHERE bank_soal_id = ?")->execute([$id]);
            db()->prepare("DELETE FROM exam_psikologi_hasil WHERE bank_soal_id = ?")->execute([$id]);
            db()->prepare("DELETE FROM exam_bank_soal WHERE id = ?")->execute([$id]);
            json_response(200, true, 'Bank soal berhasil dihapus');
            break;

        // ==========================================
        // SOAL
        // ==========================================
        case 'list_soal':
            exam_require_admin_or_guru();
            $bank_id = (int)($_GET['bank_soal_id'] ?? 0);
            if (!$bank_id) throw new Exception('bank_soal_id wajib diisi', 400);

            $stmt = db()->prepare("
                SELECT * FROM exam_soal 
                WHERE bank_soal_id = ? AND status = 1 
                ORDER BY urutan ASC, id ASC
            ");
            $stmt->execute([$bank_id]);
            json_response(200, true, '', $stmt->fetchAll());
            break;

        case 'create_soal':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();

            $bank_id = (int)($data['bank_soal_id'] ?? 0);
            $tipe = $data['tipe_soal'] ?? '';
            $pertanyaan = trim($data['pertanyaan'] ?? '');

            if (!$bank_id || empty($tipe) || empty($pertanyaan)) {
                throw new Exception('Data soal tidak lengkap', 400);
            }

            $opsi = isset($data['opsi']) ? json_encode($data['opsi'], JSON_UNESCAPED_UNICODE) : null;
            $kunci = isset($data['kunci_jawaban']) ? json_encode($data['kunci_jawaban'], JSON_UNESCAPED_UNICODE) : null;

            // Get next urutan
            $stmtMax = db()->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 FROM exam_soal WHERE bank_soal_id = ?");
            $stmtMax->execute([$bank_id]);
            $nextUrutan = $stmtMax->fetchColumn();

            $stmt = db()->prepare("
                INSERT INTO exam_soal (bank_soal_id, tipe_soal, pertanyaan, opsi, kunci_jawaban, pembahasan, bobot, gambar, audio, urutan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $bank_id,
                $tipe,
                $pertanyaan,
                $opsi,
                $kunci,
                trim($data['pembahasan'] ?? '') ?: null,
                (float)($data['bobot'] ?? 1),
                trim($data['gambar'] ?? '') ?: null,
                trim($data['audio'] ?? '') ?: null,
                $nextUrutan
            ]);
            json_response(201, true, 'Soal berhasil ditambahkan', ['id' => db()->lastInsertId()]);
            break;

        case 'update_soal':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);

            $opsi = isset($data['opsi']) ? json_encode($data['opsi'], JSON_UNESCAPED_UNICODE) : null;
            $kunci = isset($data['kunci_jawaban']) ? json_encode($data['kunci_jawaban'], JSON_UNESCAPED_UNICODE) : null;

            $stmt = db()->prepare("
                UPDATE exam_soal SET 
                    tipe_soal = ?, pertanyaan = ?, opsi = ?, kunci_jawaban = ?,
                    pembahasan = ?, bobot = ?, gambar = ?, audio = ?, urutan = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['tipe_soal'] ?? 'pilihan_satu',
                trim($data['pertanyaan'] ?? ''),
                $opsi,
                $kunci,
                trim($data['pembahasan'] ?? '') ?: null,
                (float)($data['bobot'] ?? 1),
                trim($data['gambar'] ?? '') ?: null,
                trim($data['audio'] ?? '') ?: null,
                (int)($data['urutan'] ?? 0),
                $id
            ]);
            json_response(200, true, 'Soal berhasil diperbarui');
            break;

        case 'delete_soal':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);

            $stmt = db()->prepare("UPDATE exam_soal SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            json_response(200, true, 'Soal berhasil dihapus');
            break;

        case 'reorder_soal':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $orders = $data['orders'] ?? [];
            
            $stmt = db()->prepare("UPDATE exam_soal SET urutan = ? WHERE id = ?");
            foreach ($orders as $item) {
                $stmt->execute([(int)$item['urutan'], (int)$item['id']]);
            }
            json_response(200, true, 'Urutan soal berhasil diperbarui');
            break;

        // ==========================================
        // IMPORT SOAL
        // ==========================================
        case 'import_soal':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);

            $bank_id = (int)($_POST['bank_soal_id'] ?? 0);
            if (!$bank_id) throw new Exception('bank_soal_id wajib diisi', 400);

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File tidak valid', 400);
            }

            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'json'])) {
                throw new Exception('Format file harus CSV atau JSON', 400);
            }

            $content = file_get_contents($_FILES['file']['tmp_name']);
            $imported = 0;

            // Get next urutan
            $stmtMax = db()->prepare("SELECT COALESCE(MAX(urutan), 0) FROM exam_soal WHERE bank_soal_id = ?");
            $stmtMax->execute([$bank_id]);
            $nextUrutan = $stmtMax->fetchColumn() + 1;

            if ($ext === 'json') {
                $rows = json_decode($content, true);
                if (!is_array($rows)) throw new Exception('Format JSON tidak valid', 400);
            } else {
                // CSV: tipe_soal, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci, pembahasan, bobot
                $lines = array_filter(array_map('trim', explode("\n", $content)));
                $rows = [];
                $isFirst = true;
                foreach ($lines as $line) {
                    if ($isFirst) { $isFirst = false; continue; } // Skip header
                    $cols = str_getcsv($line);
                    if (count($cols) < 3) continue;
                    
                    $tipe = trim($cols[0] ?? 'pilihan_satu');
                    $pertanyaan = trim($cols[1] ?? '');
                    
                    // Build opsi from columns
                    $opsi = [];
                    $labels = ['A','B','C','D','E'];
                    for ($i = 0; $i < 5; $i++) {
                        $val = trim($cols[$i + 2] ?? '');
                        if (!empty($val)) {
                            $opsi[] = ['label' => $labels[$i], 'text' => $val];
                        }
                    }
                    
                    $kunci = trim($cols[7] ?? '');
                    $pembahasan = trim($cols[8] ?? '');
                    $bobot = (float)($cols[9] ?? 1);

                    $rows[] = [
                        'tipe_soal' => $tipe,
                        'pertanyaan' => $pertanyaan,
                        'opsi' => $opsi,
                        'kunci_jawaban' => $kunci,
                        'pembahasan' => $pembahasan,
                        'bobot' => $bobot
                    ];
                }
            }

            $stmtInsert = db()->prepare("
                INSERT INTO exam_soal (bank_soal_id, tipe_soal, pertanyaan, opsi, kunci_jawaban, pembahasan, bobot, urutan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($rows as $row) {
                $tipe = $row['tipe_soal'] ?? 'pilihan_satu';
                $pertanyaan = trim($row['pertanyaan'] ?? '');
                if (empty($pertanyaan)) continue;

                $opsi = isset($row['opsi']) ? json_encode($row['opsi'], JSON_UNESCAPED_UNICODE) : null;
                $kunci = isset($row['kunci_jawaban']) ? json_encode($row['kunci_jawaban'], JSON_UNESCAPED_UNICODE) : null;

                $stmtInsert->execute([
                    $bank_id,
                    $tipe,
                    $pertanyaan,
                    $opsi,
                    $kunci,
                    trim($row['pembahasan'] ?? '') ?: null,
                    (float)($row['bobot'] ?? 1),
                    $nextUrutan++
                ]);
                $imported++;
            }

            json_response(200, true, "Berhasil mengimport $imported soal", ['imported' => $imported]);
            break;

        // ==========================================
        // PSIKOLOGI HASIL (Psychology Profile Results Ranges)
        // ==========================================
        case 'list_psikologi_hasil':
            exam_require_admin_or_guru();
            $bank_id = (int)($_GET['bank_soal_id'] ?? 0);
            if (!$bank_id) throw new Exception('bank_soal_id wajib diisi', 400);

            $stmt = db()->prepare("SELECT * FROM exam_psikologi_hasil WHERE bank_soal_id = ? ORDER BY rentang_min ASC");
            $stmt->execute([$bank_id]);
            json_response(200, true, '', $stmt->fetchAll());
            break;

        case 'create_psikologi_hasil':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();

            $bank_id = (int)($data['bank_soal_id'] ?? 0);
            $kode_hasil = trim($data['kode_hasil'] ?? '');
            $deskripsi = trim($data['deskripsi'] ?? '');
            $rentang_min = (float)($data['rentang_min'] ?? 0);
            $rentang_max = (float)($data['rentang_max'] ?? 100);

            if (!$bank_id || empty($kode_hasil) || empty($deskripsi)) {
                throw new Exception('Data tidak lengkap', 400);
            }

            $stmt = db()->prepare("
                INSERT INTO exam_psikologi_hasil (bank_soal_id, kode_hasil, deskripsi, rentang_min, rentang_max)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$bank_id, $kode_hasil, $deskripsi, $rentang_min, $rentang_max]);
            json_response(201, true, 'Profil hasil psikologi berhasil ditambahkan', ['id' => db()->lastInsertId()]);
            break;

        case 'update_psikologi_hasil':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();

            $id = (int)($data['id'] ?? 0);
            $kode_hasil = trim($data['kode_hasil'] ?? '');
            $deskripsi = trim($data['deskripsi'] ?? '');
            $rentang_min = (float)($data['rentang_min'] ?? 0);
            $rentang_max = (float)($data['rentang_max'] ?? 100);

            if (!$id || empty($kode_hasil) || empty($deskripsi)) {
                throw new Exception('Data tidak lengkap', 400);
            }

            $stmt = db()->prepare("
                UPDATE exam_psikologi_hasil 
                SET kode_hasil = ?, deskripsi = ?, rentang_min = ?, rentang_max = ?
                WHERE id = ?
            ");
            $stmt->execute([$kode_hasil, $deskripsi, $rentang_min, $rentang_max, $id]);
            json_response(200, true, 'Profil hasil psikologi berhasil diperbarui');
            break;

        case 'delete_psikologi_hasil':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);

            $stmt = db()->prepare("DELETE FROM exam_psikologi_hasil WHERE id = ?");
            $stmt->execute([$id]);
            json_response(200, true, 'Profil hasil psikologi berhasil dihapus');
            break;

        // ==========================================
        // CLASSES (get available classes)
        // ==========================================
        case 'list_classes':
            exam_auth();
            json_response(200, true, '', exam_get_classes());
            break;

        // ==========================================
        // STATS (dashboard)
        // ==========================================
        case 'stats':
            $user = exam_require_admin_or_guru();
            
            $whereBank = "status = 1";
            $paramsBank = [];
            if ($user['is_guru'] && !$user['is_admin']) {
                $whereBank .= " AND created_by = ?";
                $paramsBank[] = $user['user_id'];
            }

            $stmtMapel = db()->query("SELECT COUNT(*) FROM exam_mapel WHERE status = 1");
            $stmtBank = db()->prepare("SELECT COUNT(*) FROM exam_bank_soal WHERE $whereBank");
            $stmtBank->execute($paramsBank);
            $stmtSoal = db()->query("SELECT COUNT(*) FROM exam_soal WHERE status = 1");
            $stmtUjian = db()->query("SELECT COUNT(*) FROM exam_ujian");
            $stmtUjianAktif = db()->query("SELECT COUNT(*) FROM exam_ujian WHERE status = 'aktif'");

            json_response(200, true, '', [
                'total_mapel'       => (int)$stmtMapel->fetchColumn(),
                'total_bank_soal'   => (int)$stmtBank->fetchColumn(),
                'total_soal'        => (int)$stmtSoal->fetchColumn(),
                'total_ujian'       => (int)$stmtUjian->fetchColumn(),
                'ujian_aktif'       => (int)$stmtUjianAktif->fetchColumn(),
            ]);
            break;

        default:
            throw new Exception('Action tidak valid: ' . $action, 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) $code = 500;
    json_response($code, false, $e->getMessage());
}
