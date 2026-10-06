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
                   g.nama_guru, g.kode_guru,
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

    case 'move_slot':
        $source_id = (int)($_POST['source_id'] ?? $_GET['source_id'] ?? 0);
        $target_jam_id = (int)($_POST['target_jam_id'] ?? $_GET['target_jam_id'] ?? 0);
        $target_kelas_id = (int)($_POST['target_kelas_id'] ?? $_GET['target_kelas_id'] ?? 0);

        if (!$source_id || !$target_jam_id || !$target_kelas_id) {
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
                // SWAP KEDUA JADWAL
                $targetId = (int)$targetSlot['id'];
                $oldSourceJam = (int)$source['jam_belajar_id'];
                $oldSourceKelas = (int)$source['kelas_id'];

                $stmt1 = db()->prepare("UPDATE sch_jadwal SET jam_belajar_id = ?, kelas_id = ? WHERE id = ?");
                $stmt1->execute([$target_jam_id, $target_kelas_id, $source_id]);

                $stmt2 = db()->prepare("UPDATE sch_jadwal SET jam_belajar_id = ?, kelas_id = ? WHERE id = ?");
                $stmt2->execute([$oldSourceJam, $oldSourceKelas, $targetId]);

                $msg = 'Jadwal berhasil ditukar posisinya.';
            } else {
                // MOVE KE SLOT KOSONG
                $stmt = db()->prepare("UPDATE sch_jadwal SET jam_belajar_id = ?, kelas_id = ? WHERE id = ?");
                $stmt->execute([$target_jam_id, $target_kelas_id, $source_id]);
                $msg = 'Jadwal berhasil dipindahkan ke slot baru.';
            }

            db()->exec("SET FOREIGN_KEY_CHECKS = 1;");
            db()->commit();

            // Cek tabrakan / bentrok guru secara global
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
            
            $distLookup = [];
            foreach ($distribusiRows as $d) {
                $distLookup[$d['id']] = $d;
            }

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
            $maxIterations = 150;
            $startTime = microtime(true);
            $maxTimeSeconds = 55;

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
                                $nSlots = count($hariJams);

                                // Kumpulkan slot yang sudah terisi di hari ini untuk kelas ini
                                $occupiedIndices = [];
                                for ($idx = 0; $idx < $nSlots; $idx++) {
                                    if (isset($schedule[$kId][$hariJams[$idx]['id']])) {
                                        $occupiedIndices[] = $idx;
                                    }
                                }

                                // HEURISTIK ANTI JAM KOSONG:
                                // Prioritaskan slot yang menempel langsung dengan jam yang sudah terisi
                                // atau mulai dari jam ke-1 jika belum ada jam sama sekali di hari ini
                                $candidatePositions = [];
                                for ($i = 0; $i <= $nSlots - $blockSize; $i++) {
                                    $gapScore = 0;
                                    if (empty($occupiedIndices)) {
                                        // Belum ada jam di hari ini: wajib mulai dari slot paling awal (slot 0)
                                        $gapScore = $i * 50;
                                    } else {
                                        $minOcc = min($occupiedIndices);
                                        $maxOcc = max($occupiedIndices);

                                        if ($i == $maxOcc + 1) {
                                            // Menempel langsung setelah pelajaran yang sudah ada (Terbaik!)
                                            $gapScore = 0;
                                        } elseif ($i + $blockSize == $minOcc) {
                                            // Menempel langsung sebelum pelajaran yang sudah ada
                                            $gapScore = 5;
                                        } elseif ($i < $minOcc) {
                                            // Terletak sebelum tapi berjarak/bolong
                                            $gapScore = ($minOcc - ($i + $blockSize)) * 5000 + 1000;
                                        } elseif ($i > $maxOcc) {
                                            // Terletak setelah tapi berjarak/bolong (Jam kosong di tengah!)
                                            $gapScore = ($i - ($maxOcc + 1)) * 5000 + 1000;
                                        } else {
                                            $gapScore = 3000;
                                        }
                                    }
                                    $candidatePositions[] = ['i' => $i, 'score' => $gapScore];
                                }

                                usort($candidatePositions, function($a, $b) {
                                    return $a['score'] <=> $b['score'];
                                });

                                foreach ($candidatePositions as $cPos) {
                                    $i = $cPos['i'];
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

                // POST-PROCESSING COMPACTION SWEEP:
                // Geser maju jam-jam yang tersisa jika ada slot kosong di depannya (Menghapus jam kosong 100%)
                for ($compactSweep = 0; $compactSweep < 5; $compactSweep++) {
                    $shifted = false;
                    foreach ($kelasRows as $kr) {
                        $kId = $kr['id'];
                        foreach ($hariOrder as $hari) {
                            $hJams = $jamByHari[$hari] ?? [];
                            $nJ = count($hJams);
                            if ($nJ < 2) continue;

                            for ($targetIdx = 0; $targetIdx < $nJ; $targetIdx++) {
                                $targetSlotId = $hJams[$targetIdx]['id'];
                                if (isset($schedule[$kId][$targetSlotId])) continue; // terisi

                                // targetIdx kosong! Cari jam pembelajaran berikutnya pada kelas ini untuk digeser maju
                                for ($sourceIdx = $targetIdx + 1; $sourceIdx < $nJ; $sourceIdx++) {
                                    $sourceSlotId = $hJams[$sourceIdx]['id'];
                                    if (!isset($schedule[$kId][$sourceSlotId])) continue;

                                    $dId = $schedule[$kId][$sourceSlotId];
                                    $dist = $distLookup[$dId];
                                    $gId = $dist['guru_id'];

                                    // Pastikan guru tidak bentrok di targetSlotId
                                    if (!isset($guruBusy[$gId][$targetSlotId])) {
                                        if (empty($kesediaanMap[$gId]) || isset($kesediaanMap[$gId][$targetSlotId])) {
                                            // Geser maju ke targetSlotId
                                            unset($schedule[$kId][$sourceSlotId]);
                                            unset($guruBusy[$gId][$sourceSlotId]);
                                            $schedule[$kId][$targetSlotId] = $dId;
                                            $guruBusy[$gId][$targetSlotId] = true;

                                            // Update record scheduleData
                                            for ($sIdx = 0; $sIdx < count($scheduleData); $sIdx++) {
                                                if ($scheduleData[$sIdx][0] == $kId && $scheduleData[$sIdx][1] == $sourceSlotId && $scheduleData[$sIdx][2] == $dId) {
                                                    $scheduleData[$sIdx][1] = $targetSlotId;
                                                    break;
                                                }
                                            }
                                            $shifted = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if (!$shifted) break;
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

                // Penalti ketat untuk jam kosong (gap) dalam satu kelas di hari yang sama
                $totalGaps = 0;
                foreach ($kelasRows as $kr) {
                    $kId = $kr['id'];
                    foreach ($hariOrder as $hari) {
                        $hJams = $jamByHari[$hari] ?? [];
                        $occupied = [];
                        foreach ($hJams as $idx => $slot) {
                            if (isset($schedule[$kId][$slot['id']])) {
                                $occupied[] = $idx;
                            }
                        }
                        if (!empty($occupied)) {
                            $minIdx = min($occupied);
                            $maxIdx = max($occupied);
                            $gap = ($maxIdx - $minIdx + 1) - count($occupied);
                            if ($gap > 0) {
                                $penalty += $gap * 300000; // Penalti sangat tinggi agar jam kosong 0
                                $totalGaps += $gap;
                            }
                            // Penalti jika jam pembelajaran mulai siang padahal pagi kosong (leading gap)
                            if ($minIdx > 0) {
                                $penalty += $minIdx * 40000;
                                $totalGaps += $minIdx;
                            }
                        }
                    }
                }

                $penalty += $relaxedCount * 50;

                $currentResult = [
                    'penalty' => $penalty,
                    'unplacedCount' => count($unplaced),
                    'emptyDaysCount' => $emptyDaysCount,
                    'relaxedCount' => $relaxedCount,
                    'totalGaps' => $totalGaps,
                    'scheduleData' => $scheduleData,
                    'unplaced' => $unplaced,
                    'kelasDailyJp' => $kelasDailyJp
                ];

                if ($bestResult === null || $currentResult['penalty'] < $bestResult['penalty']) {
                    $bestResult = $currentResult;
                    if ($bestResult['unplacedCount'] === 0 && $bestResult['emptyDaysCount'] === 0 && $bestResult['totalGaps'] === 0 && $bestResult['relaxedCount'] <= 4) {
                        if ($iter >= 20) break;
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
                    'total_gaps' => $bestResult['totalGaps'],
                    'total_placed' => count($bestResult['scheduleData'])
                ]);
            } else {
                $msg = 'Jadwal berhasil di-generate secara utuh 100% tanpa jam kosong dan tanpa hari kosong.';
                if ($bestResult['relaxedCount'] > 0) {
                    $msg .= " Catatan: {$bestResult['relaxedCount']} jam disesuaikan otomatis ke slot aktif karena ketersediaan jam guru tertentu kurang dari total JP mengajar.";
                }
                json_response(200, true, $msg, [
                    'total_placed' => count($bestResult['scheduleData']),
                    'empty_days' => $bestResult['emptyDaysCount'],
                    'total_gaps' => $bestResult['totalGaps'],
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
