/**
 * E-Academic Core Module
 * SPA Routing, State Management, and Views
 */

const Academic = {
    // Global State
    state: {
        user: window.ACADEMIC_CONFIG ? window.ACADEMIC_CONFIG.user : null,
        token: window.ACADEMIC_CONFIG ? window.ACADEMIC_CONFIG.token : null,
        school: window.ACADEMIC_CONFIG ? window.ACADEMIC_CONFIG.school : { nama: 'E-Portal', icon: '' },
        academicYear: window.ACADEMIC_CONFIG ? window.ACADEMIC_CONFIG.academicYear : null,
        currentRoute: 'dashboard',
        params: {}
    },

    // Base URLs
    baseUrl: window.ACADEMIC_CONFIG ? window.ACADEMIC_CONFIG.baseUrl : '/',
    moduleUrl: window.ACADEMIC_CONFIG ? window.ACADEMIC_CONFIG.moduleUrl : 'modules/e-academic/',

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
                    <div class="acad-nav-label">Manajemen Data</div>
                    <button class="acad-nav-item" data-route="kelas">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Data Kelas
                    </button>
                    <button class="acad-nav-item" data-route="mapel">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Mata Pelajaran
                    </button>
                    <button class="acad-nav-item" data-route="mengajar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Penugasan Mengajar
                    </button>
                </div>
            `;
        } else {
            // For teachers, they can only view teaching assignments
            navHtml += `
                <div class="acad-nav-group">
                    <div class="acad-nav-label">Akademik Saya</div>
                    <button class="acad-nav-item" data-route="mengajar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Jadwal Mengajar Saya
                    </button>
                </div>
            `;
        }

        $('#sidebarNav').html(navHtml);

        // Click handler
        $('.acad-nav-item').on('click', function() {
            const route = $(this).data('route');
            Academic.navigate(route);
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
        window.location.hash = `#/${route}` + (Object.keys(params).length ? `?${$.param(params)}` : '');
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
            case 'mengajar':
                $title.text(this.state.user.role === 'superadmin' ? 'Penugasan Mengajar' : 'Jadwal Mengajar Saya');
                this.setBreadcrumbs([{ label: 'Mengajar' }]);
                this.renderMengajar($content);
                break;
            default:
                this.navigate('dashboard');
        }
    },

    setBreadcrumbs(crumbs) {
        const $breadcrumb = $('#breadcrumb');
        let html = `<a href="#/dashboard">E-Academic</a>`;
        
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
        $container.html(`
            <div class="acad-stats" id="dashboardStats">
                <div class="acad-stat-card skeleton-module" style="height: 100px;"></div>
                <div class="acad-stat-card skeleton-module" style="height: 100px;"></div>
                <div class="acad-stat-card skeleton-module" style="height: 100px;"></div>
            </div>
            <div class="acad-card">
                <div class="acad-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> E-Academic Portal</h3>
                </div>
                <div class="acad-card-body" style="line-height:1.7;">
                    <h4 style="margin-top:0; font-family:'Outfit',sans-serif; color:var(--acad-primary);">Selamat Datang di Sistem Informasi Akademik Sekolah</h4>
                    <p>Modul E-Academic mempermudah pengelolaan informasi kurikulum sekolah secara efisien. Anda dapat mengelola daftar mata pelajaran (Mapel), kelompok kelas beserta tingkatannya, dan melakukan penugasan mengajar guru secara langsung pada tahun ajaran aktif.</p>
                    <div style="display:flex; gap:16px; margin-top:24px;">
                        <button class="btn-acad btn-acad-primary" onclick="Academic.navigate('kelas')">Mulai Kelola Kelas</button>
                        <button class="btn-acad btn-acad-outline" onclick="Academic.navigate('mengajar')">Lihat Penugasan Guru</button>
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
                        <h4>${d.total_kelas}</h4>
                        <p>Total Rombel Kelas</p>
                    </div>
                </div>
                <div class="acad-stat-card slide-up" style="animation-delay: 0.08s">
                    <div class="acad-stat-icon" style="background: rgba(236, 72, 153, 0.1); color: var(--acad-accent);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                    </div>
                    <div class="acad-stat-info">
                        <h4>${d.total_mapel}</h4>
                        <p>Total Mata Pelajaran</p>
                    </div>
                </div>
                <div class="acad-stat-card slide-up" style="animation-delay: 0.16s">
                    <div class="acad-stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="acad-stat-info">
                        <h4>${d.total_mengajar}</h4>
                        <p>Penugasan Guru Aktif</p>
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
                        <button class="btn-acad btn-acad-primary" onclick="Academic.showKelasForm()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Kelas
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
                        <p>Tambahkan kelas baru dengan mengklik tombol Tambah Kelas.</p>
                    </div>
                `);
                return;
            }

            const rows = res.data.map((k, idx) => `
                <tr class="fade-in" style="animation-delay:${idx*0.04}s">
                    <td><strong>${this.escapeHtml(k.nama_kelas)}</strong></td>
                    <td>Tingkat ${k.tingkat}</td>
                    <td>${k.wali_nama ? `<strong>${this.escapeHtml(k.wali_nama)}</strong>` : '<span style="color:var(--acad-text-muted)">Belum ditentukan</span>'}</td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-icon" title="Edit" onclick="Academic.showKelasForm(${k.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn-icon danger" title="Hapus" onclick="Academic.deleteKelas(${k.id}, '${this.escapeHtml(k.nama_kelas)}')">
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

    showKelasForm(id = null) {
        const isEdit = id !== null;
        
        // Fetch teachers list for select dropdown
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
        const isEdit = data !== null;
        const d = data || {};
        
        const teacherOptions = teachers.map(t => 
            `<option value="${t.id}" ${d.wali_id == t.id ? 'selected' : ''}>${this.escapeHtml(t.nama_lengkap)} (${this.escapeHtml(t.username)})</option>`
        ).join('');

        EModal.form({
            title: isEdit ? 'Edit Kelas' : 'Tambah Kelas',
            size: 'md',
            form: `
                <div class="form-group-acad">
                    <label class="form-label-acad">Nama Kelas / Rombel</label>
                    <input class="form-input-acad" id="formKelasNama" value="${this.escapeHtml(d.nama_kelas || '')}" placeholder="contoh: X-MIPA-1">
                </div>
                <div class="form-group-acad">
                    <label class="form-label-acad">Tingkat Pendidikan</label>
                    <select class="form-select-acad" id="formKelasTingkat">
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
            confirmText: 'Simpan Data',
            onConfirm: () => {
                const nama = ($('#formKelasNama').val() || '').trim();
                const tingkat = $('#formKelasTingkat').val();
                const wali = $('#formKelasWali').val();

                if (!nama || !tingkat) {
                    EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Semua kolom wajib diisi.' });
                    return false;
                }

                const endpoint = isEdit ? 'kelas.php?action=update' : 'kelas.php?action=create';
                const postData = isEdit 
                    ? { id: d.id, nama_kelas: nama, tingkat: parseInt(tingkat, 10), wali_id: wali }
                    : { nama_kelas: nama, tingkat: parseInt(tingkat, 10), wali_id: wali };

                this.api(endpoint, {
                    method: 'POST',
                    data: postData
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

    // ==================== MAPEL VIEW ====================
    renderMapel($container) {
        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header">
                    <div>
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> Mata Pelajaran Kurikulum</h3>
                        <p class="acad-subtitle">Kelola daftar mata pelajaran kurikulum sekolah beserta Kriteria Ketuntasan Minimal (KKM).</p>
                    </div>
                    <div class="acad-toolbar">
                        <button class="btn-acad btn-acad-primary" onclick="Academic.showMapelForm()">
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
                $('#mapelTableWrapper').html(`
                    <div class="acad-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        <h3>Belum Ada Data Mata Pelajaran</h3>
                        <p>Tambahkan mata pelajaran kurikulum baru dengan mengklik tombol Tambah Mapel.</p>
                    </div>
                `);
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
                            <button class="btn-icon" title="Edit" onclick="Academic.showMapelForm(${m.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn-icon danger" title="Hapus" onclick="Academic.deleteMapel(${m.id}, '${this.escapeHtml(m.nama_mapel)}')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            $('#mapelTableWrapper').html(`
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kode Mapel</th>
                                <th>Nama Mata Pelajaran</th>
                                <th>Kelompok</th>
                                <th>KKM</th>
                                <th>Status</th>
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
                <div class="form-group-acad">
                    <label class="form-label-acad">Kode Mapel</label>
                    <input class="form-input-acad" id="formMapelKode" value="${this.escapeHtml(d.kode_mapel || '')}" placeholder="contoh: BIN-10" ${isEdit ? 'readonly style="opacity:0.75;"' : ''}>
                </div>
                <div class="form-group-acad">
                    <label class="form-label-acad">Nama Mata Pelajaran</label>
                    <input class="form-input-acad" id="formMapelNama" value="${this.escapeHtml(d.nama_mapel || '')}" placeholder="contoh: Bahasa Indonesia">
                </div>
                <div class="form-group-acad">
                    <label class="form-label-acad">Kelompok Kurikulum</label>
                    <select class="form-select-acad" id="formMapelKelompok">
                        <option value="Kelompok A" ${d.kelompok === 'Kelompok A' ? 'selected' : ''}>Kelompok A (Wajib)</option>
                        <option value="Kelompok B" ${d.kelompok === 'Kelompok B' ? 'selected' : ''}>Kelompok B (Wajib)</option>
                        <option value="Kelompok C" ${d.kelompok === 'Kelompok C' ? 'selected' : ''}>Kelompok C (Peminatan)</option>
                        <option value="Pilihan" ${d.kelompok === 'Pilihan' ? 'selected' : ''}>Mata Pelajaran Pilihan</option>
                        <option value="Muatan Lokal" ${d.kelompok === 'Muatan Lokal' ? 'selected' : ''}>Muatan Lokal</option>
                    </select>
                </div>
                <div class="form-group-acad">
                    <label class="form-label-acad">KKM (Kriteria Ketuntasan Minimal)</label>
                    <input type="number" class="form-input-acad" id="formMapelKkm" value="${d.kkm || 75}" min="0" max="100">
                </div>
                <div class="form-group-acad">
                    <label class="form-label-acad">Status Aktif</label>
                    <select class="form-select-acad" id="formMapelStatus">
                        <option value="1" ${d.status == 1 ? 'selected' : ''}>Aktif</option>
                        <option value="0" ${d.status == 0 ? 'selected' : ''}>Nonaktif</option>
                    </select>
                </div>
            `,
            confirmText: 'Simpan Data',
            onConfirm: () => {
                const kode = ($('#formMapelKode').val() || '').trim();
                const nama = ($('#formMapelNama').val() || '').trim();
                const kelompok = $('#formMapelKelompok').val();
                const kkm = $('#formMapelKkm').val();
                const status = $('#formMapelStatus').val();

                if (!kode || !nama) {
                    EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Kode dan nama mata pelajaran wajib diisi.' });
                    return false;
                }

                const endpoint = isEdit ? 'mapel.php?action=update' : 'mapel.php?action=create';
                const postData = isEdit
                    ? { id: d.id, kode_mapel: kode, nama_mapel: nama, kelompok: kelompok, kkm: parseInt(kkm, 10), status: parseInt(status, 10) }
                    : { kode_mapel: kode, nama_mapel: nama, kelompok: kelompok, kkm: parseInt(kkm, 10), status: parseInt(status, 10) };

                this.api(endpoint, {
                    method: 'POST',
                    data: postData
                }).done(res => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                    this.loadMapelTable();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal memproses data.' });
                });

                return false;
            }
        });
    },

    deleteMapel(id, name) {
        EModal.confirm({
            title: 'Hapus Mata Pelajaran',
            message: `Apakah Anda yakin ingin menghapus <strong>${name}</strong>? Data penugasan mengajar dan nilai rapor terkait mungkin juga akan terpengaruh.`,
            type: 'danger',
            confirmText: 'Hapus',
            onConfirm: () => {
                this.api('mapel.php?action=delete', {
                    method: 'POST',
                    data: { id }
                }).done(res => {
                    EModal.toast({ type: 'success', title: 'Terhapus', message: res.message });
                    this.loadMapelTable();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menghapus mata pelajaran.' });
                });
            }
        });
    },

    // ==================== MENGAJAR VIEW ====================
    renderMengajar($container) {
        const u = this.state.user;
        const isAdmin = u.role === 'superadmin';
        const activeYear = this.state.academicYear;
        const subTitle = activeYear?.tahun_ajaran 
            ? `Tahun Ajaran Aktif: <strong>${this.escapeHtml(activeYear.tahun_ajaran)} Semester ${activeYear.semester}</strong>`
            : '<span style="color:var(--danger)">Tahun Ajaran Aktif Belum Diatur</span>';

        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header">
                    <div>
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg> ${isAdmin ? 'Daftar Penugasan Mengajar Guru' : 'Jadwal Mengajar Saya'}</h3>
                        <p class="acad-subtitle">${subTitle}</p>
                    </div>
                    ${isAdmin ? `
                    <div class="acad-toolbar">
                        <button class="btn-acad btn-acad-primary" onclick="Academic.showMengajarForm()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Penugasan
                        </button>
                    </div>` : ''}
                </div>
                <div class="acad-card-body" id="mengajarTableWrapper">
                    <div class="skeleton-module" style="height:250px;"></div>
                </div>
            </div>
        `);

        this.loadMengajarTable();
    },

    loadMengajarTable() {
        this.api('mengajar.php?action=list').done(res => {
            const isAdmin = this.state.user.role === 'superadmin';

            if (!res.success || !res.data.length) {
                $('#mengajarTableWrapper').html(`
                    <div class="acad-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        <h3>Belum Ada Data Penugasan</h3>
                        <p>${isAdmin ? 'Tentukan penugasan mengajar guru untuk kelas dan mata pelajaran terkait.' : 'Anda belum terdaftar dalam penugasan mengajar semester ini.'}</p>
                    </div>
                `);
                return;
            }

            // Filter for teacher view
            let data = res.data;
            if (!isAdmin) {
                data = data.filter(m => m.guru_id == this.state.user.id);
                if (!data.length) {
                    $('#mengajarTableWrapper').html(`
                        <div class="acad-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <h3>Jadwal Mengajar Kosong</h3>
                            <p>Anda belum ditugaskan untuk mengajar mata pelajaran apa pun pada semester ini.</p>
                        </div>
                    `);
                    return;
                }
            }

            const rows = data.map((m, idx) => `
                <tr class="fade-in" style="animation-delay:${idx*0.04}s">
                    ${isAdmin ? `
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div class="acad-user-avatar" style="width:32px; height:32px; font-size:11px;">${this.getInitials(m.guru_nama)}</div>
                            <div>
                                <strong>${this.escapeHtml(m.guru_nama)}</strong><br>
                                <span class="text-muted" style="font-size:0.75rem;">NIK: ${this.escapeHtml(m.guru_nik || '-')}</span>
                            </div>
                        </div>
                    </td>` : ''}
                    <td><strong>${this.escapeHtml(m.nama_mapel)}</strong> <span class="text-muted" style="font-size:0.8rem;">(${this.escapeHtml(m.kode_mapel)})</span></td>
                    <td><span class="badge badge-info" style="font-weight:600;">${this.escapeHtml(m.nama_kelas)}</span></td>
                    <td>Semester ${m.semester} / ${this.escapeHtml(m.tahun_ajaran)}</td>
                    ${isAdmin ? `
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-icon danger" title="Hapus Penugasan" onclick="Academic.deleteMengajar(${m.id}, '${this.escapeHtml(m.guru_nama)}', '${this.escapeHtml(m.nama_mapel)}', '${this.escapeHtml(m.nama_kelas)}')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </td>` : ''}
                </tr>
            `).join('');

            $('#mengajarTableWrapper').html(`
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                ${isAdmin ? '<th>Guru Pengajar</th>' : ''}
                                <th>Mata Pelajaran</th>
                                <th>Kelas</th>
                                <th>Tahun Ajaran</th>
                                ${isAdmin ? '<th style="width:80px;">Aksi</th>' : ''}
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

    showMengajarForm() {
        // Fetch metadata (teachers, active classes, active mapel)
        this.api('mengajar.php?action=meta').done(res => {
            if (!res.success) return;
            const meta = res.data;
            this.renderMengajarFormModal(meta);
        });
    },

    renderMengajarFormModal(meta) {
        const teacherOptions = meta.teachers.map(t => 
            `<option value="${t.id}">${this.escapeHtml(t.nama_lengkap)} (${this.escapeHtml(t.username)})</option>`
        ).join('');

        const classOptions = meta.classes.map(c => 
            `<option value="${c.id}">${this.escapeHtml(c.nama_kelas)} (Tingkat ${c.tingkat})</option>`
        ).join('');

        const subjectOptions = meta.subjects.map(s => 
            `<option value="${s.id}">${this.escapeHtml(s.nama_mapel)} (${this.escapeHtml(s.kode_mapel)})</option>`
        ).join('');

        EModal.form({
            title: 'Tambah Penugasan Mengajar',
            size: 'md',
            form: `
                <div class="form-group-acad">
                    <label class="form-label-acad">Pilih Guru Pengajar</label>
                    <select class="form-select-acad" id="formMengajarGuru">
                        <option value="">Pilih Guru...</option>
                        ${teacherOptions}
                    </select>
                </div>
                <div class="form-group-acad">
                    <label class="form-label-acad">Mata Pelajaran</label>
                    <select class="form-select-acad" id="formMengajarMapel">
                        <option value="">Pilih Mata Pelajaran...</option>
                        ${subjectOptions}
                    </select>
                </div>
                <div class="form-group-acad">
                    <label class="form-label-acad">Kelas / Rombel</label>
                    <select class="form-select-acad" id="formMengajarKelas">
                        <option value="">Pilih Rombel Kelas...</option>
                        ${classOptions}
                    </select>
                </div>
            `,
            confirmText: 'Tambah Penugasan',
            onConfirm: () => {
                const guru = $('#formMengajarGuru').val();
                const mapel = $('#formMengajarMapel').val();
                const kelas = $('#formMengajarKelas').val();

                if (!guru || !mapel || !kelas) {
                    EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Semua kolom wajib ditentukan.' });
                    return false;
                }

                this.api('mengajar.php?action=create', {
                    method: 'POST',
                    data: { guru_id: parseInt(guru, 10), mapel_id: parseInt(mapel, 10), kelas_id: parseInt(kelas, 10) }
                }).done(res => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                    this.loadMengajarTable();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menambahkan penugasan.' });
                });

                return false;
            }
        });
    },

    deleteMengajar(id, teacher, mapel, kelas) {
        EModal.confirm({
            title: 'Hapus Penugasan Mengajar',
            message: `Apakah Anda yakin ingin membatalkan penugasan mengajar guru <strong>${teacher}</strong> untuk pelajaran <strong>${mapel}</strong> di kelas <strong>${kelas}</strong>?`,
            type: 'danger',
            confirmText: 'Hapus Penugasan',
            onConfirm: () => {
                this.api('mengajar.php?action=delete', {
                    method: 'POST',
                    data: { id }
                }).done(res => {
                    EModal.toast({ type: 'success', title: 'Terhapus', message: res.message });
                    this.loadMengajarTable();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal membatalkan penugasan.' });
                });
            }
        });
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
        // Return back to main portal dashboard
        window.location.href = this.baseUrl + '#/dashboard';
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
    }
};

$(document).ready(() => Academic.init());
