<?php
require 'e:/xampp/htdocs/eportal/api/config.php';
$stmt = db()->query("SELECT * FROM acad_jurnal WHERE jenis_jurnal = 'guru_wali' LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
