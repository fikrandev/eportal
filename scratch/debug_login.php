<?php
require 'e:/xampp/htdocs/eportal/api/config.php';

$nis = '12840';
$raw_tgl = '27092007';

$possible_dates = [];
if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw_tgl, $m)) {
    $possible_dates[] = $m[1] . '-' . $m[2] . '-' . $m[3];
}
elseif (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $raw_tgl, $m)) {
    $possible_dates[] = $m[3] . '-' . $m[2] . '-' . $m[1];
}

var_dump($possible_dates);

$activeYear = get_active_academic_year();
$activeYearId = (int)($activeYear['id'] ?? 0);
var_dump($activeYearId);

foreach ($possible_dates as $tgl) {
    $sql = "SELECT * FROM students WHERE nis = ? AND tanggal_lahir = ? AND status = 1";
    $params = [$nis, $tgl];
    if ($activeYearId > 0) {
        $sql .= " ORDER BY (academic_year_id = ?) DESC, academic_year_id DESC, id DESC LIMIT 1";
        $params[] = $activeYearId;
    } else {
        $sql .= " ORDER BY academic_year_id DESC, id DESC LIMIT 1";
    }
    
    var_dump($sql, $params);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $res = $stmt->fetch();
    var_dump($res !== false);
}
