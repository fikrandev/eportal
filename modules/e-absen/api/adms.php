<?php
/**
 * ADMS Receiver API untuk ZKTeco Cloud Server
 * Diletakkan di /modules/e-absen/api/adms.php, di-route via .htaccess dari /iclock/cdata dan /iclock/getrequest
 */

require_once __DIR__ . '/../../../api/config.php';

// Fungsi helper untuk WA Gateway asinkron
function triggerWAGateway($phone, $message) {
    $stmt = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_gateway_url'");
    $url = $stmt->fetchColumn();
    if (!$url) return;

    $data = json_encode(['number' => $phone, 'message' => $message]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($data)]);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500); 
    curl_exec($ch);
    curl_close($ch);
}

// 1. GET /iclock/cdata - Initialization (Handshake)
// Query params: SN=...&options=all&pushver=...
if ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], '/iclock/cdata') !== false) {
    header("Content-Type: text/plain");
    echo "GET OPTION FROM: " . (isset($_GET['SN']) ? $_GET['SN'] : 'UNKNOWN') . "\n";
    echo "Stamp=9999\n";
    echo "OpStamp=9999\n";
    echo "ErrorDelay=60\n";
    echo "Delay=30\n";
    echo "TransTimes=00:00;14:00\n";
    echo "TransInterval=1\n";
    echo "TransFlag=1111000000\n";
    echo "Realtime=1\n";
    echo "Encrypt=0\n";
    exit;
}

// 2. GET /iclock/getrequest - Get command from Server
// Query params: SN=...
if ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], '/iclock/getrequest') !== false) {
    header("Content-Type: text/plain");
    // Karena kita tidak akan push command balik ke mesin, kembalikan OK
    echo "OK";
    exit;
}

// 3. POST /iclock/cdata - Push Data
// Query params: SN=...&table=ATTLOG / OPERLOG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/iclock/cdata') !== false) {
    header("Content-Type: text/plain");
    
    $sn = isset($_GET['SN']) ? trim($_GET['SN']) : '';
    $table = isset($_GET['table']) ? trim($_GET['table']) : '';
    $body = file_get_contents("php://input");
    
    if (empty($sn)) {
        echo "OK"; // Ignore if no SN
        exit;
    }
    
    // Update last_sync for this machine based on SN
    try {
        $stmtMesin = db()->prepare("SELECT id, status FROM absen_mesin WHERE sn = ?");
        $stmtMesin->execute([$sn]);
        $mesin = $stmtMesin->fetch();
        
        if ($mesin) {
            db()->prepare("UPDATE absen_mesin SET last_sync = NOW() WHERE id = ?")->execute([$mesin['id']]);
        }
    } catch (PDOException $e) {
        // Ignore DB error, proceed to parse
    }
    
    if ($table === 'ATTLOG') {
        // Format body (Tab Separated):
        // PIN  Timestamp   State   Verify_Type WorkCode
        // 1    2023-10-10 08:00:00 0   1   0
        
        $lines = explode("\n", $body);
        $insertedCount = 0;
        
        $waTemplateStmt = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_message_template'");
        $waTemplate = $waTemplateStmt->fetchColumn();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $cols = explode("\t", $line);
            if (count($cols) >= 4) {
                $pin = $cols[0];
                $timestamp = $cols[1];
                $state = $cols[2];
                $verifyType = $cols[3];
                $mesinId = $mesin ? $mesin['id'] : 0;
                
                try {
                    $stmtInsert = db()->prepare("
                        INSERT IGNORE INTO absen_logs (mesin_id, mesin_pin, waktu_absen, status_absen, verify_type)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmtInsert->execute([$mesinId, $pin, $timestamp, $state, $verifyType]);
                    
                    if ($stmtInsert->rowCount() > 0 && $waTemplate) {
                        // Send WA if new log & student found
                        $stmtSiswa = db()->prepare("SELECT nama, no_hp_ortu FROM students WHERE nis = ? AND no_hp_ortu IS NOT NULL AND no_hp_ortu != ''");
                        $stmtSiswa->execute([$pin]);
                        $siswa = $stmtSiswa->fetch();
                        
                        if ($siswa) {
                            $statusText = $state == 0 ? 'Hadir (Masuk)' : 'Pulang';
                            $msg = str_replace(
                                ['{nama}', '{status_absen}', '{waktu}'], 
                                [$siswa['nama'], $statusText, $timestamp], 
                                $waTemplate
                            );
                            triggerWAGateway($siswa['no_hp_ortu'], $msg);
                        }
                    }
                } catch (PDOException $e) {
                    // Skip errors per row
                }
            }
        }
    }
    
    // Selalu balas OK agar mesin menganggap data sukses diproses
    echo "OK";
    exit;
}

// Fallback
echo "OK";
