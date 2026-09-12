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

            // Mulai transaksi setelah ALTER TABLE karena ALTER TABLE menyebabkan implicit commit
            db()->beginTransaction();

            // 2. Load Environment
            $jamRows = db()->query("SELECT * FROM sch_jam_belajar ORDER BY CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END, jam_ke ASC")->fetchAll();
            $kelasRows = db()->query("SELECT id FROM sch_kelas")->fetchAll();
            $guruRows = db()->query("SELECT id FROM sch_guru")->fetchAll();
            
            $distribusiRows = db()->query("
                SELECT d.*, m.nama_mapel 
                FROM sch_distribusi d 
                JOIN sch_mapel m ON d.mapel_id = m.id 
                ORDER BY d.jp DESC
            ")->fetchAll();
            
            $kesediaanRows = db()->query("SELECT guru_id, jam_belajar_id FROM sch_kesediaan")->fetchAll();
            
            // Map Kesediaan (guru -> jam_ids)
            $kesediaanMap = [];
            foreach($kesediaanRows as $kr) {
                $kesediaanMap[$kr['guru_id']][$kr['jam_belajar_id']] = true;
            }

            // Prepare memory structures
            $schedule = []; // schedule[kelas_id][jam_id] = distribusi_id
            $guruBusy = []; // guruBusy[guru_id][jam_id] = true

            // Group jam by hari for block searching rules
            $jamByHari = [];
            $jamList = []; // flat ordered list
            foreach($jamRows as $j) {
                $jamByHari[$j['hari']][] = $j;
                $jamList[$j['id']] = $j;
            }

            // Placing blocks solver - Multi-Start Randomized Heuristic
            // We simulate multiple placements (up to 50 iterations or 45 seconds)
            // and keep the schedule with the fewest unplaced slots.
            
            $bestUnplacedCount = PHP_INT_MAX;
            $bestScheduleData = [];
            
            $maxIterations = 100; // Increased iterations for better results
            $startTime = microtime(true);
            $maxTimeSeconds = 45; // Max 45 seconds to prevent request timeout

            for ($iter = 0; $iter < $maxIterations; $iter++) {
                if (microtime(true) - $startTime > $maxTimeSeconds) break;

                $schedule = []; // Memory tracking for overlap
                $guruBusy = [];
                $currentUnplaced = [];
                $currentScheduleData = []; // Array of [kelas_id, jam_id, distribusi_id]

                // Shuffle distribusi but prefer larger JPs first
                $shuffledDistribusi = $distribusiRows;
                shuffle($shuffledDistribusi);
                usort($shuffledDistribusi, function($a, $b) {
                    // Small random factor so it's not identical every loop
                    $scoreA = (int)$a['jp'] + (rand(0, 20) / 100);
                    $scoreB = (int)$b['jp'] + (rand(0, 20) / 100);
                    return $scoreB <=> $scoreA;
                });

                foreach ($shuffledDistribusi as $dist) {
                    $kId = $dist['kelas_id'];
                    $gId = $dist['guru_id'];
                    $dId = $dist['id'];
                    $jp = (int)$dist['jp'];
                    
                    // Rule Breakdown
                    $blocks = [];
                    if ($jp == 1) $blocks = [1];
                    elseif ($jp == 2) $blocks = [2];
                    elseif ($jp == 3) $blocks = [3];
                    elseif ($jp == 4) $blocks = [2, 2];
                    elseif ($jp == 5) $blocks = [3, 2];
                    else {
                        $rem = $jp;
                        while($rem > 0) {
                            if ($rem >= 2) { $blocks[] = 2; $rem -= 2; }
                            else { $blocks[] = 1; $rem -= 1; }
                        }
                    }

                    $blocksQueue = $blocks;
                    while (!empty($blocksQueue)) {
                        $blockSize = array_shift($blocksQueue);
                        $placed = false;
                        
                        $haris = array_keys($jamByHari);
                        shuffle($haris); // Randomize day order

                        foreach ($haris as $hari) {
                            if ($placed) break;
                            $hariJams = $jamByHari[$hari];
                            
                            for ($i = 0; $i < count($hariJams); $i++) {
                                if ($hariJams[$i]['tipe'] !== 'Pembelajaran') continue;

                                $canPlace = true;
                                $candidateSlots = [];
                                $foundBlocks = 0;

                                for ($j = $i; $j < count($hariJams) && $foundBlocks < $blockSize; $j++) {
                                    $slot = $hariJams[$j];
                                    if ($slot['tipe'] !== 'Pembelajaran') continue;

                                    if (isset($schedule[$kId][$slot['id']])) { $canPlace = false; break; }
                                    if (isset($guruBusy[$gId][$slot['id']])) { $canPlace = false; break; }
                                    if (!empty($kesediaanMap[$gId]) && !isset($kesediaanMap[$gId][$slot['id']])) { $canPlace = false; break; }

                                    $candidateSlots[] = $slot['id'];
                                    $foundBlocks++;
                                }

                                if ($canPlace && $foundBlocks == $blockSize) {
                                    foreach ($candidateSlots as $cSlotId) {
                                        $schedule[$kId][$cSlotId] = $dId;
                                        $guruBusy[$gId][$cSlotId] = true;
                                        $currentScheduleData[] = [$kId, $cSlotId, $dId];
                                    }
                                    $placed = true;
                                    break;
                                }
                            }
                        }

                        if (!$placed) {
                            if ($blockSize > 1) {
                                if ($blockSize == 3) {
                                    $blocksQueue[] = 2; $blocksQueue[] = 1;
                                } else if ($blockSize == 2) {
                                    $blocksQueue[] = 1; $blocksQueue[] = 1;
                                } else {
                                    $blocksQueue[] = $blockSize - 1; $blocksQueue[] = 1;
                                }
                            } else {
                                $currentUnplaced[] = ['distribusi_id' => $dId, 'block' => $blockSize];
                            }
                        }
                    }
                }

                // Score this iteration
                $unplacedCount = count($currentUnplaced);
                if ($unplacedCount < $bestUnplacedCount) {
                    $bestUnplacedCount = $unplacedCount;
                    $bestScheduleData = $currentScheduleData;
                    
                    if ($bestUnplacedCount === 0) {
                        break; // Perfect schedule found!
                    }
                }
            }

            // Insert best schedule into DB
            $stmtInsert = db()->prepare("INSERT INTO sch_jadwal (kelas_id, jam_belajar_id, distribusi_id) VALUES (?, ?, ?)");
            foreach ($bestScheduleData as $row) {
                $stmtInsert->execute($row);
            }
            
            db()->commit();
            
            if ($bestUnplacedCount > 0) {
                json_response(200, true, 'Jadwal di-generate sebagian, ada konflik / kekurangan slot jam. (Heuristic limit tercapai)', [
                    'unplaced_blocks' => $bestUnplacedCount
                ]);
            } else {
                json_response(200, true, 'Jadwal berhasil di-generate secara utuh 100%.');
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
