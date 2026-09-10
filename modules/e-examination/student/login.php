<?php
/**
 * E-Examination — Student Login
 * Mendukung 2 metode: Akun Kartu Ujian (Examcard) & NIS + Tanggal Lahir
 */
require_once __DIR__ . '/../../../api/config.php';

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');

// Redirect jika sudah login
session_start();
if (isset($_SESSION['exam_student'])) {
    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: " . BASE_URL . "modules/e-examination/student/dashboard.php" . $qs);
    exit;
}

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login CBT Siswa — <?php echo htmlspecialchars($school_name); ?></title>
    
    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css">
    
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #eff6ff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: #f1f5f9;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 24px 24px;
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px 16px;
        }
        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            border-radius: 20px;
            box-shadow: 0 20px 35px -10px rgba(15, 23, 42, 0.1), 0 8px 16px -6px rgba(15, 23, 42, 0.05);
            padding: 36px 28px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: linear-gradient(90deg, #2563EB, #38BDF8);
        }
        .login-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo-box {
            width: 64px;
            height: 64px;
            background: #eff6ff;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            border: 1px solid #dbeafe;
        }
        .logo-box svg {
            width: 32px;
            height: 32px;
            color: #2563EB;
        }
        .login-header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            margin: 0 0 4px 0;
            letter-spacing: -0.5px;
        }
        .login-header p {
            color: var(--text-muted);
            font-size: 13px;
            margin: 0;
        }

        /* Session Badge if from Share Link */
        .session-badge {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .session-badge strong {
            font-family: monospace;
            letter-spacing: 1px;
            color: #047857;
            background: #d1fae5;
            padding: 2px 6px;
            border-radius: 4px;
        }

        /* Dual Choice Tabs */
        .tab-wrapper {
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            display: flex;
            gap: 4px;
            margin-bottom: 22px;
        }
        .tab-btn {
            flex: 1;
            padding: 10px 8px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            border-radius: 9px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .tab-btn svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
        .tab-btn.active {
            background: #ffffff;
            color: var(--primary);
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        .form-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text-main);
            background: #ffffff;
            transition: all 0.2s ease;
            outline: none;
        }
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .input-hint {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 5px;
            line-height: 1.4;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password:hover {
            color: #475569;
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
        }
        .btn-login:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 12px rgba(37,99,235,0.25);
        }
        .btn-login:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
            display: none;
            line-height: 1.4;
        }
        .alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            display: block;
        }

        .footer-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #f1f5f9;
        }
        .footer-link a {
            color: var(--text-muted);
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }
        .footer-link a:hover {
            color: var(--primary);
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2.5px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <div class="logo-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <h1>CBT Siswa</h1>
            <p><?php echo htmlspecialchars($school_name); ?></p>
        </div>

        <?php if ($token): ?>
        <div class="session-badge">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <span>Sesi Terhubung &bull; Token: <strong><?php echo htmlspecialchars($token); ?></strong></span>
        </div>
        <?php endif; ?>

        <!-- Choice Tabs: Examcard vs NIS & Tanggal Lahir -->
        <div class="tab-wrapper">
            <button type="button" class="tab-btn active" id="tabExamcard" onclick="switchLoginMode('examcard')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="8" x2="17" y2="8"/><line x1="7" y1="12" x2="12" y2="12"/><line x1="7" y1="16" x2="9" y2="16"/></svg>
                Kartu Ujian
            </button>
            <button type="button" class="tab-btn" id="tabNisDob" onclick="switchLoginMode('nis_dob')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                NIS & Tgl Lahir
            </button>
        </div>

        <form id="loginForm">
            <div id="errorAlert" class="alert error" style="display:none;"></div>
            <input type="hidden" id="loginType" value="examcard">

            <!-- MODE 1: EXAMCARD -->
            <div id="sectionExamcard">
                <div class="form-group">
                    <label class="form-label">Username Kartu Ujian</label>
                    <div class="input-group">
                        <input type="text" class="form-input" id="cardUsername" placeholder="Contoh: 123456" autocomplete="username" autofocus>
                    </div>
                    <div class="input-hint">Masukkan username / nomor akun pada Kartu Peserta Ujian.</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password Kartu Ujian</label>
                    <div class="input-group">
                        <input type="password" class="form-input" id="cardPassword" placeholder="Masukkan password kartu" autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePassVisibility('cardPassword')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="input-hint">Password tertera pada Kartu Peserta Ujian (E-xam Card).</div>
                </div>
            </div>

            <!-- MODE 2: NIS & TANGGAL LAHIR -->
            <div id="sectionNisDob" style="display:none;">
                <div class="form-group">
                    <label class="form-label">Nomor Induk Siswa (NIS)</label>
                    <div class="input-group">
                        <input type="text" class="form-input" id="nisUsername" placeholder="Masukkan NIS Siswa">
                    </div>
                    <div class="input-hint">Nomor Induk Siswa yang terdaftar di sekolah.</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tanggal Lahir</label>
                    <div class="input-group">
                        <input type="text" class="form-input" id="dobPassword" placeholder="Contoh: 12052006 (DDMMYYYY)">
                    </div>
                    <div class="input-hint">Format 8 digit: HariBulanTahun (contoh: 12052006).</div>
                </div>
            </div>
            
            <button type="submit" class="btn-login" id="btnSubmit">
                <span>Masuk Ujian</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>

        <div class="footer-link">
            <a href="<?php echo BASE_URL; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                Kembali ke Halaman Utama Portal
            </a>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <script>
        let currentMode = 'examcard';

        function switchLoginMode(mode) {
            currentMode = mode;
            $('#loginType').val(mode);
            $('#errorAlert').hide();

            if (mode === 'examcard') {
                $('#tabExamcard').addClass('active');
                $('#tabNisDob').removeClass('active');
                $('#sectionExamcard').show();
                $('#sectionNisDob').hide();
                $('#cardUsername').focus();
            } else {
                $('#tabNisDob').addClass('active');
                $('#tabExamcard').removeClass('active');
                $('#sectionExamcard').hide();
                $('#sectionNisDob').show();
                $('#nisUsername').focus();
            }
        }

        function togglePassVisibility(inputId) {
            const $inp = $('#' + inputId);
            const type = $inp.attr('type') === 'password' ? 'text' : 'password';
            $inp.attr('type', type);
        }

        $('#loginForm').on('submit', function(e) {
            e.preventDefault();
            
            const mode = $('#loginType').val();
            let payload = { login_type: mode };

            if (mode === 'examcard') {
                const username = $('#cardUsername').val().trim();
                const password = $('#cardPassword').val().trim();
                if (!username || !password) {
                    $('#errorAlert').text('Harap isi Username dan Password Kartu Ujian').show();
                    return;
                }
                payload.username = username;
                payload.password = password;
            } else {
                const nis = $('#nisUsername').val().trim();
                const dob = $('#dobPassword').val().trim();
                if (!nis || !dob) {
                    $('#errorAlert').text('Harap isi NIS dan Tanggal Lahir').show();
                    return;
                }
                payload.nis = nis;
                payload.dob = dob;
            }

            const $btn = $('#btnSubmit');
            const $alert = $('#errorAlert');
            
            $btn.prop('disabled', true).html('<div class="spinner"></div> <span>Sedang Masuk...</span>');
            $alert.hide();

            // Kumpulkan query parameters dari URL saat ini (e.g. ?exam_id=...&token=...)
            const urlParams = new URLSearchParams(window.location.search);
            const queryString = urlParams.toString() ? '?' + urlParams.toString() : '';

            $.ajax({
                url: '../api/pengerjaan.php?action=login',
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                success: function(r) {
                    if (r.success) {
                        window.location.href = 'dashboard.php' + queryString;
                    } else {
                        $alert.text(r.message || 'Login gagal').show();
                        $btn.prop('disabled', false).html('<span>Masuk Ujian</span> <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>');
                    }
                },
                error: function(xhr) {
                    let msg = 'Terjadi kesalahan pada server';
                    try { msg = xhr.responseJSON.message || msg; } catch(e){}
                    $alert.text(msg).show();
                    $btn.prop('disabled', false).html('<span>Masuk Ujian</span> <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>');
                }
            });
        });
    </script>
</body>
</html>
