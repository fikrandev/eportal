<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/migration_helper.php';

// Jalankan auto-migration jika ada versi baru database/tabel
run_auto_migrations();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
    ?>
    <link rel="icon" id="dynamicFavicon" href="<?php echo BASE_URL . $school_icon; ?>">
    <link rel="apple-touch-icon" id="dynamicAppleIcon" href="<?php echo BASE_URL . $school_icon; ?>">

    <script>
        window.APP_CONFIG = {
            baseUrl: "<?php echo BASE_URL; ?>"
        };
        window.SCHOOL_SETTINGS = {
            nama: "<?php echo addslashes($school_name); ?>",
            icon: "<?php echo addslashes($school_icon); ?>"
        };
        window.ACTIVE_ACADEMIC_YEAR = <?php echo json_encode($active_academic_year, JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
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

    <!-- Scripts -->
    <script src="assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="assets/js/modal.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/auth.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/dashboard.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/admin.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/pwa.js?v=<?php echo time(); ?>"></script>
</body>
</html>
