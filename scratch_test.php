<?php
require 'api/config.php';
$stmt = db()->query("DESCRIBE students");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
