<?php
require 'e:/xampp/htdocs/eportal/api/config.php';
$stmt = db()->query("SELECT guru_wali FROM students WHERE guru_wali IS NOT NULL AND status=1 GROUP BY guru_wali");
$gw = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($gw);
$stmt = db()->query("SELECT nama_lengkap FROM users WHERE role='guru'");
$us = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($us);
