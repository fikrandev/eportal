<?php
/**
 * ADMS Receiver API untuk ZKTeco Cloud Server
 * Diletakkan di /modules/e-absen/api/adms.php, di-route via .htaccess dari /iclock/cdata dan /iclock/getrequest
 */

require_once __DIR__ . '/../../../api/config.php';

// DEBUG LOG — Catat semua request dari mesin absen
$RAW_BODY = file_get_contents("php://input");
$debugLog = __DIR__ . '/adms_debug.log';
$logEntry = date('Y-m-d H:i:s') . " | " . $_SERVER['REQUEST_METHOD'] . " | " . $_SERVER['REQUEST_URI'] . " | GET: " . json_encode($_GET) . " | BODY: " . substr($RAW_BODY, 0, 500) . "\n";
file_put_contents($debugLog, $logEntry, FILE_APPEND);

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

// Detect route — support both .htaccess rewrite and direct folder access
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$isCdata = (strpos($requestUri, '/iclock/cdata') !== false) || (strpos($scriptName, '/iclock/cdata') !== false);
$isGetrequest = (strpos($requestUri, '/iclock/getrequest') !== false) || (strpos($scriptName, '/iclock/getrequest') !== false);

// Fallback: jika tidak terdeteksi sama sekali, cek dari SCRIPT_FILENAME (direct include)
if (!$isCdata && !$isGetrequest) {
    $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
    if (strpos($scriptFile, 'iclock') !== false) {
        if (strpos($scriptFile, 'cdata') !== false) $isCdata = true;
        if (strpos($scriptFile, 'getrequest') !== false) $isGetrequest = true;
    }
}

// 1. GET /iclock/cdata - Initialization (Handshake)
// Query params: SN=...&options=all&pushver=...
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $isCdata) {
    header("Content-Type: text/plain");
    
    // Extract SN (case-insensitive)
    $handshakeSn = '';
    foreach ($_GET as $key => $val) {
        if (strtolower($key) === 'sn') { $handshakeSn = trim($val); break; }
    }
    
    // Auto-register: jika SN belum ada di database, otomatis daftarkan
    if ($handshakeSn) {
        try {
            $stmtCheck = db()->prepare("SELECT id FROM absen_mesin WHERE sn = ?");
            $stmtCheck->execute([$handshakeSn]);
            if (!$stmtCheck->fetch()) {
                // Belum terdaftar, auto-register
                $stmtIns = db()->prepare("INSERT INTO absen_mesin (nama_mesin, ip_address, port, sn, com_key, status, last_sync) VALUES (?, '', 4370, ?, '0', 1, NOW())");
                $stmtIns->execute(['Mesin ' . $handshakeSn, $handshakeSn]);
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " | AUTO-REGISTER | New machine SN=$handshakeSn\n", FILE_APPEND);
            } else {
                // Sudah terdaftar, update last_sync
                db()->prepare("UPDATE absen_mesin SET last_sync = NOW() WHERE sn = ?")->execute([$handshakeSn]);
            }
        } catch (Exception $e) {
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " | AUTO-REGISTER ERROR | " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
    
    echo "GET OPTION FROM: " . ($handshakeSn ?: 'UNKNOWN') . "\n";
    echo "Stamp=0\n";
    echo "OpStamp=0\n";
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
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $isGetrequest) {
    header("Content-Type: text/plain");
    
    // Cek SN mesin
    $grSn = '';
    foreach ($_GET as $key => $val) {
        if (strtolower($key) === 'sn') { $grSn = trim($val); break; }
    }
    
    // Cek apakah mesin ini sudah pernah kirim data
    $needSync = false;
    if ($grSn) {
        try {
            // Cek apakah mesin sudah terdaftar
            $stmtMesin = db()->prepare("SELECT id FROM absen_mesin WHERE sn = ?");
            $stmtMesin->execute([$grSn]);
            $mesinRow = $stmtMesin->fetch();
            
            if (!$mesinRow) {
                // Auto-register mesin baru
                $stmtIns = db()->prepare("INSERT INTO absen_mesin (nama_mesin, ip_address, port, sn, com_key, status, last_sync) VALUES (?, '', 4370, ?, '0', 1, NOW())");
                $stmtIns->execute(['Mesin ' . $grSn, $grSn]);
                $needSync = true; // Mesin baru, minta kirim semua data
                file_put_contents($debugLog, date('Y-m-d H:i:s') . " | AUTO-REGISTER (getrequest) | New machine SN=$grSn\n", FILE_APPEND);
            } else {
                // Mesin sudah terdaftar, cek apakah perlu sync
                try {
                    $stmtCheck = db()->prepare("SELECT id FROM absen_mesin WHERE sn = ? AND (force_sync = 1 OR last_sync IS NULL)");
                    $stmtCheck->execute([$grSn]);
                    if ($stmtCheck->fetch()) {
                        $needSync = true;
                    }
                } catch (Exception $e) {
                    $needSync = true;
                }
                
                // Cek apakah ada log dari mesin ini
                $stmtLogs = db()->prepare("SELECT COUNT(*) FROM absen_logs WHERE mesin_id = ?");
                $stmtLogs->execute([$mesinRow['id']]);
                if ((int)$stmtLogs->fetchColumn() === 0) {
                    $needSync = true;
                }
                
                // Update last_sync
                db()->prepare("UPDATE absen_mesin SET last_sync = NOW() WHERE id = ?")->execute([$mesinRow['id']]);
            }
        } catch (Exception $e) {
            // Jika kolom force_sync belum ada, paksa sync
            $needSync = true;
        }
    }
    
    if ($needSync) {
        // Kirim perintah ke mesin untuk push semua data ATTLOG
        echo "C:1:DATA UPDATE ATTLOG\n";
        
        // Log perintah yang dikirim
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " | COMMAND SENT | DATA UPDATE ATTLOG to SN=$grSn\n", FILE_APPEND);
    } else {
        echo "OK";
    }
    exit;
}

// 3. POST /iclock/cdata - Push Data
// Query params: SN=...&table=ATTLOG / OPERLOG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isCdata) {
    header("Content-Type: text/plain");
    
    // Case-insensitive SN query parameter
    $sn = '';
    foreach ($_GET as $key => $val) {
        if (strtolower($key) === 'sn') {
            $sn = trim($val);
            break;
        }
    }
    
    // Case-insensitive table query parameter
    $table = '';
    foreach ($_GET as $key => $val) {
        if (strtolower($key) === 'table') {
            $table = strtoupper(trim($val));
            break;
        }
    }
    
    $body = $RAW_BODY;
    
    if (empty($sn)) {
        echo "OK"; // Ignore if no SN
        exit;
    }
    
    // Update last_sync for this machine based on SN (auto-register if unknown)
    $mesin = null;
    try {
        $stmtMesin = db()->prepare("SELECT id, status FROM absen_mesin WHERE sn = ?");
        $stmtMesin->execute([$sn]);
        $mesin = $stmtMesin->fetch();
        
        if ($mesin) {
            db()->prepare("UPDATE absen_mesin SET last_sync = NOW() WHERE id = ?")->execute([$mesin['id']]);
        } else {
            // Auto-register mesin baru
            $stmtIns = db()->prepare("INSERT INTO absen_mesin (nama_mesin, ip_address, port, sn, com_key, status, last_sync) VALUES (?, '', 4370, ?, '0', 1, NOW())");
            $stmtIns->execute(['Mesin ' . $sn, $sn]);
            $newId = db()->lastInsertId();
            $mesin = ['id' => $newId, 'status' => 1];
            file_put_contents($debugLog, date('Y-m-d H:i:s') . " | AUTO-REGISTER (POST) | New machine SN=$sn, ID=$newId\n", FILE_APPEND);
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
        $parseErrors = 0;
        
        $waTemplateStmt = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_message_template'");
        $waTemplate = $waTemplateStmt->fetchColumn();
        
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Support multiple separators: TAB, multiple spaces, or mixed
            $cols = preg_split('/\t+/', $line);
            if (count($cols) < 4) {
                // Fallback: coba split dengan spasi (beberapa mesin pakai spasi)
                $cols = preg_split('/\s+/', $line, 6);
                // Jika split spasi menghasilkan timestamp terpisah (tanggal dan jam), gabungkan kembali
                if (count($cols) >= 5 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cols[1]) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $cols[2])) {
                    // Format: PIN YYYY-MM-DD HH:MM:SS State VerifyType
                    $cols = [$cols[0], $cols[1] . ' ' . $cols[2], $cols[3], $cols[4], isset($cols[5]) ? $cols[5] : '0'];
                }
            }
            
            if (count($cols) >= 4) {
                $pin = trim($cols[0]);
                $timestamp = trim($cols[1]);
                $state = trim($cols[2]);
                $verifyType = trim($cols[3]);
                $mesinId = $mesin ? $mesin['id'] : 0;
                
                try {
                    $stmtInsert = db()->prepare("
                        INSERT IGNORE INTO absen_logs (mesin_id, mesin_pin, waktu_absen, status_absen, verify_type)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmtInsert->execute([$mesinId, $pin, $timestamp, $state, $verifyType]);
                    if ($stmtInsert->rowCount() > 0) $insertedCount++;
                    
                    // Look up student by NIS, stripping leading zeros for robust matching
                    $stmtSiswa = db()->prepare("
                        SELECT id, nama, no_hp_ortu, kelas 
                        FROM students 
                        WHERE TRIM(LEADING '0' FROM nis) = TRIM(LEADING '0' FROM ?) AND status = 1 
                        LIMIT 1
                    ");
                    $stmtSiswa->execute([$pin]);
                    $siswa = $stmtSiswa->fetch();
                    
                    if ($siswa) {
                        // 1. Automatically register student attendance in acad_absensi (jam_ke = 0 for daily attendance)
                        try {
                            $stmtKelas = db()->prepare("SELECT id FROM sch_kelas WHERE nama_kelas = ? LIMIT 1");
                            $stmtKelas->execute([$siswa['kelas']]);
                            $kelasId = $stmtKelas->fetchColumn() ?: 0;
                            
                            if ($year_id > 0) {
                                $tanggal_absen = date('Y-m-d', strtotime($timestamp));
                                
                                $stmtAbsensi = db()->prepare("
                                    INSERT INTO acad_absensi (student_id, kelas_id, academic_year_id, tanggal, jam_ke, status, keterangan, dicatat_oleh)
                                    VALUES (?, ?, ?, ?, 0, 'H', 'Hadir via Fingerprint', NULL)
                                    ON DUPLICATE KEY UPDATE status = 'H', keterangan = 'Hadir via Fingerprint'
                                ");
                                $stmtAbsensi->execute([$siswa['id'], $kelasId, $year_id, $tanggal_absen]);
                            }
                        } catch (Exception $ex) {
                            // Don't let class attendance insertion fail the rest of the flow
                        }
                        
                        // 2. Trigger WA Gateway for parents if new log & parents phone number is set
                        if ($stmtInsert->rowCount() > 0 && $waTemplate && !empty($siswa['no_hp_ortu'])) {
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
