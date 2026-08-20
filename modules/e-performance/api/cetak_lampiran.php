<?php
/**
 * E-Performance — Cetak Lampiran 1
 * Render Laporan Penilaian Kinerja
 */

require_once __DIR__ . '/config_perf.php';

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
$isAdmin = in_array($auth['role'], ['admin', 'superadmin']);
if (!$isAdmin) {
    if ($auth['ptk_id'] != $ptk_id) {
        die("Akses ditolak. Anda hanya dapat melihat laporan Anda sendiri.");
    }
    if ($periode['is_released'] != 1) {
        die("Akses ditolak. Hasil penilaian untuk periode ini belum dirilis.");
    }
}

// 2. Get PTK Biodata (from perf_ptk and users)
$stmtPTK = $db->prepare("
    SELECT pt.*, u.tmt, u.nama_lengkap, u.username as niy, u.tupoksi, u.jabatan 
    FROM perf_ptk pt
    JOIN users u ON pt.niy COLLATE utf8mb4_unicode_ci = u.username
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
$stmtKS = $db->query("SELECT nama_lengkap, username as nip FROM users WHERE tupoksi = 'Kepala Sekolah' LIMIT 1");
$kepsek = $stmtKS->fetch(PDO::FETCH_ASSOC);
$nama_kepsek = $kepsek ? $kepsek['nama_lengkap'] : "SADIKIN, S.Pd.";

$ptk_jenis = trim($ptk['jenis_ptk'] ? $ptk['jenis_ptk'] : ($ptk['tupoksi'] ? $ptk['tupoksi'] : 'Guru'));

// Tentukan Kategori Utama dan Tugas Tambahan berdasarkan Tupoksi
$kategori_list = [];
$allowed_tambahan = [];

$ptk_jenis_lower = strtolower($ptk_jenis);
if (strpos($ptk_jenis_lower, 'kepala tu') !== false) {
    $kategori_list = ['Kepribadian', 'Sosial', 'Teknis'];
    $allowed_tambahan = ['Manajerial'];
} elseif (strpos($ptk_jenis_lower, 'wakil kepala sekolah') !== false) {
    $kategori_list = ['Pedagogik', 'Kepribadian', 'Sosial', 'Profesional'];
    $allowed_tambahan = ['Manajerial', 'Teknis', 'Kepemimpinan', 'Kewirausahaan'];
} elseif (strpos($ptk_jenis_lower, 'guru') !== false) {
    $kategori_list = ['Pedagogik', 'Kepribadian', 'Sosial', 'Profesional'];
    $allowed_tambahan = []; // Tidak ada tugas tambahan
} else {
    // Default untuk staf lain (IT-Support, Tenaga Administrasi, dll)
    $kategori_list = ['Kepribadian', 'Sosial', 'Teknis'];
    $allowed_tambahan = []; // Tidak ada tugas tambahan
}
$penilai_list = [
    'kepsek'  => ['label' => 'Kepala Sekolah', 'bobot' => 0.40],
    'sendiri' => ['label' => 'Diri Sendiri', 'bobot' => 0.10],
    'teman'   => ['label' => 'Teman Sejawat', 'bobot' => 0.20],
    'siswa'   => ['label' => 'Murid', 'bobot' => 0.30]
];

$scores = [];
foreach ($penilai_list as $key => $p) {
    $scores[$key] = [
        'total_avg' => 0,
        'kategori' => []
    ];
    foreach ($kategori_list as $kat) {
        $scores[$key]['kategori'][$kat] = 0; // Default 0
    }
}

// Query untuk rata-rata nilai per Kategori per tipe Penilai
$stmtNilai = $db->prepare("
    SELECT 
        p.penilai_type, 
        p.penilai_id,
        p.dinilai_ptk_id,
        u.perf_ptk_id as rater_ptk_id,
        i.kategori,
        p.nilai
    FROM perf_penilaian p
    JOIN perf_instrumen i ON p.instrumen_id = i.id
    LEFT JOIN perf_users u ON p.penilai_id = u.id
    WHERE p.periode_id = ? AND p.dinilai_ptk_id = ?
");
$stmtNilai->execute([$periode_id, $ptk_id]);
$rawScores = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

$agg = [];
foreach ($rawScores as $row) {
    $ptype_raw = strtolower($row['penilai_type']);
    $ptype = $ptype_raw;
    if (strpos($ptype_raw, 'siswa') !== false || strpos($ptype_raw, 'murid') !== false) {
        $ptype = 'siswa';
    } elseif (strpos($ptype_raw, 'kepala sekolah') !== false || strpos($ptype_raw, 'kepsek') !== false) {
        $ptype = 'kepsek';
    } else {
        // Selain siswa dan kepsek, anggap sebagai evaluasi sejawat / diri sendiri
        if ($row['rater_ptk_id'] != null && $row['rater_ptk_id'] == $row['dinilai_ptk_id']) {
            $ptype = 'sendiri';
        } else {
            $ptype = 'teman';
        }
    }
    
    $kat_db = trim($row['kategori']);
    
    // Cek apakah kategori ini adalah kategori utama
    $is_main = false;
    $main_kat_name = null;
    foreach ($kategori_list as $k) {
        if (stripos($kat_db, $k) !== false) {
            $is_main = true;
            $main_kat_name = $k;
            break;
        }
    }

    // Cek apakah kategori ini adalah tugas tambahan
    $is_tambahan = false;
    $tambahan_kat_name = null;
    if (!$is_main) {
        foreach ($allowed_tambahan as $at) {
            if (stripos($kat_db, $at) !== false) {
                $is_tambahan = true;
                $tambahan_kat_name = $at;
                break;
            }
        }
    }
    
    if (!$is_main && !$is_tambahan) {
        continue; // Abaikan jika bukan main dan bukan tambahan
    }

    if ($is_main) {
        if (!isset($agg[$ptype])) $agg[$ptype] = [];
        if (!isset($agg[$ptype][$main_kat_name])) $agg[$ptype][$main_kat_name] = ['sum' => 0, 'count' => 0];
        
        $agg[$ptype][$main_kat_name]['sum'] += (float)$row['nilai'];
        $agg[$ptype][$main_kat_name]['count']++;
    } else {
        // Ini adalah kategori angket tambahan (misal: Manajerial, dsb)
        $kat_tambahan = ucwords(strtolower($tambahan_kat_name));
        if (!isset($agg_tambahan[$ptype])) $agg_tambahan[$ptype] = [];
        if (!isset($agg_tambahan[$ptype][$kat_tambahan])) $agg_tambahan[$ptype][$kat_tambahan] = ['sum' => 0, 'count' => 0];
        
        $agg_tambahan[$ptype][$kat_tambahan]['sum'] += (float)$row['nilai'];
        $agg_tambahan[$ptype][$kat_tambahan]['count']++;
    }
}

// Fetch manual scores
$manualData = [];
$stmtManual = $db->prepare("SELECT data FROM perf_penilaian_manual WHERE periode_id = ? AND ptk_id = ? LIMIT 1");
$stmtManual->execute([$periode_id, $ptk_id]);
$manualRow = $stmtManual->fetch(PDO::FETCH_ASSOC);

if ($manualRow && $manualRow['data']) {
    $manualData = json_decode($manualRow['data'], true) ?: [];
}

// Memasukkan hasil agregasi ke array scores
$scores_tambahan = [];
foreach ($agg as $ptype => $kats) {
    $mapped_key = null;
    if ($ptype == 'sendiri' || $ptype == 'diri sendiri') $mapped_key = 'sendiri';
    elseif ($ptype == 'teman' || $ptype == 'teman sejawat') $mapped_key = 'teman';
    elseif (strpos($ptype, 'siswa') !== false || strpos($ptype, 'murid') !== false) $mapped_key = 'siswa';
    elseif (strpos($ptype, 'kepala sekolah') !== false || strpos($ptype, 'kepsek') !== false) $mapped_key = 'kepsek';

    if ($mapped_key) {
        foreach ($kats as $kat => $dataAgg) {
            if (isset($scores[$mapped_key]) && isset($scores[$mapped_key]['kategori'][$kat]) && $dataAgg['count'] > 0) {
                $scores[$mapped_key]['kategori'][$kat] = $dataAgg['sum'] / $dataAgg['count'];
            }
        }
        
        // Proses juga untuk kategori tambahan
        if (isset($agg_tambahan[$ptype])) {
            foreach ($agg_tambahan[$ptype] as $kat => $dataAgg) {
                if (!isset($scores_tambahan[$mapped_key])) $scores_tambahan[$mapped_key] = ['kategori' => []];
                if (!isset($scores_tambahan[$mapped_key]['kategori'][$kat])) $scores_tambahan[$mapped_key]['kategori'][$kat] = 0;
                
                if ($dataAgg['count'] > 0) {
                    $scores_tambahan[$mapped_key]['kategori'][$kat] = $dataAgg['sum'] / $dataAgg['count'];
                }
            }
        }
    }
}

// Menghitung Rata-rata Penilai (Total_Avg)
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
    
    // Rata-rata dari sub-kategori
    $data['total_avg'] = $count_kat > 0 ? ($sum_kat / $count_kat) : 0;
    
    // Hitung Nilai (Hasil * Bobot)
    $data['nilai'] = $data['total_avg'] * $penilai_list[$ptype]['bobot'];
    $rounded_nilai = round($data['nilai'], 2);
    
    $total_nilai_akhir += $rounded_nilai;
}
unset($data);

// Tentukan Predikat
$predikat = "Kurang";
if ($total_nilai_akhir >= 4.5) $predikat = "Sangat Baik";
elseif ($total_nilai_akhir >= 3.5) $predikat = "Baik";
elseif ($total_nilai_akhir >= 2.5) $predikat = "Cukup";

// Deskripsi
$sebutan = (strpos(strtolower($ptk_jenis), 'guru') !== false) ? 'Guru' : 'Pegawai';
$deskripsi = "{$sebutan} perlu banyak bimbingan untuk mencapai standar kompetensi dasar dalam pelayanan pendidikan.";
if ($predikat == "Sangat Baik") {
    $deskripsi = "{$sebutan} telah menunjukkan standar kompetensi yang sangat luar biasa dalam mengajar dan memberikan pelayanan pendidikan secara optimal.";
} elseif ($predikat == "Baik") {
    $deskripsi = "{$sebutan} telah memenuhi standar kompetensi dengan baik dalam mengajar dan memberikan pelayanan pendidikan secara optimal.";
} elseif ($predikat == "Cukup") {
    $deskripsi = "{$sebutan} telah memenuhi standar kompetensi dasar dalam mengajar, namun masih memerlukan bimbingan atau peningkatan pada beberapa aspek tertentu agar lebih optimal dalam pelayanan pendidikan.";
}

// Hitung Konversi Kategori
// Rumus Konversi = (SUM Avg_Kategori / (Jumlah_Penilai * 5)) * 100
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
    $max_skor = $count_penilai * 5; // Skor maksimal per penilai adalah 5
    if ($max_skor > 0) {
        $konversi[$kat] = ($sum_val / $max_skor) * 100;
    } else {
        $konversi[$kat] = 0;
    }
}

// Hitung Konversi untuk Tugas Tambahan (dari Angket)
$konversi_tambahan = [];
$kategori_tambahan_list = [];
foreach ($scores_tambahan as $ptype => $data) {
    foreach ($data['kategori'] as $kat => $val) {
        if (!in_array($kat, $kategori_tambahan_list)) {
            $kategori_tambahan_list[] = $kat;
        }
    }
}

foreach ($kategori_tambahan_list as $kat) {
    $sum_val = 0;
    $count_penilai = 0;
    foreach ($scores_tambahan as $ptype => $data) {
        if (isset($data['kategori'][$kat]) && $data['kategori'][$kat] > 0) {
            $sum_val += $data['kategori'][$kat];
            $count_penilai++;
        }
    }
    $max_skor = $count_penilai * 5;
    if ($max_skor > 0) {
        $konversi_tambahan[$kat] = ($sum_val / $max_skor) * 100;
    } else {
        $konversi_tambahan[$kat] = 0;
    }
}

$school_name = get_setting('nama_sekolah', 'Sekolah');

// Query Manual Instruments (Page 1)
$stmtManualInst = $db->prepare("SELECT id, kategori, pertanyaan, bobot, skor_ya, target_dinilai FROM perf_instrumen WHERE periode_id = ? AND is_manual = 1 ORDER BY urutan ASC, id ASC");
$stmtManualInst->execute([$periode_id]);
$allManualInstRaw = $stmtManualInst->fetchAll(PDO::FETCH_ASSOC);

$main_manual = [];
$tugas_tambahan = [];
$total_bobot_manual = 0;

$category_bobot = [];
foreach ($allManualInstRaw as $inst) {
    if ((float)$inst['bobot'] > 0) {
        $kat = $inst['kategori'];
        if (!isset($category_bobot[$kat])) $category_bobot[$kat] = 0;
        $category_bobot[$kat] += (float)$inst['bobot'];
    }
}

// PATENKAN BOBOT (HARDCODE) SESUAI REQUEST
$total_bobot_manual = 0;
foreach ($category_bobot as $kat => $val) {
    $k_lower = strtolower($kat);
    if (strpos($k_lower, 'perangkat') !== false) {
        $category_bobot[$kat] = 10;
    } elseif (strpos($k_lower, 'rapat') !== false || strpos($k_lower, 'kedinasan') !== false) {
        $category_bobot[$kat] = 10;
    } elseif (strpos($k_lower, 'hadir') !== false) {
        $category_bobot[$kat] = 20;
    } elseif (strpos($k_lower, 'partisipasi') !== false) {
        $category_bobot[$kat] = 30;
    }
    $total_bobot_manual += $category_bobot[$kat];
}

foreach ($allManualInstRaw as $inst) {
    // Filter by target_dinilai
    $targetsStr = trim($inst['target_dinilai'] ? $inst['target_dinilai'] : 'Semua');
    $targets = array_map('trim', explode(',', $targetsStr));
    if ($targetsStr !== 'Semua' && !in_array('Semua', $targets) && !in_array($ptk_jenis, $targets)) {
        continue;
    }

    if ((float)$inst['bobot'] > 0) {
        $main_manual[] = $inst;
    } else {
        $tugas_tambahan[] = $inst;
    }
}
$bobot_angket = max(0, 100 - $total_bobot_manual);

// Calculate Tugas Tambahan
$has_tambahan = false;
$tambahan_results = [];
$tambahan_sum = 0;
$tambahan_count = 0;

if (!empty($tugas_tambahan) && isset($manualData)) {
    foreach ($tugas_tambahan as $tt) {
        $id = $tt['id'];
        $kategori = $tt['kategori'];
        $score = isset($manualData[$id]) ? (float)$manualData[$id] : 0;
        if ($score > 0) {
            $has_tambahan = true;
        }
        $konversi_score = round(($score / 5) * 100);
        $tambahan_results[] = [
            'kategori' => $kategori,
            'score' => $score,
            'konversi' => $konversi_score
        ];
        $tambahan_sum += $score;
        $tambahan_count++;
    }
}

// Angket Score Calculation
$sum_konversi = 0;
$count_konversi = 0;
foreach ($kategori_list as $kat) {
    $sum_konversi += $konversi[$kat];
    $count_konversi++;
}
$angket_score = $count_konversi > 0 ? ($sum_konversi / $count_konversi) : 0;

// Set tanggal tanda tangan
$tgl_ttd_str = (new DateTime($periode['tgl_selesai'] ?? 'now'))->format('d F Y');
// Translasi bulan ke Bahasa Indonesia
$bulan_en = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$bulan_id = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tgl_ttd = str_replace($bulan_en, $bulan_id, $tgl_ttd_str);

// Generate QR Code Link for verification
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
    <title>Cetak Lampiran 1 - <?php echo htmlspecialchars($ptk['nama_lengkap']); ?></title>
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
            font-weight: normal; /* As per image header */
        }
        .text-center {
            text-align: center;
        }
        .bg-gray {
            background-color: #d1d5db;
        }
        .bg-yellow {
            background-color: #fef08a;
        }
        .bg-green {
            background-color: #86efac;
        }
        .desc-box {
            border: 1px solid #000;
            padding: 4px;
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
            margin: 10px 0;
            width: 80px;
            height: 80px;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* Print Specific Adjustments */
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
        
        <?php if (!empty($kop_surat)): ?>
            <div class="kop-surat">
                <img src="../../../<?php echo htmlspecialchars($kop_surat); ?>" alt="Kop Surat">
            </div>
        <?php endif; ?>
        <?php 
        // L1 & L2 parameter handling
        $show_l1 = isset($_GET['l1']) ? $_GET['l1'] == 1 : true;
        $show_l2 = isset($_GET['l2']) ? $_GET['l2'] == 1 : true;
        
        // If no specific parameters passed, show all
        if (!isset($_GET['l1']) && !isset($_GET['l2'])) {
            $show_l1 = true;
            $show_l2 = true;
        }
        
        if ($show_l1): 
        ?>
        <!-- LEMBAR PERTAMA: REKAP KINERJA -->
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
                <td><?php echo htmlspecialchars($ptk['jabatan'] ? $ptk['jabatan'] : $ptk['jenis_ptk']); ?></td>
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
            $total_nilai_page1 = 0;

            $grouped_manual = [];
            foreach ($main_manual as $inst) {
                $kat = $inst['kategori'];
                if (!isset($grouped_manual[$kat])) {
                    $grouped_manual[$kat] = [
                        'total_bobot' => isset($category_bobot[$kat]) ? $category_bobot[$kat] : 0,
                        'total_nilai' => 0,
                        'achieved_score' => 0,
                        'max_score' => 0,
                        'items' => []
                    ];
                }
                $score = isset($manualData[$inst['id']]) ? (float)$manualData[$inst['id']] : 0;
                $bobot = (float)$inst['bobot'];
                $skor_ya = (float)($inst['skor_ya'] > 0 ? $inst['skor_ya'] : 100);
                
                $grouped_manual[$kat]['items'][] = [
                    'pertanyaan' => $inst['pertanyaan'],
                    'score' => $score,
                    'bobot' => $bobot,
                    'kategori' => $inst['kategori']
                ];
                $grouped_manual[$kat]['achieved_score'] += $score;
                $grouped_manual[$kat]['max_score'] += $skor_ya;
            }

            foreach ($grouped_manual as $kat => $group) {
                $avg_score = $group['max_score'] > 0 ? ($group['achieved_score'] / $group['max_score']) * 100 : 0;
                $group_nilai = $avg_score * ($group['total_bobot'] / 100);
                $rounded_group_nilai = round($group_nilai, 2);
                
                $total_nilai_page1 += $rounded_group_nilai;
                
                // Print group header
                echo "<tr>";
                echo "<td class='text-center'>{$no}</td>";
                echo "<td><b>".htmlspecialchars($kat)."</b></td>";
                echo "<td class='text-center'>".number_format($avg_score, 2)."</td>";
                echo "<td class='text-center'>".number_format($group['total_bobot'], 0)."%</td>";
                echo "<td class='text-center'>".number_format($rounded_group_nilai, 2)."</td>";
                echo "</tr>";

                $no++;
            }

            $nilai_angket = $angket_score * ($bobot_angket / 100);
            $rounded_nilai_angket = round($nilai_angket, 2);
            $total_nilai_page1 += $rounded_nilai_angket;

            echo "<tr>";
            echo "<td class='text-center'>{$no}</td>";
            echo "<td>Angket</td>";
            echo "<td class='text-center'>".number_format($angket_score, 2)."</td>";
            echo "<td class='text-center'>{$bobot_angket}%</td>";
            echo "<td class='text-center'>".number_format($rounded_nilai_angket, 2)."</td>";
            echo "</tr>";

            $sub_no = 1;
            foreach ($kategori_list as $kat) {
                echo "<tr>";
                echo "<td></td>";
                echo "<td>{$no}.{$sub_no}. Kompetensi {$kat}</td>";
                echo "<td class='text-center'>".number_format($konversi[$kat], 2)."</td>";
                echo "<td class='bg-gray'></td><td class='bg-gray'></td>";
                echo "</tr>";
                $sub_no++;
            }

            if (count($kategori_tambahan_list) > 0) {
                echo "<tr><td></td><td>Tugas Tambahan</td><td class='bg-gray'></td><td class='bg-gray'></td><td class='bg-gray'></td></tr>";
                foreach ($kategori_tambahan_list as $kat) {
                    $score = isset($konversi_tambahan[$kat]) ? $konversi_tambahan[$kat] : 0;
                    echo "<tr>";
                    echo "<td></td>";
                    echo "<td>{$no}.{$sub_no}. Kompetensi ".htmlspecialchars($kat)."</td>";
                    echo "<td class='text-center'>".number_format($score, 2)."</td>";
                    echo "<td class='bg-gray'></td><td class='bg-gray'></td>";
                    echo "</tr>";
                    $sub_no++;
                }
            }

            $predikat_page1 = "Kurang";
            if ($total_nilai_page1 >= 90) $predikat_page1 = "Sangat Baik";
            elseif ($total_nilai_page1 >= 75) $predikat_page1 = "Baik";
            elseif ($total_nilai_page1 >= 60) $predikat_page1 = "Cukup";
            
            echo "<tr><td colspan='4' class='text-right'>JUMLAH</td><td class='text-center'>".number_format($total_nilai_page1, 2)."</td></tr>";
            echo "<tr><td colspan='4' class='text-right'>PREDIKAT</td><td class='text-center'>{$predikat_page1}</td></tr>";
            ?>
        </table>

        <div class="desc-box">
            DESKRIPSI<br>
            <?php echo $deskripsi; ?>
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

        <!-- LEMBAR KEDUA: LAMPIRAN 1 -->

        <div class="kop">
            [LAMPIRAN 1]
        </div>

        <div class="title">
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
                <td><?php echo htmlspecialchars($ptk['jabatan'] ? $ptk['jabatan'] : $ptk['jenis_ptk']); ?></td>
            </tr>
            <tr>
                <td>Masa Kerja</td>
                <td>:</td>
                <td><?php echo $masa_kerja; ?></td>
            </tr>
        </table>

        <!-- Tabel Penilaian -->
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
            foreach ($penilai_list as $key => $p) {
                $data = $scores[$key];
                
                // Indeks alfabet untuk sub-kategori
                $alphas = range('a', 'z');
                
                echo "<tr>";
                echo "<td class='text-center'>{$no}</td>";
                echo "<td>{$p['label']}</td>";
                echo "<td class='text-center'>".number_format($data['total_avg'], 2)."</td>";
                echo "<td class='text-center'>".($p['bobot']*100)."%</td>";
                echo "<td class='text-center'>".($data['nilai'] > 0 ? number_format($data['nilai'], 2) : '')."</td>";
                echo "</tr>";

                $a_idx = 0;
                foreach ($kategori_list as $kat) {
                    $val = $data['kategori'][$kat];
                    $bg_class = '';
                    if ($no == 1 && $kat == 'Pedagogik') $bg_class = 'bg-yellow';
                    if ($no == 1 && $kat == 'Kepribadian') $bg_class = 'bg-green';
                    if ($no == 2 && $kat == 'Pedagogik') $bg_class = 'bg-yellow';
                    if ($no == 2 && $kat == 'Kepribadian') $bg_class = 'bg-green';
                    if ($no == 3 && $kat == 'Pedagogik') $bg_class = 'bg-yellow';
                    if ($no == 3 && $kat == 'Kepribadian') $bg_class = 'bg-green';
                    if ($no == 4 && $kat == 'Pedagogik') $bg_class = 'bg-yellow';
                    if ($no == 4 && $kat == 'Kepribadian') $bg_class = 'bg-green';
                    // We remove hardcoded background colors if they were just examples in the image
                    // Wait, the image shows yellow for Pedagogik, green for Kepribadian, but only as a highlight for explanation?
                    // Actually, I won't hardcode colors because it was probably highlighted by the user for me.
                    $bg_class = ''; // Removed highlighting

                    echo "<tr>";
                    echo "<td></td>";
                    echo "<td>&nbsp;&nbsp;&nbsp;&nbsp; {$alphas[$a_idx]}. {$kat}</td>";
                    echo "<td class='text-center {$bg_class}'>".($val > 0 ? number_format($val, 2) : '')."</td>";
                    echo "<td class='bg-gray'></td>";
                    echo "<td class='bg-gray'></td>";
                    echo "</tr>";
                    $a_idx++;
                }
                $no++;
            }
            ?>

            <tr>
                <td colspan="2" class="text-right" style="text-align:right; padding-right:10px;">Total</td>
                <td class="bg-gray"></td>
                <td class="bg-gray"></td>
                <td class="text-center"><?php echo number_format($total_nilai_akhir, 2); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="text-right" style="text-align:right; padding-right:10px;">Predikat</td>
                <td class="bg-gray"></td>
                <td class="bg-gray"></td>
                <td class="text-center"><?php echo $predikat; ?></td>
            </tr>
        </table>

        <!-- Tabel Konversi -->
        <table style="width:100%; border:none; margin-bottom:5px;">
            <tr>
                <td width="20%" style="vertical-align:top;">
                    <div class="text-center" style="background:#d1d5db; border:1px solid #000; padding:5px;">
                        Tabel<br>Konversi
                    </div>
                </td>
                <td width="80%">
                    <table class="konversi-table" style="margin-bottom:0; width:100%;">
                        <tr>
                            <td width="80%">Angket</td>
                            <td width="20%"></td>
                        </tr>
                        <?php
                        $idx = 1;
                        foreach ($kategori_list as $kat) {
                            $val = $konversi[$kat];
                            echo "<tr>";
                            echo "<td>5.{$idx}. Kompetensi {$kat}</td>";
                            echo "<td class='text-center'>".number_format($val, 2)."</td>";
                            echo "</tr>";
                            $idx++;
                        }
                        ?>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Deskripsi -->
        <div class="desc-box">
            DESKRIPSI<br>
            <?php echo $deskripsi; ?>
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
    <?php if ($show_l1): ?>
    <div style="page-break-before: always;"></div>
    <?php endif; ?>

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
            <td><?php echo htmlspecialchars($ptk['jabatan'] ? $ptk['jabatan'] : $ptk['jenis_ptk']); ?></td>
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
            <td colspan="3">Tugas Tambahan</td>
        </tr>
        <?php
        $idx = 5;
        foreach ($tambahan_results as $res) {
            echo "<tr>";
            echo "<td>5.{$idx}. {$res['kategori']}</td>";
            echo "<td class='text-center'>" . number_format($res['score'], 0) . "</td>";
            echo "<td class='text-center'>{$res['konversi']}</td>";
            echo "</tr>";
            $idx++;
        }
        
        $tambahan_avg = $tambahan_count > 0 ? $tambahan_sum / $tambahan_count : 0;
        $tambahan_avg_konv = round(($tambahan_avg / 5) * 100);
        
        $pred_tambahan = "Sangat Kurang";
        if ($tambahan_avg >= 4.51) $pred_tambahan = "Sangat Baik (Istimewa)";
        elseif ($tambahan_avg >= 3.51) $pred_tambahan = "Baik";
        elseif ($tambahan_avg >= 2.51) $pred_tambahan = "Cukup";
        elseif ($tambahan_avg >= 1.51) $pred_tambahan = "Kurang";
        ?>
        <tr>
            <td colspan="2" class="text-right" style="padding-right:10px;">Rata-rata</td>
            <td class="text-center"><?php echo round($tambahan_avg, 2); ?></td>
            <td class="text-center"><?php echo $tambahan_avg_konv; ?></td>
        </tr>
        <tr>
            <td colspan="2" class="text-right" style="padding-right:10px;">Predikat</td>
            <td class="text-center"><?php echo $pred_tambahan; ?></td>
            <td class="text-center"><?php echo $pred_tambahan; ?></td>
        </tr>
    </table>

    <table class="main-table" style="margin-top: 30px;">
        <tr>
            <th>Rentang Skor<br>Rata-Rata</th>
            <th>Predikat</th>
            <th>Deskripsi Kinerja</th>
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
<?php endif; ?>

<?php
// Lampiran tambahan (Diri Sendiri dan Siswa) dihapus atas permintaan pengguna
// require_once __DIR__ . '/cetak_lampiran_tambahan.php';
?>

<?php
// Tampilkan rekap keseluruhan untuk semua PTK di lembar paling terakhir
define('REKAP_INCLUDED', true);
require_once __DIR__ . '/cetak_rekap_keseluruhan.php';
?>

</body>
</html>
