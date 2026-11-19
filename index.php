<?php
require_once __DIR__ . '/api/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="E-Portal - Sistem Informasi Sekolah Terpadu">
    <meta name="theme-color" content="#1565C0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="E-Portal">
    
    <title>E-Portal — Sistem Informasi Sekolah</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <!-- Dynamic Favicon & Icons -->
    <?php
    $school_icon = get_setting('icon_sekolah', 'assets/icons/icon-192.png');
    $school_name = get_setting('nama_sekolah', 'E-Portal');
    $active_academic_year = get_active_academic_year();
    $iconHref = BASE_URL . ltrim(str_replace('\\', '/', $school_icon), '/');
    ?>
    <link rel="icon" id="dynamicFavicon" href="<?php echo htmlspecialchars($iconHref, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" id="dynamicAppleIcon" href="<?php echo htmlspecialchars($iconHref, ENT_QUOTES, 'UTF-8'); ?>">

    <script>
        window.APP_CONFIG = {
            baseUrl: <?php echo json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            adminJsVer: <?php echo json_encode(asset_ver('assets/js/admin.js')); ?>
        };
        window.SCHOOL_SETTINGS = <?php echo json_encode(['nama' => $school_name, 'icon' => $school_icon], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
        window.ACTIVE_ACADEMIC_YEAR = <?php echo json_encode($active_academic_year, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo asset_ver('assets/css/app.css'); ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?php echo asset_ver('assets/css/login.css'); ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo asset_ver('assets/css/dashboard.css'); ?>">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo asset_ver('assets/css/admin.css'); ?>">
</head>
<body>
    <!-- Global Loading Overlay -->
    <div id="globalLoader" class="global-loader">
        <div class="loader-content">
            <div class="loader-spinner">
                <svg viewBox="0 0 50 50" class="spinner-svg">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>
                </svg>
            </div>
            <p class="loader-text">Memuat...</p>
        </div>
    </div>

    <!-- App Container -->
    <div id="app">
        <!-- Content will be injected by SPA router -->
    </div>

    <!-- Custom Modal Container -->
    <div id="modalContainer"></div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Scripts (admin.js is lazy-loaded on #/admin navigation) -->
    <script src="assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="assets/js/core-utils.js?v=<?php echo asset_ver('assets/js/core-utils.js'); ?>"></script>
    <script src="assets/js/modal.js?v=<?php echo asset_ver('assets/js/modal.js'); ?>"></script>
    <script src="assets/js/pagination.js?v=<?php echo asset_ver('assets/js/pagination.js'); ?>"></script>
    <script src="assets/js/app.js?v=<?php echo asset_ver('assets/js/app.js'); ?>"></script>
    <script src="assets/js/auth.js?v=<?php echo asset_ver('assets/js/auth.js'); ?>"></script>
    <script src="assets/js/dashboard.js?v=<?php echo asset_ver('assets/js/dashboard.js'); ?>"></script>
    <script src="assets/js/pwa.js?v=<?php echo asset_ver('assets/js/pwa.js'); ?>"></script>
</body>
</html>
