<?php
/**
 * E-Xam Card Module - Entry Point
 */
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/api/xam_helper.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$user = !empty($token) ? xam_auth_from_token($token) : null;

if (!$user) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Akses Ditolak</title></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Inter,sans-serif;background:#F4F6FA;"><div style="text-align:center;padding:40px;"><h2 style="color:#D14343;">Sesi Tidak Valid</h2><p style="color:#6B7280;margin:16px 0;">Token sesi tidak ditemukan atau telah kadaluarsa.</p><a href="' . BASE_URL . '#/login" style="display:inline-block;padding:12px 24px;background:linear-gradient(135deg,#0B6E4F,#14532D);color:white;border-radius:12px;text-decoration:none;font-weight:600;">Login Kembali</a></div></body></html>';
    exit;
}

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');
$active_academic_year = get_active_academic_year();

function xam_auth_from_token($token)
{
    $token = trim((string) $token);
    if ($token === '') return null;
    $stmt = db()->prepare("
        SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) return null;

    $user['is_admin'] = in_array($user['role'], ['superadmin', 'user'], true);
    $user['is_teacher'] = ($user['role'] === 'guru');
    $user['managed_class'] = null;
    $user['has_access'] = $user['is_admin'];

    if ($user['is_teacher']) {
        $stmt = db()->prepare("SELECT kelas FROM xam_teacher_access WHERE user_id = ? AND status = 1");
        $stmt->execute([$user['user_id']]);
        $access = $stmt->fetch();
        if ($access) {
            $user['managed_class'] = $access['kelas'];
            $user['has_access'] = true;
        }
    }

    if (!$user['has_access']) return null;

    return $user;
}

// Fetch Dynamic Module Color
$module_color = '#0F766E'; // Default Teal
try {
    $stmt = db()->prepare("SELECT color FROM modules WHERE slug = 'e-xam-card'");
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
    <meta name="description" content="E-Xam Card - Manajemen Kartu Ujian Sekolah">
    <meta name="theme-color" content="<?php echo $shades['base']; ?>">
    <title>E-Xam Card - Kartu Ujian</title>

    <?php if ($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>modules/e-sarpras/assets/css/sarpras.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/examcard.css?v=<?php echo time(); ?>">

    <style>
        :root {
            --primary: <?php echo $shades['base']; ?>;
            --primary-light: <?php echo $shades['light']; ?>;
            --primary-dark: <?php echo $shades['dark']; ?>;
            --primary-gradient: linear-gradient(135deg, <?php echo $shades['base']; ?> 0%, <?php echo $shades['dark']; ?> 100%);
            --shadow-primary: 0 4px 20px rgba(<?php echo $shades['rgb']; ?>, 0.3);
            
            --exam-primary: <?php echo $shades['base']; ?>;
            --exam-primary-strong: <?php echo $shades['dark']; ?>;
            --exam-primary-soft: rgba(<?php echo $shades['rgb']; ?>, 0.1);
            --exam-accent: <?php echo $shades['base']; ?>;
        }
        .exam-app .sp-sidebar {
            background: linear-gradient(180deg, <?php echo $shades['light']; ?> 0%, <?php echo $shades['base']; ?> 45%, <?php echo $shades['dark']; ?> 100%) !important;
        }
        .loader-spinner circle {
            stroke: <?php echo $shades['base']; ?> !important;
        }
    </style>

    <script>
        window.EXAMCARD_CONFIG = {
            baseUrl: '<?php echo BASE_URL; ?>',
            moduleUrl: '<?php echo BASE_URL; ?>modules/e-xam-card/',
            token: '<?php echo addslashes($token); ?>',
            user: {
                id: <?php echo (int) $user['user_id']; ?>,
                username: <?php echo json_encode($user['username']); ?>,
                nama_lengkap: <?php echo json_encode($user['nama_lengkap']); ?>,
                role: <?php echo json_encode($user['role']); ?>,
                avatar: <?php echo json_encode($user['avatar'] ?? ''); ?>,
                is_teacher: <?php echo json_encode($user['is_teacher']); ?>,
                managed_class: <?php echo json_encode($user['managed_class']); ?>
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
    <div id="globalLoader" class="global-loader">
        <div class="loader-content">
            <div class="loader-spinner">
                <svg viewBox="0 0 50 50" class="spinner-svg">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>
                </svg>
            </div>
            <p class="loader-text">Memuat E-Xam Card...</p>
        </div>
    </div>

    <div id="xcApp" class="sarpras-app exam-app">
        <div class="sp-sidebar-overlay" id="sidebarOverlay"></div>
        <aside class="sp-sidebar" id="sidebar">
            <div class="sp-sidebar-header">
                <div class="sp-sidebar-logo exam-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M7 8h10"></path><path d="M7 12h10"></path><path d="M7 16h6"></path></svg>
                </div>
                <div class="sp-sidebar-brand">
                    <h3>E-Xam Card</h3>
                    <span>Kartu Ujian</span>
                </div>
            </div>
            <nav class="sp-sidebar-nav" id="sidebarNav"></nav>
            <div class="sp-sidebar-footer">
                <div class="sp-sidebar-user" id="sidebarUser"></div>
            </div>
        </aside>

        <main class="sp-main">
            <header class="sp-topbar">
                <div class="sp-topbar-left">
                    <button class="sp-menu-toggle" id="menuToggle" aria-label="Toggle Menu">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                    <div>
                        <h1 id="pageTitle">Dashboard</h1>
                        <div class="sp-breadcrumb" id="pageSubTitle">Manajemen Kartu Ujian</div>
                    </div>
                </div>
                <div class="sp-topbar-right">
                    <a class="sp-back-btn" href="<?php echo BASE_URL; ?>#/dashboard">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        <span>Portal</span>
                    </a>
                </div>
            </header>
            <section class="sp-content" id="mainContent"></section>
        </main>
    </div>

    <div id="modalContainer"></div>
    <div id="toastContainer" class="toast-container"></div>

    <script src="<?php echo BASE_URL; ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/modal.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/examcard.js?v=<?php echo time(); ?>"></script>
</body>
</html>
