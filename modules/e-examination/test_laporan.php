<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'hasil_ujian';
$_GET['ujian_id'] = 1;

// Mock authorization
function exam_require_admin_or_guru() {
    return true;
}

try {
    require 'e:/xampp/htdocs/eportal/modules/e-examination/api/laporan.php';
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
