<?php
/**
 * E-Sarpras Authentication Helper
 * Centralized logic for role-based access control and scoping.
 */

function sp_permission_aliases() {
    return [
        'dashboard_view' => ['dashboard_view'],
        'tanah_manage' => ['tanah_manage'],
        'bangunan_manage' => ['bangunan_manage'],
        'ruang_manage' => ['ruang_manage'],
        'sarpras_manage' => ['sarpras_manage', 'sarana_manage', 'ahp_manage'],
        'peminjaman_manage' => ['peminjaman_manage'],
        'perbaikan_manage' => ['perbaikan_manage'],
        'penghapusan_manage' => ['penghapusan_manage'],
        'report_view' => ['report_view', 'laporan_view'],
        'referensi_manage' => ['referensi_manage'],
        'roles_manage' => ['roles_manage'],
        'settings_manage' => ['settings_manage'],
    ];
}

function sp_all_permissions() {
    return array_keys(sp_permission_aliases());
}

function sp_normalize_permissions($permissions, $ensureDashboard = true) {
    $normalized = [];
    $aliases = sp_permission_aliases();

    foreach ((array) $permissions as $permission) {
        $permission = trim((string) $permission);
        if ($permission === '') continue;

        $matched = false;
        foreach ($aliases as $canonical => $knownKeys) {
            if (in_array($permission, $knownKeys, true)) {
                $normalized[] = $canonical;
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $normalized[] = $permission;
        }
    }

    $normalized = array_values(array_unique($normalized));
    if ($ensureDashboard && !in_array('dashboard_view', $normalized, true)) {
        $normalized[] = 'dashboard_view';
    }

    return $normalized;
}

function sp_default_permissions_for_role($sarprasRole) {
    switch ((string) $sarprasRole) {
        case 'admin_sarpras':
            return sp_all_permissions();
        case 'operator_sarpras':
            return [
                'dashboard_view',
                'tanah_manage',
                'bangunan_manage',
                'ruang_manage',
                'sarpras_manage',
                'peminjaman_manage',
                'perbaikan_manage',
                'penghapusan_manage',
                'report_view',
            ];
        default:
            return ['dashboard_view'];
    }
}

function sp_extract_token() {
    return get_bearer_token(true);
}

function sp_resolve_user_by_token($token, $jsonOnFail = true) {
    $token = trim((string) $token);
    if ($token === '') {
        if ($jsonOnFail) json_response(401, false, 'Token required');
        return null;
    }

    $stmt = db()->prepare("SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar FROM sessions s JOIN users u ON s.user_id=u.id WHERE s.token=? AND s.expired_at>NOW() AND u.status=1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($jsonOnFail) json_response(401, false, 'Session expired or user inactive');
        return null;
    }

    $user['sarpras_role'] = 'viewer_sarpras';
    $user['permissions'] = ['dashboard_view'];
    $user['scoped_ruang_ids'] = [];
    $user['is_room_scoped'] = false;
    $user['custom_role_id'] = null;
    $user['custom_role_name'] = '';

    if ($user['role'] === 'superadmin') {
        $user['sarpras_role'] = 'admin_sarpras';
        $user['custom_role_name'] = 'Superadmin';
        $user['permissions'] = sp_all_permissions();
        return $user;
    }

    $stmtRole = db()->prepare("
        SELECT sr.role, sr.custom_role_id, rd.nama as role_name
        FROM sarpras_roles sr
        LEFT JOIN sarpras_roles_def rd ON sr.custom_role_id = rd.id
        WHERE sr.user_id = ?
        LIMIT 1
    ");
    $stmtRole->execute([$user['user_id']]);
    $roleData = $stmtRole->fetch();

    if (!$roleData) {
        if ($jsonOnFail) json_response(403, false, 'Akses ditolak. Anda belum terdaftar dalam Akses Modul E-Sarpras.');
        return null;
    }

    $user['sarpras_role'] = $roleData['role'] ?: 'viewer_sarpras';
    $user['custom_role_id'] = $roleData['custom_role_id'] ? (int) $roleData['custom_role_id'] : null;
    $user['custom_role_name'] = trim((string) ($roleData['role_name'] ?? ''));

    if ($user['custom_role_id']) {
        $stmtPerm = db()->prepare("SELECT permission_key FROM sarpras_role_permissions WHERE role_id = ?");
        $stmtPerm->execute([$user['custom_role_id']]);
        $user['permissions'] = sp_normalize_permissions($stmtPerm->fetchAll(PDO::FETCH_COLUMN));

        if ($user['custom_role_name'] === 'Penanggung Jawab Ruangan') {
            $user['is_room_scoped'] = true;
            // Find PJ by user_id OR nip (matching username)
            $pjStmt = db()->prepare("SELECT id FROM sarpras_pj WHERE user_id = ? OR nip = ? LIMIT 1");
            $pjStmt->execute([$user['user_id'], $user['username']]);
            $pjId = $pjStmt->fetchColumn();

            if ($pjId) {
                $ruangStmt = db()->prepare("SELECT id FROM ruang WHERE pj_id = ?");
                $ruangStmt->execute([$pjId]);
                $user['scoped_ruang_ids'] = array_map('intval', $ruangStmt->fetchAll(PDO::FETCH_COLUMN));
            }
        }
    } else {
        $user['permissions'] = sp_default_permissions_for_role($user['sarpras_role']);
    }

    return $user;
}

function sp_auth() {
    sp_ensure_default_categories();
    return sp_resolve_user_by_token(sp_extract_token(), true);
}

function sp_ensure_default_categories() {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $count = (int)db()->query("SELECT COUNT(*) FROM kategori_sarpras")->fetchColumn();
        if ($count === 0) {
            $defaults = [
                ['Lambang Negara', 'A', 'sarana'],
                ['Meubelair', 'B', 'sarana'],
                ['Elektronik', 'C', 'sarana'],
                ['Lab. Fisika', 'D', 'sarana'],
                ['Lab. Kimia', 'E', 'sarana'],
                ['Lab. Biologi', 'F', 'sarana'],
                ['Alat Musik', 'G', 'sarana'],
                ['UKS', 'H', 'sarana'],
                ['BK', 'I', 'sarana'],
                ['Perpustakaan', 'J', 'sarana'],
                ['Sarpras Lainnya', 'N', 'sarana'],
                ['K3', 'K', 'sarana'],
                ['Perangkat Kelas', 'L', 'sarana'],
                ['Perlengkapan Kelas', 'M', 'sarana'],
                ['Koleksi Buku', 'BK', 'sarana']
            ];
            $stmt = db()->prepare("INSERT INTO kategori_sarpras (nama, kode, jenis) VALUES (?, ?, ?)");
            foreach ($defaults as $d) {
                $stmt->execute($d);
            }
        }
    } catch (Exception $e) {}
}

function sp_has($user, $permission) {
    $permission = trim((string) $permission);
    if ($permission === '') return false;

    $owned = sp_normalize_permissions($user['permissions'] ?? [], false);
    $aliases = sp_permission_aliases();
    $candidates = $aliases[$permission] ?? [$permission];

    foreach ($candidates as $candidate) {
        $canonical = sp_normalize_permissions([$candidate], false);
        if (array_intersect($owned, $canonical)) {
            return true;
        }
    }

    return false;
}

function sp_has_any($user, $permissions) {
    foreach ((array) $permissions as $permission) {
        if (sp_has($user, $permission)) return true;
    }
    return false;
}

function sp_can_edit($user, $permissions = []) {
    if (empty($permissions)) {
        $permissions = [
            'tanah_manage',
            'bangunan_manage',
            'ruang_manage',
            'sarpras_manage',
            'peminjaman_manage',
            'perbaikan_manage',
            'penghapusan_manage',
            'referensi_manage',
            'roles_manage',
            'settings_manage',
        ];
    }
    return sp_has_any($user, $permissions);
}

function sp_require_any($user, $permissions, $message = 'Akses ditolak') {
    if (!sp_has_any($user, $permissions)) {
        json_response(403, false, $message);
    }
}

function sp_can_access_ruang($user, $ruangId) {
    if (empty($user['is_room_scoped'])) return true;
    $ruangId = (int)$ruangId;
    if ($ruangId <= 0) return false;
    $allowed = array_map('intval', $user['scoped_ruang_ids'] ?? []);
    return in_array($ruangId, $allowed, true);
}

function sp_scope_where($user, $table_alias = 's', $column = 'ruang_id', $allow_null = false) {
    if (!empty($user['is_room_scoped']) || !empty($user['scoped_ruang_ids'])) {
        $scoped = array_values(array_filter(array_map('intval', $user['scoped_ruang_ids'] ?? []), function($v) { return $v > 0; }));
        if (empty($scoped)) {
            return $allow_null ? " AND ($table_alias.$column IS NULL) " : " AND 1=0 ";
        }
        $ids = implode(',', $scoped);
        if ($allow_null) {
            return " AND ($table_alias.$column IN ($ids) OR $table_alias.$column IS NULL) ";
        }
        return " AND $table_alias.$column IN ($ids) ";
    }
    return "";
}

/**
 * Pastikan tabel riwayat barang masuk tersedia dan terisi saldo awal jika masih kosong
 */
function sp_ensure_riwayat_masuk_table() {
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS sarpras_riwayat_masuk (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sarpras_id INT(11) UNSIGNED NULL,
            kode_inventaris VARCHAR(50) NULL,
            nama_barang VARCHAR(200) NOT NULL,
            kategori_id INT(11) UNSIGNED NULL,
            grup_pintasan VARCHAR(50) NULL,
            ruang_id INT(11) UNSIGNED NULL,
            tanggal DATE NOT NULL,
            jumlah INT(11) NOT NULL DEFAULT 1,
            harga_satuan DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_harga DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            asal_perolehan VARCHAR(50) NULL DEFAULT 'APBD',
            merk VARCHAR(100) NULL,
            spesifikasi TEXT NULL,
            keterangan TEXT NULL,
            jenis_transaksi VARCHAR(30) NOT NULL DEFAULT 'tambah_baru',
            created_by INT(11) UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sarpras (sarpras_id),
            KEY idx_tanggal (tanggal),
            KEY idx_grup (grup_pintasan)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Backfill otomatis dari sarpras jika tabel baru dibuat dan masih kosong
        $cnt = (int)db()->query("SELECT COUNT(*) FROM sarpras_riwayat_masuk")->fetchColumn();
        if ($cnt === 0) {
            db()->exec("INSERT INTO sarpras_riwayat_masuk 
                (sarpras_id, kode_inventaris, nama_barang, kategori_id, grup_pintasan, ruang_id, tanggal, jumlah, harga_satuan, total_harga, asal_perolehan, merk, spesifikasi, keterangan, jenis_transaksi, created_at)
                SELECT 
                id, 
                kode_inventaris, 
                nama, 
                kategori_id, 
                grup_pintasan, 
                ruang_id, 
                CASE 
                    WHEN tanggal_perolehan IS NOT NULL AND tanggal_perolehan != '0000-00-00' THEN tanggal_perolehan 
                    ELSE DATE(created_at) 
                END,
                GREATEST(1, jumlah), 
                harga_perolehan, 
                (harga_perolehan * GREATEST(1, jumlah)), 
                asal_perolehan, 
                merk, 
                spesifikasi, 
                COALESCE(NULLIF(keterangan, ''), 'Pengadaan Awal / Saldo Awal'), 
                'tambah_baru', 
                created_at
                FROM sarpras WHERE is_hapus = 0");
        }
    } catch (Exception $e) {}
}

/**
 * Catat log barang masuk (pengadaan baru atau restock)
 */
function sp_log_barang_masuk($d) {
    sp_ensure_riwayat_masuk_table();
    try {
        $jml = max(1, (int)($d['jumlah'] ?? 1));
        $hrg = floatval($d['harga_satuan'] ?? 0);
        $total = floatval($d['total_harga'] ?? ($jml * $hrg));
        $tgl = !empty($d['tanggal']) && $d['tanggal'] !== '0000-00-00' ? $d['tanggal'] : date('Y-m-d');
        
        $stmt = db()->prepare("INSERT INTO sarpras_riwayat_masuk 
            (sarpras_id, kode_inventaris, nama_barang, kategori_id, grup_pintasan, ruang_id, tanggal, jumlah, harga_satuan, total_harga, asal_perolehan, merk, spesifikasi, keterangan, jenis_transaksi, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            !empty($d['sarpras_id']) ? (int)$d['sarpras_id'] : null,
            $d['kode_inventaris'] ?? null,
            $d['nama_barang'] ?? '',
            !empty($d['kategori_id']) ? (int)$d['kategori_id'] : null,
            $d['grup_pintasan'] ?? null,
            !empty($d['ruang_id']) ? (int)$d['ruang_id'] : null,
            $tgl,
            $jml,
            $hrg,
            $total,
            $d['asal_perolehan'] ?? 'APBD',
            $d['merk'] ?? '',
            $d['spesifikasi'] ?? '',
            $d['keterangan'] ?? '',
            $d['jenis_transaksi'] ?? 'tambah_baru',
            !empty($d['created_by']) ? (int)$d['created_by'] : null
        ]);
        return db()->lastInsertId();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Format tanggal Indonesia (contoh: 24 Juni 2026)
 */
function sp_format_indo_date($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '-';
    $t = strtotime($dateStr);
    if (!$t) return $dateStr;
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}
