<?php
/**
 * QR Code Generator for E-Xam Card
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/../../e-graduation/api/phpqrcode.php';

$data = $_GET['data'] ?? '';
$size = (int) ($_GET['size'] ?? 4);

if ($data) {
    header('Content-Type: image/png');
    QRcode::png($data, false, 'M', $size, 2);
}
