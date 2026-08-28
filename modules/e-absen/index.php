<?php
/**
 * E-Absen Module Entry Point
 */
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/api/auth_helper.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$user = !empty($token) ? acad_resolve_user_by_token($token, false) : null;

// Redirect if not authenticated
if (!$user) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Akses Ditolak</title></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Inter,sans-serif;background:#F5F7FA;"><div style="text-align:center;padding:40px;"><h2 style="color:#EF4444;">Sesi Tidak Valid</h2><p style="color:#6B7280;margin:16px 0;">Token sesi tidak ditemukan atau telah kadaluarsa.</p><a href="' . BASE_URL . '#/login" style="display:inline-block;padding:12px 24px;background:linear-gradient(135deg,#7C3AED,#6D28D9);color:white;border-radius:12px;text-decoration:none;font-weight:600;">Login Kembali</a></div></body></html>';
    exit;
}

// Pass configuration to JS
$config = [
    'baseUrl' => BASE_URL,
    'moduleUrl' => BASE_URL . 'modules/e-absen/',
    'user' => $user,
    'school' => [
        'nama' => get_setting('nama_sekolah', 'E-Portal'),
        'icon' => get_setting('icon_sekolah', '')
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Absen | <?php echo htmlspecialchars($config['school']['nama']); ?></title>
    
<?php if($config['school']['icon']): ?>
    <link rel="icon" href="<?php echo BASE_URL . $config['school']['icon']; ?>">
    <?php endif; ?>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Core Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css?v=<?php echo time(); ?>">
    <!-- Modal CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/emodal.css">
    
    <!-- Absen Module Styles -->
    <link rel="stylesheet" href="assets/css/absen.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Global Loader -->
    <div id="globalLoader" class="global-loader">
        <div class="loader-content">
            <div class="loader-spinner">
                <svg viewBox="0 0 50 50" class="spinner-svg">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>
                </svg>
            </div>
            <p class="loader-text">Memuat E-Absen...</p>
        </div>
    </div>

    <!-- App Shell -->
    <div id="eaApp" class="ea-app">
        <!-- Sidebar -->
        <div class="ea-sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="ea-sidebar" id="eaSidebar">
            <div class="ea-sidebar-header">
                <div class="ea-sidebar-logo" style="background: linear-gradient(135deg, #10B981, #059669);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <div class="ea-sidebar-brand">
                    <h3>E-Absen</h3>
                    <span>Fingerprint System</span>
                </div>
            </div>
            <nav class="ea-sidebar-nav" id="sidebarNav">
                <!-- Rendered via JS -->
            </nav>
            <div class="ea-sidebar-footer">
                <div class="ea-sidebar-user">
                    <div class="ea-user-avatar" id="sidebarAvatar">?</div>
                    <div class="ea-user-info">
                        <div class="ea-user-name" id="sidebarUserName">User</div>
                        <div class="ea-user-role" id="sidebarUserRole">Role</div>
                    </div>
                    <button class="ea-logout-btn" onclick="Absen.doLogout()" title="Keluar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="ea-main">
            <!-- Topbar -->
            <header class="ea-topbar" id="eaTopbar">
                <div class="ea-topbar-left">
                    <button class="ea-menu-toggle" id="menuToggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <div>
                        <h1 class="ea-page-title" id="pageTitle">Dashboard</h1>
                        <div class="ea-breadcrumb" id="breadcrumb"></div>
                    </div>
                </div>
                <div class="ea-topbar-right">
                    <a href="<?php echo BASE_URL; ?>#/dashboard" class="ea-back-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Portal Utama</span>
                    </a>
                </div>
            </header>
            <div class="ea-content" id="mainContent">
                <!-- Page content injected by JS -->
            </div>
        </main>
    </div>
    <!-- Modal & Toast Containers -->
    <div id="modalContainer"></div>
    <div id="toastContainer" class="toast-container"></div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/modal.js"></script>
    <script>
        window.ABSEN_CONFIG = <?php echo json_encode($config); ?>;
    </script>
    <script src="assets/js/absen.js?v=<?php echo time(); ?>"></script>
</body>
</html>
