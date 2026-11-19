<?php
/**
 * E-Absen Settings API
 * Mengelola konfigurasi & proxy status WhatsApp Gateway
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/../../../api/wa_gateway_client.php';
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
acad_require_admin($user);

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_wa':
        getWaSettings();
        break;
    case 'save_wa':
        saveWaSettings();
        break;
    case 'reset_wa_broadcast_today':
        resetWaBroadcastToday();
        break;
    case 'send_wa_rekap_now':
        sendWaRekapNow();
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
    return wa_gateway_endpoint_url($endpoint);
}

function getWaSettings() {
    try {
        // Cek juga auto-broadcast opportunistically saat halaman WA Gateway dibuka
        try {
            require_once __DIR__ . '/../../e-curriculum/api/wa_group_helper.php';
            checkAndSendWaGroupGuruAbsensiBatch();
        } catch (Exception $ex) {}

        $keys = [
            'wa_gateway_url', 
            'wa_message_template', 
            'wa_group_guru_id',
            'wa_guru_cutoff_masuk',
            'wa_guru_cutoff_pulang',
            'wa_guru_last_sent_date_masuk',
            'wa_guru_last_sent_time_masuk',
            'wa_guru_last_sent_date_pulang',
            'wa_guru_last_sent_time_pulang'
        ];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        
        $stmt = db()->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
        $stmt->execute($keys);
        
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // Default jam broadcast jika belum ada di database
        if (empty($settings['wa_guru_cutoff_masuk'])) {
            $settings['wa_guru_cutoff_masuk'] = '06:30';
        } else {
            $settings['wa_guru_cutoff_masuk'] = substr($settings['wa_guru_cutoff_masuk'], 0, 5);
        }

        if (empty($settings['wa_guru_cutoff_pulang'])) {
            $settings['wa_guru_cutoff_pulang'] = '17:00';
        } else {
            $settings['wa_guru_cutoff_pulang'] = substr($settings['wa_guru_cutoff_pulang'], 0, 5);
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

    $cutoffM = isset($input['wa_guru_cutoff_masuk']) ? trim($input['wa_guru_cutoff_masuk']) : '06:30';
    if (strlen($cutoffM) === 5) $cutoffM .= ':00';
    $cutoffP = isset($input['wa_guru_cutoff_pulang']) ? trim($input['wa_guru_cutoff_pulang']) : '17:00';
    if (strlen($cutoffP) === 5) $cutoffP .= ':00';
    
    try {
        db()->beginTransaction();
        
        upsert_setting('wa_gateway_url', $url ?: 'http://localhost:3000/send', 'text', 'URL Endpoint WhatsApp Gateway');
        upsert_setting('wa_message_template', $template, 'text', 'Template Pesan Notifikasi Siswa');
        upsert_setting('wa_group_guru_id', $groupGuruId, 'text', 'Target WA Group ID untuk Laporan Absensi Guru');
        upsert_setting('wa_guru_cutoff_masuk', $cutoffM, 'text', 'Batas waktu kirim WA otomatis absen pagi');
        upsert_setting('wa_guru_cutoff_pulang', $cutoffP, 'text', 'Batas waktu kirim WA otomatis absen pulang');
        
        db()->commit();

        // Cek langsung apakah jadwal broadcast sekarang terpenuhi
        try {
            require_once __DIR__ . '/../../e-curriculum/api/wa_group_helper.php';
            checkAndSendWaGroupGuruAbsensiBatch();
        } catch (Exception $ex) {}

        json_response(200, true, 'Pengaturan WhatsApp Gateway berhasil disimpan.');
    } catch (PDOException $e) {
        if (db()->inTransaction()) db()->rollBack();
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Reset status broadcast hari ini agar dapat dikirim ulang otomatis
 */
function resetWaBroadcastToday() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    try {
        upsert_setting('wa_guru_last_sent_date_masuk', '', 'text', 'Tanggal terakhir WA absen masuk guru dikirim');
        upsert_setting('wa_guru_last_sent_time_masuk', '', 'text', 'Waktu pengiriman WA absen masuk guru');
        upsert_setting('wa_guru_last_sent_date_pulang', '', 'text', 'Tanggal terakhir WA absen pulang guru dikirim');
        upsert_setting('wa_guru_last_sent_time_pulang', '', 'text', 'Waktu pengiriman WA absen pulang guru');

        // Jika waktu saat ini sudah melewati jam cutoff, auto-send dapat langsung terpicu
        $triggered = false;
        try {
            require_once __DIR__ . '/../../e-curriculum/api/wa_group_helper.php';
            $triggered = checkAndSendWaGroupGuruAbsensiBatch();
        } catch (Exception $ex) {}

        $msg = 'Status siaran hari ini berhasil direset!';
        if ($triggered) {
            $msg .= ' Karena waktu sekarang sudah melewati jam cutoff, laporan absen otomatis dikirimkan ke grup WA!';
        } else {
            $msg .= ' Laporan akan otomatis dikirim saat jam cutoff tiba atau saat sinkronisasi berikutnya.';
        }

        json_response(200, true, $msg);
    } catch (Exception $e) {
        json_response(500, false, 'Gagal mereset status: ' . $e->getMessage());
    }
}

/**
 * Kirim langsung (instant manual) rekap absen pagi atau pulang ke grup WA
 */
function sendWaRekapNow() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $tipe = isset($input['tipe']) && $input['tipe'] === 'pulang' ? 'pulang' : 'masuk';
    $tanggal = isset($input['tanggal']) ? trim($input['tanggal']) : date('Y-m-d');

    require_once __DIR__ . '/../../e-curriculum/api/wa_group_helper.php';
    $res = sendWaGroupAbsensiGuruDirect($tanggal, $tipe);

    if (!empty($res['success'])) {
        // Catat sebagai telah terkirim hari ini
        if ($tipe === 'masuk') {
            upsert_setting('wa_guru_last_sent_date_masuk', $tanggal, 'text', 'Tanggal terakhir WA absen masuk guru dikirim');
            upsert_setting('wa_guru_last_sent_time_masuk', date('Y-m-d H:i:s'), 'text', 'Waktu pengiriman WA absen masuk guru');
        } else {
            upsert_setting('wa_guru_last_sent_date_pulang', $tanggal, 'text', 'Tanggal terakhir WA absen pulang guru dikirim');
            upsert_setting('wa_guru_last_sent_time_pulang', date('Y-m-d H:i:s'), 'text', 'Waktu pengiriman WA absen pulang guru');
        }
        json_response(200, true, $res['message']);
    } else {
        json_response(400, false, $res['message'] ?? 'Gagal mengirim rekap absen ke grup WA.');
    }
}

function getWaGroupsProxy() {
    $groupsUrl = getWaEndpointUrl('groups');
    
    $ch = curl_init($groupsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, wa_gateway_headers());
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60s timeout
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
    curl_setopt($ch, CURLOPT_HTTPHEADER, wa_gateway_headers($postData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 65);
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
    curl_setopt($ch, CURLOPT_HTTPHEADER, wa_gateway_headers('{}'));
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
