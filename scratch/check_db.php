<?php
require_once __DIR__ . '/../api/config.php';
try {
    $q = db()->query("DESCRIBE acad_ketidakhadiran")->fetchAll(PDO::FETCH_ASSOC);
    print_r($q);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
