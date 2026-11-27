<?php
/**
 * E-Performance — Cetak Rekap Keseluruhan
 * Render Rekapitulasi Penilaian Kinerja Sesuai Guideline 2026 (Halaman 49)
 */

require_once __DIR__ . '/config_perf.php';
require_once __DIR__ . '/scoring_helper.php';

$auth = perf_auth_check();
if (!$auth || (!in_array($auth['role'], ['admin', 'superadmin'], true) && !in_array('hasil', $auth['permissions'] ?? [], true))) {
    die("Akses ditolak.");
}

$db = db();
$periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;

if (!$periode_id) {
    die("Periode ID diperlukan.");
}

// 1. Get Periode
$stmtPer = $db->prepare("SELECT * FROM perf_periode WHERE id = ?");
$stmtPer->execute([$periode_id]);
$periode = $stmtPer->fetch(PDO::FETCH_ASSOC);
if (!$periode) die("Periode tidak ditemukan.");

// 2. Get School Info & Settings
$school_name = get_setting('nama_sekolah', 'Sekolah');
$kop_surat = get_setting('kop_surat', '');

$setting_kepsek = get_setting('kepala_sekolah', '');
if ($setting_kepsek) {
    $nama_kepsek = $setting_kepsek;
} else {
    $stmtKS = $db->query("SELECT nama_lengkap FROM users WHERE tupoksi = 'Kepala Sekolah' LIMIT 1");
    $kepsek = $stmtKS->fetch(PDO::FETCH_ASSOC);
    $nama_kepsek = $kepsek ? $kepsek['nama_lengkap'] : "RIYATI, S.Psi.";
}

// 3. Get Manual Instruments
$stmtManualInst = $db->prepare("SELECT id, kategori, bobot, skor_ya, target_dinilai, urutan FROM perf_instrumen WHERE periode_id = ? AND is_manual = 1 ORDER BY urutan ASC, id ASC");
$stmtManualInst->execute([$periode_id]);
$allManualInst = $stmtManualInst->fetchAll(PDO::FETCH_ASSOC);

// Penilai list & bobot
$penilai_list = [
    'kepsek'  => ['label' => 'Kepala Sekolah', 'bobot' => 0.40],
    'sendiri' => ['label' => 'Diri Sendiri', 'bobot' => 0.10],
    'teman'   => ['label' => 'Teman Sejawat', 'bobot' => 0.20],
    'siswa'   => ['label' => 'Murid', 'bobot' => 0.30]
];

// 4. Get All Active PTKs
$stmtPtk = $db->prepare("
    SELECT pt.id, pt.nama, pt.niy, pt.jabatan, pt.jenis_ptk, 
           COALESCE(u.tupoksi, pt.jenis_ptk) as tupoksi
    FROM perf_ptk pt
    LEFT JOIN users u ON pt.niy COLLATE utf8mb4_unicode_ci = u.username COLLATE utf8mb4_unicode_ci
    WHERE pt.status = 1 
    ORDER BY pt.jenis_ptk ASC, pt.nama ASC
");
$stmtPtk->execute();
$all_ptks = $stmtPtk->fetchAll(PDO::FETCH_ASSOC);

$results = [];

foreach ($all_ptks as $ptk) {
    $ptk_id = $ptk['id'];
    $roleFlags = getPtkRoleFlags($ptk);
    $ptk_jenis = $roleFlags['ptk_jenis'];
    $is_staff = $roleFlags['is_staff'];
    $is_waka = $roleFlags['is_waka'];
    $allowed_tambahan = $roleFlags['allowed_tambahan'];

    // A. Manual Scores
    $stmtManual = $db->prepare("SELECT data FROM perf_penilaian_manual WHERE periode_id = ? AND ptk_id = ? LIMIT 1");
    $stmtManual->execute([$periode_id, $ptk_id]);
    $manualRow = $stmtManual->fetch(PDO::FETCH_ASSOC);
    $manualData = ($manualRow && $manualRow['data']) ? json_decode($manualRow['data'], true) : [];

    $grouped_manual = calculateManualGroups($allManualInst, $manualData, $ptk_jenis, $is_staff);
    $has_perangkat = isset($grouped_manual['Perangkat Mengajar']);

    $val_perangkat = '';
    $val_kehadiran = 0;
    $val_rapat = 0;
    $val_partisipasi = 0;

    foreach ($grouped_manual as $kat => $group) {
        if ($kat === 'Perangkat Mengajar') {
            $val_perangkat = $group['group_nilai'];
        } elseif ($kat === 'Kehadiran') {
            $val_kehadiran = $group['group_nilai'];
        } elseif ($kat === 'Keikutsertaan dalam rapat/kedinasan') {
            $val_rapat = $group['group_nilai'];
        } elseif ($kat === 'Partisipasi kegiatan sekolah') {
            $val_partisipasi = $group['group_nilai'];
        }
    }

    // B. Angket Scores
    $stmtNilai = $db->prepare("
        SELECT 
            p.penilai_type, 
            p.penilai_id,
            p.dinilai_ptk_id,
            u.perf_ptk_id as rater_ptk_id,
            u.role as rater_role,
            i.target_jabatan,
            TRIM(REPLACE(i.kategori, '\xc2\xa0', '')) as kategori,
            p.nilai
        FROM perf_penilaian p
        JOIN perf_instrumen i ON p.instrumen_id = i.id
        LEFT JOIN perf_users u ON p.penilai_id = u.id
        WHERE p.periode_id = ? AND p.dinilai_ptk_id = ? AND i.is_manual = 0
    ");
    $stmtNilai->execute([$periode_id, $ptk_id]);
    $rawScores = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

    $angketCalc = calculateAngketScores($rawScores, $is_staff, $is_waka, $allowed_tambahan, $penilai_list);
    $angket_nilai_weighted = $angketCalc['rounded_nilai_angket'];

    // Susun Nilai Kolom 1 s.d 5
    $col_scores = [
        $has_perangkat ? $val_perangkat : '',
        $val_kehadiran,
        $val_rapat,
        $val_partisipasi,
        $angket_nilai_weighted
    ];

    $total_score_ptk = ($has_perangkat ? (float)$val_perangkat : 0) + $val_kehadiran + $val_rapat + $val_partisipasi + $angket_nilai_weighted;
    $total_score_ptk = round($total_score_ptk, 2);

    // Predikat
    $predikat_full = getPredikatSkala100($total_score_ptk);
    $pred_initial = "K";
    $p_check = strtolower($predikat_full);
    if (strpos($p_check, 'sangat baik') !== false || strpos($p_check, 'istimewa') !== false) $pred_initial = "SB";
    elseif (strpos($p_check, 'baik') !== false) $pred_initial = "B";
    elseif (strpos($p_check, 'cukup') !== false) $pred_initial = "C";
    elseif (strpos($p_check, 'sangat kurang') !== false) $pred_initial = "SK";

    $deskripsi = getDeskripsiRaporUtama($db, $ptk_jenis, $total_score_ptk);

    $results[] = [
        'nama' => $ptk['nama'],
        'jabatan' => $ptk['jabatan'] ? $ptk['jabatan'] : $ptk['jenis_ptk'],
        'jenis_ptk' => $ptk_jenis,
        'scores' => $col_scores,
        'tot' => $total_score_ptk,
        'predikat' => $pred_initial,
        'deskripsi' => $deskripsi
    ];
}

// Set tanggal tanda tangan
$tgl_ttd_str = (new DateTime($periode['tgl_selesai'] ?? 'now'))->format('d F Y');
$bulan_en = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$bulan_id = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tgl_ttd = str_replace($bulan_en, $bulan_id, $tgl_ttd_str);

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
            font-size: 10pt;
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
            margin-bottom: 10px;
        }
        .kop-surat img {
            max-width: 100%;
            height: auto;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 9.5pt;
        }
        .main-table th, .main-table td {
            border: 1px solid #000;
            padding: 3px 5px;
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
            margin-top: 20px;
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
        }
    </style>
<?php if (!$is_included_rekap): ?>
</head>
<body>
    <div class="no-print" style="text-align:center; padding:15px; background:#f3f4f6; margin-bottom:20px; border-bottom:1px solid #ddd;">
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
        <?php if (!empty($kop_surat)): 
            $clean_kop = ltrim(str_replace('\\', '/', $kop_surat), '/');
            $kop_img_src = (preg_match('/^https?:\/\//i', $kop_surat)) ? $kop_surat : (defined('BASE_URL') ? BASE_URL . $clean_kop : '../../../' . $clean_kop);
        ?>
            <div class="kop-surat">
                <img src="<?php echo htmlspecialchars($kop_img_src); ?>" alt="Kop Surat" onerror="this.style.display='none'">
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
                    <th rowspan="2" width="4%">No</th>
                    <th rowspan="2" width="22%">Nama</th>
                    <th rowspan="2" width="16%">Jabatan</th>
                    <th colspan="5">Penilaian</th>
                    <th rowspan="2" width="8%">TOT</th>
                    <th rowspan="2" width="8%">Predikat</th>
                    <th rowspan="2" width="28%">Deskripsi</th>
                </tr>
                <tr>
                    <th width="6%">1</th>
                    <th width="6%">2</th>
                    <th width="6%">3</th>
                    <th width="6%">4</th>
                    <th width="6%">5</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($results as $r): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($r['nama']); ?></td>
                    <td><?php echo htmlspecialchars($r['jabatan']); ?></td>
                    <?php 
                    // Tampilkan nilai 1 s.d 5
                    for ($ci = 0; $ci < 5; $ci++): 
                        $val_col = isset($r['scores'][$ci]) ? $r['scores'][$ci] : '';
                    ?>
                    <td class="text-center"><?php 
                        if ($val_col === '') echo '';
                        else echo number_format(round((float)$val_col), 0);
                    ?></td>
                    <?php endfor; ?>
                    <td class="text-center"><b><?php echo number_format(round((float)$r['tot']), 0); ?></b></td>
                    <td class="text-center"><b><?php echo $r['predikat']; ?></b></td>
                    <td style="font-size: 8.5pt; text-align: justify;"><?php echo htmlspecialchars($r['deskripsi']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

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