        // --- GURU WALI MONITORING ---
        async renderJurnalGuruWali() {
            if (!Auth.user || !Auth.user.is_guru_wali) {
                Router.navigate('home');
                return;
            }

            const content = $('#appContent');
            const tanggalIni = getTanggalIni();

            const d = new Date();
            const firstDay = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-01';

            content.innerHTML = `
                <div class="page-enter">
                    <div class="section-title" style="margin-bottom:12px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        Jurnal & Rekap Guru Wali
                    </div>

                    <div class="tabs-segment">
                        <button class="tab-segment-btn active" id="tabJurnalFeedGw" onclick="GuruApp.switchGuruWaliTab('feed')">Jurnal Harian</button>
                        <button class="tab-segment-btn" id="tabAbsenRekapGw" onclick="GuruApp.switchGuruWaliTab('rekap')">Rekap Absensi</button>
                    </div>

                    <!-- Tab 1: Jurnal Feed -->
                    <div id="gwFeedWrapper">
                        <div class="date-picker-row">
                            <input type="date" id="gwDateFrom" value="${firstDay}">
                            <span class="text-muted text-sm">s/d</span>
                            <input type="date" id="gwDateTo" value="${tanggalIni}">
                            <button class="btn btn-sm btn-primary" onclick="GuruApp.loadGuruWaliJurnal()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </button>
                        </div>
                        <div id="gwJurnalList">
                            <div class="skeleton skeleton-card"></div>
                        </div>
                    </div>

                    <!-- Tab 2: Rekap Absensi -->
                    <div id="gwRekapWrapper" style="display:none;">
                        <div class="date-picker-row" style="margin-bottom:16px;">
                            <input type="date" id="printGwAbsenTanggal" value="${tanggalIni}" style="flex:1;">
                            <button class="btn btn-sm" onclick="GuruApp.printDailyGuruWaliAbsen()" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; display:flex; align-items:center; gap:6px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                Cetak Absen
                            </button>
                        </div>
                        <div style="background:white; border-radius:16px; padding:16px; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9;">
                            <div class="rekap-search-wrap" style="margin-bottom:0;">
                                <input type="text" class="rekap-search-input" id="rekapGwSearchInput" placeholder="Cari nama anak wali..." oninput="GuruApp.filterGuruWaliRekap(this.value)">
                                <svg class="rekap-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                            </div>
                        </div>
                        
                        <div style="margin:16px 0 8px 4px; font-size:0.85rem; font-weight:700; color:var(--text-secondary);">Daftar Anak Wali & Persentase Kehadiran (Semester Ini)</div>
                        <div id="gwRekapList">
                            <div class="skeleton skeleton-card"></div>
                            <div class="skeleton skeleton-card"></div>
                        </div>
                    </div>
                </div>
            `;
            
            Pages.loadGuruWaliJurnal();
        },

        switchGuruWaliTab(tab) {
            $('.tab-segment-btn').classList.remove('active');
            if (tab === 'feed') {
                $('#tabJurnalFeedGw').classList.add('active');
                $('#gwFeedWrapper').style.display = 'block';
                $('#gwRekapWrapper').style.display = 'none';
                if ($('#gwJurnalList').innerHTML.includes('skeleton')) {
                    Pages.loadGuruWaliJurnal();
                }
            } else {
                $('#tabAbsenRekapGw').classList.add('active');
                $('#gwFeedWrapper').style.display = 'none';
                $('#gwRekapWrapper').style.display = 'block';
                if ($('#gwRekapList').innerHTML.includes('skeleton')) {
                    Pages.loadGuruWaliRekap();
                }
            }
        },

        async loadGuruWaliJurnal() {
            const list = $('#gwJurnalList');
            if (!list) return;

            const from = $('#gwDateFrom').val();
            const to = $('#gwDateTo').val();

            list.innerHTML = '<div class="skeleton skeleton-card"></div><div class="skeleton skeleton-card"></div>';

            try {
                const res = await API.get(`api/jurnal.php?action=list&tanggal=${from}&tanggal_akhir=${to}&jenis_jurnal=guru_wali`);
                if (res.success) {
                    Pages.renderGuruWaliJurnalList(res.data);
                } else {
                    list.innerHTML = `<div class="empty-state">Gagal memuat jurnal: ${escapeHtml(res.message)}</div>`;
                }
            } catch (e) {
                list.innerHTML = `<div class="empty-state">Terjadi kesalahan koneksi</div>`;
            }
        },

        renderGuruWaliJurnalList(data) {
            const list = $('#gwJurnalList');
            if (!data || data.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                        Belum ada catatan jurnal pada rentang tanggal ini.
                    </div>
                `;
                return;
            }

            let html = '';
            data.forEach(item => {
                html += `
                    <div class="jurnal-item-card" onclick="location.hash='#/jurnal-detail/${item.id}'">
                        <div class="jurnal-item-header">
                            <div>
                                <div class="jurnal-item-mapel">Jurnal Guru Wali</div>
                                <div class="jurnal-item-date">${formatTanggalStr(item.tanggal)}</div>
                            </div>
                            <span class="badge badge-success">Guru Wali</span>
                        </div>
                        <div class="jurnal-item-body">
                            <div class="jurnal-item-materi" style="margin-bottom:8px;">${escapeHtml(item.kegiatan || '-')}</div>
                            ${item.catatan ? `<div class="jurnal-item-catatan" style="font-size:0.8rem; background:#f8fafc; padding:8px; border-radius:6px; border-left:3px solid #2563eb; color:#475569;">${escapeHtml(item.catatan)}</div>` : ''}
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        },

        _gwRekapData: [],
        async loadGuruWaliRekap() {
            const list = $('#gwRekapList');
            if (!list) return;

            list.innerHTML = '<div class="skeleton skeleton-card"></div><div class="skeleton skeleton-card"></div>';

            try {
                const res = await API.get('api/jurnal.php?action=guru_wali_rekap');
                if (res.success) {
                    this._gwRekapData = res.data;
                    Pages.renderGuruWaliRekapList(this._gwRekapData);
                } else {
                    list.innerHTML = `<div class="empty-state">Gagal memuat rekap: ${escapeHtml(res.message)}</div>`;
                }
            } catch (e) {
                list.innerHTML = `<div class="empty-state">Terjadi kesalahan koneksi</div>`;
            }
        },

        renderGuruWaliRekapList(data) {
            const list = $('#gwRekapList');
            if (!data || data.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                        Belum ada data siswa anak wali atau belum ada absensi.
                    </div>
                `;
                return;
            }

            let html = '';
            data.forEach(s => {
                let badgeColor = s.persentase >= 90 ? 'success' : (s.persentase >= 75 ? 'warning' : 'danger');
                html += `
                    <div style="background:white; border-radius:12px; padding:14px; margin-bottom:12px; box-shadow:var(--shadow-sm); border:1px solid #f1f5f9; display:flex; align-items:center; gap:14px;">
                        <div style="width:40px; height:40px; border-radius:50%; background:#f8fafc; color:#64748b; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; border:1px solid #e2e8f0;">
                            ${escapeHtml(s.nama.charAt(0))}
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; color:var(--text-primary); font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                ${escapeHtml(s.nama)}
                            </div>
                            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                Kelas: ${escapeHtml(s.kelas)} &bull; NIS: ${escapeHtml(s.nis || '-')}
                            </div>
                            <div style="display:flex; gap:8px; margin-top:6px; font-size:0.7rem; font-weight:600;">
                                <span style="color:#16a34a;">H: ${s.hadir}</span>
                                <span style="color:#ca8a04;">S: ${s.sakit}</span>
                                <span style="color:#0284c7;">I: ${s.izin}</span>
                                <span style="color:#dc2626;">A: ${s.alpha}</span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span class="badge badge-${badgeColor}" style="font-size:0.8rem; padding:4px 8px;">${s.persentase}%</span>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        },

        filterGuruWaliRekap(q) {
            if (!this._gwRekapData) return;
            const term = q.toLowerCase();
            const filtered = this._gwRekapData.filter(s => 
                s.nama.toLowerCase().includes(term) || 
                (s.nis && s.nis.toLowerCase().includes(term))
            );
            this.renderGuruWaliRekapList(filtered);
        },

        async printDailyGuruWaliAbsen() {
            const tanggal = $('#printGwAbsenTanggal').val();
            if (!tanggal) return Toast.show('Pilih tanggal dulu.', 'warning');

            const loader = Toast.loading('Menyiapkan dokumen...');
            try {
                const res = await API.get('api/jurnal.php?action=guru_wali_daily_absen&tanggal=' + tanggal);
                Toast.close(loader);
                if (!res.success) {
                    return Toast.show(res.message || 'Gagal memuat absen harian.', 'error');
                }

                const data = res.data;
                const htmlContent = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Laporan Absensi Guru Wali</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 30px; color: #000; }
                            h2, h3, h4 { margin: 0 0 10px 0; text-align: center; }
                            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                            th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
                            th { background-color: #f2f2f2; }
                            .header-info { margin-top: 20px; font-size: 11pt; }
                        </style>
                    </head>
                    <body>
                        <h2>LAPORAN ABSENSI HARIAN (ANAK WALI)</h2>
                        <h3>${Auth.school?.nama_sekolah || 'SEKOLAH'}</h3>
                        
                        <div class="header-info">
                            <table style="border:none; width:auto; margin:0;">
                                <tr><td style="border:none; padding:2px 10px 2px 0;"><strong>Nama Guru Wali</strong></td><td style="border:none; padding:2px;">: ${escapeHtml(Auth.user.nama_lengkap)}</td></tr>
                                <tr><td style="border:none; padding:2px 10px 2px 0;"><strong>Tanggal</strong></td><td style="border:none; padding:2px;">: ${formatTanggalStr(data.tanggal)}</td></tr>
                            </table>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width:30px; text-align:center;">No</th>
                                    <th rowspan="2" style="width:70px; text-align:center;">NIS</th>
                                    <th rowspan="2">Nama Siswa</th>
                                    <th rowspan="2" style="width:70px; text-align:center;">Kelas</th>
                                    <th colspan="10" style="text-align:center;">Jam Ke-</th>
                                </tr>
                                <tr>
                                    <th style="width:25px; text-align:center;">1</th>
                                    <th style="width:25px; text-align:center;">2</th>
                                    <th style="width:25px; text-align:center;">3</th>
                                    <th style="width:25px; text-align:center;">4</th>
                                    <th style="width:25px; text-align:center;">5</th>
                                    <th style="width:25px; text-align:center;">6</th>
                                    <th style="width:25px; text-align:center;">7</th>
                                    <th style="width:25px; text-align:center;">8</th>
                                    <th style="width:25px; text-align:center;">9</th>
                                    <th style="width:25px; text-align:center;">10</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.students.map((s, idx) => `
                                    <tr>
                                        <td style="text-align:center; font-size:9.5pt;">${idx + 1}</td>
                                        <td style="text-align:center; font-size:9.5pt;">${escapeHtml(s.nis || '-')}</td>
                                        <td style="font-size:9.5pt;">${escapeHtml(s.nama)}</td>
                                        <td style="text-align:center; font-size:9.5pt;">${escapeHtml(s.kelas)}</td>
                                        ${[1,2,3,4,5,6,7,8,9,10].map(j => `<td style="text-align:center; font-family:monospace; font-weight:bold; font-size:10pt;">${s.jams[j] || '.'}</td>`).join('')}
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        
                        <div style="margin-top:40px; display:flex; justify-content:flex-end;">
                            <div style="text-align:center; width:220px; font-size:10pt;">
                                <div>Mengetahui,</div>
                                <div style="font-weight:700; margin-top:5px;">Guru Wali</div>
                                <div style="margin-top:55px; font-weight:700; text-decoration:underline;">${escapeHtml(Auth.user.nama_lengkap)}</div>
                                <div>NIP/Username: ${escapeHtml(Auth.user.username)}</div>
                            </div>
                        </div>
                        
                        <script>
                            window.onload = function() {
                                setTimeout(function() {
                                    window.print();
                                    window.close();
                                }, 500);
                            };
                        </script>
                    </body>
                    </html>
                `;
                
                const printWin = window.open('', '_blank');
                if (!printWin) {
                    Toast.show('Pop-up terblokir! Izinkan pop-up untuk mencetak.', 'error');
                    return;
                }
                printWin.document.open();
                printWin.document.write(htmlContent);
                printWin.document.close();
            } catch(e) {
                Toast.close(loader);
                Toast.show('Gagal menghubungi server.', 'error');
            }
        },

