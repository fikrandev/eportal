<?php
/**
 * Database Auto-Migration Helper
 * Runs migrations automatically when root or config is loaded
 */
require_once __DIR__ . '/config.php';

function run_auto_migrations() {
    $target_version = 5;
    
    // 1. Get current version (default to 0 if not set or if table settings doesn't exist yet)
    $current_version = 0;
    try {
        $current_version = (int)get_setting('db_migration_version', 0);
    } catch (Exception $e) {
        // If settings table doesn't exist, we keep version as 0 to trigger migration
    }

    if ($current_version >= $target_version) {
        return; // Already up to date
    }

    $pdo = db();

    // Version 1 migrations (from migrate.php)
    if ($current_version < 1) {
        $v1_migrations = [
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
            "Tabel exam_student_login" => "
                CREATE TABLE IF NOT EXISTS `exam_student_login` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `student_id` INT(11) UNSIGNED NOT NULL,
                    `status` ENUM('logged_in', 'mengerjakan', 'selesai', 'logged_out') NOT NULL DEFAULT 'logged_in',
                    `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
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
            "
        ];

        foreach ($v1_migrations as $name => $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                // Keep moving, log error or ignore if already ran
            }
        }

        // Alter exam_sesi status enum if table exists
        try {
            $pdo->exec("
                ALTER TABLE `exam_sesi` 
                MODIFY COLUMN `status` ENUM('mengerjakan','berlangsung','selesai','dihentikan','didiskualifikasi') NOT NULL DEFAULT 'mengerjakan';
            ");
        } catch (PDOException $e) {
            // Ignore if exam_sesi doesn't exist
        }

        // Seed initial roles for Superadmin and Teachers into exam_roles if empty
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM exam_roles")->fetchColumn();
            if ($count == 0) {
                $superadmins = $pdo->query("SELECT id FROM users WHERE role = 'superadmin'")->fetchAll();
                $stmtIns = $pdo->prepare("INSERT IGNORE INTO exam_roles (user_id, role, status) VALUES (?, 'admin', 1)");
                foreach ($superadmins as $sa) {
                    $stmtIns->execute([$sa['id']]);
                }
            }
        } catch (Exception $e) {
            // Ignore seeding errors
        }
    }

    // Version 2 migrations (PWA settings and persistent session helpers)
    if ($current_version < 2) {
        // Ensure index for sessions.expired_at for faster cleanup
        try {
            $pdo->exec("ALTER TABLE `sessions` ADD INDEX IF NOT EXISTS `idx_expired_at` (`expired_at`)");
        } catch (PDOException $e) {
            // Ignore if index already exists
        }

        // Set or update default settings for Guru App PWA
        upsert_setting('guru_pwa_version', '1.0.0', 'text', 'Versi PWA Guru');
    }

    // Version 3 migrations (Add wali_kelas_id to ref_kelas)
    if ($current_version < 3) {
        try {
            $pdo->exec("ALTER TABLE `ref_kelas` ADD COLUMN `wali_kelas_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `nama_kelas`");
        } catch (PDOException $e) {
            // Column may already exist
        }
        try {
            $pdo->exec("ALTER TABLE `ref_kelas` ADD CONSTRAINT `fk_ref_kelas_wali` FOREIGN KEY (`wali_kelas_id`) REFERENCES `users`(`id`) ON DELETE SET NULL");
        } catch (PDOException $e) {
            // Constraint may already exist
        }
    }

    // Version 4 migrations (Ensure acad_absensi exists)
    if ($current_version < 4) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_absensi` (
                  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `student_id` INT(11) UNSIGNED NOT NULL,
                  `kelas_id` INT(10) UNSIGNED NOT NULL,
                  `academic_year_id` INT(11) UNSIGNED NOT NULL,
                  `tanggal` DATE NOT NULL,
                  `jam_ke` INT(11) NOT NULL DEFAULT 0,
                  `status` ENUM('H','S','I','A') NOT NULL DEFAULT 'H',
                  `keterangan` VARCHAR(255) DEFAULT '',
                  `dicatat_oleh` INT(11) UNSIGNED DEFAULT NULL,
                  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_absensi` (`student_id`,`tanggal`,`jam_ke`),
                  KEY `idx_absensi_tanggal` (`tanggal`),
                  KEY `idx_absensi_kelas` (`kelas_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (PDOException $e) {
            // Table may already exist
        }
    }

    // Version 5 migrations (Support Non-KBM Activity Journals and Homeroom Wali Kelas Journals)
    if ($current_version < 5) {
        // 1. Add jenis_jurnal column to acad_jurnal if not exists
        try {
            $colExists = false;
            $cols = $pdo->query("SHOW COLUMNS FROM `acad_jurnal` LIKE 'jenis_jurnal'")->fetchAll();
            if (count($cols) > 0) $colExists = true;

            if (!$colExists) {
                $pdo->exec("ALTER TABLE `acad_jurnal` ADD COLUMN `jenis_jurnal` VARCHAR(30) NOT NULL DEFAULT 'kbm' AFTER `academic_year_id`");
            }
        } catch (PDOException $e) {
            // Ignore error if column already exists
        }

        // 2. Modify kelas_id, mapel_id, jam_ke to allow NULL (for non-KBM activity journals)
        try {
            $pdo->exec("ALTER TABLE `acad_jurnal` MODIFY COLUMN `kelas_id` INT(10) UNSIGNED NULL DEFAULT NULL");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("ALTER TABLE `acad_jurnal` MODIFY COLUMN `mapel_id` INT(10) UNSIGNED NULL DEFAULT NULL");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("ALTER TABLE `acad_jurnal` MODIFY COLUMN `jam_ke` VARCHAR(20) NULL DEFAULT NULL");
        } catch (PDOException $e) {}

        // 3. Add index on (guru_id, jenis_jurnal, tanggal)
        try {
            $pdo->exec("ALTER TABLE `acad_jurnal` ADD INDEX `idx_jurnal_jenis` (`guru_id`, `jenis_jurnal`, `tanggal`)");
        } catch (PDOException $e) {}
    }

    // Update DB migration version to target_version
    try {
        upsert_setting('db_migration_version', (string)$target_version, 'number', 'Versi Migrasi Database E-Portal');
    } catch (Exception $e) {
        // Safe fallback
    }
}
