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
    case 'wa_logout':
        disconnectWaProxy();
        break;
    case 'get_wa_groups':
        getWaGroupsProxy();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * Normalizes and builds WhatsApp Gateway API endpoint URL
 */
function getWaEndpointUrl($endpoint = 'send') {
    $stmt = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_gateway_url'");
    $url = $stmt ? $stmt->fetchColumn() : null;
    if (!$url) $url = 'http://localhost:3000/send';
    
    $url = trim($url);
    // Strip trailing endpoints if already present in base URL
    $baseUrl = preg_replace('#/(send|status|groups|logout)/?$#', '', $url);
    $baseUrl = rtrim($baseUrl, '/');
    
    if (empty($baseUrl)) {
        $baseUrl = 'http://localhost:3000';
    }
    
    return $baseUrl . '/' . ltrim($endpoint, '/');
}

function getWaSettings() {
    try {
        $keys = ['wa_gateway_url', 'wa_message_template', 'wa_group_guru_id'];
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
    $groupGuruId = isset($input['wa_group_guru_id']) ? trim($input['wa_group_guru_id']) : '';
    
    try {
        db()->beginTransaction();
        
        upsert_setting('wa_gateway_url', $url ?: 'http://localhost:3000/send', 'text', 'URL Endpoint WhatsApp Gateway');
        upsert_setting('wa_message_template', $template, 'text', 'Template Pesan Notifikasi Siswa');
        upsert_setting('wa_group_guru_id', $groupGuruId, 'text', 'Target WA Group ID untuk Laporan Absensi Guru');
        
        db()->commit();
        json_response(200, true, 'Pengaturan WhatsApp Gateway berhasil disimpan.');
    } catch (PDOException $e) {
        if (db()->inTransaction()) db()->rollBack();
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getWaGroupsProxy() {
    $groupsUrl = getWaEndpointUrl('groups');
    
    $ch = curl_init($groupsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20); // 20s to allow browser to query all chats
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && ($httpCode === 200 || $httpCode === 503 || $httpCode === 400)) {
        $resData = json_decode($response, true);
        if ($resData && !empty($resData['success'])) {
            json_response(200, true, 'Daftar Grup WA dimuat', $resData['groups'] ?? []);
        } else {
            $msg = isset($resData['message']) ? $resData['message'] : 'Gagal memuat grup. Pastikan status WhatsApp sudah TERHUBUNG (Scan Barcode).';
            json_response(400, false, $msg, []);
        }
    } else {
        json_response(500, false, 'Server WhatsApp Gateway (' . $groupsUrl . ') tidak merespon: ' . ($err ?: 'Koneksi ditolak/Offline'), []);
    }
}

/**
 * Proxy status check to local Node.js server via server cURL
 */
function checkWaStatusProxy() {
    $statusUrl = getWaEndpointUrl('status');
    
    $ch = curl_init($statusUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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
        json_response(400, false, 'Nomor HP atau ID Grup tujuan wajib diisi.');
    }
    
    $sendUrl = getWaEndpointUrl('send');
    $postData = json_encode(['number' => $number, 'message' => $message]);
    
    $ch = curl_init($sendUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($postData)]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $err = curl_error($ch);
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
        json_response(500, false, 'Server WhatsApp di ' . $sendUrl . ' tidak merespon: ' . ($err ?: 'Koneksi ditolak'));
    }
}

/**
 * Proxy logout / disconnect session to local Node.js server
 */
function disconnectWaProxy() {
    $logoutUrl = getWaEndpointUrl('logout');
    
    $ch = curl_init($logoutUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: 2']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($response) {
        $resData = json_decode($response, true);
        if ($resData && !empty($resData['success'])) {
            json_response(200, true, $resData['message'] ?? 'Koneksi WhatsApp berhasil diputuskan. Silakan scan Barcode baru.');
        } else {
            $msg = isset($resData['message']) ? $resData['message'] : 'Gagal memutuskan koneksi via Node.js';
            json_response(400, false, $msg);
        }
    } else {
        json_response(500, false, 'Server WA Node.js (' . $logoutUrl . ') tidak merespon: ' . ($err ?: 'Connection failed'));
    }
}
