<?php
/**
 * E-Performance — Cetak Lampiran 1 & 2 & Rapor Kinerja Utama
 * Render Laporan Penilaian Kinerja Sesuai Guideline 2026
 */

require_once __DIR__ . '/config_perf.php';
require_once __DIR__ . '/scoring_helper.php';

$auth = perf_auth_check();
if (!$auth) {
    die("Akses ditolak. Silakan login.");
}

$db = db();

$ptk_id = isset($_GET['ptk_id']) ? (int)$_GET['ptk_id'] : 0;
$periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;

if (!$ptk_id || !$periode_id) {
    die("Parameter tidak lengkap.");
}

// 1. Get Periode Data
$stmtP = $db->prepare("SELECT * FROM perf_periode WHERE id = ?");
$stmtP->execute([$periode_id]);
$periode = $stmtP->fetch(PDO::FETCH_ASSOC);

$kop_surat = get_setting('kop_surat', '');
if (!$periode) die("Periode tidak ditemukan.");

// Check Authorization
$isAdmin = in_array($auth['role'], ['admin', 'superadmin'], true) || in_array('hasil', $auth['permissions'] ?? [], true);
if (!$isAdmin) {
    if (empty($auth['ptk_id']) || (int)$auth['ptk_id'] !== (int)$ptk_id) {
        die("Akses ditolak. Anda hanya dapat melihat laporan Anda sendiri.");
    }
    if ((int)$periode['is_released'] !== 1) {
        die("Akses ditolak. Hasil penilaian untuk periode ini belum dirilis.");
    }
}

// 2. Get PTK Biodata (from perf_ptk and users)
$stmtPTK = $db->prepare("
    SELECT pt.*, u.tmt, COALESCE(u.nama_lengkap, pt.nama) as nama_lengkap, pt.niy, COALESCE(u.tupoksi, pt.jenis_ptk) as tupoksi, u.jabatan 
    FROM perf_ptk pt
    LEFT JOIN users u ON pt.niy COLLATE utf8mb4_unicode_ci = u.username COLLATE utf8mb4_unicode_ci
    WHERE pt.id = ?
");
$stmtPTK->execute([$ptk_id]);
$ptk = $stmtPTK->fetch(PDO::FETCH_ASSOC);
if (!$ptk) die("Data PTK tidak ditemukan.");

// Calculate Masa Kerja
$masa_kerja = "-";
if (!empty($ptk['tmt']) && $ptk['tmt'] != '0000-00-00') {
    $tmt_date = new DateTime($ptk['tmt']);
    $end_date = new DateTime($periode['tgl_selesai']);
    $diff = $tmt_date->diff($end_date);
    $masa_kerja = $diff->y . " Tahun " . $diff->m . " Bulan";
}

// 3. Get Kepala Sekolah Data
$setting_kepsek = get_setting('kepala_sekolah', '');
if ($setting_kepsek) {
    $nama_kepsek = $setting_kepsek;
} else {
    $stmtKS = $db->query("SELECT nama_lengkap FROM users WHERE tupoksi = 'Kepala Sekolah' LIMIT 1");
    $kepsek = $stmtKS->fetch(PDO::FETCH_ASSOC);
    $nama_kepsek = $kepsek ? $kepsek['nama_lengkap'] : "RIYATI, S.Psi.";
}

$school_name = get_setting('nama_sekolah', 'Sekolah');
$ptk_jenis = trim($ptk['jenis_ptk'] ? $ptk['jenis_ptk'] : ($ptk['tupoksi'] ? $ptk['tupoksi'] : 'Guru'));
$group_role = normalizeTupoksiGroup($ptk_jenis);
$is_staff = ($group_role !== 'Guru');

// Deteksi Tugas Tambahan (Waka / Ka. TU / Ka. Perpus / Ka. Lab)
$jabatan_full = strtolower(trim(($ptk['jabatan'] ?? '') . ' ' . $ptk_jenis));
$is_waka = (strpos($jabatan_full, 'waka') !== false || strpos($jabatan_full, 'wakil') !== false);
$is_ka_tu = (strpos($jabatan_full, 'kepala tu') !== false || strpos($jabatan_full, 'ka. tu') !== false);
$is_ka_perpus = (strpos($jabatan_full, 'kepala perpus') !== false || strpos($jabatan_full, 'ka. perpus') !== false);
$is_ka_lab = (strpos($jabatan_full, 'kepala lab') !== false || strpos($jabatan_full, 'ka. lab') !== false);

$allowed_tambahan = [];
if ($is_waka) {
    $allowed_tambahan = ['Manajerial', 'Kepemimpinan', 'Teknis', 'Kewirausahaan'];
} elseif ($is_ka_tu || $is_ka_perpus || $is_ka_lab) {
    $allowed_tambahan = ['Manajerial'];
}

// 4. Query Penilaian Angket (perf_penilaian)
$stmtNilai = $db->prepare("
    SELECT 
        p.penilai_type, 
        p.penilai_id,
        p.dinilai_ptk_id,
        u.perf_ptk_id as rater_ptk_id,
        TRIM(REPLACE(i.kategori, '\xc2\xa0', '')) as kategori,
        p.nilai
    FROM perf_penilaian p
    JOIN perf_instrumen i ON p.instrumen_id = i.id
    LEFT JOIN perf_users u ON p.penilai_id = u.id
    WHERE p.periode_id = ? AND p.dinilai_ptk_id = ?
");
$stmtNilai->execute([$periode_id, $ptk_id]);
$rawScores = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

// Kumpulkan Kategori dari Penilaian
$allKats = [];
foreach ($rawScores as $r) {
    $k = trim($r['kategori']);
    if ($k && !in_array($k, $allKats)) {
        $allKats[] = $k;
    }
}

// Pisahkan Kategori Utama vs Tugas Tambahan
$kategori_list = [];
$kategori_tambahan_list = [];

foreach ($allKats as $k) {
    if (in_array($k, $allowed_tambahan)) {
        $kategori_tambahan_list[] = $k;
    } else {
        $kategori_list[] = $k;
    }
}

// Urutkan Kategori Utama sesuai standar Guideline jika ada
if (!$is_staff) {
    $stdOrder = ['Pedagogik', 'Kepribadian', 'Sosial', 'Profesional'];
    $kategori_list = array_values(array_intersect($stdOrder, $kategori_list));
    // Jika ada yang belum masuk standar (misal BK)
    foreach ($allKats as $k) {
        if (!in_array($k, $allowed_tambahan) && !in_array($k, $kategori_list)) {
            $kategori_list[] = $k;
        }
    }
} else {
    $stdOrderStaff = ['Kepribadian', 'Sosial', 'Teknis'];
    $kategori_list = array_values(array_intersect($stdOrderStaff, $kategori_list));
    foreach ($allKats as $k) {
        if (!in_array($k, $allowed_tambahan) && !in_array($k, $kategori_list)) {
            $kategori_list[] = $k;
        }
    }
}

// Urutkan Kategori Tambahan
if ($is_waka) {
    $stdOrderWaka = ['Manajerial', 'Kepemimpinan', 'Teknis', 'Kewirausahaan'];
    $kategori_tambahan_list = array_values(array_intersect($stdOrderWaka, $kategori_tambahan_list));
}

// Struktur Penilai
$penilai_list = [
    'kepsek'  => ['label' => 'Kepala Sekolah', 'bobot' => 0.40],
    'sendiri' => ['label' => 'Diri Sendiri', 'bobot' => 0.10],
    'teman'   => ['label' => 'Teman Sejawat', 'bobot' => 0.20],
    'siswa'   => ['label' => 'Murid', 'bobot' => 0.30]
];

// Agregasi Nilai
$agg = [];
$agg_tambahan = [];

foreach ($rawScores as $row) {
    $ptype_raw = strtolower(trim($row['penilai_type']));
    if (strpos($ptype_raw, 'siswa') !== false || strpos($ptype_raw, 'murid') !== false) {
        $ptype = 'siswa';
    } elseif ($ptype_raw === 'kepala sekolah' || strpos($ptype_raw, 'kepsek') !== false) {
        $ptype = 'kepsek';
    } else {
        if ($row['rater_ptk_id'] != null && (int)$row['rater_ptk_id'] === (int)$row['dinilai_ptk_id']) {
            $ptype = 'sendiri';
        } else {
            $ptype = 'teman';
        }
    }
    
    $k = trim($row['kategori']);
    if (in_array($k, $kategori_list)) {
        if (!isset($agg[$ptype][$k])) $agg[$ptype][$k] = ['sum' => 0, 'count' => 0];
        $agg[$ptype][$k]['sum'] += (float)$row['nilai'];
        $agg[$ptype][$k]['count']++;
    } elseif (in_array($k, $kategori_tambahan_list)) {
        if (!isset($agg_tambahan[$ptype][$k])) $agg_tambahan[$ptype][$k] = ['sum' => 0, 'count' => 0];
        $agg_tambahan[$ptype][$k]['sum'] += (float)$row['nilai'];
        $agg_tambahan[$ptype][$k]['count']++;
    }
}

// Inisialisasi Score Data
$scores = [];
foreach ($penilai_list as $key => $p) {
    $scores[$key] = [
        'total_avg' => 0,
        'nilai' => 0,
        'kategori' => []
    ];
    foreach ($kategori_list as $kat) {
        $scores[$key]['kategori'][$kat] = 0;
        if (isset($agg[$key][$kat]) && $agg[$key][$kat]['count'] > 0) {
            $scores[$key]['kategori'][$kat] = $agg[$key][$kat]['sum'] / $agg[$key][$kat]['count'];
        }
    }
}

// Menghitung Rata-rata Penilai (Total_Avg) & Nilai Berbobot (Skala 1-5)
$total_nilai_akhir = 0;
foreach ($scores as $ptype => &$data) {
    $sum_kat = 0;
    $count_kat = 0;
    foreach ($kategori_list as $kat) {
        if ($data['kategori'][$kat] > 0) {
            $sum_kat += $data['kategori'][$kat];
            $count_kat++;
        }
    }
    $data['total_avg'] = $count_kat > 0 ? ($sum_kat / $count_kat) : 0;
    $data['nilai'] = $data['total_avg'] * $penilai_list[$ptype]['bobot'];
    $total_nilai_akhir += round($data['nilai'], 3);
}
unset($data);

// Predikat & Deskripsi untuk Lampiran 1 (Skala 1-5)
$predikat_l1 = getPredikatSkala5($total_nilai_akhir);
$deskripsi_l1 = getDeskripsiAngketLampiran($db, $ptk_jenis, $total_nilai_akhir);

// Konversi Angket ke Skala 100 per Kategori
$konversi = [];
foreach ($kategori_list as $kat) {
    $sum_val = 0;
    $count_penilai = 0;
    foreach ($scores as $ptype => $data) {
        if ($data['kategori'][$kat] > 0) {
            $sum_val += $data['kategori'][$kat];
            $count_penilai++;
        }
    }
    $max_skor = $count_penilai * 5;
    $konversi[$kat] = $max_skor > 0 ? ($sum_val / $max_skor) * 100 : 0;
}

// Rata-rata Angket Skala 100
$angket_score = count($konversi) > 0 ? (array_sum($konversi) / count($konversi)) : 0;
$bobot_angket = 30; // Selalu 30% sesuai Guideline
$rounded_nilai_angket = round($angket_score * ($bobot_angket / 100), 2);

// Proses Tugas Tambahan
$has_tambahan = count($kategori_tambahan_list) > 0;
$tambahan_results = [];
$tambahan_sum = 0;
$tambahan_count = 0;

if ($has_tambahan) {
    foreach ($kategori_tambahan_list as $kat) {
        // Dinilai oleh Kepala Sekolah
        $score = 0;
        if (isset($agg_tambahan['kepsek'][$kat]) && $agg_tambahan['kepsek'][$kat]['count'] > 0) {
            $score = $agg_tambahan['kepsek'][$kat]['sum'] / $agg_tambahan['kepsek'][$kat]['count'];
        }
        $konversi_score = ($score / 5) * 100;
        $tambahan_results[] = [
            'kategori' => $kat,
            'score' => $score,
            'konversi' => $konversi_score
        ];
        $tambahan_sum += $score;
        $tambahan_count++;
    }
}

// 5. Fetch Manual Instruments (Page 1)
$stmtManual = $db->prepare("SELECT data FROM perf_penilaian_manual WHERE periode_id = ? AND ptk_id = ? LIMIT 1");
$stmtManual->execute([$periode_id, $ptk_id]);
$manualRow = $stmtManual->fetch(PDO::FETCH_ASSOC);
$manualData = ($manualRow && $manualRow['data']) ? json_decode($manualRow['data'], true) : [];

$stmtManualInst = $db->prepare("SELECT id, kategori, pertanyaan, bobot, skor_ya, target_dinilai FROM perf_instrumen WHERE periode_id = ? AND is_manual = 1 ORDER BY urutan ASC, id ASC");
$stmtManualInst->execute([$periode_id]);
$allManualInstRaw = $stmtManualInst->fetchAll(PDO::FETCH_ASSOC);

// Filter instrumen manual sesuai target PTK
$main_manual = [];
foreach ($allManualInstRaw as $inst) {
    $targetsStr = trim($inst['target_dinilai'] ? $inst['target_dinilai'] : 'Semua');
    $targets = array_map('trim', explode(',', $targetsStr));
    if ($targetsStr !== 'Semua' && !in_array('Semua', $targets) && !in_array($ptk_jenis, $targets)) {
        continue;
    }
    if ((float)$inst['bobot'] > 0) {
        $main_manual[] = $inst;
    }
}

// Kelompokkan Manual berdasarkan Kategori & Atur Bobot Resmi
$grouped_manual = [];
foreach ($main_manual as $inst) {
    $kat = trim($inst['kategori']);
    $k_lower = strtolower($kat);
    
    // Tentukan nama standar dan bobot resmi
    $clean_kat = $kat;
    $bobot_kat = 10;
    
    if (strpos($k_lower, 'perangkat') !== false) {
        $clean_kat = 'Perangkat Mengajar';
        $bobot_kat = 10;
    } elseif (strpos($k_lower, 'hadir') !== false) {
        $clean_kat = 'Kehadiran';
        $bobot_kat = 20;
    } elseif (strpos($k_lower, 'rapat') !== false || strpos($k_lower, 'kedinasan') !== false) {
        $clean_kat = 'Keikutsertaan dalam rapat/kedinasan';
        $bobot_kat = $is_staff ? 20 : 10;
    } elseif (strpos($k_lower, 'partisipasi') !== false) {
        $clean_kat = 'Partisipasi kegiatan sekolah';
        $bobot_kat = 30;
    }

    if (!isset($grouped_manual[$clean_kat])) {
        $grouped_manual[$clean_kat] = [
            'total_bobot' => $bobot_kat,
            'achieved_score' => 0,
            'max_score' => 0,
            'items' => []
        ];
    }
    $score = isset($manualData[$inst['id']]) ? (float)$manualData[$inst['id']] : 0;
    $skor_ya = (float)($inst['skor_ya'] > 0 ? $inst['skor_ya'] : 100);

    $grouped_manual[$clean_kat]['achieved_score'] += $score;
    $grouped_manual[$clean_kat]['max_score'] += $skor_ya;
}

// Urutkan Manual Sesuai Standar Guideline (Perangkat, Kehadiran, Rapat, Partisipasi)
$stdOrderManual = $is_staff ? [
    'Kehadiran',
    'Keikutsertaan dalam rapat/kedinasan',
    'Partisipasi kegiatan sekolah'
] : [
    'Perangkat Mengajar',
    'Kehadiran',
    'Keikutsertaan dalam rapat/kedinasan',
    'Partisipasi kegiatan sekolah'
];

$ordered_manual = [];
foreach ($stdOrderManual as $stdKey) {
    if (isset($grouped_manual[$stdKey])) {
        $ordered_manual[$stdKey] = $grouped_manual[$stdKey];
    }
}
foreach ($grouped_manual as $k => $v) {
    if (!isset($ordered_manual[$k])) {
        $ordered_manual[$k] = $v;
    }
}
$grouped_manual = $ordered_manual;

// Hitung Total Nilai Rapor Kinerja Utama
$total_nilai_page1 = 0;
foreach ($grouped_manual as $kat => &$group) {
    $avg_score = $group['max_score'] > 0 ? ($group['achieved_score'] / $group['max_score']) * 100 : 0;
    $group_nilai = round($avg_score * ($group['total_bobot'] / 100), 2);
    $group['avg_score'] = $avg_score;
    $group['group_nilai'] = $group_nilai;
    $total_nilai_page1 += $group_nilai;
}
unset($group);

// Tambahkan Nilai Angket ke Total Halaman 1
$total_nilai_page1 += $rounded_nilai_angket;
$total_nilai_page1 = round($total_nilai_page1, 2);

// Predikat & Deskripsi Rapor Utama (Skala 100)
$predikat_page1 = getPredikatSkala100($total_nilai_page1);
$deskripsi_page1 = getDeskripsiRaporUtama($db, $ptk_jenis, $total_nilai_page1);

// Set tanggal tanda tangan
$tgl_ttd_str = (new DateTime($periode['tgl_selesai'] ?? 'now'))->format('d F Y');
$bulan_en = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$bulan_id = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tgl_ttd = str_replace($bulan_en, $bulan_id, $tgl_ttd_str);

// Generate QR Code Link
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$full_base_url = rtrim($protocol . "://" . $host . BASE_URL, '/');
$qr_data = $full_base_url . "/modules/e-performance/verify_perf.php?id=" . $ptk_id . "&periode=" . $periode_id;
$qr_url = $full_base_url . "/modules/e-xam-card/api/qr.php?size=4&data=" . urlencode($qr_data);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Kinerja - <?php echo htmlspecialchars($ptk['nama_lengkap']); ?></title>
    <style>
        @page {
            size: 215.9mm 330.2mm; /* F4 / Folio Size */
            margin: 5mm 10mm;
        }
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 10pt;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        .kop-surat {
            width: 100%;
            padding-bottom: 0px;
            margin-bottom: 10px;
            text-align: center;
        }
        .kop-surat img {
            max-width: 100%;
            height: auto;
        }
        .kop {
            text-align: left;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .title {
            text-align: center;
            font-weight: bold;
            margin-top: 0px;
            margin-bottom: 5px;
        }
        .bio-table {
            width: 100%;
            margin-bottom: 5px;
            font-size: 10pt;
        }
        .bio-table td {
            padding: 1px 0;
            vertical-align: top;
        }
        .main-table, .konversi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10pt;
        }
        .main-table th, .main-table td, 
        .konversi-table th, .konversi-table td {
            border: 1px solid #000;
            padding: 2px 4px;
        }
        .main-table th {
            text-align: center;
            font-weight: normal;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .bg-gray {
            background-color: #d1d5db;
        }
        .desc-box {
            border: 1px solid #000;
            padding: 6px;
            margin-bottom: 5px;
            text-align: justify;
        }
        .signature-area {
            width: 100%;
            margin-top: 10px;
        }
        .signature-box {
            float: right;
            width: 300px;
            text-align: center;
        }
        .signature-box img {
            margin: 5px 0;
            width: 65px;
            height: 65px;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <div class="no-print" style="text-align:center; padding:15px; background:#f3f4f6; margin-bottom:20px; border-bottom:1px solid #ddd;">
        <button onclick="window.print()" style="padding:8px 16px; background:#4F46E5; color:#fff; border:none; border-radius:4px; font-size:14px; cursor:pointer;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:5px;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Laporan
        </button>
        <button onclick="window.close()" style="padding:8px 16px; background:#fff; color:#374151; border:1px solid #d1d5db; border-radius:4px; font-size:14px; cursor:pointer; margin-left:10px;">
            Tutup
        </button>
    </div>

    <div class="container">
        
        <?php if (!empty($kop_surat)): 
            $clean_kop = ltrim(str_replace('\\', '/', $kop_surat), '/');
            $kop_img_src = (preg_match('/^https?:\/\//i', $kop_surat)) ? $kop_surat : (defined('BASE_URL') ? BASE_URL . $clean_kop : '../../../' . $clean_kop);
        ?>
            <div class="kop-surat">
                <img src="<?php echo htmlspecialchars($kop_img_src); ?>" alt="Kop Surat" onerror="this.style.display='none'">
            </div>
        <?php endif; ?>

        <?php 
        $show_l1 = isset($_GET['l1']) ? $_GET['l1'] == 1 : true;
        $show_l2 = isset($_GET['l2']) ? $_GET['l2'] == 1 : true;
        if (!isset($_GET['l1']) && !isset($_GET['l2'])) {
            $show_l1 = true;
            $show_l2 = true;
        }
        
        if ($show_l1): 
        ?>
        <!-- ========================================== -->
        <!-- LEMBAR PERTAMA: RAPOR KINERJA UTAMA       -->
        <!-- ========================================== -->
        <div class="title" style="margin-bottom: 20px;">
            PENILAIAN KINERJA PENDIDIK DAN TENAGA KEPENDIDIKAN<br>
            <?php echo htmlspecialchars(strtoupper($school_name)); ?><br>
            PERIODE : <?php 
                $sem_label = ($periode['semester'] == '1') ? 'GANJIL' : (($periode['semester'] == '2') ? 'GENAP' : strtoupper($periode['semester']));
                $nama_periode = strtoupper($periode['nama_periode'] ?? '');
                echo htmlspecialchars($nama_periode . ' (' . $periode['tahun_ajaran'] . ' (' . $sem_label . '))'); 
            ?>
        </div>

        <table class="bio-table">
            <tr>
                <td width="150">Nama</td>
                <td width="10">:</td>
                <td><?php echo htmlspecialchars($ptk['nama_lengkap']); ?></td>
            </tr>
            <tr>
                <td>NIY</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($ptk['niy']); ?></td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>:</td>
                <td><?php 
                    $jab_display = htmlspecialchars($ptk['jabatan'] ? $ptk['jabatan'] : $ptk['jenis_ptk']);
                    if ($is_waka && stripos($jab_display, 'guru') === false) {
                        $jab_display = "Guru / " . $jab_display;
                    }
                    echo $jab_display;
                ?></td>
            </tr>
            <tr>
                <td>Masa Kerja</td>
                <td>:</td>
                <td><?php echo $masa_kerja; ?></td>
            </tr>
        </table>

        <table class="main-table">
            <tr>
                <th width="5%">No</th>
                <th>Penilaian</th>
                <th width="15%">Hasil<br>Penilaian<br>Skala 100</th>
                <th width="15%">Bobot</th>
                <th width="15%">Nilai</th>
            </tr>
            <?php
            $no = 1;

            // 1. Tampilkan Unsur Manual (1 s.d 4)
            foreach ($grouped_manual as $kat => $group) {
                echo "<tr>";
                echo "<td class='text-center'>{$no}</td>";
                echo "<td>".htmlspecialchars($kat)."</td>";
                echo "<td class='text-center'>".number_format($group['avg_score'], 0)."</td>";
                echo "<td class='text-center'>".number_format($group['total_bobot'], 0)."%</td>";
                echo "<td class='text-center'>".(fmod($group['group_nilai'], 1) !== 0.0 ? number_format($group['group_nilai'], 2) : number_format($group['group_nilai'], 0))."</td>";
                echo "</tr>";
                $no++;
            }

            // 2. Tampilkan Angket (Komponen Utama)
            $no_angket = $no;
            echo "<tr>";
            echo "<td class='text-center'>{$no_angket}</td>";
            echo "<td>Angket</td>";
            echo "<td class='text-center'>".number_format($angket_score, 2)."</td>";
            echo "<td class='text-center'>{$bobot_angket}%</td>";
            echo "<td class='text-center'>".(fmod($rounded_nilai_angket, 1) !== 0.0 ? number_format($rounded_nilai_angket, 2) : number_format($rounded_nilai_angket, 0))."</td>";
            echo "</tr>";

            // Sub-kategori Angket Utama (5.1, 5.2, 5.3, 5.4)
            $sub_no = 1;
            foreach ($kategori_list as $kat) {
                $val_k = isset($konversi[$kat]) ? $konversi[$kat] : 0;
                echo "<tr>";
                echo "<td></td>";
                echo "<td>{$no_angket}.{$sub_no}. Kompetensi {$kat}</td>";
                echo "<td class='text-center'>".number_format($val_k, 0)."</td>";
                echo "<td class='bg-gray'></td><td class='bg-gray'></td>";
                echo "</tr>";
                $sub_no++;
            }

            // 3. Jika Ada Tugas Tambahan, tampilkan di bawahnya
            if ($has_tambahan) {
                echo "<tr><td></td><td><b>Tugas Tambahan</b></td><td class='bg-gray'></td><td class='bg-gray'></td><td class='bg-gray'></td></tr>";
                foreach ($tambahan_results as $tt) {
                    echo "<tr>";
                    echo "<td></td>";
                    echo "<td>{$no_angket}.{$sub_no}. Kompetensi ".htmlspecialchars($tt['kategori'])."</td>";
                    echo "<td class='text-center'>".number_format($tt['konversi'], 0)."</td>";
                    echo "<td class='bg-gray'></td><td class='bg-gray'></td>";
                    echo "</tr>";
                    $sub_no++;
                }
            }

            echo "<tr><td colspan='4' class='text-right'><b>JUMLAH</b></td><td class='text-center'><b>".number_format($total_nilai_page1, 2)."</b></td></tr>";
            echo "<tr><td colspan='4' class='text-right'><b>PREDIKAT</b></td><td class='text-center'><b>{$predikat_page1}</b></td></tr>";
            ?>
        </table>

        <div class="desc-box">
            <div style="font-weight: bold; margin-bottom: 4px;">DESKRIPSI</div>
            <div><?php echo htmlspecialchars($deskripsi_page1); ?></div>
        </div>

        <div class="signature-area clearfix">
            <div class="signature-box">
                Surabaya, <?php echo $tgl_ttd; ?><br>
                Kepala Sekolah,
                <br>
                <img src="<?php echo $qr_url; ?>" alt="QR Code TTD" style="width: 65px; height: 65px; margin: 5px 0;">
                <br>
                <u><b><?php echo htmlspecialchars($nama_kepsek); ?></b></u>
            </div>
        </div>

        <div style="page-break-before: always;"></div>

        <!-- ========================================== -->
        <!-- LEMBAR KEDUA: LAMPIRAN 1 (ANGKET UTAMA)   -->
        <!-- ========================================== -->
        <div class="kop">
            [LAMPIRAN 1]
        </div>

        <div class="title" style="margin-bottom: 15px;">
            ANGKET PENILAIAN KOMPETENSI
        </div>

        <table class="bio-table">
            <tr>
                <td width="150">Nama</td>
                <td width="10">:</td>
                <td><?php echo htmlspecialchars($ptk['nama_lengkap']); ?></td>
            </tr>
            <tr>
                <td>NIY</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($ptk['niy']); ?></td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>:</td>
                <td><?php 
                    $jab_display = htmlspecialchars($ptk['jabatan'] ? $ptk['jabatan'] : $ptk['jenis_ptk']);
                    if ($is_waka && stripos($jab_display, 'guru') === false) {
                        $jab_display = "Guru / " . $jab_display;
                    }
                    echo $jab_display;
                ?></td>
            </tr>
            <tr>
                <td>Masa Kerja</td>
                <td>:</td>
                <td><?php echo $masa_kerja; ?></td>
            </tr>
        </table>

        <!-- Tabel Penilaian Angket (Skala 1-5) -->
        <table class="main-table">
            <tr>
                <th width="5%">No</th>
                <th width="45%">Penilaian</th>
                <th width="15%">Hasil<br>Penilaian</th>
                <th width="15%">Bobot</th>
                <th width="20%">Nilai</th>
            </tr>

            <?php
            $no = 1;
            $alphas = range('a', 'z');
            foreach ($penilai_list as $key => $p) {
                $data = $scores[$key];
                
                echo "<tr>";
                echo "<td class='text-center'>{$no}</td>";
                echo "<td><b>{$p['label']}</b></td>";
                echo "<td class='text-center'>".number_format($data['total_avg'], 2)."</td>";
                echo "<td class='text-center'>".($p['bobot']*100)."%</td>";
                echo "<td class='text-center'>".($data['nilai'] > 0 ? (fmod($data['nilai'], 1) !== 0.0 ? number_format($data['nilai'], 3) : number_format($data['nilai'], 0)) : '')."</td>";
                echo "</tr>";

                $a_idx = 0;
                foreach ($kategori_list as $kat) {
                    $val = isset($data['kategori'][$kat]) ? $data['kategori'][$kat] : 0;
                    echo "<tr>";
                    echo "<td></td>";
                    echo "<td>&nbsp;&nbsp;&nbsp;&nbsp; {$alphas[$a_idx]}. {$kat}</td>";
                    echo "<td class='text-center'>".($val > 0 ? (fmod($val, 1) !== 0.0 ? number_format($val, 2) : number_format($val, 0)) : '')."</td>";
                    echo "<td class='bg-gray'></td>";
                    echo "<td class='bg-gray'></td>";
                    echo "</tr>";
                    $a_idx++;
                }
                $no++;
            }
            ?>

            <tr>
                <td colspan="2" class="text-right" style="text-align:right; padding-right:10px;"><b>Total</b></td>
                <td class="bg-gray"></td>
                <td class="bg-gray"></td>
                <td class="text-center"><b><?php echo (fmod($total_nilai_akhir, 1) !== 0.0 ? number_format($total_nilai_akhir, 3) : number_format($total_nilai_akhir, 0)); ?></b></td>
            </tr>
            <tr>
                <td colspan="2" class="text-right" style="text-align:right; padding-right:10px;"><b>Predikat</b></td>
                <td class="bg-gray"></td>
                <td class="bg-gray"></td>
                <td class="text-center"><b><?php echo $predikat_l1; ?></b></td>
            </tr>
        </table>

        <!-- Tabel Konversi Angket ke Skala 100 -->
        <table style="width:100%; border:none; margin-bottom:5px;">
            <tr>
                <td width="20%" style="vertical-align:top;">
                    <div class="text-center" style="background:#d1d5db; border:1px solid #000; padding:12px 5px; font-weight:normal;">
                        Tabel<br>Konversi
                    </div>
                </td>
                <td width="80%">
                    <table class="konversi-table" style="margin-bottom:0; width:100%;">
                        <tr>
                            <td width="80%"><b>Angket</b></td>
                            <td width="20%"></td>
                        </tr>
                        <?php
                        $idx = 1;
                        foreach ($kategori_list as $kat) {
                            $val = isset($konversi[$kat]) ? $konversi[$kat] : 0;
                            echo "<tr>";
                            echo "<td>5.{$idx}. Kompetensi {$kat}</td>";
                            echo "<td class='text-center'>".number_format($val, 0)."</td>";
                            echo "</tr>";
                            $idx++;
                        }
                        ?>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Deskripsi Lampiran 1 -->
        <div class="desc-box">
            <div style="font-weight: bold; margin-bottom: 4px;">DESKRIPSI</div>
            <div><?php echo htmlspecialchars($deskripsi_l1); ?></div>
        </div>

        <!-- Signature -->
        <div class="signature-area clearfix">
            <div class="signature-box">
                Surabaya, <?php echo $tgl_ttd; ?><br>
                Kepala Sekolah,
                <br>
                <img src="<?php echo $qr_url; ?>" alt="QR Code TTD" style="width: 65px; height: 65px; margin: 5px 0;">
                <br>
                <u><b><?php echo htmlspecialchars($nama_kepsek); ?></b></u>
            </div>
        </div>

    </div>
<?php endif; // end show_l1 ?>

<?php if ($show_l2 && $has_tambahan): ?>
    <!-- ========================================== -->
    <!-- LEMBAR KETIGA: LAMPIRAN 2 (TUGAS TAMBAHAN)-->
    <!-- ========================================== -->
    <div style="page-break-before: always;"></div>

    <div class="container">
        <div class="kop">
            [LAMPIRAN 2]
        </div>

        <div class="title" style="margin-bottom: 20px;">
            ANGKET PENILAIAN TUGAS TAMBAHAN
        </div>

        <table class="bio-table">
            <tr>
                <td width="150">Nama</td>
                <td width="10">:</td>
                <td><?php echo htmlspecialchars($ptk['nama_lengkap']); ?></td>
            </tr>
            <tr>
                <td>NIY</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($ptk['niy']); ?></td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>:</td>
                <td><?php 
                    $jab_display = htmlspecialchars($ptk['jabatan'] ? $ptk['jabatan'] : $ptk['jenis_ptk']);
                    if ($is_waka && stripos($jab_display, 'guru') === false) {
                        $jab_display = "Guru / " . $jab_display;
                    }
                    echo $jab_display;
                ?></td>
            </tr>
            <tr>
                <td>Masa Kerja</td>
                <td>:</td>
                <td><?php echo $masa_kerja; ?></td>
            </tr>
        </table>

        <table class="main-table">
            <tr>
                <th width="5%">No</th>
                <th width="55%">Penilaian</th>
                <th width="20%">Hasil<br>Penilaian</th>
                <th width="20%">Konversi</th>
            </tr>
            <tr>
                <td class="text-center" rowspan="<?php echo $tambahan_count + 3; ?>">1</td>
                <td colspan="3"><b>Tugas Tambahan</b></td>
            </tr>
            <?php
            $idx = 5;
            foreach ($tambahan_results as $res) {
                echo "<tr>";
                echo "<td>5.{$idx}. Kompetensi {$res['kategori']}</td>";
                echo "<td class='text-center'>" . (fmod($res['score'], 1) !== 0.0 ? number_format($res['score'], 2) : number_format($res['score'], 0)) . "</td>";
                echo "<td class='text-center'>" . number_format($res['konversi'], 0) . "</td>";
                echo "</tr>";
                $idx++;
            }
            
            $tambahan_avg = $tambahan_count > 0 ? ($tambahan_sum / $tambahan_count) : 0;
            $tambahan_avg_konv = round(($tambahan_avg / 5) * 100);
            
            $pred_tambahan_raw = getPredikatSkala5($tambahan_avg);
            $pred_tambahan_konv = getPredikatSkala5($tambahan_avg);
            ?>
            <tr>
                <td colspan="2" class="text-right" style="padding-right:10px;"><b>Rata-rata</b></td>
                <td class="text-center"><b><?php echo (fmod($tambahan_avg, 1) !== 0.0 ? number_format($tambahan_avg, 2) : number_format($tambahan_avg, 0)); ?></b></td>
                <td class="text-center"><b><?php echo number_format($tambahan_avg_konv, 0); ?></b></td>
            </tr>
            <tr>
                <td colspan="2" class="text-right" style="padding-right:10px;"><b>Predikat</b></td>
                <td class="text-center"><b><?php echo $pred_tambahan_raw; ?></b></td>
                <td class="text-center"><b><?php echo $pred_tambahan_konv; ?></b></td>
            </tr>
        </table>

        <!-- Tabel Referensi Skoring Tugas Tambahan Sesuai Halaman 48 -->
        <table class="main-table" style="margin-top: 30px;">
            <tr>
                <th width="25%">Rentang Skor<br>Rata-Rata</th>
                <th width="25%">Predikat</th>
                <th width="50%">Deskripsi Kinerja</th>
            </tr>
            <tr>
                <td class="text-center">4.51 &ndash; 5.00</td>
                <td>Sangat Baik (Istimewa)</td>
                <td>Kinerja luar biasa, menjadi teladan, dan inovatif di semua aspek.</td>
            </tr>
            <tr>
                <td class="text-center">3.51 &ndash; 4.50</td>
                <td>Baik</td>
                <td>Kinerja di atas standar, sangat disiplin, kompeten, dan proaktif.</td>
            </tr>
            <tr>
                <td class="text-center">2.51 &ndash; 3.50</td>
                <td>Cukup</td>
                <td>Kinerja memenuhi standar minimal; tugas pokok terlaksana dengan cukup.</td>
            </tr>
            <tr>
                <td class="text-center">1.51 &ndash; 2.50</td>
                <td>Kurang</td>
                <td>Kinerja di bawah standar; memerlukan pembinaan dan pengawasan ketat.</td>
            </tr>
            <tr>
                <td class="text-center">1.00 &ndash; 1.50</td>
                <td>Sangat Kurang</td>
                <td>Tidak memenuhi syarat kerja; abai terhadap tugas dan kedisiplinan.</td>
            </tr>
        </table>
    </div>
<?php endif; ?>

</body>
</html>