<?php
/**
 * E-Examination — Student Login
 */
require_once __DIR__ . '/../../api/config.php';

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');

// Redirect jika sudah login
session_start();
if (isset($_SESSION['exam_student'])) {
    header("Location: " . BASE_URL . "modules/e-examination/student/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login CBT — <?php echo htmlspecialchars($school_name); ?></title>
    
    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <?php endif; ?>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css">
    
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 400px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            padding: 40px 32px;
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: linear-gradient(90deg, #2563EB, #3B82F6);
        }
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-header svg {
            width: 56px;
            height: 56px;
            color: #2563EB;
            margin-bottom: 16px;
            background: rgba(37,99,235,0.1);
            padding: 12px;
            border-radius: 16px;
        }
        .login-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 8px 0;
        }
        .login-header p {
            color: #64748b;
            font-size: 14px;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            color: #0f172a;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .form-input:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #2563EB;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-login:hover {
            background: #1D4ED8;
        }
        .btn-login:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            display: none;
        }
        .alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            display: block;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <h1>CBT Siswa</h1>
            <p><?php echo htmlspecialchars($school_name); ?></p>
        </div>

        <form id="loginForm">
            <div id="errorAlert" class="alert error" style="display:none;"></div>
            
            <div class="form-group">
                <label class="form-label">NIS (Username)</label>
                <input type="text" class="form-input" id="username" placeholder="Masukkan NIS" required autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-input" id="password" placeholder="Masukkan Password (tgl lahir ddmmyyyy)" required>
            </div>
            
            <button type="submit" class="btn-login" id="btnSubmit">
                Masuk Ujian
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>

        <div style="text-align:center; margin-top:24px;">
            <a href="<?php echo BASE_URL; ?>" style="color:#64748b; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                Kembali ke Portal
            </a>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <script>
        $('#loginForm').on('submit', function(e) {
            e.preventDefault();
            const username = $('#username').val().trim();
            const password = $('#password').val().trim();
            
            if(!username || !password) return;

            const $btn = $('#btnSubmit');
            const $alert = $('#errorAlert');
            
            $btn.prop('disabled', true).html('Sedang masuk...');
            $alert.hide();

            $.ajax({
                url: '../api/pengerjaan.php?action=login',
                method: 'POST',
                data: JSON.stringify({ username, password }),
                contentType: 'application/json',
                success: function(r) {
                    if (r.success) {
                        window.location.href = 'dashboard.php';
                    } else {
                        $alert.text(r.message || 'Login gagal').show();
                        $btn.prop('disabled', false).html('Masuk Ujian <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>');
                    }
                },
                error: function(xhr) {
                    let msg = 'Terjadi kesalahan pada server';
                    try { msg = xhr.responseJSON.message || msg; } catch(e){}
                    $alert.text(msg).show();
                    $btn.prop('disabled', false).html('Masuk Ujian <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>');
                }
            });
        });
    </script>
</body>
</html>
