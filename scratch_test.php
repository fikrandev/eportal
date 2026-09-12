<?php
require_once 'api/config.php';
$stmt = db()->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($tables);
