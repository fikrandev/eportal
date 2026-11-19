<?php
/**
 * Siswa App Login Page (redirects cleanly into SPA shell)
 */
require_once __DIR__ . '/../api/config.php';

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login Portal Murid — <?php echo htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo htmlspecialchars(BASE_URL . ltrim(str_replace('\\', '/', $school_icon), '/'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/siswa.css?v=<?php echo asset_ver('siswa/assets/css/siswa.css'); ?>">
    <script>
        window.APP_CONFIG = <?php echo json_encode([
            'baseUrl' => BASE_URL . 'siswa/',
            'rootUrl' => BASE_URL
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card animate-fade-in">
            <?php if($school_icon): ?>
                <img src="<?php echo htmlspecialchars(BASE_URL . ltrim(str_replace('\\', '/', $school_icon), '/'), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="login-logo" onerror="this.style.display='none'">
            <?php endif; ?>
            
            <h1 class="login-title">Portal Murid</h1>
            <p class="login-subtitle"><?php echo htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8'); ?></p>

            <form id="formLogin" onsubmit="event.preventDefault(); submitLogin();">
                <div class="form-group">
                    <label class="form-label" for="nis">Nomor Induk Siswa (NIS)</label>
                    <input type="text" id="nis" class="form-control" placeholder="Masukkan NIS" required autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="tgl_lahir">Password (Tanggal Lahir: DDMMYYYY)</label>
                    <input type="password" inputmode="numeric" id="tgl_lahir" class="form-control" placeholder="Contoh: 27092007" required autocomplete="off">
                </div>

                <button type="submit" class="btn btn-primary btn-block mt-4" id="btnLogin">Masuk</button>
            </form>
        </div>
    </div>

    <!-- Toasts -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="<?php echo BASE_URL; ?>assets/js/core-utils.js?v=<?php echo asset_ver('assets/js/core-utils.js'); ?>"></script>
    <script src="assets/js/siswa.js?v=<?php echo asset_ver('siswa/assets/js/siswa.js'); ?>"></script>
    <script>
        function submitLogin() {
            const btn = document.getElementById('btnLogin');
            btn.innerText = 'Memproses...';
            btn.disabled = true;

            const nis = document.getElementById('nis').value;
            const tgl = document.getElementById('tgl_lahir').value;

            App.login(nis, tgl).then(ok => {
                if (ok) window.location.href = 'index.php';
            }).finally(() => {
                btn.innerText = 'Masuk';
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>
