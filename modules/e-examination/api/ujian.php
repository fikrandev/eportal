<?php
/**
 * E-Examination — Ujian API
 * CRUD Ujian, Setting Kelas, Generate Token, Aktifasi
 */
require_once __DIR__ . '/config_exam.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ==========================================
        // DAFTAR UJIAN
        // ==========================================
        case 'list':
            $user = exam_require_admin_or_guru();
            
            $where = "1=1";
            $params = [];

            // Jika guru, hanya lihat ujian yang dia buat
            if ($user['is_guru'] && !$user['is_admin']) {
                $where .= " AND u.created_by = ?";
                $params[] = $user['user_id'];
            }

            $sql = "
                SELECT u.*, b.judul as nama_bank_soal, b.jenis as jenis_bank,
                       (SELECT GROUP_CONCAT(kelas SEPARATOR ', ') FROM exam_ujian_kelas WHERE ujian_id = u.id) as kelas_peserta,
                       (SELECT COUNT(*) FROM exam_sesi WHERE ujian_id = u.id) as total_peserta
                FROM exam_ujian u
                JOIN exam_bank_soal b ON u.bank_soal_id = b.id
                WHERE $where
                ORDER BY u.created_at DESC
            ";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            
            json_response(200, true, '', $stmt->fetchAll());
            break;

        case 'get':
            exam_require_admin_or_guru();
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);

            $stmt = db()->prepare("
                SELECT u.*, b.judul as nama_bank_soal
                FROM exam_ujian u
                JOIN exam_bank_soal b ON u.bank_soal_id = b.id
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            $ujian = $stmt->fetch();
            if (!$ujian) throw new Exception('Ujian tidak ditemukan', 404);

            // Get classes
            $stmtKelas = db()->prepare("SELECT kelas FROM exam_ujian_kelas WHERE ujian_id = ?");
            $stmtKelas->execute([$id]);
            $ujian['kelas'] = $stmtKelas->fetchAll(PDO::FETCH_COLUMN);

            json_response(200, true, '', $ujian);
            break;

        // ==========================================
        // CREATE / UPDATE UJIAN
        // ==========================================
        case 'create':
            $user = exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();

            $bank_id = (int)($data['bank_soal_id'] ?? 0);
            $judul = trim($data['judul'] ?? '');
            $durasi = (int)($data['durasi_menit'] ?? 60);
            $kelasArr = $data['kelas'] ?? []; // Array of string

            if (!$bank_id || empty($judul)) {
                throw new Exception('Bank soal dan judul wajib diisi', 400);
            }
            if (empty($kelasArr) || !is_array($kelasArr)) {
                throw new Exception('Pilih minimal satu kelas peserta', 400);
            }

            // Get bank jenis
            $stmtBank = db()->prepare("SELECT jenis FROM exam_bank_soal WHERE id = ?");
            $stmtBank->execute([$bank_id]);
            $jenis = $stmtBank->fetchColumn();
            if (!$jenis) throw new Exception('Bank soal tidak ditemukan', 404);

            try {
                db()->beginTransaction();

                $stmt = db()->prepare("
                    INSERT INTO exam_ujian (judul, bank_soal_id, jenis, durasi_menit, acak_soal, acak_opsi, tampil_nilai, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
                ");
                $stmt->execute([
                    $judul,
                    $bank_id,
                    $jenis,
                    $durasi,
                    isset($data['acak_soal']) && $data['acak_soal'] ? 1 : 0,
                    isset($data['acak_opsi']) && $data['acak_opsi'] ? 1 : 0,
                    isset($data['tampil_nilai']) && $data['tampil_nilai'] ? 1 : 0,
                    $user['user_id']
                ]);
                
                $ujian_id = db()->lastInsertId();

                // Insert classes
                $stmtKelas = db()->prepare("INSERT INTO exam_ujian_kelas (ujian_id, kelas) VALUES (?, ?)");
                foreach ($kelasArr as $k) {
                    $stmtKelas->execute([$ujian_id, trim($k)]);
                }

                db()->commit();
                json_response(201, true, 'Ujian berhasil dibuat', ['id' => $ujian_id]);
            } catch (Exception $e) {
                db()->rollBack();
                throw $e;
            }
            break;

        case 'update':
            $user = exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            
            $id = (int)($data['id'] ?? 0);
            $judul = trim($data['judul'] ?? '');
            $durasi = (int)($data['durasi_menit'] ?? 60);
            $kelasArr = $data['kelas'] ?? [];

            if (!$id || empty($judul)) throw new Exception('Data tidak valid', 400);
            if (empty($kelasArr) || !is_array($kelasArr)) {
                throw new Exception('Pilih minimal satu kelas peserta', 400);
            }

            // Cek status
            $status = db()->query("SELECT status FROM exam_ujian WHERE id = $id")->fetchColumn();
            if ($status === 'aktif') {
                throw new Exception('Tidak bisa mengedit ujian yang sedang aktif. Nonaktifkan terlebih dahulu.', 400);
            }

            try {
                db()->beginTransaction();

                $stmt = db()->prepare("
                    UPDATE exam_ujian SET 
                        judul = ?, durasi_menit = ?, acak_soal = ?, acak_opsi = ?, tampil_nilai = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $judul,
                    $durasi,
                    isset($data['acak_soal']) && $data['acak_soal'] ? 1 : 0,
                    isset($data['acak_opsi']) && $data['acak_opsi'] ? 1 : 0,
                    isset($data['tampil_nilai']) && $data['tampil_nilai'] ? 1 : 0,
                    $id
                ]);

                // Update classes: delete old, insert new
                db()->prepare("DELETE FROM exam_ujian_kelas WHERE ujian_id = ?")->execute([$id]);
                $stmtKelas = db()->prepare("INSERT INTO exam_ujian_kelas (ujian_id, kelas) VALUES (?, ?)");
                foreach ($kelasArr as $k) {
                    $stmtKelas->execute([$id, trim($k)]);
                }

                db()->commit();
                json_response(200, true, 'Ujian berhasil diperbarui');
            } catch (Exception $e) {
                db()->rollBack();
                throw $e;
            }
            break;

        case 'delete':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);

            // Cek apakah sudah ada yang mengerjakan
            $check = db()->prepare("SELECT COUNT(*) FROM exam_sesi WHERE ujian_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                throw new Exception('Tidak bisa menghapus ujian karena sudah ada siswa yang mengerjakan/menyelesaikan ujian ini.', 400);
            }

            try {
                db()->beginTransaction();
                db()->prepare("DELETE FROM exam_ujian_kelas WHERE ujian_id = ?")->execute([$id]);
                db()->prepare("DELETE FROM exam_ujian WHERE id = ?")->execute([$id]);
                db()->commit();
                json_response(200, true, 'Ujian berhasil dihapus');
            } catch (Exception $e) {
                db()->rollBack();
                throw $e;
            }
            break;

        // ==========================================
        // AKTIFASI & TOKEN
        // ==========================================
        case 'set_status':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            
            $id = (int)($data['id'] ?? 0);
            $status = $data['status'] ?? ''; // aktif, selesai, draft
            
            if (!$id || !in_array($status, ['aktif', 'selesai', 'draft'])) {
                throw new Exception('Status tidak valid', 400);
            }

            $updateData = [$status];
            $sql = "UPDATE exam_ujian SET status = ?";

            if ($status === 'aktif') {
                $token = exam_generate_token(6);
                $sql .= ", token = ?, tgl_mulai = NOW()";
                $updateData[] = $token;
            } elseif ($status === 'selesai') {
                $sql .= ", tgl_selesai = NOW()";
            }

            $sql .= " WHERE id = ?";
            $updateData[] = $id;

            $stmt = db()->prepare($sql);
            $stmt->execute($updateData);

            $msg = $status === 'aktif' ? "Ujian diaktifkan. TOKEN: $token" : "Ujian diset menjadi " . $status;
            json_response(200, true, $msg, ['token' => $token ?? null]);
            break;

        case 'refresh_token':
            exam_require_admin_or_guru();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            $data = get_input();
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);

            $token = exam_generate_token(6);
            $stmt = db()->prepare("UPDATE exam_ujian SET token = ? WHERE id = ? AND status = 'aktif'");
            $stmt->execute([$token, $id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Ujian belum aktif atau tidak ditemukan.', 400);
            }

            json_response(200, true, "Token berhasil diperbarui", ['token' => $token]);
            break;

        // ==========================================
        // OPTIONS DATA (Untuk Select)
        // ==========================================
        case 'options_bank':
            $user = exam_require_admin_or_guru();
            $where = "status = 1";
            $params = [];
            if ($user['is_guru'] && !$user['is_admin']) {
                $where .= " AND created_by = ?";
                $params[] = $user['user_id'];
            }
            $stmt = db()->prepare("SELECT id, judul, jenis FROM exam_bank_soal WHERE $where ORDER BY created_at DESC");
            $stmt->execute($params);
            json_response(200, true, '', $stmt->fetchAll());
            break;

        default:
            throw new Exception('Action tidak valid: ' . $action, 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) $code = 500;
    json_response($code, false, $e->getMessage());
}
