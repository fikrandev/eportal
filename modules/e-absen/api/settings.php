<?php
/**
 * E-Absen Settings API
 * Mengelola konfigurasi & proxy status WhatsApp Gateway
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
    case 'wa_status':
        checkWaStatusProxy();
        break;
    case 'wa_test':
        testWaMessageProxy();
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

/**
 * Proxy status check to local Node.js server via server cURL
 */
function checkWaStatusProxy() {
    $stmt = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_gateway_url'");
    $url = $stmt->fetchColumn() ?: 'http://localhost:3000/send';
    
    $statusUrl = str_replace('/send', '/status', $url);
    
    $ch = curl_init($statusUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        json_response(200, true, 'Status WA dimuat', $data);
    } else {
        json_response(200, false, 'Server WA Node.js tidak merespon', [
            'isReady' => false,
            'qr' => ''
        ]);
    }
}

/**
 * Proxy test message to local Node.js server via server cURL
 */
function testWaMessageProxy() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $number = isset($input['number']) ? trim($input['number']) : '';
    $message = isset($input['message']) ? trim($input['message']) : 'Halo! Ini adalah pesan pengujian WhatsApp Gateway dari E-Portal.';
    
    if (empty($number)) {
        json_response(400, false, 'Nomor HP wajib diisi.');
    }
    
    $stmt = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_gateway_url'");
    $url = $stmt->fetchColumn() ?: 'http://localhost:3000/send';
    
    $postData = json_encode(['number' => $number, 'message' => $message]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($postData)]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $resData = json_decode($response, true);
        if ($resData && !empty($resData['success'])) {
            json_response(200, true, 'Pesan percobaan berhasil terkirim ke WhatsApp!');
        } else {
            $msg = isset($resData['message']) ? $resData['message'] : 'Gagal mengirim pesan via Node.js';
            json_response(400, false, $msg);
        }
    } else {
        json_response(500, false, 'Server WA (Node.js) di port 3000 tidak merespon. Pastikan Node server.js sedang berjalan di server.');
    }
}
