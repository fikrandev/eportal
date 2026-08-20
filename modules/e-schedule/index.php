<?php
/**
 * E-Schedule Module — Entry Point
 * Sistem Manajemen Jadwal Pelajaran
 */
require_once __DIR__ . '/../../api/config.php';

// Validasi Token
$token = isset($_GET['token']) ? $_GET['token'] : '';
$user = null;

if (!empty($token)) {
    try {
        $stmt = db()->prepare("
            SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar
            FROM sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.token = ? AND s.expired_at > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        $user = null;
    }
}

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
    <meta name="description" content="E-Schedule — Sistem Manajemen Jadwal Pelajaran">
    <meta name="theme-color" content="#FF8F00">
    <title>E-Schedule — Jadwal Pelajaran</title>

    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Core Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css?v=<?php echo time(); ?>">
    <!-- Schedule Module Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>modules/e-schedule/assets/css/schedule.css?v=<?php echo time(); ?>">

    <script>
        window.SCHEDULE_CONFIG = {
            baseUrl: '<?php echo BASE_URL; ?>',
            moduleUrl: '<?php echo BASE_URL; ?>modules/e-schedule/',
            token: '<?php echo addslashes($token); ?>',
            user: {
                id: <?php echo $user['user_id']; ?>,
                username: <?php echo json_encode($user['username']); ?>,
                nama_lengkap: <?php echo json_encode($user['nama_lengkap']); ?>,
                role: <?php echo json_encode($user['role']); ?>,
                avatar: <?php echo json_encode($user['avatar'] ?? ''); ?>
            },
            school: {
                nama: <?php echo json_encode($school_name); ?>,
                icon: <?php echo json_encode($school_icon); ?>
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
                <svg viewBox="0 0 50 50" class="spinner-svg" style="stroke: #FF8F00">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>
                </svg>
            </div>
            <p class="loader-text">Memuat E-Schedule...</p>
        </div>
    </div>

    <!-- App Shell -->
    <div id="schApp" class="sch-app">
        <!-- Sidebar -->
        <div class="sch-sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="sch-sidebar" id="schSidebar">
            <div class="sch-sidebar-header">
                <div class="sch-sidebar-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                </div>
                <div class="sch-sidebar-brand">
                    <h3>E-Schedule</h3>
                    <span>Jadwal Pelajaran</span>
                </div>
            </div>
            <nav class="sch-sidebar-nav" id="sidebarNav">
                <!-- Injected by JS -->
            </nav>
            <div class="sch-sidebar-footer">
                <div class="sch-sidebar-user">
                    <div class="sch-user-avatar" id="sidebarAvatar"></div>
                    <div class="sch-user-info">
                        <div class="sch-user-name" id="sidebarUserName"></div>
                        <div class="sch-user-role" id="sidebarUserRole"></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="sch-main">
            <header class="sch-topbar" id="schTopbar">
                <div class="sch-topbar-left">
                    <button class="sch-menu-toggle" id="menuToggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <div>
                        <h1 class="sch-page-title" id="pageTitle">Dashboard</h1>
                        <div class="sch-breadcrumb" id="breadcrumb"></div>
                    </div>
                </div>
                <div class="sch-topbar-right">
                    <a href="<?php echo BASE_URL; ?>#/dashboard" class="sch-back-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Portal</span>
                    </a>
                </div>
            </header>
            <div class="sch-content" id="mainContent">
                <!-- Page content injected by JS -->
            </div>
        </main>
    </div>

    <!-- Modal Container -->
    <div id="modalContainer"></div>
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Content for exports -->
    <div id="exportContainer" style="display:none"></div>

    <!-- Vendor Scripts -->
    <script src="<?php echo BASE_URL; ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <!-- Modal System -->
    <script src="<?php echo BASE_URL; ?>assets/js/modal.js?v=2.0.0"></script>

    <!-- Schedule SPA -->
    <script src="<?php echo BASE_URL; ?>modules/e-schedule/assets/js/schedule.js?v=<?php echo time(); ?>"></script>
    <script>
        // Fallback: Force remove loader if JS initialization fails or takes too long
        window.addEventListener('load', function() {
            setTimeout(function() {
                var loader = document.getElementById('globalLoader');
                if (loader) {
                    console.log('Fallback: Removing loader');
                    loader.classList.add('hidden');
                    setTimeout(function() { if(loader.parentNode) loader.parentNode.removeChild(loader); }, 500);
                }
            }, 3000);
        });
    </script>
</body>
</html>
