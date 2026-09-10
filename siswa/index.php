<?php
/**
 * Siswa App — PWA Entry Point
 * Mobile PWA for students: Attendance, Leave Requests, and Guidance & Counseling (BK)
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
    <meta name="description" content="Portal Murid — <?php echo htmlspecialchars($school_name); ?>">
    <meta name="theme-color" content="#1565C0">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Portal Murid">

    <title>Portal Murid — <?php echo htmlspecialchars($school_name); ?></title>

    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . htmlspecialchars($school_icon); ?>">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL . htmlspecialchars($school_icon); ?>">
    <?php endif; ?>

    <link rel="manifest" href="manifest.php">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/siswa.css?v=<?php echo time(); ?>">
    
    <script>
        window.APP_CONFIG = { baseUrl: "<?php echo BASE_URL; ?>siswa/" };
    </script>
</head>
<body>
    <!-- Global Loading Screen -->
    <div id="globalLoader" class="app-loader">
        <div class="app-loader-content">
            <div class="app-loader-spinner"></div>
            <p class="app-loader-text">Portal Murid</p>
        </div>
    </div>

    <!-- ========== LOGIN PAGE ========== -->
    <div id="loginPage" class="login-page" style="display:none;">
        <div class="login-card">
            <div class="login-logo">
                <?php if($school_icon): ?>
                    <img src="<?php echo BASE_URL . htmlspecialchars($school_icon); ?>" alt="Logo Sekolah">
                <?php else: ?>
                    <svg class="login-logo-fallback" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                    </svg>
                <?php endif; ?>
            </div>
            <h1 class="login-title">Portal Murid</h1>
            <p class="login-subtitle"><?php echo htmlspecialchars($school_name); ?></p>

            <form id="loginForm" autocomplete="off">
                <div class="login-form-group">
                    <label class="login-form-label" for="loginUsername">Nomor Induk Siswa (NIS)</label>
                    <div class="login-form-input-wrap">
                        <input type="text" class="login-form-input" id="loginUsername" placeholder="Masukkan NIS" required autocomplete="off">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                </div>
                <div class="login-form-group">
                    <label class="login-form-label" for="loginPassword">Tanggal Lahir</label>
                    <div class="login-form-input-wrap">
                        <input type="date" class="login-form-input" id="loginPassword" required>
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                </div>
                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="btn-label">Masuk ke Portal</span>
                </button>
                <div class="login-error" id="loginError"></div>
            </form>
        </div>
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> E-Portal &bull; <?php echo htmlspecialchars($school_name); ?>
        </div>
    </div>

    <!-- ========== APP SHELL ========== -->
    <div id="appShell" class="app-shell" style="display:none;">
        <!-- Header -->
        <header class="app-header">
            <div class="app-header-left">
                <div class="app-header-avatar" id="headerAvatar">S</div>
                <div>
                    <div class="app-header-greeting" id="headerGreeting">Halo 👋</div>
                    <div class="app-header-name" id="headerName">Siswa</div>
                </div>
            </div>
            <div class="app-header-right">
                <button class="app-header-btn" onclick="App.logout()" title="Keluar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="app-content" id="appContent">
            <!-- Injected by SPA router -->
        </main>

        <!-- Bottom Navigation with iOS Safe Area -->
        <nav class="bottom-nav">
            <button class="bottom-nav-item active" data-page="dashboard" onclick="location.hash='#/dashboard'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span>Beranda</span>
            </button>
            <button class="bottom-nav-item" data-page="izin" onclick="location.hash='#/izin'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                <span>Izin</span>
            </button>
            <button class="bottom-nav-item" data-page="kehadiran" onclick="location.hash='#/kehadiran'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span>Presensi</span>
            </button>
            <button class="bottom-nav-item" data-page="bk" onclick="location.hash='#/bk'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <span>Buku BK</span>
            </button>
        </nav>
    </div>

    <!-- Modals & Toasts Container -->
    <div id="modalContainer"></div>
    <div id="toastContainer" class="toast-container"></div>

    <!-- Scripts -->
    <script src="../assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="assets/js/siswa.js?v=<?php echo time(); ?>"></script>

    <!-- Register PWA Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./sw.js')
                    .then(reg => console.log('Siswa SW registered.', reg))
                    .catch(err => console.log('Siswa SW registration failed: ', err));
            });
        }
    </script>
</body>
</html>
