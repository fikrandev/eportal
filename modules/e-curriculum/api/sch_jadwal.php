<?php
/**
 * Jadwal API for E-Schedule
 * Engine Pembuat Jadwal Pelajaran (Scheduler)
 */
require_once __DIR__ . '/../../../api/config.php';

$user = auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
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
        json_response(200, true, 'Sukses', $stmt->fetchAll());
        break;

    case 'stats':
        // Cek kesiapan data
        $tKelas = db()->query("SELECT COUNT(*) FROM sch_kelas")->fetchColumn();
        $tGuru = db()->query("SELECT COUNT(*) FROM sch_guru")->fetchColumn();
        $tDist = db()->query("SELECT COUNT(*) FROM sch_distribusi")->fetchColumn();
        $tJam = db()->query("SELECT COUNT(*) FROM sch_jam_belajar WHERE tipe='Pembelajaran'")->fetchColumn();
        
        $dist = db()->query("SELECT SUM(jp) as total_jp FROM sch_distribusi")->fetchColumn();
        
        json_response(200, true, 'Info Stats', [
            'total_kelas' => $tKelas,
            'total_guru' => $tGuru,
            'total_distribusi' => $tDist,
            'slot_belajar_minimum_per_kelas' => $tJam,
            'total_kebutuhan_jp_sekolah' => $dist ?: 0
        ]);
        break;

    case 'generate':
        set_time_limit(0); // Prevent timeout for long algorithm
        ignore_user_abort(true); // Don't stop if browser disconnects
        
        // The Engine
        try {
            // 1. Bersihkan jadwal lama
            db()->query("DELETE FROM sch_jadwal");
            db()->query("ALTER TABLE sch_jadwal AUTO_INCREMENT = 1");

            // 2. Load Environment
            $jamRows = db()->query("
                SELECT * FROM sch_jam_belajar 
                ORDER BY CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END, jam_ke ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $kelasRows = db()->query("SELECT id FROM sch_kelas")->fetchAll(PDO::FETCH_ASSOC);
            $guruRows = db()->query("SELECT id FROM sch_guru")->fetchAll(PDO::FETCH_ASSOC);
            
            $distribusiRows = db()->query("
                SELECT d.*, m.nama_mapel 
                FROM sch_distribusi d 
                JOIN sch_mapel m ON d.mapel_id = m.id 
                ORDER BY d.jp DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $kesediaanRows = db()->query("SELECT guru_id, jam_belajar_id FROM sch_kesediaan")->fetchAll(PDO::FETCH_ASSOC);
            
            // Map Kesediaan (guru -> jam_ids)
            $kesediaanMap = [];
            foreach ($kesediaanRows as $kr) {
                $kesediaanMap[$kr['guru_id']][$kr['jam_belajar_id']] = true;
            }

            // Group jam by hari for block searching rules (only Pembelajaran slots)
            $jamByHari = [];
            $hariOrder = [];
            $jamLookup = [];
            foreach ($jamRows as $j) {
                $jamLookup[$j['id']] = $j;
                if ($j['tipe'] === 'Pembelajaran') {
                    $jamByHari[$j['hari']][] = $j;
                    if (!in_array($j['hari'], $hariOrder)) {
                        $hariOrder[] = $j['hari'];
                    }
                }
            }

            $kelasTotalJp = [];
            foreach ($distribusiRows as $d) {
                $kelasTotalJp[$d['kelas_id']] = ($kelasTotalJp[$d['kelas_id']] ?? 0) + (int)$d['jp'];
            }

            // Multi-Start Optimization Solver
            $bestResult = null;
            $maxIterations = 50;
            $startTime = microtime(true);
            $maxTimeSeconds = 35;

            for ($iter = 0; $iter < $maxIterations; $iter++) {
                if (microtime(true) - $startTime > $maxTimeSeconds && $bestResult !== null) break;

                $schedule = [];
                $guruBusy = [];
                $kelasDailyJp = [];
                $classSubjectHari = [];
                $scheduleData = [];
                $unplaced = [];
                $relaxedCount = 0;

                // Most Constrained First (guru dengan rasio beban JP / slot kesediaan tertinggi diproses lebih awal)
                $teacherLoad = [];
                foreach ($distribusiRows as $d) {
                    $g = $d['guru_id'];
                    $teacherLoad[$g] = ($teacherLoad[$g] ?? 0) + (int)$d['jp'];
                }

                $distList = $distribusiRows;
                usort($distList, function($a, $b) use ($teacherLoad, $kesediaanMap) {
                    $gA = $a['guru_id'];
                    $gB = $b['guru_id'];
                    $kesA = isset($kesediaanMap[$gA]) ? count($kesediaanMap[$gA]) : 999;
                    $kesB = isset($kesediaanMap[$gB]) ? count($kesediaanMap[$gB]) : 999;
                    
                    $ratioA = ($teacherLoad[$gA] / max(1, $kesA)) + (rand(0, 15) / 100);
                    $ratioB = ($teacherLoad[$gB] / max(1, $kesB)) + (rand(0, 15) / 100);

                    if (abs($ratioA - $ratioB) > 0.05) {
                        return $ratioB <=> $ratioA;
                    }
                    return (int)$b['jp'] <=> (int)$a['jp'];
                });

                foreach ($distList as $dist) {
                    $kId = $dist['kelas_id'];
                    $gId = $dist['guru_id'];
                    $dId = $dist['id'];
                    $mId = $dist['mapel_id'];
                    $jp = (int)$dist['jp'];

                    $totJpKelas = $kelasTotalJp[$kId] ?? 17;
                    $numDays = count($hariOrder);
                    $maxPerDay = max(3, (int)ceil($totJpKelas / $numDays) + 1);

                    $blocks = [];
                    if ($jp == 1) $blocks = [1];
                    elseif ($jp == 2) $blocks = [2];
                    elseif ($jp == 3) $blocks = [3];
                    elseif ($jp == 4) $blocks = [2, 2];
                    elseif ($jp == 5) $blocks = [3, 2];
                    else {
                        $rem = $jp;
                        while ($rem > 0) {
                            if ($rem >= 2) { $blocks[] = 2; $rem -= 2; }
                            else { $blocks[] = 1; $rem -= 1; }
                        }
                    }

                    $blocksQueue = $blocks;
                    while (!empty($blocksQueue)) {
                        $blockSize = array_shift($blocksQueue);
                        $placed = false;

                        $passes = [true];
                        if (!empty($kesediaanMap[$gId])) {
                            $passes = [true, false]; // Pass 1: Strict kesediaan; Pass 2: Fallback jika slot kesediaan guru < JP mengajar
                        }

                        foreach ($passes as $strictKesediaan) {
                            if ($placed) break;

                            // Urutkan hari: utamakan hari dengan JP terendah pada kelas ini agar tidak ada HARI KOSONG
                            $haris = $hariOrder;
                            shuffle($haris);
                            usort($haris, function($hA, $hB) use ($kId, $mId, $kelasDailyJp, $classSubjectHari) {
                                $currJpA = $kelasDailyJp[$kId][$hA] ?? 0;
                                $currJpB = $kelasDailyJp[$kId][$hB] ?? 0;
                                
                                $hasSubjA = !empty($classSubjectHari[$kId][$mId][$hA]) ? 10 : 0;
                                $hasSubjB = !empty($classSubjectHari[$kId][$mId][$hB]) ? 10 : 0;

                                return ($currJpA + $hasSubjA) <=> ($currJpB + $hasSubjB);
                            });

                            foreach ($haris as $hari) {
                                if ($placed) break;
                                $currJp = $kelasDailyJp[$kId][$hari] ?? 0;

                                if ($currJp + $blockSize > $maxPerDay && $strictKesediaan) {
                                    continue;
                                }

                                $hariJams = $jamByHari[$hari];

                                for ($i = 0; $i <= count($hariJams) - $blockSize; $i++) {
                                    $canPlace = true;
                                    $candidateSlots = [];

                                    for ($step = 0; $step < $blockSize; $step++) {
                                        $slot = $hariJams[$i + $step];

                                        if ($slot['tipe'] !== 'Pembelajaran') { $canPlace = false; break; }
                                        if (isset($schedule[$kId][$slot['id']])) { $canPlace = false; break; }
                                        if (isset($guruBusy[$gId][$slot['id']])) { $canPlace = false; break; }
                                        if ($strictKesediaan && !empty($kesediaanMap[$gId]) && !isset($kesediaanMap[$gId][$slot['id']])) {
                                            $canPlace = false;
                                            break;
                                        }

                                        $candidateSlots[] = $slot['id'];
                                    }

                                    if ($canPlace && count($candidateSlots) === $blockSize) {
                                        foreach ($candidateSlots as $cSlotId) {
                                            $schedule[$kId][$cSlotId] = $dId;
                                            $guruBusy[$gId][$cSlotId] = true;
                                            $scheduleData[] = [$kId, $cSlotId, $dId];
                                        }
                                        $kelasDailyJp[$kId][$hari] = ($kelasDailyJp[$kId][$hari] ?? 0) + $blockSize;
                                        $classSubjectHari[$kId][$mId][$hari] = ($classSubjectHari[$kId][$mId][$hari] ?? 0) + 1;
                                        if (!$strictKesediaan) $relaxedCount++;
                                        $placed = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if (!$placed) {
                            if ($blockSize > 1) {
                                if ($blockSize == 3) { $blocksQueue[] = 2; $blocksQueue[] = 1; }
                                elseif ($blockSize == 2) { $blocksQueue[] = 1; $blocksQueue[] = 1; }
                                else { $blocksQueue[] = $blockSize - 1; $blocksQueue[] = 1; }
                            } else {
                                $unplaced[] = $dist;
                            }
                        }
                    }
                }

                // Skor Evaluasi Kualitas Jadwal
                $penalty = 0;
                $penalty += count($unplaced) * 1000000;

                // Penalti keras jika ada hari yang kosong untuk kelas yang memiliki cukup JP
                $emptyDaysCount = 0;
                $numDays = count($hariOrder);
                foreach ($kelasTotalJp as $kId => $totJp) {
                    if ($totJp >= $numDays) {
                        foreach ($hariOrder as $h) {
                            $j = $kelasDailyJp[$kId][$h] ?? 0;
                            if ($j === 0) {
                                $emptyDaysCount++;
                                $penalty += 50000;
                            }
                        }
                    }
                }

                // Penalti ketidakmerataan harian
                foreach ($kelasTotalJp as $kId => $totJp) {
                    if ($totJp <= 0) continue;
                    $target = $totJp / $numDays;
                    foreach ($hariOrder as $h) {
                        $j = $kelasDailyJp[$kId][$h] ?? 0;
                        $penalty += (int)pow(($j - $target) * 10, 2);
                    }
                }

                $penalty += $relaxedCount * 50;

                $currentResult = [
                    'penalty' => $penalty,
                    'unplacedCount' => count($unplaced),
                    'emptyDaysCount' => $emptyDaysCount,
                    'relaxedCount' => $relaxedCount,
                    'scheduleData' => $scheduleData,
                    'unplaced' => $unplaced,
                    'kelasDailyJp' => $kelasDailyJp
                ];

                if ($bestResult === null || $currentResult['penalty'] < $bestResult['penalty']) {
                    $bestResult = $currentResult;
                    if ($bestResult['unplacedCount'] === 0 && $bestResult['emptyDaysCount'] === 0 && $bestResult['relaxedCount'] <= 6) {
                        if ($iter >= 25) break;
                    }
                }
            }

            // Simpan jadwal terbaik ke DB dalam transaksi
            db()->beginTransaction();
            $stmtInsert = db()->prepare("INSERT INTO sch_jadwal (kelas_id, jam_belajar_id, distribusi_id) VALUES (?, ?, ?)");
            foreach ($bestResult['scheduleData'] as $row) {
                $stmtInsert->execute($row);
            }
            db()->commit();
            
            if ($bestResult['unplacedCount'] > 0) {
                json_response(200, true, 'Jadwal di-generate sebagian, ada konflik / kekurangan slot jam.', [
                    'unplaced_blocks' => $bestResult['unplacedCount'],
                    'empty_days' => $bestResult['emptyDaysCount'],
                    'total_placed' => count($bestResult['scheduleData'])
                ]);
            } else {
                $msg = 'Jadwal berhasil di-generate secara utuh 100% (Semua hari terisi merata tanpa hari kosong).';
                if ($bestResult['relaxedCount'] > 0) {
                    $msg .= " Catatan: {$bestResult['relaxedCount']} jam disesuaikan otomatis ke slot aktif karena ketersediaan jam guru tertentu kurang dari total JP mengajar.";
                }
                json_response(200, true, $msg, [
                    'total_placed' => count($bestResult['scheduleData']),
                    'empty_days' => $bestResult['emptyDaysCount'],
                    'relaxed_kesediaan_slots' => $bestResult['relaxedCount']
                ]);
            }

        } catch (Exception $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            json_response(500, false, 'Gagal Generate: ' . $e->getMessage());
        }
        break;

    default:
        json_response(400, false, 'Invalid action');
}
