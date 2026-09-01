<?php
/**
 * Dynamic PWA Manifest for Siswa App
 * Uses school favicon/icon for the install icon
 */
require_once __DIR__ . '/../api/config.php';

$school_name = get_setting('nama_sekolah', 'E-Portal');
$school_icon = get_setting('icon_sekolah', '');

// Use absolute BASE_URL path for reliable icon resolution in PWA installer
$icon_url = !empty($school_icon) 
    ? BASE_URL . $school_icon 
    : BASE_URL . 'assets/icons/icon-192.png';

header('Content-Type: application/json');
echo json_encode([
    'name' => 'Siswa ' . $school_name,
    'short_name' => 'Siswa App',
    'description' => 'Aplikasi Siswa - Absensi & Izin',
    'start_url' => './',
    'scope' => './',
    'id' => '/eportal/siswa/',
    'display' => 'standalone',
    'background_color' => '#FFFFFF',
    'theme_color' => '#1565C0',
    'orientation' => 'portrait-primary',
    'icons' => [
        [
            'src' => $icon_url,
            'sizes' => '48x48',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $icon_url,
            'sizes' => '72x72',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $icon_url,
            'sizes' => '96x96',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $icon_url,
            'sizes' => '144x144',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $icon_url,
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $icon_url,
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ],
    'categories' => ['education', 'productivity'],
    'screenshots' => [],
    'prefer_related_applications' => false
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
