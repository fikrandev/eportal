<?php
/**
 * E-Portal Settings API
 * School settings management (Superadmin only)
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get':
        getSettings();
        break;
    case 'update':
        updateSettings();
        break;
    case 'upload-icon':
        uploadSchoolIcon();
        break;
    case 'upload-kop-surat':
        uploadLetterhead();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * Get all settings
 */
function getSettings() {
    require_superadmin();

    try {
        upsert_setting('nama_sekolah', get_setting('nama_sekolah', 'E-Portal Sekolah'), 'text', 'Nama sekolah yang ditampilkan');
        upsert_setting('icon_sekolah', get_setting('icon_sekolah', ''), 'file', 'Path icon/logo sekolah');
        upsert_setting('kepala_sekolah', get_setting('kepala_sekolah', get_setting('sarpras_kepala_sekolah', '')), 'text', 'Nama kepala sekolah untuk surat');
        upsert_setting('kop_surat', get_setting('kop_surat', ''), 'file', 'Path kop surat global untuk semua modul');

        $stmt = db()->query("SELECT * FROM settings ORDER BY id ASC");
        $settings = $stmt->fetchAll();

        // Convert to key-value format
        $result = [];
        foreach ($settings as $s) {
            $result[$s['setting_key']] = [
                'value' => $s['setting_value'],
                'type' => $s['setting_type'],
                'keterangan' => $s['keterangan']
            ];
        }

        json_response(200, true, 'Settings berhasil dimuat.', $result);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Update settings
 */
function updateSettings() {
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();

    if (empty($input)) {
        json_response(400, false, 'Data settings tidak valid.');
    }

    try {
        foreach ($input as $key => $value) {
            if (!in_array($key, ['nama_sekolah', 'kepala_sekolah'], true)) {
                continue;
            }
            upsert_setting($key, sanitize($value), 'text', $key === 'kepala_sekolah' ? 'Nama kepala sekolah untuk surat' : 'Nama sekolah yang ditampilkan');
        }

        json_response(200, true, 'Settings berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Upload global letterhead.
 */
function uploadLetterhead() {
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    if (!isset($_FILES['kop_surat']) || $_FILES['kop_surat']['error'] !== UPLOAD_ERR_OK) {
        json_response(400, false, 'File kop surat harus diupload.');
    }

    $upload = handle_upload($_FILES['kop_surat'], 'letterheads/', ['jpg', 'jpeg', 'png', 'webp']);
    if (!$upload['success']) {
        json_response(400, false, $upload['message']);
    }

    if ($_FILES['kop_surat']['size'] > 900 * 1024) {
        compress_image($upload['full_path'], $upload['full_path'], 900);
    }

    $old = get_setting('kop_surat', '');
    if (!empty($old) && file_exists(__DIR__ . '/../' . $old)) {
        unlink(__DIR__ . '/../' . $old);
    }

    upsert_setting('kop_surat', $upload['path'], 'file', 'Path kop surat global untuk semua modul');

    json_response(200, true, 'Kop surat berhasil diperbarui.', [
        'path' => $upload['path']
    ]);
}

/**
 * Upload school icon
 */
function uploadSchoolIcon() {
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    if (!isset($_FILES['icon']) || $_FILES['icon']['error'] !== UPLOAD_ERR_OK) {
        json_response(400, false, 'File icon harus diupload.');
    }

    $upload = handle_upload($_FILES['icon'], 'icons/', ['jpg', 'jpeg', 'png', 'svg', 'webp']);
    if (!$upload['success']) {
        json_response(400, false, $upload['message']);
    }

    // Check size and compress if needed (> 500KB)
    if ($_FILES['icon']['size'] > 500 * 1024) {
        compress_image($upload['full_path'], $upload['full_path'], 500);
    }

    // Delete old icon if exists
    $oldIcon = get_setting('icon_sekolah', '');
    if (!empty($oldIcon) && file_exists(__DIR__ . '/../' . $oldIcon)) {
        unlink(__DIR__ . '/../' . $oldIcon);
    }

    // Update setting
    update_setting('icon_sekolah', $upload['path']);

    json_response(200, true, 'Icon sekolah berhasil diperbarui.', [
        'path' => $upload['path']
    ]);
}
