<?php
require 'api/config.php';
$stmt = db()->query("SELECT id, exam_id, student_id, username, password_plain, status FROM xam_exam_students WHERE student_id = 315");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
