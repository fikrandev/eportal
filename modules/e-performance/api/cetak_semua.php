<?php
/**
 * E-Performance - Cetak Seluruh Laporan
 */

require_once __DIR__ . '/config_perf.php';
perf_auth_check();

$db = db();
$periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
if (!$periode_id) die("Periode ID diperlukan.");

$stmtPtk = $db->prepare("SELECT id FROM perf_ptk WHERE status = 1 ORDER BY jenis_ptk ASC, nama ASC");
$stmtPtk->execute();
$ptks = $stmtPtk->fetchAll(PDO::FETCH_ASSOC);

function get_lampiran_html($ptk_id, $periode_id, $is_first) {
    $_GET['ptk_id'] = $ptk_id;
    $_GET['periode_id'] = $periode_id;
    ob_start();
    include __DIR__ . '/cetak_lampiran.php';
    $out = ob_get_clean();
    
    if ($is_first) {
        // Return everything except closing body/html
        return preg_replace('/<\/body>\s*<\/html>\s*$/i', '', $out);
    } else {
        // Extract only body contents and add a page break before it
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $out, $matches)) {
            // hapus print button div jika ada
            $body = preg_replace('/<div class="print-actions">.*?<\/div>/is', '', $matches[1]);
            return '<div style="page-break-before: always;"></div>' . $body;
        }
        return $out;
    }
}

$first = true;
foreach ($ptks as $ptk) {
    echo get_lampiran_html($ptk['id'], $periode_id, $first);
    $first = false;
}

// Kemudian tambahkan rekap keseluruhan di akhir
$_GET['periode_id'] = $periode_id;
if (!defined('REKAP_INCLUDED')) define('REKAP_INCLUDED', true);

ob_start();
include __DIR__ . '/cetak_rekap_keseluruhan.php';
$rekap_out = ob_get_clean();

// Bersihkan tombol print dari rekap jika ada
$rekap_out = preg_replace('/<div class="print-actions">.*?<\/div>/is', '', $rekap_out);

echo '<div style="page-break-before: always;"></div>';
echo $rekap_out;

echo '</body></html>';
