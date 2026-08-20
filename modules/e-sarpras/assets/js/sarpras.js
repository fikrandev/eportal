/**
 * E-Sarpras Module — Single Page Application (SPA)
 * Elite Royal Blue — Premium School Infrastructure Management
 */

const Sarpras = {
    state: {
        currentRoute: 'dashboard',
        params: {},
        user: window.SARPRAS_CONFIG.user,
        school: window.SARPRAS_CONFIG.school,
        token: window.SARPRAS_CONFIG.token,
        baseUrl: window.SARPRAS_CONFIG.baseUrl,
        apiUrl: window.SARPRAS_CONFIG.baseUrl + 'modules/e-sarpras/api/'
    },

    escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    },

    permissionAliases: {
        dashboard_view: ['dashboard_view'],
        tanah_manage: ['tanah_manage'],
        bangunan_manage: ['bangunan_manage'],
        ruang_manage: ['ruang_manage'],
        sarpras_manage: ['sarpras_manage', 'sarana_manage', 'ahp_manage'],
        peminjaman_manage: ['peminjaman_manage'],
        perbaikan_manage: ['perbaikan_manage'],
        penghapusan_manage: ['penghapusan_manage'],
        report_view: ['report_view', 'laporan_view'],
        referensi_manage: ['referensi_manage'],
        roles_manage: ['roles_manage'],
        settings_manage: ['settings_manage']
    },

    getPermissions() {
        return Array.isArray(this.state.user.permissions) ? this.state.user.permissions : [];
    },

    hasPermission(permission) {
        const permissions = this.getPermissions();
        const candidates = this.permissionAliases[permission] || [permission];
        return candidates.some((key) => permissions.includes(key));
    },

    hasAnyPermission(permissions) {
        return permissions.some((permission) => this.hasPermission(permission));
    },

    getRoleLabel() {
        return this.state.user.custom_role_name || (this.state.user.sarpras_role || 'viewer_sarpras').replace(/_/g, ' ');
    },

    canAccessRoute(route) {
        const routePermissions = {
            dashboard: ['dashboard_view'],
            tanah: ['tanah_manage'],
            bangunan: ['bangunan_manage'],
            ruang: ['ruang_manage'],
            sarpras: ['sarpras_manage'],
            'master-sarpras': ['sarpras_manage'],
            'ahp-bhp': ['sarpras_manage'],
            angkutan: ['sarpras_manage'],
            buku: ['sarpras_manage'],
            'sarpras-detail': ['sarpras_manage'],
            perbaikan: ['perbaikan_manage'],
            periodik: ['sarpras_manage'],
            penyusutan: ['report_view'],
            barcode: ['sarpras_manage'],
            laporan: ['report_view'],
            referensi: ['referensi_manage'],
            roles: ['roles_manage'],
            penghapusan: ['penghapusan_manage', 'perbaikan_manage'],
            peminjaman: ['peminjaman_manage'],
            'settings-surat': ['settings_manage']
        };

        const isPJ = this.state.user.custom_role_name === 'Penanggung Jawab Ruangan' || (this.state.user.scoped_ruang_ids && this.state.user.scoped_ruang_ids.length > 0);
        const required = routePermissions[route];
        if (!required) return true;
        if (['sarpras', 'barcode', 'sarpras-detail'].includes(route) && isPJ) return true;
        return this.hasAnyPermission(required);
    },

    /**
     * Initialization
     */
    init() {
        this.bindEvents();
        this.renderSidebar();
        this.loadMyRooms(); // Load assigned rooms for PJ
        this.loadRouteFromHash();
        
        // Hide global loader
        setTimeout(() => {
            $('#globalLoader').addClass('hide');
            setTimeout(() => $('#globalLoader').remove(), 500);
        }, 800);
    },

    bindEvents() {
        window.addEventListener('hashchange', () => this.loadRouteFromHash());
        $('#menuToggle').on('click', () => this.toggleSidebar());
        $('#sidebarOverlay').on('click', () => this.toggleSidebar(false));

        // Global: Format Rupiah on input for all .sp-rupiah-input fields
        $(document).on('input', '.sp-rupiah-input', function() {
            let val = this.value.replace(/\./g, '').replace(/[^0-9]/g, '');
            if (val) {
                this.value = new Intl.NumberFormat('id-ID').format(parseInt(val, 10));
            }
        });

        // Global: Auto-calculate Luas for all .sp-calc-luas fields
        $(document).on('input', '.sp-calc-luas', function() {
            const $modal = $(this).closest('.emodal-body, .emodal-form-overlay');
            const p = parseFloat($modal.find('[id$="P"]').val()) || 0;
            const l = parseFloat($modal.find('[id$="L"]').val()) || 0;
            $modal.find('[id$="Luas"]').val((p * l).toFixed(2));
        });
    },

    /**
     * Sidebar Management
     */
    renderSidebar() {
        const u = this.state.user;
        const myRooms = this.state.myRooms || [];
        const canTanah = this.hasPermission('tanah_manage');
        const canBangunan = this.hasPermission('bangunan_manage');
        const canRuang = this.hasPermission('ruang_manage');
        const canSarpras = this.hasPermission('sarpras_manage');
        const canPeminjaman = this.hasPermission('peminjaman_manage');
        const canPerbaikan = this.hasPermission('perbaikan_manage');
        const canPenghapusan = this.hasAnyPermission(['penghapusan_manage', 'perbaikan_manage']);
        const canReport = this.hasPermission('report_view');
        const canReferensi = this.hasPermission('referensi_manage');
        const canRoles = this.hasPermission('roles_manage');
        const canSettings = this.hasPermission('settings_manage');
        const isPJ = u.custom_role_name === 'Penanggung Jawab Ruangan' || (u.scoped_ruang_ids && u.scoped_ruang_ids.length > 0);
        const hasAssetSection = (canTanah || canBangunan || canRuang || canSarpras) && !isPJ;

        let navHtml = '';

        // Tanggung Jawab Saya (For PJ)
        if (myRooms.length > 0) {
            navHtml += `
                <div class="sp-nav-group">
                    <div class="sp-nav-label">Tanggung Jawab Saya</div>
                    ${myRooms.map(r => `
                        <button class="sp-nav-item" data-route="sarpras" data-params='{"ruang_id":${r.id}}'>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            ${r.nama}
                        </button>
                    `).join('')}
                </div>
            `;
        }

        // Menu Utama (Standard for everyone)
        navHtml += `
            <div class="sp-nav-group">
                <div class="sp-nav-label">Menu Utama</div>
                <button class="sp-nav-item" data-route="dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Dashboard
                </button>
                ${hasAssetSection || isPJ ? `
                <div class="sp-nav-dropdown">
                    <button class="sp-nav-item dropdown-toggle" id="btnAsetDropdown" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                        Data Aset
                        <svg class="dd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:auto; width:16px; height:16px; transition:transform 0.2s"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="sp-nav-dropdown-menu" id="menuAsetDropdown" style="display:none; padding-left:35px; background: rgba(0,0,0,0.05); padding-top:4px; padding-bottom:4px; border-radius: 4px;">
                        ${canTanah ? `<button class="sp-nav-item" data-route="tanah" style="font-size:0.9rem; padding:8px 15px; min-height:36px; margin-bottom:2px;">Aset Tanah</button>` : ''}
                        ${canBangunan ? `<button class="sp-nav-item" data-route="bangunan" style="font-size:0.9rem; padding:8px 15px; min-height:36px; margin-bottom:2px;">Aset Bangunan</button>` : ''}
                        ${canRuang || isPJ ? `<button class="sp-nav-item" data-route="ruang" style="font-size:0.9rem; padding:8px 15px; min-height:36px;">Aset Ruangan</button>` : ''}
                    </div>
                </div>
                ` : ''}
                ${canSarpras ? `
                <button class="sp-nav-item" data-route="master-sarpras">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                    Data Sarpras
                </button>
                ` : ''}
            </div>
        `;
        navHtml += `
            <div class="sp-nav-group">
                <div class="sp-nav-label">Grup Aset Pintasan</div>
                <button class="sp-nav-item" data-route="ahp-bhp">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9m0 0l-2-2H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
                    AHP & BHP
                </button>
                <button class="sp-nav-item" data-route="angkutan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polyline points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    Alat Angkutan
                </button>
                <button class="sp-nav-item" data-route="buku">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    Koleksi Buku
                </button>
            </div>
        `;
        navHtml += `
            ${(canPeminjaman || canPerbaikan || canPenghapusan || canSarpras) ? `
            <div class="sp-nav-group">
                <div class="sp-nav-label">Operasional</div>
                ${canPeminjaman ? `
                <button class="sp-nav-item" data-route="peminjaman">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14h6"/><path d="M9 10h6"/><path d="M9 18h6"/></svg>
                    Peminjaman Barang
                </button>
                ` : ''}
                ${canPerbaikan ? `
                <button class="sp-nav-item" data-route="perbaikan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    Data Perbaikan
                </button>
                ` : ''}
                ${canPenghapusan && !isPJ ? `
                <button class="sp-nav-item" data-route="penghapusan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    Data Penghapusan
                </button>
                ` : ''}
                ${(canSarpras || isPJ) ? `
                <button class="sp-nav-item" data-route="barcode">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/></svg>
                    Label Barcode
                </button>
                ` : ''}
            </div>
            ` : ''}
        `;
        navHtml += `
            ${(canReport || isPJ) ? `
            <div class="sp-nav-group">
                <div class="sp-nav-label">Analisis & Laporan</div>
                ${canReport ? `
                <button class="sp-nav-item" data-route="penyusutan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
                    Penyusutan
                </button>
                ` : ''}
                    <button class="sp-nav-item" data-route="laporan">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Laporan
                    </button>
                </div>
            ` : ''}
            `;

        if ((canReferensi || canRoles || canSettings) && !isPJ) {
            navHtml += `
                <div class="sp-nav-group">
                    <div class="sp-nav-label">Pengaturan</div>
                    ${canReferensi ? `
                    <button class="sp-nav-item" data-route="referensi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        Data Referensi
                    </button>
                    ` : ''}
                    ${canRoles ? `
                    <button class="sp-nav-item" data-route="roles">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Akses Modul
                    </button>
                    ` : ''}
                    ${canSettings ? `
                    <button class="sp-nav-item" data-route="settings-surat">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Pengaturan Surat
                    </button>
                    ` : ''}
                </div>
            `;
        }

        $('#sidebarNav').html(navHtml);
        
        // Bind dropdown
        $('#btnAsetDropdown').on('click', function(e) {
            e.stopPropagation();
            const menu = $('#menuAsetDropdown');
            menu.slideToggle(200);
            $(this).find('.dd-icon').css('transform', menu.is(':visible') ? 'rotate(180deg)' : 'rotate(0deg)');
        });

        // Bind sidebar clicks
        $('.sp-nav-item:not(.dropdown-toggle)').on('click', (e) => {
            const $btn = $(e.currentTarget);
            const route = $btn.data('route');
            const params = $btn.data('params');
            this.navigate(route, params);
        });

        // Highlight parent if child is active
        const routeGroup = this.state.currentRoute.split('/')[0];
        if (['tanah', 'bangunan', 'ruang'].includes(routeGroup)) {
            $('#menuAsetDropdown').show();
            $('#btnAsetDropdown .dd-icon').css('transform', 'rotate(180deg)');
        }


        // Update user info
        $('#sidebarAvatar').text(this.getInitials(u.nama_lengkap));
        $('#sidebarUserName').text(u.nama_lengkap);
        $('#sidebarUserRole').text(this.getRoleLabel());
    },

    toggleSidebar(show = null) {
        if (show === null) {
            $('#spSidebar').toggleClass('show');
            $('#sidebarOverlay').toggleClass('show');
        } else if (show) {
            $('#spSidebar').addClass('show');
            $('#sidebarOverlay').addClass('show');
        } else {
            $('#spSidebar').removeClass('show');
            $('#sidebarOverlay').removeClass('show');
        }
    },

    loadMyRooms() {
        if (this.state.user.scoped_ruang_ids && this.state.user.scoped_ruang_ids.length > 0) {
            this.api('pj.php?action=my_rooms').done(res => {
                if (res.success) {
                    this.state.myRooms = res.data;
                    this.renderSidebar(); // Re-render to show rooms
                    
                    // If we are on dashboard, maybe re-render it too
                    if (this.state.currentRoute === 'dashboard') {
                        this.renderDashboard($('#mainContent'));
                    }
                }
            });
        }
    },

    doLogout() {
        EModal.confirm({
            title: 'Logout',
            message: 'Yakin ingin keluar dari E-Sarpras?',
            type: 'danger',
            confirmText: 'Ya, Logout',
            onConfirm: () => {
                // Call server to invalidate the session token in database
                $.ajax({
                    url: this.state.baseUrl + 'api/auth.php?action=logout',
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + this.state.token },
                    complete: () => {
                        // Remember to redirect back to sarpras after re-login
                        sessionStorage.setItem('eportal_intended_module', 'modules/e-sarpras/');
                        // Clear client-side storage
                        localStorage.removeItem('auth_token');
                        localStorage.removeItem('user');
                        // Redirect to portal login
                        window.location.href = this.state.baseUrl + '#/dashboard';
                    }
                });
            }
        });
    },

    /**
     * Routing System
     */
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
        $('.sp-nav-item').removeClass('active');
        $(`.sp-nav-item[data-route="${route.split('/')[0]}"]`).addClass('active');

        this.toggleSidebar(false);
        this.renderPage(route, params);
    },

    renderPage(route, params) {
        const $content = $('#mainContent');
        const $title = $('#pageTitle');
        const $breadcrumb = $('#breadcrumb');

        // Reset breadcrumb
        $breadcrumb.empty();

        if (!this.canAccessRoute(route)) {
            $title.text('Akses Ditolak');
            this.setBreadcrumbs([{ label: 'Akses Ditolak' }]);
            $content.html('<div class="sp-empty"><h3>Akses Ditolak</h3><p>Role Anda belum memiliki izin untuk membuka halaman ini.</p></div>');
            return;
        }

        switch (route) {
            case 'dashboard':
                $title.text('Dashboard');
                this.setBreadcrumbs([]);
                this.renderDashboard($content);
                break;
            case 'tanah':
                $title.text('Data Tanah');
                this.setBreadcrumbs([{ label: 'Tanah' }]);
                this.renderTanah($content);
                break;
            case 'bangunan':
                $title.text('Data Bangunan');
                if (params.tanah_id) {
                    this.api(`tanah.php?action=get&id=${params.tanah_id}`).done(res => {
                        this.setBreadcrumbs([{ label: 'Tanah', route: 'tanah' }, { label: res.data.nama }]);
                    });
                } else {
                    this.setBreadcrumbs([{ label: 'Bangunan' }]);
                }
                this.renderBangunan($content, params.tanah_id);
                break;
            case 'pj':
                // Redirection for legacy #/pj route to the new 3-level structure
                if (params.bangunan_id) {
                    this.navigate('ruang', { bangunan_id: params.bangunan_id });
                } else {
                    this.navigate('tanah');
                }
                break;
            case 'ruang':
                $title.text('Data Ruang');
                if (params.bangunan_id) {
                    this.api(`bangunan.php?action=get&id=${params.bangunan_id}`).done(res => {
                        this.setBreadcrumbs([
                            { label: 'Tanah', route: 'tanah' },
                            { label: res.data.tanah_nama, route: 'bangunan', params: { tanah_id: res.data.tanah_id } },
                            { label: res.data.nama }
                        ]);
                    });
                } else {
                    this.setBreadcrumbs([{ label: 'Ruang' }]);
                }
                this.renderRuang($content, params.bangunan_id);
                break;
            case 'sarpras':
                $title.text('Data Aset');
                if (params.ruang_id) {
                    this.api(`ruang.php?action=get&id=${params.ruang_id}`).done(res => {
                        this.setBreadcrumbs([
                            { label: 'Tanah', route: 'tanah' },
                            { label: res.data.tanah_nama, route: 'bangunan', params: { tanah_id: res.data.t_id } },
                            { label: res.data.bangunan_nama, route: 'ruang', params: { bangunan_id: res.data.bangunan_id } },
                            { label: res.data.nama }
                        ]);
                    });
                } else {
                    this.setBreadcrumbs([{ label: 'Data Aset' }]);
                }
                this.renderSarpras($content, params.ruang_id);
                break;
            case 'master-sarpras':
                $title.text('Data Sarpras (Katalog)');
                this.setBreadcrumbs([{ label: 'Data Sarpras' }]);
                this.renderMasterSarpras($content);
                break;
            case 'ahp-bhp':
                $title.text('Manajemen Alat & Bahan (AHP-BHP)');
                this.setBreadcrumbs([{ label: 'Grup Aset' }, { label: 'AHP-BHP' }]);
                this.renderAssetGroup($content, 'ahp-bhp');
                break;
            case 'angkutan':
                $title.text('Manajemen Alat Angkutan');
                this.setBreadcrumbs([{ label: 'Grup Aset' }, { label: 'Angkutan' }]);
                this.renderAssetGroup($content, 'angkutan');
                break;
            case 'buku':
                $title.text('Manajemen Koleksi Buku');
                this.setBreadcrumbs([{ label: 'Grup Aset' }, { label: 'Buku' }]);
                this.renderAssetGroup($content, 'buku');
                break;
            case 'sarpras-detail':
                $title.text('Detail Sarpras');
                this.setBreadcrumbs([{ label: 'Sarpras', route: 'sarpras' }, { label: 'Detail' }]);
                this.renderSarprasDetail($content, params.id);
                break;
            case 'perbaikan':
                $title.text('Data Perbaikan');
                this.setBreadcrumbs([{ label: 'Perbaikan' }]);
                this.renderPerbaikan($content);
                break;
            case 'periodik':
                $title.text('Update Periodik');
                this.setBreadcrumbs([{ label: 'Periodik' }]);
                this.renderPeriodik($content);
                break;
            case 'penyusutan':
                $title.text('Penyusutan Aset');
                this.setBreadcrumbs([{ label: 'Penyusutan' }]);
                this.renderPenyusutan($content);
                break;
            case 'barcode':
                $title.text('Label Barcode');
                this.setBreadcrumbs([{ label: 'Barcode' }]);
                this.renderBarcodeManager($content);
                break;
            case 'laporan':
                $title.text('Laporan Sarpras');
                this.setBreadcrumbs([{ label: 'Laporan' }]);
                this.renderLaporan($content);
                break;
            case 'kategori':
                // Retro-compatibility redirect
                this.navigate('referensi', { tab: 'kategori' });
                return;
            case 'referensi':
                $title.text('Data Referensi');
                this.setBreadcrumbs([{ label: 'Referensi' }]);
                this.renderReferensi($content);
                break;
            case 'roles':
                $title.text('Akses Modul');
                this.setBreadcrumbs([{ label: 'Roles' }]);
                this.renderRoles($content);
                break;
            case 'penghapusan':
                $title.text('Data Penghapusan Sarpras');
                this.setBreadcrumbs([{ label: 'Penghapusan' }]);
                this.renderPenghapusan($content);
                break;
            case 'peminjaman':
                $title.text('Data Peminjaman Barang');
                this.setBreadcrumbs([{ label: 'Peminjaman' }]);
                this.renderPeminjaman($content);
                break;
            case 'settings-surat':
                $title.text('Pengaturan Surat');
                this.setBreadcrumbs([{ label: 'Pengaturan Surat' }]);
                this.renderSettingsSurat($content);
                break;
            default:
                $content.html('<div class="sp-empty"><h3>Halaman Tidak Ditemukan</h3></div>');
        }
    },

    setBreadcrumbs(items) {
        const $breadcrumb = $('#breadcrumb');
        $breadcrumb.empty();
        
        // Home icon/Dashboard link as root
        $breadcrumb.append(`<a href="#/dashboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="margin-right:4px; vertical-align:text-bottom"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></a>`);
        
        items.forEach((item, index) => {
            $breadcrumb.append('<span class="sp-sep">/</span>');
            if (item.route) {
                const query = item.params ? `?${$.param(item.params)}` : '';
                $breadcrumb.append(`<a href="#/${item.route}${query}">${item.label}</a>`);
            } else {
                $breadcrumb.append(`<span class="${index === items.length - 1 ? 'active' : ''}">${item.label}</span>`);
            }
        });
    },

    /**
     * PAGE: DASHBOARD
     */
    renderDashboard($container) {
        const u = this.state.user;
        const myRooms = this.state.myRooms || [];
        let welcomeHtml = '';

        if (myRooms.length > 0) {
            welcomeHtml = `
                <div class="sp-card sp-slide-up" style="background: linear-gradient(135deg, #1565C0, #1E88E5); color: white; border: none; margin-bottom: 24px;">
                    <div class="sp-card-body" style="display: flex; align-items: center; gap: 20px;">
                        <div style="width: 52px; height: 52px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink:0;">
                             <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </div>
                        <div style="flex:1">
                            <h2 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 2px;">Selamat Datang, ${u.nama_lengkap}!</h2>
                            <p style="opacity: 0.9; font-size: 0.8rem;">Anda bertanggung jawab atas <strong>${myRooms.length} Ruangan</strong> (${myRooms.map(r => r.nama).join(', ')}).</p>
                        </div>
                        <div>
                            <button class="btn btn-sm" style="background:white; color:var(--primary); font-weight:700; border:none; padding:8px 16px;" onclick="Sarpras.navigate('sarpras', {ruang_id: ${myRooms[0].id}})">Buka Ruangan</button>
                        </div>
                    </div>
                </div>
            `;
        }

        $container.html(welcomeHtml + `
            <div class="sp-stats-grid">
                <div class="sp-stat-card skeleton-stat"></div>
                <div class="sp-stat-card skeleton-stat"></div>
                <div class="sp-stat-card skeleton-stat"></div>
                <div class="sp-stat-card skeleton-stat"></div>
            </div>
            <div class="sp-dashboard-grid">
                <div class="sp-card full">
                    <div class="sp-card-header">
                        <h3>Kondisi Seluruh Sarpras</h3>
                        <div class="sp-toolbar"><span class="badge badge-info" id="totalAssetCounter">0 Unit</span></div>
                    </div>
                    <div class="sp-card-body" id="kondisiOverview">
                        <div class="skeleton" style="height:140px"></div>
                    </div>
                </div>
                <div class="sp-card">
                    <div class="sp-card-header">
                        <h3>Total Nilai Perolehan</h3>
                        <div style="font-size:0.75rem; color:var(--text-muted)">Berdasarkan Kategori</div>
                    </div>
                    <div class="sp-card-body" id="valueByCategory"><div class="skeleton" style="height:200px"></div></div>
                </div>
                <div class="sp-card">
                    <div class="sp-card-header"><h3>Perbaikan Terbaru</h3></div>
                    <div class="sp-card-body" id="recentRepairs"><div class="skeleton" style="height:200px"></div></div>
                </div>
                <div class="sp-card">
                    <div class="sp-card-header"><h3>Aset Habis Masa Manfaat</h3></div>
                    <div class="sp-card-body" id="expiredAssets"><div class="skeleton" style="height:200px"></div></div>
                </div>
            </div>
        `);

        this.api('dashboard.php?action=stats').done(res => {
            if (!res.success) return;
            const s = res.data;

            // Stats Cards
            $container.find('.sp-stats-grid').html(`
                <div class="sp-stat-card sp-fade-in">
                    <div class="sp-stat-icon" style="background:#E3F2FD; color:#1565C0">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
                    </div>
                    <div class="sp-stat-info">
                        <h4>${s.tanah.total}</h4>
                        <p>Total Tanah</p>
                    </div>
                </div>
                <div class="sp-stat-card sp-fade-in" style="animation-delay:0.1s">
                    <div class="sp-stat-icon" style="background:#E8F5E9; color:#2E7D32">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9h1"/><path d="M9 13h1"/></svg>
                    </div>
                    <div class="sp-stat-info">
                        <h4>${s.bangunan.total}</h4>
                        <p>Total Gedung</p>
                    </div>
                </div>
                <div class="sp-stat-card sp-fade-in" style="animation-delay:0.2s">
                    <div class="sp-stat-icon" style="background:#FFF3E0; color:#EF6C00">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </div>
                    <div class="sp-stat-info">
                        <h4>${s.ruang.total}</h4>
                        <p>Total Ruangan</p>
                    </div>
                </div>
                <div class="sp-stat-card sp-fade-in" style="animation-delay:0.3s">
                    <div class="sp-stat-icon" style="background:#F3E5F5; color:#7B1FA2">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M2 12h20"/><circle cx="12" cy="12" r="10"/></svg>
                    </div>
                    <div class="sp-stat-info">
                        <h4 style="font-size:1.1rem">Rp ${this.formatCompact(s.sarpras.total_nilai)}</h4>
                        <p>Nilai Aset</p>
                    </div>
                </div>
            `);

            $('#totalAssetCounter').text(`${s.sarpras.total_unit} Unit`);

            // Conditions Donut
            const total = s.sarpras.total_unit || 1;
            const pb = (s.sarpras.baik / total) * 100;
            const prr = (s.sarpras.rusak_ringan / total) * 100;
            const prb = (s.sarpras.rusak_berat / total) * 100;

            $('#kondisiOverview').html(`
                <div class="sp-kondisi-chart">
                    <div class="sp-chart-main">
                        <div class="sp-donut">
                            <svg viewBox="0 0 36 36">
                              <circle cx="18" cy="18" r="16" fill="none" stroke="#FEE2E2" stroke-width="4"></circle>
                              <circle cx="18" cy="18" r="16" fill="none" stroke="#FEF3C7" stroke-width="4" stroke-dasharray="${prr+pb} 100" stroke-dashoffset="-${pb}"></circle>
                              <circle cx="18" cy="18" r="16" fill="none" stroke="#D1FAE5" stroke-width="4" stroke-dasharray="${pb} 100"></circle>
                            </svg>
                            <div class="sp-donut-center">
                                <strong>${Math.round(pb)}%</strong>
                                <small>Kondisi Baik</small>
                            </div>
                        </div>
                    </div>
                    <div class="sp-legend">
                        <div class="sp-legend-item">
                            <div class="sp-legend-label">
                                <div class="sp-legend-dot" style="background:#10B981"></div>
                                <div><strong>Kondisi Baik</strong><p>${s.sarpras.baik} Unit</p></div>
                            </div>
                        </div>
                        <div class="sp-legend-item">
                            <div class="sp-legend-label">
                                <div class="sp-legend-dot" style="background:#F59E0B"></div>
                                <div><strong>Rusak Ringan</strong><p>${s.sarpras.rusak_ringan} Unit</p></div>
                            </div>
                        </div>
                        <div class="sp-legend-item">
                            <div class="sp-legend-label">
                                <div class="sp-legend-dot" style="background:#EF4444"></div>
                                <div><strong>Rusak Berat</strong><p>${s.sarpras.rusak_berat} Unit</p></div>
                            </div>
                        </div>
                    </div>
                </div>
            `);

            // Value by Category
            if (s.per_kategori.length) {
                let catHtml = s.per_kategori.map(c => {
                    const pct = (c.jumlah_unit / total) * 100;
                    return `
                        <div class="sp-cat-bar-item">
                            <div class="sp-cat-info">
                                <span>${c.kategori}</span>
                                <span>${c.jumlah_unit} Unit</span>
                            </div>
                            <div class="sp-progress-bg"><div class="sp-progress-bar" style="width:${pct}%"></div></div>
                        </div>
                    `;
                }).join('');
                $('#valueByCategory').html(catHtml);
            } else {
                $('#valueByCategory').html('<div class="sp-empty">Belum ada data kategori.</div>');
            }

            // Recent Repairs
            if (s.perbaikan_terbaru.length) {
                let repairHtml = s.perbaikan_terbaru.map(p => `
                    <div class="sp-legend-item">
                        <div class="sp-legend-label">
                            <div style="font-weight:600">${p.nama_sarpras}</div>
                            <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px">${p.status} &bull; ${this.formatDate(p.tanggal)}</div>
                        </div>
                        <div class="sp-nilai sm">Rp ${this.formatNumber(p.biaya)}</div>
                    </div>
                `).join('');
                $('#recentRepairs').html(repairHtml);
            } else {
                $('#recentRepairs').html('<div class="sp-empty" style="padding:20px">Tidak ada perbaikan.</div>');
            }

            // Expired Assets
            if (s.aset_habis_manfaat.length) {
                let assetsHtml = s.aset_habis_manfaat.map(a => `
                    <div class="sp-legend-item">
                        <div class="sp-legend-label">
                            <div style="flex:1">
                                <div style="font-weight:600">${a.nama}</div>
                                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px">${a.ruang_nama || 'Tanpa Ruangan'} &bull; Perolehan ${a.tahun_perolehan || '-'}</div>
                            </div>
                        </div>
                        <div class="badge badge-danger">${parseInt(a.tahun_perolehan || 0) + parseInt(a.masa_manfaat_tahun || 0)}</div>
                    </div>
                `).join('');
                $('#expiredAssets').html(assetsHtml);
            } else {
                $('#expiredAssets').html('<div class="sp-empty" style="padding:20px">Semua aset masih dalam masa manfaat.</div>');
            }
        });
    },

    /**
     * PAGE: DATA ASET (Accordion Hierarchy)
     * Tanah → Bangunan → PJ → Ruang
     */
    renderTanah($container) {
        const canEditTanah = this.hasPermission('tanah_manage');
        const canAddBangunan = this.hasPermission('bangunan_manage');
        const canDeleteTanah = this.hasPermission('tanah_manage');

        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg> Data Aset Sekolah</h3>
                    <div class="sp-toolbar">
                        ${canEditTanah ? `<button class="btn btn-primary btn-sm" onclick="Sarpras.formTanah()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Tanah
                        </button>` : ''}
                    </div>
                </div>
                <div class="sp-card-body" style="padding:16px">
                    <div id="tanahAccordion"><div class="sp-acc-loading"><div class="spinner"></div> Memuat data...</div></div>
                </div>
            </div>
        `);

        this.api('tanah.php?action=list&per_page=50').done(res => {
            if (!res.success) return;
            if (!res.data.data.length) {
                $('#tanahAccordion').html('<div class="sp-acc-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg><p>Belum ada data tanah. Klik "Tambah Tanah" untuk mulai.</p></div>');
                return;
            }

            const html = res.data.data.map(t => `
                <div class="sp-acc-item level-tanah" id="acc-tanah-${t.id}">
                    <div class="sp-acc-header" onclick="Sarpras.toggleAccordion('tanah', ${t.id})">
                        <div class="sp-acc-icon tanah">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
                        </div>
                        <div class="sp-acc-info">
                            <div class="sp-acc-title">${t.nama}</div>
                            <div class="sp-acc-subtitle">
                                <span>📍 ${t.lokasi || '-'}</span>
                                <span class="dot"></span>
                                <span>${t.luas_m2} m²</span>
                                <span class="dot"></span>
                                <span>Rp ${this.formatNumber(t.harga_perolehan)}</span>
                            </div>
                        </div>
                        <div class="sp-acc-badges">
                            <span class="badge badge-info">${t.jumlah_bangunan} Bangunan</span>
                        </div>
                        <div class="sp-acc-chevron">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                    <div class="sp-acc-body" id="acc-tanah-body-${t.id}">
                        <div class="sp-acc-profile">
                            <div class="sp-acc-profile-item"><div class="sp-acc-profile-label">Kode</div><div class="sp-acc-profile-value">${t.kode_tanah || '-'}</div></div>
                            <div class="sp-acc-profile-item"><div class="sp-acc-profile-label">Ukuran</div><div class="sp-acc-profile-value">${t.panjang_m || '?'} x ${t.lebar_m || '?'} m</div></div>
                            <div class="sp-acc-profile-item"><div class="sp-acc-profile-label">Kepemilikan</div><div class="sp-acc-profile-value">${t.status_kepemilikan || '-'}</div></div>
                            <div class="sp-acc-profile-item"><div class="sp-acc-profile-label">Sertifikat</div><div class="sp-acc-profile-value">${t.no_sertifikat || '-'}</div></div>
                            <div class="sp-acc-profile-item"><div class="sp-acc-profile-label">Thn Perolehan</div><div class="sp-acc-profile-value">${t.tahun_perolehan || '-'}</div></div>
                            <div class="sp-acc-profile-item"><div class="sp-acc-profile-label">Nilai</div><div class="sp-acc-profile-value" style="color:var(--primary)">Rp ${this.formatNumber(t.harga_perolehan)}</div></div>
                        </div>
                        <div class="sp-acc-actions">
                            <div class="sp-acc-actions-left">
                                ${canAddBangunan ? `<button class="sp-acc-btn primary" onclick="event.stopPropagation();Sarpras.formBangunan(null, ${t.id})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <span class="btn-label">Tambah Bangunan</span>
                                </button>` : ''}
                            </div>
                            <div class="sp-acc-actions-right">
                                ${canEditTanah ? `<button class="sp-acc-btn edit" onclick="event.stopPropagation();Sarpras.formTanah(${t.id})" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>` : ''}
                                ${canDeleteTanah ? `<button class="sp-acc-btn danger" onclick="event.stopPropagation();Sarpras.delTanah(${t.id}, '${t.nama}')" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>` : ''}
                            </div>
                        </div>
                        <div class="sp-acc-children" id="acc-tanah-children-${t.id}">
                            <div class="sp-acc-loading"><div class="spinner"></div> Memuat bangunan...</div>
                        </div>
                    </div>
                </div>
            `).join('');

            $('#tanahAccordion').html(html);
        });
    },

    /**
     * Toggle any accordion level
     */
    toggleAccordion(level, id) {
        const $item = $(`#acc-${level}-${id}`);
        const wasOpen = $item.hasClass('open');

        if (wasOpen) {
            $item.removeClass('open');
            return;
        }

        // Open this one
        $item.addClass('open');

        // Lazy-load children if not loaded yet
        const $children = $(`#acc-${level}-children-${id}`);
        if ($children.data('loaded')) return;

        switch (level) {
            case 'tanah':
                this.loadBangunanAccordion(id, $children);
                break;
            case 'bangunan':
                this.loadRuangCards(id, $children);
                break;
        }
    },

    /**
     * Load Bangunan accordion inside Tanah
     */
    loadBangunanAccordion(tanahId, $container) {
        const canEditBangunan = this.hasPermission('bangunan_manage');
        const canAddRuang = this.hasPermission('ruang_manage');
        const canDeleteBangunan = this.hasPermission('bangunan_manage');

        this.api(`bangunan.php?action=list&tanah_id=${tanahId}`).done(res => {
            $container.data('loaded', true);
            if (!res.data.length) {
                $container.html('<div class="sp-acc-empty"><p>Belum ada bangunan di tanah ini.</p></div>');
                return;
            }

            const html = res.data.map(b => `
                <div class="sp-acc-item level-bangunan" id="acc-bangunan-${b.id}">
                    <div class="sp-acc-header" onclick="Sarpras.toggleAccordion('bangunan', ${b.id})">
                        <div class="sp-acc-icon bangunan">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9h1"/><path d="M9 13h1"/></svg>
                        </div>
                        <div class="sp-acc-info">
                            <div class="sp-acc-title">${b.nama}</div>
                            <div class="sp-acc-subtitle">
                                <span>${b.luas_m2} m²</span>
                                <span class="dot"></span>
                                <span>${b.jumlah_lantai} Lantai</span>
                                <span class="dot"></span>
                                <span class="sp-kondisi"><div class="sp-kondisi-dot ${b.kondisi === 'Baik' ? 'baik' : (b.kondisi === 'Rusak Ringan' ? 'rr' : 'rb')}"></div> ${b.kondisi}</span>
                            </div>
                        </div>
                        <div class="sp-acc-badges">
                            <span class="badge badge-info">${b.jumlah_ruang} Ruang</span>
                        </div>
                        <div class="sp-acc-chevron">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                    <div class="sp-acc-body" id="acc-bangunan-body-${b.id}">
                        <div class="sp-acc-profile">
                            <div class="sp-acc-profile-item"><div class="sp-acc-profile-label">Kode</div><div class="sp-acc-profile-value">${b.kode_bangunan || '-'}</div></div>
                            <div class="sp-acc-profile-item"><div class="sp-acc-profile-label">Ukuran</div><div class="sp-acc-profile-value">${b.panjang_m || '?'} x ${b.lebar_m || '?'} m</div></div>
                            <div class="sp-acc-profile-item"><div class="sp-acc-profile-label">Thn Dibangun</div><div class="sp-acc-profile-value">${b.tahun_dibangun || '-'}</div></div>
                            <div class="sp-acc-profile-item"><div class="sp-acc-profile-label">Nilai</div><div class="sp-acc-profile-value" style="color:var(--primary)">Rp ${this.formatNumber(b.harga_perolehan)}</div></div>
                        </div>
                        <div class="sp-acc-actions">
                            <div class="sp-acc-actions-left">
                                ${canAddRuang ? `<button class="sp-acc-btn primary" onclick="event.stopPropagation();Sarpras.formRuang(null, ${b.id})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <span class="btn-label">Tambah Ruang</span>
                                </button>` : ''}
                            </div>
                            <div class="sp-acc-actions-right">
                                ${canEditBangunan ? `<button class="sp-acc-btn edit" onclick="event.stopPropagation();Sarpras.formBangunan(${b.id}, ${tanahId})" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>` : ''}
                                ${canDeleteBangunan ? `<button class="sp-acc-btn danger" onclick="event.stopPropagation();Sarpras.delBangunan(${b.id}, '${b.nama}', ${tanahId})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>` : ''}
                            </div>
                        </div>
                        <div class="sp-acc-children" id="acc-bangunan-children-${b.id}">
                            <div class="sp-acc-loading"><div class="spinner"></div> Memuat ruang...</div>
                        </div>
                    </div>
                </div>
            `).join('');

            $container.html(html);
        });
    },



    /**
     * Load Ruang cards inside Bangunan (final level — not accordion)
     */
    loadRuangCards(bangunanId, $container) {
        const canManageRuang = this.hasPermission('ruang_manage');

        this.api(`ruang.php?action=list&bangunan_id=${bangunanId}`).done(res => {
            $container.data('loaded', true);
            if (!res.data.length) {
                $container.html('<div class="sp-acc-empty"><p>Belum ada ruang di bangunan ini.</p></div>');
                return;
            }

            const html = res.data.map(r => {
                const luas = (parseFloat(r.panjang_m || 0) * parseFloat(r.lebar_m || 0)).toFixed(1);
                const kondisiClass = r.kondisi === 'Baik' ? 'baik' : (r.kondisi === 'Rusak Ringan' ? 'rr' : 'rb');
                const pjLabel = r.pj_nama ? `<span>👤 ${r.pj_nama}</span><span>•</span>` : '';
                return `
                    <div class="sp-ruang-card">
                        <div class="sp-ruang-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </div>
                        <div class="sp-ruang-info" onclick="Sarpras.navigate('sarpras', {ruang_id: ${r.id}})" style="cursor:pointer">
                            <div class="sp-ruang-name">${r.nama}</div>
                            <div class="sp-ruang-meta">
                                ${pjLabel}
                                <span>${r.kode_ruang}</span>
                                <span>•</span>
                                <span>${r.jenis_ruang}</span>
                                <span>•</span>
                                <span>${luas} m²</span>
                                <span>•</span>
                                <span class="sp-kondisi"><div class="sp-kondisi-dot ${kondisiClass}"></div> ${r.kondisi}</span>
                                <span>•</span>
                                <span>${r.total_unit || 0} Aset</span>
                            </div>
                        </div>
                        <div class="sp-ruang-actions" onclick="event.stopPropagation()">
                            <button class="sp-acc-btn edit" onclick="Sarpras.navigate('sarpras', {ruang_id: ${r.id}})" title="Lihat Aset">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button class="sp-acc-btn edit primary" onclick="Sarpras.printReport('detail-per-ruang', {ruang_id: ${r.id}})" title="Cetak Laporan">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </button>
                            ${canManageRuang ? `<button class="sp-acc-btn edit" onclick="Sarpras.formRuang(${r.id}, ${bangunanId})" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>` : ''}
                            ${canManageRuang ? `<button class="sp-acc-btn danger" onclick="Sarpras.delRuang(${r.id}, '${r.nama}', ${bangunanId})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>` : ''}
                        </div>
                    </div>
                `;
            }).join('');

            $container.html(html);
        });
    },

    /**
     * Refresh accordion children after CRUD operations
     */
    refreshAccordionLevel(level, parentId) {
        const $children = $(`#acc-${level}-children-${parentId}`);
        $children.removeData('loaded');
        $children.html(`<div class="sp-acc-loading"><div class="spinner"></div> Memuat...</div>`);
        
        switch (level) {
            case 'tanah':
                this.loadBangunanAccordion(parentId, $children);
                break;
            case 'bangunan':
                this.loadRuangCards(parentId, $children);
                break;
        }
    },

    /**
     * FORM: TANAH
     */
    formTanah(id = null) {
        const isEdit = id !== null;
        EModal.form({
            title: isEdit ? 'Edit Data Tanah' : 'Tambah Tanah Baru',
            form: `
                <input type="hidden" name="id" id="f_tnId" value="${id || ''}">
                <div class="sp-form-row">
                    <div class="form-group"><label>Nama Tanah</label><input class="form-input" id="f_tnNama" required placeholder="Contoh: Tanah Kampus A"></div>
                    <div class="form-group"><label>Kode Tanah (Auto)</label><input class="form-input" id="f_tnKode" placeholder="Otomatis jika kosong"></div>
                </div>
                <div class="form-group"><label>Lokasi / Alamat</label><textarea class="form-input" id="f_tnLokasi" required rows="2"></textarea></div>
                <div class="form-group">
                    <label style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                        Titik Koordinat Tanah
                        <button type="button" class="btn btn-sm btn-ghost" onclick="Sarpras.openMapPicker()" style="padding:2px 8px; font-size:11px; color:var(--primary); border:1px solid currentColor;">
                            📍 Buka Peta Koordinat
                        </button>
                    </label>
                    <div class="sp-form-row" style="gap:10px; margin-bottom:0">
                        <input class="form-input" id="f_tnLintang" placeholder="Lintang (Contoh: -0.8988604)">
                        <input class="form-input" id="f_tnBujur" placeholder="Bujur (Contoh: 119.876790)">
                    </div>
                </div>
                <div class="sp-form-row three">
                    <div class="form-group"><label>Panjang (m)</label><input type="number" class="form-input sp-calc-luas" id="f_tnP"></div>
                    <div class="form-group"><label>Lebar (m)</label><input type="number" class="form-input sp-calc-luas" id="f_tnL"></div>
                    <div class="form-group"><label>Luas (m²)</label><input type="number" class="form-input" id="f_tnLuas" required readonly style="background:#f8f9fa"></div>
                </div>
                <div class="sp-form-row">
                    <div class="form-group"><label>Status Kepemilikan</label><select class="form-select" id="f_tnStat"><option>Milik Sendiri</option><option>Sewa</option><option>Pinjam Pakai</option></select></div>
                    <div class="form-group"><label>No. Sertifikat</label><input class="form-input" id="f_tnSert"></div>
                </div>
                <div class="sp-form-row three">
                    <div class="form-group"><label>Thn Perolehan</label><input type="number" class="form-input" id="f_tnThn" value="${new Date().getFullYear()}"></div>
                    <div class="form-group"><label>Harga (Rp)</label><input type="text" class="form-input sp-rupiah-input" id="f_tnHarga" placeholder="0"></div>
                    <div class="form-group"><label>Asal Dana</label><select class="form-select" id="f_tnAsal"><option value="">-- Pilih --</option></select></div>
                </div>
                <div class="form-group"><label>Keterangan</label><textarea class="form-input" id="f_tnKet" rows="2"></textarea></div>
                ${isEdit ? `
                    <div class="form-group">
                        <label>Foto Tanah (Max 5)</label>
                        <div id="tnFotoList" class="sp-foto-grid"></div>
                    </div>
                ` : ''}
            `,
            onOpen: () => {
                // Fetch Asal Dana
                this.api('referensi.php?action=list&kategori=asal_dana').done(res => {
                    res.data.forEach(r => $('#f_tnAsal').append(`<option value="${r.nama}">${r.nama}</option>`));
                    if (isEdit) $('#f_tnAsal').val(this._tempAsal);
                });

                if (isEdit) {
                    this.api(`tanah.php?action=get&id=${id}`).done(res => {
                        const d = res.data;
                        $('#f_tnNama').val(d.nama); $('#f_tnKode').val(d.kode_tanah); $('#f_tnLokasi').val(d.lokasi);
                        $('#f_tnLuas').val(d.luas_m2); $('#f_tnP').val(d.panjang_m); $('#f_tnL').val(d.lebar_m);
                        $('#f_tnHarga').val(this.formatNumber(d.harga_perolehan)); this._tempAsal = d.asal_anggaran;
                        $('#f_tnThn').val(d.tahun_perolehan); $('#f_tnStat').val(d.status_kepemilikan);
                        $('#f_tnSert').val(d.no_sertifikat); $('#f_tnKet').val(d.keterangan);
                        $('#f_tnLintang').val(d.lintang); $('#f_tnBujur').val(d.bujur);
                        this.renderTanahFotos(d.fotos, id);
                    });
                }
            },
            onConfirm: () => {
                const data = {
                    id: $('#f_tnId').val(), nama: $('#f_tnNama').val(), kode_tanah: $('#f_tnKode').val(), lokasi: $('#f_tnLokasi').val(),
                    luas_m2: $('#f_tnLuas').val(), panjang_m: $('#f_tnP').val(), lebar_m: $('#f_tnL').val(),
                    harga_perolehan: this.unformatNumber($('#f_tnHarga').val()), asal_anggaran: $('#f_tnAsal').val(),
                    tahun_perolehan: $('#f_tnThn').val(), status_kepemilikan: $('#f_tnStat').val(),
                    no_sertifikat: $('#f_tnSert').val(), keterangan: $('#f_tnKet').val(),
                    lintang: $('#f_tnLintang').val(), bujur: $('#f_tnBujur').val()
                };
                if (!data.nama || !data.luas_m2) return false;
                
                const act = isEdit ? 'update' : 'create';
                this.api(`tanah.php?action=${act}`, { method: 'POST', data }).done(res => {
                    if (res.success) {
                        EModal.closeAll();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                        this.renderTanah($('#mainContent'));
                    }
                }).fail(xhr => { EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message }); });
                return false;
            }
        });
    },

    renderTanahFotos(fotos, tanahId) {
        let html = fotos.map(f => `
            <div class="sp-foto-item">
                <img src="${this.state.baseUrl}${f.foto_path}">
                <button class="sp-foto-delete" onclick="Sarpras.delTanahFoto(${f.id}, ${tanahId})">&times;</button>
            </div>
        `).join('');
        
        if (fotos.length < 5) {
            html += `<div class="sp-foto-upload" onclick="$('#tnFileInp').click()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                <span>Upload</span>
            </div>
            <input type="file" id="tnFileInp" style="display:none" accept="image/*" onchange="Sarpras.doTanahUpload(${tanahId}, this)">`;
        }
        $('#tnFotoList').html(html);
    },

    doTanahUpload(tanahId, input) {
        if (!input.files[0]) return;
        const fd = new FormData();
        fd.append('tanah_id', tanahId);
        fd.append('foto', input.files[0]);
        
        const loader = EModal.loading('Mengupload foto...');
        this.api('tanah.php?action=upload-foto', { method: 'POST', data: fd }).done(res => {
            EModal.close(loader);
            if (res.success) {
                this.api(`tanah.php?action=get-foto&id=${tanahId}`).done(res2 => this.renderTanahFotos(res2.data, tanahId));
            }
        }).fail(() => EModal.close(loader));
    },

    delTanahFoto(fotoId, tanahId) {
        EModal.confirm({
            title: 'Hapus Foto', message: 'Yakin hapus foto ini?', type: 'danger',
            onConfirm: () => {
                this.api('tanah.php?action=delete-foto', { method: 'POST', data: { foto_id: fotoId } }).done(() => {
                    this.api(`tanah.php?action=get-foto&id=${tanahId}`).done(res => this.renderTanahFotos(res.data, tanahId));
                });
            }
        });
    },

    delTanah(id, name) {
        EModal.confirm({
            title: 'Hapus Data Tanah', message: `Yakin ingin menghapus <strong>${name}</strong>? Seluruh data bangunan dan ruang di dalamnya akan ikut terhapus.`,
            type: 'danger', onConfirm: () => {
                this.api('tanah.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    this.renderTanah($('#mainContent'));
                    EModal.toast({ type: 'success', title: 'Data Dihapus' });
                });
            }
        });
    },

    /**
     * LEAFLET MAP PICKER
     */
    openMapPicker() {
        if (!window.L) {
            const loader = EModal.loading('Memuat Peta Koordinat...');
            $('head').append('<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />');
            $.getScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', () => {
                EModal.close(loader);
                this.showMapModal();
            });
        } else {
            this.showMapModal();
        }
    },

    showMapModal() {
        const currentLat = $('#f_tnLintang').val() || -0.8988604;
        const currentLng = $('#f_tnBujur').val() || 119.8767931;

        EModal.form({
            title: 'Pilih Titik Koordinat Tanah',
            confirmText: 'Simpan Titik Ini',
            form: `
                <div style="padding:15px; text-align:center;">
                    <p style="margin-bottom:10px; font-size:13px; color:var(--text-muted)">Geser peta atau klik pada lokasi untuk menentukan titik koordinat yang tepat.</p>
                    <div id="sarprasMap" style="height: 400px; width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border:1px solid var(--border-color); z-index:1;"></div>
                    <div style="margin-top:15px; display:flex; gap:10px; justify-content:center; align-items:center;">
                        <div>
                            <div style="font-size:11px; color:var(--text-muted)">Lintang</div>
                            <input type="text" id="mapLatDisplay" class="form-input" readonly value="${currentLat}" style="width:120px; text-align:center; background:#f8f9fa;">
                        </div>
                        <div>
                            <div style="font-size:11px; color:var(--text-muted)">Bujur</div>
                            <input type="text" id="mapLngDisplay" class="form-input" readonly value="${currentLng}" style="width:120px; text-align:center; background:#f8f9fa;">
                        </div>
                    </div>
                </div>
            `,
            onOpen: () => {
                setTimeout(() => {
                    const map = L.map('sarprasMap').setView([currentLat, currentLng], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(map);

                    const marker = L.marker([currentLat, currentLng], {draggable: true}).addTo(map);

                    function updateDisplay(latlng) {
                        $('#mapLatDisplay').val(latlng.lat.toFixed(7));
                        $('#mapLngDisplay').val(latlng.lng.toFixed(7));
                    }

                    marker.on('dragend', function(e) { updateDisplay(marker.getLatLng()); });
                    map.on('click', function(e) { marker.setLatLng(e.latlng); updateDisplay(e.latlng); });

                    if (!$('#f_tnLintang').val() && navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition((pos) => {
                            const latlng = [pos.coords.latitude, pos.coords.longitude];
                            map.setView(latlng, 17);
                            marker.setLatLng(latlng);
                            updateDisplay({lat: pos.coords.latitude, lng: pos.coords.longitude});
                        }, () => {}, {timeout: 5000});
                    }
                    
                    // Invalidate size to ensure map renders within EModal perfectly 
                    map.invalidateSize();
                }, 300);
            },
            onConfirm: () => {
                $('#f_tnLintang').val($('#mapLatDisplay').val());
                $('#f_tnBujur').val($('#mapLngDisplay').val());
                // Returning explicit true/undefined allows EModal to close the topmost overlay automatically
            }
        });
    },



    /**
     * HELPERS
     */
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
                EModal.info({ type: 'error', title: 'Sesi Berakhir', message: 'Silakan masuk kembali.', onClose: () => window.location.href = this.state.baseUrl + '#/login' });
            }
        });
    },

    getToken() {
        return this.state.token;
    },

    formatNumber(n) { return new Intl.NumberFormat('id-ID').format(n || 0); },
    unformatNumber(s) { return parseFloat(s.toString().replace(/\./g, '').replace(/,/g, '.')) || 0; },
    formatCompact(num) { return new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(num || 0); },
    formatDate(dateStr) { if (!dateStr) return '-'; const d = new Date(dateStr); return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }); },
    getInitials(name) { return name ? name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) : '?'; },

    /**
     * PAGE: BANGUNAN
     */
    renderBangunan($container, tanahId) {
        $container.html('<div class="skeleton" style="height:300px"></div>');
        
        const renderView = (judul) => {
            $container.html(`
                <div class="sp-card">
                    <div class="sp-card-header">
                        <h3>${judul}</h3>
                        <div class="sp-toolbar">
                            <button class="btn btn-primary btn-sm" onclick="Sarpras.formBangunan(null, ${tanahId || 'null'})">Tambah Bangunan</button>
                        </div>
                    </div>
                    <div class="sp-card-body">
                        <div class="sp-table-wrapper" id="bangunanTable"></div>
                    </div>
                </div>
            `);

            this.api(`bangunan.php?action=list` + (tanahId ? `&tanah_id=${tanahId}` : '')).done(res => {
                if (!res.data.length) { $('#bangunanTable').html('<div class="sp-empty">Belum ada bangunan.</div>'); return; }
                const rows = res.data.map(b => `
                    <tr>
                        <td><strong>${b.nama}</strong><br><small>${b.tanah_nama}</small></td>
                        <td>${b.luas_m2} m²<br><small>${b.jumlah_lantai} Lantai</small></td>
                        <td><code class="sp-code">${b.kode_bangunan || '-'}</code></td>
                        <td><span class="sp-kondisi"><div class="sp-kondisi-dot ${b.kondisi === 'Baik' ? 'baik' : (b.kondisi === 'Rusak Ringan' ? 'rr' : 'rb')}"></div> ${b.kondisi}</span></td>
                        <td>Rp ${this.formatNumber(b.harga_perolehan)}</td>
                        <td><span class="badge badge-info">${b.jumlah_ruang} Ruang</span></td>
                        <td>
                            <div class="sp-actions">
                                <button class="sp-btn-icon" title="Lihat Ruang" onclick="Sarpras.navigate('ruang', {bangunan_id: ${b.id}})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                </button>
                                <button class="sp-btn-icon" title="Lihat Penanggung Jawab" onclick="Sarpras.navigate('pj', {bangunan_id: ${b.id}})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8"/></svg>
                                </button>
                                <button class="sp-btn-icon" onclick="Sarpras.formBangunan(${b.id}, ${b.tanah_id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                <button class="sp-btn-icon danger" onclick="Sarpras.delBangunan(${b.id}, '${b.nama}', ${b.tanah_id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </div>
                        </td>
                    </tr>
                `).join('');
                $('#bangunanTable').html(`<table class="sp-table"><thead><tr><th>Bangunan (Tanah)</th><th>Spesifikasi</th><th>Kode</th><th>Kondisi</th><th>Nilai</th><th>Fasilitas</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
            });
        };

        if (tanahId) {
            this.api(`tanah.php?action=get&id=${tanahId}`).done(resTanah => {
                const t = resTanah.data;
                $('#breadcrumb').html(`<a href="#/tanah">Tanah</a> <span class="sep">/</span> <span class="current">${t.nama}</span>`);
                renderView(`Daftar Bangunan di ${t.nama}`);
            });
        } else {
            $('#breadcrumb').html(`<span class="current">Semua Bangunan</span>`);
            renderView('Semua Daftar Bangunan');
        }
    },

    formBangunan(id, tanahId) {
        const isEdit = id !== null && id !== undefined;
        EModal.form({
            title: isEdit ? 'Edit Bangunan' : 'Tambah Bangunan',
            form: `
                <div class="form-group">
                    <label>Tanah / Lahan <span style="color:var(--danger)">*</span></label>
                    <select class="form-select" id="f_bgTanahId" required>
                        <option value="">-- Pilih Tanah --</option>
                    </select>
                </div>
                <div class="sp-form-row">
                    <div class="form-group"><label>Nama Bangunan</label><input class="form-input" id="f_bgNama" required placeholder="Contoh: Gedung Rektorat"></div>
                    <div class="form-group"><label>Kode Bangunan (Auto)</label><input class="form-input" id="f_bgKode" placeholder="Otomatis jika kosong"></div>
                </div>
                <div class="sp-form-row three">
                    <div class="form-group"><label>Panjang (m)</label><input type="number" class="form-input sp-calc-luas" id="f_bgP"></div>
                    <div class="form-group"><label>Lebar (m)</label><input type="number" class="form-input sp-calc-luas" id="f_bgL"></div>
                    <div class="form-group"><label>Luas (m²)</label><input type="number" class="form-input" id="f_bgLuas" required readonly style="background:#f8f9fa"></div>
                </div>
                <div class="sp-form-row three">
                    <div class="form-group"><label>Jumlah Lantai</label><input type="number" class="form-input" id="f_bgLantai" value="1"></div>
                    <div class="form-group"><label>Kapasitas (Orang)</label><input type="number" class="form-input" id="f_bgKap" placeholder="0"></div>
                    <div class="form-group"><label>Thn Dibangun</label><input type="number" class="form-input" id="f_bgThn" value="${new Date().getFullYear()}"></div>
                </div>
                <div class="sp-form-row">
                    <div class="form-group"><label>Kondisi</label><select class="form-select" id="f_bgKon"><option>Baik</option><option>Rusak Ringan</option><option>Rusak Berat</option></select></div>
                    <div class="form-group"><label>Asal Dana</label><select class="form-select" id="f_bgAsal"><option value="">-- Pilih --</option></select></div>
                </div>
                <div class="sp-form-row">
                    <div class="form-group"><label>Harga Perolehan (Rp)</label><input type="text" class="form-input sp-rupiah-input" id="f_bgHarga" placeholder="0"></div>
                    <div class="form-group"><label>Keterangan</label><textarea class="form-input" id="f_bgKet" rows="2"></textarea></div>
                </div>
                <div class="form-group">
                    <label>Foto Bangunan <small style="color:var(--text-muted);font-weight:400">(Maks. 5 foto, otomatis dikompres ke 500KB)</small></label>
                    <div id="bgFotoList" class="sp-foto-grid"></div>
                    <div class="sp-foto-add" id="bgFotoAdd" onclick="Sarpras._pickBgFoto(${id || 0}, ${tanahId})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>Upload Foto</span>
                    </div>
                </div>
            `,
            onOpen: () => {
                // Fetch Tanah list
                this.api('tanah.php?action=list&per_page=500').done(res => {
                    const $sel = $('#f_bgTanahId');
                    if (res.data && res.data.data) {
                        res.data.data.forEach(t => $sel.append(`<option value="${t.id}">${t.nama}</option>`));
                    }
                    if (tanahId) $sel.val(tanahId);
                });
                // Fetch Asal Dana
                this.api('referensi.php?action=list&kategori=asal_dana').done(res => {
                    res.data.forEach(r => $('#f_bgAsal').append(`<option value="${r.nama}">${r.nama}</option>`));
                    if (isEdit) $('#f_bgAsal').val(this._tempBgAsal);
                });
                if (isEdit) {
                    this.api(`bangunan.php?action=get&id=${id}`).done(res => {
                        const d = res.data;
                        $('#f_bgNama').val(d.nama); $('#f_bgKode').val(d.kode_bangunan); $('#f_bgLantai').val(d.jumlah_lantai);
                        $('#f_bgP').val(d.panjang_m); $('#f_bgL').val(d.lebar_m); $('#f_bgLuas').val(d.luas_m2);
                        $('#f_bgKap').val(d.kapasitas || ''); $('#f_bgThn').val(d.tahun_dibangun);
                        $('#f_bgKon').val(d.kondisi); $('#f_bgHarga').val(this.formatNumber(d.harga_perolehan));
                        this._tempBgAsal = d.asal_anggaran; $('#f_bgKet').val(d.keterangan);
                        // Load existing photos
                        this._renderBgFotos(d.fotos || [], id, tanahId);
                    });
                }
            },
            onConfirm: () => {
                const data = {
                    id, tanah_id: $('#f_bgTanahId').val(), nama: $('#f_bgNama').val(),
                    kode_bangunan: $('#f_bgKode').val(),
                    luas_m2: $('#f_bgLuas').val(), jumlah_lantai: $('#f_bgLantai').val(),
                    kapasitas: $('#f_bgKap').val(),
                    panjang_m: $('#f_bgP').val(), lebar_m: $('#f_bgL').val(),
                    tahun_dibangun: $('#f_bgThn').val(), kondisi: $('#f_bgKon').val(),
                    harga_perolehan: this.unformatNumber($('#f_bgHarga').val()),
                    asal_anggaran: $('#f_bgAsal').val(), keterangan: $('#f_bgKet').val()
                };
                if (!data.nama || !data.luas_m2) { EModal.toast({type:'error',title:'Wajib diisi',message:'Nama dan Luas harus diisi!'}); return false; }
                this.api(`bangunan.php?action=${isEdit?'update':'create'}`, { method: 'POST', data }).done(() => {
                    EModal.closeAll();
                    if (this.state.currentRoute === 'tanah') {
                        this.refreshAccordionLevel('tanah', tanahId);
                    } else {
                        this.renderBangunan($('#mainContent'), tanahId);
                    }
                });
                return false;
            }
        });
    },

    _renderBgFotos(fotos, bgId, tanahId) {
        let html = '';
        if (fotos && fotos.length) {
            html = fotos.map(f => `
                <div class="sp-foto-item">
                    <img src="${this.state.baseUrl}${f.foto_path}" onclick="window.open(this.src,'_blank')">
                    <button class="btn-del" onclick="Sarpras._delBgFoto(${f.id}, ${bgId}, ${tanahId})">&times;</button>
                </div>
            `).join('');
        }
        $('#bgFotoList').html(html);
        // Show/hide upload button based on count
        if (fotos && fotos.length >= 5) {
            $('#bgFotoAdd').hide();
        } else {
            $('#bgFotoAdd').show().attr('onclick', `Sarpras._pickBgFoto(${bgId}, ${tanahId})`);
        }
    },

    _pickBgFoto(bgId, tanahId) {
        if (!bgId || bgId === 0) {
            EModal.toast({type:'info', title:'Info', message:'Simpan data bangunan terlebih dahulu sebelum upload foto.'});
            return;
        }
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('foto', file);
            formData.append('bangunan_id', bgId);
            formData.append('keterangan', 'Foto Bangunan');
            const loader = EModal.loading('Mengupload foto...');
            this.api('bangunan.php?action=upload-foto', { method: 'POST', data: formData }).done(res => {
                EModal.close(loader);
                EModal.toast({title:'Foto Berhasil Diupload'});
                // Reload fotos
                this.api(`bangunan.php?action=get&id=${bgId}`).done(r => this._renderBgFotos(r.data.fotos || [], bgId, tanahId));
            }).fail(xhr => {
                EModal.close(loader);
                EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message || 'Upload gagal'});
            });
        };
        input.click();
    },

    _delBgFoto(fotoId, bgId, tanahId) {
        EModal.confirm({
            title: 'Hapus Foto', message: 'Yakin hapus foto ini?', type: 'danger',
            onConfirm: () => {
                this.api('bangunan.php?action=delete-foto', { method: 'POST', data: { foto_id: fotoId } }).done(() => {
                    this.api(`bangunan.php?action=get&id=${bgId}`).done(r => this._renderBgFotos(r.data.fotos || [], bgId, tanahId));
                });
            }
        });
    },


    delBangunan(id, name, tanahId) {
        EModal.confirm({
            title: 'Hapus Bangunan', message: `Yakin hapus <strong>${name}</strong>?`, type: 'danger',
            onConfirm: () => this.api('bangunan.php?action=delete', { method: 'POST', data: { id } }).done(() => {
                if (this.state.currentRoute === 'tanah') {
                    this.refreshAccordionLevel('tanah', tanahId);
                } else {
                    this.renderBangunan($('#mainContent'), tanahId);
                }
            })
        });
    },

    delRuang(id, name, bangunanId) {
        EModal.confirm({
            title: 'Hapus Ruang', message: `Yakin hapus <strong>${name}</strong>?`, type: 'danger',
            onConfirm: () => this.api('ruang.php?action=delete', { method: 'POST', data: { id } }).done(() => {
                if (this.state.currentRoute === 'tanah') {
                    this.refreshAccordionLevel('bangunan', bangunanId);
                } else {
                    this.renderRuang($('#mainContent'), bangunanId);
                }
            })
        });
    },

    renderRuang($container, bangunanId) {
        $container.html('<div class="skeleton" style="height:300px"></div>');
        
        const renderView = (judul) => {
            $container.html(`
                <div class="sp-card">
                    <div class="sp-card-header">
                        <h3>${judul}</h3>
                        <div class="sp-toolbar">
                            <button class="btn btn-primary btn-sm" onclick="Sarpras.formRuang(null, ${bangunanId || 'null'})">Tambah Ruang</button>
                        </div>
                    </div>
                    <div class="sp-card-body">
                        <div class="sp-table-wrapper" id="ruangTable"></div>
                    </div>
                </div>
            `);
            
            this.api(`ruang.php?action=list` + (bangunanId ? `&bangunan_id=${bangunanId}` : '')).done(res => {
                if (!res.data.length) { $('#ruangTable').html('<div class="sp-empty">Belum ada ruang.</div>'); return; }
                let totalLuasRuang = 0;
                res.data.forEach(r => { totalLuasRuang += parseFloat(r.panjang_m || 0) * parseFloat(r.lebar_m || 0); });
                const rows = res.data.map(r => `
                    <tr>
                        <td><strong>${r.nama}</strong><br><small>Kode: ${r.kode_ruang}</small><br><small style="color:var(--primary)">${r.bangunan_nama} &bull; ${r.tanah_nama}</small></td>
                        <td>${r.jenis_ruang}<br><small>Lantai ${r.lantai}</small></td>
                        <td>${r.pj_nama ? `<strong>${r.pj_nama}</strong>` : '<span style="color:#94a3b8; font-style:italic">Tanpa PJ</span>'}</td>
                        <td>${(parseFloat(r.panjang_m||0)*parseFloat(r.lebar_m||0)).toFixed(1)} m²</td>
                        <td><span class="sp-kondisi"><div class="sp-kondisi-dot ${r.kondisi === 'Baik' ? 'baik' : (r.kondisi === 'Rusak Ringan' ? 'rr' : 'rb')}"></div> ${r.kondisi}</span></td>
                        <td>
                            <div class="sp-actions">
                                <button class="sp-btn-icon primary" title="Cetak Laporan" onclick="Sarpras.printReport('detail-per-ruang', {ruang_id: ${r.id}})">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                </button>
                                <button class="sp-btn-icon" title="Lihat Aset" onclick="Sarpras.navigate('sarpras', {ruang_id: ${r.id}})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <button class="sp-btn-icon" title="Edit" onclick="Sarpras.formRuang(${r.id}, ${r.bangunan_id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                <button class="sp-btn-icon danger" title="Hapus" onclick="Sarpras.delRuang(${r.id}, '${r.nama}', ${r.bangunan_id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </div>
                        </td>
                    </tr>
                `).join('');
                $('#ruangTable').html(`
                    <table class="sp-table">
                        <thead>
                            <tr>
                                <th>Ruang (Bangunan & Tanah)</th>
                                <th>Tipe</th>
                                <th>Penanggung Jawab</th>
                                <th>Luas</th>
                                <th>Kondisi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                    <div class="sp-luas-summary">Total Luas Ruangan: <strong>${totalLuasRuang.toFixed(1)} m²</strong></div>
                `);
            });
        };

        if (bangunanId) {
            this.api(`bangunan.php?action=get&id=${bangunanId}`).done(resB => {
                const b = resB.data;
                this.setBreadcrumbs([
                    { label: 'Tanah', route: 'tanah' },
                    { label: b.tanah_nama, route: 'bangunan', params: { tanah_id: b.tanah_id } },
                    { label: b.nama }
                ]);
                renderView(`Daftar Ruang di ${b.nama}`);
            });
        } else {
            this.setBreadcrumbs([{ label: 'Semua Ruang' }]);
            renderView('Semua Daftar Ruang');
        }
    },

    formRuang(id, bangunanId) {
        const isEdit = id !== null && id !== undefined;
        EModal.form({
            title: isEdit ? 'Edit Ruang' : 'Tambah Ruang',
            form: `
                <style>
                    .sp-pj-cs { position:relative; user-select:none; margin-bottom:4px; }
                    .sp-pj-cs-btn { cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:10px 15px; height:44px; transition:all 0.2s; }
                    .sp-pj-cs-btn:hover { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
                    .sp-pj-cs-btn.active { border-color:#3b82f6; }
                    .sp-pj-cs-dd { display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:#fff; border:1px solid #cbd5e1; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:9999; overflow:hidden; max-height:240px; }
                    .sp-pj-cs-search { padding:10px 12px; border-bottom:1px solid #f1f5f9; }
                    .sp-pj-cs-search input { width:100%; padding:8px 10px; border:1px solid #e2e8f0; border-radius:8px; font-size:0.85rem; outline:none; }
                    .sp-pj-cs-search input:focus { border-color:#3b82f6; }
                    .sp-pj-cs-list { max-height:180px; overflow-y:auto; }
                    .sp-pj-cs-opt { padding:10px 14px; cursor:pointer; border-bottom:1px solid #f1f5f9; transition:background 0.15s; }
                    .sp-pj-cs-opt:hover { background:#f1f5f9; }
                    .sp-pj-cs-opt .pj-n { font-weight:600; color:#1e293b; font-size:0.9rem; }
                    .sp-pj-cs-opt .pj-j { font-size:0.75rem; color:#64748b; margin-top:1px; }
                    .sp-pj-cs-opt.none { color:#94a3b8; font-style:italic; font-size:0.85rem; }
                </style>
                <div class="form-group">
                    <label>Bangunan / Gedung <span style="color:var(--danger)">*</span></label>
                    <select class="form-select" id="f_rgBgId" required>
                        <option value="">-- Pilih Bangunan --</option>
                    </select>
                </div>
                <input type="hidden" id="f_rgPjId" value="">
                <input type="hidden" id="f_rgPjJabatan" value="">
                
                <div class="form-group"><label>Nama Ruang</label><input class="form-input" id="f_rgNama" required placeholder="Contoh: Ruang Kelas XII IPA 1"></div>
                <div class="sp-form-row">
                    <div class="form-group"><label>Kode Ruang</label><input class="form-input" id="f_rgKode" placeholder="Contoh: R01-LT1"></div>
                    <div class="form-group"><label>Tipe Ruang</label><select class="form-select" id="f_rgTipe"><option value="">-- Pilih --</option></select></div>
                </div>
                <div class="sp-form-row three">
                    <div class="form-group"><label>Lantai</label><input type="number" class="form-input" id="f_rgLantai" value="1"></div>
                    <div class="form-group"><label>Panjang (m)</label><input type="number" class="form-input sp-calc-luas" id="f_rgP"></div>
                    <div class="form-group"><label>Lebar (m)</label><input type="number" class="form-input sp-calc-luas" id="f_rgL"></div>
                </div>
                <div class="sp-form-row">
                    <div class="form-group"><label>Luas (m²)</label><input type="number" class="form-input" id="f_rgLuas" readonly style="background:#f8f9fa"></div>
                </div>
                <div class="sp-form-row">
                    <div class="form-group"><label>Kapasitas (Orang)</label><input type="number" class="form-input" id="f_rgKap" value="0"></div>
                    <div class="form-group"><label>Kondisi</label><select class="form-select" id="f_rgKon"><option>Baik</option><option>Rusak Ringan</option><option>Rusak Berat</option></select></div>
                </div>
                <div class="form-group"><label>Keterangan</label><textarea class="form-input" id="f_rgKet" rows="2"></textarea></div>
            `,
            onOpen: () => {
                // Fetch Bangunan list
                this.api('bangunan.php?action=list&per_page=1000').done(res => {
                    const $sel = $('#f_rgBgId');
                    if (res.data) {
                        res.data.forEach(b => $sel.append(`<option value="${b.id}">${b.nama} (${b.tanah_nama})</option>`));
                    }
                    if (bangunanId) $sel.val(bangunanId);
                });
                // Fetch Tipe Ruang
                this.api('referensi.php?action=list&kategori=jenis_ruang').done(res => {
                    $('#f_rgTipe').html('<option value="">-- Pilih --</option>');
                    res.data.forEach(r => $('#f_rgTipe').append(`<option value="${r.nama}">${r.nama}</option>`));
                    if (isEdit && this._tempRgTipe) $('#f_rgTipe').val(this._tempRgTipe);
                });

                const checkLuas = () => {
                    const p = parseFloat($('#f_rgP').val()) || 0;
                    const l = parseFloat($('#f_rgL').val()) || 0;
                    $('#f_rgLuas').val((p * l).toFixed(2));
                };
                $(document).on('input.ruangLuas', '#f_rgP, #f_rgL', checkLuas);

                if (isEdit) {
                    this.api(`ruang.php?action=get&id=${id}`).done(res => {
                        const d = res.data;
                        this._tempRgPjId = d.pj_id;
                        $('#f_rgPjId').val(d.pj_id || '');
                        $('#f_rgBgId').val(d.bangunan_id || bangunanId);
                        $('#f_rgNama').val(d.nama); $('#f_rgKode').val(d.kode_ruang);
                        this._tempRgTipe = d.jenis_ruang;
                        $('#f_rgTipe').val(d.jenis_ruang);
                        $('#f_rgLantai').val(d.lantai); $('#f_rgP').val(d.panjang_m); $('#f_rgL').val(d.lebar_m);
                        $('#f_rgLuas').val((parseFloat(d.panjang_m||0)*parseFloat(d.lebar_m||0)).toFixed(2));
                        $('#f_rgKap').val(d.kapasitas); $('#f_rgKon').val(d.kondisi); $('#f_rgKet').val(d.keterangan);
                    });
                }
            },
            onConfirm: () => {
                $(document).off('input.ruangLuas');
                const pjid = $('#f_rgPjId').val() || null;
                const bgId = $('#f_rgBgId').val();
                const data = {
                    id, bangunan_id: bgId, pj_id: pjid, nama: $('#f_rgNama').val(), kode_ruang: $('#f_rgKode').val(),
                    jenis_ruang: $('#f_rgTipe').val(), lantai: $('#f_rgLantai').val(), panjang_m: $('#f_rgP').val(),
                    lebar_m: $('#f_rgL').val(), kapasitas: $('#f_rgKap').val(), kondisi: $('#f_rgKon').val(),
                    keterangan: $('#f_rgKet').val()
                };
                if (!data.nama) { EModal.toast({type:'error',title:'Perhatian',message:'Nama Ruang wajib diisi!'}); return false; }
                this.api(`ruang.php?action=${isEdit?'update':'create'}`, { method: 'POST', data }).done(() => {
                    EModal.closeAll();
                    if (this.state.currentRoute === 'tanah') {
                        this.refreshAccordionLevel('bangunan', bgId);
                    } else {
                        this.renderRuang($('#mainContent'), bgId);
                    }
                }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message:xhr.responseJSON?.message}));
                return false;
            }
        });
    },

    delRuang(id, name, bangunanId) {
        EModal.confirm({
            title: 'Hapus Ruang', message: `Yakin hapus <strong>${name}</strong>?`, type: 'danger',
            onConfirm: () => this.api('ruang.php?action=delete', { method: 'POST', data: { id } }).done(() => {
                if (this.state.currentRoute === 'tanah') {
                    this.refreshAccordionLevel('bangunan', bangunanId);
                } else {
                    this.renderRuang($('#mainContent'), bangunanId);
                }
            })
        });
    },

    /**
     * PAGE: SARPRAS (INVENTARIS)
     */
    renderSarpras($container, ruangId) {
        if (!ruangId) return this.navigate('tanah');
        $container.html('<div class="skeleton" style="height:300px"></div>');
        this.api(`ruang.php?action=get&id=${ruangId}`).done(resRuang => {
            const r = resRuang.data;
            const pjHtml = r.pj_nama ? `<div style="font-size:0.8rem; font-weight:normal; color:var(--text-light); margin-top:4px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:text-bottom; margin-right:4px"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> PJ: <strong>${r.pj_nama}</strong> ${r.pj_nip ? `(${r.pj_nip})` : ''}</div>` : '';
            const ruangType = String(r.jenis_ruang || '').trim();
            const ruangName = String(r.nama || '').trim();
            const canImportBuku = /perpustak/i.test(ruangType) || /perpustak/i.test(ruangName);
            
            $container.html(`
                <div class="sp-card">
                    <div class="sp-card-header">
                        <div>
                            <h3>Inventaris di ${r.nama}</h3>
                            ${pjHtml}
                        </div>
                        <div class="sp-toolbar">
                            ${canImportBuku ? `<button class="btn btn-secondary btn-sm" onclick="Sarpras.formImportBuku(${ruangId})">Import Buku</button>` : ''}
                            <button class="btn btn-secondary btn-sm" onclick="Sarpras.formCopySarpras(${ruangId})">Salin Aset</button>
                            
                            <button class="btn btn-primary btn-sm" onclick="Sarpras.formSarpras(null, ${ruangId}, 'ahp-bhp')">+ Barang (AHP/BHP)</button>
                            <button class="btn btn-primary btn-sm" onclick="Sarpras.formSarpras(null, ${ruangId}, 'angkutan')">+ Angkutan</button>
                            <button class="btn btn-primary btn-sm" onclick="Sarpras.formSarpras(null, ${ruangId}, 'buku')">+ Buku</button>
                        </div>
                    </div>
                    <div class="sp-card-body">
                        <div class="sp-table-wrapper" id="sarprasTable"></div>
                    </div>
                </div>
            `);
            
            this.api(`sarpras.php?action=list&ruang_id=${ruangId}`).done(res => {
                if (!res.data.data.length) { $('#sarprasTable').html('<div class="sp-empty">Belum ada barang di ruang ini.</div>'); return; }
                const rows = res.data.data.map(s => {
                    const groupBadge = this.getAssetGroupBadge(s.grup_pintasan);
                    return `
                    <tr>
                        <td><strong>${s.nama}</strong><br><small>${s.kode_inventaris}</small></td>
                        <td>${s.kategori_nama}${groupBadge}</td>
                        <td>${s.jumlah} Unit</td>
                        <td>
                            <div style="font-size:0.7rem">
                                <span style="color:var(--success)">B: ${s.kondisi_baik}</span> | 
                                <span style="color:var(--warning)">RR: ${s.kondisi_rusak_ringan}</span> | 
                                <span style="color:var(--danger)">RB: ${s.kondisi_rusak_berat}</span>
                            </div>
                        </td>
                        <td>
                            <div class="sp-actions">
                                <button class="sp-btn-icon" title="Detail" onclick="Sarpras.navigate('sarpras-detail', {id: ${s.id}})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <button class="sp-btn-icon" onclick="Sarpras.formSarpras(${s.id}, ${ruangId})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                <button class="sp-btn-icon danger" onclick="Sarpras.delSarpras(${s.id}, '${s.nama}', ${ruangId})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </div>
                        </td>
                    </tr>
                `;
                }).join('');
                $('#sarprasTable').html(`<table class="sp-table"><thead><tr><th>Nama / Kode</th><th>Kategori</th><th>Jml</th><th>Kondisi</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
            });
        });
    },

    /**
     * Helper: Get asset group badge HTML based on grup_pintasan
     */
    getAssetGroupBadge(grupKode) {
        if (grupKode === 'ahp-bhp') return '<div style="margin-top:3px"><span style="display:inline-block;font-size:0.65rem;padding:2px 8px;border-radius:20px;background:#FEF3C7;color:#92400E;font-weight:600;">AHP & BHP</span></div>';
        if (grupKode === 'angkutan') return '<div style="margin-top:3px"><span style="display:inline-block;font-size:0.65rem;padding:2px 8px;border-radius:20px;background:#DBEAFE;color:#1E40AF;font-weight:600;">Alat Angkutan</span></div>';
        if (grupKode === 'buku') return '<div style="margin-top:3px"><span style="display:inline-block;font-size:0.65rem;padding:2px 8px;border-radius:20px;background:#D1FAE5;color:#065F46;font-weight:600;">Koleksi Buku</span></div>';
        return '';
    },

    renderAssetGroup($container, group) {
        let route = this.state.currentRoute;
        let title = '';
        if (route === 'ahp-bhp') { title = 'Alat & Bahan Habis Pakai (AHP-BHP)'; }
        else if (route === 'angkutan') { title = 'Alat Angkutan / Kendaraan'; }
        else if (route === 'buku') { title = 'Koleksi Buku'; }

        const isAngkutan = route === 'angkutan';
        const isBuku = route === 'buku';
        
        let addBtnAction = 'Sarpras.formSarpras(null, null)';
        let addBtnLabel = '+ Tambah Barang';
        let extraBtn = '';

        if (isAngkutan) {
            addBtnAction = 'Sarpras.formAngkutan()';
            addBtnLabel = '+ Tambah Kendaraan';
        } else if (isBuku) {
            addBtnAction = 'Sarpras.formBuku()';
            addBtnLabel = '+ Tambah Buku';
            extraBtn = '<button class="btn btn-secondary btn-sm" onclick="Sarpras.formImportBuku(null)">Import (CSV)</button>';
        }

        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <h3 style="margin:0">${title}</h3>
                    <div class="sp-toolbar" style="display:flex; align-items:center; gap:10px;">
                        <div class="sp-search-box" style="position:relative;">
                            <input type="text" id="assetLiveSearch" placeholder="Pencarian cepat..." style="padding:6px 12px 6px 30px; border-radius:20px; border:1px solid #cbd5e1; font-size:13px; width:220px; outline:none; transition: border-color 0.2s;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute; left:10px; top:8px; width:14px; height:14px; color:#94a3b8;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                        ${extraBtn}
                        <button class="btn btn-primary btn-sm" onclick="${addBtnAction}">${addBtnLabel}</button>
                    </div>
                </div>
                <div class="sp-card-body">
                    <div class="sp-table-wrapper" id="groupTable"><div class="skeleton" style="height:200px"></div></div>
                </div>
            </div>
        `);

        $('#assetLiveSearch').on('keyup', function() {
            const val = $(this).val().toLowerCase();
            $('#groupTable table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
            });
        });

        this.api(`sarpras.php?action=list&grup=${route}&per_page=50`).done(res => {
            const items = res.data.data || [];
            if (!items.length) { 
                $('#groupTable').html('<div class="sp-empty">Belum ada data di grup ini. Silakan tambah barang baru.</div>'); 
                return; 
            }

            if (isAngkutan) {
                // === ANGKUTAN TABLE ===
                const rows = items.map(s => `
                    <tr>
                        <td>
                            <strong>${s.nama}</strong><br>
                            <small style="color:var(--text-muted)">${s.kode_inventaris}</small>
                        </td>
                        <td>${s.jenis_sarana || s.kategori_nama || '-'}</td>
                        <td>${s.merk || '-'}</td>
                        <td style="text-align:center">
                            <span style="font-weight:700; color:var(--primary); background:#eff6ff; padding:3px 10px; border-radius:6px; font-size:0.85rem;">${s.no_polisi || '-'}</span>
                        </td>
                        <td style="text-align:center"><strong>${s.total_batch || s.jumlah}</strong></td>
                        <td style="text-align:center; color:var(--primary)">${s.terpakai_batch || 0}</td>
                        <td style="text-align:center; color:var(--warning)"><strong>${s.jumlah}</strong></td>
                        <td>
                            <div class="sp-actions">
                                <button class="sp-btn-icon" title="Detail" onclick="Sarpras.navigate('sarpras-detail', {id: ${s.id}})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <button class="sp-btn-icon" title="Edit" onclick="Sarpras.formAngkutan(${s.id})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button class="sp-btn-icon danger" title="Hapus" onclick="Sarpras.delSarprasGroup(${s.id}, '${s.nama.replace(/'/g, "\\\\\'")}')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
                
                $('#groupTable').html(`
                    <table class="sp-table">
                        <thead>
                            <tr>
                                <th>Nama / Kode</th>
                                <th>Jenis Sarana</th>
                                <th>Merk</th>
                                <th style="text-align:center">No. Polisi</th>
                                <th style="text-align:center">Total</th>
                                <th style="text-align:center">Terpakai</th>
                                <th style="text-align:center">Sisa Stok</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                `);
            } else if (isBuku) {
                // === BUKU TABLE ===
                const rows = items.map(s => `
                    <tr>
                        <td>
                            <strong>${s.nama}</strong><br>
                            <small style="color:var(--text-muted)">${s.kode_inventaris}</small>
                        </td>
                        <td>${s.pengarang || '-'}</td>
                        <td>${s.penerbit || '-'}</td>
                        <td style="text-align:center"><strong>${s.total_batch || s.jumlah}</strong></td>
                        <td style="text-align:center; color:var(--primary)">${s.terpakai_batch || 0}</td>
                        <td style="text-align:center; color:var(--warning)"><strong>${s.jumlah}</strong></td>
                        <td>
                            <div class="sp-actions">
                                <button class="sp-btn-icon" title="Detail" onclick="Sarpras.navigate('sarpras-detail', {id: ${s.id}})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <button class="sp-btn-icon" title="Edit" onclick="Sarpras.formBuku(${s.id})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button class="sp-btn-icon" title="Tambah Batch (Lagi)" style="color:var(--success)" onclick="Sarpras.formBuku(${s.id}, true)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                                </button>
                                <button class="sp-btn-icon" title="Hapus" onclick="Sarpras.delSarprasGroup(${s.id}, '${s.nama.replace(/'/g, "\\\\\'")}')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');

                $('#groupTable').html(`
                    <table class="sp-table">
                        <thead>
                            <tr>
                                <th>Judul / Kode</th>
                                <th>Pengarang</th>
                                <th>Penerbit</th>
                                <th style="text-align:center">Total</th>
                                <th style="text-align:center">Terdistribusi</th>
                                <th style="text-align:center">Sisa Stok</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                `);
            } else {
                // === DEFAULT TABLE (AHP-BHP / BUKU) ===
                const isAHPBHP = route === 'ahp-bhp';
                const rows = items.map(s => `
                    <tr>
                        <td><strong>${s.nama}</strong><br><small>${s.kode_inventaris}</small></td>
                        <td>${s.kategori_nama}</td>
                        ${isAHPBHP ? `<td style="text-align:right">Rp ${Sarpras.formatNumber(s.harga_perolehan)}</td>` : ''}
                        <td style="text-align:center"><strong>${s.total_batch || s.jumlah}</strong></td>
                        <td style="text-align:center; color:var(--primary)">${s.terpakai_batch || 0}</td>
                        <td style="text-align:center; color:var(--warning)"><strong>${s.jumlah}</strong></td>
                        <td>
                            <div style="font-size:0.75rem">
                                <span style="color:var(--success)">B: ${s.kondisi_baik}</span> | 
                                <span style="color:var(--warning)">RR: ${s.kondisi_rusak_ringan}</span> | 
                                <span style="color:var(--danger)">RB: ${s.kondisi_rusak_berat}</span>
                            </div>
                        </td>
                        <td>
                            <div class="sp-actions">
                                <button class="sp-btn-icon" title="Detail" onclick="Sarpras.navigate('sarpras-detail', {id: ${s.id}})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <button class="sp-btn-icon" title="Edit" onclick="Sarpras.formSarpras(${s.id}, null)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                <button class="sp-btn-icon" title="Tambah Batch (Lagi)" style="color:var(--success)" onclick="Sarpras.formSarpras(${s.id}, null, null, true)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></button>
                                <button class="sp-btn-icon danger" title="Hapus" onclick="Sarpras.delSarprasGroup(${s.id}, '${s.nama.replace(/'/g, "\\\\'")}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </div>
                        </td>
                    </tr>
                `).join('');
                
                $('#groupTable').html(`
                    <table class="sp-table">
                        <thead>
                            <tr>
                                <th>Nama / Kode</th>
                                <th>Kategori</th>
                                ${isAHPBHP ? '<th style="text-align:right">Harga Satuan</th>' : ''}
                                <th style="text-align:center">Total Stok</th>
                                <th style="text-align:center">Terdistribusi</th>
                                <th style="text-align:center">Sisa Stok</th>
                                <th>Kondisi (Gudang)</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                `);
            }
        });
    },

    /**
     * Delete aset dari halaman Grup Aset Pintasan, lalu refresh tabel grup
     */
    delSarprasGroup(id, name) {
        EModal.confirm({
            title: 'Hapus Barang', message: `Yakin hapus <strong>${name}</strong>?`, type: 'danger',
            onConfirm: () => {
                this.api('sarpras.php?action=delete', { method: 'POST', data: { id } }).done(() => {
                    EModal.toast({ type: 'success', title: 'Data Dihapus' });
                    this.loadRouteFromHash(); // Refresh current group page
                });
            }
        });
    },

    /**
     * FORM: ANGKUTAN (Dedicated Vehicle Form)
     */
    formAngkutan(id = null) {
        const isEdit = id !== null && id !== undefined;

        // Fetch kategori silently for required kategori_id
        this.api('manage.php?entity=kategori&action=list').done(resKat => {
            const kData = resKat.data || [];
            const jenisOptions = [
                'Bis Sekolah',
                'Mobil Bak Terbuka',
                'Mobil Dinas',
                'Mobil Truk',
                'Motor',
                'Perahu',
                'Sepeda'
            ];
            const jenisDropdown = jenisOptions.map(j => `<option value="${j}">${j}</option>`).join('');
            const katOptions = kData.map(k => `<option value="${k.id}">${k.nama}</option>`).join('');

            // Source of funds options
            const asalOptions = ['APBD', 'APBN', 'BOS', 'BOSDA', 'Hibah', 'Lainnya'].map(a => `<option value="${a}">${a}</option>`).join('');

            EModal.form({
                title: isEdit ? 'Edit Data Kendaraan' : 'Tambah Kendaraan Baru',
                size: 'lg',
                form: `
                    <style>
                        .sp-angkutan-form .sp-form-section { margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px dashed #e2e8f0; }
                        .sp-angkutan-form .sp-form-section:last-child { border-bottom: none; }
                        .sp-angkutan-form .sp-form-section-title { 
                            font-family: var(--font-heading); font-size: 0.9rem; font-weight: 700; 
                            color: var(--primary); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
                            text-transform: uppercase; letter-spacing: 0.5px;
                        }
                        .sp-angkutan-form .sp-form-section-title svg { width: 16px; height: 16px; }
                        .sp-angkutan-form .sp-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                        @media (max-width: 768px) { .sp-angkutan-form .sp-form-grid-2 { grid-template-columns: 1fr !important; } }
                    </style>
                    <div class="sp-angkutan-form">
                        <input type="hidden" id="f_agId" value="${id || ''}">

                        <!-- SECTION 1: IDENTITAS KENDARAAN -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polyline points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                Identitas Kendaraan
                            </div>
                            <div class="sp-form-grid-2">
                                <div class="form-group">
                                    <label>Jenis Sarana <span style="color:var(--danger)">*</span></label>
                                    <select class="form-select" id="f_agJenisSarana" required>
                                        <option value="">-- Pilih Jenis Sarana --</option>
                                        ${jenisDropdown}
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Kategori Inventaris <span style="color:var(--danger)">*</span></label>
                                    <select class="form-select" id="f_agKategori" required>
                                        <option value="">-- Pilih Kategori --</option>
                                        ${katOptions}
                                    </select>
                                </div>
                            </div>
                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>Nama Kendaraan <span style="color:var(--danger)">*</span></label><input class="form-input" id="f_agNama" required placeholder="Contoh: Toyota Avanza, Honda Beat"></div>
                                <div class="form-group"><label>Merk / Model</label><input class="form-input" id="f_agMerk" placeholder="Contoh: Toyota, Honda, Suzuki"></div>
                            </div>
                            <div class="form-group"><label>Spesifikasi</label><textarea class="form-input" id="f_agSpek" rows="2" placeholder="Warna, tahun produksi, kapasitas, tipe mesin, dll."></textarea></div>
                        </div>

                        <!-- SECTION 2: DATA DOKUMEN KENDARAAN -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-title" style="color:#15803d;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                Data Dokumen & Perolehan
                            </div>
                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>No. Polisi</label><input class="form-input" id="f_agNoPolisi" placeholder="Contoh: DN 1234 AB" style="text-transform:uppercase;"></div>
                                <div class="form-group"><label>No. BPKB</label><input class="form-input" id="f_agNoBPKB" placeholder="Nomor BPKB"></div>
                            </div>
                            <div class="form-group"><label>Alamat (sesuai STNK/BPKB)</label><input class="form-input" id="f_agAlamat" placeholder="Alamat sesuai dokumen kendaraan"></div>
                            
                            <div class="sp-form-grid-2">
                                <div class="form-group">
                                    <label>Status Kepemilikan</label>
                                    <select class="form-select" id="f_agKepemilikan">
                                        <option value="Milik">Milik</option>
                                        <option value="Sewa">Sewa</option>
                                    </select>
                                </div>
                                <div class="form-group"><label>Tanggal Perolehan</label><input type="date" class="form-input" id="f_agTglPerolehan" value="${new Date().toISOString().split('T')[0]}"></div>
                            </div>

                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>Jumlah (Unit)</label><input type="number" class="form-input" id="f_agJml" value="1" min="1"></div>
                                <div class="form-group"><label>Harga (Satuan)</label><input type="text" class="form-input sp-rupiah-input" id="f_agHarga" value="0"></div>
                            </div>

                            <div class="form-group">
                                <label>Asal Perolehan (Dana)</label>
                                <select class="form-select" id="f_agAsal">
                                    ${asalOptions}
                                </select>
                            </div>
                        </div>

                        <!-- SECTION 3: MEDIA -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                Media Kendaraan
                            </div>
                            <div id="ag_photo_preview" style="display:none; margin-bottom:15px; width:150px; height:100px; border-radius:10px; overflow:hidden; border:1px solid #e2e8f0;">
                                <img src="" style="width:100%; height:100%; object-fit:cover;">
                            </div>
                            <div class="form-group">
                                <label>Upload Foto Kendaraan</label>
                                <input type="file" class="form-input" id="f_agFoto" accept="image/*">
                                <small style="display:block; margin-top:5px; color:var(--text-muted)">Foto ini akan muncul saat barcode kendaraan di-scan.</small>
                            </div>
                        </div>
                    </div>
                `,
                onOpen: () => {
                    if (isEdit) {
                        this.api(`sarpras.php?action=get&id=${id}`).done(res => {
                            const d = res.data;
                            $('#f_agJenisSarana').val(d.jenis_sarana || '');
                            $('#f_agKategori').val(d.kategori_id);
                            $('#f_agNama').val(d.nama);
                            $('#f_agMerk').val(d.merk);
                            $('#f_agSpek').val(d.spesifikasi);
                            $('#f_agNoPolisi').val(d.no_polisi);
                            $('#f_agNoBPKB').val(d.no_bpkb);
                            $('#f_agAlamat').val(d.alamat);
                            $('#f_agKepemilikan').val(d.kepemilikan || 'Milik');
                            $('#f_agAsal').val(d.asal_perolehan || 'APBD');
                            $('#f_agTglPerolehan').val(d.tanggal_perolehan || new Date().toISOString().split('T')[0]);
                            $('#f_agJml').val(d.jumlah || 1);
                            $('#f_agHarga').val(Sarpras.formatNumber(d.harga_perolehan || 0));

                            if (d.fotos && d.fotos.length > 0) {
                                $('#ag_photo_preview img').attr('src', window.SARPRAS_CONFIG.baseUrl + d.fotos[0].foto_path);
                                $('#ag_photo_preview').show();
                            }
                        });
                    }
                },
                onConfirm: () => {
                    const jenisSarana = $('#f_agJenisSarana').val();
                    const nama = $('#f_agNama').val().trim();

                    if (!jenisSarana) { EModal.toast({type:'error', title:'Error', message:'Pilih Jenis Sarana'}); return false; }
                    if (!nama) { EModal.toast({type:'error', title:'Error', message:'Nama kendaraan wajib diisi'}); return false; }

                    const fd = new FormData();
                    if (isEdit) fd.append('id', id);
                    fd.append('kategori_id', $('#f_agKategori').val());
                    fd.append('asal_perolehan', $('#f_agAsal').val());
                    fd.append('jenis_sarana', jenisSarana);
                    fd.append('nama', nama);
                    fd.append('merk', $('#f_agMerk').val());
                    fd.append('spesifikasi', $('#f_agSpek').val());
                    fd.append('no_polisi', $('#f_agNoPolisi').val());
                    fd.append('no_bpkb', $('#f_agNoBPKB').val());
                    fd.append('alamat', $('#f_agAlamat').val());
                    fd.append('kepemilikan', $('#f_agKepemilikan').val());
                    fd.append('grup_pintasan', 'angkutan');
                    
                    const jml = parseInt($('#f_agJml').val() || 1);
                    fd.append('jumlah', jml);
                    fd.append('kondisi_baik', jml); // Default all units as Good
                    fd.append('kondisi_rusak_ringan', 0);
                    fd.append('kondisi_rusak_berat', 0);
                    
                    fd.append('kode_inventaris', 'Otomatis');
                    fd.append('tanggal_perolehan', $('#f_agTglPerolehan').val());
                    
                    const cleanedHarga = ($('#f_agHarga').val() || '0').replace(/\./g, '');
                    fd.append('harga_perolehan', cleanedHarga);
                    fd.append('masa_manfaat_tahun', 5);

                    const fileInput = document.getElementById('f_agFoto');
                    if (fileInput.files.length > 0) fd.append('foto', fileInput.files[0]);

                    const action = isEdit ? 'update' : 'create';
                    this.api(`sarpras.php?action=${action}`, { method: 'POST', data: fd }).done(() => {
                        EModal.closeAll();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: isEdit ? 'Data kendaraan diperbarui' : 'Kendaraan berhasil ditambahkan' });
                        this.loadRouteFromHash();
                    }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message}));
                    return false;
                }
            });
        });
    },

    /**
     * FORM: BUKU (Dedicated Book Form)
     */
    formBuku(id = null, isAddMore = false) {
        const isEdit = id !== null && id !== undefined && !isAddMore;
        const isTemplate = isAddMore && id !== null;

        this.api('referensi.php?action=list&kategori=klasifikasi_buku').done(resKat => {
            const kData = resKat.data || [];
            const katOptions = kData.map(k => `<option value="${k.nama}">${k.nama}</option>`).join('');
            const asalOptions = ['APBD', 'APBN', 'BOS', 'BOSDA', 'Hibah', 'Lainnya'].map(a => `<option value="${a}">${a}</option>`).join('');

            EModal.form({
                title: isEdit ? 'Edit Data Koleksi Buku' : (isAddMore ? 'Tambah Batch Buku' : 'Tambah Koleksi Buku Baru'),
                size: 'lg',
                form: `
                    <style>
                        .sp-buku-form .sp-form-section { margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px dashed #e2e8f0; }
                        .sp-buku-form .sp-form-section:last-child { border-bottom: none; }
                        .sp-buku-form .sp-form-section-title { 
                            font-family: var(--font-heading); font-size: 0.9rem; font-weight: 700; 
                            color: var(--primary); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
                            text-transform: uppercase; letter-spacing: 0.5px;
                        }
                        .sp-buku-form .sp-form-section-title svg { width: 16px; height: 16px; }
                        .sp-buku-form .sp-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                        @media (max-width: 768px) { .sp-buku-form .sp-form-grid-2 { grid-template-columns: 1fr !important; } }
                    </style>
                    <div class="sp-buku-form">
                        <input type="hidden" id="f_bkId" value="${isEdit ? id : ''}">

                        <!-- SECTION 1: IDENTITAS BUKU -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                Identitas Buku
                            </div>
                            <div class="form-group">
                                <label>Judul Buku <span style="color:var(--danger)">*</span></label>
                                <input class="form-input" id="f_bkJudul" required placeholder="Judul lengkap buku">
                            </div>
                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>Pengarang</label><input class="form-input" id="f_bkPengarang" placeholder="Nama penulis / pengarang"></div>
                                <div class="form-group"><label>Penerbit</label><input class="form-input" id="f_bkPenerbit" placeholder="Nama penerbit"></div>
                            </div>
                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>Tahun Terbit</label><input type="number" class="form-input" id="f_bkTahun" placeholder="Contoh: 2023"></div>
                                <div class="form-group">
                                    <label>Klasifikasi Buku <span style="color:var(--danger)">*</span></label>
                                    <select class="form-select" id="f_bkKategori" required>
                                        <option value="">-- Pilih Klasifikasi --</option>
                                        ${katOptions}
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 2: DATA PEROLEHAN BUKU -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-title" style="color:#15803d;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Data Perolehan
                            </div>
                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>Jumlah (Eksemplar)</label><input type="number" class="form-input" id="f_bkJml" value="1" min="1"></div>
                                <div class="form-group">
                                    <label>Asal Dana (Sumber)</label>
                                    <select class="form-select" id="f_bkAsal">
                                        ${asalOptions}
                                    </select>
                                </div>
                            </div>
                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>Tanggal Perolehan</label><input type="date" class="form-input" id="f_bkTglPerolehan" value="${new Date().toISOString().split('T')[0]}"></div>
                                <div class="form-group"><label>Harga (Satuan / Rp)</label><input type="text" class="form-input sp-rupiah-input" id="f_bkHarga" value="0"></div>
                            </div>
                        </div>

                        <!-- SECTION 3: MEDIA -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                Media & Cover Buku
                            </div>
                            <div id="bk_photo_preview" style="display:none; margin-bottom:15px; width:100px; height:140px; border-radius:10px; overflow:hidden; border:1px solid #e2e8f0;">
                                <img src="" style="width:100%; height:100%; object-fit:cover;">
                            </div>
                            <div class="form-group">
                                <label>Upload Foto Cover Buku</label>
                                <input type="file" class="form-input" id="f_bkFoto" accept="image/*">
                            </div>
                        </div>
                    </div>
                `,
                onOpen: () => {
                    if (isEdit || isTemplate) {
                        this.api(`sarpras.php?action=get&id=${id}`).done(res => {
                            const d = res.data;
                            $('#f_bkJudul').val(d.nama);
                            $('#f_bkPengarang').val(d.pengarang);
                            $('#f_bkPenerbit').val(d.penerbit);
                            $('#f_bkTahun').val(d.spesifikasi); // Using spesifikasi for year in book context
                            $('#f_bkKategori').val(d.jenis_sarana);
                            $('#f_bkAsal').val(d.asal_perolehan || 'BOS');
                            
                            if (!isAddMore) {
                                $('#f_bkTglPerolehan').val(d.tanggal_perolehan || new Date().toISOString().split('T')[0]);
                                $('#f_bkJml').val(d.jumlah || 1);
                                $('#f_bkHarga').val(Sarpras.formatNumber(d.harga_perolehan || 0));
                            } else {
                                $('#f_bkTglPerolehan').val(new Date().toISOString().split('T')[0]);
                                $('#f_bkJml').val(1);
                                $('#f_bkHarga').val(Sarpras.formatNumber(d.harga_perolehan || 0));
                            }

                            if (d.fotos && d.fotos.length > 0 && !isAddMore) {
                                $('#bk_photo_preview img').attr('src', window.SARPRAS_CONFIG.baseUrl + d.fotos[0].foto_path);
                                $('#bk_photo_preview').show();
                            }
                        });
                    }
                },
                onConfirm: () => {
                    const judul = $('#f_bkJudul').val().trim();
                    const klasifikasi = $('#f_bkKategori').val();

                    if (!judul) { EModal.toast({type:'error', title:'Error', message:'Judul Buku wajib diisi'}); return false; }
                    if (!klasifikasi) { EModal.toast({type:'error', title:'Error', message:'Pilih Klasifikasi Buku'}); return false; }

                    const fd = new FormData();
                    if (isEdit) fd.append('id', id);
                    fd.append('nama', judul); // Nama is used for Title in DB
                    fd.append('judul_buku', judul);
                    fd.append('pengarang', $('#f_bkPengarang').val());
                    fd.append('penerbit', $('#f_bkPenerbit').val());
                    fd.append('spesifikasi', $('#f_bkTahun').val()); // Using spesifikasi for year
                    fd.append('jenis_sarana', klasifikasi);
                    fd.append('kategori_id', '0'); // Backend automatically handles 'buku'
                    fd.append('asal_perolehan', $('#f_bkAsal').val());
                    fd.append('tanggal_perolehan', $('#f_bkTglPerolehan').val());
                    fd.append('grup_pintasan', 'buku');
                    
                    const jml = parseInt($('#f_bkJml').val() || 1);
                    fd.append('jumlah', jml);
                    fd.append('kondisi_baik', jml);
                    fd.append('kondisi_rusak_ringan', 0);
                    fd.append('kondisi_rusak_berat', 0);
                    
                    fd.append('kode_inventaris', 'Otomatis');
                    
                    const cleanedHarga = ($('#f_bkHarga').val() || '0').replace(/\./g, '');
                    fd.append('harga_perolehan', cleanedHarga);
                    fd.append('masa_manfaat_tahun', 5);

                    const fileInput = document.getElementById('f_bkFoto');
                    if (fileInput.files.length > 0) fd.append('foto', fileInput.files[0]);

                    const action = isEdit ? 'update' : 'create';
                    this.api(`sarpras.php?action=${action}`, { method: 'POST', data: fd }).done(() => {
                        EModal.closeAll();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: isEdit ? 'Data buku diperbarui' : 'Koleksi buku berhasil ditambahkan' });
                        this.loadRouteFromHash();
                    }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message}));
                    return false;
                }
            });
        });
    },

    formImportBuku(ruangId) {
        if (ruangId) {
            this.api('sarpras.php?action=list&grup=buku&per_page=500').done(res => {
                const bukuItems = (res.data?.data || []).filter(item => Number(item.jumlah || 0) > 0);

                EModal.form({
                    title: 'Import Buku dari Koleksi Buku',
                    size: 'lg',
                    confirmText: 'Import Buku',
                    form: `
                        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px 16px; margin-bottom:16px; color:#1e3a8a; font-size:13px; line-height:1.6;">
                            Pilih buku dari fitur <strong>Koleksi Buku</strong> yang ingin dipindahkan ke ruang perpustakaan ini.
                        </div>
                        <div class="form-group">
                            <label>Pilih Buku</label>
                            <div style="margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                                <label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer;">
                                    <input type="checkbox" id="f_importBooksSelectAll" ${bukuItems.length ? 'checked' : ''}>
                                    Pilih Semua
                                </label>
                                <input type="text" class="form-input" id="f_importBooksSearch" placeholder="Cari judul, kode, pengarang..." style="max-width:260px;">
                            </div>
                            <div id="f_importBooksContainer" style="border:1px solid #cbd5e1; border-radius:8px; max-height:340px; overflow-y:auto; padding:12px; background:#f8fafc;">
                                ${bukuItems.length ? bukuItems.map(item => `
                                    <label class="sp-import-book-item" style="display:flex; align-items:flex-start; gap:10px; padding:10px 8px; border-bottom:1px solid #e2e8f0; cursor:pointer;">
                                        <input type="checkbox" name="import_books[]" value="${item.id}" checked style="margin-top:4px;">
                                        <div style="min-width:0;">
                                            <div style="font-weight:700; color:#0f172a;">${this.escapeHtml(item.nama)}</div>
                                            <div style="font-size:12px; color:#64748b; line-height:1.5;">
                                                ${this.escapeHtml(item.kode_inventaris || '-')} • ${this.escapeHtml(item.pengarang || 'Pengarang tidak diisi')} • Stok: ${item.jumlah || 0}
                                            </div>
                                        </div>
                                    </label>
                                `).join('') : `<div class="sp-empty" style="padding:18px;">Belum ada data pada fitur Koleksi Buku yang siap diimport.</div>`}
                            </div>
                        </div>
                    `,
                    onOpen: () => {
                        const syncSelectAll = () => {
                            const $items = $('input[name="import_books[]"]:visible');
                            const total = $items.length;
                            const checked = $items.filter(':checked').length;
                            $('#f_importBooksSelectAll').prop('checked', total > 0 && total === checked);
                        };

                        $('#f_importBooksSelectAll').on('change', function() {
                            const checked = $(this).is(':checked');
                            $('input[name="import_books[]"]:visible').prop('checked', checked);
                        });

                        $(document).on('change.importBooks', 'input[name="import_books[]"]', syncSelectAll);

                        $('#f_importBooksSearch').on('input', function() {
                            const keyword = $(this).val().toLowerCase();
                            $('#f_importBooksContainer .sp-import-book-item').each(function() {
                                const match = $(this).text().toLowerCase().includes(keyword);
                                $(this).toggle(match);
                            });
                            syncSelectAll();
                        });
                    },
                    onConfirm: () => {
                        const selected = $('input[name="import_books[]"]:checked').map(function() {
                            return $(this).val();
                        }).get();

                        if (!selected.length) {
                            EModal.toast({type:'warning', title:'Pilih Buku', message:'Pilih minimal satu buku untuk diimport.'});
                            return false;
                        }

                        this.api('sarpras.php?action=import_koleksi_buku', {
                            method: 'POST',
                            data: { ruang_id: ruangId, item_ids: selected }
                        }).done(res2 => {
                            $(document).off('change.importBooks');
                            EModal.toast({title:'Import Berhasil', message: res2.message});
                            EModal.closeAll();
                            this.renderSarpras($('#mainContent'), ruangId);
                        }).fail(xhr => EModal.toast({type:'error', title:'Import Gagal', message: xhr.responseJSON?.message || 'Gagal mengimport buku.'}));
                        return false;
                    },
                    onCancel: () => {
                        $(document).off('change.importBooks');
                    }
                });
            }).fail(() => {
                EModal.toast({type:'error', title:'Gagal', message:'Gagal memuat data koleksi buku.'});
            });
            return;
        }

        EModal.form({
            title: 'Import Data Buku (CSV)',
            form: `
                <style>
                    .sp-drag-drop {
                        border: 2px dashed #94a3b8; border-radius: 12px; padding: 40px 20px;
                        text-align: center; cursor: pointer; transition: all 0.2s ease;
                        background: #f8fafc;
                    }
                    .sp-drag-drop:hover, .sp-drag-drop.dragover {
                        border-color: #3b82f6; background: #eff6ff;
                    }
                    .sp-drag-drop svg { width: 48px; height: 48px; color: #64748b; margin-bottom: 10px; }
                    .sp-drag-drop.dragover svg { color: #3b82f6; }
                </style>
                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:15px; margin-bottom:15px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:10px;">
                        <h4 style="margin:0; color:#0369a1; font-weight:700; font-size:14px; display:flex; align-items:center; gap:6px;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            Panduan Format CSV
                        </h4>
                        <a href="api/sarpras.php?action=template_buku" class="btn btn-outline btn-sm" style="white-space:nowrap; text-decoration:none; display:inline-flex; align-items:center; gap:6px; background:#fff;">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Download Template
                        </a>
                    </div>
                    <div style="color:#334155; font-size:13px; line-height:1.6;">
                        <ul style="margin:0; padding-left:20px; list-style-type:disc;">
                            <li>Baris pertama pada file CSV dianggap <b>header</b> dan akan diabaikan.</li>
                            <li>Pastikan urutan kolom sesuai urutan berikut:</li>
                        </ul>
                        <div style="margin-top:8px; padding:8px; background:#ffffff; border:1px solid #e2e8f0; border-radius:6px; font-family:monospace; font-size:12px; overflow-x:auto; white-space:nowrap; color:#0f172a;">
                            <b>Judul Buku, Pengarang, Penerbit, Tahun Perolehan, Klasifikasi Buku, Jumlah, Sumber Dana, Harga</b>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-top:15px;">
                    <div class="sp-drag-drop" id="bukuDropZone" onclick="document.getElementById('f_importBuku').click()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <h4 style="margin:0 0 5px 0; color:#334155;">Drag & Drop file CSV di sini</h4>
                        <p style="margin:0; font-size:13px; color:#64748b;">atau klik untuk memilih file</p>
                        <div id="bukuFileName" style="margin-top:10px; font-weight:bold; color:#0f172a;"></div>
                    </div>
                    <input type="file" id="f_importBuku" class="form-input" accept=".csv" required style="display:none;">
                </div>
            `,
            onOpen: () => {
                const dropZone = document.getElementById('bukuDropZone');
                const fileInput = document.getElementById('f_importBuku');
                const fileNameDisplay = document.getElementById('bukuFileName');

                dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
                dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
                dropZone.addEventListener('drop', (e) => {
                    e.preventDefault(); dropZone.classList.remove('dragover');
                    if (e.dataTransfer.files.length) {
                        fileInput.files = e.dataTransfer.files;
                        fileNameDisplay.textContent = e.dataTransfer.files[0].name;
                    }
                });
                
                fileInput.addEventListener('change', () => {
                    if (fileInput.files.length) fileNameDisplay.textContent = fileInput.files[0].name;
                });
            },
            onConfirm: () => {
                const fileInput = document.getElementById('f_importBuku');
                if (!fileInput.files.length) { EModal.toast({type:'error', title:'Perhatian', message:'Silakan pilih file CSV terlebih dahulu'}); return false; }
                
                const fd = new FormData();
                fd.append('file', fileInput.files[0]);
                fd.append('ruang_id', ruangId);
                
                this.api('sarpras.php?action=import_buku', { method: 'POST', data: fd }).done(res => {
                    EModal.toast({title: 'Import Berhasil', message: res.message});
                    EModal.closeAll();
                    this.renderSarpras($('#mainContent'), ruangId);
                }).fail(xhr => EModal.toast({type:'error', title:'Import Gagal', message: xhr.responseJSON?.message}));
                return false;
            }
        });
    },

    formCopySarpras(ruangId) {
        this.api('ruang.php?action=all').done(res => {
            const ruangOptions = res.data.filter(r => r.id != ruangId).map(r => `<option value="${r.id}">${r.tanah_nama} - ${r.bangunan_nama} - ${r.nama}</option>`).join('');
            
            EModal.form({
                title: 'Salin Aset dari Ruang Lain',
                form: `
                    <div class="form-group">
                        <label>Pilih Ruang Sumber</label>
                        <select class="form-select" id="f_copySrcRuang">
                            <option value="">-- Pilih Ruang --</option>
                            ${ruangOptions}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pilih Aset yang Akan Disalin</label>
                        <div id="f_copyItemsContainer" style="border:1px solid #cbd5e1; border-radius:6px; max-height:250px; overflow-y:auto; padding:10px; background:#f8fafc;">
                            <div class="sp-empty" style="padding:15px;">Silakan pilih ruang sumber terlebih dahulu</div>
                        </div>
                    </div>
                `,
                onOpen: () => {
                    $('#f_copySrcRuang').on('change', function() {
                        const srcId = $(this).val();
                        if (!srcId) {
                            $('#f_copyItemsContainer').html('<div class="sp-empty" style="padding:15px;">Silakan pilih ruang sumber terlebih dahulu</div>');
                            return;
                        }
                        $('#f_copyItemsContainer').html('<div class="sp-empty" style="padding:15px;">Memuat data...</div>');
                        Sarpras.api(`sarpras.php?action=list&ruang_id=${srcId}&per_page=100`).done(r2 => {
                            if (!r2.data.data.length) {
                                $('#f_copyItemsContainer').html('<div class="sp-empty" style="padding:15px;">Ruang ini tidak memiliki aset / barang.</div>');
                                return;
                            }
                            const itemsHtml = r2.data.data.map(item => `
                                <label style="display:flex; align-items:flex-start; margin-bottom:10px; cursor:pointer;">
                                    <input type="checkbox" name="copy_items[]" value="${item.id}" checked style="margin-top:3px; margin-right:8px;">
                                    <div>
                                        <strong>${item.nama}</strong> <small style="color:#64748b">(${item.kategori_nama})</small><br>
                                        <small>${item.jumlah} Unit | Merk: ${item.merk||'-'}</small>
                                    </div>
                                </label>
                            `).join('');
                            $('#f_copyItemsContainer').html(`<div style="margin-bottom:10px;"><label><input type="checkbox" id="f_copySelectAll" checked> Pilih Semua</label></div>${itemsHtml}`);
                            
                            $('#f_copySelectAll').on('change', function() {
                                $('input[name="copy_items[]"]').prop('checked', $(this).prop('checked'));
                            });
                        });
                    });
                },
                onConfirm: () => {
                    const srcId = $('#f_copySrcRuang').val();
                    const itemIds = [];
                    $('input[name="copy_items[]"]:checked').each(function() { itemIds.push($(this).val()); });
                    
                    if (!srcId) { EModal.toast({type:'error', title:'Error', message:'Pilih ruang sumber'}); return false; }
                    if (!itemIds.length) { EModal.toast({type:'error', title:'Error', message:'Pilih minimal 1 aset untuk disalin'}); return false; }
                    
                    this.api('sarpras.php?action=copy_to_ruang', { method: 'POST', data: { source_ruang_id: srcId, target_ruang_id: ruangId, item_ids: itemIds } }).done(r => {
                        EModal.toast({title: 'Berhasil', message: r.message});
                        EModal.closeAll();
                        this.renderSarpras($('#mainContent'), ruangId);
                    }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message}));
                    return false;
                }
            });
        });
    },

    formSarpras(id, ruangId, groupFilter = null, isAddMore = false) {
        const isEdit = id !== null && id !== undefined && !isAddMore;
        const isTemplate = isAddMore && id !== null;

        // If adding to room, we fetch from warehouse (ruang_id is NULL)
        let catalogUrl = (ruangId && !isEdit) 
            ? 'sarpras.php?action=list&ruang_id=0&per_page=500' 
            : 'master_sarpras.php?action=list';

        if (groupFilter && !isEdit) {
            catalogUrl += `&grup=${groupFilter}`;
        }

        $.when(
            this.api('manage.php?entity=kategori&action=list'),
            this.api(catalogUrl),
            this.api('ruang.php?action=list')
        ).done((resKat, resCatalog, resRuang) => {
            const kData = resKat[0].data || [];
            const rData = resRuang[0].data || [];
            const mData = (ruangId && !isEdit) ? (resCatalog[0].data.data || []) : (resCatalog[0].data || []);
            
            const katOptions = kData.map(k => `<option value="${k.id}">${k.nama}</option>`).join('');
            const ruangOptions = rData.map(r => `<option value="${r.id}">${r.nama} (${r.bangunan_nama})</option>`).join('');
            
            let customSelectOptions = '';
            if (ruangId && !isEdit) {
                // Catalog from Warehouse Stock
                customSelectOptions = mData.map(m => `
                    <div class="sp-cs-option" data-id="${m.id}" data-nama="${m.nama}" data-source-id="${m.id}" data-kat="${m.kategori_id}" data-kode="${m.kode_inventaris}" data-merk="${m.merk||''}" data-manfaat="${m.masa_manfaat_tahun||5}" data-harga="${m.harga_perolehan||0}" data-tgl="${m.tanggal_perolehan||''}" data-available="${m.jumlah}">
                        <div class="cs-opt-m">${m.nama}</div>
                        <div class="cs-opt-k">${m.kode_inventaris} | ${m.kategori_nama}</div>
                    </div>
                `).join('');
            } else {
                // Catalog from Master Reference
                customSelectOptions = mData.map(m => `
                    <div class="sp-cs-option" data-id="${m.id}" data-nama="${m.nama}" data-kat="${m.kategori_id}" data-kode="${m.kode||''}" data-merk="${m.merk_default||''}" data-manfaat="${m.masa_manfaat_default||5}" data-harga="${m.harga_perolehan||0}" data-tgl="${m.tanggal_perolehan||''}">
                        <div class="cs-opt-m">${m.nama}</div>
                        <div class="cs-opt-k">${m.kategori_nama}</div>
                    </div>
                `).join('');
            }
            
            const csFallback = customSelectOptions || `<div style="padding:15px; text-align:center; color:var(--text-muted); font-size:0.85rem;">Belum ada master data untuk kategori ini. Silakan tambahkan di menu Data Sarpras.</div>`;

            let formTitle = isEdit ? 'Edit Barang' : (isAddMore ? 'Tambah Batch Baru' : 'Tambah Barang Baru');
            if (groupFilter === 'ahp-bhp') formTitle = 'Tambah Barang (AHP & BHP)';
            else if (groupFilter === 'angkutan') formTitle = 'Tambah Alat Angkutan';
            else if (groupFilter === 'buku') formTitle = 'Tambah Koleksi Buku';

            EModal.form({
                title: formTitle,
                    size: 'lg',
                    form: `
                        <style>
                            .sp-form-section { margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px dashed #e2e8f0; }
                            .sp-form-section:last-child { border-bottom: none; }
                            .sp-form-section-title { 
                                font-family: var(--font-heading); font-size: 0.9rem; font-weight: 700; 
                                color: var(--primary); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
                                text-transform: uppercase; letter-spacing: 0.5px;
                            }
                            .sp-form-section-title svg { width: 16px; height: 16px; }

                            .sp-cs-container { position:relative; user-select:none; margin-bottom: 20px; }
                            .sp-cs-btn { 
                                cursor:pointer; display:flex; justify-content:space-between; align-items:center; 
                                background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:10px 15px;
                                height:48px; transition: all 0.2s;
                            }
                            .sp-cs-btn:hover { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
                            .sp-cs-btn.active { border-color: #3b82f6; }
                            
                            .sp-cs-dropdown { 
                                display:none; position:absolute; top:calc(100% + 5px); left:0; right:0; 
                                background:#fff; border:1px solid #cbd5e1; border-radius:10px; 
                                box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:9999; overflow:hidden;
                            }
                            .sp-cs-option { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
                            .sp-cs-option:hover { background: #f1f5f9; }
                            .cs-opt-m { font-weight: 600; color: #1e293b; font-size: 0.95rem; }
                            .cs-opt-k { font-size: 0.8rem; color: #64748b; margin-top: 2px; }
                            
                            .sp-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                            .sp-form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
                            
                            @media (max-width: 768px) {
                                .sp-form-grid-2, .sp-form-grid-3 { grid-template-columns: 1fr !important; }
                            }
                        </style>
                        <input type="hidden" id="f_sRuangId" value="${ruangId}">
                        <input type="hidden" id="f_sNamaHidden" value="">
                        <input type="hidden" id="f_sMaster" value="">
                        <input type="hidden" id="f_sSourceId" value="">
                        <input type="hidden" id="f_sAvailable" value="">
                        
                        <!-- SECTION 1: IDENTITAS BARANG -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                Identitas & Katalog Barang
                            </div>
                            
                            <div class="form-group">
                                <label>Pilih Barang dari Katalog</label>
                                <div class="sp-cs-container" id="customSelectContainer">
                                    <div class="sp-cs-btn" id="customSelectBtn">
                                        <span id="csSelectedText" style="color:#64748b; font-size:0.95rem;">-- Pilih Barang dari Master --</span>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </div>
                                    <div class="sp-cs-dropdown" id="customSelectDropdown">
                                        <div style="padding:12px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                                            <input type="text" id="csSearchInput" class="form-input" placeholder="Cari nama barang..." style="width:100%; padding:10px 14px; height:42px; border-radius:8px; border:1px solid #cbd5e1; outline:none;" autocomplete="off">
                                        </div>
                                        <div id="csOptionsList" style="max-height:250px; overflow-y:auto; padding:5px 0;">
                                            ${csFallback}
                                        </div>
                                    </div>
                                </div>
                                <div id="csStockLabel" style="display:none; margin-top: 5px; margin-bottom: 20px; font-size: 0.8rem; color: var(--primary); font-weight: 600; background: #eff6ff; padding: 8px 12px; border-radius: 8px; border: 1px solid #bfdbfe; align-items: center; gap: 6px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                    Stok Gudang Tersedia: <span id="csStockValue">0</span>
                                </div>
                            </div>

                            <div class="sp-form-grid-2">
                                ${(!ruangId || ['ahp-bhp', 'angkutan', 'buku'].includes(this.state.currentRoute)) ? '' : `<div class="form-group"><label>Lokasi / Ruangan</label><select class="form-select" id="f_sRuangIdSelect" disabled>${ruangOptions}</select></div>`}
                                <div class="form-group" style="${(!ruangId || ['ahp-bhp', 'angkutan', 'buku'].includes(this.state.currentRoute)) ? 'grid-column: span 2;' : ''}"><label>Kategori</label><select class="form-select" id="f_sKat" disabled>${katOptions}</select></div>
                            </div>
                        </div>

                        <!-- ANGKUTAN KHUSUS (CONDITIONAL) -->
                        <div class="sp-layout-angkutan" style="display:none; background:#f0fdf4; padding:20px; border-radius:12px; margin-bottom:24px; border:1px solid #bbf7d0;">
                            <div class="sp-form-section-title" style="color:#15803d;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polyline points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                Data Kendaraan / Angkutan
                            </div>
                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>No. Polisi</label><input class="form-input" id="f_sNoPolisi" placeholder="Contoh: DN 1234 AB"></div>
                                <div class="form-group"><label>No. BPKB</label><input class="form-input" id="f_sNoBPKB" placeholder="Nomor BPKB"></div>
                            </div>
                            <div class="form-group"><label>Alamat Pemilik (STNK/BPKB)</label><input class="form-input" id="f_sAlamat" placeholder="Alamat sesuai dokumen"></div>
                            <div class="form-group">
                                <label>Status Kepemilikan</label>
                                <select class="form-select" id="f_sKepemilikan">
                                    <option value="Milik Sendiri">Milik Sendiri</option>
                                    <option value="Pinjam Pakai">Pinjam Pakai</option>
                                    <option value="Sewa">Sewa</option>
                                    <option value="Hibah">Hibah</option>
                                </select>
                            </div>
                        </div>

                        <!-- BUKU KHUSUS (CONDITIONAL) -->
                        <div class="sp-layout-buku" style="display:none; background:#f0f9ff; padding:20px; border-radius:12px; margin-bottom:24px; border:1px solid #bae6fd;">
                            <div class="sp-form-section-title" style="color:#0369a1;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                Data Koleksi Buku
                            </div>
                            <div class="form-group"><label>Judul Buku</label><input class="form-input" id="f_sJudulBuku" placeholder="Judul Buku Lengkap"></div>
                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>Pengarang</label><input class="form-input" id="f_sPengarang"></div>
                                <div class="form-group"><label>Penerbit</label><input class="form-input" id="f_sPenerbit"></div>
                            </div>
                        </div>

                        <!-- SECTION 2: DETAIL PEROLEHAN -->
                        <div class="sp-form-section" ${(ruangId && !isEdit) || groupFilter ? 'style="display:none"' : ''}>
                            <div class="sp-form-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Detail & Perolehan
                            </div>
                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>Kode Inventaris</label><input class="form-input" id="f_sKode" placeholder="Pilih katalog dlu" readonly style="background:#f8f9fa;"></div>
                                <div class="form-group"><label>Merk / Model</label><input class="form-input" id="f_sMerk" placeholder="Contoh: Honda, Sharp, Erlangga"></div>
                            </div>
                            <div class="sp-form-grid-2">
                                <div class="form-group"><label>Tanggal Perolehan</label><input type="date" class="form-input" id="f_sTglPerolehan" value="${new Date().toISOString().split('T')[0]}"></div>
                                <div class="form-group"><label>Masa Manfaat (Tahun)</label><input type="number" class="form-input" id="f_sManfaat" value="5"></div>
                            </div>
                            <div class="form-group"><label>Spesifikasi (Ukuran, Bahan, Warna, dll)</label><textarea class="form-input" id="f_sSpek" rows="2" placeholder="Detail fisik barang..."></textarea></div>
                        </div>

                        <!-- SECTION 3: KUANTITAS & KONDISI -->
                        <div class="sp-form-section">
                            <div>
                                <div class="sp-form-section-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    Kuantitas & Harga
                                </div>
                                <div class="sp-form-grid-2">
                                    <div class="form-group"><label>Jumlah (Unit/Eksemplar)</label><input type="number" class="form-input" id="f_sJml" value="1"></div>
                                    <div class="form-group" ${ruangId && !isEdit ? 'style="display:none"' : ''}><label>Harga (Satuan)</label><input type="text" class="form-input sp-rupiah-input" id="f_sHarga" value="0"></div>
                                </div>
                            </div>
                            
                            <div class="sp-form-section-title" style="margin-top:10px; font-size:0.8rem; opacity:0.8;">Status Kondisi</div>
                            <div class="sp-form-grid-3">
                                <div class="form-group"><label style="color:var(--success)">Kondisi Baik</label><input type="number" class="form-input" id="f_sBaik" value="0"></div>
                                <div class="form-group"><label style="color:var(--warning)">Rusak Ringan</label><input type="number" class="form-input" id="f_sRR" value="0"></div>
                                <div class="form-group"><label style="color:var(--danger)">Rusak Berat</label><input type="number" class="form-input" id="f_sRB" value="0"></div>
                            </div>
                        </div>

                        <!-- SECTION 4: LAMPIRAN -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                Media & Catatan
                            </div>
                            <div class="sp-form-grid-2" style="grid-template-columns: 2fr 3fr;">
                                <div class="form-group"><label>Upload Foto Utama</label><input type="file" class="form-input" id="f_sFoto" accept="image/*"></div>
                                <div class="form-group"><label>Keterangan Tambahan</label><textarea class="form-input" id="f_sKet" rows="2" placeholder="Catatan opsional..."></textarea></div>
                            </div>
                        </div>
                    `,
                    onOpen: () => {
                        const toggleSpecialLayouts = () => {
                            const katText = $('#f_sKat option:selected').text().toLowerCase();
                            const isBuku = katText.includes('buku');
                            const isAngkutan = katText.includes('angkutan') || katText.includes('kendaraan') || this.state.currentRoute === 'angkutan';
                            
                            // Hide special layouts if it's a distribution (picking from master)
                            const isPickingMaster = !!groupFilter;

                            $('.sp-layout-buku').toggle(isBuku && !isPickingMaster);
                            $('.sp-layout-angkutan').toggle(isAngkutan && !isPickingMaster);
                            
                            if (isBuku) { $('#f_sMerk').val('-'); }
                        };
                        $('#f_sKat').on('change', toggleSpecialLayouts);
                        // Custom dropdown logic
                        $('#customSelectBtn').on('click', function(e) {
                            e.stopPropagation();
                            $(this).toggleClass('active');
                            $('#customSelectDropdown').toggle();
                            if ($('#customSelectDropdown').is(':visible')) {
                                $('#csSearchInput').val('').trigger('input').focus();
                            }
                        });

                        $(document).on('click.csDropdown', function(e) {
                            if (!$(e.target).closest('#customSelectContainer').length) {
                                $('#customSelectDropdown').hide();
                                $('#customSelectBtn').removeClass('active');
                            }
                        });

                        $('#csSearchInput').on('input', function() {
                            const term = $(this).val().toLowerCase();
                            $('.sp-cs-option').each(function() {
                                const text = $(this).text().toLowerCase();
                                $(this).toggle(text.includes(term));
                            });
                        });

                        $('#csOptionsList').on('click', '.sp-cs-option', function() {
                            const d = $(this).data();
                            $('#f_sNamaHidden').val(d.nama);
                            $('#f_sMaster').val(d.id);
                            
                            if (d.sourceId) {
                                $('#f_sSourceId').val(d.sourceId);
                                $('#f_sKode').val(d.kode); // Use existing code for distribution
                            } else {
                                $('#f_sSourceId').val('');
                                $('#f_sKode').val('Otomatis'); // Let backend generate new code for new item
                            }

                            if (d.available !== undefined) {
                                $('#f_sAvailable').val(d.available);
                                $('#csStockValue').text(d.available);
                                $('#csStockLabel').css('display', 'flex').show();
                            } else {
                                $('#csStockLabel').hide();
                            }
                            
                            $('#f_sMerk').val(d.merk);
                            $('#f_sManfaat').val(d.manfaat);
                            $('#f_sHarga').val(Sarpras.formatNumber(d.harga));
                            $('#f_sTglPerolehan').val(d.tgl || new Date().toISOString().split('T')[0]);
                            
                            $('#f_sKat').val(d.kat).trigger('change');
                            $('#csSelectedText').html(`<div style="font-weight:600; font-size:0.9rem; color:#1e293b;">${d.nama}</div><div style="font-size:0.75rem; color:#64748b;">${d.kode}</div>`);
                            $('#customSelectDropdown').hide();
                            $('#customSelectBtn').removeClass('active');
                        });
                        
                        if (ruangId) {
                            $('#f_sRuangIdSelect').val(ruangId);
                        }

                        if (isEdit || isTemplate) {
                            this.api(`sarpras.php?action=get&id=${id}`).done(res => {
                                const d = res.data;
                                $('#f_sRuangIdSelect').val(d.ruang_id);
                                const matchedOpt = mData.find(m => m.nama === d.nama);
                                if (matchedOpt) {
                                    $('#f_sMaster').val(matchedOpt.id);
                                    $('#csSelectedText').html(`<div style="font-weight:600; font-size:0.9rem; color:#1e293b;">${d.nama}</div><div style="font-size:0.75rem; color:#64748b;">${d.kode_inventaris}</div>`);
                                } else {
                                    $('#csSelectedText').text(d.judul_buku || d.nama).css('color', '#1e293b');
                                    $('#f_sMaster').val('custom');
                                }
                                
                                $('#f_sKat').val(d.kategori_id).trigger('change');
                                $('#f_sNamaHidden').val(d.nama); 
                                $('#f_sKode').val(isAddMore ? d.kode_inventaris : d.kode_inventaris); // Maintain same code
                                $('#f_sJudulBuku').val(d.judul_buku);
                                $('#f_sPengarang').val(d.pengarang);
                                $('#f_sPenerbit').val(d.penerbit);
                                $('#f_sMerk').val(d.merk); 
                                
                                if (!isAddMore) {
                                    $('#f_sJml').val(d.jumlah); 
                                    $('#f_sBaik').val(d.kondisi_baik);
                                    $('#f_sRR').val(d.kondisi_rusak_ringan); 
                                    $('#f_sRB').val(d.kondisi_rusak_berat);
                                    $('#f_sTglPerolehan').val(d.tanggal_perolehan);
                                    $('#f_sHarga').val(this.formatNumber(d.harga_perolehan));
                                } else {
                                    // Reset batch-specific fields for Add More
                                    $('#f_sJml').val(1);
                                    $('#f_sBaik').val(1);
                                    $('#f_sRR').val(0);
                                    $('#f_sRB').val(0);
                                    $('#f_sTglPerolehan').val(new Date().toISOString().split('T')[0]);
                                    $('#f_sHarga').val(this.formatNumber(d.harga_perolehan));
                                }
                                
                                $('#f_sManfaat').val(d.masa_manfaat_tahun);
                                $('#f_sSpek').val(d.spesifikasi);
                                $('#f_sKet').val(d.keterangan);

                                // Load Vehicle fields
                                $('#f_sNoPolisi').val(d.no_polisi);
                                $('#f_sNoBPKB').val(d.no_bpkb);
                                $('#f_sAlamat').val(d.alamat);
                                $('#f_sKepemilikan').val(d.kepemilikan || 'Milik Sendiri');
                            });
                        }
                    },
                    onConfirm: () => {
                        let finalNama = $('#f_sNamaHidden').val();
                        if ($('#f_sKat option:selected').text().toLowerCase().includes('buku')) {
                            finalNama = $('#f_sJudulBuku').val() || finalNama;
                        }

                        if (!finalNama && !$('#f_sMaster').val()) { EModal.toast({type:'error', title:'Error', message:'Silakan pilih barang dari katalog atau isi judul buku'}); return false; }
                        
                        const noLocationNeeded = !ruangId || ['ahp-bhp', 'angkutan', 'buku'].includes(this.state.currentRoute);
                        if (!noLocationNeeded && !$('#f_sRuangIdSelect').val()) { EModal.toast({type:'error', title:'Error', message:'Silakan pilih lokasi/ruangan'}); return false; }

                        const sourceId = $('#f_sSourceId').val();
                        const available = parseInt($('#f_sAvailable').val() || 0);
                        const jml = parseInt($('#f_sJml').val() || 0);
                        const baik = parseInt($('#f_sBaik').val() || 0);
                        const rr = parseInt($('#f_sRR').val() || 0);
                        const rb = parseInt($('#f_sRB').val() || 0);

                        if (sourceId && jml > available) {
                            EModal.toast({type:'error', title:'Error', message:`Jumlah (${jml}) melebihi stok gudang (${available})`});
                            return false;
                        }

                        if ((baik + rr + rb) !== jml) {
                            EModal.toast({type:'error', title:'Error', message:`Total kondisi (${baik}+${rr}+${rb}) harus sama dengan Jumlah (${jml})`});
                            return false;
                        }

                        const fd = new FormData();
                        if (isEdit) fd.append('id', id);
                        fd.append('ruang_id', noLocationNeeded ? '' : $('#f_sRuangIdSelect').val());
                        if (sourceId) fd.append('source_id', sourceId);
                        fd.append('kategori_id', $('#f_sKat').val());
                        fd.append('nama', finalNama);
                        fd.append('judul_buku', $('#f_sJudulBuku').val());
                        fd.append('pengarang', $('#f_sPengarang').val());
                        fd.append('penerbit', $('#f_sPenerbit').val());
                        fd.append('kode_inventaris', $('#f_sKode').val());
                        fd.append('merk', $('#f_sMerk').val());
                        fd.append('spesifikasi', $('#f_sSpek').val());
                        fd.append('jumlah', $('#f_sJml').val());
                        fd.append('kondisi_baik', $('#f_sBaik').val());
                        fd.append('kondisi_rusak_ringan', $('#f_sRR').val());
                        fd.append('kondisi_rusak_berat', $('#f_sRB').val());
                        fd.append('tanggal_perolehan', $('#f_sTglPerolehan').val());
                        const cleanedHarga = ($('#f_sHarga').val() || '0').replace(/\./g, '');
                        fd.append('harga_perolehan', cleanedHarga);
                        fd.append('masa_manfaat_tahun', $('#f_sManfaat').val());
                        fd.append('keterangan', $('#f_sKet').val());
                        
                        // Append Vehicle fields
                        fd.append('no_polisi', $('#f_sNoPolisi').val());
                        fd.append('no_bpkb', $('#f_sNoBPKB').val());
                        fd.append('alamat', $('#f_sAlamat').val());
                        fd.append('kepemilikan', $('#f_sKepemilikan').val());
                        
                        if (['ahp-bhp', 'angkutan', 'buku'].includes(this.state.currentRoute)) {
                            fd.append('grup_pintasan', this.state.currentRoute);
                        }

                        const fileInput = document.getElementById('f_sFoto');
                        if (fileInput.files.length > 0) fd.append('foto', fileInput.files[0]);

                        this.api(`sarpras.php?action=${isEdit?'update':'create'}`, { method: 'POST', data: fd }).done(() => {
                            EModal.closeAll();
                            EModal.toast({ type: 'success', title: 'Berhasil', message: isEdit ? 'Data berhasil diperbarui' : 'Barang berhasil ditambahkan' });
                            if (ruangId) this.renderSarpras($('#mainContent'), ruangId);
                            else this.loadRouteFromHash(); // Refresh current global view
                            $(document).off('click.csDropdown');
                        }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message:xhr.responseJSON?.message}));
                        return false;
                    },
                    onClose: () => {
                        $(document).off('click.csDropdown');
                    }
                });
            });
    },

    delSarpras(id, name, ruangId) {
        EModal.confirm({
            title: 'Hapus Barang', message: `Yakin hapus <strong>${name}</strong>?`, type: 'danger',
            onConfirm: () => this.api('sarpras.php?action=delete', { method: 'POST', data: { id } }).done(() => this.renderSarpras($('#mainContent'), ruangId))
        });
    },

    // Placeholder for remaining detail renderers
    /**
     * PAGE: MASTER SARPRAS (Katalog Aset)
     */
    renderMasterSarpras($container) {
        $container.html('<div class="skeleton" style="height:300px"></div>');
        this.api('manage.php?entity=kategori&action=list').done(resKat => {
            const katOptions = resKat.data.map(k => `<option value="${k.id}">${k.nama}</option>`).join('');
            
            $container.html(`
                <div class="sp-card">
                    <div class="sp-card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; flex-wrap: wrap; gap: 10px;">
                        <h3 style="margin: 0; white-space: nowrap;">Katalog Jenis Aset (Master Sarpras)</h3>
                        <div class="sp-toolbar" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1; justify-content: flex-end;">
                            <div class="sp-search-box" style="position: relative; min-width: 200px; flex: 1; max-width: 300px;">
                                <input type="text" id="f_searchMS" class="form-input" placeholder="Cari nama barang..." style="padding-left: 35px; width: 100%; height: 38px; margin:0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #94a3b8;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </div>
                            <select class="form-select" id="f_filterKat" style="min-width: 180px; width: auto; height: 38px; margin:0; flex-shrink: 0;">
                                <option value="">-- Semua Kategori --</option>
                                ${katOptions}
                            </select>
                            <button class="btn btn-secondary" onclick="Sarpras.formImportMasterSarpras()" style="white-space: nowrap; height: 38px; display: flex; align-items: center; gap: 8px; padding: 0 15px; flex-shrink: 0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <span>Import</span>
                            </button>
                            <button class="btn btn-primary" onclick="Sarpras.formMasterSarpras(null)" style="white-space: nowrap; height: 38px; display: flex; align-items: center; gap: 8px; padding: 0 15px; flex-shrink: 0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                <span>Tambah Baru</span>
                            </button>
                        </div>
                    </div>
                    <div class="sp-card-body">
                        <div class="sp-table-wrapper" id="masterTable"></div>
                    </div>
                </div>
            `);

            let searchTimer;
            const loadMaster = () => {
                const katId = $('#f_filterKat').val();
                const search = $('#f_searchMS').val();
                let url = `master_sarpras.php?action=list`;
                if (katId) url += `&kategori_id=${katId}`;
                if (search) url += `&search=${encodeURIComponent(search)}`;

                this.api(url).done(res => {
                    if (!res.data.length) { 
                        $('#masterTable').html('<div class="sp-empty">Tidak ada data yang ditemukan.</div>'); 
                        return; 
                    }
                    const rows = res.data.map(m => `
                        <tr>
                            <td><strong>${m.nama}</strong><br><small class="text-muted">${m.kategori_nama}</small></td>
                            <td><code class="sp-code">${m.kode || '-'}</code></td>
                            <td>${m.masa_manfaat_default} Tahun</td>
                            <td>
                                <div class="sp-actions">
                                    <button class="sp-btn-icon" title="Edit" onclick="Sarpras.formMasterSarpras(${m.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                    <button class="sp-btn-icon danger" title="Hapus" onclick="Sarpras.delMasterSarpras(${m.id}, '${m.nama.replace(/'/g, "\\'")}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                    $('#masterTable').html(`<table class="sp-table"><thead><tr><th>Nama / Kategori</th><th>Kode Barang</th><th>Masa Manfaat</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
                });
            };
            
            $('#f_filterKat').on('change', loadMaster);
            $('#f_searchMS').on('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(loadMaster, 300);
            });
            loadMaster();
        });
    },

    formImportMasterSarpras() {
        const templateUrl = `api/master_sarpras.php?action=template-csv&token=${this.getToken()}`;
        this._masterImportFile = null;

        EModal.form({
            title: 'Import Master Sarpras',
            size: 'xl',
            confirmText: 'Import Data',
            form: `
                <style>
                    .sp-import-shell {
                        display:grid;
                        grid-template-columns:minmax(0, 1.08fr) minmax(320px, 0.92fr);
                        gap:24px;
                        align-items:start;
                    }
                    .sp-import-card {
                        position:relative;
                        border:1px solid #dbe3ef;
                        border-radius:24px;
                        background:linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                        padding:24px;
                        box-shadow:0 18px 50px rgba(15, 23, 42, 0.06);
                        overflow:hidden;
                    }
                    .sp-import-card::before {
                        content:'';
                        position:absolute;
                        inset:0 0 auto 0;
                        height:1px;
                        background:linear-gradient(90deg, rgba(59,130,246,0.12), rgba(255,255,255,0));
                    }
                    .sp-import-card--upload {
                        background:
                            radial-gradient(circle at top left, rgba(59,130,246,0.12), transparent 34%),
                            linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
                    }
                    .sp-import-card--guide {
                        background:
                            radial-gradient(circle at top right, rgba(14,165,233,0.10), transparent 34%),
                            linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    }
                    .sp-import-section-title {
                        margin:0 0 18px;
                        display:flex;
                        align-items:center;
                        gap:10px;
                        font-family:var(--font-heading);
                        font-size:0.78rem;
                        font-weight:800;
                        letter-spacing:0.12em;
                        text-transform:uppercase;
                        color:#1d4ed8;
                    }
                    .sp-import-section-title svg {
                        width:18px;
                        height:18px;
                        flex-shrink:0;
                    }
                    .sp-import-hero {
                        margin-bottom:18px;
                    }
                    .sp-import-hero-badge {
                        display:inline-flex;
                        align-items:center;
                        gap:8px;
                        padding:8px 12px;
                        border-radius:999px;
                        background:#dbeafe;
                        color:#1d4ed8;
                        font-size:0.74rem;
                        font-weight:800;
                        letter-spacing:0.08em;
                        text-transform:uppercase;
                        margin-bottom:12px;
                    }
                    .sp-import-hero h4,
                    .sp-import-preview-head h4 {
                        margin:0 0 8px;
                        color:#0f172a;
                        font-size:1.25rem;
                        line-height:1.25;
                    }
                    .sp-import-hero p,
                    .sp-import-preview-head p {
                        margin:0;
                        color:#475569;
                        font-size:0.93rem;
                        line-height:1.65;
                    }
                    .sp-import-dropzone {
                        border:2px dashed #93c5fd;
                        border-radius:24px;
                        background:linear-gradient(180deg, rgba(239,246,255,0.96) 0%, rgba(248,250,252,0.98) 100%);
                        padding:28px 24px;
                        min-height:264px;
                        display:flex;
                        flex-direction:column;
                        align-items:center;
                        justify-content:center;
                        text-align:center;
                        cursor:pointer;
                        transition:all 0.22s ease;
                    }
                    .sp-import-dropzone:hover,
                    .sp-import-dropzone.dragover {
                        border-color:#2563eb;
                        background:linear-gradient(180deg, #dbeafe 0%, #eff6ff 100%);
                        box-shadow:0 0 0 5px rgba(37, 99, 235, 0.08);
                        transform:translateY(-1px);
                    }
                    .sp-import-dropzone.file-ready {
                        border-color:#2563eb;
                        background:linear-gradient(180deg, #dbeafe 0%, #f8fbff 100%);
                    }
                    .sp-import-dropzone-icon {
                        width:78px;
                        height:78px;
                        border-radius:24px;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        background:linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                        color:#fff;
                        box-shadow:0 18px 32px rgba(37, 99, 235, 0.2);
                        margin-bottom:18px;
                    }
                    .sp-import-dropzone-icon svg {
                        width:34px;
                        height:34px;
                        margin:0;
                        color:currentColor;
                    }
                    .sp-import-dropzone h4 {
                        margin:0 0 10px;
                        color:#0f172a;
                        font-size:1.08rem;
                    }
                    .sp-import-dropzone p {
                        margin:0;
                        max-width:420px;
                        color:#475569;
                        font-size:0.92rem;
                        line-height:1.65;
                    }
                    .sp-import-dropzone-meta {
                        display:flex;
                        gap:10px;
                        flex-wrap:wrap;
                        justify-content:center;
                        margin-top:16px;
                    }
                    .sp-import-dropzone-meta span {
                        display:inline-flex;
                        align-items:center;
                        padding:7px 12px;
                        border-radius:999px;
                        background:rgba(255,255,255,0.9);
                        border:1px solid rgba(148,163,184,0.32);
                        color:#334155;
                        font-size:0.76rem;
                        font-weight:700;
                    }
                    .sp-import-file-chip {
                        display:none;
                        margin-top:16px;
                        padding:14px 16px;
                        border-radius:18px;
                        border:1px solid #bfdbfe;
                        background:#ffffff;
                        color:#0f172a;
                        text-align:left;
                        gap:12px;
                        align-items:flex-start;
                    }
                    .sp-import-file-chip-icon {
                        width:38px;
                        height:38px;
                        border-radius:12px;
                        background:#dbeafe;
                        color:#1d4ed8;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        flex-shrink:0;
                    }
                    .sp-import-file-chip-icon svg {
                        width:18px;
                        height:18px;
                    }
                    .sp-import-file-chip strong {
                        display:block;
                        margin-bottom:2px;
                        font-size:0.92rem;
                    }
                    .sp-import-file-chip span {
                        display:block;
                        color:#64748b;
                        font-size:0.8rem;
                        line-height:1.5;
                    }
                    .sp-import-actions {
                        display:flex;
                        gap:10px;
                        flex-wrap:wrap;
                        margin-top:18px;
                    }
                    .sp-import-quicklist {
                        display:grid;
                        gap:10px;
                        margin-top:18px;
                    }
                    .sp-import-quickitem {
                        display:flex;
                        gap:12px;
                        align-items:flex-start;
                        padding:12px 14px;
                        border-radius:16px;
                        background:rgba(255,255,255,0.92);
                        border:1px solid #e2e8f0;
                    }
                    .sp-import-quickitem-badge {
                        width:28px;
                        height:28px;
                        border-radius:999px;
                        background:#dbeafe;
                        color:#1d4ed8;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:0.8rem;
                        font-weight:800;
                        flex-shrink:0;
                    }
                    .sp-import-quickitem strong {
                        display:block;
                        margin-bottom:3px;
                        color:#0f172a;
                        font-size:0.88rem;
                    }
                    .sp-import-quickitem span {
                        color:#475569;
                        font-size:0.84rem;
                        line-height:1.5;
                    }
                    .sp-import-note {
                        margin-top:18px;
                        padding:15px 16px;
                        border-radius:18px;
                        background:linear-gradient(180deg, #fff7ed 0%, #fffbeb 100%);
                        border:1px solid #fed7aa;
                        color:#9a3412;
                        font-size:0.86rem;
                        line-height:1.65;
                    }
                    .sp-import-note strong { color:#7c2d12; }
                    .sp-import-note code,
                    .sp-import-mini-item code {
                        display:inline-block;
                        padding:2px 7px;
                        border-radius:8px;
                        background:rgba(255,255,255,0.9);
                        border:1px solid rgba(251,146,60,0.25);
                        color:#9a3412;
                        font-size:0.78rem;
                        font-weight:700;
                    }
                    .sp-import-preview-head {
                        display:flex;
                        align-items:flex-start;
                        justify-content:space-between;
                        gap:16px;
                        margin-bottom:16px;
                    }
                    .sp-import-pill {
                        display:inline-flex;
                        align-items:center;
                        padding:8px 12px;
                        border-radius:999px;
                        background:#e0f2fe;
                        color:#0369a1;
                        font-size:0.75rem;
                        font-weight:800;
                        letter-spacing:0.05em;
                        text-transform:uppercase;
                        white-space:nowrap;
                    }
                    .sp-import-template-wrap {
                        border:1px solid #dbe3ef;
                        border-radius:18px;
                        overflow:hidden;
                        background:#fff;
                        box-shadow:inset 0 1px 0 rgba(255,255,255,0.7);
                    }
                    .sp-import-template {
                        width:100%;
                        border-collapse:collapse;
                        font-size:0.85rem;
                    }
                    .sp-import-template th,
                    .sp-import-template td {
                        border:1px solid #dbe3ef;
                        padding:12px 14px;
                        vertical-align:top;
                    }
                    .sp-import-template th {
                        background:#eff6ff;
                        color:#1e3a8a;
                        font-weight:700;
                        white-space:nowrap;
                    }
                    .sp-import-template td {
                        color:#334155;
                        background:#fff;
                    }
                    .sp-import-mini {
                        display:grid;
                        gap:12px;
                        grid-template-columns:repeat(3, minmax(0, 1fr));
                    }
                    .sp-import-mini-item {
                        border:1px solid #e2e8f0;
                        border-radius:16px;
                        padding:16px;
                        background:#fff;
                    }
                    .sp-import-mini-item h5 {
                        margin:0 0 8px;
                        color:#0f172a;
                        font-size:0.92rem;
                    }
                    .sp-import-mini-item p {
                        margin:0;
                        color:#475569;
                        font-size:0.84rem;
                        line-height:1.6;
                    }
                    @media (max-width: 980px) {
                        .sp-import-shell { grid-template-columns: 1fr; }
                        .sp-import-mini { grid-template-columns:1fr; }
                    }
                    @media (max-width: 640px) {
                        .sp-import-card { padding:18px; border-radius:20px; }
                        .sp-import-dropzone { min-height:220px; padding:24px 18px; border-radius:20px; }
                        .sp-import-dropzone-icon { width:64px; height:64px; border-radius:20px; }
                        .sp-import-dropzone-icon svg { width:28px; height:28px; }
                        .sp-import-preview-head { flex-direction:column; }
                        .sp-import-template-wrap { overflow-x:auto; }
                    }
                </style>
                <div class="sp-import-shell">
                    <div class="sp-import-card sp-import-card--upload">
                        <div class="sp-import-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Area Upload
                        </div>
                        <div class="sp-import-hero">
                            <div class="sp-import-hero-badge">CSV Master Data</div>
                            <h4>Import banyak item lebih cepat</h4>
                            <p>Upload satu file CSV untuk menambahkan katalog master sarpras tanpa input manual satu per satu.</p>
                        </div>
                        <input type="file" id="masterImportFile" accept=".csv,text/csv" style="display:none">
                        <div class="sp-import-dropzone" id="masterImportDropzone">
                            <div class="sp-import-dropzone-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            </div>
                            <h4>Tarik file CSV atau klik untuk memilih</h4>
                            <p>Format yang didukung: <strong>CSV UTF-8</strong>. Pastikan kolom mengikuti struktur template agar proses import berjalan mulus.</p>
                            <div class="sp-import-dropzone-meta">
                                <span>1 file</span>
                                <span>UTF-8</span>
                                <span>Tanpa ZIP</span>
                            </div>
                        </div>
                        <div class="sp-import-file-chip" id="masterImportFileInfo"></div>
                        <div class="sp-import-actions">
                            <a href="${templateUrl}" class="btn btn-outline btn-sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Download Template CSV
                            </a>
                        </div>
                        <div class="sp-import-quicklist">
                            <div class="sp-import-quickitem">
                                <div class="sp-import-quickitem-badge">1</div>
                                <div>
                                    <strong>Unduh template resmi</strong>
                                    <span>Gunakan template agar urutan dan nama kolom sesuai dengan yang dibaca sistem.</span>
                                </div>
                            </div>
                            <div class="sp-import-quickitem">
                                <div class="sp-import-quickitem-badge">2</div>
                                <div>
                                    <strong>Simpan sebagai CSV UTF-8</strong>
                                    <span>Encoding yang tepat membantu menghindari karakter aneh saat nama barang diimport.</span>
                                </div>
                            </div>
                        </div>
                        <div class="sp-import-note">
                            <strong>Field template mengikuti form manual Data Sarpras:</strong><br>
                            <code>nama</code>, <code>kategori_kode</code>, <code>kode</code>, dan <code>masa_manfaat_tahun</code>.<br>
                            Kolom <code>kode</code> boleh dikosongkan, nanti sistem akan membuat kode otomatis berdasarkan kategori.
                        </div>
                    </div>
                    <div class="sp-import-card sp-import-card--guide">
                        <div class="sp-import-section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v18H3z"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>
                            Panduan Template
                        </div>
                        <div class="sp-import-preview-head">
                            <div>
                                <h4>Contoh struktur file</h4>
                                <p>Template di bawah menunjukkan kolom minimum yang perlu ada agar import master sarpras berhasil.</p>
                            </div>
                            <div class="sp-import-pill">4 Kolom Inti</div>
                        </div>
                        <div class="sp-import-template-wrap">
                            <table class="sp-import-template">
                                <thead>
                                    <tr>
                                        <th>nama</th>
                                        <th>kategori_kode</th>
                                        <th>kode</th>
                                        <th>masa_manfaat_tahun</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Kursi Guru</td>
                                        <td>MEB</td>
                                        <td>MEB.1</td>
                                        <td>5</td>
                                    </tr>
                                    <tr>
                                        <td>LCD Proyektor</td>
                                        <td>ELK</td>
                                        <td></td>
                                        <td>4</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="sp-import-mini" style="margin-top:16px;">
                            <div class="sp-import-mini-item">
                                <h5>kategori_kode</h5>
                                <p>Isi dengan kode kategori yang sudah ada di referensi kategori Sarpras, misalnya <strong>MEB</strong> atau <strong>ELK</strong>.</p>
                            </div>
                            <div class="sp-import-mini-item">
                                <h5>kode</h5>
                                <p>Boleh diisi manual. Jika dikosongkan, sistem membuat kode otomatis dengan format <code>&lt;kategori_kode&gt;.&lt;nomor&gt;</code>.</p>
                            </div>
                            <div class="sp-import-mini-item">
                                <h5>masa_manfaat_tahun</h5>
                                <p>Isi angka tahun. Jika kosong, sistem memakai nilai default <strong>5</strong>.</p>
                            </div>
                        </div>
                    </div>
                </div>
            `,
            onOpen: () => {
                const $dropzone = $('#masterImportDropzone');
                const $fileInput = $('#masterImportFile');
                const $fileInfo = $('#masterImportFileInfo');

                const setFile = (file) => {
                    this._masterImportFile = file || null;
                    if (!file) {
                        $dropzone.removeClass('file-ready');
                        $fileInfo.hide().text('');
                        return;
                    }
                    $dropzone.addClass('file-ready');
                    $fileInfo
                        .html(`
                            <div class="sp-import-file-chip-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="8" y1="13" x2="16" y2="13"></line>
                                    <line x1="8" y1="17" x2="13" y2="17"></line>
                                </svg>
                            </div>
                            <div>
                                <strong>${this.escapeHtml(file.name)}</strong>
                                <span>Ukuran file: ${(file.size / 1024).toFixed(1)} KB</span>
                                <span>Siap diimport ke katalog master sarpras.</span>
                            </div>
                        `)
                        .css('display', 'flex');
                };

                $dropzone.on('click', () => $fileInput.trigger('click'));
                $fileInput.on('change', (e) => setFile(e.target.files[0]));

                $dropzone.on('dragenter dragover', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    $dropzone.addClass('dragover');
                });

                $dropzone.on('dragleave dragend drop', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    $dropzone.removeClass('dragover');
                });

                $dropzone.on('drop', (e) => {
                    const file = e.originalEvent.dataTransfer?.files?.[0];
                    if (file) setFile(file);
                });
            },
            onConfirm: () => {
                const file = this._masterImportFile;
                if (!file) {
                    EModal.toast({ type: 'warning', title: 'File Belum Dipilih', message: 'Silakan pilih atau drag file CSV terlebih dahulu.' });
                    return false;
                }

                const fd = new FormData();
                fd.append('file', file);

                const loader = EModal.loading('Mengimport master sarpras...');
                this.api('master_sarpras.php?action=import-csv', { method: 'POST', data: fd }).done((res) => {
                    EModal.close(loader);
                    EModal.closeAll();
                    const errors = res.data?.errors || [];
                    const message = errors.length
                        ? `${res.message}. Catatan: ${errors.slice(0, 2).join(' | ')}`
                        : res.message;
                    EModal.toast({ title: 'Import Berhasil', message, duration: 6000 });
                    this.renderMasterSarpras($('#mainContent'));
                }).fail((xhr) => {
                    EModal.close(loader);
                    EModal.toast({ type: 'error', title: 'Import Gagal', message: xhr.responseJSON?.message || 'Terjadi kesalahan saat import.' });
                });
                return false;
            },
            onCancel: () => {
                this._masterImportFile = null;
            }
        });
    },

    formMasterSarpras(id) {
        const isEdit = id !== null && id !== undefined;
        // Fetch kategori from the proper kategori table (foreign key constraint) and satuan from referensi
        $.when(
            this.api('manage.php?entity=kategori&action=list'),
            this.api('referensi.php?action=list&kategori=satuan_sarpras')
        ).done((resKat, resSat) => {
            // Because we use $.when, the response is usually an array [data, textStatus, jqXHR]
            // We only need the response data from index 0
            const kData = resKat[0].data || [];
            const sData = resSat[0].data || [];
            
            const katOptions = kData.map(k => `<option value="${k.id}">${k.nama}</option>`).join('');
            const satOptions = sData.map(s => `<option value="${s.nama}">${s.nama}</option>`).join('');

            EModal.form({
                title: isEdit ? 'Edit Sarpras' : 'Tambah Sarpras',
                form: `
                    <div class="sp-form-row">
                        <div class="form-group"><label>Nama Barang (cth: Kursi Guru)</label><input class="form-input" id="f_msNama" required></div>
                        <div class="form-group"><label>Kategori</label><select class="form-select" id="f_msKat"><option value="">-- Pilih Kategori --</option>${katOptions}</select></div>
                    </div>
                    <div class="sp-form-row">
                        <div class="form-group"><label>Kode Barang (Otomatis)</label><input class="form-input" id="f_msKode" placeholder="Pilih kategori dlu"></div>
                        <div class="form-group"><label>Masa Manfaat (Tahun)</label><input type="number" class="form-input" id="f_msManfaat" value="5" required></div>
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted); background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0; margin-top:10px;">
                        <strong>Catatan:</strong> Detail seperti Merk, Spesifikasi, dan Tanggal Perolehan diisi saat penginputan inventaris (Form AHP-BHP, Kendaraan, atau Buku).
                    </div>
                `,
                onOpen: () => {
                    // Auto-generation logic for Kode Barang
                    $('#f_msKat').on('change', function() {
                        const katId = $(this).val();
                        if (!katId) { $('#f_msKode').val(''); return; }
                        
                        const selectedKat = kData.find(k => k.id == katId);
                        if (selectedKat && selectedKat.kode) {
                            // Fetch existing items for this category to determine next number
                            Sarpras.api(`master_sarpras.php?action=list&kategori_id=${katId}`).done(res => {
                                const nextNum = res.data.length + 1;
                                $('#f_msKode').val(`${selectedKat.kode}.${nextNum}`);
                            });
                        }
                    });

                    if (isEdit) {
                        this.api('master_sarpras.php?action=list').done(res => {
                            const d = res.data.find(x => x.id == id);
                            if (d) {
                                $('#f_msNama').val(d.nama); $('#f_msKode').val(d.kode); 
                                $('#f_msKat').val(d.kategori_id); $('#f_msManfaat').val(d.masa_manfaat_default);
                            }
                        });
                    }
                },
                onConfirm: () => {
                    const data = {
                        id, nama: $('#f_msNama').val(), kode: $('#f_msKode').val(), 
                        kategori_id: $('#f_msKat').val(), masa_manfaat_default: $('#f_msManfaat').val()
                    };
                    if (!data.nama || !data.kategori_id) { EModal.toast({type:'error', title:'Error', message:'Nama dan Kategori wajib diisi'}); return false; }

                    this.api(`master_sarpras.php?action=${isEdit?'update':'create'}`, { method: 'POST', data }).done(() => {
                        EModal.closeAll(); this.renderMasterSarpras($('#mainContent'));
                    }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message:xhr.responseJSON?.message}));
                    return false;
                }
            });
        });
    },

    delMasterSarpras(id, name) {
        EModal.confirm({
            title: 'Hapus Jenis Aset', message: `Yakin hapus <strong>${name}</strong> dari katalog?`, type: 'danger',
            onConfirm: () => this.api('master_sarpras.php?action=delete', { method: 'POST', data: { id } }).done(() => this.renderMasterSarpras($('#mainContent')))
        });
    },

    /**
     * PAGE: SARPRAS DETAIL
     */
    renderSarprasDetail($container, id) {
        if (!id) return this.navigate('tanah');
        $container.html('<div class="skeleton" style="height:400px"></div>');
        
        this.api(`sarpras.php?action=get&id=${id}`).done(res => {
            const s = res.data;
            $('#breadcrumb').html(`<a href="#/tanah">Tanah</a> <span class="sep">/</span> <a href="#/sarpras?ruang_id=${s.ruang_id}">${s.ruang_nama}</a> <span class="sep">/</span> <span class="current">${s.nama}</span>`);
            
            $container.html(`
                <div class="sp-dashboard-grid">
                    <div class="sp-card" style="grid-column: 1 / -1">
                        <div class="sp-card-header">
                            <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg> ${s.nama}</h3>
                            ${(canSarpras || isPJ) ? `
                            <div class="sp-toolbar">
                                <button class="btn btn-primary btn-sm" onclick="Sarpras.formSarpras(${s.id}, ${s.ruang_id})">Edit Data</button>
                                <button class="btn btn-secondary btn-sm" onclick="Sarpras.printDetail(${s.id})">Cetak Label</button>
                            </div>` : ''}
                        </div>
                        <div class="sp-card-body">
                            <div class="sp-form-row">
                                <div class="sp-info-group">
                                    <div class="sp-foto-grid" id="sFotos">${this.renderSarprasFotosHtml(s.fotos, s.id)}</div>
                                </div>
                                <div class="sp-info-details">
                                    <div class="sp-row"><span class="sp-label">Kode Inventaris</span><span class="sp-value">${s.kode_inventaris}</span></div>
                                    <div class="sp-row"><span class="sp-label">Kategori</span><span class="sp-value">${s.kategori_nama} (${s.kategori_kode})</span></div>
                                    <div class="sp-row"><span class="sp-label">Merk / Spek</span><span class="sp-value">${s.merk || '-'} / ${s.spesifikasi || '-'}</span></div>
                                    <div class="sp-row"><span class="sp-label">Lokasi</span><span class="sp-value">${s.ruang_nama} &bull; ${s.bangunan_nama} &bull; ${s.tanah_nama}</span></div>
                                    <div class="sp-row"><span class="sp-label">Perolehan</span><span class="sp-value">${s.tanggal_perolehan ? this.formatDate(s.tanggal_perolehan) : '-'} &bull; Rp ${this.formatNumber(s.harga_perolehan)} (${s.asal_perolehan})</span></div>
                                    <div class="sp-row"><span class="sp-label">Masa Manfaat</span><span class="sp-value">${s.masa_manfaat_tahun} Tahun</span></div>
                                    
                                    <div class="kondisi-grid" style="margin-top:20px">
                                        <div class="kondisi-item baik"><div class="num">${s.kondisi_baik}</div><div class="lbl">BAIK</div></div>
                                        <div class="kondisi-item rr"><div class="num">${s.kondisi_rusak_ringan}</div><div class="lbl">R. RINGAN</div></div>
                                        <div class="kondisi-item rb"><div class="num">${s.kondisi_rusak_berat}</div><div class="lbl">R. BERAT</div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sp-card">
                        <div class="sp-card-header"><h3>Riwayat Perbaikan</h3><button class="btn btn-sm" onclick="Sarpras.formRepair(${s.id})">+ Catat</button></div>
                        <div class="sp-card-body"><div class="sp-table-wrapper">${this.renderRepairTable(s.perbaikan)}</div></div>
                    </div>
                    
                    <div class="sp-card">
                        <div class="sp-card-header"><h3>Riwayat Periodik</h3></div>
                        <div class="sp-card-body"><div class="sp-table-wrapper">${this.renderPeriodikTable(s.periodik)}</div></div>
                    </div>
                </div>
            `);
        });
    },

    renderSarprasFotosHtml(fotos, sarprasId) {
        let html = fotos.map(f => `
            <div class="sp-foto-item">
                <img src="${this.state.baseUrl}${f.foto_path}">
                <button class="sp-foto-delete" onclick="Sarpras.delSarprasFoto(${f.id}, ${sarprasId})">&times;</button>
            </div>
        `).join('');
        if (fotos.length < 5) html += `<div class="sp-foto-upload" onclick="Sarpras.triggerSarprasUpload()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg><span>Upload</span><input type="file" id="sFileInp" style="display:none" accept="image/*" onchange="Sarpras.doSarprasUpload(${sarprasId}, this)"></div>`;
        return html;
    },

    triggerSarprasUpload() { $('#sFileInp').click(); },
    
    doSarprasUpload(id, input) {
        if (!input.files[0]) return;
        const fd = new FormData(); fd.append('sarpras_id', id); fd.append('foto', input.files[0]);
        const loader = EModal.loading('Uploading...');
        this.api('sarpras.php?action=upload-foto', { method: 'POST', data: fd }).done(() => {
            EModal.close(loader); this.api(`sarpras.php?action=get&id=${id}`).done(res => $('#sFotos').html(this.renderSarprasFotosHtml(res.data.fotos, id)));
        }).fail(() => EModal.close(loader));
    },

    delSarprasFoto(fid, sid) {
        EModal.confirm({ title: 'Hapus Foto', type: 'danger', onConfirm: () => this.api('sarpras.php?action=delete-foto', { method: 'POST', data: { foto_id: fid } }).done(() => this.api(`sarpras.php?action=get&id=${sid}`).done(res => $('#sFotos').html(this.renderSarprasFotosHtml(res.data.fotos, sid)))) });
    },

    renderRepairTable(repairs) {
        if (!repairs.length) return '<div class="sp-empty">Belum ada perbaikan.</div>';
        return `<table class="sp-table"><thead><tr><th>Tgl</th><th>Deskripsi</th><th>Status</th></tr></thead><tbody>${repairs.map(r => `<tr><td>${this.formatDate(r.tanggal)}</td><td>${r.deskripsi}</td><td><span class="badge badge-${r.status === 'Selesai' ? 'success' : 'warning'}">${r.status}</span></td></tr>`).join('')}</tbody></table>`;
    },

    renderPeriodikTable(periodics) {
        if (!periodics.length) return '<div class="sp-empty">Belum ada data periodik.</div>';
        return `<table class="sp-table"><thead><tr><th>Periode</th><th>Tahun</th><th>B / RR / RB</th><th>Updater</th></tr></thead><tbody>${periodics.map(p => `<tr><td>${p.periode}</td><td>${p.tahun}</td><td>${p.kondisi_baik} / ${p.kondisi_rusak_ringan} / ${p.kondisi_rusak_berat}</td><td>${p.updated_by_name}</td></tr>`).join('')}</tbody></table>`;
    },

    /**
     * PAGE: PERBAIKAN
     */
    renderPerbaikan($container) {
        $container.html('<div class="skeleton" style="height:300px"></div>');
        this.api('manage.php?entity=perbaikan&action=list').done(res => {
            $container.html(`
                <div class="sp-card">
                    <div class="sp-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Data Perbaikan Aset</h3>
                        <div class="sp-actions">
                            <button class="btn btn-secondary btn-sm" onclick="Sarpras.openRepairLogModal()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px; height:14px;"><path d="M6 9l6 6 6-6"/></svg>
                                Cetak Log Perbaikan
                            </button>
                        </div>
                    </div>
                    <div class="sp-card-body"><div class="sp-table-wrapper" id="repairListTable"></div></div>
                </div>
            `);
            if (!res.data.length) { $('#repairListTable').html('<div class="sp-empty">Tidak ada riwayat perbaikan.</div>'); return; }
            const rows = res.data.map(r => `
                <tr>
                    <td>
                        <strong>${r.sarpras_nama}</strong><br>
                        <small style="color:var(--text-muted)">${r.kode_inventaris}</small>
                    </td>
                    <td>${this.formatDate(r.tanggal)}</td>
                    <td>${r.deskripsi}</td>
                    <td><span class="badge badge-info">${r.ruang_nama || '<span style="color:var(--warning)">Gudang</span>'}</span></td>
                    <td><span class="badge badge-${r.status==='Selesai'?'success':(r.status==='Proses'?'warning':(r.status==='Penghapusan'?'danger':'info'))}">${r.status}</span></td>
                    <td>
                        <div class="sp-actions">
                            <button class="btn btn-primary btn-sm" onclick="Sarpras.formRepair(${r.sarpras_id}, ${r.id})">Update Progress</button>
                        </div>
                    </td>
                </tr>
            `).join('');
            $('#repairListTable').html(`<table class="sp-table"><thead><tr><th>Barang</th><th>Tanggal</th><th>Deskripsi</th><th>Lokasi</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
        });
    },

    openRepairLogModal() {
        EModal.form({
            title: 'Cetak Log Perbaikan',
            form: `
                <div class="form-group"><label>Dari Tanggal</label><input type="date" class="form-input" id="rep_start" value="${new Date().toISOString().split('T')[0]}"></div>
                <div class="form-group"><label>Sampai Tanggal</label><input type="date" class="form-input" id="rep_end" value="${new Date().toISOString().split('T')[0]}"></div>
            `,
            onConfirm: () => {
                const start = $('#rep_start').val();
                const end = $('#rep_end').val();
                window.open(`api/manage.php?entity=laporan&action=print&type=perbaikan-log&start=${start}&end=${end}&token=${this.getToken()}`, '_blank');
                return true;
            }
        });
    },

    formRepair(sarprasId, id = null) {
        const isEdit = id !== null;
        EModal.form({
            title: isEdit ? 'Update Perbaikan / Penghapusan' : 'Catat Perbaikan Baru',
            size: 'md',
            form: `
                <div id="repairFormContainer">
                    <input type="hidden" id="f_rpId" value="${id || ''}">
                    <input type="hidden" id="f_rpSid" value="${sarprasId}">
                    
                    <div class="sp-form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" class="form-input" id="f_rpTgl" value="${new Date().toISOString().split('T')[0]}">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-select" id="f_rpStat">
                                <option value="Diajukan">Diajukan</option>
                                <option value="Proses">Dalam Proses</option>
                                <option value="Selesai">Selesai</option>
                                <option value="Dibatalkan">Dibatalkan</option>
                                <option value="Penghapusan">Penghapusan Barang</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:15px;">
                        <label>Deskripsi / Keluhan</label>
                        <textarea class="form-input" id="f_rpDesc" rows="3"></textarea>
                    </div>

                    <div id="row_selesai" style="display:none">
                        <div class="sp-form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                            <div class="form-group">
                                <label>Biaya Perbaikan</label>
                                <input type="text" class="form-input sp-rupiah-input" id="f_rpHarga" value="0">
                                <input type="hidden" id="f_rpBiayaReal" value="0">
                            </div>
                            <div class="form-group">
                                <label>Bukti Bayar (PDF/IMG)</label>
                                <input type="file" class="form-input" id="f_rpBukti" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                        </div>
                    </div>

                    <div id="row_batal" style="display:none">
                        <div class="form-group" style="margin-bottom:15px;">
                            <label>Alasan Pembatalan</label>
                            <input class="form-input" id="f_rpBatal">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Catatan Tambahan</label>
                        <input class="form-input" id="f_rpCat">
                    </div>

                    <div id="warn_hapus" style="display:none; padding:10px; background:#fff1f2; border:1px solid #fda4af; border-radius:6px; color:#991b1b; font-size:0.85rem; margin-top:15px;">
                        <strong>Peringatan Penghapusan:</strong> Barang ini akan dipindahkan ke kategori "Dihapus" dan tidak akan muncul di inventaris aktif setelah disimpan.
                    </div>

                    <!-- RIWAYAT LOG -->
                    <div id="repairLogSection" style="margin-top:25px; display:none;">
                        <div style="font-weight:700; font-size:0.8rem; color:var(--text-muted); text-transform:uppercase; margin-bottom:10px; border-bottom:1px solid #e2e8f0; padding-bottom:5px;">Riwayat Proses</div>
                        <div id="repairLogList" style="max-height:200px; overflow-y:auto; padding-right:5px;"></div>
                    </div>
                </div>
            `,
            onOpen: () => {
                const toggleFields = () => {
                    const status = $('#f_rpStat').val();
                    $('#row_selesai').toggle(status === 'Selesai');
                    $('#row_batal').toggle(status === 'Dibatalkan');
                    $('#warn_hapus').toggle(status === 'Penghapusan');
                };
                
                $('#f_rpStat').on('change', toggleFields);
                $('#f_rpHarga').on('input', function() {
                    const val = $(this).val().replace(/\./g, '');
                    $('#f_rpBiayaReal').val(val);
                });
                
                if (isEdit) {
                    this.api(`manage.php?entity=perbaikan&action=list`).done(res => {
                        const d = res.data.find(x => x.id == id);
                        if (d) { 
                            $('#f_rpTgl').val(d.tanggal); 
                            $('#f_rpStat').val(d.status); 
                            $('#f_rpDesc').val(d.deskripsi); 
                            $('#f_rpBatal').val(d.alasan_batal);
                            $('#f_rpHarga').val(Sarpras.formatNumber(d.biaya || 0));
                            $('#f_rpBiayaReal').val(d.biaya || 0);
                            $('#f_rpCat').val(d.catatan); 
                            toggleFields();
                        }
                    });

                    // Fetch Log
                    this.api(`manage.php?entity=perbaikan&action=log&id=${id}`).done(res => {
                        const logs = res.data || [];
                        if (logs.length > 0) {
                            const html = logs.map(l => `
                                <div style="margin-bottom:12px; padding:8px; background:#f8fafc; border-radius:8px; border-left:3px solid var(--primary);">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                        <span class="badge badge-info" style="font-size:0.7rem">${l.status}</span>
                                        <small style="color:var(--text-muted); font-size:0.7rem;">${this.formatDate(l.tanggal)}</small>
                                    </div>
                                    <div style="font-size:0.85rem; color:#1e293b;">${l.catatan || '-'}</div>
                                    <div style="font-size:0.7rem; color:var(--text-muted); margin-top:4px;">Oleh: ${l.updated_by_name || 'Sistem'}</div>
                                </div>
                            `).join('');
                            $('#repairLogList').html(html);
                            $('#repairLogSection').show();
                        }
                    });
                } else {
                    toggleFields();
                }
            },
            onConfirm: () => {
                const formData = new FormData();
                formData.append('id', $('#f_rpId').val());
                formData.append('sarpras_id', $('#f_rpSid').val());
                formData.append('tanggal', $('#f_rpTgl').val());
                formData.append('status', $('#f_rpStat').val());
                formData.append('deskripsi', $('#f_rpDesc').val());
                formData.append('biaya', $('#f_rpBiayaReal').val());
                formData.append('alasan_batal', $('#f_rpBatal').val());
                formData.append('catatan', $('#f_rpCat').val());
                
                const fileInput = $('#f_rpBukti')[0];
                if (fileInput && fileInput.files[0]) {
                    formData.append('bukti_bayar', fileInput.files[0]);
                }
                
                $.ajax({
                    url: `api/manage.php?entity=perbaikan&action=${isEdit?'update':'create'}&token=${this.getToken()}`,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: (res) => {
                        EModal.closeAll(); EModal.toast({type:'success', title:'Berhasil disimpan'}); this.loadRouteFromHash();
                    },
                    error: (xhr) => {
                        const msg = xhr.responseJSON?.message || 'Terjadi kesalahan sistem';
                        EModal.toast({type:'error', title:'Gagal Simpan', message: msg});
                    }
                });
                return false;
            }
        });
    },

    /**
     * PAGE: PERIODIK
     */
    renderPeriodik($container) {
        const curYear = new Date().getFullYear();
        const curMonth = new Date().getMonth();
        const curQ = curMonth < 3 ? 'Q1' : (curMonth < 6 ? 'Q2' : (curMonth < 9 ? 'Q3' : 'Q4'));
        
        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header">
                    <h3>Update Data Periodik</h3>
                    <div class="sp-filter-bar">
                        <select class="form-select" id="p_qSel"><option value="Q1">Q1 (Jan-Mar)</option><option value="Q2">Q2 (Apr-Jun)</option><option value="Q3">Q3 (Jul-Sep)</option><option value="Q4">Q4 (Okt-Des)</option></select>
                        <input type="number" class="form-input" id="p_ySel" value="${curYear}">
                        <button class="btn btn-secondary btn-sm" onclick="Sarpras.loadPeriodik()">Filter</button>
                        <button class="btn btn-sm" onclick="Sarpras.copyPeriodik()">Salin dari Periode Lalu</button>
                    </div>
                </div>
                <div class="sp-card-body">
                    <div class="sp-table-wrapper" id="periodikTable"><div class="sp-empty">Pilih periode dan klik filter.</div></div>
                </div>
                <div class="sp-card-footer">
                    <button class="btn btn-primary" id="savePeriodikBtn" style="display:none" onclick="Sarpras.savePeriodik()">Simpan Perubahan</button>
                </div>
            </div>
        `);
        $('#p_qSel').val(curQ);
        this.loadPeriodik();
    },

    loadPeriodik() {
        const q = $('#p_qSel').val(), y = $('#p_ySel').val();
        $('#periodikTable').html('<div class="skeleton" style="height:300px"></div>');
        this.api(`manage.php?entity=periodik&action=list&periode=${q}&tahun=${y}`).done(res => {
            if (!res.data.length) { $('#periodikTable').html('<div class="sp-empty">Tidak ada data sarpras.</div>'); $('#savePeriodikBtn').hide(); return; }
            $('#savePeriodikBtn').show();
            const rows = res.data.map(s => `
                <tr data-sid="${s.id}">
                    <td><strong>${s.nama}</strong><br><small>${s.ruang_nama}</small></td>
                    <td>${s.jumlah}</td>
                    <td><input type="number" class="form-input p-baik" value="${s.p_baik !== null ? s.p_baik : s.kondisi_baik}" max="${s.jumlah}"></td>
                    <td><input type="number" class="form-input p-rr" value="${s.p_rr !== null ? s.p_rr : s.kondisi_rusak_ringan}"></td>
                    <td><input type="number" class="form-input p-rb" value="${s.p_rb !== null ? s.p_rb : s.kondisi_rusak_berat}"></td>
                    <td><input type="text" class="form-input p-cat" value="${s.p_catatan || ''}"></td>
                </tr>
            `).join('');
            $('#periodikTable').html(`
                <table class="sp-table sp-periodik-table">
                    <thead><tr><th>Sarpras / Ruang</th><th>Total Unit</th><th>Baik</th><th>R. Ringan</th><th>R. Berat</th><th>Catatan</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        });
    },

    savePeriodik() {
        const items = [];
        $('#periodikTable tbody tr').each((i, el) => {
            const $row = $(el);
            items.push({
                sarpras_id: $row.data('sid'),
                kondisi_baik: $row.find('.p-baik').val(),
                kondisi_rusak_ringan: $row.find('.p-rr').val(),
                kondisi_rusak_berat: $row.find('.p-rb').val(),
                catatan: $row.find('.p-cat').val()
            });
        });
        const q = $('#p_qSel').val(), y = $('#p_ySel').val();
        this.api('manage.php?entity=periodik&action=save', { method: 'POST', data: { periode: q, tahun: y, items } }).done(res => {
            EModal.toast({title:'Berhasil', message: res.message});
            this.loadPeriodik();
        });
    },

    copyPeriodik() {
        const q = $('#p_qSel').val(), y = $('#p_ySel').val();
        EModal.confirm({
            title: 'Salin Data', message: `Salin data kondisi dari periode sebelumnya ke ${q} ${y}?`,
            onConfirm: () => {
                this.api('manage.php?entity=periodik&action=copy-previous', { method: 'POST', data: { target_periode: q, target_tahun: y } }).done(res => {
                    EModal.toast({title:'Berhasil', message: res.message}); this.loadPeriodik();
                }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message}));
            }
        });
    },

    /**
     * PAGE: BARCODE MANAGER
     */
    renderBarcodeManager($container) {
        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header">
                    <h3>Cetak Label Barcode</h3>
                    <div class="sp-toolbar"><button class="btn btn-primary" onclick="window.print()">Cetak Halaman Ini</button></div>
                </div>
                <div class="sp-card-body">
                    <div class="sp-filter-bar no-print">
                        <select class="form-select" id="bc_rSel" onchange="Sarpras.loadBarcodes()"><option value="">Semua Ruang</option></select>
                    </div>
                    <div class="sp-barcode-grid" id="barcodeGrid"><div class="skeleton" style="height:300px"></div></div>
                </div>
            </div>
        `);
        
        this.api('ruang.php?action=all').done(res => {
            res.data.forEach(r => $('#bc_rSel').append(`<option value="${r.id}">${r.tanah_nama} - ${r.bangunan_nama} - ${r.nama}</option>`));
        });
        this.loadBarcodes();
    },

    loadBarcodes() {
        const rid = $('#bc_rSel').val();
        this.api(`sarpras.php?action=barcode-data&ruang_id=${rid}`).done(res => {
            if (!res.data.length) { $('#barcodeGrid').html('<div class="sp-empty">Pilih ruang yang memiliki sarpras.</div>'); return; }
            const html = res.data.map(s => `
                <div class="sp-barcode-item">
                    <div class="sp-bc-wrap" data-code="${s.kode_inventaris}"></div>
                    <div class="sp-barcode-label">${s.kode_inventaris}</div>
                    <div class="sp-barcode-sub">${s.nama}</div>
                    <div class="sp-barcode-sub" style="font-size:0.5rem; opacity:0.6">${s.ruang_nama}</div>
                </div>
            `).join('');
            $('#barcodeGrid').html(html);
            
            // Generate QR Codes using library (assumes qrcode.js is loaded)
            $('.sp-bc-wrap').each((i, el) => {
                const code = $(el).data('code');
                // Use absolute URL so scanner detects it as a web link
                const url = `${window.location.origin}${this.state.baseUrl}modules/e-sarpras/scan.php?kode=${code}`;
                new QRCode(el, { text: url, width: 80, height: 80, correctLevel: QRCode.CorrectLevel.M });
            });
        });
    },

    printDetail(id) {
        const url = `${window.location.origin}${this.state.baseUrl}modules/e-sarpras/scan.php?kode=PRINT&sarpras_id=${id}`;
        const win = window.open(url, '_blank');
        // Logic inside scan.php handles specific printing if needed, or we just rely on browser print
    },

    /**
     * PAGE: PENYUSUTAN
     */
    renderPenyusutan($container) {
        const d = new Date();
        const currentBulan = d.getMonth() + 1;
        const currentTahun = d.getFullYear();

        let monthOpts = '';
        const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        months.forEach((m, i) => {
            monthOpts += `<option value="${i+1}" ${currentBulan === i+1 ? 'selected' : ''}>${m}</option>`;
        });

        let yearOpts = '';
        for (let y = currentTahun - 10; y <= currentTahun + 5; y++) {
            yearOpts += `<option value="${y}" ${currentTahun === y ? 'selected' : ''}>${y}</option>`;
        }

        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header">
                    <h3>Data Penyusutan Bulanan Berjalan</h3>
                    <div class="sp-toolbar">
                        <select id="filterPenyusutanBulan" class="sp-input" style="width:130px">${monthOpts}</select>
                        <select id="filterPenyusutanTahun" class="sp-input" style="width:100px">${yearOpts}</select>
                        <button class="btn btn-sm btn-primary" onclick="Sarpras.loadPenyusutanData()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="margin-right:4px;vertical-align:middle"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                            Hitung / Tampilkan
                        </button>
                    </div>
                </div>
                <div class="sp-card-body">
                    <div class="sp-toolbar" style="margin-bottom: 12px;">
                        <button class="btn btn-sm btn-info" style="background:#0ea5e9; color:white; border:none; margin-right:auto;" onclick="Sarpras.showPenyusutanFormula()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="margin-right:4px;vertical-align:middle"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            Lihat Rumus
                        </button>
                        <button class="btn btn-sm" style="background:#e11d48; color:white; border:none;" onclick="Sarpras.exportPenyusutan('pdf')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="margin-right:4px;vertical-align:middle"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            Cetak PDF
                        </button>
                        <button class="btn btn-sm" style="background:#16a34a; color:white; border:none; margin-left:8px;" onclick="Sarpras.exportPenyusutan('excel')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="margin-right:4px;vertical-align:middle"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            Export Excel
                        </button>
                    </div>
                    <div class="sp-table-wrapper" id="penyusutanTable">
                        <div class="skeleton" style="height:300px"></div>
                    </div>
                </div>
            </div>
        `);
        
        this.loadPenyusutanData();
    },

    loadPenyusutanData() {
        const bln = $('#filterPenyusutanBulan').val() || new Date().getMonth() + 1;
        const thn = $('#filterPenyusutanTahun').val() || new Date().getFullYear();
        $('#penyusutanTable').html('<div class="skeleton" style="height:300px"></div>');
        
        this.api(`manage.php?entity=penyusutan&action=list&bulan=${bln}&tahun=${thn}`).done(res => {
            const sum = res.data.summary;
            let rows = res.data.items.map((p, index) => `
                <tr>
                    <td><div style="font-weight:600; color:#1e293b">${p.sarpras_nama}</div><div style="font-size:11px; color:#64748b">${p.kode_inventaris}</div></td>
                    <td align="center">${p.tahun_perolehan}</td>
                    <td align="center">${p.masa_manfaat_tahun}</td>
                    <td align="right">Rp ${this.formatNumber(p.nilai_perolehan)}</td>
                    <td align="right">Rp ${this.formatNumber(p.beban_penyusutan)}</td>
                    <td align="right">Rp ${this.formatNumber(p.akumulasi_penyusutan)}</td>
                    <td align="right"><span class="sp-nilai sm" style="display:inline-block">Rp ${this.formatNumber(p.nilai_buku)}</span></td>
                </tr>
            `).join('');
            
            // Append summary row
            rows += `
                <tr style="background:#f1f5f9; font-weight:700;">
                    <td colspan="3" align="center" style="color:#0f172a">TOTAL KESELURUHAN</td>
                    <td align="right" style="color:#0f172a">Rp ${this.formatNumber(sum.total_perolehan)}</td>
                    <td align="right" style="color:#0f172a">Rp ${this.formatNumber(sum.total_beban)}</td>
                    <td align="right" style="color:#0f172a">Rp ${this.formatNumber(sum.total_akumulasi)}</td>
                    <td align="right" style="color:#0f172a">Rp ${this.formatNumber(sum.total_nilai_buku)}</td>
                </tr>
            `;

            $('#penyusutanTable').html(`<table class="sp-table">
                <thead>
                    <tr>
                        <th>Identitas Barang</th>
                        <th style="min-width:60px">Thn Beli</th>
                        <th style="min-width:60px">Umur</th>
                        <th style="text-align:right">Perolehan</th>
                        <th style="text-align:right">Beban Bulanan</th>
                        <th style="text-align:right">Akumulasi</th>
                        <th style="text-align:right">Nilai Buku</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>`);
        }).fail(() => {
            $('#penyusutanTable').html('<div class="sp-empty">Gagal memuat kalkulasi</div>');
        });
    },

    exportPenyusutan(type) {
        const bln = $('#filterPenyusutanBulan').val() || new Date().getMonth() + 1;
        const thn = $('#filterPenyusutanTahun').val() || new Date().getFullYear();
        window.open(`${this.state.apiUrl}manage.php?entity=penyusutan&action=${type}&bulan=${bln}&tahun=${thn}&token=${this.state.token}`, '_blank');
    },

    showPenyusutanFormula() {
        EModal.form({
            title: 'Metode & Rumus Perhitungan Penyusutan',
            size: 'md',
            form: `
                <div style="line-height: 1.6; color: #334155; padding: 5px;">
                    <p>Sistem ini menggunakan <strong>Metode Garis Lurus (Straight Line Method)</strong> untuk menghitung penyusutan aset secara merata setiap bulannya.</p>
                    
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; border-left: 4px solid #0ea5e9; margin: 12px 0;">
                        <h4 style="margin:0 0 5px 0; color:#0ea5e9; font-size:14px;">1. Nilai Perolehan</h4>
                        <code style="background:#e0f2fe; padding:2px 6px; border-radius:4px; font-weight:600;">Total Perolehan = Harga Satuan x Jumlah Unit</code>
                    </div>

                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; border-left: 4px solid #0ea5e9; margin: 12px 0;">
                        <h4 style="margin:0 0 5px 0; color:#0ea5e9; font-size:14px;">2. Beban Penyusutan Bulanan</h4>
                        <p style="margin:0 0 5px 0; font-size:13px;">Dihitung dengan membagi nilai perolehan dengan masa manfaat dalam satuan bulan.</p>
                        <code style="background:#e0f2fe; padding:2px 6px; border-radius:4px; font-weight:600;">Beban Bulanan = Total Perolehan / (Masa Manfaat Tahun x 12)</code>
                    </div>

                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; border-left: 4px solid #0ea5e9; margin: 12px 0;">
                        <h4 style="margin:0 0 5px 0; color:#0ea5e9; font-size:14px;">3. Akumulasi Penyusutan</h4>
                        <p style="margin:0 0 5px 0; font-size:13px;">Total penyusutan yang telah terjadi sejak tanggal perolehan hingga bulan laporan.</p>
                        <code style="background:#e0f2fe; padding:2px 6px; border-radius:4px; font-weight:600;">Akumulasi = Beban Bulanan x Jumlah Bulan Berjalan</code>
                    </div>

                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; border-left: 4px solid #0ea5e9; margin: 12px 0;">
                        <h4 style="margin:0 0 5px 0; color:#0ea5e9; font-size:14px;">4. Nilai Buku (Net Book Value)</h4>
                        <p style="margin:0 0 5px 0; font-size:13px;">Nilai sisa aset setelah dikurangi akumulasi penyusutan.</p>
                        <code style="background:#e0f2fe; padding:2px 6px; border-radius:4px; font-weight:600;">Nilai Buku = Total Perolehan - Akumulasi Penyusutan</code>
                    </div>

                    <p style="font-size: 0.8rem; color: #64748b; margin-top: 15px; background: #fffbeb; padding: 8px; border-radius: 6px; border: 1px solid #fde68a;">
                        <strong>Catatan:</strong> Jika umur aset sudah melebihi masa manfaatnya, maka nilai buku akan menjadi Rp 0 dan penyusutan bulanan tidak lagi dihitung.
                    </p>
                </div>
            `,
            hideFooter: true
        });
    },

    /**
     * PAGE: LAPORAN
     */
    renderLaporan($container) {
        $container.html(`
            <div class="sp-dashboard-grid">
                <div class="sp-card">
                    <div class="sp-card-header"><h3>Laporan Rekapitulasi</h3></div>
                    <div class="sp-card-body">
                        <div class="sp-laporan-item" onclick="Sarpras.printReport('rekap-kondisi')">
                            <div class="sp-lap-icon" style="background: #ffe4e6; color: #e11d48;"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg></div>
                            <div class="sp-lap-text"><h4>Rekap Kondisi Barang</h4><p>Laporan jumlah barang berdasarkan kondisi (Baik, RR, RB)</p></div>
                        </div>
                        <div class="sp-laporan-item" onclick="Sarpras.printReport('rekap-nilai')">
                            <div class="sp-lap-icon" style="background: #dcfce7; color: #16a34a;"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                            <div class="sp-lap-text"><h4>Rekap Nilai Aset</h4><p>Laporan total nilai perolehan dan nilai buku aset</p></div>
                        </div>
                    </div>
                </div>
                <div class="sp-card">
                    <div class="sp-card-header"><h3>Laporan Detail</h3></div>
                    <div class="sp-card-body">
                        <div class="sp-laporan-item" onclick="Sarpras.printReport('detail-per-ruang')">
                            <div class="sp-lap-icon" style="background: #e0e7ff; color: #4f46e5;"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg></div>
                            <div class="sp-lap-text"><h4>Daftar Barang per Ruang</h4><p>Daftar inventaris lengkap per ruangan</p></div>
                        </div>
                    </div>
                </div>
            </div>
        `);
    },

    printReport(type, params = {}) {
        let url = `${this.state.apiUrl}manage.php?entity=laporan&action=print&type=${type}&token=${this.state.token}`;
        for (const [key, value] of Object.entries(params)) {
            url += `&${key}=${encodeURIComponent(value)}`;
        }
        window.open(url, '_blank');
    },

    /**
     * PAGE: KATEGORI
     */
    formKategori(id = null) {
        const isEdit = id !== null;
        EModal.form({
            title: isEdit ? 'Edit Kategori' : 'Tambah Kategori',
            form: `<div class="form-group"><label>Nama Kategori</label><input class="form-input" id="f_ktNama" required></div><div class="form-group"><label>Kode</label><input class="form-input" id="f_ktKode" required></div>`,
            onOpen: () => { if (isEdit) this.api('manage.php?entity=kategori&action=list').done(res => { const d = res.data.find(x => x.id == id); $('#f_ktNama').val(d.nama); $('#f_ktKode').val(d.kode); }); },
            onConfirm: () => {
                const data = { id, nama: $('#f_ktNama').val(), kode: $('#f_ktKode').val() };
                this.api(`manage.php?entity=kategori&action=${isEdit?'update':'create'}`, { method: 'POST', data }).done(() => { EModal.closeAll(); this.loadReferensiData('kategori'); });
                return false;
            }
        });
    },

    delKategori(id, name) {
        EModal.confirm({
            title: 'Hapus Kategori', message: `Yakin hapus kategori <strong>${name}</strong>? <br><small class="text-danger">Kategori hanya bisa dihapus jika tidak ada barang di dalamnya.</small>`, type: 'danger',
            onConfirm: () => this.api('manage.php?entity=kategori&action=delete', { method: 'POST', data: { id } }).done(() => {
                EModal.toast({title: 'Berhasil', message: 'Kategori dihapus'});
                this.loadReferensiData('kategori');
            }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message}))
        });
    },

    /**
     * PAGE: AKSES MODUL (RBAC)
     */
    renderRoles($container) {
        $container.html(`
            <div class="sp-tab-container">
                <div class="sp-tab-header" style="display:flex;gap:0;border-bottom:2px solid var(--border-color);margin-bottom:20px">
                    <button class="sp-tab-btn active" data-tab="accounts" style="padding:10px 20px;border:none;background:none;font-weight:600;cursor:pointer;border-bottom:2px solid var(--primary);margin-bottom:-2px;color:var(--primary)">
                        Manajemen Akun
                    </button>
                    <button class="sp-tab-btn" data-tab="roles" style="padding:10px 20px;border:none;background:none;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;color:var(--text-muted)">
                        Role &amp; Izin
                    </button>
                </div>
                <div class="sp-tab-content" id="tab-accounts">
                    <div class="sp-card">
                        <div class="sp-card-header">
                            <div>
                                <h3>Pengaturan Akses Pengguna</h3>
                                <div style="font-size:0.82rem;color:var(--text-muted);margin-top:4px">
                                    Semua akun aktif dari Admin E-Portal ditampilkan di sini. Tinggal pilih siapa yang boleh mengakses modul Sarpras.
                                </div>
                            </div>
                            <div class="sp-toolbar">
                                <!-- Generate button removed as requested -->
                            </div>
                        </div>
                        <div class="sp-card-body">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
                                <div id="accountsSummary" style="font-size:0.84rem;color:var(--text-muted)">Memuat data akun...</div>
                                <div style="min-width:280px;flex:1;max-width:420px">
                                    <input type="text" class="form-input" id="accountsSearchInput" placeholder="Cari username, nama, role portal, atau role Sarpras...">
                                </div>
                            </div>
                            <div id="accountsTable"><div class="sp-acc-loading"><div class="spinner"></div> Memuat...</div></div>
                        </div>
                    </div>
                </div>
                <div class="sp-tab-content" id="tab-roles" style="display:none">
                    <div class="sp-card">
                        <div class="sp-card-header">
                            <h3>Konfigurasi Role &amp; Hak Akses</h3>
                            <div class="sp-toolbar">
                                <button class="btn btn-primary btn-sm" onclick="Sarpras.formRole()">+ Tambah Role</button>
                            </div>
                        </div>
                        <div class="sp-card-body"><div id="rolesDefTable"></div></div>
                    </div>
                </div>
            </div>
        `);

        // Tab switcher
        $container.find('.sp-tab-btn').click(function() {
            $container.find('.sp-tab-btn').css({borderBottomColor:'transparent',color:'var(--text-muted)'});
            $(this).css({borderBottomColor:'var(--primary)',color:'var(--primary)'});
            $container.find('.sp-tab-content').hide();
            $container.find('#tab-'+$(this).data('tab')).show();
        });

        this._loadAccounts();
        this._loadRolesDef();

        $container.find('#accountsSearchInput').on('input', (e) => {
            this._renderAccountsTable($(e.currentTarget).val());
        });
    },

    _loadAccounts() {
        this.api('manage.php?entity=roles&action=accounts_list').done(res => {
            if (!res.success) { $('#accountsTable').html('<div class="sp-empty">Gagal memuat data.</div>'); return; }
            this._accountsCache = Array.isArray(res.data) ? res.data : [];
            this._renderAccountsTable($('#accountsSearchInput').val() || '');
        });
    },

    _renderAccountsTable(query = '') {
        const data = Array.isArray(this._accountsCache) ? this._accountsCache : [];
        const keyword = String(query || '').trim().toLowerCase();
        const filtered = !keyword ? data : data.filter(u => {
            const haystack = [
                u.username,
                u.nama_lengkap,
                u.nik,
                u.custom_role_nama
            ].filter(Boolean).join(' ').toLowerCase();
            return haystack.includes(keyword);
        });

        const withAccess = data.filter(u => parseInt(u.custom_role_id || 0) > 0).length;
        $('#accountsSummary').html(
            `<strong>${filtered.length}</strong> akun tampil dari <strong>${data.length}</strong> akun portal aktif.
            Akses Sarpras aktif: <strong>${withAccess}</strong> akun.`
        );

        if (!filtered.length) {
            $('#accountsTable').html('<div class="sp-empty">Tidak ada akun yang cocok dengan pencarian.</div>');
            return;
        }

        const rows = filtered.map(u => {
            const hasAccess = parseInt(u.custom_role_id || 0) > 0;
            const roleBadge = hasAccess
                ? '<span class="badge badge-info">'+this.escapeHtml(u.custom_role_nama)+'</span>'
                : '<span class="badge" style="background:#f1f5f9;color:#64748b">Tanpa Akses</span>';
            const scopeInfo = u.pj_name
                ? '<span style="color:#2E7D32;font-weight:600">&#x1F3E0; '+this.escapeHtml(u.pj_name)+'</span>'
                : '<span style="color:#94a3b8">Belum terhubung ke PJ</span>';
            const safeName = (u.nama_lengkap || '').replace(/'/g, "\\'");
            const safeUsername = this.escapeHtml(u.username || '-');
            const safePortalRole = this.escapeHtml(u.portal_role || '-');
            const actionBtns = [
                '<button class="btn btn-sm btn-ghost" style="border:1px solid var(--border-color)" onclick="Sarpras.formAssignAccount('+u.id+',\''+safeName+'\','+(u.custom_role_id || 0)+')">'+(hasAccess ? 'Ubah Akses' : 'Atur Akses')+'</button>'
            ];
            if (hasAccess) {
                actionBtns.push('<button class="btn btn-sm btn-danger" onclick="Sarpras.revokeAccountAccess('+u.id+',\''+safeName+'\')">Cabut</button>');
            }
            return '<tr>'
                +'<td><div style="font-weight:700">@'+safeUsername+'</div></td>'
                +'<td>'+this.escapeHtml(u.nama_lengkap || '-')+'</td>'
                +'<td>'+this.escapeHtml(u.nik || '-')+'</td>'
                +'<td>'+roleBadge+'</td>'
                +'<td><div style="display:flex;gap:8px;flex-wrap:wrap">'+actionBtns.join('')+'</div></td>'
                +'</tr>';
        }).join('');

        $('#accountsTable').html('<table class="sp-table"><thead><tr><th>Username Portal</th><th>Nama</th><th>NIK</th><th>Akses Sarpras</th><th>Aksi</th></tr></thead><tbody>'+rows+'</tbody></table>');
    },

    _loadRolesDef() {
        this.api('manage.php?entity=roles&action=list_roles').done(res => {
            if (!res.success) { $('#rolesDefTable').html('<div class="sp-empty">Gagal memuat.</div>'); return; }
            const rows = res.data.map(r => {
                const perms = (r.permissions||'').split(',').filter(Boolean);
                const badges = perms.map(p => '<span class="badge badge-outline" style="font-size:9px;margin:1px">'+p+'</span>').join('');
                const safeName = (r.nama||'').replace(/'/g,"\\'");
                const lockIcon = parseInt(r.is_locked) ? ' &#x1F512;' : '';
                const delBtn = parseInt(r.is_locked) ? '' : '<button class="sp-btn-icon danger" onclick="Sarpras.delRole('+r.id+',\''+safeName+'\')" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>';
                return '<tr>'
                    +'<td><strong>'+r.nama+lockIcon+'</strong><div style="font-size:0.75rem;color:var(--text-muted)">'+(r.deskripsi||'-')+'</div></td>'
                    +'<td style="max-width:300px"><div style="display:flex;flex-wrap:wrap;gap:2px">'+badges+'</div></td>'
                    +'<td><button class="sp-btn-icon" onclick="Sarpras.formRole('+r.id+')" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>'+delBtn+'</td>'
                    +'</tr>';
            }).join('');
            $('#rolesDefTable').html('<table class="sp-table"><thead><tr><th>Role</th><th>Izin (Permissions)</th><th>Aksi</th></tr></thead><tbody>'+rows+'</tbody></table>');
        });
    },

    formRole(id) {
        const isEdit = id !== undefined && id !== null;
        const allPerms = [
            {key:'dashboard_view',label:'Lihat Dashboard'},
            {key:'tanah_manage',label:'Kelola Tanah'},
            {key:'bangunan_manage',label:'Kelola Bangunan'},
            {key:'ruang_manage',label:'Kelola Ruangan'},
            {key:'sarpras_manage',label:'Kelola Sarana/Prasarana'},
            {key:'perbaikan_manage',label:'Kelola Perbaikan'},
            {key:'penghapusan_manage',label:'Kelola Penghapusan'},
            {key:'peminjaman_manage',label:'Kelola Peminjaman'},
            {key:'referensi_manage',label:'Kelola Referensi'},
            {key:'report_view',label:'Lihat/Cetak Laporan'},
            {key:'roles_manage',label:'Kelola Role & Akses'},
            {key:'settings_manage',label:'Ubah Pengaturan Surat'}
        ];
        const checksHtml = allPerms.map(p =>
            '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:normal">'
            +'<input type="checkbox" class="rp-chk" value="'+p.key+'"> '+p.label+'</label>'
        ).join('');

        EModal.form({
            title: isEdit ? 'Edit Role & Izin' : 'Tambah Role Baru',
            form: '<div class="form-group"><label>Nama Role</label><input class="form-input" id="f_rlNama" required></div>'
                +'<div class="form-group"><label>Deskripsi</label><input class="form-input" id="f_rlDesc"></div>'
                +'<div class="form-group"><label>Hak Akses</label>'
                +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:12px;border:1px solid var(--border-color);border-radius:8px;background:#f9fafb">'
                +checksHtml+'</div></div>',
            onOpen: () => {
                if (isEdit) {
                    this.api('manage.php?entity=roles&action=list_roles').done(res => {
                        const r = res.data.find(x => x.id == id);
                        if (!r) return;
                        $('#f_rlNama').val(r.nama);
                        $('#f_rlDesc').val(r.deskripsi);
                        if (parseInt(r.is_locked)) $('#f_rlNama').prop('readonly',true);
                        const pp = (r.permissions||'').split(',');
                        $('.rp-chk').each(function(){ if(pp.includes($(this).val())) $(this).prop('checked',true); });
                    });
                }
            },
            onConfirm: () => {
                const perms = []; $('.rp-chk:checked').each(function(){ perms.push($(this).val()); });
                const data = {id:id||0, nama:$('#f_rlNama').val(), deskripsi:$('#f_rlDesc').val(), 'permissions[]': perms};
                if (!data.nama) { EModal.toast({type:'error',title:'Nama role wajib diisi'}); return false; }
                this.api('manage.php?entity=roles&action=save_role', {method:'POST',data}).done(res => {
                    if (res.success) { EModal.closeAll(); this._loadRolesDef(); EModal.toast({type:'success',title:'Role Tersimpan'}); }
                });
                return false;
            }
        });
    },

    delRole(id, nama) {
        EModal.confirm({
            title:'Hapus Role', message:'Yakin hapus role <strong>'+nama+'</strong>?', type:'danger',
            onConfirm: () => {
                this.api('manage.php?entity=roles&action=delete_role',{method:'POST',data:{id}}).done(res => {
                    if (res.success) { this._loadRolesDef(); EModal.toast({type:'success',title:'Role Dihapus'}); }
                });
            }
        });
    },

    formAssignAccount(userId, userName, currentRoleId = 0) {
        this.api('manage.php?entity=roles&action=list_roles').done(res => {
            const opts = res.data.map(r => '<option value="'+r.id+'">'+r.nama+'</option>').join('');
            EModal.form({
                title: 'Atur Akses: '+userName,
                form: '<div class="form-group"><label>Pilih Role Sarpras</label>'
                    +'<select class="form-select" id="f_assignRl"><option value="0">-- Tanpa Akses --</option>'+opts+'</select></div>'
                    +'<div style="font-size:12px;color:var(--text-muted);background:#f3f4f6;padding:10px;border-radius:6px">'
                    +'<strong>Info:</strong> Akun ini akan mendapatkan hak akses sesuai izin pada role yang dipilih.</div>',
                onOpen: () => {
                    $('#f_assignRl').val(String(currentRoleId || 0));
                },
                onConfirm: () => {
                    const data = {user_id:userId, role_id:$('#f_assignRl').val()};
                    this.api('manage.php?entity=roles&action=assign_account', {method:'POST',data}).done(res => {
                        if (res.success) { EModal.closeAll(); this._loadAccounts(); EModal.toast({type:'success',title:'Akses Diperbarui'}); }
                    });
                    return false;
                }
            });
        });
    },

    revokeAccountAccess(userId, userName) {
        EModal.confirm({
            title: 'Cabut Akses Sarpras',
            message: 'Yakin ingin mencabut akses modul Sarpras untuk <strong>'+userName+'</strong>?',
            type: 'danger',
            onConfirm: () => {
                this.api('manage.php?entity=roles&action=assign_account', {method:'POST',data:{user_id:userId, role_id:0}}).done(res => {
                    if (res.success) {
                        this._loadAccounts();
                        EModal.toast({type:'success', title:'Akses Dicabut', message:res.message || 'Akses berhasil dicabut.'});
                    }
                });
            }
        });
    },

    doGeneratePjAccounts() {
        EModal.confirm({
            title:'Generate Akun PJ Otomatis',
            message:'Sistem akan membuatkan akun untuk semua Penanggung Jawab yang belum memiliki akun.<br>Password default: <strong>12345678</strong><br><br>Lanjutkan?',
            onConfirm: () => {
                const loader = EModal.loading('Memproses...');
                this.api('manage.php?entity=roles&action=generate_pj_accounts').done(res => {
                    EModal.close(loader);
                    if (res.success) {
                        this._loadAccounts();
                        const logLines = (res.data||[]).join('<br>');
                        const logBox = logLines ? '<div style="max-height:200px;overflow-y:auto;font-family:monospace;font-size:11px;background:#1a1a2e;color:#0f0;padding:10px;border-radius:6px;margin-top:10px">'+logLines+'</div>' : '';
                        EModal.info({title:'Selesai', message:res.message+logBox});
                    }
                }).fail(() => EModal.close(loader));
            }
        });
    },

    renderSarprasFotosHtml(fotos, sarprasId) {
        let html = '';
        if (fotos && fotos.length) {
            html = fotos.map(f => `
                <div class="sp-foto-item">
                    <img src="${this.state.baseUrl}${f.foto_path}" onclick="window.open(this.src)">
                    <button class="btn-del" onclick="Sarpras.deleteSarprasFoto(${f.id}, ${sarprasId})">&times;</button>
                </div>
            `).join('');
        }
        if (!fotos || fotos.length < 5) {
            html += `
                <div class="sp-foto-add" onclick="Sarpras.uploadSarprasFoto(${sarprasId})">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Upload Foto</span>
                </div>
            `;
        }
        return html;
    },

    uploadSarprasFoto(sarprasId) {
        const input = $('<input type="file" accept="image/*">');
        input.on('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('foto', file);
            formData.append('sarpras_id', sarprasId);
            formData.append('keterangan', 'Foto Aset');
            this.api('sarpras.php?action=upload-foto', { method: 'POST', data: formData }).done(() => {
                EModal.toast({title:'Berhasil Upload'});
                this.renderPage(this.state.currentRoute, this.state.params);
            });
        });
        input.click();
    },

    deleteSarprasFoto(fotoId, sarprasId) {
        EModal.confirm({
            title: 'Hapus Foto', message: 'Yakin hapus foto ini?', type: 'danger',
            onConfirm: () => this.api('sarpras.php?action=delete-foto', { method: 'POST', data: { foto_id: fotoId } }).done(() => {
                this.renderPage(this.state.currentRoute, this.state.params);
            })
        });
    },

    /**
     * PAGE: REFERENSI
     */
    renderReferensi($container) {
        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header">
                    <h3>Data Referensi E-Sarpras</h3>
                    <button class="btn btn-primary btn-sm" onclick="Sarpras.formActiveTab()">+ Tambah</button>
                </div>
                <div class="sp-card-body" style="padding-top:0;">
                    <div class="sp-tabs" id="referensiTabs">
                        <button class="sp-tab" data-tab="kategori">Kategori Sarpras</button>
                        <button class="sp-tab" data-tab="penanggung_jawab">Penanggung Jawab</button>
                        <button class="sp-tab" data-tab="asal_dana">Asal Dana</button>
                        <button class="sp-tab" data-tab="klasifikasi_buku">Klasifikasi Buku</button>
                        <button class="sp-tab" data-tab="jenis_ruang">Tipe Ruangan</button>
                    </div>
                    <div class="sp-table-wrapper" id="referensiTable"><div class="skeleton" style="height:200px"></div></div>
                </div>
            </div>
        `);
        
        $('.sp-tab').on('click', function() {
            $('.sp-tab').removeClass('active');
            $(this).addClass('active');
            Sarpras.loadReferensiData($(this).data('tab'));
        });

        // Set default based on route params or fallback to kategori
        const defaultTab = this.state.params.tab || 'kategori';
        $(`.sp-tab[data-tab="${defaultTab}"]`).click();
    },

    loadReferensiData(tab) {
        this.state.activeRefTab = tab;
        $('#referensiTable').html('<div class="skeleton" style="height:200px"></div>');
        
        if (tab === 'kategori') {
            this.api('manage.php?entity=kategori&action=list').done(res => {
                if (!res.data.length) { $('#referensiTable').html('<div class="sp-empty">Belum ada Kategori.</div>'); return; }
                const rows = res.data.map(k => `<tr>
                    <td><strong>${k.nama}</strong></td>
                    <td>${k.kode}</td>
                    <td>${k.total_barang || k.jumlah_sarpras || 0} Barang</td>
                    <td>
                        <div class="sp-actions">
                            <button class="sp-btn-icon" onclick="Sarpras.formKategori(${k.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="sp-btn-icon danger" onclick="Sarpras.delKategori(${k.id}, '${k.nama}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>`).join('');
                $('#referensiTable').html(`<table class="sp-table"><thead><tr><th>Nama Kategori</th><th>Kode</th><th>Jumlah Aset</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
            });
        } else if (tab === 'penanggung_jawab') {
            this.api('pj.php?action=list').done(res => {
                if (!res.data.length) { $('#referensiTable').html('<div class="sp-empty">Belum ada data Penanggung Jawab. Klik "+ Tambah" untuk menambahkan.</div>'); return; }
                const rows = res.data.map(r => `<tr>
                    <td><strong>${r.nama}</strong>${r.nip ? `<br><small style="color:var(--text-muted)">NIP: ${r.nip}</small>` : ''}</td>
                    <td>${r.keterangan || '-'}</td>
                    <td><span class="badge badge-info">${r.jumlah_ruang || 0} Ruang</span></td>
                    <td>
                        <div class="sp-actions">
                            <button class="sp-btn-icon" onclick="Sarpras.formPjRef(${r.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="sp-btn-icon danger" onclick="Sarpras.deletePjRef(${r.id}, '${r.nama}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>`).join('');
                $('#referensiTable').html(`<table class="sp-table"><thead><tr><th>Nama / NIP</th><th>Jabatan</th><th>Ruang</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
            });
        } else {
            this.api(`referensi.php?action=list&kategori=${tab}`).done(res => {
                if (!res.data.length) { $('#referensiTable').html('<div class="sp-empty">Belum ada data referensi.</div>'); return; }
                
                const isKlasifikasi = tab === 'klasifikasi_buku';
                const isAsalDana = tab === 'asal_dana';
                const isJenisRuang = tab === 'jenis_ruang';

                const col1 = isKlasifikasi ? 'Kode' : 'Identitas / Nilai';
                const col2 = isKlasifikasi ? 'Klasifikasi' : (isJenisRuang ? 'Kategori' : 'Kelompok Tab');
                const col3 = isKlasifikasi ? 'Keterangan' : 'Deskripsi Lanjutan';

                const headerHtml = `<tr>
                    <th>${col1}</th>
                    ${!isAsalDana ? `<th>${col2}</th>` : ''}
                    <th>${col3}</th>
                    <th style="width:120px">Aksi</th>
                </tr>`;

                const rows = res.data.map(r => {
                    const isLocked = isJenisRuang && (r.nama === 'Ruang Kelas' || r.nama === 'Perpustakaan');
                    return `<tr>
                        <td><strong>${isKlasifikasi ? (r.kode || '-') : r.nama}</strong></td>
                        ${!isAsalDana ? `<td><span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:0.8rem; font-weight:600;">${isKlasifikasi ? r.nama : r.kategori.replace('_', ' ').toUpperCase()}</span></td>` : ''}
                        <td>${r.keterangan || '-'}</td>
                        <td>
                            <div class="sp-actions">
                                ${isLocked ? `<span style="font-size:0.75rem; color:#94a3b8; font-style:italic;">Dikunci Sistem</span>` : `
                                    <button class="sp-btn-icon" onclick="Sarpras.formReferensi(${r.id}, '${tab}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                    <button class="sp-btn-icon danger" onclick="Sarpras.deleteReferensi(${r.id}, '${tab}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                `}
                            </div>
                        </td>
                    </tr>`;
                }).join('');
                $('#referensiTable').html(`<table class="sp-table"><thead>${headerHtml}</thead><tbody>${rows}</tbody></table>`);
            });
        }
    },

    formActiveTab() {
        if (this.state.activeRefTab === 'kategori') this.formKategori();
        else if (this.state.activeRefTab === 'penanggung_jawab') this.formPjRef();
        else this.formReferensi(null, this.state.activeRefTab);
    },

    formReferensi(id = null, tabCat = 'asal_dana') {
        const isEdit = id !== null;
        const isPJ = tabCat === 'penanggung_jawab';
        const isKlasifikasi = tabCat === 'klasifikasi_buku';
        const catName = isPJ ? 'Penanggung Jawab' : (isKlasifikasi ? 'Klasifikasi Buku' : tabCat.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()));
        const namaLabel = isPJ ? 'Nama Lengkap PJ' : (isKlasifikasi ? 'Nama Klasifikasi' : 'Nama / Nilai');
        const kodeLabel = isKlasifikasi ? 'Kode Klasifikasi' : 'Kode (Opsional)';
        const ketLabel = isPJ ? 'Jabatan (Contoh: Wali Kelas XII IPA 1)' : 'Keterangan';
        EModal.form({
            title: isEdit ? `Edit ${catName}` : `Tambah ${catName}`,
            form: `
                <input type="hidden" id="f_rfCat" value="${tabCat}">
                <div class="form-group"><label>${kodeLabel}</label><input class="form-input" id="f_rfKode" placeholder="${isKlasifikasi ? 'Contoh: 000, 100, 200...' : ''}"></div>
                <div class="form-group"><label>${namaLabel}</label><input class="form-input" id="f_rfNama" required placeholder="${isPJ ? 'Contoh: Budi Santoso, S.Pd' : (isKlasifikasi ? 'Contoh: Karya Umum, Filsafat...' : '')}"></div>
                <div class="form-group"><label>${ketLabel}</label><input class="form-input" id="f_rfKet" placeholder="${isPJ ? 'Contoh: Wali Kelas XII IPA 1 / Kepala Lab' : ''}"></div>
            `,
            onOpen: () => {
                if (isEdit) this.api('referensi.php?action=list').done(res => {
                    const d = res.data.find(x => x.id == id);
                    if (d) { $('#f_rfCat').val(d.kategori); $('#f_rfNama').val(d.nama); $('#f_rfKode').val(d.kode); $('#f_rfKet').val(d.keterangan); }
                });
            },
            onConfirm: () => {
                const data = { id, kategori: $('#f_rfCat').val(), nama: $('#f_rfNama').val(), kode: $('#f_rfKode').val(), keterangan: $('#f_rfKet').val() };
                this.api(`referensi.php?action=${isEdit?'update':'create'}`, { method: 'POST', data }).done(() => { 
                    EModal.closeAll(); 
                    this.loadReferensiData(this.state.activeRefTab); 
                });
                return false;
            }
        });
    },

    deleteReferensi(id, tabCat) {
        EModal.confirm({
            title: 'Hapus Referensi', message: 'Yakin hapus data ini?', type: 'danger',
            onConfirm: () => this.api('referensi.php?action=delete', { method: 'POST', data: { id } }).done(() => this.loadReferensiData(tabCat))
        });
    },

    /**
     * PJ Management via Referensi page (uses pj.php API)
     */
    formPjRef(id = null) {
        const isEdit = id !== null;
        EModal.form({
            title: isEdit ? 'Edit Penanggung Jawab' : 'Tambah Penanggung Jawab',
            form: `
                <style>
                    .sp-ruang-cs { position:relative; user-select:none; margin-bottom:4px; }
                    .sp-ruang-cs-btn { cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:10px 15px; height:44px; transition:all 0.2s; }
                    .sp-ruang-cs-btn:hover { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
                    .sp-ruang-cs-btn.active { border-color:#3b82f6; }
                    .sp-ruang-cs-dd { display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:#fff; border:1px solid #cbd5e1; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:10000; overflow:hidden; max-height:240px; }
                    .sp-ruang-cs-search { padding:10px 12px; border-bottom:1px solid #f1f5f9; }
                    .sp-ruang-cs-search input { width:100%; padding:8px 10px; border:1px solid #e2e8f0; border-radius:8px; font-size:0.85rem; outline:none; }
                    .sp-ruang-cs-search input:focus { border-color:#3b82f6; }
                    .sp-ruang-cs-list { max-height:180px; overflow-y:auto; }
                    .sp-ruang-cs-opt { padding:10px 14px; cursor:pointer; border-bottom:1px solid #f1f5f9; transition:background 0.15s; }
                    .sp-ruang-cs-opt:hover { background:#f1f5f9; }
                    .sp-ruang-cs-opt .r-n { font-weight:600; color:#1e293b; font-size:0.9rem; }
                    .sp-ruang-cs-opt .r-b { font-size:0.75rem; color:#64748b; margin-top:1px; }
                    .sp-ruang-cs-opt.none { color:#94a3b8; font-style:italic; font-size:0.85rem; }
                </style>
                <div class="form-group">
                    <label>Pilih Data Guru (Dari Portal)</label>
                    <div class="sp-ruang-cs" id="guruSelectContainer">
                        <div class="sp-ruang-cs-btn" id="guruSelectBtn" style="height:44px; padding:10px 15px;">
                            <span id="guruSelectedText" style="color:#94a3b8;">-- Cari & Pilih Nama Guru --</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                        <div class="sp-ruang-cs-dd" id="guruDropdown">
                            <div class="sp-ruang-cs-search"><input type="text" id="guruSearchInput" placeholder="Ketik nama untuk mencari..."></div>
                            <div class="sp-ruang-cs-list" id="guruOptionsList"></div>
                        </div>
                    </div>
                    <input type="hidden" id="f_pjrNama" value="">
                    <input type="hidden" id="f_pjrUserId" value="">
                </div>

                <div class="form-group">
                    <label>NIP / User</label>
                    <input class="form-input" id="f_pjrNip" placeholder="Otomatis terisi" readonly style="background:#f8fafc;">
                </div>
                
                <div class="form-group">
                    <label>Jabatan</label>
                    <select class="form-select" id="f_pjrKet">
                        <option value="">-- Pilih Jabatan --</option>
                    </select>
                </div>
                
                <input type="hidden" id="f_pjrRuangId" value="">
                <div class="form-group" id="ruangSelectWrapper">
                    <label id="ruangSelectLabel">Pilih Ruangan</label>
                    <div class="sp-ruang-cs" id="ruangSelectContainer">
                        <div class="sp-ruang-cs-btn" id="ruangSelectBtn">
                            <span id="ruangSelectedText" style="color:#94a3b8; font-size:0.9rem;">-- Pilih Ruangan --</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                        <div class="sp-ruang-cs-dd" id="ruangDropdown">
                            <div class="sp-ruang-cs-search"><input type="text" id="ruangSearchInput" placeholder="Cari ruangan..."></div>
                            <div class="sp-ruang-cs-list" id="ruangOptionsList"></div>
                        </div>
                    </div>
                </div>
            `,
            onOpen: () => {
                // Load Guru from Portal
                this.api('pj.php?action=guru_list').done(res => {
                    const gList = res.data || [];
                    let gHtml = '';
                    gList.forEach(g => {
                        const nipText = g.nip ? g.nip : '-';
                        gHtml += `<div class="sp-ruang-cs-opt sg-opt" data-id="${g.id}" data-nama="${g.nama_lengkap}" data-nip="${g.nip}" data-jabatan="${g.jabatan||''}">
                            <div class="r-n">${g.nama_lengkap}</div>
                            <div class="r-b">NIP/User: ${nipText} ${g.jabatan ? '— ' + g.jabatan : ''}</div>
                        </div>`;
                    });
                    $('#guruOptionsList').html(gHtml);

                    // Direct binding to ensure clicks always register
                    $('#guruOptionsList .sg-opt').off('click').on('click', function(e) {
                        e.stopPropagation();
                        const nama = $(this).data('nama') || '';
                        const nip = $(this).data('nip') || '';
                        const jabatan = $(this).data('jabatan') || '';

                        if (nama) {
                            $('#f_pjrNama').val(nama);
                            $('#f_pjrUserId').val($(this).data('id'));
                            if(nip && nip.toString().toLowerCase() !== 'admin') $('#f_pjrNip').val(nip);
                            $('#guruSelectedText').text(nama).css('color', '#1e293b');
                            
                            if(jabatan){
                                if($('#f_pjrKet option[value="'+jabatan+'"]').length > 0) {
                                    $('#f_pjrKet').val(jabatan).trigger('change');
                                } else {
                                    $('#f_pjrKet').append(`<option value="${jabatan}">${jabatan}</option>`).val(jabatan).trigger('change');
                                }
                            }
                        } else {
                            $('#f_pjrNama').val('');
                            $('#f_pjrUserId').val('');
                            $('#f_pjrNip').val('');
                            $('#f_pjrKet').val('').trigger('change');
                            $('#guruSelectedText').text('-- Cari & Pilih Nama Guru --').css('color', '#94a3b8');
                        }
                        
                        $('#guruDropdown').hide();
                        $('#guruSelectBtn').removeClass('active');
                    });
                });

                // Guru dropdown handlers
                $('#guruSelectBtn').on('click', function(e) {
                    e.stopPropagation();
                    const dd = $('#guruDropdown');
                    const isOpen = dd.is(':visible');
                    dd.toggle(!isOpen);
                    $(this).toggleClass('active', !isOpen);
                    if (!isOpen) { $('#guruSearchInput').val('').focus(); $('#guruOptionsList .sg-opt').show(); }
                });

                $('#guruSearchInput').on('input', function() {
                    const q = $(this).val().toLowerCase();
                    $('#guruOptionsList .sg-opt').each(function() {
                        const nama = ($(this).data('nama') || '').toString().toLowerCase();
                        $(this).toggle(nama.includes(q) || $(this).hasClass('none'));
                    });
                });

                // Global click listener to close dropdown
                $(document).off('click.closeGuru').on('click.closeGuru', function(e) {
                    if (!$(e.target).closest('#guruSelectContainer').length) {
                        $('#guruDropdown').hide();
                        $('#guruSelectBtn').removeClass('active');
                    }
                });

                // Fetch Jabatan 
                $.ajax({
                    url: this.state.baseUrl + 'api/referensi.php?action=list&kategori=jabatan',
                    method: 'GET',
                    success: function(resJab) {
                        if(resJab.data) {
                            resJab.data.forEach(r => $('#f_pjrKet').append(`<option value="${r.nama}">${r.nama}</option>`));
                        }
                    }
                }).always(() => {
                    if (isEdit) {
                        this.api(`pj.php?action=get&id=${id}`).done(res => {
                            if(res.success) {
                                $('#f_pjrNama').val(res.data.nama);
                                $('#f_pjrUserId').val(res.data.user_id || '');
                                $('#f_pjrNip').val(res.data.nip);
                                $('#guruSelectedText').text(res.data.nama).css('color', '#1e293b');
                                $('#f_pjrKet').val(res.data.keterangan).trigger('change');
                                
                                if (res.data.ruang_id) {
                                    $('#f_pjrRuangId').val(res.data.ruang_id);
                                    this.api(`ruang.php?action=get&id=${res.data.ruang_id}`).done(rres => {
                                        if (rres.success && rres.data) {
                                            $('#ruangSelectedText').text(rres.data.nama).css('color', '#1e293b');
                                        }
                                    });
                                }
                            }
                        });
                    }
                });


                let allRooms = [];
                const renderRoomList = () => {
                    const jab = ($('#f_pjrKet').val() || '').toLowerCase();
                    const isWali = jab.includes('wali kelas');
                    
                    $('#ruangSelectLabel').text(isWali ? 'Pilih Ruang Kelas (Wajib)' : 'Pilih Ruangan (Selain Ruang Kelas)');
                    
                    const filtered = allRooms.filter(r => {
                        const isRK = (r.jenis_ruang || '').toLowerCase() === 'ruang kelas';
                        return isWali ? isRK : !isRK;
                    });

                    let optHtml = `<div class="sp-ruang-cs-opt none" data-id="">— Tanpa Ruang —</div>`;
                    filtered.forEach(r => {
                        optHtml += `<div class="sp-ruang-cs-opt" data-id="${r.id}" data-nama="${r.nama}">
                            <div class="r-n">${r.nama}</div>
                            <div class="r-b">${r.bangunan_nama} - ${r.tanah_nama}</div>
                        </div>`;
                    });
                    $('#ruangOptionsList').html(optHtml);
                };

                // Load All Rooms
                this.api('ruang.php?action=list').done(res => {
                    allRooms = res.data || [];
                    renderRoomList();
                });

                // Update room list on Jabatan change
                $('#f_pjrKet').on('change', () => {
                    renderRoomList();
                });

                // Ruang dropdown toggle
                $('#ruangSelectBtn').on('click', function(e) {
                    e.stopPropagation();
                    const dd = $('#ruangDropdown');
                    const isOpen = dd.is(':visible');
                    dd.toggle(!isOpen);
                    $(this).toggleClass('active', !isOpen);
                    if (!isOpen) { $('#ruangSearchInput').val('').focus(); $('#ruangOptionsList .sp-ruang-cs-opt').show(); }
                });

                // Search filter
                $(document).on('input.ruangSearch', '#ruangSearchInput', function() {
                    const q = $(this).val().toLowerCase();
                    $('#ruangOptionsList .sp-ruang-cs-opt').each(function() {
                        const nama = ($(this).data('nama') || '').toString().toLowerCase();
                        $(this).toggle(nama.includes(q) || $(this).hasClass('none'));
                    });
                });

                // Ruang option click
                $(document).on('click.ruangSelect', '.sp-ruang-cs-opt', function(e) {
                    e.stopPropagation();
                    const rid = $(this).data('id');
                    const rnama = $(this).data('nama') || '';
                    
                    $('#f_pjrRuangId').val(rid || '');
                    
                    if (rid) {
                        $('#ruangSelectedText').text(rnama).css('color', '#1e293b');
                    } else {
                        $('#ruangSelectedText').text('-- Pilih Ruang Kelas --').css('color', '#94a3b8');
                    }
                    
                    $('#ruangDropdown').hide();
                    $('#ruangSelectBtn').removeClass('active');
                });

                // Close dropdown on outside click
                $(document).on('click.ruangClose', function(e) {
                    if (!$(e.target).closest('#ruangSelectContainer').length) {
                        $('#ruangDropdown').hide();
                        $('#ruangSelectBtn').removeClass('active');
                    }
                    if (!$(e.target).closest('#guruSelectContainer').length) {
                        $('#guruDropdown').hide();
                        $('#guruSelectBtn').removeClass('active');
                    }
                });
            },
            onConfirm: () => {
                $(document).off('input.ruangSearch').off('click.ruangSelect').off('click.ruangClose')
                           .off('input.guruSearch').off('click.guruSelect');
                const data = {
                    id,
                    bangunan_id: 0, // PJ is now global, not tied to specific bangunan
                    nama: $('#f_pjrNama').val(),
                    user_id: $('#f_pjrUserId').val() || null,
                    nip: $('#f_pjrNip').val(),
                    keterangan: $('#f_pjrKet').val(),
                    ruang_id: $('#f_pjrRuangId').val() || null
                };
                if (!data.nama) { EModal.toast({type:'error', title:'Error', message:'Nama wajib diisi'}); return false; }
                this.api(`pj.php?action=${isEdit ? 'update' : 'create'}`, { method: 'POST', data }).done(() => {
                    EModal.closeAll();
                    this.loadReferensiData('penanggung_jawab');
                }).fail(err => {
                    const msg = err.responseJSON && err.responseJSON.message ? err.responseJSON.message : 'Terjadi kesalahan sistem';
                    EModal.toast({type:'error', title:'Gagal Disimpan', message: msg, duration: 5000});
                });
                return false;
            }
        });
    },

    deletePjRef(id, name) {
        EModal.confirm({
            title: 'Hapus Penanggung Jawab',
            message: `Yakin hapus <strong>${name}</strong>? Ruangan yang memiliki PJ ini akan kehilangan data PJ-nya.`,
            type: 'danger',
            onConfirm: () => this.api('pj.php?action=delete', { method: 'POST', data: { id } }).done(() => this.loadReferensiData('penanggung_jawab'))
        });
    },

    // ==================== PENGATURAN SURAT ====================
    renderSettingsSurat($container) {
        $container.html('<div class="skeleton" style="height:300px"></div>');
        this.api('manage.php?entity=settings&action=get').done(res => {
            const d = res.data;
            const kopPreview = d.kop_surat 
                ? `<img src="${this.state.baseUrl}${d.kop_surat}" style="max-width:100%; max-height:120px; border:1px solid #ddd; border-radius:8px; padding:5px; margin-bottom:10px;">` 
                : '<div style="padding:20px; background:#f8fafc; border:2px dashed #cbd5e1; border-radius:8px; text-align:center; color:#94a3b8; margin-bottom:10px;">Belum ada kop surat di Pengaturan E-Portal.</div>';
            
            $container.html(`
                <div class="sp-card">
                    <div class="sp-card-header"><h3>Pengaturan Berita Acara</h3></div>
                    <div class="sp-card-body">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px;">
                            <div>
                                <h4 style="margin-bottom:15px; color:var(--primary)">Kop Surat</h4>
                                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:10px;">Kop surat diambil otomatis dari Pengaturan E-Portal admin dan dipakai lintas modul.</p>
                                ${kopPreview}
                                <div style="padding:12px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; color:#1d4ed8; font-size:0.9rem;">
                                    Untuk mengubah kop surat, buka Pengaturan di E-Portal admin.
                                </div>
                            </div>
                            <div>
                                <h4 style="margin-bottom:15px; color:var(--primary)">Tanda Tangan</h4>
                                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:10px;">Nama kepala sekolah selalu mengikuti Pengaturan E-Portal admin.</p>
                                <div class="form-group" style="margin-bottom:15px;">
                                    <label>Nama Kepala Sekolah</label>
                                    <div style="padding:12px 14px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; color:#0f172a; min-height:46px; display:flex; align-items:center;">
                                        ${this.escapeHtml(d.kepala_sekolah || 'Belum diatur di E-Portal')}
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom:15px;">
                                    <label>Nama Waka Sarpras</label>
                                    <input class="form-input" id="f_waka" value="${d.waka_sarpras || ''}" placeholder="Contoh: Shobahul Hoir, S.Pd., M.Pd.">
                                </div>
                            </div>
                        </div>
                        <div style="margin-top:20px; text-align:right;">
                            <button class="btn btn-primary" onclick="Sarpras.saveSettingsSurat()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px; vertical-align:middle; margin-right:5px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                Simpan Pengaturan
                            </button>
                        </div>
                    </div>
                </div>
            `);
        });
    },

    saveSettingsSurat() {
        const formData = new FormData();
        formData.append('waka_sarpras', $('#f_waka').val());
        
        $.ajax({
            url: `api/manage.php?entity=settings&action=save&token=${this.getToken()}`,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: () => {
                EModal.toast({type:'success', title:'Pengaturan Tersimpan'});
                this.loadRouteFromHash();
            },
            error: (xhr) => {
                EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message || 'Error'});
            }
        });
    },

    // ==================== PENGHAPUSAN ====================
    renderPenghapusan($container) {
        $container.html('<div class="sp-loading-container"><div class="sp-spinner"></div><p>Memuat data penghapusan...</p></div>');
        
        this.api('manage.php?entity=penghapusan&action=list').done(res => {
            const items = res.data.items;
            const countPending = res.data.count_pending;
            
            let html = `
                <div class="sp-header-actions" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                    <div><h2 style="font-size:1.2rem; display:inline-block; margin:0">Log Penghapusan Barang</h2></div>
                    ${countPending > 0 ? `
                        <button class="btn btn-primary" onclick="Sarpras.openGenerateBAModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px; margin-right:5px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                            Generate BA untuk ${countPending} Barang Baru
                        </button>
                    ` : ''}
                </div>
                
                <div class="sp-card">
                    <div class="sp-table-responsive">
                        <table class="sp-table">
                            <thead>
                                <tr>
                                    <th style="width:50px">No</th>
                                    <th>Nama Barang</th>
                                    <th>Kode Inventaris</th>
                                    <th style="width:120px">Tgl Hapus</th>
                                    <th>Alasan</th>
                                    <th style="width:180px">No. Berita Acara</th>
                                    <th style="width:100px; text-align:center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (items.length === 0) {
                html += '<tr><td colspan="7" class="sp-empty-table">Belum ada data penghapusan.</td></tr>';
            } else {
                // Process rowspans
                const groups = {};
                items.forEach((item, idx) => {
                    const key = item.ba_id || `pending_${idx}`;
                    if (!groups[key]) groups[key] = [];
                    groups[key].push(item);
                });

                let globalIdx = 1;
                Object.keys(groups).forEach(key => {
                    const groupItems = groups[key];
                    const isGrouped = !key.startsWith('pending_');

                    groupItems.forEach((item, innerIdx) => {
                        html += `<tr ${!item.ba_id ? 'style="background-color:#fffbeb"' : ''}>
                            <td>${globalIdx++}</td>
                            <td><div style="font-weight:600">${item.sarpras_nama}</div></td>
                            <td><code>${item.kode_inventaris}</code></td>
                            <td>${item.tanggal}</td>
                            <td><div style="font-size:0.85rem">${item.deskripsi || '-'}</div></td>
                        `;

                        if (innerIdx === 0) {
                            if (isGrouped) {
                                html += `
                                    <td rowspan="${groupItems.length}" style="background:#f8fafc; border-left:1px solid #e2e8f0; font-weight:600">
                                        <div style="color:var(--primary)">${item.nomor_ba}</div>
                                        <div style="font-size:0.75rem; color:var(--text-muted)">BA tgl: ${item.tanggal_ba}</div>
                                    </td>
                                    <td rowspan="${groupItems.length}" style="text-align:center; background:#f8fafc; border-left:1px solid #e2e8f0">
                                        <div class="sp-actions" style="justify-content:center">
                                            <a href="api/manage.php?entity=laporan&action=print&type=berita-acara&ba_id=${item.ba_id}&token=${this.getToken()}" target="_blank" class="sp-btn-icon primary" title="Cetak BA">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px; height:18px"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                            </a>
                                            <button class="sp-btn-icon" onclick="Sarpras.cancelBA(${item.ba_id})" title="Batalkan BA (Lepas barang dari BA)">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px; height:18px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                            <button class="sp-btn-icon danger" onclick="Sarpras.deletePenghapusan(${item.ba_id}, true)" title="Hapus Permanen & Kembalikan ke Inventaris">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px; height:18px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                `;
                            } else {
                                html += `
                                    <td style="color:#9a3412; font-size:0.8rem; font-style:italic">Belum ada BA</td>
                                    <td style="text-align:center">
                                        <div class="sp-actions" style="justify-content:center">
                                            <button class="sp-btn-icon danger" onclick="Sarpras.deletePenghapusan(${item.id}, false)" title="Hapus Permanen & Kembalikan ke Inventaris">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px; height:18px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                `;
                            }
                        }
                        
                        html += `</tr>`;
                    });
                });
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            $container.html(html);
        });
    },

    openGenerateBAModal() {
        this.api('manage.php?entity=penghapusan&action=list').done(res => {
            const pendingItems = res.data.items.filter(i => !i.ba_id);
            const itemListHtml = pendingItems.map(i => `<li><b>${i.sarpras_nama}</b> (${i.kode_inventaris})</li>`).join('');
            
            EModal.confirm({
                title: 'Generate Berita Acara',
                message: `
                    <div style="padding:10px;">
                        <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:8px; padding:15px; margin-bottom:20px; display:flex; gap:15px; align-items:flex-start;">
                            <div style="background:#f59e0b; color:white; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-weight:bold; font-size:1.2rem;">!</div>
                            <div>
                                <h4 style="color:#92400e; margin-bottom:5px">Konfirmasi Pembuatan BA</h4>
                                <p style="font-size:0.85rem; color:#b45309">Barang-barang berikut akan digabungkan ke dalam satu Berita Acara:</p>
                                <ul style="font-size:0.85rem; margin-top:10px; margin-left:20px; color:#92400e">
                                    ${itemListHtml}
                                </ul>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:15px">
                            <label>Nomor Berita Acara (Manual)</label>
                            <input class="form-input" id="f_baNomor" placeholder="Contoh: 001/BA-HPS/2026">
                        </div>
                        <div class="form-group">
                            <label>Tanggal Berita Acara</label>
                            <input type="date" class="form-input" id="f_baTanggal" value="${new Date().toISOString().split('T')[0]}">
                        </div>
                    </div>
                `,
                confirmLabel: 'Ya, Buat Berita Acara',
                onConfirm: () => {
                    const data = {
                        nomor_ba: $('#f_baNomor').val(),
                        tanggal_ba: $('#f_baTanggal').val()
                    };
                    if(!data.nomor_ba) { EModal.toast({type:'error', title:'Error', message:'Nomor BA wajib diisi'}); return false; }
                    
                    this.api('manage.php?entity=penghapusan&action=generate-ba', { method: 'POST', data }).done(() => {
                        this.loadRouteFromHash();
                    });
                }
            });
        });
    },

    cancelBA(id) {
        EModal.confirm({
            title: 'Batalkan BA',
            message: 'Yakin ingin membatalkan Berita Acara ini? Barang-barang di dalamnya akan kembali berstatus "Belum ada BA".',
            onConfirm: () => {
                this.api(`manage.php?entity=penghapusan&action=delete-ba&id=${id}`).done(() => {
                    this.loadRouteFromHash();
                });
            }
        });
    },

    deletePenghapusan(id, isBa) {
        const title = isBa ? 'Hapus BA & Riwayat' : 'Hapus Riwayat Penghapusan';
        const msg = isBa 
            ? 'Yakin ingin menghapus Berita Acara ini beserta seluruh riwayat penghapusan barang di dalamnya? Barang akan dikembalikan ke daftar inventaris aktif.'
            : 'Yakin ingin menghapus riwayat penghapusan barang ini? Barang akan dikembalikan ke daftar inventaris aktif.';
            
        EModal.confirm({
            title: title,
            message: msg,
            type: 'danger',
            onConfirm: () => {
                const param = isBa ? `ba_id=${id}` : `id=${id}`;
                this.api(`manage.php?entity=penghapusan&action=delete-history&${param}`).done(res => {
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                    this.loadRouteFromHash();
                });
            }
        });
    },

    // ==================== PEMINJAMAN ====================
    renderPeminjaman($container) {
        $container.html(`
            <div class="sp-card">
                <div class="sp-card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <h3 style="margin:0">Data Peminjaman Barang</h3>
                    <div class="sp-toolbar">
                        <div class="sp-search-wrapper" style="position:relative; width: 250px;">
                            <svg style="position:absolute; left:10px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:#64748b;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="searchPeminjaman" class="form-input" placeholder="Cari peminjam / barang..." style="padding-left:35px; width:100%;">
                        </div>
                        <select id="filterStatusPeminjaman" class="form-input" style="width: auto; padding: 6px 12px; border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: 0;">
                            <option value="">Semua Status</option>
                            <option value="Dipinjam" selected>Sedang Dipinjam</option>
                            <option value="Dikembalikan">Sudah Dikembalikan</option>
                        </select>
                        <button class="btn btn-primary btn-sm" onclick="Sarpras.formPeminjaman()" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">+ Peminjaman Baru</button>
                    </div>
                </div>
                <div class="sp-card-body">
                    <div class="sp-table-wrapper" id="peminjamanTable"><div class="skeleton" style="height:200px"></div></div>
                </div>
            </div>
        `);
        
        let searchTimer;
        $('#searchPeminjaman').on('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => this.fetchPeminjaman(), 300);
        });
        $('#filterStatusPeminjaman').on('change', () => this.fetchPeminjaman());
        this.fetchPeminjaman();
    },

    fetchPeminjaman() {
        const s = $('#filterStatusPeminjaman').val();
        const search = $('#searchPeminjaman').val() || '';
        this.api(`manage.php?entity=peminjaman&action=list&status=${s}&search=${encodeURIComponent(search)}`).done(res => {
            const data = res.data || [];
            if(!data.length) {
                $('#peminjamanTable').html('<div class="sp-empty">Belum ada data peminjaman di status ini.</div>');
                return;
            }
            const rows = data.map(d => `
                <tr>
                    <td>
                        <strong>${d.nama_peminjam}</strong><br>
                        <small style="color:var(--text-muted)">${d.jabatan || '-'}</small>
                    </td>
                    <td>
                        <strong>${d.sarpras_nama}</strong><br>
                        <small style="color:var(--text-muted)">${d.kode_inventaris}</small>
                    </td>
                    <td style="text-align:center">
                        <div style="font-size:0.85em; background:#f1f5f9; padding:2px 6px; border-radius:4px; display:inline-block;">Pinjam: ${this.formatDate(d.tanggal_pinjam)}</div><br>
                        ${d.status === 'Dikembalikan'
                            ? `<div style="font-size:0.85em; background:#dcfce7; padding:2px 6px; border-radius:4px; display:inline-block; margin-top:4px; color:#166534">Kembali: ${this.formatDate(d.tanggal_kembali_aktual)}</div>`
                            : `<div style="font-size:0.85em; background:#fef3c7; padding:2px 6px; border-radius:4px; display:inline-block; margin-top:4px; color:#92400e">Rencana: ${d.tanggal_kembali_rencana ? this.formatDate(d.tanggal_kembali_rencana) : '-'}</div>`
                        }
                    </td>
                    <td style="text-align:center; font-weight:bold;">${d.jumlah}</td>
                    <td style="text-align:center">
                        ${d.status === 'Dipinjam' ? '<span class="badge badge-warning">Dipinjam</span>' : '<span class="badge badge-success">Dikembalikan</span>'}
                    </td>
                    <td>
                        <div class="sp-actions">
                            <button class="sp-btn-icon" title="Cetak Surat" onclick="window.open('${this.state.apiUrl}manage.php?entity=peminjaman&action=print&id=${d.id}&token=${this.state.token}', '_blank')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </button>
                            ${d.status === 'Dipinjam' ? `
                                <button class="sp-btn-icon" title="Pengembalian" onclick="Sarpras.formPengembalian(${d.id})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 10 4 15 9 20"/><path d="M20 4v7a4 4 0 0 1-4 4H4"/></svg>
                                </button>
                            ` : ''}
                            ${this.hasPermission('peminjaman_manage') ? `
                            <button class="sp-btn-icon danger" title="Hapus" onclick="Sarpras.delPeminjaman(${d.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2 2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
            
            $('#peminjamanTable').html(`
                <table class="sp-table">
                    <thead><tr><th>Peminjam</th><th>Aset Dipinjam</th><th style="text-align:center">Tanggal</th><th style="text-align:center">Qty</th><th style="text-align:center">Status</th><th>Aksi</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        });
    },

    formPeminjaman() {
        const today = new Date().toISOString().split('T')[0];
        const loadingId = EModal.loading('Memuat data referensi...');

        $.when(
            this.api('ruang.php?action=list'),
            this.api('pj.php?action=guru_list')
        ).done((resRuang, resGuru) => {
            EModal.close(loadingId);
            
            const ruangList = (resRuang[0].data || []);
            const guruList = (resGuru[0].data || []);

            const ruangOpts = ruangList.map(r => `<option value="${r.id}">${r.nama} (${r.bangunan_nama})</option>`).join('');


            const guruCustomOpts = guruList.map(g => `
                <div class="sp-cs-option" data-id="${g.id}" data-nama="${g.nama_lengkap}" data-jabatan="${g.jabatan || ''}">
                    <div class="cs-opt-m">${g.nama_lengkap}</div>
                    <div class="cs-opt-k">${g.jabatan || 'Guru/Pegawai'}</div>
                </div>
            `).join('');

            EModal.form({
                title: 'Peminjaman Barang Baru',
                size: 'lg',
                form: `
                    <style>
                        .sp-pinjam-form .sp-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
                        @media (max-width: 768px) { .sp-pinjam-form .sp-form-grid-2 { grid-template-columns: 1fr !important; } }
                        
                        /* Custom Searchable Select Styling */
                        .sp-cs-container { position:relative; user-select:none; margin-bottom: 5px; }
                        .sp-cs-btn { 
                            cursor:pointer; display:flex; justify-content:space-between; align-items:center; 
                            background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:10px 15px;
                            height:48px; transition: all 0.2s;
                        }
                        .sp-cs-btn:hover { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
                        .sp-cs-btn.active { border-color: #3b82f6; }
                        .sp-cs-dropdown { 
                            display:none; position:absolute; top:calc(100% + 5px); left:0; right:0; 
                            background:#fff; border:1px solid #cbd5e1; border-radius:10px; 
                            box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:9999; overflow:hidden;
                        }
                        .sp-cs-option { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
                        .sp-cs-option:hover { background: #f1f5f9; }
                        .cs-opt-m { font-weight: 600; color: #1e293b; font-size: 0.9rem; line-height: 1.2; }
                        .cs-opt-k { font-size: 0.75rem; color: #64748b; margin-top: 3px; }
                        .cs-search-box { padding:10px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
                        .cs-search-box input { width:100%; padding:8px 12px; border-radius:8px; border:1px solid #cbd5e1; outline:none; font-size:13px; }
                        .cs-options-list { max-height:220px; overflow-y:auto; }
                    </style>
                    <div class="sp-pinjam-form">
                        <input type="hidden" id="f_pjSarprasId" value="">
                        <input type="hidden" id="f_pjGuruId" value="">
                        
                        <div class="form-group">
                            <label>Pilih Ruangan (Lokasi Barang) <span style="color:var(--danger)">*</span></label>
                            <select class="form-select" id="f_pjRuangId">
                                <option value="">-- Pilih Ruangan --</option>
                                <option value="0">Gudang / Tanpa Ruang</option>
                                ${ruangOpts}
                            </select>
                        </div>

                        <div class="form-group" id="pjItemWrapper" style="display:none">
                            <label>Pilih Barang / Sarpras <span style="color:var(--danger)">*</span></label>
                            <div class="sp-cs-container" id="csPjSarprasContainer">
                                <div class="sp-cs-btn" id="csPjSarprasBtn">
                                    <span id="csPjSarprasText" style="color:#64748b;">-- Cari barang yang dipinjam --</span>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </div>
                                <div class="sp-cs-dropdown" id="csPjSarprasDropdown">
                                    <div class="cs-search-box"><input type="text" id="csPjSarprasSearch" placeholder="Ketik nama barang..." autocomplete="off"></div>
                                    <div class="cs-options-list" id="pjItemOptions"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Peminjam (Pilih dari Data Guru/Pegawai)</label>
                            <div class="sp-cs-container" id="csPjGuruContainer">
                                <div class="sp-cs-btn" id="csPjGuruBtn">
                                    <span id="csPjGuruText" style="color:#64748b;">-- Cari nama guru/pegawai --</span>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </div>
                                <div class="sp-cs-dropdown" id="csPjGuruDropdown">
                                    <div class="cs-search-box"><input type="text" id="csPjGuruSearch" placeholder="Ketik nama guru..." autocomplete="off"></div>
                                    <div class="cs-options-list">
                                        <div class="sp-cs-option" data-id="" data-nama="" data-jabatan="">
                                            <div class="cs-opt-m">-- Input Manual --</div>
                                            <div class="cs-opt-k">Gunakan jika peminjam tidak ada di daftar</div>
                                        </div>
                                        ${guruCustomOpts}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sp-form-grid-2">
                            <div class="form-group"><label>Nama Lengkap <span style="color:var(--danger)">*</span></label><input class="form-input" id="f_pjNama" required placeholder="Nama peminjam"></div>
                            <div class="form-group"><label>Jabatan</label><input class="form-input" id="f_pjJabatan" placeholder="Jabatan peminjam"></div>
                        </div>
                        <div class="sp-form-grid-2">
                            <div class="form-group"><label>No. HP / WA</label><input class="form-input" id="f_pjHp" placeholder="08xxx"></div>
                            <div class="form-group"><label>Asal Unit / Bagian</label><input class="form-input" id="f_pjUnit" placeholder="Cth: Kurikulum"></div>
                        </div>
                        <div class="sp-form-grid-2">
                            <div class="form-group"><label>Jumlah Dipinjam <span style="color:var(--danger)">*</span></label><input type="number" class="form-input" id="f_pjJumlah" value="1" min="1" required></div>
                            <div class="form-group"><label>Keperluan / Nama Kegiatan</label><input class="form-input" id="f_pjKegiatan" placeholder="Cth: Rapat OSIS"></div>
                        </div>
                        <div class="sp-form-grid-2">
                            <div class="form-group"><label>Tanggal Pinjam <span style="color:var(--danger)">*</span></label><input type="date" class="form-input" id="f_pjTglPinjam" value="${today}" required></div>
                            <div class="form-group"><label>Rencana Kembali</label><input type="date" class="form-input" id="f_pjTglKembali"></div>
                        </div>
                        <div class="form-group">
                            <label>Catatan Kondisi Barang (Sebelum Dipinjam)</label>
                            <textarea class="form-input" id="f_pjKondisi" rows="2" placeholder="Cth: Kondisi baik, lensa bersih"></textarea>
                        </div>
                    </div>
                `,
                onOpen: () => {
                    // Logic for Room Selection
                    $('#f_pjRuangId').on('change', () => {
                        const rid = $('#f_pjRuangId').val();
                        if (rid === '') {
                            $('#pjItemWrapper').hide();
                            return;
                        }
                        
                        // Fetch items in this room, excluding books
                        $('#csPjSarprasText').text('Memuat barang...').css('color', '#64748b');
                        $('#f_pjSarprasId').val('');
                        
                        this.api(`sarpras.php?action=list&ruang_id=${rid}&exclude_grup=buku&per_page=500`).done(res => {
                            const items = res.data.data || [];
                            if (items.length === 0) {
                                $('#pjItemOptions').html('<div style="padding:15px; text-align:center; color:var(--danger);">Tidak ada barang yang bisa dipinjam di ruangan ini.</div>');
                            } else {
                                const opts = items.map(s => `
                                    <div class="sp-cs-option" data-id="${s.id}" data-nama="${s.nama}" data-kode="${s.kode_inventaris}" data-stok="${s.jumlah}">
                                        <div class="cs-opt-m">${s.nama}</div>
                                        <div class="cs-opt-k">${s.kode_inventaris} — Tersedia: ${s.jumlah}</div>
                                    </div>
                                `).join('');
                                $('#pjItemOptions').html(opts);
                            }
                            $('#csPjSarprasText').text('-- Cari barang yang dipinjam --');
                            $('#pjItemWrapper').show();
                        });
                    });

                    // Logic for Sarpras Dropdown
                    $('#csPjSarprasBtn').on('click', function(e) {
                        e.stopPropagation();
                        $(this).toggleClass('active');
                        $('#csPjSarprasDropdown').toggle();
                        if ($('#csPjSarprasDropdown').is(':visible')) $('#csPjSarprasSearch').val('').trigger('input').focus();
                    });

                    $('#csPjSarprasSearch').on('input', function() {
                        const term = $(this).val().toLowerCase();
                        $('#csPjSarprasDropdown .sp-cs-option').each(function() {
                            $(this).toggle($(this).text().toLowerCase().includes(term));
                        });
                    });

                    $('#csPjSarprasDropdown').on('click', '.sp-cs-option', function() {
                        const d = $(this).data();
                        if (parseInt(d.stok) <= 0) {
                            EModal.toast({type:'error', title:'Stok Habis', message:'Barang ini tidak memiliki stok tersedia di ruangan ini.'});
                            return;
                        }
                        $('#f_pjSarprasId').val(d.id);
                        $('#f_pjJumlah').attr('max', d.stok);
                        $('#csPjSarprasText').html(`<div style="font-weight:600; color:#1e293b;">${d.nama}</div><div style="font-size:0.7rem; color:#64748b;">${d.kode} — Tersedia: ${d.stok}</div>`);
                        $('#csPjSarprasDropdown').hide();
                        $('#csPjSarprasBtn').removeClass('active');
                    });

                    // Logic for Guru Dropdown
                    $('#csPjGuruBtn').on('click', function(e) {
                        e.stopPropagation();
                        $(this).toggleClass('active');
                        $('#csPjGuruDropdown').toggle();
                        if ($('#csPjGuruDropdown').is(':visible')) $('#csPjGuruSearch').val('').trigger('input').focus();
                    });

                    $('#csPjGuruSearch').on('input', function() {
                        const term = $(this).val().toLowerCase();
                        $('#csPjGuruDropdown .sp-cs-option').each(function() {
                            $(this).toggle($(this).text().toLowerCase().includes(term));
                        });
                    });

                    $('#csPjGuruDropdown').on('click', '.sp-cs-option', function() {
                        const d = $(this).data();
                        $('#f_pjGuruId').val(d.id);
                        if (d.id) {
                            $('#f_pjNama').val(d.nama).prop('readonly', true);
                            $('#f_pjJabatan').val(d.jabatan);
                            $('#csPjGuruText').html(`<div style="font-weight:600; color:#1e293b;">${d.nama}</div><div style="font-size:0.7rem; color:#64748b;">${d.jabatan || 'Guru/Pegawai'}</div>`);
                        } else {
                            $('#f_pjNama').val('').prop('readonly', false);
                            $('#f_pjJabatan').val('');
                            $('#csPjGuruText').text('-- Pilih dari daftar atau input manual --').css('color', '#64748b');
                        }
                        $('#csPjGuruDropdown').hide();
                        $('#csPjGuruBtn').removeClass('active');
                    });

                    // Close dropdowns on outside click
                    $(document).on('click.csPjDropdown', function(e) {
                        if (!$(e.target).closest('.sp-cs-container').length) {
                            $('.sp-cs-dropdown').hide();
                            $('.sp-cs-btn').removeClass('active');
                        }
                    });
                },
                onConfirm: () => {
                    const sarprasId = $('#f_pjSarprasId').val();
                    const nama = $('#f_pjNama').val().trim();
                    if (!sarprasId) { EModal.toast({type:'error', title:'Error', message:'Pilih barang yang dipinjam'}); return false; }
                    if (!nama) { EModal.toast({type:'error', title:'Error', message:'Nama peminjam wajib diisi'}); return false; }

                    const payload = {
                        user_id: $('#f_pjGuruId').val() || null,
                        nama_peminjam: nama,
                        jabatan: $('#f_pjJabatan').val(),
                        no_hp: $('#f_pjHp').val(),
                        asal_unit: $('#f_pjUnit').val(),
                        nama_kegiatan: $('#f_pjKegiatan').val(),
                        sarpras_id: sarprasId,
                        jumlah: $('#f_pjJumlah').val() || 1,
                        kondisi_sebelum: $('#f_pjKondisi').val(),
                        tanggal_pinjam: $('#f_pjTglPinjam').val() || today,
                        tanggal_kembali_rencana: $('#f_pjTglKembali').val() || null
                    };

                    this.api('manage.php?entity=peminjaman&action=create', { method: 'POST', data: payload }).done(res => {
                        EModal.closeAll();
                        $(document).off('click.csPjDropdown'); // Cleanup listener
                        EModal.toast({ type: 'success', title: 'Berhasil', message: 'Data peminjaman berhasil dicatat' });
                        this.fetchPeminjaman();
                        window.open(`${this.state.apiUrl}manage.php?entity=peminjaman&action=print&id=${res.data.id}&token=${this.state.token}`, '_blank');
                    }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message || 'Gagal menyimpan data.'}));
                    return false;
                }
            });
        }).fail(() => {
            EModal.close(loadingId);
            EModal.toast({type:'error', title:'Error', message:'Gagal mengambil data dari server.'});
        });
    },

    formPengembalian(id) {
        const today = new Date().toISOString().split('T')[0];
        EModal.form({
            title: 'Proses Pengembalian Barang',
            form: `
                <div class="form-group">
                    <label>Tanggal Dikembalikan <span style="color:var(--danger)">*</span></label>
                    <input type="date" class="form-input" id="f_retTgl" value="${today}" required>
                </div>
                <div class="form-group">
                    <label>Kondisi Saat Dikembalikan</label>
                    <textarea class="form-input" id="f_retKondisi" rows="2" placeholder="Cth: Kondisi baik, lensa bersih..."></textarea>
                </div>
                <div class="form-group">
                    <label>Catatan Tambahan (Opsional)</label>
                    <textarea class="form-input" id="f_retCatatan" rows="1"></textarea>
                </div>
            `,
            onConfirm: () => {
                const payload = {
                    id: id,
                    tanggal_kembali_aktual: $('#f_retTgl').val() || today,
                    kondisi_sesudah: $('#f_retKondisi').val(),
                    catatan: $('#f_retCatatan').val()
                };
                this.api('manage.php?entity=peminjaman&action=update', { method: 'POST', data: payload }).done(() => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: 'Barang telah dikembalikan' });
                    this.fetchPeminjaman();
                    window.open(`${this.state.apiUrl}manage.php?entity=peminjaman&action=print&id=${id}&token=${this.state.token}`, '_blank');
                }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message}));
                return false;
            }
        });
    },

    delPeminjaman(id) {
        EModal.confirm({
            title: 'Hapus Data Riwayat',
            message: 'Yakin menghapus riwayat peminjaman ini secara permanen? Aksi ini tidak dapat dibatalkan.',
            type: 'danger',
            onConfirm: () => {
                this.api('manage.php?entity=peminjaman&action=delete', { method: 'POST', data: { id } }).done(() => {
                    EModal.toast({ type: 'info', title: 'Dihapus', message: 'Riwayat peminjaman berhasil dihapus' });
                    this.fetchPeminjaman();
                }).fail(xhr => EModal.toast({type:'error', title:'Gagal', message: xhr.responseJSON?.message}));
            }
        });
    },
};


// Initialize on Load
$(() => {
    Sarpras.init();
});
