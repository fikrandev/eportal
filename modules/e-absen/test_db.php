<?php
require 'E:/xampp/htdocs/eportal/api/config.php';
$stmt = db()->query("SHOW TABLES LIKE 'absen_mesin'");
var_dump($stmt->fetchAll());
