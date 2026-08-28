<?php
/**
 * ZKLibrary - PHP ZKTeco / Interactive Fingerprint Device Library
 * Communicates with devices over UDP/TCP on Port 4370.
 * Basic implementation to fetch attendance logs.
 */

class ZKLibrary {
    public $ip;
    public $port;
    public $zkclient;
    public $session_id = 0;
    
    private $connected = false;
    
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ENABLEDEVICE = 1002;
    const CMD_DISABLEDEVICE = 1003;
    const CMD_ATTLOG_RRQ = 13;
    const CMD_CLEAR_ATTLOG = 14;
    const CMD_ACK_OK = 2000;
    const CMD_ACK_ERROR = 2001;
    const CMD_ACK_DATA = 2002;

    public function __construct($ip, $port = 4370) {
        $this->ip = $ip;
        $this->port = $port;
    }

    public function connect() {
        if ($this->ip == null || $this->port == null) return false;
        
        // Try UDP connection first as it is standard for port 4370
        $this->zkclient = @fsockopen("udp://" . $this->ip, $this->port, $errno, $errstr, 5);
        if (!$this->zkclient) {
            // Try TCP fallback
            $this->zkclient = @fsockopen($this->ip, $this->port, $errno, $errstr, 5);
        }
        
        if (!$this->zkclient) {
            return false;
        }
        
        stream_set_blocking($this->zkclient, true);
        stream_set_timeout($this->zkclient, 5);
        
        $command = self::CMD_CONNECT;
        $command_string = pack('H*', '5050827d08000000') . pack('v', $command) . pack('v', 0) . pack('v', 0) . pack('v', 0);
        
        $chksum = $this->createChkSum($command_string);
        $command_string = pack('H*', '5050827d08000000') . pack('v', $command) . pack('v', $chksum) . pack('v', 0) . pack('v', 0);
        
        fwrite($this->zkclient, $command_string);
        $response = fread($this->zkclient, 1024);
        
        if (strlen($response) > 0) {
            $this->session_id = hexdec(bin2hex(substr($response, 14, 2) . substr($response, 12, 2)));
            $this->connected = true;
            return true;
        }
        return false;
    }

    public function disconnect() {
        if ($this->connected) {
            $command = self::CMD_EXIT;
            $command_string = pack('H*', '5050827d08000000') . pack('v', $command) . pack('v', 0) . pack('v', $this->session_id) . pack('v', 0);
            $chksum = $this->createChkSum($command_string);
            $command_string = pack('H*', '5050827d08000000') . pack('v', $command) . pack('v', $chksum) . pack('v', $this->session_id) . pack('v', 0);
            fwrite($this->zkclient, $command_string);
            fclose($this->zkclient);
            $this->connected = false;
        }
    }

    public function getAttendance() {
        if (!$this->connected) return false;
        
        $command = self::CMD_ATTLOG_RRQ;
        $command_string = pack('H*', '5050827d08000000') . pack('v', $command) . pack('v', 0) . pack('v', $this->session_id) . pack('v', 0);
        $chksum = $this->createChkSum($command_string);
        $command_string = pack('H*', '5050827d08000000') . pack('v', $command) . pack('v', $chksum) . pack('v', $this->session_id) . pack('v', 0);
        
        fwrite($this->zkclient, $command_string);
        $response = fread($this->zkclient, 1024);
        
        if (strlen($response) > 0) {
            $ack = hexdec(bin2hex(substr($response, 8, 2) . substr($response, 10, 2)));
            if ($ack == self::CMD_ACK_OK || $ack == self::CMD_ACK_DATA) {
                return $this->parseAttendanceData();
            }
        }
        return false;
    }

    private function parseAttendanceData() {
        $attendances = [];
        $bytes = $this->recvData();
        
        if ($bytes && strlen($bytes) > 0) {
            // Simplified parsing for standard ZKTeco packet
            // Usually fixed length chunks (40 bytes per log)
            $offset = 12; // Skip header
            $len = strlen($bytes);
            
            while ($offset < $len) {
                // Different firmware has different structure. 
                // This is a simplified fallback that parses common Interactive/ZKTeco logs.
                // Format: PIN (usually string or int), VerifyType (byte), Time (encoded 4 bytes), Status (byte)
                
                $pin = trim(substr($bytes, $offset, 24));
                $pin = preg_replace('/[^\x20-\x7E]/', '', $pin); // Clean non-ascii
                
                if (empty($pin)) {
                    $offset += 40;
                    continue;
                }
                
                $verify = ord(substr($bytes, $offset + 24, 1));
                
                $timeData = substr($bytes, $offset + 26, 4);
                if (strlen($timeData) == 4) {
                    $timeDec = hexdec(bin2hex(strrev($timeData)));
                    $time = $this->decodeTime($timeDec);
                } else {
                    $time = date('Y-m-d H:i:s');
                }
                
                $status = ord(substr($bytes, $offset + 30, 1));
                
                $attendances[] = [
                    'uid' => $pin,
                    'id' => $pin,
                    'state' => $status, // 0: Check-In, 1: Check-Out, etc.
                    'timestamp' => $time,
                    'type' => $verify // 0: Password, 1: Fingerprint, 2: Card
                ];
                
                $offset += 40;
            }
        }
        return $attendances;
    }

    private function recvData() {
        $data = '';
        stream_set_blocking($this->zkclient, false);
        $timeout = time() + 3; // 3 seconds timeout for receiving data chunks
        
        while (time() < $timeout) {
            $chunk = fread($this->zkclient, 8192);
            if ($chunk) {
                $data .= $chunk;
                $timeout = time() + 3; // Reset timeout if we got data
            } else {
                if (strlen($data) > 0) break;
            }
            usleep(100000); // 100ms
        }
        stream_set_blocking($this->zkclient, true);
        return $data;
    }

    private function createChkSum($p) {
        $l = strlen($p);
        $chksum = 0;
        $i = 0;
        while ($l > 1) {
            $chksum += hexdec(bin2hex(substr($p, $i + 1, 1) . substr($p, $i, 1)));
            $i += 2;
            $l -= 2;
        }
        if ($l > 0) {
            $chksum += hexdec(bin2hex(substr($p, $i, 1)));
        }
        while ($chksum > 0xFFFF) {
            $chksum = ($chksum & 0xFFFF) + ($chksum >> 16);
        }
        $chksum = ~$chksum;
        return $chksum & 0xFFFF;
    }

    private function decodeTime($t) {
        $second = $t % 60;
        $t = $t / 60;
        $minute = $t % 60;
        $t = $t / 60;
        $hour = $t % 24;
        $t = $t / 24;
        $day = $t % 31 + 1;
        $t = $t / 31;
        $month = $t % 12 + 1;
        $t = $t / 12;
        $year = floor($t + 2000);
        
        return sprintf("%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $minute, $second);
    }
}
