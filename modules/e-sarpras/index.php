<?php
/**
 * E-Sarpras Module — Entry Point
 * Sistem Manajemen Sarana & Prasarana Sekolah
 * Opens as standalone SPA in new tab, authenticated via token parameter
 */
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/api/auth_helper.php';

// ============================================
// AUTH: Validate token from URL parameter
// ============================================
$token = isset($_GET['token']) ? $_GET['token'] : '';
$user = !empty($token) ? sp_resolve_user_by_token($token, false) : null;

// Redirect if not authenticated
if (!$user) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Akses Ditolak</title></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Inter,sans-serif;background:#F5F7FA;"><div style="text-align:center;padding:40px;"><h2 style="color:#EF4444;">Sesi Tidak Valid</h2><p style="color:#6B7280;margin:16px 0;">Token sesi tidak ditemukan atau telah kadaluarsa.</p><a href="' . BASE_URL . '#/login" style="display:inline-block;padding:12px 24px;background:linear-gradient(135deg,#1565C0,#0D47A1);color:white;border-radius:12px;text-decoration:none;font-weight:600;">Login Kembali</a></div></body></html>';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="E-Sarpras — Sistem Manajemen Sarana & Prasarana Sekolah">
    <meta name="theme-color" content="#1565C0">
    <title>E-Sarpras — Sarana & Prasarana</title>

    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Core Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css?v=<?php echo time(); ?>">
    <!-- Sarpras Module Styles -->
    <link rel="stylesheet" href="assets/css/sarpras.css?v=<?php echo time(); ?>">

    <script>
        // Inject server data into JS
        window.SARPRAS_CONFIG = {
            baseUrl: '<?php echo BASE_URL; ?>',
            moduleUrl: '<?php echo BASE_URL; ?>modules/e-sarpras/',
            token: '<?php echo addslashes($token); ?>',
            user: {
                id: <?php echo $user['user_id']; ?>,
                username: '<?php echo addslashes($user['username']); ?>',
                nama_lengkap: '<?php echo addslashes($user['nama_lengkap']); ?>',
                role: '<?php echo $user['role']; ?>',
                avatar: '<?php echo addslashes($user['avatar'] ?? ''); ?>',
                sarpras_role: '<?php echo addslashes($user['sarpras_role'] ?? 'viewer_sarpras'); ?>',
                custom_role_name: '<?php echo addslashes($user['custom_role_name'] ?? ''); ?>',
                permissions: <?php echo json_encode(array_values($user['permissions'] ?? []), JSON_UNESCAPED_UNICODE); ?>,
                scoped_ruang_ids: <?php echo json_encode(array_map('intval', $user['scoped_ruang_ids'] ?? []), JSON_UNESCAPED_UNICODE); ?>
            },
            school: {
                nama: '<?php echo addslashes($school_name); ?>',
                icon: '<?php echo addslashes($school_icon); ?>'
            },
            academicYear: <?php echo json_encode($active_academic_year, JSON_UNESCAPED_UNICODE); ?>
        };
    </script>
</head>
<body>
    <!-- Global Loading -->
    <div id="globalLoader" class="global-loader">
        <div class="loader-content">
            <div class="loader-spinner">
                <svg viewBox="0 0 50 50" class="spinner-svg">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>
                </svg>
            </div>
            <p class="loader-text">Memuat E-Sarpras...</p>
        </div>
    </div>

    <!-- App Shell -->
    <div id="sarprasApp" class="sarpras-app">
        <!-- Sidebar -->
        <div class="sp-sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="sp-sidebar" id="spSidebar">
            <div class="sp-sidebar-header">
                <div class="sp-sidebar-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9h1"/><path d="M9 13h1"/><path d="M9 17h1"/></svg>
                </div>
                <div class="sp-sidebar-brand">
                    <h3>E-Sarpras</h3>
                    <span>Sarana & Prasarana</span>
                </div>
            </div>
            <nav class="sp-sidebar-nav" id="sidebarNav">
                <!-- Injected by JS -->
            </nav>
            <div class="sp-sidebar-footer">
                <div class="sp-sidebar-user">
                    <div class="sp-user-avatar" id="sidebarAvatar"></div>
                    <div class="sp-user-info">
                        <div class="sp-user-name" id="sidebarUserName"></div>
                        <div class="sp-user-role" id="sidebarUserRole"></div>
                    </div>
                    <button class="sp-logout-btn" onclick="Sarpras.doLogout()" title="Logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="sp-main">
            <header class="sp-topbar" id="spTopbar">
                <div class="sp-topbar-left">
                    <button class="sp-menu-toggle" id="menuToggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <div>
                        <h1 class="sp-page-title" id="pageTitle">Dashboard</h1>
                        <div class="sp-breadcrumb" id="breadcrumb"></div>
                    </div>
                </div>
                <div class="sp-topbar-right">
                    <a href="<?php echo BASE_URL; ?>#/dashboard" class="sp-back-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Portal</span>
                    </a>
                </div>
            </header>
            <div class="sp-content" id="mainContent">
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
    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <!-- Modal System -->
    <script src="<?php echo BASE_URL; ?>assets/js/modal.js?v=<?php echo time(); ?>"></script>
    <!-- Sarpras SPA -->
    <script src="assets/js/sarpras.js?v=2.1_<?php echo time(); ?>"></script>
</body>
</html>
