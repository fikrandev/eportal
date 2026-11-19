<?php
/**
 * E-Portal Modules API
 * CRUD operations for portal modules
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listModules();
        break;
    case 'list_all':
        listAllModules();
        break;
    case 'get':
        getModule();
        break;
    case 'create':
        createModule();
        break;
    case 'update':
        updateModule();
        break;
    case 'delete':
        deleteModule();
        break;
    case 'reorder':
        reorderModules();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List all active modules (for dashboard)
 */
function listModules() {
    try {
        $user = auth_get_optional_user();

        $stmt = db()->query("SELECT * FROM modules WHERE status = 1 ORDER BY urutan ASC, nama_modul ASC");
        $modules = $stmt->fetchAll();

        if ($user && ($user['role'] ?? '') !== 'superadmin') {
            $userPerms = $user['permissions'] ?? [];
            if (!in_array('*', $userPerms, true) && !empty($user['portal_role_id'])) {
                $filtered = [];
                foreach ($modules as $m) {
                    if (in_array('module_' . $m['slug'], $userPerms, true)) {
                        $filtered[] = $m;
                    }
                }
                $modules = $filtered;
            }
        }

        json_response(200, true, 'Data modul berhasil dimuat.', $modules);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * List ALL modules including inactive ones (for Admin Panel)
 */
function listAllModules() {
    portal_require_permission('modules_manage');
    try {
        $stmt = db()->query("SELECT * FROM modules ORDER BY urutan ASC, nama_modul ASC");
        json_response(200, true, 'Data semua modul berhasil dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get single module
 */
function getModule() {
    portal_require_permission('modules_manage');

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID modul tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM modules WHERE id = ?");
        $stmt->execute([$id]);
        $module = $stmt->fetch();

        if (!$module) {
            json_response(404, false, 'Modul tidak ditemukan.');
        }

        json_response(200, true, 'Data modul.', $module);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Create new module
 */
function createModule() {
    portal_require_permission('modules_manage');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $nama = isset($input['nama_modul']) ? sanitize($input['nama_modul']) : '';
    $slug = isset($input['slug']) ? sanitize($input['slug']) : '';
    $deskripsi = isset($input['deskripsi']) ? sanitize($input['deskripsi']) : '';
    $iconSvg = isset($input['icon_svg']) ? sanitize_module_svg($input['icon_svg']) : '';
    $urlPath = isset($input['url_path']) ? sanitize($input['url_path']) : '';
    $color = isset($input['color']) ? sanitize($input['color']) : '#1565C0';

    if (empty($nama) || empty($slug) || empty($iconSvg)) {
        json_response(400, false, 'Nama modul, slug, dan icon harus diisi.');
    }

    // Auto-slug if not provided
    if (empty($slug)) {
        $slug = strtolower(str_replace(' ', '-', $nama));
    }

    try {
        // Check duplicate slug
        $stmt = db()->prepare("SELECT id FROM modules WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            json_response(400, false, 'Slug modul sudah digunakan.');
        }

        // Get max order
        $stmt = db()->query("SELECT COALESCE(MAX(urutan), 0) + 1 as next_order FROM modules");
        $nextOrder = $stmt->fetch()['next_order'];

        $stmt = db()->prepare("
            INSERT INTO modules (nama_modul, slug, deskripsi, icon_svg, url_path, color, urutan)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nama, $slug, $deskripsi, $iconSvg, $urlPath, $color, $nextOrder]);

        json_response(201, true, 'Modul berhasil ditambahkan.', [
            'id' => db()->lastInsertId()
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Update module
 */
function updateModule() {
    portal_require_permission('modules_manage');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    if ($id <= 0) {
        json_response(400, false, 'ID modul tidak valid.');
    }

    $nama = isset($input['nama_modul']) ? sanitize($input['nama_modul']) : '';
    $deskripsi = isset($input['deskripsi']) ? sanitize($input['deskripsi']) : '';
    $iconSvg = isset($input['icon_svg']) ? sanitize_module_svg($input['icon_svg']) : '';
    $urlPath = isset($input['url_path']) ? sanitize($input['url_path']) : '';
    $color = isset($input['color']) ? sanitize($input['color']) : '#1565C0';
    $status = isset($input['status']) ? (int)$input['status'] : 1;

    if (empty($nama)) {
        json_response(400, false, 'Nama modul harus diisi.');
    }

    try {
        $stmt = db()->prepare("
            UPDATE modules SET nama_modul = ?, deskripsi = ?, icon_svg = ?, url_path = ?, color = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$nama, $deskripsi, $iconSvg, $urlPath, $color, $status, $id]);

        json_response(200, true, 'Modul berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Delete module
 */
function deleteModule() {
    portal_require_permission('modules_manage');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        json_response(400, false, 'ID modul tidak valid.');
    }

    try {
        $stmt = db()->prepare("DELETE FROM modules WHERE id = ?");
        $stmt->execute([$id]);

        json_response(200, true, 'Modul berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Reorder modules
 */
function reorderModules() {
    portal_require_permission('modules_manage');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $orders = isset($input['orders']) ? $input['orders'] : [];

    if (empty($orders)) {
        json_response(400, false, 'Data urutan tidak valid.');
    }

    try {
        $stmt = db()->prepare("UPDATE modules SET urutan = ? WHERE id = ?");
        foreach ($orders as $order) {
            $stmt->execute([(int)$order['urutan'], (int)$order['id']]);
        }

        json_response(200, true, 'Urutan modul berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function sanitize_module_svg($svg) {
    $svg = (string)$svg;
    $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg);
    $svg = preg_replace('/\son\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg);
    $svg = preg_replace('/javascript\s*:/i', '', $svg);
    return trim($svg);
}
