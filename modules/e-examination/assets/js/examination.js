/**
 * E-Examination Module — Single Page Application (SPA)
 * Royal Blue — Premium CBT System
 */

const Exam = {
    state: {
        currentRoute: 'dashboard',
        params: {},
        user: window.EXAM_CONFIG.user,
        school: window.EXAM_CONFIG.school,
        token: window.EXAM_CONFIG.token,
        baseUrl: window.EXAM_CONFIG.baseUrl,
        apiUrl: window.EXAM_CONFIG.baseUrl + 'modules/e-examination/api/',
        moduleUrl: window.EXAM_CONFIG.moduleUrl,
        mapelList: [],
        classList: [],
    },

    getInitials(name) {
        return name ? name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) : '?';
    },

    doLogout() {
        EModal.confirm({
            title: 'Logout',
            message: 'Yakin ingin keluar dari E-Examination?',
            type: 'danger',
            confirmText: 'Ya, Logout',
            onConfirm: () => {
                window.location.href = this.state.baseUrl + '#/dashboard';
            }
        });
    },

    init() {
        console.log('E-Examination Script Loaded');
        try {
            this.bindEvents();
            this.renderSidebar();
            this.loadRouteFromHash();
            this.loadMasterData();

            setTimeout(() => {
                $('#globalLoader').addClass('hidden');
                setTimeout(() => $('#globalLoader').remove(), 500);
            }, 500);
        } catch(e) {
            console.error('Init failed', e);
            $('#globalLoader').remove();
        }
    },

    bindEvents() {
        window.addEventListener('hashchange', () => this.loadRouteFromHash());
        $('#menuToggle').on('click', () => this.toggleSidebar());
        $('#sidebarOverlay').on('click', () => this.toggleSidebar(false));
    },

    api(endpoint, options = {}) {
        let ct = options.contentType !== undefined
            ? options.contentType
            : (options.data instanceof FormData ? false : 'application/json; charset=UTF-8');
        let processData = !(options.data instanceof FormData);

        return $.ajax({
            url: this.state.apiUrl + endpoint,
            method: options.method || 'GET',
            headers: { 'Authorization': 'Bearer ' + this.state.token },
            contentType: ct,
            processData: processData,
            data: (options.data && !(options.data instanceof FormData))
                ? JSON.stringify(options.data) : options.data
        });
    },

    loadMasterData() {
        this.api('bank_soal.php?action=list_mapel').then(r => {
            if (r.success) this.state.mapelList = r.data || [];
        });
        this.api('bank_soal.php?action=list_classes').then(r => {
            if (r.success) this.state.classList = r.data || [];
        });
    },

    // ==========================================
    // ROUTING
    // ==========================================
    navigate(route, params = {}) {
        this.state.params = params;
        let hash = '#/' + route;
        if (params.id) hash += '/' + params.id;
        window.location.hash = hash;
    },

    loadRouteFromHash() {
        let hash = (window.location.hash || '#/dashboard').replace('#/', '');
        let parts = hash.split('/');
        let route = parts[0] || 'dashboard';
        if (parts[1]) this.state.params.id = parts[1];
        this.state.currentRoute = route;
        this.renderRoute(route);
    },

    renderRoute(route) {
        const $c = $('#mainContent');
        $c.html('<div style="text-align:center;padding:60px;"><div class="loading-spinner"></div></div>');

        // Update sidebar active state
        $('.ex-nav-item').removeClass('active');
        $(`.ex-nav-item[data-route="${route}"]`).addClass('active');

        // Route labels
        const labels = {
            dashboard: 'Dashboard',
            mapel: 'Mata Pelajaran',
            bank_soal: 'Bank Soal',
            detail_bank: 'Detail Bank Soal',
            ujian: 'Kelola Ujian',
            laporan: 'Laporan & Hasil Ujian'
        };

        $('#pageTitle').text(labels[route] || 'E-Examination');
        $('#breadcrumbCurrent').text(labels[route] || route);

        switch(route) {
            case 'dashboard': this.renderDashboard($c); break;
            case 'mapel': this.renderMapel($c); break;
            case 'bank_soal': this.renderBankSoal($c); break;
            case 'detail_bank': this.renderDetailBank($c); break;
            case 'ujian': this.renderUjian($c); break;
            case 'laporan': this.renderLaporan($c); break;
            default: this.renderDashboard($c);
        }
    },

    toggleSidebar(show) {
        const sidebar = $('.ex-sidebar');
        const overlay = $('.ex-sidebar-overlay');
        if (show === undefined) show = !sidebar.hasClass('show');
        sidebar.toggleClass('show', show);
        overlay.toggleClass('show', show);
    },

    // ==========================================
    // SIDEBAR
    // ==========================================
    renderSidebar() {
        const u = this.state.user;
        const role = u.role;
        let navHtml = '';

        navHtml += `
            <div class="ex-nav-group">
                <div class="ex-nav-label">Menu Utama</div>
                <button class="ex-nav-item" data-route="dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Dashboard
                </button>
            </div>`;

        navHtml += `
            <div class="ex-nav-group">
                <div class="ex-nav-label">Bank Soal</div>
                <button class="ex-nav-item" data-route="mapel">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    Mata Pelajaran
                </button>
                <button class="ex-nav-item" data-route="bank_soal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Bank Soal
                </button>
            </div>
            <div class="ex-nav-group">
                <div class="ex-nav-label">Ujian</div>
                <button class="ex-nav-item" data-route="ujian">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Kelola Ujian
                </button>
                <button class="ex-nav-item" data-route="laporan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Laporan & Hasil
                </button>
            </div>
            <div class="ex-nav-group">
                <div class="ex-nav-label">Pengaturan</div>
                <button class="ex-nav-item" onclick="Exam.settingGemini()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="7.5 4.21 12 6.81 16.5 4.21"/><polyline points="7.5 19.79 7.5 14.6 3 12"/><polyline points="21 12 16.5 14.6 16.5 19.79"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    Koreksi AI (Gemini)
                </button>
            </div>`;

        $('#sidebarNav').html(navHtml);
        $('.ex-nav-item').on('click', (e) => {
            this.navigate($(e.currentTarget).data('route'));
            this.toggleSidebar(false);
        });

        $('#sidebarAvatar').text(this.getInitials(u.nama_lengkap));
        $('#sidebarUserName').text(u.nama_lengkap);
        const roleLabels = { superadmin:'Administrator', user:'Admin', guru:'Guru' };
        $('#sidebarUserRole').text(roleLabels[role] || role);
    },

    // ==========================================
    // DASHBOARD
    // ==========================================
    renderDashboard($c) {
        this.api('bank_soal.php?action=stats').then(r => {
            if (!r.success) { $c.html('<p>Error loading stats</p>'); return; }
            const d = r.data;
            $c.html(`
                <div class="ex-stats-grid ex-slide-up">
                    <div class="ex-stat-card">
                        <div class="ex-stat-icon" style="background:rgba(59,130,246,0.1); color:#3B82F6;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        </div>
                        <div class="ex-stat-info"><h4>${d.total_mapel}</h4><p>Mata Pelajaran</p></div>
                    </div>
                    <div class="ex-stat-card">
                        <div class="ex-stat-icon" style="background:rgba(139,92,246,0.1); color:#7C3AED;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="ex-stat-info"><h4>${d.total_bank_soal}</h4><p>Bank Soal</p></div>
                    </div>
                    <div class="ex-stat-card">
                        <div class="ex-stat-icon" style="background:rgba(16,185,129,0.1); color:#059669;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        </div>
                        <div class="ex-stat-info"><h4>${d.total_soal}</h4><p>Total Soal</p></div>
                    </div>
                    <div class="ex-stat-card">
                        <div class="ex-stat-icon" style="background:rgba(245,158,11,0.1); color:#D97706;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div class="ex-stat-info"><h4>${d.ujian_aktif}</h4><p>Ujian Aktif</p></div>
                    </div>
                </div>
                <div class="ex-card ex-slide-up" style="animation-delay:0.1s">
                    <div class="ex-card-header">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            Selamat Datang di E-Examination
                        </h3>
                    </div>
                    <div class="ex-card-body">
                        <p style="color:var(--text-secondary);line-height:1.8;font-size:0.9rem;">
                            Modul ujian digital (Computer Based Test) untuk membuat dan mengelola berbagai jenis ujian.
                            Mulai dengan membuat <strong>Mata Pelajaran</strong>, lalu buat <strong>Bank Soal</strong> dan tambahkan soal-soalnya.
                        </p>
                    </div>
                </div>
            `);
        }).fail(() => {
            $c.html('<div class="ex-empty"><h3>Gagal memuat data</h3><p>Periksa koneksi ke server.</p></div>');
        });
    },

    // ==========================================
    // MATA PELAJARAN
    // ==========================================
    renderMapel($c) {
        this.api('bank_soal.php?action=list_mapel').then(r => {
            if (!r.success) { $c.html('<p>Error</p>'); return; }
            const data = r.data || [];
            this.state.mapelList = data;

            let rows = data.map((m, i) => `
                <tr>
                    <td>${i+1}</td>
                    <td><strong>${this.esc(m.nama_mapel)}</strong></td>
                    <td>${m.kode ? `<span class="ex-badge ex-badge-blue">${this.esc(m.kode)}</span>` : '-'}</td>
                    <td>
                        <div class="ex-actions">
                            <button class="ex-btn-icon" onclick="Exam.editMapel(${m.id}, '${this.esc(m.nama_mapel)}', '${this.esc(m.kode || '')}')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="ex-btn-icon danger" onclick="Exam.deleteMapel(${m.id}, '${this.esc(m.nama_mapel)}')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            if (!rows) rows = '<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--text-muted);">Belum ada mata pelajaran</td></tr>';

            $c.html(`
                <div class="ex-card ex-slide-up">
                    <div class="ex-card-header">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            Daftar Mata Pelajaran
                        </h3>
                        <div class="ex-toolbar">
                            <button class="btn btn-primary btn-sm" onclick="Exam.addMapel()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Tambah Mapel
                            </button>
                        </div>
                    </div>
                    <div class="ex-card-body" style="padding:0;">
                        <div class="ex-table-wrapper">
                            <table class="ex-table">
                                <thead><tr><th style="width:50px">#</th><th>Nama Mata Pelajaran</th><th>Kode</th><th style="width:100px">Aksi</th></tr></thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `);
        });
    },

    addMapel() {
        EModal.form({
            title: 'Tambah Mata Pelajaran',
            form: `
                <div class="form-group"><label class="form-label">Nama Mapel *</label><input type="text" class="form-input" id="fMapelNama" placeholder="Contoh: Matematika"></div>
                <div class="form-group"><label class="form-label">Kode</label><input type="text" class="form-input" id="fMapelKode" placeholder="Contoh: MTK"></div>
            `,
            onConfirm: () => {
                const nama = $('#fMapelNama').val().trim();
                if (!nama) { EModal.toast({type:'error',title:'Nama mapel wajib diisi'}); return false; }
                this.api('bank_soal.php?action=create_mapel', { method:'POST', data:{ nama_mapel: nama, kode: $('#fMapelKode').val().trim() } }).then(r => {
                    if (r.success) { EModal.toast({type:'success',title:'Berhasil',message:r.message}); this.navigate('mapel'); }
                    else EModal.toast({type:'error',title:'Gagal',message:r.message});
                });
            }
        });
    },

    editMapel(id, nama, kode) {
        EModal.form({
            title: 'Edit Mata Pelajaran',
            form: `
                <div class="form-group"><label class="form-label">Nama Mapel *</label><input type="text" class="form-input" id="fMapelNama" value="${this.esc(nama)}"></div>
                <div class="form-group"><label class="form-label">Kode</label><input type="text" class="form-input" id="fMapelKode" value="${this.esc(kode)}"></div>
            `,
            onConfirm: () => {
                const n = $('#fMapelNama').val().trim();
                if (!n) { EModal.toast({type:'error',title:'Nama mapel wajib diisi'}); return false; }
                this.api('bank_soal.php?action=update_mapel', { method:'POST', data:{ id, nama_mapel: n, kode: $('#fMapelKode').val().trim() } }).then(r => {
                    if (r.success) { EModal.toast({type:'success',title:'Berhasil'}); this.navigate('mapel'); }
                    else EModal.toast({type:'error',title:'Gagal',message:r.message});
                });
            }
        });
    },

    deleteMapel(id, nama) {
        EModal.confirm({
            title: 'Hapus Mata Pelajaran',
            message: `Yakin hapus <strong>${this.esc(nama)}</strong>?`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('bank_soal.php?action=delete_mapel', { method:'POST', data:{ id } }).then(r => {
                    if (r.success) { EModal.toast({type:'success',title:'Berhasil'}); this.navigate('mapel'); }
                    else EModal.alert('Gagal', r.message);
                });
            }
        });
    },

    // ==========================================
    // BANK SOAL
    // ==========================================
    renderBankSoal($c) {
        this.api('bank_soal.php?action=list_bank').then(r => {
            if (!r.success) { $c.html('<p>Error</p>'); return; }
            const data = r.data || [];

            let cards = data.map(b => {
                const jenisLabel = b.jenis === 'psikologi' ? '<span class="ex-badge ex-badge-purple">Psikologi</span>' : '<span class="ex-badge ex-badge-blue">Penilaian</span>';
                const katLabel = b.kategori_ujian ? `<span class="ex-badge ex-badge-gray">${this.esc(b.kategori_ujian)}</span>` : '';
                return `
                <div class="ex-soal-card" style="cursor:pointer" onclick="Exam.navigate('detail_bank', {id:${b.id}})">
                    <div class="ex-soal-actions">
                        <button class="ex-btn-icon" onclick="event.stopPropagation(); Exam.editBank(${b.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="ex-btn-icon danger" onclick="event.stopPropagation(); Exam.deleteBank(${b.id}, '${this.esc(b.judul)}')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                    <h4 style="font-family:var(--font-heading);font-weight:700;margin-bottom:8px;padding-right:80px;">${this.esc(b.judul)}</h4>
                    <p style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:12px;">${this.esc(b.nama_mapel || '-')} ${b.kelas ? '• Kelas ' + this.esc(b.kelas) : ''}</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        ${jenisLabel} ${katLabel}
                        <span class="ex-badge ex-badge-green">${b.jumlah_soal || 0} soal</span>
                        ${b.tahun_ajaran ? '<span class="ex-badge ex-badge-gray">' + this.esc(b.tahun_ajaran) + ' / Smt ' + this.esc(b.semester) + '</span>' : ''}
                    </div>
                </div>`;
            }).join('');

            if (!cards) cards = `<div class="ex-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><h3>Belum ada Bank Soal</h3><p>Buat bank soal baru untuk mulai menambahkan soal.</p></div>`;

            $c.html(`
                <div class="ex-card ex-slide-up">
                    <div class="ex-card-header">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Daftar Bank Soal
                        </h3>
                        <div class="ex-toolbar">
                            <button class="btn btn-primary btn-sm" onclick="Exam.addBank()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Buat Bank Soal
                            </button>
                        </div>
                    </div>
                    <div class="ex-card-body">${cards}</div>
                </div>
            `);
        });
    },

    addBank() {
        const mapelOpts = this.state.mapelList.map(m => `<option value="${m.id}">${this.esc(m.nama_mapel)}</option>`).join('');
        const classOpts = this.state.classList.map(c => `<option value="${this.esc(c.kelas)}">${this.esc(c.kelas)} (${c.total_siswa} siswa)</option>`).join('');
        
        EModal.form({
            title: 'Buat Bank Soal Baru',
            size: 'lg',
            form: `
                <div class="ex-form-row">
                    <div class="form-group"><label class="form-label">Mata Pelajaran *</label><select class="form-select" id="fBankMapel"><option value="">-- Pilih --</option>${mapelOpts}</select></div>
                    <div class="form-group"><label class="form-label">Jenis</label><select class="form-select" id="fBankJenis"><option value="penilaian">Tes Penilaian</option><option value="psikologi">Tes Psikologi</option></select></div>
                </div>
                <div class="form-group"><label class="form-label">Judul Bank Soal *</label><input type="text" class="form-input" id="fBankJudul" placeholder="Contoh: UHB Matematika Kelas X Semester 1"></div>
                <div class="ex-form-row three">
                    <div class="form-group"><label class="form-label">Kategori Ujian</label><input type="text" class="form-input" id="fBankKategori" placeholder="UHB, STS, SAS, dll"></div>
                    <div class="form-group"><label class="form-label">Tahun Ajaran</label><input type="text" class="form-input" id="fBankTahun" placeholder="2025/2026"></div>
                    <div class="form-group"><label class="form-label">Semester</label><select class="form-select" id="fBankSemester"><option value="1">Semester 1</option><option value="2">Semester 2</option></select></div>
                </div>
                <div class="form-group"><label class="form-label">Kelas</label><select class="form-select" id="fBankKelas"><option value="">-- Semua Kelas --</option>${classOpts}</select></div>
            `,
            onConfirm: () => {
                const mapelId = $('#fBankMapel').val();
                const judul = $('#fBankJudul').val().trim();
                if (!mapelId || !judul) { EModal.toast({type:'error',title:'Mapel dan judul wajib diisi'}); return false; }
                this.api('bank_soal.php?action=create_bank', { method:'POST', data:{
                    mapel_id: mapelId, judul, jenis: $('#fBankJenis').val(),
                    kategori_ujian: $('#fBankKategori').val().trim(),
                    tahun_ajaran: $('#fBankTahun').val().trim(),
                    semester: $('#fBankSemester').val(),
                    kelas: $('#fBankKelas').val()
                }}).then(r => {
                    if (r.success) { EModal.toast({type:'success',title:'Berhasil',message:r.message}); this.navigate('detail_bank', {id: r.data.id}); }
                    else EModal.toast({type:'error',title:'Gagal',message:r.message});
                });
            }
        });
    },

    editBank(id) {
        this.api('bank_soal.php?action=get_bank&id=' + id).then(r => {
            if (!r.success) { EModal.alert('Error', r.message); return; }
            const b = r.data;
            const mapelOpts = this.state.mapelList.map(m => `<option value="${m.id}" ${m.id == b.mapel_id ? 'selected':''}>${this.esc(m.nama_mapel)}</option>`).join('');
            const classOpts = this.state.classList.map(c => `<option value="${this.esc(c.kelas)}" ${c.kelas === b.kelas ? 'selected':''}>${this.esc(c.kelas)}</option>`).join('');

            EModal.form({
                title: 'Edit Bank Soal',
                size: 'lg',
                form: `
                    <div class="ex-form-row">
                        <div class="form-group"><label class="form-label">Mata Pelajaran *</label><select class="form-select" id="fBankMapel"><option value="">-- Pilih --</option>${mapelOpts}</select></div>
                        <div class="form-group"><label class="form-label">Jenis</label><select class="form-select" id="fBankJenis"><option value="penilaian" ${b.jenis==='penilaian'?'selected':''}>Tes Penilaian</option><option value="psikologi" ${b.jenis==='psikologi'?'selected':''}>Tes Psikologi</option></select></div>
                    </div>
                    <div class="form-group"><label class="form-label">Judul *</label><input type="text" class="form-input" id="fBankJudul" value="${this.esc(b.judul)}"></div>
                    <div class="ex-form-row three">
                        <div class="form-group"><label class="form-label">Kategori</label><input type="text" class="form-input" id="fBankKategori" value="${this.esc(b.kategori_ujian || '')}"></div>
                        <div class="form-group"><label class="form-label">Tahun Ajaran</label><input type="text" class="form-input" id="fBankTahun" value="${this.esc(b.tahun_ajaran || '')}"></div>
                        <div class="form-group"><label class="form-label">Semester</label><select class="form-select" id="fBankSemester"><option value="1" ${b.semester==='1'?'selected':''}>Semester 1</option><option value="2" ${b.semester==='2'?'selected':''}>Semester 2</option></select></div>
                    </div>
                    <div class="form-group"><label class="form-label">Kelas</label><select class="form-select" id="fBankKelas"><option value="">-- Semua Kelas --</option>${classOpts}</select></div>
                `,
                onConfirm: () => {
                    this.api('bank_soal.php?action=update_bank', { method:'POST', data:{
                        id, mapel_id: $('#fBankMapel').val(), judul: $('#fBankJudul').val().trim(),
                        jenis: $('#fBankJenis').val(), kategori_ujian: $('#fBankKategori').val().trim(),
                        tahun_ajaran: $('#fBankTahun').val().trim(), semester: $('#fBankSemester').val(), kelas: $('#fBankKelas').val()
                    }}).then(r => {
                        if (r.success) { EModal.toast({type:'success',title:'Berhasil'}); this.navigate('bank_soal'); }
                        else EModal.toast({type:'error',title:'Gagal',message:r.message});
                    });
                }
            });
        });
    },

    deleteBank(id, judul) {
        EModal.confirm({
            title: 'Hapus Bank Soal', message: `Yakin hapus <strong>${this.esc(judul)}</strong> beserta semua soalnya?`,
            type: 'danger', confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('bank_soal.php?action=delete_bank', { method:'POST', data:{id} }).then(r => {
                    if (r.success) { EModal.toast({type:'success',title:'Berhasil'}); this.navigate('bank_soal'); }
                    else EModal.alert('Gagal', r.message);
                });
            }
        });
    },

    // ==========================================
    // DETAIL BANK SOAL (List Soal)
    // ==========================================
    renderDetailBank($c) {
        const bankId = this.state.params.id;
        if (!bankId) { this.navigate('bank_soal'); return; }

        Promise.all([
            this.api('bank_soal.php?action=get_bank&id=' + bankId),
            this.api('bank_soal.php?action=list_soal&bank_soal_id=' + bankId)
        ]).then(([bankR, soalR]) => {
            if (!bankR.success) { $c.html('<p>Bank soal tidak ditemukan</p>'); return; }
            const bank = bankR.data;
            const soalList = soalR.data || [];

            this.state.currentBankJenis = bank.jenis;
            this.state.currentBankId = bank.id;

            let soalCards = soalList.map((s, i) => {
                const tipeBadge = this.tipeSoalBadge(s.tipe_soal);
                let opsiHtml = '';
                
                if (['pilihan_satu','pilihan_banyak','benar_salah'].includes(s.tipe_soal)) {
                    const opsi = typeof s.opsi === 'string' ? JSON.parse(s.opsi || '[]') : (s.opsi || []);
                    const kunci = typeof s.kunci_jawaban === 'string' ? JSON.parse(s.kunci_jawaban || '""') : (s.kunci_jawaban || '');
                    const kunciArr = Array.isArray(kunci) ? kunci : [kunci];
                    
                    opsiHtml = '<div class="ex-soal-opsi">' + opsi.map(o => {
                        const isCorrect = bank.jenis === 'psikologi' ? false : kunciArr.includes(o.label);
                        const scoreLabel = (bank.jenis === 'psikologi' && o.score !== undefined) ? ` <span style="color:#7C3AED; font-weight:600;">(Skor: ${o.score})</span>` : '';
                        return `<div class="ex-opsi-item ${isCorrect ? 'correct' : ''}">
                            <span class="ex-opsi-label">${this.esc(o.label)}</span>
                            <span>${this.esc(o.text || o.teks)} ${scoreLabel}</span>
                        </div>`;
                    }).join('') + '</div>';
                }

                return `
                <div class="ex-soal-card ex-slide-up" style="animation-delay:${i*0.05}s">
                    <div class="ex-soal-actions">
                        <button class="ex-btn-icon" onclick="Exam.editSoal(${s.id}, ${bankId})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="ex-btn-icon danger" onclick="Exam.deleteSoal(${s.id}, ${bankId})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                    <div class="ex-soal-header">
                        <span class="ex-soal-number">${i+1}</span>
                        <div class="ex-soal-pertanyaan">${s.pertanyaan}</div>
                    </div>
                    ${s.gambar ? `<div style="padding-left:44px;margin-bottom:12px;"><img src="${this.state.moduleUrl + s.gambar}" style="max-width:300px;border-radius:8px;border:1px solid var(--bg-dark);" alt="gambar soal"></div>` : ''}
                    ${s.audio ? `<div style="padding-left:44px;margin-bottom:12px;"><audio controls style="max-width:100%"><source src="${this.state.moduleUrl + s.audio}" type="audio/mpeg"></audio></div>` : ''}
                    ${opsiHtml}
                    <div class="ex-soal-meta">
                        ${tipeBadge}
                        <span class="ex-badge ex-badge-gray">Bobot: ${s.bobot}</span>
                        ${s.audio ? '<span class="ex-badge ex-badge-purple">🔊 Audio</span>' : ''}
                    </div>
                </div>`;
            }).join('');

            if (!soalCards) soalCards = `<div class="ex-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><h3>Belum ada soal</h3><p>Tambahkan soal baru atau import dari file.</p></div>`;

            const psikologiBtn = bank.jenis === 'psikologi' ? `
                <button class="btn btn-outline btn-sm" onclick="Exam.managePsikologiHasil(${bankId})" style="border-color:#7C3AED;color:#7C3AED;display:inline-flex;align-items:center;gap:6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
                    Hasil Psikologi
                </button>
            ` : '';

            $c.html(`
                <div style="margin-bottom:20px;">
                    <button class="btn btn-ghost btn-sm" onclick="Exam.navigate('bank_soal')" style="display:inline-flex;align-items:center;gap:6px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        Kembali
                    </button>
                </div>
                <div class="ex-card ex-slide-up">
                    <div class="ex-card-header">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            ${this.esc(bank.judul)}
                            <span class="ex-badge ex-badge-green" style="margin-left:8px;">${soalList.length} soal</span>
                        </h3>
                        <div class="ex-toolbar">
                            ${psikologiBtn}
                            <button class="btn btn-outline btn-sm" onclick="Exam.importSoalModal(${bankId})">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                Import
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="Exam.addSoalModal(${bankId})">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Tambah Soal
                            </button>
                        </div>
                    </div>
                    <div class="ex-card-body">${soalCards}</div>
                </div>
            `);

            // Math auto-render using KaTeX
            if (window.renderMathInElement) {
                setTimeout(() => {
                    renderMathInElement(document.getElementById('mainContent'), {
                        delimiters: [
                            {left: "$$", right: "$$", display: true},
                            {left: "$", right: "$", display: false},
                            {left: "\\(", right: "\\)", display: false},
                            {left: "\\[", right: "\\]", display: true}
                        ]
                    });
                }, 100);
            }
        });
    },

    tipeSoalBadge(tipe) {
        const map = {
            benar_salah: ['Benar/Salah', 'blue'],
            menjodohkan: ['Menjodohkan', 'purple'],
            pilihan_satu: ['Pilihan Ganda', 'blue'],
            pilihan_banyak: ['Pilihan Banyak', 'purple'],
            jawaban_singkat: ['Jawaban Singkat', 'yellow'],
            esai: ['Esai', 'green']
        };
        const [label, color] = map[tipe] || [tipe, 'gray'];
        return `<span class="ex-badge ex-badge-${color}">${label}</span>`;
    },

    // ==========================================
    // ADD / EDIT SOAL
    // ==========================================
    addSoalModal(bankId) {
        this.showSoalForm(bankId, null);
    },

    editSoal(soalId, bankId) {
        this.api('bank_soal.php?action=list_soal&bank_soal_id=' + bankId).then(r => {
            const soal = (r.data || []).find(s => s.id == soalId);
            if (soal) this.showSoalForm(bankId, soal);
            else EModal.alert('Error', 'Soal tidak ditemukan');
        });
    },

    showSoalForm(bankId, existing) {
        const isEdit = !!existing;
        const isPsikologi = (this.state.currentBankJenis === 'psikologi');
        
        const tipe = existing ? existing.tipe_soal : 'pilihan_satu';
        const pertanyaan = existing ? existing.pertanyaan : '';
        const pembahasan = existing ? (existing.pembahasan || '') : '';
        const bobot = existing ? existing.bobot : 1;
        let opsi = [];
        let kunci = '';
        if (existing) {
            opsi = typeof existing.opsi === 'string' ? JSON.parse(existing.opsi || '[]') : (existing.opsi || []);
            kunci = typeof existing.kunci_jawaban === 'string' ? JSON.parse(existing.kunci_jawaban || '""') : (existing.kunci_jawaban || '');
        }

        const tipeOptions = isPsikologi ? [
            ['pilihan_satu', 'Pilihan Ganda (Psikotes)']
        ] : [
            ['pilihan_satu', 'Pilihan Ganda (1 benar)'],
            ['pilihan_banyak', 'Pilihan Ganda (> 1 benar)'],
            ['benar_salah', 'Benar / Salah'],
            ['jawaban_singkat', 'Jawaban Singkat'],
            ['esai', 'Esai'],
            ['menjodohkan', 'Menjodohkan'],
        ];

        const tipeSelect = tipeOptions.map(([v,l]) => `<option value="${v}" ${v===tipe?'selected':''}>${l}</option>`).join('');

        EModal.form({
            title: isEdit ? 'Edit Soal' : 'Tambah Soal',
            size: 'lg',
            form: `
                <div class="ex-form-row">
                    <div class="form-group"><label class="form-label">Tipe Soal *</label><select class="form-select" id="fSoalTipe" ${isPsikologi ? 'disabled' : ''} onchange="Exam.toggleSoalForm()">${tipeSelect}</select></div>
                    <div class="form-group"><label class="form-label">Bobot</label><input type="number" class="form-input" id="fSoalBobot" value="${bobot}" min="0.1" step="0.1"></div>
                </div>
                <div class="form-group"><label class="form-label">Pertanyaan *</label><textarea class="form-input" id="fSoalPertanyaan" rows="4" placeholder="Tulis pertanyaan di sini...">${this.esc(pertanyaan)}</textarea></div>
                <div class="ex-form-row">
                    <div class="form-group">
                        <label class="form-label">Gambar Soal (opsional)</label>
                        <input type="file" class="form-input" id="fSoalGambarFile" accept="image/*">
                        ${existing && existing.gambar ? `<div style="margin-top:6px; font-size:12px; color:#3b82f6;">Saat ini: ${existing.gambar}</div>` : ''}
                    </div>
                    <div class="form-group">
                        <label class="form-label">Audio Listening (opsional)</label>
                        <input type="file" class="form-input" id="fSoalAudioFile" accept="audio/*">
                        ${existing && existing.audio ? `<div style="margin-top:6px; font-size:12px; color:#7c3aed;">Saat ini: ${existing.audio}</div>` : ''}
                    </div>
                </div>
                <div id="fSoalOpsiContainer" style="margin-top:16px;"></div>
                <div id="fSoalKunciContainer"></div>
                <div class="form-group"><label class="form-label">Pembahasan (opsional)</label><textarea class="form-input" id="fSoalPembahasan" rows="2">${this.esc(pembahasan)}</textarea></div>
            `,
            onOpen: () => {
                window._examSoalOpsi = opsi;
                window._examSoalKunci = kunci;
                this.toggleSoalForm();
            },
            onConfirm: () => {
                const data = this.collectSoalFormData();
                if (!data) return false;
                data.bank_soal_id = bankId;
                if (isEdit) {
                    data.id = existing.id;
                    data.gambar = existing.gambar;
                    data.audio = existing.audio;
                }

                const imgFile = document.getElementById('fSoalGambarFile').files[0];
                const audFile = document.getElementById('fSoalAudioFile').files[0];

                const uploadPromises = [];
                const loader = EModal.loading('Menyimpan Soal...');

                if (imgFile) {
                    uploadPromises.push(
                        this.uploadFile(imgFile, 'image').then(r => {
                            if (r.success) data.gambar = r.data.path;
                        })
                    );
                }
                if (audFile) {
                    uploadPromises.push(
                        this.uploadFile(audFile, 'audio').then(r => {
                            if (r.success) data.audio = r.data.path;
                        })
                    );
                }

                Promise.all(uploadPromises).then(() => {
                    const action = isEdit ? 'update_soal' : 'create_soal';
                    this.api('bank_soal.php?action=' + action, { method:'POST', data }).then(r => {
                        EModal.close(loader);
                        if (r.success) { EModal.toast({type:'success',title:'Berhasil'}); this.navigate('detail_bank', {id: bankId}); }
                        else EModal.toast({type:'error',title:'Gagal',message:r.message});
                    }).fail(() => {
                        EModal.close(loader);
                        EModal.toast({type:'error',title:'Gagal menyimpan soal'});
                    });
                }).catch(() => {
                    EModal.close(loader);
                    EModal.toast({type:'error',title:'Gagal mengupload media'});
                });
                return false;
            }
        });
    },

    toggleSoalForm() {
        const tipe = $('#fSoalTipe').val();
        const opsi = window._examSoalOpsi || [];
        const kunci = window._examSoalKunci || '';
        const kunciArr = Array.isArray(kunci) ? kunci : [kunci];
        const isPsikologi = (this.state.currentBankJenis === 'psikologi');

        let opsiHtml = '';
        let kunciHtml = '';

        if (['pilihan_satu','pilihan_banyak'].includes(tipe)) {
            const labels = ['A','B','C','D','E'];
            const defaultOpsi = opsi.length > 0 ? opsi : labels.map(l => ({label:l, text:'', score: 0}));
            opsiHtml = '<div class="form-group"><label class="form-label">Pilihan Jawaban</label>';
            defaultOpsi.forEach(o => {
                const scoreValue = o.score !== undefined ? o.score : (o.nilai !== undefined ? o.nilai : 0);
                opsiHtml += `<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                    <span style="width:28px;height:28px;background:var(--bg-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;">${o.label}</span>
                    <input type="text" class="form-input ex-opsi-input" data-label="${o.label}" value="${this.esc(o.text || o.teks || '')}" placeholder="Isi opsi ${o.label}" style="flex:1;">
                    ${isPsikologi ? `<input type="number" class="form-input ex-opsi-score" data-label="${o.label}" value="${scoreValue}" placeholder="Skor" style="width:80px;" min="0" step="1">` : ''}
                </div>`;
            });
            opsiHtml += '</div>';

            if (!isPsikologi) {
                if (tipe === 'pilihan_satu') {
                    kunciHtml = `<div class="form-group"><label class="form-label">Kunci Jawaban *</label><select class="form-select" id="fSoalKunci">
                        ${defaultOpsi.map(o => `<option value="${o.label}" ${kunciArr.includes(o.label)?'selected':''}>${o.label}</option>`).join('')}
                    </select></div>`;
                } else {
                    kunciHtml = `<div class="form-group"><label class="form-label">Kunci Jawaban (pilih yang benar) *</label><div>
                        ${defaultOpsi.map(o => `<label style="display:inline-flex;align-items:center;gap:6px;margin-right:16px;font-size:0.85rem;">
                            <input type="checkbox" class="ex-kunci-cb" value="${o.label}" ${kunciArr.includes(o.label)?'checked':''}>
                            ${o.label}
                        </label>`).join('')}
                    </div></div>`;
                }
            }
        } else if (tipe === 'benar_salah') {
            opsiHtml = '';
            kunciHtml = `<div class="form-group"><label class="form-label">Kunci Jawaban *</label><select class="form-select" id="fSoalKunci">
                <option value="Benar" ${kunci==='Benar'?'selected':''}>Benar</option>
                <option value="Salah" ${kunci==='Salah'?'selected':''}>Salah</option>
            </select></div>`;
        } else if (tipe === 'jawaban_singkat') {
            kunciHtml = `<div class="form-group"><label class="form-label">Kunci Jawaban *</label><input type="text" class="form-input" id="fSoalKunci" value="${this.esc(typeof kunci === 'string' ? kunci : '')}" placeholder="Jawaban yang benar"></div>`;
        } else if (tipe === 'esai') {
            kunciHtml = `<div class="form-group"><label class="form-label">Kunci / Rubrik Jawaban (untuk referensi AI koreksi)</label><textarea class="form-input" id="fSoalKunci" rows="3" placeholder="Tulis poin-poin jawaban yang diharapkan...">${this.esc(typeof kunci === 'string' ? kunci : '')}</textarea></div>`;
        } else if (tipe === 'menjodohkan') {
            const pairs = opsi.length > 0 ? opsi : [{left:'',right:''},{left:'',right:''},{left:'',right:''}];
            opsiHtml = `<div class="form-group"><label class="form-label">Pasangan (Kiri → Kanan)</label><div id="fMenjodohkanPairs">`;
            pairs.forEach((p, i) => {
                opsiHtml += `<div style="display:flex;gap:8px;margin-bottom:8px;" class="ex-pair-row">
                    <input type="text" class="form-input ex-pair-left" value="${this.esc(p.left || '')}" placeholder="Pernyataan ${i+1}" style="flex:1;">
                    <span style="display:flex;align-items:center;font-weight:700;color:var(--text-muted);">→</span>
                    <input type="text" class="form-input ex-pair-right" value="${this.esc(p.right || '')}" placeholder="Jawaban ${i+1}" style="flex:1;">
                </div>`;
            });
            opsiHtml += `</div><button type="button" class="btn btn-ghost btn-sm" onclick="Exam.addPairRow()">+ Tambah pasangan</button></div>`;
        }

        $('#fSoalOpsiContainer').html(opsiHtml);
        $('#fSoalKunciContainer').html(kunciHtml);
    },

    addPairRow() {
        const idx = $('.ex-pair-row').length + 1;
        $('#fMenjodohkanPairs').append(`<div style="display:flex;gap:8px;margin-bottom:8px;" class="ex-pair-row">
            <input type="text" class="form-input ex-pair-left" placeholder="Pernyataan ${idx}" style="flex:1;">
            <span style="display:flex;align-items:center;font-weight:700;color:var(--text-muted);">→</span>
            <input type="text" class="form-input ex-pair-right" placeholder="Jawaban ${idx}" style="flex:1;">
        </div>`);
    },

    collectSoalFormData() {
        const tipe = $('#fSoalTipe').val();
        const pertanyaan = $('#fSoalPertanyaan').val().trim();
        const bobot = parseFloat($('#fSoalBobot').val()) || 1;
        const pembahasan = $('#fSoalPembahasan').val().trim();
        const isPsikologi = (Exam.state.currentBankJenis === 'psikologi');

        if (!pertanyaan) { EModal.toast({type:'error',title:'Pertanyaan wajib diisi'}); return null; }

        let opsi = null;
        let kunci_jawaban = null;

        if (['pilihan_satu','pilihan_banyak'].includes(tipe)) {
            opsi = [];
            $('.ex-opsi-input').each(function() {
                const label = $(this).data('label');
                const text = $(this).val().trim();
                if (text) {
                    if (isPsikologi) {
                        const score = parseFloat($(`.ex-opsi-score[data-label="${label}"]`).val()) || 0;
                        opsi.push({label, text, score});
                    } else {
                        opsi.push({label, text});
                    }
                }
            });
            if (opsi.length < 2) { EModal.toast({type:'error',title:'Minimal 2 opsi jawaban'}); return null; }

            if (isPsikologi) {
                kunci_jawaban = null;
            } else {
                if (tipe === 'pilihan_satu') {
                    kunci_jawaban = $('#fSoalKunci').val();
                } else {
                    kunci_jawaban = [];
                    $('.ex-kunci-cb:checked').each(function() { kunci_jawaban.push($(this).val()); });
                    if (kunci_jawaban.length === 0) { EModal.toast({type:'error',title:'Pilih minimal 1 kunci jawaban'}); return null; }
                }
            }
        } else if (tipe === 'benar_salah') {
            opsi = [{label:'Benar',text:'Benar'},{label:'Salah',text:'Salah'}];
            kunci_jawaban = $('#fSoalKunci').val();
        } else if (tipe === 'jawaban_singkat') {
            kunci_jawaban = $('#fSoalKunci').val().trim();
        } else if (tipe === 'esai') {
            kunci_jawaban = $('#fSoalKunci').val().trim();
        } else if (tipe === 'menjodohkan') {
            opsi = [];
            $('.ex-pair-row').each(function() {
                const left = $(this).find('.ex-pair-left').val().trim();
                const right = $(this).find('.ex-pair-right').val().trim();
                if (left && right) opsi.push({left, right});
            });
            if (opsi.length < 2) { EModal.toast({type:'error',title:'Minimal 2 pasangan'}); return null; }
            kunci_jawaban = opsi;
        }

        return { tipe_soal: tipe, pertanyaan, opsi, kunci_jawaban, pembahasan, bobot };
    },

    deleteSoal(id, bankId) {
        EModal.confirm({
            title: 'Hapus Soal', message: 'Yakin ingin menghapus soal ini?',
            type: 'danger', confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('bank_soal.php?action=delete_soal', { method:'POST', data:{id} }).then(r => {
                    if (r.success) { EModal.toast({type:'success',title:'Berhasil'}); this.navigate('detail_bank', {id: bankId}); }
                    else EModal.alert('Gagal', r.message);
                });
            }
        });
    },

    importSoalModal(bankId) {
        EModal.form({
            title: 'Import Soal',
            form: `
                <div class="form-group">
                    <label class="form-label">Upload File (CSV atau JSON)</label>
                    <input type="file" class="form-input" id="fImportFile" accept=".csv,.json">
                </div>
                <div style="background:var(--bg-light);padding:16px;border-radius:10px;font-size:0.8rem;color:var(--text-secondary);line-height:1.7;">
                    <strong>Format CSV:</strong><br>
                    Header: tipe_soal, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci, pembahasan, bobot<br><br>
                    <strong>Format JSON:</strong> Array objek dengan field: tipe_soal, pertanyaan, opsi [{label,text}], kunci_jawaban, pembahasan, bobot
                </div>
            `,
            onConfirm: () => {
                const fileInput = document.getElementById('fImportFile');
                if (!fileInput.files.length) { EModal.toast({type:'error',title:'Pilih file terlebih dahulu'}); return false; }

                const formData = new FormData();
                formData.append('file', fileInput.files[0]);
                formData.append('bank_soal_id', bankId);

                const loader = EModal.loading('Mengimport soal...');
                this.api('bank_soal.php?action=import_soal', { method:'POST', data: formData }).then(r => {
                    EModal.close(loader);
                    if (r.success) { EModal.info({type:'success',title:'Import Berhasil',message:`${r.data.imported} soal berhasil diimport.`}); this.navigate('detail_bank', {id: bankId}); }
                    else EModal.alert('Import Gagal', r.message);
                }).fail(() => { EModal.close(loader); EModal.alert('Error', 'Gagal mengupload file'); });
            }
        });
    },

    // ==========================================
    // KELOLA UJIAN (placeholder for Phase 3)
    // ==========================================
    renderUjian($c) {
        this.api('ujian.php?action=list').then(r => {
            if (!r.success) { $c.html('<p>Error</p>'); return; }
            const data = r.data || [];

            let rows = data.map((u, i) => {
                let statusBadge = '';
                if (u.status === 'draft') statusBadge = '<span class="ex-badge ex-badge-gray">Draft</span>';
                else if (u.status === 'aktif') statusBadge = '<span class="ex-badge ex-badge-green">Aktif</span>';
                else if (u.status === 'selesai') statusBadge = '<span class="ex-badge ex-badge-blue">Selesai</span>';

                let tokenHtml = u.status === 'aktif' 
                    ? `<div style="font-family:monospace;font-weight:bold;color:var(--primary);font-size:1.1rem;">
                        ${u.token}
                        <button class="ex-btn-icon" style="display:inline-flex;margin-left:4px;" onclick="Exam.refreshToken(${u.id})" title="Refresh Token">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                        </button>
                       </div>` 
                    : '-';

                return `
                <tr>
                    <td>${i+1}</td>
                    <td>
                        <strong>${this.esc(u.judul)}</strong><br>
                        <span style="font-size:0.8rem;color:var(--text-secondary);">${this.esc(u.nama_bank_soal)}</span>
                    </td>
                    <td>${this.esc(u.kelas_peserta || '-')}</td>
                    <td>${u.durasi_menit} Menit</td>
                    <td>${statusBadge}</td>
                    <td>${tokenHtml}</td>
                    <td>${u.total_peserta || 0} Siswa</td>
                    <td>
                        <div class="ex-actions">
                            ${u.status === 'draft' ? `
                                <button class="ex-btn-icon" onclick="Exam.editUjian(${u.id})" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                <button class="ex-btn-icon success" onclick="Exam.setUjianStatus(${u.id}, 'aktif')" title="Aktifkan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg></button>
                            ` : ''}
                            ${u.status === 'aktif' ? `
                                <button class="ex-btn-icon danger" onclick="Exam.setUjianStatus(${u.id}, 'selesai')" title="Selesaikan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/></svg></button>
                            ` : ''}
                            <button class="ex-btn-icon danger" onclick="Exam.deleteUjian(${u.id}, '${this.esc(u.judul)}')" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');

            if (!rows) rows = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">Belum ada jadwal ujian</td></tr>';

            $c.html(`
                <div class="ex-card ex-slide-up">
                    <div class="ex-card-header">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Kelola Jadwal Ujian
                        </h3>
                        <div class="ex-toolbar">
                            <button class="btn btn-primary btn-sm" onclick="Exam.addUjian()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Buat Ujian
                            </button>
                        </div>
                    </div>
                    <div class="ex-card-body" style="padding:0;">
                        <div class="ex-table-wrapper">
                            <table class="ex-table">
                                <thead><tr><th style="width:40px">#</th><th>Judul & Bank Soal</th><th>Kelas Peserta</th><th>Durasi</th><th>Status</th><th>TOKEN</th><th>Peserta</th><th style="width:120px">Aksi</th></tr></thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `);
        });
    },

    addUjian() {
        this.api('ujian.php?action=options_bank').then(r => {
            const bankList = r.data || [];
            if(bankList.length === 0) { EModal.alert('Perhatian', 'Buat Bank Soal terlebih dahulu.'); return; }
            
            const bankOpts = bankList.map(b => `<option value="${b.id}">${this.esc(b.judul)} (${b.jenis})</option>`).join('');
            const classOpts = this.state.classList.map(c => `<label style="display:inline-flex;align-items:center;gap:6px;width:120px;margin-bottom:8px;"><input type="checkbox" class="ex-ujian-kelas-cb" value="${this.esc(c.kelas)}"> ${this.esc(c.kelas)}</label>`).join('');

            EModal.form({
                title: 'Buat Jadwal Ujian',
                size: 'lg',
                form: `
                    <div class="form-group"><label class="form-label">Judul Ujian *</label><input type="text" class="form-input" id="fUjianJudul" placeholder="Contoh: Ujian STS Genap Matematika"></div>
                    <div class="ex-form-row">
                        <div class="form-group"><label class="form-label">Bank Soal *</label><select class="form-select" id="fUjianBank"><option value="">-- Pilih Bank Soal --</option>${bankOpts}</select></div>
                        <div class="form-group"><label class="form-label">Durasi (Menit) *</label><input type="number" class="form-input" id="fUjianDurasi" value="60" min="1"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Kelas Peserta *</label><div>${classOpts}</div></div>
                    <div class="ex-form-row three" style="margin-top:16px;">
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" id="fUjianAcakSoal" checked> Acak Urutan Soal</label>
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" id="fUjianAcakOpsi" checked> Acak Opsi Jawaban</label>
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" id="fUjianTampilNilai"> Tampilkan Nilai Siswa</label>
                    </div>
                `,
                onConfirm: () => {
                    const bank_soal_id = $('#fUjianBank').val();
                    const judul = $('#fUjianJudul').val().trim();
                    const durasi_menit = $('#fUjianDurasi').val();
                    let kelas = [];
                    $('.ex-ujian-kelas-cb:checked').each(function(){ kelas.push($(this).val()); });

                    if(!judul || !bank_soal_id || kelas.length === 0) {
                        EModal.toast({type:'error', title:'Judul, Bank Soal, dan Kelas wajib diisi'}); return false;
                    }

                    const data = {
                        judul, bank_soal_id, durasi_menit, kelas,
                        acak_soal: $('#fUjianAcakSoal').is(':checked'),
                        acak_opsi: $('#fUjianAcakOpsi').is(':checked'),
                        tampil_nilai: $('#fUjianTampilNilai').is(':checked')
                    };

                    this.api('ujian.php?action=create', { method:'POST', data }).then(r => {
                        if (r.success) { EModal.toast({type:'success',title:'Berhasil'}); this.navigate('ujian'); }
                        else EModal.alert('Gagal', r.message);
                    });
                }
            });
        });
    },

    editUjian(id) {
        Promise.all([
            this.api('ujian.php?action=get&id=' + id),
            this.api('ujian.php?action=options_bank')
        ]).then(([rUjian, rBank]) => {
            if(!rUjian.success) { EModal.alert('Error', rUjian.message); return; }
            const u = rUjian.data;
            const bankList = rBank.data || [];
            
            // Note: Bank Soal cannot be changed after creation to maintain data integrity
            const classOpts = this.state.classList.map(c => {
                const checked = (u.kelas || []).includes(c.kelas) ? 'checked' : '';
                return `<label style="display:inline-flex;align-items:center;gap:6px;width:120px;margin-bottom:8px;"><input type="checkbox" class="ex-ujian-kelas-cb" value="${this.esc(c.kelas)}" ${checked}> ${this.esc(c.kelas)}</label>`;
            }).join('');

            EModal.form({
                title: 'Edit Ujian',
                size: 'lg',
                form: `
                    <div class="form-group"><label class="form-label">Judul Ujian *</label><input type="text" class="form-input" id="fUjianJudul" value="${this.esc(u.judul)}"></div>
                    <div class="ex-form-row">
                        <div class="form-group"><label class="form-label">Bank Soal (Readonly)</label><input type="text" class="form-input" value="${this.esc(u.nama_bank_soal)}" readonly style="background:#f1f5f9;"></div>
                        <div class="form-group"><label class="form-label">Durasi (Menit) *</label><input type="number" class="form-input" id="fUjianDurasi" value="${u.durasi_menit}" min="1"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Kelas Peserta *</label><div>${classOpts}</div></div>
                    <div class="ex-form-row three" style="margin-top:16px;">
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" id="fUjianAcakSoal" ${u.acak_soal ? 'checked':''}> Acak Urutan Soal</label>
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" id="fUjianAcakOpsi" ${u.acak_opsi ? 'checked':''}> Acak Opsi Jawaban</label>
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" id="fUjianTampilNilai" ${u.tampil_nilai ? 'checked':''}> Tampilkan Nilai Siswa</label>
                    </div>
                `,
                onConfirm: () => {
                    const judul = $('#fUjianJudul').val().trim();
                    const durasi_menit = $('#fUjianDurasi').val();
                    let kelas = [];
                    $('.ex-ujian-kelas-cb:checked').each(function(){ kelas.push($(this).val()); });

                    if(!judul || kelas.length === 0) {
                        EModal.toast({type:'error', title:'Judul dan Kelas wajib diisi'}); return false;
                    }

                    const data = {
                        id, judul, durasi_menit, kelas,
                        acak_soal: $('#fUjianAcakSoal').is(':checked'),
                        acak_opsi: $('#fUjianAcakOpsi').is(':checked'),
                        tampil_nilai: $('#fUjianTampilNilai').is(':checked')
                    };

                    this.api('ujian.php?action=update', { method:'POST', data }).then(r => {
                        if (r.success) { EModal.toast({type:'success',title:'Berhasil'}); this.navigate('ujian'); }
                        else EModal.alert('Gagal', r.message);
                    });
                }
            });
        });
    },

    setUjianStatus(id, status) {
        let msg = status === 'aktif' ? 'Yakin ingin <strong>mengaktifkan</strong> ujian ini? Token akan di-generate dan siswa dapat mulai mengerjakan.' : 'Yakin ingin <strong>menyelesaikan</strong> ujian ini? Siswa tidak bisa lagi masuk/mengerjakan.';
        
        EModal.confirm({
            title: status === 'aktif' ? 'Aktifkan Ujian' : 'Selesaikan Ujian',
            message: msg,
            type: status === 'aktif' ? 'primary' : 'danger',
            confirmText: 'Ya',
            onConfirm: () => {
                this.api('ujian.php?action=set_status', { method:'POST', data:{id, status} }).then(r => {
                    if (r.success) {
                        EModal.info({type:'success', title:'Berhasil', message: r.message});
                        this.navigate('ujian');
                    } else EModal.alert('Gagal', r.message);
                });
            }
        });
    },

    refreshToken(id) {
        EModal.confirm({
            title: 'Refresh Token',
            message: 'Yakin ingin me-refresh token ujian ini? Siswa yang belum masuk harus menggunakan token baru.',
            type: 'primary',
            confirmText: 'Ya, Refresh',
            onConfirm: () => {
                this.api('ujian.php?action=refresh_token', { method:'POST', data:{id} }).then(r => {
                    if (r.success) {
                        EModal.toast({type:'success', title:'Token Diperbarui'});
                        this.navigate('ujian');
                    } else EModal.alert('Gagal', r.message);
                });
            }
        });
    },

    deleteUjian(id, judul) {
        EModal.confirm({
            title: 'Hapus Ujian',
            message: `Yakin ingin menghapus jadwal <strong>${this.esc(judul)}</strong>?`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('ujian.php?action=delete', { method:'POST', data:{id} }).then(r => {
                    if (r.success) { EModal.toast({type:'success',title:'Berhasil dihapus'}); this.navigate('ujian'); }
                    else EModal.alert('Gagal', r.message);
                });
            }
        });
    },

    // ==========================================
    // LAPORAN & HASIL UJIAN
    // ==========================================
    renderLaporan($c) {
        this.api('ujian.php?action=list').then(r => {
            if (!r.success) { $c.html('<p>Error</p>'); return; }
            const data = r.data || [];
            
            // Only show exams that are active or finished
            const filtered = data.filter(u => u.status !== 'draft');

            let rows = filtered.map((u, i) => {
                let statusBadge = u.status === 'aktif' ? '<span class="ex-badge ex-badge-green">Aktif</span>' : '<span class="ex-badge ex-badge-blue">Selesai</span>';
                
                return `
                <tr>
                    <td>${i+1}</td>
                    <td>
                        <strong>${this.esc(u.judul)}</strong><br>
                        <span style="font-size:0.8rem;color:var(--text-secondary);">${this.esc(u.nama_bank_soal)}</span>
                    </td>
                    <td>${this.esc(u.kelas_peserta || '-')}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="Exam.detailLaporan(${u.id}, '${this.esc(u.judul)}')">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            Lihat Hasil
                        </button>
                    </td>
                </tr>`;
            }).join('');

            if (!rows) rows = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);">Belum ada ujian yang berjalan/selesai</td></tr>';

            $c.html(`
                <div class="ex-card ex-slide-up">
                    <div class="ex-card-header">
                        <h3>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            Laporan Hasil Ujian
                        </h3>
                    </div>
                    <div class="ex-card-body" style="padding:0;">
                        <div class="ex-table-wrapper">
                            <table class="ex-table">
                                <thead><tr><th style="width:40px">#</th><th>Judul Ujian</th><th>Kelas</th><th>Status Ujian</th><th style="width:150px">Aksi</th></tr></thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `);
        });
    },

    detailLaporan(ujianId, judul) {
        this.navigate('laporan', { id: ujianId });
        const $c = $('#mainContent');
        $c.html('<div style="text-align:center;padding:60px;"><div class="loading-spinner"></div></div>');
        
        this.api('laporan.php?action=hasil_ujian&ujian_id=' + ujianId).then(r => {
            if(!r.success) { $c.html('<p>Error memuat hasil</p>'); return; }
            const res = r.data || [];
            
            let rows = res.map((s, i) => {
                let statusSesi = '';
                if(s.status === 'mengerjakan') statusSesi = '<span class="ex-badge ex-badge-gray">Sedang Ujian</span>';
                else if(s.status === 'dihentikan') statusSesi = '<span class="ex-badge ex-badge-red">Dihentikan (Curang)</span>';
                else statusSesi = '<span class="ex-badge ex-badge-green">Selesai</span>';

                let skorHtml = s.skor !== null ? `<strong>${parseFloat(s.skor).toFixed(2)}</strong>` : '<span style="color:#94a3b8">Belum Dinilai</span>';

                return `
                <tr>
                    <td>${i+1}</td>
                    <td><strong>${this.esc(s.nama_siswa)}</strong><br><span style="font-size:12px;color:#64748b;">${this.esc(s.nis)}</span></td>
                    <td>${this.esc(s.kelas)}</td>
                    <td>${statusSesi}</td>
                    <td>${s.waktu_mulai ? s.waktu_mulai.substring(0,16) : '-'}</td>
                    <td>${skorHtml}</td>
                </tr>`;
            }).join('');

            if (!rows) rows = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">Belum ada siswa yang mengerjakan ujian ini</td></tr>';

            $c.html(`
                <button class="btn btn-primary btn-sm" style="margin-bottom:16px;" onclick="Exam.navigate('laporan')">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    Kembali
                </button>
                <div class="ex-card ex-slide-up">
                    <div class="ex-card-header" style="flex-wrap:wrap; gap:16px;">
                        <h3>Hasil: ${this.esc(judul)}</h3>
                        <div class="ex-toolbar">
                            <button class="btn btn-primary btn-sm" onclick="Exam.koreksiMasal(${ujianId})" style="background:#eab308;color:#854d0e;border:none;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                Auto Koreksi / AI Grading
                            </button>
                            <a href="${this.state.apiUrl}laporan.php?action=download_excel&ujian_id=${ujianId}&token=${this.state.token}" target="_blank" class="btn btn-primary btn-sm" style="background:#10b981;border:none;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Export Excel
                            </a>
                        </div>
                    </div>
                    <div class="ex-card-body" style="padding:0;">
                        <div class="ex-table-wrapper">
                            <table class="ex-table">
                                <thead><tr><th style="width:40px">#</th><th>Nama & NIS</th><th>Kelas</th><th>Status</th><th>Mulai</th><th>Skor (0-100)</th></tr></thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `);
        });
    },

    koreksiMasal(ujianId) {
        EModal.confirm({
            title: 'Koreksi Massal & AI Grading',
            message: 'Proses ini akan mengkoreksi seluruh jawaban siswa yang telah selesai ujian, termasuk memanggil Google Gemini API untuk jawaban esai/singkat. Lanjutkan?',
            type: 'primary',
            confirmText: 'Ya, Proses Sekarang',
            onConfirm: () => {
                const loader = EModal.loading('Memproses koreksi... Mohon tunggu (bisa memakan waktu)');
                this.api('koreksi.php?action=koreksi_masal', { method: 'POST', data: { ujian_id: ujianId } }).then(r => {
                    EModal.close(loader);
                    if (r.success) {
                        EModal.toast({type:'success', title:'Koreksi Selesai'});
                        this.detailLaporan(ujianId, this.state.params.judul || '');
                    } else {
                        EModal.alert('Gagal', r.message);
                    }
                }).catch(e => {
                    EModal.close(loader);
                    EModal.alert('Error', 'Terjadi kesalahan pada server');
                });
            }
        });
    },

    settingGemini() {
        this.api('koreksi.php?action=get_settings').then(r => {
            const currentKey = (r.data && r.data.gemini_api_key) ? r.data.gemini_api_key : '';
            EModal.form({
                title: 'Pengaturan Google Gemini AI',
                form: `
                    <div style="background:#eff6ff; color:#1e40af; padding:12px; border-radius:8px; font-size:13px; margin-bottom:16px;">
                        API Key diperlukan untuk fitur <strong>AI Grading</strong> yang dapat menilai soal Esai dan Jawaban Singkat secara otomatis. <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color:#2563EB;font-weight:bold;">Dapatkan API Key di sini</a>.
                    </div>
                    <div class="form-group">
                        <label class="form-label">API Key</label>
                        <input type="password" class="form-input" id="fGeminiKey" placeholder="Paste Gemini API Key Anda" value="${currentKey}">
                        <div style="font-size:12px; color:#64748b; margin-top:4px;">Kosongkan jika tidak ingin mengubah.</div>
                    </div>
                `,
                onConfirm: () => {
                    const key = $('#fGeminiKey').val().trim();
                    if(!key) return true; // nothing changed
                    
                    this.api('koreksi.php?action=save_settings', { method:'POST', data: { gemini_api_key: key } }).then(res => {
                        if (res.success) EModal.toast({type:'success', title:'Tersimpan'});
                        else EModal.alert('Gagal', res.message);
                    });
                }
            });
        });
    },

    // ==========================================
    // UTILITY
    // ==========================================
    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

// Initialize when DOM ready
$(document).ready(() => Exam.init());
