<?php
/**
 * E-Performance Roles API
 */
require_once __DIR__ . '/config_perf.php';
$auth = perf_require_permission('akses_modul'); // requires 'akses_modul' permission

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list': listRoles(); break;
    case 'save': saveRole(); break;
    case 'delete': deleteRole(); break;
    default: json_response(400, false, 'Action tidak valid.');
}

function listRoles() {
    $stmt = db()->query("SELECT * FROM perf_roles WHERE is_system = 0 ORDER BY role_name ASC");
    $data = $stmt->fetchAll();
    foreach($data as &$row) {
        $row['permissions'] = json_decode($row['permissions'] ?: '[]');
    }
    json_response(200, true, 'Data roles', $data);
}

function saveRole() {
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $name = isset($input['role_name']) ? sanitize($input['role_name']) : '';
    $slug = isset($input['role_slug']) ? sanitize($input['role_slug']) : '';
    $perms = isset($input['permissions']) && is_array($input['permissions']) ? json_encode($input['permissions']) : '[]';

    if(empty($name) || empty($slug)) {
        json_response(400, false, 'Nama dan Slug Role wajib diisi');
    }

    if ($id > 0) {
        $stmt = db()->prepare("UPDATE perf_roles SET role_name=?, permissions=? WHERE id=?");
        $stmt->execute([$name, $perms, $id]);
        json_response(200, true, 'Role berhasil diupdate');
    } else {
        $stmt = db()->prepare("SELECT id FROM perf_roles WHERE role_slug=?");
        $stmt->execute([$slug]);
        if($stmt->fetch()) {
            json_response(400, false, 'Slug sudah digunakan');
        }
        
        $stmt = db()->prepare("INSERT INTO perf_roles (role_slug, role_name, permissions) VALUES (?, ?, ?)");
        $stmt->execute([$slug, $name, $perms]);
        json_response(200, true, 'Role berhasil ditambahkan');
    }
}

function deleteRole() {
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    $stmt = db()->prepare("SELECT * FROM perf_roles WHERE id=?");
    $stmt->execute([$id]);
    $role = $stmt->fetch();
    
    if(!$role) {
        json_response(404, false, 'Role tidak ditemukan');
    }
    if($role['is_system'] == 1) {
        json_response(400, false, 'Role sistem tidak dapat dihapus');
    }
    
    $stmt = db()->prepare("DELETE FROM perf_roles WHERE id=?");
    $stmt->execute([$id]);
    json_response(200, true, 'Role berhasil dihapus');
}
