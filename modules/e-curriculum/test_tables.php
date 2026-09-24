<?php
require 'e:/xampp/htdocs/eportal/api/config.php';
$stmt = db()->query("DESCRIBE sch_kelas");
var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = db()->query("DESCRIBE acad_kelas");
var_dump($stmt2->fetchAll(PDO::FETCH_ASSOC));
