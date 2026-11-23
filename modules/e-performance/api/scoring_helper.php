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
