/**
 * E-Curriculum Core Module (Extended)
 * SPA Routing, State Management, and Views
 * Features: Dashboard, Kelas, Mapel, Mengajar, Jurnal, Absensi,
 *           Ketidakhadiran, Piket, Buku Penghubung, Laporan
 */

const Curriculum = {
    // Global State
    state: {
        user: window.CURRICULUM_CONFIG ? window.CURRICULUM_CONFIG.user : null,
        token: window.CURRICULUM_CONFIG ? window.CURRICULUM_CONFIG.token : null,
        school: window.CURRICULUM_CONFIG ? window.CURRICULUM_CONFIG.school : { nama: 'E-Portal', icon: '' },
        academicYear: window.CURRICULUM_CONFIG ? window.CURRICULUM_CONFIG.academicYear : null,
        currentRoute: 'dashboard',
        params: {}
    },

    // Base URLs
    baseUrl: window.CURRICULUM_CONFIG ? window.CURRICULUM_CONFIG.baseUrl : '/',
    moduleUrl: window.CURRICULUM_CONFIG ? window.CURRICULUM_CONFIG.moduleUrl : 'modules/e-curriculum/',

    /**
     * Initialization
     */
    init() {
        this.bindEvents();
        this.renderSidebar();
        this.loadRouteFromHash();

        // Update profile in sidebar
        if (this.state.user) {
            $('#sidebarAvatar').text(this.getInitials(this.state.user.nama_lengkap));
            $('#sidebarUserName').text(this.state.user.nama_lengkap);
            $('#sidebarUserRole').text(this.state.user.role === 'superadmin' ? 'Super Admin' : this.state.user.role);
        }

        // Hide global loader
        setTimeout(() => {
            $('#globalLoader').addClass('hidden');
            setTimeout(() => $('#globalLoader').remove(), 600);
        }, 800);
    },

    bindEvents() {
        window.addEventListener('hashchange', () => this.loadRouteFromHash());
        $('#menuToggle').on('click', () => this.toggleSidebar());
        $('#sidebarOverlay').on('click', () => this.toggleSidebar(false));
    },

    /**
     * Sidebar Management
     */
    renderSidebar() {
        const u = this.state.user;
        const isAdmin = u.role === 'superadmin';
        
        let navHtml = `
            <div class="acad-nav-group">
                <div class="acad-nav-label">Menu Utama</div>
                <button class="acad-nav-item" data-route="dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Dashboard
                </button>
            </div>
        `;

        if (isAdmin) {
            navHtml += `

                <div class="acad-nav-group">
                    <div class="acad-nav-label">Data Jadwal</div>
                    <button class="acad-nav-item" data-route="sch_jam">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Jam Belajar
                    </button>
                    <button class="acad-nav-item" data-route="sch_mapel">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        Mata Pelajaran
                    </button>
                    <button class="acad-nav-item" data-route="sch_kelas">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Data Kelas
                    </button>
                    <button class="acad-nav-item" data-route="sch_guru">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Data Guru
                    </button>
                    <button class="acad-nav-item" data-route="sch_distribusi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14h6"/><path d="M9 10h6"/></svg>
                        Distribusi Mengajar
                    </button>
                    <button class="acad-nav-item" data-route="sch_kesediaan">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                        Kesediaan Guru
                    </button>
                    <button class="acad-nav-item" data-route="sch_jadwal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Jadwal Pelajaran
                    </button>
                </div>
                <div class="acad-nav-group">
                    <div class="acad-nav-label">Akademik</div>
                    <button class="acad-nav-item" data-route="jurnal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Jurnal Mengajar
                    </button>
                    <button class="acad-nav-item" data-route="absensi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Absensi Siswa
                    </button>
                    <button class="acad-nav-item" data-route="ketidakhadiran">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        Ketidakhadiran
                    </button>
                    <button class="acad-nav-item" data-route="piket">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Piket Guru
                    </button>
                    <button class="acad-nav-item" data-route="buku_penghubung">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Buku Penghubung
                    </button>
                </div>
                <div class="acad-nav-group">
                    <div class="acad-nav-label">Laporan</div>
                    <button class="acad-nav-item" data-route="laporan_jurnal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Laporan Jurnal
                    </button>
                    <button class="acad-nav-item" data-route="laporan_kehadiran">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Laporan Kehadiran
                    </button>
                </div>
            `;
        } else {
            // Teacher menu
            navHtml += `
                <div class="acad-nav-group">
                    <div class="acad-nav-label">Akademik Saya</div>
                    <button class="acad-nav-item" data-route="mengajar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Jadwal Mengajar
                    </button>
                    <button class="acad-nav-item" data-route="jurnal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Jurnal Mengajar
                    </button>
                    <button class="acad-nav-item" data-route="absensi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Absensi Siswa
                    </button>
                    <button class="acad-nav-item" data-route="ketidakhadiran">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        Ketidakhadiran
                    </button>
                    <button class="acad-nav-item" data-route="buku_penghubung">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Buku Penghubung
                    </button>
                </div>
            `;
        }

        $('#sidebarNav').html(navHtml);

        // Click handler
        $('.acad-nav-item').on('click', function() {
            const route = $(this).data('route');
            Curriculum.navigate(route);
        });
    },

    toggleSidebar(show = null) {
        const $sidebar = $('#acadSidebar');
        const $overlay = $('#sidebarOverlay');
        
        if (show === null) {
            $sidebar.toggleClass('show');
            $overlay.toggleClass('show');
        } else if (show) {
            $sidebar.addClass('show');
            $overlay.addClass('show');
        } else {
            $sidebar.removeClass('show');
            $overlay.removeClass('show');
        }
    },

    navigate(route, params = {}) {
        let hash = `#/${route}` + (Object.keys(params).length ? `?${$.param(params)}` : '');
        if (window.location.hash === hash) {
            this.loadRouteFromHash();
        } else {
            window.location.hash = hash;
        }
    },

    reloadCurrentPage() {
        this.loadRouteFromHash();
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
        $('.acad-nav-item').removeClass('active');
        $(`.acad-nav-item[data-route="${route}"]`).addClass('active');

        this.toggleSidebar(false);
        this.renderPage(route, params);
    },

    renderPage(route, params) {
        const $content = $('#mainContent');
        const $title = $('#pageTitle');
        const $breadcrumb = $('#breadcrumb');

        $breadcrumb.empty();

        switch (route) {
            case 'dashboard':
                $title.text('Dashboard');
                this.setBreadcrumbs([]);
                this.renderDashboard($content);
                break;
            case 'kelas':
                $title.text('Manajemen Kelas');
                this.setBreadcrumbs([{ label: 'Kelas' }]);
                this.renderKelas($content);
                break;
            case 'mapel':
                $title.text('Mata Pelajaran');
                this.setBreadcrumbs([{ label: 'Mata Pelajaran' }]);
                this.renderMapel($content);
                break;
            case 'sch_jam':
                $title.text('Data Jam Belajar (Sch)');
                this.setBreadcrumbs([{ label: 'Master' }, { label: 'Jam Belajar' }]);
                this.renderSchJam($content);
                break;
            case 'sch_mapel':
                $title.text('Mata Pelajaran (Sch)');
                this.setBreadcrumbs([{ label: 'Master' }, { label: 'Mata Pelajaran' }]);
                this.renderSchMapel($content);
                break;
            case 'sch_kelas':
                $title.text('Data Kelas (Sch)');
                this.setBreadcrumbs([{ label: 'Master' }, { label: 'Data Kelas' }]);
                this.renderSchKelas($content);
                break;
            case 'sch_guru':
                $title.text('Data Guru (Sch)');
                this.setBreadcrumbs([{ label: 'Master' }, { label: 'Data Guru' }]);
                this.renderSchGuru($content);
                break;
            case 'sch_distribusi':
                $title.text('Distribusi Mengajar');
                this.setBreadcrumbs([{ label: 'Jadwal' }, { label: 'Distribusi Mengajar' }]);
                this.renderSchDistribusi($content);
                break;
            case 'sch_kesediaan':
                $title.text('Kesediaan Guru');
                this.setBreadcrumbs([{ label: 'Jadwal' }, { label: 'Kesediaan Guru' }]);
                this.renderSchKesediaan($content);
                break;
            case 'sch_jadwal':
                $title.text('Jadwal Pelajaran');
                this.setBreadcrumbs([{ label: 'Jadwal' }, { label: 'Generate Jadwal' }]);
                this.renderSchJadwal($content);
                break;
            case 'mengajar':
                $title.text(this.state.user.role === 'superadmin' ? 'Penugasan Mengajar' : 'Jadwal Mengajar Saya');
                this.setBreadcrumbs([{ label: 'Mengajar' }]);
                this.renderMengajar($content);
                break;
            case 'jurnal':
                $title.text('Jurnal Mengajar');
                this.setBreadcrumbs([{ label: 'Jurnal' }]);
                this.renderJurnal($content);
                break;
            case 'absensi':
                $title.text('Absensi Siswa');
                this.setBreadcrumbs([{ label: 'Absensi' }]);
                this.renderAbsensi($content);
                break;
            case 'ketidakhadiran':
                $title.text('Ketidakhadiran Guru');
                this.setBreadcrumbs([{ label: 'Ketidakhadiran' }]);
                this.renderKetidakhadiran($content);
                break;
            case 'piket':
                $title.text('Piket Guru');
                this.setBreadcrumbs([{ label: 'Piket' }]);
                this.renderPiket($content);
                break;
            case 'buku_penghubung':
                $title.text('Buku Penghubung Siswa');
                this.setBreadcrumbs([{ label: 'Buku Penghubung' }]);
                this.renderBukuPenghubung($content);
                break;
            case 'laporan_jurnal':
                $title.text('Laporan Jurnal');
                this.setBreadcrumbs([{ label: 'Laporan Jurnal' }]);
                this.renderLaporanJurnal($content);
                break;
            case 'laporan_kehadiran':
                $title.text('Laporan Kehadiran');
                this.setBreadcrumbs([{ label: 'Laporan Kehadiran' }]);
                this.renderLaporanKehadiran($content);
                break;
            default:
                this.navigate('dashboard');
        }
    },

    setBreadcrumbs(crumbs) {
        const $breadcrumb = $('#breadcrumb');
        let html = `<a href="#/dashboard">E-Curriculum</a>`;
        
        crumbs.forEach(c => {
            html += ` <span class="sep">/</span> `;
            if (c.route) {
                const paramStr = c.params ? `?${$.param(c.params)}` : '';
                html += `<a href="#/${c.route}${paramStr}">${c.label}</a>`;
            } else {
                html += `<span class="current">${c.label}</span>`;
            }
        });

        $breadcrumb.html(html);
    },

    // ==================== DASHBOARD VIEW ====================
    renderDashboard($container) {
        const today = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        $container.html(`
            <div class="acad-stats" id="dashboardStats">
                <div class="acad-stat-card skeleton-module" style="height: 100px;"></div>
                <div class="acad-stat-card skeleton-module" style="height: 100px;"></div>
                <div class="acad-stat-card skeleton-module" style="height: 100px;"></div>
                <div class="acad-stat-card skeleton-module" style="height: 100px;"></div>
            </div>
            <div class="acad-card">
                <div class="acad-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> E-Curriculum Portal</h3>
                </div>
                <div class="acad-card-body" style="line-height:1.7;">
                    <h4 style="margin-top:0; font-family:'Outfit',sans-serif; color:var(--acad-primary);">Selamat Datang di Sistem Informasi Kurikulum</h4>
                    <p style="color:var(--acad-text-muted);">${today}</p>
                    <p>Modul E-Curriculum mengelola data kurikulum sekolah secara lengkap: mata pelajaran, kelas, penugasan mengajar, jurnal mengajar harian, absensi siswa, manajemen ketidakhadiran guru, piket, dan buku penghubung siswa.</p>
                    <div class="dash-actions" style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.navigate('jurnal')">📝 Jurnal Mengajar</button>
                        <button class="btn-acad btn-acad-outline" onclick="Curriculum.navigate('absensi')">✅ Absensi Siswa</button>
                        <button class="btn-acad btn-acad-outline" onclick="Curriculum.navigate('ketidakhadiran')">📋 Ketidakhadiran</button>
                    </div>
                </div>
            </div>
        `);

        this.api('dashboard.php?action=stats').done(res => {
            if (!res.success) return;
            const d = res.data;
            const activeYear = d.academic_year?.tahun_ajaran 
                ? `${d.academic_year.tahun_ajaran} - Semester ${d.academic_year.semester}`
                : 'Belum Ditentukan';
            
            $('#dashboardStats').html(`
                <div class="acad-stat-card slide-up">
                    <div class="acad-stat-icon" style="background: rgba(124, 58, 237, 0.1); color: var(--acad-primary);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                    <div class="acad-stat-info">
                        <h4>${d.total_guru}</h4>
                        <p>Total Guru Aktif</p>
                    </div>
                </div>
                <div class="acad-stat-card slide-up" style="animation-delay: 0.08s">
                    <div class="acad-stat-icon" style="background: rgba(236, 72, 153, 0.1); color: var(--acad-accent);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                    </div>
                    <div class="acad-stat-info">
                        <h4>${d.total_siswa}</h4>
                        <p>Total Siswa</p>
                    </div>
                </div>
                <div class="acad-stat-card slide-up" style="animation-delay: 0.16s">
                    <div class="acad-stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="acad-stat-info">
                        <h4>${d.jurnal_hari_ini}</h4>
                        <p>Jurnal Hari Ini</p>
                    </div>
                </div>
                <div class="acad-stat-card slide-up" style="animation-delay: 0.24s">
                    <div class="acad-stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #EF4444;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <div class="acad-stat-info">
                        <h4>${d.tidak_hadir_hari_ini}</h4>
                        <p>Guru Tidak Hadir</p>
                    </div>
                </div>
            `);
        });
    },

    // ==================== KELAS VIEW ====================
    renderKelas($container) {
        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header">
                    <div>
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Daftar Rombongan Belajar (Kelas)</h3>
                        <p class="acad-subtitle">Kelola pembagian kelas, tingkat pendidikan, beserta guru wali kelas.</p>
                    </div>
                    <div class="acad-toolbar">
                        <button class="btn-acad btn-acad-danger" id="btnBulkDeleteKelas" style="display: none; margin-right: 8px;" onclick="Curriculum.bulkDeleteKelas()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Hapus Terpilih
                        </button>
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.importKelas()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Tarik Data Kelas
                        </button>
                    </div>
                </div>
                <div class="acad-card-body" id="kelasTableWrapper">
                    <div class="skeleton-module" style="height:250px;"></div>
                </div>
            </div>
        `);

        this.loadKelasTable();
    },

    loadKelasTable() {
        this.api('kelas.php?action=list').done(res => {
            if (!res.success || !res.data.length) {
                $('#kelasTableWrapper').html(`
                    <div class="acad-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8"/></svg>
                        <h3>Belum Ada Data Kelas</h3>
                        <p>Klik tombol Tarik Data Kelas untuk menyalin data kelas dari E-Portal Utama.</p>
                    </div>
                `);
                return;
            }

            const rows = res.data.map((k, idx) => `
                <tr class="fade-in" style="animation-delay:${idx*0.04}s">
                    <td style="width: 40px; text-align: center;"><input type="checkbox" class="kelas-checkbox" value="${k.id}" onchange="Curriculum.toggleBulkDeleteKelas()"></td>
                    <td><strong>${this.escapeHtml(k.nama_kelas)}</strong></td>
                    <td>Tingkat ${k.tingkat}</td>
                    <td>${k.wali_nama ? `<strong>${this.escapeHtml(k.wali_nama)}</strong>` : '<span style="color:var(--acad-text-muted)">Belum ditentukan</span>'}</td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-icon" title="Edit" onclick="Curriculum.showKelasForm(${k.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn-icon danger" title="Hapus" onclick="Curriculum.deleteKelas(${k.id}, '${this.escapeHtml(k.nama_kelas)}')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            $('#kelasTableWrapper').html(`
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllKelas" onchange="$('.kelas-checkbox').prop('checked', this.checked); Curriculum.toggleBulkDeleteKelas();"></th>
                                <th>Nama Kelas</th>
                                <th>Tingkat</th>
                                <th>Wali Kelas</th>
                                <th style="width:100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                    </table>
                </div>
            `);
        });
    },

    importKelas() {
        EModal.confirm({
            title: 'Tarik Data Kelas',
            message: 'Tarik semua data kelas dari data Siswa E-Portal ke E-Curriculum? Kelas yang sudah ada tidak akan terhapus, hanya kelas baru yang ditambahkan.',
            type: 'info',
            confirmText: 'Ya, Tarik Data',
            onConfirm: () => {
                const l = EModal.loading('Menarik data kelas...');
                this.api('kelas.php?action=import', { method: 'POST' }).done(res => {
                    EModal.close(l);
                    EModal.toast({ title: 'Berhasil', message: res.message, type: 'success' });
                    this.loadKelasTable();
                }).fail(xhr => {
                    EModal.close(l);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error.' });
                });
            }
        });
    },

    showKelasForm(id = null) {
        const isEdit = id !== null;
        
        this.api('kelas.php?action=teachers').done(resTeachers => {
            if (!resTeachers.success) return;
            const teachers = resTeachers.data;
            
            if (isEdit) {
                this.api(`kelas.php?action=get&id=${id}`).done(res => {
                    if (!res.success) return;
                    this.renderKelasFormModal(res.data, teachers);
                });
            } else {
                this.renderKelasFormModal(null, teachers);
            }
        });
    },

    renderKelasFormModal(data, teachers) {
        const d = data || {};
        
        const teacherOptions = teachers.map(t => 
            `<option value="${t.id}" ${d.wali_id == t.id ? 'selected' : ''}>${this.escapeHtml(t.nama_lengkap)} (${this.escapeHtml(t.username)})</option>`
        ).join('');

        EModal.form({
            title: 'Set Wali Kelas',
            size: 'md',
            form: `
                <div class="form-group-acad">
                    <label class="form-label-acad">Nama Kelas / Rombel</label>
                    <input class="form-input-acad" id="formKelasNama" value="${this.escapeHtml(d.nama_kelas || '')}" disabled>
                </div>
                <div class="form-group-acad">
                    <label class="form-label-acad">Tingkat Pendidikan</label>
                    <select class="form-select-acad" id="formKelasTingkat" disabled>
                        <option value="">Pilih Tingkat...</option>
                        <option value="10" ${d.tingkat == 10 ? 'selected' : ''}>Tingkat 10</option>
                        <option value="11" ${d.tingkat == 11 ? 'selected' : ''}>Tingkat 11</option>
                        <option value="12" ${d.tingkat == 12 ? 'selected' : ''}>Tingkat 12</option>
                    </select>
                </div>
                <div class="form-group-acad">
                    <label class="form-label-acad">Guru Wali Kelas</label>
                    <select class="form-select-acad" id="formKelasWali">
                        <option value="">Tanpa Wali Kelas / Tentukan Nanti...</option>
                        ${teacherOptions}
                    </select>
                </div>
            `,
            confirmText: 'Simpan Perubahan',
            onConfirm: () => {
                const data = {
                    id: d.id,
                    nama_kelas: d.nama_kelas,
                    tingkat: parseInt(d.tingkat, 10),
                    wali_id: $('#formKelasWali').val() || null
                };

                this.api('kelas.php?action=update', {
                    method: 'POST',
                    data: data
                }).done(res => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                    this.loadKelasTable();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal memproses data.' });
                });

                return false;
            }
        });
    },

    deleteKelas(id, name) {
        EModal.confirm({
            title: 'Hapus Kelas',
            message: `Apakah Anda yakin ingin menghapus kelas <strong>${name}</strong>? Tindakan ini juga akan menghapus data penugasan mengajar yang terkait.`,
            type: 'danger',
            confirmText: 'Hapus',
            onConfirm: () => {
                this.api('kelas.php?action=delete', {
                    method: 'POST',
                    data: { id }
                }).done(res => {
                    EModal.toast({ type: 'success', title: 'Terhapus', message: res.message });
                    this.loadKelasTable();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menghapus kelas.' });
                });
            }
        });
    },

    toggleBulkDeleteKelas() {
        const checked = $('.kelas-checkbox:checked').length;
        if (checked > 0) {
            $('#btnBulkDeleteKelas').show();
            $('#btnBulkDeleteKelas').html(`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Hapus Terpilih (${checked})`);
        } else {
            $('#btnBulkDeleteKelas').hide();
        }
        
        const total = $('.kelas-checkbox').length;
        $('#selectAllKelas').prop('checked', total > 0 && checked === total);
    },

    bulkDeleteKelas() {
        const ids = [];
        $('.kelas-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;

        EModal.confirm({
            title: 'Hapus Kelas Massal',
            message: `Yakin ingin menghapus <strong>${ids.length}</strong> kelas yang dipilih? Semua data penugasan terkait juga akan terhapus.`,
            type: 'danger',
            confirmText: 'Ya, Hapus Semua',
            onConfirm: () => {
                const l = EModal.loading('Menghapus kelas...');
                this.api('kelas.php?action=delete', { method: 'POST', data: { ids } }).done(res => {
                    EModal.close(l);
                    EModal.toast({ title: 'Terhapus!', message: res.message, type: 'success' });
                    $('#btnBulkDeleteKelas').hide();
                    this.loadKelasTable();
                }).fail(xhr => {
                    EModal.close(l);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error.' });
                });
            }
        });
    },

    // ==================== MAPEL VIEW ====================
    renderMapel($container) {
        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header">
                    <div>
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> Mata Pelajaran Kurikulum</h3>
                        <p class="acad-subtitle">Kelola daftar mata pelajaran kurikulum sekolah.</p>
                    </div>
                    <div class="acad-toolbar">
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.showMapelForm()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Mapel
                        </button>
                    </div>
                </div>
                <div class="acad-card-body" id="mapelTableWrapper">
                    <div class="skeleton-module" style="height:250px;"></div>
                </div>
            </div>
        `);
        this.loadMapelTable();
    },

    loadMapelTable() {
        this.api('mapel.php?action=list').done(res => {
            if (!res.success || !res.data.length) {
                $('#mapelTableWrapper').html(`<div class="acad-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg><h3>Belum Ada Data Mata Pelajaran</h3><p>Tambahkan mata pelajaran baru.</p></div>`);
                return;
            }
            const rows = res.data.map((m, idx) => `
                <tr class="fade-in" style="animation-delay:${idx*0.04}s">
                    <td><code>${this.escapeHtml(m.kode_mapel)}</code></td>
                    <td><strong>${this.escapeHtml(m.nama_mapel)}</strong></td>
                    <td><span class="badge badge-info">${this.escapeHtml(m.kelompok)}</span></td>
                    <td><strong style="color:var(--acad-primary)">${m.kkm}</strong></td>
                    <td><span class="badge ${m.status == 1 ? 'badge-success' : 'badge-danger'}">${m.status == 1 ? 'Aktif' : 'Nonaktif'}</span></td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-icon" title="Edit" onclick="Curriculum.showMapelForm(${m.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="btn-icon danger" title="Hapus" onclick="Curriculum.deleteMapel(${m.id}, '${this.escapeHtml(m.nama_mapel)}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>
            `).join('');
            $('#mapelTableWrapper').html(`<div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Kode</th><th>Nama Mapel</th><th>Kelompok</th><th>KKM</th><th>Status</th><th style="width:100px;">Aksi</th></tr></thead><tbody>${rows}</tbody></table></div>`);
        });
    },

    showMapelForm(id = null) {
        const isEdit = id !== null;
        if (isEdit) {
            this.api(`mapel.php?action=get&id=${id}`).done(res => {
                if (!res.success) return;
                this.renderMapelFormModal(res.data);
            });
        } else {
            this.renderMapelFormModal(null);
        }
    },

    renderMapelFormModal(data) {
        const isEdit = data !== null;
        const d = data || {};
        EModal.form({
            title: isEdit ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran',
            size: 'md',
            form: `
                <div class="form-group-acad"><label class="form-label-acad">Kode Mapel</label><input class="form-input-acad" id="formMapelKode" value="${this.escapeHtml(d.kode_mapel || '')}" placeholder="contoh: BIN-10" ${isEdit ? 'readonly style="opacity:0.75;"' : ''}></div>
                <div class="form-group-acad"><label class="form-label-acad">Nama Mata Pelajaran</label><input class="form-input-acad" id="formMapelNama" value="${this.escapeHtml(d.nama_mapel || '')}" placeholder="contoh: Bahasa Indonesia"></div>
                <div class="form-group-acad"><label class="form-label-acad">Kelompok</label><select class="form-select-acad" id="formMapelKelompok"><option value="Kelompok A" ${d.kelompok === 'Kelompok A' ? 'selected' : ''}>Kelompok A (Wajib)</option><option value="Kelompok B" ${d.kelompok === 'Kelompok B' ? 'selected' : ''}>Kelompok B (Wajib)</option><option value="Kelompok C" ${d.kelompok === 'Kelompok C' ? 'selected' : ''}>Kelompok C (Peminatan)</option><option value="Pilihan" ${d.kelompok === 'Pilihan' ? 'selected' : ''}>Pilihan</option><option value="Muatan Lokal" ${d.kelompok === 'Muatan Lokal' ? 'selected' : ''}>Muatan Lokal</option></select></div>
                <div class="form-group-acad"><label class="form-label-acad">KKM</label><input type="number" class="form-input-acad" id="formMapelKkm" value="${d.kkm || 75}" min="0" max="100"></div>
                <div class="form-group-acad"><label class="form-label-acad">Status</label><select class="form-select-acad" id="formMapelStatus"><option value="1" ${d.status == 1 ? 'selected' : ''}>Aktif</option><option value="0" ${d.status == 0 ? 'selected' : ''}>Nonaktif</option></select></div>
            `,
            confirmText: 'Simpan Data',
            onConfirm: () => {
                const kode = ($('#formMapelKode').val() || '').trim();
                const nama = ($('#formMapelNama').val() || '').trim();
                const kelompok = $('#formMapelKelompok').val();
                const kkm = $('#formMapelKkm').val();
                const status = $('#formMapelStatus').val();
                if (!kode || !nama) { EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Kode dan nama wajib diisi.' }); return false; }
                const endpoint = isEdit ? 'mapel.php?action=update' : 'mapel.php?action=create';
                const postData = isEdit ? { id: d.id, kode_mapel: kode, nama_mapel: nama, kelompok, kkm: parseInt(kkm, 10), status: parseInt(status, 10) }
                    : { kode_mapel: kode, nama_mapel: nama, kelompok, kkm: parseInt(kkm, 10), status: parseInt(status, 10) };
                this.api(endpoint, { method: 'POST', data: postData }).done(res => {
                    EModal.closeAll(); EModal.toast({ type: 'success', title: 'Berhasil', message: res.message }); this.loadMapelTable();
                }).fail(xhr => { EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal.' }); });
                return false;
            }
        });
    },

    deleteMapel(id, name) {
        EModal.confirm({ title: 'Hapus Mapel', message: `Hapus <strong>${name}</strong>?`, type: 'danger', confirmText: 'Hapus',
            onConfirm: () => {
                this.api('mapel.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    EModal.toast({ type: 'success', title: 'Terhapus', message: res.message }); this.loadMapelTable();
                }).fail(xhr => { EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal.' }); });
            }
        });
    },

    // ==================== MENGAJAR VIEW ====================
    renderMengajar($container) {
        const u = this.state.user;
        const isAdmin = u.role === 'superadmin';
        const activeYear = this.state.academicYear;
        const subTitle = activeYear?.tahun_ajaran 
            ? `Tahun Ajaran: <strong>${this.escapeHtml(activeYear.tahun_ajaran)} Semester ${activeYear.semester}</strong>`
            : '<span style="color:var(--danger)">Tahun Ajaran Belum Diatur</span>';

        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header">
                    <div><h3>Daftar Penugasan Mengajar</h3><p class="acad-subtitle">${subTitle}</p></div>
                    ${isAdmin ? `<div class="acad-toolbar"><button class="btn-acad btn-acad-primary" onclick="Curriculum.showMengajarForm()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah</button></div>` : ''}
                </div>
                <div class="acad-card-body" id="mengajarTableWrapper"><div class="skeleton-module" style="height:250px;"></div></div>
            </div>
        `);
        this.loadMengajarTable();
    },

    loadMengajarTable() {
        this.api('mengajar.php?action=list').done(res => {
            const isAdmin = this.state.user.role === 'superadmin';
            let data = res.data || [];
            if (!isAdmin) data = data.filter(m => m.guru_id == this.state.user.id);
            if (!data.length) {
                $('#mengajarTableWrapper').html(`<div class="acad-empty"><h3>Belum Ada Penugasan</h3><p>${isAdmin ? 'Tambahkan penugasan baru.' : 'Anda belum ditugaskan.'}</p></div>`);
                return;
            }
            const rows = data.map((m, idx) => `
                <tr class="fade-in" style="animation-delay:${idx*0.04}s">
                    ${isAdmin ? `<td><strong>${this.escapeHtml(m.guru_nama)}</strong><br><span class="text-muted" style="font-size:0.75rem;">NIK: ${this.escapeHtml(m.guru_nik || '-')}</span></td>` : ''}
                    <td><strong>${this.escapeHtml(m.nama_mapel)}</strong> <span class="text-muted">(${this.escapeHtml(m.kode_mapel)})</span></td>
                    <td><span class="badge badge-info">${this.escapeHtml(m.nama_kelas)}</span></td>
                    <td>Sem ${m.semester} / ${this.escapeHtml(m.tahun_ajaran)}</td>
                    ${isAdmin ? `<td><button class="btn-icon danger" onclick="Curriculum.deleteMengajar(${m.id}, '${this.escapeHtml(m.guru_nama)}', '${this.escapeHtml(m.nama_mapel)}', '${this.escapeHtml(m.nama_kelas)}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button></td>` : ''}
                </tr>
            `).join('');
            $('#mengajarTableWrapper').html(`<div class="data-table-wrapper"><table class="data-table"><thead><tr>${isAdmin ? '<th>Guru</th>' : ''}<th>Mapel</th><th>Kelas</th><th>TA</th>${isAdmin ? '<th style="width:80px;">Aksi</th>' : ''}</tr></thead><tbody>${rows}</tbody></table></div>`);
        });
    },

    showMengajarForm() {
        this.api('mengajar.php?action=meta').done(res => {
            if (!res.success) return;
            const meta = res.data;
            const tOpts = meta.teachers.map(t => `<option value="${t.id}">${this.escapeHtml(t.nama_lengkap)}</option>`).join('');
            const cOpts = meta.classes.map(c => `<option value="${c.id}">${this.escapeHtml(c.nama_kelas)}</option>`).join('');
            const sOpts = meta.subjects.map(s => `<option value="${s.id}">${this.escapeHtml(s.nama_mapel)} (${this.escapeHtml(s.kode_mapel)})</option>`).join('');
            EModal.form({
                title: 'Tambah Penugasan', size: 'md',
                form: `
                    <div class="form-group-acad"><label class="form-label-acad">Guru</label><select class="form-select-acad" id="formMengajarGuru"><option value="">Pilih...</option>${tOpts}</select></div>
                    <div class="form-group-acad"><label class="form-label-acad">Mapel</label><select class="form-select-acad" id="formMengajarMapel"><option value="">Pilih...</option>${sOpts}</select></div>
                    <div class="form-group-acad"><label class="form-label-acad">Kelas</label><select class="form-select-acad" id="formMengajarKelas"><option value="">Pilih...</option>${cOpts}</select></div>
                `,
                confirmText: 'Tambah',
                onConfirm: () => {
                    const guru = $('#formMengajarGuru').val(), mapel = $('#formMengajarMapel').val(), kelas = $('#formMengajarKelas').val();
                    if (!guru || !mapel || !kelas) { EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Semua wajib diisi.' }); return false; }
                    this.api('mengajar.php?action=create', { method: 'POST', data: { guru_id: +guru, mapel_id: +mapel, kelas_id: +kelas } }).done(res => {
                        EModal.closeAll(); EModal.toast({ type: 'success', title: 'Berhasil', message: res.message }); this.loadMengajarTable();
                    }).fail(xhr => { EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal.' }); });
                    return false;
                }
            });
        });
    },

    importSchKelas() {
        EModal.confirm({
            title: 'Tarik Data Kelas',
            message: 'Tarik data kelas dari data siswa di E-Portal Admin? Proses ini hanya akan menambahkan kelas yang belum ada.',
            type: 'info',
            confirmText: 'Ya, Tarik Data',
            onConfirm: () => {
                const l = EModal.loading('Menarik data kelas...');
                this.api('sch_kelas.php?action=import_portal', { method: 'POST' }).done(res => {
                    EModal.close(l);
                    EModal.toast({ title: 'Selesai!', message: res.message, type: 'success' });
                    this.loadSchKelas();
                }).fail(xhr => {
                    EModal.close(l);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menarik data.' });
                });
            }
        });
    },

    toggleBulkDeleteSchKelas() {
        const checked = $('.sch-kelas-checkbox:checked').length;
        if (checked > 0) {
            $('#btnBulkDeleteSchKelas').show();
            $('#btnBulkDeleteSchKelas').html(`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Hapus Terpilih (${checked})`);
        } else {
            $('#btnBulkDeleteSchKelas').hide();
        }
        
        const total = $('.sch-kelas-checkbox').length;
        $('#selectAllSchKelas').prop('checked', total > 0 && checked === total);
    },

    bulkDeleteSchKelas() {
        const ids = [];
        $('.sch-kelas-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;

        EModal.confirm({
            title: 'Hapus Kelas Massal',
            message: `Yakin ingin menghapus <strong>${ids.length}</strong> kelas yang dipilih?`,
            type: 'danger',
            confirmText: 'Ya, Hapus Semua',
            onConfirm: () => {
                const l = EModal.loading('Menghapus kelas...');
                this.api('sch_kelas.php?action=delete', { method: 'POST', data: { ids } }).done(res => {
                    EModal.close(l);
                    EModal.toast({ title: 'Terhapus!', message: res.message, type: 'success' });
                    $('#btnBulkDeleteSchKelas').hide();
                    this.loadSchKelas();
                }).fail(xhr => {
                    EModal.close(l);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error.' });
                });
            }
        });
    },

    deleteMengajar(id, teacher, mapel, kelas) {
        EModal.confirm({ title: 'Hapus Penugasan', message: `Hapus penugasan <strong>${teacher}</strong> → ${mapel} di ${kelas}?`, type: 'danger', confirmText: 'Hapus',
            onConfirm: () => {
                this.api('mengajar.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    EModal.toast({ type: 'success', title: 'Terhapus', message: res.message }); this.loadMengajarTable();
                }).fail(xhr => { EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal.' }); });
            }
        });
    },

    // ==================== JURNAL MENGAJAR VIEW ====================
    renderJurnal($container) {
        const today = new Date().toISOString().split('T')[0];
        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header">
                    <div><h3>📝 Jurnal Mengajar Harian</h3><p class="acad-subtitle">Catat aktivitas pembelajaran harian per kelas per mata pelajaran.</p></div>
                    <div class="acad-toolbar">
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.showJurnalForm()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Jurnal
                        </button>
                    </div>
                </div>
                <div class="acad-card-body">
                    <div class="filter-bar">
                        <div class="filter-item">
                            <label>Tanggal</label>
                            <input type="date" class="form-input-acad" id="jurnalTanggal" value="${today}">
                        </div>
                        <div class="filter-item">
                            <button class="btn-acad btn-acad-outline" onclick="Curriculum.loadJurnalTable()">🔍 Tampilkan</button>
                        </div>
                    </div>
                    <div id="jurnalTableWrapper"><div class="skeleton-module" style="height:200px;"></div></div>
                </div>
            </div>
        `);
        this.loadJurnalTable();
    },

    loadJurnalTable() {
        const tanggal = $('#jurnalTanggal').val() || new Date().toISOString().split('T')[0];
        this.api(`jurnal.php?action=list&tanggal=${tanggal}`).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#jurnalTableWrapper').html(`<div class="acad-empty"><h3>Belum Ada Jurnal</h3><p>Belum ada jurnal untuk tanggal ini. Klik Tambah Jurnal untuk memulai.</p></div>`);
                return;
            }
            const rows = data.map((j, idx) => {
                const createdDate = j.created_at ? j.created_at.split(' ')[0] : '';
                const classDate = j.tanggal;
                const isLate = createdDate && createdDate > classDate;
                const lateBadge = isLate ? `<span class="badge badge-danger" style="margin-left:6px; font-size:10px; background:#ef4444; color:white; padding:2px 6px; border-radius:4px;" title="Diisi terlambat pada ${createdDate}">Terlambat</span>` : '';
                
                return `
                    <tr class="fade-in" style="animation-delay:${idx*0.03}s">
                        <td><span class="badge badge-info">Jam ${this.escapeHtml(j.jam_ke)}</span></td>
                        <td><strong>${this.escapeHtml(j.nama_kelas)}</strong></td>
                        <td>
                            ${this.escapeHtml(j.nama_mapel)}
                            ${lateBadge}
                        </td>
                        <td style="max-width:200px;"><div class="text-truncate">${this.escapeHtml(j.tujuan_pembelajaran || '-')}</div></td>
                        <td style="max-width:150px;"><div class="text-truncate">${this.escapeHtml(j.catatan || '-')}</div></td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <button class="btn-icon" title="Edit" onclick="Curriculum.showJurnalEditForm(${j.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                <button class="btn-icon danger" title="Hapus" onclick="Curriculum.deleteJurnal(${j.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
            $('#jurnalTableWrapper').html(`<div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Jam</th><th>Kelas</th><th>Mapel</th><th>Tujuan Pembelajaran</th><th>Catatan</th><th style="width:100px;">Aksi</th></tr></thead><tbody>${rows}</tbody></table></div>`);
        });
    },

    showJurnalForm() {
        const today = new Date().toISOString().split('T')[0];
        
        // Fetch teachers list first
        this.api('jurnal.php?action=teachers').done(res => {
            if (!res.success) return;
            const teachers = res.data || [];
            
            const tOpts = teachers.map(t => `<option value="${t.id}">${this.escapeHtml(t.nama_lengkap)}</option>`).join('');
            
            this.loadSelect2(() => {
                EModal.form({
                    title: 'Tambah Jurnal Mengajar (Manual)', size: 'lg',
                    form: `
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div class="form-group-acad">
                                <label class="form-label-acad">Pilih Guru</label>
                                <select class="form-select-acad" id="fJurnalGuru" style="width:100%;">
                                    <option value="">Pilih Guru...</option>
                                    ${tOpts}
                                </select>
                            </div>
                            <div class="form-group-acad">
                                <label class="form-label-acad">Tanggal</label>
                                <input type="date" class="form-input-acad" id="fJurnalTgl" value="${today}">
                            </div>
                        </div>
                        
                        <div id="lateWarningNotice" style="display:none; margin-top:8px; padding:10px 14px; background:#fffbeb; border:1px solid #fef3c7; border-radius:6px; color:#b45309; font-size:12.5px; font-weight:600;">
                            ⚠️ Pengisian jurnal ini melewati tanggal KBM (Terlambat Mengisi).
                        </div>
                        
                        <div class="form-group-acad" style="margin-top: 12px;">
                            <label class="form-label-acad">Pilih Jadwal Mengajar</label>
                            <select class="form-select-acad" id="fJurnalJadwalSelect" onchange="Curriculum.onJurnalJadwalChange()" disabled>
                                <option value="">Pilih guru dan tanggal terlebih dahulu...</option>
                            </select>
                        </div>
    
                        <div id="fJurnalFormFields" style="display:none; margin-top:16px;">
                            <div class="form-group-acad"><label class="form-label-acad">Tujuan Pembelajaran (TP)</label><textarea class="form-input-acad" id="fJurnalTP" rows="2" placeholder="Tulis singkat TP hari ini..."></textarea></div>
                            <div class="form-group-acad"><label class="form-label-acad">Indikator Pencapaian TP (IPTP)</label><textarea class="form-input-acad" id="fJurnalIPTP" rows="2" placeholder="Tulis singkat IPTP..."></textarea></div>
                            <div class="form-group-acad"><label class="form-label-acad">Catatan Pembelajaran</label><textarea class="form-input-acad" id="fJurnalCatatan" rows="2" placeholder="Catatan tambahan..."></textarea></div>
                            
                            <div class="form-group-acad" style="margin-top:16px;">
                                <label class="form-label-acad" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                    <span>Absensi Siswa</span>
                                    <span class="badge badge-info" id="absSummary" style="font-weight:700;">Semua Hadir</span>
                                </label>
                                <div id="fJurnalAbsensiContainer" style="max-height: 250px; overflow-y: auto; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px; background: #f8fafc;">
                                    <div class="text-center text-muted text-sm py-3">Memuat data siswa...</div>
                                </div>
                            </div>
                        </div>
                    `,
                    confirmText: 'Simpan Jurnal',
                    onConfirm: () => {
                        const guruId = $('#fJurnalGuru').val();
                        const tanggal = $('#fJurnalTgl').val();
                        const scheduleSelect = $('#fJurnalJadwalSelect');
                        const selectedVal = scheduleSelect.val();
                        if (!guruId) { EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Silakan pilih guru.' }); return false; }
                        if (selectedVal === '' || selectedVal === null) { EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Silakan pilih jadwal mengajar.' }); return false; }
                        
                        const jadwal = this.state.currentTeacherSchedules[parseInt(selectedVal)];
                        if (!jadwal) { EModal.toast({ type: 'error', title: 'Error', message: 'Jadwal tidak ditemukan.' }); return false; }
                        
                        // Gather student absensi
                        const absensi = [];
                        $('.admin-student-abs-row').each(function() {
                            const studentId = parseInt($(this).attr('data-student-id'));
                            const activePill = $(this).find('.abs-pill.active');
                            if (studentId && activePill.length) {
                                absensi.push({
                                    student_id: studentId,
                                    status: activePill.attr('data-status')
                                });
                            }
                        });
                        
                        this.api('jurnal.php?action=create', {
                            method: 'POST',
                            data: {
                                guru_id: +guruId,
                                tanggal: tanggal,
                                kelas_id: +jadwal.kelas_id,
                                mapel_id: +jadwal.mapel_id,
                                jam_ke: jadwal.jam_ke,
                                tujuan_pembelajaran: $('#fJurnalTP').val(),
                                indikator_tp: $('#fJurnalIPTP').val(),
                                catatan: $('#fJurnalCatatan').val(),
                                absensi: absensi
                            }
                        }).done(res => {
                            EModal.closeAll();
                            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                            this.loadJurnalTable();
                        }).fail(xhr => {
                            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menyimpan.' });
                        });
                        return false;
                    }
                });
                
                // Initialize Select2 dropdown search
                $('#fJurnalGuru').select2({
                    placeholder: 'Cari Guru...',
                    width: '100%'
                }).on('change', () => {
                    this.onJurnalGuruOrDateChange();
                });
                
                // Listen to Date changes programmatically to update late notice and refresh schedule list
                $('#fJurnalTgl').on('change', () => {
                    this.onJurnalGuruOrDateChange();
                });
            });
        });
    },
    
    onJurnalGuruOrDateChange() {
        const guruId = $('#fJurnalGuru').val();
        const tanggal = $('#fJurnalTgl').val();
        const scheduleSelect = $('#fJurnalJadwalSelect');
        const formFields = $('#fJurnalFormFields');
        const warningNotice = $('#lateWarningNotice');
        
        formFields.hide();
        scheduleSelect.val('');
        scheduleSelect.prop('disabled', true);
        scheduleSelect.html('<option value="">Memuat jadwal...</option>');
        this.state.currentTeacherSchedules = [];
        
        // Show/hide late warning notice dynamically
        const todayStr = new Date().toISOString().split('T')[0];
        if (tanggal && tanggal < todayStr) {
            warningNotice.show();
        } else {
            warningNotice.hide();
        }
        
        if (!guruId || !tanggal) {
            scheduleSelect.html('<option value="">Pilih guru dan tanggal terlebih dahulu...</option>');
            return;
        }
        
        this.api(`jurnal.php?action=teacher_schedules&guru_id=${guruId}&tanggal=${tanggal}`).done(res => {
            if (!res.success) {
                scheduleSelect.html('<option value="">Gagal memuat jadwal</option>');
                return;
            }
            const schedules = res.data.jadwal || [];
            this.state.currentTeacherSchedules = schedules;
            if (schedules.length === 0) {
                scheduleSelect.html('<option value="">Tidak ada jadwal mengajar pada hari ini</option>');
                return;
            }
            
            let opts = '<option value="">Pilih Jadwal Mengajar...</option>';
            schedules.forEach((s, idx) => {
                const text = `${s.nama_jam} - ${s.nama_mapel} (Kelas ${s.nama_kelas})${s.jurnal_filled ? ' [SUDAH DIISI]' : ''}`;
                opts += `<option value="${idx}" ${s.jurnal_filled ? 'disabled' : ''}>${text}</option>`;
            });
            scheduleSelect.html(opts);
            scheduleSelect.prop('disabled', false);
        }).fail(() => {
            scheduleSelect.html('<option value="">Gagal memuat jadwal</option>');
        });
    },

    onJurnalJadwalChange() {
        const scheduleSelect = $('#fJurnalJadwalSelect');
        const formFields = $('#fJurnalFormFields');
        const absContainer = $('#fJurnalAbsensiContainer');
        const selectedVal = scheduleSelect.val();
        
        if (selectedVal === '' || selectedVal === null) {
            formFields.hide();
            return;
        }
        
        const jadwal = this.state.currentTeacherSchedules[parseInt(selectedVal)];
        if (!jadwal) return;
        
        formFields.show();
        absContainer.html('<div class="text-center text-muted text-sm py-3">Memuat data siswa...</div>');
        
        this.api(`jurnal.php?action=students&kelas_id=${jadwal.kelas_id}`).done(res => {
            if (!res.success) {
                absContainer.html('<div class="text-center text-danger text-sm py-3">Gagal memuat data siswa.</div>');
                return;
            }
            const students = res.data || [];
            if (students.length === 0) {
                absContainer.html('<div class="text-center text-muted text-sm py-3">Tidak ada data siswa di kelas ini.</div>');
                return;
            }
            
            let studentRows = students.map(s => {
                return `
                    <div class="admin-student-abs-row" data-student-id="${s.id}" style="display:flex; justify-content:space-between; align-items:center; padding:8px; border-bottom:1px solid #e2e8f0;">
                        <div>
                            <div style="font-weight:600; font-size:13px; color:#1e293b;">${this.escapeHtml(s.nama)}</div>
                            <div style="font-size:11px; color:#64748b;">NIS: ${this.escapeHtml(s.nis || '-')}</div>
                        </div>
                        <div class="abs-pills" style="display:flex; gap:6px;">
                            <span class="abs-pill active" data-status="H" onclick="Curriculum.setAbsPill(this, 'H')">H</span>
                            <span class="abs-pill" data-status="S" onclick="Curriculum.setAbsPill(this, 'S')">S</span>
                            <span class="abs-pill" data-status="I" onclick="Curriculum.setAbsPill(this, 'I')">I</span>
                            <span class="abs-pill" data-status="A" onclick="Curriculum.setAbsPill(this, 'A')">A</span>
                        </div>
                    </div>
                `;
            }).join('');
            absContainer.html(studentRows);
            this.updateAbsSummary();
        }).fail(() => {
            absContainer.html('<div class="text-center text-danger text-sm py-3">Gagal terhubung ke server.</div>');
        });
    },

    setAbsPill(el, status) {
        const $el = $(el);
        const row = $el.closest('.admin-student-abs-row');
        row.find('.abs-pill').removeClass('active');
        $el.addClass('active');
        
        if (status !== 'H') {
            row.css('background-color', '#fff5f5');
        } else {
            row.css('background-color', 'transparent');
        }
        this.updateAbsSummary();
    },

    updateAbsSummary() {
        let hCount = 0, sCount = 0, iCount = 0, aCount = 0;
        $('.admin-student-abs-row').each(function() {
            const activePill = $(this).find('.abs-pill.active');
            if (activePill.length) {
                const status = activePill.attr('data-status');
                if (status === 'H') hCount++;
                else if (status === 'S') sCount++;
                else if (status === 'I') iCount++;
                else if (status === 'A') aCount++;
            }
        });
        
        let summaryText = 'Semua Hadir';
        if (sCount > 0 || iCount > 0 || aCount > 0) {
            summaryText = `${hCount} Hadir`;
            if (sCount > 0) summaryText += `, ${sCount} Sakit`;
            if (iCount > 0) summaryText += `, ${iCount} Izin`;
            if (aCount > 0) summaryText += `, ${aCount} Alpa`;
        }
        $('#absSummary').text(summaryText);
    },

    showJurnalEditForm(id) {
        this.api(`jurnal.php?action=get&id=${id}`).done(res => {
            if (!res.success) return;
            const j = res.data;
            
            // Fetch students list for the class
            this.api(`jurnal.php?action=students&kelas_id=${j.kelas_id}`).done(sRes => {
                const students = sRes.data || [];
                
                // Map existing absensi status
                const existingAbsMap = {};
                if (j.absensi) {
                    j.absensi.forEach(a => {
                        existingAbsMap[a.student_id] = a.status;
                    });
                }
                
                let studentRows = students.map(s => {
                    const curStatus = existingAbsMap[s.id] || 'H';
                    return `
                        <div class="admin-student-abs-row" data-student-id="${s.id}" style="display:flex; justify-content:space-between; align-items:center; padding:8px; border-bottom:1px solid #e2e8f0; ${curStatus !== 'H' ? 'background-color:#fff5f5;' : ''}">
                            <div>
                                <div style="font-weight:600; font-size:13px; color:#1e293b;">${this.escapeHtml(s.nama)}</div>
                                <div style="font-size:11px; color:#64748b;">NIS: ${this.escapeHtml(s.nis || '-')}</div>
                            </div>
                            <div class="abs-pills" style="display:flex; gap:6px;">
                                <span class="abs-pill ${curStatus === 'H' ? 'active' : ''}" data-status="H" onclick="Curriculum.setAbsPill(this, 'H')">H</span>
                                <span class="abs-pill ${curStatus === 'S' ? 'active' : ''}" data-status="S" onclick="Curriculum.setAbsPill(this, 'S')">S</span>
                                <span class="abs-pill ${curStatus === 'I' ? 'active' : ''}" data-status="I" onclick="Curriculum.setAbsPill(this, 'I')">I</span>
                                <span class="abs-pill ${curStatus === 'A' ? 'active' : ''}" data-status="A" onclick="Curriculum.setAbsPill(this, 'A')">A</span>
                            </div>
                        </div>
                    `;
                }).join('');
                
                if (students.length === 0) {
                    studentRows = '<div class="text-center text-muted text-sm py-3">Tidak ada data siswa di kelas ini.</div>';
                }
                
                EModal.form({
                    title: `Edit Jurnal — ${j.nama_kelas} / ${j.nama_mapel}`, size: 'lg',
                    form: `
                        <div class="form-group-acad"><label class="form-label-acad">Tujuan Pembelajaran (TP)</label><textarea class="form-input-acad" id="fJurnalTP" rows="3">${this.escapeHtml(j.tujuan_pembelajaran || '')}</textarea></div>
                        <div class="form-group-acad"><label class="form-label-acad">Indikator Pencapaian TP (IPTP)</label><textarea class="form-input-acad" id="fJurnalIPTP" rows="3">${this.escapeHtml(j.indikator_tp || '')}</textarea></div>
                        <div class="form-group-acad"><label class="form-label-acad">Catatan</label><textarea class="form-input-acad" id="fJurnalCatatan" rows="3">${this.escapeHtml(j.catatan || '')}</textarea></div>
                        
                        <div class="form-group-acad" style="margin-top:16px;">
                            <label class="form-label-acad" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <span>Absensi Siswa</span>
                                <span class="badge badge-info" id="absSummary" style="font-weight:700;">Semua Hadir</span>
                            </label>
                            <div id="fJurnalAbsensiContainer" style="max-height: 250px; overflow-y: auto; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px; background: #f8fafc;">
                                ${studentRows}
                            </div>
                        </div>
                    `,
                    confirmText: 'Simpan Perubahan',
                    onConfirm: () => {
                        const absensi = [];
                        $('.admin-student-abs-row').each(function() {
                            const studentId = parseInt($(this).attr('data-student-id'));
                            const activePill = $(this).find('.abs-pill.active');
                            if (studentId && activePill.length) {
                                absensi.push({
                                    student_id: studentId,
                                    status: activePill.attr('data-status')
                                });
                            }
                        });
                        
                        this.api('jurnal.php?action=update', { method: 'POST', data: {
                            id: j.id, 
                            tujuan_pembelajaran: $('#fJurnalTP').val(), 
                            indikator_tp: $('#fJurnalIPTP').val(), 
                            catatan: $('#fJurnalCatatan').val(),
                            absensi: absensi
                        }}).done(res => {
                            EModal.closeAll(); 
                            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message }); 
                            this.loadJurnalTable();
                        }).fail(xhr => { 
                            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal.' }); 
                        });
                        return false;
                    }
                });
                
                this.updateAbsSummary();
            });
        });
    },

    deleteJurnal(id) {
        EModal.confirm({ title: 'Hapus Jurnal', message: 'Hapus jurnal ini?', type: 'danger', confirmText: 'Hapus',
            onConfirm: () => {
                this.api('jurnal.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    EModal.toast({ type: 'success', title: 'Terhapus', message: res.message }); this.loadJurnalTable();
                });
            }
        });
    },

    // ==================== ABSENSI SISWA VIEW ====================
    renderAbsensi($container) {
        const today = new Date().toISOString().split('T')[0];
        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header">
                    <div><h3>✅ Absensi Siswa</h3><p class="acad-subtitle">Kelola kehadiran siswa per kelas per hari.</p></div>
                </div>
                <div class="acad-card-body">
                    <div class="filter-bar">
                        <div class="filter-item"><label>Tanggal</label><input type="date" class="form-input-acad" id="absensiTanggal" value="${today}"></div>
                        <div class="filter-item"><label>Kelas</label><select class="form-select-acad" id="absensiKelas"><option value="">Pilih Kelas...</option></select></div>
                        <div class="filter-item"><button class="btn-acad btn-acad-primary" onclick="Curriculum.loadAbsensiTable()">🔍 Tampilkan</button></div>
                    </div>
                    <div id="absensiTableWrapper"></div>
                </div>
            </div>
        `);
        // Load kelas for dropdown
        this.api('jurnal.php?action=meta').done(res => {
            if (!res.success) return;
            const opts = res.data.classes.map(c => `<option value="${c.id}">${this.escapeHtml(c.nama_kelas)}</option>`).join('');
            $('#absensiKelas').append(opts);
        });
    },

    loadAbsensiTable() {
        const tanggal = $('#absensiTanggal').val();
        const kelas_id = $('#absensiKelas').val();
        if (!kelas_id) { EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Pilih kelas terlebih dahulu.' }); return; }

        this.api(`absensi.php?action=list&tanggal=${tanggal}&kelas_id=${kelas_id}&jam_ke=0`).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#absensiTableWrapper').html(`<div class="acad-empty"><h3>Tidak Ada Siswa</h3><p>Tidak ditemukan siswa untuk kelas ini.</p></div>`);
                return;
            }
            const rows = data.map((s, idx) => `
                <tr class="fade-in" style="animation-delay:${idx*0.02}s">
                    <td>${idx + 1}</td>
                    <td>${this.escapeHtml(s.nis)}</td>
                    <td><strong>${this.escapeHtml(s.nama)}</strong></td>
                    <td>
                        <div class="absensi-radio-group">
                            <label class="absensi-radio ${s.status === 'H' ? 'active-h' : ''}"><input type="radio" name="abs_${s.student_id}" value="H" ${s.status === 'H' ? 'checked' : ''}> H</label>
                            <label class="absensi-radio ${s.status === 'S' ? 'active-s' : ''}"><input type="radio" name="abs_${s.student_id}" value="S" ${s.status === 'S' ? 'checked' : ''}> S</label>
                            <label class="absensi-radio ${s.status === 'I' ? 'active-i' : ''}"><input type="radio" name="abs_${s.student_id}" value="I" ${s.status === 'I' ? 'checked' : ''}> I</label>
                            <label class="absensi-radio ${s.status === 'A' ? 'active-a' : ''}"><input type="radio" name="abs_${s.student_id}" value="A" ${s.status === 'A' ? 'checked' : ''}> A</label>
                        </div>
                    </td>
                </tr>
            `).join('');

            $('#absensiTableWrapper').html(`
                <div class="data-table-wrapper">
                    <table class="data-table" id="absensiDataTable">
                        <thead><tr><th width="50">No</th><th>NIS</th><th>Nama Siswa</th><th>Status Kehadiran</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
                <div style="margin-top:16px; display:flex; justify-content:flex-end;">
                    <button class="btn-acad btn-acad-primary" onclick="Curriculum.saveAbsensi()">💾 Simpan Absensi</button>
                </div>
            `);

            // Radio change handler for visual feedback
            $('#absensiDataTable input[type=radio]').on('change', function() {
                const $group = $(this).closest('.absensi-radio-group');
                $group.find('.absensi-radio').removeClass('active-h active-s active-i active-a');
                const val = $(this).val();
                $(this).parent().addClass(`active-${val.toLowerCase()}`);
            });
        });
    },

    saveAbsensi() {
        const tanggal = $('#absensiTanggal').val();
        const kelas_id = $('#absensiKelas').val();
        const absensi = [];

        $('#absensiDataTable tbody tr').each(function() {
            const $checked = $(this).find('input[type=radio]:checked');
            if ($checked.length) {
                const name = $checked.attr('name');
                const studentId = name.replace('abs_', '');
                absensi.push({ student_id: parseInt(studentId), status: $checked.val() });
            }
        });

        if (!absensi.length) { EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Tidak ada data absensi.' }); return; }

        this.api('absensi.php?action=save', { method: 'POST', data: { tanggal, kelas_id: parseInt(kelas_id), jam_ke: 0, absensi } }).done(res => {
            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
        }).fail(xhr => { EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menyimpan.' }); });
    },

    // ==================== KETIDAKHADIRAN GURU VIEW ====================
    renderKetidakhadiran($container) {
        const today = new Date().toISOString().split('T')[0];
        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header">
                    <div><h3>❌ Ketidakhadiran Guru</h3><p class="acad-subtitle">Catat ketidakhadiran guru (Izin/Sakit).</p></div>
                    <div class="acad-toolbar">
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.showKetidakhadiranForm()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Input Ketidakhadiran
                        </button>
                    </div>
                </div>
                <div class="acad-card-body">
                    <div class="filter-bar">
                        <div class="filter-item"><label>Tanggal</label><input type="date" class="form-input-acad" id="ketidakhadiranTgl" value="${today}"></div>
                        <div class="filter-item"><label>Sampai</label><input type="date" class="form-input-acad" id="ketidakhadiranTglEnd" value="${today}"></div>
                        <div class="filter-item"><button class="btn-acad btn-acad-outline" onclick="Curriculum.loadKetidakhadiranTable()">🔍 Tampilkan</button></div>
                    </div>
                    <div id="ketidakhadiranTableWrapper"><div class="skeleton-module" style="height:200px;"></div></div>
                </div>
            </div>
        `);
        this.loadKetidakhadiranTable();
    },

    loadKetidakhadiranTable() {
        const tgl = $('#ketidakhadiranTgl').val() || new Date().toISOString().split('T')[0];
        const tglEnd = $('#ketidakhadiranTglEnd').val() || tgl;
        this.api(`ketidakhadiran.php?action=list&tanggal=${tgl}&tanggal_akhir=${tglEnd}`).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#ketidakhadiranTableWrapper').html(`<div class="acad-empty"><h3>Tidak Ada Data</h3><p>Belum ada catatan ketidakhadiran pada tanggal ini.</p></div>`);
                return;
            }
            const rows = data.map((k, idx) => {
                const status = k.status || 'Pending';
                let statusBadge = '';
                if (status === 'Approved') {
                    statusBadge = '<span class="badge badge-success">Approved</span>';
                } else if (status === 'Rejected') {
                    statusBadge = '<span class="badge badge-danger">Rejected</span>';
                } else {
                    statusBadge = '<span class="badge badge-warning">Pending</span>';
                }

                let actionBtns = '';
                if (status === 'Pending') {
                    actionBtns = `
                        <button class="btn-icon success" title="Approve" onclick="Curriculum.updateKetidakhadiranStatus(${k.id}, 'Approved')" style="margin-right:4px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
                        </button>
                        <button class="btn-icon danger" title="Reject" onclick="Curriculum.updateKetidakhadiranStatus(${k.id}, 'Rejected')" style="margin-right:4px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>`;
                } else {
                    actionBtns = `
                        <button class="btn-icon" title="Reset ke Pending" onclick="Curriculum.updateKetidakhadiranStatus(${k.id}, 'Pending')" style="margin-right:4px; color:#6366f1;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        </button>`;
                }
                actionBtns += `<button class="btn-icon danger" title="Hapus" onclick="Curriculum.deleteKetidakhadiran(${k.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>`;

                return `
                <tr class="fade-in" style="animation-delay:${idx*0.03}s">
                    <td>${this.escapeHtml(k.tanggal)}</td>
                    <td><strong>${this.escapeHtml(k.guru_nama)}</strong></td>
                    <td><span class="badge ${k.jenis === 'Sakit' ? 'badge-danger' : k.jenis === 'Cuti' ? 'badge-info' : 'badge-warning'}">${this.escapeHtml(k.jenis)}</span></td>
                    <td>${this.escapeHtml(k.catatan || '-')}</td>
                    <td>${statusBadge}</td>
                    <td style="white-space:nowrap;">${actionBtns}</td>
                </tr>`;
            }).join('');
            $('#ketidakhadiranTableWrapper').html(`<div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Tanggal</th><th>Guru</th><th>Jenis</th><th>Catatan</th><th>Status</th><th style="width:130px;">Aksi</th></tr></thead><tbody>${rows}</tbody></table></div>`);
        });
    },

    showKetidakhadiranForm() {
        const today = new Date().toISOString().split('T')[0];
        EModal.form({
            title: 'Input Ketidakhadiran', size: 'md',
            form: `
                <div class="form-group-acad"><label class="form-label-acad">Tanggal</label><input type="date" class="form-input-acad" id="fKetTgl" value="${today}"></div>
                <div class="form-group-acad"><label class="form-label-acad">Jenis</label><select class="form-select-acad" id="fKetJenis"><option value="Izin">Izin</option><option value="Sakit">Sakit</option></select></div>
                <div class="form-group-acad"><label class="form-label-acad">Catatan</label><textarea class="form-input-acad" id="fKetCatatan" rows="3" placeholder="Keterangan..."></textarea></div>
            `,
            confirmText: 'Simpan',
            onConfirm: () => {
                this.api('ketidakhadiran.php?action=create', { method: 'POST', data: {
                    tanggal: $('#fKetTgl').val(), jenis: $('#fKetJenis').val(), catatan: $('#fKetCatatan').val()
                }}).done(res => {
                    EModal.closeAll(); EModal.toast({ type: 'success', title: 'Berhasil', message: res.message }); this.loadKetidakhadiranTable();
                }).fail(xhr => { EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal.' }); });
                return false;
            }
        });
    },

    deleteKetidakhadiran(id) {
        EModal.confirm({ title: 'Hapus', message: 'Hapus catatan ketidakhadiran ini?', type: 'danger', confirmText: 'Hapus',
            onConfirm: () => {
                this.api('ketidakhadiran.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    EModal.toast({ type: 'success', title: 'Terhapus', message: res.message }); this.loadKetidakhadiranTable();
                });
            }
        });
    },

    updateKetidakhadiranStatus(id, status) {
        const labels = { 'Approved': 'menyetujui', 'Rejected': 'menolak', 'Pending': 'mengembalikan ke pending' };
        const types = { 'Approved': 'success', 'Rejected': 'danger', 'Pending': 'warning' };
        EModal.confirm({
            title: status === 'Approved' ? 'Setujui Izin' : status === 'Rejected' ? 'Tolak Izin' : 'Reset Status',
            message: `Apakah Anda yakin ingin <strong>${labels[status]}</strong> pengajuan izin ini?`,
            type: types[status] || 'warning',
            confirmText: status === 'Approved' ? 'Ya, Setujui' : status === 'Rejected' ? 'Ya, Tolak' : 'Ya, Reset',
            onConfirm: () => {
                this.api('ketidakhadiran.php?action=update_status', { method: 'POST', data: { id, status } }).done(res => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                    this.loadKetidakhadiranTable();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal mengubah status.' });
                });
                return false;
            }
        });
    },

    // ==================== PIKET GURU VIEW ====================
    renderPiket($container) {
        const today = new Date().toISOString().split('T')[0];
        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header">
                    <div><h3>📅 Piket Guru</h3><p class="acad-subtitle">Atur guru piket pengganti untuk guru yang tidak hadir.</p></div>
                    <div class="acad-toolbar">
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.showPiketForm()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Piket
                        </button>
                    </div>
                </div>
                <div class="acad-card-body">
                    <div class="filter-bar">
                        <div class="filter-item"><label>Tanggal</label><input type="date" class="form-input-acad" id="piketTgl" value="${today}"></div>
                        <div class="filter-item"><button class="btn-acad btn-acad-outline" onclick="Curriculum.loadPiketTable()">🔍 Tampilkan</button></div>
                    </div>
                    <div id="piketTableWrapper"><div class="skeleton-module" style="height:200px;"></div></div>
                </div>
            </div>
        `);
        this.loadPiketTable();
    },

    loadPiketTable() {
        const tgl = $('#piketTgl').val() || new Date().toISOString().split('T')[0];
        this.api(`piket.php?action=list&tanggal=${tgl}`).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#piketTableWrapper').html(`<div class="acad-empty"><h3>Belum Ada Piket</h3><p>Belum ada data piket untuk tanggal ini.</p></div>`);
                return;
            }
            const rows = data.map((p, idx) => `
                <tr class="fade-in" style="animation-delay:${idx*0.03}s">
                    <td>${this.escapeHtml(p.tanggal)}</td>
                    <td><strong>${this.escapeHtml(p.guru_piket_nama)}</strong></td>
                    <td>${p.guru_diganti_nama ? this.escapeHtml(p.guru_diganti_nama) : '-'}</td>
                    <td>${p.nama_kelas ? this.escapeHtml(p.nama_kelas) : '-'}</td>
                    <td>${p.jam_ke ? `Jam ${this.escapeHtml(p.jam_ke)}` : '-'}</td>
                    <td><button class="btn-icon danger" onclick="Curriculum.deletePiket(${p.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button></td>
                </tr>
            `).join('');
            $('#piketTableWrapper').html(`<div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Tanggal</th><th>Guru Piket</th><th>Menggantikan</th><th>Kelas</th><th>Jam</th><th style="width:80px;">Aksi</th></tr></thead><tbody>${rows}</tbody></table></div>`);
        });
    },

    showPiketForm() {
        const today = new Date().toISOString().split('T')[0];
        this.api(`piket.php?action=available_guru&tanggal=${today}`).done(res => {
            if (!res.success) return;
            const { available, absent } = res.data;
            const guruOpts = available.map(g => `<option value="${g.id}">${this.escapeHtml(g.nama_lengkap)}</option>`).join('');
            const absentOpts = absent.map(g => `<option value="${g.id}">${this.escapeHtml(g.nama_lengkap)}</option>`).join('');

            // Load kelas
            this.api('kelas.php?action=list').done(resK => {
                const kelasOpts = (resK.data || []).map(k => `<option value="${k.id}">${this.escapeHtml(k.nama_kelas)}</option>`).join('');

                EModal.form({
                    title: 'Tambah Piket Guru', size: 'md',
                    form: `
                        <div class="form-group-acad"><label class="form-label-acad">Tanggal</label><input type="date" class="form-input-acad" id="fPiketTgl" value="${today}"></div>
                        <div class="form-group-acad"><label class="form-label-acad">Guru Piket (Pengganti)</label><select class="form-select-acad" id="fPiketGuru"><option value="">Pilih...</option>${guruOpts}</select></div>
                        <div class="form-group-acad"><label class="form-label-acad">Menggantikan Guru (opsional)</label><select class="form-select-acad" id="fPiketDiganti"><option value="">- Tidak ada -</option>${absentOpts}</select></div>
                        <div class="form-group-acad"><label class="form-label-acad">Kelas (opsional)</label><select class="form-select-acad" id="fPiketKelas"><option value="">- Semua -</option>${kelasOpts}</select></div>
                        <div class="form-group-acad"><label class="form-label-acad">Jam Ke (opsional)</label><input class="form-input-acad" id="fPiketJam" placeholder="contoh: 1-2"></div>
                    `,
                    confirmText: 'Simpan',
                    onConfirm: () => {
                        const guru = $('#fPiketGuru').val();
                        if (!guru) { EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Guru piket wajib dipilih.' }); return false; }
                        this.api('piket.php?action=save', { method: 'POST', data: {
                            tanggal: $('#fPiketTgl').val(), guru_id: +guru,
                            guru_diganti_id: $('#fPiketDiganti').val() || null,
                            kelas_id: $('#fPiketKelas').val() || null,
                            jam_ke: $('#fPiketJam').val()
                        }}).done(res => {
                            EModal.closeAll(); EModal.toast({ type: 'success', title: 'Berhasil', message: res.message }); this.loadPiketTable();
                        }).fail(xhr => { EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal.' }); });
                        return false;
                    }
                });
            });
        });
    },

    deletePiket(id) {
        EModal.confirm({ title: 'Hapus Piket', message: 'Hapus data piket ini?', type: 'danger', confirmText: 'Hapus',
            onConfirm: () => {
                this.api('piket.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    EModal.toast({ type: 'success', title: 'Terhapus', message: res.message }); this.loadPiketTable();
                });
            }
        });
    },

    // ==================== BUKU PENGHUBUNG VIEW ====================
    renderBukuPenghubung($container) {
        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header">
                    <div><h3>📖 Buku Penghubung Siswa</h3><p class="acad-subtitle">Catatan perilaku, prestasi, pelanggaran, dan konsultasi siswa.</p></div>
                    <div class="acad-toolbar">
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.showBukuForm()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Catatan
                        </button>
                    </div>
                </div>
                <div class="acad-card-body">
                    <div class="filter-bar">
                        <div class="filter-item"><label>Kelas</label><select class="form-select-acad" id="bukuKelas"><option value="">Semua Kelas</option></select></div>
                        <div class="filter-item"><label>Jenis</label><select class="form-select-acad" id="bukuJenis"><option value="">Semua</option><option value="Keterlambatan">Keterlambatan</option><option value="Pelanggaran">Pelanggaran</option><option value="Prestasi">Prestasi</option><option value="Screening">Screening</option><option value="Konsultasi">Konsultasi</option></select></div>
                        <div class="filter-item"><button class="btn-acad btn-acad-outline" onclick="Curriculum.loadBukuTable()">🔍 Filter</button></div>
                    </div>
                    <div id="bukuTableWrapper"><div class="skeleton-module" style="height:200px;"></div></div>
                </div>
            </div>
        `);
        // Load classes
        this.api('kelas.php?action=list').done(res => {
            const opts = (res.data || []).map(k => `<option value="${k.id}">${this.escapeHtml(k.nama_kelas)}</option>`).join('');
            $('#bukuKelas').append(opts);
        });
        this.loadBukuTable();
    },

    loadBukuTable() {
        const kelas = $('#bukuKelas').val() || '';
        const jenis = $('#bukuJenis').val() || '';
        let url = 'buku_penghubung.php?action=list';
        if (kelas) url += `&kelas_id=${kelas}`;
        if (jenis) url += `&jenis=${jenis}`;

        this.api(url).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#bukuTableWrapper').html(`<div class="acad-empty"><h3>Belum Ada Catatan</h3><p>Tambahkan catatan baru untuk siswa.</p></div>`);
                return;
            }
            const jenisColors = { Keterlambatan: 'badge-warning', Pelanggaran: 'badge-danger', Prestasi: 'badge-success', Screening: 'badge-info', Konsultasi: 'badge-info' };
            const rows = data.map((b, idx) => `
                <tr class="fade-in" style="animation-delay:${idx*0.03}s">
                    <td>${this.escapeHtml(b.tanggal)}</td>
                    <td><strong>${this.escapeHtml(b.nama_siswa)}</strong><br><span class="text-muted" style="font-size:0.75rem;">NIS: ${this.escapeHtml(b.nis)}</span></td>
                    <td>${this.escapeHtml(b.nama_kelas)}</td>
                    <td><span class="badge ${jenisColors[b.jenis] || 'badge-info'}">${this.escapeHtml(b.jenis)}</span></td>
                    <td style="max-width:250px;"><div class="text-truncate">${this.escapeHtml(b.catatan)}</div></td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <button class="btn-icon" title="Edit" onclick="Curriculum.editBuku(${b.id}, '${this.escapeHtml(b.catatan).replace(/'/g, "\\'")}', '${b.jenis}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="btn-icon danger" title="Hapus" onclick="Curriculum.deleteBuku(${b.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>
            `).join('');
            $('#bukuTableWrapper').html(`<div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Jenis</th><th>Catatan</th><th style="width:100px;">Aksi</th></tr></thead><tbody>${rows}</tbody></table></div>`);
        });
    },

    showBukuForm() {
        const today = new Date().toISOString().split('T')[0];
        this.api('kelas.php?action=list').done(resK => {
            const kelasOpts = (resK.data || []).map(k => `<option value="${k.id}">${this.escapeHtml(k.nama_kelas)}</option>`).join('');

            EModal.form({
                title: 'Tambah Catatan Buku Penghubung', size: 'md',
                form: `
                    <div class="form-group-acad"><label class="form-label-acad">Kelas</label><select class="form-select-acad" id="fBukuKelas" onchange="Curriculum.loadStudentsForBuku()"><option value="">Pilih...</option>${kelasOpts}</select></div>
                    <div class="form-group-acad"><label class="form-label-acad">Siswa</label><select class="form-select-acad" id="fBukuSiswa"><option value="">Pilih kelas dahulu...</option></select></div>
                    <div class="form-group-acad"><label class="form-label-acad">Jenis Catatan</label><select class="form-select-acad" id="fBukuJenis"><option value="Keterlambatan">Keterlambatan</option><option value="Pelanggaran">Pelanggaran</option><option value="Prestasi">Prestasi</option><option value="Screening">Screening</option><option value="Konsultasi">Konsultasi / Bimbingan</option></select></div>
                    <div class="form-group-acad"><label class="form-label-acad">Tanggal</label><input type="date" class="form-input-acad" id="fBukuTgl" value="${today}"></div>
                    <div class="form-group-acad"><label class="form-label-acad">Catatan</label><textarea class="form-input-acad" id="fBukuCatatan" rows="3" placeholder="Tulis catatan..."></textarea></div>
                `,
                confirmText: 'Simpan',
                onConfirm: () => {
                    const student = $('#fBukuSiswa').val(), kelas = $('#fBukuKelas').val();
                    if (!student || !kelas) { EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Kelas dan Siswa wajib dipilih.' }); return false; }
                    this.api('buku_penghubung.php?action=create', { method: 'POST', data: {
                        student_id: +student, kelas_id: +kelas, jenis: $('#fBukuJenis').val(), tanggal: $('#fBukuTgl').val(), catatan: $('#fBukuCatatan').val()
                    }}).done(res => {
                        EModal.closeAll(); EModal.toast({ type: 'success', title: 'Berhasil', message: res.message }); this.loadBukuTable();
                    }).fail(xhr => { EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal.' }); });
                    return false;
                }
            });
        });
    },

    loadStudentsForBuku() {
        const kelas_id = $('#fBukuKelas').val();
        if (!kelas_id) return;
        this.api(`absensi.php?action=students&kelas_id=${kelas_id}`).done(res => {
            const opts = (res.data || []).map(s => `<option value="${s.id}">${this.escapeHtml(s.nama)} (${this.escapeHtml(s.nis)})</option>`).join('');
            $('#fBukuSiswa').html(`<option value="">Pilih Siswa...</option>${opts}`);
        });
    },

    editBuku(id, catatan, jenis) {
        EModal.form({
            title: 'Edit Catatan', size: 'md',
            form: `
                <div class="form-group-acad"><label class="form-label-acad">Jenis</label><select class="form-select-acad" id="fBukuEditJenis"><option value="Keterlambatan" ${jenis==='Keterlambatan'?'selected':''}>Keterlambatan</option><option value="Pelanggaran" ${jenis==='Pelanggaran'?'selected':''}>Pelanggaran</option><option value="Prestasi" ${jenis==='Prestasi'?'selected':''}>Prestasi</option><option value="Screening" ${jenis==='Screening'?'selected':''}>Screening</option><option value="Konsultasi" ${jenis==='Konsultasi'?'selected':''}>Konsultasi</option></select></div>
                <div class="form-group-acad"><label class="form-label-acad">Catatan</label><textarea class="form-input-acad" id="fBukuEditCatatan" rows="4">${catatan}</textarea></div>
            `,
            confirmText: 'Simpan',
            onConfirm: () => {
                this.api('buku_penghubung.php?action=update', { method: 'POST', data: { id, jenis: $('#fBukuEditJenis').val(), catatan: $('#fBukuEditCatatan').val() } }).done(res => {
                    EModal.closeAll(); EModal.toast({ type: 'success', title: 'Berhasil', message: res.message }); this.loadBukuTable();
                });
                return false;
            }
        });
    },

    deleteBuku(id) {
        EModal.confirm({ title: 'Hapus Catatan', message: 'Hapus catatan ini?', type: 'danger', confirmText: 'Hapus',
            onConfirm: () => {
                this.api('buku_penghubung.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    EModal.toast({ type: 'success', title: 'Terhapus', message: res.message }); this.loadBukuTable();
                });
            }
        });
    },

    // ==================== LAPORAN JURNAL VIEW ====================
    renderLaporanJurnal($container) {
        const today = new Date().toISOString().split('T')[0];
        const weekAgo = new Date(Date.now() - 7*24*60*60*1000).toISOString().split('T')[0];
        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header"><div><h3>📊 Laporan Jurnal Mengajar</h3><p class="acad-subtitle">Laporan jurnal per kelas, per guru, dalam rentang tanggal.</p></div></div>
                <div class="acad-card-body">
                    <div class="filter-bar">
                        <div class="filter-item"><label>Dari</label><input type="date" class="form-input-acad" id="lapJurnalDari" value="${weekAgo}"></div>
                        <div class="filter-item"><label>Sampai</label><input type="date" class="form-input-acad" id="lapJurnalSampai" value="${today}"></div>
                        <div class="filter-item"><label>Kelas</label><select class="form-select-acad" id="lapJurnalKelas"><option value="">Semua</option></select></div>
                        <div class="filter-item"><button class="btn-acad btn-acad-primary" onclick="Curriculum.loadLaporanJurnal()">📋 Tampilkan</button></div>
                    </div>
                    <div id="lapJurnalWrapper"></div>
                </div>
            </div>
        `);
        this.api('kelas.php?action=list').done(res => {
            const opts = (res.data || []).map(k => `<option value="${k.id}">${this.escapeHtml(k.nama_kelas)}</option>`).join('');
            $('#lapJurnalKelas').append(opts);
        });
    },

    loadLaporanJurnal() {
        const dari = $('#lapJurnalDari').val(), sampai = $('#lapJurnalSampai').val(), kelas = $('#lapJurnalKelas').val();
        let url = `jurnal.php?action=report&type=kelas&tanggal=${dari}&tanggal_akhir=${sampai}`;
        if (kelas) url += `&kelas_id=${kelas}`;

        this.api(url).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#lapJurnalWrapper').html(`<div class="acad-empty"><h3>Tidak Ada Data</h3><p>Belum ada jurnal dalam rentang tanggal ini.</p></div>`);
                return;
            }
            this.state.laporanJurnalData = res.data;
            const rows = data.map((j, idx) => `
                <tr class="fade-in" style="animation-delay:${idx*0.02}s">
                    <td>${this.escapeHtml(j.tanggal)}</td>
                    <td>${this.escapeHtml(j.nama_kelas)}</td>
                    <td>Jam ${this.escapeHtml(j.jam_ke)}</td>
                    <td>${this.escapeHtml(j.nama_mapel)}</td>
                    <td>${this.escapeHtml(j.guru_nama)}</td>
                    <td style="max-width:200px;">${this.escapeHtml(j.tujuan_pembelajaran || '-')}</td>
                    <td style="max-width:150px;">${this.escapeHtml(j.catatan || '-')}</td>
                </tr>
            `).join('');
            $('#lapJurnalWrapper').html(`
                <div style="margin-bottom:12px; text-align:right;"><button class="btn-acad btn-acad-outline" onclick="Curriculum.printJurnalReport()">🖨️ Cetak</button></div>
                <div class="data-table-wrapper"><table class="data-table" id="lapJurnalTable"><thead><tr><th>Tanggal</th><th>Kelas</th><th>Jam</th><th>Mapel</th><th>Guru</th><th>TP</th><th>Catatan</th></tr></thead><tbody>${rows}</tbody></table></div>
            `);
        });
    },

    printJurnalReport() {
        const data = this.state.laporanJurnalData;
        const dari = $('#lapJurnalDari').val();
        const sampai = $('#lapJurnalSampai').val();
        if (!data || !data.length) return;
        
        // Group data by tanggal (date)
        const groups = {};
        data.forEach(j => {
            if (!groups[j.tanggal]) groups[j.tanggal] = [];
            groups[j.tanggal].push(j);
        });
        
        // Sort dates in ascending order
        const dates = Object.keys(groups).sort();
        
        // Determine the signer (teacher name)
        // If there is only one unique teacher in the data, use their name
        const uniqueTeachers = [...new Set(data.map(j => j.guru_nama))];
        const signerName = uniqueTeachers.length === 1 ? uniqueTeachers[0] : (App.state.user ? App.state.user.nama_lengkap : 'Guru Mata Pelajaran');
        const signerId = uniqueTeachers.length === 1 ? data[0].guru_id : (App.state.user ? App.state.user.id : 0);
        
        // Build the HTML for the print window
        let contentHtml = '';
        
        dates.forEach(date => {
            const formattedDate = this.formatDate(date);
            const entries = groups[date];
            
            let tableRows = entries.map((j, idx) => {
                let absentText = j.siswa_tidak_hadir || 'Semua Hadir';
                // replace newline with break tags
                absentText = this.escapeHtml(absentText).replace(/\n/g, '<br>');
                
                return `
                    <tr>
                        <td style="text-align: center; border: 1px solid #1a202c; padding: 6px;">${idx + 1}</td>
                        <td style="text-align: center; border: 1px solid #1a202c; padding: 6px;">${this.escapeHtml(j.nama_kelas)}</td>
                        <td style="text-align: center; border: 1px solid #1a202c; padding: 6px;">${this.escapeHtml(j.jam_ke)}</td>
                        <td style="border: 1px solid #1a202c; padding: 6px;">${this.escapeHtml(j.nama_mapel)}</td>
                        <td style="border: 1px solid #1a202c; padding: 6px;">${this.escapeHtml(j.tujuan_pembelajaran || '-')}</td>
                        <td style="border: 1px solid #1a202c; padding: 6px;">${this.escapeHtml(j.indikator_tp || '-')}</td>
                        <td style="border: 1px solid #1a202c; padding: 6px;">${this.escapeHtml(j.catatan || '-')}</td>
                        <td style="font-size: 11px; color: #b91c1c; border: 1px solid #1a202c; padding: 6px;">${absentText}</td>
                    </tr>
                `;
            }).join('');
            
            contentHtml += `
                <div class="jurnal-group" style="page-break-inside: avoid; margin-bottom: 30px;">
                    <div style="font-weight: bold; font-size: 14px; margin-bottom: 8px; font-family: Arial, sans-serif;">
                        Jurnal Tanggal : ${formattedDate}
                    </div>
                    <table class="report-table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; font-family: Arial, sans-serif;">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center; border: 1px solid #1a202c; padding: 8px; background-color: #f7fafc;">No</th>
                                <th style="width: 80px; text-align: center; border: 1px solid #1a202c; padding: 8px; background-color: #f7fafc;">Kelas</th>
                                <th style="width: 70px; text-align: center; border: 1px solid #1a202c; padding: 8px; background-color: #f7fafc;">Jam Ke-</th>
                                <th style="width: 150px; border: 1px solid #1a202c; padding: 8px; background-color: #f7fafc;">Mata Pelajaran</th>
                                <th style="border: 1px solid #1a202c; padding: 8px; background-color: #f7fafc;">TP</th>
                                <th style="border: 1px solid #1a202c; padding: 8px; background-color: #f7fafc;">IPTP</th>
                                <th style="border: 1px solid #1a202c; padding: 8px; background-color: #f7fafc;">Catatan</th>
                                <th style="width: 150px; border: 1px solid #1a202c; padding: 8px; background-color: #f7fafc;">Siswa tidak hadir</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                        </tbody>
                    </table>
                </div>
            `;
        });
        
        // Format the end date for Surabaya, [tgl akhir]
        const formattedEndDate = this.formatDate(sampai);
        
        // Generate QR code url for verification
        const verifyLink = `${window.location.origin}${App.baseUrl}modules/e-curriculum/verify_jurnal.php?guru_id=${signerId}&dari=${dari}&sampai=${sampai}`;
        const qrUrl = `${window.location.origin}${App.baseUrl}modules/e-xam-card/api/qr.php?size=3&data=${encodeURIComponent(verifyLink)}`;
        
        const printWin = window.open('', '_blank');
        printWin.document.write(`
            <html>
            <head>
                <title>Laporan Jurnal Mengajar</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        color: #1a202c;
                        padding: 30px;
                        margin: 0;
                    }
                    .header-title {
                        font-size: 18px;
                        font-weight: bold;
                        text-align: center;
                        margin-bottom: 25px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    }
                    .signature-section {
                        margin-top: 40px;
                        float: right;
                        text-align: left;
                        width: 250px;
                        font-family: Arial, sans-serif;
                        font-size: 13px;
                        page-break-inside: avoid;
                    }
                    .signature-qr {
                        margin: 10px 0;
                    }
                    .signature-name {
                        font-weight: bold;
                        text-decoration: underline;
                        margin-top: 5px;
                    }
                    .clear-fix {
                        clear: both;
                    }
                </style>
            </head>
            <body>
                <div class="header-title">Laporan Jurnal Kelas</div>
                
                ${contentHtml}
                
                <div class="signature-section">
                    <div>Surabaya, ${formattedEndDate}</div>
                    <div style="margin-top: 4px;">Guru Mata Pelajaran,</div>
                    <div class="signature-qr">
                        <img src="${qrUrl}" alt="QR Code TTD" style="width: 90px; height: 90px; display: block; border: 1px solid #cbd5e0; padding: 4px; background: white;" />
                    </div>
                    <div class="signature-name">${this.escapeHtml(signerName)}</div>
                </div>
                <div class="clear-fix"></div>
                
                <script>
                    window.onload = function() {
                        window.print();
                    }
                <\/script>
            </body>
            </html>
        `);
        printWin.document.close();
    },

    // ==================== LAPORAN KEHADIRAN VIEW ====================
    renderLaporanKehadiran($container) {
        const today = new Date().toISOString().split('T')[0];
        const monthAgo = new Date(Date.now() - 30*24*60*60*1000).toISOString().split('T')[0];
        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header"><div><h3>📊 Laporan Kehadiran</h3><p class="acad-subtitle">Rekapitulasi kehadiran siswa dan ketidakhadiran guru.</p></div></div>
                <div class="acad-card-body">
                    <div class="filter-bar">
                        <div class="filter-item"><label>Dari</label><input type="date" class="form-input-acad" id="lapHadirDari" value="${monthAgo}"></div>
                        <div class="filter-item"><label>Sampai</label><input type="date" class="form-input-acad" id="lapHadirSampai" value="${today}"></div>
                        <div class="filter-item">
                            <label>Tipe</label>
                            <select class="form-select-acad" id="lapHadirTipe"><option value="guru">Ketidakhadiran Guru</option><option value="piket">Rekap Piket</option></select>
                        </div>
                        <div class="filter-item"><button class="btn-acad btn-acad-primary" onclick="Curriculum.loadLaporanKehadiran()">📋 Tampilkan</button></div>
                    </div>
                    <div id="lapHadirWrapper"></div>
                </div>
            </div>
        `);
    },

    loadLaporanKehadiran() {
        const dari = $('#lapHadirDari').val(), sampai = $('#lapHadirSampai').val(), tipe = $('#lapHadirTipe').val();

        if (tipe === 'guru') {
            this.api(`ketidakhadiran.php?action=rekap&tanggal=${dari}&tanggal_akhir=${sampai}`).done(res => {
                const data = res.data || [];
                if (!data.length) { $('#lapHadirWrapper').html(`<div class="acad-empty"><h3>Tidak Ada Data</h3></div>`); return; }
                const rows = data.map((r, idx) => `
                    <tr class="fade-in" style="animation-delay:${idx*0.03}s">
                        <td>${idx+1}</td>
                        <td><strong>${this.escapeHtml(r.guru_nama)}</strong></td>
                        <td>${this.escapeHtml(r.nik || '-')}</td>
                        <td>${r.sakit}</td>
                        <td>${r.izin}</td>
                        <td><strong>${r.total}</strong></td>
                    </tr>
                `).join('');
                $('#lapHadirWrapper').html(`
                    <div style="margin-bottom:12px; text-align:right;"><button class="btn-acad btn-acad-outline" onclick="Curriculum.printTable('lapHadirTable')">🖨️ Cetak</button></div>
                    <div class="data-table-wrapper"><table class="data-table" id="lapHadirTable"><thead><tr><th>No</th><th>Guru</th><th>NIK</th><th>Sakit</th><th>Izin</th><th>Total</th></tr></thead><tbody>${rows}</tbody></table></div>
                `);
            });
        } else {
            this.api(`piket.php?action=rekap&tanggal=${dari}&tanggal_akhir=${sampai}`).done(res => {
                const data = res.data || [];
                if (!data.length) { $('#lapHadirWrapper').html(`<div class="acad-empty"><h3>Tidak Ada Data</h3></div>`); return; }
                const rows = data.map((r, idx) => `
                    <tr class="fade-in" style="animation-delay:${idx*0.03}s">
                        <td>${idx+1}</td>
                        <td><strong>${this.escapeHtml(r.guru_nama)}</strong></td>
                        <td><strong>${r.total_piket}</strong></td>
                    </tr>
                `).join('');
                $('#lapHadirWrapper').html(`
                    <div style="margin-bottom:12px; text-align:right;"><button class="btn-acad btn-acad-outline" onclick="Curriculum.printTable('lapHadirTable')">🖨️ Cetak</button></div>
                    <div class="data-table-wrapper"><table class="data-table" id="lapHadirTable"><thead><tr><th>No</th><th>Guru Piket</th><th>Total Piket</th></tr></thead><tbody>${rows}</tbody></table></div>
                `);
            });
        }
    },

    // ==================== PRINT HELPER ====================
    printTable(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const printWin = window.open('', '_blank');
        printWin.document.write(`
            <html><head><title>Cetak Laporan</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #333; padding: 8px; text-align: left; font-size: 12px; }
                th { background: #f0f0f0; font-weight: bold; }
                h2 { text-align: center; margin-bottom: 5px; }
                .subtitle { text-align: center; color: #666; margin-bottom: 20px; }
            </style></head><body>
            <h2>${this.state.school.nama}</h2>
            <p class="subtitle">Dicetak: ${new Date().toLocaleDateString('id-ID')}</p>
            ${table.outerHTML}
            <script>window.print();<\/script>
            </body></html>
        `);
        printWin.document.close();
    },

    printJadwal() {
        const viewer = document.getElementById('jdwViewer');
        if (!viewer) return;
        
        // Sembunyikan tombol cetak saat mengambil HTML
        const btn = document.getElementById('btnCetakJadwal');
        if (btn) btn.style.display = 'none';
        const html = viewer.innerHTML;
        if (btn) btn.style.display = 'inline-block';

        const printWin = window.open('', '_blank');
        printWin.document.write(`
            <html><head><title>Cetak Jadwal Pelajaran</title>
            <style>
                @page { size: landscape; margin: 1cm; }
                body { 
                    font-family: Arial, sans-serif; 
                    -webkit-print-color-adjust: exact !important; 
                    print-color-adjust: exact !important; 
                    color-adjust: exact !important; 
                    margin: 0;
                    padding: 10px;
                }
                .sch-table { width: 100%; border-collapse: collapse; page-break-inside: avoid; margin-bottom: 20px; }
                .sch-table th, .sch-table td { border: 1px solid #333 !important; padding: 6px; font-size: 11px; }
                .sch-table th { background-color: #3b82f6 !important; color: white !important; }
                
                /* Override specific inline styles from UI for printing */
                td[style*="background-color:transparent"] { background-color: transparent !important; }
                td[style*="background-color:#fee2e2"], td[style*="background-color: #fee2e2"] { background-color: #fee2e2 !important; }
                td[style*="background:#f8fafc"], td[style*="background: #f8fafc"] { background-color: #f8fafc !important; }
                td[style*="background:#e2e8f0"], td[style*="background: #e2e8f0"] { background-color: #e2e8f0 !important; }
                
                h4 { font-size: 14px; margin-bottom: 5px; color: #1e293b !important; }
                h3 { font-size: 18px; text-align: center; margin-bottom: 15px; }
                .header-wrapper { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            </style></head><body>
            <div class="header-wrapper">
                <h3>Jadwal Pelajaran<br><small style="font-size:14px; font-weight:normal;">${this.state.school.nama}</small></h3>
            </div>
            ${html}
            <script>
                setTimeout(() => {
                    window.print();
                    window.close();
                }, 500);
            <\/script>
            </body></html>
        `);
        printWin.document.close();
    },

    // ==================== GLOBAL HELPERS ====================
    api(endpoint, options = {}) {
        const defaults = {
            url: this.moduleUrl + 'api/' + endpoint,
            dataType: 'json',
            contentType: 'application/json',
            timeout: 30000,
            headers: { 'Authorization': 'Bearer ' + this.state.token }
        };

        if (options.data && typeof options.data === 'object' && !(options.data instanceof FormData)) {
            options.data = JSON.stringify(options.data);
        }

        return $.ajax({ ...defaults, ...options });
    },

    doLogout() {
        EModal.confirm({
            title: 'Logout',
            message: 'Yakin ingin keluar dari E-Curriculum?',
            type: 'danger',
            confirmText: 'Ya, Logout',
            onConfirm: () => {
                const loader = EModal.loading('Logging out...');
                const token = this.state.token || (window.localStorage ? localStorage.getItem('eportal_token') : null);
                $.ajax({
                    url: this.baseUrl + 'api/auth.php?action=logout',
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
                        window.location.href = this.baseUrl + '#/login';
                    }
                });
            }
        });
    },

    getInitials(name) {
        if (!name) return '??';
        return name
            .split(' ')
            .map(w => w[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();
    },

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    // --- E-SCHEDULE FUNCTIONS ---

    // ==============================================
    // DATA KELAS (SCH) WITH WALI_ID
    // ==============================================
    renderSchKelas($container) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3>Data Kelas</h3>
                    <div class="sch-toolbar">
                        <button class="btn-acad btn-acad-danger" id="btnBulkDeleteSchKelas" style="display: none; margin-right: 8px;" onclick="Curriculum.bulkDeleteSchKelas()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Hapus Terpilih
                        </button>
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.importSchKelas()" style="margin-right: 8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Tarik Data Kelas
                        </button>
                    </div>
                </div>
                <div class="sch-card-body">
                    <div class="sch-table-wrapper" id="schKelasTable"><div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat data...</h3></div></div>
                </div>
            </div>
        `);
        this.loadSchKelas();
    },

    loadSchKelas() {
        this.api('sch_kelas.php?action=list').done(res => {
            if (!res.data.length) {
                $('#schKelasTable').html('<div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg><h3>Tidak ada data</h3></div>');
                return;
            }
            this.state.sch_kelasData = res.data;

            const rows = res.data.map(d => `
                <tr>
                    <td style="width: 40px; text-align: center;"><input type="checkbox" class="sch-kelas-checkbox" value="${d.id}" onchange="Curriculum.toggleBulkDeleteSchKelas()"></td>
                    <td>Tingkat ${d.rombel}</td>
                    <td><strong>${d.nama_kelas}</strong></td>
                    <td>${d.wali_nama ? d.wali_nama : '<span style="color:var(--text-muted)">-</span>'}</td>
                    <td style="width:120px">
                        <div class="sch-actions">
                            <button class="sch-btn-icon" onclick="Curriculum.formSchKelas(${d.id}, '${d.rombel}', '${d.nama_kelas}', '${d.wali_id||''}')" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="sch-btn-icon danger" onclick="Curriculum.deleteSchMaster('sch_kelas', ${d.id})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                        </div>
                    </td>
                </tr>
            `).join('');

            $('#schKelasTable').html(`
                <table class="sch-table">
                    <thead><tr><th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllSchKelas" onchange="$('.sch-kelas-checkbox').prop('checked', this.checked); Curriculum.toggleBulkDeleteSchKelas();"></th><th>Tingkat/Rombel</th><th>Nama Kelas</th><th>Wali Kelas</th><th>Aksi</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        });
    },

    formSchKelas(id = null, rombel = '', nama_kelas = '', wali_id = '') {
        // Fetch users for wali kelas
        this.api('sch_guru.php?action=list').done(res => { // Wait, do we have teachers? Let's assume we can fetch teachers from somewhere, or just use a generic endpoint. Actually, I can just show the modal and then populate it.
            let opts = '<option value="">-- Pilih Wali Kelas (Opsional) --</option>';
            // Since we need teachers, maybe we call `sch_guru.php?action=list`? 
            if(res.success) {
                res.data.forEach(g => {
                    opts += `<option value="${g.id_user}" ${wali_id == g.id_user ? 'selected' : ''}>${g.nama_guru}</option>`;
                });
            }
            
            let rombelOpts = '';
            for(let i=1; i<=12; i++) rombelOpts += `<option value="${i}" ${rombel==i?'selected':''}>Tingkat ${i}</option>`;

            EModal.form({
                title: id ? 'Edit Data Kelas' : 'Tambah Kelas Baru',
                form: `
                    <input type="hidden" id="fmId" value="${id || ''}">
                    <div class="sch-form-row">
                        <div class="form-group"><label>Tingkat</label><select class="form-select" id="fmRombel">${rombelOpts}</select></div>
                        <div class="form-group"><label>Nama Kelas</label><input class="form-input" id="fmNamaKelas" required value="${nama_kelas}"></div>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label>Wali Kelas</label>
                        <select class="form-select" id="fmWaliId">${opts}</select>
                    </div>
                `,
                onConfirm: () => {
                    const data = { 
                        id: $('#fmId').val(),
                        rombel: $('#fmRombel').val(),
                        nama_kelas: $('#fmNamaKelas').val(),
                        wali_id: $('#fmWaliId').val()
                    };
                    const action = id ? 'update' : 'create';
                    this.api('sch_kelas.php?action=' + action, { method: 'POST', data }).done(res => {
                        if (res.success) {
                            EModal.closeAll();
                            this.loadSchKelas();
                            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                        }
                    });
                    return false;
                }
            });
        });
    },

    // ==============================================
    // DATA MAPEL (SCH)
    // ==============================================
    renderSchMapel($container) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3>Mata Pelajaran</h3>
                    <div class="sch-toolbar">
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.formSchMapel()">Tambah Baru</button>
                    </div>
                </div>
                <div class="sch-card-body">
                    <div class="sch-table-wrapper" id="schMapelTable"><div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat data...</h3></div></div>
                </div>
            </div>
        `);
        this.loadSchMapel();
    },

    loadSchMapel() {
        this.api('sch_mapel.php?action=list').done(res => {
            if (!res.data.length) {
                $('#schMapelTable').html('<div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg><h3>Tidak ada data</h3></div>');
                return;
            }
            const rows = res.data.map(d => `
                <tr>
                    <td>${d.kode_mapel || '-'}</td>
                    <td>${d.nama_mapel}</td>
                    <td style="width:120px">
                        <div class="sch-actions">
                            <button class="sch-btn-icon" onclick="Curriculum.formSchMapel(${d.id}, '${d.kode_mapel || ''}', '${d.nama_mapel}')" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="sch-btn-icon danger" onclick="Curriculum.deleteSchMaster('sch_mapel', ${d.id})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                        </div>
                    </td>
                </tr>
            `).join('');
            $('#schMapelTable').html(`<table class="sch-table"><thead><tr><th>Kode Mapel</th><th>Nama Mapel</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
        });
    },

    formSchMapel(id = null, kode = '', nama = '') {
        EModal.form({
            title: id ? 'Edit Mapel' : 'Tambah Mapel',
            form: `
                <input type="hidden" id="fmId" value="${id || ''}">
                <div class="form-group"><label>Kode Mata Pelajaran</label><input class="form-input" id="fmKode" required value="${kode}"></div>
                <div class="form-group" style="margin-top:16px;"><label>Nama Mata Pelajaran</label><input class="form-input" id="fmNama" required value="${nama}"></div>
            `,
            onConfirm: () => {
                const data = { id: $('#fmId').val(), kode_mapel: $('#fmKode').val(), nama_mapel: $('#fmNama').val() };
                const action = id ? 'update' : 'create';
                this.api('sch_mapel.php?action=' + action, { method: 'POST', data }).done(res => {
                    if (res.success) {
                        EModal.closeAll();
                        this.loadSchMapel();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                    }
                });
                return false;
            }
        });
    },

    // ==============================================
    // DATA GURU (SCH)
    // ==============================================
    renderSchGuru($container) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3 style="display:flex; align-items:center; gap:10px;">Data Guru <span id="totalGuruBadge" style="font-size:0.8rem; background:var(--primary); color:white; padding:2px 8px; border-radius:12px; font-weight:600; display:none;">0</span></h3>
                    <div class="sch-toolbar">
                        <button class="btn-acad btn-acad-danger" id="btnBulkDeleteSchGuru" style="display: none; margin-right: 8px;" onclick="Curriculum.bulkDeleteSchGuru()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Hapus Terpilih
                        </button>
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.importSchGuru()" style="margin-right: 8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Tarik Data Guru
                        </button>
                    </div>
                </div>
                <div class="sch-card-body">
                    <div class="sch-table-wrapper" id="schGuruTable"><div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat data...</h3></div></div>
                </div>
            </div>
        `);
        this.loadSchGuru();
    },

    loadSchGuru() {
        this.api('sch_guru.php?action=list').done(res => {
            $('#totalGuruBadge').text(res.data.length || 0).show();
            if (!res.data.length) {
                $('#schGuruTable').html('<div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg><h3>Tidak ada data</h3></div>');
                return;
            }
            const rows = res.data.map((d, idx) => `
                <tr>
                    <td style="width: 40px; text-align: center;"><input type="checkbox" class="sch-guru-checkbox" value="${d.id}" onchange="Curriculum.toggleBulkDeleteSchGuru()"></td>
                    <td style="width: 50px; text-align:center;">${idx + 1}</td>
                    <td>
                        <strong title="NIP/Username">${d.kode_guru}</strong><br>
                        <span class="badge" style="background:#e2e8f0; color:#475569; font-size:11px; padding:2px 6px; border-radius:4px;" title="Kode Singkat dari E-Portal">${d.singkatan || '-'}</span>
                    </td>
                    <td>
                        <strong>${d.nama_guru}</strong><br>
                        <small style="color:var(--text-muted); font-size:0.8rem;">${d.tupoksi || '-'}</small>
                    </td>
                    <td style="width:120px">
                        <div class="sch-actions">
                            <button class="sch-btn-icon danger" onclick="Curriculum.deleteSchMaster('sch_guru', ${d.id})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                        </div>
                    </td>
                </tr>
            `).join('');
            $('#schGuruTable').html(`<table class="sch-table"><thead><tr><th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllSchGuru" onchange="$('.sch-guru-checkbox').prop('checked', this.checked); Curriculum.toggleBulkDeleteSchGuru();"></th><th style="width: 50px; text-align:center;">No.</th><th>NIP & Kode</th><th>Nama Guru</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
        });
    },



    formSchGuru(id = null, kode = '', nama = '') {
        EModal.form({
            title: id ? 'Edit Guru' : 'Tambah Guru',
            form: `
                <input type="hidden" id="fmId" value="${id || ''}">
                <div class="sch-form-row">
                    <div class="form-group"><label>Kode Guru</label><input class="form-input" id="fmKode" required value="${kode}"></div>
                    <div class="form-group"><label>Nama Guru</label><input class="form-input" id="fmNama" required value="${nama}"></div>
                </div>
            `,
            onConfirm: () => {
                const data = { id: $('#fmId').val(), kode_guru: $('#fmKode').val(), nama_guru: $('#fmNama').val() };
                const action = id ? 'update' : 'create';
                this.api('sch_guru.php?action=' + action, { method: 'POST', data }).done(res => {
                    if (res.success) {
                        EModal.closeAll();
                        this.loadSchGuru();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                    }
                });
                return false;
            }
        });
    },

    importSchGuru() {
        const l = EModal.loading('Memuat data tupoksi...');
        this.api('sch_guru.php?action=list_tupoksi_guru').done(res => {
            EModal.close(l);
            let options = '';
            res.data.forEach(t => {
                options += `<label style="display:block; margin-bottom:8px; cursor:pointer;"><input type="checkbox" class="tupoksi-cb form-checkbox" value="${t}" style="margin-right:8px;"> ${t}</label>`;
            });

            EModal.confirm({
                title: 'Tarik Data Guru',
                message: `
                    <p style="margin-bottom:15px;color:var(--text-muted);font-size:0.9rem; text-align:left;">
                        Centang tupoksi spesifik, atau biarkan kosong untuk menarik semua guru.
                    </p>
                    <div class="form-group" style="text-align:left; max-height:200px; overflow-y:auto; border:1px solid var(--border-color); padding:12px; border-radius:6px;">
                        ${options}
                    </div>
                `,
                type: 'info',
                confirmText: 'Tarik Data',
                onConfirm: () => {
                    let selected = [];
                    $('.tupoksi-cb:checked').each(function() {
                        selected.push($(this).val());
                    });
                    const tupoksi = selected.join(',');
                    const url = `sch_guru.php?action=import_portal${tupoksi ? '&tupoksi='+encodeURIComponent(tupoksi) : ''}`;
                    const l2 = EModal.loading('Menarik data guru...');
                    this.api(url, { method: 'POST' }).done(resImp => {
                        EModal.close(l2);
                        EModal.closeAll();
                        EModal.toast({ title: 'Selesai!', message: resImp.message, type: 'success' });
                        this.loadSchGuru();
                    }).fail(xhr => {
                        EModal.close(l2);
                        EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menarik data.' });
                    });
                    return false;
                }
            });
        }).fail(() => {
            EModal.close(l);
            EModal.toast({type:'error', message:'Gagal memuat daftar tupoksi'});
        });
    },

    toggleBulkDeleteSchGuru() {
        const checked = $('.sch-guru-checkbox:checked').length;
        if (checked > 0) {
            $('#btnBulkDeleteSchGuru').show();
            $('#btnBulkDeleteSchGuru').html(`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Hapus Terpilih (${checked})`);
        } else {
            $('#btnBulkDeleteSchGuru').hide();
        }
        
        const total = $('.sch-guru-checkbox').length;
        $('#selectAllSchGuru').prop('checked', total > 0 && checked === total);
    },

    bulkDeleteSchGuru() {
        const ids = [];
        $('.sch-guru-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;

        EModal.confirm({
            title: 'Hapus Guru Massal',
            message: `Yakin ingin menghapus <strong>${ids.length}</strong> guru yang dipilih?`,
            type: 'danger',
            confirmText: 'Ya, Hapus Semua',
            onConfirm: () => {
                const l = EModal.loading('Menghapus guru...');
                this.api('sch_guru.php?action=delete', { method: 'POST', data: { ids } }).done(res => {
                    EModal.close(l);
                    EModal.toast({ title: 'Terhapus!', message: res.message, type: 'success' });
                    $('#btnBulkDeleteSchGuru').hide();
                    this.loadSchGuru();
                }).fail(xhr => {
                    EModal.close(l);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error.' });
                });
            }
        });
    },

    deleteSchMaster(type, id) {
        EModal.confirm({
            title: 'Hapus Data',
            message: 'Yakin menghapus data ini?',
            type: 'danger',
            onConfirm: () => {
                this.api(type + '.php?action=delete', { method: 'POST', data: {id} }).done(res => {
                    if(type == 'sch_kelas') this.loadSchKelas();
                    if(type == 'sch_mapel') this.loadSchMapel();
                    if(type == 'sch_guru') this.loadSchGuru();
                    EModal.toast({ type: 'success', message: 'Dihapus' });
                });
            }
        });
    },


    renderSchJam($container) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3>Daftar Jam Belajar</h3>
                    <div class="sch-toolbar">
                        <button class="btn btn-outline" onclick="Curriculum.exportData('jam')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export</button>
                        <button class="btn btn-outline" onclick="Curriculum.importData('jam')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Import</button>
                        <button class="btn btn-outline" id="btnCopyJam">Salin Data Hari</button>
                        <button class="btn btn-primary" onclick="Curriculum.formJam()">Tambah Jam</button>
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
                    this.api('sch_jam.php?action=copy', {method:'POST', data}).done(res => {
                        EModal.closeAll();
                        this.reloadCurrentPage();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: 'Data jam berhasil disalin.' });
                    });
                    return false;
                }
            });
        });

        this.api('sch_jam.php?action=list').done(res => {
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
                                    <button class="sch-btn-icon" style="width:26px;height:26px;background:#f1f5f9;border-radius:4px;display:flex;align-items:center;justify-content:center" onclick="Curriculum.formJam(${cell.id})" title="Edit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                    <button class="sch-btn-icon danger" style="width:26px;height:26px;background:#FEE2E2;color:#DC2626;border-radius:4px;display:flex;align-items:center;justify-content:center" onclick="Curriculum.deleteMaster('jam', ${cell.id})" title="Hapus"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
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
                this.api('sch_jam.php?action=' + act, { method:'POST', data }).done(res => {
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
    renderSchDistribusi($container) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3>Penugasan / Distribusi Mengajar</h3>
                    <div class="sch-toolbar">
                        <button class="btn btn-outline" onclick="Curriculum.exportData('distribusi')">Export</button>
                        <button class="btn btn-outline" onclick="Curriculum.importData('distribusi')">Import</button>
                        <button class="btn btn-primary" onclick="Curriculum.formDist()">Tambah Distribusi</button>
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
        this.api('sch_kelas.php?action=list').done(res => {
            res.data.forEach(k => $('#distFilterKelas').append(`<option value="${k.id}">${k.nama_kelas}</option>`));
        });

        $('#distFilterKelas').on('change', (e) => this.loadDistribusi($(e.target).val()));
        this.loadDistribusi();
    },

    loadDistribusi(kelasId = '') {
        this.api('sch_distribusi.php?action=list').done(res => {
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
                    <td>${d.nama_kelas}</td>
                    <td>${d.nama_mapel}</td>
                    <td><span style="background:var(--primary-light);padding:2px 8px;border-radius:12px;font-weight:600">${d.jp}</span></td>
                    <td>
                        <div class="sch-actions">
                            <button class="sch-btn-icon" onclick="Curriculum.formDist(${d.id})" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="sch-btn-icon danger" onclick="Curriculum.deleteMaster('distribusi', ${d.id})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                        </div>
                    </td>
                </tr>`;
            });
            html += `</tbody><tfoot><tr><td colspan="4" style="text-align:right;font-weight:700">Total JP:</td><td colspan="2" style="font-weight:700;color:var(--primary-dark)">${totalJp} Jam</td></tr></tfoot></table>`;
            $('#distTable').html(html);
        });
    },

    formDist(id = null) {
        let listG = '', listM = '';
        
        let promiseG = this.api('sch_guru.php?action=list');
        let promiseK = this.api('sch_kelas.php?action=list');
        let promiseM = this.api('sch_mapel.php?action=list');

        Promise.all([promiseG, promiseK, promiseM]).then(results => {
            results[0].data.forEach(x => {
                listG += `<div class="sp-cs-option" data-id="${x.id}" data-text="${x.kode_guru} - ${x.nama_guru}"><strong>${x.nama_guru}</strong> <br><small style="color:gray">${x.kode_guru}</small></div>`;
            });
            
            let gridK = '<div class="class-checkbox-grid" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">';
            results[1].data.forEach(x => {
                gridK += `<input type="checkbox" id="ck_${x.id}" value="${x.id}" class="kIdCheckbox" style="display:none;">`;
                gridK += `<label for="ck_${x.id}" style="display:inline-block; width:65px; text-align:center; padding:6px 0; background:var(--bg-color,#f8fafc); border:1px solid var(--border-color,#e2e8f0); border-radius:8px; cursor:pointer; font-size:0.85rem; font-weight:600; transition:all 0.2s; user-select:none;">${x.nama_kelas}</label>`;
            });
            gridK += '</div>';
            
            // Add custom style for checked state dynamically
            const style = `
            <style>
                .class-checkbox-grid input:checked + label { background:var(--primary-color,#3b82f6) !important; color:white !important; border-color:var(--primary-color,#3b82f6) !important; box-shadow:0 2px 6px rgba(59,130,246,0.3) !important; }
                
                /* Custom Select Styles */
                .sp-cs-container { position:relative; user-select:none; }
                .sp-cs-btn { cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; height:42px; transition: all 0.2s; }
                .sp-cs-btn:hover { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
                .sp-cs-btn.active { border-color: var(--primary); }
                .sp-cs-dropdown { display:none; position:absolute; top:calc(100% + 5px); left:0; right:0; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:999999; overflow:hidden; }
                .sp-cs-option { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; font-size: 0.9rem; line-height:1.2; }
                .sp-cs-option:hover { background: #f1f5f9; }
                .sp-cs-option.selected { background: #e0f2fe; color: #0369a1; font-weight: 600; position: relative; }
                .sp-cs-option.selected::after { content: '✓'; position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-size: 1.1rem; color: #0284c7; }
            </style>`;

            results[2].data.forEach(x => {
                listM += `<div class="sp-cs-option" data-id="${x.id}" data-text="${x.nama_mapel}"><strong>${x.nama_mapel}</strong> <br><small style="color:gray">${x.kode_mapel}</small></div>`;
            });

            EModal.form({
                title: id ? 'Edit Distribusi' : 'Tambah Distribusi Mengajar',
                form: `
                    ${style}
                    <input type="hidden" id="fId" value="${id || ''}">
                    
                    <div class="form-group">
                        <label>Pilih Guru</label>
                        <input type="hidden" id="fG">
                        <div class="sp-cs-container" id="csContainerG">
                            <div class="sp-cs-btn" id="csBtnG">
                                <span id="csTextG" style="color:#64748b;">-- Pilih Guru --</span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </div>
                            <div class="sp-cs-dropdown" id="csDropG">
                                <div style="padding:10px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                                    <input type="text" id="csSearchG" class="form-input" placeholder="Cari guru..." style="width:100%; padding:8px 12px; height:36px; border-radius:6px; outline:none;" autocomplete="off">
                                </div>
                                <div id="csListG" style="max-height:200px; overflow-y:auto; padding:0;">
                                    ${listG}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group"><label>Pilih Kelas</label>${gridK}<p style="font-size:0.75rem;color:gray;margin-top:6px">Klik kotak kelas untuk memilih (bisa lebih dari satu)</p></div>
                    
                    <div class="sch-form-row">
                        <div class="form-group">
                            <label>Mata Pelajaran</label>
                            <input type="hidden" id="fM">
                            <div class="sp-cs-container" id="csContainerM">
                                <div class="sp-cs-btn" id="csBtnM">
                                    <span id="csTextM" style="color:#64748b;">-- Pilih Mapel --</span>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </div>
                                <div class="sp-cs-dropdown" id="csDropM">
                                    <div style="padding:10px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                                        <input type="text" id="csSearchM" class="form-input" placeholder="Cari mapel..." style="width:100%; padding:8px 12px; height:36px; border-radius:6px; outline:none;" autocomplete="off">
                                    </div>
                                    <div id="csListM" style="max-height:200px; overflow-y:auto; padding:0;">
                                        ${listM}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group"><label>Jumlah JP</label><select class="form-select" id="fJp">${[1,2,3,4,5,6,7].map(j=>'<option value="'+j+'">'+j+' JP</option>').join('')}</select></div>
                    </div>
                `,
                onOpen: () => {
                    const bindCustomSelect = (idPrefix, hiddenInputId, isMultiple = false) => {
                        $(`#csBtn${idPrefix}`).on('click', function(e) {
                            e.stopPropagation();
                            const isActive = $(this).hasClass('active');
                            $('.sp-cs-dropdown').hide();
                            $('.sp-cs-btn').removeClass('active');
                            
                            if (!isActive) {
                                $(this).addClass('active');
                                $(`#csDrop${idPrefix}`).show();
                                $(`#csSearch${idPrefix}`).val('').trigger('input').focus();
                            }
                        });

                        $(`#csSearch${idPrefix}`).on('input', function() {
                            const term = $(this).val().toLowerCase();
                            $(`#csList${idPrefix} .sp-cs-option`).each(function() {
                                const text = $(this).text().toLowerCase();
                                $(this).toggle(text.includes(term));
                            });
                        });

                        $(`#csList${idPrefix}`).on('click', '.sp-cs-option', function(e) {
                            e.stopPropagation();
                            if (isMultiple) {
                                $(this).toggleClass('selected');
                                let selectedIds = [];
                                let selectedTexts = [];
                                $(`#csList${idPrefix} .sp-cs-option.selected`).each(function() {
                                    selectedIds.push($(this).data('id'));
                                    const rawText = $(this).data('text');
                                    const shortName = rawText.includes(' - ') ? rawText.split(' - ')[1] : rawText;
                                    selectedTexts.push(shortName);
                                });
                                $(`#${hiddenInputId}`).val(selectedIds.join(','));
                                if (selectedIds.length === 0) {
                                    $(`#csText${idPrefix}`).html(`-- Pilih ${idPrefix === 'G' ? 'Guru' : 'Mapel'} --`).css('color', '#64748b');
                                } else {
                                    let textToShow = selectedTexts.join(', ');
                                    if (textToShow.length > 25) {
                                        textToShow = `${selectedIds.length} Guru Terpilih`;
                                    }
                                    $(`#csText${idPrefix}`).html(textToShow).css('color', '#1e293b');
                                }
                            } else {
                                $(`#csList${idPrefix} .sp-cs-option`).removeClass('selected');
                                $(this).addClass('selected');
                                const val = $(this).data('id');
                                const text = $(this).data('text');
                                $(`#${hiddenInputId}`).val(val);
                                $(`#csText${idPrefix}`).html(text).css('color', '#1e293b');
                                $(`#csDrop${idPrefix}`).hide();
                                $(`#csBtn${idPrefix}`).removeClass('active');
                            }
                        });
                    };

                    bindCustomSelect('G', 'fG', !id);
                    bindCustomSelect('M', 'fM', false);

                    $(document).on('click.csDropdown', function(e) {
                        if (!$(e.target).closest('.sp-cs-container').length) {
                            $('.sp-cs-dropdown').hide();
                            $('.sp-cs-btn').removeClass('active');
                        }
                    });

                    if (id) {
                        const row = this.state.distData.find(x => x.id == id);
                        if(row) { 
                            $('#fG').val(row.guru_id); 
                            $(`#csListG .sp-cs-option`).removeClass('selected');
                            const optG = $(`#csListG .sp-cs-option[data-id="${row.guru_id}"]`);
                            if(optG.length) {
                                optG.addClass('selected');
                                $('#csTextG').html(optG.data('text')).css('color', '#1e293b');
                            }
                            
                            $('#ck_'+row.kelas_id).prop('checked', true);
                            
                            // Restrict to single class selection when editing
                            $('.kIdCheckbox').on('change', function() {
                                if ($(this).is(':checked')) {
                                    $('.kIdCheckbox').not(this).prop('checked', false);
                                }
                            });
                            
                            $('#fM').val(row.mapel_id); 
                            const optM = $(`#csListM .sp-cs-option[data-id="${row.mapel_id}"]`);
                            if(optM.length) $('#csTextM').html(optM.data('text')).css('color', '#1e293b');

                            $('#fJp').val(row.jp); 
                        }
                    }
                },
                onConfirm: () => {
                    const gIdVal = $('#fG').val(), mId = $('#fM').val(), jp = $('#fJp').val();
                    const kIds = [];
                    $('.kIdCheckbox:checked').each(function(){ kIds.push($(this).val()); });
                    if(!gIdVal || !mId || !kIds || kIds.length===0) {
                        EModal.toast({type: 'error', title: 'Perhatian', message: 'Silakan isi Guru, Mapel, dan minimal satu Kelas!'});
                        return false;
                    }

                    const gIds = gIdVal.split(',').map(x => x.trim()).filter(x => x !== '');
                    let targetAction = id ? 'update' : 'create';
                    
                    if (id) {
                        // Update: single teacher and class
                        let data = { id: id, guru_id: gIds[0], kelas_id: kIds[0], mapel_id: mId, jp: jp };
                        this.api('sch_distribusi.php?action=update', {method:'POST', data}).done(() => {
                            EModal.closeAll(); this.reloadCurrentPage();
                            EModal.toast({ type: 'success', title: 'Berhasil', message: 'Distribusi diperbarui.' });
                        });
                    } else {
                        // Create: combination of multiple teachers and multiple classes
                        let requests = [];
                        gIds.forEach(gId => {
                            kIds.forEach(kId => {
                                let data = { guru_id: gId, kelas_id: kId, mapel_id: mId, jp: jp };
                                requests.push(this.api('sch_distribusi.php?action=create', {method:'POST', data}));
                            });
                        });

                        Promise.all(requests).then(() => {
                            EModal.closeAll(); this.reloadCurrentPage();
                            EModal.toast({ type: 'success', title: 'Berhasil', message: 'Distribusi berhasil ditambahkan.' });
                        });
                    }
                    return false;
                }
            });
        });
    },

    // ==============================================
    // KESEDIAAN GURU
    // ==============================================
    renderSchKesediaan($container) {
        $container.html(`
            <div class="sch-card">
                <div class="sch-card-header">
                    <h3>Matriks Ketersediaan Guru</h3>
                    <div class="sch-toolbar">
                        <button class="btn btn-outline" onclick="Curriculum.exportData('kesediaan')">Export</button>
                        <button class="btn btn-outline" onclick="Curriculum.importData('kesediaan')">Import</button>
                    </div>
                </div>
                <div class="sch-card-body">
                    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px">Pilih hari dimana guru <strong>siap</strong> untuk mengajar. Hari yang kosong dan tipe non-pembelajaran diabaikan. <span style="color:var(--success);font-weight:600;margin-left:8px">✓ Tersimpan Otomatis (Auto-Save)</span></p>
                    <div class="matrix-container" id="kesediaanTable"><div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat matriks...</h3></div></div>
                </div>
            </div>
        `);

        // Fetch jam and guru kesediaan
        Promise.all([
            this.api('sch_jam.php?action=list'),
            this.api('sch_kesediaan.php?action=list')
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
            days.forEach(d => html += `<th>${d} <br><small style="font-weight:400">Jam: ${daysMap[d].map(j=>j.nama_jam).join(',')}</small></th>`);
            html += '</tr></thead><tbody>';

            data.forEach(g => {
                html += `<tr data-gid="${g.id}">
                    <td style="text-align:left"><strong>${g.nama_guru}</strong><br><small>${g.kode_guru}</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline" onclick="Curriculum.checkAllRow(${g.id}, true)">All</button>
                        <button class="btn btn-sm btn-outline" onclick="Curriculum.checkAllRow(${g.id}, false)">0</button>
                    </td>`;
                
                days.forEach(d => {
                    html += `<td>
                        <div style="text-align:center; margin-bottom:6px;">
                            <span style="font-size:0.75rem; color:var(--primary-color,#3b82f6); cursor:pointer; font-weight:600; background:var(--bg-color,#f8fafc); padding:2px 8px; border-radius:12px; border:1px solid #e2e8f0; user-select:none" onclick="Curriculum.toggleDayCell(${g.id}, '${d}')">Pilih Semua</span>
                        </div>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:center">`;
                    daysMap[d].forEach(j => {
                        const isChecked = g.jam_ids.includes(j.id) ? 'checked' : '';
                        html += `
                        <label title="Jam ke-${j.jam_ke} (${j.nama_jam})">
                            <input type="checkbox" onchange="Curriculum.saveGuruKesediaan(${g.id})" class="matrix-checkbox jcb-${g.id} jcb-${g.id}-${d}" style="display:none" value="${j.id}" ${isChecked}>
                            <div class="chk-btn">${j.nama_jam}</div>
                        </label>`;
                    });
                    html += `</div></td>`;
                });
                html += '</tr>';
            });
            html += '</tbody></table>';

            $('#kesediaanTable').html(html);
        });
    },

    checkAllRow(gid, state) {
        $(`.jcb-${gid}`).prop('checked', state);
        this.saveGuruKesediaan(gid);
    },

    toggleDayCell(gid, day) {
        const $chks = $(`.jcb-${gid}-${day}`);
        const allChecked = $chks.length === $chks.filter(':checked').length;
        $chks.prop('checked', !allChecked);
        this.saveGuruKesediaan(gid);
    },

    saveKesediaan(guruList) {
        // Obsolete: Kept for reference but not used since we have auto-save
    },

    saveGuruKesediaan(gid) {
        if (!this._saveTimers) this._saveTimers = {};
        clearTimeout(this._saveTimers[gid]);
        
        this._saveTimers[gid] = setTimeout(() => {
            let jids = [];
            $(`.jcb-${gid}:checked`).each(function() { jids.push(parseInt($(this).val())); });
            this.api('sch_kesediaan.php?action=save', { method:'POST', data: { guru_id: gid, jam_ids: jids }});
        }, 400); // 400ms debounce
    },

    // ==============================================
    // ENGINE JADWAL
    // ==============================================
    renderSchJadwal($container) {
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
                        <button class="btn btn-primary" onclick="Curriculum.generateJadwal()" style="padding:12px 24px;font-size:1.1rem">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg> Generate Jadwal
                        </button>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                        <select id="jdwFilter" class="form-select" style="max-width:300px"><option value="">Semua Kelas</option></select>
                        <button class="btn btn-outline" id="btnCetakJadwal" onclick="Curriculum.printJadwal()">Cetak PDF</button>
                    </div>
                    <div id="jdwViewer"><div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat jadwal...</h3></div></div>
                </div>
            </div>
        `);
        
        this.api('sch_kelas.php?action=list').done(res => {
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
                const $overlay = $(`
                    <div id="genProgressOverlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.9);backdrop-filter:blur(4px);z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;color:white;font-family:'Inter',sans-serif;">
                        <h2 style="margin-bottom:12px;font-size:24px;font-weight:700">Engine Algoritma Sedang Bekerja...</h2>
                        <p style="margin-bottom:30px;color:#94a3b8;font-size:16px" id="genProgressText">Mencari kombinasi jadwal terbaik (Estimasi: 15 - 45 detik)</p>
                        <div style="width:400px;background:#334155;border-radius:12px;height:16px;overflow:hidden;box-shadow:inset 0 2px 4px rgba(0,0,0,0.3)">
                            <div id="genProgressBar" style="width:0%;background:linear-gradient(90deg, #3b82f6, #60a5fa);height:100%;transition:width 0.4s ease;border-radius:12px"></div>
                        </div>
                        <div style="margin-top:16px;font-weight:bold;font-size:18px;color:#60a5fa" id="genProgressPct">0%</div>
                    </div>
                `);
                $('body').append($overlay);

                let pct = 0;
                const interval = setInterval(() => {
                    if (pct < 98) {
                        let add = (98 - pct) * 0.08;
                        if (add < 0.2) add = 0.2;
                        pct += add;
                        $('#genProgressBar').css('width', pct + '%');
                        $('#genProgressPct').text(Math.floor(pct) + '%');
                    }
                }, 400);

                this.api('sch_jadwal.php?action=generate', { method: 'POST' }).done(res => {
                    clearInterval(interval);
                    $('#genProgressBar').css('width', '100%');
                    $('#genProgressPct').text('100%');
                    
                    setTimeout(() => {
                        $('#genProgressOverlay').fadeOut(400, function() { $(this).remove(); });
                        EModal.info({ title: 'Selesai', message: res.message });
                        this.viewJadwal('');
                    }, 600);
                }).fail(xhr => {
                    clearInterval(interval);
                    $('#genProgressOverlay').remove();
                    EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message || 'Gagal memproses jadwal.'});
                });
            }
        });
    },

    viewJadwal(kelasId) {
        // Fetch structural jam to build the grid headers and ALL schedules to detect clashes globally
        Promise.all([
            this.api('sch_jadwal.php?action=list'),
            this.api('sch_jam.php?action=list')
        ]).then(res => {
            const allJadwal = res[0].data;
            const jams = res[1].data;
            if(!allJadwal.length) { $('#jdwViewer').html('<div class="sch-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg><h3>Jadwal Masih Kosong</h3><p>Silakan klik tombol <strong>Generate Jadwal</strong> untuk mulai memproses distribusi mengajar.</p></div>'); return; }

            // Filter for display if class is selected
            const displayJadwal = kelasId ? allJadwal.filter(x => x.kelas_id == kelasId) : allJadwal;

            // Because a full school view is huge, we'll split by Kelas if no filter, or show one if filter.
            // Using a simple grid approach grouped by Kelas.
            const kelasGroups = {};
            displayJadwal.forEach(j => {
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
            
            const renderCell = (cellJam, kjadwal) => {
                if (!cellJam) return `<td style="background:#f1f5f9; border:1px solid #e2e8f0;"></td>`;
                
                if (cellJam.tipe !== 'Pembelajaran') {
                    return `<td style="background:#f8fafc; border:1px solid #e2e8f0; text-align:center; vertical-align:middle; padding:6px; min-width:90px;">
                                <div style="font-size:0.7rem; font-weight:700; color:#64748b; letter-spacing:0.5px;">${cellJam.nama_jam.toUpperCase()}</div>
                            </td>`;
                }

                const slotApp = kjadwal.find(x => x.jam_belajar_id == cellJam.id);
                if (!slotApp) return `<td style="background:#fff;min-width:90px; border:1px solid #e2e8f0;"></td>`;
                
                const clashingSlots = allJadwal.filter(x => x.jam_belajar_id == cellJam.id && x.guru_id == slotApp.guru_id);
                const isClash = clashingSlots.length > 1;
                
                let bgWarna = isClash ? '#fee2e2' : 'transparent';
                let borderWarna = isClash ? 'border: 2px solid #ef4444;' : 'border: 1px solid #e2e8f0;';
                
                return `<td style="background-color:${bgWarna}; ${borderWarna} padding:6px; min-width:90px; text-align:center; vertical-align:middle;">
                            <div style="font-weight:800;color:${isClash ? '#b91c1c' : 'var(--primary)'};font-size:0.75rem;">${slotApp.kode_mapel}</div>
                            <div style="font-size:0.65rem;color:${isClash ? '#ef4444' : 'var(--text-muted)'}; line-height:1.2; margin-top:2px; font-weight:700;">${slotApp.singkatan || slotApp.nama_guru}</div>
                            ${isClash ? '<div style="font-size:0.55rem;color:white;background:#ef4444;padding:2px 4px;border-radius:4px;display:inline-block;margin-top:3px;font-weight:bold;letter-spacing:0.5px;">TABRAKAN</div>' : ''}
                        </td>`;
            };

            if (kelasId) {
                // TAMPILAN PER KELAS
                for(let kname in kelasGroups) {
                    const kjadwal = kelasGroups[kname];
                    html += `<div style="margin-bottom:40px">
                        <h4 style="font-size:1.1rem;padding:8px 16px;background:var(--primary);color:white;display:inline-block;border-radius:8px">Kelas: ${kname}</h4>
                        <div style="overflow-x:auto;margin-top:12px;border:1px solid var(--border-color);border-radius:8px;">
                            <table class="sch-table matrix-table" style="min-width:800px;background:white; border-collapse:collapse;">
                                <thead>
                                    <tr><th style="width:100px; background:var(--primary); color:white; border:1px solid #cbd5e1;">HARI</th>
                                    <th colspan="${maxJams}" style="background:var(--primary); color:white; border:1px solid #cbd5e1;">JADWAL</th></tr>
                                </thead>
                                <tbody>`;
                    
                    daysArr.forEach(d => {
                        html += `<tr><td style="font-weight:700; text-align:center; vertical-align:middle; background:#f8fafc; border:1px solid #cbd5e1; border-right:2px solid #94a3b8; text-transform:uppercase;">${d}</td>`;
                        
                        let dayJams = jams.filter(x => x.hari === d).sort((a,b) => parseInt(a.jam_ke) - parseInt(b.jam_ke));
                        
                        for(let idx=0; idx<maxJams; idx++) {
                            html += renderCell(dayJams[idx], kjadwal);
                        }
                        html += `</tr>`;
                    });
                    html += `</tbody></table></div></div>`;
                }
            } else {
                // TAMPILAN MASTER JADWAL (SEMUA KELAS)
                // Layout:
                // SENIN
                // JAM | 10.1 | 10.2 | 10.3
                // Jam 1 | ... | ... | ...
                
                const kelasNames = Object.keys(kelasGroups).sort();
                
                html += `<div style="overflow-x:auto; border:1px solid var(--border-color); border-radius:8px;">
                    <table class="sch-table matrix-table" style="min-width:1000px;background:white; border-collapse:collapse;">`;
                
                daysArr.forEach(d => {
                    const dayJams = jams.filter(x => x.hari === d).sort((a,b) => parseInt(a.jam_ke) - parseInt(b.jam_ke));
                    
                    html += `<tbody>`;
                    // Baris Nama Hari
                    html += `<tr><td colspan="${kelasNames.length + 1}" style="font-weight:800; font-size:1.2rem; text-align:center; padding:12px 16px; background:var(--primary); border:1px solid #cbd5e1; color:white; text-transform:uppercase; letter-spacing:1px;">${d}</td></tr>`;
                    
                    // Baris Nama Kelas (Header Horizontal)
                    html += `<tr>`;
                    html += `<td style="font-weight:700; background:#e2e8f0; border:1px solid #cbd5e1; color:var(--primary-dark); text-align:center; width:80px; font-size:0.8rem;">JAM</td>`;
                    kelasNames.forEach(kname => {
                        html += `<td style="font-weight:700; background:#f1f5f9; border:1px solid #cbd5e1; color:var(--primary-dark); text-align:center; min-width:90px; font-size:0.8rem;">${kname}</td>`;
                    });
                    html += `</tr>`;
                    
                    // Baris Jam (Data vertikal)
                    for(let idx=0; idx<maxJams; idx++) {
                        const cellJam = dayJams[idx];
                        
                        let labelJam = cellJam ? (cellJam.tipe === 'Pembelajaran' ? cellJam.nama_jam : cellJam.nama_jam.toUpperCase()) : (idx+1);
                        
                        html += `<tr>`;
                        if (cellJam && cellJam.tipe !== 'Pembelajaran') {
                            html += `<td colspan="${kelasNames.length + 1}" style="background:#f8fafc; border:1px solid #e2e8f0; text-align:center; vertical-align:middle; padding:6px; letter-spacing: 2px;">
                                        <div style="font-size:0.75rem; font-weight:800; color:#64748b;">${cellJam.nama_jam.toUpperCase()}</div>
                                     </td>`;
                        } else {
                            html += `<td style="font-weight:700; background:#f8fafc; border:1px solid #e2e8f0; border-right:2px solid #94a3b8; color:var(--primary-dark); text-align:center; font-size:0.75rem; letter-spacing:0.5px;">${labelJam}</td>`;
                            kelasNames.forEach(kname => {
                                const kjadwal = kelasGroups[kname];
                                html += renderCell(cellJam, kjadwal);
                            });
                        }
                        html += `</tr>`;
                    }
                    html += `</tbody>`;
                });
                
                html += `</table></div>`;
            }

            $('#jdwViewer').html(html);
        });
    },

    // ==============================================
    // SELECT2 LOADER
    // ==============================================
    loadSelect2(callback) {
        if (window.jQuery && window.jQuery.fn.select2) { callback(); return; }
        const loader = EModal.loading('Memuat komponen pencarian...');
        $('<link/>', {rel: 'stylesheet', type: 'text/css', href: 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css'}).appendTo('head');
        $.getScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js')
            .done(() => { EModal.close(loader); callback(); })
            .fail(() => { EModal.close(loader); callback(); }); // Fallback
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
                                this.api('sch_' + type + '.php?action=import', { method: 'POST', data: { data: excelRows } }).done(res => {
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

$(document).ready(() => Curriculum.init());
