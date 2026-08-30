<?php
/**
 * Guru App — PWA Entry Point
 * Standalone mobile app for teachers
 * Schedule viewing & journal management
 */
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/migration_helper.php';

// Jalankan auto-migration jika ada versi baru
run_auto_migrations();

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="description" content="Guru App — Jadwal & Jurnal Mengajar">
    <meta name="theme-color" content="#1565C0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Guru App">

    <title>Guru App — <?php echo htmlspecialchars($school_name); ?></title>

    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <?php else: ?>
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/icons/icon-192.png">
    <?php endif; ?>

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.php">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Guru App Styles -->
    <link rel="stylesheet" href="assets/css/guru.css?v=<?php echo time(); ?>">

    <script>
        window.GURU_CONFIG = {
            baseUrl: '<?php echo BASE_URL; ?>',
            moduleUrl: '<?php echo BASE_URL; ?>guru/',
            school: {
                nama: '<?php echo addslashes($school_name); ?>',
                icon: '<?php echo addslashes($school_icon); ?>'
            }
        };
    </script>
</head>
<body>
    <!-- Global Loading -->
    <div id="globalLoader" class="guru-loader">
        <div class="guru-loader-content">
            <div class="guru-loader-spinner"></div>
            <p class="guru-loader-text">Guru App</p>
        </div>
    </div>

    <!-- ========== LOGIN PAGE ========== -->
    <div id="loginPage" class="login-page" style="display:none;">
        <div class="login-card">
            <div class="login-logo">
                <?php if($school_icon): ?>
                    <img src="<?php echo BASE_URL . $school_icon; ?>" alt="Logo Sekolah">
                <?php else: ?>
                    <svg class="login-logo-fallback" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                    </svg>
                <?php endif; ?>
            </div>
            <h1 class="login-title">Guru App</h1>
            <p class="login-subtitle"><?php echo htmlspecialchars($school_name); ?></p>

            <form id="loginForm" autocomplete="off">
                <div class="login-form-group">
                    <label class="login-form-label">Username</label>
                    <div class="login-form-input-wrap">
                        <input type="text" class="login-form-input" id="loginUsername" placeholder="Masukkan username" autocomplete="username" required>
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                </div>
                <div class="login-form-group">
                    <label class="login-form-label">Password</label>
                    <div class="login-form-input-wrap">
                        <input type="password" class="login-form-input" id="loginPassword" placeholder="Masukkan password" autocomplete="current-password" required>
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                </div>
                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="btn-label">Masuk</span>
                </button>
                <div class="login-error" id="loginError"></div>
            </form>
        </div>
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> E-Portal <?php echo htmlspecialchars($school_name); ?>
        </div>
    </div>

    <!-- ========== APP SHELL ========== -->
    <div id="appShell" class="app-shell" style="display:none;">
        <!-- Header -->
        <header class="app-header">
            <div class="app-header-left">
                <div class="app-header-avatar" id="headerAvatar"></div>
                <div>
                    <div class="app-header-greeting" id="headerGreeting">Selamat Pagi 👋</div>
                    <div class="app-header-name" id="headerName">Guru</div>
                </div>
            </div>
            <div class="app-header-right">
                <button class="app-header-btn" onclick="location.reload()" title="Refresh">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                    </svg>
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="app-content" id="appContent">
            <!-- Injected by SPA router -->
        </main>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <button class="bottom-nav-item active" data-page="home" onclick="location.hash='#/home'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span>Beranda</span>
            </button>
            <button class="bottom-nav-item center-fab" data-page="jurnal" onclick="location.hash='#/jurnal'">
                <div class="fab-circle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                </div>
                <span>Isi Jurnal</span>
            </button>
            <button class="bottom-nav-item" data-page="profil" onclick="location.hash='#/profil'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span>Profil</span>
            </button>
        </nav>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Guru App Script -->
    <script src="assets/js/guru.js?v=<?php echo time(); ?>"></script>
</body>
</html>
