<?php
/**
 * E-Examination Module — Entry Point
 * Sistem Ujian Digital (CBT)
 */
require_once __DIR__ . '/../../api/config.php';

// ============================================
// AUTH: Validate token
// ============================================
$token = isset($_GET['token']) ? $_GET['token'] : '';
$user = null;

if (!empty($token)) {
    // Eportal session token (admin/guru)
    try {
        $stmt = db()->prepare("
            SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar
            FROM sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        // Block students/unauthorized
        if ($user && !in_array($user['role'], ['superadmin', 'user', 'guru'])) {
            $user = null;
        }
    } catch (PDOException $e) { $user = null; }
}

// Redirect if not authenticated
if (!$user) {
    header("Location: " . BASE_URL . "#/login");
    exit;
}

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="E-Examination — Sistem Ujian Digital CBT">
    <meta name="theme-color" content="#2563EB">
    <title>E-Examination — CBT</title>

    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Eportal Global CSS (reuse for some utilities) -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css?v=<?php echo time(); ?>">
    <!-- Examination Custom CSS -->
    <link rel="stylesheet" href="assets/css/examination.css?v=<?php echo time(); ?>">
    
    <!-- KaTeX for Math Equations -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>

    <script>
        window.EXAM_CONFIG = {
            baseUrl: '<?php echo BASE_URL; ?>',
            moduleUrl: '<?php echo BASE_URL; ?>modules/e-examination/',
            token: '<?php echo addslashes($token); ?>',
            user: {
                id: <?php echo $user['user_id']; ?>,
                username: '<?php echo addslashes($user['username']); ?>',
                nama_lengkap: '<?php echo addslashes($user['nama_lengkap']); ?>',
                role: '<?php echo $user['role']; ?>',
                avatar: '<?php echo addslashes($user['avatar'] ?? ''); ?>'
            },
            school: {
                nama: '<?php echo addslashes($school_name); ?>',
                icon: '<?php echo addslashes($school_icon); ?>'
            }
        };
    </script>
</head>
<body>
    <!-- Global Loading -->
    <div id="globalLoader" class="global-loader" style="background:#2563EB;">
        <div class="loader-content">
            <div class="loader-spinner">
                <svg viewBox="0 0 50 50" class="spinner-svg">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="#ffffff"></circle>
                </svg>
            </div>
            <p class="loader-text" style="color:white;">Memuat E-Examination...</p>
        </div>
    </div>

    <!-- App Shell -->
    <div id="examApp" class="ex-app">
        <!-- Sidebar -->
        <div class="ex-sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="ex-sidebar" id="exSidebar">
            <div class="ex-sidebar-header">
                <div class="ex-sidebar-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="ex-sidebar-brand">
                    <h3>E-Examination</h3>
                    <span>Computer Based Test</span>
                </div>
            </div>
            <nav class="ex-sidebar-nav" id="sidebarNav">
                <!-- Injected by JS -->
            </nav>
            <div class="ex-sidebar-footer">
                <div class="ex-sidebar-user">
                    <div class="ex-user-avatar" id="sidebarAvatar"></div>
                    <div class="ex-user-info">
                        <div class="ex-user-name" id="sidebarUserName"></div>
                        <div class="ex-user-role" id="sidebarUserRole"></div>
                    </div>
                    <button class="ex-logout-btn" onclick="Exam.doLogout()" title="Kembali ke Portal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="ex-main">
            <header class="ex-topbar" id="exTopbar">
                <div class="ex-topbar-left">
                    <button class="ex-menu-toggle" id="menuToggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <div>
                        <h1 class="ex-page-title" id="pageTitle">Dashboard</h1>
                        <div class="ex-breadcrumb" id="breadcrumb">
                            <a href="#/dashboard">E-Examination</a>
                            <span class="sep">/</span>
                            <span class="current" id="breadcrumbCurrent">Dashboard</span>
                        </div>
                    </div>
                </div>
                <div class="ex-topbar-right">
                    <a href="<?php echo BASE_URL; ?>#/dashboard" class="btn-outline" style="padding:8px 16px; border-radius:8px; text-decoration:none; display:flex; align-items:center; gap:8px; font-weight:600; font-size:0.85rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Portal</span>
                    </a>
                </div>
            </header>
            <div class="ex-content" id="mainContent">
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
    
    <!-- Module Script -->
    <script src="assets/js/examination.js?v=<?php echo time(); ?>"></script>
</body>
</html>
