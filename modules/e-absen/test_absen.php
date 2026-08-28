<?php
require 'e:/xampp/htdocs/eportal/api/config.php';

try {
    // 1. Add no_hp_ortu to students table
    $stmt = db()->query("SHOW COLUMNS FROM students LIKE 'no_hp_ortu'");
    if (!$stmt->fetch()) {
        db()->exec("ALTER TABLE students ADD COLUMN no_hp_ortu VARCHAR(30) NULL AFTER email");
        echo "Added no_hp_ortu to students.\n";
    }

    // 2. Add WA Gateway settings to settings table
    $wa_settings = [
        ['wa_gateway_url', 'http://localhost:3000/send', 'text', 'Endpoint URL WA Gateway Lokal'],
        ['wa_message_template', 'Halo Orang Tua/Wali dari {nama}. Menginformasikan bahwa ananda telah {status_absen} di sekolah pada {waktu}. Terima Kasih.', 'text', 'Template pesan WA absen']
    ];

    foreach ($wa_settings as $s) {
        $stmt = db()->prepare("SELECT id FROM settings WHERE setting_key = ?");
        $stmt->execute([$s[0]]);
        if (!$stmt->fetch()) {
            $insert = db()->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, keterangan) VALUES (?, ?, ?, ?)");
            $insert->execute($s);
            echo "Added setting {$s[0]}.\n";
        }
    }
    echo "Database schema updated successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
