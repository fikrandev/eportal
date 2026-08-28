<?php
require 'E:/xampp/htdocs/eportal/api/config.php';
$stmt = db()->query("DESCRIBE absen_mesin");
var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
