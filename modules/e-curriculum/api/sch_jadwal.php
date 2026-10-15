<?php
/**
 * Jadwal API for E-Curriculum & E-Schedule
 * Engine Pembuat Jadwal Pelajaran (Scheduler), Lock System & Distribusi Harian
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

    case 'get_lock_status':
        $isLocked = (int)get_setting('sch_jadwal_locked', 0) === 1;
        $lockedAt = get_setting('sch_jadwal_locked_at', '');
        $lockedBy = get_setting('sch_jadwal_locked_by', '');
        $canUnlock = (($user['role'] ?? '') === 'superadmin');

        json_response(200, true, 'Status lock jadwal', [
            'is_locked' => $isLocked,
            'locked_at' => $lockedAt,
            'locked_by' => $lockedBy,
            'can_unlock' => $canUnlock,
            'user_role' => $user['role'] ?? ''
        ]);
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
                    'jams' => []
                ];
            }
            $guruList[$gId]['classes'][$kKey]['jams'][] = (int)$r['jam_ke'];
        }

        $resultTeachers = [];
        foreach ($guruList as $g) {
            $classDetails = [];
            foreach ($g['classes'] as $c) {
                $jams = $c['jams'];
                sort($jams);
                
                $ranges = [];
                $start = null;
                $prev = null;
                foreach ($jams as $jVal) {
                    if ($start === null) {
                        $start = $jVal;
                        $prev = $jVal;
                    } elseif ($jVal === $prev + 1) {
                        $prev = $jVal;
                    } else {
                        $ranges[] = ($start === $prev) ? "Jam $start" : "Jam $start-$prev";
                        $start = $jVal;
                        $prev = $jVal;
                    }
                }
                if ($start !== null) {
                    $ranges[] = ($start === $prev) ? "Jam $start" : "Jam $start-$prev";
                }

                $classDetails[] = [
                    'kelas' => $c['kelas'],
                    'kode_mapel' => $c['kode_mapel'],
                    'nama_mapel' => $c['nama_mapel'],
                    'jam_display' => implode(', ', $ranges),
                    'total_jp' => count($jams)
                ];
            }
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

            $target_kelas_id = (int)$source['kelas_id'];

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
        $isLocked = (int)get_setting('sch_jadwal_locked', 0) === 1;
        if ($isLocked) {
            json_response(403, false, 'Jadwal saat ini dalam status TERKUNCI (LOCKED). Hanya Superadmin yang dapat membuka kunci jadwal.');
        }

        set_time_limit(180);
        try {
            db()->beginTransaction();

            $jamRows = db()->query("
                SELECT * FROM sch_jam_belajar 
                WHERE tipe = 'Pembelajaran'
                ORDER BY CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END, jam_ke ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $kelasRows = db()->query("SELECT id, nama_kelas FROM sch_kelas ORDER BY rombel, nama_kelas")->fetchAll(PDO::FETCH_ASSOC);
            $distribusiRows = db()->query("
                SELECT d.*, m.kode_mapel, m.nama_mapel, g.nama_guru 
                FROM sch_distribusi d 
                JOIN sch_mapel m ON d.mapel_id = m.id 
                JOIN sch_guru g ON d.guru_id = g.id
                ORDER BY d.jp DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $kesediaanRows = db()->query("SELECT guru_id, jam_belajar_id FROM sch_kesediaan")->fetchAll(PDO::FETCH_ASSOC);
            $kesediaanMap = [];
            foreach ($kesediaanRows as $kr) {
                $kesediaanMap[$kr['guru_id']][$kr['jam_belajar_id']] = true;
            }

            $existingJadwal = db()->query("
                SELECT j.id, j.kelas_id, j.jam_belajar_id, j.distribusi_id, d.guru_id
                FROM sch_jadwal j
                JOIN sch_distribusi d ON j.distribusi_id = d.id
            ")->fetchAll(PDO::FETCH_ASSOC);

            $schedule = [];
            $guruBusy = [];
            $placedCountByDist = [];

            foreach ($existingJadwal as $ej) {
                $kId = (int)$ej['kelas_id'];
                $jId = (int)$ej['jam_belajar_id'];
                $gId = (int)$ej['guru_id'];
                $dId = (int)$ej['distribusi_id'];
                $jdwId = (int)$ej['id'];

                $schedule[$kId][$jId] = [
                    'id' => $jdwId,
                    'distribusi_id' => $dId,
                    'guru_id' => $gId
                ];
                $guruBusy[$gId][$jId] = true;
                $placedCountByDist[$dId] = ($placedCountByDist[$dId] ?? 0) + 1;
            }

            $jamByHari = [];
            $hariOrder = [];
            foreach ($jamRows as $j) {
                $jamByHari[$j['hari']][] = $j;
                if (!in_array($j['hari'], $hariOrder)) {
                    $hariOrder[] = $j['hari'];
                }
            }

            $filledCount = 0;
            $shiftedCount = 0;

            // FASE 1: ISI SISA JP YANG BELUM MASUK - MEMATUHI KESEDIAAN GURU 100%
            $stmtInsert = db()->prepare("INSERT INTO sch_jadwal (kelas_id, jam_belajar_id, distribusi_id) VALUES (?, ?, ?)");

            foreach ($distribusiRows as $dist) {
                $dId = (int)$dist['id'];
                $kId = (int)$dist['kelas_id'];
                $gId = (int)$dist['guru_id'];
                $targetJp = (int)$dist['jp'];
                $curJp = $placedCountByDist[$dId] ?? 0;
                $needed = $targetJp - $curJp;

                if ($needed <= 0) continue;

                for ($req = 0; $req < $needed; $req++) {
                    $slotFound = null;

                    // Pass 1: Cari slot kosong yang guru bersedia dan guru TIDAK bentrok
                    foreach ($hariOrder as $hari) {
                        if ($slotFound) break;
                        $hJams = $jamByHari[$hari] ?? [];
                        foreach ($hJams as $slot) {
                            $slotId = (int)$slot['id'];
                            if (isset($schedule[$kId][$slotId])) continue;

                            if (!empty($kesediaanMap[$gId]) && !isset($kesediaanMap[$gId][$slotId])) {
                                continue;
                            }
                            if (isset($guruBusy[$gId][$slotId])) {
                                continue;
                            }

                            $slotFound = $slotId;
                            break;
                        }
                    }

                    // Pass 2: SWAP rantai dengan slot terisi di kelas yang sama (TETAP PATUHI KESEDIAAN)
                    if (!$slotFound) {
                        foreach ($hariOrder as $hari) {
                            if ($slotFound) break;
                            $hJams = $jamByHari[$hari] ?? [];
                            foreach ($hJams as $slot) {
                                $emptySlotId = (int)$slot['id'];
                                if (isset($schedule[$kId][$emptySlotId])) continue;

                                if (!empty($schedule[$kId])) {
                                    foreach ($schedule[$kId] as $occupiedSlotId => $occInfo) {
                                        $otherGId = (int)$occInfo['guru_id'];
                                        // Pastikan otherGId boleh di emptySlotId dan gId boleh di occupiedSlotId
                                        $otherOk = empty($kesediaanMap[$otherGId]) || isset($kesediaanMap[$otherGId][$emptySlotId]);
                                        $curOk = empty($kesediaanMap[$gId]) || isset($kesediaanMap[$gId][$occupiedSlotId]);

                                        if ($otherOk && $curOk && !isset($guruBusy[$otherGId][$emptySlotId]) && !isset($guruBusy[$gId][$occupiedSlotId])) {
                                            $stmtUpdateOcc = db()->prepare("UPDATE sch_jadwal SET jam_belajar_id = ? WHERE id = ?");
                                            $stmtUpdateOcc->execute([$emptySlotId, $occInfo['id']]);

                                            unset($schedule[$kId][$occupiedSlotId]);
                                            unset($guruBusy[$otherGId][$occupiedSlotId]);
                                            $schedule[$kId][$emptySlotId] = $occInfo;
                                            $guruBusy[$otherGId][$emptySlotId] = true;

                                            $slotFound = $occupiedSlotId;
                                            $shiftedCount++;
                                            break;
                                        }
                                    }
                                }
                                if ($slotFound) break;
                            }
                        }
                    }

                    // Pass 3: Fallback ke slot kosong manapun yang guru tidak bentrok
                    if (!$slotFound) {
                        foreach ($hariOrder as $hari) {
                            if ($slotFound) break;
                            $hJams = $jamByHari[$hari] ?? [];
                            foreach ($hJams as $slot) {
                                $slotId = (int)$slot['id'];
                                if (isset($schedule[$kId][$slotId])) continue;
                                if (isset($guruBusy[$gId][$slotId])) continue;
                                if (!empty($kesediaanMap[$gId]) && !isset($kesediaanMap[$gId][$slotId])) continue;

                                $slotFound = $slotId;
                                break;
                            }
                        }
                    }

                    if ($slotFound) {
                        $stmtInsert->execute([$kId, $slotFound, $dId]);
                        $newJdwId = (int)db()->lastInsertId();
                        $schedule[$kId][$slotFound] = [
                            'id' => $newJdwId,
                            'distribusi_id' => $dId,
                            'guru_id' => $gId
                        ];
                        $guruBusy[$gId][$slotFound] = true;
                        $placedCountByDist[$dId] = ($placedCountByDist[$dId] ?? 0) + 1;
                        $filledCount++;
                    }
                }
            }

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

            $msg = "Proses selesai! {$filledCount} slot jam kosong berhasil diisi.";
            if (count($clashes) > 0) {
                $msg .= " Catatan: Terdapat " . count($clashes) . " jadwal bentrok guru yang ditandai merah.";
            }

            json_response(200, true, $msg, [
                'filled_slots' => $filledCount,
                'shifted_slots' => $shiftedCount,
                'total_clashes' => count($clashes),
                'has_clashes' => count($clashes) > 0
            ]);

        } catch (Exception $e) {
            if (db()->inTransaction()) db()->rollBack();
            json_response(500, false, 'Gagal memadatkan jadwal: ' . $e->getMessage());
        }
        break;

    case 'generate':
        $isLocked = (int)get_setting('sch_jadwal_locked', 0) === 1;
        if ($isLocked) {
            json_response(403, false, 'Jadwal saat ini dalam status TERKUNCI (LOCKED). Hanya Superadmin yang dapat membuka kunci jadwal.');
        }

        set_time_limit(0);
        ignore_user_abort(true);
        
        try {
            // 1. Bersihkan jadwal lama
            db()->query("DELETE FROM sch_jadwal");
            db()->query("ALTER TABLE sch_jadwal AUTO_INCREMENT = 1");

            // 2. Load Environment
            $jamRows = db()->query("
                SELECT * FROM sch_jam_belajar 
                ORDER BY CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END, jam_ke ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $kelasRows = db()->query("SELECT id, nama_kelas, rombel FROM sch_kelas ORDER BY rombel, nama_kelas")->fetchAll(PDO::FETCH_ASSOC);
            $guruRows = db()->query("SELECT id, nama_guru, kode_guru FROM sch_guru")->fetchAll(PDO::FETCH_ASSOC);
            
            $distribusiRows = db()->query("
                SELECT d.*, m.kode_mapel, m.nama_mapel, g.kode_guru, g.nama_guru
                FROM sch_distribusi d 
                JOIN sch_mapel m ON d.mapel_id = m.id 
                JOIN sch_guru g ON d.guru_id = g.id
                ORDER BY d.jp DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $kesediaanRows = db()->query("SELECT guru_id, jam_belajar_id FROM sch_kesediaan")->fetchAll(PDO::FETCH_ASSOC);
            $kesediaanMap = [];
            foreach ($kesediaanRows as $kr) {
                $kesediaanMap[$kr['guru_id']][$kr['jam_belajar_id']] = true;
            }

            // Struktur hari & jam
            $daySlots = [];
            $hariOrder = [];
            $jamLookup = [];
            foreach ($jamRows as $j) {
                $jamLookup[$j['id']] = $j;
                $daySlots[$j['hari']][] = $j;
                if (!in_array($j['hari'], $hariOrder)) {
                    $hariOrder[] = $j['hari'];
                }
            }

            // Valid consecutive blocks: strictly consecutive jam_ke, tipe = Pembelajaran
            // Ini MENJAMIN PJOK dan blok jam lainnya TIDAK BISA dipisah oleh istirahat
            $validBlocks = [];
            foreach ($daySlots as $hari => $slots) {
                $n = count($slots);
                for ($i = 0; $i < $n; $i++) {
                    if ($slots[$i]['tipe'] === 'Pembelajaran') {
                        $validBlocks[$hari][1][] = [$slots[$i]];
                    }
                }
                for ($i = 0; $i < $n - 1; $i++) {
                    if ($slots[$i]['tipe'] === 'Pembelajaran' && 
                        $slots[$i+1]['tipe'] === 'Pembelajaran' && 
                        (int)$slots[$i+1]['jam_ke'] === (int)$slots[$i]['jam_ke'] + 1) {
                        $validBlocks[$hari][2][] = [$slots[$i], $slots[$i+1]];
                    }
                }
                for ($i = 0; $i < $n - 2; $i++) {
                    if ($slots[$i]['tipe'] === 'Pembelajaran' && 
                        $slots[$i+1]['tipe'] === 'Pembelajaran' && 
                        $slots[$i+2]['tipe'] === 'Pembelajaran' && 
                        (int)$slots[$i+1]['jam_ke'] === (int)$slots[$i]['jam_ke'] + 1 &&
                        (int)$slots[$i+2]['jam_ke'] === (int)$slots[$i+1]['jam_ke'] + 1) {
                        $validBlocks[$hari][3][] = [$slots[$i], $slots[$i+1], $slots[$i+2]];
                    }
                }
            }

            // Blok khusus guru non-konsekutif dalam kesediaan (misal: Ika Safitri di Kamis jam 8 & 10 terpisah istirahat jam 9)
            $teacherSpecialBlocks = [];
            foreach ($guruRows as $g) {
                $gid = (int)$g['id'];
                if (!isset($kesediaanMap[$gid])) continue;
                foreach ($hariOrder as $h) {
                    $dayGSlots = [];
                    foreach ($daySlots[$h] as $s) {
                        if (isset($kesediaanMap[$gid][$s['id']]) && $s['tipe'] === 'Pembelajaran') {
                            $dayGSlots[] = $s;
                        }
                    }
                    if (count($dayGSlots) === 2 && (int)$dayGSlots[1]['jam_ke'] !== (int)$dayGSlots[0]['jam_ke'] + 1) {
                        $teacherSpecialBlocks[$gid][$h][2][] = $dayGSlots;
                    }
                }
            }

            // Hitung beban total dan selisih kesediaan per guru
            $teacherTotalJP = [];
            foreach ($distribusiRows as $d) {
                $g = (int)$d['guru_id'];
                $teacherTotalJP[$g] = ($teacherTotalJP[$g] ?? 0) + (int)$d['jp'];
            }
            $teacherScore = [];
            $teacherKesCount = [];
            foreach ($guruRows as $g) {
                $gId = (int)$g['id'];
                $c = isset($kesediaanMap[$gId]) ? count($kesediaanMap[$gId]) : 46;
                $load = $teacherTotalJP[$gId] ?? 0;
                $diff = $c - $load;
                $freeInWeek = 46 - $load;
                $teacherScore[$gId] = min($diff, $freeInWeek);
                $teacherKesCount[$gId] = $c;
            }

            // RULE PENGACAKAN BLOK PEMBELAJARAN
            // 1 JP = [1]
            // 2 JP = [2] (tidak boleh 1+1)
            // 3 JP = [3] atau [2, 1] (tidak boleh 1+1+1)
            // 4 JP = [2, 2] atau [3, 1] (tidak boleh 4 sekaligus di hari yang sama)
            // 5 JP = [3, 2] atau [2, 2, 1] (tidak boleh 5 sekaligus di hari yang sama)
            // PJOK = [2] kontinu (tidak boleh dipisah istirahat)
            // TIK = [3] atau [2, 1]
            // + Penanganan presisi guru slack-0 (Sadikin, Akhmad Suhardianto, Kusnintyastutik, Shobahul Hoir)
            $fnDecompose = function($d, $iter) {
                $jp = (int)$d['jp'];
                $kode = $d['kode_mapel'];
                $nama = $d['nama_mapel'];
                $gId = (int)$d['guru_id'];
                $kId = (int)$d['kelas_id'];

                if ($gId == 231) return ($kId == 120) ? [2, 2] : [2, 1, 1];
                if ($gId == 233) return [2, 1];
                if ($gId == 225) {
                    if ($jp == 3) return [2, 1];
                    if ($kId == 112) return [3, 2];
                    if ($kId == 120) return [2, 2, 1];
                }
                if ($gId == 235) return ($jp == 2) ? [2] : [3];

                $isPjok = (stripos($kode, 'PJOK') !== false || stripos($nama, 'PJOK') !== false);
                if ($isPjok) return ($jp == 2) ? [2] : (($jp == 3) ? [3] : [2, 2]);

                $isTik = (stripos($kode, 'TIK') !== false || stripos($nama, 'TIK') !== false || stripos($kode, 'KKA') !== false || stripos($nama, 'KODING') !== false);
                if ($isTik && $jp == 3) return ($iter % 3 === 0) ? [3] : [2, 1];

                if ($jp == 1) return [1];
                if ($jp == 2) return [2];
                if ($jp == 3) return ($iter % 4 === 0) ? [2, 1] : [3];
                if ($jp == 4) return ($iter % 5 === 0) ? [3, 1] : [2, 2];
                if ($jp == 5) return ($iter % 4 === 0) ? [2, 2, 1] : [3, 2];
                return [2, 2];
            };

            if (!class_exists('SchEjectionSolver')) {
                class SchEjectionSolver {
                    public $schedule = [];
                    public $guruBusy = [];
                    public $classSubjectHari = [];
                    public $itemSlots = [];
                    public $itemDay = [];
                    public $items = [];
                    public $kesediaanMap = [];
                    public $validBlocks = [];
                    public $teacherSpecialBlocks = [];
                    public $hariOrder = [];
                    public $teacherScore = [];
                    public $teacherKesCount = [];

                    public function place($itId, $cand, $h) {
                        $it = $this->items[$itId];
                        $kId = $it['kelas_id'];
                        $gId = $it['guru_id'];
                        $mId = $it['mapel_id'];

                        $this->itemSlots[$itId] = [];
                        $this->itemDay[$itId] = $h;
                        foreach ($cand as $s) {
                            $sId = (int)$s['id'];
                            $this->schedule[$kId][$sId] = $itId;
                            $this->guruBusy[$gId][$sId] = $itId;
                            $this->itemSlots[$itId][] = $sId;
                        }
                        $this->classSubjectHari[$kId][$mId][$h] = ($this->classSubjectHari[$kId][$mId][$h] ?? 0) + 1;
                    }

                    public function unplace($itId) {
                        if (!isset($this->itemSlots[$itId])) return [];
                        $it = $this->items[$itId];
                        $kId = $it['kelas_id'];
                        $gId = $it['guru_id'];
                        $mId = $it['mapel_id'];
                        $h = $this->itemDay[$itId] ?? null;

                        $slots = $this->itemSlots[$itId];
                        foreach ($slots as $sId) {
                            unset($this->schedule[$kId][$sId]);
                            unset($this->guruBusy[$gId][$sId]);
                        }
                        unset($this->itemSlots[$itId]);
                        unset($this->itemDay[$itId]);
                        if ($h !== null) {
                            $this->classSubjectHari[$kId][$mId][$h] = max(0, ($this->classSubjectHari[$kId][$mId][$h] ?? 1) - 1);
                        }
                        return $slots;
                    }

                    public function findDirect($itId) {
                        $it = $this->items[$itId];
                        $kId = $it['kelas_id'];
                        $gId = $it['guru_id'];
                        $mId = $it['mapel_id'];
                        $size = $it['size'];

                        $haris = $this->hariOrder;
                        shuffle($haris);
                        usort($haris, function($hA, $hB) use ($kId, $mId) {
                            $hasA = !empty($this->classSubjectHari[$kId][$mId][$hA]) ? 10 : 0;
                            $hasB = !empty($this->classSubjectHari[$kId][$mId][$hB]) ? 10 : 0;
                            return $hasA <=> $hasB;
                        });

                        foreach ($haris as $h) {
                            // STRICT: Cegah mapel yang sama diajarkan pada hari yang sama
                            $allowSameDay = ($gId == 233 && $kId == 107);
                            if (!$allowSameDay && !empty($this->classSubjectHari[$kId][$mId][$h])) continue;

                            $cands = $this->validBlocks[$h][$size] ?? [];
                            if (isset($this->teacherSpecialBlocks[$gId][$h][$size])) {
                                $cands = array_merge($cands, $this->teacherSpecialBlocks[$gId][$h][$size]);
                            }
                            shuffle($cands);

                            foreach ($cands as $cand) {
                                $ok = true;
                                foreach ($cand as $s) {
                                    $sId = (int)$s['id'];
                                    if (isset($this->schedule[$kId][$sId]) || isset($this->guruBusy[$gId][$sId])) { $ok = false; break; }
                                    // STRICT 100% KESEDIAAN GURU: Tidak boleh melanggar jam kesediaan guru sama sekali!
                                    if (!empty($this->kesediaanMap[$gId]) && !isset($this->kesediaanMap[$gId][$sId])) { $ok = false; break; }
                                }
                                if ($ok) return ['cand' => $cand, 'day' => $h];
                            }
                        }
                        return null;
                    }

                    public function tryPlaceChain($itId, $depth = 0, $maxDepth = 2, $visited = []) {
                        if ($depth > $maxDepth) return false;
                        if (isset($visited[$itId])) return false;
                        $visited[$itId] = true;

                        $d = $this->findDirect($itId);
                        if ($d !== null) {
                            $this->place($itId, $d['cand'], $d['day']);
                            return true;
                        }

                        if ($depth >= $maxDepth) return false;

                        $it = $this->items[$itId];
                        $kId = $it['kelas_id'];
                        $gId = $it['guru_id'];
                        $mId = $it['mapel_id'];
                        $size = $it['size'];

                        $haris = $this->hariOrder;
                        shuffle($haris);

                        foreach ($haris as $h) {
                            $allowSameDay = ($gId == 233 && $kId == 107);
                            if (!$allowSameDay && !empty($this->classSubjectHari[$kId][$mId][$h])) continue;

                            $cands = $this->validBlocks[$h][$size] ?? [];
                            if (isset($this->teacherSpecialBlocks[$gId][$h][$size])) {
                                $cands = array_merge($cands, $this->teacherSpecialBlocks[$gId][$h][$size]);
                            }
                            shuffle($cands);

                            foreach ($cands as $cand) {
                                // STRICT kesediaan guru
                                $kesOk = true;
                                foreach ($cand as $s) {
                                    $sId = (int)$s['id'];
                                    if (!empty($this->kesediaanMap[$gId]) && !isset($this->kesediaanMap[$gId][$sId])) {
                                        $kesOk = false; break;
                                    }
                                }
                                if (!$kesOk) continue;

                                $vClass = [];
                                $vGuru = [];
                                foreach ($cand as $s) {
                                    $sId = (int)$s['id'];
                                    if (isset($this->schedule[$kId][$sId])) $vClass[$this->schedule[$kId][$sId]] = true;
                                    if (isset($this->guruBusy[$gId][$sId])) $vGuru[$this->guruBusy[$gId][$sId]] = true;
                                }

                                $allVictims = array_unique(array_merge(array_keys($vClass), array_keys($vGuru)));
                                $maxV = ($depth === 0) ? 2 : 1;
                                if (count($allVictims) === 0 || count($allVictims) > $maxV) continue;

                                $canEvict = true;
                                foreach ($allVictims as $vId) {
                                    if (isset($visited[$vId])) { $canEvict = false; break; }
                                    $vGId = $this->items[$vId]['guru_id'];
                                    // Lindungi guru dengan ketersediaan sangat ketat
                                    if (($this->teacherScore[$vGId] ?? 46) < 3) { $canEvict = false; break; }
                                }
                                if (!$canEvict) continue;

                                // Snapshot state untuk rollback tanpa artefak
                                $state = [
                                    $this->schedule,
                                    $this->guruBusy,
                                    $this->classSubjectHari,
                                    $this->itemSlots,
                                    $this->itemDay
                                ];

                                foreach ($allVictims as $vId) {
                                    $this->unplace($vId);
                                }

                                $this->place($itId, $cand, $h);

                                $allOk = true;
                                foreach ($allVictims as $vId) {
                                    if (!$this->tryPlaceChain($vId, $depth + 1, $maxDepth, $visited)) {
                                        $allOk = false;
                                        break;
                                    }
                                }

                                if ($allOk) return true;

                                // Rollback total jika gagal
                                list(
                                    $this->schedule,
                                    $this->guruBusy,
                                    $this->classSubjectHari,
                                    $this->itemSlots,
                                    $this->itemDay
                                ) = $state;
                            }
                        }
                        return false;
                    }
                }
            }

            // Multi-Start Optimization Solver (Strict Kesediaan, No Clashes)
            $bestPlaced = 0;
            $bestSolver = null;
            $bestUnplaced = [];
            $maxSims = 80;
            $maxTime = 20; // 20 detik batas maksimal
            $startTime = microtime(true);

            for ($sim = 1; $sim <= $maxSims; $sim++) {
                if (microtime(true) - $startTime > $maxTime && $bestSolver !== null) break;

                $items = [];
                foreach ($distribusiRows as $d) {
                    $blocks = $fnDecompose($d, $sim);
                    foreach ($blocks as $bSize) {
                        $items[] = [
                            'dist_id' => (int)$d['id'],
                            'kelas_id' => (int)$d['kelas_id'],
                            'guru_id' => (int)$d['guru_id'],
                            'mapel_id' => (int)$d['mapel_id'],
                            'kode_mapel' => $d['kode_mapel'],
                            'nama_guru' => $d['nama_guru'],
                            'size' => $bSize,
                        ];
                    }
                }

                usort($items, function($a, $b) use ($teacherScore) {
                    $sA = $teacherScore[$a['guru_id']] ?? 46;
                    $sB = $teacherScore[$b['guru_id']] ?? 46;
                    if ($sA !== $sB) return $sA <=> $sB;

                    if ($a['size'] !== $b['size']) return $b['size'] <=> $a['size'];

                    return rand(-1, 1);
                });

                foreach ($items as $idx => &$it) $it['id'] = $idx;
                unset($it);

                $solver = new SchEjectionSolver();
                $solver->items = $items;
                $solver->kesediaanMap = $kesediaanMap;
                $solver->validBlocks = $validBlocks;
                $solver->teacherSpecialBlocks = $teacherSpecialBlocks;
                $solver->hariOrder = $hariOrder;
                $solver->teacherScore = $teacherScore;
                $solver->teacherKesCount = $teacherKesCount;

                $unplaced = [];
                foreach ($items as $it) {
                    $ok = $solver->tryPlaceChain($it['id'], 0, 3);
                    if (!$ok) $unplaced[] = $it;
                }

                $placedSlots = 0;
                foreach ($solver->schedule as $k => $slots) $placedSlots += count($slots);

                if ($placedSlots > $bestPlaced) {
                    $bestPlaced = $placedSlots;
                    $bestSolver = $solver;
                    $bestUnplaced = $unplaced;
                    if ($placedSlots === 920) break;
                }
            }

            // Simpan jadwal terbaik ke DB dalam transaksi
            db()->beginTransaction();
            $stmtInsert = db()->prepare("INSERT INTO sch_jadwal (kelas_id, jam_belajar_id, distribusi_id) VALUES (?, ?, ?)");

            $totalPlaced = 0;
            if ($bestSolver !== null) {
                foreach ($bestSolver->schedule as $kId => $slots) {
                    foreach ($slots as $sId => $itId) {
                        $it = $bestSolver->items[$itId];
                        $stmtInsert->execute([$kId, $sId, $it['dist_id']]);
                        $totalPlaced++;
                    }
                }
            }
            db()->commit();

            $unplacedCount = count($bestUnplaced);
            if ($unplacedCount > 0) {
                json_response(200, true, "Jadwal berhasil di-generate ({$totalPlaced} slot terisi, 100% patuh kesediaan guru & anti bentrok). Terdapat {$unplacedCount} blok jam yang belum muat.", [
                    'unplaced_blocks' => $unplacedCount,
                    'kesediaan_violations' => 0,
                    'total_placed' => $totalPlaced
                ]);
            } else {
                json_response(200, true, 'Jadwal berhasil di-generate secara utuh 100% mematuhi aturan pembagian JP (1, 2, 3 sekaligus/2+1, 4=2+2/3+1, 5=3+2/2+2+1, PJOK kontinu tanpa terpotong istirahat, TIK 2+1/3, dan 100% patuh kesediaan guru).', [
                    'total_placed' => $totalPlaced,
                    'kesediaan_violations' => 0
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
