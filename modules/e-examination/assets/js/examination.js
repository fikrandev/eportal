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
                const loader = EModal.loading('Logging out...');
                const token = this.state.token || (window.localStorage ? localStorage.getItem('eportal_token') : null);
                $.ajax({
                    url: this.state.baseUrl + 'api/auth.php?action=logout',
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token },
                    complete: () => {
                        if (window.localStorage) {
                            localStorage.removeItem('eportal_token');
                            localStorage.removeItem('eportal_user');
                            localStorage.removeItem('eportal_school');
                            localStorage.removeItem('eportal_academic_year');
                        }
                        EModal.close(loader);
                        window.location.href = this.state.baseUrl + '#/login';
                    }
                });
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
            : (options.data instanceof FormData ? false : (options.method === 'POST' ? 'application/json; charset=UTF-8' : 'application/x-www-form-urlencoded'));
        let processData = !(options.data instanceof FormData);

        let url = this.state.apiUrl + endpoint;
        if (this.state.token && !url.includes('token=')) {
            const sep = url.includes('?') ? '&' : '?';
            url += sep + 'token=' + encodeURIComponent(this.state.token);
        }

        return $.ajax({
            url: url,
            method: options.method || 'GET',
            headers: {
                'Authorization': 'Bearer ' + this.state.token
            },
            data: options.method === 'POST' ? (options.data instanceof FormData ? options.data : JSON.stringify(options.data)) : options.data,
            contentType: ct,
            processData: processData,
        });
    },

    uploadFile(file, type) {
        const formData = new FormData();
        formData.append('file', file);
        return this.api('upload.php?action=' + type, { method: 'POST', data: formData });
    },

    loadMasterData() {
        this.api('bank_soal.php?action=list_mapel').then(r => {
            if (r.success) this.state.mapelList = r.data || [];
        }).catch(() => {});
        this.api('bank_soal.php?action=list_classes').then(r => {
            if (r.success) this.state.classList = r.data || [];
        }).catch(() => {});
    },

    // ==========================================
    // ROUTING
    // ==========================================
    navigate(route, params = {}) {
        this.state.params = params;
        let hash = '#/' + route;
        if (params.id) hash += '/' + params.id;
        if (window.location.hash === hash) {
            this.loadRouteFromHash();
        } else {
            window.location.hash = hash;
        }
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
        if (this._proktorInterval) {
            clearInterval(this._proktorInterval);
            this._proktorInterval = null;
        }

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
            proktor: 'Proktor & Monitoring CBT',
            laporan: 'Laporan & Hasil Ujian',
            akses_modul: 'Akses Modul & Hak Pengguna'
        };

        $('#pageTitle').text(labels[route] || 'E-Examination');
        $('#breadcrumbCurrent').text(labels[route] || route);

        switch(route) {
            case 'dashboard': this.renderDashboard($c); break;
            case 'mapel': this.renderMapel($c); break;
            case 'bank_soal': this.renderBankSoal($c); break;
            case 'detail_bank': this.renderDetailBank($c); break;
            case 'ujian': this.renderUjian($c); break;
            case 'proktor': this.renderProktor($c); break;
            case 'laporan': this.renderLaporan($c); break;
            case 'akses_modul': this.renderAksesModul($c); break;
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
        const examRole = u.exam_role || (u.role === 'superadmin' ? 'admin' : (u.role === 'guru' ? 'guru' : 'admin'));
        let navHtml = '';

        navHtml += `
            <div class="ex-nav-group">
                <div class="ex-nav-label">Menu Utama</div>
                <button class="ex-nav-item" data-route="dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Dashboard
                </button>
            </div>`;

        if (examRole === 'admin' || examRole === 'guru') {
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
                </div>`;
        }

        if (examRole === 'admin') {
            navHtml += `
                <div class="ex-nav-group">
                    <div class="ex-nav-label">Ujian & Proktor</div>
                    <button class="ex-nav-item" data-route="ujian">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Kelola Ujian
                    </button>
                    <button class="ex-nav-item" data-route="proktor">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="2" fill="currentColor"/></svg>
                        Proktor & Monitoring
                    </button>
                    <button class="ex-nav-item" data-route="laporan">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Laporan & Hasil
                    </button>
                </div>
                <div class="ex-nav-group">
                    <div class="ex-nav-label">Pengaturan</div>
                    <button class="ex-nav-item" data-route="akses_modul">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        Akses Modul
                    </button>
                    <button class="ex-nav-item" onclick="Exam.settingGemini()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="7.5 4.21 12 6.81 16.5 4.21"/><polyline points="7.5 19.79 7.5 14.6 3 12"/><polyline points="21 12 16.5 14.6 16.5 19.79"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        Koreksi AI (Gemini)
                    </button>
                </div>`;
        } else if (examRole === 'proktor') {
            navHtml += `
                <div class="ex-nav-group">
                    <div class="ex-nav-label">Proktor & Ujian</div>
                    <button class="ex-nav-item" data-route="proktor">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="2" fill="currentColor"/></svg>
                        Proktor & Monitoring
                    </button>
                    <button class="ex-nav-item" data-route="ujian">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Jadwal & Token Ujian
                    </button>
                    <button class="ex-nav-item" data-route="laporan">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Laporan & Hasil
                    </button>
                </div>`;
        } else {
            // Guru
            navHtml += `
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
                </div>`;
        }

        $('#sidebarNav').html(navHtml);
        $('.ex-nav-item').on('click', (e) => {
            this.navigate($(e.currentTarget).data('route'));
            this.toggleSidebar(false);
        });

        $('#sidebarAvatar').text(this.getInitials(u.nama_lengkap));
        $('#sidebarUserName').text(u.nama_lengkap);
        const roleLabels = { admin: 'Administrator', proktor: 'Proktor Ujian', guru: 'Guru Pengajar' };
        $('#sidebarUserRole').text(roleLabels[examRole] || u.role);
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
                    ${(isPsikologi || tipe === 'pilihan_banyak') ? `<input type="number" class="form-input ex-opsi-score" data-label="${o.label}" value="${scoreValue}" placeholder="Skor" style="width:80px;" step="0.1">` : ''}
                </div>`;
            });
            opsiHtml += '</div>';

            if (!isPsikologi) {
                if (tipe === 'pilihan_satu') {
                    kunciHtml = `<div class="form-group"><label class="form-label">Kunci Jawaban *</label><select class="form-select" id="fSoalKunci">
                        ${defaultOpsi.map(o => `<option value="${o.label}" ${kunciArr.includes(o.label)?'selected':''}>${o.label}</option>`).join('')}
                    </select></div>`;
                } else {
                    kunciHtml = `<div class="form-group"><label class="form-label">Kunci Jawaban (Otomatis dari input skor) </label><div>
                        <span class="text-muted" style="font-size:0.85rem;">Centang jawaban benar tidak diperlukan lagi karena penilaian berdasarkan Skor masing-masing opsi di atas.</span>
                    </div></div>`;
                    $('#fSoalBobot').prop('disabled', true).attr('title', 'Bobot dihitung otomatis dari total skor opsi');
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
            const pairs = opsi.length > 0 ? opsi : [{left:'',right:'', score:0},{left:'',right:'', score:0},{left:'',right:'', score:0}];
            opsiHtml = `<div class="form-group"><label class="form-label">Pasangan (Kiri → Kanan)</label><div id="fMenjodohkanPairs">`;
            pairs.forEach((p, i) => {
                const scoreValue = p.score !== undefined ? p.score : 0;
                opsiHtml += `<div style="display:flex;gap:8px;margin-bottom:8px;" class="ex-pair-row">
                    <input type="text" class="form-input ex-pair-left" value="${this.esc(p.left || '')}" placeholder="Pernyataan ${i+1}" style="flex:1;">
                    <span style="display:flex;align-items:center;font-weight:700;color:var(--text-muted);">→</span>
                    <input type="text" class="form-input ex-pair-right" value="${this.esc(p.right || '')}" placeholder="Jawaban ${i+1}" style="flex:1;">
                    <input type="number" class="form-input ex-pair-score" value="${scoreValue}" placeholder="Skor" style="width:80px;" step="0.1">
                </div>`;
            });
            opsiHtml += `</div><button type="button" class="btn btn-ghost btn-sm" onclick="Exam.addPairRow()">+ Tambah pasangan</button></div>`;
            $('#fSoalBobot').prop('disabled', true).attr('title', 'Bobot dihitung otomatis dari total skor pasangan');
        }

        if (tipe !== 'pilihan_banyak' && tipe !== 'menjodohkan') {
            $('#fSoalBobot').prop('disabled', false).removeAttr('title');
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
            <input type="number" class="form-input ex-pair-score" value="0" placeholder="Skor" style="width:80px;" step="0.1">
        </div>`);
    },

    collectSoalFormData() {
        const tipe = $('#fSoalTipe').val();
        const pertanyaan = $('#fSoalPertanyaan').val().trim();
        let bobot = parseFloat($('#fSoalBobot').val()) || 1;
        const pembahasan = $('#fSoalPembahasan').val().trim();
        const isPsikologi = (Exam.state.currentBankJenis === 'psikologi');

        if (!pertanyaan) { EModal.toast({type:'error',title:'Pertanyaan wajib diisi'}); return null; }

        let opsi = null;
        let kunci_jawaban = null;

        if (['pilihan_satu','pilihan_banyak'].includes(tipe)) {
            opsi = [];
            let totalScore = 0;
            $('.ex-opsi-input').each(function() {
                const label = $(this).data('label');
                const text = $(this).val().trim();
                if (text) {
                    if (isPsikologi || tipe === 'pilihan_banyak') {
                        const score = parseFloat($(`.ex-opsi-score[data-label="${label}"]`).val()) || 0;
                        opsi.push({label, text, score});
                        totalScore += score;
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
                } else if (tipe === 'pilihan_banyak') {
                    // Kunci jawaban tidak lagi menggunakan checkbox karena berbasis skor masing-masing opsi
                    kunci_jawaban = [];
                    opsi.forEach(o => {
                        if (o.score > 0) kunci_jawaban.push(o.label); // Auto-assign kunci based on score > 0 (for legacy fallback if needed)
                    });
                    bobot = totalScore;
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
            let totalScore = 0;
            $('.ex-pair-row').each(function() {
                const left = $(this).find('.ex-pair-left').val().trim();
                const right = $(this).find('.ex-pair-right').val().trim();
                const score = parseFloat($(this).find('.ex-pair-score').val()) || 0;
                if (left && right) {
                    opsi.push({left, right, score});
                    totalScore += score;
                }
            });
            if (opsi.length < 2) { EModal.toast({type:'error',title:'Minimal 2 pasangan'}); return null; }
            kunci_jawaban = opsi;
            bobot = totalScore;
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
                    ? `<div style="font-family:monospace;font-weight:bold;color:var(--primary);font-size:1.1rem;display:flex;align-items:center;">
                        ${u.token}
                        <button class="ex-btn-icon" style="display:inline-flex;margin-left:8px;" onclick="Exam.refreshToken(${u.id})" title="Refresh Token">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                        </button>
                        <button class="ex-btn-icon" style="display:inline-flex;margin-left:4px;" onclick="Exam.shareUjian(${u.id}, '${u.token}', '${this.esc(u.judul)}')" title="Bagikan Tautan & QR">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
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

    shareUjian(id, token, judul) {
        const link = `${this.state.moduleUrl}student/login.php?exam_id=${id}&token=${token}`;
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(link)}`;
        
        EModal.form({
            title: 'Bagikan Tautan Ujian',
            form: `
                <div style="text-align:center; padding: 10px 20px;">
                    <h3 style="margin-top:0; font-size:16px; color:var(--text-main);">${this.esc(judul)}</h3>
                    <div style="margin: 20px 0; display: flex; justify-content: center;">
                        <img src="${qrUrl}" alt="QR Code" style="border-radius: 8px; border: 1px solid #e2e8f0; padding: 10px; background: white; width:200px; height:200px; object-fit:contain;">
                    </div>
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:8px;">Bagikan tautan ini ke siswa, token otomatis terisi:</p>
                    <div style="display:flex; gap: 8px; align-items:center;">
                        <input type="text" id="fShareLink" class="form-input" value="${link}" readonly style="background:#f8fafc; font-size: 13px;">
                        <button type="button" class="ex-btn primary" onclick="Exam.copyLink()" style="white-space:nowrap; padding: 8px 16px;">Copy Link</button>
                    </div>
                </div>
            `,
            confirmText: 'Tutup',
            onConfirm: () => { return true; } // close
        });
    },

    copyLink() {
        const input = document.getElementById('fShareLink');
        input.select();
        document.execCommand('copy');
        EModal.toast({type: 'success', title: 'Tautan disalin!'});
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
        this.navigate('laporan', { id: ujianId, judul: judul });
        const $c = $('#mainContent');
        $c.html('<div style="text-align:center;padding:60px;"><div class="loading-spinner"></div></div>');
        
        this.api('laporan.php?action=hasil_ujian&ujian_id=' + ujianId).then(r => {
            if(!r.success) { $c.html('<p>Error memuat hasil: ' + this.esc(r.message || '') + '</p>'); return; }
            
            const rawData = r.data || {};
            const ujianInfo = rawData.ujian || {};
            const res = Array.isArray(rawData) ? rawData : (rawData.results || []);
            const isPsikologi = (ujianInfo.jenis === 'psikologi');
            const displayJudul = judul || ujianInfo.judul || 'Hasil Ujian';
            
            let rows = res.map((s, i) => {
                let statusSesi = '';
                if (s.status === 'mengerjakan' || s.status === 'berlangsung') {
                    statusSesi = '<span class="ex-badge ex-badge-gray">Sedang Ujian</span>';
                } else if (s.status === 'dihentikan' || s.status === 'didiskualifikasi') {
                    statusSesi = '<span class="ex-badge ex-badge-red">Dihentikan (Curang)</span>';
                } else if (s.status === 'selesai') {
                    statusSesi = '<span class="ex-badge ex-badge-green">Selesai</span>';
                } else {
                    statusSesi = '<span class="ex-badge" style="background:#f1f5f9;color:#64748b;">Belum Mulai</span>';
                }

                let pelanggaranBadge = (s.pelanggaran > 0) 
                    ? `<span title="Pelanggaran Anti-Cheat" style="display:inline-flex;align-items:center;gap:2px;padding:2px 6px;border-radius:4px;background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;margin-left:6px;">⚠ ${s.pelanggaran}×</span>` 
                    : '';

                let nilaiCol = '';
                if (isPsikologi) {
                    if (s.psikologi_hasil) {
                        nilaiCol = `<span class="ex-badge" style="background:#f3e8ff;color:#6b21a8;font-weight:700;">${this.esc(s.psikologi_hasil)}</span><br><span style="font-size:11px;color:#64748b;">Skor: ${s.skor !== null ? parseFloat(s.skor).toFixed(2) : '-'}</span>`;
                    } else if (s.skor !== null) {
                        nilaiCol = `<strong>${parseFloat(s.skor).toFixed(2)}</strong>`;
                    } else {
                        nilaiCol = '<span style="color:#94a3b8">Belum Selesai</span>';
                    }
                } else {
                    nilaiCol = s.skor !== null ? `<strong>${parseFloat(s.skor).toFixed(2)}</strong>` : '<span style="color:#94a3b8">Belum Dinilai</span>';
                }

                return `
                <tr>
                    <td>${i+1}</td>
                    <td>
                        <strong>${this.esc(s.nama_siswa)}</strong> ${pelanggaranBadge}<br>
                        <span style="font-size:12px;color:#64748b;">NIS: ${this.esc(s.nis)}</span>
                    </td>
                    <td>${this.esc(s.kelas)}</td>
                    <td>${statusSesi}</td>
                    <td>${s.waktu_mulai ? s.waktu_mulai.substring(0,16) : '-'}</td>
                    <td>${s.waktu_selesai ? s.waktu_selesai.substring(0,16) : '-'}</td>
                    <td>${nilaiCol}</td>
                </tr>`;
            }).join('');

            if (!rows) rows = '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">Belum ada data siswa untuk kelas ujian ini</td></tr>';

            const scoreColHeader = isPsikologi ? 'Hasil Psikologi' : 'Nilai (0-100)';

            $c.html(`
                <button class="btn btn-primary btn-sm" style="margin-bottom:16px;" onclick="Exam.navigate('laporan')">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    Kembali ke Daftar Laporan
                </button>
                <div class="ex-card ex-slide-up">
                    <div class="ex-card-header" style="flex-wrap:wrap; gap:16px;">
                        <div>
                            <h3 style="margin:0 0 4px 0;">Hasil: ${this.esc(displayJudul)}</h3>
                            <span style="font-size:13px;color:var(--text-secondary);">${isPsikologi ? 'Tipe: Tes Psikologi' : 'Tipe: Tes Penilaian'}</span>
                        </div>
                        <div class="ex-toolbar">
                            <button class="btn btn-primary btn-sm" onclick="Exam.koreksiMasal(${ujianId})" style="background:#eab308;color:#854d0e;border:none;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                Auto Koreksi / AI Grading
                            </button>
                            <a href="${this.state.apiUrl}laporan.php?action=download_excel&ujian_id=${ujianId}&token=${this.state.token}" target="_blank" class="btn btn-primary btn-sm" style="background:#10b981;border:none;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Export Excel (CSV)
                            </a>
                        </div>
                    </div>
                    <div class="ex-card-body" style="padding:0;">
                        <div class="ex-table-wrapper">
                            <table class="ex-table">
                                <thead><tr><th style="width:40px">#</th><th>Nama & NIS</th><th>Kelas</th><th>Status</th><th>Mulai</th><th>Selesai</th><th>${scoreColHeader}</th></tr></thead>
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
    // AKSES MODUL (MANAJEMEN PENGGUNA)
    // ==========================================
    renderAksesModul($c) {
        this.api('akses.php?action=list').then(r => {
            if (!r.success) {
                $c.html(`
                    <div class="ex-card">
                        <div class="ex-card-body" style="text-align:center;padding:40px;">
                            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#ef4444" stroke-width="1.5" style="margin-bottom:12px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <h4 style="margin-bottom:8px;color:#991b1b;">Akses Gagal Dimuat</h4>
                            <p style="font-size:13px;color:#64748b;max-width:500px;margin:0 auto 16px;">${this.esc(r.message || 'Gagal memuat akses modul.')}</p>
                            <button class="btn btn-primary btn-sm" onclick="Exam.renderAksesModul($('#mainContent'))">Coba Lagi</button>
                        </div>
                    </div>
                `);
                return;
            }

            this.state.accessData = r.data || { accesses: [], portal_users: [] };
            const accesses = this.state.accessData.accesses || [];

            const totalAdmin = accesses.filter(a => a.exam_role === 'admin' && parseInt(a.access_status) === 1).length;
            const totalGuru = accesses.filter(a => a.exam_role === 'guru' && parseInt(a.access_status) === 1).length;
            const totalProktor = accesses.filter(a => a.exam_role === 'proktor' && parseInt(a.access_status) === 1).length;

            $c.html(`
                <div class="ex-card ex-slide-up" style="margin-bottom:24px;">
                    <div class="ex-card-header" style="flex-wrap:wrap; gap:16px;">
                        <div>
                            <h3 style="margin:0 0 4px 0; font-size:1.15rem;">Akses Modul E-Examination</h3>
                            <span style="font-size:13px; color:var(--text-secondary);">
                                Kelola hak akses pengguna (Admin, Guru, Proktor). Akun diambil langsung dari E-Portal Admin.
                            </span>
                        </div>
                        <div class="ex-toolbar">
                            <button class="btn btn-primary btn-sm" onclick="Exam.openAccessForm()">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Tambah Akses Pengguna
                            </button>
                        </div>
                    </div>
                    <div class="ex-card-body">
                        <!-- Stats Grid -->
                        <div class="ex-stats-grid" style="margin-bottom:20px;">
                            <div class="ex-stat-card" style="padding:14px;">
                                <div class="ex-stat-icon" style="background:#eff6ff; color:#2563EB;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <div class="ex-stat-info"><h4>${totalAdmin}</h4><p>Admin Examination</p></div>
                            </div>
                            <div class="ex-stat-card" style="padding:14px;">
                                <div class="ex-stat-icon" style="background:#fef3c7; color:#d97706;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                </div>
                                <div class="ex-stat-info"><h4>${totalProktor}</h4><p>Proktor Ujian</p></div>
                            </div>
                            <div class="ex-stat-card" style="padding:14px;">
                                <div class="ex-stat-icon" style="background:#ecfdf5; color:#059669;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                </div>
                                <div class="ex-stat-info"><h4>${totalGuru}</h4><p>Guru Pengajar</p></div>
                            </div>
                            <div class="ex-stat-card" style="padding:14px;">
                                <div class="ex-stat-icon" style="background:#f1f5f9; color:#475569;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                </div>
                                <div class="ex-stat-info"><h4>${accesses.length}</h4><p>Total Diberi Akses</p></div>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
                            <input type="text" id="accessTableSearch" class="form-input" placeholder="Cari nama, NIK/username, atau tugas..." style="max-width:340px; font-size:13px;">
                            <div style="font-size:13px; color:var(--text-secondary);">
                                Menampilkan <strong>${accesses.length}</strong> pengguna
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="ex-table-wrapper" id="accessTableContent">
                            ${this.renderAccessTableRows(accesses)}
                        </div>
                    </div>
                </div>
            `);

            $('#accessTableSearch').on('input', (e) => {
                const q = $(e.currentTarget).val().toLowerCase().trim();
                const filtered = accesses.filter(a => {
                    const str = `${a.nama_lengkap} ${a.username} ${a.nik || ''} ${a.jabatan || ''} ${a.exam_role}`.toLowerCase();
                    return str.includes(q);
                });
                $('#accessTableContent').html(this.renderAccessTableRows(filtered));
            });
        }).catch(err => {
            const msg = (err.responseJSON && err.responseJSON.message) ? err.responseJSON.message : (err.responseText || 'Terjadi kesalahan saat memuat data akses modul.');
            $c.html(`
                <div class="ex-card">
                    <div class="ex-card-body" style="text-align:center;padding:40px;">
                        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#ef4444" stroke-width="1.5" style="margin-bottom:12px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <h4 style="margin-bottom:8px;color:#991b1b;">Terjadi Kesalahan Akses Modul</h4>
                        <p style="font-size:13px;color:#64748b;max-width:500px;margin:0 auto 16px;">${this.esc(msg)}</p>
                        <button class="btn btn-primary btn-sm" onclick="Exam.renderAksesModul($('#mainContent'))">Coba Lagi</button>
                    </div>
                </div>
            `);
        });
    },

    renderAccessTableRows(accesses) {
        if (!accesses || !accesses.length) {
            return `<div style="text-align:center;padding:40px;color:var(--text-secondary);">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.4;margin-bottom:12px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <p>Belum ada pengguna yang ditugaskan di modul E-Examination.<br>Klik tombol <strong>"Tambah Akses Pengguna"</strong> di atas.</p>
            </div>`;
        }

        const roleBadges = {
            admin: '<span class="badge badge-admin">🛡️ Admin</span>',
            proktor: '<span class="badge badge-proktor">⏱️ Proktor</span>',
            guru: '<span class="badge badge-guru">📖 Guru</span>'
        };

        const rows = accesses.map((a, i) => {
            const isSuperadmin = (a.portal_role === 'superadmin');
            const isActive = parseInt(a.access_status) === 1;
            const statusBadge = isActive 
                ? '<span class="badge badge-active">Aktif</span>' 
                : '<span class="badge badge-locked">Nonaktif</span>';

            return `
                <tr>
                    <td style="width:40px; text-align:center;">${i+1}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="ex-user-avatar-sm">${this.getInitials(a.nama_lengkap)}</div>
                            <div>
                                <div style="font-weight:600; color:#0f172a;">${this.esc(a.nama_lengkap)} ${isSuperadmin ? '<span style="font-size:11px;color:#2563eb;font-weight:700;">(Superadmin)</span>' : ''}</div>
                                <div style="font-size:12px; color:var(--text-secondary);">Username: <code style="font-family:monospace;background:#f1f5f9;padding:1px 4px;border-radius:4px;">${this.esc(a.username)}</code> ${a.nik ? `• NIK: ${this.esc(a.nik)}` : ''}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:13px; color:#334155;">${this.esc(a.jabatan || a.tupoksi || 'Pegawai / Guru')}</div>
                        <div style="font-size:11px; color:#64748b;">Role Portal: ${this.esc(a.portal_role || 'user')}</div>
                    </td>
                    <td>${roleBadges[a.exam_role] || a.exam_role}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="ex-actions">
                            <button class="ex-action-btn" title="Edit Akses" onclick="Exam.openAccessForm(${a.user_id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            ${!isSuperadmin ? `
                            <button class="ex-action-btn danger" title="Cabut Akses" onclick="Exam.deleteAccess(${a.user_id}, '${this.esc(a.nama_lengkap)}')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        return `
            <table class="ex-table">
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">#</th>
                        <th>Nama Pengguna</th>
                        <th>Jabatan E-Portal</th>
                        <th>Peran di CBT</th>
                        <th>Status</th>
                        <th style="width:100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    },

    openAccessForm(userId = null) {
        const accesses = (this.state.accessData && this.state.accessData.accesses) ? this.state.accessData.accesses : [];
        const portalUsers = (this.state.accessData && this.state.accessData.portal_users) ? this.state.accessData.portal_users : [];
        
        let existing = null;
        if (userId) {
            existing = accesses.find(a => parseInt(a.user_id) === parseInt(userId));
        }

        const selectedRole = existing ? existing.exam_role : 'guru';
        const selectedStatus = existing ? parseInt(existing.access_status) : 1;

        let userPreviewHtml = '';
        if (existing) {
            userPreviewHtml = `
                <div style="display:flex;align-items:center;gap:12px;background:#f8fafc;padding:12px 16px;border-radius:10px;border:1px solid #e2e8f0;">
                    <div class="ex-user-avatar-sm" style="width:40px;height:40px;font-size:0.9rem;">${this.getInitials(existing.nama_lengkap)}</div>
                    <div>
                        <div style="font-weight:700;color:#0f172a;">${this.esc(existing.nama_lengkap)}</div>
                        <div style="font-size:12px;color:#64748b;">Username: ${this.esc(existing.username)} • ${this.esc(existing.jabatan || 'Guru')}</div>
                    </div>
                </div>
            `;
        }

        EModal.form({
            title: existing ? 'Edit Akses Pengguna CBT' : 'Tambah Akses Pengguna Baru',
            form: `
                <input type="hidden" id="fAccessUserId" value="${userId || ''}">
                <input type="hidden" id="fAccessRole" value="${selectedRole}">

                ${!existing ? `
                <div class="form-group" style="position:relative;">
                    <label class="form-label" style="font-weight:600;">Cari Nama Pengguna (E-Portal Admin)</label>
                    <div class="ex-user-select-container">
                        <input type="text" id="fUserSearchInput" class="ex-user-search-input" placeholder="🔍 Ketik nama, username, atau NIK guru/pegawai..." autocomplete="off">
                        <div id="fUserDropdownList" class="ex-user-dropdown-list"></div>
                    </div>
                    <div id="fSelectedUserBox" style="margin-top:10px;"></div>
                </div>
                ` : `
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">Pengguna Terpilih</label>
                    ${userPreviewHtml}
                </div>
                `}

                <div class="form-group" style="margin-top:20px;">
                    <label class="form-label" style="font-weight:600;">Pilih Tugas / Peran di E-Examination</label>
                    <div class="ex-role-grid">
                        <div class="ex-role-card ${selectedRole === 'admin' ? 'active' : ''}" data-role="admin">
                            <div class="ex-role-card-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <div class="ex-role-card-title">Admin</div>
                            <div class="ex-role-card-desc">Akses penuh ke seluruh bank soal, jadwal ujian, proktor, hasil nilai, dan akses modul.</div>
                        </div>
                        <div class="ex-role-card ${selectedRole === 'guru' ? 'active' : ''}" data-role="guru">
                            <div class="ex-role-card-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            </div>
                            <div class="ex-role-card-title">Guru</div>
                            <div class="ex-role-card-desc">Membuat bank soal, kelola ujian miliknya, lihat hasil ujian siswa, dan koreksi nilai.</div>
                        </div>
                        <div class="ex-role-card ${selectedRole === 'proktor' ? 'active' : ''}" data-role="proktor">
                            <div class="ex-role-card-icon">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="2" fill="currentColor"/></svg>
                            </div>
                            <div class="ex-role-card-title">Proktor</div>
                            <div class="ex-role-card-desc">Live monitoring pengerjaan, reset login siswa keluar/terkunci, dan generate link ujian.</div>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label" style="font-weight:600;">Status Akses</label>
                    <select class="form-select" id="fAccessStatus">
                        <option value="1" ${selectedStatus === 1 ? 'selected' : ''}>Aktif (Dapat Mengakses)</option>
                        <option value="0" ${selectedStatus === 0 ? 'selected' : ''}>Nonaktif (Diblokir)</option>
                    </select>
                </div>
            `,
            onOpen: () => {
                // Role card selector
                $('.ex-role-card').on('click', function() {
                    $('.ex-role-card').removeClass('active');
                    $(this).addClass('active');
                    $('#fAccessRole').val($(this).data('role'));
                });

                if (!existing) {
                    const $input = $('#fUserSearchInput');
                    const $list = $('#fUserDropdownList');
                    const $selectedBox = $('#fSelectedUserBox');

                    const renderDropdown = (query = '') => {
                        const q = query.toLowerCase().trim();
                        const matches = portalUsers.filter(u => {
                            const str = `${u.nama_lengkap} ${u.username} ${u.nik || ''} ${u.jabatan || ''}`.toLowerCase();
                            return !q || str.includes(q);
                        }).slice(0, 15);

                        if (!matches.length) {
                            $list.html('<div style="padding:12px;text-align:center;color:#64748b;font-size:13px;">Tidak ada pengguna yang cocok</div>').show();
                            return;
                        }

                        const items = matches.map(u => `
                            <div class="ex-user-dropdown-item" data-id="${u.id}" data-nama="${Exam.esc(u.nama_lengkap)}" data-user="${Exam.esc(u.username)}" data-jabatan="${Exam.esc(u.jabatan || u.tupoksi || '')}">
                                <div class="ex-user-avatar-sm">${Exam.getInitials(u.nama_lengkap)}</div>
                                <div style="flex:1;">
                                    <div style="font-weight:600;font-size:13px;color:#0f172a;">${Exam.esc(u.nama_lengkap)}</div>
                                    <div style="font-size:11px;color:#64748b;">${Exam.esc(u.username)} ${u.jabatan ? `• ${Exam.esc(u.jabatan)}` : ''}</div>
                                </div>
                                ${u.current_exam_role ? `<span class="badge badge-${u.current_exam_role}" style="font-size:10px;">${u.current_exam_role.toUpperCase()}</span>` : ''}
                            </div>
                        `).join('');

                        $list.html(items).show();
                    };

                    $input.on('focus input', () => renderDropdown($input.val()));

                    $(document).on('click.userSelect', (e) => {
                        if (!$(e.target).closest('.ex-user-select-container').length) {
                            $list.hide();
                        }
                    });

                    $list.on('click', '.ex-user-dropdown-item', function() {
                        const uid = $(this).data('id');
                        const nama = $(this).data('nama');
                        const username = $(this).data('user');
                        const jabatan = $(this).data('jabatan');

                        $('#fAccessUserId').val(uid);
                        $input.val(nama);
                        $list.hide();

                        $selectedBox.html(`
                            <div style="display:flex;align-items:center;gap:12px;background:#eff6ff;padding:10px 14px;border-radius:8px;border:1px solid #bfdbfe;">
                                <div class="ex-user-avatar-sm" style="background:#2563eb;color:white;">${Exam.getInitials(nama)}</div>
                                <div style="flex:1;">
                                    <div style="font-weight:700;color:#1e40af;font-size:13px;">${nama}</div>
                                    <div style="font-size:12px;color:#3b82f6;">Username: ${username} • ${jabatan || 'Guru/Pegawai'}</div>
                                </div>
                                <span style="font-size:11px;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:6px;font-weight:700;">Terpilih</span>
                            </div>
                        `);
                    });
                }
            },
            onClose: () => {
                $(document).off('click.userSelect');
            },
            onConfirm: () => {
                const userId = $('#fAccessUserId').val();
                const role = $('#fAccessRole').val();
                const status = $('#fAccessStatus').val();

                if (!userId || parseInt(userId) <= 0) {
                    EModal.toast({ type: 'error', title: 'Pilih pengguna terlebih dahulu' });
                    return false;
                }

                const loader = EModal.loading('Menyimpan hak akses...');
                this.api('akses.php?action=save', {
                    method: 'POST',
                    data: { user_id: userId, role: role, status: status }
                }).then(res => {
                    EModal.close(loader);
                    if (res.success) {
                        EModal.toast({ type: 'success', title: res.message || 'Akses berhasil disimpan' });
                        this.renderAksesModul($('#mainContent'));
                    } else {
                        EModal.alert('Gagal', res.message);
                    }
                }).catch(err => {
                    EModal.close(loader);
                    EModal.alert('Error', 'Gagal menghubungi server');
                });
            }
        });
    },

    deleteAccess(userId, userName) {
        EModal.confirm({
            title: 'Cabut Akses Modul',
            message: `Apakah Anda yakin ingin mencabut hak akses E-Examination untuk <strong>${this.esc(userName)}</strong>?`,
            type: 'danger',
            confirmText: 'Ya, Cabut Akses',
            onConfirm: () => {
                const loader = EModal.loading('Mencabut akses...');
                this.api('akses.php?action=delete', {
                    method: 'POST',
                    data: { user_id: userId }
                }).then(res => {
                    EModal.close(loader);
                    if (res.success) {
                        EModal.toast({ type: 'success', title: 'Akses berhasil dicabut' });
                        this.renderAksesModul($('#mainContent'));
                    } else {
                        EModal.alert('Gagal', res.message);
                    }
                }).catch(() => {
                    EModal.close(loader);
                    EModal.alert('Error', 'Gagal memproses permintaan');
                });
            }
        });
    },

    // ==========================================
    // PROKTOR & LIVE MONITORING
    // ==========================================
    renderProktor($c) {
        $c.html(`
            <div class="ex-card ex-slide-up" style="margin-bottom:24px;">
                <div class="ex-card-header" style="flex-wrap:wrap; gap:16px;">
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span class="ex-live-dot"></span>
                            <h3 style="margin:0; font-size:1.15rem;">Proktor & Live Monitoring CBT</h3>
                        </div>
                        <span style="font-size:13px; color:var(--text-secondary); margin-top:4px; display:block;">
                            Pantau pengerjaan siswa secara real-time, reset login siswa terkunci, dan generate link ujian.
                        </span>
                    </div>
                    <div class="ex-toolbar" style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn btn-primary btn-sm" onclick="Exam.proktorGenerateLink()" style="background:#2563EB;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            Generate Link Ujian
                        </button>
                        <button class="btn btn-outline btn-sm" onclick="Exam.proktorResetAllLocked()" style="color:#b91c1c;border-color:#fca5a5;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                            Reset Semua Terkunci
                        </button>
                        <button class="btn btn-outline btn-sm" onclick="Exam.loadProktorData()" title="Segarkan Data Sekarang">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                            Refresh
                        </button>
                    </div>
                </div>

                <div class="ex-card-body">
                    <!-- Real-time Stat Counters -->
                    <div class="ex-stats-grid" style="margin-bottom:20px;">
                        <div class="ex-stat-card" style="padding:14px;border-left:4px solid #2563EB;">
                            <div class="ex-stat-icon" style="background:#eff6ff; color:#2563EB;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            </div>
                            <div class="ex-stat-info"><h4 id="statMengerjakan">0</h4><p>Sedang Mengerjakan</p></div>
                        </div>
                        <div class="ex-stat-card" style="padding:14px;border-left:4px solid #10B981;">
                            <div class="ex-stat-icon" style="background:#ecfdf5; color:#10B981;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div class="ex-stat-info"><h4 id="statLogin">0</h4><p>Sedang Login</p></div>
                        </div>
                        <div class="ex-stat-card" style="padding:14px;border-left:4px solid #EF4444;">
                            <div class="ex-stat-icon" style="background:#fef2f2; color:#EF4444;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </div>
                            <div class="ex-stat-info"><h4 id="statTerkunci" style="color:#b91c1c;">0</h4><p>Terkunci (Butuh Reset)</p></div>
                        </div>
                        <div class="ex-stat-card" style="padding:14px;border-left:4px solid #8B5CF6;">
                            <div class="ex-stat-icon" style="background:#f5f3ff; color:#8B5CF6;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                            </div>
                            <div class="ex-stat-info"><h4 id="statServerTime" style="font-size:1.05rem;">--:--:--</h4><p>Waktu Server</p></div>
                        </div>
                    </div>

                    <!-- Filter Toolbar -->
                    <div style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:200px;">
                            <select id="proktorFilterUjian" class="form-select" style="font-size:13px;">
                                <option value="0">-- Semua Ujian Aktif --</option>
                            </select>
                        </div>
                        <div style="min-width:160px;">
                            <select id="proktorFilterKelas" class="form-select" style="font-size:13px;">
                                <option value="">-- Semua Kelas --</option>
                            </select>
                        </div>
                        <div style="flex:1; min-width:200px;">
                            <input type="text" id="proktorFilterSearch" class="form-input" placeholder="🔍 Cari nama siswa atau NIS..." style="font-size:13px;">
                        </div>
                    </div>

                    <!-- Tabs Navigation -->
                    <div class="ex-tabs">
                        <button class="ex-tab-btn active" data-tab="tab-mengerjakan">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            Sedang Mengerjakan
                            <span class="ex-tab-badge" id="tabBadgeMengerjakan">0</span>
                        </button>
                        <button class="ex-tab-btn" data-tab="tab-terkunci">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Siswa Terkunci
                            <span class="ex-tab-badge" id="tabBadgeTerkunci" style="background:#fee2e2;color:#b91c1c;">0</span>
                        </button>
                        <button class="ex-tab-btn" data-tab="tab-login">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Status Login Siswa
                            <span class="ex-tab-badge" id="tabBadgeLogin">0</span>
                        </button>
                    </div>

                    <!-- Tab Contents -->
                    <div id="tab-mengerjakan" class="ex-tab-content" style="display:block;">
                        <div class="ex-table-wrapper" id="tableMengerjakanContainer"><div class="loading-spinner"></div></div>
                    </div>
                    <div id="tab-terkunci" class="ex-tab-content" style="display:none;">
                        <div class="ex-table-wrapper" id="tableTerkunciContainer"><div class="loading-spinner"></div></div>
                    </div>
                    <div id="tab-login" class="ex-tab-content" style="display:none;">
                        <div class="ex-table-wrapper" id="tableLoginContainer"><div class="loading-spinner"></div></div>
                    </div>
                </div>
            </div>
        `);

        // Tab Switching
        $('.ex-tab-btn').on('click', function() {
            $('.ex-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.ex-tab-content').hide();
            $('#' + $(this).data('tab')).fadeIn(150);
        });

        // Filter events
        $('#proktorFilterUjian, #proktorFilterKelas').on('change', () => this.loadProktorData());
        $('#proktorFilterSearch').on('input', () => this.loadProktorData());

        this.loadProktorData(true);

        // Start auto refresh every 8 seconds while on proktor view
        this._proktorInterval = setInterval(() => {
            if (this.state.currentRoute === 'proktor') {
                this.loadProktorData(false);
            }
        }, 8000);
    },

    loadProktorData(initFilters = false) {
        const ujianId = $('#proktorFilterUjian').val() || 0;
        const kelas = $('#proktorFilterKelas').val() || '';
        const search = $('#proktorFilterSearch').val() || '';

        this.api(`proktor.php?action=monitoring&ujian_id=${ujianId}&kelas=${encodeURIComponent(kelas)}&search=${encodeURIComponent(search)}`).then(r => {
            if (!r.success) return;
            const d = r.data;

            // Populate filters on first load
            if (initFilters) {
                if (d.active_exams && d.active_exams.length) {
                    const examOpts = d.active_exams.map(e => `<option value="${e.id}">${this.esc(e.judul)} (Token: ${this.esc(e.token || '-')})</option>`).join('');
                    $('#proktorFilterUjian').append(examOpts);
                }
                if (d.classes && d.classes.length) {
                    const classOpts = d.classes.map(k => `<option value="${this.esc(k.kelas)}">${this.esc(k.kelas)}</option>`).join('');
                    $('#proktorFilterKelas').append(classOpts);
                }
            }

            // Update stats
            $('#statMengerjakan').text(d.stats.total_mengerjakan || 0);
            $('#statLogin').text(d.stats.total_login || 0);
            $('#statTerkunci').text(d.stats.total_terkunci || 0);
            if (d.server_time) $('#statServerTime').text(d.server_time.split(' ')[1] || d.server_time);

            $('#tabBadgeMengerjakan').text(d.mengerjakan.length);
            $('#tabBadgeLogin').text(d.login.length);
            $('#tabBadgeTerkunci').text(d.terkunci.length);

            // 1. Render Table Mengerjakan
            this.renderTableMengerjakan(d.mengerjakan);

            // 2. Render Table Terkunci
            this.renderTableTerkunci(d.terkunci);

            // 3. Render Table Login
            this.renderTableLogin(d.login);
        }).catch(() => {});
    },

    renderTableMengerjakan(list) {
        if (!list || !list.length) {
            $('#tableMengerjakanContainer').html(`
                <div style="text-align:center;padding:40px;color:var(--text-secondary);">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.4;margin-bottom:12px;"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <p>Tidak ada siswa yang sedang mengerjakan ujian saat ini.</p>
                </div>
            `);
            return;
        }

        const rows = list.map((m, i) => {
            const total = parseInt(m.total_soal) || 1;
            const answered = parseInt(m.total_terjawab) || 0;
            const pct = Math.round((answered / total) * 100);
            const isLocked = parseInt(m.is_locked) === 1;

            let remainingTimeHtml = `<span style="font-family:monospace;font-weight:700;color:#1e40af;">${m.sisa_menit}m</span>`;
            if (m.sisa_menit <= 5) {
                remainingTimeHtml = `<span style="font-family:monospace;font-weight:700;color:#dc2626;background:#fee2e2;padding:2px 6px;border-radius:4px;">⏱️ ${m.sisa_menit}m</span>`;
            }

            return `
                <tr>
                    <td style="width:40px;text-align:center;">${i+1}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="ex-user-avatar-sm">${this.getInitials(m.nama_siswa)}</div>
                            <div>
                                <div style="font-weight:600;color:#0f172a;">${this.esc(m.nama_siswa)}</div>
                                <div style="font-size:12px;color:var(--text-secondary);"><code style="font-family:monospace;background:#f1f5f9;padding:1px 4px;border-radius:4px;">${this.esc(m.nis)}</code> • Kelas ${this.esc(m.kelas)}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px;">${this.esc(m.nama_ujian)}</div>
                        <div style="font-size:11px;color:#64748b;">Token: <strong style="color:#2563eb;">${this.esc(m.token_ujian || '-')}</strong></div>
                    </td>
                    <td>${remainingTimeHtml}</td>
                    <td style="min-width:140px;">
                        <div style="display:flex;justify-content:space-between;font-size:11px;font-weight:600;color:#475569;margin-bottom:2px;">
                            <span>${answered} / ${total} Soal</span>
                            <span>${pct}%</span>
                        </div>
                        <div class="ex-progress-bar">
                            <div class="ex-progress-fill" style="width:${pct}%;"></div>
                        </div>
                    </td>
                    <td>
                        ${parseInt(m.pelanggaran) > 0 
                            ? `<span class="badge badge-locked" style="font-weight:700;">⚠ ${m.pelanggaran} Pelanggaran</span>` 
                            : '<span class="badge" style="background:#f1f5f9;color:#64748b;">0</span>'}
                    </td>
                    <td>
                        ${isLocked 
                            ? '<span class="badge badge-locked">🔒 Terkunci (Keluar)</span>' 
                            : '<span class="badge badge-active">🟢 Aktif Mengerjakan</span>'}
                    </td>
                    <td>
                        <div class="ex-actions">
                            <button class="ex-action-btn" title="Reset Login Siswa" onclick="Exam.proktorResetLogin(${m.student_id}, '${this.esc(m.nama_siswa)}')" style="color:#2563eb;background:#eff6ff;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                            </button>
                            <button class="ex-action-btn danger" title="Selesaikan Paksa Sesi Ini" onclick="Exam.proktorForceFinish(${m.sesi_id}, '${this.esc(m.nama_siswa)}')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        $('#tableMengerjakanContainer').html(`
            <table class="ex-table">
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">#</th>
                        <th>Siswa & NIS</th>
                        <th>Ujian</th>
                        <th>Sisa Waktu</th>
                        <th>Progres Jawaban</th>
                        <th>Pelanggaran</th>
                        <th>Status</th>
                        <th style="width:100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `);
    },

    renderTableTerkunci(list) {
        if (!list || !list.length) {
            $('#tableTerkunciContainer').html(`
                <div style="text-align:center;padding:40px;color:var(--text-secondary);">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#10b981" stroke-width="1.5" style="opacity:0.6;margin-bottom:12px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <p style="color:#059669;font-weight:600;">Semua login normal! Tidak ada akun siswa yang sedang terkunci.</p>
                </div>
            `);
            return;
        }

        const rows = list.map((t, i) => `
            <tr style="background:#fff7ed;">
                <td style="width:40px;text-align:center;">${i+1}</td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="ex-user-avatar-sm" style="background:#fee2e2;color:#991b1b;">${this.getInitials(t.nama_siswa)}</div>
                        <div>
                            <div style="font-weight:700;color:#0f172a;">${this.esc(t.nama_siswa)}</div>
                            <div style="font-size:12px;color:var(--text-secondary);"><code style="font-family:monospace;background:#fee2e2;padding:1px 4px;border-radius:4px;color:#991b1b;">${this.esc(t.nis)}</code> • Kelas ${this.esc(t.kelas)}</div>
                        </div>
                    </div>
                </td>
                <td><strong style="color:#334155;">${this.esc(t.nama_ujian || 'Ujian CBT')}</strong></td>
                <td>
                    <div style="color:#991b1b;font-weight:600;font-size:12px;">⚠ ${this.esc(t.lock_reason || 'Keluar dari aplikasi ujian')}</div>
                    <div style="font-size:11px;color:#64748b;">${t.waktu_terkunci ? t.waktu_terkunci : '-'}</div>
                </td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="Exam.proktorResetLogin(${t.student_id}, '${this.esc(t.nama_siswa)}')" style="background:#2563EB;display:inline-flex;align-items:center;gap:6px;">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                        Reset Login
                    </button>
                </td>
            </tr>
        `).join('');

        $('#tableTerkunciContainer').html(`
            <table class="ex-table">
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">#</th>
                        <th>Siswa & NIS</th>
                        <th>Ujian</th>
                        <th>Alasan & Waktu Terkunci</th>
                        <th style="width:140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `);
    },

    renderTableLogin(list) {
        if (!list || !list.length) {
            $('#tableLoginContainer').html(`
                <div style="text-align:center;padding:40px;color:var(--text-secondary);">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.4;margin-bottom:12px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <p>Tidak ada sesi login siswa yang aktif.</p>
                </div>
            `);
            return;
        }

        const rows = list.map((l, i) => {
            const isLocked = parseInt(l.is_locked) === 1;
            return `
                <tr>
                    <td style="width:40px;text-align:center;">${i+1}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="ex-user-avatar-sm">${this.getInitials(l.nama_siswa)}</div>
                            <div>
                                <div style="font-weight:600;color:#0f172a;">${this.esc(l.nama_siswa)}</div>
                                <div style="font-size:12px;color:var(--text-secondary);">${this.esc(l.nis)} • Kelas ${this.esc(l.kelas)}</div>
                            </div>
                        </div>
                    </td>
                    <td><code style="font-family:monospace;font-size:12px;">${this.esc(l.ip_address || '-')}</code></td>
                    <td><span style="font-size:12px;color:#475569;">${l.login_at || '-'}</span></td>
                    <td>
                        ${l.login_status === 'mengerjakan' 
                            ? '<span class="badge badge-active">Sedang Mengerjakan</span>' 
                            : '<span class="badge" style="background:#e0f2fe;color:#0369a1;">Dashboard CBT</span>'}
                    </td>
                    <td>
                        ${isLocked 
                            ? '<span class="badge badge-locked">🔒 Terkunci</span>' 
                            : '<span class="badge badge-active">✓ Normal</span>'}
                    </td>
                    <td>
                        <button class="btn btn-outline btn-sm" onclick="Exam.proktorResetLogin(${l.student_id}, '${this.esc(l.nama_siswa)}')" style="font-size:12px;padding:4px 10px;">
                            Reset Login
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        $('#tableLoginContainer').html(`
            <table class="ex-table">
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">#</th>
                        <th>Siswa & NIS</th>
                        <th>IP Address</th>
                        <th>Waktu Login</th>
                        <th>Status CBT</th>
                        <th>Status Lock</th>
                        <th style="width:120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `);
    },

    proktorResetLogin(studentId, studentName) {
        EModal.confirm({
            title: 'Reset Login Siswa',
            message: `Apakah Anda yakin ingin melakukan <strong>Reset Login</strong> untuk siswa <strong>${this.esc(studentName)}</strong>? Siswa akan dapat login kembali.`,
            type: 'primary',
            confirmText: 'Ya, Reset Login',
            onConfirm: () => {
                const loader = EModal.loading('Mereset status login...');
                this.api('proktor.php?action=reset_login', {
                    method: 'POST',
                    data: { student_id: studentId }
                }).then(res => {
                    EModal.close(loader);
                    if (res.success) {
                        EModal.toast({ type: 'success', title: res.message || 'Login berhasil di-reset' });
                        this.loadProktorData(false);
                    } else {
                        EModal.alert('Gagal', res.message);
                    }
                }).catch(() => {
                    EModal.close(loader);
                    EModal.alert('Error', 'Gagal memproses permintaan');
                });
            }
        });
    },

    proktorResetAllLocked() {
        const ujianId = $('#proktorFilterUjian').val() || 0;
        const kelas = $('#proktorFilterKelas').val() || '';

        EModal.confirm({
            title: 'Reset Semua Akun Terkunci',
            message: `Yakin ingin mereset <strong>SEMUA</strong> akun siswa yang saat ini berstatus terkunci?`,
            type: 'danger',
            confirmText: 'Ya, Reset Semua',
            onConfirm: () => {
                const loader = EModal.loading('Mereset seluruh akun terkunci...');
                this.api('proktor.php?action=reset_all_locked', {
                    method: 'POST',
                    data: { ujian_id: ujianId, kelas: kelas }
                }).then(res => {
                    EModal.close(loader);
                    if (res.success) {
                        EModal.toast({ type: 'success', title: res.message || 'Reset massal selesai' });
                        this.loadProktorData(false);
                    } else {
                        EModal.alert('Gagal', res.message);
                    }
                }).catch(() => {
                    EModal.close(loader);
                    EModal.alert('Error', 'Gagal memproses permintaan');
                });
            }
        });
    },

    proktorForceFinish(sesiId, studentName) {
        EModal.confirm({
            title: 'Selesaikan Paksa Ujian',
            message: `Yakin ingin memaksa selesai sesi ujian untuk <strong>${this.esc(studentName)}</strong>? Jawaban siswa saat ini akan langsung difinalisasi.`,
            type: 'danger',
            confirmText: 'Ya, Selesaikan',
            onConfirm: () => {
                const loader = EModal.loading('Memproses penyelesaian ujian...');
                this.api('proktor.php?action=force_finish', {
                    method: 'POST',
                    data: { sesi_id: sesiId }
                }).then(res => {
                    EModal.close(loader);
                    if (res.success) {
                        EModal.toast({ type: 'success', title: 'Sesi ujian berhasil diselesaikan' });
                        this.loadProktorData(false);
                    } else {
                        EModal.alert('Gagal', res.message);
                    }
                }).catch(() => {
                    EModal.close(loader);
                    EModal.alert('Error', 'Gagal memproses permintaan');
                });
            }
        });
    },

    proktorGenerateLink(ujianId = null) {
        if (!ujianId) {
            ujianId = $('#proktorFilterUjian').val() || 0;
        }

        const loader = EModal.loading('Mengambil link ujian...');
        this.api(`proktor.php?action=generate_link&ujian_id=${ujianId}`).then(r => {
            EModal.close(loader);
            if (!r.success) { EModal.alert('Gagal', r.message); return; }
            const d = r.data;

            EModal.form({
                title: '🔗 Link Pengerjaan Ujian CBT',
                form: `
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600;">Link Login Siswa CBT</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" class="form-input" id="fDirectLink" value="${d.login_url}" readonly style="font-family:monospace;font-size:13px;background:#f8fafc;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="navigator.clipboard.writeText($('#fDirectLink').val()); EModal.toast({type:'success',title:'Link Disalin!'});" style="white-space:nowrap;">
                                Salin Link
                            </button>
                        </div>
                    </div>

                    ${d.exam_info ? `
                    <div style="background:#eff6ff;padding:14px;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:16px;">
                        <div style="font-weight:700;color:#1e40af;margin-bottom:4px;">${this.esc(d.exam_info.judul)}</div>
                        <div style="display:flex;gap:12px;font-size:13px;color:#3b82f6;">
                            <span>Token: <strong style="font-size:15px;color:#1e40af;letter-spacing:1px;">${this.esc(d.exam_info.token || '-')}</strong></span>
                            <span>Durasi: <strong>${d.exam_info.durasi_menit} Menit</strong></span>
                        </div>
                    </div>
                    ` : ''}

                    <div class="form-group">
                        <label class="form-label" style="font-weight:600;">Teks Siap Bagikan (WhatsApp / Broadcast)</label>
                        <textarea class="form-input" id="fShareText" rows="6" style="font-family:monospace;font-size:12px;resize:vertical;" readonly>${this.esc(d.share_text)}</textarea>
                    </div>
                `,
                confirmText: 'Salin Teks Lengkap',
                onConfirm: () => {
                    const text = $('#fShareText').val();
                    navigator.clipboard.writeText(text);
                    EModal.toast({ type: 'success', title: 'Teks Informasi Berhasil Disalin!' });
                }
            });
        }).catch(() => {
            EModal.close(loader);
            EModal.alert('Error', 'Gagal membuat link ujian');
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
