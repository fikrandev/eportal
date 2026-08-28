<?php
require_once __DIR__ . '/../../api/config.php';

header('Content-Type: application/json');

$school_icon = get_setting('icon_sekolah', 'assets/icons/icon-192.png');
$icon_url = BASE_URL . $school_icon;

$manifest = [
    "name" => "E-Curriculum App",
    "short_name" => "E-Curriculum",
    "description" => "Aplikasi Jurnal Mengajar & E-Curriculum",
    "start_url" => ".",
    "scope" => ".",
    "display" => "standalone",
    "background_color" => "#F4F6F9",
    "theme_color" => "#2563EB",
    "orientation" => "portrait-primary",
    "icons" => [
        [
            "src" => $icon_url,
            "sizes" => "192x192 512x512",
            "type" => "image/png",
            "purpose" => "any maskable"
        ]
    ],
    "categories" => ["education", "productivity"]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
