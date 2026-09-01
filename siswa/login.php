<?php
/**
 * Siswa App Login Page
 */
require_once __DIR__ . '/../api/config.php';

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Login Siswa — <?php echo htmlspecialchars($school_name); ?></title>
    
    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . htmlspecialchars($school_icon); ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/siswa.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card animate-fade-in">
            <?php if($school_icon): ?>
                <img src="<?php echo BASE_URL . htmlspecialchars($school_icon); ?>" alt="Logo" class="login-logo">
            <?php endif; ?>
            
            <h1 class="login-title">Siswa App</h1>
            <p class="login-subtitle"><?php echo htmlspecialchars($school_name); ?></p>

            <form id="formLogin" onsubmit="event.preventDefault(); submitLogin();">
                <div class="form-group">
                    <label class="form-label">Nomor Induk Siswa (NIS)</label>
                    <input type="text" id="nis" class="form-control" placeholder="Masukkan NIS" required autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password (Tanggal Lahir: YYYYMMDD)</label>
                    <input type="password" id="tgl_lahir" class="form-control" placeholder="Contoh: 20070927" required autocomplete="off">
                </div>

                <button type="submit" class="btn btn-primary btn-block mt-4" id="btnLogin">Masuk</button>
            </form>
        </div>
    </div>

    <!-- Toasts -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="../assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="assets/js/siswa.js?v=<?php echo time(); ?>"></script>
    <script>
        function submitLogin() {
            const btn = document.getElementById('btnLogin');
            btn.innerText = 'Memproses...';
            btn.disabled = true;

            const nis = document.getElementById('nis').value;
            const tgl = document.getElementById('tgl_lahir').value;

            App.login(nis, tgl).finally(() => {
                btn.innerText = 'Masuk';
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>
