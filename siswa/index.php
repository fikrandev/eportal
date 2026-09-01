<?php
/**
 * Siswa App — PWA Entry Point
 * Standalone mobile app for students
 * Attendance, Leave Requests, and BK Notes
 */
require_once __DIR__ . '/../api/config.php';

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="description" content="Siswa App — E-Portal">
    <meta name="theme-color" content="#1565C0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Siswa App">

    <title>Siswa App — <?php echo htmlspecialchars($school_name); ?></title>

    <?php if($school_icon): ?>
    <link rel="icon" href="<?php echo BASE_URL . htmlspecialchars($school_icon); ?>">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL . htmlspecialchars($school_icon); ?>">
    <?php endif; ?>

    <link rel="manifest" href="manifest.php">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/siswa.css?v=<?php echo time(); ?>">
    
    <script>
        window.APP_CONFIG = { baseUrl: "<?php echo BASE_URL; ?>siswa/" };
    </script>
</head>
<body>

    <!-- App Container -->
    <div id="app" class="app-container">
        <!-- Content injected via JS SPA routing -->
    </div>

    <!-- Modals & Toasts Container -->
    <div id="modalContainer"></div>
    <div id="toastContainer" class="toast-container"></div>

    <!-- Scripts -->
    <script src="../assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="assets/js/siswa.js?v=<?php echo time(); ?>"></script>

    <!-- Register PWA Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./sw.js')
                    .then(reg => console.log('Siswa SW registered.', reg))
                    .catch(err => console.log('Siswa SW registration failed: ', err));
            });
        }
    </script>
</body>
</html>
