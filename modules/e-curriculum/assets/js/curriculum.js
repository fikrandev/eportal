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
        this.initRealtimeSync();

        // Update profile in sidebar
        if (this.state.user) {
            $('#sidebarAvatar').text(this.getInitials(this.state.user.nama_lengkap));
            $('#sidebarUserName').text(this.state.user.nama_lengkap);
            $('#sidebarUserRole').text(this.state.user.custom_role_nama || (this.state.user.role === 'superadmin' ? 'Super Admin' : (this.state.user.role === 'guru' ? 'Guru' : this.state.user.role)));
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

    // ==================== REALTIME SYNC ENGINE ====================
    _realtimeTimer: null,
    _lastRealtimeSignatures: {},

    initRealtimeSync() {
        if (this._realtimeTimer) clearInterval(this._realtimeTimer);

        // Render visual badge in header
        this.renderRealtimeBadge();

        // Background polling every 4 seconds
        this._realtimeTimer = setInterval(() => {
            if (document.hidden) return; // Tab in background, skip
            // If user is currently editing or interacting with an open modal, don't interrupt
            if ($('.emodal-overlay:visible, .acad-modal:visible, .modal.show:visible').length > 0) return;
            if ($('input:focus, textarea:focus, select:focus').length > 0) return;

            this.syncCurrentRouteData(true);
        }, 4000);
    },

    renderRealtimeBadge() {
        // Realtime sync operates silently in background without topbar badge as requested
    },

    flashRealtimeSync() {
        // No-op (badge is hidden)
    },

    syncCurrentRouteData(silent = true) {
        if (!silent) {
            this.flashRealtimeSync();
            this.toast('Sinkronisasi', 'Memperbarui data terbaru...', 'info');
        }

        const route = this.state.currentRoute;
        switch (route) {
            case 'dokumen':
                this.syncRealtimeDokumen(silent);
                break;
            case 'dashboard':
                this.syncRealtimeDashboard(silent);
                break;
            case 'users':
                this.syncRealtimeUsers(silent);
                break;
            case 'kelas':
                this.syncRealtimeKelas(silent);
                break;
            case 'mapel':
                this.syncRealtimeMapel(silent);
                break;
            case 'sch_jam':
                this.syncRealtimeSchJam(silent);
                break;
            case 'sch_mapel':
                this.syncRealtimeSchMapel(silent);
                break;
            case 'sch_kelas':
                this.syncRealtimeSchKelas(silent);
                break;
            case 'sch_guru':
                this.syncRealtimeSchGuru(silent);
                break;
            case 'sch_distribusi':
                this.syncRealtimeSchDistribusi(silent);
                break;
            case 'sch_kesediaan':
                this.syncRealtimeSchKesediaan(silent);
                break;
            case 'sch_jadwal':
                this.syncRealtimeSchJadwal(silent);
                break;
            case 'mengajar':
                this.syncRealtimeMengajar(silent);
                break;
            case 'jurnal':
                this.syncRealtimeJurnal(silent);
                break;
            case 'absensi':
                this.syncRealtimeAbsensi(silent);
                break;
            case 'absensi_guru':
                this.syncRealtimeAbsensiGuru(silent);
                break;
            case 'ketidakhadiran':
                this.syncRealtimeKetidakhadiran(silent);
                break;
            case 'piket':
                this.syncRealtimePiket(silent);
                break;
            case 'buku_penghubung':
                this.syncRealtimeBukuPenghubung(silent);
                break;
        }
    },

    syncRealtimeDokumen(silent = true) {
        this.api('documents.php?action=list').done(res => {
            if (!res.success) return;
            const list = Array.isArray(res.data) ? res.data : [];
            const sig = JSON.stringify(list.map(d => `${d.id}_${d.status}_${d.updated_at || d.created_at}`));
            
            const prevSig = this._lastRealtimeSignatures['dokumen'];
            this._lastRealtimeSignatures['dokumen'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();

                // If admin view
                if (this._dokumenAdminCache !== undefined) {
                    const oldCache = this._dokumenAdminCache || [];
                    const oldIds = oldCache.map(d => d.id);
                    const newDocs = list.filter(d => !oldIds.includes(d.id));

                    if (newDocs.length > 0) {
                        const first = newDocs[0];
                        this.toast('Dokumen Baru Masuk! 🔔', `${first.nama_guru || 'Guru'} mengunggah: "${first.judul}"`, 'info');
                    }

                    this._dokumenAdminCache = list;
                    this._renderDokumenAdminTable();
                } else if (this._dokumenGuruCache !== undefined) {
                    const oldCache = this._dokumenGuruCache || [];
                    const oldMap = {};
                    oldCache.forEach(d => { oldMap[d.id] = d.status; });

                    list.forEach(d => {
                        if (oldMap[d.id] && oldMap[d.id] !== d.status) {
                            if (d.status === 'approved') {
                                this.toast('Disetujui! ✅', `Dokumen "${d.judul}" telah disetujui oleh Kurikulum.`, 'success');
                            } else if (d.status === 'rejected') {
                                this.toast('Ditolak / Revisi ⚠️', `Dokumen "${d.judul}" ditolak: ${d.catatan_admin || 'Silakan cek catatan.'}`, 'warning');
                            }
                        }
                    });

                    this._dokumenGuruCache = list;
                    this._renderDokumenGuruCards();
                }
            } else if (!prevSig) {
                if (this._dokumenAdminCache !== undefined) {
                    this._dokumenAdminCache = list;
                } else if (this._dokumenGuruCache !== undefined) {
                    this._dokumenGuruCache = list;
                }
            }
        });
    },

    syncRealtimeDashboard(silent = true) {
        this.api('dashboard.php?action=stats').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify(res.data);
            const prevSig = this._lastRealtimeSignatures['dashboard'];
            this._lastRealtimeSignatures['dashboard'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                const d = res.data;
                const $stats = $('#dashboardStats');
                if ($stats.length) {
                    $stats.find('.acad-stat-card:eq(0) h4').text(d.total_guru || 0);
                    $stats.find('.acad-stat-card:eq(1) h4').text(d.total_kelas || 0);
                    $stats.find('.acad-stat-card:eq(2) h4').text(d.total_mapel || 0);
                    $stats.find('.acad-stat-card:eq(3) h4').text(d.jurnal_hari_ini || 0);
                }
            }
        });
    },

    syncRealtimeUsers(silent = true) {
        if (!$('#rolesDefTable').length && !$('#accountsTable').length) return;
        this.api('users.php?action=list_roles').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(r => `${r.id}_${r.nama}_${r.permissions}`));
            const prevSig = this._lastRealtimeSignatures['users_roles'];
            this._lastRealtimeSignatures['users_roles'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this._loadRolesDef === 'function') this._loadRolesDef();
            }
        });
        this.api('users.php?action=accounts_list').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(a => `${a.id}_${a.custom_role_id}`));
            const prevSig = this._lastRealtimeSignatures['users_accounts'];
            this._lastRealtimeSignatures['users_accounts'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this._loadAccounts === 'function') this._loadAccounts();
            }
        });
    },

    syncRealtimeKelas(silent = true) {
        if (!$('#kelasTableWrapper').length) return;
        this.api('kelas.php?action=list').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(k => `${k.id}_${k.nama_kelas}_${k.wali_id}`));
            const prevSig = this._lastRealtimeSignatures['kelas'];
            this._lastRealtimeSignatures['kelas'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadKelasTable === 'function') this.loadKelasTable();
            }
        });
    },

    syncRealtimeMapel(silent = true) {
        if (!$('#mapelTableWrapper').length) return;
        this.api('mapel.php?action=list').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(m => `${m.id}_${m.nama_mapel}`));
            const prevSig = this._lastRealtimeSignatures['mapel'];
            this._lastRealtimeSignatures['mapel'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadMapelTable === 'function') this.loadMapelTable();
            }
        });
    },

    syncRealtimeSchJam(silent = true) {
        if (!$('#jamTable').length) return;
        this.api('sch_jam.php?action=list').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(j => `${j.id}_${j.hari}_${j.jam_ke}_${j.nama_jam}`));
            const prevSig = this._lastRealtimeSignatures['sch_jam'];
            this._lastRealtimeSignatures['sch_jam'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadSchJam === 'function') this.loadSchJam();
            }
        });
    },

    syncRealtimeSchMapel(silent = true) {
        if (!$('#schMapelTable').length) return;
        this.api('sch_mapel.php?action=list').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(m => `${m.id}_${m.nama_mapel}`));
            const prevSig = this._lastRealtimeSignatures['sch_mapel'];
            this._lastRealtimeSignatures['sch_mapel'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadSchMapel === 'function') this.loadSchMapel();
            }
        });
    },

    syncRealtimeSchKelas(silent = true) {
        if (!$('#schKelasTable').length) return;
        this.api('sch_kelas.php?action=list').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(k => `${k.id}_${k.nama_kelas}`));
            const prevSig = this._lastRealtimeSignatures['sch_kelas'];
            this._lastRealtimeSignatures['sch_kelas'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadSchKelas === 'function') this.loadSchKelas();
            }
        });
    },

    syncRealtimeSchGuru(silent = true) {
        if (!$('#schGuruTable').length) return;
        this.api('sch_guru.php?action=list').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(g => `${g.id}_${g.nama_guru}`));
            const prevSig = this._lastRealtimeSignatures['sch_guru'];
            this._lastRealtimeSignatures['sch_guru'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadSchGuru === 'function') this.loadSchGuru();
            }
        });
    },

    syncRealtimeSchDistribusi(silent = true) {
        if (!$('#distTable').length) return;
        const kelasId = $('#distFilterKelas').val() || '';
        this.api(`sch_distribusi.php?action=list${kelasId ? `&kelas_id=${kelasId}` : ''}`).done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(d => `${d.id}_${d.guru_id}_${d.mapel_id}_${d.jp}`));
            const prevSig = this._lastRealtimeSignatures['sch_distribusi'];
            this._lastRealtimeSignatures['sch_distribusi'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadSchDistribusi === 'function') this.loadSchDistribusi();
            }
        });
    },

    syncRealtimeSchKesediaan(silent = true) {
        if (!$('#kesediaanTable').length) return;
        this.api('sch_kesediaan.php?action=list').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(k => `${k.guru_id}_${k.hari}_${k.status}`));
            const prevSig = this._lastRealtimeSignatures['sch_kesediaan'];
            this._lastRealtimeSignatures['sch_kesediaan'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadSchKesediaan === 'function') this.loadSchKesediaan();
            }
        });
    },

    syncRealtimeSchJadwal(silent = true) {
        if (!$('#jdwViewer').length) return;
        const kelasId = $('#jdwFilter').val() || '';
        this.api(`sch_jadwal.php?action=list${kelasId ? `&kelas_id=${kelasId}` : ''}`).done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(j => `${j.id}_${j.hari}_${j.jam_ke}`));
            const prevSig = this._lastRealtimeSignatures['sch_jadwal'];
            this._lastRealtimeSignatures['sch_jadwal'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.viewJadwal === 'function') this.viewJadwal(kelasId);
            }
        });
    },

    syncRealtimeMengajar(silent = true) {
        if (!$('#mengajarTableWrapper').length) return;
        this.api('mengajar.php?action=list').done(res => {
            if (!res.success) return;
            const sig = JSON.stringify((res.data || []).map(m => `${m.id}_${m.updated_at || m.created_at || ''}`));
            const prevSig = this._lastRealtimeSignatures['mengajar'];
            this._lastRealtimeSignatures['mengajar'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadMengajarTable === 'function') this.loadMengajarTable();
            }
        });
    },

    syncRealtimeJurnal(silent = true) {
        const tanggal = $('#jurnalTanggal').val() || new Date().toISOString().split('T')[0];
        this.api(`jurnal.php?action=list&tanggal=${tanggal}`).done(res => {
            if (!res.success) return;
            const data = res.data || [];
            const sig = JSON.stringify(data.map(j => `${j.id}_${j.updated_at || j.created_at || ''}`));
            const prevSig = this._lastRealtimeSignatures['jurnal'];
            this._lastRealtimeSignatures['jurnal'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadJurnalTable === 'function') this.loadJurnalTable();
            }
        });
    },

    syncRealtimeAbsensi(silent = true) {
        if (!$('#absensiTableWrapper').length) return;
        const tanggal = $('#absensiTanggal').val() || new Date().toISOString().split('T')[0];
        const kelasId = $('#absensiKelas').val() || '';
        this.api(`absensi.php?action=list_by_date&tanggal=${tanggal}&kelas_id=${kelasId}`).done(res => {
            if (!res.success) return;
            const data = res.data || [];
            const sig = JSON.stringify(data.map(a => `${a.id}_${a.status}`));
            const prevSig = this._lastRealtimeSignatures['absensi'];
            this._lastRealtimeSignatures['absensi'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadAbsensiTable === 'function') this.loadAbsensiTable();
            }
        });
    },

    syncRealtimeAbsensiGuru(silent = true) {
        const tanggal = $('#absensiGuruTanggal').val() || new Date().toISOString().split('T')[0];
        if (!$('#absensiGuruTableWrapper').length) return;

        this.api(`absensi_guru.php?action=list&tanggal=${tanggal}`).done(res => {
            if (!res.success) return;
            const list = res.data || [];
            const sig = JSON.stringify((list.teachers || list || []).map(a => `${a.guru_id || a.id}_${a.status}`));
            const prevSig = this._lastRealtimeSignatures['absensi_guru'];
            this._lastRealtimeSignatures['absensi_guru'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadAbsensiGuruTable === 'function') {
                    this.loadAbsensiGuruTable();
                }
            }
        });
    },

    syncRealtimeKetidakhadiran(silent = true) {
        if (!$('#ketidakhadiranTableWrapper').length) return;
        this.api('ketidakhadiran.php?action=list').done(res => {
            if (!res.success) return;
            const list = res.data || [];
            const sig = JSON.stringify(list.map(k => `${k.id}_${k.status}`));
            const prevSig = this._lastRealtimeSignatures['ketidakhadiran'];
            this._lastRealtimeSignatures['ketidakhadiran'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.renderKetidakhadiranTable === 'function') {
                    this.renderKetidakhadiranTable(list);
                } else if (typeof this.loadKetidakhadiranTable === 'function') {
                    this.loadKetidakhadiranTable();
                }
            }
        });
    },

    syncRealtimePiket(silent = true) {
        if (!$('#piketTableWrapper').length) return;
        const tanggal = $('#piketTanggal').val() || new Date().toISOString().split('T')[0];
        this.api(`piket.php?action=list&tanggal=${tanggal}`).done(res => {
            if (!res.success) return;
            const list = res.data || [];
            const sig = JSON.stringify(list.map(p => `${p.id}_${p.status || ''}`));
            const prevSig = this._lastRealtimeSignatures['piket'];
            this._lastRealtimeSignatures['piket'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.renderPiketTable === 'function') {
                    this.renderPiketTable(list);
                } else if (typeof this.loadPiketTable === 'function') {
                    this.loadPiketTable();
                }
            }
        });
    },

    syncRealtimeBukuPenghubung(silent = true) {
        if (!$('#bukuPenghubungTableWrapper').length) return;
        this.api('buku_penghubung.php?action=list').done(res => {
            if (!res.success) return;
            const list = res.data || [];
            const sig = JSON.stringify(list.map(b => `${b.id}_${b.updated_at || b.created_at || ''}`));
            const prevSig = this._lastRealtimeSignatures['buku_penghubung'];
            this._lastRealtimeSignatures['buku_penghubung'] = sig;

            if (prevSig && prevSig !== sig) {
                this.flashRealtimeSync();
                if (typeof this.loadBukuPenghubungTable === 'function') {
                    this.loadBukuPenghubungTable();
                }
            }
        });
    },

    can(permission) {
        const u = this.state.user;
        if (!u) return false;
        if (u.role === 'superadmin') return true;
        const perms = Array.isArray(u.permissions) ? u.permissions : [];
        return perms.includes(permission);
    },

    canAccessRoute(route) {
        const u = this.state.user || {};
        if (u.role === 'superadmin') return true;
        const hasCustomPerms = Array.isArray(u.permissions) && u.permissions.length > 0;
        const isTeacher = u.role === 'guru';

        switch (route) {
            case 'dashboard':
                return this.can('dashboard_view') || (!hasCustomPerms && isTeacher);
            case 'sch_jam':
            case 'sch_mapel':
            case 'sch_kelas':
            case 'sch_guru':
            case 'sch_distribusi':
            case 'sch_kesediaan':
            case 'sch_jadwal':
            case 'kelas':
            case 'mapel':
                return this.can('jadwal_manage');
            case 'jurnal':
                return this.can('jurnal_manage');
            case 'absensi':
                return this.can('absensi_manage');
            case 'absensi_guru':
                return this.can('absensi_guru_manage');
            case 'ketidakhadiran':
                return this.can('ketidakhadiran_manage');
            case 'piket':
                return this.can('piket_manage');
            case 'buku_penghubung':
                return this.can('buku_penghubung_manage') || (!hasCustomPerms && isTeacher);
            case 'laporan_jurnal':
            case 'laporan_kehadiran':
                return this.can('laporan_view');
            case 'dokumen':
                return this.can('dokumen_manage') || (!hasCustomPerms && isTeacher);
            case 'users':
                return this.can('roles_manage');
            case 'mengajar':
                return !hasCustomPerms && isTeacher;
            default:
                return false;
        }
    },

    getDefaultRoute() {
        const u = this.state.user || {};
        if (u.role === 'superadmin') return 'dashboard';
        
        if (this.can('dashboard_view')) return 'dashboard';
        if (this.can('buku_penghubung_manage')) return 'buku_penghubung';
        if (this.can('jurnal_manage')) return 'jurnal';
        if (this.can('absensi_manage')) return 'absensi';
        if (this.can('absensi_guru_manage')) return 'absensi_guru';
        if (this.can('piket_manage')) return 'piket';
        if (this.can('ketidakhadiran_manage')) return 'ketidakhadiran';
        if (this.can('jadwal_manage')) return 'sch_jadwal';
        if (this.can('laporan_view')) return 'laporan_jurnal';
        if (this.can('dokumen_manage')) return 'dokumen';
        if (this.can('roles_manage')) return 'users';
        
        const hasCustomPerms = Array.isArray(u.permissions) && u.permissions.length > 0;
        if (u.role === 'guru' && !hasCustomPerms) {
            return 'dashboard';
        }
        
        return 'dashboard';
    },

    /**
     * Sidebar Management
     */
    renderSidebar() {
        const u = this.state.user || {};
        const isSuperAdmin = u.role === 'superadmin';
        const hasCustomPerms = Array.isArray(u.permissions) && u.permissions.length > 0;
        const isTeacher = u.role === 'guru';
        
        let navHtml = '';

        // 0. MENU UTAMA (Dashboard)
        const showDashboard = isSuperAdmin || this.can('dashboard_view') || (!hasCustomPerms && isTeacher);
        if (showDashboard) {
            navHtml += `
                <div class="acad-nav-group">
                    <div class="acad-nav-label">Menu Utama</div>
                    <button class="acad-nav-item" data-route="dashboard">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Dashboard
                    </button>
                </div>
            `;
        }

        // 1. DATA JADWAL (Admin Kurikulum / Permissions jadwal_manage)
        if (isSuperAdmin || this.can('jadwal_manage')) {
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
            `;
        }

        // 2. LAYANAN AKADEMIK & KURIKULUM (Sesuai Izin Role)
        const showJurnal = isSuperAdmin || this.can('jurnal_manage');
        const showAbsenSiswa = isSuperAdmin || this.can('absensi_manage');
        const showAbsenGuru = isSuperAdmin || this.can('absensi_guru_manage');
        const showKetidakhadiran = isSuperAdmin || this.can('ketidakhadiran_manage');
        const showPiket = isSuperAdmin || this.can('piket_manage');
        const showBukuPenghubung = isSuperAdmin || this.can('buku_penghubung_manage') || (!hasCustomPerms && isTeacher);
        const showDokumen = isSuperAdmin || this.can('dokumen_manage');

        if (showJurnal || showAbsenSiswa || showAbsenGuru || showKetidakhadiran || showPiket || showBukuPenghubung || showDokumen) {
            navHtml += `<div class="acad-nav-group"><div class="acad-nav-label">Akademik &amp; Layanan</div>`;
            if (showJurnal) {
                navHtml += `
                    <button class="acad-nav-item" data-route="jurnal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Jurnal Mengajar
                    </button>
                `;
            }
            if (showAbsenSiswa) {
                navHtml += `
                    <button class="acad-nav-item" data-route="absensi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Absensi Siswa
                    </button>
                `;
            }
            if (showAbsenGuru) {
                navHtml += `
                    <button class="acad-nav-item" data-route="absensi_guru">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Absensi Guru
                    </button>
                `;
            }
            if (showKetidakhadiran) {
                navHtml += `
                    <button class="acad-nav-item" data-route="ketidakhadiran">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        Ketidakhadiran
                    </button>
                `;
            }
            if (showPiket) {
                navHtml += `
                    <button class="acad-nav-item" data-route="piket">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Piket Guru
                    </button>
                `;
            }
            if (showBukuPenghubung) {
                navHtml += `
                    <button class="acad-nav-item" data-route="buku_penghubung">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Buku Penghubung
                    </button>
                `;
            }
            if (showDokumen) {
                navHtml += `
                    <button class="acad-nav-item" data-route="dokumen">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Kelola Perangkat
                    </button>
                `;
            }
            navHtml += `</div>`;
        }

        // 3. LAPORAN (Laporan Jurnal & Kehadiran)
        if (isSuperAdmin || this.can('laporan_view')) {
            navHtml += `
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
        }

        // 4. PENGATURAN MODUL (Role Access)
        const showRoles = isSuperAdmin || this.can('roles_manage');
        if (showRoles) {
            navHtml += `
                <div class="acad-nav-group">
                    <div class="acad-nav-label">Pengaturan Modul</div>
                    <button class="acad-nav-item" data-route="users">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Akses Modul
                    </button>
                </div>
            `;
        }

        // 5. KHUSUS GURU PENGAJAR (Akademik Saya) — hanya untuk guru reguler yang tidak memiliki custom role RBAC
        if (isTeacher && !isSuperAdmin && !hasCustomPerms) {
            navHtml += `
                <div class="acad-nav-group">
                    <div class="acad-nav-label">Akademik Saya</div>
                    <button class="acad-nav-item" data-route="mengajar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Jadwal Mengajar
                    </button>
                    <button class="acad-nav-item" data-route="buku_penghubung">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Buku Penghubung
                    </button>
                    <button class="acad-nav-item" data-route="dokumen">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Dokumen Saya
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
        const hash = window.location.hash || '';
        let route = 'dashboard';
        let params = {};
        
        if (hash) {
            const clean = hash.replace(/^#\/?/, '');
            const parts = clean.split('?');
            route = parts[0] || 'dashboard';
            const searchParams = new URLSearchParams(parts[1] || '');
            params = Object.fromEntries(searchParams.entries());
        }

        // Enforce route RBAC permission
        if (!this.canAccessRoute(route)) {
            const defaultRoute = this.getDefaultRoute();
            if (route !== defaultRoute) {
                this.navigate(defaultRoute);
                return;
            }
        }

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
            case 'dokumen':
                $title.text('Kelola Perangkat Pembelajaran');
                this.setBreadcrumbs([{ label: 'Akademik & Layanan' }, { label: 'Kelola Perangkat' }]);
                this.renderDokumen($content);
                break;
            case 'users':
                $title.text('Akses Modul');
                this.setBreadcrumbs([{ label: 'Pengaturan' }, { label: 'Akses Modul' }]);
                this.renderRoles($content);
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
            case 'absensi_guru':
                $title.text('Absensi Guru');
                this.setBreadcrumbs([{ label: 'Absensi Guru' }]);
                this.renderAbsensiGuru($content);
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
                this.navigate(this.getDefaultRoute());
        }
    },

    setBreadcrumbs(crumbs) {
        const $breadcrumb = $('#breadcrumb');
        const defaultRoute = this.getDefaultRoute();
        let html = `<a href="#/${defaultRoute}">E-Curriculum</a>`;
        
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
        const todayStr = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        // Initial skeleton layout
        $container.html(`
            <div class="dash-hero-banner skeleton-module" style="height: 160px; margin-bottom: 22px;"></div>
            <div class="dash-stats-grid">
                <div class="dash-stat-card skeleton-module" style="height: 120px;"></div>
                <div class="dash-stat-card skeleton-module" style="height: 120px;"></div>
                <div class="dash-stat-card skeleton-module" style="height: 120px;"></div>
                <div class="dash-stat-card skeleton-module" style="height: 120px;"></div>
                <div class="dash-stat-card skeleton-module" style="height: 120px;"></div>
                <div class="dash-stat-card skeleton-module" style="height: 120px;"></div>
                <div class="dash-stat-card skeleton-module" style="height: 120px;"></div>
                <div class="dash-stat-card skeleton-module" style="height: 120px;"></div>
            </div>
            <div class="dash-content-grid">
                <div>
                    <div class="dash-card skeleton-module" style="height: 280px; margin-bottom: 20px;"></div>
                    <div class="dash-card skeleton-module" style="height: 280px; margin-bottom: 20px;"></div>
                    <div class="dash-card skeleton-module" style="height: 280px;"></div>
                </div>
                <div>
                    <div class="dash-card skeleton-module" style="height: 220px; margin-bottom: 20px;"></div>
                    <div class="dash-card skeleton-module" style="height: 240px; margin-bottom: 20px;"></div>
                    <div class="dash-card skeleton-module" style="height: 220px;"></div>
                </div>
            </div>
        `);

        this.api('dashboard.php?action=stats').done(res => {
            if (!res.success) {
                $container.html(`<div class="acad-empty" style="color:#ef4444;"><h3>Gagal Memuat Dashboard</h3><p>${this.escapeHtml(res.message || 'Terjadi kesalahan.')}</p></div>`);
                return;
            }

            const data = res.data;
            const summary = data.summary || {};
            const recentJurnals = data.recent_jurnals || [];
            const recentBuku = data.recent_buku || [];
            const guruTidakHadir = data.guru_tidak_hadir || [];
            const piketToday = data.piket_today || [];
            const jadwalToday = data.jadwal_today || [];
            const isFallbackJadwal = data.is_fallback_jadwal || false;
            const topTeachers = data.top_teachers || [];
            const docByType = data.doc_by_type || [];
            const absensiGuru = summary.absensi_guru || { hadir: 0, sakit: 0, izin: 0, alpha: 0, total_recorded: 0 };
            const absensiSiswa = summary.absensi_siswa || { today: { hadir: 0, total: 0, rate: 100 }, month: { hadir: 0, total: 0, rate: 100 } };
            const docStats = summary.dokumen || { total: 0, pending: 0, approved: 0, rejected: 0 };
            const workload = summary.workload || { total_guru_mengajar: 0, total_all_jp: 0, avg_jp_per_guru: 0, max_jp: 0 };

            // Greeting based on time
            const hour = new Date().getHours();
            let greeting = 'Selamat Pagi';
            if (hour >= 11 && hour < 15) greeting = 'Selamat Siang';
            else if (hour >= 15 && hour < 18) greeting = 'Selamat Sore';
            else if (hour >= 18 || hour < 5) greeting = 'Selamat Malam';

            const userName = this.state.user ? this.state.user.nama_lengkap : 'Bapak/Ibu Guru';
            const schoolName = this.state.school ? this.state.school.nama : 'E-Portal';
            const activeYearText = data.academic_year?.tahun_ajaran 
                ? `${data.academic_year.tahun_ajaran} • Semester ${data.academic_year.semester}`
                : 'Tahun Ajaran Aktif';
            const currentDayTitle = isFallbackJadwal ? 'Jadwal Pembelajaran (Pratinjau)' : `Jadwal Pelajaran Hari Ini (${data.today_day_name || 'Aktif'})`;

            // 1. Jurnal Rows HTML
            let jurnalRowsHtml = '';
            if (recentJurnals.length > 0) {
                jurnalRowsHtml = recentJurnals.map(j => {
                    const initials = this.getInitials(j.guru_nama);
                    const jamText = j.jam_ke ? `Jam Ke-${this.escapeHtml(j.jam_ke)}` : 'Pagi';
                    const materiText = j.materi ? (j.materi.length > 70 ? j.materi.substring(0, 70) + '...' : j.materi) : '-';
                    return `
                        <div class="dash-feed-item">
                            <div class="dash-feed-avatar">${this.escapeHtml(initials)}</div>
                            <div class="dash-feed-content">
                                <div class="dash-feed-header">
                                    <div class="dash-feed-title">${this.escapeHtml(j.guru_nama)}</div>
                                    <span class="dash-feed-time">📅 ${this.escapeHtml(j.tanggal)}</span>
                                </div>
                                <div style="display:flex; gap:6px; align-items:center; margin-bottom:4px; flex-wrap:wrap;">
                                    <span class="badge badge-primary" style="font-size:0.75rem; padding:2px 8px;">${this.escapeHtml(j.nama_mapel)}</span>
                                    <span class="badge badge-info" style="font-size:0.75rem; padding:2px 8px;">Kelas ${this.escapeHtml(j.nama_kelas)}</span>
                                    <span class="badge badge-warning" style="font-size:0.75rem; padding:2px 8px;">${jamText}</span>
                                </div>
                                <div class="dash-feed-desc">📖 <strong>Materi:</strong> ${this.escapeHtml(materiText)}</div>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                jurnalRowsHtml = `
                    <div style="text-align:center; padding:30px 16px; color:#94a3b8;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40" style="opacity:0.4; margin-bottom:8px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <p style="margin:0; font-size:0.88rem;">Belum ada jurnal mengajar yang dicatat hari ini.</p>
                        <button class="btn-acad btn-acad-sm btn-acad-primary" onclick="Curriculum.navigate('jurnal')" style="margin-top:12px;">+ Input Jurnal Baru</button>
                    </div>
                `;
            }

            // 2. Schedule List HTML
            let scheduleListHtml = '';
            if (jadwalToday.length > 0) {
                scheduleListHtml = `
                    <div class="dash-schedule-list">
                        ${jadwalToday.map(s => {
                            const jamLabel = s.nama_jam ? `Jam ${this.escapeHtml(s.nama_jam)}` : `Ke-${this.escapeHtml(s.jam_ke)}`;
                            return `
                                <div class="dash-schedule-item">
                                    <div class="dash-schedule-time">
                                        <span class="jam-pill">${jamLabel}</span>
                                        <span style="font-size:0.68rem; color:#94a3b8; margin-top:2px;">${this.escapeHtml(s.hari)}</span>
                                    </div>
                                    <div class="dash-schedule-body">
                                        <div class="dash-schedule-mapel">${this.escapeHtml(s.nama_mapel)}</div>
                                        <div class="dash-schedule-guru">
                                            <span>👨‍🏫</span> ${this.escapeHtml(s.guru_nama)}
                                        </div>
                                    </div>
                                    <div class="dash-schedule-kelas">
                                        <span class="badge badge-info" style="font-size:0.75rem; padding:4px 8px; font-weight:700;">
                                            ${this.escapeHtml(s.nama_kelas)}
                                        </span>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                `;
            } else {
                scheduleListHtml = `
                    <div style="text-align:center; padding:30px 16px; color:#94a3b8;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40" style="opacity:0.4; margin-bottom:8px;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                        <p style="margin:0; font-size:0.88rem;">Tidak ada jadwal KBM yang aktif untuk hari ini.</p>
                        <button class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.navigate('sch_jadwal')" style="margin-top:10px;">Lihat Jadwal Keseluruhan</button>
                    </div>
                `;
            }

            // 3. Leaderboard Guru HTML
            let leaderboardHtml = '';
            if (topTeachers.length > 0) {
                const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
                const rankClasses = ['dash-rank-1', 'dash-rank-2', 'dash-rank-3', 'dash-rank-other', 'dash-rank-other'];
                leaderboardHtml = `
                    <div class="dash-leaderboard-list">
                        ${topTeachers.map((t, idx) => {
                            const medal = medals[idx] || (idx + 1);
                            const rankCls = rankClasses[idx] || 'dash-rank-other';
                            return `
                                <div class="dash-leaderboard-item">
                                    <div class="dash-rank-badge ${rankCls}">${medal}</div>
                                    <div class="dash-leader-info">
                                        <div class="dash-leader-name" title="${this.escapeHtml(t.nama_lengkap)}">${this.escapeHtml(t.nama_lengkap)}</div>
                                        <div class="dash-leader-meta">
                                            <span>📚 ${t.total_kelas_ajar || 0} Kelas</span>
                                            <span>•</span>
                                            <span>⏱️ ${t.total_jp || 0} JP</span>
                                        </div>
                                    </div>
                                    <div class="dash-leader-score">
                                        <strong>${t.total_jurnal || 0}</strong>
                                        <span>Jurnal</span>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                `;
            } else {
                leaderboardHtml = `<div style="text-align:center; padding:20px; color:#94a3b8; font-size:0.85rem;">Belum ada data aktivitas guru.</div>`;
            }

            // 4. Perangkat By Type HTML
            let docMatrixHtml = '';
            if (docByType.length > 0) {
                docMatrixHtml = `
                    <div class="dash-doc-matrix">
                        ${docByType.map(d => {
                            const total = parseInt(d.total) || 1;
                            const approved = parseInt(d.approved) || 0;
                            const pending = parseInt(d.pending) || 0;
                            const rejected = parseInt(d.rejected) || 0;
                            const approvedPct = (approved / total) * 100;
                            const pendingPct = (pending / total) * 100;
                            const rejectedPct = (rejected / total) * 100;
                            return `
                                <div class="dash-doc-matrix-item">
                                    <div class="dash-doc-matrix-header">
                                        <div class="dash-doc-matrix-name">${this.escapeHtml(d.tipe_dokumen)}</div>
                                        <div class="dash-doc-matrix-count">${approved}/${total} Disetujui</div>
                                    </div>
                                    <div class="dash-doc-bar">
                                        <div class="dash-doc-bar-fill" style="width:${approvedPct}%; background:#22c55e;" title="Disetujui: ${approved}"></div>
                                        <div class="dash-doc-bar-fill" style="width:${pendingPct}%; background:#eab308;" title="Pending: ${pending}"></div>
                                        <div class="dash-doc-bar-fill" style="width:${rejectedPct}%; background:#ef4444;" title="Ditolak: ${rejected}"></div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                `;
            } else {
                docMatrixHtml = `
                    <div style="background:#f8fafc; border-radius:10px; padding:14px; text-align:center; font-size:0.84rem; color:#64748b;">
                        Belum ada dokumen perangkat ajar terunggah.
                    </div>
                `;
            }

            // 5. Buku Penghubung Rows HTML
            let bukuRowsHtml = '';
            if (recentBuku.length > 0) {
                bukuRowsHtml = recentBuku.map(b => {
                    const badgeCls = b.warna_badge || 'badge-info';
                    const safeCatatan = b.catatan ? (b.catatan.length > 80 ? b.catatan.substring(0, 80) + '...' : b.catatan) : '-';
                    return `
                        <div class="dash-feed-item">
                            <div class="dash-feed-content">
                                <div class="dash-feed-header">
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <span class="badge ${badgeCls}" style="font-size:0.75rem; padding:3px 8px;">${this.escapeHtml(b.jenis)}</span>
                                        <strong style="font-size:0.88rem; color:#1e293b;">${this.escapeHtml(b.nama_siswa)}</strong>
                                        <span style="font-size:0.75rem; color:#64748b;">(${this.escapeHtml(b.nama_kelas || '-')})</span>
                                    </div>
                                    <span class="dash-feed-time">📅 ${this.escapeHtml(b.tanggal)}</span>
                                </div>
                                <div class="dash-feed-desc" style="margin-top:4px;">${this.escapeHtml(safeCatatan)}</div>
                                ${b.dicatat_nama ? `<div style="font-size:0.72rem; color:#94a3b8; margin-top:4px;">✍️ Dicatat oleh: <strong>${this.escapeHtml(b.dicatat_nama)}</strong></div>` : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                bukuRowsHtml = `
                    <div style="text-align:center; padding:30px 16px; color:#94a3b8;">
                        <p style="margin:0; font-size:0.88rem;">Belum ada catatan buku penghubung.</p>
                        <button class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.navigate('buku_penghubung')" style="margin-top:10px;">+ Buat Catatan Siswa</button>
                    </div>
                `;
            }

            // 6. Guru Tidak Hadir Status HTML
            let guruPresensiHtml = '';
            if (guruTidakHadir.length > 0) {
                const listTh = guruTidakHadir.map(g => {
                    const statusBadge = g.status === 'S' ? '<span class="badge badge-info">Sakit</span>' : (g.status === 'I' ? '<span class="badge badge-warning">Izin</span>' : '<span class="badge badge-danger">Alpha</span>');
                    return `
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f8fafc;">
                            <div>
                                <strong style="font-size:0.85rem; color:#1e293b;">${this.escapeHtml(g.guru_nama)}</strong>
                                ${g.keterangan ? `<div style="font-size:0.75rem; color:#64748b;">${this.escapeHtml(g.keterangan)}</div>` : ''}
                            </div>
                            <div>${statusBadge}</div>
                        </div>
                    `;
                }).join('');
                guruPresensiHtml = `
                    <div style="margin-top:10px;">
                        <div style="font-size:0.8rem; font-weight:700; color:#ef4444; margin-bottom:6px; display:flex; align-items:center; gap:4px;">
                            ⚠️ Guru Tidak Hadir Hari Ini (${guruTidakHadir.length}):
                        </div>
                        ${listTh}
                    </div>
                `;
            } else {
                guruPresensiHtml = `
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:12px; margin-top:12px; display:flex; align-items:center; gap:8px;">
                        <span style="font-size:1.2rem;">✅</span>
                        <div>
                            <div style="font-size:0.85rem; font-weight:700; color:#15803d;">Presensi Guru Terkendali</div>
                            <div style="font-size:0.75rem; color:#166534;">Tidak ada laporan ketidakhadiran guru hari ini.</div>
                        </div>
                    </div>
                `;
            }

            // 7. Piket Status HTML
            let piketSectionHtml = '';
            if (piketToday.length > 0) {
                const piketListHtml = piketToday.map(p => `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f8fafc; font-size:0.82rem;">
                        <div>
                            <strong>${this.escapeHtml(p.guru_piket_nama)}</strong>
                            <span style="color:#64748b;"> mengajar kelas ${this.escapeHtml(p.nama_kelas || '-')}</span>
                        </div>
                        ${p.guru_diganti_nama ? `<span style="color:#ef4444; font-size:0.75rem;">(Gantikan ${this.escapeHtml(p.guru_diganti_nama)})</span>` : ''}
                    </div>
                `).join('');
                piketSectionHtml = `
                    <div style="margin-top:14px; padding-top:12px; border-top:1px dashed #e2e8f0;">
                        <div style="font-size:0.8rem; font-weight:700; color:#6366f1; margin-bottom:6px;">
                            📅 Guru Piket Aktif Hari Ini:
                        </div>
                        ${piketListHtml}
                    </div>
                `;
            }

            // Main Dashboard HTML Assembly
            $container.html(`
                <!-- HERO BANNER -->
                <div class="dash-hero-banner fade-in">
                    <div class="dash-hero-top">
                        <div class="dash-hero-title">
                            <h2>${greeting}, ${this.escapeHtml(userName)}! 👋</h2>
                            <p>
                                <span>🏫 ${this.escapeHtml(schoolName)}</span>
                                <span>•</span>
                                <span>Pusat Manajemen Kurikulum &amp; Akademik</span>
                            </p>
                        </div>
                        <div class="dash-hero-badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            ${todayStr}
                        </div>
                    </div>
                    <div class="dash-hero-actions">
                        <button class="dash-action-pill" onclick="Curriculum.navigate('jurnal')">
                            ✍️ Input Jurnal Mengajar
                        </button>
                        <button class="dash-action-pill" onclick="Curriculum.navigate('absensi')">
                            📊 Rekap Absensi Siswa
                        </button>
                        <button class="dash-action-pill" onclick="Curriculum.navigate('buku_penghubung')">
                            📖 Catat Buku Penghubung
                        </button>
                        <button class="dash-action-pill" onclick="Curriculum.navigate('dokumen')">
                            📁 Verifikasi Perangkat (${docStats.pending || 0})
                        </button>
                    </div>
                </div>

                <!-- 8-CARD SUMMARY STATS KPI GRID -->
                <div class="dash-stats-grid">
                    <!-- 1. GURU -->
                    <div class="dash-stat-card fade-in" style="animation-delay:0.02s;">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background:#f5f3ff; color:#7c3aed;">👨‍🏫</div>
                            <span class="badge badge-primary" style="font-size:0.72rem;">Aktif</span>
                        </div>
                        <div class="dash-stat-num">${summary.total_guru || 0}</div>
                        <div class="dash-stat-label">Total Guru Pengajar</div>
                        <div class="dash-stat-footer">
                            <span>👥 Terdaftar di sistem portal</span>
                        </div>
                    </div>

                    <!-- 2. KELAS / ROMBEL -->
                    <div class="dash-stat-card fade-in" style="animation-delay:0.05s;">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background:#eff6ff; color:#2563eb;">🏫</div>
                            <span class="badge badge-info" style="font-size:0.72rem;">Rombel</span>
                        </div>
                        <div class="dash-stat-num">${summary.total_kelas || 0}</div>
                        <div class="dash-stat-label">Rombongan Belajar</div>
                        <div class="dash-stat-footer">
                            <span>🎓 ${summary.total_siswa || 0} Total Siswa</span>
                        </div>
                    </div>

                    <!-- 3. MATA PELAJARAN & BEBAN JAM -->
                    <div class="dash-stat-card fade-in" style="animation-delay:0.08s;">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background:#ecfdf5; color:#059669;">📚</div>
                            <span class="badge badge-success" style="font-size:0.72rem;">Kurikulum</span>
                        </div>
                        <div class="dash-stat-num">${summary.total_mapel || 0}</div>
                        <div class="dash-stat-label">Mata Pelajaran</div>
                        <div class="dash-stat-footer">
                            <span>⏱️ <strong>${summary.total_jp || 0} JP</strong> Alokasi Mingguan</span>
                        </div>
                    </div>

                    <!-- 4. JURNAL HARI INI -->
                    <div class="dash-stat-card fade-in" style="animation-delay:0.11s;">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background:#fdf2f8; color:#db2777;">📝</div>
                            <span class="badge badge-warning" style="font-size:0.72rem;">Hari Ini</span>
                        </div>
                        <div class="dash-stat-num" style="color:#db2777;">${summary.jurnal_today_count || 0}</div>
                        <div class="dash-stat-label">Jurnal Pembelajaran</div>
                        <div class="dash-stat-footer">
                            <span>📊 Kegiatan KBM tercatat</span>
                        </div>
                    </div>

                    <!-- 5. PRESENSI SISWA -->
                    <div class="dash-stat-card fade-in" style="animation-delay:0.14s;">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background:#f0fdf4; color:#16a34a;">🎒</div>
                            <span class="badge badge-success" style="font-size:0.72rem;">${absensiSiswa.month.rate}% Kehadiran</span>
                        </div>
                        <div class="dash-stat-num" style="color:#16a34a;">${absensiSiswa.today.hadir || 0}</div>
                        <div class="dash-stat-label">Siswa Hadir Hari Ini</div>
                        <div class="dash-stat-footer">
                            <span>S: ${absensiSiswa.today.sakit} • I: ${absensiSiswa.today.izin} • A: ${absensiSiswa.today.alpha}</span>
                        </div>
                    </div>

                    <!-- 6. PRESENSI GURU -->
                    <div class="dash-stat-card fade-in" style="animation-delay:0.17s;">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background:#fef3c7; color:#d97706;">👔</div>
                            <span class="badge ${absensiGuru.sakit + absensiGuru.izin + absensiGuru.alpha > 0 ? 'badge-danger' : 'badge-success'}" style="font-size:0.72rem;">
                                ${absensiGuru.sakit + absensiGuru.izin + absensiGuru.alpha > 0 ? `${absensiGuru.sakit + absensiGuru.izin + absensiGuru.alpha} Berhalangan` : 'Lengkap'}
                            </span>
                        </div>
                        <div class="dash-stat-num">${absensiGuru.hadir || 0}</div>
                        <div class="dash-stat-label">Guru Hadir Hari Ini</div>
                        <div class="dash-stat-footer">
                            <span>S: ${absensiGuru.sakit} • I: ${absensiGuru.izin} • A: ${absensiGuru.alpha}</span>
                        </div>
                    </div>

                    <!-- 7. RATA-RATA BEBAN MENGAJAR -->
                    <div class="dash-stat-card fade-in" style="animation-delay:0.20s;">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background:#f0f9ff; color:#0284c7;">⚖️</div>
                            <span class="badge badge-info" style="font-size:0.72rem;">Rata-Rata</span>
                        </div>
                        <div class="dash-stat-num" style="color:#0284c7;">${workload.avg_jp_per_guru || 0} <span style="font-size:1rem; font-weight:600;">JP</span></div>
                        <div class="dash-stat-label">Rata Beban per Guru</div>
                        <div class="dash-stat-footer">
                            <span>Maks: ${workload.max_jp || 0} JP • ${workload.total_guru_mengajar || 0} Pengajar</span>
                        </div>
                    </div>

                    <!-- 8. PERANGKAT AJAR DOKUMEN -->
                    <div class="dash-stat-card fade-in" style="animation-delay:0.23s;">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background:#e0e7ff; color:#4f46e5;">📁</div>
                            <span class="badge ${docStats.pending > 0 ? 'badge-warning' : 'badge-success'}" style="font-size:0.72rem;">
                                ${docStats.pending > 0 ? `${docStats.pending} Menunggu` : 'Tersinkron'}
                            </span>
                        </div>
                        <div class="dash-stat-num">${docStats.total || 0}</div>
                        <div class="dash-stat-label">Dokumen Perangkat</div>
                        <div class="dash-stat-footer">
                            <span>✅ ${docStats.approved || 0} Disetujui (${docStats.total > 0 ? Math.round((docStats.approved / docStats.total) * 100) : 0}%)</span>
                        </div>
                    </div>
                </div>

                <!-- MAIN TWO-COLUMN CONTENT GRID -->
                <div class="dash-content-grid">
                    <!-- LEFT COLUMN (PRIMARY FEEDS & SCHEDULE) -->
                    <div>
                        <!-- JADWAL KBM HARI INI -->
                        <div class="dash-card fade-in">
                            <div class="dash-card-header">
                                <h3 class="dash-card-title">
                                    <span style="font-size:1.1rem;">📅</span> ${this.escapeHtml(currentDayTitle)}
                                </h3>
                                <button class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.navigate('sch_jadwal')">
                                    Lihat Jadwal ➔
                                </button>
                            </div>
                            <div class="dash-card-body" style="padding:14px 20px;">
                                ${scheduleListHtml}
                            </div>
                        </div>

                        <!-- JURNAL MENGAJAR TERKINI -->
                        <div class="dash-card fade-in">
                            <div class="dash-card-header">
                                <h3 class="dash-card-title">
                                    <span style="font-size:1.1rem;">📝</span> Jurnal Pembelajaran Guru Terkini
                                </h3>
                                <button class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.navigate('jurnal')">
                                    Lihat Semua ➔
                                </button>
                            </div>
                            <div class="dash-card-body" style="padding:14px 20px;">
                                ${jurnalRowsHtml}
                            </div>
                        </div>

                        <!-- BUKU PENGHUBUNG & BK -->
                        <div class="dash-card fade-in">
                            <div class="dash-card-header">
                                <h3 class="dash-card-title">
                                    <span style="font-size:1.1rem;">📖</span> Catatan Buku Penghubung &amp; BK
                                </h3>
                                <button class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.navigate('buku_penghubung')">
                                    Buka Menu ➔
                                </button>
                            </div>
                            <div class="dash-card-body" style="padding:14px 20px;">
                                ${bukuRowsHtml}
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN (WIDGETS, LEADERBOARD & METRICS) -->
                    <div>
                        <!-- PINTASAN AKSES CEPAT -->
                        <div class="dash-card fade-in">
                            <div class="dash-card-header">
                                <h3 class="dash-card-title">
                                    <span style="font-size:1.1rem;">⚡</span> Pintasan Modul Kurikulum
                                </h3>
                            </div>
                            <div class="dash-card-body">
                                <div class="dash-nav-grid">
                                    <a class="dash-nav-tile" onclick="Curriculum.navigate('sch_jadwal')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                        Jadwal KBM
                                    </a>
                                    <a class="dash-nav-tile" onclick="Curriculum.navigate('jurnal')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        Jurnal KBM
                                    </a>
                                    <a class="dash-nav-tile" onclick="Curriculum.navigate('absensi')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                        Absen Siswa
                                    </a>
                                    <a class="dash-nav-tile" onclick="Curriculum.navigate('absensi_guru')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                        Absen Guru
                                    </a>
                                    <a class="dash-nav-tile" onclick="Curriculum.navigate('dokumen')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                        Perangkat Ajar
                                    </a>
                                    <a class="dash-nav-tile" onclick="Curriculum.navigate('buku_penghubung')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                                        Penghubung
                                    </a>
                                    <a class="dash-nav-tile" onclick="Curriculum.navigate('piket')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        Piket Guru
                                    </a>
                                    <a class="dash-nav-tile" onclick="Curriculum.navigate('laporan_jurnal')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                                        Rekap Laporan
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- TOP LEADERBOARD GURU KBM -->
                        <div class="dash-card fade-in">
                            <div class="dash-card-header">
                                <h3 class="dash-card-title">
                                    <span style="font-size:1.1rem;">🏆</span> Guru Teraktif Input Jurnal KBM
                                </h3>
                                <button class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.navigate('laporan_jurnal')">
                                    Rekap ➔
                                </button>
                            </div>
                            <div class="dash-card-body">
                                ${leaderboardHtml}
                            </div>
                        </div>

                        <!-- TINGKAT PRESENSI SISWA -->
                        <div class="dash-card fade-in">
                            <div class="dash-card-header">
                                <h3 class="dash-card-title">
                                    <span style="font-size:1.1rem;">🎒</span> Tingkat Presensi Siswa
                                </h3>
                                <button class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.navigate('absensi')">
                                    Absensi ➔
                                </button>
                            </div>
                            <div class="dash-card-body">
                                <div class="dash-attend-box">
                                    <div class="dash-attend-rate">${absensiSiswa.month.rate}%</div>
                                    <div class="dash-attend-label">Rata-rata Tingkat Kehadiran Siswa Bulan Ini</div>
                                </div>
                                <div class="dash-attend-pills">
                                    <div class="dash-attend-pill-item">
                                        <div class="val" style="color:#16a34a;">${absensiSiswa.today.hadir || 0}</div>
                                        <div class="lbl">Hadir</div>
                                    </div>
                                    <div class="dash-attend-pill-item">
                                        <div class="val" style="color:#2563eb;">${absensiSiswa.today.sakit || 0}</div>
                                        <div class="lbl">Sakit</div>
                                    </div>
                                    <div class="dash-attend-pill-item">
                                        <div class="val" style="color:#d97706;">${absensiSiswa.today.izin || 0}</div>
                                        <div class="lbl">Izin</div>
                                    </div>
                                    <div class="dash-attend-pill-item">
                                        <div class="val" style="color:#dc2626;">${absensiSiswa.today.alpha || 0}</div>
                                        <div class="lbl">Alpha</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STATUS PRESENSI & PIKET GURU HARI INI -->
                        <div class="dash-card fade-in">
                            <div class="dash-card-header">
                                <h3 class="dash-card-title">
                                    <span style="font-size:1.1rem;">👔</span> Presensi &amp; Piket Hari Ini
                                </h3>
                                <button class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.navigate('absensi_guru')">
                                    Presensi ➔
                                </button>
                            </div>
                            <div class="dash-card-body">
                                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; text-align:center;">
                                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:10px 6px;">
                                        <div style="font-size:1.25rem; font-weight:800; color:#15803d;">${absensiGuru.hadir || 0}</div>
                                        <div style="font-size:0.72rem; color:#166534; font-weight:600;">Hadir</div>
                                    </div>
                                    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:10px 6px;">
                                        <div style="font-size:1.25rem; font-weight:800; color:#1d4ed8;">${absensiGuru.sakit || 0}</div>
                                        <div style="font-size:0.72rem; color:#1e40af; font-weight:600;">Sakit</div>
                                    </div>
                                    <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:10px 6px;">
                                        <div style="font-size:1.25rem; font-weight:800; color:#b45309;">${absensiGuru.izin || 0}</div>
                                        <div style="font-size:0.72rem; color:#92400e; font-weight:600;">Izin</div>
                                    </div>
                                </div>
                                ${guruPresensiHtml}
                                ${piketSectionHtml}
                            </div>
                        </div>

                        <!-- MATRIKS KELENGKAPAN PERANGKAT AJAR GURU -->
                        <div class="dash-card fade-in">
                            <div class="dash-card-header">
                                <h3 class="dash-card-title">
                                    <span style="font-size:1.1rem;">📁</span> Kelengkapan Perangkat Ajar
                                </h3>
                                <button class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.navigate('dokumen')">
                                    Verifikasi ➔
                                </button>
                            </div>
                            <div class="dash-card-body">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                    <span style="font-size:0.85rem; color:#64748b;">Total Dokumen Masuk:</span>
                                    <strong style="font-size:1rem; color:#1e293b;">${docStats.total || 0} Berkas</strong>
                                </div>
                                <div style="height:10px; background:#f1f5f9; border-radius:6px; overflow:hidden; display:flex; margin-bottom:14px;">
                                    <div style="width:${docStats.total > 0 ? (docStats.approved / docStats.total * 100) : 0}%; background:#22c55e;" title="Disetujui: ${docStats.approved}"></div>
                                    <div style="width:${docStats.total > 0 ? (docStats.pending / docStats.total * 100) : 0}%; background:#eab308;" title="Menunggu: ${docStats.pending}"></div>
                                    <div style="width:${docStats.total > 0 ? (docStats.rejected / docStats.total * 100) : 0}%; background:#ef4444;" title="Ditolak: ${docStats.rejected}"></div>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:0.78rem; margin-bottom:14px;">
                                    <span style="color:#15803d; font-weight:600;">🟢 ${docStats.approved || 0} Disetujui</span>
                                    <span style="color:#a16207; font-weight:600;">🟡 ${docStats.pending || 0} Menunggu</span>
                                    <span style="color:#b91c1c; font-weight:600;">🔴 ${docStats.rejected || 0} Ditolak</span>
                                </div>
                                ${docMatrixHtml}
                            </div>
                        </div>

                        <!-- INFORMASI TAHUN AJARAN -->
                        <div class="dash-card fade-in" style="background:#f8fafc; border:1px solid #e2e8f0;">
                            <div class="dash-card-body" style="padding:16px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:36px; height:36px; border-radius:10px; background:#ede9fe; color:#7c3aed; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
                                        🎓
                                    </div>
                                    <div>
                                        <div style="font-size:0.78rem; color:#64748b; font-weight:600;">PERIODE AKADEMIK AKTIF</div>
                                        <div style="font-size:0.92rem; font-weight:700; color:#1e293b;">${this.escapeHtml(activeYearText)}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
        const firstDayOfMonth = today.substring(0, 8) + '01';
        this.state.absensiSesi = 1; // 1 = Masuk, 2 = Istirahat, 3 = Pulang

        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3>✅ Absensi Siswa</h3>
                        <p class="acad-subtitle">Terintegrasi otomatis dengan mesin E-Absen & Rekapitulasi Kehadiran 3 Sesi (Masuk, Istirahat, Pulang).</p>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn-acad btn-acad-outline" onclick="Curriculum.showSettingWaktuModal()">
                            ⚙️ Setting Jam Absensi Siswa
                        </button>
                    </div>
                </div>
                <div class="acad-card-body">
                    <!-- Main Navigation Tabs -->
                    <div style="margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; display: flex; gap: 16px;">
                        <button class="absensi-tab-btn active" data-tab="harian" onclick="Curriculum.switchAbsensiTab('harian')" style="background: none; border: none; padding: 10px 16px; cursor: pointer; border-bottom: 2px solid #7C3AED; font-weight: 600; color: #7C3AED;">
                            📌 Absensi Harian
                        </button>
                        <button class="absensi-tab-btn" data-tab="rekap" onclick="Curriculum.switchAbsensiTab('rekap')" style="background: none; border: none; padding: 10px 16px; cursor: pointer; border-bottom: 2px solid transparent; color: #64748B;">
                            📊 Rekapitulasi Kehadiran
                        </button>
                    </div>

                    <!-- Tab 1: Absensi Harian (3 Sesi) -->
                    <div id="absensiTabHarian" class="absensi-tab-content">
                        <!-- 3 Sesi Sub-Tabs -->
                        <div class="absensi-sesi-nav" style="display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap;">
                            <button class="btn-acad absensi-sesi-btn active" data-sesi="1" onclick="Curriculum.switchAbsensiSesi(1)" style="padding:8px 18px; border-radius:20px; font-weight:600; font-size:0.875rem; background:#7C3AED; color:white; border:1px solid #7C3AED; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all 0.2s;">
                                🌅 <span>Absen Masuk</span>
                            </button>
                            <button class="btn-acad absensi-sesi-btn" data-sesi="2" onclick="Curriculum.switchAbsensiSesi(2)" style="padding:8px 18px; border-radius:20px; font-weight:600; font-size:0.875rem; background:#F1F5F9; color:#475569; border:1px solid #CBD5E1; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all 0.2s;">
                                ☕ <span>Absen Istirahat</span>
                            </button>
                            <button class="btn-acad absensi-sesi-btn" data-sesi="3" onclick="Curriculum.switchAbsensiSesi(3)" style="padding:8px 18px; border-radius:20px; font-weight:600; font-size:0.875rem; background:#F1F5F9; color:#475569; border:1px solid #CBD5E1; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all 0.2s;">
                                🏠 <span>Absen Pulang</span>
                            </button>
                        </div>

                        <div class="filter-bar">
                            <div class="filter-item"><label>Tanggal</label><input type="date" class="form-input-acad" id="absensiTanggal" value="${today}"></div>
                            <div class="filter-item"><label>Kelas</label><select class="form-select-acad" id="absensiKelas"><option value="">Pilih Kelas...</option></select></div>
                            <div class="filter-item" style="display:flex; flex-direction:row; align-items:flex-end;"><button class="btn-acad btn-acad-primary" onclick="Curriculum.loadAbsensiTable()">🔍 Tampilkan</button></div>
                        </div>

                        <div id="absensiInfoBanner" style="margin-bottom:16px; padding:12px 16px; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:10px; color:#1E40AF; font-size:0.875rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                            <span id="absensiBannerText">📌 Sesi <strong>Absen Masuk</strong>: Menyelaraskan tap pagi E-Absen. <strong>Batas Terlambat: <span id="lblJamTerlambat">06:30</span></strong></span>
                        </div>
                        <div id="absensiTableWrapper"></div>
                    </div>

                    <!-- Tab 2: Rekapitulasi Absensi -->
                    <div id="absensiTabRekap" class="absensi-tab-content" style="display:none;">
                        <div class="filter-bar">
                            <div class="filter-item"><label>Kelas</label><select class="form-select-acad" id="rekapAbsensiKelas"><option value="0">Semua Kelas</option></select></div>
                            <div class="filter-item"><label>Tanggal Awal</label><input type="date" class="form-input-acad" id="rekapTglAwal" value="${firstDayOfMonth}"></div>
                            <div class="filter-item"><label>Tanggal Akhir</label><input type="date" class="form-input-acad" id="rekapTglAkhir" value="${today}"></div>
                            <div class="filter-item" style="display:flex; flex-direction:row; align-items:flex-end; gap:8px;">
                                <button class="btn-acad btn-acad-primary" onclick="Curriculum.loadAbsensiRekapTable()">🔍 Tampilkan Rekap</button>
                                <button class="btn-acad btn-acad-success" onclick="Curriculum.exportAbsensiExcel()" style="background:#10B981; color:white; border:none;">📥 Export Excel</button>
                            </div>
                        </div>
                        <div id="rekapAbsensiTableWrapper"></div>
                    </div>
                </div>
            </div>
        `);

        // Load kelas for dropdowns
        this.api('sch_kelas.php?action=list').done(res => {
            if (!res.success) return;
            const opts = res.data.map(c => `<option value="${c.id}">${this.escapeHtml(c.nama_kelas)}</option>`).join('');
            $('#absensiKelas').append(opts);
            $('#rekapAbsensiKelas').append(opts);
        });

        // Load current time settings
        this.api('absensi.php?action=get_settings').done(res => {
            if (res.data) {
                this.state.siswaSettings = res.data;
                this.updateAbsensiBannerText();
            }
        });
    },

    switchAbsensiTab(tab) {
        $('.absensi-tab-btn').css({ 'border-bottom': '2px solid transparent', 'color': '#64748B', 'font-weight': 'normal' });
        $(`.absensi-tab-btn[data-tab="${tab}"]`).css({ 'border-bottom': '2px solid #7C3AED', 'color': '#7C3AED', 'font-weight': '600' });

        $('.absensi-tab-content').hide();
        if (tab === 'harian') {
            $('#absensiTabHarian').fadeIn(200);
        } else {
            $('#absensiTabRekap').fadeIn(200);
            if (!$('#rekapAbsensiTableWrapper').children().length) {
                this.loadAbsensiRekapTable();
            }
        }
    },

    switchAbsensiSesi(sesi) {
        this.state.absensiSesi = parseInt(sesi) || 1;
        $('.absensi-sesi-btn').each(function() {
            const btnSesi = parseInt($(this).data('sesi'));
            if (btnSesi === sesi) {
                $(this).addClass('active').css({ 'background': '#7C3AED', 'color': 'white', 'border-color': '#7C3AED' });
            } else {
                $(this).removeClass('active').css({ 'background': '#F1F5F9', 'color': '#475569', 'border-color': '#CBD5E1' });
            }
        });

        this.updateAbsensiBannerText();
        if ($('#absensiKelas').val()) {
            this.loadAbsensiTable();
        }
    },

    updateAbsensiBannerText() {
        const sesi = this.state.absensiSesi || 1;
        const cfg = this.state.siswaSettings || {};
        const jamMasuk = cfg.waktu_terlambat || '06:30';
        const jamIstMulai = cfg.waktu_istirahat_mulai || '09:30';
        const jamIstSelesai = cfg.waktu_istirahat_selesai || '10:15';
        const jamPulang = cfg.waktu_pulang || '15:30';

        let bannerHtml = '';
        if (sesi === 1) {
            bannerHtml = `<span>🌅 Sesi <strong>Absen Masuk Siswa</strong>: Menyelaraskan tap pagi E-Absen. <strong>Batas Masuk / Terlambat: <span id="lblJamTerlambat">${jamMasuk}</span></strong></span>`;
        } else if (sesi === 2) {
            bannerHtml = `<span>☕ Sesi <strong>Absen Istirahat Siswa</strong>: Menyelaraskan tap istirahat E-Absen. <strong>Waktu Istirahat: <span id="lblJamIstirahat">${jamIstMulai} - ${jamIstSelesai}</span></strong></span>`;
        } else if (sesi === 3) {
            bannerHtml = `<span>🏠 Sesi <strong>Absen Pulang Siswa</strong>: Menyelaraskan tap pulang E-Absen. <strong>Jam Batas Pulang: <span id="lblJamPulang">${jamPulang}</span></strong></span>`;
        }
        $('#absensiBannerText').html(bannerHtml);
    },

    setAllAbsensiStatus(status) {
        $(`#absensiDataTable input[type=radio][value="${status}"]`).each(function() {
            $(this).prop('checked', true).trigger('change');
        });
        EModal.toast({ type: 'info', message: `Semua siswa di-set ${status === 'H' ? 'Hadir' : status}.` });
    },

    loadAbsensiTable() {
        const tanggal = $('#absensiTanggal').val();
        const kelas_id = $('#absensiKelas').val();
        const sesi = this.state.absensiSesi || 1;
        if (!kelas_id) { EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Pilih kelas terlebih dahulu.' }); return; }

        const sesiLabels = { 1: 'Masuk', 2: 'Istirahat', 3: 'Pulang' };
        const labelSesi = sesiLabels[sesi] || 'Harian';

        $('#absensiTableWrapper').html('<div class="skeleton-module" style="height:200px;"></div>');

        this.api(`absensi.php?action=list&tanggal=${tanggal}&kelas_id=${kelas_id}&jam_ke=${sesi}`).done(res => {
            const data = res.data ? res.data.students || [] : [];
            if (res.data) {
                this.state.siswaSettings = res.data;
                this.updateAbsensiBannerText();
            }

            if (!data.length) {
                $('#absensiTableWrapper').html(`<div class="acad-empty"><h3>Tidak Ada Siswa</h3><p>Tidak ditemukan siswa aktif untuk kelas ini.</p></div>`);
                return;
            }

            const rows = data.map((s, idx) => {
                let scanBadge = '';
                if (sesi === 1) {
                    if (s.jam_scan) {
                        scanBadge = `<span class="badge ${s.status === 'T' ? 'badge-warning' : 'badge-success'}" style="font-size:0.8rem; background:${s.status === 'T' ? '#FEF3C7; color:#92400E; border:1px solid #FCD34D' : '#DCFCE7; color:#166534; border:1px solid #86EFAC'};">${this.escapeHtml(s.scan_info)}</span>`;
                    } else {
                        scanBadge = `<span class="badge badge-secondary" style="font-size:0.8rem; background:#F1F5F9; color:#64748B; border:1px solid #CBD5E1;">Belum Scan Mesin</span>`;
                    }
                } else {
                    // Sesi 2 (Istirahat) & Sesi 3 (Pulang): Hanya tampilkan jam saja
                    if (s.jam_scan) {
                        const timeStr = s.scan_info || s.jam_scan.substring(0, 5);
                        scanBadge = `<span style="font-weight:600; font-size:0.85rem; color:#1E293B; background:#F8FAFC; padding:4px 10px; border-radius:6px; border:1px solid #CBD5E1; display:inline-flex; align-items:center; gap:4px;">🕒 ${this.escapeHtml(timeStr)}</span>`;
                    } else {
                        scanBadge = `<span style="font-size:0.8rem; color:#94A3B8; background:#F8FAFC; padding:4px 10px; border-radius:6px; border:1px dashed #CBD5E1;">Belum Scan</span>`;
                    }
                }

                return `
                <tr class="fade-in">
                    <td style="text-align:center;">${idx + 1}</td>
                    <td>${this.escapeHtml(s.nis)}</td>
                    <td><strong>${this.escapeHtml(s.nama)}</strong></td>
                    <td>${scanBadge}</td>
                    <td>
                        <div class="absensi-radio-group">
                            <label class="absensi-radio ${s.status === 'H' ? 'active-h' : ''}"><input type="radio" name="abs_${s.student_id}" value="H" ${s.status === 'H' ? 'checked' : ''}> Hadir</label>
                            <label class="absensi-radio ${s.status === 'T' ? 'active-t' : ''}"><input type="radio" name="abs_${s.student_id}" value="T" ${s.status === 'T' ? 'checked' : ''}> Terlambat</label>
                            <label class="absensi-radio ${s.status === 'S' ? 'active-s' : ''}"><input type="radio" name="abs_${s.student_id}" value="S" ${s.status === 'S' ? 'checked' : ''}> Sakit</label>
                            <label class="absensi-radio ${s.status === 'I' ? 'active-i' : ''}"><input type="radio" name="abs_${s.student_id}" value="I" ${s.status === 'I' ? 'checked' : ''}> Izin</label>
                            <label class="absensi-radio ${s.status === 'A' ? 'active-a' : ''}"><input type="radio" name="abs_${s.student_id}" value="A" ${s.status === 'A' ? 'checked' : ''}> Alpha</label>
                        </div>
                    </td>
                    <td>
                        <input type="text" class="form-input-acad abs-keterangan" data-studentid="${s.student_id}" value="${this.escapeHtml(s.keterangan || '')}" placeholder="Keterangan..." style="width:100%; font-size:0.85rem;">
                    </td>
                </tr>
            `;
            }).join('');

            $('#absensiTableWrapper').html(`
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div style="display:flex; gap:8px;">
                        <button class="btn-acad btn-acad-outline btn-acad-sm" onclick="Curriculum.setAllAbsensiStatus('H')" style="font-size:0.8rem; border-color:#10B981; color:#059669;">
                            ✅ Set Semua Hadir
                        </button>
                        <button class="btn-acad btn-acad-outline btn-acad-sm" onclick="Curriculum.setAllAbsensiStatus('A')" style="font-size:0.8rem; border-color:#EF4444; color:#DC2626;">
                            ❌ Set Semua Alpha
                        </button>
                        <button class="btn-acad btn-acad-outline btn-acad-sm" onclick="Curriculum.loadAbsensiTable()" style="font-size:0.8rem;">
                            🔄 Reset Sesuai Mesin
                        </button>
                    </div>
                    <div style="font-size:0.85rem; color:#64748B;">Total: <strong>${data.length} Siswa</strong></div>
                </div>
                <div class="data-table-wrapper">
                    <table class="data-table" id="absensiDataTable">
                        <thead><tr><th width="45" style="text-align:center;">No</th><th>NIS</th><th>Nama Siswa</th><th>Log Mesin (${labelSesi})</th><th>Status Kehadiran</th><th>Keterangan</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
                <div style="margin-top:16px; display:flex; justify-content:flex-end;">
                    <button class="btn-acad btn-acad-primary" onclick="Curriculum.saveAbsensi()">💾 Simpan Absensi ${labelSesi}</button>
                </div>
            `);

            // Radio change handler for visual feedback
            $('#absensiDataTable input[type=radio]').on('change', function() {
                const $group = $(this).closest('.absensi-radio-group');
                $group.find('.absensi-radio').removeClass('active-h active-s active-i active-a active-t');
                const val = $(this).val();
                $(this).parent().addClass(`active-${val.toLowerCase()}`);
            });
        });
    },

    saveAbsensi() {
        const tanggal = $('#absensiTanggal').val();
        const kelas_id = $('#absensiKelas').val();
        const sesi = this.state.absensiSesi || 1;
        const absensi = [];

        $('#absensiDataTable tbody tr').each(function() {
            const $checked = $(this).find('input[type=radio]:checked');
            const $ketInput = $(this).find('.abs-keterangan');
            if ($checked.length) {
                const name = $checked.attr('name');
                const studentId = name.replace('abs_', '');
                absensi.push({
                    student_id: parseInt(studentId),
                    status: $checked.val(),
                    keterangan: $ketInput.val() || ''
                });
            }
        });

        if (!absensi.length) { EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Tidak ada data absensi.' }); return; }

        this.api('absensi.php?action=save', { method: 'POST', data: { tanggal, kelas_id: parseInt(kelas_id), jam_ke: sesi, absensi } }).done(res => {
            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
        }).fail(xhr => { EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menyimpan.' }); });
    },

    loadAbsensiRekapTable() {
        const kelas_id = $('#rekapAbsensiKelas').val() || 0;
        const tgl_awal = $('#rekapTglAwal').val();
        const tgl_akhir = $('#rekapTglAkhir').val();

        $('#rekapAbsensiTableWrapper').html('<div class="skeleton-module" style="height:250px;"></div>');

        this.api(`absensi.php?action=rekap&kelas_id=${kelas_id}&tanggal_awal=${tgl_awal}&tanggal_akhir=${tgl_akhir}`).done(res => {
            const data = res.data ? res.data.rekap || [] : [];

            if (!data.length) {
                $('#rekapAbsensiTableWrapper').html(`<div class="acad-empty"><h3>Tidak Ada Data Rekap</h3><p>Tidak ada data absensi pada rentang tanggal ini.</p></div>`);
                return;
            }

            const rows = data.map((r, idx) => `
                <tr>
                    <td style="text-align:center;">${idx + 1}</td>
                    <td>${this.escapeHtml(r.nis)}</td>
                    <td><strong>${this.escapeHtml(r.nama)}</strong></td>
                    <td><span class="badge badge-info">${this.escapeHtml(r.kelas)}</span></td>
                    <td><span style="color:#10B981; font-weight:600;">${r.hadir}</span></td>
                    <td><span style="color:#F59E0B; font-weight:600;">${r.terlambat}</span></td>
                    <td><span style="color:#3B82F6; font-weight:600;">${r.sakit}</span></td>
                    <td><span style="color:#8B5CF6; font-weight:600;">${r.izin}</span></td>
                    <td><span style="color:#EF4444; font-weight:600;">${r.alpha}</span></td>
                    <td><strong>${r.total_hadir}</strong></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="flex:1; background:#e2e8f0; border-radius:4px; height:8px; overflow:hidden;">
                                <div style="width:${r.persentase}%; background:${r.persentase >= 80 ? '#10B981' : (r.persentase >= 60 ? '#F59E0B' : '#EF4444')}; height:100%;"></div>
                            </div>
                            <span style="font-size:0.8rem; font-weight:600;">${r.persentase}%</span>
                        </div>
                    </td>
                </tr>
            `).join('');

            $('#rekapAbsensiTableWrapper').html(`
                <div class="data-table-wrapper">
                    <table class="data-table" id="tableExportRekapAbsensi">
                        <thead>
                            <tr>
                                <th width="40" style="text-align:center;">No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Hadir</th>
                                <th>Terlambat</th>
                                <th>Sakit</th>
                                <th>Izin</th>
                                <th>Alpha</th>
                                <th>Total Kehadiran</th>
                                <th>% Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `);
        });
    },

    exportAbsensiExcel() {
        const table = document.getElementById('tableExportRekapAbsensi');
        if (!table) {
            EModal.toast({ type: 'warning', title: 'Kosong', message: 'Tidak ada data rekap untuk diekspor.' });
            return;
        }

        const tgl_awal = $('#rekapTglAwal').val();
        const tgl_akhir = $('#rekapTglAkhir').val();
        const wb = XLSX.utils.table_to_book(table, { sheet: "Rekap Absensi Siswa" });
        XLSX.writeFile(wb, `Rekap_Absensi_Siswa_${tgl_awal}_sd_${tgl_akhir}.xlsx`);
    },

    showSettingWaktuModal() {
        this.api('absensi.php?action=get_settings').done(res => {
            const data = res.data || {};
            const curMasuk = data.waktu_terlambat || '06:30';
            const curIstMulai = data.waktu_istirahat_mulai || '09:30';
            const curIstSelesai = data.waktu_istirahat_selesai || '10:15';
            const curPulang = data.waktu_pulang || '15:30';
            const curPulangMulai = data.waktu_pulang_mulai || '13:30';

            const idMasuk = 'settingMasukSiswa_' + Date.now();
            const idIstMulai = 'settingIstMulaiSiswa_' + Date.now();
            const idIstSelesai = 'settingIstSelesaiSiswa_' + Date.now();
            const idPulang = 'settingPulangSiswa_' + Date.now();
            const idPulangMulai = 'settingPulangMulaiSiswa_' + Date.now();

            EModal.form({
                title: '⚙️ Pengaturan Jam Absensi Siswa (3 Sesi)',
                size: 'md',
                form: `
                    <!-- Sesi 1: Masuk -->
                    <div style="background:#f8fafc; padding:12px 14px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:14px;">
                        <h4 style="margin:0 0 8px 0; color:#1e293b; font-size:0.95rem;">🌅 Sesi Absen Masuk</h4>
                        <div class="form-group-acad" style="margin-bottom:0;">
                            <label class="form-label-acad">Batas Jam Masuk / Terlambat (Format HH:MM 24 Jam)</label>
                            <input type="text" class="form-input-acad" id="${idMasuk}" value="${curMasuk}" placeholder="06:30" maxlength="5">
                            <small class="text-muted" style="margin-top:4px; display:block;">Siswa tap setelah jam ini otomatis berstatus <strong>Terlambat</strong>.</small>
                        </div>
                    </div>

                    <!-- Sesi 2: Istirahat -->
                    <div style="background:#f8fafc; padding:12px 14px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:14px;">
                        <h4 style="margin:0 0 8px 0; color:#1e293b; font-size:0.95rem;">☕ Sesi Absen Istirahat</h4>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div class="form-group-acad" style="margin-bottom:0;">
                                <label class="form-label-acad">Jam Mulai Istirahat</label>
                                <input type="text" class="form-input-acad" id="${idIstMulai}" value="${curIstMulai}" placeholder="09:30" maxlength="5">
                            </div>
                            <div class="form-group-acad" style="margin-bottom:0;">
                                <label class="form-label-acad">Jam Selesai Istirahat</label>
                                <input type="text" class="form-input-acad" id="${idIstSelesai}" value="${curIstSelesai}" placeholder="10:15" maxlength="5">
                            </div>
                        </div>
                        <small class="text-muted" style="margin-top:4px; display:block;">Tap mesin pada rentang ini ditampung sebagai <strong>Absen Istirahat</strong>.</small>
                    </div>

                    <!-- Sesi 3: Pulang -->
                    <div style="background:#f8fafc; padding:12px 14px; border:1px solid #e2e8f0; border-radius:8px;">
                        <h4 style="margin:0 0 8px 0; color:#1e293b; font-size:0.95rem;">🏠 Sesi Absen Pulang</h4>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div class="form-group-acad" style="margin-bottom:0;">
                                <label class="form-label-acad">Jam Batas Pulang Sekolah</label>
                                <input type="text" class="form-input-acad" id="${idPulang}" value="${curPulang}" placeholder="15:30" maxlength="5">
                            </div>
                            <div class="form-group-acad" style="margin-bottom:0;">
                                <label class="form-label-acad">Mulai Tap Mesin Pulang</label>
                                <input type="text" class="form-input-acad" id="${idPulangMulai}" value="${curPulangMulai}" placeholder="13:30" maxlength="5">
                            </div>
                        </div>
                        <small class="text-muted" style="margin-top:4px; display:block;">Tap mesin mulai jam ini ditampung sebagai <strong>Jam Pulang</strong> siswa.</small>
                    </div>
                `,
                confirmText: 'Simpan Semua Pengaturan',
                cancelText: 'Batal',
                onConfirm: () => {
                    const masuk = $('#' + idMasuk).val().trim();
                    const istMulai = $('#' + idIstMulai).val().trim();
                    const istSelesai = $('#' + idIstSelesai).val().trim();
                    const pulang = $('#' + idPulang).val().trim();
                    const pulangMulai = $('#' + idPulangMulai).val().trim();

                    const timeRegex = /^([01]\d|2[0-3]):([0-5]\d)$/;
                    if (!timeRegex.test(masuk) || !timeRegex.test(istMulai) || !timeRegex.test(istSelesai) || !timeRegex.test(pulang) || !timeRegex.test(pulangMulai)) {
                        EModal.toast({ type: 'error', title: 'Format Salah', message: 'Harap gunakan format 24 jam yang valid (HH:MM), contoh: 06:30 atau 15:30' });
                        return false;
                    }

                    const fd = new FormData();
                    fd.append('waktu_terlambat', masuk);
                    fd.append('waktu_istirahat_mulai', istMulai);
                    fd.append('waktu_istirahat_selesai', istSelesai);
                    fd.append('waktu_pulang', pulang);
                    fd.append('waktu_pulang_mulai', pulangMulai);

                    this.api('absensi.php?action=save_settings', { 
                        method: 'POST', 
                        data: fd,
                        contentType: false,
                        processData: false
                    }).done(res => {
                        EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                        this.state.siswaSettings = res.data;
                        this.updateAbsensiBannerText();
                        if ($('#absensiKelas').val()) {
                            this.loadAbsensiTable();
                        }
                        EModal.closeAll();
                    }).fail(xhr => {
                        EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menyimpan.' });
                    });
                    
                    return false;
                }
            });
        });
    },

    // ==================== ABSENSI GURU VIEW (3 SESI) ====================
    renderAbsensiGuru($container) {
        const today = new Date().toISOString().split('T')[0];
        const firstDayOfMonth = today.substring(0, 8) + '01';
        this.state.absensiGuruSesi = 'masuk'; // 'masuk', 'istirahat', 'pulang'

        $container.html(`
            <div class="acad-card">
                <div class="acad-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3>📋 Absensi Guru</h3>
                        <p class="acad-subtitle">Terintegrasi otomatis dengan mesin E-Absen & Rekapitulasi Kehadiran 3 Sesi (Masuk, Istirahat, Pulang).</p>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button class="btn-acad btn-acad-outline" id="btnWaGroupGuru" onclick="Curriculum.sendWaGroupAbsenGuruActive()" style="border-color:#10B981; color:#059669;" title="Kirim Laporan WA ke Grup">
                            📲 <span id="lblBtnWaGuru">Kirim WA Masuk</span>
                        </button>
                        <button class="btn-acad btn-acad-outline" onclick="Curriculum.showSettingWaktuGuruModal()">
                            ⚙️ Setting Jam & WA Guru
                        </button>
                    </div>
                </div>
                <div class="acad-card-body">
                    <!-- Main Navigation Tabs -->
                    <div style="margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; display: flex; gap: 16px;">
                        <button class="absensi-guru-tab-btn active" data-tab="harian" onclick="Curriculum.switchAbsensiGuruTab('harian')" style="background: none; border: none; padding: 10px 16px; cursor: pointer; border-bottom: 2px solid #7C3AED; font-weight: 600; color: #7C3AED;">
                            📌 Absensi Harian
                        </button>
                        <button class="absensi-guru-tab-btn" data-tab="rekap" onclick="Curriculum.switchAbsensiGuruTab('rekap')" style="background: none; border: none; padding: 10px 16px; cursor: pointer; border-bottom: 2px solid transparent; font-weight: 600; color: #64748b;">
                            📊 Rekapitulasi Kehadiran
                        </button>
                    </div>

                    <!-- Tab 1: Absensi Harian (3 Sesi) -->
                    <div id="absensiGuruTabHarian" class="absensi-guru-tab-content">
                        <!-- 3 Sesi Sub-Tabs -->
                        <div class="absensi-guru-sesi-nav" style="display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap;">
                            <button class="btn-acad absensi-guru-sesi-btn active" data-sesi="masuk" onclick="Curriculum.switchAbsensiGuruSesi('masuk')" style="padding:8px 18px; border-radius:20px; font-weight:600; font-size:0.875rem; background:#7C3AED; color:white; border:1px solid #7C3AED; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all 0.2s;">
                                🌅 <span>Absen Masuk</span>
                            </button>
                            <button class="btn-acad absensi-guru-sesi-btn" data-sesi="istirahat" onclick="Curriculum.switchAbsensiGuruSesi('istirahat')" style="padding:8px 18px; border-radius:20px; font-weight:600; font-size:0.875rem; background:#F1F5F9; color:#475569; border:1px solid #CBD5E1; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all 0.2s;">
                                ☕ <span>Absen Istirahat</span>
                            </button>
                            <button class="btn-acad absensi-guru-sesi-btn" data-sesi="pulang" onclick="Curriculum.switchAbsensiGuruSesi('pulang')" style="padding:8px 18px; border-radius:20px; font-weight:600; font-size:0.875rem; background:#F1F5F9; color:#475569; border:1px solid #CBD5E1; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all 0.2s;">
                                🏠 <span>Absen Pulang</span>
                            </button>
                        </div>

                        <div class="filter-bar">
                            <div class="filter-item"><label>Tanggal</label><input type="date" class="form-input-acad" id="absensiGuruTanggal" value="${today}"></div>
                            <div class="filter-item" style="display:flex; flex-direction:row; align-items:flex-end;"><button class="btn-acad btn-acad-primary" onclick="Curriculum.loadAbsensiGuruTable()">🔍 Tampilkan</button></div>
                        </div>

                        <div id="absensiGuruInfoBanner" style="margin-bottom:16px; padding:12px 16px; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:10px; color:#1E40AF; font-size:0.875rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                            <span id="absensiGuruBannerText">📌 Sesi <strong>Absen Masuk Guru</strong>: Menyelaraskan tap pagi E-Absen. <strong>Batas Terlambat: <span id="lblJamTerlambatGuru">06:30</span></strong></span>
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
            if (res.data) {
                this.state.guruSettings = res.data;
                this.updateAbsensiGuruBannerText();
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

    switchAbsensiGuruSesi(sesi) {
        this.state.absensiGuruSesi = sesi;
        $('.absensi-guru-sesi-btn').each(function() {
            const btnSesi = $(this).data('sesi');
            if (btnSesi === sesi) {
                $(this).addClass('active').css({ 'background': '#7C3AED', 'color': 'white', 'border-color': '#7C3AED' });
            } else {
                $(this).removeClass('active').css({ 'background': '#F1F5F9', 'color': '#475569', 'border-color': '#CBD5E1' });
            }
        });

        // Update WA button label
        const labels = { 'masuk': 'Kirim WA Masuk', 'istirahat': 'Kirim WA Istirahat', 'pulang': 'Kirim WA Pulang' };
        $('#lblBtnWaGuru').text(labels[sesi] || 'Kirim WA');

        this.updateAbsensiGuruBannerText();
        this.loadAbsensiGuruTable();
    },

    updateAbsensiGuruBannerText() {
        const sesi = this.state.absensiGuruSesi || 'masuk';
        const cfg = this.state.guruSettings || {};
        const jamMasuk = cfg.waktu_terlambat || '06:30';
        const jamIstMulai = cfg.waktu_istirahat_mulai || '12:00';
        const jamIstSelesai = cfg.waktu_istirahat_selesai || '13:00';
        const jamPulang = cfg.waktu_pulang || '15:30';

        let bannerHtml = '';
        if (sesi === 'masuk') {
            bannerHtml = `<span>🌅 Sesi <strong>Absen Masuk Guru</strong>: Menyelaraskan tap pagi E-Absen. <strong>Batas Masuk / Terlambat: <span id="lblJamTerlambatGuru">${jamMasuk}</span></strong></span>`;
        } else if (sesi === 'istirahat') {
            bannerHtml = `<span>☕ Sesi <strong>Absen Istirahat Guru</strong>: Menyelaraskan tap istirahat E-Absen. <strong>Waktu Istirahat: <span id="lblJamIstirahatGuru">${jamIstMulai} - ${jamIstSelesai}</span></strong></span>`;
        } else if (sesi === 'pulang') {
            bannerHtml = `<span>🏠 Sesi <strong>Absen Pulang Guru</strong>: Menyelaraskan tap pulang E-Absen. <strong>Jam Batas Pulang: <span id="lblJamPulangGuru">${jamPulang}</span></strong></span>`;
        }
        $('#absensiGuruBannerText').html(bannerHtml);
    },

    sendWaGroupAbsenGuruActive() {
        const sesi = this.state.absensiGuruSesi || 'masuk';
        this.sendWaGroupAbsenGuru(sesi);
    },

    sendWaGroupAbsenGuru(tipe = 'masuk') {
        const tanggal = $('#absensiGuruTanggal').val() || new Date().toISOString().split('T')[0];
        const labelMap = { 'masuk': 'Absen Pagi', 'istirahat': 'Absen Istirahat', 'pulang': 'Absen Pulang' };
        const label = labelMap[tipe] || 'Absen';
        
        EModal.confirm({
            title: `Kirim ${label} ke Grup WA`,
            message: `Kirim laporan <strong>${label} Guru</strong> tanggal <strong>${tanggal}</strong> ke Grup WA yang dikonfigurasi?`,
            type: 'info',
            confirmText: 'Ya, Kirim Sekarang',
            onConfirm: () => {
                const loader = EModal.loading(`Mengirim laporan ${label} ke Grup WA...`);
                this.api('absensi_guru.php?action=send_wa_group', {
                    method: 'POST',
                    data: { tanggal, tipe }
                }).done(res => {
                    EModal.close(loader);
                    EModal.toast({ type: 'success', title: 'Terkirim', message: res.message });
                }).fail(xhr => {
                    EModal.close(loader);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal mengirim pesan.' });
                });
            }
        });
    },

    setAllAbsensiGuruStatus(status) {
        $('.absensi-guru-status').val(status).trigger('change');
        EModal.toast({ type: 'info', message: `Semua guru di-set ${status === 'H' ? 'Hadir' : status}.` });
    },

    loadAbsensiGuruTable() {
        const tanggal = $('#absensiGuruTanggal').val();
        const sesi = this.state.absensiGuruSesi || 'masuk';
        if (!tanggal) return;

        const sesiLabels = { 'masuk': 'Masuk', 'istirahat': 'Istirahat', 'pulang': 'Pulang' };
        const labelSesi = sesiLabels[sesi] || 'Harian';

        const $wrapper = $('#absensiGuruTableWrapper');
        $wrapper.html('<div style="padding:20px; text-align:center;">Memuat data absensi...</div>');

        this.api(`absensi_guru.php?action=list&tanggal=${tanggal}&sesi=${sesi}`).done(res => {
            if (!res.success) {
                $wrapper.html(`<div style="color:red; padding:20px;">Error: ${res.message}</div>`);
                return;
            }

            if (res.data) {
                this.state.guruSettings = res.data;
                this.updateAbsensiGuruBannerText();
            }

            const data = res.data.teachers || [];
            if (data.length === 0) {
                $wrapper.html('<div style="padding:20px; text-align:center; color:gray;">Tidak ada data guru aktif.</div>');
                return;
            }

            let trs = '';
            data.forEach((t, i) => {
                const hasScan = t.jam_scan !== null;
                const statusColor = t.status === 'H' ? '#10B981' : (t.status === 'T' ? '#F59E0B' : (t.status === 'A' ? '#EF4444' : '#3B82F6'));
                
                let scanBadge = '';
                if (sesi === 'masuk') {
                    if (hasScan) {
                        if (t.status === 'T') {
                            scanBadge = `<span style="font-size:0.75rem; padding:2px 8px; background:#fef3c7; color:#92400e; border:1px solid #fcd34d; border-radius:4px; font-weight:600;">${this.escapeHtml(t.scan_info)}</span>`;
                        } else {
                            scanBadge = `<span style="font-size:0.75rem; padding:2px 8px; background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; border-radius:4px; font-weight:600;">${this.escapeHtml(t.scan_info)}</span>`;
                        }
                    } else {
                        scanBadge = `<span style="font-size:0.75rem; padding:2px 8px; background:#f8fafc; color:#64748b; border:1px solid #cbd5e1; border-radius:4px;">Belum Scan Mesin</span>`;
                    }
                } else {
                    // Sesi istirahat & pulang: Hanya tampilkan jam saja
                    if (hasScan) {
                        const timeStr = t.scan_info || t.jam_scan.substring(0, 5);
                        scanBadge = `<span style="font-size:0.8rem; font-weight:600; padding:2px 8px; background:#f8fafc; color:#1e293b; border:1px solid #cbd5e1; border-radius:4px; display:inline-flex; align-items:center; gap:4px;">🕒 ${this.escapeHtml(timeStr)}</span>`;
                    } else {
                        scanBadge = `<span style="font-size:0.75rem; padding:2px 8px; background:#f8fafc; color:#94a3b8; border:1px dashed #cbd5e1; border-radius:4px;">Belum Scan</span>`;
                    }
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
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div style="display:flex; gap:8px;">
                        <button class="btn-acad btn-acad-outline btn-acad-sm" onclick="Curriculum.setAllAbsensiGuruStatus('H')" style="font-size:0.8rem; border-color:#10B981; color:#059669;">
                            ✅ Set Semua Hadir
                        </button>
                        <button class="btn-acad btn-acad-outline btn-acad-sm" onclick="Curriculum.loadAbsensiGuruTable()" style="font-size:0.8rem;">
                            🔄 Reset Sesuai Mesin
                        </button>
                    </div>
                    <div style="font-size:0.85rem; color:#64748B;">Total: <strong>${data.length} Guru</strong></div>
                </div>
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:50px; text-align:center;">No</th>
                                <th style="width:120px;">Kode Guru</th>
                                <th>Nama Guru & Info Scan (${labelSesi})</th>
                                <th style="width:150px;">Status</th>
                                <th>Keterangan (Manual)</th>
                            </tr>
                        </thead>
                        <tbody>${trs}</tbody>
                    </table>
                </div>
                <div style="margin-top:16px; text-align:right;">
                    <button class="btn-acad btn-acad-primary" onclick="Curriculum.saveAbsensiGuru()">💾 Simpan Data Absensi Guru ${labelSesi}</button>
                </div>
            `);
        });
    },

    saveAbsensiGuru() {
        const tanggal = $('#absensiGuruTanggal').val();
        const sesi = this.state.absensiGuruSesi || 'masuk';
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
            data: { tanggal, sesi, absensi }
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
                <div class="data-table-wrapper">
                    <table class="data-table" id="tableExportRekapAbsensiGuru">
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
        XLSX.writeFile(wb, `Rekap_Absensi_Guru_${tgl_awal}_sd_${tgl_akhir}.xlsx`);
    },

    showSettingWaktuGuruModal() {
        this.api('absensi_guru.php?action=get_settings').done(res => {
            const data = res.data || {};
            const curMasuk = data.waktu_terlambat || '06:30';
            const curIstMulai = data.waktu_istirahat_mulai || '12:00';
            const curIstSelesai = data.waktu_istirahat_selesai || '13:00';
            const curPulang = data.waktu_pulang || '15:30';
            const curMulPulang = data.wa_mulai_pulang || '13:00';
            const curCutMasuk = data.wa_cutoff_masuk || '06:30';
            const curCutPulang = data.wa_cutoff_pulang || '17:00';

            const uniqueId = 'settingJamGuru_' + Date.now();
            const idIstMulai = 'settingIstMulaiGuru_' + Date.now();
            const idIstSelesai = 'settingIstSelesaiGuru_' + Date.now();
            const idPulang = 'settingPulangGuru_' + Date.now();
            const idCutoffMasuk = 'settingCutoffMasuk_' + Date.now();
            const idCutoffPulang = 'settingCutoffPulang_' + Date.now();
            const idMulaiPulang = 'settingMulaiPulang_' + Date.now();

            EModal.form({
                title: '⚙️ Pengaturan Jam Absensi & Broadcast WA Guru (3 Sesi)',
                size: 'md',
                form: `
                    <!-- Sesi 1: Masuk -->
                    <div style="background:#f8fafc; padding:12px 14px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:14px;">
                        <h4 style="margin:0 0 8px 0; color:#1e293b; font-size:0.95rem;">🌅 Sesi Absen Masuk Guru</h4>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div class="form-group-acad" style="margin-bottom:0;">
                                <label class="form-label-acad">Batas Jam Masuk / Terlambat</label>
                                <input type="text" class="form-input-acad" id="${uniqueId}" value="${curMasuk}" placeholder="06:30" maxlength="5">
                            </div>
                            <div class="form-group-acad" style="margin-bottom:0;">
                                <label class="form-label-acad">Batas Auto WA Pagi</label>
                                <input type="text" class="form-input-acad" id="${idCutoffMasuk}" value="${curCutMasuk}" placeholder="06:30" maxlength="5">
                            </div>
                        </div>
                        <small class="text-muted" style="margin-top:4px; display:block;">Guru tap setelah jam ini otomatis berstatus <strong>Terlambat</strong>.</small>
                    </div>

                    <!-- Sesi 2: Istirahat -->
                    <div style="background:#f8fafc; padding:12px 14px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:14px;">
                        <h4 style="margin:0 0 8px 0; color:#1e293b; font-size:0.95rem;">☕ Sesi Absen Istirahat Guru</h4>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div class="form-group-acad" style="margin-bottom:0;">
                                <label class="form-label-acad">Jam Mulai Istirahat</label>
                                <input type="text" class="form-input-acad" id="${idIstMulai}" value="${curIstMulai}" placeholder="12:00" maxlength="5">
                            </div>
                            <div class="form-group-acad" style="margin-bottom:0;">
                                <label class="form-label-acad">Jam Selesai Istirahat</label>
                                <input type="text" class="form-input-acad" id="${idIstSelesai}" value="${curIstSelesai}" placeholder="13:00" maxlength="5">
                            </div>
                        </div>
                        <small class="text-muted" style="margin-top:4px; display:block;">Tap mesin pada rentang ini ditampung sebagai <strong>Absen Istirahat Guru</strong>.</small>
                    </div>

                    <!-- Sesi 3: Pulang -->
                    <div style="background:#f8fafc; padding:12px 14px; border:1px solid #e2e8f0; border-radius:8px;">
                        <h4 style="margin:0 0 8px 0; color:#1e293b; font-size:0.95rem;">🏠 Sesi Absen Pulang Guru</h4>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:10px;">
                            <div class="form-group-acad" style="margin-bottom:0;">
                                <label class="form-label-acad">Jam Pulang Guru</label>
                                <input type="text" class="form-input-acad" id="${idPulang}" value="${curPulang}" placeholder="15:30" maxlength="5">
                            </div>
                            <div class="form-group-acad" style="margin-bottom:0;">
                                <label class="form-label-acad">Mulai Tap Pulang Mesin</label>
                                <input type="text" class="form-input-acad" id="${idMulaiPulang}" value="${curMulPulang}" placeholder="13:00" maxlength="5">
                            </div>
                        </div>
                        <div class="form-group-acad" style="margin-bottom:0;">
                            <label class="form-label-acad">Waktu Auto Broadcast WA Pulang</label>
                            <input type="text" class="form-input-acad" id="${idCutoffPulang}" value="${curCutPulang}" placeholder="17:00" maxlength="5">
                            <small class="text-muted" style="margin-top:4px; display:block;">Laporan WA sore dikirim otomatis ke grup pada jam ini.</small>
                        </div>
                    </div>
                `,
                confirmText: 'Simpan Semua Pengaturan',
                cancelText: 'Batal',
                onConfirm: () => {
                    const waktu = $('#' + uniqueId).val().trim();
                    const istMulai = $('#' + idIstMulai).val().trim();
                    const istSelesai = $('#' + idIstSelesai).val().trim();
                    const pulang = $('#' + idPulang).val().trim();
                    const cutMasuk = $('#' + idCutoffMasuk).val().trim();
                    const cutPulang = $('#' + idCutoffPulang).val().trim();
                    const mulPulang = $('#' + idMulaiPulang).val().trim();

                    const timeRegex = /^([01]\d|2[0-3]):([0-5]\d)$/;
                    if (!timeRegex.test(waktu) || !timeRegex.test(istMulai) || !timeRegex.test(istSelesai) || !timeRegex.test(pulang)) {
                        EModal.toast({ type: 'error', title: 'Format Salah', message: 'Harap gunakan format 24 jam yang valid (HH:MM), contoh: 06:30 atau 15:30' });
                        return false;
                    }

                    const fd = new FormData();
                    fd.append('waktu_terlambat', waktu);
                    fd.append('waktu_istirahat_mulai', istMulai);
                    fd.append('waktu_istirahat_selesai', istSelesai);
                    fd.append('waktu_pulang', pulang);
                    if (cutMasuk) fd.append('wa_cutoff_masuk', cutMasuk);
                    if (cutPulang) fd.append('wa_cutoff_pulang', cutPulang);
                    if (mulPulang) fd.append('wa_mulai_pulang', mulPulang);

                    this.api('absensi_guru.php?action=save_settings', { 
                        method: 'POST', 
                        data: fd,
                        contentType: false,
                        processData: false
                    }).done(res => {
                        EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                        this.state.guruSettings = res.data;
                        this.updateAbsensiGuruBannerText();
                        this.loadAbsensiGuruTable();
                        if ($('#absensiGuruTabRekap').is(':visible')) {
                            this.loadAbsensiGuruRekapTable();
                        }
                        EModal.closeAll();
                    }).fail(xhr => {
                        EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menyimpan.' });
                    });
                    
                    return false;
                }
            });
        });
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
                <div class="acad-card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                    <div>
                        <h3 style="margin:0;font-size:1.15rem;font-weight:700">📖 Buku Penghubung Siswa</h3>
                        <p class="acad-subtitle" style="margin-top:4px">Catatan perilaku, prestasi, kedisiplinan, dan konsultasi siswa (terhubung ke Portal Siswa).</p>
                    </div>
                    <div class="acad-toolbar" style="display:flex;gap:8px;flex-wrap:wrap">
                        <button class="btn-acad btn-acad-outline" onclick="Curriculum.openManageJenisCatatanModal()" style="display:flex;align-items:center;gap:6px;padding:7px 14px;font-size:13px;border-radius:6px;font-weight:600">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                            Kelola Jenis Catatan
                        </button>
                        <button class="btn-acad btn-acad-primary" onclick="Curriculum.showBukuForm()" style="display:flex;align-items:center;gap:6px;padding:7px 14px;font-size:13px;border-radius:6px;font-weight:600">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Catatan
                        </button>
                    </div>
                </div>
                <div class="acad-card-body">
                    <div class="filter-bar" style="margin-bottom:16px">
                        <div class="filter-item"><label>Kelas</label><select class="form-select-acad" id="bukuKelas" onchange="Curriculum.loadBukuTable()"><option value="">Semua Kelas</option></select></div>
                        <div class="filter-item"><label>Jenis Catatan</label><select class="form-select-acad" id="bukuJenis" onchange="Curriculum.loadBukuTable()"><option value="">Semua Jenis Catatan</option></select></div>
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

        // Load dynamic Jenis Catatan options
        this._populateBukuJenisFilter('#bukuJenis');

        this.loadBukuTable();
    },

    _populateBukuJenisFilter(selector, selectedVal = '') {
        this.api('buku_penghubung.php?action=list_types').done(res => {
            if (res && res.success && Array.isArray(res.data)) {
                const $select = $(selector);
                if (!$select.length) return;
                let opts = '<option value="">Semua Jenis Catatan</option>';
                res.data.forEach(t => {
                    const sel = (selectedVal && selectedVal === t.nama_jenis) ? 'selected' : '';
                    opts += `<option value="${this.escapeHtml(t.nama_jenis)}" ${sel}>${this.escapeHtml(t.nama_jenis)}</option>`;
                });
                $select.html(opts);
            }
        });
    },

    loadBukuTable() {
        const kelas = $('#bukuKelas').val() || '';
        const jenis = $('#bukuJenis').val() || '';
        let url = 'buku_penghubung.php?action=list';
        if (kelas) url += `&kelas_id=${kelas}`;
        if (jenis) url += `&jenis=${encodeURIComponent(jenis)}`;

        this.api(url).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#bukuTableWrapper').html(`<div class="acad-empty"><h3>Belum Ada Catatan</h3><p>Tambahkan catatan baru untuk siswa.</p></div>`);
                return;
            }
            const rows = data.map((b, idx) => {
                const safeCatatan = this.escapeHtml(b.catatan).replace(/'/g, "\\'");
                const safeJenis = this.escapeHtml(b.jenis).replace(/'/g, "\\'");
                const badgeClass = b.warna_badge || 'badge-info';
                return `
                <tr class="fade-in" style="animation-delay:${idx*0.03}s">
                    <td>${this.escapeHtml(b.tanggal)}</td>
                    <td><strong>${this.escapeHtml(b.nama_siswa)}</strong><br><span class="text-muted" style="font-size:0.75rem;">NIS: ${this.escapeHtml(b.nis || '-')}</span></td>
                    <td>${this.escapeHtml(b.nama_kelas || '-')}</td>
                    <td><span class="badge ${badgeClass}">${this.escapeHtml(b.jenis)}</span></td>
                    <td style="max-width:280px;">
                        <div style="white-space:pre-wrap;">${this.escapeHtml(b.catatan)}</div>
                        ${b.dicatat_nama ? `<div class="text-muted" style="font-size:0.72rem; margin-top:4px;">✍️ Dicatat: <strong>${this.escapeHtml(b.dicatat_nama)}</strong></div>` : ''}
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <button class="btn-icon" title="Edit" onclick="Curriculum.editBuku(${b.id}, '${safeCatatan}', '${safeJenis}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="btn-icon danger" title="Hapus" onclick="Curriculum.deleteBuku(${b.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>
            `;
            }).join('');
            $('#bukuTableWrapper').html(`<div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Jenis</th><th>Catatan</th><th style="width:100px;">Aksi</th></tr></thead><tbody>${rows}</tbody></table></div>`);
        }).fail(xhr => {
            let errMsg = 'Gagal memuat catatan buku penghubung.';
            if (xhr.responseJSON && xhr.responseJSON.message) errMsg = xhr.responseJSON.message;
            $('#bukuTableWrapper').html(`<div class="acad-empty" style="color:#ef4444;"><h3>Gagal Memuat Data</h3><p>${this.escapeHtml(errMsg)}</p><button class="btn-acad btn-acad-outline" onclick="Curriculum.loadBukuTable()" style="margin-top:10px;">🔄 Coba Lagi</button></div>`);
        });
    },

    showBukuForm() {
        const today = new Date().toISOString().split('T')[0];
        Promise.all([
            this.api('kelas.php?action=list'),
            this.api('buku_penghubung.php?action=list_types')
        ]).then(([resK, resT]) => {
            const kelasList = resK.data || [];
            const typesList = resT.data || [];
            this._renderBukuModal(kelasList, typesList, today);
        }).catch(() => {
            this._renderBukuModal([], [], today);
        });
    },

    _renderBukuModal(kelasList, typesList, today) {
        let kelasOptionsHtml = '';
        if (kelasList && kelasList.length) {
            kelasOptionsHtml = kelasList.map(k => `
                <div class="acad-cs-option" data-id="${k.id}" data-nama="${this.escapeHtml(k.nama_kelas)}">
                    <div class="acad-cs-opt-main">${this.escapeHtml(k.nama_kelas)}</div>
                </div>
            `).join('');
            kelasOptionsHtml += '<div id="csKelasEmpty" class="acad-cs-empty" style="display:none;">Kelas tidak ditemukan</div>';
        } else {
            kelasOptionsHtml = '<div class="acad-cs-empty">Tidak ada data kelas</div>';
        }
        
        let typesOpts = '';
        if (typesList && typesList.length) {
            typesOpts = typesList.map(t => `<option value="${this.escapeHtml(t.nama_jenis)}">${this.escapeHtml(t.nama_jenis)}</option>`).join('');
        } else {
            typesOpts = `
                <option value="Keterlambatan">Keterlambatan</option>
                <option value="Pelanggaran">Pelanggaran</option>
                <option value="Prestasi">Prestasi</option>
                <option value="Screening">Screening</option>
                <option value="Konsultasi">Konsultasi / Bimbingan</option>
            `;
        }

        EModal.form({
            title: 'Tambah Catatan Buku Penghubung',
            size: 'md',
            form: `
                <!-- Field Kelas dengan Custom Search Dropdown -->
                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">Kelas <span class="text-danger">*</span></label>
                    <input type="hidden" id="fBukuKelas" value="">
                    <div class="acad-cs-container" id="csKelasContainer">
                        <div class="acad-cs-btn" id="csKelasBtn">
                            <span id="csKelasSelectedText" style="color:#64748b; font-size:0.9rem;">-- Cari / Pilih Kelas --</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <div class="acad-cs-dropdown" id="csKelasDropdown">
                            <div class="acad-cs-search-wrap">
                                <input type="text" id="csKelasSearchInput" class="acad-cs-search-input" placeholder="🔍 Cari kelas..." autocomplete="off">
                            </div>
                            <div class="acad-cs-list" id="csKelasList">
                                ${kelasOptionsHtml}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Field Siswa dengan Custom Search Dropdown -->
                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">Siswa <span class="text-danger">*</span></label>
                    <input type="hidden" id="fBukuSiswa" value="">
                    <div class="acad-cs-container" id="csSiswaContainer">
                        <div class="acad-cs-btn disabled" id="csSiswaBtn">
                            <span id="csSiswaSelectedText" style="color:#94a3b8; font-size:0.9rem;">Pilih kelas terlebih dahulu...</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <div class="acad-cs-dropdown" id="csSiswaDropdown">
                            <div class="acad-cs-search-wrap">
                                <input type="text" id="csSiswaSearchInput" class="acad-cs-search-input" placeholder="🔍 Cari nama atau NIS siswa..." autocomplete="off">
                            </div>
                            <div class="acad-cs-list" id="csSiswaList">
                                <div class="acad-cs-empty">Pilih kelas terlebih dahulu</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">Jenis Catatan <span class="text-danger">*</span></label>
                    <select class="form-select-acad" id="fBukuJenis">
                        ${typesOpts}
                    </select>
                </div>
                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" class="form-input-acad" id="fBukuTgl" value="${today}">
                </div>
                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">Catatan <span class="text-danger">*</span></label>
                    <textarea class="form-input-acad" id="fBukuCatatan" rows="3" placeholder="Tuliskan catatan kejadian, pelanggaran, prestasi, atau bimbingan..."></textarea>
                </div>
                <div style="font-size:12px;color:var(--text-muted,#94a3b8);background:#f8fafc;padding:10px;border-radius:6px;border:1px solid #e2e8f0">
                    <strong>ℹ️ Info:</strong> Catatan ini akan otomatis muncul pada menu Catatan / Buku Penghubung di <strong>Portal Siswa</strong> yang bersangkutan.
                </div>
            `,
            confirmText: 'Simpan Catatan',
            onOpen: () => {
                // Dropdown Kelas toggle
                $('#csKelasBtn').on('click', function(e) {
                    e.stopPropagation();
                    $('#csSiswaDropdown').hide();
                    $('#csSiswaBtn').removeClass('active');
                    $(this).toggleClass('active');
                    $('#csKelasDropdown').toggle();
                    if ($('#csKelasDropdown').is(':visible')) {
                        $('#csKelasSearchInput').val('').trigger('input').focus();
                    }
                });

                // Filter Pencarian Kelas
                $('#csKelasSearchInput').on('input', function() {
                    const term = $(this).val().toLowerCase().trim();
                    let matchCount = 0;
                    $('#csKelasList .acad-cs-option').each(function() {
                        const text = $(this).text().toLowerCase();
                        const match = text.includes(term);
                        $(this).toggle(match);
                        if (match) matchCount++;
                    });
                    $('#csKelasEmpty').toggle(matchCount === 0);
                });

                // Pilih Option Kelas
                $('#csKelasList').on('click', '.acad-cs-option', (e) => {
                    const $opt = $(e.currentTarget);
                    const id = $opt.data('id');
                    const nama = $opt.data('nama');
                    
                    $('#fBukuKelas').val(id);
                    $('#csKelasSelectedText').html(`<span style="font-weight:600; color:#1e293b;">${this.escapeHtml(nama)}</span>`);
                    $('#csKelasList .acad-cs-option').removeClass('selected');
                    $opt.addClass('selected');
                    $('#csKelasDropdown').hide();
                    $('#csKelasBtn').removeClass('active');

                    // Reset & Load Siswa
                    $('#fBukuSiswa').val('');
                    $('#csSiswaBtn').removeClass('disabled');
                    $('#csSiswaSelectedText').html('<span style="color:#64748b; font-size:0.9rem;">-- Cari / Pilih Siswa --</span>');
                    $('#csSiswaList').html('<div class="acad-cs-empty"><span class="acad-spinner-sm"></span> Memuat data siswa...</div>');

                    this.api(`absensi.php?action=students&kelas_id=${id}`).done(res => {
                        const list = res.data || [];
                        if (!list.length) {
                            $('#csSiswaList').html('<div class="acad-cs-empty">Tidak ada siswa terdaftar di kelas ini</div>');
                            return;
                        }
                        let sHtml = '';
                        list.forEach(s => {
                            sHtml += `
                                <div class="acad-cs-option" data-id="${s.id}" data-nama="${this.escapeHtml(s.nama)}" data-nis="${this.escapeHtml(s.nis || '-')}">
                                    <div class="acad-cs-opt-main">${this.escapeHtml(s.nama)}</div>
                                    <div class="acad-cs-opt-sub">NIS: ${this.escapeHtml(s.nis || '-')}</div>
                                </div>
                            `;
                        });
                        sHtml += '<div id="csSiswaEmpty" class="acad-cs-empty" style="display:none;">Siswa tidak ditemukan</div>';
                        $('#csSiswaList').html(sHtml);
                    }).fail(() => {
                        $('#csSiswaList').html('<div class="acad-cs-empty" style="color:#ef4444;">Gagal memuat data siswa</div>');
                    });
                });

                // Dropdown Siswa toggle
                $('#csSiswaBtn').on('click', function(e) {
                    e.stopPropagation();
                    if ($(this).hasClass('disabled')) {
                        EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Silakan pilih Kelas terlebih dahulu.' });
                        return;
                    }
                    $('#csKelasDropdown').hide();
                    $('#csKelasBtn').removeClass('active');
                    $(this).toggleClass('active');
                    $('#csSiswaDropdown').toggle();
                    if ($('#csSiswaDropdown').is(':visible')) {
                        $('#csSiswaSearchInput').val('').trigger('input').focus();
                    }
                });

                // Filter Pencarian Siswa
                $('#csSiswaSearchInput').on('input', function() {
                    const term = $(this).val().toLowerCase().trim();
                    let matchCount = 0;
                    $('#csSiswaList .acad-cs-option').each(function() {
                        const text = $(this).text().toLowerCase();
                        const match = text.includes(term);
                        $(this).toggle(match);
                        if (match) matchCount++;
                    });
                    $('#csSiswaEmpty').toggle(matchCount === 0);
                });

                // Pilih Option Siswa
                $('#csSiswaList').on('click', '.acad-cs-option', (e) => {
                    const $opt = $(e.currentTarget);
                    const id = $opt.data('id');
                    const nama = $opt.data('nama');
                    const nis = $opt.data('nis');

                    $('#fBukuSiswa').val(id);
                    $('#csSiswaSelectedText').html(`
                        <div>
                            <div style="font-weight:600; font-size:0.9rem; color:#1e293b;">${this.escapeHtml(nama)}</div>
                            <div style="font-size:0.75rem; color:#64748b;">NIS: ${this.escapeHtml(nis)}</div>
                        </div>
                    `);
                    $('#csSiswaList .acad-cs-option').removeClass('selected');
                    $opt.addClass('selected');
                    $('#csSiswaDropdown').hide();
                    $('#csSiswaBtn').removeClass('active');
                });

                // Global outside-click listener
                $(document).off('click.acadCsModal').on('click.acadCsModal', function(e) {
                    if (!$(e.target).closest('#csKelasContainer').length) {
                        $('#csKelasDropdown').hide();
                        $('#csKelasBtn').removeClass('active');
                    }
                    if (!$(e.target).closest('#csSiswaContainer').length) {
                        $('#csSiswaDropdown').hide();
                        $('#csSiswaBtn').removeClass('active');
                    }
                });
            },
            onConfirm: () => {
                const student = $('#fBukuSiswa').val();
                const kelas = $('#fBukuKelas').val();
                const catatan = ($('#fBukuCatatan').val() || '').trim();
                const tgl = $('#fBukuTgl').val() || today;
                const jenis = $('#fBukuJenis').val() || 'Konsultasi';

                if (!kelas) {
                    EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Silakan cari & pilih Kelas terlebih dahulu.' });
                    return false;
                }
                if (!student) {
                    EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Silakan cari & pilih Siswa.' });
                    return false;
                }
                if (!catatan) {
                    EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Catatan tidak boleh kosong.' });
                    return false;
                }

                this.api('buku_penghubung.php?action=create', {
                    method: 'POST',
                    data: {
                        student_id: +student,
                        kelas_id: +kelas,
                        jenis: jenis,
                        tanggal: tgl,
                        catatan: catatan
                    }
                }).done(res => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message || 'Catatan berhasil disimpan.' });
                    this.loadBukuTable();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menyimpan catatan.' });
                });
                return false;
            }
        });
    },

    editBuku(id, catatan, currentJenis) {
        this.api('buku_penghubung.php?action=list_types').done(resT => {
            const typesList = resT.data || [];
            let typesOpts = '';
            if (typesList.length) {
                typesOpts = typesList.map(t => `<option value="${this.escapeHtml(t.nama_jenis)}" ${currentJenis === t.nama_jenis ? 'selected' : ''}>${this.escapeHtml(t.nama_jenis)}</option>`).join('');
            } else {
                typesOpts = `
                    <option value="Keterlambatan" ${currentJenis==='Keterlambatan'?'selected':''}>Keterlambatan</option>
                    <option value="Pelanggaran" ${currentJenis==='Pelanggaran'?'selected':''}>Pelanggaran</option>
                    <option value="Prestasi" ${currentJenis==='Prestasi'?'selected':''}>Prestasi</option>
                    <option value="Screening" ${currentJenis==='Screening'?'selected':''}>Screening</option>
                    <option value="Konsultasi" ${currentJenis==='Konsultasi'?'selected':''}>Konsultasi</option>
                `;
            }

            EModal.form({
                title: 'Edit Catatan Buku Penghubung', size: 'md',
                form: `
                    <div class="form-group-acad mb-3"><label class="form-label-acad">Jenis Catatan</label><select class="form-select-acad" id="fBukuEditJenis">${typesOpts}</select></div>
                    <div class="form-group-acad mb-3"><label class="form-label-acad">Isi Catatan</label><textarea class="form-input-acad" id="fBukuEditCatatan" rows="4">${catatan}</textarea></div>
                `,
                confirmText: 'Simpan Perubahan',
                onConfirm: () => {
                    const updatedCatatan = ($('#fBukuEditCatatan').val() || '').trim();
                    const updatedJenis = $('#fBukuEditJenis').val();
                    if (!updatedCatatan) {
                        EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Catatan tidak boleh kosong.' });
                        return false;
                    }
                    this.api('buku_penghubung.php?action=update', {
                        method: 'POST',
                        data: { id, jenis: updatedJenis, catatan: updatedCatatan }
                    }).done(res => {
                        EModal.closeAll();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: res.message || 'Catatan berhasil diperbarui.' });
                        this.loadBukuTable();
                    }).fail(xhr => {
                        EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal memperbarui catatan.' });
                    });
                    return false;
                }
            });
        });
    },

    deleteBuku(id) {
        EModal.confirm({
            title: 'Hapus Catatan',
            message: 'Apakah Anda yakin ingin menghapus catatan buku penghubung ini?',
            type: 'danger',
            confirmText: 'Hapus',
            onConfirm: () => {
                this.api('buku_penghubung.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    EModal.toast({ type: 'success', title: 'Terhapus', message: res.message || 'Catatan berhasil dihapus.' });
                    this.loadBukuTable();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menghapus catatan.' });
                });
            }
        });
    },

    // ==========================================
    // MASTER JENIS CATATAN (CRUD MODAL)
    // ==========================================
    openManageJenisCatatanModal() {
        const modalHtml = `
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:20px">
                <div style="font-size:13px;font-weight:700;color:var(--acad-text,#1e293b);margin-bottom:10px" id="lblFormJenisCatatan">
                    ➕ Tambah Jenis Catatan Baru
                </div>
                <input type="hidden" id="f_jenisBukuId" value="0">
                <div style="display:grid;grid-template-columns:1fr 1.2fr 1fr auto;gap:10px;align-items:center">
                    <input type="text" class="form-input-acad" id="f_jenisBukuNama" placeholder="Nama Jenis (contoh: Konseling Karir)" style="padding:8px 12px;font-size:13px">
                    <input type="text" class="form-input-acad" id="f_jenisBukuDesc" placeholder="Keterangan singkat..." style="padding:8px 12px;font-size:13px">
                    <select class="form-select-acad" id="f_jenisBukuWarna" style="padding:8px 12px;font-size:13px">
                        <option value="badge-primary">🟣 Primary (Ungu)</option>
                        <option value="badge-info">🔵 Info (Biru)</option>
                        <option value="badge-success">🟢 Success (Hijau)</option>
                        <option value="badge-warning">🟡 Warning (Kuning)</option>
                        <option value="badge-danger">🔴 Danger (Merah)</option>
                    </select>
                    <div style="display:flex;gap:6px">
                        <button type="button" class="btn-acad btn-acad-primary" id="btnSaveJenisCatatan" onclick="Curriculum.submitSaveJenisCatatan()" style="padding:8px 16px;font-size:13px;white-space:nowrap">
                            💾 Simpan
                        </button>
                        <button type="button" class="btn-acad btn-acad-outline" id="btnCancelEditJenisCatatan" onclick="Curriculum.resetJenisCatatanForm()" style="display:none;padding:8px 12px;font-size:13px">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <div style="font-size:13px;font-weight:700;color:var(--acad-text,#1e293b)">
                    📋 Daftar Jenis Catatan Tersedia
                </div>
                <div id="jenisCatatanCountBadge" style="font-size:12px;color:var(--text-muted,#64748b)"></div>
            </div>
            <div id="jenisCatatanTableWrapper" style="max-height:300px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px">
                <div style="text-align:center;padding:30px;color:#94a3b8"><div class="spinner" style="margin:0 auto 8px"></div> Memuat daftar...</div>
            </div>
        `;

        EModal.form({
            title: 'Kelola Jenis Catatan Buku Penghubung',
            form: modalHtml,
            size: 'lg',
            confirmText: 'Tutup',
            cancelText: '',
            onOpen: () => {
                $('.emodal-card .emodal-footer .btn-ghost').hide();
                this._loadJenisCatatanList();
                $('#f_jenisBukuNama, #f_jenisBukuDesc').off('keydown').on('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.submitSaveJenisCatatan();
                    }
                });
            },
            onConfirm: () => {
                EModal.closeAll();
                return false;
            }
        });
    },

    _loadJenisCatatanList() {
        this.api('buku_penghubung.php?action=list_types').done(res => {
            if (!res.success) {
                $('#jenisCatatanTableWrapper').html('<div style="text-align:center;padding:24px;color:#dc2626">Gagal memuat jenis catatan.</div>');
                return;
            }
            const types = res.data || [];
            $('#jenisCatatanCountBadge').text(`${types.length} jenis terdaftar`);

            if (types.length === 0) {
                $('#jenisCatatanTableWrapper').html('<div style="text-align:center;padding:24px;color:#94a3b8">Belum ada jenis catatan. Silakan tambahkan di atas.</div>');
                return;
            }

            const rows = types.map((t, idx) => {
                const safeNama = (t.nama_jenis || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                const safeDesc = (t.deskripsi || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                const safeWarna = t.warna_badge || 'badge-info';
                return `
                    <tr style="border-bottom:1px solid #f1f5f9">
                        <td style="padding:10px 14px;font-size:13px;color:#64748b;width:40px">${idx + 1}</td>
                        <td style="padding:10px 14px;font-size:13px;font-weight:600;color:#1e293b">
                            <span class="badge ${safeWarna}" style="margin-right:6px">${this.escapeHtml(t.nama_jenis)}</span>
                        </td>
                        <td style="padding:10px 14px;font-size:13px;color:#64748b">${this.escapeHtml(t.deskripsi || '-')}</td>
                        <td style="padding:10px 14px;text-align:right;width:140px;white-space:nowrap">
                            <button type="button" class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.editJenisCatatan(${t.id}, '${safeNama}', '${safeDesc}', '${safeWarna}')" style="padding:4px 10px;font-size:12px;margin-right:4px">
                                ✏️ Edit
                            </button>
                            <button type="button" class="btn-acad btn-acad-sm" onclick="Curriculum.deleteJenisCatatan(${t.id}, '${safeNama}')" style="padding:4px 10px;font-size:12px;background:#fee2e2;color:#dc2626;border:none">
                                🗑️ Hapus
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            $('#jenisCatatanTableWrapper').html(`
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left">
                            <th style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;width:40px">NO</th>
                            <th style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b">NAMA JENIS CATATAN</th>
                            <th style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b">DESKRIPSI / KETERANGAN</th>
                            <th style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;text-align:right;width:140px">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        }).fail(() => {
            $('#jenisCatatanTableWrapper').html('<div style="text-align:center;padding:24px;color:#dc2626">Terjadi kesalahan saat memuat data.</div>');
        });
    },

    editJenisCatatan(id, nama, desc, warna) {
        $('#f_jenisBukuId').val(id);
        $('#f_jenisBukuNama').val(nama).focus();
        $('#f_jenisBukuDesc').val(desc);
        $('#f_jenisBukuWarna').val(warna || 'badge-info');
        $('#lblFormJenisCatatan').html(`✏️ Edit Jenis Catatan: <strong>${this.escapeHtml(nama)}</strong>`);
        $('#btnSaveJenisCatatan').text('💾 Update');
        $('#btnCancelEditJenisCatatan').show();
    },

    resetJenisCatatanForm() {
        $('#f_jenisBukuId').val('0');
        $('#f_jenisBukuNama').val('');
        $('#f_jenisBukuDesc').val('');
        $('#f_jenisBukuWarna').val('badge-info');
        $('#lblFormJenisCatatan').html('➕ Tambah Jenis Catatan Baru');
        $('#btnSaveJenisCatatan').text('💾 Simpan');
        $('#btnCancelEditJenisCatatan').hide();
    },

    submitSaveJenisCatatan() {
        const id = parseInt($('#f_jenisBukuId').val()) || 0;
        const nama_jenis = $('#f_jenisBukuNama').val().trim();
        const deskripsi = $('#f_jenisBukuDesc').val().trim();
        const warna_badge = $('#f_jenisBukuWarna').val() || 'badge-info';

        if (!nama_jenis) {
            this.toast('Gagal', 'Nama jenis catatan wajib diisi!', 'error');
            $('#f_jenisBukuNama').focus();
            return;
        }

        const $btn = $('#btnSaveJenisCatatan');
        const origText = id > 0 ? '💾 Update' : '💾 Simpan';
        $btn.prop('disabled', true).text('Menyimpan...');

        this.api('buku_penghubung.php?action=save_type', {
            method: 'POST',
            data: { id, nama_jenis, deskripsi, warna_badge }
        }).done(res => {
            if (res && res.success) {
                this.toast('Berhasil', res.message || 'Jenis catatan tersimpan', 'success');
                this.resetJenisCatatanForm();
                this._loadJenisCatatanList();
                this._populateBukuJenisFilter('#bukuJenis');
                this.loadBukuTable();
            } else {
                this.toast('Gagal', (res && res.message) || 'Gagal menyimpan jenis catatan', 'error');
            }
        }).fail(xhr => {
            let msg = 'Terjadi kesalahan saat menyimpan';
            try {
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    const parsed = JSON.parse(xhr.responseText);
                    if (parsed && parsed.message) msg = parsed.message;
                }
            } catch(e) {}
            this.toast('Gagal', msg, 'error');
        }).always(() => {
            $btn.prop('disabled', false).text(origText);
        });
    },

    deleteJenisCatatan(id, nama) {
        EModal.confirm({
            title: 'Hapus Jenis Catatan',
            message: `Apakah Anda yakin ingin menghapus jenis catatan <strong>${this.escapeHtml(nama)}</strong>?`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('buku_penghubung.php?action=delete_type', {
                    method: 'POST',
                    data: { id }
                }).done(res => {
                    if (res && res.success) {
                        this.toast('Berhasil', res.message || 'Jenis catatan dihapus', 'success');
                        this.resetJenisCatatanForm();
                        this._loadJenisCatatanList();
                        this._populateBukuJenisFilter('#bukuJenis');
                        this.loadBukuTable();
                    } else {
                        this.toast('Gagal', (res && res.message) || 'Gagal menghapus jenis catatan', 'error');
                    }
                }).fail(xhr => {
                    let msg = 'Gagal menghapus jenis catatan';
                    try {
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    } catch(e) {}
                    this.toast('Gagal', msg, 'error');
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
        let fullUrl = this.moduleUrl + 'api/' + endpoint;
        if (this.state.token && !fullUrl.includes('token=')) {
            fullUrl += (fullUrl.includes('?') ? '&' : '?') + 'token=' + encodeURIComponent(this.state.token);
        }
        const defaults = {
            url: fullUrl,
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

    toast(title, message, type = 'info') {
        const toastType = (type === 'danger' || type === 'error') ? 'error' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
        if (window.EModal && typeof EModal.toast === 'function') {
            EModal.toast({
                type: toastType,
                title: title || (toastType === 'success' ? 'Berhasil' : (toastType === 'error' ? 'Gagal' : 'Pemberitahuan')),
                message: message || '',
                duration: 3500
            });
        } else {
            console.log(`[Toast ${toastType}] ${title}: ${message}`);
        }
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
                    <div class="sch-toolbar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <button class="btn btn-sm" id="btnBulkDeleteDist" style="display:none;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;font-weight:600;" onclick="Curriculum.bulkDeleteDistribusi()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:2px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg> Hapus Terpilih (<span id="bulkDistCount">0</span>)
                        </button>
                        <button class="btn btn-sm" style="background:#fef2f2;color:#ef4444;border:1px solid #fecaca;font-weight:600;" onclick="Curriculum.clearAllDistribusi()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:2px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Hapus Semua
                        </button>
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
            let html = '<table class="sch-table"><thead><tr>';
            html += '<th style="width:36px;text-align:center"><input type="checkbox" id="selectAllDist" onchange="Curriculum.toggleSelectAllDist(this.checked)" title="Pilih Semua"></th>';
            html += '<th>No</th><th>Guru</th><th>Kelas</th><th>Mata Pelajaran</th><th>JP</th><th>Aksi</th></tr></thead><tbody>';
            
            data.forEach((d, i) => {
                totalJp += parseInt(d.jp);
                const escapedName = this.escapeHtml(d.nama_guru);
                const escapedMapel = this.escapeHtml(d.nama_mapel);
                html += `<tr>
                    <td style="text-align:center"><input type="checkbox" class="dist-cb" value="${d.id}" onchange="Curriculum.updateBulkDeleteDistState()"></td>
                    <td>${i+1}</td>
                    <td><strong>${escapedName}</strong><br><small style="color:var(--text-muted)">${this.escapeHtml(d.kode_guru)}</small></td>
                    <td>${this.escapeHtml(d.nama_kelas)}</td>
                    <td>${escapedMapel}</td>
                    <td><span style="background:var(--primary-light);padding:2px 8px;border-radius:12px;font-weight:600">${d.jp}</span></td>
                    <td>
                        <div class="sch-actions">
                            <button class="sch-btn-icon" onclick="Curriculum.formDist(${d.id})" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="sch-btn-icon danger" onclick="Curriculum.deleteSingleDistribusi(${d.id}, '${escapedName.replace(/'/g, "\\'")}', '${escapedMapel.replace(/'/g, "\\'")}')" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                        </div>
                    </td>
                </tr>`;
            });
            html += `</tbody><tfoot><tr><td colspan="5" style="text-align:right;font-weight:700">Total JP:</td><td colspan="2" style="font-weight:700;color:var(--primary-dark)">${totalJp} Jam</td></tr></tfoot></table>`;
            $('#distTable').html(html);
            this.updateBulkDeleteDistState();
        });
    },

    toggleSelectAllDist(checked) {
        $('.dist-cb').prop('checked', checked);
        this.updateBulkDeleteDistState();
    },

    updateBulkDeleteDistState() {
        const selectedCount = $('.dist-cb:checked').length;
        const totalCount = $('.dist-cb').length;
        $('#selectAllDist').prop('checked', totalCount > 0 && selectedCount === totalCount);
        if (selectedCount > 0) {
            $('#bulkDistCount').text(selectedCount);
            $('#btnBulkDeleteDist').show();
        } else {
            $('#btnBulkDeleteDist').hide();
        }
    },

    deleteSingleDistribusi(id, namaGuru, namaMapel) {
        EModal.confirm({
            title: 'Hapus Penugasan Mengajar',
            message: `Yakin ingin menghapus penugasan <strong>${namaMapel}</strong> untuk <strong>${namaGuru}</strong>?`,
            onConfirm: () => {
                this.api('sch_distribusi.php?action=delete', { method: 'POST', data: { id: id } }).done(res => {
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message || 'Penugasan berhasil dihapus.' });
                    this.loadDistribusi($('#distFilterKelas').val());
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menghapus penugasan.' });
                });
            }
        });
    },

    bulkDeleteDistribusi() {
        const selectedIds = [];
        $('.dist-cb:checked').each(function() {
            selectedIds.push(parseInt($(this).val()));
        });
        if (selectedIds.length === 0) return;

        EModal.confirm({
            title: 'Hapus Penugasan Terpilih',
            message: `Yakin ingin menghapus <strong>${selectedIds.length} data penugasan</strong> yang dicentang?`,
            onConfirm: () => {
                this.api('sch_distribusi.php?action=delete_bulk', { method: 'POST', data: { ids: selectedIds } }).done(res => {
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message || 'Penugasan terpilih berhasil dihapus.' });
                    this.loadDistribusi($('#distFilterKelas').val());
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menghapus penugasan terpilih.' });
                });
            }
        });
    },

    clearAllDistribusi() {
        EModal.confirm({
            title: 'Hapus Semua Distribusi Mengajar',
            message: '<strong>PERINGATAN!</strong> Yakin ingin menghapus SELURUH data penugasan/distribusi mengajar? Tindakan ini tidak dapat dibatalkan.',
            onConfirm: () => {
                this.api('sch_distribusi.php?action=clear_all', { method: 'POST' }).done(res => {
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message || 'Semua data distribusi mengajar berhasil dihapus.' });
                    this.loadDistribusi($('#distFilterKelas').val());
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menghapus semua data distribusi.' });
                });
            }
        });
    },

    formDist(id = null) {
        let listG = '', listM = '';
        
        let promiseG = this.api('sch_guru.php?action=list');
        let promiseK = this.api('sch_kelas.php?action=list');
        let promiseM = this.api('sch_mapel.php?action=list');

        Promise.all([promiseG, promiseK, promiseM]).then(results => {
            const guruData = results[0].data || [];
            const kelasData = results[1].data || [];
            const mapelData = results[2].data || [];

            guruData.forEach(x => {
                listG += `<div class="sp-cs-option" data-id="${x.id}" data-text="${x.kode_guru} - ${x.nama_guru}"><strong>${x.nama_guru}</strong> <br><small style="color:gray">${x.kode_guru}</small></div>`;
            });

            mapelData.forEach(x => {
                listM += `<div class="sp-cs-option" data-id="${x.id}" data-text="${x.nama_mapel}"><strong>${x.nama_mapel}</strong> <br><small style="color:gray">${x.kode_mapel}</small></div>`;
            });

            const renderKelasGridHTML = (blockId) => {
                let gridK = '<div class="class-checkbox-grid" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">';
                kelasData.forEach(x => {
                    gridK += `<input type="checkbox" id="ck_${blockId}_${x.id}" value="${x.id}" class="block-kelas-checkbox kIdCheckbox_${blockId}" style="display:none;">`;
                    gridK += `<label for="ck_${blockId}_${x.id}" style="display:inline-block; width:62px; text-align:center; padding:6px 0; background:var(--bg-color,#f8fafc); border:1px solid var(--border-color,#e2e8f0); border-radius:8px; cursor:pointer; font-size:0.82rem; font-weight:600; transition:all 0.2s; user-select:none;">${x.nama_kelas}</label>`;
                });
                gridK += '</div>';
                return gridK;
            };

            const style = `
            <style>
                .class-checkbox-grid input:checked + label { background:var(--primary-color,#3b82f6) !important; color:white !important; border-color:var(--primary-color,#3b82f6) !important; box-shadow:0 2px 6px rgba(59,130,246,0.3) !important; }
                .sp-cs-container { position:relative; user-select:none; }
                .sp-cs-btn { cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; height:42px; transition: all 0.2s; }
                .sp-cs-btn:hover { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
                .sp-cs-btn.active { border-color: var(--primary); }
                .sp-cs-dropdown { display:none; position:absolute; top:calc(100% + 5px); left:0; right:0; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:999999; overflow:hidden; }
                .sp-cs-option { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; font-size: 0.9rem; line-height:1.2; }
                .sp-cs-option:hover { background: #f1f5f9; }
                .sp-cs-option.selected { background: #e0f2fe; color: #0369a1; font-weight: 600; position: relative; }
                .sp-cs-option.selected::after { content: '✓'; position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-size: 1.1rem; color: #0284c7; }
                .teacher-block-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:14px; transition: all 0.2s; }
                .teacher-block-card:hover { border-color:#cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
                .emodal-content, .modal-dialog, .modal-content { max-width: 650px !important; }
            </style>`;

            EModal.form({
                title: id ? 'Edit Distribusi' : 'Tambah Distribusi Mengajar',
                width: '650px',
                form: `
                    ${style}
                    <input type="hidden" id="fId" value="${id || ''}">
                    
                    <div class="sch-form-row" style="display:grid; grid-template-columns: 1fr 160px; gap:14px; margin-bottom:15px; align-items:start;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-weight:600; margin-bottom:6px; display:block;">Pilih Mata Pelajaran (Mapel)</label>
                            <input type="hidden" id="fM">
                            <div class="sp-cs-container" id="csContainerM">
                                <div class="sp-cs-btn" id="csBtnM">
                                    <span id="csTextM" style="color:#64748b;">-- Pilih Mapel --</span>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </div>
                                <div class="sp-cs-dropdown" id="csDropM">
                                    <div style="padding:8px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                                        <input type="text" id="csSearchM" class="form-input" placeholder="Cari mapel..." style="width:100%; padding:6px 10px; height:34px; border-radius:6px; outline:none; border:1px solid #cbd5e1;" autocomplete="off">
                                    </div>
                                    <div id="csListM" style="max-height:180px; overflow-y:auto; padding:0;">
                                        ${listM}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-weight:600; white-space:nowrap; margin-bottom:6px; display:block;">Jumlah JP</label>
                            <select class="form-select" id="fJp" style="height:42px; width:100%; border-radius:8px; border:1px solid #cbd5e1; padding:0 30px 0 12px; font-weight:600; font-size:0.9rem; background-color:#fff; cursor:pointer;">
                                ${[1,2,3,4,5,6,7].map(j=>`<option value="${j}">${j} JP</option>`).join('')}
                            </select>
                        </div>
                    </div>

                    <div id="teacherBlockContainer"></div>

                    ${!id ? `
                    <button type="button" id="btnAddTeacherBlock" class="btn" style="width:100%; padding:10px; border:2px dashed var(--primary-color, #3b82f6); border-radius:8px; font-weight:600; color:var(--primary-color, #3b82f6); background:transparent; cursor:pointer; margin-bottom:10px; display:flex; align-items:center; justify-content:center; gap:8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        + Tambah Guru
                    </button>
                    ` : ''}
                `,
                onOpen: () => {
                    let blockCounter = 0;

                    const bindMapelSelect = () => {
                        $('#csBtnM').on('click', function(e) {
                            e.stopPropagation();
                            const isActive = $(this).hasClass('active');
                            $('.sp-cs-dropdown').hide();
                            $('.sp-cs-btn').removeClass('active');
                            if (!isActive) {
                                $(this).addClass('active');
                                $('#csDropM').show();
                                $('#csSearchM').val('').trigger('input').focus();
                            }
                        });

                        $('#csSearchM').on('input', function() {
                            const term = $(this).val().toLowerCase();
                            $('#csListM .sp-cs-option').each(function() {
                                const text = $(this).text().toLowerCase();
                                $(this).toggle(text.includes(term));
                            });
                        });

                        $('#csListM').on('click', '.sp-cs-option', function(e) {
                            e.stopPropagation();
                            $('#csListM .sp-cs-option').removeClass('selected');
                            $(this).addClass('selected');
                            const val = $(this).data('id');
                            const text = $(this).data('text');
                            $('#fM').val(val);
                            $('#csTextM').html(text).css('color', '#1e293b');
                            $('#csDropM').hide();
                            $('#csBtnM').removeClass('active');
                        });
                    };

                    const updateBlockHeaders = () => {
                        const blocks = $('.teacher-block-card');
                        blocks.each(function(index) {
                            $(this).find('.block-title').text(`Guru #${index + 1}`);
                            if (blocks.length > 1 && !id) {
                                $(this).find('.btn-remove-block').show();
                            } else {
                                $(this).find('.btn-remove-block').hide();
                            }
                        });
                    };

                    const updateDisabledClasses = () => {
                        const checkedIds = [];
                        $('.block-kelas-checkbox:checked').each(function() {
                            checkedIds.push($(this).val());
                        });

                        $('.block-kelas-checkbox').each(function() {
                            const val = $(this).val();
                            if (!$(this).is(':checked')) {
                                if (checkedIds.includes(val)) {
                                    $(this).prop('disabled', true);
                                    $(this).next('label').css({'opacity': '0.4', 'cursor': 'not-allowed'});
                                } else {
                                    $(this).prop('disabled', false);
                                    $(this).next('label').css({'opacity': '1', 'cursor': 'pointer'});
                                }
                            } else {
                                $(this).prop('disabled', false);
                                $(this).next('label').css({'opacity': '1', 'cursor': 'pointer'});
                            }
                        });
                    };

                    const addBlock = () => {
                        blockCounter++;
                        const bId = blockCounter;
                        const blockHtml = `
                            <div class="teacher-block-card" id="tBlock_${bId}">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
                                    <span class="block-title" style="font-weight:700; color:#1e293b; font-size:0.9rem;">Guru</span>
                                    <button type="button" class="btn-remove-block" data-bid="${bId}" style="display:none; background:#fee2e2; color:#ef4444; border:none; padding:3px 10px; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer;">&times; Hapus</button>
                                </div>

                                <div class="form-group" style="margin-bottom:12px;">
                                    <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Pilih Guru</label>
                                    <input type="hidden" class="block-guru-id" id="fG_${bId}">
                                    <div class="sp-cs-container" id="csContainerG_${bId}">
                                        <div class="sp-cs-btn" id="csBtnG_${bId}">
                                            <span id="csTextG_${bId}" style="color:#64748b;">-- Pilih Guru --</span>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                        </div>
                                        <div class="sp-cs-dropdown" id="csDropG_${bId}">
                                            <div style="padding:8px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                                                <input type="text" id="csSearchG_${bId}" class="form-input" placeholder="Cari guru..." style="width:100%; padding:6px 10px; height:34px; border-radius:6px; outline:none; border:1px solid #cbd5e1;" autocomplete="off">
                                            </div>
                                            <div id="csListG_${bId}" style="max-height:180px; overflow-y:auto; padding:0;">
                                                ${listG}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Pilih Kelas</label>
                                    ${renderKelasGridHTML(bId)}
                                </div>
                            </div>
                        `;

                        $('#teacherBlockContainer').append(blockHtml);
                        updateBlockHeaders();
                        updateDisabledClasses();

                        // Bind Teacher Select for this block
                        $(`#csBtnG_${bId}`).on('click', function(e) {
                            e.stopPropagation();
                            const isActive = $(this).hasClass('active');
                            $('.sp-cs-dropdown').hide();
                            $('.sp-cs-btn').removeClass('active');
                            if (!isActive) {
                                $(this).addClass('active');
                                $(`#csDropG_${bId}`).show();
                                $(`#csSearchG_${bId}`).val('').trigger('input').focus();
                            }
                        });

                        $(`#csSearchG_${bId}`).on('input', function() {
                            const term = $(this).val().toLowerCase();
                            $(`#csListG_${bId} .sp-cs-option`).each(function() {
                                const text = $(this).text().toLowerCase();
                                $(this).toggle(text.includes(term));
                            });
                        });

                        $(`#csListG_${bId}`).on('click', '.sp-cs-option', function(e) {
                            e.stopPropagation();
                            $(`#csListG_${bId} .sp-cs-option`).removeClass('selected');
                            $(this).addClass('selected');
                            const val = $(this).data('id');
                            const text = $(this).data('text');
                            $(`#fG_${bId}`).val(val);
                            $(`#csTextG_${bId}`).html(text).css('color', '#1e293b');
                            $(`#csDropG_${bId}`).hide();
                            $(`#csBtnG_${bId}`).removeClass('active');
                        });
                    };

                    bindMapelSelect();
                    addBlock(); // Always create initial Block #1

                    $('#btnAddTeacherBlock').on('click', function() {
                        addBlock();
                    });

                    $('#teacherBlockContainer').on('click', '.btn-remove-block', function() {
                        $(this).closest('.teacher-block-card').remove();
                        updateBlockHeaders();
                        updateDisabledClasses();
                    });

                    $('#teacherBlockContainer').on('change', '.block-kelas-checkbox', function() {
                        updateDisabledClasses();
                    });

                    $(document).on('click.csDropdown', function(e) {
                        if (!$(e.target).closest('.sp-cs-container').length) {
                            $('.sp-cs-dropdown').hide();
                            $('.sp-cs-btn').removeClass('active');
                        }
                    });

                    // Edit existing record mode
                    if (id) {
                        const row = this.state.distData.find(x => x.id == id);
                        if (row) {
                            $('#fM').val(row.mapel_id);
                            const optM = $(`#csListM .sp-cs-option[data-id="${row.mapel_id}"]`);
                            if (optM.length) $('#csTextM').html(optM.data('text')).css('color', '#1e293b');

                            $('#fJp').val(row.jp);

                            // Block 1 data binding
                            const bId = 1;
                            $(`#fG_${bId}`).val(row.guru_id);
                            $(`#csListG_${bId} .sp-cs-option`).removeClass('selected');
                            const optG = $(`#csListG_${bId} .sp-cs-option[data-id="${row.guru_id}"]`);
                            if (optG.length) {
                                optG.addClass('selected');
                                $(`#csTextG_${bId}`).html(optG.data('text')).css('color', '#1e293b');
                            }

                            $(`#ck_${bId}_${row.kelas_id}`).prop('checked', true);

                            // Restrict to single class selection when editing an existing single record
                            $(`.kIdCheckbox_${bId}`).on('change', function() {
                                if ($(this).is(':checked')) {
                                    $(`.kIdCheckbox_${bId}`).not(this).prop('checked', false);
                                }
                            });
                        }
                    }
                },
                onConfirm: () => {
                    const mId = $('#fM').val();
                    const jp = $('#fJp').val();

                    if (!mId) {
                        EModal.toast({type: 'error', title: 'Perhatian', message: 'Silakan pilih Mata Pelajaran!'});
                        return false;
                    }

                    if (id) {
                        // Edit single record
                        const gId = $('#fG_1').val();
                        const kId = $('.block-kelas-checkbox:checked').val();
                        if (!gId || !kId) {
                            EModal.toast({type: 'error', title: 'Perhatian', message: 'Silakan pilih Guru dan Kelas!'});
                            return false;
                        }
                        let data = { id: id, guru_id: gId, kelas_id: kId, mapel_id: mId, jp: jp };
                        this.api('sch_distribusi.php?action=update', {method:'POST', data}).done(() => {
                            EModal.closeAll(); 
                            this.reloadCurrentPage();
                            EModal.toast({ type: 'success', title: 'Berhasil', message: 'Distribusi diperbarui.' });
                        });
                    } else {
                        // Create grouped distributions
                        const assignments = [];
                        let isValid = true;
                        let errorMsg = '';

                        $('.teacher-block-card').each(function() {
                            const guruId = $(this).find('.block-guru-id').val();
                            const kelasIds = [];
                            $(this).find('.block-kelas-checkbox:checked').each(function() {
                                kelasIds.push($(this).val());
                            });

                            if (!guruId) {
                                isValid = false;
                                errorMsg = 'Ada blok guru yang belum memilih Guru!';
                                return false;
                            }
                            if (kelasIds.length === 0) {
                                isValid = false;
                                errorMsg = 'Ada blok guru yang belum memilih Kelas!';
                                return false;
                            }

                            assignments.push({ guru_id: guruId, kelas_ids: kelasIds });
                        });

                        if (!isValid) {
                            EModal.toast({type: 'error', title: 'Perhatian', message: errorMsg});
                            return false;
                        }

                        let data = { mapel_id: mId, jp: jp, assignments: assignments };
                        this.api('sch_distribusi.php?action=save_grouped', {method:'POST', data}).done(() => {
                            EModal.closeAll(); 
                            this.reloadCurrentPage();
                            EModal.toast({ type: 'success', title: 'Berhasil', message: 'Distribusi mengajar berhasil disimpan.' });
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
                    <div class="sch-toolbar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <button class="btn btn-sm" id="btnBulkDeleteKesediaan" style="display:none;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;font-weight:600;" onclick="Curriculum.bulkDeleteKesediaan()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:2px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg> Hapus Terpilih (<span id="bulkKesediaanCount">0</span>)
                        </button>
                        <button class="btn btn-sm" style="background:#fef2f2;color:#ef4444;border:1px solid #fecaca;font-weight:600;" onclick="Curriculum.clearAllKesediaan()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:2px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Hapus Semua
                        </button>
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

            let html = '<table class="matrix-table"><thead><tr>';
            html += '<th style="width:40px;text-align:center"><input type="checkbox" id="selectAllKesediaanGuru" onchange="Curriculum.toggleSelectAllKesediaanGuru(this.checked)" title="Pilih Semua Guru"></th>';
            html += '<th style="min-width:150px;text-align:left">Nama Guru</th><th style="width:110px;text-align:center">Aksi</th>';
            days.forEach(d => html += `<th>${d} <br><small style="font-weight:400">Jam: ${daysMap[d].map(j=>j.nama_jam).join(',')}</small></th>`);
            html += '</tr></thead><tbody>';

            data.forEach(g => {
                const escapedName = this.escapeHtml(g.nama_guru);
                html += `<tr data-gid="${g.id}">
                    <td style="text-align:center"><input type="checkbox" class="ks-guru-cb" value="${g.id}" onchange="Curriculum.updateBulkDeleteKesediaanState()"></td>
                    <td style="text-align:left"><strong>${escapedName}</strong><br><small>${this.escapeHtml(g.kode_guru)}</small></td>
                    <td style="text-align:center">
                        <div style="display:flex;gap:4px;justify-content:center;align-items:center">
                            <button class="btn btn-sm btn-outline" onclick="Curriculum.checkAllRow(${g.id}, true)" title="Pilih Semua Jam">All</button>
                            <button class="btn btn-sm btn-outline" onclick="Curriculum.checkAllRow(${g.id}, false)" title="Kosongkan Jam">0</button>
                            <button class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;padding:2px 6px;" onclick="Curriculum.deleteSingleKesediaan(${g.id}, '${escapedName.replace(/'/g, "\\'")}')" title="Hapus ketersediaan guru ini">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
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
            this.updateBulkDeleteKesediaanState();
        });
    },

    toggleSelectAllKesediaanGuru(checked) {
        $('.ks-guru-cb').prop('checked', checked);
        this.updateBulkDeleteKesediaanState();
    },

    updateBulkDeleteKesediaanState() {
        const selectedCount = $('.ks-guru-cb:checked').length;
        const totalCount = $('.ks-guru-cb').length;
        $('#selectAllKesediaanGuru').prop('checked', totalCount > 0 && selectedCount === totalCount);
        if (selectedCount > 0) {
            $('#bulkKesediaanCount').text(selectedCount);
            $('#btnBulkDeleteKesediaan').show();
        } else {
            $('#btnBulkDeleteKesediaan').hide();
        }
    },

    deleteSingleKesediaan(gid, namaGuru) {
        EModal.confirm({
            title: 'Hapus Ketersediaan Guru',
            message: `Yakin ingin menghapus semua jam ketersediaan mengajar untuk <strong>${namaGuru}</strong>?`,
            onConfirm: () => {
                this.api('sch_kesediaan.php?action=delete_single', {
                    method: 'POST',
                    data: { guru_id: gid }
                }).done(res => {
                    $(`.jcb-${gid}`).prop('checked', false);
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message || 'Ketersediaan guru berhasil dihapus' });
                    this.navigate('sch_kesediaan');
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menghapus ketersediaan guru' });
                });
            }
        });
    },

    bulkDeleteKesediaan() {
        const selectedGids = [];
        $('.ks-guru-cb:checked').each(function() {
            selectedGids.push(parseInt($(this).val()));
        });
        if (selectedGids.length === 0) return;

        EModal.confirm({
            title: 'Hapus Ketersediaan Terpilih',
            message: `Yakin ingin menghapus ketersediaan mengajar untuk <strong>${selectedGids.length} guru</strong> yang dicentang?`,
            onConfirm: () => {
                this.api('sch_kesediaan.php?action=delete_bulk', {
                    method: 'POST',
                    data: { guru_ids: selectedGids }
                }).done(res => {
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message || 'Ketersediaan guru terpilih berhasil dihapus' });
                    this.navigate('sch_kesediaan');
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menghapus ketersediaan guru' });
                });
            }
        });
    },

    clearAllKesediaan() {
        EModal.confirm({
            title: 'Hapus Semua Ketersediaan',
            message: '<strong>PERINGATAN!</strong> Yakin ingin menghapus SELURUH data ketersediaan mengajar semua guru? Tindakan ini tidak dapat dibatalkan.',
            onConfirm: () => {
                this.api('sch_kesediaan.php?action=clear_all', {
                    method: 'POST'
                }).done(res => {
                    EModal.toast({ type: 'success', title: 'Berhasil', message: res.message || 'Semua ketersediaan mengajar berhasil dihapus' });
                    this.navigate('sch_kesediaan');
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menghapus semua ketersediaan' });
                });
            }
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
    },

    // ==========================================
    // AKSES MODUL (RBAC)
    // ==========================================
    renderRoles($content) {
        $content.html(`
            <div class="acad-tab-container">
                <div class="acad-tab-header" style="display:flex;gap:0;border-bottom:2px solid var(--border-color,#e2e8f0);margin-bottom:20px">
                    <button class="acad-tab-btn active" data-tab="accounts" style="padding:10px 24px;border:none;background:none;font-weight:600;cursor:pointer;border-bottom:2px solid var(--primary,#4f46e5);margin-bottom:-2px;color:var(--primary,#4f46e5);font-size:14px;transition:all .2s">
                        Manajemen Akun
                    </button>
                    <button class="acad-tab-btn" data-tab="roles" style="padding:10px 24px;border:none;background:none;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;color:var(--text-muted,#94a3b8);font-size:14px;transition:all .2s">
                        Role &amp; Izin
                    </button>
                </div>
                <div class="acad-tab-content" id="tab-accounts">
                    <div class="acad-card">
                        <div class="acad-card-header">
                            <div>
                                <h3 class="acad-card-title" style="margin:0">Pengaturan Akses Pengguna</h3>
                                <div style="font-size:0.82rem;color:var(--text-muted,#94a3b8);margin-top:4px">
                                    Semua akun aktif dari Admin E-Portal ditampilkan di sini. Tinggal pilih siapa yang boleh mengakses modul Kurikulum.
                                </div>
                            </div>
                        </div>
                        <div class="acad-card-body">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
                                <div id="accountsSummary" style="font-size:0.84rem;color:var(--text-muted,#94a3b8)">Memuat data akun...</div>
                                <div style="min-width:280px;flex:1;max-width:420px">
                                    <input type="text" class="form-input-acad" id="accountsSearchInput" placeholder="Cari username, nama, atau role Kurikulum...">
                                </div>
                            </div>
                            <div id="accountsTable"><div style="text-align:center;padding:40px;color:var(--text-muted,#94a3b8)"><div class="spinner" style="margin:0 auto 10px"></div> Memuat...</div></div>
                        </div>
                    </div>
                </div>
                <div class="acad-tab-content" id="tab-roles" style="display:none">
                    <div class="acad-card">
                        <div class="acad-card-header" style="display:flex;justify-content:space-between;align-items:center">
                            <h3 class="acad-card-title" style="margin:0">Konfigurasi Role &amp; Hak Akses</h3>
                            <button class="btn-acad btn-acad-primary" onclick="Curriculum.formRole()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Tambah Role
                            </button>
                        </div>
                        <div class="acad-card-body"><div id="rolesDefTable"></div></div>
                    </div>
                </div>
            </div>
        `);

        // Tab switcher
        $content.find('.acad-tab-btn').click(function() {
            $content.find('.acad-tab-btn').css({borderBottomColor:'transparent',color:'var(--text-muted,#94a3b8)'});
            $(this).css({borderBottomColor:'var(--primary,#4f46e5)',color:'var(--primary,#4f46e5)'});
            $content.find('.acad-tab-content').hide();
            $content.find('#tab-'+$(this).data('tab')).show();
        });

        this._loadAccounts();
        this._loadRolesDef();

        $content.find('#accountsSearchInput').on('input', (e) => {
            this._renderAccountsTable($(e.currentTarget).val());
        });
    },

    // ---------- Tab 1: Manajemen Akun ----------
    _loadAccounts() {
        this.api('users.php?action=accounts_list').done(res => {
            if (!res.success) { $('#accountsTable').html('<div style="text-align:center;padding:30px;color:var(--text-muted)">Gagal memuat data.</div>'); return; }
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
                u.tupoksi,
                u.jabatan,
                u.mapel,
                u.custom_role_nama
            ].filter(Boolean).join(' ').toLowerCase();
            return haystack.includes(keyword);
        });

        const withAccess = data.filter(u => parseInt(u.custom_role_id || 0) > 0).length;
        $('#accountsSummary').html(
            `<strong>${filtered.length}</strong> akun tampil dari <strong>${data.length}</strong> akun portal aktif. Akses Kurikulum aktif: <strong>${withAccess}</strong> akun.`
        );

        if (!filtered.length) {
            $('#accountsTable').html('<div style="text-align:center;padding:30px;color:var(--text-muted,#94a3b8)">Tidak ada akun yang cocok dengan pencarian.</div>');
            return;
        }

        const rows = filtered.map(u => {
            const hasAccess = parseInt(u.custom_role_id || 0) > 0;
            const roleBadge = hasAccess
                ? '<span class="badge badge-primary" style="font-weight:600">'+this.escapeHtml(u.custom_role_nama)+'</span>'
                : '<span class="badge" style="background:#f1f5f9;color:#64748b">Tanpa Akses</span>';
            
            const safeName = (u.nama_lengkap || '').replace(/'/g, "\\'");
            const safeUsername = this.escapeHtml(u.username || '-');
            const safeTupoksi = (u.tupoksi || '').replace(/'/g, "\\'");

            // Tupoksi & Jabatan formatting
            let tupoksiLabel = u.tupoksi ? this.escapeHtml(u.tupoksi) : (u.portal_role === 'guru' ? 'Guru' : (u.portal_role === 'superadmin' ? 'Super Admin' : u.portal_role || '-'));
            let tupoksiBadgeColor = '#eff6ff';
            let tupoksiTextColor = '#1d4ed8';
            let tupoksiBorder = '#bfdbfe';

            const lowTup = tupoksiLabel.toLowerCase();
            if (lowTup.includes('bk')) {
                tupoksiBadgeColor = '#fdf4ff';
                tupoksiTextColor = '#9333ea';
                tupoksiBorder = '#f0abfc';
            } else if (lowTup.includes('kepala') || lowTup.includes('waka')) {
                tupoksiBadgeColor = '#fff7ed';
                tupoksiTextColor = '#c2410c';
                tupoksiBorder = '#fed7aa';
            } else if (lowTup.includes('kebersihan') || lowTup.includes('tu') || lowTup.includes('tenaga') || lowTup.includes('satpam')) {
                tupoksiBadgeColor = '#f8fafc';
                tupoksiTextColor = '#475569';
                tupoksiBorder = '#cbd5e1';
            }

            let extraInfo = '';
            if (u.jabatan) {
                extraInfo += `<div style="font-size:11px;color:#475569;font-weight:500;margin-top:2px;">📌 ${this.escapeHtml(u.jabatan)}</div>`;
            }
            if (u.mapel) {
                extraInfo += `<div style="font-size:11px;color:#64748b;margin-top:1px;">📖 ${this.escapeHtml(u.mapel)}</div>`;
            }

            const tupoksiCell = `
                <div>
                    <span class="badge" style="background:${tupoksiBadgeColor};color:${tupoksiTextColor};border:1px solid ${tupoksiBorder};font-size:11.5px;font-weight:600;padding:3px 8px;border-radius:6px;display:inline-block">
                        ${tupoksiLabel}
                    </span>
                    ${extraInfo}
                </div>
            `;

            const actionBtns = [
                '<button class="btn-acad btn-acad-sm" style="border:1px solid var(--border-color,#e2e8f0);background:transparent;color:var(--text-color,#1e293b);font-weight:500" onclick="Curriculum.formAssignAccount('+u.id+',\''+safeName+'\','+(u.custom_role_id || 0)+',\''+safeTupoksi+'\')">'
                +(hasAccess ? 'Ubah Akses' : 'Atur Akses')+'</button>'
            ];
            if (hasAccess) {
                actionBtns.push('<button class="btn-acad btn-acad-sm" style="background:#fee2e2;color:#dc2626;border:none;font-weight:500" onclick="Curriculum.revokeAccountAccess('+u.id+',\''+safeName+'\')">❌ Cabut</button>');
            }

            return '<tr>'
                +'<td><div style="font-weight:700">@'+safeUsername+'</div></td>'
                +'<td><strong>'+this.escapeHtml(u.nama_lengkap || '-')+'</strong><div style="font-size:0.75rem;color:var(--text-muted,#94a3b8)">NIK: '+this.escapeHtml(u.nik || '-')+'</div></td>'
                +'<td>'+tupoksiCell+'</td>'
                +'<td>'+roleBadge+'</td>'
                +'<td><div style="display:flex;gap:8px;flex-wrap:wrap">'+actionBtns.join('')+'</div></td>'
                +'</tr>';
        }).join('');

        $('#accountsTable').html('<div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Username</th><th>Nama Pegawai / Guru</th><th>Tupoksi &amp; Jabatan</th><th>Akses Kurikulum</th><th>Aksi</th></tr></thead><tbody>'+rows+'</tbody></table></div>');
    },

    // ---------- Tab 2: Role & Izin ----------
    _loadRolesDef() {
        const permLabels = {
            'dashboard_view': 'Dashboard',
            'jadwal_manage': 'Kelola Jadwal',
            'jurnal_manage': 'Jurnal Mengajar',
            'absensi_manage': 'Absensi Siswa',
            'absensi_guru_manage': 'Absensi Guru',
            'piket_manage': 'Piket Guru',
            'ketidakhadiran_manage': 'Ketidakhadiran',
            'buku_penghubung_manage': 'Buku Penghubung',
            'dokumen_manage': 'Dokumen Perangkat',
            'laporan_view': 'Laporan',
            'roles_manage': 'Kelola Role & Akses'
        };

        this.api('users.php?action=list_roles').done(res => {
            if (!res.success) { $('#rolesDefTable').html('<div style="text-align:center;padding:30px;color:var(--text-muted)">Gagal memuat.</div>'); return; }
            const rows = res.data.map(r => {
                const perms = (r.permissions||'').split(',').filter(Boolean);
                const badges = perms.map(p => {
                    const label = permLabels[p] || p;
                    return '<span class="badge" style="font-size:11px;margin:2px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:3px 8px;border-radius:6px;font-weight:500;display:inline-block">'+label+'</span>';
                }).join('');
                const safeName = (r.nama||'').replace(/'/g,"\\'");
                const lockIcon = parseInt(r.is_locked) ? ' &#x1F512;' : '';
                const delBtn = parseInt(r.is_locked) ? '' : '<button class="btn-icon text-danger" onclick="Curriculum.delRole('+r.id+',\''+safeName+'\')" title="Hapus Role"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>';
                return '<tr>'
                    +'<td><strong>'+r.nama+lockIcon+'</strong><div style="font-size:0.75rem;color:var(--text-muted,#94a3b8)">'+(r.deskripsi||'-')+'</div></td>'
                    +'<td style="max-width:480px"><div style="display:flex;flex-wrap:wrap;gap:4px">'+badges+'</div></td>'
                    +'<td><div style="display:flex;gap:6px"><button class="btn-icon" onclick="Curriculum.formRole('+r.id+')" title="Edit Role & Izin"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>'+delBtn+'</div></td>'
                    +'</tr>';
            }).join('');
            $('#rolesDefTable').html('<div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Role</th><th>Izin (Hak Akses Modul)</th><th>Aksi</th></tr></thead><tbody>'+rows+'</tbody></table></div>');
        });
    },

    formRole(id) {
        const isEdit = id !== undefined && id !== null;
        const allPerms = [
            {key:'dashboard_view',label:'Lihat Dashboard'},
            {key:'jadwal_manage',label:'Kelola Jadwal (Jam, Kelas, Mapel, Guru, Distribusi)'},
            {key:'jurnal_manage',label:'Kelola Jurnal Mengajar'},
            {key:'absensi_manage',label:'Kelola Absensi Siswa'},
            {key:'absensi_guru_manage',label:'Kelola Absensi Guru'},
            {key:'piket_manage',label:'Kelola Piket'},
            {key:'ketidakhadiran_manage',label:'Kelola Ketidakhadiran'},
            {key:'buku_penghubung_manage',label:'Kelola Buku Penghubung'},
            {key:'dokumen_manage',label:'Kelola Dokumen Perangkat'},
            {key:'laporan_view',label:'Lihat/Cetak Laporan'},
            {key:'roles_manage',label:'Kelola Role & Akses'}
        ];
        const checksHtml = allPerms.map(p =>
            '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:normal">'
            +'<input type="checkbox" class="rp-chk" value="'+p.key+'"> '+p.label+'</label>'
        ).join('');

        EModal.form({
            title: isEdit ? 'Edit Role & Izin' : 'Tambah Role Baru',
            form: '<div class="form-group-acad mb-3"><label class="form-label-acad">Nama Role</label><input class="form-input-acad" id="f_rlNama" required></div>'
                +'<div class="form-group-acad mb-3"><label class="form-label-acad">Deskripsi</label><input class="form-input-acad" id="f_rlDesc"></div>'
                +'<div class="form-group-acad mb-3"><label class="form-label-acad">Hak Akses</label>'
                +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:12px;border:1px solid var(--border-color,#e2e8f0);border-radius:8px;background:#f9fafb">'
                +checksHtml+'</div></div>',
            size: 'lg',
            confirmText: 'Simpan',
            cancelText: 'Batal',
            onOpen: () => {
                if (isEdit) {
                    this.api('users.php?action=list_roles').done(res => {
                        const r = (res.data || []).find(x => x.id == id);
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
                const payload = {id: id||0, nama: $('#f_rlNama').val(), deskripsi: $('#f_rlDesc').val(), permissions: perms};
                if (!payload.nama) { this.toast('Gagal', 'Nama role wajib diisi', 'error'); return false; }

                const $btn = $('.emodal-save-btn');
                const origText = $btn.html();
                $btn.prop('disabled', true).text('Menyimpan...');

                this.api('users.php?action=save_role', { method:'POST', data: payload })
                    .done(res => {
                        if (res.success) {
                            EModal.closeAll();
                            this._loadRolesDef();
                            this.toast('Berhasil', 'Role Berhasil Disimpan', 'success');
                        } else {
                            this.toast('Gagal', res.message || 'Gagal menyimpan role', 'error');
                            $btn.prop('disabled', false).html(origText);
                        }
                    })
                    .fail(xhr => {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Terjadi kesalahan saat menyimpan role';
                        this.toast('Gagal', msg, 'error');
                        $btn.prop('disabled', false).html(origText);
                    });
                return false;
            }
        });
    },

    delRole(id, nama) {
        EModal.confirm({
            title:'Hapus Role',
            message:'Yakin hapus role <strong>'+nama+'</strong>? Semua user dengan role ini akan kehilangan akses.',
            type:'danger',
            onConfirm: () => {
                this.api('users.php?action=delete_role', {method:'POST', data:{id}})
                    .done(res => {
                        if (res.success) {
                            this._loadRolesDef();
                            this._loadAccounts();
                            this.toast('Berhasil', 'Role Dihapus', 'success');
                        } else {
                            this.toast('Gagal', res.message || 'Gagal menghapus role', 'error');
                        }
                    })
                    .fail(xhr => {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Gagal menghapus role';
                        this.toast('Gagal', msg, 'error');
                    });
            }
        });
    },

    formAssignAccount(userId, userName, currentRoleId = 0, userTupoksi = '') {
        this.api('users.php?action=list_roles').done(res => {
            const opts = (res.data || []).map(r => '<option value="'+r.id+'">'+r.nama+'</option>').join('');
            const tupoksiSubtitle = userTupoksi ? `<div style="font-size:12px;color:var(--text-muted,#64748b);margin-bottom:10px;padding:6px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px">Tupoksi: <strong>${this.escapeHtml(userTupoksi)}</strong></div>` : '';
            EModal.form({
                title: 'Atur Akses: '+userName,
                form: '<div class="form-group-acad mb-3">'
                    +tupoksiSubtitle
                    +'<label class="form-label-acad">Pilih Role Kurikulum</label>'
                    +'<select class="form-select-acad" id="f_assignRl"><option value="0">-- Tanpa Akses --</option>'+opts+'</select></div>'
                    +'<div style="font-size:12px;color:var(--text-muted,#94a3b8);background:#f3f4f6;padding:10px;border-radius:6px">'
                    +'<strong>Info:</strong> Akun ini akan mendapatkan hak akses sesuai izin pada role yang dipilih.</div>',
                size: 'md',
                confirmText: 'Simpan',
                cancelText: 'Batal',
                onOpen: () => {
                    $('#f_assignRl').val(String(currentRoleId || 0));
                },
                onConfirm: () => {
                    const data = {user_id:userId, role_id:$('#f_assignRl').val()};
                    const $btn = $('.emodal-save-btn');
                    const origText = $btn.html();
                    $btn.prop('disabled', true).text('Menyimpan...');

                    this.api('users.php?action=assign_account', {method:'POST', data})
                        .done(res => {
                            if (res.success) {
                                EModal.closeAll();
                                this._loadAccounts();
                                this.toast('Berhasil', 'Akses Diperbarui', 'success');
                            } else {
                                this.toast('Gagal', res.message || 'Gagal memperbarui akses', 'error');
                                $btn.prop('disabled', false).html(origText);
                            }
                        })
                        .fail(xhr => {
                            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Gagal memperbarui akses';
                            this.toast('Gagal', msg, 'error');
                            $btn.prop('disabled', false).html(origText);
                        });
                    return false;
                }
            });
        });
    },

    revokeAccountAccess(userId, userName) {
        EModal.confirm({
            title: 'Cabut Akses Kurikulum',
            message: 'Yakin ingin mencabut akses modul Kurikulum untuk <strong>'+userName+'</strong>?',
            type: 'danger',
            onConfirm: () => {
                this.api('users.php?action=assign_account', {method:'POST', data:{user_id:userId, role_id:0}})
                    .done(res => {
                        if (res.success) {
                            this._loadAccounts();
                            this.toast('Berhasil', 'Akses berhasil dicabut', 'success');
                        } else {
                            this.toast('Gagal', res.message || 'Gagal mencabut akses', 'error');
                        }
                    })
                    .fail(xhr => {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Gagal mencabut akses';
                        this.toast('Gagal', msg, 'error');
                    });
            }
        });
    },

    // ==========================================
    // DOKUMEN PERANGKAT (RPP, MODUL AJAR)
    // ==========================================
    renderDokumen($content) {
        const isAdmin = this.state.user.role === 'superadmin' || this.can('dokumen_manage');
        const isTeacher = this.state.user.role === 'guru';
        
        if (isAdmin) {
            // ========== ADMIN VIEW: Table with approval workflow ==========
            $content.html(`
                <div class="acad-card">
                    <div class="acad-card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                        <div>
                            <h3 class="acad-card-title" style="margin:0;font-size:1.15rem;font-weight:700">Dokumen Perangkat Guru</h3>
                            <div class="acad-subtitle" style="margin-top:4px">Kelola dan verifikasi dokumen perangkat pembelajaran (RPP, Modul Ajar, Silabus) yang dikirim oleh guru.</div>
                        </div>
                        <div>
                            <button class="btn-acad btn-acad-outline" onclick="Curriculum.openManageJenisDokumenModal()" style="display:flex;align-items:center;gap:6px;padding:7px 14px;font-size:13px;border-radius:6px;font-weight:600">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                                Kelola Jenis Perangkat
                            </button>
                        </div>
                    </div>
                    <div class="acad-card-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px;padding:12px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px">
                            <div id="dokumenAdminSummary" style="font-size:0.85rem;color:var(--text-muted,#64748b)">Memuat ringkasan...</div>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <div style="width:190px;min-width:160px">
                                    <select class="form-select-acad" id="dokFilterTipe" style="padding:7px 12px;font-size:13px;height:38px;border-radius:6px">
                                        <option value="">Semua Jenis Perangkat</option>
                                    </select>
                                </div>
                                <div style="width:150px;min-width:130px">
                                    <select class="form-select-acad" id="dokFilterStatus" style="padding:7px 12px;font-size:13px;height:38px;border-radius:6px">
                                        <option value="">Semua Status</option>
                                        <option value="pending">⏳ Pending</option>
                                        <option value="approved">✅ Disetujui</option>
                                        <option value="rejected">❌ Ditolak</option>
                                    </select>
                                </div>
                                <div style="width:220px;min-width:170px">
                                    <input type="text" class="form-input-acad" id="dokSearchInput" placeholder="Cari judul / guru..." style="padding:7px 12px;font-size:13px;height:38px;border-radius:6px">
                                </div>
                            </div>
                        </div>
                        <div id="dokumenAdminTable">
                            <div style="text-align:center;padding:40px;color:var(--text-muted,#94a3b8)"><div class="spinner" style="margin:0 auto 10px"></div> Memuat dokumen...</div>
                        </div>
                    </div>
                </div>
            `);
            this._loadDokumenAdmin();
            this._populateDokumenTypeFilter('#dokFilterTipe');
            $('#dokFilterTipe, #dokFilterStatus, #dokSearchInput').on('input change', () => this._renderDokumenAdminTable());
        } else if (isTeacher) {
            // ========== GURU VIEW: Card-based with upload ==========
            $content.html(`
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
                    <div>
                        <h2 style="margin:0;font-size:1.3rem;font-weight:700;color:var(--text-color,#1e293b)">Dokumen Perangkat Saya</h2>
                        <p style="margin:4px 0 0;font-size:0.84rem;color:var(--text-muted,#94a3b8)">Upload RPP, Modul Ajar, Silabus, dan dokumen lainnya.</p>
                    </div>
                    <button class="btn-acad btn-acad-primary" onclick="Curriculum.openUploadDokumenModal()" style="display:flex;align-items:center;gap:6px">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload Dokumen
                    </button>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px;padding:12px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px">
                    <div id="dokumenGuruSummary" style="font-size:0.84rem;color:var(--text-muted,#64748b)">Memuat ringkasan...</div>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <div style="width:190px;min-width:160px">
                            <select class="form-select-acad" id="dokGuruFilterTipe" style="padding:7px 12px;font-size:13px;height:38px;border-radius:6px">
                                <option value="">Semua Jenis Perangkat</option>
                            </select>
                        </div>
                        <div style="width:150px;min-width:130px">
                            <select class="form-select-acad" id="dokGuruFilterStatus" style="padding:7px 12px;font-size:13px;height:38px;border-radius:6px">
                                <option value="">Semua Status</option>
                                <option value="pending">⏳ Pending</option>
                                <option value="approved">✅ Disetujui</option>
                                <option value="rejected">❌ Ditolak</option>
                            </select>
                        </div>
                        <div style="width:200px;min-width:160px">
                            <input type="text" class="form-input-acad" id="dokGuruSearchInput" placeholder="Cari judul dokumen..." style="padding:7px 12px;font-size:13px;height:38px;border-radius:6px">
                        </div>
                    </div>
                </div>
                <div id="dokumenGuruCards">
                    <div style="text-align:center;padding:60px 20px;color:var(--text-muted,#94a3b8)"><div class="spinner" style="margin:0 auto 10px"></div> Memuat dokumen...</div>
                </div>
            `);
            this._loadDokumenGuru();
            this._populateDokumenTypeFilter('#dokGuruFilterTipe');
            $('#dokGuruFilterTipe, #dokGuruFilterStatus, #dokGuruSearchInput').on('input change', () => this._renderDokumenGuruCards());
        } else {
            $content.html(`
                <div class="acad-card" style="text-align:center;padding:60px 20px;">
                    <div style="font-size:40px;margin-bottom:12px">&#128274;</div>
                    <h3 style="margin:0 0 8px;color:var(--text-color,#1e293b)">Akses Dibatasi</h3>
                    <p style="color:var(--text-muted,#94a3b8);font-size:14px;margin:0">Anda tidak memiliki izin untuk mengakses dokumen.</p>
                </div>
            `);
        }
    },

    _populateDokumenTypeFilter(selector) {
        this.api('documents.php?action=list_types').done(res => {
            if (res && res.success && Array.isArray(res.data)) {
                const $select = $(selector);
                if (!$select.length) return;
                const currentVal = $select.val() || '';
                let opts = '<option value="">Semua Jenis Perangkat</option>';
                res.data.forEach(t => {
                    const val = t.nama_tipe;
                    opts += `<option value="${this.escapeHtml(val)}">${this.escapeHtml(val)}</option>`;
                });
                $select.html(opts).val(currentVal);
            }
        });
    },

    // ========== GURU: Load & Render Cards ==========
    _loadDokumenGuru() {
        this.api('documents.php?action=list').done(res => {
            if (!res.success) { $('#dokumenGuruCards').html('<div style="text-align:center;padding:40px;color:#94a3b8">Gagal memuat data.</div>'); return; }
            this._dokumenGuruCache = Array.isArray(res.data) ? res.data : [];
            this._renderDokumenGuruCards();
        });
    },

    _renderDokumenGuruCards() {
        const data = this._dokumenGuruCache || [];
        const filterStatus = ($('#dokGuruFilterStatus').val() || '').toLowerCase();
        const filterTipe = ($('#dokGuruFilterTipe').val() || '').trim().toLowerCase();
        const searchQuery = ($('#dokGuruSearchInput').val() || '').trim().toLowerCase();
        
        const pendingCount = data.filter(d => d.status === 'pending').length;
        const approvedCount = data.filter(d => d.status === 'approved').length;
        const rejectedCount = data.filter(d => d.status === 'rejected').length;
        
        $('#dokumenGuruSummary').html(
            `Total: <strong>${data.length}</strong> dokumen &nbsp;|&nbsp; ` +
            `<span style="color:#f59e0b">⏳ Pending: <strong>${pendingCount}</strong></span> &nbsp;|&nbsp; ` +
            `<span style="color:#22c55e">✅ Disetujui: <strong>${approvedCount}</strong></span> &nbsp;|&nbsp; ` +
            `<span style="color:#ef4444">❌ Ditolak: <strong>${rejectedCount}</strong></span>`
        );

        const filtered = data.filter(d => {
            if (filterStatus && (d.status || '').toLowerCase() !== filterStatus) return false;
            if (filterTipe && (d.tipe_dokumen || '').trim().toLowerCase() !== filterTipe) return false;
            if (searchQuery) {
                const haystack = [d.judul, d.tipe_dokumen].filter(Boolean).join(' ').toLowerCase();
                if (!haystack.includes(searchQuery)) return false;
            }
            return true;
        });
        
        if (filtered.length === 0) {
            $('#dokumenGuruCards').html(`
                <div style="text-align:center;padding:60px 20px;border:2px dashed var(--border-color,#e2e8f0);border-radius:12px;background:#fafbfc">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" width="48" height="48" style="margin-bottom:12px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <h3 style="color:var(--text-muted,#64748b);margin:0 0 6px;font-size:1rem">${data.length === 0 ? 'Belum Ada Dokumen' : 'Tidak Ada Dokumen yang Cocok'}</h3>
                    <p style="color:#94a3b8;font-size:0.85rem;margin:0">${data.length === 0 ? 'Klik tombol <strong>Upload Dokumen</strong> untuk mulai mengunggah.' : 'Coba sesuaikan filter atau kata kunci pencarian Anda.'}</p>
                </div>
            `);
            return;
        }

        const cards = filtered.map(d => {
            // Status styling
            let statusColor, statusBg, statusIcon, statusText;
            switch(d.status) {
                case 'approved':
                    statusColor = '#16a34a'; statusBg = '#f0fdf4'; statusIcon = '✅'; statusText = 'Disetujui';
                    break;
                case 'rejected':
                    statusColor = '#dc2626'; statusBg = '#fef2f2'; statusIcon = '❌'; statusText = 'Ditolak';
                    break;
                default:
                    statusColor = '#d97706'; statusBg = '#fffbeb'; statusIcon = '⏳'; statusText = 'Menunggu Persetujuan';
            }

            // File icon by extension
            const ext = (d.file_path || '').split('.').pop().toLowerCase();
            let fileIcon = '📄', fileColor = '#64748b';
            if (['pdf'].includes(ext)) { fileIcon = '📕'; fileColor = '#dc2626'; }
            else if (['doc','docx'].includes(ext)) { fileIcon = '📘'; fileColor = '#2563eb'; }
            else if (['xls','xlsx'].includes(ext)) { fileIcon = '📗'; fileColor = '#16a34a'; }
            else if (['ppt','pptx'].includes(ext)) { fileIcon = '📙'; fileColor = '#ea580c'; }
            else if (['zip'].includes(ext)) { fileIcon = '📦'; fileColor = '#7c3aed'; }

            // Date formatting
            const dateStr = d.created_at ? d.created_at.split(' ')[0] : '-';
            
            // Rejection note
            const rejNote = (d.status === 'rejected' && d.catatan_admin) 
                ? `<div style="margin-top:10px;padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:12px;color:#b91c1c">
                    <strong>📝 Catatan Admin:</strong> ${this.escapeHtml(d.catatan_admin)}
                   </div>` 
                : '';
            
            // Delete button (only for non-approved)
            const canDelete = d.status !== 'approved';
            const deleteBtn = canDelete 
                ? `<button onclick="Curriculum.deleteDokumen(${d.id})" style="padding:6px 12px;font-size:12px;background:#fee2e2;color:#dc2626;border:none;border-radius:6px;cursor:pointer;font-weight:500;transition:all .2s" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">🗑️ Hapus</button>`
                : '';

            return `
                <div style="background:white;border:1px solid var(--border-color,#e2e8f0);border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);transition:all .2s" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';this.style.transform='translateY(-1px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.06)';this.style.transform='none'">
                    <div style="padding:16px 20px;display:flex;gap:14px;align-items:flex-start">
                        <div style="font-size:32px;line-height:1;flex-shrink:0;margin-top:2px">${fileIcon}</div>
                        <div style="flex:1;min-width:0">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap">
                                <div style="flex:1;min-width:0">
                                    <h3 style="margin:0;font-size:0.95rem;font-weight:700;color:var(--text-color,#1e293b);line-height:1.3">${this.escapeHtml(d.judul)}</h3>
                                    <div style="display:flex;gap:10px;align-items:center;margin-top:6px;flex-wrap:wrap">
                                        <span style="font-size:12px;color:#64748b;display:flex;align-items:center;gap:3px">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                            ${dateStr}
                                        </span>
                                        <span style="font-size:11px;padding:2px 8px;background:#f1f5f9;border-radius:4px;color:#475569;font-weight:500">${this.escapeHtml(d.tipe_dokumen)}</span>
                                        <span style="font-size:11px;padding:2px 8px;background:${statusBg};border-radius:4px;color:${statusColor};font-weight:600">${statusIcon} ${statusText}</span>
                                    </div>
                                </div>
                            </div>
                            ${rejNote}
                        </div>
                    </div>
                    <div style="padding:0 20px 14px;display:flex;gap:8px;justify-content:flex-end;border-top:1px solid #f1f5f9;padding-top:12px;margin-top:0">
                        <a href="${this.baseUrl}${d.file_path}" target="_blank" style="padding:6px 12px;font-size:12px;background:#eff6ff;color:#2563eb;border:none;border-radius:6px;cursor:pointer;text-decoration:none;font-weight:500;display:inline-flex;align-items:center;gap:4px;transition:all .2s" onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            Buka File
                        </a>
                        ${deleteBtn}
                    </div>
                </div>
            `;
        }).join('');

        $('#dokumenGuruCards').html(`<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:16px">${cards}</div>`);
    },

    // ========== ADMIN: Load & Render Table ==========
    _loadDokumenAdmin() {
        this.api('documents.php?action=list').done(res => {
            if (!res.success) { $('#dokumenAdminTable').html('<div style="text-align:center;padding:40px;color:#94a3b8">Gagal memuat data.</div>'); return; }
            this._dokumenAdminCache = Array.isArray(res.data) ? res.data : [];
            this._renderDokumenAdminTable();
        });
    },

    _renderDokumenAdminTable() {
        const data = this._dokumenAdminCache || [];
        const filterStatus = ($('#dokFilterStatus').val() || '').toLowerCase();
        const filterTipe = ($('#dokFilterTipe').val() || '').trim().toLowerCase();
        const searchQuery = ($('#dokSearchInput').val() || '').trim().toLowerCase();

        const filtered = data.filter(d => {
            if (filterStatus && (d.status || '').toLowerCase() !== filterStatus) return false;
            if (filterTipe && (d.tipe_dokumen || '').trim().toLowerCase() !== filterTipe) return false;
            if (searchQuery) {
                const haystack = [d.judul, d.nama_guru, d.tipe_dokumen].filter(Boolean).join(' ').toLowerCase();
                if (!haystack.includes(searchQuery)) return false;
            }
            return true;
        });

        const pendingCount = data.filter(d => d.status === 'pending').length;
        const totalCount = data.length;
        $('#dokumenAdminSummary').html(
            `<strong>${filtered.length}</strong> dokumen tampil dari <strong>${totalCount}</strong> total. ` +
            (pendingCount > 0 ? `<span style="color:#d97706;font-weight:600">⏳ ${pendingCount} menunggu persetujuan</span>` : '<span style="color:#16a34a">✅ Semua sudah diproses</span>')
        );

        if (!filtered.length) {
            $('#dokumenAdminTable').html('<div style="text-align:center;padding:40px;color:#94a3b8">Tidak ada dokumen yang cocok.</div>');
            return;
        }

        const rows = filtered.map((d, i) => {
            let statusBadge, statusText;
            switch(d.status) {
                case 'approved': statusBadge = 'badge-success'; statusText = '✅ Disetujui'; break;
                case 'rejected': statusBadge = 'badge-danger'; statusText = '❌ Ditolak'; break;
                default: statusBadge = 'badge-warning'; statusText = '⏳ Pending'; break;
            }

            const catatan = (d.status === 'rejected' && d.catatan_admin) 
                ? `<div style="font-size:11px;color:#dc2626;margin-top:3px">📝 ${this.escapeHtml(d.catatan_admin)}</div>` : '';

            let aksi = '';
            if (d.status === 'pending') {
                aksi = `
                    <button class="btn-icon text-success" title="Setujui" onclick="Curriculum.approveDokumen(${d.id})" style="margin-right:4px">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                    <button class="btn-icon text-danger" title="Tolak" onclick="Curriculum.rejectDokumen(${d.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                `;
            }
            aksi += `
                <button class="btn-icon text-danger" title="Hapus" onclick="Curriculum.deleteDokumen(${d.id})" style="margin-left:4px">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
            `;

            return `<tr>
                <td>${i + 1}</td>
                <td>${(d.created_at||'').split(' ')[0]}</td>
                <td>${this.escapeHtml(d.nama_guru || '-')}</td>
                <td><strong>${this.escapeHtml(d.judul)}</strong>${catatan}</td>
                <td>${this.escapeHtml(d.tipe_dokumen)}</td>
                <td><span class="badge ${statusBadge}">${statusText}</span></td>
                <td><a href="${this.baseUrl}${d.file_path}" target="_blank" class="text-primary" style="text-decoration:none;font-size:13px">📎 Buka</a></td>
                <td><div style="display:flex;align-items:center">${aksi}</div></td>
            </tr>`;
        }).join('');

        $('#dokumenAdminTable').html(`
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead><tr>
                        <th>NO</th><th>TANGGAL</th><th>NAMA GURU</th><th>JUDUL DOKUMEN</th><th>TIPE</th><th>STATUS</th><th>FILE</th><th>AKSI</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `);
    },

    openUploadDokumenModal() {
        this.api('documents.php?action=list_types').done(res => {
            const types = (res.data && res.data.length > 0) ? res.data : [
                { nama_tipe: 'RPP', deskripsi: 'Rencana Pelaksanaan Pembelajaran' },
                { nama_tipe: 'Modul Ajar', deskripsi: 'Modul Ajar Kurikulum Merdeka' },
                { nama_tipe: 'Silabus', deskripsi: 'Silabus' },
                { nama_tipe: 'Prota / Promes', deskripsi: 'Program Tahunan & Semester' },
                { nama_tipe: 'ATP', deskripsi: 'Alur Tujuan Pembelajaran' },
                { nama_tipe: 'Lainnya', deskripsi: 'Dokumen Lainnya' }
            ];

            const typeOptions = types.map(t => {
                const desc = t.deskripsi ? ` (${this.escapeHtml(t.deskripsi)})` : '';
                return `<option value="${this.escapeHtml(t.nama_tipe)}">${this.escapeHtml(t.nama_tipe)}${desc}</option>`;
            }).join('');

            const modalHtml = `
                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">Judul Dokumen</label>
                    <input type="text" class="form-input-acad" id="dokumenJudul" placeholder="Contoh: RPP Matematika Kelas X">
                </div>
                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">Jenis / Tipe Perangkat</label>
                    <select class="form-select-acad" id="dokumenTipe">
                        ${typeOptions}
                    </select>
                </div>
                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">File (PDF/Word/Excel/PPT/ZIP, maks 10MB)</label>
                    <input type="file" class="form-input-acad" id="dokumenFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip">
                </div>
                <div style="font-size:12px;color:var(--text-muted,#94a3b8);background:#f8fafc;padding:10px;border-radius:6px;border:1px solid #e2e8f0">
                    <strong>ℹ️ Info:</strong> Dokumen yang diunggah akan menunggu persetujuan admin kurikulum sebelum dianggap valid.
                </div>
            `;

            EModal.form({
                title: 'Upload Dokumen Perangkat',
                form: modalHtml,
                size: 'md',
                confirmText: 'Upload',
                cancelText: 'Batal',
                onConfirm: () => this.uploadDokumen()
            });
        }).fail(() => {
            const defaultOptions = `
                <option value="RPP">RPP (Rencana Pelaksanaan Pembelajaran)</option>
                <option value="Modul Ajar">Modul Ajar</option>
                <option value="Silabus">Silabus</option>
                <option value="Prota/Promes">Prota/Promes</option>
                <option value="Lainnya">Lainnya</option>
            `;
            const modalHtml = `
                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">Judul Dokumen</label>
                    <input type="text" class="form-input-acad" id="dokumenJudul" placeholder="Contoh: RPP Matematika Kelas X">
                </div>
                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">Jenis / Tipe Perangkat</label>
                    <select class="form-select-acad" id="dokumenTipe">
                        ${defaultOptions}
                    </select>
                </div>
                <div class="form-group-acad mb-3">
                    <label class="form-label-acad">File (PDF/Word/Excel/PPT/ZIP, maks 10MB)</label>
                    <input type="file" class="form-input-acad" id="dokumenFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip">
                </div>
                <div style="font-size:12px;color:var(--text-muted,#94a3b8);background:#f8fafc;padding:10px;border-radius:6px;border:1px solid #e2e8f0">
                    <strong>ℹ️ Info:</strong> Dokumen yang diunggah akan menunggu persetujuan admin kurikulum sebelum dianggap valid.
                </div>
            `;
            EModal.form({
                title: 'Upload Dokumen Perangkat',
                form: modalHtml,
                size: 'md',
                confirmText: 'Upload',
                cancelText: 'Batal',
                onConfirm: () => this.uploadDokumen()
            });
        });
    },

    uploadDokumen() {
        const judul = $('#dokumenJudul').val();
        const tipe = $('#dokumenTipe').val();
        const fileInput = document.getElementById('dokumenFile');
        
        if (!judul || !tipe || fileInput.files.length === 0) {
            this.toast('Gagal', 'Judul, Tipe, dan File wajib diisi!', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('judul', judul);
        formData.append('tipe', tipe);
        formData.append('file', fileInput.files[0]);

        const btn = $('.acad-modal-footer .btn-primary, .emodal-footer .btn-primary').addClass('btn-loading').prop('disabled', true);
        
        $.ajax({
            url: `${this.moduleUrl}api/documents.php?action=upload`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'Authorization': `Bearer ${this.state.token}`
            },
            success: (res) => {
                if (res.success) {
                    this.toast('Berhasil', res.message, 'success');
                    EModal.closeAll();
                    // Reload based on view
                    if (this._dokumenGuruCache !== undefined) {
                        this._loadDokumenGuru();
                    } else {
                        this._loadDokumenAdmin();
                    }
                } else {
                    this.toast('Gagal', res.message || 'Error uploading file', 'error');
                }
                btn.removeClass('btn-loading').prop('disabled', false);
            },
            error: (err) => {
                const res = err.responseJSON || {};
                this.toast('Gagal', res.message || 'Server error', 'error');
                btn.removeClass('btn-loading').prop('disabled', false);
            }
        });
    },

    approveDokumen(id) {
        EModal.confirm({
            title: 'Setujui Dokumen',
            message: 'Yakin ingin menyetujui dokumen ini?',
            type: 'success',
            confirmText: 'Ya, Setujui',
            onConfirm: () => {
                this.api('documents.php?action=approve', { method: 'POST', data: { id } }).done(res => {
                    if (res.success) {
                        this.toast('Berhasil', res.message, 'success');
                        this._loadDokumenAdmin();
                    } else {
                        this.toast('Gagal', res.message, 'error');
                    }
                });
            }
        });
    },

    rejectDokumen(id) {
        const modalHtml = `
            <div class="form-group-acad mb-3">
                <label class="form-label-acad">Alasan Penolakan / Revisi</label>
                <textarea class="form-input-acad" id="rejectCatatan" rows="3" placeholder="Masukkan catatan revisi untuk guru..."></textarea>
            </div>
        `;
        EModal.form({
            title: 'Tolak Dokumen',
            form: modalHtml,
            size: 'md',
            confirmText: 'Tolak',
            cancelText: 'Batal',
            onConfirm: () => {
                const catatan = $('#rejectCatatan').val();
                if (!catatan) {
                    this.toast('Gagal', 'Catatan wajib diisi!', 'error');
                    return;
                }
                this.api('documents.php?action=reject', { method: 'POST', data: { id, catatan } }).done(res => {
                    if (res.success) {
                        this.toast('Berhasil', res.message, 'success');
                        EModal.closeAll();
                        this._loadDokumenAdmin();
                    } else {
                        this.toast('Gagal', res.message, 'error');
                    }
                });
            }
        });
    },

    deleteDokumen(id) {
        EModal.confirm({
            title: 'Hapus Dokumen',
            message: 'Apakah Anda yakin ingin menghapus dokumen ini?',
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('documents.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    if (res.success) {
                        this.toast('Berhasil', res.message, 'success');
                        if (this._dokumenGuruCache !== undefined) {
                            this._loadDokumenGuru();
                        } else {
                            this._loadDokumenAdmin();
                        }
                    } else {
                        this.toast('Gagal', res.message, 'error');
                    }
                });
            }
        });
    },

    // ==========================================
    // MASTER JENIS PERANGKAT (CRUD MODAL)
    // ==========================================
    openManageJenisDokumenModal() {
        const modalHtml = `
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:20px">
                <div style="font-size:13px;font-weight:700;color:var(--acad-text,#1e293b);margin-bottom:10px" id="lblFormJenis">
                    ➕ Tambah Jenis Perangkat Baru
                </div>
                <input type="hidden" id="f_jenisId" value="0">
                <div style="display:grid;grid-template-columns:1fr 1.5fr auto;gap:10px;align-items:center">
                    <input type="text" class="form-input-acad" id="f_jenisNama" placeholder="Nama Jenis (contoh: Modul P5)" style="padding:8px 12px;font-size:13px">
                    <input type="text" class="form-input-acad" id="f_jenisDesc" placeholder="Keterangan (contoh: Projek Penguatan Profil Pelajar Pancasila)" style="padding:8px 12px;font-size:13px">
                    <div style="display:flex;gap:6px">
                        <button type="button" class="btn-acad btn-acad-primary" id="btnSaveJenis" onclick="Curriculum.submitSaveJenisDokumen()" style="padding:8px 16px;font-size:13px;white-space:nowrap">
                            💾 Simpan
                        </button>
                        <button type="button" class="btn-acad btn-acad-outline" id="btnCancelEditJenis" onclick="Curriculum.resetJenisDokumenForm()" style="display:none;padding:8px 12px;font-size:13px">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <div style="font-size:13px;font-weight:700;color:var(--acad-text,#1e293b)">
                    📋 Daftar Jenis Perangkat Tersedia
                </div>
                <div id="jenisCountBadge" style="font-size:12px;color:var(--text-muted,#64748b)"></div>
            </div>
            <div id="jenisDokumenTableWrapper" style="max-height:300px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px">
                <div style="text-align:center;padding:30px;color:#94a3b8"><div class="spinner" style="margin:0 auto 8px"></div> Memuat daftar...</div>
            </div>
        `;

        EModal.form({
            title: 'Kelola Jenis Perangkat Pembelajaran',
            form: modalHtml,
            size: 'lg',
            confirmText: 'Tutup',
            cancelText: '',
            onOpen: () => {
                $('.emodal-card .emodal-footer .btn-ghost').hide();
                this._loadJenisDokumenList();
                $('#f_jenisNama, #f_jenisDesc').off('keydown').on('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.submitSaveJenisDokumen();
                    }
                });
            },
            onConfirm: () => {
                EModal.closeAll();
                return false;
            }
        });
    },

    _loadJenisDokumenList() {
        this.api('documents.php?action=list_types').done(res => {
            if (!res.success) {
                $('#jenisDokumenTableWrapper').html('<div style="text-align:center;padding:24px;color:#dc2626">Gagal memuat jenis perangkat.</div>');
                return;
            }
            const types = res.data || [];
            $('#jenisCountBadge').text(`${types.length} jenis terdaftar`);

            if (types.length === 0) {
                $('#jenisDokumenTableWrapper').html('<div style="text-align:center;padding:24px;color:#94a3b8">Belum ada jenis perangkat. Silakan tambahkan di atas.</div>');
                return;
            }

            const rows = types.map((t, idx) => {
                const safeNama = (t.nama_tipe || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                const safeDesc = (t.deskripsi || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                return `
                    <tr style="border-bottom:1px solid #f1f5f9">
                        <td style="padding:10px 14px;font-size:13px;color:#64748b;width:40px">${idx + 1}</td>
                        <td style="padding:10px 14px;font-size:13px;font-weight:600;color:#1e293b">${this.escapeHtml(t.nama_tipe)}</td>
                        <td style="padding:10px 14px;font-size:13px;color:#64748b">${this.escapeHtml(t.deskripsi || '-')}</td>
                        <td style="padding:10px 14px;text-align:right;width:140px;white-space:nowrap">
                            <button type="button" class="btn-acad btn-acad-sm btn-acad-outline" onclick="Curriculum.editJenisDokumen(${t.id}, '${safeNama}', '${safeDesc}')" style="padding:4px 10px;font-size:12px;margin-right:4px">
                                ✏️ Edit
                            </button>
                            <button type="button" class="btn-acad btn-acad-sm" onclick="Curriculum.deleteJenisDokumen(${t.id}, '${safeNama}')" style="padding:4px 10px;font-size:12px;background:#fee2e2;color:#dc2626;border:none">
                                🗑️ Hapus
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            $('#jenisDokumenTableWrapper').html(`
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left">
                            <th style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;width:40px">NO</th>
                            <th style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b">NAMA JENIS PERANGKAT</th>
                            <th style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b">DESKRIPSI / KETERANGAN</th>
                            <th style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;text-align:right;width:140px">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        }).fail(() => {
            $('#jenisDokumenTableWrapper').html('<div style="text-align:center;padding:24px;color:#dc2626">Terjadi kesalahan saat memuat data.</div>');
        });
    },

    editJenisDokumen(id, nama, desc) {
        $('#f_jenisId').val(id);
        $('#f_jenisNama').val(nama).focus();
        $('#f_jenisDesc').val(desc);
        $('#lblFormJenis').html(`✏️ Edit Jenis Perangkat: <strong>${this.escapeHtml(nama)}</strong>`);
        $('#btnSaveJenis').text('💾 Update');
        $('#btnCancelEditJenis').show();
    },

    resetJenisDokumenForm() {
        $('#f_jenisId').val('0');
        $('#f_jenisNama').val('');
        $('#f_jenisDesc').val('');
        $('#lblFormJenis').html('➕ Tambah Jenis Perangkat Baru');
        $('#btnSaveJenis').text('💾 Simpan');
        $('#btnCancelEditJenis').hide();
    },

    submitSaveJenisDokumen() {
        const id = parseInt($('#f_jenisId').val()) || 0;
        const nama_tipe = $('#f_jenisNama').val().trim();
        const deskripsi = $('#f_jenisDesc').val().trim();

        if (!nama_tipe) {
            this.toast('Gagal', 'Nama jenis perangkat wajib diisi!', 'error');
            $('#f_jenisNama').focus();
            return;
        }

        const $btn = $('#btnSaveJenis');
        const origText = id > 0 ? '💾 Update' : '💾 Simpan';
        $btn.prop('disabled', true).text('Menyimpan...');

        this.api('documents.php?action=save_type', {
            method: 'POST',
            data: { id, nama_tipe, deskripsi }
        }).done(res => {
            if (res && res.success) {
                this.toast('Berhasil', res.message || 'Jenis perangkat tersimpan', 'success');
                this.resetJenisDokumenForm();
                this._loadJenisDokumenList();
                this._populateDokumenTypeFilter('#dokFilterTipe');
                this._populateDokumenTypeFilter('#dokGuruFilterTipe');
            } else {
                this.toast('Gagal', (res && res.message) || 'Gagal menyimpan jenis perangkat', 'error');
            }
        }).fail(xhr => {
            let msg = 'Terjadi kesalahan saat menyimpan';
            try {
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    const parsed = JSON.parse(xhr.responseText);
                    if (parsed && parsed.message) msg = parsed.message;
                }
            } catch(e) {}
            this.toast('Gagal', msg, 'error');
        }).always(() => {
            $btn.prop('disabled', false).text(origText);
        });
    },

    deleteJenisDokumen(id, nama) {
        EModal.confirm({
            title: 'Hapus Jenis Perangkat',
            message: `Apakah Anda yakin ingin menghapus jenis perangkat <strong>${this.escapeHtml(nama)}</strong>?`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('documents.php?action=delete_type', {
                    method: 'POST',
                    data: { id }
                }).done(res => {
                    if (res && res.success) {
                        this.toast('Berhasil', res.message || 'Jenis perangkat dihapus', 'success');
                        this.resetJenisDokumenForm();
                        this._loadJenisDokumenList();
                        this._populateDokumenTypeFilter('#dokFilterTipe');
                        this._populateDokumenTypeFilter('#dokGuruFilterTipe');
                    } else {
                        this.toast('Gagal', (res && res.message) || 'Gagal menghapus jenis perangkat', 'error');
                    }
                }).fail(xhr => {
                    let msg = 'Gagal menghapus jenis perangkat';
                    try {
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    } catch(e) {}
                    this.toast('Gagal', msg, 'error');
                });
            }
        });
    }

};

// Bootstrap
document.addEventListener('DOMContentLoaded', () => {
    Curriculum.init();
});
