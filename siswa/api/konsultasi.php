<?php
require_once __DIR__ . '/auth_helper.php';
$siswa = siswa_auth();

try {
    $guruWaliName = $siswa['guru_wali'] ?? '';
    $guruId = null;
    $guruNama = $guruWaliName;

    if (!empty($guruWaliName)) {
        $baseName = trim(preg_replace('/,.*$/', '', $guruWaliName));
        $baseName = trim(preg_replace('/^(Drs\.|Dra\.|Ir\.|H\.|Hj\.)\s*/i', '', $baseName));

        $stmt = db()->prepare("SELECT id, nama_lengkap FROM users WHERE (nama_lengkap = ? OR nama_lengkap LIKE ?) AND (role = 'guru' OR role = 'waka' OR role = 'kepsek') LIMIT 1");
        $stmt->execute([$guruWaliName, '%'.$baseName.'%']);
        $guru = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($guru) {
            $guruId = $guru['id'];
            $guruNama = $guru['nama_lengkap'];
        }
    }

    $where = "j.jenis_jurnal = 'guru_wali' AND (j.student_id = ?";
    $params = [$siswa['id']];

    if ($guruId) {
        $where .= " OR (j.student_id IS NULL AND j.guru_id = ?)";
        $params[] = $guruId;
    }
    $where .= ")";

    $stmt2 = db()->prepare("
        SELECT j.id, j.tanggal, j.catatan, j.foto_kegiatan, j.student_id, u.nama_lengkap as guru_nama
        FROM acad_jurnal j
        LEFT JOIN users u ON j.guru_id = u.id
        WHERE $where
        ORDER BY j.tanggal DESC, j.id DESC
    ");
    $stmt2->execute($params);
    $konsultasi = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    foreach ($konsultasi as &$k) {
        $k['tanggal_indo'] = format_tanggal($k['tanggal']);
        $k['is_personal'] = !empty($k['student_id']);
        if (empty($k['guru_nama'])) {
            $k['guru_nama'] = $guruNama;
        }
        if (!empty($k['foto_kegiatan'])) {
            $parsed = json_decode($k['foto_kegiatan'], true);
            $k['fotos'] = is_array($parsed) ? $parsed : [$k['foto_kegiatan']];
        } else {
            $k['fotos'] = [];
        }
    }

    json_response(200, true, 'Konsultasi dimuat', $konsultasi);
} catch (Exception $e) {
    json_response(500, false, $e->getMessage());
}
