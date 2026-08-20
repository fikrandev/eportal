<?php
// Extracted from cetak_lampiran.php to render Diri Sendiri and Siswa

// ---------------------------------------------------------
// REKAP DIRI SENDIRI
// ---------------------------------------------------------
$show_diri = isset($_GET['diri']) ? $_GET['diri'] == 1 : false;

if ($show_diri) {
    $stmtUser = $db->prepare("SELECT id, username FROM perf_users WHERE perf_ptk_id = ? LIMIT 1");
    $stmtUser->execute([$ptk_id]);
    $perfUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $penilai_id = $perfUser ? $perfUser['id'] : 0;

    $stmtTupoksi = $db->prepare("SELECT tupoksi FROM users WHERE username = ? AND status = 1 LIMIT 1");
    $stmtTupoksi->execute([$perfUser['username'] ?? '']);
    $tupoksiRow = $stmtTupoksi->fetch(PDO::FETCH_ASSOC);

    $actualTupoksi = ($tupoksiRow && !empty($tupoksiRow['tupoksi'])) ? $tupoksiRow['tupoksi'] : 'Guru';
    $rater_str = $actualTupoksi;
    $rater_str_self = 'Diri Sendiri';
    $rater_fallback = (stripos($actualTupoksi, 'Guru') !== false || stripos($actualTupoksi, 'Wali Kelas') !== false) ? 'Guru' : $actualTupoksi;

    $stmtQ = $db->prepare("
        SELECT id as instrumen_id, kategori, pertanyaan
        FROM perf_instrumen
        WHERE periode_id = ? AND (
            (
                (FIND_IN_SET(?, target_jabatan) > 0 OR FIND_IN_SET(?, target_jabatan) > 0)
                AND (target_dinilai = 'Semua' OR FIND_IN_SET(?, target_dinilai) > 0)
            )
            OR
            (
                FIND_IN_SET(?, target_jabatan) > 0 
                AND (target_dinilai = 'Semua' OR FIND_IN_SET(?, target_dinilai) > 0 OR FIND_IN_SET(?, target_dinilai) > 0)
            )
            OR
            (FIND_IN_SET(?, target_jabatan) > 0 AND FIND_IN_SET(?, target_dinilai) > 0)
        )
        ORDER BY kategori ASC, urutan ASC, id ASC
    ");
    $stmtQ->execute([
        $periode_id, 
        $rater_str, $rater_fallback, $ptk['jenis_ptk'],
        $rater_str_self, $ptk['jenis_ptk'], $rater_str_self,
        $ptk['jenis_ptk'], $rater_str_self
    ]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    $stmtA = $db->prepare("SELECT instrumen_id, nilai FROM perf_penilaian WHERE periode_id = ? AND dinilai_ptk_id = ? AND penilai_id = ?");
    $stmtA->execute([$periode_id, $ptk_id, $penilai_id]);
    $answers = [];
    while($row = $stmtA->fetch(PDO::FETCH_ASSOC)) {
        $answers[$row['instrumen_id']] = $row['nilai'];
    }

    $grouped_diri = [];
    foreach ($questions as $q) {
        $kat = $q['kategori'] ? trim($q['kategori']) : 'Umum';
        if (!isset($grouped_diri[$kat])) {
            $grouped_diri[$kat] = ['kategori' => $kat, 'pertanyaan' => [], 'total_skor' => 0, 'count' => 0];
        }
        $skor = isset($answers[$q['instrumen_id']]) ? (int)$answers[$q['instrumen_id']] : 0;
        $grouped_diri[$kat]['pertanyaan'][] = ['teks' => $q['pertanyaan'], 'skor' => $skor];
        $grouped_diri[$kat]['total_skor'] += $skor;
        $grouped_diri[$kat]['count'] += 1;
    }

    if (!empty($grouped_diri)) {
        echo '<div style="page-break-before: always;"></div>';
        if (!empty($kop_surat)) {
            echo '<div class="kop-surat"><img src="../../../' . htmlspecialchars($kop_surat) . '" alt="Kop Surat"></div>';
        }
        echo '<div class="title" style="margin-bottom: 20px;">REKAPITULASI PENILAIAN DIRI SENDIRI<br>' . htmlspecialchars($ptk['nama_lengkap']) . '</div>';
        echo '<table class="main-table">';
        echo '<thead><tr><th width="5%">No</th><th width="20%">Kompetensi</th><th width="50%">Pertanyaan</th><th width="5%">SKOR</th><th width="10%">RT2</th><th width="10%">KONVERSI</th></tr></thead>';
        echo '<tbody>';
        $no = 1;
        foreach ($grouped_diri as $kat => $data) {
            $rt2 = $data['count'] > 0 ? round($data['total_skor'] / $data['count'], 2) : 0;
            $konversi = round(($rt2 / 4) * 100);
            
            $isFirst = true;
            $rowSpan = count($data['pertanyaan']);
            
            foreach ($data['pertanyaan'] as $p) {
                echo '<tr style="page-break-inside: avoid;">';
                echo '<td class="text-center">' . $no++ . '</td>';
                if ($isFirst) {
                    echo '<td rowspan="' . $rowSpan . '" style="vertical-align:top;">' . htmlspecialchars($kat) . '</td>';
                }
                echo '<td>' . htmlspecialchars($p['teks']) . '</td>';
                echo '<td class="text-center">' . $p['skor'] . '</td>';
                if ($isFirst) {
                    echo '<td class="text-center" rowspan="' . $rowSpan . '" style="vertical-align:top;">' . $rt2 . '</td>';
                    echo '<td class="text-center" rowspan="' . $rowSpan . '" style="vertical-align:top;">' . $konversi . '</td>';
                }
                echo '</tr>';
                $isFirst = false;
            }
        }
        echo '</tbody></table>';
    }
}

// ---------------------------------------------------------
// REKAP SISWA
// ---------------------------------------------------------
$show_siswa = isset($_GET['siswa']) ? $_GET['siswa'] == 1 : false;

if ($show_siswa) {
    $stmtQS = $db->prepare("SELECT id as instrumen_id, kategori, pertanyaan FROM perf_instrumen WHERE periode_id = ? AND target_jabatan LIKE '%Siswa%' AND (target_dinilai = 'Semua' OR FIND_IN_SET(?, target_dinilai) > 0) ORDER BY kategori ASC, urutan ASC, id ASC");
    $stmtQS->execute([$periode_id, $ptk['jenis_ptk']]);
    $questions_siswa = $stmtQS->fetchAll(PDO::FETCH_ASSOC);

    $q_ids = array_column($questions_siswa, 'instrumen_id');
    if (!empty($q_ids)) {
        $in_q = implode(',', array_fill(0, count($q_ids), '?'));
        $params = array_merge([$periode_id, $ptk_id], $q_ids);
        $stmtPenilai = $db->prepare("SELECT DISTINCT penilai_id FROM perf_penilaian WHERE periode_id = ? AND dinilai_ptk_id = ? AND instrumen_id IN ($in_q) ORDER BY penilai_id ASC");
        $stmtPenilai->execute($params);
        $penilai_list_siswa = $stmtPenilai->fetchAll(PDO::FETCH_COLUMN);

        $murid_map = [];
        $i = 1;
        foreach ($penilai_list_siswa as $pid) {
            $murid_map[$pid] = "MURID $i";
            $i++;
        }

        $stmtA = $db->prepare("SELECT instrumen_id, penilai_id, nilai FROM perf_penilaian WHERE periode_id = ? AND dinilai_ptk_id = ? AND instrumen_id IN ($in_q)");
        $stmtA->execute($params);
        $answers_siswa = [];
        while($row = $stmtA->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($answers_siswa[$row['instrumen_id']])) $answers_siswa[$row['instrumen_id']] = [];
            $answers_siswa[$row['instrumen_id']][$row['penilai_id']] = $row['nilai'];
        }

        $grouped_siswa = [];
        foreach ($questions_siswa as $q) {
            $kat = $q['kategori'] ? trim($q['kategori']) : 'Umum';
            if (!isset($grouped_siswa[$kat])) {
                $grouped_siswa[$kat] = ['kategori' => $kat, 'pertanyaan' => []];
            }
            
            $skor_murid = [];
            foreach ($penilai_list_siswa as $pid) {
                $skor_murid[$murid_map[$pid]] = isset($answers_siswa[$q['instrumen_id']][$pid]) ? (int)$answers_siswa[$q['instrumen_id']][$pid] : 0;
            }

            $grouped_siswa[$kat]['pertanyaan'][] = [
                 'teks' => $q['pertanyaan'],
                 'skor_murid' => $skor_murid
            ];
        }

        if (!empty($penilai_list_siswa) && !empty($grouped_siswa)) {
            echo '<div style="page-break-before: always;"></div>';
            if (!empty($kop_surat)) {
                echo '<div class="kop-surat"><img src="../../../' . htmlspecialchars($kop_surat) . '" alt="Kop Surat"></div>';
            }
            echo '<div class="title" style="margin-bottom: 20px;">REKAPITULASI PENILAIAN OLEH SISWA<br>' . htmlspecialchars($ptk['nama_lengkap']) . '</div>';
            echo '<table class="main-table" style="font-size:10px;">';
            
            // Theader
            echo '<thead><tr><th width="3%">No</th><th width="15%">Kompetensi</th><th>Pertanyaan</th>';
            foreach ($murid_map as $pid => $lbl) {
                echo '<th width="3%">' . $lbl . '</th>';
            }
            echo '</tr></thead>';
            
            // Tbody
            echo '<tbody>';
            $no = 1;
            foreach ($grouped_siswa as $kat => $data) {
                $isFirst = true;
                $rowSpan = count($data['pertanyaan']);
                
                foreach ($data['pertanyaan'] as $p) {
                    echo '<tr style="page-break-inside: avoid;">';
                    echo '<td class="text-center">' . $no++ . '</td>';
                    if ($isFirst) {
                        echo '<td rowspan="' . $rowSpan . '" style="vertical-align:top;">' . htmlspecialchars($kat) . '</td>';
                    }
                    echo '<td>' . htmlspecialchars($p['teks']) . '</td>';
                    
                    foreach ($murid_map as $pid => $lbl) {
                        echo '<td class="text-center">' . $p['skor_murid'][$lbl] . '</td>';
                    }
                    
                    echo '</tr>';
                    $isFirst = false;
                }
            }
            echo '</tbody></table>';
        }
    }
}
?>
