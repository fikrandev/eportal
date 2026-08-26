<?php
/**
 * Database Migration Script
 * E-Portal Global & E-Examination Module
 */
require_once __DIR__ . '/api/config.php';

header('Content-Type: text/html; charset=UTF-8');

$isCli = (php_sapi_name() === 'cli');

function log_msg($msg, $type = 'info') {
    global $isCli;
    $colors = [
        'info' => '#2563EB',
        'success' => '#16A34A',
        'error' => '#DC2626',
        'warn' => '#D97706'
    ];
    if ($isCli) {
        echo "[" . strtoupper($type) . "] " . strip_tags($msg) . "\n";
    } else {
        $color = $colors[$type] ?? '#333';
        echo "<div style='margin: 8px 0; padding: 10px 14px; border-left: 4px solid {$color}; background: #f8fafc; font-family: monospace; font-size: 14px; color: #1e293b;'>";
        echo "<strong>[" . strtoupper($type) . "]</strong> {$msg}";
        echo "</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Database Migration — E-Portal</title>";
    echo "<style>body{font-family:Inter,sans-serif;background:#f1f5f9;padding:32px;max-width:900px;margin:0 auto;}</style>";
    echo "</head><body>";
    echo "<div style='background:white;padding:24px 32px;border-radius:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);'>";
    echo "<h1 style='margin-top:0;color:#0f172a;font-size:22px;'>🚀 E-Portal Database Migration</h1>";
    echo "<p style='color:#64748b;'>Menjalankan migrasi tabel modul E-Examination & sistem akses...</p>";
}

$pdo = db();
$successCount = 0;
$errorCount = 0;

$migrations = [
    // 1. Table exam_roles
    "Tabel exam_roles" => "
        CREATE TABLE IF NOT EXISTS `exam_roles` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) UNSIGNED NOT NULL,
            `role` ENUM('admin', 'guru', 'proktor') NOT NULL DEFAULT 'guru',
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_exam_user` (`user_id`),
            CONSTRAINT `fk_exam_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    // 2. Table exam_student_login (Live monitoring & session lock)
    "Tabel exam_student_login" => "
        CREATE TABLE IF NOT EXISTS `exam_student_login` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `student_id` INT(11) UNSIGNED NOT NULL,
            `status` ENUM('logged_in', 'mengerjakan', 'selesai', 'logged_out') NOT NULL DEFAULT 'logged_in',
            `is_locked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Terkunci karena keluar dari aplikasi',
            `lock_reason` VARCHAR(255) DEFAULT NULL,
            `ujian_id` INT(11) UNSIGNED DEFAULT NULL,
            `sesi_id` INT(11) UNSIGNED DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `last_heartbeat` DATETIME DEFAULT NULL,
            `login_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_exam_student` (`student_id`),
            KEY `idx_status_locked` (`status`, `is_locked`),
            KEY `idx_ujian_login` (`ujian_id`),
            CONSTRAINT `fk_exam_student_login_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    // 3. Ensure exam_sesi status has 'mengerjakan', 'berlangsung', 'selesai', 'dihentikan', 'didiskualifikasi'
    "Alter exam_sesi status enum" => "
        ALTER TABLE `exam_sesi` 
        MODIFY COLUMN `status` ENUM('mengerjakan','berlangsung','selesai','dihentikan','didiskualifikasi') NOT NULL DEFAULT 'mengerjakan';
    "
];

foreach ($migrations as $name => $sql) {
    try {
        $pdo->exec($sql);
        log_msg("Berhasil mengeksekusi: <strong>{$name}</strong>", 'success');
        $successCount++;
    } catch (PDOException $e) {
        log_msg("Peringatan/Gagal pada <strong>{$name}</strong>: " . htmlspecialchars($e->getMessage()), 'warn');
    }
}

// Seed initial roles for Superadmin and Teachers into exam_roles if empty
try {
    $count = $pdo->query("SELECT COUNT(*) FROM exam_roles")->fetchColumn();
    if ($count == 0) {
        // Auto assign superadmin as admin
        $superadmins = $pdo->query("SELECT id FROM users WHERE role = 'superadmin'")->fetchAll();
        $stmtIns = $pdo->prepare("INSERT IGNORE INTO exam_roles (user_id, role, status) VALUES (?, 'admin', 1)");
        foreach ($superadmins as $sa) {
            $stmtIns->execute([$sa['id']]);
        }
        log_msg("Inisialisasi peran Superadmin ke tabel exam_roles berhasil.", 'info');
    }
} catch (Exception $e) {
    log_msg("Seeding roles info: " . htmlspecialchars($e->getMessage()), 'info');
}

if (!$isCli) {
    echo "<hr style='margin:24px 0; border:none; border-top:1px solid #e2e8f0;'>";
    echo "<p style='color:#16A34A; font-weight:700; font-size:16px;'>✓ Migrasi selesai dijalankan.</p>";
    echo "<a href='" . BASE_URL . "modules/e-examination/' style='display:inline-block; padding:10px 20px; background:#2563EB; color:white; border-radius:8px; text-decoration:none; font-weight:600;'>Buka E-Examination</a>";
    echo "</div></body></html>";
}
