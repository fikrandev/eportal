<?php
$file = 'e:/xampp/htdocs/eportal/modules/e-curriculum/assets/js/curriculum.js';
$content = file_get_contents($file);

$sidebarAdminTarget = <<<HTML
                    <button class="acad-nav-item" data-route="absensi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Absensi Siswa
                    </button>
HTML;
$sidebarAdminReplacement = $sidebarAdminTarget . <<<HTML

                    <button class="acad-nav-item" data-route="absensi_guru">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Absensi Guru
                    </button>
HTML;
$content = str_replace($sidebarAdminTarget, $sidebarAdminReplacement, $content);


$routingTarget = <<<JS
            case 'absensi':
                \$title.text('Absensi Siswa');
                this.setBreadcrumbs([{ label: 'Absensi Siswa' }]);
                this.renderAbsensi(\$content);
                break;
JS;
$routingReplacement = $routingTarget . <<<JS

            case 'absensi_guru':
                \$title.text('Absensi Guru');
                this.setBreadcrumbs([{ label: 'Absensi Guru' }]);
                this.renderAbsensiGuru(\$content);
                break;
JS;
$content = str_replace($routingTarget, $routingReplacement, $content);


$jsTarget = <<<JS
                            this.api('absensi.php?action=save_settings', { method: 'POST', data: { waktu_terlambat: waktu } }).done(res => {
                                EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                                $('#lblJamTerlambat').text(waktu);
                                EModal.closeAll();
                                this.loadAbsensiTable();
                            });
                        }
                    }
                ]
            });
        });
    },
JS;

$newJs = file_get_contents('e:/xampp/htdocs/eportal/scratch.js');
$jsReplacement = $jsTarget . "\n\n" . $newJs . "\n";

$content = str_replace($jsTarget, $jsReplacement, $content);

file_put_contents($file, $content);
echo "Injected JS successfully.";
