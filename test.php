<?php
require 'api/config.php';
$stmt = db()->query("SELECT COUNT(*) FROM xam_exam_students WHERE status = 'OKE'");
echo "OKE count: " . $stmt->fetchColumn() . "\n";
$stmt = db()->query("SELECT COUNT(*) FROM xam_exam_students WHERE status = 'DITANGGUHKAN'");
echo "DITANGGUHKAN count: " . $stmt->fetchColumn() . "\n";
