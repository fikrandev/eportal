    // ==================== ABSENSI GURU VIEW ====================
    renderAbsensiGuru($container) {
        const today = new Date().toISOString().split('T')[0];
        const firstDayOfMonth = today.substring(0, 8) + '01';

        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3>📋 Absensi Guru</h3>
                        <p class="acad-subtitle">Terintegrasi otomatis dengan mesin E-Absen & Rekapitulasi Kehadiran.</p>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn-acad btn-acad-outline" onclick="Curriculum.showSettingWaktuGuruModal()">
                            ⚙️ Setting Jam Terlambat
                        </button>
                    </div>
                </div>
                <div class="acad-card-body">
                    <!-- Navigation Tabs -->
                    <div style="margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; display: flex; gap: 16px;">
                        <button class="absensi-guru-tab-btn active" data-tab="harian" onclick="Curriculum.switchAbsensiGuruTab('harian')" style="background: none; border: none; padding: 10px 16px; cursor: pointer; border-bottom: 2px solid #7C3AED; font-weight: 600; color: #7C3AED;">
                            📌 Absensi Harian
                        </button>
                        <button class="absensi-guru-tab-btn" data-tab="rekap" onclick="Curriculum.switchAbsensiGuruTab('rekap')" style="background: none; border: none; padding: 10px 16px; cursor: pointer; border-bottom: 2px solid transparent; font-weight: 600; color: #64748b;">
                            📊 Rekapitulasi Kehadiran
                        </button>
                    </div>

                    <!-- Tab 1: Absensi Harian -->
                    <div id="absensiGuruTabHarian" class="absensi-guru-tab-content">
                        <div class="filter-bar">
                            <div class="filter-item"><label>Tanggal</label><input type="date" class="form-input-acad" id="absensiGuruTanggal" value="${today}"></div>
                            <div class="filter-item" style="display:flex; flex-direction:row; align-items:flex-end;"><button class="btn-acad btn-acad-primary" onclick="Curriculum.loadAbsensiGuruTable()">🔍 Tampilkan</button></div>
                        </div>
                        <div id="absensiGuruInfoBanner" style="margin-bottom:16px; padding:10px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; color:#1e40af; font-size:0.875rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>📌 Data otomatis menyelaraskan jam tap dari E-Absen. <strong>Batas Jam Terlambat: <span id="lblJamTerlambatGuru">07:15</span></strong></span>
                        </div>
                        <div id="absensiGuruTableWrapper"></div>
                    </div>

                    <!-- Tab 2: Rekapitulasi Absensi -->
                    <div id="absensiGuruTabRekap" class="absensi-guru-tab-content" style="display:none;">
                        <div class="filter-bar">
                            <div class="filter-item"><label>Tanggal Awal</label><input type="date" class="form-input-acad" id="rekapGuruTglAwal" value="${firstDayOfMonth}"></div>
                            <div class="filter-item"><label>Tanggal Akhir</label><input type="date" class="form-input-acad" id="rekapGuruTglAkhir" value="${today}"></div>
                            <div class="filter-item" style="display:flex; flex-direction:row; align-items:flex-end; gap:8px;">
                                <button class="btn-acad btn-acad-primary" onclick="Curriculum.loadAbsensiGuruRekapTable()">🔍 Tampilkan Rekap</button>
                                <button class="btn-acad btn-acad-success" onclick="Curriculum.exportAbsensiGuruExcel()" style="background:#10B981; color:white; border:none;">📥 Export Excel</button>
                            </div>
                        </div>
                        <div id="rekapAbsensiGuruTableWrapper"></div>
                    </div>
                </div>
            </div>
        `);

        // Load current late setting
        this.api('absensi_guru.php?action=get_settings').done(res => {
            if (res.data && res.data.waktu_terlambat) {
                $('#lblJamTerlambatGuru').text(res.data.waktu_terlambat);
            }
        });

        // Initialize table
        this.loadAbsensiGuruTable();
    },

    switchAbsensiGuruTab(tabName) {
        $('.absensi-guru-tab-btn').removeClass('active').css({ 'border-bottom-color': 'transparent', 'color': '#64748b' });
        $(`.absensi-guru-tab-btn[data-tab="${tabName}"]`).addClass('active').css({ 'border-bottom-color': '#7C3AED', 'color': '#7C3AED' });
        $('.absensi-guru-tab-content').hide();
        
        if (tabName === 'harian') {
            $('#absensiGuruTabHarian').fadeIn();
        } else {
            $('#absensiGuruTabRekap').fadeIn();
        }
    },

    loadAbsensiGuruTable() {
        const tanggal = $('#absensiGuruTanggal').val();
        if (!tanggal) return;

        const $wrapper = $('#absensiGuruTableWrapper');
        $wrapper.html('<div style="padding:20px; text-align:center;">Memuat data absensi...</div>');

        this.api(`absensi_guru.php?action=list&tanggal=${tanggal}`).done(res => {
            if (!res.success) {
                $wrapper.html(`<div style="color:red; padding:20px;">Error: ${res.message}</div>`);
                return;
            }

            const data = res.data.teachers;
            if (data.length === 0) {
                $wrapper.html('<div style="padding:20px; text-align:center; color:gray;">Tidak ada data guru.</div>');
                return;
            }

            let trs = '';
            data.forEach((t, i) => {
                const isLate = t.status === 'T';
                const hasScan = t.jam_masuk !== null;
                const statusColor = t.status === 'H' ? '#10B981' : (t.status === 'T' ? '#F59E0B' : (t.status === 'A' ? '#EF4444' : '#3B82F6'));
                
                let scanBadge = '';
                if (hasScan) {
                    scanBadge = `<span style="font-size:0.75rem; padding:2px 6px; background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; border-radius:4px;">${this.escapeHtml(t.scan_info)}</span>`;
                } else {
                    scanBadge = `<span style="font-size:0.75rem; padding:2px 6px; background:#fef2f2; color:#991b1b; border:1px solid #fecaca; border-radius:4px;">Belum Scan Mesin</span>`;
                }

                trs += `
                    <tr>
                        <td style="text-align:center;">${i+1}</td>
                        <td>${this.escapeHtml(t.kode_guru)}</td>
                        <td>
                            <div style="font-weight:600; color:var(--acad-text-main);">${this.escapeHtml(t.nama)}</div>
                            <div style="margin-top:4px;">${scanBadge}</div>
                            <input type="hidden" class="absensi-guru-id" value="${t.guru_id}">
                        </td>
                        <td>
                            <select class="form-select-acad absensi-guru-status" style="width:130px; font-weight:600; color:${statusColor};" onchange="this.style.color = this.value==='H'?'#10B981':(this.value==='T'?'#F59E0B':(this.value==='A'?'#EF4444':'#3B82F6'))">
                                <option value="H" ${t.status === 'H' ? 'selected' : ''} style="color:#10B981;">Hadir</option>
                                <option value="T" ${t.status === 'T' ? 'selected' : ''} style="color:#F59E0B;">Terlambat</option>
                                <option value="S" ${t.status === 'S' ? 'selected' : ''} style="color:#3B82F6;">Sakit</option>
                                <option value="I" ${t.status === 'I' ? 'selected' : ''} style="color:#3B82F6;">Izin</option>
                                <option value="A" ${t.status === 'A' ? 'selected' : ''} style="color:#EF4444;">Alpha</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" class="form-input-acad absensi-guru-keterangan" value="${this.escapeHtml(t.keterangan || '')}" placeholder="Opsional...">
                        </td>
                    </tr>
                `;
            });

            $wrapper.html(`
                <div style="overflow-x:auto;">
                    <table class="acad-table">
                        <thead>
                            <tr>
                                <th style="width:50px; text-align:center;">No</th>
                                <th style="width:120px;">Kode Guru</th>
                                <th>Nama Guru & Info Scan</th>
                                <th style="width:150px;">Status</th>
                                <th>Keterangan (Manual)</th>
                            </tr>
                        </thead>
                        <tbody>${trs}</tbody>
                    </table>
                </div>
                <div style="margin-top:16px; text-align:right;">
                    <button class="btn-acad btn-acad-primary" onclick="Curriculum.saveAbsensiGuru()">💾 Simpan Data Absensi Guru</button>
                </div>
            `);
        });
    },

    saveAbsensiGuru() {
        const tanggal = $('#absensiGuruTanggal').val();
        if (!tanggal) {
            EModal.toast({ type: 'warning', message: 'Tanggal absensi tidak valid.' });
            return;
        }

        const absensi = [];
        $('#absensiGuruTableWrapper tbody tr').each(function() {
            const tr = $(this);
            absensi.push({
                guru_id: tr.find('.absensi-guru-id').val(),
                status: tr.find('.absensi-guru-status').val(),
                keterangan: tr.find('.absensi-guru-keterangan').val()
            });
        });

        if (absensi.length === 0) return;

        const btn = $('#absensiGuruTableWrapper button');
        const oldText = btn.text();
        btn.prop('disabled', true).text('Menyimpan...');

        this.api('absensi_guru.php?action=save', {
            method: 'POST',
            data: { tanggal, absensi }
        }).done(res => {
            if (res.success) {
                EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
            } else {
                EModal.toast({ type: 'error', title: 'Gagal', message: res.message });
            }
        }).always(() => {
            btn.prop('disabled', false).text(oldText);
        });
    },

    loadAbsensiGuruRekapTable() {
        const tgl_awal = $('#rekapGuruTglAwal').val();
        const tgl_akhir = $('#rekapGuruTglAkhir').val();
        
        if (!tgl_awal || !tgl_akhir) {
            EModal.toast({ type: 'warning', message: 'Lengkapi tanggal awal dan akhir.' });
            return;
        }

        const $wrapper = $('#rekapAbsensiGuruTableWrapper');
        $wrapper.html('<div style="padding:20px; text-align:center;">Menghitung rekapitulasi...</div>');

        this.api(`absensi_guru.php?action=rekap&tanggal_awal=${tgl_awal}&tanggal_akhir=${tgl_akhir}`).done(res => {
            if (!res.success) {
                $wrapper.html(`<div style="color:red; padding:20px;">Error: ${res.message}</div>`);
                return;
            }

            const data = res.data.rekap;
            if (data.length === 0) {
                $wrapper.html('<div style="padding:20px; text-align:center; color:gray;">Tidak ada data rekap guru.</div>');
                return;
            }

            let trs = '';
            data.forEach((t, i) => {
                trs += `
                    <tr>
                        <td style="text-align:center;">${i+1}</td>
                        <td>${this.escapeHtml(t.kode_guru)}</td>
                        <td style="font-weight:600;">${this.escapeHtml(t.nama)}</td>
                        <td style="text-align:center; font-weight:600; color:#10B981;">${t.hadir}</td>
                        <td style="text-align:center; font-weight:600; color:#F59E0B;">${t.terlambat}</td>
                        <td style="text-align:center; font-weight:600; color:#3B82F6;">${t.sakit}</td>
                        <td style="text-align:center; font-weight:600; color:#3B82F6;">${t.izin}</td>
                        <td style="text-align:center; font-weight:600; color:#EF4444;">${t.alpha}</td>
                        <td style="text-align:center; font-weight:700;">${t.total_hadir}</td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="flex-grow:1; background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                                    <div style="width:${t.persentase}%; height:100%; background:${t.persentase > 80 ? '#10B981' : (t.persentase > 60 ? '#F59E0B' : '#EF4444')};"></div>
                                </div>
                                <span style="font-size:0.8rem; font-weight:600; width:35px; text-align:right;">${t.persentase}%</span>
                            </div>
                        </td>
                    </tr>
                `;
            });

            $wrapper.html(`
                <div style="overflow-x:auto;">
                    <table class="acad-table" id="tableExportRekapAbsensiGuru">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="width:50px; text-align:center;">No</th>
                                <th>Kode</th>
                                <th>Nama Guru</th>
                                <th style="text-align:center; width:80px;">Hadir</th>
                                <th style="text-align:center; width:80px;">Terlambat</th>
                                <th style="text-align:center; width:80px;">Sakit</th>
                                <th style="text-align:center; width:80px;">Izin</th>
                                <th style="text-align:center; width:80px;">Alpha</th>
                                <th style="text-align:center; width:100px;">Total Kehadiran</th>
                                <th style="width:120px;">% Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>${trs}</tbody>
                    </table>
                </div>
            `);
        });
    },

    exportAbsensiGuruExcel() {
        const table = document.getElementById('tableExportRekapAbsensiGuru');
        if (!table) {
            EModal.toast({ type: 'warning', title: 'Kosong', message: 'Tidak ada data rekap untuk diekspor.' });
            return;
        }

        const tgl_awal = $('#rekapGuruTglAwal').val();
        const tgl_akhir = $('#rekapGuruTglAkhir').val();
        const wb = XLSX.utils.table_to_book(table, { sheet: "Rekap Absensi Guru" });
        XLSX.writeFile(wb, \`Rekap_Absensi_Guru_\${tgl_awal}_sd_\${tgl_akhir}.xlsx\`);
    },

    showSettingWaktuGuruModal() {
        this.api('absensi_guru.php?action=get_settings').done(res => {
            const currentWaktu = res.data ? res.data.waktu_terlambat : '07:15';

            EModal.form({
                title: '⚙️ Setting Jam Batas Terlambat Guru',
                size: 'sm',
                form: \`
                    <div class="form-group-acad">
                        <label class="form-label-acad">Batas Jam Masuk / Terlambat</label>
                        <input type="time" class="form-input-acad" id="settingJamTerlambatGuru" value="\${currentWaktu}">
                        <small class="text-muted" style="margin-top:6px; display:block;">
                            Guru yang melakukan tap di E-Absen <strong>setelah jam ini</strong> akan otomatis dikategorikan sebagai <strong>Terlambat</strong>.
                        </small>
                    </div>
                \`,
                buttons: [
                    { text: 'Batal', class: 'btn-acad btn-acad-outline', close: true },
                    {
                        text: '💾 Simpan Setting',
                        class: 'btn-acad btn-acad-primary',
                        click: () => {
                            const waktu = $('#settingJamTerlambatGuru').val();
                            if (!waktu) {
                                EModal.toast({ type: 'warning', message: 'Jam batas wajib diisi.' });
                                return;
                            }

                            this.api('absensi_guru.php?action=save_settings', { method: 'POST', data: { waktu_terlambat: waktu } }).done(res => {
                                EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                                $('#lblJamTerlambatGuru').text(waktu);
                                EModal.closeAll();
                                this.loadAbsensiGuruTable();
                            });
                        }
                    }
                ]
            });
        });
    }
