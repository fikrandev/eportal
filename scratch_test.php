<?php
require_once __DIR__ . '/api/config.php';
$r = db()->query("SHOW INDEX FROM sch_guru")->fetchAll(PDO::FETCH_ASSOC);
print_r($r);
