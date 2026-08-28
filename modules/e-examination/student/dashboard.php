<?php
/**
 * E-Examination — Student Dashboard
 */
require_once __DIR__ . '/../../../api/config.php';

session_start();
if (!isset($_SESSION['exam_student'])) {
    header("Location: login.php");
    exit;
}

$student = $_SESSION['exam_student'];
$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');

// Get active exams for student's class
$stmt = db()->prepare("
    SELECT u.*, b.judul as nama_bank_soal, b.jenis
    FROM exam_ujian u
    JOIN exam_bank_soal b ON u.bank_soal_id = b.id
    JOIN exam_ujian_kelas uk ON uk.ujian_id = u.id
    WHERE u.status = 'aktif' AND uk.kelas = ?
    ORDER BY u.created_at DESC
");
$stmt->execute([$student['kelas']]);
$activeExams = $stmt->fetchAll();

// Get past/finished exams
$stmtPast = db()->prepare("
    SELECT s.*, u.judul as nama_ujian, b.judul as nama_bank_soal, b.jenis, b.id as bank_soal_id
    FROM exam_sesi s
    JOIN exam_ujian u ON s.ujian_id = u.id
    JOIN exam_bank_soal b ON u.bank_soal_id = b.id
    WHERE s.student_id = ? AND s.status IN ('selesai', 'dihentikan')
    ORDER BY s.waktu_selesai DESC
");
$stmtPast->execute([$student['id']]);
$pastExams = $stmtPast->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Ujian — <?php echo htmlspecialchars($school_name); ?></title>
    
    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <?php endif; ?>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/examination.css">

    <!-- KaTeX for Math Equations -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (window.renderMathInElement) {
                renderMathInElement(document.body, {
                    delimiters: [
                        {left: "$$", right: "$$", display: true},
                        {left: "$", right: "$", display: false},
                        {left: "\\(", right: "\\)", display: false},
                        {left: "\\[", right: "\\]", display: true}
                    ]
                });
            }
        });
    </script>

    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        .std-nav { background: #2563EB; color: white; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .std-nav h1 { margin: 0; font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .std-user { display: flex; align-items: center; gap: 12px; }
        .std-user-info { text-align: right; }
        .std-user-name { font-weight: 600; font-size: 14px; }
        .std-user-kelas { font-size: 12px; color: #bfdbfe; }
        .std-logout { background: rgba(255,255,255,0.2); border: none; color: white; padding: 8px; border-radius: 8px; cursor: pointer; transition: 0.2s; }
        .std-logout:hover { background: rgba(255,255,255,0.3); }
        
        .std-container { max-width: 1000px; margin: 32px auto; padding: 0 20px; }
        .std-section { margin-bottom: 40px; }
        .std-section-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        
        .exam-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .exam-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; flex-direction: column; }
        .exam-card-title { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0 0 8px 0; }
        .exam-card-desc { font-size: 13px; color: #64748b; margin: 0 0 16px 0; }
        .exam-meta { display: flex; gap: 12px; font-size: 13px; color: #475569; margin-bottom: 20px; }
        .exam-meta span { display: inline-flex; align-items: center; gap: 4px; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; }
        .btn-start { background: #2563EB; color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; text-align: center; width: 100%; transition: 0.2s; }
        .btn-start:hover { background: #1D4ED8; }
        
        .history-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .history-table th, .history-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .history-table th { background: #f8fafc; font-weight: 600; color: #475569; }
        .score-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-weight: 700; background: #dcfce7; color: #166534; }
    </style>
</head>
<body>

    <nav class="std-nav">
        <h1>
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Dashboard CBT
        </h1>
        <div class="std-user">
            <div class="std-user-info">
                <div class="std-user-name"><?php echo htmlspecialchars($student['nama']); ?></div>
                <div class="std-user-kelas">Kelas <?php echo htmlspecialchars($student['kelas']); ?></div>
            </div>
            <button class="std-logout" onclick="logout()" title="Logout">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </div>
    </nav>

    <div class="std-container">
        
        <div class="std-section">
            <h2 class="std-section-title">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#2563EB" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Ujian Tersedia
            </h2>
            
            <?php if (empty($activeExams)): ?>
                <div style="background:white;padding:32px;text-align:center;border-radius:12px;color:#64748b;border:1px dashed #cbd5e1;">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.5;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <p>Tidak ada ujian yang sedang aktif untuk kelas Anda saat ini.</p>
                </div>
            <?php else: ?>
                <div class="exam-grid">
                    <?php foreach ($activeExams as $ex): ?>
                        <div class="exam-card">
                            <h3 class="exam-card-title"><?php echo htmlspecialchars($ex['judul']); ?></h3>
                            <p class="exam-card-desc"><?php echo htmlspecialchars($ex['nama_bank_soal']); ?></p>
                            <div class="exam-meta">
                                <span><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?php echo $ex['durasi_menit']; ?> Menit</span>
                                <span><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> <?php echo $ex['jenis'] == 'psikologi' ? 'Psikotes' : 'Penilaian'; ?></span>
                            </div>
                            <div style="margin-top:auto;">
                                <button class="btn-start" onclick="showTokenModal(<?php echo $ex['id']; ?>)">Mulai Ujian</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($pastExams)): ?>
        <div class="std-section">
            <h2 class="std-section-title">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#64748b" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Riwayat Ujian
            </h2>
            <div style="overflow-x:auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Ujian</th>
                            <th>Status</th>
                            <th>Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pastExams as $p): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($p['waktu_selesai'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($p['nama_ujian']); ?></strong><br>
                                <span style="font-size:12px;color:#64748b;"><?php echo htmlspecialchars($p['nama_bank_soal']); ?></span>
                            </td>
                            <td>
                                <?php if($p['status'] == 'dihentikan'): ?>
                                    <span style="color:#dc2626;font-weight:600;">Dihentikan (Pelanggaran)</span>
                                <?php else: ?>
                                    <span style="color:#059669;font-weight:600;">Selesai</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['skor'] !== null): ?>
                                    <?php if ($p['jenis'] === 'psikologi'): 
                                        $stmtH = db()->prepare("
                                            SELECT kode_hasil FROM exam_psikologi_hasil 
                                            WHERE bank_soal_id = ? AND ? >= rentang_min AND ? <= rentang_max 
                                            LIMIT 1
                                        ");
                                        $stmtH->execute([$p['bank_soal_id'], $p['skor'], $p['skor']]);
                                        $outcome = $stmtH->fetchColumn() ?: 'Tidak terdefinisi';
                                    ?>
                                        <span class="score-badge" style="background:#f3e8ff;color:#6b21a8;"><?php echo htmlspecialchars($outcome); ?></span>
                                    <?php else: ?>
                                        <span class="score-badge"><?php echo round($p['skor'], 2); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">Tidak ditampilkan / Menunggu AI</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Modal Container -->
    <div id="modalContainer"></div>
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="<?php echo BASE_URL; ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/modal.js"></script>
    <script>
        function logout() {
            EModal.confirm({
                title: 'Logout',
                message: 'Yakin ingin keluar dari portal CBT?',
                type: 'danger',
                onConfirm: () => {
                    $.post('../api/pengerjaan.php?action=logout', function(r) {
                        window.location.href = 'login.php';
                    });
                }
            });
        }

        function showTokenModal(ujianId, defaultToken = '') {
            EModal.form({
                title: 'Masukkan Token & Kartu Ujian',
                form: `
                    <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px;">
                        Silakan masukkan token ujian beserta kredensial dari E-xam Card Anda.
                    </p>
                    <div class="form-group">
                        <label class="form-label">TOKEN UJIAN</label>
                        <input type="text" id="fToken" class="form-input" style="font-family:monospace;font-size:20px;text-align:center;letter-spacing:4px;text-transform:uppercase;" maxlength="6" autofocus placeholder="TOKEN" value="${defaultToken}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">USERNAME CARD</label>
                        <input type="text" id="fUsernameCard" class="form-input" placeholder="Username E-xam Card" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label">PASSWORD CARD</label>
                        <input type="password" id="fPasswordCard" class="form-input" placeholder="Password E-xam Card" autocomplete="off">
                    </div>
                `,
                confirmText: 'Verifikasi & Mulai',
                onConfirm: () => {
                    const token = $('#fToken').val().trim().toUpperCase();
                    const username_card = $('#fUsernameCard').val().trim();
                    const password_card = $('#fPasswordCard').val().trim();
                    
                    if (!token) { EModal.toast({type:'error', title:'Token wajib diisi'}); return false; }
                    if (!username_card || !password_card) { EModal.toast({type:'error', title:'Kredensial E-xam Card wajib diisi'}); return false; }
                    
                    const loader = EModal.loading('Memverifikasi kredensial...');
                    $.ajax({
                        url: '../api/pengerjaan.php?action=start',
                        method: 'POST',
                        data: JSON.stringify({ 
                            ujian_id: ujianId, 
                            token: token,
                            username_card: username_card,
                            password_card: password_card
                        }),
                        contentType: 'application/json',
                        success: function(r) {
                            EModal.close(loader);
                            if (r.success) {
                                window.location.href = 'exam.php?session_id=' + r.data.session_id;
                            } else {
                                EModal.alert('Gagal', r.message);
                            }
                        },
                        error: function(xhr) {
                            EModal.close(loader);
                            let msg = 'Terjadi kesalahan';
                            try { msg = xhr.responseJSON.message || msg; } catch(e){}
                            EModal.alert('Gagal', msg);
                        }
                    });
                }
            });
        }

        // Auto-trigger if URL has exam_id and token
        $(document).ready(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const examId = urlParams.get('exam_id');
            const token = urlParams.get('token');
            if (examId && token) {
                setTimeout(() => showTokenModal(examId, token), 500);
            }
        });
    </script>
</body>
</html>
