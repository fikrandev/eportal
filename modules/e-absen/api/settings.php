<?php
/**
 * E-Absen Settings API
 * Mengelola konfigurasi WhatsApp Gateway
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
if ($user['role'] !== 'superadmin') {
    json_response(403, false, 'Akses ditolak.');
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_wa':
        getWaSettings();
        break;
    case 'save_wa':
        saveWaSettings();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function getWaSettings() {
    try {
        $keys = ['wa_gateway_url', 'wa_message_template'];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        
        $stmt = db()->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
        $stmt->execute($keys);
        
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        json_response(200, true, 'Settings dimuat', $settings);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}

function saveWaSettings() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    
    $input = get_input();
    $url = isset($input['wa_gateway_url']) ? trim($input['wa_gateway_url']) : '';
    $template = isset($input['wa_message_template']) ? trim($input['wa_message_template']) : '';
    
    try {
        db()->beginTransaction();
        
        $stmt = db()->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$url, 'wa_gateway_url']);
        $stmt->execute([$template, 'wa_message_template']);
        
        db()->commit();
        json_response(200, true, 'Pengaturan WhatsApp Gateway berhasil disimpan.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}
