<?php
/**
 * E-Curriculum Module — Entry Point
 * Sistem Informasi Kurikulum Sekolah
 * Standalone SPA in new tab, authenticated via token parameter
 */
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/theme_helper.php';
require_once __DIR__ . '/api/auth_helper.php';

// ============================================
// AUTH: Validate token from URL parameter
// ============================================
$token = isset($_GET['token']) ? $_GET['token'] : '';
$user = !empty($token) ? acad_resolve_user_by_token($token, false) : null;

// Redirect if not authenticated
if (!$user) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Akses Ditolak</title></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Inter,sans-serif;background:#F5F7FA;"><div style="text-align:center;padding:40px;"><h2 style="color:#EF4444;">Sesi Tidak Valid</h2><p style="color:#6B7280;margin:16px 0;">Token sesi tidak ditemukan atau telah kadaluarsa.</p><a href="' . BASE_URL . '#/login" style="display:inline-block;padding:12px 24px;background:linear-gradient(135deg,#7C3AED,#6D28D9);color:white;border-radius:12px;text-decoration:none;font-weight:600;">Login Kembali</a></div></body></html>';
    exit;
}

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');
$active_academic_year = get_active_academic_year();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="E-Curriculum — Sistem Informasi Kurikulum Sekolah">
    
    <title>E-Curriculum — Sistem Informasi Kurikulum</title>

    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo htmlspecialchars(BASE_URL . ltrim(str_replace('\\', '/', $school_icon), '/'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(BASE_URL . ltrim(str_replace('\\', '/', $school_icon), '/'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <!-- PWA Manifest & Service Worker -->
    <link rel="manifest" href="manifest.php">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').catch(err => console.error('SW registration failed:', err));
            });
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Core Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css?v=<?php echo asset_ver('assets/css/app.css'); ?>">
    <!-- Curriculum Module Styles -->
    <link rel="stylesheet" href="assets/css/curriculum.css?v=<?php echo asset_ver('modules/e-curriculum/assets/css/curriculum.css'); ?>">

    <script>
        // Inject server data into JS
        window.CURRICULUM_CONFIG = <?php echo json_encode([
            'baseUrl' => BASE_URL,
            'moduleUrl' => BASE_URL . 'modules/e-curriculum/',
            'token' => $token,
            'user' => [
                'id' => (int)$user['user_id'],
                'username' => $user['username'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role' => $user['role'],
                'acad_role' => $user['acad_role'] ?? '',
                'custom_role_nama' => $user['custom_role_nama'] ?? '',
                'permissions' => array_values($user['permissions'] ?? []),
                'avatar' => $user['avatar'] ?? '',
            ],
            'school' => [
                'nama' => $school_name,
                'icon' => $school_icon,
            ],
            'academicYear' => $active_academic_year,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <?php echo get_module_theme_css('e-curriculum'); ?>
</head>
<body>
    <!-- Global Loading -->
    <div id="globalLoader" class="global-loader" style="background:#7C3AED;">
        <div class="loader-content">
            <div class="loader-spinner">
                <svg viewBox="0 0 50 50" class="spinner-svg">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="#ffffff"></circle>
                </svg>
            </div>
            <p class="loader-text" style="color:white;">Memuat E-Curriculum...</p>
        </div>
    </div>

    <!-- App Shell -->
    <div id="curriculumApp" class="curriculum-app">
        <!-- Sidebar -->
        <div class="acad-sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="acad-sidebar" id="acadSidebar">
            <div class="acad-sidebar-header">
                <div class="acad-sidebar-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                </div>
                <div class="acad-sidebar-brand">
                    <h3>E-Curriculum</h3>
                    <span>Layanan Kurikulum</span>
                </div>
            </div>
            <nav class="acad-sidebar-nav" id="sidebarNav">
                <!-- Injected by JS -->
            </nav>
            <div class="acad-sidebar-footer">
                <div class="acad-sidebar-user">
                    <div class="acad-user-avatar" id="sidebarAvatar"></div>
                    <div class="acad-user-info">
                        <div class="acad-user-name" id="sidebarUserName"></div>
                        <div class="acad-user-role" id="sidebarUserRole"></div>
                    </div>
                    <button class="acad-logout-btn" onclick="Curriculum.doLogout()" title="Logout / Kembali ke Portal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="acad-main">
            <header class="acad-topbar" id="acadTopbar">
                <div class="acad-topbar-left">
                    <button class="acad-menu-toggle" id="menuToggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <div>
                        <h1 class="acad-page-title" id="pageTitle">Dashboard</h1>
                        <div class="acad-breadcrumb" id="breadcrumb"></div>
                    </div>
                </div>
                <div class="acad-topbar-right">
                    <a href="<?php echo BASE_URL; ?>#/dashboard" class="acad-back-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Portal</span>
                    </a>
                </div>
            </header>
            <div class="acad-content" id="mainContent">
                <!-- Page content injected by JS -->
            </div>
        </main>
    </div>

    <!-- Modal Container -->
    <div id="modalContainer"></div>
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Vendor Scripts -->
    <script src="<?php echo BASE_URL; ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/core-utils.js?v=<?php echo asset_ver('assets/js/core-utils.js'); ?>"></script>
    <!-- SheetJS Excel Library -->
    <script src="<?php echo BASE_URL; ?>assets/vendor/xlsx.full.min.js?v=<?php echo asset_ver('assets/vendor/xlsx.full.min.js'); ?>"></script>
    <script>if(!window.XLSX){document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"><\/script>');}</script>
    <!-- Modal System -->
    <script src="<?php echo BASE_URL; ?>assets/js/modal.js?v=<?php echo asset_ver('assets/js/modal.js'); ?>"></script>
    <!-- Chart Library -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <!-- Curriculum SPA JS -->
    <script src="<?php echo BASE_URL; ?>assets/js/pagination.js?v=<?php echo asset_ver('assets/js/pagination.js'); ?>"></script>
    <script src="assets/js/curriculum.js?v=<?php echo asset_ver('modules/e-curriculum/assets/js/curriculum.js'); ?>"></script>
</body>
</html>
