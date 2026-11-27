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
$roleFlags = getPtkRoleFlags($ptk);
$ptk_jenis = $roleFlags['ptk_jenis'];
$group_role = $roleFlags['group_role'];
$is_staff = $roleFlags['is_staff'];
$is_waka = $roleFlags['is_waka'];
$is_ka_tu = $roleFlags['is_ka_tu'];
$is_ka_perpus = $roleFlags['is_ka_perpus'];
$is_ka_lab = $roleFlags['is_ka_lab'];
$allowed_tambahan = $roleFlags['allowed_tambahan'];

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
    WHERE p.periode_id = ? AND p.dinilai_ptk_id = ? AND i.is_manual = 0
");
$stmtNilai->execute([$periode_id, $ptk_id]);
$rawScores = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

// Struktur Penilai
$penilai_list = [
    'kepsek'  => ['label' => 'Kepala Sekolah', 'bobot' => 0.40],
    'sendiri' => ['label' => 'Diri Sendiri', 'bobot' => 0.10],
    'teman'   => ['label' => 'Teman Sejawat', 'bobot' => 0.20],
    'siswa'   => ['label' => 'Murid', 'bobot' => 0.30]
];

$angketCalc = calculateAngketScores($rawScores, $is_staff, $is_waka, $allowed_tambahan, $penilai_list);
$kategori_list = $angketCalc['kategori_list'];
$kategori_tambahan_list = $angketCalc['kategori_tambahan_list'];
$scores = $angketCalc['scores'];
$total_nilai_akhir = $angketCalc['total_nilai_akhir'];
$konversi = $angketCalc['konversi'];
$angket_score = $angketCalc['angket_score'];
$bobot_angket = $angketCalc['bobot_angket'];
$rounded_nilai_angket = $angketCalc['rounded_nilai_angket'];
$has_tambahan = $angketCalc['has_tambahan'];
$tambahan_results = $angketCalc['tambahan_results'];
$tambahan_sum = $angketCalc['tambahan_sum'];
$tambahan_count = $angketCalc['tambahan_count'];

// Predikat & Deskripsi untuk Lampiran 1 (Skala 1-5)
$predikat_l1 = getPredikatSkala5($total_nilai_akhir);
$deskripsi_l1 = getDeskripsiAngketLampiran($db, $ptk_jenis, $total_nilai_akhir);

// 5. Fetch Manual Instruments (Page 1)
$stmtManual = $db->prepare("SELECT data FROM perf_penilaian_manual WHERE periode_id = ? AND ptk_id = ? LIMIT 1");
$stmtManual->execute([$periode_id, $ptk_id]);
$manualRow = $stmtManual->fetch(PDO::FETCH_ASSOC);
$manualData = ($manualRow && $manualRow['data']) ? json_decode($manualRow['data'], true) : [];

$stmtManualInst = $db->prepare("SELECT id, kategori, pertanyaan, bobot, skor_ya, target_dinilai, urutan FROM perf_instrumen WHERE periode_id = ? AND is_manual = 1 ORDER BY urutan ASC, id ASC");
$stmtManualInst->execute([$periode_id]);
$allManualInstRaw = $stmtManualInst->fetchAll(PDO::FETCH_ASSOC);

$grouped_manual = calculateManualGroups($allManualInstRaw, $manualData, $ptk_jenis, $is_staff);
$no_angket = count($grouped_manual) + 1;

// Hitung Total Nilai Rapor Kinerja Utama
$total_nilai_page1 = 0;
foreach ($grouped_manual as $kat => $group) {
    $total_nilai_page1 += $group['group_nilai'];
}

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
                echo "<td class='text-center'>".number_format(round($group['avg_score']), 0)."</td>";
                echo "<td class='text-center'>".number_format($group['total_bobot'], 0)."%</td>";
                echo "<td class='text-center'>".number_format(round($group['group_nilai']), 0)."</td>";
                echo "</tr>";
                $no++;
            }

            // 2. Tampilkan Angket (Komponen Utama)
            $no_angket = $no;
            echo "<tr>";
            echo "<td class='text-center'>{$no_angket}</td>";
            echo "<td>Angket</td>";
            echo "<td class='text-center'>".number_format(round($angket_score), 0)."</td>";
            echo "<td class='text-center'>{$bobot_angket}%</td>";
            echo "<td class='text-center'>".number_format(round($rounded_nilai_angket), 0)."</td>";
            echo "</tr>";

            // Sub-kategori Angket Utama (5.1, 5.2, 5.3, 5.4)
            $sub_no = 1;
            foreach ($kategori_list as $kat) {
                $val_k = isset($konversi[$kat]) ? $konversi[$kat] : 0;
                echo "<tr>";
                echo "<td></td>";
                echo "<td>{$no_angket}.{$sub_no}. Kompetensi {$kat}</td>";
                echo "<td class='text-center'>".number_format(round($val_k), 0)."</td>";
                echo "<td class='bg-gray'></td><td class='bg-gray'></td>";
                echo "</tr>";
                $sub_no++;
            }

            // 3. Jika Ada Tugas Tambahan, tampilkan di bawahnya
            if ($has_tambahan) {
                echo "<tr><td></td><td>Tugas Tambahan</td><td class='bg-gray'></td><td class='bg-gray'></td><td class='bg-gray'></td></tr>";
                foreach ($tambahan_results as $tt) {
                    echo "<tr>";
                    echo "<td></td>";
                    echo "<td>{$no_angket}.{$sub_no}. Kompetensi ".htmlspecialchars($tt['kategori'])."</td>";
                    echo "<td class='text-center'>".number_format(round($tt['konversi']), 0)."</td>";
                    echo "<td class='bg-gray'></td><td class='bg-gray'></td>";
                    echo "</tr>";
                    $sub_no++;
                }
            }

            echo "<tr><td></td><td class='text-right' style='padding-right:8px;'><b>JUMLAH</b></td><td class='bg-gray'></td><td class='bg-gray'></td><td class='text-center'><b>".number_format(round($total_nilai_page1), 0)."</b></td></tr>";
            echo "<tr><td></td><td class='text-right' style='padding-right:8px;'><b>PREDIKAT</b></td><td class='bg-gray'></td><td class='bg-gray'></td><td class='text-center'><b>{$predikat_page1}</b></td></tr>";
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
                echo "<td class='text-center'>".number_format(round($data['total_avg']), 0)."</td>";
                echo "<td class='text-center'>".($p['bobot']*100)."%</td>";
                echo "<td class='text-center'>".($data['nilai'] > 0 ? number_format(round($data['nilai']), 0) : '')."</td>";
                echo "</tr>";

                $a_idx = 0;
                foreach ($kategori_list as $kat) {
                    $val = isset($data['kategori'][$kat]) ? $data['kategori'][$kat] : 0;
                    echo "<tr>";
                    echo "<td></td>";
                    echo "<td>&nbsp;&nbsp;&nbsp;&nbsp; {$alphas[$a_idx]}. {$kat}</td>";
                    echo "<td class='text-center'>".($val > 0 ? number_format(round($val), 0) : '')."</td>";
                    echo "<td class='bg-gray'></td>";
                    echo "<td class='bg-gray'></td>";
                    echo "</tr>";
                    $a_idx++;
                }
                $no++;
            }
            ?>

            <tr>
                <td></td>
                <td class="text-right" style="text-align:right; padding-right:10px;"><b>Total</b></td>
                <td class="bg-gray"></td>
                <td class="bg-gray"></td>
                <td class="text-center"><b><?php echo number_format(round($total_nilai_akhir), 0); ?></b></td>
            </tr>
            <tr>
                <td></td>
                <td class="text-right" style="text-align:right; padding-right:10px;"><b>Predikat</b></td>
                <td class="bg-gray"></td>
                <td class="bg-gray"></td>
                <td class="text-center"><b><?php echo $predikat_l1; ?></b></td>
            </tr>
        </table>

        <!-- Tabel Konversi Angket ke Skala 100 -->
        <table class="konversi-table" style="width:100%; margin-bottom:10px;">
            <tr>
                <td width="22%" rowspan="<?php echo count($kategori_list) + 1; ?>" class="text-center" style="vertical-align:top; padding-top:8px;">
                    Tabel<br>Konversi
                </td>
                <td width="58%"><b>Angket</b></td>
                <td width="20%"></td>
            </tr>
            <?php
            $idx = 1;
            foreach ($kategori_list as $kat) {
                $val = isset($konversi[$kat]) ? $konversi[$kat] : 0;
                echo "<tr>";
                echo "<td>{$no_angket}.{$idx}. Kompetensi {$kat}</td>";
                echo "<td class='text-center'>".number_format(round($val), 0)."</td>";
                echo "</tr>";
                $idx++;
            }
            ?>
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
                <td class="text-center" rowspan="<?php echo $tambahan_count + 1; ?>">1</td>
                <td colspan="3">Tugas Tambahan</td>
            </tr>
            <?php
            $idx = count($kategori_list) + 1;
            foreach ($tambahan_results as $res) {
                echo "<tr>";
                echo "<td>{$no_angket}.{$idx}. Kompetensi {$res['kategori']}</td>";
                echo "<td class='text-center'>" . number_format(round($res['score']), 0) . "</td>";
                echo "<td class='text-center'>" . number_format(round($res['konversi']), 0) . "</td>";
                echo "</tr>";
                $idx++;
            }
            
            $tambahan_avg = $tambahan_count > 0 ? ($tambahan_sum / $tambahan_count) : 0;
            $tambahan_avg_konv = round(($tambahan_avg / 5) * 100);
            
            $pred_tambahan_raw = getPredikatSkala5($tambahan_avg);
            $pred_tambahan_konv = getPredikatSkala5($tambahan_avg);
            ?>
            <tr>
                <td></td>
                <td class="text-right" style="padding-right:10px;"><b>Rata-rata</b></td>
                <td class="text-center"><b><?php echo number_format(round($tambahan_avg), 0); ?></b></td>
                <td class="text-center"><b><?php echo number_format(round($tambahan_avg_konv), 0); ?></b></td>
            </tr>
            <tr>
                <td></td>
                <td class="text-right" style="padding-right:10px;"><b>Predikat</b></td>
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