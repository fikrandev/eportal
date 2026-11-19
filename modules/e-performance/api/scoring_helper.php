<?php
/**
 * E-Performance — Shared Scoring & Predikat Description Helpers
 * Prevents fatal function redeclaration when cetak_semua.php includes cetak_lampiran.php and cetak_rekap_keseluruhan.php.
 */

if (!function_exists('getDeskripsiInfo')) {
    function getDeskripsiInfo($db, $tupoksi, $nilai) {
        $stmt = $db->prepare("SELECT predikat, deskripsi FROM perf_deskripsi WHERE tupoksi = ? AND ? >= min_nilai AND ? <= max_nilai LIMIT 1");
        $stmt->execute([$tupoksi, $nilai, $nilai]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) return $res;

        if ($nilai > 0 && $nilai <= 5.0) {
            $nilai100 = ($nilai / 5.0) * 100;
            $stmt->execute([$tupoksi, $nilai100, $nilai100]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) return $res;
        }

        return null;
    }
}

if (!function_exists('getDefaultDeskripsiText')) {
    function getDefaultDeskripsiText($tupoksi, $predikat) {
        $sebutan = (strpos(strtolower((string)$tupoksi), 'guru') !== false) ? 'Guru' : 'Pegawai';
        $pLower = strtolower((string)$predikat);
        if (strpos($pLower, 'sangat baik') !== false || strpos($pLower, 'sb') !== false || strpos($pLower, 'istimewa') !== false) {
            return "{$sebutan} telah menunjukkan standar kompetensi yang sangat luar biasa dalam mengajar dan memberikan pelayanan pendidikan secara optimal.";
        } elseif (strpos($pLower, 'baik') !== false || $pLower === 'b') {
            return "{$sebutan} telah memenuhi standar kompetensi dengan baik dalam mengajar dan memberikan pelayanan pendidikan secara optimal.";
        } elseif (strpos($pLower, 'cukup') !== false || $pLower === 'c') {
            return "{$sebutan} telah memenuhi standar kompetensi dasar dalam mengajar, namun masih memerlukan bimbingan atau peningkatan pada beberapa aspek tertentu agar lebih optimal dalam pelayanan pendidikan.";
        } else {
            return "{$sebutan} perlu banyak bimbingan untuk mencapai standar kompetensi dasar dalam pelayanan pendidikan.";
        }
    }
}

if (!function_exists('getDeskripsiPenilaian')) {
    function getDeskripsiPenilaian($db, $tupoksi, $nilai, $fallbackPredikat = '') {
        $info = getDeskripsiInfo($db, $tupoksi, $nilai);
        if ($info && !empty($info['deskripsi'])) {
            return $info['deskripsi'];
        }
        if ($fallbackPredikat) {
            return getDefaultDeskripsiText($tupoksi, $fallbackPredikat);
        }
        return '-';
    }
}
