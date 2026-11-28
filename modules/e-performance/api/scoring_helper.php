<?php
/**
 * E-Performance — Shared Scoring & Predikat Description Helpers
 * Conforms to Official Guideline E-Performance 2026
 */

if (!function_exists('normalizeTupoksiGroup')) {
    function normalizeTupoksiGroup($tupoksi) {
        $t = strtolower(trim((string)$tupoksi));
        if (strpos($t, 'guru') !== false || strpos($t, 'waka') !== false || strpos($t, 'wakil') !== false || strpos($t, 'kepala sekolah') !== false) {
            return 'Guru';
        } elseif (strpos($t, 'pustaka') !== false || strpos($t, 'perpus') !== false) {
            return 'Pustakawan';
        } elseif (strpos($t, 'it') !== false || strpos($t, 'teknisi') !== false || strpos($t, 'lab') !== false) {
            return 'IT-Support';
        } elseif (strpos($t, 'tu') !== false || strpos($t, 'administrasi') !== false || strpos($t, 'tata usaha') !== false) {
            return 'Tenaga Administrasi';
        }
        return 'Guru';
    }
}

if (!function_exists('getPredikatSkala100')) {
    function getPredikatSkala100($score) {
        $s = (float)$score;
        if ($s >= 91.0) return 'Sangat Baik (Istimewa)';
        if ($s >= 76.0) return 'Baik';
        if ($s >= 61.0) return 'Cukup';
        if ($s >= 51.0) return 'Kurang';
        return 'Sangat Kurang';
    }
}

if (!function_exists('getPredikatSkala5')) {
    function getPredikatSkala5($score) {
        $s = (float)$score;
        if ($s >= 4.51) return 'Sangat Baik (Istimewa)';
        if ($s >= 3.51) return 'Baik';
        if ($s >= 2.51) return 'Cukup';
        if ($s >= 1.51) return 'Kurang';
        return 'Sangat Kurang';
    }
}

if (!function_exists('getDeskripsiInfo')) {
    function getDeskripsiInfo($db, $tupoksi, $nilai) {
        $s = (float)$nilai;
        $group = normalizeTupoksiGroup($tupoksi);

        // If score is 0-5, query scale 1-5 or scale 100 appropriately
        if ($s > 0 && $s <= 5.0) {
            // Check direct scale 5 match in perf_deskripsi first
            $stmt = $db->prepare("SELECT predikat, deskripsi FROM perf_deskripsi WHERE (tupoksi = ? OR tupoksi = ?) AND min_nilai <= ? AND max_nilai >= ? AND max_nilai <= 5.0 LIMIT 1");
            $stmt->execute([$tupoksi, $group, $s, $s]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) return $res;

            // Otherwise, get deskripsi via getDeskripsiAngketLampiran
            $pred = getPredikatSkala5($s);
            $desc = getDeskripsiAngketLampiran($db, $tupoksi, $s);
            return ['predikat' => $pred, 'deskripsi' => $desc];
        } else {
            // Scale 100 query
            $stmt = $db->prepare("SELECT predikat, deskripsi FROM perf_deskripsi WHERE (tupoksi = ? OR tupoksi = ?) AND min_nilai <= ? AND max_nilai >= ? AND max_nilai > 5.0 LIMIT 1");
            $stmt->execute([$tupoksi, $group, $s, $s]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) return $res;

            $pred = getPredikatSkala100($s);
            $desc = getDeskripsiRaporUtama($db, $tupoksi, $s);
            return ['predikat' => $pred, 'deskripsi' => $desc];
        }
    }
}

if (!function_exists('getDeskripsiRaporUtama')) {
    function getDeskripsiRaporUtama($db, $tupoksi, $nilai100) {
        $s = (float)$nilai100;
        $group = normalizeTupoksiGroup($tupoksi);

        // Try querying perf_deskripsi first
        if ($db) {
            $stmt = $db->prepare("SELECT deskripsi FROM perf_deskripsi WHERE (tupoksi = ? OR tupoksi = ?) AND min_nilai <= ? AND max_nilai >= ? AND max_nilai > 5.0 LIMIT 1");
            $stmt->execute([$tupoksi, $group, $s, $s]);
            $d = $stmt->fetchColumn();
            if ($d) return $d;
        }

        // Official Guideline Fallback (Skala 100)
        if ($group === 'Tenaga Administrasi') {
            if ($s >= 91.0) return 'Kehadiran sangat sempurna dan ketelitian administrasi sangat tinggi; sangat kontributif dalam rapat dinas serta menjadi motor penggerak dalam setiap partisipasi kegiatan sekolah.';
            if ($s >= 76.0) return 'Disiplin hadir tepat waktu dan tertib dalam pengelolaan dokumen; aktif mengikuti rapat kedinasan dan menunjukkan kerja sama tim yang solid pada kegiatan sekolah.';
            if ($s >= 61.0) return 'Tugas administrasi rutin terlaksana dengan kehadiran yang cukup, namun perlu meningkatkan partisipasi aktif dalam agenda rapat dan kegiatan sekolah lainnya.';
            if ($s >= 51.0) return 'Sering terlambat hadir dan administrasi kantor sering terbengkalai; jarang memberikan masukan dalam rapat serta kurang terlibat dalam kegiatan besar sekolah.';
            return 'Gagal menjalankan fungsi administrasi dan sering mangkir kerja; tidak memiliki perangkat kerja yang jelas, abai terhadap instruksi dalam rapat, dan tidak berpartisipasi dalam agenda sekolah.';
        } elseif ($group === 'Pustakawan') {
            if ($s >= 91.0) return 'Disiplin kehadiran sangat tinggi dan administrasi perpustakaan sangat lengkap; sangat aktif memberikan ide dalam rapat dinas serta menunjukkan partisipasi luar biasa dalam kegiatan sekolah.';
            if ($s >= 76.0) return 'Hadir secara konsisten dan tertib dalam pengelolaan sirkulasi; selalu hadir dalam rapat kedinasan dan mendukung penuh setiap kegiatan sekolah dengan kompetensi sosial yang baik.';
            if ($s >= 61.0) return 'Kehadiran cukup baik namun pengelolaan administrasi perpustakaan perlu lebih teliti; partisipasi dalam rapat dan kegiatan sekolah masih bersifat pasif.';
            if ($s >= 51.0) return 'Sering meninggalkan tempat tugas dan administrasi perpustakaan tidak terurus; tidak aktif dalam rapat dinas dan kurang berpartisipasi dalam agenda sekolah.';
            return 'Perpustakaan terbengkalai dengan administrasi yang kacau; kehadiran sangat buruk, tidak mengikuti perkembangan kebijakan sekolah dalam rapat, serta menutup diri dari kegiatan sekolah';
        } elseif ($group === 'IT-Support') {
            if ($s >= 91.0) return 'Kehadiran sangat handal dan perangkat IT sekolah terawat sempurna; sangat solutif dalam rapat kedinasan serta menunjukkan dedikasi tinggi pada dokumentasi dan konten setiap kegiatan sekolah.';
            if ($s >= 76.0) return 'Disiplin dalam kehadiran dan pemeliharaan sistem; aktif berkoordinasi dalam rapat dinas serta selalu siap memberikan dukungan teknis pada seluruh kegiatan sekolah.';
            if ($s >= 61.0) return 'Kehadiran memenuhi syarat namun administrasi perawatan perangkat perlu lebih rapi; keikutsertaan dalam rapat dan kegiatan sekolah perlu ditingkatkan secara kualitas.';
            if ($s >= 51.0) return 'Kehadiran rendah dan sering terjadi gangguan teknis yang lama tertangani; jarang mengikuti rapat kedinasan dan kurang berpartisipasi dalam menyukseskan acara sekolah.';
            return 'Gagal menjaga infrastruktur IT sekolah dengan kehadiran yang sangat minim; tidak memiliki catatan pemeliharaan perangkat, abai terhadap kebutuhan digital sekolah dalam rapat, dan tidak berkontribusi pada dokumentasi kegiatan.';
        } else {
            // Guru (Mata Pelajaran, BK, Waka)
            if ($s >= 91.0) return 'Sangat istimewa dalam kehadiran dan kelengkapan perangkat mengajar; menunjukkan penguasaan pedagogik yang luar biasa serta sangat aktif dalam rapat dinas dan seluruh kegiatan sekolah.';
            if ($s >= 76.0) return 'Disiplin tinggi dalam kehadiran dan penyusunan administrasi mengajar; memiliki kompetensi sosial yang baik serta rutin berpartisipasi dalam rapat dan agenda sekolah.';
            if ($s >= 61.0) return 'Kehadiran dan perangkat mengajar memenuhi standar, namun perlu meningkatkan keaktifan dalam rapat kedinasan serta inisiatif dalam partisipasi kegiatan sekolah.';
            if ($s >= 51.0) return 'Kehadiran tidak konsisten dan perangkat mengajar tidak lengkap; kurang terlibat dalam koordinasi rapat serta memerlukan pembinaan dalam kompetensi mengajar.';
            return 'Sering mengabaikan tugas mengajar dan kelengkapan administrasi; tingkat kehadiran sangat rendah, tidak pernah aktif dalam rapat maupun kegiatan sekolah, serta menunjukkan kompetensi pedagogik yang sangat lemah.';
        }
    }
}

if (!function_exists('getDeskripsiAngketLampiran')) {
    function getDeskripsiAngketLampiran($db, $tupoksi, $nilai5) {
        $s = (float)$nilai5;
        $group = normalizeTupoksiGroup($tupoksi);

        if ($group === 'Tenaga Administrasi') {
            if ($s >= 4.51) return 'Menunjukkan kinerja yang luar biasa; administrasi sangat rapi, sangat mahir teknologi, dan memberikan pelayanan yang melebihi ekspektasi. Menjadi teladan bagi rekan sejawat dalam kedisiplinan dan integritas.';
            if ($s >= 3.51) return 'Menjalankan tugas administrasi dengan profesional dan tepat waktu. Memiliki komunikasi yang santun kepada murid dan orang tua, serta mampu bekerja sama dengan tim secara solid.';
            if ($s >= 2.51) return 'Memenuhi standar minimal pekerjaan administrasi. Namun, masih memerlukan peningkatan dalam hal ketelitian data, kecepatan pelayanan, atau inisiatif dalam penggunaan teknologi kantor.';
            if ($s >= 1.51) return 'Sering terjadi keterlambatan dalam penyelesaian dokumen atau ketidakakuratan data. Sering mendapat keluhan terkait keramahan layanan atau kedisiplinan waktu kehadiran.';
            return 'Tidak menunjukkan penguasaan teknis administrasi dan mengabaikan tanggung jawab pelayanan. Memerlukan evaluasi mendalam dan pembinaan khusus dari pimpinan.';
        } elseif ($group === 'Pustakawan') {
            if ($s >= 4.51) return 'Pengelolaan perpustakaan sangat modern, koleksi sangat terawat, dan berhasil menciptakan budaya literasi yang sangat kuat di sekolah.';
            if ($s >= 3.51) return 'Administrasi perpustakaan tertib, pelayanan ramah dan komunikatif, serta koleksi buku tertata rapi sesuai standar operasional.';
            if ($s >= 2.51) return 'Layanan perpustakaan berjalan normal, namun perlu peningkatan dalam penataan koleksi, inovasi program, dan kecepatan pelayanan digital.';
            if ($s >= 1.51) return 'Ruang perpustakaan kurang terawat, banyak buku tidak pada tempatnya, dan pelayanan sering tidak memuaskan pengunjung.';
            return 'Tidak ada sistem pendataan yang jelas, pelayanan buruk, dan banyak koleksi buku yang hilang atau rusak tanpa tanggung jawab.';
        } elseif ($group === 'IT-Support') {
            if ($s >= 4.51) return 'Infrastruktur digital sangat stabil, konten promosi kreatif, dan seluruh perangkat terawat dengan standar profesional tinggi.';
            if ($s >= 3.51) return 'Layanan IT berjalan lancar, respons cepat terhadap kendala, dan media informasi aktif diperbarui.';
            if ($s >= 2.51) return 'Tugas teknis harian terpenuhi, namun perlu peningkatan dalam inisiatif perawatan rutin dan inovasi konten.';
            if ($s >= 1.51) return 'Perawatan perangkat sering terlambat dan respons terhadap kendala teknis lambat; dokumentasi kegiatan kurang maksimal.';
            return 'Sering terjadi gangguan teknis yang lambat ditangani dan dokumentasi kegiatan tidak terorganisir.';
        } else {
            // Guru (Mata Pelajaran, BK, Waka)
            if ($s >= 4.51) return 'Guru menunjukkan kinerja yang luar biasa dan konsisten di atas standar dalam seluruh kompetensi. Menjadi teladan bagi rekan sejawat dan memberikan dampak positif yang signifikan bagi siswa serta lingkungan sekolah.';
            if ($s >= 3.51) return 'Guru menjalankan tugas dengan profesional dan memenuhi standar kompetensi dengan sangat baik. Menunjukkan inisiatif dalam pengembangan diri dan memiliki hubungan sosial yang harmonis dengan seluruh warga sekolah.';
            if ($s >= 2.51) return 'Guru telah memenuhi standar kompetensi dasar dalam mengajar, namun masih memerlukan bimbingan atau peningkatan pada beberapa aspek tertentu agar lebih optimal dalam pelayanan pendidikan.';
            if ($s >= 1.51) return 'Guru belum sepenuhnya memenuhi standar kompetensi yang ditetapkan. Terdapat kelemahan yang nyata dalam proses pembelajaran atau perilaku profesional yang memerlukan pembinaan intensif dari pimpinan.';
            return 'Guru menunjukkan kinerja yang berada di bawah standar minimal dan sering kali mengabaikan tugas serta tanggung jawabnya. Memerlukan evaluasi serius terkait status keprofesiannya.';
        }
    }
}

if (!function_exists('getDeskripsiTugasTambahan')) {
    function getDeskripsiTugasTambahan($nilai5) {
        $s = (float)$nilai5;
        if ($s >= 4.51) return 'Kinerja luar biasa, menjadi teladan, dan inovatif di semua aspek.';
        if ($s >= 3.51) return 'Kinerja di atas standar, sangat disiplin, kompeten, dan proaktif.';
        if ($s >= 2.51) return 'Kinerja memenuhi standar minimal; tugas pokok terlaksana dengan cukup.';
        if ($s >= 1.51) return 'Kinerja di bawah standar; memerlukan pembinaan dan pengawasan ketat.';
        return 'Tidak memenuhi syarat kerja; abai terhadap tugas dan kedisiplinan.';
    }
}

if (!function_exists('getDefaultDeskripsiText')) {
    function getDefaultDeskripsiText($tupoksi, $predikat) {
        $group = normalizeTupoksiGroup($tupoksi);
        $pLower = strtolower((string)$predikat);
        $score = 80; // default Baik
        if (strpos($pLower, 'sangat baik') !== false || strpos($pLower, 'istimewa') !== false) $score = 95;
        elseif (strpos($pLower, 'cukup') !== false) $score = 70;
        elseif (strpos($pLower, 'sangat kurang') !== false) $score = 40;
        elseif (strpos($pLower, 'kurang') !== false) $score = 55;
        return getDeskripsiRaporUtama(null, $group, $score);
    }
}

if (!function_exists('getDeskripsiPenilaian')) {
    function getDeskripsiPenilaian($db, $tupoksi, $nilai, $fallbackPredikat = '') {
        $info = getDeskripsiInfo($db, $tupoksi, $nilai);
        if ($info && !empty($info['deskripsi'])) {
            return $info['deskripsi'];
        }
        if ($fallbackPredikat) {
            return getDefaultDeskripsiText($tupoksi, $fallbackPredikat);
        }
        return '-';
    }
}

if (!function_exists('getPtkRoleFlags')) {
    /**
     * Resolve normalized PTK role flags and allowed Tugas Tambahan categories.
     */
    function getPtkRoleFlags($ptk) {
        $ptk_jenis = trim(!empty($ptk['jenis_ptk']) ? $ptk['jenis_ptk'] : (!empty($ptk['tupoksi']) ? $ptk['tupoksi'] : 'Guru'));
        $group_role = normalizeTupoksiGroup($ptk_jenis);
        $is_staff = ($group_role !== 'Guru');

        $jabatan_full = strtolower(trim(($ptk['jabatan'] ?? '') . ' ' . $ptk_jenis));
        $is_waka = (strpos($jabatan_full, 'waka') !== false || strpos($jabatan_full, 'wakil') !== false);
        $is_ka_tu = (strpos($jabatan_full, 'kepala tu') !== false || strpos($jabatan_full, 'ka. tu') !== false);
        $is_ka_perpus = (strpos($jabatan_full, 'kepala perpus') !== false || strpos($jabatan_full, 'ka. perpus') !== false);
        $is_ka_lab = (strpos($jabatan_full, 'kepala lab') !== false || strpos($jabatan_full, 'ka. lab') !== false);

        $allowed_tambahan = [];
        if ($is_waka) {
            $allowed_tambahan = ['Manajerial', 'Kepemimpinan', 'Teknis', 'Kewirausahaan'];
        } elseif ($is_ka_tu || $is_ka_perpus || $is_ka_lab) {
            $allowed_tambahan = ['Manajerial'];
        }

        return [
            'ptk_jenis' => $ptk_jenis,
            'group_role' => $group_role,
            'is_staff' => $is_staff,
            'is_waka' => $is_waka,
            'is_ka_tu' => $is_ka_tu,
            'is_ka_perpus' => $is_ka_perpus,
            'is_ka_lab' => $is_ka_lab,
            'allowed_tambahan' => $allowed_tambahan,
        ];
    }
}

if (!function_exists('normalizeAngketKategori')) {
    /**
     * Normalize competency category labels across raters (including Guru BK & typos)
     * so equivalent competencies aggregate cleanly under standard Guideline names.
     */
    function normalizeAngketKategori($kategori, $is_staff = false) {
        $clean = trim(str_replace(["\xc2\xa0", "\xC2\xA0"], ' ', (string)$kategori));
        $clean = trim(preg_replace('/\?+$/', '', $clean));
        $clean = preg_replace('/^Kompetensi\s+/i', '', $clean);
        $lower = strtolower($clean);

        if (!$is_staff) {
            if (in_array($lower, ['pedagogik', 'paedagogik', 'pelayanan', 'kreativitas'], true)) {
                return 'Pedagogik';
            }
            if ($lower === 'kepribadian') {
                return 'Kepribadian';
            }
            if ($lower === 'sosial') {
                return 'Sosial';
            }
            if (in_array($lower, ['profesional', 'professional', 'profesionalisme', 'kepercayaan'], true)) {
                return 'Profesional';
            }
        } else {
            if ($lower === 'kepribadian') return 'Kepribadian';
            if ($lower === 'sosial') return 'Sosial';
            if ($lower === 'teknis') return 'Teknis';
        }

        return $clean;
    }
}

if (!function_exists('calculateManualGroups')) {
    /**
     * Group, deduplicate (legacy vs active instrument batches), and score manual items.
     */
    function calculateManualGroups($allManualInstRaw, $manualData, $ptk_jenis, $is_staff) {
        $batches = [];
        foreach ($allManualInstRaw as $inst) {
            $targetsStr = trim(!empty($inst['target_dinilai']) ? $inst['target_dinilai'] : 'Semua');
            $targets = array_map('trim', explode(',', $targetsStr));
            if ($targetsStr !== 'Semua' && !in_array('Semua', $targets, true) && !in_array($ptk_jenis, $targets, true)) {
                continue;
            }
            if ((float)$inst['bobot'] <= 0) {
                continue;
            }

            $kat = trim((string)$inst['kategori']);
            $k_lower = strtolower($kat);
            $clean_kat = $kat;

            // Check rapat/kedinasan BEFORE hadir because "Kehadiran rapat" contains both words
            if (strpos($k_lower, 'perangkat') !== false) {
                $clean_kat = 'Perangkat Mengajar';
            } elseif (strpos($k_lower, 'rapat') !== false || strpos($k_lower, 'kedinasan') !== false) {
                $clean_kat = 'Keikutsertaan dalam rapat/kedinasan';
            } elseif (strpos($k_lower, 'hadir') !== false) {
                $clean_kat = 'Kehadiran';
            } elseif (strpos($k_lower, 'partisipasi') !== false) {
                $clean_kat = 'Partisipasi kegiatan sekolah';
            }

            $urutan = isset($inst['urutan']) ? (int)$inst['urutan'] : 0;
            if (!isset($batches[$clean_kat][$urutan])) {
                $batches[$clean_kat][$urutan] = [
                    'achieved_score' => 0.0,
                    'max_score' => 0.0,
                    'max_id' => 0,
                    'urutan' => $urutan,
                ];
            }

            $score = isset($manualData[$inst['id']]) ? (float)$manualData[$inst['id']] : 0.0;
            $skor_ya = (float)($inst['skor_ya'] > 0 ? $inst['skor_ya'] : 100);
            $batches[$clean_kat][$urutan]['achieved_score'] += $score;
            $batches[$clean_kat][$urutan]['max_score'] += $skor_ya;
            if ((int)$inst['id'] > $batches[$clean_kat][$urutan]['max_id']) {
                $batches[$clean_kat][$urutan]['max_id'] = (int)$inst['id'];
            }
        }

        $has_perangkat = isset($batches['Perangkat Mengajar']);
        $bobot_map = [
            'Perangkat Mengajar' => 10,
            'Kehadiran' => 20,
            'Keikutsertaan dalam rapat/kedinasan' => ($is_staff && !$has_perangkat) ? 20 : 10,
            'Partisipasi kegiatan sekolah' => 30,
        ];

        $grouped_manual = [];
        foreach ($batches as $clean_kat => $urutanGroups) {
            // Pick the active batch (prefer filled non-zero scores, tie-break by newest id / urutan 0)
            usort($urutanGroups, function ($a, $b) {
                $aHas = $a['achieved_score'] > 0 ? 1 : 0;
                $bHas = $b['achieved_score'] > 0 ? 1 : 0;
                if ($aHas !== $bHas) return $bHas - $aHas;
                if ($a['achieved_score'] != $b['achieved_score']) return ($b['achieved_score'] > $a['achieved_score']) ? 1 : -1;
                return $b['max_id'] - $a['max_id'];
            });
            $chosen = $urutanGroups[0];
            $total_bobot = $bobot_map[$clean_kat] ?? 10;
            $avg_score = $chosen['max_score'] > 0 ? ($chosen['achieved_score'] / $chosen['max_score']) * 100 : 0.0;
            $group_nilai = round($avg_score * ($total_bobot / 100), 0);

            $grouped_manual[$clean_kat] = [
                'total_bobot' => $total_bobot,
                'achieved_score' => $chosen['achieved_score'],
                'max_score' => $chosen['max_score'],
                'avg_score' => $avg_score,
                'group_nilai' => $group_nilai,
                'items' => [],
            ];
        }

        $stdOrderManual = $has_perangkat ? [
            'Perangkat Mengajar',
            'Kehadiran',
            'Keikutsertaan dalam rapat/kedinasan',
            'Partisipasi kegiatan sekolah',
        ] : [
            'Kehadiran',
            'Keikutsertaan dalam rapat/kedinasan',
            'Partisipasi kegiatan sekolah',
        ];

        $ordered_manual = [];
        foreach ($stdOrderManual as $stdKey) {
            if (isset($grouped_manual[$stdKey])) {
                $ordered_manual[$stdKey] = $grouped_manual[$stdKey];
            }
        }
        foreach ($grouped_manual as $k => $v) {
            if (!isset($ordered_manual[$k])) {
                $ordered_manual[$k] = $v;
            }
        }

        return $ordered_manual;
    }
}

if (!function_exists('perfSplitCsv')) {
    function perfSplitCsv($csv): array {
        $clean = str_replace(["\xc2\xa0", "\xC2\xA0"], ' ', (string)$csv);
        $parts = explode(',', $clean);
        $out = [];
        foreach ($parts as $p) {
            $t = trim($p);
            if ($t !== '') $out[] = $t;
        }
        return $out;
    }
}

if (!function_exists('perfInCsv')) {
    function perfInCsv(string $needle, $csv): bool {
        $n = strtolower(trim(str_replace(["\xc2\xa0", "\xC2\xA0"], ' ', $needle)));
        if ($n === '') return false;
        foreach (perfSplitCsv($csv) as $item) {
            if (strtolower($item) === $n) return true;
        }
        return false;
    }
}

if (!function_exists('perfLoadPeriodInstrumen')) {
    function perfLoadPeriodInstrumen($db, int $periode_id): array {
        $stmt = $db->prepare("
            SELECT id AS instrumen_id, kategori, target_jabatan, target_dinilai, pertanyaan, urutan
            FROM perf_instrumen
            WHERE periode_id = ? AND is_manual = 0
            ORDER BY urutan ASC, id ASC
        ");
        $stmt->execute([$periode_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('perfGetInstrumenForTarget')) {
    /**
     * Resolve non-manual instruments for a target PTK & rater mode ('kepsek', 'self', 'peer', 'siswa').
     * Automatically inherits missing main Guru/Staff competencies (e.g. Kepala Sekolah menilai
     * Wakil Kepala Sekolah sebagai Guru: Pedagogik, Kepribadian, Sosial, Profesional) without duplicates.
     */
    function perfGetInstrumenForTarget($db, int $periode_id, string $rater_mode, $target_ptk, string $rater_tupoksi = '', ?array $allInstrumen = null): array {
        if ($allInstrumen === null) {
            $allInstrumen = perfLoadPeriodInstrumen($db, $periode_id);
        }
        if (empty($allInstrumen)) return [];

        if (!is_array($target_ptk)) {
            $target_ptk = ['jenis_ptk' => (string)$target_ptk, 'jabatan' => ''];
        }
        $flags = getPtkRoleFlags($target_ptk);
        $ptk_jenis = $flags['ptk_jenis'];
        $is_staff = $flags['is_staff'];
        $is_waka = $flags['is_waka'];
        $is_ka_tu = $flags['is_ka_tu'];
        $is_ka_perpus = $flags['is_ka_perpus'];
        $is_ka_lab = $flags['is_ka_lab'];
        $allowed_tambahan = $flags['allowed_tambahan'];

        $rater_tupoksi = trim($rater_tupoksi);
        $isGuruRater = (stripos($rater_tupoksi, 'guru') !== false || stripos($rater_tupoksi, 'wali kelas') !== false || stripos($rater_tupoksi, 'wakil') !== false || stripos($rater_tupoksi, 'waka') !== false);

        $matchesRoleAndTarget = function (array $row, array $targetRoles, array $extraRaterRoles = []) use ($rater_mode, $rater_tupoksi, $isGuruRater): bool {
            $tJab = $row['target_jabatan'] ?? '';
            $tDin = $row['target_dinilai'] ?? '';

            $targetHit = perfInCsv('Semua', $tDin);
            if (!$targetHit) {
                foreach ($targetRoles as $tr) {
                    if (perfInCsv($tr, $tDin)) {
                        $targetHit = true;
                        break;
                    }
                }
            }

            if ($rater_mode === 'kepsek') {
                $raterHit = perfInCsv('Kepala Sekolah', $tJab) || perfInCsv('Kepsek', $tJab) || perfInCsv('Plt. Kepala Sekolah', $tJab);
                return $raterHit && $targetHit;
            }

            if ($rater_mode === 'siswa') {
                $raterHit = perfInCsv('Siswa', $tJab) || perfInCsv('Murid', $tJab);
                return $raterHit && $targetHit;
            }

            $raterCandidates = array_filter(array_unique(array_merge(
                [$rater_tupoksi],
                $isGuruRater ? ['Guru'] : [],
                $extraRaterRoles
            )));

            $raterDirectHit = false;
            foreach ($raterCandidates as $rc) {
                if (perfInCsv($rc, $tJab)) {
                    $raterDirectHit = true;
                    break;
                }
            }

            if ($rater_mode === 'self') {
                if ($raterDirectHit && (perfInCsv('Diri Sendiri', $tDin) || $targetHit)) return true;
                if (perfInCsv('Diri Sendiri', $tJab) && (perfInCsv('Diri Sendiri', $tDin) || $targetHit)) return true;
                return false;
            }

            if ($rater_mode === 'peer') {
                if ($raterDirectHit && (perfInCsv('Teman Sejawat', $tDin) || $targetHit)) return true;
                if (perfInCsv('Teman Sejawat', $tJab) && (perfInCsv('Teman Sejawat', $tDin) || $targetHit)) return true;
                return false;
            }

            return false;
        };

        // 1. Direct target roles
        $directTargets = [$ptk_jenis];
        if ($is_waka && !in_array('Wakil Kepala Sekolah', $directTargets, true)) $directTargets[] = 'Wakil Kepala Sekolah';
        if ($is_ka_tu && !in_array('Kepala TU', $directTargets, true)) $directTargets[] = 'Kepala TU';
        if ($is_ka_perpus && !in_array('Kepala Perpus', $directTargets, true)) $directTargets[] = 'Kepala Perpus';
        if ($is_ka_lab && !in_array('Kepala Lab', $directTargets, true)) $directTargets[] = 'Kepala Lab';

        $matched = [];
        foreach ($allInstrumen as $row) {
            $normKat = normalizeAngketKategori($row['kategori'] ?? '', $is_staff);
            $isTambahanKat = in_array($normKat, $allowed_tambahan, true);
            if ($isTambahanKat && $rater_mode !== 'kepsek') {
                continue;
            }
            if ($matchesRoleAndTarget($row, $directTargets)) {
                $row['norm_kategori'] = $normKat;
                $row['is_tambahan'] = $isTambahanKat;
                $matched[(int)$row['instrumen_id']] = $row;
            }
        }

        // 2. Base-role inheritance: Wakil Kepala Sekolah, Kepala TU, Kepala Perpus, dan Kepala Lab
        // wajib mewarisi instrumen kompetensi pokok ("Sebagai Guru" / "Sebagai Staf") apabila belum diset langsung
        $needsBaseInheritance = ($is_waka || $is_ka_tu || $is_ka_perpus || $is_ka_lab);
        if ($needsBaseInheritance) {
            $reqMainKats = $is_staff
                ? ['Kepribadian', 'Sosial', 'Teknis']
                : ['Pedagogik', 'Kepribadian', 'Sosial', 'Profesional'];

            $presentMainKats = [];
            foreach ($matched as $m) {
                if (!$m['is_tambahan'] && in_array($m['norm_kategori'], $reqMainKats, true)) {
                    $presentMainKats[$m['norm_kategori']] = true;
                }
            }

            $missingMainKats = [];
            foreach ($reqMainKats as $rk) {
                if (empty($presentMainKats[$rk])) $missingMainKats[] = $rk;
            }

            if (!empty($missingMainKats)) {
                $fallbackTargets = [];
                if (!$is_staff) {
                    if (stripos($ptk_jenis, 'bk') !== false) {
                        $fallbackTargets = ['Guru BK', 'Guru Mata Pelajaran', 'Guru'];
                    } else {
                        // Wakil Kepala Sekolah & Guru lainnya mewarisi instrumen Guru Mata Pelajaran / Guru
                        $fallbackTargets = ['Guru Mata Pelajaran', 'Guru'];
                    }
                } elseif ($flags['group_role'] === 'Tenaga Administrasi') {
                    $fallbackTargets = ['Tenaga Administrasi', 'TU', 'Tata Usaha'];
                } elseif ($flags['group_role'] === 'Pustakawan') {
                    $fallbackTargets = ['Pustakawan'];
                } elseif ($flags['group_role'] === 'IT-Support') {
                    $fallbackTargets = ['IT-Support'];
                }

                if (!empty($fallbackTargets)) {
                    // Populate one fallback role at a time so we don't mix Guru BK + Guru Mapel if Guru Mapel already has it
                    foreach ($fallbackTargets as $fbRole) {
                        $stillMissing = [];
                        foreach ($reqMainKats as $rk) {
                            if (empty($presentMainKats[$rk])) $stillMissing[] = $rk;
                        }
                        if (empty($stillMissing)) break;

                        $addedCatsInPass = [];
                        foreach ($allInstrumen as $row) {
                            $id = (int)$row['instrumen_id'];
                            if (isset($matched[$id])) continue;

                            $normKat = normalizeAngketKategori($row['kategori'] ?? '', $is_staff);
                            if (in_array($normKat, $allowed_tambahan, true)) continue;
                            if (!in_array($normKat, $stillMissing, true)) continue;

                            if ($matchesRoleAndTarget($row, [$fbRole], [$fbRole])) {
                                $row['norm_kategori'] = $normKat;
                                $row['is_tambahan'] = false;
                                $matched[$id] = $row;
                                $addedCatsInPass[$normKat] = true;
                            }
                        }
                        foreach ($addedCatsInPass as $ck => $_) {
                            $presentMainKats[$ck] = true;
                        }
                    }
                }
            }
        }

        // 3. Order cleanly: Main competencies in standard Guideline order first, Tugas Tambahan second
        $mainOrder = $is_staff
            ? ['Kepribadian' => 1, 'Sosial' => 2, 'Teknis' => 3]
            : ['Pedagogik' => 1, 'Kepribadian' => 2, 'Sosial' => 3, 'Profesional' => 4];
        $tambahanOrder = ['Manajerial' => 101, 'Kepemimpinan' => 102, 'Teknis' => 103, 'Kewirausahaan' => 104];

        $result = array_values($matched);
        usort($result, function ($a, $b) use ($mainOrder, $tambahanOrder) {
            $aTam = !empty($a['is_tambahan']) ? 1 : 0;
            $bTam = !empty($b['is_tambahan']) ? 1 : 0;
            if ($aTam !== $bTam) return $aTam - $bTam;

            $aRank = $aTam ? ($tambahanOrder[$a['norm_kategori']] ?? 199) : ($mainOrder[$a['norm_kategori']] ?? 99);
            $bRank = $bTam ? ($tambahanOrder[$b['norm_kategori']] ?? 199) : ($mainOrder[$b['norm_kategori']] ?? 99);
            if ($aRank !== $bRank) return $aRank - $bRank;

            $aUrut = (int)($a['urutan'] ?? 0);
            $bUrut = (int)($b['urutan'] ?? 0);
            if ($aUrut !== $bUrut) return $aUrut - $bUrut;

            return ((int)$a['instrumen_id']) - ((int)$b['instrumen_id']);
        });

        return $result;
    }
}

if (!function_exists('calculateAngketScores')) {
    /**
     * Aggregate angket evaluations, separate main vs Tugas Tambahan, and convert to scale 100.
     */
    function calculateAngketScores($rawScores, $is_staff, $is_waka, $allowed_tambahan, $penilai_list) {
        $allKats = [];
        foreach ($rawScores as $r) {
            $k = normalizeAngketKategori($r['kategori'], $is_staff);
            if ($k && !in_array($k, $allKats, true)) {
                $allKats[] = $k;
            }
        }

        $kategori_list = [];
        $kategori_tambahan_list = [];
        foreach ($allKats as $k) {
            if (in_array($k, $allowed_tambahan, true)) {
                $kategori_tambahan_list[] = $k;
            } else {
                $kategori_list[] = $k;
            }
        }

        $stdOrder = $is_staff
            ? ['Kepribadian', 'Sosial', 'Teknis']
            : ['Pedagogik', 'Kepribadian', 'Sosial', 'Profesional'];
        $ordered_kats = $stdOrder;
        foreach ($kategori_list as $k) {
            if (!in_array($k, $ordered_kats, true)) {
                $ordered_kats[] = $k;
            }
        }
        $kategori_list = $ordered_kats;

        if ($is_waka) {
            $stdOrderWaka = ['Manajerial', 'Kepemimpinan', 'Teknis', 'Kewirausahaan'];
            $kategori_tambahan_list = array_values(array_intersect($stdOrderWaka, $kategori_tambahan_list));
        }

        $agg = [];
        $agg_tambahan = [];
        foreach ($rawScores as $row) {
            $ptype_raw = strtolower(trim((string)$row['penilai_type']));
            $rater_role = strtolower(trim((string)($row['rater_role'] ?? '')));
            $t_jab = trim((string)($row['target_jabatan'] ?? ''));

            $isWakaOrHeadStaffPtype = (
                strpos($ptype_raw, 'wakil') !== false ||
                strpos($ptype_raw, 'waka') !== false ||
                strpos($ptype_raw, 'kepala tu') !== false ||
                strpos($ptype_raw, 'ka. tu') !== false ||
                strpos($ptype_raw, 'kepala perpus') !== false ||
                strpos($ptype_raw, 'ka. perpus') !== false ||
                strpos($ptype_raw, 'kepala lab') !== false ||
                strpos($ptype_raw, 'ka. lab') !== false
            );

            if (strpos($ptype_raw, 'siswa') !== false || strpos($ptype_raw, 'murid') !== false || $rater_role === 'siswa') {
                $ptype = 'siswa';
            } elseif (
                !$isWakaOrHeadStaffPtype && (
                    $ptype_raw === 'kepala sekolah' ||
                    $ptype_raw === 'plt. kepala sekolah' ||
                    $ptype_raw === 'plt kepala sekolah' ||
                    strpos($ptype_raw, 'kepsek') !== false ||
                    strpos($ptype_raw, 'kepala sekolah') !== false ||
                    $rater_role === 'kepsek' ||
                    strcasecmp($t_jab, 'Kepala Sekolah') === 0
                )
            ) {
                $ptype = 'kepsek';
            } elseif ($row['rater_ptk_id'] != null && (int)$row['rater_ptk_id'] === (int)$row['dinilai_ptk_id']) {
                $ptype = 'sendiri';
            } else {
                $ptype = 'teman';
            }

            $k = normalizeAngketKategori($row['kategori'], $is_staff);
            if (in_array($k, $kategori_list, true)) {
                if (!isset($agg[$ptype][$k])) $agg[$ptype][$k] = ['sum' => 0.0, 'count' => 0];
                $agg[$ptype][$k]['sum'] += (float)$row['nilai'];
                $agg[$ptype][$k]['count']++;
            } elseif (in_array($k, $kategori_tambahan_list, true)) {
                if (!isset($agg_tambahan[$ptype][$k])) $agg_tambahan[$ptype][$k] = ['sum' => 0.0, 'count' => 0];
                $agg_tambahan[$ptype][$k]['sum'] += (float)$row['nilai'];
                $agg_tambahan[$ptype][$k]['count']++;
            }
        }

        $scores = [];
        $total_nilai_akhir = 0.0;
        foreach ($penilai_list as $key => $p) {
            $scores[$key] = ['total_avg' => 0.0, 'nilai' => 0.0, 'kategori' => []];
            $sum_kat = 0.0;
            $count_kat = 0;
            foreach ($kategori_list as $kat) {
                $val = 0.0;
                if (isset($agg[$key][$kat]) && $agg[$key][$kat]['count'] > 0) {
                    $val = $agg[$key][$kat]['sum'] / $agg[$key][$kat]['count'];
                }
                $scores[$key]['kategori'][$kat] = $val;
                if ($val > 0) {
                    $sum_kat += $val;
                    $count_kat++;
                }
            }
            $scores[$key]['total_avg'] = $count_kat > 0 ? ($sum_kat / $count_kat) : 0.0;
            $scores[$key]['nilai'] = $scores[$key]['total_avg'] * $p['bobot'];
            $total_nilai_akhir += round($scores[$key]['nilai'], 3);
        }

        $konversi = [];
        foreach ($kategori_list as $kat) {
            $sum_val = 0.0;
            $count_penilai = 0;
            foreach ($scores as $data) {
                if ($data['kategori'][$kat] > 0) {
                    $sum_val += $data['kategori'][$kat];
                    $count_penilai++;
                }
            }
            $max_skor = $count_penilai * 5;
            $konversi[$kat] = $max_skor > 0 ? ($sum_val / $max_skor) * 100 : 0.0;
        }

        $angket_score = count($konversi) > 0 ? (array_sum($konversi) / count($konversi)) : 0.0;
        $bobot_angket = 30;
        $rounded_nilai_angket = round($angket_score * ($bobot_angket / 100), 0);

        $tambahan_results = [];
        $tambahan_sum = 0.0;
        $tambahan_count = 0;
        foreach ($kategori_tambahan_list as $kat) {
            $score = 0.0;
            if (isset($agg_tambahan['kepsek'][$kat]) && $agg_tambahan['kepsek'][$kat]['count'] > 0) {
                $score = $agg_tambahan['kepsek'][$kat]['sum'] / $agg_tambahan['kepsek'][$kat]['count'];
            }
            $tambahan_results[] = [
                'kategori' => $kat,
                'score' => $score,
                'konversi' => ($score / 5) * 100,
            ];
            $tambahan_sum += $score;
            $tambahan_count++;
        }

        return [
            'kategori_list' => $kategori_list,
            'kategori_tambahan_list' => $kategori_tambahan_list,
            'scores' => $scores,
            'total_nilai_akhir' => $total_nilai_akhir,
            'konversi' => $konversi,
            'angket_score' => $angket_score,
            'bobot_angket' => $bobot_angket,
            'rounded_nilai_angket' => $rounded_nilai_angket,
            'has_tambahan' => count($kategori_tambahan_list) > 0,
            'tambahan_results' => $tambahan_results,
            'tambahan_sum' => $tambahan_sum,
            'tambahan_count' => $tambahan_count,
        ];
    }
}

if (!function_exists('formatPerfNumber')) {
    /**
     * Format numeric report values cleanly (omit .00 on whole integers; optionally trim trailing zeros).
     */
    function formatPerfNumber($val, int $decimals = 2, bool $trimTrailingZeros = false): string {
        $num = round((float)$val, $decimals);
        if (abs($num - round($num)) < (0.5 * pow(10, -$decimals))) {
            return number_format(round($num), 0, '.', '');
        }
        $formatted = number_format($num, $decimals, '.', '');
        if ($trimTrailingZeros) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }
        return $formatted;
    }
}
