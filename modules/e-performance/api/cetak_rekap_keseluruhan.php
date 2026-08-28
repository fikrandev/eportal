<?php
/**
 * Cetak Rekap Keseluruhan
 */
require_once __DIR__ . '/config_perf.php';
perf_auth_check();

$db = db();
$periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;

if (!$periode_id) {
    die("Periode ID diperlukan.");
}

function getDeskripsiPenilaian($db, $tupoksi, $nilai) {
    $stmt = $db->prepare("SELECT deskripsi FROM perf_deskripsi WHERE tupoksi = ? AND ? >= min_nilai AND ? <= max_nilai LIMIT 1");
    $stmt->execute([$tupoksi, $nilai, $nilai]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ? $res['deskripsi'] : '-';
}


// 1. Get Periode
$stmtPer = $db->prepare("SELECT * FROM perf_periode WHERE id = ?");
$stmtPer->execute([$periode_id]);
$periode = $stmtPer->fetch(PDO::FETCH_ASSOC);
if (!$periode) die("Periode tidak ditemukan.");

// 2. Get School Info
$school_name = get_setting('nama_sekolah', 'Sekolah');
$kop_surat = get_setting('kop_surat', '');

// 3. Get Manual Instruments
$stmtManualInst = $db->prepare("SELECT id, kategori, bobot, skor_ya, target_dinilai FROM perf_instrumen WHERE periode_id = ? AND is_manual = 1 ORDER BY urutan ASC, id ASC");
$stmtManualInst->execute([$periode_id]);
$allManualInst = $stmtManualInst->fetchAll(PDO::FETCH_ASSOC);

$main_manual = [];
$category_bobot = [];
foreach ($allManualInst as $inst) {
    if ((float)$inst['bobot'] > 0) {
        $kat = trim($inst['kategori']);
        if (!isset($main_manual[$kat])) {
            $main_manual[$kat] = [
                'kategori' => $kat,
                'bobot' => 0,
                'ids' => []
            ];
            $category_bobot[$kat] = 0;
        }
        $main_manual[$kat]['bobot'] += (float)$inst['bobot'];
        $main_manual[$kat]['ids'][] = $inst;
        $category_bobot[$kat] += (float)$inst['bobot'];
    }
}

// PATENKAN BOBOT (HARDCODE) SESUAI REQUEST
$total_bobot_manual = 0;
foreach ($category_bobot as $kat => $val) {
    $k_lower = strtolower($kat);
    if (strpos($k_lower, 'perangkat') !== false) {
        $category_bobot[$kat] = 10;
        $main_manual[$kat]['bobot'] = 10;
    } elseif (strpos($k_lower, 'rapat') !== false || strpos($k_lower, 'kedinasan') !== false) {
        $category_bobot[$kat] = 10;
        $main_manual[$kat]['bobot'] = 10;
    } elseif (strpos($k_lower, 'hadir') !== false) {
        $category_bobot[$kat] = 20;
        $main_manual[$kat]['bobot'] = 20;
    } elseif (strpos($k_lower, 'partisipasi') !== false) {
        $category_bobot[$kat] = 30;
        $main_manual[$kat]['bobot'] = 30;
    }
    $total_bobot_manual += $category_bobot[$kat];
}
$bobot_angket = max(0, 100 - $total_bobot_manual);

// Ambil semua kategori angket
$stmtValidKategori = $db->prepare("SELECT DISTINCT kategori, target_dinilai FROM perf_instrumen WHERE periode_id = ? AND is_manual = 0");
$stmtValidKategori->execute([$periode_id]);
$all_angket_kategori = $stmtValidKategori->fetchAll(PDO::FETCH_ASSOC);

// Kategori Baku
$kategori_list_raw = ['Pedagogik', 'Kepribadian', 'Sosial', 'Profesional'];
$penilai_list = [
    'sendiri' => ['label' => 'Diri Sendiri', 'bobot' => 0.10],
    'teman'   => ['label' => 'Teman Sejawat', 'bobot' => 0.20],
    'siswa'   => ['label' => 'Murid', 'bobot' => 0.30],
    'kepsek'  => ['label' => 'Kepala Sekolah', 'bobot' => 0.40]
];

// Define Columns for the table based on Lampiran 1
$columns = [];
foreach ($main_manual as $kat => $m) {
    $columns[] = [
        'type' => 'manual',
        'key' => $kat,
        'bobot' => $category_bobot[$kat]
    ];
}
// Note: Angket di-handle terpisah, tidak dimasukkan ke $columns

// 6. Get All Active PTKs (JOIN users untuk ambil tupoksi)
$stmtPtk = $db->prepare("
    SELECT pt.id, pt.nama, pt.niy, pt.jabatan, pt.jenis_ptk, 
           COALESCE(u.tupoksi, pt.jenis_ptk) as tupoksi
    FROM perf_ptk pt
    LEFT JOIN users u ON pt.niy COLLATE utf8mb4_unicode_ci = u.username
    WHERE pt.status = 1 
    ORDER BY pt.jenis_ptk ASC, pt.nama ASC
");
$stmtPtk->execute();
$all_ptks = $stmtPtk->fetchAll(PDO::FETCH_ASSOC);

$results = [];

foreach ($all_ptks as $ptk) {
    $ptk_id = $ptk['id'];
    $ptk_jenis = $ptk['jenis_ptk'];
    
    // Calculate Manual Scores
    $stmtManual = $db->prepare("SELECT data FROM perf_penilaian_manual WHERE periode_id = ? AND ptk_id = ? LIMIT 1");
    $stmtManual->execute([$periode_id, $ptk_id]);
    $manualRow = $stmtManual->fetch(PDO::FETCH_ASSOC);
    $manualData = $manualRow && $manualRow['data'] ? json_decode($manualRow['data'], true) : [];
    
    $ptk_scores = [];
    $total_score_ptk = 0;
    
    // Calculate Angket
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

    $ptk_jenis = trim($ptk['jenis_ptk'] ? $ptk['jenis_ptk'] : 'Guru');
    $ptk_jenis_lower = strtolower($ptk_jenis);

    // Tentukan Kategori Utama secara dinamis dari rawScores (angket)
    $kategori_list = [];
    foreach ($rawScores as $rScore) {
        $kName = trim($rScore['kategori']);
        if ($kName && !in_array($kName, $kategori_list)) {
            $kategori_list[] = $kName;
        }
    }

    $scores = [];
    foreach ($penilai_list as $key => $p) {
        $scores[$key] = ['total_avg' => 0, 'kategori' => []];
        foreach ($kategori_list as $kat) {
            $scores[$key]['kategori'][$kat] = 0;
        }
    }

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
        
        // Loose matching for kategori
        $kat_db = trim($row['kategori']);
        
        // Cek apakah kategori ini ada di list
        $is_main = false;
        $main_kat_name = null;
        foreach ($kategori_list as $k) {
            if (stripos($kat_db, $k) !== false) {
                $is_main = true;
                $main_kat_name = $k;
                break;
            }
        }
        
        if (!$is_main) {
            continue;
        }
        
        $kat = $main_kat_name;
        
        if (!isset($agg[$ptype])) $agg[$ptype] = [];
        if (!isset($agg[$ptype][$kat])) $agg[$ptype][$kat] = [];
        $agg[$ptype][$kat][] = (int)$row['nilai'];
    }

    // Average per Kategori
    foreach ($agg as $ptype => $kats) {
        // Map penilai_type to our keys
        $mapped_key = null;
        if ($ptype == 'sendiri' || $ptype == 'diri sendiri') $mapped_key = 'sendiri';
        elseif ($ptype == 'teman' || $ptype == 'teman sejawat') $mapped_key = 'teman';
        elseif (strpos($ptype, 'siswa') !== false || strpos($ptype, 'murid') !== false) $mapped_key = 'siswa';
        elseif (strpos($ptype, 'kepala sekolah') !== false || strpos($ptype, 'kepsek') !== false) $mapped_key = 'kepsek';
        
        if ($mapped_key && isset($scores[$mapped_key])) {
            foreach ($kats as $kat => $vals) {
                if (isset($scores[$mapped_key]['kategori'][$kat]) && count($vals) > 0) {
                    $scores[$mapped_key]['kategori'][$kat] = array_sum($vals) / count($vals);
                }
            }
        }
    }

    // Konversi per kategori (TANPA pembulatan, sama persis dengan cetak_lampiran.php)
    $konversi = [];
    foreach ($kategori_list as $kat) {
        $sum_val = 0;
        $count_penilai = 0;
        foreach ($penilai_list as $key => $p) {
            if ($scores[$key]['kategori'][$kat] > 0) {
                $sum_val += $scores[$key]['kategori'][$kat];
                $count_penilai++;
            }
        }
        $max_skor = $count_penilai * 5;
        if ($max_skor > 0) {
            $konversi[$kat] = ($sum_val / $max_skor) * 100;
        } else {
            $konversi[$kat] = 0;
        }
    }

    // Angket Score Calculation (Match cetak_lampiran.php exactly)
    $sum_konversi = 0;
    $count_konversi = 0;
    foreach ($kategori_list as $kat) {
        $sum_konversi += $konversi[$kat];
        $count_konversi++;
    }
    $angket_score = $count_konversi > 0 ? ($sum_konversi / $count_konversi) : 0;
    $angket_nilai_weighted = round($angket_score * ($bobot_angket / 100), 2);


    // Build PTK Scores sequentially exactly like cetak_lampiran.php
    $ptk_jenis = trim($ptk['jenis_ptk'] ? $ptk['jenis_ptk'] : ($ptk['tupoksi'] ? $ptk['tupoksi'] : 'Guru'));
    $ptk_main_manual = [];
    foreach ($allManualInst as $inst) {
        $targetsStr = trim($inst['target_dinilai'] ? $inst['target_dinilai'] : 'Semua');
        $targets = array_map('trim', explode(',', $targetsStr));
        if ($targetsStr !== 'Semua' && !in_array('Semua', $targets) && !in_array($ptk_jenis, $targets)) {
            continue;
        }
        if ((float)$inst['bobot'] > 0) {
            $ptk_main_manual[] = $inst;
        }
    }

    $grouped_manual = [];
    foreach ($ptk_main_manual as $inst) {
        $kat = $inst['kategori'];
        if (!isset($grouped_manual[$kat])) {
            $grouped_manual[$kat] = [
                'total_bobot' => isset($category_bobot[$kat]) ? $category_bobot[$kat] : 0,
                'achieved_score' => 0,
                'max_score' => 0
            ];
        }
        $score = isset($manualData[$inst['id']]) ? (float)$manualData[$inst['id']] : 0;
        $skor_ya = (float)($inst['skor_ya'] > 0 ? $inst['skor_ya'] : 100);
        $grouped_manual[$kat]['achieved_score'] += $score;
        $grouped_manual[$kat]['max_score'] += $skor_ya;
    }

    foreach ($grouped_manual as $kat => $group) {
        $avg_score = $group['max_score'] > 0 ? ($group['achieved_score'] / $group['max_score']) * 100 : 0;
        $group_nilai = round($avg_score * ($group['total_bobot'] / 100), 2);
        
        // Simpan nilai Skala 100 ($avg_score) ke array agar persis dengan kolom 'Hasil Penilaian Skala 100' di Lembar 1
        $ptk_scores[] = $avg_score;
        $total_score_ptk += $group_nilai;
    }

    // Pad with empty strings if this PTK has fewer items than the max columns
    $max_columns = count($columns);
    while (count($ptk_scores) < $max_columns) {
        $ptk_scores[] = '';
    }
    
    // Add Angket Score separately (Skala 100)
    $ptk_scores[] = $angket_score;
    $total_score_ptk += $angket_nilai_weighted;
    
    $predikat = "Kurang";
    if ($total_score_ptk >= 90) $predikat = "Sangat Baik";
    elseif ($total_score_ptk >= 75) $predikat = "Baik";
    elseif ($total_score_ptk >= 60) $predikat = "Cukup";
    
    // In screenshot it uses 'B' for Baik? The logic earlier in cetak_lampiran.php had "Baik", "Sangat Baik" etc.
    // Let's use the initials as in screenshot (B, SB, C, K) or full text?
    // Screenshot: "B". We'll map it to initials if needed, or just display full text if it's "Baik" = "B"
    $pred_initial = "K";
    if ($predikat == "Sangat Baik") $pred_initial = "SB";
    elseif ($predikat == "Baik") $pred_initial = "B";
    elseif ($predikat == "Cukup") $pred_initial = "C";
    
    $results[] = [
        'nama' => $ptk['nama'],
        'jabatan' => $ptk['jabatan'] ? $ptk['jabatan'] : $ptk['jenis_ptk'],
        'jenis_ptk' => $ptk_jenis,
        'scores' => $ptk_scores,
        'tot' => $total_score_ptk,
        'predikat' => $pred_initial
    ];
}

// Set tanggal tanda tangan
$tgl_ttd_str = (new DateTime($periode['tgl_selesai'] ?? 'now'))->format('d F Y');
$bulan_en = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$bulan_id = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tgl_ttd = str_replace($bulan_en, $bulan_id, $tgl_ttd_str);

$nama_kepsek = "SADIKIN, S.Pd."; // This might need to come from settings
$setting_kepsek = get_setting('kepala_sekolah', '');
if ($setting_kepsek) $nama_kepsek = $setting_kepsek;

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$full_base_url = rtrim($protocol . "://" . $host . BASE_URL, '/');
$qr_data = $full_base_url . "/modules/e-performance/verify_rekap.php?periode=" . $periode_id;
$qr_url = $full_base_url . "/modules/e-xam-card/api/qr.php?size=4&data=" . urlencode($qr_data);
?>
<?php $is_included_rekap = defined('REKAP_INCLUDED'); ?>
<?php if (!$is_included_rekap): ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Penilaian Kinerja</title>
<?php endif; ?>
    <style>
        @page {
            size: 215.9mm 330.2mm; /* F4 / Folio Size */
            margin: 10mm 15mm;
        }
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 11pt;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
        }
        .kop-surat {
            text-align: center;
            margin-bottom: 20px;
        }
        .kop-surat img {
            max-width: 100%;
            height: auto;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11pt;
        }
        .main-table th, .main-table td {
            border: 1px solid #000;
            padding: 4px 5px;
        }
        .main-table th {
            text-align: center;
            vertical-align: middle;
        }
        .text-center {
            text-align: center;
        }
        .signature-area {
            width: 100%;
            margin-top: 30px;
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
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
<?php if (!$is_included_rekap): ?>
</head>
<body>
    <div class="print-actions">
        <button onclick="window.print()" style="padding:8px 16px; background:#10B981; color:white; border:none; border-radius:4px; font-size:14px; cursor:pointer;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:5px;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Rekapitulasi
        </button>
        <button onclick="window.close()" style="padding:8px 16px; background:#fff; color:#374151; border:1px solid #d1d5db; border-radius:4px; font-size:14px; cursor:pointer; margin-left:10px;">
            Tutup
        </button>
    </div>
<?php else: ?>
    <div style="page-break-before: always;"></div>
<?php endif; ?>

    <div class="container">
        <?php if (!empty($kop_surat)): ?>
            <div class="kop-surat">
                <img src="../../../<?php echo htmlspecialchars($kop_surat); ?>" alt="Kop Surat">
            </div>
        <?php endif; ?>

        <div class="title">
            REKAPITULASI PENILAIAN KINERJA<br>
            <?php echo htmlspecialchars(strtoupper($school_name)); ?><br>
            PERIODE : <?php 
                $sem_label = ($periode['semester'] == '1') ? 'GANJIL' : (($periode['semester'] == '2') ? 'GENAP' : strtoupper($periode['semester']));
                $nama_periode = strtoupper($periode['nama_periode'] ?? '');
                echo htmlspecialchars($nama_periode . ' (' . $periode['tahun_ajaran'] . ' (' . $sem_label . '))'); 
            ?>
        </div>

        <table class="main-table">
            <thead>
                <tr>
                    <th rowspan="2" width="5%">No</th>
                    <th rowspan="2" width="25%">Nama</th>
                    <th rowspan="2" width="20%">Jabatan</th>
                    <th colspan="<?php echo count($columns) + 1; ?>">Penilaian</th>
                    <th rowspan="2" width="10%">TOT</th>
                    <th rowspan="2" width="10%">Predikat</th>
                    <th rowspan="2" width="25%">Deskripsi</th>
                </tr>
                <tr>
                    <?php for($i=1; $i<=count($columns) + 1; $i++): ?>
                    <th><?php echo $i; ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($results as $r): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($r['nama']); ?></td>
                    <td><?php echo htmlspecialchars($r['jabatan']); ?></td>
                    <?php foreach ($r['scores'] as $score): ?>
                    <td class="text-center"><?php echo $score === '' ? '' : (fmod((float)$score, 1) !== 0.0 ? number_format($score, 2) : number_format($score, 0)); ?></td>
                    <?php endforeach; ?>
                    <td class="text-center"><?php echo fmod((float)$r['tot'], 1) !== 0.0 ? number_format($r['tot'], 2) : number_format($r['tot'], 0); ?></td>
                    <td class="text-center"><?php echo $r['predikat']; ?></td>
                    <td><?php echo getDeskripsiPenilaian($db, $r['jenis_ptk'], $r['tot']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Legend removed per user request -->

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
<?php if (!$is_included_rekap): ?>
</body>
</html>
<?php endif; ?>
