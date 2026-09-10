<?php
/**
 * Database Auto-Migration Helper
 * Runs migrations automatically when root or config is loaded
 */
require_once __DIR__ . '/config.php';

function run_auto_migrations() {
    $target_version = 11;
    
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

    // Version 6 migrations (Ketidakhadiran Guru / Izin)
    if ($current_version < 6) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_ketidakhadiran` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `guru_id` int(11) unsigned NOT NULL COMMENT 'References users.id',
                  `tanggal` date NOT NULL,
                  `jenis` enum('Sakit','Cuti','Tugas','Izin','Lainnya') NOT NULL DEFAULT 'Izin',
                  `catatan` text DEFAULT NULL,
                  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_ketidakhadiran` (`guru_id`,`tanggal`),
                  KEY `idx_ketidakhadiran_tanggal` (`tanggal`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            // Ignore if table already exists
        }
    }

    // Version 7 migrations (Update existing acad_ketidakhadiran)
    if ($current_version < 7) {
        try {
            // Check if status column exists
            $columns = $pdo->query("SHOW COLUMNS FROM acad_ketidakhadiran")->fetchAll(PDO::FETCH_COLUMN);
            
            // 1. Modify jenis column
            $pdo->exec("ALTER TABLE acad_ketidakhadiran MODIFY COLUMN jenis ENUM('Sakit', 'Cuti', 'Tugas', 'Izin', 'Lainnya') NOT NULL DEFAULT 'Izin'");
            
            // 2. Add status column if it does not exist
            if (!in_array('status', $columns)) {
                $pdo->exec("ALTER TABLE acad_ketidakhadiran ADD COLUMN status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending' AFTER catatan");
            }
        } catch (Exception $e) {
            // Ignore errors if table doesn't exist yet
        }
    }

    // Version 8 migrations (Izin Siswa App)
    if ($current_version < 8) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_izin_siswa` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `student_id` int(11) unsigned NOT NULL,
                  `academic_year_id` int(11) unsigned DEFAULT NULL,
                  `tanggal` date NOT NULL,
                  `jenis` enum('Sakit','Izin','Lainnya') NOT NULL DEFAULT 'Izin',
                  `keterangan` text DEFAULT NULL,
                  `lampiran` varchar(255) DEFAULT NULL,
                  `status` enum('Pending','Disetujui','Ditolak','Approved','Rejected') NOT NULL DEFAULT 'Pending',
                  `disetujui_oleh` int(11) unsigned DEFAULT NULL,
                  `alasan_tolak` text DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `idx_izin_siswa_tanggal` (`tanggal`),
                  KEY `idx_izin_siswa_student` (`student_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            $cols = $pdo->query("SHOW COLUMNS FROM acad_izin_siswa")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('academic_year_id', $cols)) {
                $pdo->exec("ALTER TABLE `acad_izin_siswa` ADD COLUMN `academic_year_id` INT(11) UNSIGNED DEFAULT NULL AFTER `student_id`");
            }
            if (!in_array('lampiran', $cols)) {
                $pdo->exec("ALTER TABLE `acad_izin_siswa` ADD COLUMN `lampiran` VARCHAR(255) DEFAULT NULL AFTER `keterangan`");
            }
            if (!in_array('disetujui_oleh', $cols)) {
                $pdo->exec("ALTER TABLE `acad_izin_siswa` ADD COLUMN `disetujui_oleh` INT(11) UNSIGNED DEFAULT NULL AFTER `status`");
            }
            if (!in_array('alasan_tolak', $cols)) {
                $pdo->exec("ALTER TABLE `acad_izin_siswa` ADD COLUMN `alasan_tolak` TEXT DEFAULT NULL AFTER `disetujui_oleh`");
            }
            $pdo->exec("ALTER TABLE `acad_izin_siswa` MODIFY COLUMN `status` ENUM('Pending','Disetujui','Ditolak','Approved','Rejected') NOT NULL DEFAULT 'Pending'");
        } catch (PDOException $e) {
            // Ignore if table already exists
        }
    }

    // Version 9 migrations (Siswa Lulus / Status Siswa)
    if ($current_version < 9) {
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('status_siswa', $columns)) {
                $pdo->exec("ALTER TABLE `students` ADD COLUMN `status_siswa` ENUM('Aktif','Lulus','Keluar','Mutasi') NOT NULL DEFAULT 'Aktif' AFTER `status`");
            }
            if (!in_array('academic_year_id_lulus', $columns)) {
                $pdo->exec("ALTER TABLE `students` ADD COLUMN `academic_year_id_lulus` INT(11) UNSIGNED DEFAULT NULL AFTER `status_siswa`");
            }
            if (!in_array('tanggal_lulus', $columns)) {
                $pdo->exec("ALTER TABLE `students` ADD COLUMN `tanggal_lulus` DATE DEFAULT NULL AFTER `academic_year_id_lulus`");
            }
        } catch (Exception $e) {
            // Ignore if error
        }
    }

    // Version 10 migrations (E-Curriculum RBAC, Document Management, Master Document Types & Absensi Guru)
    if ($current_version < 10) {
        // 1. Table acad_roles_def
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_roles_def` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `nama` VARCHAR(100) NOT NULL,
                    `deskripsi` VARCHAR(255) DEFAULT NULL,
                    `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (PDOException $e) {}

        // 2. Table acad_role_permissions
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_role_permissions` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `role_id` INT(11) UNSIGNED NOT NULL,
                    `permission_key` VARCHAR(100) NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_role_id` (`role_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (PDOException $e) {}

        // 3. Table acad_roles (User to Custom Role mapping)
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_roles` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT(11) UNSIGNED NOT NULL,
                    `custom_role_id` INT(11) UNSIGNED DEFAULT NULL,
                    `role` ENUM('admin_kurikulum','operator_kurikulum') NOT NULL DEFAULT 'operator_kurikulum',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_user_id` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (PDOException $e) {}

        // 4. Seed default locked roles if not exist
        try {
            $checkRoles = $pdo->query("SELECT COUNT(*) FROM `acad_roles_def` WHERE `is_locked` = 1")->fetchColumn();
            if ((int)$checkRoles === 0) {
                // Admin Kurikulum
                $pdo->exec("INSERT INTO `acad_roles_def` (`nama`, `deskripsi`, `is_locked`) VALUES ('Admin Kurikulum', 'Akses penuh ke semua fitur E-Curriculum', 1)");
                $adminId = $pdo->lastInsertId();
                $allPerms = ['dashboard_view','jadwal_manage','jurnal_manage','absensi_manage','absensi_guru_manage','piket_manage','ketidakhadiran_manage','buku_penghubung_manage','dokumen_manage','laporan_view','roles_manage'];
                $stmtP = $pdo->prepare("INSERT INTO `acad_role_permissions` (`role_id`, `permission_key`) VALUES (?,?)");
                foreach ($allPerms as $p) { $stmtP->execute([$adminId, $p]); }

                // Operator Kurikulum
                $pdo->exec("INSERT INTO `acad_roles_def` (`nama`, `deskripsi`, `is_locked`) VALUES ('Operator Kurikulum', 'Akses terbatas untuk operasional harian', 1)");
                $opId = $pdo->lastInsertId();
                $opPerms = ['dashboard_view','jurnal_manage','absensi_manage','absensi_guru_manage','piket_manage','ketidakhadiran_manage','buku_penghubung_manage','laporan_view'];
                foreach ($opPerms as $p) { $stmtP->execute([$opId, $p]); }
            }
        } catch (Exception $e) {}

        // 5. Table acad_documents
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_documents` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT(11) UNSIGNED NOT NULL,
                    `academic_year_id` INT(11) UNSIGNED DEFAULT NULL,
                    `judul` VARCHAR(255) NOT NULL,
                    `tipe_dokumen` VARCHAR(100) NOT NULL DEFAULT 'RPP',
                    `file_path` VARCHAR(255) NOT NULL,
                    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                    `catatan_admin` TEXT DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_doc_user` (`user_id`),
                    KEY `idx_doc_academic_year` (`academic_year_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (PDOException $e) {}

        // 6. Table acad_document_types (Master Jenis Perangkat)
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_document_types` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `nama_tipe` VARCHAR(100) NOT NULL,
                    `deskripsi` VARCHAR(255) DEFAULT NULL,
                    `urutan` INT(11) NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_nama_tipe` (`nama_tipe`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $checkTypes = $pdo->query("SELECT COUNT(*) FROM `acad_document_types`")->fetchColumn();
            if ((int)$checkTypes === 0) {
                $defaultTypes = [
                    ['RPP', 'Rencana Pelaksanaan Pembelajaran', 1],
                    ['Modul Ajar', 'Modul Ajar Kurikulum Merdeka', 2],
                    ['Silabus', 'Silabus Mata Pelajaran', 3],
                    ['Prota / Promes', 'Program Tahunan & Semester', 4],
                    ['ATP', 'Alur Tujuan Pembelajaran', 5],
                    ['Lainnya', 'Dokumen Pendukung Lainnya', 6]
                ];
                $stmtDT = $pdo->prepare("INSERT IGNORE INTO `acad_document_types` (`nama_tipe`, `deskripsi`, `urutan`) VALUES (?,?,?)");
                foreach ($defaultTypes as $dt) {
                    $stmtDT->execute($dt);
                }
            }
        } catch (Exception $e) {}

        // 7. Table acad_absensi_guru
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_absensi_guru` (
                    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `guru_id` INT(11) UNSIGNED NOT NULL,
                    `tanggal` DATE NOT NULL,
                    `status` ENUM('H','S','I','A') NOT NULL DEFAULT 'H',
                    `keterangan` VARCHAR(255) DEFAULT '',
                    `dicatat_oleh` INT(11) UNSIGNED DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_guru_tanggal` (`guru_id`,`tanggal`),
                    KEY `idx_guru_tgl` (`tanggal`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (PDOException $e) {}
    }

    // Version 11 migrations (Buku Penghubung Types & Penghubung Custom Schema)
    if ($current_version < 11) {
        // Table acad_buku_types
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_buku_types` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `nama_jenis` VARCHAR(100) NOT NULL,
                    `deskripsi` VARCHAR(255) DEFAULT NULL,
                    `warna_badge` VARCHAR(50) NOT NULL DEFAULT 'badge-info',
                    `urutan` INT(11) NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_nama_jenis` (`nama_jenis`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $checkTypes = $pdo->query("SELECT COUNT(*) FROM `acad_buku_types`")->fetchColumn();
            if ((int)$checkTypes === 0) {
                $defaults = [
                    ['Keterlambatan', 'Catatan siswa terlambat masuk sekolah', 'badge-warning', 1],
                    ['Pelanggaran', 'Catatan pelanggaran tata tertib sekolah', 'badge-danger', 2],
                    ['Prestasi', 'Penghargaan / prestasi akademik & non-akademik', 'badge-success', 3],
                    ['Screening', 'Catatan screening kesehatan / perilaku siswa', 'badge-info', 4],
                    ['Konsultasi', 'Bimbingan dan konseling siswa', 'badge-primary', 5]
                ];
                $stmt = $pdo->prepare("INSERT IGNORE INTO `acad_buku_types` (`nama_jenis`, `deskripsi`, `warna_badge`, `urutan`) VALUES (?,?,?,?)");
                foreach ($defaults as $d) {
                    $stmt->execute($d);
                }
            }
        } catch (Exception $e) {}

        // Table acad_buku_penghubung
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `acad_buku_penghubung` (
                    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `student_id` INT(11) UNSIGNED NOT NULL COMMENT 'References students.id',
                    `kelas_id` INT(10) UNSIGNED NOT NULL,
                    `academic_year_id` INT(11) UNSIGNED NOT NULL,
                    `jenis` VARCHAR(100) NOT NULL DEFAULT 'Konsultasi',
                    `tanggal` DATE NOT NULL,
                    `catatan` TEXT NOT NULL,
                    `dicatat_oleh` INT(11) UNSIGNED DEFAULT NULL COMMENT 'user_id guru',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_buku_student` (`student_id`),
                    KEY `idx_buku_kelas` (`kelas_id`),
                    KEY `idx_buku_tanggal` (`tanggal`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $pdo->exec("ALTER TABLE `acad_buku_penghubung` MODIFY COLUMN `jenis` VARCHAR(100) NOT NULL DEFAULT 'Konsultasi'");
        } catch (PDOException $e) {}
    }

    // Update DB migration version to target_version
    try {
        upsert_setting('db_migration_version', (string)$target_version, 'number', 'Versi Migrasi Database E-Portal');
    } catch (Exception $e) {
        // Safe fallback
    }
}
