<?php
/**
 * E-Performance Module — Entry Point
 * Sistem Penilaian Kinerja PTK
 * Supports dual auth: eportal token (admin) + perf_sessions token (PTK/siswa)
 */
require_once __DIR__ . '/../../api/config.php';

// ============================================
// AUTH: Validate token
// ============================================
$token = isset($_GET['token']) ? $_GET['token'] : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'eportal';
$user = null;
$perfRole = 'viewer';

if (!empty($token)) {
    if ($mode === 'perf') {
        // Perf session token (NIY login)
        try {
            $stmt = db()->prepare("
                SELECT ps.*, pu.id as perf_user_id, pu.username, pu.nama_lengkap, pu.role,
                       pu.perf_ptk_id, pt.jenis_ptk, r.permissions
                FROM perf_sessions ps
                JOIN perf_users pu ON ps.perf_user_id = pu.id
                LEFT JOIN perf_ptk pt ON pu.perf_ptk_id = pt.id
                LEFT JOIN perf_roles r ON pu.role = r.role_slug
                WHERE ps.token = ? AND ps.expired_at > NOW() AND pu.status = 1
            ");
            $stmt->execute([$token]);
            $session = $stmt->fetch();

            if ($session) {
                $user = [
                    'user_id' => $session['perf_user_id'],
                    'username' => $session['username'],
                    'nama_lengkap' => $session['nama_lengkap'],
                    'role' => $session['role'],
                    'tupoksi' => !empty($session['jenis_ptk']) ? $session['jenis_ptk'] : '',
                    'avatar' => '',
                    'permissions' => json_decode($session['permissions'] ?: '[]')
                ];
                $perfRole = $session['role'];
            }
        } catch (PDOException $e) { $user = null; }
    } else {
        // Eportal session token (admin/superadmin)
        try {
            $stmt = db()->prepare("
                SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar, u.tupoksi, r.permissions
                FROM sessions s 
                JOIN users u ON s.user_id = u.id 
                LEFT JOIN perf_roles r ON u.role COLLATE utf8mb4_unicode_ci = r.role_slug COLLATE utf8mb4_unicode_ci
                WHERE s.token = ? AND s.expired_at > NOW()
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            if ($user) {
                $perfRole = ($user['role'] === 'superadmin') ? 'admin' : $user['role'];
                if (empty($user['tupoksi'])) $user['tupoksi'] = '';
                $user['permissions'] = json_decode($user['permissions'] ?: '[]');
            }
        } catch (PDOException $e) { $user = null; }
    }
}

// Redirect if not authenticated
if (!$user) {
    header("Location: ../../#/login");
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
    <meta name="description" content="E-Performance — Sistem Penilaian Kinerja PTK">
    <meta name="theme-color" content="#10B981">
    <title>E-Performance — Penilaian Kinerja</title>

    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/performance.css?v=<?php echo time(); ?>">

    <script>
        window.PERF_CONFIG = {
            baseUrl: '<?php echo BASE_URL; ?>',
            moduleUrl: '<?php echo BASE_URL; ?>modules/e-performance/',
            token: '<?php echo addslashes($token); ?>',
            mode: '<?php echo $mode; ?>',
            user: {
                id: <?php echo $user['user_id']; ?>,
                username: '<?php echo addslashes($user['username']); ?>',
                nama_lengkap: '<?php echo addslashes($user['nama_lengkap']); ?>',
                role: '<?php echo $perfRole; ?>',
                tupoksi: '<?php echo addslashes($user['tupoksi'] ?? ''); ?>',
                avatar: '<?php echo addslashes($user['avatar'] ?? ''); ?>',
                permissions: <?php echo isset($user['permissions']) ? json_encode($user['permissions']) : '[]'; ?>
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
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="#10B981"></circle>
                </svg>
            </div>
            <p class="loader-text">Memuat E-Performance...</p>
        </div>
    </div>

    <!-- App Shell -->
    <div id="perfApp" class="pf-app">
        <!-- Sidebar -->
        <div class="pf-sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="pf-sidebar" id="pfSidebar">
            <div class="pf-sidebar-header">
                <div class="pf-sidebar-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="pf-sidebar-brand">
                    <h3>E-Performance</h3>
                    <span>Penilaian Kinerja</span>
                </div>
            </div>
            <nav class="pf-sidebar-nav" id="sidebarNav">
                <!-- Injected by JS based on role -->
            </nav>
            <div class="pf-sidebar-footer">
                <div class="pf-sidebar-user">
                    <div class="pf-user-avatar" id="sidebarAvatar"></div>
                    <div class="pf-user-info">
                        <div class="pf-user-name" id="sidebarUserName"></div>
                        <div class="pf-user-role" id="sidebarUserRole"></div>
                    </div>
                    <button class="pf-logout-btn" onclick="Perf.showChangePasswordModal()" title="Ubah Password" style="margin-right: 4px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </button>
                    <button class="pf-logout-btn" onclick="Perf.doLogout()" title="Logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="pf-main">
            <header class="pf-topbar" id="pfTopbar">
                <div class="pf-topbar-left">
                    <button class="pf-menu-toggle" id="menuToggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <div>
                        <h1 class="pf-page-title" id="pageTitle">Dashboard</h1>
                        <div class="pf-breadcrumb" id="breadcrumb"></div>
                    </div>
                </div>
                <div class="pf-topbar-right">
                    <?php if ($mode !== 'perf'): ?>
                    <a href="<?php echo BASE_URL; ?>#/dashboard" class="pf-back-btn" id="backBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Portal</span>
                    </a>
                    <?php endif; ?>
                </div>
            </header>
            <div class="pf-content" id="mainContent">
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
    <script src="<?php echo BASE_URL; ?>assets/js/modal.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/performance.js?v=<?php echo time(); ?>"></script>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                var loader = document.getElementById('globalLoader');
                if (loader) {
                    loader.classList.add('hidden');
                    setTimeout(function() { if(loader.parentNode) loader.parentNode.removeChild(loader); }, 500);
                }
            }, 3000);
        });
    </script>
</body>
</html>
