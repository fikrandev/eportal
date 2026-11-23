<?php
/**
 * Jadwal API for E-Curriculum & E-Schedule
 * Engine Pembuat Jadwal Pelajaran (Scheduler), Lock System & Distribusi Harian
 */
require_once __DIR__ . '/../../../api/config.php';

$user = (PHP_SAPI === 'cli') ? ['id' => 1, 'role' => 'admin'] : auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : (PHP_SAPI === 'cli' ? 'cli_noop' : 'list');

/**
 * Bangun konteks data penjadwalan dari DB beserta pre-validasi dan diagnosa kapasitas run.
 */
function sch_build_problem_context(PDO $pdo): array {
    $allJamRows = $pdo->query("
        SELECT * FROM sch_jam_belajar
        ORDER BY CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END, jam_ke ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $jams = [];
    $slotHari = [];
    $slotHariName = [];
    $slotJamKe = [];
    $slotJamId = [];
    $jamIdToSlot = [];
    $slotRun = [];
    $slotPrev = [];
    $slotNext = [];
    $hariIdxMap = [];
    $hariList = [];
    $runs = [];

    $runId = -1;
    $prevHari = null;
    $prevJamKe = null;
    $hadBreak = true;

    foreach ($allJamRows as $j) {
        if (($j['tipe'] ?? '') !== 'Pembelajaran') {
            $hadBreak = true;
            continue;
        }
        $i = count($jams);
        $jams[] = $j;
        $h = $j['hari'];
        $jk = (int)$j['jam_ke'];
        $jId = (int)$j['id'];

        if (!isset($hariIdxMap[$h])) {
            $hariIdxMap[$h] = count($hariList);
            $hariList[] = $h;
        }
        $hIdx = $hariIdxMap[$h];
        $slotHari[$i] = $hIdx;
        $slotHariName[$i] = $h;
        $slotJamKe[$i] = $jk;
        $slotJamId[$i] = $jId;
        $jamIdToSlot[$jId] = $i;

        $isContiguous = (!$hadBreak && $prevHari === $h && $prevJamKe !== null && $jk === $prevJamKe + 1);
        if (!$isContiguous) {
            $runId++;
            $slotPrev[$i] = -1;
            $runs[$runId] = ['hari' => $h, 'slots' => [$i], 'jam_ke' => [$jk]];
        } else {
            $slotPrev[$i] = $i - 1;
            $slotNext[$i - 1] = $i;
            $runs[$runId]['slots'][] = $i;
            $runs[$runId]['jam_ke'][] = $jk;
        }
        $slotRun[$i] = $runId;
        $slotNext[$i] = -1;
        $hadBreak = false;
        $prevHari = $h;
        $prevJamKe = $jk;
    }

    $nSlots = count($jams);
    $allPairs = [];
    $allTriples = [];
    for ($s = 0; $s < $nSlots; $s++) {
        if ($slotNext[$s] !== -1) {
            $allPairs[] = [$s, $s + 1];
            if ($slotNext[$s + 1] !== -1) {
                $allTriples[] = [$s, $s + 1, $s + 2];
            }
        }
    }

    $kelasRows = $pdo->query("SELECT id, nama_kelas, rombel FROM sch_kelas ORDER BY rombel, nama_kelas, id")->fetchAll(PDO::FETCH_ASSOC);
    $distRows = $pdo->query("
        SELECT d.id, d.guru_id, d.kelas_id, d.mapel_id, d.jp,
               m.kode_mapel, m.nama_mapel, g.kode_guru, g.nama_guru, k.nama_kelas
        FROM sch_distribusi d
        JOIN sch_mapel m ON d.mapel_id = m.id
        JOIN sch_guru g ON d.guru_id = g.id
        JOIN sch_kelas k ON d.kelas_id = k.id
        WHERE d.jp > 0
        ORDER BY d.kelas_id ASC, d.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $kesRows = $pdo->query("SELECT guru_id, jam_belajar_id FROM sch_kesediaan")->fetchAll(PDO::FETCH_ASSOC);

    $rawGuruKes = [];
    foreach ($kesRows as $kr) {
        $jId = (int)$kr['jam_belajar_id'];
        if (isset($jamIdToSlot[$jId])) {
            $rawGuruKes[(int)$kr['guru_id']][$jamIdToSlot[$jId]] = true;
        }
    }

    $distMeta = [
        0 => [
            'id' => 0, 'guru_id' => 0, 'kelas_id' => 0, 'mapel_id' => 0,
            'jp' => 0, 'is_pjok' => false, 'kode' => '', 'nama_mapel' => '',
            'nama_guru' => '', 'nama_kelas' => ''
        ]
    ];
    $kelasDists = [];
    $kelasMeta = [];
    $kelasJpSum = [];
    foreach ($kelasRows as $kr) {
        $kId = (int)$kr['id'];
        $kelasDists[$kId] = [];
        $kelasMeta[$kId] = $kr['nama_kelas'];
        $kelasJpSum[$kId] = 0;
    }

    $guruTotalJp = [];
    $guruNames = [];
    $guruJps = [];
    $totalRequiredJp = 0;

    foreach ($distRows as $dr) {
        $dId = (int)$dr['id'];
        $gId = (int)$dr['guru_id'];
        $kId = (int)$dr['kelas_id'];
        $jp = (int)$dr['jp'];
        if (!isset($kelasDists[$kId])) continue;

        $kode = (string)($dr['kode_mapel'] ?? '');
        $namaMapel = (string)($dr['nama_mapel'] ?? '');
        $isPjok = (
            stripos($kode, 'PJOK') !== false ||
            stripos($namaMapel, 'PJOK') !== false ||
            stripos($namaMapel, 'Penjas') !== false ||
            stripos($namaMapel, 'Olahraga') !== false
        );
        $isTik = (
            stripos($kode, 'TIK') !== false ||
            stripos($namaMapel, 'TIK') !== false ||
            stripos($kode, 'INF') !== false ||
            stripos($namaMapel, 'Informatika') !== false ||
            stripos($namaMapel, 'Komputer') !== false
        );

        $distMeta[$dId] = [
            'id' => $dId,
            'guru_id' => $gId,
            'kelas_id' => $kId,
            'mapel_id' => (int)$dr['mapel_id'],
            'jp' => $jp,
            'is_pjok' => $isPjok,
            'is_tik' => $isTik,
            'kode' => $kode,
            'nama_mapel' => $namaMapel,
            'nama_guru' => (string)$dr['nama_guru'],
            'nama_kelas' => (string)$dr['nama_kelas']
        ];
        $kelasDists[$kId][] = $dId;
        $kelasJpSum[$kId] += $jp;
        $guruTotalJp[$gId] = ($guruTotalJp[$gId] ?? 0) + $jp;
        $guruNames[$gId] = (string)$dr['nama_guru'];
        $guruJps[$gId][$jp] = true;
        $totalRequiredJp += $jp;
    }

    $guruKes = [0 => ($nSlots > 0 ? array_fill(0, $nSlots, true) : [])];
    $diagnostics = [
        'teachers_without_kesediaan' => [],
        'run_bottlenecks' => []
    ];
    $errors = [];

    if ($nSlots === 0) {
        $errors[] = 'Data struktur jam pembelajaran (sch_jam_belajar) masih kosong.';
    }
    if (empty($kelasRows)) {
        $errors[] = 'Data rombel/kelas (sch_kelas) masih kosong.';
    }
    if (empty($distRows)) {
        $errors[] = 'Data distribusi mengajar (sch_distribusi) masih kosong.';
    }

    foreach ($guruTotalJp as $gId => $totJp) {
        if (!empty($rawGuruKes[$gId])) {
            $guruKes[$gId] = $rawGuruKes[$gId];
            $availCount = count($guruKes[$gId]);
            if ($totJp > $availCount) {
                $errors[] = "Guru {$guruNames[$gId]} memiliki beban {$totJp} JP tetapi hanya memilih {$availCount} slot kesediaan mengajar.";
            }
        } else {
            // Guru di distribusi yang belum mengisi kesediaan dianggap bersedia pada seluruh slot pembelajaran
            $guruKes[$gId] = ($nSlots > 0 ? array_fill(0, $nSlots, true) : []);
            $diagnostics['teachers_without_kesediaan'][] = $guruNames[$gId];
        }
    }

    foreach ($kelasJpSum as $kId => $jpSum) {
        if ($nSlots > 0 && $jpSum > $nSlots) {
            $errors[] = "Total JP kelas {$kelasMeta[$kId]} ({$jpSum} JP) melebihi kapasitas slot pembelajaran ({$nSlots} slot).";
        }
    }

    // Diagnosa kapasitas run & slot untuk mendeteksi bottleneck kesediaan sejak awal
    $nKelas = count($kelasRows);
    if ($nSlots > 0 && $nKelas > 0 && empty($errors)) {
        foreach ($runs as $run) {
            $len = count($run['slots']);
            $req = $nKelas * $len;
            $minSlotTeachers = PHP_INT_MAX;
            $strict2Cap = 0;

            foreach ($run['slots'] as $s) {
                $cnt = 0;
                foreach ($guruTotalJp as $gId => $_) {
                    if (isset($guruKes[$gId][$s])) $cnt++;
                }
                if ($cnt < $minSlotTeachers) $minSlotTeachers = $cnt;
            }
            if ($minSlotTeachers < $nKelas) {
                $jkRange = count($run['jam_ke']) > 1
                    ? $run['jam_ke'][0] . '-' . end($run['jam_ke'])
                    : (string)$run['jam_ke'][0];
                $errors[] = "Slot {$run['hari']} jam_ke {$jkRange} hanya memiliki {$minSlotTeachers} guru aktif yang bersedia untuk {$nKelas} kelas.";
                continue;
            }

            foreach ($guruTotalJp as $gId => $_) {
                $jpSet = $guruJps[$gId] ?? [];
                $only2 = (count($jpSet) === 1 && isset($jpSet[2]));
                $presentCount = 0;
                $subLen = 0;
                $usable = 0;
                foreach ($run['slots'] as $s) {
                    if (isset($guruKes[$gId][$s])) {
                        $presentCount++;
                        $subLen++;
                    } else {
                        if ($only2) $usable += intdiv($subLen, 2) * 2;
                        $subLen = 0;
                    }
                }
                if ($only2) {
                    $usable += intdiv($subLen, 2) * 2;
                    $strict2Cap += $usable;
                } else {
                    $strict2Cap += $presentCount;
                }
            }

            if ($strict2Cap < $req) {
                $jkRange = count($run['jam_ke']) > 1
                    ? $run['jam_ke'][0] . '-' . end($run['jam_ke'])
                    : (string)$run['jam_ke'][0];
                $minBroken = (int)ceil(($req - $strict2Cap) / 2);
                $diagnostics['run_bottlenecks'][] = "{$run['hari']} jam {$jkRange} (blok {$len} jam): kapasitas blok 2-JP utuh hanya {$strict2Cap}/{$req} slot kelas (minimal guru aktif {$minSlotTeachers}), sehingga minimal {$minBroken} distribusi tidak dapat terhindar dari pemecahan blok tanpa tambahan kesediaan guru.";
            }
        }
    }

    $guruKesList = [];
    $guruPairs = [];
    $guruTriples = [];
    foreach ($guruKes as $gid => $smap) {
        $guruKesList[$gid] = array_keys($smap);
        $guruPairs[$gid] = [];
        foreach ($allPairs as $p) {
            if (isset($smap[$p[0]]) && isset($smap[$p[1]])) $guruPairs[$gid][] = $p;
        }
        $guruTriples[$gid] = [];
        foreach ($allTriples as $t) {
            if (isset($smap[$t[0]]) && isset($smap[$t[1]]) && isset($smap[$t[2]])) $guruTriples[$gid][] = $t;
        }
    }

    return [
        'allJamRows' => $allJamRows,
        'jams' => $jams,
        'nSlots' => $nSlots,
        'slotHari' => $slotHari,
        'slotHariName' => $slotHariName,
        'slotJamKe' => $slotJamKe,
        'slotJamId' => $slotJamId,
        'jamIdToSlot' => $jamIdToSlot,
        'slotRun' => $slotRun,
        'slotPrev' => $slotPrev,
        'slotNext' => $slotNext,
        'allPairs' => $allPairs,
        'allTriples' => $allTriples,
        'runs' => $runs,
        'kelasRows' => $kelasRows,
        'kelasIds' => array_keys($kelasDists),
        'kelasMeta' => $kelasMeta,
        'kelasDists' => $kelasDists,
        'distRows' => $distRows,
        'distMeta' => $distMeta,
        'guruKes' => $guruKes,
        'guruKesList' => $guruKesList,
        'guruPairs' => $guruPairs,
        'guruTriples' => $guruTriples,
        'totalRequiredJp' => $totalRequiredJp,
        'errors' => $errors,
        'diagnostics' => $diagnostics
    ];
}

/**
 * Dapatkan partisi blok JP yang diizinkan sesuai aturan kurikulum:
 *   1 JP = [1]
 *   2 JP = [2] (tidak boleh 1+1)
 *   3 JP = [3] atau [2, 1] (tidak boleh 1+1+1)
 *   4 JP = [2, 2] atau [3, 1] (tidak boleh 1+1+1+1, tidak boleh 4 sekaligus)
 *   5 JP = [3, 2] atau [2, 2, 1] (tidak boleh 5 sekaligus)
 *   PJOK = [jp] (wajib 1 blok utuh kontinu dalam run yang sama, tidak boleh dipisah istirahat/hari)
 *   TIK = [3] atau [2, 1] (jika 3 JP)
 */
function sch_get_allowed_partitions(int $jp, bool $isPjok = false, bool $isTik = false): array {
    if ($isPjok) {
        return [[$jp]];
    }
    switch ($jp) {
        case 1:
            return [[1]];
        case 2:
            return [[2]];
        case 3:
            return [[3], [2, 1]];
        case 4:
            return [[2, 2], [3, 1]];
        case 5:
            return [[3, 2], [2, 2, 1]];
        default:
            if ($jp === 6) return [[3, 3], [2, 2, 2]];
            return [[3, 2, 2]];
    }
}

/**
 * Evaluasi pelanggaran aturan blok JP & pemecahan di hari yang sama untuk satu distribusi.
 * Return: [blkViol, pjokViol, sameDayViol]
 */
function sch_eval_dist_slots(int $d, array $sl, array &$distMeta, array &$slotRun, array &$slotHari): array {
    if ($d === 0 || empty($sl)) return [0, 0, 0];
    $m = $distMeta[$d];
    $jp = $m['jp'];
    if ($jp <= 1) return [0, 0, 0];

    sort($sl);
    $n = count($sl);
    $parts = [];
    $curLen = 1;
    $daySeen = [];
    $sd = 0;

    for ($i = 1; $i <= $n; $i++) {
        if ($i < $n && $sl[$i] === $sl[$i - 1] + 1 && $slotRun[$sl[$i]] === $slotRun[$sl[$i - 1]]) {
            $curLen++;
        } else {
            $parts[] = $curLen;
            $day = $slotHari[$sl[$i - 1]];
            if (isset($daySeen[$day])) {
                $sd++;
            } else {
                $daySeen[$day] = true;
            }
            $curLen = 1;
        }
    }

    rsort($parts);
    $nBlk = count($parts);

    // Rule: PJOK = tidak boleh dipisah (1 JP sebelum istirahat, 1 JP setelah istirahat, tidak boleh)
    // Harus 1 blok utuh dalam run yang sama
    if ($m['is_pjok']) {
        $pv = ($nBlk === 1) ? 0 : ($nBlk - 1);
        return [$pv * 3, $pv, $sd];
    }

    // Exact user rules:
    // 2 JP = 2, tidak boleh 1+1
    // 3 JP = 3 sekaligus, 2+1, tidak boleh 1+1+1
    // 4 JP = 2+2, 3+1, tidak boleh 1+1+1+1, tidak boleh 4 sekaligus
    // 5 JP = 2+2+1, 3+2, tidak boleh 5 sekaligus
    // TIK = wajib 2+1, atau 3 sekaligus
    $blkViol = 0;
    if ($jp === 2) {
        if ($parts !== [2]) $blkViol = 2; // 1+1 not allowed
    } elseif ($jp === 3) {
        if ($parts === [1, 1, 1]) $blkViol = 3; // 1+1+1 not allowed
        elseif ($parts !== [3] && $parts !== [2, 1]) $blkViol = 2;
    } elseif ($jp === 4) {
        if ($parts === [4]) $blkViol = 3; // 4 sekaligus not allowed
        elseif ($parts === [1, 1, 1, 1]) $blkViol = 4; // 1+1+1+1 not allowed
        elseif ($parts !== [2, 2] && $parts !== [3, 1]) $blkViol = 2; // e.g. [2, 1, 1]
    } elseif ($jp === 5) {
        if ($parts === [5]) $blkViol = 4; // 5 sekaligus not allowed
        elseif ($parts === [4, 1]) $blkViol = 3; // 4 block not allowed
        elseif ($parts === [1, 1, 1, 1, 1]) $blkViol = 5;
        elseif ($parts !== [3, 2] && $parts !== [2, 2, 1]) $blkViol = 2; // e.g. [3, 1, 1]
    } else {
        foreach ($parts as $len) {
            if ($len > 3) $blkViol += ($len - 3) * 2;
        }
    }

    return [$blkViol, 0, $sd];
}

/**
 * Bentuk template pemecahan slice & target blok slot untuk satu kelas.
 */
function sch_generate_class_block_template(array &$ctx, int $kId): array {
    $runs = $ctx['runs'];
    $distMeta = $ctx['distMeta'];
    $dists = $ctx['kelasDists'][$kId];

    $j1 = [];
    $j2 = [];
    $j3 = [];
    $j4 = [];
    $j5 = [];
    $pjok = null;

    foreach ($dists as $d) {
        $m = $distMeta[$d];
        if ($m['is_pjok']) {
            $pjok = $d;
        } elseif ($m['jp'] === 1) {
            $j1[] = $d;
        } elseif ($m['jp'] === 2) {
            $j2[] = $d;
        } elseif ($m['jp'] === 3) {
            $j3[] = $d;
        } elseif ($m['jp'] === 4) {
            $j4[] = $d;
        } elseif ($m['jp'] === 5) {
            $j5[] = $d;
        }
    }

    $s3 = [];
    $s2 = [];
    $s1 = [];

    if ($pjok !== null) {
        $jp = $distMeta[$pjok]['jp'];
        if ($jp === 2) $s2[] = $pjok;
        elseif ($jp === 3) $s3[] = $pjok;
    }

    foreach ($j1 as $d) $s1[] = $d;
    foreach ($j2 as $d) $s2[] = $d;
    foreach ($j3 as $d) $s3[] = $d;
    foreach ($j4 as $d) {
        $s2[] = $d;
        $s2[] = $d;
    }
    foreach ($j5 as $d) {
        $s3[] = $d;
        $s2[] = $d;
    }

    // Adaptasi 3-JP slices ke [2, 1] jika slot blok 3 melebihi kapasitas run
    while (count($s3) > 4 + count($s1)) {
        $found = false;
        for ($i = count($s3) - 1; $i >= 0; $i--) {
            $d = $s3[$i];
            if ($distMeta[$d]['jp'] === 3 && !$distMeta[$d]['is_pjok']) {
                array_splice($s3, $i, 1);
                $s2[] = $d;
                $s1[] = $d;
                $found = true;
                break;
            }
        }
        if (!$found) break;
    }

    $nPair31 = count($s1);
    $nPure3 = count($s3) - $nPair31;

    $len4Runs = [];
    $len3Runs = [];
    $len2Runs = [];
    foreach ($runs as $rIdx => $r) {
        $l = count($r['slots']);
        if ($l === 4) $len4Runs[] = $rIdx;
        elseif ($l === 3) $len3Runs[] = $rIdx;
        elseif ($l === 2) $len2Runs[] = $rIdx;
    }

    $blocks2 = [];
    $blocks3 = [];
    $blocks1 = [];

    // Assign len 4 runs: [3, 1] atau [2, 2]
    for ($i = 0; $i < count($len4Runs); $i++) {
        $rIdx = $len4Runs[$i];
        $sl = $runs[$rIdx]['slots'];
        if ($i < $nPair31) {
            $blocks3[] = ['slots' => array_slice($sl, 0, 3), 'run' => $rIdx];
            $blocks1[] = ['slots' => array_slice($sl, 3, 1), 'run' => $rIdx];
        } else {
            $blocks2[] = ['slots' => array_slice($sl, 0, 2), 'run' => $rIdx];
            $blocks2[] = ['slots' => array_slice($sl, 2, 2), 'run' => $rIdx];
        }
    }

    // Assign len 3 runs: [3] atau [2, 1]
    for ($i = 0; $i < count($len3Runs); $i++) {
        $rIdx = $len3Runs[$i];
        $sl = $runs[$rIdx]['slots'];
        if ($i < $nPure3) {
            $blocks3[] = ['slots' => $sl, 'run' => $rIdx];
        } else {
            $blocks2[] = ['slots' => array_slice($sl, 0, 2), 'run' => $rIdx];
            $blocks1[] = ['slots' => array_slice($sl, 2, 1), 'run' => $rIdx];
        }
    }

    // Assign len 2 runs: [2]
    foreach ($len2Runs as $rIdx) {
        $blocks2[] = ['slots' => $runs[$rIdx]['slots'], 'run' => $rIdx];
    }

    return [
        's3' => $s3,
        's2' => $s2,
        's1' => $s1,
        'blocks3' => $blocks3,
        'blocks2' => $blocks2,
        'blocks1' => $blocks1
    ];
}

/**
 * Inisialisasi grid awal berbasis pencocokan blok JP kontinu (Block-Matching Bipartite).
 * Jika $existingGrid diberikan (untuk auto_fill_gaps), slot valid dipertahankan dan sisanya dicocokkan.
 */
function sch_init_grid(array &$ctx, ?array $existingGrid = null): array {
    $nSlots = $ctx['nSlots'];
    $distMeta = $ctx['distMeta'];
    $guruKes = $ctx['guruKes'];
    $guruKesList = $ctx['guruKesList'];
    $grid = [];
    $hasSoftKes = false;

    // Jika mode generate baru (bukan auto_fill_gaps), gunakan konstruksi template blok utuh
    if ($existingGrid === null) {
        foreach ($ctx['kelasIds'] as $kId) {
            $row = array_fill(0, $nSlots, 0);
            $t = sch_generate_class_block_template($ctx, $kId);

            foreach ([3, 2, 1] as $blen) {
                $slList = $t['s' . $blen];
                $bList = $t['blocks' . $blen];
                $nSl = count($slList);
                $nB = count($bList);

                $adj = [];
                for ($i = 0; $i < $nSl; $i++) {
                    $d = $slList[$i];
                    $g = $distMeta[$d]['guru_id'];
                    $adj[$i] = [];
                    for ($j = 0; $j < $nB; $j++) {
                        $ok = true;
                        foreach ($bList[$j]['slots'] as $s) {
                            if (!isset($guruKes[$g][$s])) { $ok = false; break; }
                        }
                        if ($ok) $adj[$i][] = $j;
                    }
                    shuffle($adj[$i]);
                }

                $bToSl = array_fill(0, $nB, -1);
                $unmatched = [];
                for ($i = 0; $i < $nSl; $i++) {
                    $visited = [];
                    $dfs = function ($u) use (&$dfs, &$adj, &$bToSl, &$visited, &$bList, &$slList): bool {
                        $uDist = $slList[$u];
                        foreach ($adj[$u] as $v) {
                            if (isset($visited[$v])) continue;
                            $visited[$v] = true;

                            // Cegah dua slice dari mapel yang sama (misal 4 JP atau 5 JP) berada di run yang sama
                            $vRun = $bList[$v]['run'];
                            $conflict = false;
                            for ($otherB = 0; $otherB < count($bList); $otherB++) {
                                if ($otherB !== $v && $bList[$otherB]['run'] === $vRun && $bToSl[$otherB] !== -1) {
                                    if ($slList[$bToSl[$otherB]] === $uDist) {
                                        $conflict = true;
                                        break;
                                    }
                                }
                            }
                            if ($conflict) continue;

                            if ($bToSl[$v] === -1 || $dfs($bToSl[$v])) {
                                $bToSl[$v] = $u;
                                return true;
                            }
                        }
                        return false;
                    };
                    if (!$dfs($i)) $unmatched[] = $i;
                }

                if (!empty($unmatched)) {
                    $hasSoftKes = true;
                    $freeB = [];
                    for ($j = 0; $j < $nB; $j++) if ($bToSl[$j] === -1) $freeB[] = $j;
                    shuffle($freeB);
                    foreach ($unmatched as $idx => $u) {
                        if (isset($freeB[$idx])) $bToSl[$freeB[$idx]] = $u;
                    }
                }

                for ($j = 0; $j < $nB; $j++) {
                    $u = $bToSl[$j];
                    if ($u !== -1 && isset($slList[$u])) {
                        $d = $slList[$u];
                        foreach ($bList[$j]['slots'] as $s) {
                            $row[$s] = $d;
                        }
                    }
                }
            }

            $grid[$kId] = $row;
        }

        return ['grid' => $grid, 'hasSoftKes' => $hasSoftKes];
    }

    // Fallback matching untuk auto_fill_gaps
    foreach ($ctx['kelasIds'] as $k) {
        $remCounts = [];
        foreach ($ctx['kelasDists'][$k] as $d) {
            $remCounts[$d] = $distMeta[$d]['jp'];
        }

        $row = array_fill(0, $nSlots, -1);
        $freeSlots = [];

        if (!empty($existingGrid[$k])) {
            for ($s = 0; $s < $nSlots; $s++) {
                $d = $existingGrid[$k][$s] ?? 0;
                if ($d > 0 && isset($remCounts[$d]) && $remCounts[$d] > 0) {
                    $g = $distMeta[$d]['guru_id'];
                    if (isset($guruKes[$g][$s])) {
                        $row[$s] = $d;
                        $remCounts[$d]--;
                        continue;
                    }
                }
                $freeSlots[] = $s;
            }
        } else {
            for ($s = 0; $s < $nSlots; $s++) {
                $freeSlots[] = $s;
            }
        }

        $tokens = [];
        foreach ($remCounts as $d => $cnt) {
            for ($i = 0; $i < $cnt; $i++) $tokens[] = $d;
        }
        $nFree = count($freeSlots);
        while (count($tokens) < $nFree) {
            $tokens[] = 0;
        }
        shuffle($tokens);

        $freeSlotSet = array_flip($freeSlots);
        $slotToTok = [];
        foreach ($freeSlots as $s) {
            $slotToTok[$s] = -1;
        }

        $tokAdj = [];
        foreach ($tokens as $ti => $d) {
            $g = $distMeta[$d]['guru_id'];
            $adj = [];
            foreach ($guruKesList[$g] ?? [] as $s) {
                if (isset($freeSlotSet[$s])) $adj[] = $s;
            }
            shuffle($adj);
            $tokAdj[$ti] = $adj;
        }

        $unmatchedTokens = [];
        for ($ti = 0; $ti < count($tokens); $ti++) {
            $visited = [];
            $dfs = function ($u) use (&$dfs, &$tokAdj, &$slotToTok, &$visited): bool {
                foreach ($tokAdj[$u] as $s) {
                    if (isset($visited[$s])) continue;
                    $visited[$s] = true;
                    if ($slotToTok[$s] === -1 || $dfs($slotToTok[$s])) {
                        $slotToTok[$s] = $u;
                        return true;
                    }
                }
                return false;
            };
            if (!$dfs($ti)) {
                $unmatchedTokens[] = $ti;
            }
        }

        if (!empty($unmatchedTokens)) {
            $hasSoftKes = true;
            $remFree = [];
            foreach ($slotToTok as $s => $ti) {
                if ($ti === -1) $remFree[] = $s;
            }
            shuffle($remFree);
            foreach ($unmatchedTokens as $idx => $ti) {
                if (isset($remFree[$idx])) {
                    $slotToTok[$remFree[$idx]] = $ti;
                }
            }
        }

        foreach ($freeSlots as $s) {
            $ti = $slotToTok[$s];
            $row[$s] = ($ti !== -1 && isset($tokens[$ti])) ? $tokens[$ti] : 0;
        }
        $grid[$k] = $row;
    }

    return ['grid' => $grid, 'hasSoftKes' => $hasSoftKes];
}

/**
 * Optimasi Jadwal menggunakan Multi-Neighborhood Simulated Annealing (Slot + Broken-Block Teleport + Snap + Rot3)
 * diikuti Stage-2 Strict Polish untuk menekan block violations & same-day splits tanpa bentrok guru.
 */
function sch_solve_sa(array &$ctx, array $initGrid, bool $hasSoftKes, float $stage1Budget = 6.5, float $stage2Budget = 2.5): array {
    $t0 = microtime(true);
    $nSlots = $ctx['nSlots'];
    $kelasIds = $ctx['kelasIds'];
    $nKelas = count($kelasIds);
    $distMeta = &$ctx['distMeta'];
    $guruKes = &$ctx['guruKes'];
    $guruKesList = &$ctx['guruKesList'];
    $guruPairs = &$ctx['guruPairs'];
    $guruTriples = &$ctx['guruTriples'];
    $slotRun = &$ctx['slotRun'];
    $slotHari = &$ctx['slotHari'];
    $slotPrev = &$ctx['slotPrev'];
    $slotNext = &$ctx['slotNext'];

    $grid = $initGrid;

    $guruCnt = [];
    $distSlots = [];
    foreach ($distMeta as $d => $m) {
        if ($d > 0) $distSlots[$d] = [];
        $g = $m['guru_id'];
        if (!isset($guruCnt[$g])) $guruCnt[$g] = array_fill(0, $nSlots, 0);
    }

    $totKes = 0;
    foreach ($kelasIds as $k) {
        for ($s = 0; $s < $nSlots; $s++) {
            $d = $grid[$k][$s];
            if ($d > 0) {
                $distSlots[$d][] = $s;
                $g = $distMeta[$d]['guru_id'];
                $guruCnt[$g][$s]++;
                if (!isset($guruKes[$g][$s])) $totKes++;
            }
        }
    }

    $totCl = 0;
    foreach ($guruCnt as $g => $arr) {
        if ($g === 0) continue;
        for ($s = 0; $s < $nSlots; $s++) {
            if ($arr[$s] > 1) $totCl += ($arr[$s] - 1);
        }
    }

    $distBlk = [0 => 0];
    $distPjok = [0 => 0];
    $distSd = [0 => 0];
    $totBlk = 0;
    $totPjok = 0;
    $totSd = 0;
    foreach ($distSlots as $d => $sl) {
        [$b, $pv, $sd] = sch_eval_dist_slots($d, $sl, $distMeta, $slotRun, $slotHari);
        $distBlk[$d] = $b;
        $distPjok[$d] = $pv;
        $distSd[$d] = $sd;
        $totBlk += $b;
        $totPjok += $pv;
        $totSd += $sd;
    }

    $W_KES = 750;
    $W_CL = 180;
    $W_PJOK = 350;
    $W_BLK = 250;
    $W_SD = 8;

    $bestKes = $totKes;
    $bestCl = $totCl;
    $bestPjok = $totPjok;
    $bestBlk = $totBlk;
    $bestSd = $totSd;
    $bestGrid = $grid;

    $applyPerm = function (int $k, array $srcSlots, array $dstSlots, float $T, int $mode) use (
        &$grid, &$distMeta, &$guruKes, &$guruCnt, &$distSlots, &$distBlk, &$distPjok, &$distSd,
        &$totKes, &$totCl, &$totBlk, &$totPjok, &$totSd, &$slotRun, &$slotHari,
        &$W_KES, &$W_CL, &$W_PJOK, &$W_BLK, &$W_SD, $hasSoftKes
    ): bool {
        $len = count($srcSlots);
        $dArr = [];
        $changed = false;
        $dKes = 0;

        for ($i = 0; $i < $len; $i++) {
            $d = $grid[$k][$srcSlots[$i]];
            $dArr[$i] = $d;
            if ($d !== $grid[$k][$dstSlots[$i]]) $changed = true;
            $g = $distMeta[$d]['guru_id'];
            $okDst = isset($guruKes[$g][$dstSlots[$i]]) ? 1 : 0;
            if (!$hasSoftKes && !$okDst) return false;
            if ($hasSoftKes && $d > 0) {
                $okSrc = isset($guruKes[$g][$srcSlots[$i]]) ? 1 : 0;
                $dKes += ($okSrc - $okDst);
            }
        }
        if (!$changed) return false;
        if ($mode === 1 && $dKes > 0) return false;

        $touchedG = [];
        for ($i = 0; $i < $len; $i++) {
            $g = $distMeta[$dArr[$i]]['guru_id'];
            if ($g > 0) {
                $touchedG[$g][$srcSlots[$i]] = ($touchedG[$g][$srcSlots[$i]] ?? 0) - 1;
                $touchedG[$g][$dstSlots[$i]] = ($touchedG[$g][$dstSlots[$i]] ?? 0) + 1;
            }
        }
        $dCl = 0;
        foreach ($touchedG as $g => $sDeltas) {
            foreach ($sDeltas as $s => $chg) {
                if ($chg === 0) continue;
                $old = $guruCnt[$g][$s];
                $new = $old + $chg;
                $dCl += ($new > 1 ? $new - 1 : 0) - ($old > 1 ? $old - 1 : 0);
            }
        }
        if ($mode === 1 && $dCl > 0) return false;

        $aff = [];
        for ($i = 0; $i < $len; $i++) {
            if ($dArr[$i] > 0) $aff[$dArr[$i]] = true;
        }
        $newSlotsMap = [];
        foreach ($aff as $d => $_) $newSlotsMap[$d] = $distSlots[$d];
        for ($i = 0; $i < $len; $i++) {
            $d = $dArr[$i];
            if ($d > 0) {
                foreach ($newSlotsMap[$d] as $idx => $v) {
                    if ($v === $srcSlots[$i]) { $newSlotsMap[$d][$idx] = -1000 - $i; break; }
                }
            }
        }
        for ($i = 0; $i < $len; $i++) {
            $d = $dArr[$i];
            if ($d > 0) {
                foreach ($newSlotsMap[$d] as $idx => $v) {
                    if ($v === -1000 - $i) { $newSlotsMap[$d][$idx] = $dstSlots[$i]; break; }
                }
            }
        }

        $dBlk = 0;
        $dPjok = 0;
        $dSd = 0;
        $newEval = [];
        foreach ($aff as $d => $_) {
            [$nb, $npv, $nsd] = sch_eval_dist_slots($d, $newSlotsMap[$d], $distMeta, $slotRun, $slotHari);
            $newEval[$d] = [$nb, $npv, $nsd];
            $dBlk += ($nb - $distBlk[$d]);
            $dPjok += ($npv - $distPjok[$d]);
            $dSd += ($nsd - $distSd[$d]);
        }
        if ($mode === 1 && ($dPjok > 0 || $dBlk > 0)) return false;

        $delta = ($mode === 1)
            ? ($dKes * $W_KES + $dBlk * 250 + $dSd * 10)
            : ($dKes * $W_KES + $dCl * $W_CL + $dPjok * $W_PJOK + $dBlk * $W_BLK + $dSd * $W_SD);

        if ($delta <= 0 || ($T > 0.01 && (mt_rand() / mt_getrandmax()) < exp(-$delta / $T))) {
            for ($i = 0; $i < $len; $i++) $grid[$k][$dstSlots[$i]] = $dArr[$i];
            foreach ($touchedG as $g => $sDeltas) {
                foreach ($sDeltas as $s => $chg) $guruCnt[$g][$s] += $chg;
            }
            foreach ($aff as $d => $_) {
                $distSlots[$d] = $newSlotsMap[$d];
                $distBlk[$d] = $newEval[$d][0];
                $distPjok[$d] = $newEval[$d][1];
                $distSd[$d] = $newEval[$d][2];
            }
            $totKes += $dKes;
            $totCl += $dCl;
            $totBlk += $dBlk;
            $totPjok += $dPjok;
            $totSd += $dSd;
            return true;
        }
        return false;
    };

    $swap1 = static function (int $k, int $s1, int $s2, float $T, int $m) use ($applyPerm): bool {
        if ($s1 === $s2) return false;
        return $applyPerm($k, [$s1, $s2], [$s2, $s1], $T, $m);
    };

    $swapPair = static function (int $k, array $A, array $B, float $T, int $m) use ($applyPerm, $swap1): bool {
        if ($A[0] === $A[1] || $B[0] === $B[1]) return false;
        if ($A[0] !== $B[0] && $A[0] !== $B[1] && $A[1] !== $B[0] && $A[1] !== $B[1]) {
            return $applyPerm($k, [$A[0], $A[1], $B[0], $B[1]], [$B[0], $B[1], $A[0], $A[1]], $T, $m);
        }
        // Handle partial 1-slot overlap cleanly by reducing to a single-slot swap
        if ($A[0] === $B[0] && $A[1] !== $B[1]) return $swap1($k, $A[1], $B[1], $T, $m);
        if ($A[0] === $B[1] && $A[1] !== $B[0]) return $swap1($k, $A[1], $B[0], $T, $m);
        if ($A[1] === $B[0] && $A[0] !== $B[1]) return $swap1($k, $A[0], $B[1], $T, $m);
        if ($A[1] === $B[1] && $A[0] !== $B[0]) return $swap1($k, $A[0], $B[0], $T, $m);
        return false;
    };

    $swapTriple = static function (int $k, array $A, array $B, float $T, int $m) use ($applyPerm): bool {
        foreach ($A as $sa) foreach ($B as $sb) if ($sa === $sb) return false;
        return $applyPerm($k, [$A[0], $A[1], $A[2], $B[0], $B[1], $B[2]], [$B[0], $B[1], $B[2], $A[0], $A[1], $A[2]], $T, $m);
    };

    $rot3 = static function (int $k, int $s1, int $s2, int $s3, float $T, int $m) use ($applyPerm): bool {
        if ($s1 === $s2 || $s2 === $s3 || $s1 === $s3) return false;
        return $applyPerm($k, [$s1, $s2, $s3], [$s2, $s3, $s1], $T, $m);
    };

    $getBlockAt = static function (int $k, int $s) use (&$grid, &$slotPrev, &$slotNext): array {
        $d = $grid[$k][$s];
        if ($d === 0) return [$s];
        $slots = [$s];
        $cur = $s;
        while ($slotPrev[$cur] !== -1 && $grid[$k][$slotPrev[$cur]] === $d) {
            $cur = $slotPrev[$cur];
            array_unshift($slots, $cur);
        }
        $cur = $s;
        while ($slotNext[$cur] !== -1 && $grid[$k][$slotNext[$cur]] === $d) {
            $cur = $slotNext[$cur];
            $slots[] = $cur;
        }
        return $slots;
    };

    $pickCand = static function (int $g, float $fallbackAll = 0.0) use (&$guruKesList, $nSlots): int {
        $list = $guruKesList[$g] ?? [];
        if (empty($list) || ($fallbackAll > 0 && mt_rand() / mt_getrandmax() < $fallbackAll)) {
            return mt_rand(0, $nSlots - 1);
        }
        return $list[mt_rand(0, count($list) - 1)];
    };

    $maybeUpdateBest = static function () use (&$totKes, &$totCl, &$totPjok, &$totBlk, &$totSd, &$bestKes, &$bestCl, &$bestPjok, &$bestBlk, &$bestSd, &$grid, &$bestGrid): void {
        $isBetter = false;
        if ($totKes !== $bestKes) {
            $isBetter = ($totKes < $bestKes);
        } elseif ($totCl !== $bestCl) {
            $isBetter = ($totCl < $bestCl);
        } elseif ($totPjok !== $bestPjok) {
            $isBetter = ($totPjok < $bestPjok);
        } elseif ($totBlk !== $bestBlk) {
            $isBetter = ($totBlk < $bestBlk);
        } elseif ($totSd < $bestSd) {
            $isBetter = true;
        }
        if ($isBetter) {
            $bestKes = $totKes;
            $bestCl = $totCl;
            $bestPjok = $totPjok;
            $bestBlk = $totBlk;
            $bestSd = $totSd;
            $bestGrid = $grid;
        }
    };

    $refreshActiveLists = function (array &$badDists, array &$clashCells, array &$sdDists) use (&$distBlk, &$distSd, &$kelasIds, &$grid, &$distMeta, &$guruCnt, $nSlots): void {
        $badDists = [];
        $sdDists = [];
        foreach ($distBlk as $d => $b) if ($b > 0) $badDists[] = $d;
        foreach ($distSd as $d => $v) if ($v > 0) $sdDists[] = $d;
        $clashCells = [];
        foreach ($kelasIds as $k) {
            for ($s = 0; $s < $nSlots; $s++) {
                $d = $grid[$k][$s];
                if ($d > 0 && $guruCnt[$distMeta[$d]['guru_id']][$s] > 1) {
                    $clashCells[] = [$k, $s];
                }
            }
        }
    };

    // Stage 1: Multi-Cycle Balanced SA
    $itersPerCycle = 75000;
    for ($cycle = 0; $cycle < 10; $cycle++) {
        if (microtime(true) - $t0 > $stage1Budget) break;
        if ($bestKes === 0 && $bestCl === 0 && $bestPjok === 0 && $bestBlk === 0 && $bestSd <= 2) break;

        $W_CL = ($cycle >= 2 && $bestCl > 0) ? 155 : 125;
        $Tstart = ($cycle === 0) ? 48.0 : 32.0;
        $Tend = 2.5;
        $cool = pow($Tend / $Tstart, 1.0 / $itersPerCycle);
        $T = $Tstart;

        $badDists = [];
        $clashCells = [];
        $sdDists = [];
        $refreshActiveLists($badDists, $clashCells, $sdDists);

        for ($it = 0; $it < $itersPerCycle; $it++) {
            if (($it & 511) === 0) {
                if (microtime(true) - $t0 > $stage1Budget) break;
                $refreshActiveLists($badDists, $clashCells, $sdDists);
            }

            $r = mt_rand(0, 99);
            if ($r < 32 && !empty($clashCells)) {
                $cc = $clashCells[mt_rand(0, count($clashCells) - 1)];
                $k = $cc[0];
                $s1 = $cc[1];
                $d1 = $grid[$k][$s1];
                $g1 = $distMeta[$d1]['guru_id'];
                $blk1 = $getBlockAt($k, $s1);
                $len1 = count($blk1);

                if ($len1 === 2 && !empty($guruPairs[$g1]) && mt_rand(0, 9) < 7) {
                    $pB = $guruPairs[$g1][mt_rand(0, count($guruPairs[$g1]) - 1)];
                    if (mt_rand(0, 1)) $pB = [$pB[1], $pB[0]];
                    $swapPair($k, $blk1, $pB, $T, 0);
                } elseif ($len1 === 3 && !empty($guruTriples[$g1]) && mt_rand(0, 9) < 7) {
                    $tB = $guruTriples[$g1][mt_rand(0, count($guruTriples[$g1]) - 1)];
                    $swapTriple($k, $blk1, $tB, $T, 0);
                } elseif (mt_rand(0, 9) < 6) {
                    $swap1($k, $s1, $pickCand($g1), $T, 0);
                } else {
                    $s2 = $pickCand($g1);
                    $g2 = $distMeta[$grid[$k][$s2]]['guru_id'];
                    $s3 = $pickCand($g2);
                    $rot3($k, $s1, $s2, $s3, $T, 0);
                }
            } elseif ($r < 56 && !empty($badDists)) {
                $d = $badDists[mt_rand(0, count($badDists) - 1)];
                $k = $distMeta[$d]['kelas_id'];
                $g = $distMeta[$d]['guru_id'];
                $sl = $distSlots[$d];
                $cnt = count($sl);
                if ($cnt >= 2) {
                    $i1 = mt_rand(0, $cnt - 1);
                    $i2 = mt_rand(0, $cnt - 2);
                    if ($i2 >= $i1) $i2++;
                    $m = mt_rand(0, 9);
                    if ($m < 5 && !empty($guruPairs[$g])) {
                        $pB = $guruPairs[$g][mt_rand(0, count($guruPairs[$g]) - 1)];
                        if (mt_rand(0, 1)) $pB = [$pB[1], $pB[0]];
                        $swapPair($k, [$sl[$i1], $sl[$i2]], $pB, $T, 0);
                    } elseif ($m < 8) {
                        $sA = $sl[$i1];
                        $sB = $sl[$i2];
                        $candN = [];
                        if ($slotPrev[$sA] !== -1 && $grid[$k][$slotPrev[$sA]] !== $d) $candN[] = $slotPrev[$sA];
                        if ($slotNext[$sA] !== -1 && $grid[$k][$slotNext[$sA]] !== $d) $candN[] = $slotNext[$sA];
                        if (!empty($candN)) {
                            $sN = $candN[mt_rand(0, count($candN) - 1)];
                            if (!$swap1($k, $sB, $sN, $T, 0)) {
                                $gN = $distMeta[$grid[$k][$sN]]['guru_id'];
                                $rot3($k, $sB, $sN, $pickCand($gN), $T, 0);
                            }
                        }
                    } else {
                        $s1 = $sl[$i1];
                        $s2 = $pickCand($g);
                        $g2 = $distMeta[$grid[$k][$s2]]['guru_id'];
                        $rot3($k, $s1, $s2, $pickCand($g2), $T, 0);
                    }
                }
            } elseif ($r < 72 && !empty($sdDists)) {
                $d = $sdDists[mt_rand(0, count($sdDists) - 1)];
                $k = $distMeta[$d]['kelas_id'];
                $g = $distMeta[$d]['guru_id'];
                $sl = $distSlots[$d];
                $s1 = $sl[mt_rand(0, count($sl) - 1)];
                $pairA = null;
                if ($slotNext[$s1] !== -1 && $grid[$k][$s1 + 1] === $d) $pairA = [$s1, $s1 + 1];
                elseif ($slotPrev[$s1] !== -1 && $grid[$k][$s1 - 1] === $d) $pairA = [$s1 - 1, $s1];
                if ($pairA !== null && !empty($guruPairs[$g]) && mt_rand(0, 1)) {
                    $pB = $guruPairs[$g][mt_rand(0, count($guruPairs[$g]) - 1)];
                    if (mt_rand(0, 1)) $pB = [$pB[1], $pB[0]];
                    $swapPair($k, $pairA, $pB, $T, 0);
                } else {
                    $swap1($k, $s1, $pickCand($g), $T, 0);
                }
            } elseif ($r < 88) {
                $k = $kelasIds[mt_rand(0, $nKelas - 1)];
                $s1 = mt_rand(0, $nSlots - 1);
                $d1 = $grid[$k][$s1];
                $g1 = $distMeta[$d1]['guru_id'];
                $pairA = null;
                if ($slotNext[$s1] !== -1 && $grid[$k][$s1 + 1] === $d1) $pairA = [$s1, $s1 + 1];
                elseif ($slotPrev[$s1] !== -1 && $grid[$k][$s1 - 1] === $d1) $pairA = [$s1 - 1, $s1];
                if ($pairA !== null && !empty($guruPairs[$g1])) {
                    $pB = $guruPairs[$g1][mt_rand(0, count($guruPairs[$g1]) - 1)];
                    if (mt_rand(0, 1)) $pB = [$pB[1], $pB[0]];
                    $swapPair($k, $pairA, $pB, $T, 0);
                } else {
                    $swap1($k, $s1, $pickCand($g1), $T, 0);
                }
            } elseif ($r < 95) {
                $k = $kelasIds[mt_rand(0, $nKelas - 1)];
                $s1 = mt_rand(0, $nSlots - 1);
                $g1 = $distMeta[$grid[$k][$s1]]['guru_id'];
                $s2 = $pickCand($g1);
                $g2 = $distMeta[$grid[$k][$s2]]['guru_id'];
                $rot3($k, $s1, $s2, $pickCand($g2), $T, 0);
            } else {
                $k = $kelasIds[mt_rand(0, $nKelas - 1)];
                $s1 = mt_rand(0, $nSlots - 1);
                $d1 = $grid[$k][$s1];
                $g1 = $distMeta[$d1]['guru_id'];
                if ($slotPrev[$s1] !== -1 && $slotNext[$s1] !== -1 && $grid[$k][$s1 - 1] === $d1 && $grid[$k][$s1 + 1] === $d1 && !empty($guruTriples[$g1])) {
                    $swapTriple($k, [$s1 - 1, $s1, $s1 + 1], $guruTriples[$g1][mt_rand(0, count($guruTriples[$g1]) - 1)], $T, 0);
                }
            }

            $maybeUpdateBest();
            $T *= $cool;
        }
    }

    // Helper sinkronisasi state dari $bestGrid sebelum fase lanjutan
    $syncStateFromBest = static function () use (&$grid, &$bestGrid, &$guruCnt, &$distSlots, &$kelasIds, &$distMeta, &$distBlk, &$distPjok, &$distSd, &$totKes, &$totCl, &$totBlk, &$totPjok, &$totSd, &$guruKes, &$slotRun, &$slotHari, $nSlots): void {
        $grid = $bestGrid;
        foreach ($guruCnt as &$arr) $arr = array_fill(0, $nSlots, 0);
        unset($arr);
        foreach ($distSlots as &$arr) $arr = [];
        unset($arr);
        $totKes = 0;
        foreach ($kelasIds as $k) {
            for ($s = 0; $s < $nSlots; $s++) {
                $d = $grid[$k][$s];
                if ($d > 0) {
                    $distSlots[$d][] = $s;
                    $g = $distMeta[$d]['guru_id'];
                    $guruCnt[$g][$s]++;
                    if (!isset($guruKes[$g][$s])) $totKes++;
                }
            }
        }
        $totCl = 0;
        foreach ($guruCnt as $g => $arr) {
            if ($g === 0) continue;
            for ($s = 0; $s < $nSlots; $s++) if ($arr[$s] > 1) $totCl += ($arr[$s] - 1);
        }
        $totBlk = 0;
        $totPjok = 0;
        $totSd = 0;
        foreach ($distSlots as $d => $sl) {
            [$b, $pv, $sd] = sch_eval_dist_slots($d, $sl, $distMeta, $slotRun, $slotHari);
            $distBlk[$d] = $b;
            $distPjok[$d] = $pv;
            $distSd[$d] = $sd;
            $totBlk += $b;
            $totPjok += $pv;
            $totSd += $sd;
        }
    };

    // Fallback khusus jika masih terdapat bentrok setelah Stage 1
    if ($bestCl > 0) {
        $syncStateFromBest();
        $W_CL = 260;
        $W_PJOK = 115;
        $W_BLK = 75;
        $W_SD = 5;
        $fbStart = microtime(true);
        $fbIters = 180000;
        $T = 40.0;
        $cool = pow(2.0 / 40.0, 1.0 / $fbIters);
        $badDists = [];
        $clashCells = [];
        $sdDists = [];
        $refreshActiveLists($badDists, $clashCells, $sdDists);

        for ($it = 0; $it < $fbIters; $it++) {
            if ($bestCl === 0) break;
            if (($it & 511) === 0) {
                if (microtime(true) - $fbStart > 2.5) break;
                $refreshActiveLists($badDists, $clashCells, $sdDists);
            }
            if (!empty($clashCells) && mt_rand(0, 99) < 65) {
                $cc = $clashCells[mt_rand(0, count($clashCells) - 1)];
                $k = $cc[0];
                $s1 = $cc[1];
                $d1 = $grid[$k][$s1];
                $g1 = $distMeta[$d1]['guru_id'];
                $blk1 = $getBlockAt($k, $s1);
                $len1 = count($blk1);

                if ($len1 === 2 && !empty($guruPairs[$g1]) && mt_rand(0, 1)) {
                    $pB = $guruPairs[$g1][mt_rand(0, count($guruPairs[$g1]) - 1)];
                    if (mt_rand(0, 1)) $pB = [$pB[1], $pB[0]];
                    $swapPair($k, $blk1, $pB, $T, 0);
                } elseif ($len1 === 3 && !empty($guruTriples[$g1]) && mt_rand(0, 1)) {
                    $tB = $guruTriples[$g1][mt_rand(0, count($guruTriples[$g1]) - 1)];
                    $swapTriple($k, $blk1, $tB, $T, 0);
                } elseif (mt_rand(0, 2) !== 0) {
                    $swap1($k, $s1, $pickCand($g1), $T, 0);
                } else {
                    $s2 = $pickCand($g1);
                    $g2 = $distMeta[$grid[$k][$s2]]['guru_id'];
                    $rot3($k, $s1, $s2, $pickCand($g2), $T, 0);
                }
            } else {
                $k = $kelasIds[mt_rand(0, $nKelas - 1)];
                $s1 = mt_rand(0, $nSlots - 1);
                $g1 = $distMeta[$grid[$k][$s1]]['guru_id'];
                $swap1($k, $s1, $pickCand($g1), $T, 0);
            }
            $maybeUpdateBest();
            $T *= $cool;
        }
    }

    // Stage 2: Strict Polish (menjaga Kes, Clashes, Pjok, Blk tidak memburuk sambil menekan Blk & Same-Day ke titik optimal)
    $syncStateFromBest();
    $pStart = microtime(true);
    $pIters = 180000;
    $T = 16.0;
    $cool = pow(0.2 / 16.0, 1.0 / $pIters);
    $badDists = [];
    $clashCells = [];
    $sdDists = [];
    $refreshActiveLists($badDists, $clashCells, $sdDists);

    for ($it = 0; $it < $pIters; $it++) {
        if (($it & 511) === 0) {
            if (microtime(true) - $pStart > $stage2Budget) break;
            $refreshActiveLists($badDists, $clashCells, $sdDists);
            if (empty($sdDists) && empty($badDists)) break;
        }

        $r = mt_rand(0, 99);
        if ($r < 28 && !empty($badDists)) {
            $d = $badDists[mt_rand(0, count($badDists) - 1)];
            $k = $distMeta[$d]['kelas_id'];
            $g = $distMeta[$d]['guru_id'];
            $sl = $distSlots[$d];
            $cnt = count($sl);
            if ($cnt >= 2) {
                $i1 = mt_rand(0, $cnt - 1);
                $i2 = mt_rand(0, $cnt - 2);
                if ($i2 >= $i1) $i2++;
                $m = mt_rand(0, 9);
                if ($m < 5 && !empty($guruPairs[$g])) {
                    $pB = $guruPairs[$g][mt_rand(0, count($guruPairs[$g]) - 1)];
                    if (mt_rand(0, 1)) $pB = [$pB[1], $pB[0]];
                    $swapPair($k, [$sl[$i1], $sl[$i2]], $pB, $T, 1);
                } elseif ($m < 8) {
                    $sA = $sl[$i1];
                    $sB = $sl[$i2];
                    $candN = [];
                    if ($slotPrev[$sA] !== -1 && $grid[$k][$slotPrev[$sA]] !== $d) $candN[] = $slotPrev[$sA];
                    if ($slotNext[$sA] !== -1 && $grid[$k][$slotNext[$sA]] !== $d) $candN[] = $slotNext[$sA];
                    if (!empty($candN)) {
                        $sN = $candN[mt_rand(0, count($candN) - 1)];
                        if (!$swap1($k, $sB, $sN, $T, 1)) {
                            $gN = $distMeta[$grid[$k][$sN]]['guru_id'];
                            $rot3($k, $sB, $sN, $pickCand($gN), $T, 1);
                        }
                    }
                } else {
                    $s1 = $sl[$i1];
                    $s2 = $pickCand($g);
                    $g2 = $distMeta[$grid[$k][$s2]]['guru_id'];
                    $rot3($k, $s1, $s2, $pickCand($g2), $T, 1);
                }
            }
        } elseif ($r < 68 && !empty($sdDists)) {
            $d = $sdDists[mt_rand(0, count($sdDists) - 1)];
            $k = $distMeta[$d]['kelas_id'];
            $g = $distMeta[$d]['guru_id'];
            $sl = $distSlots[$d];
            $s1 = $sl[mt_rand(0, count($sl) - 1)];
            $pairA = null;
            if ($slotNext[$s1] !== -1 && $grid[$k][$s1 + 1] === $d) $pairA = [$s1, $s1 + 1];
            elseif ($slotPrev[$s1] !== -1 && $grid[$k][$s1 - 1] === $d) $pairA = [$s1 - 1, $s1];
            if ($pairA !== null && !empty($guruPairs[$g]) && mt_rand(0, 2) !== 0) {
                $pB = $guruPairs[$g][mt_rand(0, count($guruPairs[$g]) - 1)];
                if (mt_rand(0, 1)) $pB = [$pB[1], $pB[0]];
                $swapPair($k, $pairA, $pB, $T, 1);
            } elseif (mt_rand(0, 2) !== 0) {
                $swap1($k, $s1, $pickCand($g), $T, 1);
            } else {
                $s2 = $pickCand($g);
                $g2 = $distMeta[$grid[$k][$s2]]['guru_id'];
                $rot3($k, $s1, $s2, $pickCand($g2), $T, 1);
            }
        } elseif ($r < 85) {
            $k = $kelasIds[mt_rand(0, $nKelas - 1)];
            $s1 = mt_rand(0, $nSlots - 1);
            $d1 = $grid[$k][$s1];
            $g1 = $distMeta[$d1]['guru_id'];
            $pairA = null;
            if ($slotNext[$s1] !== -1 && $grid[$k][$s1 + 1] === $d1) $pairA = [$s1, $s1 + 1];
            elseif ($slotPrev[$s1] !== -1 && $grid[$k][$s1 - 1] === $d1) $pairA = [$s1 - 1, $s1];
            if ($pairA !== null && !empty($guruPairs[$g1])) {
                $pB = $guruPairs[$g1][mt_rand(0, count($guruPairs[$g1]) - 1)];
                if (mt_rand(0, 1)) $pB = [$pB[1], $pB[0]];
                $swapPair($k, $pairA, $pB, $T, 1);
            } else {
                $swap1($k, $s1, $pickCand($g1), $T, 1);
            }
        } elseif ($r < 95) {
            $k = $kelasIds[mt_rand(0, $nKelas - 1)];
            $s1 = mt_rand(0, $nSlots - 1);
            $g1 = $distMeta[$grid[$k][$s1]]['guru_id'];
            $s2 = $pickCand($g1);
            $g2 = $distMeta[$grid[$k][$s2]]['guru_id'];
            $rot3($k, $s1, $s2, $pickCand($g2), $T, 1);
        } else {
            $k = $kelasIds[mt_rand(0, $nKelas - 1)];
            $s1 = mt_rand(0, $nSlots - 1);
            $d1 = $grid[$k][$s1];
            $g1 = $distMeta[$d1]['guru_id'];
            if ($slotPrev[$s1] !== -1 && $slotNext[$s1] !== -1 && $grid[$k][$s1 - 1] === $d1 && $grid[$k][$s1 + 1] === $d1 && !empty($guruTriples[$g1])) {
                $swapTriple($k, [$s1 - 1, $s1, $s1 + 1], $guruTriples[$g1][mt_rand(0, count($guruTriples[$g1]) - 1)], $T, 1);
            }
        }

        $maybeUpdateBest();
        $T *= $cool;
    }

    return [
        'grid' => $bestGrid,
        'elapsed_seconds' => round(microtime(true) - $t0, 2)
    ];
}

/**
 * Audit independen terhadap grid jadwal hasil optimasi.
 */
function sch_audit_grid(array &$ctx, array $grid): array {
    $nSlots = $ctx['nSlots'];
    $nKelas = count($ctx['kelasIds']);
    $distMeta = &$ctx['distMeta'];
    $guruKes = &$ctx['guruKes'];
    $slotRun = &$ctx['slotRun'];
    $slotHari = &$ctx['slotHari'];

    $totalPlaced = 0;
    $kesViol = 0;
    $gCheck = [];
    $dCheck = [];

    foreach ($ctx['kelasIds'] as $k) {
        for ($s = 0; $s < $nSlots; $s++) {
            $d = $grid[$k][$s] ?? 0;
            if ($d === 0) continue;
            $totalPlaced++;
            $g = $distMeta[$d]['guru_id'];
            if (!isset($guruKes[$g][$s])) $kesViol++;
            $gCheck[$g][$s] = ($gCheck[$g][$s] ?? 0) + 1;
            $dCheck[$d][] = $s;
        }
    }

    $clashes = 0;
    foreach ($gCheck as $map) {
        foreach ($map as $cnt) {
            if ($cnt > 1) $clashes += ($cnt - 1);
        }
    }

    $blkViol = 0;
    $pjokViol = 0;
    $sameDaySplits = 0;
    $blkDetails = [];
    foreach ($dCheck as $d => $sl) {
        [$b, $pv, $sd] = sch_eval_dist_slots($d, $sl, $distMeta, $slotRun, $slotHari);
        $blkViol += $b;
        $pjokViol += $pv;
        $sameDaySplits += $sd;
        if ($b > 0 && count($blkDetails) < 10) {
            $m = $distMeta[$d];
            $blkDetails[] = "{$m['nama_kelas']} {$m['kode']} ({$m['jp']} JP - {$m['nama_guru']})";
        }
    }

    $totalRequiredJp = $ctx['totalRequiredJp'];
    $totalCapacity = $nKelas * $nSlots;

    return [
        'total_placed' => $totalPlaced,
        'total_required_jp' => $totalRequiredJp,
        'total_capacity' => $totalCapacity,
        'empty_slots' => max(0, $totalCapacity - $totalPlaced),
        'unplaced_slots' => max(0, $totalRequiredJp - $totalPlaced),
        'total_clashes' => $clashes,
        'has_clashes' => ($clashes > 0),
        'kesediaan_violations' => $kesViol,
        'pjok_violations' => $pjokViol,
        'block_violations' => $blkViol,
        'same_day_splits' => $sameDaySplits,
        'block_violation_details' => $blkDetails
    ];
}

/**
 * Simpan grid jadwal ke tabel sch_jadwal secara atomik dalam transaksi DB.
 */
function sch_persist_grid(PDO $pdo, array &$ctx, array $grid): void {
    $nSlots = $ctx['nSlots'];
    $slotJamId = $ctx['slotJamId'];

    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM sch_jadwal");
        $stmtInsert = $pdo->prepare("INSERT INTO sch_jadwal (kelas_id, jam_belajar_id, distribusi_id) VALUES (?, ?, ?)");
        foreach ($ctx['kelasIds'] as $k) {
            for ($s = 0; $s < $nSlots; $s++) {
                $d = $grid[$k][$s] ?? 0;
                if ($d > 0) {
                    $stmtInsert->execute([$k, $slotJamId[$s], $d]);
                }
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Bentuk pesan & payload respon standar untuk aksi generate dan auto_fill_gaps.
 */
function sch_build_solver_response(array &$ctx, array $audit, float $elapsed, string $verbLabel = 'di-generate'): array {
    $placed = $audit['total_placed'];
    $reqJp = $audit['total_required_jp'];
    $emptySlots = $audit['empty_slots'];
    $clashes = $audit['total_clashes'];
    $kesViol = $audit['kesediaan_violations'];
    $pjokViol = $audit['pjok_violations'];
    $blkViol = $audit['block_violations'];
    $sdSplits = $audit['same_day_splits'];
    $bottlenecks = $ctx['diagnostics']['run_bottlenecks'] ?? [];

    $summaryParts = [
        "{$placed}/{$reqJp} JP terisi" . ($emptySlots > 0 ? " ({$emptySlots} slot kosong)" : " tanpa jam kosong"),
        "{$clashes} bentrok guru",
        "{$kesViol} pelanggaran kesediaan",
        "{$blkViol} pelanggaran blok JP",
        "{$sdSplits} mapel terpecah di hari sama"
    ];

    if ($clashes === 0 && $kesViol === 0 && $pjokViol === 0 && $blkViol === 0 && $emptySlots === 0) {
        $msg = "Jadwal berhasil {$verbLabel} 100% optimal! (" . implode(', ', $summaryParts) . ").";
    } elseif ($clashes === 0 && $kesViol === 0 && $pjokViol === 0) {
        $msg = "Jadwal selesai {$verbLabel} dengan 0 bentrok guru & 100% patuh kesediaan (" . implode(', ', $summaryParts) . ").";
        if ($blkViol > 0 && !empty($bottlenecks)) {
            $msg .= ' Catatan kapasitas kesediaan: ' . implode(' | ', $bottlenecks);
        }
    } else {
        $msg = "Jadwal selesai {$verbLabel} dengan catatan kendala: " . implode(', ', $summaryParts) . ".";
    }

    return [
        'message' => $msg,
        'data' => [
            'filled_slots' => $placed,
            'total_placed' => $placed,
            'total_required_jp' => $reqJp,
            'empty_slots' => $emptySlots,
            'unplaced_blocks' => $audit['unplaced_slots'],
            'total_clashes' => $clashes,
            'has_clashes' => ($clashes > 0),
            'kesediaan_violations' => $kesViol,
            'pjok_violations' => $pjokViol,
            'block_violations' => $blkViol,
            'same_day_splits' => $sdSplits,
            'elapsed_seconds' => $elapsed,
            'diagnostics' => [
                'teachers_without_kesediaan' => $ctx['diagnostics']['teachers_without_kesediaan'] ?? [],
                'run_bottlenecks' => $bottlenecks,
                'block_violation_details' => $audit['block_violation_details']
            ]
        ]
    ];
}

switch ($action) {
    case 'cli_noop':
        break;

    case 'list':
        $kelas_id = $_GET['kelas_id'] ?? null;
        
        $where = "";
        if ($kelas_id) $where = "WHERE j.kelas_id = " . (int)$kelas_id;
        
        $query = "
            SELECT j.*, 
                   k.nama_kelas,
                   jb.hari, jb.jam_ke, jb.tipe, jb.nama_jam,
                   d.guru_id, d.mapel_id,
                   g.nama_guru, g.kode_guru, g.singkatan,
                   m.nama_mapel, m.kode_mapel
            FROM sch_jadwal j
            JOIN sch_kelas k ON j.kelas_id = k.id
            JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
            JOIN sch_distribusi d ON j.distribusi_id = d.id
            JOIN sch_guru g ON d.guru_id = g.id
            JOIN sch_mapel m ON d.mapel_id = m.id
            $where
            ORDER BY k.rombel, k.nama_kelas, 
            CASE jb.hari
                WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3
                WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END, 
            jb.jam_ke ASC
        ";
        $stmt = db()->query($query);
        json_response(200, true, 'Sukses', $stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'stats':
        $tKelas = db()->query("SELECT COUNT(*) FROM sch_kelas")->fetchColumn();
        $tGuru = db()->query("SELECT COUNT(*) FROM sch_guru")->fetchColumn();
        $tDist = db()->query("SELECT COUNT(*) FROM sch_distribusi")->fetchColumn();
        $tJam = db()->query("SELECT COUNT(*) FROM sch_jam_belajar WHERE tipe='Pembelajaran'")->fetchColumn();
        $dist = db()->query("SELECT SUM(jp) as total_jp FROM sch_distribusi")->fetchColumn();
        
        $isLocked = (int)get_setting('sch_jadwal_locked', 0) === 1;

        json_response(200, true, 'Info Stats', [
            'total_kelas' => (int)$tKelas,
            'total_guru' => (int)$tGuru,
            'total_distribusi' => (int)$tDist,
            'slot_belajar_minimum_per_kelas' => (int)$tJam,
            'total_kebutuhan_jp_sekolah' => (int)($dist ?: 0),
            'is_locked' => $isLocked
        ]);
        break;

    case 'get_lock':
    case 'get_lock_status':
        $isLocked = (int)get_setting('sch_jadwal_locked', 0) === 1;
        $lockedAt = get_setting('sch_jadwal_locked_at', '');
        $lockedBy = get_setting('sch_jadwal_locked_by', '');
        $canUnlock = (($user['role'] ?? '') === 'superadmin');

        json_response(200, true, 'Status lock jadwal', [
            'locked' => $isLocked ? 1 : 0,
            'is_locked' => $isLocked,
            'locked_at' => $lockedAt,
            'locked_by' => $lockedBy,
            'can_unlock' => $canUnlock,
            'user_role' => $user['role'] ?? ''
        ]);
        break;

    case 'toggle_lock':
        $isLocked = (int)get_setting('sch_jadwal_locked', 0) === 1;
        if ($isLocked) {
            if (($user['role'] ?? '') !== 'superadmin') {
                json_response(403, false, 'Akses ditolak. Hanya Superadmin yang berhak membuka kunci (UNLOCK) jadwal.');
            }
            upsert_setting('sch_jadwal_locked', '0', 'number', 'Status Kunci Jadwal');
            json_response(200, true, 'Kunci jadwal berhasil DIBUKA (UNLOCKED).', [
                'locked' => 0,
                'is_locked' => false
            ]);
        } else {
            if (!portal_has_permission($user, 'jadwal_manage') && ($user['role'] ?? '') !== 'superadmin') {
                json_response(403, false, 'Akses ditolak. Anda tidak memiliki izin mengunci jadwal.');
            }
            $totalJadwal = (int)db()->query("SELECT COUNT(*) FROM sch_jadwal")->fetchColumn();
            if ($totalJadwal === 0) {
                json_response(400, false, 'Jadwal masih kosong, tidak dapat mengunci jadwal.');
            }
            $nowStr = date('d/m/Y H:i') . ' WIB';
            $byName = $user['nama_lengkap'] ?? $user['username'];
            upsert_setting('sch_jadwal_locked', '1', 'number', 'Status Kunci Jadwal');
            upsert_setting('sch_jadwal_locked_at', $nowStr, 'text', 'Waktu Penguncian Jadwal');
            upsert_setting('sch_jadwal_locked_by', $byName, 'text', 'User Pengunci Jadwal');
            json_response(200, true, 'Jadwal berhasil DIKUNCI (LOCKED).', [
                'locked' => 1,
                'is_locked' => true,
                'locked_at' => $nowStr,
                'locked_by' => $byName
            ]);
        }
        break;

    case 'lock':
        if (!portal_has_permission($user, 'jadwal_manage') && ($user['role'] ?? '') !== 'superadmin') {
            json_response(403, false, 'Akses ditolak. Anda tidak memiliki izin mengunci jadwal.');
        }

        $totalJadwal = (int)db()->query("SELECT COUNT(*) FROM sch_jadwal")->fetchColumn();
        if ($totalJadwal === 0) {
            json_response(400, false, 'Jadwal masih kosong, tidak dapat mengunci jadwal.');
        }

        $nowStr = date('d/m/Y H:i') . ' WIB';
        $byName = $user['nama_lengkap'] ?? $user['username'];

        upsert_setting('sch_jadwal_locked', '1', 'number', 'Status Kunci Jadwal');
        upsert_setting('sch_jadwal_locked_at', $nowStr, 'text', 'Waktu Penguncian Jadwal');
        upsert_setting('sch_jadwal_locked_by', $byName, 'text', 'User Pengunci Jadwal');

        json_response(200, true, 'Jadwal berhasil DIKUNCI (LOCKED). Jadwal tidak dapat diubah atau di-generate ulang kecuali dibuka kuncinya oleh Superadmin.', [
            'is_locked' => true,
            'locked_at' => $nowStr,
            'locked_by' => $byName
        ]);
        break;

    case 'unlock':
        // HANYA SUPERADMIN YANG BISA UNLOCK
        if (($user['role'] ?? '') !== 'superadmin') {
            json_response(403, false, 'Akses ditolak. Hanya Superadmin yang berhak membuka kunci (UNLOCK) jadwal.');
        }

        upsert_setting('sch_jadwal_locked', '0', 'number', 'Status Kunci Jadwal');

        json_response(200, true, 'Kunci jadwal berhasil DIBUKA (UNLOCKED). Jadwal sekarang dapat diedit dan di-generate ulang.', [
            'is_locked' => false
        ]);
        break;

    case 'daily_distribution':
        $dayMap = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
            4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
        ];
        $currentHari = $dayMap[(int)date('N')] ?? 'Senin';
        $selectedHari = isset($_GET['hari']) && !empty($_GET['hari']) ? sanitize($_GET['hari']) : $currentHari;

        $stmt = db()->prepare("
            SELECT 
                g.id as guru_id,
                g.kode_guru,
                g.nama_guru,
                g.singkatan,
                m.kode_mapel,
                m.nama_mapel,
                k.id as kelas_id,
                k.nama_kelas,
                jb.id as jam_id,
                jb.jam_ke,
                jb.nama_jam,
                j.id as jadwal_id
            FROM sch_jadwal j
            JOIN sch_distribusi d ON j.distribusi_id = d.id
            JOIN sch_guru g ON d.guru_id = g.id
            JOIN sch_mapel m ON d.mapel_id = m.id
            JOIN sch_kelas k ON j.kelas_id = k.id
            JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
            WHERE jb.hari = ?
            ORDER BY g.nama_guru ASC, k.nama_kelas ASC, jb.jam_ke ASC
        ");
        $stmt->execute([$selectedHari]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $guruList = [];
        $activeClasses = [];
        $totalJp = count($rows);

        foreach ($rows as $r) {
            $gId = (int)$r['guru_id'];
            $activeClasses[$r['kelas_id']] = true;

            if (!isset($guruList[$gId])) {
                $guruList[$gId] = [
                    'guru_id' => $gId,
                    'kode_guru' => $r['kode_guru'],
                    'nama_guru' => $r['nama_guru'],
                    'singkatan' => $r['singkatan'] ?: $r['kode_guru'],
                    'total_jp' => 0,
                    'classes' => []
                ];
            }
            $guruList[$gId]['total_jp']++;

            $kKey = $r['nama_kelas'] . '_' . $r['kode_mapel'];
            if (!isset($guruList[$gId]['classes'][$kKey])) {
                $guruList[$gId]['classes'][$kKey] = [
                    'kelas' => $r['nama_kelas'],
                    'kode_mapel' => $r['kode_mapel'],
                    'nama_mapel' => $r['nama_mapel'],
                    'slots' => []
                ];
            }
            $guruList[$gId]['classes'][$kKey]['slots'][] = [
                'jam_ke' => (int)$r['jam_ke'],
                'nama_jam' => (string)($r['nama_jam'] ?? '')
            ];
        }

        $resultTeachers = [];
        foreach ($guruList as $g) {
            $classDetails = [];
            foreach ($g['classes'] as $c) {
                $slots = $c['slots'];
                usort($slots, fn($a, $b) => $a['jam_ke'] <=> $b['jam_ke']);

                $ranges = [];
                $startLabel = null;
                $prevJamKe = null;
                $prevLabel = null;
                foreach ($slots as $slot) {
                    $jk = $slot['jam_ke'];
                    $lbl = preg_replace('/^jam\s+/i', '', trim($slot['nama_jam']));
                    if ($lbl === '') {
                        $lbl = (string)$jk;
                    }
                    if ($startLabel === null) {
                        $startLabel = $lbl;
                        $prevJamKe = $jk;
                        $prevLabel = $lbl;
                    } elseif ($jk === $prevJamKe + 1) {
                        $prevJamKe = $jk;
                        $prevLabel = $lbl;
                    } else {
                        $ranges[] = ($startLabel === $prevLabel) ? "Jam $startLabel" : "Jam $startLabel-$prevLabel";
                        $startLabel = $lbl;
                        $prevJamKe = $jk;
                        $prevLabel = $lbl;
                    }
                }
                if ($startLabel !== null) {
                    $ranges[] = ($startLabel === $prevLabel) ? "Jam $startLabel" : "Jam $startLabel-$prevLabel";
                }

                $classDetails[] = [
                    'kelas' => $c['kelas'],
                    'kode_mapel' => $c['kode_mapel'],
                    'nama_mapel' => $c['nama_mapel'],
                    'jam_display' => implode(', ', $ranges),
                    'total_jp' => count($slots)
                ];
            }
            usort($classDetails, fn($a, $b) => strnatcasecmp($a['kelas'], $b['kelas']));
            $g['class_details'] = $classDetails;
            unset($g['classes']);
            $resultTeachers[] = $g;
        }

        $allHari = db()->query("
            SELECT DISTINCT hari 
            FROM sch_jam_belajar 
            ORDER BY CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END
        ")->fetchAll(PDO::FETCH_COLUMN);

        $isLocked = (int)get_setting('sch_jadwal_locked', 0) === 1;

        json_response(200, true, 'Rekapitulasi Distribusi Harian', [
            'hari' => $selectedHari,
            'is_today' => ($selectedHari === $currentHari),
            'is_locked' => $isLocked,
            'available_days' => $allHari,
            'all_hari' => $allHari,
            'total_guru_mengajar' => count($resultTeachers),
            'total_jp' => $totalJp,
            'total_kelas_aktif' => count($activeClasses),
            'teachers' => $resultTeachers
        ]);
        break;

    case 'move_slot':
        $isLocked = (int)get_setting('sch_jadwal_locked', 0) === 1;
        if ($isLocked) {
            json_response(403, false, 'Jadwal saat ini dalam status TERKUNCI (LOCKED). Hanya Superadmin yang dapat membuka kunci jadwal.');
        }

        $input = get_input();
        $source_id = (int)($input['source_id'] ?? $_POST['source_id'] ?? $_GET['source_id'] ?? 0);
        $target_jam_id = (int)($input['target_jam_id'] ?? $_POST['target_jam_id'] ?? $_GET['target_jam_id'] ?? 0);
        $target_kelas_id = (int)($input['target_kelas_id'] ?? $_POST['target_kelas_id'] ?? $_GET['target_kelas_id'] ?? 0);

        if (!$source_id || !$target_jam_id) {
            json_response(400, false, 'Data pemindahan jadwal tidak lengkap.');
        }

        try {
            db()->beginTransaction();

            $stmtSrc = db()->prepare("SELECT * FROM sch_jadwal WHERE id = ?");
            $stmtSrc->execute([$source_id]);
            $source = $stmtSrc->fetch(PDO::FETCH_ASSOC);

            if (!$source) {
                db()->rollBack();
                json_response(404, false, 'Jadwal sumber tidak ditemukan.');
            }

            $source_kelas_id = (int)$source['kelas_id'];
            if ($target_kelas_id > 0 && $target_kelas_id !== $source_kelas_id) {
                db()->rollBack();
                json_response(400, false, 'Pemindahan jadwal hanya diperbolehkan di dalam kelas yang sama.');
            }
            $target_kelas_id = $source_kelas_id;

            $stmtJam = db()->prepare("SELECT * FROM sch_jam_belajar WHERE id = ?");
            $stmtJam->execute([$target_jam_id]);
            $targetJam = $stmtJam->fetch(PDO::FETCH_ASSOC);

            if (!$targetJam || $targetJam['tipe'] !== 'Pembelajaran') {
                db()->rollBack();
                json_response(400, false, 'Slot tujuan bukan jam pembelajaran yang valid.');
            }

            // Cek apakah di kelas tujuan pada jam tersebut sudah ada jadwal (SWAP)
            $stmtTarget = db()->prepare("SELECT * FROM sch_jadwal WHERE kelas_id = ? AND jam_belajar_id = ?");
            $stmtTarget->execute([$target_kelas_id, $target_jam_id]);
            $targetSlot = $stmtTarget->fetch(PDO::FETCH_ASSOC);

            db()->exec("SET FOREIGN_KEY_CHECKS = 0;");

            if ($targetSlot) {
                $targetId = (int)$targetSlot['id'];
                $sourceDistId = (int)$source['distribusi_id'];
                $targetDistId = (int)$targetSlot['distribusi_id'];

                $stmtSwap = db()->prepare("UPDATE sch_jadwal SET distribusi_id = ? WHERE id = ?");
                $stmtSwap->execute([$targetDistId, $source_id]);
                $stmtSwap->execute([$sourceDistId, $targetId]);

                $msg = 'Jadwal berhasil ditukar posisinya.';
            } else {
                $stmt = db()->prepare("UPDATE sch_jadwal SET jam_belajar_id = ? WHERE id = ?");
                $stmt->execute([$target_jam_id, $source_id]);
                $msg = 'Jadwal berhasil dipindahkan ke slot baru.';
            }

            db()->exec("SET FOREIGN_KEY_CHECKS = 1;");
            db()->commit();

            // Cek bentrok guru
            $clashes = db()->query("
                SELECT j1.id as id1, j2.id as id2, d1.guru_id, g.nama_guru, k1.nama_kelas as kelas1, k2.nama_kelas as kelas2, jb.hari, jb.jam_ke
                FROM sch_jadwal j1
                JOIN sch_jadwal j2 ON j1.jam_belajar_id = j2.jam_belajar_id AND j1.id < j2.id
                JOIN sch_distribusi d1 ON j1.distribusi_id = d1.id
                JOIN sch_distribusi d2 ON j2.distribusi_id = d2.id AND d1.guru_id = d2.guru_id
                JOIN sch_guru g ON d1.guru_id = g.id
                JOIN sch_kelas k1 ON j1.kelas_id = k1.id
                JOIN sch_kelas k2 ON j2.kelas_id = k2.id
                JOIN sch_jam_belajar jb ON j1.jam_belajar_id = jb.id
            ")->fetchAll(PDO::FETCH_ASSOC);

            $hasClashes = count($clashes) > 0;
            if ($hasClashes) {
                $msg .= ' Peringatan: Terdapat guru bentrok pada jam ini (ditandai merah).';
            }

            json_response(200, true, $msg, [
                'has_clashes' => $hasClashes,
                'total_clashes' => count($clashes),
                'clashes' => $clashes
            ]);

        } catch (Exception $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            db()->exec("SET FOREIGN_KEY_CHECKS = 1;");
            json_response(500, false, 'Gagal memindahkan jadwal: ' . $e->getMessage());
        }
        break;

    case 'auto_fill_gaps':
    case 'generate':
        $isFillMode = ($action === 'auto_fill_gaps');
        $isLocked = (int)get_setting('sch_jadwal_locked', 0) === 1;
        if ($isLocked) {
            json_response(403, false, 'Jadwal saat ini dalam status TERKUNCI (LOCKED). Hanya Superadmin yang dapat membuka kunci jadwal.');
        }

        set_time_limit(180);
        ignore_user_abort(true);

        try {
            $ctx = sch_build_problem_context(db());
            if (!empty($ctx['errors'])) {
                json_response(400, false, implode(' ', $ctx['errors']), [
                    'errors' => $ctx['errors'],
                    'diagnostics' => $ctx['diagnostics']
                ]);
            }

            $existingGrid = null;
            if ($isFillMode) {
                $existingRows = db()->query("SELECT kelas_id, jam_belajar_id, distribusi_id FROM sch_jadwal")->fetchAll(PDO::FETCH_ASSOC);
                $existingGrid = [];
                foreach ($existingRows as $er) {
                    $jId = (int)$er['jam_belajar_id'];
                    if (isset($ctx['jamIdToSlot'][$jId])) {
                        $existingGrid[(int)$er['kelas_id']][$ctx['jamIdToSlot'][$jId]] = (int)$er['distribusi_id'];
                    }
                }
            }

            $init = sch_init_grid($ctx, $existingGrid);
            $stage1Budget = $isFillMode ? 3.5 : 6.5;
            $stage2Budget = $isFillMode ? 1.5 : 2.5;
            $result = sch_solve_sa($ctx, $init['grid'], $init['hasSoftKes'], $stage1Budget, $stage2Budget);
            $audit = sch_audit_grid($ctx, $result['grid']);

            // Simpan atomik ke database hanya setelah solusi lengkap selesai & teraudit
            sch_persist_grid(db(), $ctx, $result['grid']);

            $verbLabel = $isFillMode ? 'dipadatkan' : 'di-generate';
            $payload = sch_build_solver_response($ctx, $audit, $result['elapsed_seconds'], $verbLabel);

            json_response(200, true, $payload['message'], $payload['data']);
        } catch (Exception $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $prefix = $isFillMode ? 'Gagal memadatkan jadwal: ' : 'Gagal Generate: ';
            json_response(500, false, $prefix . $e->getMessage());
        }
        break;

    default:
        json_response(400, false, 'Invalid action');
}
