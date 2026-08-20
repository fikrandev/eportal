<?php
/**
 * Student Login - E-Xam Card
 */
require_once __DIR__ . '/../../../api/config.php';
$school_name = get_setting('nama_sekolah', 'E-Portal Sekolah');
$school_icon = get_setting('icon_sekolah', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login Siswa - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f766e;
            --primary-dark: #0d9488;
            --bg: #f8fafc;
            --text: #1e293b;
            --text-muted: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .login-card { background: #fff; width: 100%; max-width: 400px; padding: 40px 30px; border-radius: 24px; box-shadow: 0 20px 50px rgba(15, 118, 110, 0.08); text-align: center; }
        .logo-box { width: 80px; height: 80px; background: #f0fdfa; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        .logo-box img { max-width: 50px; max-height: 50px; }
        .logo-box svg { width: 40px; height: 40px; color: var(--primary); }
        h1 { font-family: 'Outfit', sans-serif; font-size: 24px; font-weight: 800; margin-bottom: 8px; color: #0f172a; }
        p.subtitle { color: var(--text-muted); font-size: 14px; margin-bottom: 32px; }
        .form-group { text-align: left; margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #475569; margin-left: 4px; }
        .form-input { width: 100%; padding: 14px 18px; background: #f1f5f9; border: 2px solid transparent; border-radius: 14px; font-size: 15px; font-family: inherit; transition: all 0.2s; outline: none; }
        .form-input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.1); }
        .btn-login { width: 100%; padding: 16px; background: var(--primary); color: #fff; border: none; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-login:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(15, 118, 110, 0.2); }
        .btn-login:active { transform: translateY(0); }
        .footer { margin-top: 32px; font-size: 12px; color: var(--text-muted); }
        
        /* Modal Styles */
        .modal-overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
        .modal-card { background: #fff; width: 100%; max-width: 360px; border-radius: 24px; padding: 30px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: modalIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        @keyframes modalIn { from { opacity:0; transform: scale(0.9) translateY(20px); } to { opacity:1; transform: scale(1) translateY(0); } }
        .status-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .status-icon.oke { background: #ecfdf5; color: #10b981; }
        .status-icon.tangguh { background: #fff1f2; color: #f43f5e; }
        .modal-title { font-family: 'Outfit', sans-serif; font-size: 15px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .status-text { font-size: 28px; font-weight: 900; margin-bottom: 16px; font-family: 'Outfit', sans-serif; }
        .status-text.oke { color: #059669; }
        .status-text.tangguh { color: #e11d48; }
        .modal-desc { font-size: 14px; line-height: 1.6; color: #475569; margin-bottom: 24px; }
        .btn-modal { width: 100%; padding: 14px; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .btn-modal.oke { background: #10b981; color: #fff; }
        .btn-modal.oke:hover { background: #059669; }
        .btn-modal.tangguh { background: #e2e8f0; color: #1e293b; }
        .btn-modal.tangguh:hover { background: #cbd5e1; }

        #loader { display: none; margin-right: 8px; }
        .spinner { width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-box">
            <?php if ($school_icon): ?>
                <img src="<?php echo BASE_URL . $school_icon; ?>" alt="Logo">
            <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
            <?php endif; ?>
        </div>
        <h1>E-Xam Card</h1>
        <p class="subtitle">Cek status dan unduh kartu ujian Anda</p>

        <form id="loginForm">
            <div class="form-group">
                <label>Nomor Induk Siswa (NIS)</label>
                <input type="text" id="nis" class="form-input" placeholder="Masukkan NIS Anda" required>
            </div>
            <div class="form-group">
                <label>Password (Tanggal Lahir)</label>
                <input type="text" id="dob" class="form-input" placeholder="Contoh: 12052005" required maxlength="8">
            </div>
            <button type="submit" class="btn-login" id="btnLogin">
                <div id="loader" class="spinner"></div>
                <span>Cek Status Kartu</span>
            </button>
        </form>

        <div class="footer">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?>
        </div>
    </div>

    <!-- Modal Status -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal-card">
            <div id="statusIcon" class="status-icon"></div>
            <div class="modal-title">Status Kartu Ujian Anda</div>
            <div id="statusText" class="status-text"></div>
            <div id="statusDesc" class="modal-desc"></div>
            <button id="btnModalAction" class="btn-modal"></button>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                
                const nis = $('#nis').val().trim();
                const dob = $('#dob').val();
                
                if (!nis || !dob) return;

                $('#btnLogin').prop('disabled', true);
                $('#loader').show();

                $.ajax({
                    url: 'api/auth.php',
                    method: 'POST',
                    data: JSON.stringify({ nis, dob }),
                    contentType: 'application/json',
                    success: function(res) {
                        $('#btnLogin').prop('disabled', false);
                        $('#loader').hide();

                        if (res.success) {
                            showStatus(res.data);
                        } else {
                            alert(res.message || 'Data tidak ditemukan.');
                        }
                    },
                    error: function(xhr) {
                        $('#btnLogin').prop('disabled', false);
                        $('#loader').hide();
                        
                        let msg = 'Terjadi gangguan koneksi.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                    }
                });
            });

            function showStatus(data) {
                const isOke = data.status === 'OKE';
                const modal = $('#statusModal');
                const icon = $('#statusIcon');
                const text = $('#statusText');
                const desc = $('#statusDesc');
                const btn = $('#btnModalAction');

                icon.removeClass('oke tangguh').addClass(isOke ? 'oke' : 'tangguh');
                icon.html(isOke 
                    ? '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>'
                    : '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>'
                );

                text.removeClass('oke tangguh').addClass(isOke ? 'oke' : 'tangguh').text('“' + data.status + '”');
                
                if (isOke) {
                    desc.text('Silakan Klik OKE untuk melihat / mengunduh kartu ujian');
                    btn.removeClass('tangguh').addClass('oke').text('OKE');
                    btn.off('click').on('click', function() {
                        window.location.href = 'view.php?token=' + data.token;
                    });
                } else {
                    desc.text(data.suspension_note || 'Silakan Hubungi Wali Kelas / Waka. Kesiswaan');
                    btn.removeClass('oke').addClass('tangguh').text('OKE');
                    btn.off('click').on('click', function() {
                        modal.fadeOut();
                    });
                }

                modal.css('display', 'flex').hide().fadeIn();
            }
        });
    </script>
</body>
</html>
