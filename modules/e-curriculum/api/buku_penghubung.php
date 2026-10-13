<?php
/**
 * E-Curriculum Buku Penghubung API
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Ensure tables exist before executing any action
ensure_buku_tables();

switch ($action) {
    case 'list':
        listBuku($user);
        break;
    case 'create':
        createBuku($user);
        break;
    case 'update':
        updateBuku($user);
        break;
    case 'delete':
        deleteBuku($user);
        break;
    case 'student_detail':
        studentDetail($user);
        break;
    case 'list_types':
        listBukuTypes($user);
        break;
    case 'save_type':
        saveBukuType($user);
        break;
    case 'delete_type':
        deleteBukuType($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function ensure_buku_tables() {
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS acad_buku_types (
            id int(11) unsigned NOT NULL AUTO_INCREMENT,
            nama_jenis varchar(100) NOT NULL,
            deskripsi varchar(255) DEFAULT NULL,
            warna_badge varchar(50) NOT NULL DEFAULT 'badge-info',
            urutan int(11) NOT NULL DEFAULT 0,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY nama_jenis (nama_jenis)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $count = (int)db()->query("SELECT COUNT(*) FROM acad_buku_types")->fetchColumn();
        if ($count === 0) {
            $defaultBukuTypes = [
                ['Keterlambatan', 'Catatan siswa terlambat masuk sekolah', 'badge-warning', 1],
                ['Pelanggaran', 'Catatan pelanggaran tata tertib sekolah', 'badge-danger', 2],
                ['Prestasi', 'Penghargaan / prestasi akademik & non-akademik', 'badge-success', 3],
                ['Screening', 'Catatan screening kesehatan / perilaku siswa', 'badge-info', 4],
                ['Konsultasi', 'Bimbingan dan konseling siswa', 'badge-primary', 5]
            ];
            $stmtBT = db()->prepare("INSERT IGNORE INTO acad_buku_types (nama_jenis, deskripsi, warna_badge, urutan) VALUES (?,?,?,?)");
            foreach ($defaultBukuTypes as $dbt) {
                $stmtBT->execute($dbt);
            }
        }

        db()->exec("CREATE TABLE IF NOT EXISTS acad_buku_penghubung (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            student_id int(11) unsigned NOT NULL,
            kelas_id int(10) unsigned NOT NULL,
            academic_year_id int(11) unsigned NOT NULL,
            jenis varchar(100) NOT NULL DEFAULT 'Konsultasi',
            tanggal date NOT NULL,
            catatan text NOT NULL,
            dicatat_oleh int(11) unsigned DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (id),
            KEY idx_student (student_id),
            KEY idx_kelas (kelas_id),
            KEY idx_tanggal (tanggal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        try {
            db()->exec("ALTER TABLE acad_buku_penghubung ADD COLUMN foto text DEFAULT NULL AFTER catatan");
        } catch (Exception $ex) {}
    } catch (Exception $e) {}
}

function helper_save_base64_photo($base64Data, $subfolder = 'bk') {
    if (empty($base64Data)) return null;

    if (strpos($base64Data, 'uploads/') === 0 || strpos($base64Data, 'http://') === 0 || strpos($base64Data, 'https://') === 0) {
        return $base64Data;
    }

    if (!preg_match('/^data:image\/(\w+);base64,/', $base64Data, $typeMatch)) {
        return null;
    }

    $ext = strtolower($typeMatch[1]);
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $ext = 'jpg';
    }
    if ($ext === 'jpeg') $ext = 'jpg';

    $base64Body = substr($base64Data, strpos($base64Data, ',') + 1);
    $binary = base64_decode($base64Body);
    if ($binary === false || strlen($binary) === 0) return null;

    $uploadRoot = realpath(__DIR__ . '/../../../uploads') ?: (__DIR__ . '/../../../uploads');
    $targetDir = $uploadRoot . '/' . trim($subfolder, '/');
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    $filename = $subfolder . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filePath = $targetDir . '/' . $filename;
    file_put_contents($filePath, $binary);

    return 'uploads/' . trim($subfolder, '/') . '/' . $filename;
}

function get_buku_input() {
    $input = get_input();
    if (empty($input) || (!isset($input['student_id']) && !isset($input['id']) && !isset($input['nama_jenis']))) {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $input = array_merge($input ?: [], $json);
            }
        }
    }
    return $input ?: [];
}

function listBuku($user) {
    try {
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
        $jenis = isset($_GET['jenis']) ? trim($_GET['jenis']) : '';
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        $where = "1=1";
        $params = [];

        if ($year_id > 0) {
            $where .= " AND (b.academic_year_id = ? OR b.academic_year_id IS NULL OR b.academic_year_id = 0)";
            $params[] = $year_id;
        }

        if ($kelas_id > 0) {
            $where .= " AND b.kelas_id = ?";
            $params[] = $kelas_id;
        }
        if ($student_id > 0) {
            $where .= " AND b.student_id = ?";
            $params[] = $student_id;
        }
        if (!empty($jenis)) {
            $where .= " AND b.jenis = ?";
            $params[] = $jenis;
        }

        $stmt = db()->prepare("
            SELECT b.*, s.nama as nama_siswa, s.nis, COALESCE(k.nama_kelas, s.kelas) as nama_kelas, u.nama_lengkap as dicatat_nama,
                   COALESCE(t.warna_badge, 'badge-info') as warna_badge, t.deskripsi as jenis_deskripsi
            FROM acad_buku_penghubung b
            JOIN students s ON b.student_id = s.id
            LEFT JOIN sch_kelas k ON b.kelas_id = k.id
            LEFT JOIN users u ON b.dicatat_oleh = u.id
            LEFT JOIN acad_buku_types t ON b.jenis = t.nama_jenis
            WHERE $where
            ORDER BY b.tanggal DESC, b.id DESC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response(200, true, 'Data buku penghubung dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function createBuku($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_buku_input();
    $student_id = isset($input['student_id']) ? (int)$input['student_id'] : 0;
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : 0;
    $jenis = isset($input['jenis']) ? trim($input['jenis']) : '';
    $tanggal = isset($input['tanggal']) && !empty($input['tanggal']) ? trim($input['tanggal']) : date('Y-m-d');
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';

    if ($student_id <= 0) {
        json_response(400, false, 'Silakan pilih siswa terlebih dahulu.');
    }

    // Auto-resolve kelas_id if not provided
    if ($kelas_id <= 0) {
        $stmtK = db()->prepare("
            SELECT k.id FROM sch_kelas k
            JOIN students s ON s.kelas = k.nama_kelas
            WHERE s.id = ? LIMIT 1
        ");
        $stmtK->execute([$student_id]);
        $kelas_id = (int)$stmtK->fetchColumn();
    }

    if ($kelas_id <= 0) {
        json_response(400, false, 'Kelas siswa tidak ditemukan.');
    }

    if (empty($jenis)) {
        $jenis = 'Konsultasi';
    }

    if (empty($catatan)) {
        json_response(400, false, 'Isi catatan tidak boleh kosong.');
    }

    $active_year = get_active_academic_year();
    $year_id = !empty($active_year['id']) ? (int)$active_year['id'] : 0;
    if ($year_id <= 0) {
        $year_id = (int)db()->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
        if ($year_id <= 0) {
            $year_id = (int)db()->query("SELECT id FROM academic_years ORDER BY id DESC LIMIT 1")->fetchColumn();
        }
    }

    $foto_raw = isset($input['foto']) ? trim($input['foto']) : '';
    $foto_path = helper_save_base64_photo($foto_raw, 'bk');

    try {
        $stmt = db()->prepare("
            INSERT INTO acad_buku_penghubung (student_id, kelas_id, academic_year_id, jenis, tanggal, catatan, foto, dicatat_oleh)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $kelas_id, $year_id, $jenis, $tanggal, $catatan, $foto_path, $user['user_id']]);
        json_response(201, true, 'Catatan buku penghubung berhasil ditambahkan.');
    } catch (PDOException $e) {
        json_response(500, false, 'Gagal menyimpan catatan: ' . $e->getMessage());
    }
}

function updateBuku($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_buku_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';
    $jenis = isset($input['jenis']) ? trim($input['jenis']) : '';
    $foto_raw = isset($input['foto']) ? trim($input['foto']) : null;

    if ($id <= 0) json_response(400, false, 'ID tidak valid.');
    if (empty($catatan)) json_response(400, false, 'Catatan tidak boleh kosong.');
    if (empty($jenis)) $jenis = 'Konsultasi';

    try {
        if ($foto_raw !== null) {
            if ($foto_raw === '' || $foto_raw === 'delete') {
                $stmt = db()->prepare("UPDATE acad_buku_penghubung SET catatan = ?, jenis = ?, foto = NULL WHERE id = ?");
                $stmt->execute([$catatan, $jenis, $id]);
            } else {
                $foto_path = helper_save_base64_photo($foto_raw, 'bk');
                $stmt = db()->prepare("UPDATE acad_buku_penghubung SET catatan = ?, jenis = ?, foto = ? WHERE id = ?");
                $stmt->execute([$catatan, $jenis, $foto_path, $id]);
            }
        } else {
            $stmt = db()->prepare("UPDATE acad_buku_penghubung SET catatan = ?, jenis = ? WHERE id = ?");
            $stmt->execute([$catatan, $jenis, $id]);
        }
        json_response(200, true, 'Catatan berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function listBukuTypes($user) {
    try {
        $stmt = db()->query("SELECT * FROM acad_buku_types ORDER BY urutan ASC, id ASC");
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response(200, true, 'Daftar jenis catatan dimuat.', $types);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveBukuType($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_buku_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $nama_jenis = isset($input['nama_jenis']) ? trim($input['nama_jenis']) : '';
    $deskripsi = isset($input['deskripsi']) ? trim($input['deskripsi']) : '';
    $warna_badge = isset($input['warna_badge']) && !empty($input['warna_badge']) ? trim($input['warna_badge']) : 'badge-info';

    if (empty($nama_jenis)) {
        json_response(400, false, 'Nama jenis catatan wajib diisi.');
    }

    try {
        if ($id > 0) {
            // Update
            $stmt = db()->prepare("UPDATE acad_buku_types SET nama_jenis = ?, deskripsi = ?, warna_badge = ? WHERE id = ?");
            $stmt->execute([$nama_jenis, $deskripsi, $warna_badge, $id]);
            json_response(200, true, 'Jenis catatan berhasil diperbarui.');
        } else {
            // Insert
            $maxUrutan = (int)db()->query("SELECT MAX(urutan) FROM acad_buku_types")->fetchColumn();
            $stmt = db()->prepare("INSERT INTO acad_buku_types (nama_jenis, deskripsi, warna_badge, urutan) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama_jenis, $deskripsi, $warna_badge, $maxUrutan + 1]);
            json_response(201, true, 'Jenis catatan baru berhasil ditambahkan.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            json_response(400, false, 'Nama jenis catatan sudah ada. Silakan gunakan nama lain.');
        }
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteBukuType($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_buku_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmtCheck = db()->prepare("SELECT nama_jenis FROM acad_buku_types WHERE id = ?");
        $stmtCheck->execute([$id]);
        $namaJenis = $stmtCheck->fetchColumn();

        if (!$namaJenis) {
            json_response(404, false, 'Jenis catatan tidak ditemukan.');
        }

        $stmt = db()->prepare("DELETE FROM acad_buku_types WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Jenis catatan "' . $namaJenis . '" berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteBuku($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_buku_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmtFoto = db()->prepare("SELECT foto FROM acad_buku_penghubung WHERE id = ?");
        $stmtFoto->execute([$id]);
        $oldFoto = $stmtFoto->fetchColumn();
        if ($oldFoto && !empty($oldFoto)) {
            $fullPath = realpath(__DIR__ . '/../../../' . $oldFoto);
            if ($fullPath && file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $stmt = db()->prepare("DELETE FROM acad_buku_penghubung WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Catatan berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function studentDetail($user) {
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    if ($student_id <= 0) json_response(400, false, 'ID siswa tidak valid.');

    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        $stmtS = db()->prepare("SELECT * FROM students WHERE id = ?");
        $stmtS->execute([$student_id]);
        $student = $stmtS->fetch();
        if (!$student) json_response(404, false, 'Siswa tidak ditemukan.');

        // Get absensi summary
        $stmtA = db()->prepare("
            SELECT 
                SUM(CASE WHEN status='S' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN status='I' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status='A' THEN 1 ELSE 0 END) as alpha
            FROM acad_absensi WHERE student_id = ? AND (academic_year_id = ? OR academic_year_id IS NULL) AND jam_ke = 0
        ");
        $stmtA->execute([$student_id, $year_id]);
        $absensi = $stmtA->fetch();

        // Get buku penghubung entries
        $stmtB = db()->prepare("
            SELECT b.*, u.nama_lengkap as dicatat_nama
            FROM acad_buku_penghubung b
            LEFT JOIN users u ON b.dicatat_oleh = u.id
            WHERE b.student_id = ? AND (b.academic_year_id = ? OR b.academic_year_id IS NULL)
            ORDER BY b.tanggal DESC
        ");
        $stmtB->execute([$student_id, $year_id]);
        $entries = $stmtB->fetchAll();

        json_response(200, true, 'Detail siswa dimuat.', [
            'student' => $student,
            'absensi' => $absensi,
            'entries' => $entries
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

