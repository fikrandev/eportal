<?php
require_once __DIR__ . '/../../../api/config.php';
$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');
$icon_url = !empty($school_icon) ? BASE_URL . $school_icon : BASE_URL . 'assets/icons/icon-192.png';

header('Content-Type: application/json');
echo json_encode([
    'name' => 'CBT - ' . $school_name,
    'short_name' => 'CBT Ujian',
    'description' => 'Aplikasi Ujian CBT',
    'start_url' => './login.php',
    'scope' => './',
    'display' => 'standalone',
    'background_color' => '#f8fafc',
    'theme_color' => '#2563EB',
    'orientation' => 'portrait-primary',
    'icons' => [
        ['src' => $icon_url, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => $icon_url, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable']
    ]
], JSON_UNESCAPED_SLASHES);
