/**
 * E-Graduation SPA.
 */
const Graduation = {
    state: {
        currentRoute: 'dashboard',
        user: window.GRADUATION_CONFIG.user,
        school: window.GRADUATION_CONFIG.school,
        token: window.GRADUATION_CONFIG.token,
        academicYear: window.GRADUATION_CONFIG.academicYear,
        baseUrl: window.GRADUATION_CONFIG.baseUrl,
        apiUrl: window.GRADUATION_CONFIG.baseUrl + 'modules/e-graduation/api/',
        groups: [],
        classes: [],
        letterSetup: null,
        letterRows: [],
        reportMeta: { classes: [], students: [] },
        accessData: { accesses: [], teachers: [], classes: [] },
        scoreMeta: { classes: [], subjects: [], students: [], scores: {} },
        accountMeta: { classes: [], settings: {} },
        subjectSearch: '',
        subjectGroupFilter: ''
    },

    init() {
        this.bindEvents();
        this.renderSidebar();
        this.loadRouteFromHash();

        setTimeout(() => {
            $('#globalLoader').addClass('hide');
            setTimeout(() => $('#globalLoader').remove(), 500);
        }, 600);
    },

    bindEvents() {
        window.addEventListener('hashchange', () => this.loadRouteFromHash());
        $('#menuToggle').on('click', () => this.toggleSidebar());
        $('#sidebarOverlay').on('click', () => this.toggleSidebar(false));
    },

    renderSidebar() {
        const u = this.state.user;
        const isAdmin = !!u.can_manage_graduation;
        const adminMenus = isAdmin ? `
                <button class="sp-nav-item" data-route="subjects">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 7h8"/><path d="M8 11h8"/></svg>
                    Input Mapel
                </button>
                <button class="sp-nav-item" data-route="letters">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 14h6"/><path d="M9 18h6"/><path d="M9 10h1"/></svg>
                    Input No Surat
                </button>
                <button class="sp-nav-item" data-route="access">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>
                    Akses Modul
                </button>
                <button class="sp-nav-item" data-route="accounts">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10"/><path d="M7 12h5"/><path d="M7 16h8"/></svg>
                    Data Siswa
                </button>
        ` : '';
        const teacherMenus = !isAdmin ? `
                <button class="sp-nav-item" data-route="scores">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v18H3z"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h4"/></svg>
                    Input Nilai
                </button>
        ` : '';
        const resetMenu = isAdmin ? `
            <div class="sp-nav-group" style="margin-top:auto; border-top:1px solid #e2e8f0; padding-top:8px;">
                <button class="sp-nav-item" data-route="reset" style="color:#ef4444">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                    Reset Data
                </button>
            </div>
        ` : '';

        $('#sidebarNav').html(`
            <div class="sp-nav-group">
                <div class="sp-nav-label">Menu Utama</div>
                <button class="sp-nav-item" data-route="dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Dashboard
                </button>
                ${adminMenus}
                ${teacherMenus}
                <button class="sp-nav-item" data-route="reports">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13h8"/><path d="M8 17h5"/><path d="M10 9H8"/></svg>
                    Laporan
                </button>
            </div>
            ${resetMenu}
        `);

        $('.sp-nav-item[data-route]').on('click', (e) => {
            this.navigate($(e.currentTarget).data('route'));
        });

        $('#sidebarAvatar').text(this.getInitials(u.nama_lengkap));
        $('#sidebarUserName').text(u.nama_lengkap);
        $('#sidebarUserRole').text(u.can_manage_graduation ? 'Admin Graduation' : (u.graduation_role === 'wali_kelas' ? 'Wali Kelas' : 'Guru'));
    },

    navigate(route) {
        window.location.hash = `#/${route}`;
    },

    loadRouteFromHash() {
        const route = (window.location.hash || '#/dashboard').replace('#/', '') || 'dashboard';
        this.state.currentRoute = route;
        $('.sp-nav-item').removeClass('active');
        $(`.sp-nav-item[data-route="${route}"]`).addClass('active');
        this.toggleSidebar(false);
        this.renderPage(route);
    },

    renderPage(route) {
        const $content = $('#mainContent');
        const $title = $('#pageTitle');
        const adminOnly = ['subjects', 'letters', 'access', 'accounts', 'reset'];
        if (!this.state.user.can_manage_graduation && adminOnly.includes(route)) {
            this.navigate('scores');
            return;
        }

        switch (route) {
            case 'subjects':
                $title.text('Input Mapel');
                this.setBreadcrumbs([{ label: 'Input Mapel' }]);
                this.renderSubjects($content);
                break;
            case 'letters':
                $title.text('Input No Surat');
                this.setBreadcrumbs([{ label: 'Input No Surat' }]);
                this.renderLetters($content);
                break;
            case 'reports':
                $title.text('Laporan');
                this.setBreadcrumbs([{ label: 'Laporan' }]);
                this.renderReports($content);
                break;
            case 'access':
                $title.text('Akses Modul');
                this.setBreadcrumbs([{ label: 'Akses Modul' }]);
                this.renderAccess($content);
                break;
            case 'accounts':
                $title.text('Data Siswa');
                this.setBreadcrumbs([{ label: 'Data Siswa' }]);
                this.renderStudentAccounts($content);
                break;
            case 'scores':
                $title.text('Input Nilai');
                this.setBreadcrumbs([{ label: 'Input Nilai' }]);
                this.renderScores($content);
                break;
            case 'reset':
                $title.text('Reset Data');
                this.setBreadcrumbs([{ label: 'Reset Data' }]);
                this.renderReset($content);
                break;
            case 'dashboard':
            default:
                $title.text('Dashboard');
                this.setBreadcrumbs([]);
                this.renderDashboard($content);
                break;
        }
    },

    setBreadcrumbs(items) {
        if (!items.length) {
            $('#breadcrumb').html(`<span class="grad-year-pill">Tahun aktif: ${this.activeYearLabel()}</span>`);
            return;
        }

        const html = [
            `<a href="#/dashboard">Dashboard</a>`
        ].concat(items.map(item => `<span class="sp-sep">/</span><span class="active">${this.escapeHtml(item.label)}</span>`)).join('');
        $('#breadcrumb').html(html);
    },

    toggleSidebar(show = null) {
        if (show === null) {
            $('#gradSidebar').toggleClass('show');
            $('#sidebarOverlay').toggleClass('show');
        } else if (show) {
            $('#gradSidebar').addClass('show');
            $('#sidebarOverlay').addClass('show');
        } else {
            $('#gradSidebar').removeClass('show');
            $('#sidebarOverlay').removeClass('show');
        }
    },

    doLogout() {
        EModal.confirm({
            title: 'Logout',
            message: 'Yakin ingin keluar dari E-Graduation?',
            type: 'danger',
            confirmText: 'Ya, Logout',
            onConfirm: () => {
                $.ajax({
                    url: this.state.baseUrl + 'api/auth.php?action=logout',
                    method: 'POST',
                    headers: { Authorization: 'Bearer ' + this.state.token },
                    complete: () => {
                        sessionStorage.setItem('eportal_intended_module', 'modules/e-graduation/');
                        localStorage.removeItem('auth_token');
                        localStorage.removeItem('user');
                        window.location.href = this.state.baseUrl + '#/dashboard';
                    }
                });
            }
        });
    },

    renderDashboard($container) {
        $container.html(`
            <div class="sp-stats-grid">
                <div class="sp-stat-card skeleton-stat"></div>
                <div class="sp-stat-card skeleton-stat"></div>
                <div class="sp-stat-card skeleton-stat"></div>
                <div class="sp-stat-card skeleton-stat"></div>
            </div>
            <div class="sp-dashboard-grid">
                <div class="sp-card full grad-dashboard-note">
                    <div class="sp-card-header">
                        <h3>Ringkasan E-Graduation</h3>
                        <span class="grad-year-pill">${this.activeYearLabel()}</span>
                    </div>
                    <div class="sp-card-body" id="graduationOverview"><div class="skeleton" style="height:160px"></div></div>
                </div>
                <div class="sp-card">
                    <div class="sp-card-header"><h3>Mapel Per Kelompok</h3></div>
                    <div class="sp-card-body" id="groupSummary"><div class="skeleton" style="height:220px"></div></div>
                </div>
                <div class="sp-card">
                    <div class="sp-card-header"><h3>Data Kelas Siswa</h3></div>
                    <div class="sp-card-body" id="classSummary"><div class="skeleton" style="height:220px"></div></div>
                </div>
            </div>
        `);

        this.api('dashboard.php?action=stats').done(res => {
            if (!res.success) return;
            const s = res.data;
            $container.find('.sp-stats-grid').html(`
                ${this.statCard('Siswa Aktif', s.total_siswa, '#E3F2FD', '#1565C0', 'students')}
                ${this.statCard('Kelas', s.total_kelas, '#E8F5E9', '#0F766E', 'classes')}
                ${this.statCard('Kelompok Mapel', s.total_kelompok, '#FFF7ED', '#EA580C', 'groups')}
                ${this.statCard('No Surat', s.total_no_surat || 0, '#F3E8FF', '#7C3AED', 'letters')}
            `);

            $('#graduationOverview').html(`
                <div class="grad-mini-list">
                    <div class="grad-mini-item">
                        <div>
                            <strong>Fondasi modul sudah aktif</strong>
                            <div class="grad-muted">Input Mapel memakai tahun ajaran aktif dan kelas dari data siswa.</div>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="Graduation.navigate('subjects')">Kelola Mapel</button>
                    </div>
                    <div class="grad-mini-item">
                        <div>
                            <strong>Alur mapel pilihan</strong>
                            <div class="grad-muted">Saat kelompok bertipe pilihan dipilih, field kelas akan muncul otomatis.</div>
                        </div>
                        <span class="badge badge-success">Siap dipakai</span>
                    </div>
                    <div class="grad-mini-item">
                        <div>
                            <strong>Nomor surat kelulusan</strong>
                            <div class="grad-muted">${s.letter_setting ? `${s.letter_setting.total} nomor disiapkan, mulai ${s.letter_setting.start_number}` : 'Belum disiapkan untuk tahun ajaran aktif.'}</div>
                        </div>
                        <button class="btn btn-accent btn-sm" onclick="Graduation.navigate('letters')">Input No Surat</button>
                    </div>
                </div>
            `);

            $('#groupSummary').html(this.renderGroupSummary(s.per_kelompok));
            $('#classSummary').html(this.renderClassSummary(s.kelas));
        }).fail(xhr => this.showAjaxError(xhr));
    },

    statCard(title, value, bg, color, type) {
        const icons = {
            students: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            classes: '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/>',
            groups: '<path d="M4 4h16v16H4z"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/>',
            subjects: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            letters: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 14h6"/><path d="M9 18h6"/>'
        };

        return `
            <div class="sp-stat-card sp-fade-in">
                <div class="sp-stat-icon" style="background:${bg}; color:${color}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${icons[type]}</svg>
                </div>
                <div class="sp-stat-info">
                    <h4>${this.formatNumber(value)}</h4>
                    <p>${title}</p>
                </div>
            </div>
        `;
    },

    renderLetters($container) {
        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header">
                    <div>
                        <h3>Input Nomor Surat Kelulusan</h3>
                        <div class="grad-muted">Nomor akan digenerate otomatis untuk siswa pada tahun ajaran aktif.</div>
                    </div>
                    <span class="grad-year-pill">${this.activeYearLabel()}</span>
                </div>
                <div class="sp-card-body" id="letterSetupBody">
                    <div class="skeleton" style="height:320px"></div>
                </div>
            </div>
            <div class="sp-card">
                <div class="sp-card-header">
                    <div>
                        <h3>Daftar Nomor Surat Siswa</h3>
                        <div class="grad-muted">Preview hasil nomor surat setelah digenerate.</div>
                    </div>
                    <div class="sp-toolbar">
                        <button class="btn btn-outline btn-sm" onclick="Graduation.loadLetterRows()">Refresh</button>
                    </div>
                </div>
                <div class="sp-card-body">
                    <div class="sp-table-wrapper" id="letterRowsTable"><div class="skeleton" style="height:260px"></div></div>
                </div>
            </div>
        `);

        $.when(this.loadLetterSetup(), this.loadLetterRows());
    },

    loadLetterSetup() {
        return this.api('letters.php?action=get').done(res => {
            this.state.letterSetup = res.data;
            this.renderLetterSetupForm(res.data);
        }).fail(xhr => this.showAjaxError(xhr));
    },

    renderLetterSetupForm(data) {
        const setting = data.setting || {};
        const teachers = data.teachers || [];
        const teacherOptions = teachers.map(t => `
            <option value="${t.id}" data-name="${this.escapeAttr(t.nama_lengkap)}" data-niy="${this.escapeAttr(t.username)}" data-jabatan="${this.escapeAttr(t.jabatan || '')}">
                ${this.escapeHtml(t.nama_lengkap)} - ${this.escapeHtml(t.username || '-')} ${t.jabatan ? `(${this.escapeHtml(t.jabatan)})` : ''}
            </option>
        `).join('');

        $('#letterSetupBody').html(`
            <div class="grad-letter-summary">
                <div class="grad-letter-summary-item">
                    <span>Jumlah Siswa</span>
                    <strong>${this.formatNumber(data.total_students)}</strong>
                </div>
                <div class="grad-letter-summary-item">
                    <span>Nomor Tergenerate</span>
                    <strong>${this.formatNumber(data.generated_total)}</strong>
                </div>
                <div class="grad-letter-summary-item">
                    <span>Format Aktif</span>
                    <strong>${this.escapeHtml(setting.letter_format || '-')}</strong>
                </div>
                <div class="grad-letter-summary-item">
                    <span>Kop SKL</span>
                    <strong>${setting.kop_image ? 'Sudah diupload' : 'Belum diupload'}</strong>
                </div>
            </div>
            <form id="letterSetupForm" onsubmit="return false;">
                <div class="sp-form-row three">
                    <div class="form-group">
                        <label class="form-label">Dimulai Nomor</label>
                        <input class="form-input" type="number" id="letterStartNumber" min="1" value="${setting.start_number || 1}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total</label>
                        <div class="grad-input-action">
                            <input class="form-input" type="number" id="letterTotal" min="1" max="${data.total_students}" value="${setting.total || data.total_students || 0}">
                            <button class="btn btn-ghost btn-sm" type="button" onclick="$('#letterTotal').val(${data.total_students})">Semua</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preview Nomor Awal</label>
                        <input class="form-input" id="letterPreview" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Format Nomor Surat</label>
                    <input class="form-input" id="letterFormat" value="${this.escapeAttr(setting.letter_format || '')}" placeholder="{nomor} / I04.1/SMA.WH1/V/2026">
                    <div class="grad-muted" style="margin-top:6px">Gunakan <strong>{nomor}</strong> sebagai posisi nomor otomatis. Contoh: {nomor} / I04.1/SMA.WH1/V/2026</div>
                </div>
                <div class="sp-form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal Kelulusan</label>
                        <input class="form-input" type="date" id="graduationDate" value="${setting.graduation_date || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Tanda Tangan</label>
                        <input class="form-input" type="date" id="signingDate" value="${setting.signing_date || ''}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kepala Sekolah dari Data Guru</label>
                    <select class="form-select" id="headmasterUser">
                        <option value="">Pilih Kepala Sekolah</option>
                        ${teacherOptions}
                    </select>
                    <div class="grad-muted" style="margin-top:6px">Nama dan NIY otomatis diambil dari master Data Guru. NIY memakai nomor identitas/username guru.</div>
                </div>
                <div class="sp-form-row three">
                    <div class="form-group">
                        <label class="form-label">Nama Kepala Sekolah</label>
                        <input class="form-input" id="headmasterName" value="${this.escapeAttr(setting.headmaster_name || '')}" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">NIY</label>
                        <input class="form-input" id="headmasterNiy" value="${this.escapeAttr(setting.headmaster_niy || '')}" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jabatan</label>
                        <input class="form-input" id="headmasterPosition" value="${this.escapeAttr(setting.headmaster_position || 'Kepala Sekolah')}">
                    </div>
                </div>
                <div class="grad-skl-format-box">
                    <div>
                        <strong>Format SKL</strong>
                        <div class="grad-muted">Upload gambar kop surat dan lengkapi data keputusan untuk cetak SKL.</div>
                    </div>
                    <div class="grad-skl-preview">
                        ${setting.kop_image ? `<img src="${this.state.baseUrl}${this.escapeAttr(setting.kop_image)}" alt="Kop SKL">` : '<span>Belum ada kop</span>'}
                    </div>
                    <button class="btn btn-outline btn-sm" type="button" onclick="Graduation.openSklSettings()">Pengaturan Format SKL</button>
                </div>
                <div class="grad-letter-actions">
                    <button class="btn btn-primary" id="saveLetterBtn" onclick="Graduation.saveLetterSetup()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Simpan & Generate
                    </button>
                </div>
            </form>
        `);

        $('#headmasterUser').val(setting.headmaster_user_id || '');
        $('#headmasterUser').on('change', () => this.fillHeadmasterFields());
        $('#letterStartNumber, #letterFormat').on('input', () => this.updateLetterPreview());
        if (!setting.headmaster_name) this.fillHeadmasterFields();
        this.updateLetterPreview();
    },

    fillHeadmasterFields() {
        const $selected = $('#headmasterUser option:selected');
        const name = $selected.data('name') || '';
        const niy = $selected.data('niy') || '';
        const jabatan = $selected.data('jabatan') || '';
        $('#headmasterName').val(name);
        $('#headmasterNiy').val(niy);
        $('#headmasterPosition').val(String(jabatan).toLowerCase().includes('kepala') ? jabatan : 'Kepala Sekolah');
    },

    updateLetterPreview() {
        const number = $('#letterStartNumber').val() || '1';
        const format = $('#letterFormat').val() || '{nomor} / I04.1/SMA.WH1/V/2026';
        $('#letterPreview').val(this.formatLetterNumber(format, number));
    },

    saveLetterSetup() {
        const btn = document.getElementById('saveLetterBtn');
        const payload = {
            start_number: $('#letterStartNumber').val(),
            total: $('#letterTotal').val(),
            letter_format: $('#letterFormat').val(),
            graduation_date: $('#graduationDate').val(),
            signing_date: $('#signingDate').val(),
            headmaster_user_id: $('#headmasterUser').val(),
            headmaster_position: $('#headmasterPosition').val()
        };

        if (!payload.headmaster_user_id) {
            EModal.toast({ type: 'warning', title: 'Kepala sekolah belum dipilih', message: 'Pilih kepala sekolah dari Data Guru terlebih dahulu.' });
            return;
        }

        EModal.btnLoading(btn, true);
        this.api('letters.php?action=save-generate', { method: 'POST', data: payload }).done(res => {
            EModal.toast({ type: 'success', title: 'Berhasil', message: `${res.data.generated_total} nomor surat berhasil digenerate.` });
            this.loadLetterSetup();
            this.loadLetterRows();
        }).fail(xhr => this.showAjaxError(xhr)).always(() => EModal.btnLoading(btn, false));
    },

    openSklSettings() {
        const setting = this.state.letterSetup?.setting || {};
        EModal.form({
            title: 'Pengaturan Format SKL',
            size: 'lg',
            form: `
                <div class="grad-skl-modal-preview">
                    ${setting.kop_image ? `<img src="${this.state.baseUrl}${this.escapeAttr(setting.kop_image)}" alt="Kop SKL">` : '<div class="grad-muted">Belum ada kop surat. Upload gambar kop sesuai format sekolah.</div>'}
                </div>
                <div class="form-group">
                    <label class="form-label">Upload Gambar Kop Surat</label>
                    <input class="form-input" type="file" id="sklKopImage" accept="image/png,image/jpeg,image/webp">
                    <div class="grad-muted" style="margin-top:6px">Gunakan gambar kop penuh seperti contoh SKL. Format: JPG, PNG, atau WebP.</div>
                </div>
                <div class="sp-form-row">
                    <div class="form-group">
                        <label class="form-label">Nomor Keputusan Kepala Sekolah</label>
                        <input class="form-input" id="sklDecisionNumber" value="${this.escapeAttr(setting.decision_number || '')}" placeholder="Contoh: 421.3/....">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Keputusan</label>
                        <input class="form-input" type="date" id="sklDecisionDate" value="${setting.decision_date || ''}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tentang</label>
                    <input class="form-input" id="sklDecisionAbout" value="${this.escapeAttr(setting.decision_about || '')}" placeholder="Kelulusan Peserta Didik Tahun Pelajaran 2025/2026">
                </div>
                <div class="form-group">
                    <label class="form-label">Kota Tanda Tangan</label>
                    <input class="form-input" id="sklCity" value="${this.escapeAttr(setting.skl_city || '')}" placeholder="Kota Surabaya">
                </div>
                <div class="sp-form-row">
                    <div class="form-group">
                        <label class="form-label">NPSN Sekolah (untuk teks pembuka SKL)</label>
                        <input class="form-input" id="sklNpsn" value="${this.escapeAttr(setting.skl_npsn || '')}" placeholder="Contoh: 20532108">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Provinsi (untuk teks pembuka SKL)</label>
                        <input class="form-input" id="sklProvince" value="${this.escapeAttr(setting.skl_province || '')}" placeholder="Contoh: Jawa Timur">
                    </div>
                </div>
            `,
            confirmText: 'Simpan Format SKL',
            onConfirm: () => {
                const fd = new FormData();
                const file = document.getElementById('sklKopImage').files[0];
                if (file) fd.append('kop_image', file);
                fd.append('decision_number', $('#sklDecisionNumber').val());
                fd.append('decision_date', $('#sklDecisionDate').val());
                fd.append('decision_about', $('#sklDecisionAbout').val());
                fd.append('skl_city', $('#sklCity').val());
                fd.append('skl_npsn', $('#sklNpsn').val());
                fd.append('skl_province', $('#sklProvince').val());

                this.api('letters.php?action=save-skl-settings', { method: 'POST', data: fd }).done(() => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: 'Format SKL disimpan.' });
                    this.loadLetterSetup();
                }).fail(xhr => this.showAjaxError(xhr));
                return false;
            }
        });
    },

    loadLetterRows() {
        return this.api('letters.php?action=list').done(res => {
            this.state.letterRows = res.data || [];
            this.renderLetterRowsTable(this.state.letterRows);
        }).fail(xhr => this.showAjaxError(xhr));
    },

    renderLetterRowsTable(rows) {
        if (!rows.length) {
            $('#letterRowsTable').html(`
                <div class="sp-empty">
                    <h3>Belum Ada Data Siswa</h3>
                    <p>Tambahkan Data Siswa pada tahun ajaran aktif sebelum generate nomor surat.</p>
                </div>
            `);
            return;
        }

        const html = rows.map((row, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>
                    <strong>${this.escapeHtml(row.nama)}</strong>
                    <div class="grad-muted">${this.escapeHtml(row.nis || '-')} / ${this.escapeHtml(row.nisn || '-')}</div>
                </td>
                <td><span class="badge badge-success">${this.escapeHtml(row.kelas || '-')}</span></td>
                <td>${row.letter_number ? `<strong>${this.escapeHtml(row.letter_number)}</strong>` : '<span class="badge badge-warning">Belum generate</span>'}</td>
                <td>${row.graduation_date ? this.formatDate(row.graduation_date) : '-'}</td>
                <td>${row.headmaster_name ? `${this.escapeHtml(row.headmaster_name)}<div class="grad-muted">${this.escapeHtml(row.headmaster_niy || '-')}</div>` : '-'}</td>
            </tr>
        `).join('');

        $('#letterRowsTable').html(`
            <table class="sp-table">
                <thead><tr><th>No</th><th>Siswa</th><th>Kelas</th><th>Nomor Surat</th><th>Tgl Kelulusan</th><th>Kepala Sekolah</th></tr></thead>
                <tbody>${html}</tbody>
            </table>
        `);
    },

    renderAccess($container) {
        if (!this.state.user.can_manage_graduation) {
            $container.html('<div class="sp-empty"><h3>Akses Ditolak</h3><p>Hanya admin yang dapat mengatur akses guru.</p></div>');
            return;
        }
        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header">
                    <div>
                        <h3>Akses Modul</h3>
                        <div class="grad-muted">Hanya guru yang ditambahkan di sini yang bisa akses modul E-Graduation.</div>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="Graduation.openAccessForm()">Tambah Akses</button>
                </div>
                <div class="sp-card-body">
                    <div class="sp-table-wrapper" id="accessTable"><div class="skeleton" style="height:260px"></div></div>
                </div>
            </div>
        `);
        this.loadAccess();
    },

    loadAccess() {
        return this.api('access.php?action=list').done(res => {
            this.state.accessData = res.data || { accesses: [], teachers: [], classes: [] };
            this.renderAccessTable();
        }).fail(xhr => this.showAjaxError(xhr));
    },

    renderAccessTable() {
        const accesses = this.state.accessData.accesses || [];
        if (!accesses.length) {
            $('#accessTable').html('<div class="sp-empty"><h3>Belum Ada Akses Guru</h3><p>Klik "Tambah Akses", cari guru, pilih kelas, lalu simpan.</p></div>');
            return;
        }

        const rows = accesses.map(t => `
            <tr>
                <td>
                    <strong>${this.escapeHtml(t.nama_lengkap)}</strong>
                    <div class="grad-muted">${this.escapeHtml(t.jabatan || '-')}</div>
                </td>
                <td><code class="sp-code">${this.escapeHtml(t.username || '-')}</code></td>
                <td>${t.kelas ? `<span class="badge badge-success">${this.escapeHtml(t.kelas)}</span>` : '<span class="badge badge-warning">Belum diatur</span>'}</td>
                <td><span class="badge ${t.access_status == 1 ? 'badge-primary' : 'badge-danger'}">${t.access_status == 1 ? 'Aktif' : (t.access_id ? 'Nonaktif' : 'Belum Ada')}</span></td>
                <td>
                    <div class="sp-actions">
                        <button class="sp-btn-icon" title="Atur Akses" onclick="Graduation.openAccessForm(${t.user_id})">${this.iconEdit()}</button>
                        ${t.access_id ? `<button class="sp-btn-icon danger" title="Hapus Akses" onclick="Graduation.deleteAccess(${t.user_id}, this.dataset.name)" data-name="${this.escapeAttr(t.nama_lengkap)}">${this.iconDelete()}</button>` : ''}
                    </div>
                </td>
            </tr>
        `).join('');

        $('#accessTable').html(`<table class="sp-table"><thead><tr><th>Guru</th><th>Username</th><th>Kelas Wali</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
    },

    openAccessForm(userId = null) {
        const accesses = this.state.accessData.accesses || [];
        const teachers = this.state.accessData.teachers || [];
        const classes = this.state.accessData.classes || [];
        const existing = accesses.find(t => String(t.user_id) === String(userId)) || {};
        const usedTeacherIds = new Set(accesses.map(t => String(t.user_id)));
        const teacherChoices = userId
            ? teachers.filter(t => String(t.id) === String(userId))
            : teachers.filter(t => !usedTeacherIds.has(String(t.id)));
        const classOptions = classes.map(k => `<option value="${this.escapeAttr(k.kelas)}">${this.escapeHtml(k.kelas)} (${k.total_siswa} siswa)</option>`).join('');
        if (!teacherChoices.length && !userId) {
            EModal.toast({ type: 'info', title: 'Semua guru sudah dipilih', message: 'Tidak ada guru tersisa untuk ditambahkan.' });
            return;
        }
        const teacherOptionItems = teacherChoices.map(t => `
            <div class="sp-cs-option grad-access-teacher-option"
                 data-user-id="${t.id}"
                 data-name="${this.escapeAttr(t.nama_lengkap || '')}"
                 data-username="${this.escapeAttr(t.username || '')}"
                 data-jabatan="${this.escapeAttr(t.jabatan || '')}">
                <div class="cs-opt-m">${this.escapeHtml(t.nama_lengkap || '-')}</div>
                <div class="cs-opt-k">${this.escapeHtml(t.username || '-')} | ${this.escapeHtml(t.jabatan || '-')}</div>
            </div>
        `).join('');

        EModal.form({
            title: userId ? 'Edit Akses Guru' : 'Tambah Akses Guru',
            size: 'md',
            form: `
                <style>
                    .grad-access-cs-container { position:relative; user-select:none; }
                    .grad-access-cs-btn {
                        cursor:pointer; display:flex; justify-content:space-between; align-items:center;
                        background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:10px 15px; height:48px; transition:all .2s;
                    }
                    .grad-access-cs-btn:hover { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
                    .grad-access-cs-btn.active { border-color:#3b82f6; }
                    .grad-access-cs-btn.disabled { opacity:.7; cursor:not-allowed; background:#f8fafc; }
                    .grad-access-cs-dropdown {
                        display:none; position:absolute; top:calc(100% + 5px); left:0; right:0;
                        background:#fff; border:1px solid #cbd5e1; border-radius:10px;
                        box-shadow:0 10px 25px rgba(0,0,0,.1); z-index:9999; overflow:hidden;
                    }
                    .grad-access-cs-search { padding:12px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
                    .grad-access-cs-search input {
                        width:100%; padding:10px 14px; height:42px; border-radius:8px; border:1px solid #cbd5e1; outline:none;
                    }
                    .grad-access-cs-search input:focus { border-color:#3b82f6; }
                    .grad-access-empty {
                        display:none; padding:12px 15px; font-size:.85rem; color:#64748b; text-align:center; border-top:1px solid #f1f5f9;
                    }
                </style>
                <input type="hidden" id="accessUser" value="">
                <div class="form-group">
                    <label class="form-label">Guru</label>
                    <div class="grad-access-cs-container" id="accessTeacherSelectContainer">
                        <div class="grad-access-cs-btn ${userId ? 'disabled' : ''}" id="accessTeacherSelectBtn">
                            <span id="accessTeacherSelectedText" style="color:#64748b; font-size:.95rem;">-- Pilih Guru --</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <div class="grad-access-cs-dropdown" id="accessTeacherDropdown">
                            <div class="grad-access-cs-search">
                                <input type="text" id="accessTeacherSearch" placeholder="Ketik nama guru..." autocomplete="off">
                            </div>
                            <div id="accessTeacherOptions" style="max-height:250px; overflow-y:auto; padding:5px 0;">
                                ${teacherOptionItems || '<div class="grad-access-empty" style="display:block;">Data guru tidak tersedia.</div>'}
                            </div>
                            <div class="grad-access-empty" id="accessTeacherEmpty">Tidak ada guru yang cocok.</div>
                        </div>
                    </div>
                </div>
                <div class="sp-form-row">
                    <div class="form-group">
                        <label class="form-label">Kelas Wali</label>
                        <select class="form-select" id="accessClass">
                            <option value="">Pilih kelas</option>
                            ${classOptions}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="accessStatus">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="grad-muted">Guru yang dipilih akan diberi akses ke Input Nilai dan Laporan sesuai kelas wali.</div>
            `,
            confirmText: 'Simpan Akses',
            onOpen: () => {
                const setSelectedTeacher = (id) => {
                    const selected = teacherChoices.find(t => String(t.id) === String(id));
                    if (selected) {
                        $('#accessUser').val(String(selected.id));
                        $('#accessTeacherSelectedText').text(`${selected.nama_lengkap} - ${selected.username || '-'}`).css('color', '#1e293b');
                        return;
                    }
                    $('#accessUser').val('');
                    $('#accessTeacherSelectedText').text('-- Pilih Guru --').css('color', '#64748b');
                };

                const filterTeacherOptions = () => {
                    const term = String($('#accessTeacherSearch').val() || '').trim().toLowerCase();
                    let visible = 0;
                    $('#accessTeacherOptions .grad-access-teacher-option').each(function() {
                        const name = String($(this).data('name') || '').toLowerCase();
                        const username = String($(this).data('username') || '').toLowerCase();
                        const jabatan = String($(this).data('jabatan') || '').toLowerCase();
                        const show = !term || name.includes(term) || username.includes(term) || jabatan.includes(term);
                        $(this).toggle(show);
                        if (show) visible++;
                    });
                    $('#accessTeacherEmpty').toggle(visible === 0);
                };

                setSelectedTeacher(existing.user_id || '');
                $('#accessClass').val(existing.kelas || '');
                $('#accessStatus').val(existing.access_status == 0 ? '0' : '1');
                $(document).off('click.gradAccessTeacher');

                if (!userId) {
                    $('#accessTeacherSelectBtn').on('click', (e) => {
                        e.stopPropagation();
                        $('#accessTeacherSelectBtn').toggleClass('active');
                        $('#accessTeacherDropdown').toggle();
                        if ($('#accessTeacherDropdown').is(':visible')) {
                            $('#accessTeacherSearch').val('');
                            filterTeacherOptions();
                            $('#accessTeacherSearch').trigger('focus');
                        }
                    });

                    $('#accessTeacherSearch').on('input', filterTeacherOptions);

                    $('#accessTeacherOptions').on('click', '.grad-access-teacher-option', function() {
                        const selectedUserId = $(this).data('user-id');
                        const selectedTeacher = teacherChoices.find(t => String(t.id) === String(selectedUserId)) || {};
                        const selectedName = selectedTeacher.nama_lengkap || '';
                        const selectedUsername = selectedTeacher.username || '-';
                        $('#accessUser').val(String(selectedUserId));
                        $('#accessTeacherSelectedText').text(`${selectedName} - ${selectedUsername}`).css('color', '#1e293b');
                        $('#accessTeacherDropdown').hide();
                        $('#accessTeacherSelectBtn').removeClass('active');

                        const selectedAccess = accesses.find(t => String(t.user_id) === String(selectedUserId)) || {};
                        if (selectedAccess.kelas) {
                            $('#accessClass').val(selectedAccess.kelas);
                        }
                    });
                }

                $(document).on('click.gradAccessTeacher', (e) => {
                    if (!$(e.target).closest('#accessTeacherSelectContainer').length) {
                        $('#accessTeacherDropdown').hide();
                        $('#accessTeacherSelectBtn').removeClass('active');
                    }
                });
            },
            onConfirm: () => {
                const data = {
                    user_id: $('#accessUser').val(),
                    kelas: $('#accessClass').val(),
                    status: $('#accessStatus').val()
                };
                if (!data.user_id || !data.kelas) {
                    EModal.toast({ type: 'warning', title: 'Lengkapi data', message: 'Guru dan kelas wajib diisi.' });
                    return false;
                }
                this.api('access.php?action=save', { method: 'POST', data }).done(() => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: 'Akses guru disimpan.' });
                    this.loadAccess();
                }).fail(xhr => this.showAjaxError(xhr));
                return false;
            }
        });
    },

    deleteAccess(userId, name) {
        EModal.confirm({
            title: 'Hapus Akses Guru',
            message: `Yakin hapus akses <strong>${this.escapeHtml(name)}</strong>?`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('access.php?action=delete', { method: 'POST', data: { user_id: userId } }).done(() => {
                    EModal.toast({ type: 'success', title: 'Dihapus', message: 'Akses guru dihapus.' });
                    this.loadAccess();
                }).fail(xhr => this.showAjaxError(xhr));
            }
        });
    },

    renderStudentAccounts($container) {
        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header">
                    <div>
                        <h3>Data Siswa E-Graduation</h3>
                        <div class="grad-muted">Sinkron data akun siswa dari Admin Portal ke login E-Graduation.</div>
                    </div>
                    <span class="grad-year-pill">${this.activeYearLabel()}</span>
                </div>
                <div class="sp-card-body" id="studentAccountBody"><div class="skeleton" style="height:260px"></div></div>
            </div>
        `);
        this.loadStudentAccountMeta();
    },

    loadStudentAccountMeta() {
        return this.api('student_accounts.php?action=meta').done(res => {
            this.state.accountMeta = res.data || { classes: [], settings: {} };
            this.renderStudentAccountBody();
        }).fail(xhr => this.showAjaxError(xhr));
    },

    renderStudentAccountBody() {
        const meta = this.state.accountMeta;
        const settings = meta.settings || {};
        const printClasses = (meta.account_classes && meta.account_classes.length) ? meta.account_classes : (meta.classes || []);
        const classOptions = printClasses.map(k => `<option value="${this.escapeAttr(k.kelas)}">${this.escapeHtml(k.kelas)} (${k.total_siswa} siswa)</option>`).join('');
        const announcementAt = settings.announcement_at ? String(settings.announcement_at).replace(' ', 'T').substring(0, 16) : '';

        $('#studentAccountBody').html(`
            <div class="grad-letter-summary">
                <div class="grad-letter-summary-item"><span>Jumlah Siswa</span><strong>${this.formatNumber(meta.total_students)}</strong></div>
                <div class="grad-letter-summary-item"><span>Akun Aktif</span><strong>${this.formatNumber(meta.total_accounts)}</strong></div>
                <div class="grad-letter-summary-item"><span>Portal Siswa</span><strong style="font-size:.8rem">${this.escapeHtml(meta.student_portal_url || '-')}</strong></div>
            </div>
            <div class="grad-muted" style="margin:8px 0 14px">Import mengambil data siswa dari Admin Portal, lalu sinkron ke login E-Graduation berdasarkan kelas yang dicentang.</div>
            <div class="grad-account-actions">
                <button class="btn btn-primary" onclick="Graduation.generateStudentAccounts()">Import Akun dari Admin Portal</button>
                <a class="btn btn-outline" href="${this.escapeAttr(meta.student_portal_url || '#')}" target="_blank">Buka Portal Siswa</a>
            </div>
            <hr class="grad-soft-line">
            <div class="sp-form-row">
                <div class="form-group">
                    <label class="form-label">Status Pengumuman</label>
                    <select class="form-select" id="announcementStatus">
                        <option value="not_set">Belum Ditetapkan</option>
                        <option value="scheduled">Counting Down / Terjadwal</option>
                        <option value="published">Selamat Lulus / Dibuka</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal & Jam Pengumuman</label>
                    <input class="form-input" type="datetime-local" id="announcementAt" value="${announcementAt}">
                </div>
            </div>
            <div class="grad-account-actions">
                <button class="btn btn-accent" onclick="Graduation.saveAnnouncementSettings()">Simpan Pengumuman</button>
            </div>
            <hr class="grad-soft-line">
            <div class="sp-form-row">
                <div class="form-group">
                    <label class="form-label">Mode Cetak Akun PDF</label>
                    <select class="form-select" id="accountPrintScope">
                        <option value="class">Per Kelas</option>
                        <option value="all">Keseluruhan</option>
                    </select>
                </div>
                <div class="form-group" id="accountPrintClassWrap">
                    <label class="form-label">Kelas</label>
                    <select class="form-select" id="accountPrintClass"><option value="">Pilih kelas</option>${classOptions}</select>
                </div>
            </div>
            <button class="btn btn-primary" onclick="Graduation.downloadStudentAccountsPdf()">Cetak PDF Akun Siswa</button>
        `);

        $('#announcementStatus').val(settings.announcement_status || 'not_set');
        $('#accountPrintScope').on('change', () => $('#accountPrintClassWrap').toggle($('#accountPrintScope').val() === 'class')).trigger('change');
    },

    generateStudentAccounts() {
        const classes = this.state.accountMeta?.classes || [];
        if (!classes.length) {
            EModal.toast({ type: 'warning', title: 'Data kelas kosong', message: 'Belum ada kelas siswa pada tahun ajaran aktif.' });
            return;
        }

        const pickerId = `gradImportClassPicker${Date.now()}`;
        const toggleId = `gradImportClassAll${Date.now()}`;
        const countId = `gradImportClassCount${Date.now()}`;
        const checkboxes = classes.map(k => `
            <label class="grad-check-item">
                <input type="checkbox" class="grad-generate-class-check" value="${this.escapeAttr(k.kelas)}">
                <span>${this.escapeHtml(k.kelas)} <small>(${this.formatNumber(k.total_siswa)} siswa)</small></span>
            </label>
        `).join('');

        EModal.form({
            title: 'Import Akun dari Admin Portal',
            size: 'md',
            form: `
                <div class="form-group" id="${pickerId}">
                    <label class="form-label">Pilih kelas yang akan diimport</label>
                    <div class="grad-account-class-tools">
                        <label class="grad-check-toggle">
                            <input type="checkbox" id="${toggleId}">
                            <span>Pilih Semua Kelas</span>
                        </label>
                        <span class="grad-muted" id="${countId}"></span>
                    </div>
                    <div class="grad-account-class-grid">
                        ${checkboxes}
                    </div>
                    <div class="grad-muted" style="margin-top:8px">Akun sumber: Admin Portal (username siswa = NIS).</div>
                </div>
            `,
            confirmText: 'Import Akun',
            onOpen: () => {
                const $root = $(`#${pickerId}`);
                const $checks = $root.find('.grad-generate-class-check');
                const $toggle = $(`#${toggleId}`);
                const $count = $(`#${countId}`);
                const sync = () => {
                    const total = $checks.length;
                    const checked = $checks.filter(':checked').length;
                    const allChecked = total > 0 && checked === total;
                    $count.text(`${checked}/${total} kelas dipilih`);
                    $toggle
                        .prop('checked', allChecked)
                        .prop('indeterminate', checked > 0 && checked < total);
                };

                $toggle.on('change', function() {
                    $checks.prop('checked', this.checked);
                    sync();
                });
                $checks.on('change', sync);
                sync();
            },
            onConfirm: () => {
                const $root = $(`#${pickerId}`);
                const selected = $root.find('.grad-generate-class-check:checked').map((_, el) => $(el).val()).get();
                if (!selected.length) {
                    EModal.toast({ type: 'warning', title: 'Pilih kelas', message: 'Centang minimal satu kelas terlebih dahulu.' });
                    return false;
                }

                this.api('student_accounts.php?action=import', {
                    method: 'POST',
                    data: {
                        classes_json: JSON.stringify(selected),
                        use_class_filter: 1
                    }
                }).done(res => {
                    const imported = res.data?.imported || 0;
                    const updated = res.data?.updated || 0;
                    const skipped = res.data?.skipped || 0;
                    const deactivated = res.data?.deactivated_outside_selection || 0;
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: `${imported} akun baru, ${updated} akun diperbarui, ${skipped} akun belum tersedia di Admin Portal, ${deactivated} akun di luar kelas terpilih dinonaktifkan.` });
                    this.loadStudentAccountMeta();
                }).fail(xhr => this.showAjaxError(xhr));
                return false;
            }
        });
    },

    saveAnnouncementSettings() {
        const data = {
            announcement_status: $('#announcementStatus').val(),
            announcement_at: $('#announcementAt').val()
        };
        this.api('student_accounts.php?action=settings-save', { method: 'POST', data }).done(() => {
            EModal.toast({ type: 'success', title: 'Berhasil', message: 'Pengaturan pengumuman disimpan.' });
            this.loadStudentAccountMeta();
        }).fail(xhr => this.showAjaxError(xhr));
    },

    downloadStudentAccountsPdf() {
        const scope = $('#accountPrintScope').val();
        const params = new URLSearchParams({ action: 'download-pdf', scope, token: this.state.token });
        if (scope === 'class') {
            const kelas = $('#accountPrintClass').val();
            if (!kelas) {
                EModal.toast({ type: 'warning', title: 'Pilih kelas', message: 'Pilih kelas terlebih dahulu.' });
                return;
            }
            params.set('kelas', kelas);
        }
        window.open(`${this.state.apiUrl}student_accounts.php?${params.toString()}`, '_blank');
    },

    renderScores($container) {
        $container.html(`
            <div class="sp-card grad-score-card">
                <div class="sp-card-header grad-score-header">
                    <div>
                        <h3>Input Nilai Akhir</h3>
                        <div class="grad-muted">NIS dan Nama Siswa otomatis muncul sesuai kelas yang dipilih.</div>
                    </div>
                    <div class="sp-toolbar grad-score-toolbar">
                        <button class="btn btn-outline btn-sm" onclick="Graduation.downloadScoreTemplate()">Template Excel</button>
                        <button class="btn btn-accent btn-sm" onclick="Graduation.openScoreImport()">Import Nilai</button>
                        <button class="btn btn-primary btn-sm" id="btnSaveAllScores" onclick="Graduation.saveScores(this)">Simpan Nilai</button>
                    </div>
                </div>
                <div class="sp-card-body">
                    <div class="grad-filter-row" style="margin-bottom:16px">
                        <select class="form-select" id="scoreClass"><option value="">Pilih kelas</option></select>
                        <button class="btn btn-outline btn-sm" onclick="Graduation.loadScores()">Tampilkan</button>
                    </div>
                    <div id="scoreScrollControlContainer"></div>
                    <div class="grad-score-xscroll" id="scoreTableWrapper">
                        <div class="skeleton" style="height:320px"></div>
                    </div>
                </div>
            </div>
        `);
        this.loadScores();
    },

    loadScores(kelas = '') {
        const selected = kelas || $('#scoreClass').val() || '';
        const query = selected ? `&kelas=${encodeURIComponent(selected)}` : '';
        return this.api(`scores.php?action=meta${query}`).done(res => {
            this.state.scoreMeta = res.data;
            this.renderScoreClassOptions(res.data.classes || [], res.data.kelas);
            this.renderScoreTable(res.data);
        }).fail(xhr => this.showAjaxError(xhr));
    },

    renderScoreClassOptions(classes, current) {
        const options = classes.map(k => `<option value="${this.escapeAttr(k.kelas)}">${this.escapeHtml(k.kelas)} (${k.total_siswa} siswa)</option>`).join('');
        $('#scoreClass').html(`<option value="">Pilih kelas</option>${options}`).val(current || '');
    },

    renderScoreTable(data) {
        const subjects = data.subjects || [];
        const students = data.students || [];
        const scores = data.scores || {};
        if (!subjects.length) {
            $('#scoreTable').html('<div class="sp-empty"><h3>Belum Ada Mapel</h3><p>Tambahkan mata pelajaran untuk kelas ini terlebih dahulu.</p></div>');
            return;
        }
        if (!students.length) {
            $('#scoreTable').html('<div class="sp-empty"><h3>Belum Ada Siswa</h3><p>Data siswa kelas ini masih kosong.</p></div>');
            return;
        }

        const header = subjects.map(s => `<th title="${this.escapeAttr(s.nama_mapel)}">${this.escapeHtml(s.kode_mapel || s.nama_mapel)}</th>`).join('');
        const rows = students.map(st => `
            <tr data-student-row="${st.id}">
                <td><code class="sp-code">${this.escapeHtml(st.nis)}</code></td>
                <td><strong>${this.escapeHtml(st.nama)}</strong></td>
                ${subjects.map(sub => {
                    const key = `${st.id}:${sub.id}`;
                    const value = scores[key] ?? '';
                    return `<td><input class="form-input grad-score-input" type="number" min="0" max="100" step="0.01" data-student="${st.id}" data-subject="${sub.id}" data-initial="${this.escapeAttr(value)}" value="${this.escapeAttr(value)}"></td>`;
                }).join('')}
                <td><input class="form-input grad-average-input" type="number" min="0" max="100" step="0.01" data-student="${st.id}" data-initial="${this.escapeAttr(st.rata_rata ?? '')}" value="${this.escapeAttr(st.rata_rata ?? '')}" style="font-weight:700; background:#f0fdfa; border-color:#99f6e4;"></td>
                <td style="text-align:center">
                    <button class="btn btn-primary btn-icon btn-sm grad-row-save-btn" onclick="Graduation.saveStudentScores(${st.id}, this)" title="Simpan baris ini" style="width:32px; height:32px; padding:0; display:flex; align-items:center; justify-content:center;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    </button>
                </td>
            </tr>
        `).join('');

        const tableMinWidth = Math.max(1200, 320 + (subjects.length * 130) + 120 + 80);
        $('#scoreTableWrapper').html(`<table class="sp-table grad-score-table" id="gradScoreTableActual" style="width:max-content; min-width:${tableMinWidth}px; table-layout:auto;"><thead><tr><th style="min-width:120px">NIS</th><th style="min-width:260px">Nama Siswa</th>${header}<th style="min-width:100px; background:#ccfbf1; color:#0f766e;">Rata-rata</th><th style="min-width:60px; text-align:center">Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);

        // Change tracking listeners
        $('.grad-score-input, .grad-average-input').on('input change', function() {
            const $this = $(this);
            const initial = String($this.data('initial') || '');
            const current = String($this.val() || '');
            if (initial !== current) {
                $this.addClass('is-changed');
                $this.closest('tr').addClass('row-changed');
            } else {
                $this.removeClass('is-changed');
                const $row = $this.closest('tr');
                if (!$row.find('.is-changed').length) $row.removeClass('row-changed');
            }
        });

        this.initScoreScrollControl();
    },

    initScoreScrollControl() {
        const $wrap = $('#scoreTableWrapper');
        const $container = $('#scoreScrollControlContainer');
        const $table = $('#gradScoreTableActual');

        setTimeout(() => {
            if (!$table.length || $wrap[0].scrollWidth <= $wrap[0].clientWidth) {
                $container.empty();
                return;
            }

            $container.html(`
                <div class="grad-score-scroll-control sp-fade-in">
                    <span>Geser Tabel:</span>
                    <button class="btn btn-ghost btn-sm" onclick="document.getElementById('scoreScrollSlider').stepDown(10)">-</button>
                    <input type="range" id="scoreScrollSlider" min="0" max="100" value="0" style="cursor:pointer">
                    <button class="btn btn-ghost btn-sm" onclick="document.getElementById('scoreScrollSlider').stepUp(10)">+</button>
                </div>
            `);

            const $slider = $('#scoreScrollSlider');
            const updateSlider = () => {
                if (!$wrap.length || !$wrap[0]) return;
                const maxScroll = $wrap[0].scrollWidth - $wrap[0].clientWidth;
                if (maxScroll <= 0) {
                    $container.empty();
                    return;
                }
                const percent = ($wrap.scrollLeft() / maxScroll) * 100;
                $slider.val(percent);
            };

            $slider.on('input', function() {
                const maxScroll = $wrap[0].scrollWidth - $wrap[0].clientWidth;
                const scrollLeft = (this.value / 100) * maxScroll;
                $wrap.scrollLeft(scrollLeft);
            });

            $wrap.on('scroll', updateSlider);
            $(window).off('resize.gradScoreScroll').on('resize.gradScoreScroll', updateSlider);
            updateSlider();
        }, 200);
    },

    saveScores(btn) {
        const kelas = $('#scoreClass').val() || this.state.scoreMeta.kelas;
        if (!kelas) {
            EModal.toast({ type: 'warning', title: 'Pilih kelas', message: 'Pilih kelas terlebih dahulu.' });
            return;
        }

        const scores = {};
        const averages = {};
        let changeCount = 0;

        $('.grad-score-input.is-changed').each(function() {
            const student = $(this).data('student');
            const subject = $(this).data('subject');
            if (!scores[student]) scores[student] = {};
            scores[student][subject] = this.value;
            changeCount++;
        });

        $('.grad-average-input.is-changed').each(function() {
            const student = $(this).data('student');
            averages[student] = this.value;
            changeCount++;
        });

        if (changeCount === 0) {
            EModal.toast({ type: 'info', title: 'Tidak ada perubahan', message: 'Tidak ada data nilai yang berubah untuk disimpan.' });
            return;
        }

        if (btn) EModal.btnLoading(btn, true);
        this.api('scores.php?action=save', { method: 'POST', data: { kelas, scores, averages } }).done(res => {
            EModal.toast({ type: 'success', title: 'Berhasil', message: `${res.data.saved} nilai berhasil diperbarui.` });
            // Update initial values
            $('.is-changed').each(function() {
                const $this = $(this);
                $this.data('initial', $this.val());
                $this.removeClass('is-changed');
            });
            $('.row-changed').removeClass('row-changed');
        }).fail(xhr => this.showAjaxError(xhr)).always(() => {
            if (btn) EModal.btnLoading(btn, false);
        });
    },

    saveStudentScores(studentId, btn) {
        const kelas = $('#scoreClass').val() || this.state.scoreMeta.kelas;
        const $row = $(`tr[data-student-row="${studentId}"]`);
        const scores = {};
        const averages = {};
        let changeCount = 0;

        $row.find('.grad-score-input.is-changed').each(function() {
            const subject = $(this).data('subject');
            if (!scores[studentId]) scores[studentId] = {};
            scores[studentId][subject] = this.value;
            changeCount++;
        });

        $row.find('.grad-average-input.is-changed').each(function() {
            averages[studentId] = this.value;
            changeCount++;
        });

        if (changeCount === 0) {
            EModal.toast({ type: 'info', title: 'Tidak ada perubahan', message: 'Tidak ada perubahan nilai untuk siswa ini.' });
            return;
        }

        if (btn) EModal.btnLoading(btn, true);
        this.api('scores.php?action=save', { method: 'POST', data: { kelas, scores, averages } }).done(res => {
            EModal.toast({ type: 'success', title: 'Berhasil', message: `Nilai siswa berhasil diperbarui.` });
            $row.find('.is-changed').each(function() {
                const $this = $(this);
                $this.data('initial', $this.val());
                $this.removeClass('is-changed');
            });
            $row.removeClass('row-changed');
        }).fail(xhr => this.showAjaxError(xhr)).always(() => {
            if (btn) EModal.btnLoading(btn, false);
        });
    },

    openScoreImport(forceReady = false) {
        const kelas = $('#scoreClass').val() || this.state.scoreMeta.kelas;
        if (!kelas) {
            EModal.toast({ type: 'warning', title: 'Pilih kelas', message: 'Pilih kelas terlebih dahulu.' });
            return;
        }
        if (!forceReady && kelas !== this.state.scoreMeta.kelas) {
            const loader = EModal.loading('Memuat data kelas...');
            this.loadScores(kelas).done(() => {
                EModal.close(loader);
                this.openScoreImport(true);
            }).fail(() => EModal.close(loader));
            return;
        }
        if (!this.state.scoreMeta.subjects?.length || !this.state.scoreMeta.students?.length) {
            EModal.toast({ type: 'warning', title: 'Data belum siap', message: 'Tampilkan data kelas terlebih dahulu sebelum import.' });
            return;
        }
        EModal.form({
            title: 'Import Nilai Excel',
            size: 'md',
            form: `
                <div class="form-group">
                    <label class="form-label">Kelas</label>
                    <input class="form-input" value="${this.escapeAttr(kelas)}" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">File Excel</label>
                    <input class="form-input" type="file" id="scoreImportFile" accept=".xlsx,.xls,.csv">
                    <div class="grad-muted" style="margin-top:8px">Gunakan template agar format kolom cocok otomatis. Kolom mapel dibaca dari kode mapel atau nama mapel.</div>
                    <button type="button" class="btn btn-outline btn-sm" style="margin-top:10px" onclick="Graduation.downloadScoreTemplate()">Download Template Excel</button>
                </div>
            `,
            confirmText: 'Import',
            onConfirm: () => {
                const file = document.getElementById('scoreImportFile').files[0];
                if (!file) {
                    EModal.toast({ type: 'warning', title: 'Pilih file', message: 'Upload file Excel terlebih dahulu.' });
                    return false;
                }
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                const isCsv = ext === 'csv';

                this.loadSheetJS(() => {
                    const reader = new FileReader();
                    const loader = EModal.loading('Membaca file Excel...');
                    reader.onload = (event) => {
                        try {
                            const data = new Uint8Array(event.target.result);
                            const workbook = XLSX.read(data, { type: 'array' });
                            const firstSheet = workbook.SheetNames[0];
                            const matrix = XLSX.utils.sheet_to_json(workbook.Sheets[firstSheet], { header: 1, raw: false, defval: '' });
                            EModal.close(loader);

                            const parsed = this.parseScoreImportMatrix(matrix);
                            if (parsed.error) {
                                EModal.toast({ type: 'warning', title: 'Format tidak cocok', message: parsed.error });
                                return;
                            }
                            if (!parsed.saved_count) {
                                EModal.toast({ type: 'warning', title: 'Tidak ada nilai', message: 'Tidak ada nilai valid yang bisa diimport.' });
                                return;
                            }

                            const saving = EModal.loading('Menyimpan nilai import...');
                            this.api('scores.php?action=save', { method: 'POST', data: { kelas, scores: parsed.scores, averages: parsed.averages } }).done(res => {
                                EModal.close(saving);
                                EModal.closeAll();
                                const invalidInfo = parsed.invalid_count > 0 ? ` ${parsed.invalid_count} nilai dilewati karena tidak valid.` : '';
                                EModal.toast({ type: 'success', title: 'Import berhasil', message: `${res.data.saved} nilai diimport dari ${parsed.matched_students} siswa.${invalidInfo}` });
                                this.loadScores(kelas);
                            }).fail(xhr => {
                                EModal.close(saving);
                                this.showAjaxError(xhr);
                            });
                        } catch (err) {
                            EModal.close(loader);
                            EModal.toast({ type: 'error', title: 'File tidak valid', message: 'Format file tidak bisa dibaca. Gunakan template Excel.' });
                        }
                    };
                    reader.onerror = () => {
                        EModal.close(loader);
                        EModal.toast({ type: 'error', title: 'Gagal membaca file', message: 'Silakan coba pilih file kembali.' });
                    };
                    reader.readAsArrayBuffer(file);
                }, () => {
                    if (isCsv) {
                        this.importScoresCsvFallback(file, kelas);
                        return;
                    }
                    EModal.toast({ type: 'error', title: 'Gagal memuat modul Excel', message: 'Periksa koneksi internet lalu coba lagi.' });
                });
                return false;
            }
        });
    },

    downloadScoreTemplate(forceReady = false) {
        const kelas = $('#scoreClass').val() || this.state.scoreMeta.kelas;
        if (!kelas) {
            EModal.toast({ type: 'warning', title: 'Pilih kelas', message: 'Pilih kelas terlebih dahulu.' });
            return;
        }
        if (!forceReady && kelas !== this.state.scoreMeta.kelas) {
            const loader = EModal.loading('Memuat data kelas...');
            this.loadScores(kelas).done(() => {
                EModal.close(loader);
                this.downloadScoreTemplate(true);
            }).fail(() => EModal.close(loader));
            return;
        }
        const subjects = this.state.scoreMeta.subjects || [];
        const students = this.state.scoreMeta.students || [];
        if (!subjects.length || !students.length) {
            EModal.toast({ type: 'warning', title: 'Data belum siap', message: 'Tampilkan data nilai kelas terlebih dahulu.' });
            return;
        }

        this.loadSheetJS(() => {
            const headers = ['NIS', 'NAMA SISWA'].concat(subjects.map(sub => sub.kode_mapel || sub.nama_mapel)).concat(['RATA-RATA']);
            const aoa = [headers];
            students.forEach(st => {
                const row = [st.nis, st.nama];
                subjects.forEach(sub => {
                    const key = `${st.id}:${sub.id}`;
                    row.push(this.state.scoreMeta.scores?.[key] ?? '');
                });
                row.push(st.rata_rata ?? '');
                aoa.push(row);
            });

            const ws = XLSX.utils.aoa_to_sheet(aoa);
            ws['!cols'] = [{ wch: 16 }, { wch: 34 }].concat(subjects.map(() => ({ wch: 12 }))).concat([{ wch: 14 }]);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Template Nilai');
            const safeClass = String(kelas).replace(/[^A-Za-z0-9_-]/g, '-');
            XLSX.writeFile(wb, `Template-Nilai-${safeClass}.xlsx`);
        }, () => {
            EModal.toast({ type: 'error', title: 'Gagal memuat modul Excel', message: 'Periksa koneksi internet lalu coba lagi.' });
        });
    },

    parseScoreImportMatrix(matrix) {
        if (!Array.isArray(matrix) || matrix.length < 2) {
            return { error: 'File kosong atau tidak memiliki baris data.' };
        }

        const header = (matrix[0] || []).map(col => String(col ?? '').trim());
        const nisIndex = header.findIndex(col => col.toUpperCase() === 'NIS');
        if (nisIndex < 0) {
            return { error: 'Kolom NIS wajib ada pada header template.' };
        }

        const subjectByHeader = {};
        (this.state.scoreMeta.subjects || []).forEach(sub => {
            const codeKey = String(sub.kode_mapel || '').trim().toUpperCase();
            const nameKey = String(sub.nama_mapel || '').trim().toUpperCase();
            if (codeKey) subjectByHeader[codeKey] = sub.id;
            if (nameKey && !subjectByHeader[nameKey]) subjectByHeader[nameKey] = sub.id;
        });

        const scoreColumns = {};
        header.forEach((name, idx) => {
            const key = String(name || '').trim().toUpperCase();
            if (subjectByHeader[key]) {
                scoreColumns[idx] = subjectByHeader[key];
            }
        });

        const rataRataIndex = header.findIndex(col => col.toUpperCase() === 'RATA-RATA');

        if (!Object.keys(scoreColumns).length && rataRataIndex < 0) {
            return { error: 'Kolom mapel atau RATA-RATA tidak ditemukan. Gunakan file dari template Excel.' };
        }

        const studentByNis = {};
        (this.state.scoreMeta.students || []).forEach(st => {
            studentByNis[String(st.nis || '').trim()] = st.id;
        });

        const scores = {};
        const averages = {};
        let matchedStudents = 0;
        let savedCount = 0;
        let invalidCount = 0;

        for (let i = 1; i < matrix.length; i++) {
            const row = Array.isArray(matrix[i]) ? matrix[i] : [];
            const nis = String(row[nisIndex] ?? '').trim();
            const studentId = studentByNis[nis];
            if (!nis || !studentId) continue;

            let hasScore = false;
            Object.keys(scoreColumns).forEach((idxKey) => {
                const idx = Number(idxKey);
                const subjectId = scoreColumns[idx];
                const raw = String(row[idx] ?? '').trim();
                if (raw === '') return;
                const normalized = raw.replace(/\s+/g, '').replace(',', '.');
                const value = Number(normalized);
                if (!Number.isFinite(value) || value < 0 || value > 100) {
                    invalidCount++;
                    return;
                }
                if (!scores[studentId]) scores[studentId] = {};
                scores[studentId][subjectId] = value;
                savedCount++;
                hasScore = true;
            });

            // Extract rata-rata
            if (rataRataIndex >= 0) {
                const rawAvg = String(row[rataRataIndex] ?? '').trim();
                if (rawAvg !== '') {
                    const normalizedAvg = rawAvg.replace(/\s+/g, '').replace(',', '.');
                    const valAvg = Number(normalizedAvg);
                    if (Number.isFinite(valAvg) && valAvg >= 0 && valAvg <= 100) {
                        averages[studentId] = valAvg;
                        savedCount++;
                        hasScore = true;
                    } else {
                        invalidCount++;
                    }
                }
            }

            if (hasScore) matchedStudents++;
        }

        return {
            scores,
            averages,
            matched_students: matchedStudents,
            saved_count: savedCount,
            invalid_count: invalidCount
        };
    },

    importScoresCsvFallback(file, kelas) {
        const fd = new FormData();
        fd.append('kelas', kelas);
        fd.append('csv', file);
        const loader = EModal.loading('Mengimport CSV...');
        this.api('scores.php?action=import-csv', { method: 'POST', data: fd }).done(res => {
            EModal.close(loader);
            EModal.closeAll();
            EModal.toast({ type: 'success', title: 'Import berhasil', message: `${res.data.saved} nilai diimport.` });
            this.loadScores(kelas);
        }).fail(xhr => {
            EModal.close(loader);
            this.showAjaxError(xhr);
        });
    },

    loadSheetJS(onReady, onFail) {
        if (window.XLSX) {
            onReady();
            return;
        }
        const loader = EModal.loading('Memuat modul Excel...');
        $.getScript('https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js')
            .done(() => {
                EModal.close(loader);
                onReady();
            })
            .fail(() => {
                EModal.close(loader);
                if (typeof onFail === 'function') onFail();
            });
    },

    renderReports($container) {
        const isAdmin = !!this.state.user.can_manage_graduation;
        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header">
                    <div>
                        <h3>Status Pengisian Nilai${isAdmin ? '' : ' Wali Kelas'}</h3>
                        <div class="grad-muted">${isAdmin ? 'Pilih kelas untuk melihat nama mata pelajaran dan status penilaian.' : 'Kelas dibatasi sesuai pengaturan akses wali kelas.'}</div>
                    </div>
                    <span class="grad-year-pill">${this.activeYearLabel()}</span>
                </div>
                <div class="sp-card-body">
                    <div class="grad-filter-row" style="margin-bottom:16px">
                        <select class="form-select" id="reportStatusClass"><option value="">Pilih kelas</option></select>
                        <button class="btn btn-primary btn-sm" onclick="Graduation.loadReportStatus()">Tampilkan Status</button>
                    </div>
                    <div class="sp-table-wrapper" id="reportStatusTable">
                        <div class="sp-empty" style="padding:28px"><p>Pilih kelas untuk melihat status penilaian.</p></div>
                    </div>
                </div>
            </div>
            <div class="grad-report-grid">
                <div class="sp-card">
                    <div class="sp-card-header">
                        <div>
                            <h3>Download SKL</h3>
                            <div class="grad-muted">${isAdmin ? 'Unduh SKL PDF per orang, per kelas, atau semua siswa.' : 'Unduh SKL PDF per orang atau 1 kelas wali.'}</div>
                        </div>
                    </div>
                    <div class="sp-card-body">
                        <div class="form-group">
                            <label class="form-label">Mode Download</label>
                            <select class="form-select" id="sklScope">
                                <option value="student">Per Orang</option>
                                <option value="class">${isAdmin ? 'Per Kelas' : '1 Kelas'}</option>
                                ${isAdmin ? '<option value="all">Semua Siswa</option>' : ''}
                            </select>
                        </div>
                        <div class="form-group" id="sklStudentWrap">
                            <label class="form-label">Siswa</label>
                            <input type="hidden" id="sklStudent" value="">
                            <div class="sp-cs-container" id="sklStudentContainer">
                                <div class="sp-cs-btn" id="sklStudentBtn">
                                    <span id="sklStudentText" style="color:#64748b; font-size:0.95rem;">-- Pilih Siswa --</span>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </div>
                                <div class="sp-cs-dropdown" id="sklStudentDropdown">
                                    <div style="padding:12px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                                        <input type="text" id="sklStudentSearch" class="form-input" placeholder="Cari nama siswa..." style="width:100%; padding:10px 14px; height:42px; border-radius:8px; border:1px solid #cbd5e1; outline:none;" autocomplete="off">
                                    </div>
                                    <div id="sklStudentList" style="max-height:250px; overflow-y:auto; padding:5px 0;">
                                        <div style="padding:15px; text-align:center; color:#64748b; font-size:0.85rem;">Memuat data siswa...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" id="sklClassWrap">
                            <label class="form-label">Kelas</label>
                            <select class="form-select" id="sklClass"><option value="">Pilih kelas</option></select>
                        </div>
                        <button class="btn btn-primary btn-block" id="btnDownloadSkl" onclick="Graduation.downloadSkl()">Download SKL PDF</button>
                    </div>
                </div>
                <div class="sp-card">
                    <div class="sp-card-header">
                        <div>
                            <h3>Download Leger Nilai Akhir</h3>
                            <div class="grad-muted">${isAdmin ? 'Unduh leger nilai akhir per kelas atau semua kelas.' : 'Unduh leger nilai akhir untuk 1 kelas wali.'}</div>
                        </div>
                    </div>
                    <div class="sp-card-body">
                        <div class="form-group">
                            <label class="form-label">Mode Download</label>
                            <select class="form-select" id="legerScope">
                                <option value="class">${isAdmin ? 'Per Kelas' : '1 Kelas'}</option>
                                ${isAdmin ? '<option value="all">Semua Kelas</option>' : ''}
                            </select>
                        </div>
                        <div class="form-group" id="legerClassWrap">
                            <label class="form-label">Kelas</label>
                            <select class="form-select" id="legerClass"><option value="">Pilih kelas</option></select>
                        </div>
                        <button class="btn btn-accent btn-block" id="btnDownloadLeger" onclick="Graduation.downloadLeger()">Download Leger</button>
                    </div>
                </div>
            </div>
        `);

        this.loadReportMeta();
        $('#sklScope').on('change', () => this.toggleSklScope());
        $('#legerScope').on('change', () => this.toggleLegerScope());
        this.toggleSklScope();
        this.toggleLegerScope();
    },

    loadReportMeta() {
        return this.api('reports.php?action=meta').done(res => {
            const isAdmin = !!this.state.user.can_manage_graduation;
            this.state.reportMeta = {
                classes: res.data.classes || [],
                students: res.data.students || []
            };
            const classOptions = this.state.reportMeta.classes.map(k => `<option value="${this.escapeAttr(k.kelas)}">${this.escapeHtml(k.kelas)} (${k.total_siswa} siswa)</option>`).join('');
            $('#reportStatusClass, #sklClass, #legerClass').append(classOptions);

            // Populate searchable student dropdown
            const students = this.state.reportMeta.students;
            if (students.length) {
                const optionsHtml = students.map(s => `
                    <div class="sp-cs-option" data-id="${s.id}" data-nama="${this.escapeAttr(s.nama)}">
                        <div class="cs-opt-m">${this.escapeHtml(s.nama)}</div>
                        <div class="cs-opt-k">${this.escapeHtml(s.kelas)} • NIS: ${this.escapeHtml(s.nis || '-')}</div>
                    </div>
                `).join('');
                $('#sklStudentList').html(optionsHtml);
            } else {
                $('#sklStudentList').html('<div style="padding:15px; text-align:center; color:#64748b; font-size:0.85rem;">Belum ada data siswa.</div>');
            }

            // Search dropdown interactions
            $('#sklStudentBtn').off('click').on('click', function(e) {
                e.stopPropagation();
                $(this).toggleClass('active');
                const $dd = $('#sklStudentDropdown');
                $dd.toggle();
                if ($dd.is(':visible')) {
                    // Auto-flip: open upward if not enough space below
                    $dd.css({ top: '', bottom: '' });
                    const btnRect = this.getBoundingClientRect();
                    const spaceBelow = window.innerHeight - btnRect.bottom;
                    if (spaceBelow < 300) {
                        $dd.css({ top: 'auto', bottom: 'calc(100% + 5px)' });
                    } else {
                        $dd.css({ top: 'calc(100% + 5px)', bottom: 'auto' });
                    }
                    $('#sklStudentSearch').val('').trigger('input').focus();
                }
            });

            $(document).off('click.sklStudentDd').on('click.sklStudentDd', function(e) {
                if (!$(e.target).closest('#sklStudentContainer').length) {
                    $('#sklStudentDropdown').hide();
                    $('#sklStudentBtn').removeClass('active');
                }
            });

            $('#sklStudentSearch').off('input').on('input', function() {
                const term = $(this).val().toLowerCase();
                $('#sklStudentList .sp-cs-option').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(term));
                });
            });

            $('#sklStudentList').off('click').on('click', '.sp-cs-option', function() {
                const id = $(this).data('id');
                const nama = $(this).find('.cs-opt-m').text();
                $('#sklStudent').val(id);
                $('#sklStudentText').text(nama).css('color', '#1e293b');
                $('#sklStudentDropdown').hide();
                $('#sklStudentBtn').removeClass('active');
            });

            if (!isAdmin && this.state.reportMeta.classes.length === 1) {
                const kelas = this.state.reportMeta.classes[0].kelas;
                $('#reportStatusClass, #sklClass, #legerClass').val(kelas).prop('disabled', true);
                if (students.length === 1) {
                    $('#sklStudent').val(students[0].id);
                    $('#sklStudentText').text(students[0].nama).css('color', '#1e293b');
                }
                this.loadReportStatus();
            }
        }).fail(xhr => this.showAjaxError(xhr));
    },

    loadReportStatus() {
        const kelas = $('#reportStatusClass').val();
        if (!kelas) {
            EModal.toast({ type: 'warning', title: 'Pilih kelas', message: 'Pilih kelas terlebih dahulu.' });
            return;
        }

        $('#reportStatusTable').html('<div class="skeleton" style="height:220px"></div>');
        this.api(`reports.php?action=status&kelas=${encodeURIComponent(kelas)}`).done(res => {
            const data = res.data;
            if (!data.items.length) {
                $('#reportStatusTable').html('<div class="sp-empty"><h3>Belum Ada Mapel</h3><p>Tambahkan mata pelajaran untuk kelas ini terlebih dahulu.</p></div>');
                return;
            }

            const rows = data.items.map((item, idx) => {
                const badge = item.status === 'Lengkap' ? 'badge-success' : (item.status === 'Proses' ? 'badge-warning' : 'badge-danger');
                return `
                    <tr>
                        <td>${idx + 1}</td>
                        <td>
                            <strong>${this.escapeHtml(item.nama_mapel)}</strong>
                            <div class="grad-muted">${this.escapeHtml(item.group_nama)} ${item.group_tipe === 'pilihan' ? `- ${this.escapeHtml(item.kelas || data.kelas)}` : ''}</div>
                        </td>
                        <td><span class="badge ${badge}">${this.escapeHtml(item.status)}</span></td>
                        <td>${item.terisi}/${item.total_siswa} siswa</td>
                        <td>${item.kurang}</td>
                    </tr>
                `;
            }).join('');

            $('#reportStatusTable').html(`
                <table class="sp-table">
                    <thead><tr><th>No</th><th>Nama Mata Pelajaran</th><th>Status Penilaian</th><th>Terisi</th><th>Kurang</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        }).fail(xhr => this.showAjaxError(xhr));
    },

    toggleSklScope() {
        const scope = $('#sklScope').val();
        $('#sklStudentWrap').toggle(scope === 'student');
        $('#sklClassWrap').toggle(scope === 'class');
    },

    toggleLegerScope() {
        $('#legerClassWrap').toggle($('#legerScope').val() === 'class');
    },

    downloadSkl() {
        const scope = $('#sklScope').val();
        const params = new URLSearchParams({ action: 'download-skl', scope, token: this.state.token });
        if (scope === 'student') {
            const studentId = $('#sklStudent').val();
            if (!studentId) {
                EModal.toast({ type: 'warning', title: 'Pilih siswa', message: 'Pilih siswa terlebih dahulu.' });
                return;
            }
            params.set('student_id', studentId);
        }
        if (scope === 'class') {
            const kelas = $('#sklClass').val();
            if (!kelas) {
                EModal.toast({ type: 'warning', title: 'Pilih kelas', message: 'Pilih kelas terlebih dahulu.' });
                return;
            }
            params.set('kelas', kelas);
        }
        this._fetchDownload(`${this.state.apiUrl}reports.php?${params.toString()}`, 'SKL.pdf', '#btnDownloadSkl', 'Download SKL PDF');
    },

    downloadLeger() {
        const scope = $('#legerScope').val();
        const params = new URLSearchParams({ action: 'download-leger', scope, token: this.state.token });
        if (scope === 'class') {
            const kelas = $('#legerClass').val();
            if (!kelas) {
                EModal.toast({ type: 'warning', title: 'Pilih kelas', message: 'Pilih kelas terlebih dahulu.' });
                return;
            }
            params.set('kelas', kelas);
        }
        this._fetchDownload(`${this.state.apiUrl}reports.php?${params.toString()}`, 'Leger.pdf', '#btnDownloadLeger', 'Download Leger');
    },

    _fetchDownload(url, fallbackName, btnSelector, btnOriginalText) {
        const $btn = $(btnSelector);
        $btn.prop('disabled', true).html('<span class="grad-btn-spin"></span> Memproses...');

        fetch(url)
            .then(res => {
                if (!res.ok) {
                    return res.text().then(txt => { throw new Error(txt || 'Gagal mengunduh file.'); });
                }
                const disposition = res.headers.get('Content-Disposition') || '';
                const match = disposition.match(/filename="?([^"]+)"?/);
                const filename = match ? match[1] : fallbackName;
                return res.blob().then(blob => ({ blob, filename }));
            })
            .then(({ blob, filename }) => {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(a.href);
                EModal.toast({ type: 'success', title: 'Berhasil', message: 'File berhasil diunduh.' });
            })
            .catch(err => {
                EModal.toast({ type: 'danger', title: 'Gagal', message: err.message });
            })
            .finally(() => {
                $btn.prop('disabled', false).html(btnOriginalText);
            });
    },

    renderGroupSummary(items) {
        if (!items.length) {
            return '<div class="sp-empty" style="padding:24px">Belum ada kelompok mapel.</div>';
        }
        return `<div class="grad-mini-list">${items.map(item => `
            <div class="grad-mini-item">
                <div>
                    <strong><span class="grad-code">${this.escapeHtml(item.kode)}</span> ${this.escapeHtml(item.nama)}</strong>
                    <div class="grad-muted">${this.tipeLabel(item.tipe)}</div>
                </div>
                <span class="badge badge-primary">${item.total_mapel} mapel</span>
            </div>
        `).join('')}</div>`;
    },

    renderClassSummary(items) {
        if (!items.length) {
            return '<div class="sp-empty" style="padding:24px">Belum ada kelas dari data siswa.</div>';
        }
        return `<div class="grad-mini-list">${items.map(item => `
            <div class="grad-mini-item">
                <strong>${this.escapeHtml(item.kelas)}</strong>
                <span class="badge badge-success">${item.total_siswa} siswa</span>
            </div>
        `).join('')}</div>`;
    },

    renderSubjects($container) {
        $container.html(`
            <div class="grad-mapel-grid">
                <div class="sp-card">
                    <div class="sp-card-header">
                        <div>
                            <h3>Kelompok Mapel</h3>
                            <div class="grad-muted">Contoh: A - Mapel Wajib, B - Mapel Pilihan</div>
                        </div>
                        <div class="sp-toolbar">
                            ${this.state.user.can_manage_graduation ? `<button class="btn btn-primary btn-sm" onclick="Graduation.openGroupForm()">Tambah Kelompok</button>` : ''}
                        </div>
                    </div>
                    <div class="sp-card-body">
                        <div class="sp-table-wrapper" id="groupsTable"><div class="skeleton" style="height:260px"></div></div>
                    </div>
                </div>
                <div class="sp-card">
                    <div class="sp-card-header">
                        <div>
                            <h3>Mata Pelajaran</h3>
                            <div class="grad-muted">Mapel pilihan bisa dipasang ke beberapa kelas sekaligus, misalnya 12.1 dan 12.2.</div>
                        </div>
                        <div class="sp-toolbar">
                            ${this.state.user.can_manage_graduation ? `<button class="btn btn-primary btn-sm" onclick="Graduation.openSubjectForm()">Tambah Mapel</button>` : ''}
                        </div>
                    </div>
                    <div class="sp-card-body">
                        <div class="grad-filter-row" style="margin-bottom:16px">
                            <select class="form-select" id="subjectGroupFilter"><option value="">Semua kelompok</option></select>
                            <div class="search-box">
                                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="subjectSearch" placeholder="Cari kode, nama, kelas..." value="${this.escapeHtml(this.state.subjectSearch)}">
                            </div>
                        </div>
                        <div class="sp-table-wrapper" id="subjectsTable"><div class="skeleton" style="height:260px"></div></div>
                    </div>
                </div>
            </div>
        `);

        $('#subjectSearch').on('input', this.debounce((e) => {
            this.state.subjectSearch = e.target.value;
            this.loadSubjects();
        }, 350));

        $('#subjectGroupFilter').on('change', e => {
            this.state.subjectGroupFilter = e.target.value;
            this.loadSubjects();
        });

        $.when(this.loadGroups(), this.loadClasses()).always(() => this.loadSubjects());
    },

    loadGroups() {
        return this.api('subjects.php?action=groups').done(res => {
            this.state.groups = res.data || [];
            this.renderGroupsTable();
            this.fillGroupFilter();
        }).fail(xhr => this.showAjaxError(xhr));
    },

    loadClasses() {
        return this.api('subjects.php?action=classes').done(res => {
            this.state.classes = res.data || [];
        });
    },

    renderGroupsTable() {
        const groups = this.state.groups;
        if (!groups.length) {
            $('#groupsTable').html(`
                <div class="sp-empty">
                    <h3>Belum Ada Kelompok Mapel</h3>
                    <p>Tambahkan kelompok seperti A: Mapel Wajib atau B: Mapel Pilihan.</p>
                </div>
            `);
            return;
        }

        const rows = groups.map(g => `
            <tr>
                <td><span class="grad-code">${this.escapeHtml(g.kode)}</span></td>
                <td>
                    <strong>${this.escapeHtml(g.nama)}</strong>
                    <div class="grad-muted">${this.escapeHtml(g.deskripsi || '-')}</div>
                </td>
                <td><span class="badge ${g.tipe === 'pilihan' ? 'badge-warning' : 'badge-primary'}">${this.tipeLabel(g.tipe)}</span></td>
                <td><span class="badge badge-info">${g.total_mapel} mapel</span></td>
                <td>
                    <div class="sp-actions">
                        ${this.state.user.can_manage_graduation ? `
                        <button class="sp-btn-icon" title="Edit" onclick="Graduation.openGroupForm(${g.id})">${this.iconEdit()}</button>
                        <button class="sp-btn-icon danger" title="Hapus" data-name="${this.escapeAttr(g.nama)}" onclick="Graduation.deleteGroup(${g.id}, this.dataset.name)">${this.iconDelete()}</button>
                        ` : '-'}
                    </div>
                </td>
            </tr>
        `).join('');

        $('#groupsTable').html(`<table class="sp-table"><thead><tr><th>Kode</th><th>Kelompok</th><th>Tipe</th><th>Isi</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
    },

    fillGroupFilter() {
        const current = this.state.subjectGroupFilter;
        const options = this.state.groups.map(g => `<option value="${g.id}">${this.escapeHtml(g.kode)} - ${this.escapeHtml(g.nama)}</option>`).join('');
        $('#subjectGroupFilter').html(`<option value="">Semua kelompok</option>${options}`).val(current);
    },

    loadSubjects() {
        const params = new URLSearchParams({
            action: 'subjects',
            group_id: this.state.subjectGroupFilter || '',
            search: this.state.subjectSearch || ''
        });
        return this.api(`subjects.php?${params.toString()}`).done(res => {
            this.renderSubjectsTable(res.data || []);
        }).fail(xhr => this.showAjaxError(xhr));
    },

    renderSubjectsTable(items) {
        if (!items.length) {
            $('#subjectsTable').html(`
                <div class="sp-empty">
                    <h3>Belum Ada Mata Pelajaran</h3>
                    <p>Tambahkan mata pelajaran setelah kelompok mapel dibuat.</p>
                </div>
            `);
            return;
        }

        const rows = items.map(s => {
            const classBadges = s.kelas
                ? String(s.kelas).split(',').filter(Boolean).map(k => `<span class="badge badge-success">${this.escapeHtml(k.trim())}</span>`).join(' ')
                : '<span class="grad-muted">Semua kelas</span>';
            return `
            <tr>
                <td><code class="sp-code">${this.escapeHtml(s.kode_mapel || '-')}</code></td>
                <td>
                    <strong>${this.escapeHtml(s.nama_mapel)}</strong>
                    <div class="grad-muted">${this.escapeHtml(s.group_kode)} - ${this.escapeHtml(s.group_nama)}</div>
                </td>
                <td><span class="badge ${s.group_tipe === 'pilihan' ? 'badge-warning' : 'badge-primary'}">${this.tipeLabel(s.group_tipe)}</span></td>
                <td><div class="grad-class-badges">${classBadges}</div></td>
                <td>
                    <div class="sp-actions">
                        ${this.state.user.can_manage_graduation ? `
                        <button class="sp-btn-icon" title="Edit" onclick="Graduation.openSubjectForm(${s.id})">${this.iconEdit()}</button>
                        <button class="sp-btn-icon danger" title="Hapus" data-name="${this.escapeAttr(s.nama_mapel)}" onclick="Graduation.deleteSubject(${s.id}, this.dataset.name)">${this.iconDelete()}</button>
                        ` : '-'}
                    </div>
                </td>
            </tr>
        `;
        }).join('');

        $('#subjectsTable').html(`<table class="sp-table"><thead><tr><th>Kode</th><th>Mata Pelajaran</th><th>Tipe</th><th>Kelas</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
    },

    openGroupForm(id = null) {
        const isEdit = id !== null;
        EModal.form({
            title: isEdit ? 'Edit Kelompok Mapel' : 'Tambah Kelompok Mapel',
            size: 'md',
            form: `
                <input type="hidden" id="groupId" value="${id || ''}">
                <div class="sp-form-row">
                    <div class="form-group">
                        <label class="form-label">Kode Kelompok</label>
                        <input class="form-input" id="groupKode" placeholder="A, B, C">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipe Kelompok</label>
                        <select class="form-select" id="groupTipe">
                            <option value="wajib">Mapel Wajib</option>
                            <option value="pilihan">Mapel Pilihan</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Kelompok</label>
                    <input class="form-input" id="groupNama" placeholder="Mapel Wajib">
                </div>
                <div class="sp-form-row">
                    <div class="form-group">
                        <label class="form-label">Urutan</label>
                        <input class="form-input" type="number" id="groupUrutan" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tahun Ajaran</label>
                        <input class="form-input" value="${this.activeYearLabel()}" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-input" id="groupDeskripsi" rows="3" placeholder="Opsional"></textarea>
                </div>
            `,
            confirmText: 'Simpan Kelompok',
            onOpen: () => {
                if (isEdit) {
                    this.api(`subjects.php?action=group-get&id=${id}`).done(res => {
                        const g = res.data;
                        $('#groupKode').val(g.kode);
                        $('#groupNama').val(g.nama);
                        $('#groupTipe').val(g.tipe);
                        $('#groupUrutan').val(g.urutan);
                        $('#groupDeskripsi').val(g.deskripsi || '');
                    });
                }
            },
            onConfirm: () => {
                const data = {
                    id: $('#groupId').val(),
                    kode: $('#groupKode').val(),
                    nama: $('#groupNama').val(),
                    tipe: $('#groupTipe').val(),
                    urutan: $('#groupUrutan').val(),
                    deskripsi: $('#groupDeskripsi').val()
                };
                if (!data.kode || !data.nama) {
                    EModal.toast({ type: 'warning', title: 'Lengkapi data', message: 'Kode dan nama kelompok wajib diisi.' });
                    return false;
                }
                this.api('subjects.php?action=group-save', { method: 'POST', data }).done(() => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: 'Kelompok mapel disimpan.' });
                    this.loadGroups().always(() => this.loadSubjects());
                }).fail(xhr => this.showAjaxError(xhr));
                return false;
            }
        });
    },

    openSubjectForm(id = null) {
        if (!this.state.groups.length) {
            EModal.toast({ type: 'warning', title: 'Kelompok kosong', message: 'Tambahkan kelompok mapel terlebih dahulu.' });
            return;
        }

        const isEdit = id !== null;
        const groupOptions = this.state.groups.map(g => `<option value="${g.id}" data-tipe="${g.tipe}">${this.escapeHtml(g.kode)} - ${this.escapeHtml(g.nama)}</option>`).join('');
        const classOptions = this.state.classes.map(k => `<option value="${this.escapeAttr(k.kelas)}">${this.escapeHtml(k.kelas)} (${k.total_siswa} siswa)</option>`).join('');

        EModal.form({
            title: isEdit ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran',
            size: 'lg',
            form: `
                <input type="hidden" id="subjectId" value="${id || ''}">
                <div class="form-group">
                    <label class="form-label">Kelompok Mata Pelajaran</label>
                    <select class="form-select" id="subjectGroup">${groupOptions}</select>
                    <div class="grad-muted" style="margin-top:6px">Jika kelompok bertipe Mapel Pilihan, pilihan kelas multi-select akan muncul otomatis.</div>
                </div>
                <div class="sp-form-row">
                    <div class="form-group">
                        <label class="form-label">Kode Mapel</label>
                        <input class="form-input" id="subjectKode" placeholder="BIN, MTK, FIS">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Urutan</label>
                        <input class="form-input" type="number" id="subjectUrutan" value="0">
                    </div>
                </div>
                <div class="form-group grad-choice-field" id="subjectClassField">
                    <label class="form-label">Kelas (bisa lebih dari satu)</label>
                    <select class="form-select grad-multi-select" id="subjectKelas" multiple>
                        ${classOptions}
                    </select>
                    <div class="grad-muted" style="margin-top:6px">Tahan Ctrl saat memilih beberapa kelas. Contoh: 12.1 dan 12.2 memakai mapel pilihan yang sama.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Mata Pelajaran</label>
                    <input class="form-input" id="subjectNama" placeholder="Bahasa Indonesia">
                </div>
            `,
            confirmText: 'Simpan Mapel',
            onOpen: () => {
                const toggleClass = () => {
                    const tipe = $('#subjectGroup option:selected').data('tipe');
                    $('#subjectClassField').toggleClass('show', tipe === 'pilihan');
                    if (tipe !== 'pilihan') $('#subjectKelas').val([]);
                };
                $('#subjectGroup').on('change', toggleClass);

                if (isEdit) {
                    this.api(`subjects.php?action=subject-get&id=${id}`).done(res => {
                        const s = res.data;
                        $('#subjectGroup').val(s.group_id);
                        $('#subjectKode').val(s.kode_mapel || '');
                        $('#subjectNama').val(s.nama_mapel);
                        $('#subjectUrutan').val(s.urutan);
                        toggleClass();
                        $('#subjectKelas').val(String(s.kelas || '').split(',').map(k => k.trim()).filter(Boolean));
                    });
                } else {
                    toggleClass();
                }
            },
            onConfirm: () => {
                const data = {
                    id: $('#subjectId').val(),
                    group_id: $('#subjectGroup').val(),
                    kode_mapel: $('#subjectKode').val(),
                    nama_mapel: $('#subjectNama').val(),
                    kelas: $('#subjectKelas').val() || [],
                    urutan: $('#subjectUrutan').val()
                };
                if (!data.group_id || !data.nama_mapel) {
                    EModal.toast({ type: 'warning', title: 'Lengkapi data', message: 'Kelompok dan nama mapel wajib diisi.' });
                    return false;
                }
                this.api('subjects.php?action=subject-save', { method: 'POST', data }).done(() => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: 'Mata pelajaran disimpan.' });
                    this.loadGroups().always(() => this.loadSubjects());
                }).fail(xhr => this.showAjaxError(xhr));
                return false;
            }
        });
    },

    deleteGroup(id, name) {
        EModal.confirm({
            title: 'Hapus Kelompok Mapel',
            message: `Yakin hapus <strong>${this.escapeHtml(name)}</strong>? Mata pelajaran di dalamnya akan ikut terhapus.`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('subjects.php?action=group-delete', { method: 'POST', data: { id } }).done(() => {
                    EModal.toast({ type: 'success', title: 'Dihapus', message: 'Kelompok mapel dihapus.' });
                    this.loadGroups().always(() => this.loadSubjects());
                }).fail(xhr => this.showAjaxError(xhr));
            }
        });
    },

    deleteSubject(id, name) {
        EModal.confirm({
            title: 'Hapus Mata Pelajaran',
            message: `Yakin hapus <strong>${this.escapeHtml(name)}</strong>?`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('subjects.php?action=subject-delete', { method: 'POST', data: { id } }).done(() => {
                    EModal.toast({ type: 'success', title: 'Dihapus', message: 'Mata pelajaran dihapus.' });
                    this.loadGroups().always(() => this.loadSubjects());
                }).fail(xhr => this.showAjaxError(xhr));
            }
        });
    },

    api(endpoint, options = {}) {
        return $.ajax({
            url: this.state.apiUrl + endpoint,
            method: options.method || 'GET',
            data: options.data,
            processData: !(options.data instanceof FormData),
            contentType: options.data instanceof FormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
            headers: { Authorization: 'Bearer ' + this.state.token }
        }).fail(xhr => {
            if (xhr.status === 401) {
                EModal.info({ type: 'error', title: 'Sesi Berakhir', message: 'Silakan masuk kembali.', onClose: () => window.location.href = this.state.baseUrl + '#/login' });
            }
        });
    },

    showAjaxError(xhr) {
        if (xhr.status === 401) return;
        EModal.toast({
            type: 'error',
            title: 'Gagal',
            message: xhr.responseJSON?.message || 'Terjadi kesalahan sistem.'
        });
    },

    activeYearLabel() {
        const y = this.state.academicYear || {};
        if (!y.tahun_ajaran) return 'Tahun ajaran belum diatur';
        return `${y.tahun_ajaran} Semester ${y.semester || '-'}`;
    },

    tipeLabel(tipe) {
        const labels = { wajib: 'Mapel Wajib', pilihan: 'Mapel Pilihan', lainnya: 'Lainnya' };
        return labels[tipe] || 'Lainnya';
    },

    formatLetterNumber(format, number) {
        const cleanNumber = String(number || '1');
        if (format.includes('{nomor}')) return format.replace('{nomor}', cleanNumber);
        if (format.toLowerCase().includes('nomor')) return format.replace(/nomor/i, cleanNumber);
        return `${cleanNumber} / ${format.replace(/^\/\s*/, '')}`;
    },

    formatNumber(n) {
        return new Intl.NumberFormat('id-ID').format(n || 0);
    },

    formatDate(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    getInitials(name) {
        return name ? name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) : '?';
    },

    escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"]/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'
        }[m]));
    },

    escapeAttr(value) {
        return this.escapeHtml(value).replace(/'/g, '&#039;').replace(/`/g, '&#096;');
    },

    debounce(fn, wait) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    },

    // ======================== RESET DATA ========================

    renderReset($container) {
        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;color:#ef4444"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg> Reset Data E-Graduation</h3>
                    <span class="grad-year-pill">${this.activeYearLabel()}</span>
                </div>
                <div class="sp-card-body">
                    <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:16px; margin-bottom:20px;">
                        <strong style="color:#991b1b;">⚠️ Peringatan</strong>
                        <p style="color:#7f1d1d; font-size:14px; margin-top:4px;">Data yang di-reset tidak dapat dikembalikan. Pastikan Anda sudah membackup data sebelum melanjutkan. Data yang di-reset hanya untuk tahun ajaran aktif.</p>
                    </div>
                    <div id="resetItemsList" style="margin-bottom:20px;">
                        <div style="text-align:center; padding:30px; color:#94a3b8;">Memuat data...</div>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <button class="btn btn-sm" id="btnCheckAll" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;">Pilih Semua</button>
                        <button class="btn btn-sm" id="btnUncheckAll" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;">Batal Pilih</button>
                        <div style="flex:1"></div>
                        <button class="btn btn-sm" id="btnExecuteReset" style="background:#dc2626; color:#fff; display:inline-flex; align-items:center; gap:6px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Reset Data Terpilih
                        </button>
                    </div>
                </div>
            </div>
        `);

        this.loadResetInfo();

        $('#btnCheckAll').on('click', () => { $('#resetItemsList input[type=checkbox]').prop('checked', true); });
        $('#btnUncheckAll').on('click', () => { $('#resetItemsList input[type=checkbox]').prop('checked', false); });
        $('#btnExecuteReset').on('click', () => this.confirmReset());
    },

    loadResetInfo() {
        this.api('reset.php?action=info').done(res => {
            const items = res.data.items || [];
            const html = items.map(item => `
                <label class="grad-reset-item">
                    <input type="checkbox" value="${item.key}">
                    <div class="grad-reset-info">
                        <span class="grad-reset-label">${this.escapeHtml(item.label)}</span>
                        <span class="grad-reset-count">${item.count} data</span>
                    </div>
                </label>
            `).join('');
            $('#resetItemsList').html(html);
        }).fail(xhr => this.showAjaxError(xhr));
    },

    confirmReset() {
        const checked = [];
        $('#resetItemsList input[type=checkbox]:checked').each(function() {
            checked.push($(this).val());
        });
        if (!checked.length) {
            EModal.toast({ type: 'warning', title: 'Pilih Data', message: 'Centang minimal satu data yang ingin di-reset.' });
            return;
        }

        EModal.confirm({
            title: '⚠️ Konfirmasi Reset Data',
            message: `<p style="margin-bottom:12px;">Anda akan menghapus <strong>${checked.length}</strong> jenis data. Ketik <strong>RESET</strong> di bawah untuk mengkonfirmasi:</p>
                      <input type="text" id="resetConfirmInput" class="form-input" placeholder="Ketik RESET" style="width:100%; padding:10px 14px; border-radius:10px; border:2px solid #fecaca; text-align:center; font-weight:700; font-size:16px; letter-spacing:2px;">`,
            type: 'danger',
            confirmText: 'Reset Sekarang',
            onConfirm: () => {
                const confirmText = ($('#resetConfirmInput').val() || '').trim();
                if (confirmText !== 'RESET') {
                    EModal.toast({ type: 'warning', title: 'Gagal', message: 'Ketik RESET dengan benar untuk mengkonfirmasi.' });
                    return;
                }
                this.executeReset(checked, confirmText);
            }
        });
    },

    executeReset(targets, confirm) {
        this.api('reset.php?action=execute', {
            method: 'POST',
            data: { targets, confirm }
        }).done(res => {
            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
            this.loadResetInfo();
        }).fail(xhr => this.showAjaxError(xhr));
    },

    iconEdit() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
    },

    iconDelete() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
    }
};

$(document).ready(() => Graduation.init());
