<?php
/**
 * E-Examination — API Proktor (Live Monitoring, Reset Login, Generate Link)
 */
require_once __DIR__ . '/config_exam.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ==========================================
        // LIVE MONITORING
        // ==========================================
        case 'monitoring':
            $user = exam_require_proktor();

            $ujian_id = (int)($_GET['ujian_id'] ?? 0);
            $kelas = trim($_GET['kelas'] ?? '');
            $search = trim($_GET['search'] ?? '');

            // 1. Data Siswa Sedang Mengerjakan
            $whereSesi = ["es.status = 'mengerjakan'"];
            $paramSesi = [];

            if ($ujian_id > 0) {
                $whereSesi[] = "es.ujian_id = ?";
                $paramSesi[] = $ujian_id;
            }
            if ($kelas !== '') {
                $whereSesi[] = "s.kelas = ?";
                $paramSesi[] = $kelas;
            }
            if ($search !== '') {
                $whereSesi[] = "(s.nama LIKE ? OR s.nis LIKE ?)";
                $paramSesi[] = "%$search%";
                $paramSesi[] = "%$search%";
            }
            $whereSesiSql = implode(' AND ', $whereSesi);

            $stmtMengerjakan = db()->prepare("
                SELECT es.id as sesi_id, es.ujian_id, es.student_id, es.waktu_mulai, es.status as sesi_status,
                       es.pelanggaran, es.ip_address,
                       s.nis, s.nama as nama_siswa, s.kelas,
                       u.judul as nama_ujian, u.durasi_menit, u.token as token_ujian,
                       COALESCE(el.is_locked, 0) as is_locked,
                       el.lock_reason, el.last_heartbeat, el.status as login_status,
                       (SELECT COUNT(*) FROM exam_soal WHERE bank_soal_id = u.bank_soal_id) as total_soal,
                       (SELECT COUNT(*) FROM exam_jawaban WHERE sesi_id = es.id AND (jawaban IS NOT NULL AND jawaban != '' OR jawaban_voice IS NOT NULL)) as total_terjawab,
                       (SELECT COUNT(*) FROM exam_jawaban WHERE sesi_id = es.id AND is_ragu = 1) as total_ragu
                FROM exam_sesi es
                JOIN students s ON es.student_id = s.id
                JOIN exam_ujian u ON es.ujian_id = u.id
                LEFT JOIN exam_student_login el ON el.student_id = s.id
                WHERE $whereSesiSql
                ORDER BY es.waktu_mulai DESC
            ");
            $stmtMengerjakan->execute($paramSesi);
            $listMengerjakan = $stmtMengerjakan->fetchAll(PDO::FETCH_ASSOC);

            // Hitung sisa waktu untuk setiap pengerjaan
            foreach ($listMengerjakan as &$m) {
                $startTime = strtotime($m['waktu_mulai']);
                $endTime = $startTime + ($m['durasi_menit'] * 60);
                $remaining = max(0, $endTime - time());
                $m['sisa_detik'] = $remaining;
                $m['sisa_menit'] = ceil($remaining / 60);
            }

            // 2. Data Siswa Sedang Login / Aktif di CBT
            $whereLogin = ["el.status IN ('logged_in', 'mengerjakan')"];
            $paramLogin = [];
            if ($kelas !== '') {
                $whereLogin[] = "s.kelas = ?";
                $paramLogin[] = $kelas;
            }
            if ($search !== '') {
                $whereLogin[] = "(s.nama LIKE ? OR s.nis LIKE ?)";
                $paramLogin[] = "%$search%";
                $paramLogin[] = "%$search%";
            }
            $whereLoginSql = implode(' AND ', $whereLogin);

            $stmtLogin = db()->prepare("
                SELECT el.id as login_id, el.student_id, el.status as login_status, el.is_locked, el.lock_reason,
                       el.ip_address, el.user_agent, el.login_at, el.last_heartbeat, el.ujian_id,
                       s.nis, s.nama as nama_siswa, s.kelas,
                       u.judul as nama_ujian
                FROM exam_student_login el
                JOIN students s ON el.student_id = s.id
                LEFT JOIN exam_ujian u ON el.ujian_id = u.id
                WHERE $whereLoginSql
                ORDER BY el.last_heartbeat DESC, el.login_at DESC
            ");
            $stmtLogin->execute($paramLogin);
            $listLogin = $stmtLogin->fetchAll(PDO::FETCH_ASSOC);

            // 3. Data Siswa Terkunci (Butuh Reset Proktor)
            $whereLocked = ["el.is_locked = 1"];
            $paramLocked = [];
            if ($kelas !== '') {
                $whereLocked[] = "s.kelas = ?";
                $paramLocked[] = $kelas;
            }
            if ($search !== '') {
                $whereLocked[] = "(s.nama LIKE ? OR s.nis LIKE ?)";
                $paramLocked[] = "%$search%";
                $paramLocked[] = "%$search%";
            }
            $whereLockedSql = implode(' AND ', $whereLocked);

            $stmtLocked = db()->prepare("
                SELECT el.id as login_id, el.student_id, el.status as login_status, el.is_locked, el.lock_reason,
                       el.ip_address, el.updated_at as waktu_terkunci, el.ujian_id,
                       s.nis, s.nama as nama_siswa, s.kelas,
                       u.judul as nama_ujian,
                       es.id as sesi_id
                FROM exam_student_login el
                JOIN students s ON el.student_id = s.id
                LEFT JOIN exam_ujian u ON el.ujian_id = u.id
                LEFT JOIN exam_sesi es ON (es.student_id = s.id AND es.ujian_id = el.ujian_id AND es.status = 'mengerjakan')
                WHERE $whereLockedSql
                ORDER BY el.updated_at DESC
            ");
            $stmtLocked->execute($paramLocked);
            $listLocked = $stmtLocked->fetchAll(PDO::FETCH_ASSOC);

            // 4. Data Ujian Aktif untuk filter & generator
            $stmtExams = db()->query("
                SELECT u.id, u.judul, u.token, u.durasi_menit, u.status,
                       (SELECT GROUP_CONCAT(kelas SEPARATOR ', ') FROM exam_ujian_kelas WHERE ujian_id = u.id) as kelas_list
                FROM exam_ujian u
                WHERE u.status = 'aktif'
                ORDER BY u.created_at DESC
            ");
            $activeExams = $stmtExams->fetchAll(PDO::FETCH_ASSOC);

            // 5. Data Kelas untuk filter
            $classes = exam_get_classes();

            // Stats summary
            $stats = [
                'total_mengerjakan' => count($listMengerjakan),
                'total_login'       => count($listLogin),
                'total_terkunci'    => count($listLocked),
            ];

            json_response(200, true, 'Data monitoring live berhasil dimuat', [
                'stats'            => $stats,
                'mengerjakan'      => $listMengerjakan,
                'login'            => $listLogin,
                'terkunci'         => $listLocked,
                'active_exams'     => $activeExams,
                'classes'          => $classes,
                'server_time'      => date('Y-m-d H:i:s')
            ]);
            break;

        // ==========================================
        // RESET LOGIN SISWA (SATUAN / MASSAL)
        // ==========================================
        case 'reset_login':
            exam_require_proktor();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);

            $data = get_input();
            $studentId = (int)($data['student_id'] ?? 0);
            $studentIds = $data['student_ids'] ?? [];

            if ($studentId > 0) {
                $studentIds = [$studentId];
            }

            if (empty($studentIds) || !is_array($studentIds)) {
                throw new Exception('Pilih siswa yang ingin di-reset loginnya.', 400);
            }

            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

            // Unlock student login and reset status to logged_out so they can log in again
            $stmt = db()->prepare("
                UPDATE exam_student_login 
                SET is_locked = 0, 
                    status = 'logged_out', 
                    lock_reason = NULL, 
                    updated_at = NOW() 
                WHERE student_id IN ($placeholders)
            ");
            $stmt->execute($studentIds);

            // Also check student names for feedback message
            $stmtNames = db()->prepare("SELECT nama FROM students WHERE id IN ($placeholders) LIMIT 3");
            $stmtNames->execute($studentIds);
            $names = $stmtNames->fetchAll(PDO::FETCH_COLUMN);
            $nameStr = implode(', ', $names);
            if (count($studentIds) > 3) $nameStr .= ' dan ' . (count($studentIds) - 3) . ' siswa lainnya';

            json_response(200, true, "Login berhasil di-reset untuk: {$nameStr}. Siswa sekarang dapat login kembali.");
            break;

        // ==========================================
        // RESET SEMUA LOGIN YANG TERKUNCI
        // ==========================================
        case 'reset_all_locked':
            exam_require_proktor();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);

            $data = get_input();
            $kelas = trim($data['kelas'] ?? '');
            $ujian_id = (int)($data['ujian_id'] ?? 0);

            $where = ["el.is_locked = 1"];
            $params = [];

            if ($kelas !== '') {
                $where[] = "s.kelas = ?";
                $params[] = $kelas;
            }
            if ($ujian_id > 0) {
                $where[] = "el.ujian_id = ?";
                $params[] = $ujian_id;
            }
            $whereSql = implode(' AND ', $where);

            // Fetch target student IDs
            $stmtFind = db()->prepare("
                SELECT el.student_id 
                FROM exam_student_login el 
                JOIN students s ON el.student_id = s.id 
                WHERE $whereSql
            ");
            $stmtFind->execute($params);
            $targetIds = $stmtFind->fetchAll(PDO::FETCH_COLUMN);

            if (empty($targetIds)) {
                json_response(200, true, 'Tidak ada akun siswa terkunci yang perlu di-reset.');
            }

            $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
            $stmtReset = db()->prepare("
                UPDATE exam_student_login 
                SET is_locked = 0, status = 'logged_out', lock_reason = NULL, updated_at = NOW() 
                WHERE student_id IN ($placeholders)
            ");
            $stmtReset->execute($targetIds);

            json_response(200, true, 'Berhasil mereset ' . count($targetIds) . ' akun siswa yang terkunci.');
            break;

        // ==========================================
        // GENERATE LINK UJIAN & SALIN INFORMASI
        // ==========================================
        case 'generate_link':
            exam_require_proktor();

            $ujian_id = (int)($_GET['ujian_id'] ?? 0);
            $loginUrl = BASE_URL . 'modules/e-examination/student/login.php';

            $examInfo = null;
            if ($ujian_id > 0) {
                $stmt = db()->prepare("
                    SELECT u.*, 
                           (SELECT GROUP_CONCAT(kelas SEPARATOR ', ') FROM exam_ujian_kelas WHERE ujian_id = u.id) as kelas_peserta
                    FROM exam_ujian u 
                    WHERE u.id = ?
                ");
                $stmt->execute([$ujian_id]);
                $examInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $shareText = "📋 *INFORMASI UJIAN CBT*\n";
            if ($examInfo) {
                $shareText .= "📌 *Ujian:* " . $examInfo['judul'] . "\n";
                $shareText .= "🔑 *Token Ujian:* " . ($examInfo['token'] ?: 'DRAFT / BELUM DIAKTIFKAN') . "\n";
                $shareText .= "⏱️ *Durasi:* " . $examInfo['durasi_menit'] . " Menit\n";
                $shareText .= "👥 *Kelas:* " . ($examInfo['kelas_peserta'] ?: 'Semua') . "\n";
            }
            $shareText .= "🔗 *Link Pengerjaan:* " . $loginUrl . "\n\n";
            $shareText .= "⚠️ *Catatan:* Masuk menggunakan NIS dan Password akun siswa. Jangan keluar aplikasi selama ujian berlangsung.";

            json_response(200, true, 'Link ujian berhasil digenerate', [
                'login_url'  => $loginUrl,
                'exam_info'  => $examInfo,
                'share_text' => $shareText
            ]);
            break;

        // ==========================================
        // FORCE FINISH SESI SISWA
        // ==========================================
        case 'force_finish':
            exam_require_proktor();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);

            $data = get_input();
            $sesi_id = (int)($data['sesi_id'] ?? 0);
            if (!$sesi_id) throw new Exception('Sesi ID tidak valid', 400);

            // Fetch session info
            $stmtS = db()->prepare("SELECT student_id, ujian_id, status FROM exam_sesi WHERE id = ?");
            $stmtS->execute([$sesi_id]);
            $sesi = $stmtS->fetch(PDO::FETCH_ASSOC);
            if (!$sesi) throw new Exception('Sesi ujian tidak ditemukan', 404);

            db()->beginTransaction();
            // Update exam_sesi
            $stmtUp = db()->prepare("UPDATE exam_sesi SET status = 'selesai', waktu_selesai = NOW() WHERE id = ?");
            $stmtUp->execute([$sesi_id]);

            // Update exam_student_login
            $stmtUpLog = db()->prepare("UPDATE exam_student_login SET status = 'selesai', is_locked = 0 WHERE student_id = ?");
            $stmtUpLog->execute([$sesi['student_id']]);
            db()->commit();

            json_response(200, true, 'Sesi ujian berhasil diselesaikan secara paksa.');
            break;

        default:
            throw new Exception('Action tidak valid', 400);
    }
} catch (Exception $e) {
    if (db()->inTransaction()) db()->rollBack();
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) $code = 500;
    json_response($code, false, $e->getMessage());
}
