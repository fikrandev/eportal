<?php
/**
 * E-Curriculum Authentication Helper
 * Centralized logic for session resolution and authentication checks
 */

require_once __DIR__ . '/../../../api/config.php';

/**
 * Extract token from Authorization header or query parameter
 */
function acad_extract_token() {
    $token = '';
    
    // Check via $_SERVER HTTP headers
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } 
    // Fallback: getallheaders
    elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        } elseif (isset($headers['authorization'])) {
            $token = str_replace('Bearer ', '', $headers['authorization']);
        }
    }
    
    // Final fallback: query parameters
    if ($token === '' && isset($_GET['token'])) {
        $token = (string)$_GET['token'];
    }
    
    return trim($token);
}

/**
 * Resolve user by session token
 */
function acad_resolve_user_by_token($token, $jsonOnFail = true) {
    $token = trim((string)$token);
    if ($token === '') {
        if ($jsonOnFail) json_response(401, false, 'Token required');
        return null;
    }

    try {
        $stmt = db()->prepare("
            SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            if ($jsonOnFail) json_response(401, false, 'Sesi tidak valid atau telah berakhir.');
            return null;
        }

        // Resolve acad_role & custom permissions
        $user['acad_role'] = '';
        $user['custom_role_nama'] = '';
        $user['permissions'] = [];

        if ($user['role'] === 'superadmin') {
            $user['acad_role'] = 'admin_kurikulum';
            $user['custom_role_nama'] = 'Super Admin';
            $user['permissions'] = ['dashboard_view','jadwal_manage','jurnal_manage','absensi_manage','absensi_guru_manage','piket_manage','ketidakhadiran_manage','buku_penghubung_manage','dokumen_manage','laporan_view','roles_manage'];
        } else {
            // Check acad_roles joined with acad_roles_def
            try {
                $stmtRole = db()->prepare("
                    SELECT ar.role, ar.custom_role_id, rd.nama as role_nama,
                           (SELECT GROUP_CONCAT(permission_key) FROM acad_role_permissions WHERE role_id = ar.custom_role_id) as permissions
                    FROM acad_roles ar
                    LEFT JOIN acad_roles_def rd ON ar.custom_role_id = rd.id
                    WHERE ar.user_id = ?
                    LIMIT 1
                ");
                $stmtRole->execute([$user['user_id']]);
                $roleData = $stmtRole->fetch();

                if ($roleData) {
                    $user['acad_role'] = $roleData['role'] ?: 'operator_kurikulum';
                    $user['custom_role_nama'] = $roleData['role_nama'] ?? '';
                    $rawPerms = (string)($roleData['permissions'] ?? '');
                    $user['permissions'] = array_values(array_filter(array_map('trim', explode(',', $rawPerms))));
                } else {
                    // Fallback legacy acad_users
                    $stmtLegacy = db()->prepare("SELECT role FROM acad_users WHERE user_id = ? LIMIT 1");
                    $stmtLegacy->execute([$user['user_id']]);
                    $acadRole = $stmtLegacy->fetchColumn();
                    if ($acadRole) {
                        $user['acad_role'] = $acadRole;
                        if ($acadRole === 'admin_kurikulum') {
                            $user['custom_role_nama'] = 'Admin Kurikulum';
                            $user['permissions'] = ['dashboard_view','jadwal_manage','jurnal_manage','absensi_manage','absensi_guru_manage','piket_manage','ketidakhadiran_manage','buku_penghubung_manage','dokumen_manage','laporan_view','roles_manage'];
                        } else {
                            $user['custom_role_nama'] = 'Operator Kurikulum';
                            $user['permissions'] = ['dashboard_view','jurnal_manage','absensi_manage','absensi_guru_manage','piket_manage','ketidakhadiran_manage','buku_penghubung_manage','laporan_view'];
                        }
                    }
                }
            } catch (Exception $ex) {}
        }

        return $user;
    } catch (PDOException $e) {
        if ($jsonOnFail) json_response(500, false, 'Database error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Enforce authentication and return the user object
 */
function acad_auth() {
    return acad_resolve_user_by_token(acad_extract_token(), true);
}

/**
 * Enforce admin role permission check
 */
function acad_require_admin($user) {
    $isSuperAdmin = ($user['role'] ?? '') === 'superadmin';
    $hasRoleManage = in_array('roles_manage', $user['permissions'] ?? []);
    $isAdminKurikulum = in_array($user['acad_role'] ?? '', ['admin_kurikulum', 'operator_kurikulum']);
    if (!$isSuperAdmin && !$hasRoleManage && !$isAdminKurikulum) {
        json_response(403, false, 'Akses ditolak. Anda tidak memiliki hak akses admin untuk modul ini.');
    }
}

/**
 * Auto-migrate tables for E-Curriculum
 */
function acad_run_migrations() {
    try {
        // Table acad_users
        db()->exec("CREATE TABLE IF NOT EXISTS acad_users (
            id int(11) unsigned NOT NULL AUTO_INCREMENT,
            user_id int(11) unsigned NOT NULL,
            role enum('admin_kurikulum','operator_kurikulum') NOT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Table acad_documents
        db()->exec("CREATE TABLE IF NOT EXISTS acad_documents (
            id int(11) unsigned NOT NULL AUTO_INCREMENT, 
            user_id int(11) unsigned NOT NULL, 
            academic_year_id int(11) unsigned NOT NULL,
            judul varchar(255) NOT NULL,
            tipe_dokumen varchar(100) NOT NULL,
            file_path varchar(255) NOT NULL,
            status enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            catatan_admin text DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(), 
            updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), 
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY academic_year_id (academic_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Table acad_absensi_guru (Added for remote server auto-migration)
        db()->exec("CREATE TABLE IF NOT EXISTS acad_absensi_guru (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            guru_id bigint(20) unsigned NOT NULL,
            year_id bigint(20) unsigned NOT NULL,
            tanggal date NOT NULL,
            status enum('H','T','S','I','A') NOT NULL DEFAULT 'H',
            keterangan varchar(255) DEFAULT NULL,
            dicatat_oleh bigint(20) unsigned NOT NULL,
            created_at timestamp NULL DEFAULT current_timestamp(),
            updated_at timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY guru_tanggal_unique (guru_id,tanggal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // ============ RBAC TABLES FOR AKSES MODUL ============

        // Table acad_roles_def — Role definitions
        db()->exec("CREATE TABLE IF NOT EXISTS acad_roles_def (
            id int(11) unsigned NOT NULL AUTO_INCREMENT,
            nama varchar(100) NOT NULL,
            deskripsi varchar(255) DEFAULT NULL,
            is_locked tinyint(1) NOT NULL DEFAULT 0,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Table acad_role_permissions — Permission keys per role
        db()->exec("CREATE TABLE IF NOT EXISTS acad_role_permissions (
            id int(11) unsigned NOT NULL AUTO_INCREMENT,
            role_id int(11) unsigned NOT NULL,
            permission_key varchar(100) NOT NULL,
            PRIMARY KEY (id),
            KEY role_id (role_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Table acad_roles — User-to-role mapping
        db()->exec("CREATE TABLE IF NOT EXISTS acad_roles (
            id int(11) unsigned NOT NULL AUTO_INCREMENT,
            user_id int(11) unsigned NOT NULL,
            custom_role_id int(11) unsigned DEFAULT NULL,
            role enum('admin_kurikulum','operator_kurikulum') NOT NULL DEFAULT 'operator_kurikulum',
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Seed default locked roles if not exist
        $checkRoles = db()->query("SELECT COUNT(*) FROM acad_roles_def WHERE is_locked = 1")->fetchColumn();
        if ((int)$checkRoles === 0) {
            // Admin Kurikulum — full access
            db()->exec("INSERT INTO acad_roles_def (nama, deskripsi, is_locked) VALUES ('Admin Kurikulum', 'Akses penuh ke semua fitur E-Curriculum', 1)");
            $adminId = db()->lastInsertId();
            $allPerms = ['dashboard_view','jadwal_manage','jurnal_manage','absensi_manage','absensi_guru_manage','piket_manage','ketidakhadiran_manage','buku_penghubung_manage','dokumen_manage','laporan_view','roles_manage'];
            $stmtP = db()->prepare("INSERT INTO acad_role_permissions (role_id, permission_key) VALUES (?,?)");
            foreach ($allPerms as $p) { $stmtP->execute([$adminId, $p]); }

            // Operator Kurikulum — limited access
            db()->exec("INSERT INTO acad_roles_def (nama, deskripsi, is_locked) VALUES ('Operator Kurikulum', 'Akses terbatas untuk operasional harian', 1)");
            $opId = db()->lastInsertId();
            $opPerms = ['dashboard_view','jurnal_manage','absensi_manage','absensi_guru_manage','piket_manage','ketidakhadiran_manage','buku_penghubung_manage','laporan_view'];
            foreach ($opPerms as $p) { $stmtP->execute([$opId, $p]); }
        }

        // Migrate existing acad_users to acad_roles
        try {
            $existing = db()->query("SELECT user_id, role FROM acad_users")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($existing)) {
                $adminRoleId = db()->query("SELECT id FROM acad_roles_def WHERE nama='Admin Kurikulum' AND is_locked=1 LIMIT 1")->fetchColumn();
                $opRoleId = db()->query("SELECT id FROM acad_roles_def WHERE nama='Operator Kurikulum' AND is_locked=1 LIMIT 1")->fetchColumn();
                $stmtMig = db()->prepare("INSERT IGNORE INTO acad_roles (user_id, custom_role_id, role) VALUES (?,?,?)");
                foreach ($existing as $row) {
                    $rid = ($row['role'] === 'admin_kurikulum') ? $adminRoleId : $opRoleId;
                    $stmtMig->execute([$row['user_id'], $rid, $row['role']]);
                }
            }
        } catch (Exception $e) { /* ignore migration errors */ }

        // Table acad_document_types — Master Jenis/Tipe Dokumen Perangkat
        db()->exec("CREATE TABLE IF NOT EXISTS acad_document_types (
            id int(11) unsigned NOT NULL AUTO_INCREMENT,
            nama_tipe varchar(100) NOT NULL,
            deskripsi varchar(255) DEFAULT NULL,
            urutan int(11) NOT NULL DEFAULT 0,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY nama_tipe (nama_tipe)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Seed default document types if empty
        $checkTypes = db()->query("SELECT COUNT(*) FROM acad_document_types")->fetchColumn();
        if ((int)$checkTypes === 0) {
            $defaultTypes = [
                ['RPP', 'Rencana Pelaksanaan Pembelajaran', 1],
                ['Modul Ajar', 'Modul Ajar Kurikulum Merdeka', 2],
                ['Silabus', 'Silabus Mata Pelajaran', 3],
                ['Prota / Promes', 'Program Tahunan & Semester', 4],
                ['ATP', 'Alur Tujuan Pembelajaran', 5],
                ['Lainnya', 'Dokumen Pendukung Lainnya', 6]
            ];
            $stmtDT = db()->prepare("INSERT IGNORE INTO acad_document_types (nama_tipe, deskripsi, urutan) VALUES (?,?,?)");
            foreach ($defaultTypes as $dt) {
                $stmtDT->execute($dt);
            }
        }

    } catch (Exception $e) {
        // Ignore errors to not break the API if migration fails
    }
}

// Run migrations silently on API boot
acad_run_migrations();
