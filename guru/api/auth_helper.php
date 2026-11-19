<?php
/**
 * Guru App — Shared Authentication Helper
 */
require_once __DIR__ . '/../../api/config.php';

function guru_auth() {
    $token = get_bearer_token(true);
    if (empty($token)) {
        json_response(401, false, 'Token tidak ditemukan.');
    }

    $stmt = db()->prepare("
        SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar, u.tupoksi, u.jabatan
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1 AND u.role = 'guru'
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        json_response(401, false, 'Sesi tidak valid atau telah berakhir.');
    }

    return $user;
}
