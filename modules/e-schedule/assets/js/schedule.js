/**
 * E-Schedule Module — Single Page Application (SPA)
 */

const Schedule = {
    state: {
        currentRoute: 'dashboard',
        params: {},
        user: window.SCHEDULE_CONFIG.user,
        school: window.SCHEDULE_CONFIG.school,
        token: window.SCHEDULE_CONFIG.token,
        baseUrl: window.SCHEDULE_CONFIG.baseUrl,
        apiUrl: window.SCHEDULE_CONFIG.baseUrl + 'modules/e-schedule/api/'
    },

    // Helpers
    getInitials(name) { 
        return name ? name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) : '?'; 
    },
    formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    },
    formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num || 0);
    },


    init() {
        try {
            this.bindEvents();
            this.renderSidebar();
            this.loadRouteFromHash();
            
            // Hide global loader
            setTimeout(() => {
                $('#globalLoader').addClass('hidden');
                setTimeout(() => $('#globalLoader').remove(), 500);
            }, 500);
        } catch(e) {
            console.error('Initialisation failed', e);
            $('#globalLoader').remove();
        }
    },

    bindEvents() {
        window.addEventListener('hashchange', () => this.loadRouteFromHash());
        $('#menuToggle').on('click', () => this.toggleSidebar());
        $('#sidebarOverlay').on('click', () => this.toggleSidebar(false));
    },

    api(endpoint, options = {}) {
        return $.ajax({
            url: this.state.apiUrl + endpoint,
            method: options.method || 'GET',
            data: options.data,
            processData: !(options.data instanceof FormData),
            contentType: options.data instanceof FormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
            headers: { 'Authorization': 'Bearer ' + this.state.token }
        }).fail(xhr => {
            if (xhr.status === 401) {
                EModal.toast({ type: 'error', title: 'Sesi Berakhir', message: 'Silakan masuk kembali.' });
            }
        });
    },

    renderSidebar() {
        const u = this.state.user;
        let navHtml = `
            <div class="sch-nav-group">
                <div class="sch-nav-label">Menu Utama</div>
                <button class="sch-nav-item" data-route="dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Dashboard
                </button>
            </div>
            <div class="sch-nav-group">
                <div class="sch-nav-label">Data Master</div>
                <button class="sch-nav-item" data-route="jam">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Jam Belajar
                </button>
                <button class="sch-nav-item" data-route="mapel">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    Mata Pelajaran
                </button>
                <button class="sch-nav-item" data-route="kelas">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Data Kelas
                </button>
                <button class="sch-nav-item" data-route="guru">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Data Guru
                </button>
            </div>
            <div class="sch-nav-group">
                <div class="sch-nav-label">Penugasan & Integrasi</div>
                <button class="sch-nav-item" data-route="distribusi">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14h6"/><path d="M9 10h6"/></svg>
                    Distribusi Mengajar
                </button>
                <button class="sch-nav-item" data-route="kesediaan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
                    Kesediaan Guru
                </button>
            </div>
            <div class="sch-nav-group">
                <div class="sch-nav-label">Manajemen Jadwal</div>
                <button class="sch-nav-item" data-route="jadwal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                    Generate Jadwal
                </button>
            </div>
        `;

        $('#sidebarNav').html(navHtml);
        
        // Bind sidebar clicks
        $('.sch-nav-item').on('click', (e) => {
            const route = $(e.currentTarget).data('route');
            this.navigate(route);
        });

        // Update user info
        $('#sidebarAvatar').text(this.getInitials(u.nama_lengkap));
        $('#sidebarUserName').text(u.nama_lengkap);
        $('#sidebarUserRole').text((u.role === 'superadmin' ? 'Administrator' : 'User'));
    },

    toggleSidebar(show = null) {
        if (show === null) {
            $('#schSidebar').toggleClass('show');
            $('#sidebarOverlay').toggleClass('show');
        } else if (show) {
            $('#schSidebar').addClass('show');
            $('#sidebarOverlay').addClass('show');
        } else {
            $('#schSidebar').removeClass('show');
            $('#sidebarOverlay').removeClass('show');
        }
    },

    navigate(route, params = {}) {
        window.location.hash = `#/${route}` + (Object.keys(params).length ? `?${$.param(params)}` : '');
    },

    reloadCurrentPage() {
        this.renderPage(this.state.currentRoute, this.state.params);
    },

    loadRouteFromHash() {
        const hash = window.location.hash || '#/dashboard';
        const parts = hash.replace('#/', '').split('?');
        const route = parts[0] || 'dashboard';
        const searchParams = new URLSearchParams(parts[1] || '');
        const params = Object.fromEntries(searchParams.entries());

        this.state.currentRoute = route;
        this.state.params = params;

        // Update active menu
        $('.sch-nav-item').removeClass('active');
        $(`.sch-nav-item[data-route="${route.split('/')[0]}"]`).addClass('active');

        this.toggleSidebar(false);
        this.renderPage(route, params);
    },

    setBreadcrumbs(items) {
        const $breadcrumb = $('#breadcrumb');
        $breadcrumb.empty();
        $breadcrumb.append(`<a href="#/dashboard" style="display:inline-flex;align-items:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="margin-right:2px"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></a>`);
        items.forEach((item, index) => {
            $breadcrumb.append('<span class="sep">/</span>');
            if (item.route) {
                const query = item.params ? `?${$.param(item.params)}` : '';
                $breadcrumb.append(`<a href="#/${item.route}${query}">${item.label}</a>`);
            } else {
                $breadcrumb.append(`<span class="current">${item.label}</span>`);
            }
        });
    },

    renderPage(route, params) {
        const $content = $('#mainContent');
        const $title = $('#pageTitle');

        switch (route) {
            case 'dashboard':
                $title.text('Dashboard');
                this.setBreadcrumbs([]);
                this.renderDashboard($content);
                break;
            case 'jam':
                $title.text('Data Jam Belajar');
                this.setBreadcrumbs([{ label: 'Master' }, { label: 'Jam Belajar' }]);
                this.renderJam($content);
                break;
            case 'mapel':
                $title.text('Data Mata Pelajaran');
                this.setBreadcrumbs([{ label: 'Master' }, { label: 'Mapel' }]);
                this.renderMaster('mapel', $content, { title: 'Mata Pelajaran', field1: 'kode_mapel', label1: 'Kode Mapel', field2: 'nama_mapel', label2: 'Nama Mapel' });
                break;
            case 'kelas':
                $title.text('Data Kelas');
                this.setBreadcrumbs([{ label: 'Master' }, { label: 'Kelas' }]);
                this.renderMaster('kelas', $content, { title: 'Data Kelas', field1: 'rombel', label1: 'Rombel', field2: 'nama_kelas', label2: 'Nama Kelas', isRombel: true });
                break;
            case 'guru':
                $title.text('Data Guru');
                this.setBreadcrumbs([{ label: 'Master' }, { label: 'Guru' }]);
                this.renderMaster('guru', $content, { title: 'Data Guru', field1: 'kode_guru', label1: 'Kode Guru', field2: 'nama_guru', label2: 'Nama Guru' });
                break;
            case 'distribusi':
                $title.text('Distribusi Mengajar');
                this.setBreadcrumbs([{ label: 'Penugasan' }, { label: 'Distribusi JP' }]);
                this.renderDistribusi($content);
                break;
            case 'kesediaan':
                $title.text('Kesediaan Mengajar');
                this.setBreadcrumbs([{ label: 'Penugasan' }, { label: 'Kesediaan Guru' }]);
                this.renderKesediaan($content);
                break;
            case 'jadwal':
                $title.text('Manajemen Jadwal');
                this.setBreadcrumbs([{ label: 'Jadwal' }, { label: 'Generate & Review' }]);
                this.renderJadwal($content);
                break;
            default:
                $content.html('<div class="sch-empty"><h3>Halaman Tidak Ditemukan</h3></div>');
        }
    },

    // ==============================================
    // DASHBOARD VIEWS
    // ==============================================

    renderDashboard($container) {
        $container.html(`
            <div class="sch-stats-grid">
                <div class="sch-stat-card">
                    <div class="sch-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="sch-stat-info">
                        <h4 id="dashGuru">-</h4>
                        <p>Total Guru</p>
                    </div>
                </div>
                <div class="sch-stat-card">
                    <div class="sch-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    </div>
                    <div class="sch-stat-info">
                        <h4 id="dashKelas">-</h4>
                        <p>Total Kelas</p>
                    </div>
                </div>
                <div class="sch-stat-card">
                    <div class="sch-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="sch-stat-info">
                        <h4 id="dashKeb">-</h4>
                        <p>Kebutuhan JP Sekolah</p>
                    </div>
                </div>
            </div>
            
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Aksi Cepat</h3>
                </div>
                <div class="sch-card-body" style="display:flex;gap:12px;flex-wrap:wrap">
                    <button class="btn btn-outline" onclick="Schedule.navigate('distribusi')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg> Atur Penugasan
                    </button>
                    <button class="btn btn-outline" onclick="Schedule.navigate('kesediaan')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><path d="M9 16l2 2 4-4"/></svg> Matriks Kesediaan
                    </button>
                    <button class="btn btn-primary" onclick="Schedule.navigate('jadwal')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg> Lihat & Generate Jadwal
                    </button>
                </div>
            </div>
        `);

        this.api('jadwal.php?action=stats').done(res => {
            if (res.success) {
                $('#dashGuru').text(res.data.total_guru);
                $('#dashKelas').text(res.data.total_kelas);
                $('#dashKeb').text(res.data.total_kebutuhan_jp_sekolah + ' Jam');
            }
        });
    },


    // ==============================================
    // GENERIC MASTER VIEWS (Mapel, Kelas, Guru)
    // ==============================================

    renderMaster(type, $container, meta) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3>${meta.title}</h3>
                    <div class="sch-toolbar">
                        <button class="btn btn-outline" onclick="Schedule.exportData('${type}', '${meta.label1}', '${meta.label2}', '${meta.field1}', '${meta.field2}')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export</button>
                        <button class="btn btn-outline" onclick="Schedule.importData('${type}')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Import</button>
                        <button class="btn btn-primary" onclick="Schedule.formMaster('${type}', null, '${meta.field1}','${meta.label1}','${meta.field2}','${meta.label2}', ${meta.isRombel})">Tambah Baru</button>
                    </div>
                </div>
                <div class="sch-card-body">
                    <div class="sch-table-wrapper" id="${type}Table"><div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat data...</h3></div></div>
                </div>
            </div>
        `);
        this.loadMaster(type, meta);
    },

    loadMaster(type, meta) {
        this.api(type + '.php?action=list').done(res => {
            if (!res.data.length) {
                $('#' + type + 'Table').html('<div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg><h3>Tidak ada data</h3><p>Data belum ditambahkan ke dalam sistem.</p></div>');
                return;
            }
            // Add a global memory reference for export
            this.state[type + 'Data'] = res.data;

            const rows = res.data.map(d => `
                <tr>
                    <td>${d[meta.field1]}</td>
                    <td><strong>${d[meta.field2]}</strong></td>
                    <td style="width:120px">
                        <div class="sch-actions">
                            <button class="sch-btn-icon" onclick="Schedule.formMaster('${type}', ${d.id}, '${meta.field1}','${meta.label1}','${meta.field2}','${meta.label2}', ${meta.isRombel}, '${d[meta.field1]}', '${d[meta.field2]}')" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="sch-btn-icon danger" onclick="Schedule.deleteMaster('${type}', ${d.id})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                        </div>
                    </td>
                </tr>
            `).join('');

            $('#' + type + 'Table').html(`
                <table class="sch-table">
                    <thead><tr><th>${meta.label1}</th><th>${meta.label2}</th><th>Aksi</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        });
    },

    formMaster(type, id, f1, l1, f2, l2, isRombel, val1='', val2='') {
        let input1Html = `<input class="form-input" id="fmInput1" required value="${val1}">`;
        if (isRombel) {
            let opts = '';
            for(let i=1; i<=12; i++) opts += `<option value="${i}" ${val1==i?'selected':''}>Rombel ${i}</option>`;
            input1Html = `<select class="form-select" id="fmInput1">${opts}</select>`;
        }

        EModal.form({
            title: id ? 'Edit Data' : 'Tambah Data',
            form: `
                <input type="hidden" id="fmId" value="${id || ''}">
                <div class="sch-form-row">
                    <div class="form-group"><label>${l1}</label>${input1Html}</div>
                    <div class="form-group"><label>${l2}</label><input class="form-input" id="fmInput2" required value="${val2}"></div>
                </div>
            `,
            onConfirm: () => {
                const data = { id: $('#fmId').val() };
                data[f1] = $('#fmInput1').val();
                data[f2] = $('#fmInput2').val();
                
                const action = id ? 'update' : 'create';
                this.api(type + '.php?action=' + action, { method: 'POST', data }).done(res => {
                    if (res.success) {
                        EModal.closeAll();
                        this.reloadCurrentPage();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                    }
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message });
                });
                return false; // Prevent auto close to wait for API
            }
        });
    },

    deleteMaster(type, id) {
        EModal.confirm({
            title: 'Hapus Data',
            message: 'Yakin menghapus data ini? Semua data terkait (Distribusi/Kesediaan) mungkin ikut terhapus.',
            type: 'danger',
            onConfirm: () => {
                this.api(type + '.php?action=delete', { method: 'POST', data: {id} }).done(res => {
                    this.reloadCurrentPage();
                    EModal.toast({ type: 'success', message: 'Dihapus' });
                });
            }
        });
    },

    // ==============================================
    // JAM BELAJAR VIEWS
    // ==============================================
    renderJam($container) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3>Daftar Jam Belajar</h3>
                    <div class="sch-toolbar">
                        <button class="btn btn-outline" onclick="Schedule.exportData('jam')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export</button>
                        <button class="btn btn-outline" onclick="Schedule.importData('jam')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Import</button>
                        <button class="btn btn-outline" id="btnCopyJam">Salin Data Hari</button>
                        <button class="btn btn-primary" onclick="Schedule.formJam()">Tambah Jam</button>
                    </div>
                </div>
                <div class="sch-card-body">
                    <div class="sch-table-wrapper" id="jamTable"><div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat data...</h3></div></div>
                </div>
            </div>
        `);
        
        $('#btnCopyJam').on('click', () => {
            EModal.form({
                title: 'Salin Jam Belajar',
                form: `
                    <div class="form-group"><label>Dari Hari (Sumber)</label>
                        <select class="form-select" id="copySrc"><option>Senin</option><option>Selasa</option><option>Rabu</option><option>Kamis</option><option>Jumat</option><option>Sabtu</option></select>
                    </div>
                    <div class="form-group"><label>Ke Hari (Target)</label>
                        <select class="form-select" id="copyTgt"><option>Senin</option><option>Selasa</option><option>Rabu</option><option>Kamis</option><option>Jumat</option><option>Sabtu</option></select>
                    </div>
                    <p style="color:var(--text-muted);font-size:0.8rem">Data target yang sudah ada akan dihapus dan ditimpa secara penuh.</p>
                `,
                onConfirm: () => {
                    const data = { source_hari: $('#copySrc').val(), target_hari: $('#copyTgt').val() };
                    this.api('jam.php?action=copy', {method:'POST', data}).done(res => {
                        EModal.closeAll();
                        this.reloadCurrentPage();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: 'Data jam berhasil disalin.' });
                    });
                    return false;
                }
            });
        });

        this.api('jam.php?action=list').done(res => {
            this.state.jamData = res.data;
            if (!res.data.length) { $('#jamTable').html('<div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg><h3>Belum ada jam belajar</h3><p>Tambahkan jam belajar per hari untuk menyusun blok jadwal.</p></div>'); return; }
            
            const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            let maxJamKe = 0;
            res.data.forEach(d => {
                if (parseInt(d.jam_ke) > maxJamKe) maxJamKe = parseInt(d.jam_ke);
            });

            if (maxJamKe === 0) maxJamKe = 1;

            let html = '<div style="overflow-x:auto"><table class="sch-table matrix-table" style="min-width:1000px;border-collapse:collapse;width:100%"><thead><tr><th style="width:80px;text-align:center;border-bottom:2px solid var(--border-color);padding:12px;background:#f8fafc">Jam Ke</th>';
            days.forEach(day => {
                html += `<th style="text-align:center;border-bottom:2px solid var(--border-color);padding:12px;background:#f8fafc">${day}</th>`;
            });
            html += '</tr></thead><tbody>';

            for (let i = 1; i <= maxJamKe; i++) {
                html += `<tr><td style="text-align:center;font-weight:bold;border-bottom:1px solid var(--border-color);padding:12px">${i}</td>`;
                days.forEach(day => {
                    const cell = res.data.find(x => x.hari === day && parseInt(x.jam_ke) === i);
                    if (cell) {
                        html += `<td style="border-bottom:1px solid var(--border-color);padding:8px">
                            <div style="padding:10px;border:1px solid var(--border-color);border-radius:8px;background:${cell.tipe !== 'Pembelajaran' ? '#FFF3E0' : '#fff'};position:relative;min-height:85px;box-shadow:0 1px 2px rgba(0,0,0,0.05)">
                                <div style="font-size:0.7rem;padding:3px 6px;border-radius:4px;background:rgba(0,0,0,0.05);display:inline-block;margin-bottom:6px;color:var(--text-muted);font-weight:500">${cell.tipe}</div>
                                <div style="font-weight:600;font-size:0.85rem;margin-bottom:12px;color:${cell.tipe !== 'Pembelajaran' ? '#E65100' : 'var(--text-main)'};line-height:1.3">${cell.nama_jam}</div>
                                <div class="sch-actions" style="position:absolute;bottom:8px;right:8px;gap:6px;display:flex">
                                    <button class="sch-btn-icon" style="width:26px;height:26px;background:#f1f5f9;border-radius:4px;display:flex;align-items:center;justify-content:center" onclick="Schedule.formJam(${cell.id})" title="Edit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                    <button class="sch-btn-icon danger" style="width:26px;height:26px;background:#FEE2E2;color:#DC2626;border-radius:4px;display:flex;align-items:center;justify-content:center" onclick="Schedule.deleteMaster('jam', ${cell.id})" title="Hapus"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                                </div>
                            </div>
                        </td>`;
                    } else {
                        html += `<td style="background:#fafafa;border-bottom:1px solid var(--border-color);padding:8px">
                            <div style="height:100%;min-height:85px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:0.8rem;border:1px dashed #e2e8f0;border-radius:8px">
                                Kosong
                            </div>
                        </td>`;
                    }
                });
                html += '</tr>';
            }
            html += '</tbody></table></div>';
            $('#jamTable').html(html);
        });
    },

    formJam(id = null) {
        let isEdit = id !== null;
        EModal.form({
            title: isEdit ? 'Edit Jam Belajar' : 'Tambah Jam Belajar',
            form: `
                <input type="hidden" id="fId" value="${id || ''}">
                <div class="sch-form-row">
                    <div class="form-group"><label>Hari</label><select class="form-select" id="fHari"><option>Senin</option><option>Selasa</option><option>Rabu</option><option>Kamis</option><option>Jumat</option><option>Sabtu</option></select></div>
                    <div class="form-group"><label>Jam Ke</label><input type="number" class="form-input" id="fJamKe" min="1" required></div>
                </div>
                <div class="form-group"><label>Tipe</label>
                    <select class="form-select" id="fTipe">
                        <option value="Pembelajaran">Pembelajaran (Bisa diisi Mapel)</option>
                        <option value="Istirahat">Istirahat</option>
                        <option value="Upacara">Upacara</option>
                        <option value="Pembiasaan">Pembiasaan</option>
                    </select>
                </div>
                <div class="form-group"><label>Nama Jam</label><input class="form-input" id="fNama" required placeholder="Contoh: Jam Pelajaran 1 / Upacara Bendera"></div>
            `,
            onOpen: () => {
                $('#fTipe').on('change', function() {
                    if ($(this).val() !== 'Pembelajaran') {
                        $('#fNama').val($(this).val()).prop('readonly', true).css('background', '#f1f5f9');
                    } else {
                        $('#fNama').prop('readonly', false).css('background', '#fff').val('');
                    }
                });

                if (isEdit) {
                    const row = this.state.jamData.find(x => x.id == id);
                    if(row) {
                        $('#fHari').val(row.hari); $('#fJamKe').val(row.jam_ke); 
                        $('#fTipe').val(row.tipe); $('#fNama').val(row.nama_jam);
                        if(row.tipe !== 'Pembelajaran') $('#fNama').prop('readonly', true).css('background', '#f1f5f9');
                    }
                }
            },
            onConfirm: () => {
                const data = { id: $('#fId').val(), hari: $('#fHari').val(), jam_ke: $('#fJamKe').val(), tipe: $('#fTipe').val(), nama_jam: $('#fNama').val() };
                const act = isEdit ? 'update' : 'create';
                this.api('jam.php?action=' + act, { method:'POST', data }).done(res => {
                    EModal.closeAll(); this.reloadCurrentPage();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: isEdit ? 'Jam belajar diperbarui.' : 'Jam belajar ditambahkan.' });
                });
                return false;
            }
        });
    },

    // ==============================================
    // DISTRIBUSI MENGAJAR
    // ==============================================
    renderDistribusi($container) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3>Penugasan / Distribusi Mengajar</h3>
                    <div class="sch-toolbar">
                        <button class="btn btn-outline" onclick="Schedule.exportData('distribusi')">Export</button>
                        <button class="btn btn-outline" onclick="Schedule.importData('distribusi')">Import</button>
                        <button class="btn btn-primary" onclick="Schedule.formDist()">Tambah Distribusi</button>
                    </div>
                </div>
                <div class="sch-card-body">
                    <div style="margin-bottom:16px;display:flex;gap:12px;align-items:center">
                        <span style="font-size:0.85rem">Filter:</span>
                        <select id="distFilterKelas" class="form-select" style="width:200px"><option value="">Semua Kelas</option></select>
                    </div>
                    <div class="sch-table-wrapper" id="distTable"><div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat data...</h3></div></div>
                </div>
            </div>
        `);
        
        // Fetch kelas for filter
        this.api('kelas.php?action=list').done(res => {
            res.data.forEach(k => $('#distFilterKelas').append(`<option value="${k.id}">Rombel ${k.rombel} - ${k.nama_kelas}</option>`));
        });

        $('#distFilterKelas').on('change', (e) => this.loadDistribusi($(e.target).val()));
        this.loadDistribusi();
    },

    loadDistribusi(kelasId = '') {
        this.api('distribusi.php?action=list').done(res => {
            let data = res.data;
            if (kelasId) data = data.filter(d => d.kelas_id == kelasId);
            this.state.distData = res.data; // save all for export

            if (!data.length) { $('#distTable').html('<div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg><h3>Tidak ada distribusi</h3><p>Pilih kelas lain atau tambahkan penugasan baru.</p></div>'); return; }

            let totalJp = 0;
            let html = '<table class="sch-table"><thead><tr><th>No</th><th>Guru</th><th>Kelas</th><th>Mata Pelajaran</th><th>JP</th><th>Aksi</th></tr></thead><tbody>';
            data.forEach((d, i) => {
                totalJp += parseInt(d.jp);
                html += `<tr>
                    <td>${i+1}</td>
                    <td><strong>${d.nama_guru}</strong><br><small style="color:var(--text-muted)">${d.kode_guru}</small></td>
                    <td>Rbl ${d.rombel} - ${d.nama_kelas}</td>
                    <td>${d.nama_mapel}</td>
                    <td><span style="background:var(--primary-light);padding:2px 8px;border-radius:12px;font-weight:600">${d.jp}</span></td>
                    <td>
                        <div class="sch-actions">
                            <button class="sch-btn-icon" onclick="Schedule.formDist(${d.id})" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="sch-btn-icon danger" onclick="Schedule.deleteMaster('distribusi', ${d.id})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                        </div>
                    </td>
                </tr>`;
            });
            html += `</tbody><tfoot><tr><td colspan="4" style="text-align:right;font-weight:700">Total JP:</td><td colspan="2" style="font-weight:700;color:var(--primary-dark)">${totalJp} Jam</td></tr></tfoot></table>`;
            $('#distTable').html(html);
        });
    },

    formDist(id = null) {
        // Prepare options
        let optG = '', optK = '', optM = '';
        
        let promiseG = this.api('guru.php?action=list');
        let promiseK = this.api('kelas.php?action=list');
        let promiseM = this.api('mapel.php?action=list');

        Promise.all([promiseG, promiseK, promiseM]).then(results => {
            results[0].data.forEach(x => optG += `<option value="${x.id}">${x.kode_guru} - ${x.nama_guru}</option>`);
            results[1].data.forEach(x => optK += `<option value="${x.id}">Rbl ${x.rombel} - ${x.nama_kelas}</option>`);
            results[2].data.forEach(x => optM += `<option value="${x.id}">${x.kode_mapel} - ${x.nama_mapel}</option>`);

            EModal.form({
                title: id ? 'Edit Distribusi' : 'Tambah Distribusi Mengajar',
                form: `
                    <input type="hidden" id="fId" value="${id || ''}">
                    <div class="form-group"><label>Pilih Guru</label><select class="form-select" id="fG" required>${optG}</select></div>
                    <div class="form-group"><label>Pilih Kelas</label><select class="form-select" id="fK" required multiple style="height:100px">${optK}</select><p style="font-size:0.75rem;color:gray;margin-top:4px">Bisa pilih lebih dari 1 kelas sekaligus (Ctrl+Click)</p></div>
                    <div class="sch-form-row">
                        <div class="form-group"><label>Mata Pelajaran</label><select class="form-select" id="fM" required>${optM}</select></div>
                        <div class="form-group"><label>Jumlah JP</label><select class="form-select" id="fJp">${[1,2,3,4,5,6,7].map(j=>'<option value="'+j+'">'+j+' JP</option>').join('')}</select></div>
                    </div>
                `,
                onOpen: () => {
                    if (id) {
                        const row = this.state.distData.find(x => x.id == id);
                        if(row) { $('#fG').val(row.guru_id); $('#fK').val([row.kelas_id]).prop('multiple', false).css('height','auto'); $('#fM').val(row.mapel_id); $('#fJp').val(row.jp); }
                    }
                },
                onConfirm: () => {
                    const gId = $('#fG').val(), mId = $('#fM').val(), jp = $('#fJp').val();
                    const kIds = $('#fK').val(); // array multiple
                    if(!gId || !mId || !kIds || kIds.length===0) return false;

                    // If multiple inserts, recursive or Promise.all. Simpler: loop.
                    let targetAction = id ? 'update' : 'create';
                    
                    let requests = kIds.map(kId => {
                        let data = { guru_id: gId, kelas_id: kId, mapel_id: mId, jp: jp };
                        if(id) data.id = id;
                        return this.api('distribusi.php?action='+targetAction, {method:'POST', data});
                    });

                    Promise.all(requests).then(() => {
                        EModal.closeAll(); this.reloadCurrentPage();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: id ? 'Distribusi diperbarui.' : 'Distribusi berhasil ditambahkan.' });
                    });
                    return false;
                }
            });
        });
    },

    // ==============================================
    // KESEDIAAN GURU
    // ==============================================
    renderKesediaan($container) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3>Matriks Ketersediaan Guru</h3>
                    <div class="sch-toolbar">
                        <button class="btn btn-outline" onclick="Schedule.exportData('kesediaan')">Export</button>
                        <button class="btn btn-outline" onclick="Schedule.importData('kesediaan')">Import</button>
                    </div>
                </div>
                <div class="sch-card-body">
                    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px">Pilih hari dimana guru <strong>siap</strong> untuk mengajar. Hari yang kosong dan tipe non-pembelajaran diabaikan.</p>
                    <div class="matrix-container" id="kesediaanTable"><div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat matriks...</h3></div></div>
                    <div style="margin-top:20px;text-align:right">
                        <button class="btn btn-primary" id="btnSaveKesediaan" style="display:none">Simpan Ketersediaan</button>
                    </div>
                </div>
            </div>
        `);

        // Fetch jam and guru kesediaan
        Promise.all([
            this.api('jam.php?action=list'),
            this.api('kesediaan.php?action=list')
        ]).then(res => {
            const jams = res[0].data.filter(j => j.tipe === 'Pembelajaran'); // Only learning block
            const data = res[1].data;
            this.state.jamMatrixData = jams;
            
            // grouping jams by Hari
            let daysMap = {};
            jams.forEach(j => { if(!daysMap[j.hari]) daysMap[j.hari] = []; daysMap[j.hari].push(j); });
            const days = Object.keys(daysMap);

            if (!data.length || !jams.length) { $('#kesediaanTable').html('<div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg><h3>Master Jam / Guru belum lengkap</h3><p>Pastikan jam belajar harian dan data guru sudah disiapkan terlebih dahulu.</p></div>'); return; }

            let html = '<table class="matrix-table"><thead><tr><th style="min-width:150px;text-align:left">Nama Guru</th><th style="width:80px">Aksi</th>';
            days.forEach(d => html += `<th>${d} <br><small style="font-weight:400">Jam: ${daysMap[d].map(j=>j.jam_ke).join(',')}</small></th>`);
            html += '</tr></thead><tbody>';

            data.forEach(g => {
                html += `<tr data-gid="${g.id}">
                    <td style="text-align:left"><strong>${g.nama_guru}</strong><br><small>${g.kode_guru}</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline" onclick="Schedule.checkAllRow(${g.id}, true)">All</button>
                        <button class="btn btn-sm btn-outline" onclick="Schedule.checkAllRow(${g.id}, false)">0</button>
                    </td>`;
                
                days.forEach(d => {
                    html += `<td><div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:center">`;
                    daysMap[d].forEach(j => {
                        const isChecked = g.jam_ids.includes(j.id) ? 'checked' : '';
                        html += `
                        <label title="Jam ${j.jam_ke}">
                            <input type="checkbox" class="matrix-checkbox jcb-${g.id}" style="display:none" value="${j.id}" ${isChecked}>
                            <div class="chk-btn">${j.jam_ke}</div>
                        </label>`;
                    });
                    html += `</div></td>`;
                });
                html += '</tr>';
            });
            html += '</tbody></table>';

            $('#kesediaanTable').html(html);
            $('#btnSaveKesediaan').show().on('click', () => this.saveKesediaan(data));
        });
    },

    checkAllRow(gid, state) {
        $(`.jcb-${gid}`).prop('checked', state);
    },

    saveKesediaan(guruList) {
        const payload = [];
        let requests = [];
        guruList.forEach(g => {
            let jids = [];
            $(`.jcb-${g.id}:checked`).each(function() { jids.push(parseInt($(this).val())); });
            requests.push(this.api('kesediaan.php?action=save', { method:'POST', data: { guru_id: g.id, jam_ids: jids }}));
        });
        
        EModal.loading('Menyimpan matriks...');
        Promise.all(requests).then(() => {
            EModal.closeAll();
            EModal.toast({ type:'success', message:'Kesediaan disimpan.' });
        }).catch(()=>EModal.closeAll());
    },

    // ==============================================
    // ENGINE JADWAL
    // ==============================================
    renderJadwal($container) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3>Manajemen Jadwal & Generator</h3>
                </div>
                <div class="sch-card-body">
                    <div style="display:flex;gap:12px;background:var(--bg-color);padding:24px;border-radius:12px;margin-bottom:24px;align-items:center;justify-content:space-between;flex-wrap:wrap">
                        <div>
                            <h4 style="margin:0 0 8px">Engine Algoritma Heuristik</h4>
                            <p style="margin:0;font-size:0.85rem;color:var(--text-muted)">Menghasilkan jadwal berdasarkan distribusi dan mematuhi constraints ketersediaan dan aturan blok mapel.</p>
                        </div>
                        <button class="btn btn-primary" onclick="Schedule.generateJadwal()" style="padding:12px 24px;font-size:1.1rem">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg> Generate Jadwal
                        </button>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                        <select id="jdwFilter" class="form-select" style="max-width:300px"><option value="">Semua Kelas</option></select>
                        <button class="btn btn-outline" onclick="window.print()">Cetak PDF</button>
                    </div>
                    <div id="jdwViewer"><div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat jadwal...</h3></div></div>
                </div>
            </div>
        `);
        
        this.api('kelas.php?action=list').done(res => {
            res.data.forEach(k => $('#jdwFilter').append(`<option value="${k.id}">Rombel ${k.rombel} - ${k.nama_kelas}</option>`));
        });
        $('#jdwFilter').on('change', (e) => this.viewJadwal($(e.target).val()));
        this.viewJadwal('');
    },

    generateJadwal() {
        EModal.confirm({
            title: 'Generate Baru?',
            message: 'Proses ini akan <strong>menghapus semua jadwal yang ada saat ini</strong> dan membuat ulang acakan. Lanjutkan?',
            onConfirm: () => {
                const loader = EModal.loading('Engine sedang bekerja... mencari pattern terbaik.');
                this.api('jadwal.php?action=generate', { method: 'POST' }).done(res => {
                    EModal.close(loader);
                    EModal.info({ title: 'Selesai', message: res.message });
                    this.viewJadwal('');
                });
            }
        });
    },

    viewJadwal(kelasId) {
        // Fetch structural jam to build the grid headers
        Promise.all([
            this.api('jadwal.php?action=list' + (kelasId ? `&kelas_id=${kelasId}` : '')),
            this.api('jam.php?action=list')
        ]).then(res => {
            const jadwal = res[0].data;
            const jams = res[1].data;
            if(!jadwal.length) { $('#jdwViewer').html('<div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg><h3>Jadwal Masih Kosong</h3><p>Silakan klik tombol <strong>Generate Jadwal</strong> untuk mulai memproses distribusi mengajar.</p></div>'); return; }

            // Because a full school view is huge, we'll split by Kelas if no filter, or show one if filter.
            // Using a simple grid approach grouped by Kelas.
            const kelasGroups = {};
            jadwal.forEach(j => {
                if(!kelasGroups[j.nama_kelas]) kelasGroups[j.nama_kelas] = [];
                kelasGroups[j.nama_kelas].push(j);
            });

            // Group Jam structure
            let daysMap = {};
            let maxJams = 0;
            jams.forEach(j => { 
                if(!daysMap[j.hari]) daysMap[j.hari] = []; 
                daysMap[j.hari].push(j); 
            });
            Object.values(daysMap).forEach(arr => { if(arr.length > maxJams) maxJams = arr.length; });
            const daysArr = Object.keys(daysMap);

            let html = '';
            for(let kname in kelasGroups) {
                const kjadwal = kelasGroups[kname];
                
                html += `<div style="margin-bottom:40px">
                    <h4 style="font-size:1.1rem;padding:8px 16px;background:var(--primary);color:white;display:inline-block;border-radius:8px">Kelas: ${kname}</h4>
                    <div style="overflow-x:auto;margin-top:12px;border:1px solid var(--border-color)">
                        <table class="sch-table matrix-table" style="min-width:800px;background:white">
                            <thead>
                                <tr><th style="width:100px">HARI / JAM</th>`;
                                for(let idx=1; idx<=maxJams; idx++) html += `<th>Jam ke-${idx}</th>`;
                                html += `</tr>
                            </thead>
                            <tbody>`;
                
                daysArr.forEach(d => {
                    html += `<tr><td style="font-weight:700">${d}</td>`;
                    const dayJams = daysMap[d] || [];
                    
                    for(let idx=0; idx<maxJams; idx++) {
                        const cellJam = dayJams[idx];
                        if (!cellJam) {
                            html += `<td style="background:#f1f5f9"></td>`; // Empty slot (no jam defined for this idx on this day)
                        } else {
                            if (cellJam.tipe !== 'Pembelajaran') {
                                html += `<td style="background:#FFF3E0;color:#F57F17;font-weight:600;font-size:0.8rem">${cellJam.nama_jam}</td>`;
                            } else {
                                // Find exactly if placed here
                                const slotApp = kjadwal.find(x => x.jam_belajar_id == cellJam.id);
                                if (slotApp) {
                                    html += `<td><div style="font-weight:700;color:var(--text-main);font-size:0.85rem">${slotApp.nama_mapel}</div><div style="font-size:0.75rem;color:var(--text-muted)">${slotApp.nama_guru}</div></td>`;
                                } else {
                                    html += `<td></td>`; // Free
                                }
                            }
                        }
                    }
                    html += `</tr>`;
                });
                html += `</tbody></table></div></div>`;
            }

            $('#jdwViewer').html(html);
        });
    },

    // ==============================================
    // SHEETJS IMPORT/EXPORT WRAPPERS
    // ==============================================
    loadSheetJS(callback) {
        if (window.XLSX) { callback(); return; }
        const loader = EModal.loading('Memuat komponen Export/Import...');
        $.getScript('https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js')
            .done(() => { EModal.close(loader); callback(); })
            .fail(() => { EModal.close(loader); EModal.toast({type:'error', message:'Gagal mengunduh modul SheetJS. Periksa internet Anda!'}); });
    },

    exportData(type, l1, l2, f1, f2) {
        this.loadSheetJS(() => {
            let data = [];
            if (['mapel','kelas','guru'].includes(type) && this.state[type+'Data']) {
                data = this.state[type+'Data'].map(row => {
                    let obj = {};
                    obj[l1] = row[f1];
                    obj[l2] = row[f2];
                    return obj;
                });
            } else if (type === 'jam' && this.state.jamData) {
                data = this.state.jamData.map(row => ({ 'Hari': row.hari, 'Jam': row.jam_ke, 'Tipe': row.tipe, 'Nama Jam': row.nama_jam }));
            } else if (type === 'distribusi' && this.state.distData) {
                data = this.state.distData.map(row => ({ 'Kode Guru': row.kode_guru, 'Nama Kelas': row.nama_kelas, 'Kode Mapel': row.kode_mapel, 'JP': row.jp }));
            } else if (type === 'kesediaan') {
                data = [{'Kode Guru': 'CONTOH_KODE', 'Hari': 'senin,selasa,rabu', 'INFO': 'Isi dengan kode guru dan hari koma-sparated / kolom baru.'}];
            } else {
                alert('Tidak ada data untuk di-export.'); return;
            }

            const ws = XLSX.utils.json_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Data");
            XLSX.writeFile(wb, "E-Schedule_Export_" + type + ".xlsx");
        });
    },

    importData(type) {
        this.loadSheetJS(() => {
            // Buat input file on the fly
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = '.xlsx, .xls, .csv';
            fileInput.onchange = (e) => {
                const file = e.target.files[0];
                if (!file) return;

                EModal.confirm({
                    title: 'Konfirmasi Import',
                    message: 'PERINGATAN: Semua data lama pada tabel ini akan <strong>dihapus total</strong> dan diganti dengan data dari file impor. Apakah Anda yakin?',
                    type: 'danger',
                    onConfirm: () => {
                        const reader = new FileReader();
                        reader.onload = (evt) => {
                            const data = evt.target.result;
                            const workbook = XLSX.read(data, {type: 'binary'});
                            const firstSheet = workbook.SheetNames[0];
                            const excelRows = XLSX.utils.sheet_to_row_object_array(workbook.Sheets[firstSheet]);
                            
                            if(excelRows.length > 0) {
                                EModal.loading('Memproses import...');
                                this.api(type + '.php?action=import', { method: 'POST', data: { data: excelRows } }).done(res => {
                                    EModal.closeAll();
                                    EModal.toast({type:'success', message: res.message});
                                    this.reloadCurrentPage();
                                });
                            } else {
                                alert("Data tidak ditemukan di file excel.");
                            }
                        };
                        reader.readAsBinaryString(file);
                    }
                });
            };
            fileInput.click();
        });
    }
};

$(document).ready(() => Schedule.init());
