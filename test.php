<?php
require 'api/config.php';
$stmt = db()->query("SELECT id, exam_name, status FROM xam_exams");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
