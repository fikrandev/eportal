<?php
require 'e:/xampp/htdocs/eportal/api/config.php';

// 1. Get a student
$stmt = db()->query("SELECT nis, tanggal_lahir FROM students WHERE status = 1 LIMIT 1");
$student = $stmt->fetch();

if (!$student) {
    die("No active student found in DB\n");
}

echo "Found student: NIS " . $student['nis'] . ", DOB " . $student['tanggal_lahir'] . "\n";

// Convert DOB to DDMMYYYY
$dobTime = strtotime($student['tanggal_lahir']);
$dobFormatted = date('dmY', $dobTime);
echo "Formatted DOB for login: " . $dobFormatted . "\n";

// 2. Simulate API Request
$url = 'http://localhost/eportal/siswa/api/auth.php'; // or directly include it?
// Let's just directly test the API logic by overriding $_SERVER and $_POST.
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['nis'] = $student['nis'];
$_POST['tanggal_lahir'] = $dobFormatted;

echo "Running API...\n";
ob_start();
require 'e:/xampp/htdocs/eportal/siswa/api/auth.php';
$output = ob_get_clean();

echo "API Response:\n";
echo $output;
