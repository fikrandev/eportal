<?php
/**
 * E-Graduation Module - Entry Point
 */
require_once __DIR__ . '/../../api/config.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$user = null;

if (!empty($token)) {
    try {
        $stmt = db()->prepare("
            SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        $user = null;
    }
}

if (!$user) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Akses Ditolak</title></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Inter,sans-serif;background:#F5F7FA;"><div style="text-align:center;padding:40px;"><h2 style="color:#EF4444;">Sesi Tidak Valid</h2><p style="color:#6B7280;margin:16px 0;">Token sesi tidak ditemukan atau telah kadaluarsa.</p><a href="' . BASE_URL . '#/login" style="display:inline-block;padding:12px 24px;background:linear-gradient(135deg,#0F766E,#0d9488);color:white;border-radius:12px;text-decoration:none;font-weight:600;">Login Kembali</a></div></body></html>';
    exit;
}

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');
$active_academic_year = get_active_academic_year();
$graduation_role = $user['role'] === 'superadmin' ? 'admin' : 'viewer';
$scoped_classes = [];
if ($user['role'] !== 'superadmin') {
    try {
        $stmt = db()->prepare("SELECT access_role, kelas FROM grad_teacher_access WHERE user_id = ? AND status = 1");
        $stmt->execute([$user['user_id']]);
        $access = $stmt->fetchAll();
        if ($access) {
            $graduation_role = $access[0]['access_role'];
            $scoped_classes = array_values(array_unique(array_column($access, 'kelas')));
        } else {
            $user = null;
        }
    } catch (PDOException $e) {
        $user = null;
    }
}

if (!$user) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Akses Ditolak</title></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Inter,sans-serif;background:#F5F7FA;"><div style="text-align:center;padding:40px;"><h2 style="color:#EF4444;">Akses Ditolak</h2><p style="color:#6B7280;margin:16px 0;">Anda belum terdaftar dalam Akses Modul E-Graduation.</p><a href="' . BASE_URL . '#/dashboard" style="display:inline-block;padding:12px 24px;background:linear-gradient(135deg,#0F766E,#0d9488);color:white;border-radius:12px;text-decoration:none;font-weight:600;">Kembali ke Dashboard</a></div></body></html>';
    exit;
}

// Fetch Dynamic Module Color
$module_color = '#0F766E'; // Default Teal
try {
    $stmt = db()->prepare("SELECT color FROM modules WHERE slug = 'e-graduation'");
    $stmt->execute();
    if ($row = $stmt->fetch()) {
        $module_color = $row['color'] ?: '#0F766E';
    }
} catch (PDOException $e) {}

function getThemeShades($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    $r = hexdec(substr($hex, 0, 2) ?: '00');
    $g = hexdec(substr($hex, 2, 2) ?: '00');
    $b = hexdec(substr($hex, 4, 2) ?: '00');
    return [
        'base' => sprintf("#%02x%02x%02x", $r, $g, $b),
        'light' => sprintf("#%02x%02x%02x", min(255, $r + 30), min(255, $g + 30), min(255, $b + 30)),
        'dark' => sprintf("#%02x%02x%02x", max(0, $r - 30), max(0, $g - 30), max(0, $b - 30)),
        'darker' => sprintf("#%02x%02x%02x", max(0, $r - 50), max(0, $g - 50), max(0, $b - 50)),
        'rgb' => "$r, $g, $b"
    ];
}
$shades = getThemeShades($module_color);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="E-Graduation - Sistem Manajemen Kelulusan Sekolah">
    <meta name="theme-color" content="<?php echo $shades['base']; ?>">
    <title>E-Graduation - Kelulusan</title>

    <?php if ($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../e-sarpras/assets/css/sarpras.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/graduation.css?v=<?php echo time(); ?>">

    <style>
        :root {
            --primary: <?php echo $shades['base']; ?>;
            --primary-light: <?php echo $shades['light']; ?>;
            --primary-dark: <?php echo $shades['dark']; ?>;
            --primary-gradient: linear-gradient(135deg, <?php echo $shades['base']; ?> 0%, <?php echo $shades['dark']; ?> 100%);
            --shadow-primary: 0 4px 20px rgba(<?php echo $shades['rgb']; ?>, 0.3);
        }
        .grad-app .sp-sidebar {
            background: linear-gradient(180deg, <?php echo $shades['base']; ?> 0%, <?php echo $shades['dark']; ?> 60%, <?php echo $shades['darker']; ?> 100%) !important;
        }
        .grad-year-pill {
            color: <?php echo $shades['base']; ?> !important;
            background: rgba(<?php echo $shades['rgb']; ?>, 0.1) !important;
        }
        .grad-dashboard-note {
            background: radial-gradient(circle at 92% 12%, rgba(<?php echo $shades['rgb']; ?>, 0.14), transparent 26%), linear-gradient(135deg, #ffffff 0%, #f8fbff 52%, #ecfdf5 100%) !important;
        }
        .grad-score-scroll-control input[type="range"] {
            accent-color: <?php echo $shades['base']; ?> !important;
        }
        .sp-btn-primary, .btn-primary {
            background: linear-gradient(135deg, <?php echo $shades['light']; ?>, <?php echo $shades['base']; ?>) !important;
        }
        .loader-spinner circle {
            stroke: <?php echo $shades['base']; ?> !important;
        }
    </style>

    <script>
        window.GRADUATION_CONFIG = {
            baseUrl: '<?php echo BASE_URL; ?>',
            moduleUrl: '<?php echo BASE_URL; ?>modules/e-graduation/',
            token: '<?php echo addslashes($token); ?>',
            user: {
                id: <?php echo (int) $user['user_id']; ?>,
                username: '<?php echo addslashes($user['username']); ?>',
                nama_lengkap: '<?php echo addslashes($user['nama_lengkap']); ?>',
                role: '<?php echo $user['role']; ?>',
                avatar: '<?php echo addslashes($user['avatar'] ?? ''); ?>',
                can_manage_graduation: <?php echo $user['role'] === 'superadmin' ? 'true' : 'false'; ?>,
                graduation_role: '<?php echo addslashes($graduation_role); ?>',
                scoped_classes: <?php echo json_encode($scoped_classes, JSON_UNESCAPED_UNICODE); ?>
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
    <div id="globalLoader" class="global-loader">
        <div class="loader-content">
            <div class="loader-spinner">
                <svg viewBox="0 0 50 50" class="spinner-svg">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>
                </svg>
            </div>
            <p class="loader-text">Memuat E-Graduation...</p>
        </div>
    </div>

    <div id="graduationApp" class="sarpras-app grad-app">
        <div class="sp-sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="sp-sidebar" id="gradSidebar">
            <div class="sp-sidebar-header">
                <div class="sp-sidebar-logo grad-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 10.5 12 5 2 10.5 12 16l10-5.5Z"/><path d="M6 13v4c2 1.5 10 1.5 12 0v-4"/><path d="M12 16v5"/><path d="M8 21h8"/></svg>
                </div>
                <div class="sp-sidebar-brand">
                    <h3>E-Graduation</h3>
                    <span>Kelulusan Siswa</span>
                </div>
            </div>
            <nav class="sp-sidebar-nav" id="sidebarNav"></nav>
            <div class="sp-sidebar-footer">
                <div class="sp-sidebar-user">
                    <div class="sp-user-avatar" id="sidebarAvatar"></div>
                    <div class="sp-user-info">
                        <div class="sp-user-name" id="sidebarUserName"></div>
                        <div class="sp-user-role" id="sidebarUserRole"></div>
                    </div>
                    <button class="sp-logout-btn" onclick="Graduation.doLogout()" title="Logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </div>
            </div>
        </aside>

        <main class="sp-main">
            <header class="sp-topbar">
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
            <div class="sp-content" id="mainContent"></div>
        </main>
    </div>

    <div id="modalContainer"></div>
    <div id="toastContainer" class="toast-container"></div>

    <script src="<?php echo BASE_URL; ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/modal.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/graduation.js?v=<?php echo time(); ?>"></script>
</body>
</html>
