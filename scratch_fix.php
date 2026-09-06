<?php
$file = 'e:/xampp/htdocs/eportal/modules/e-curriculum/assets/js/curriculum.js';
$content = file_get_contents($file);

// Fix 1
$t1 = "XLSX.writeFile(wb, \`Rekap_Absensi_Guru_\${tgl_awal}_sd_\${tgl_akhir}.xlsx\`);";
$r1 = "XLSX.writeFile(wb, `Rekap_Absensi_Guru_\${tgl_awal}_sd_\${tgl_akhir}.xlsx`);";
$content = str_replace($t1, $r1, $content);

// Fix 2
$t2 = "form: \`
                    <div class=\"form-group-acad\">
                        <label class=\"form-label-acad\">Batas Jam Masuk / Terlambat</label>
                        <input type=\"time\" class=\"form-input-acad\" id=\"settingJamTerlambatGuru\" value=\"\${currentWaktu}\">
                        <small class=\"text-muted\" style=\"margin-top:6px; display:block;\">
                            Guru yang melakukan tap di E-Absen <strong>setelah jam ini</strong> akan otomatis dikategorikan sebagai <strong>Terlambat</strong>.
                        </small>
                    </div>
                \`,";

$r2 = "form: `
                    <div class=\"form-group-acad\">
                        <label class=\"form-label-acad\">Batas Jam Masuk / Terlambat</label>
                        <input type=\"time\" class=\"form-input-acad\" id=\"settingJamTerlambatGuru\" value=\"\${currentWaktu}\">
                        <small class=\"text-muted\" style=\"margin-top:6px; display:block;\">
                            Guru yang melakukan tap di E-Absen <strong>setelah jam ini</strong> akan otomatis dikategorikan sebagai <strong>Terlambat</strong>.
                        </small>
                    </div>
                `,";

$content = str_replace($t2, $r2, $content);

file_put_contents($file, $content);
echo "Fixed syntax errors.";
