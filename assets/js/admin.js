/**
 * E-Portal Superadmin Panel
 * Complete admin interface for managing users, modules, and settings
 */
const Admin = {
    currentSection: 'dashboard',

    render(container, section = 'dashboard') {
        this.currentSection = section;
        const user = App.state.user;
        const school = App.state.school;

        if (user.role !== 'superadmin') {
            App.navigate('dashboard');
            EModal.toast({ type: 'error', title: 'Akses Ditolak', message: 'Hanya superadmin.' });
            return;
        }

        container.innerHTML = `
        <div class="admin-wrapper">
            <div class="sidebar-overlay" id="sidebarOverlay" onclick="Admin.closeSidebar()"></div>
            <aside class="admin-sidebar" id="adminSidebar">
                <div class="sidebar-header">
                    <div class="sidebar-logo">
                        ${school.icon ? `<img src="${App.baseUrl}${school.icon}" alt="Logo">` : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>`}
                    </div>
                    <div class="sidebar-brand">
                        <h3>Admin Panel</h3>
                        <span>E-Portal</span>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <div class="sidebar-nav-group">
                        <div class="sidebar-nav-label">Ringkasan</div>
                        <button class="sidebar-nav-item ${section==='dashboard'?'active':''}" data-section="dashboard" onclick="Admin.goTo('dashboard')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                            Dashboard
                        </button>
                    </div>
                    <div class="sidebar-nav-group">
                        <div class="sidebar-nav-label">Data Akademik</div>
                        <button class="sidebar-nav-item ${section==='academic-years'?'active':''}" data-section="academic-years" onclick="Admin.goTo('academic-years')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-5"/></svg>
                            Tahun Ajaran
                        </button>
                        <button class="sidebar-nav-item ${section==='students'?'active':''}" data-section="students" onclick="Admin.goTo('students')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10.5 12 5 2 10.5 12 16l10-5.5Z"/><path d="M6 13v4c2 1.5 10 1.5 12 0v-4"/><path d="M12 16v5"/></svg>
                            Data Siswa
                        </button>
                        <button class="sidebar-nav-item ${section==='foto-siswa'?'active':''}" data-section="foto-siswa" onclick="Admin.goTo('foto-siswa')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            Foto Siswa
                        </button>
                        <button class="sidebar-nav-item ${section==='gurus'?'active':''}" data-section="gurus" onclick="Admin.goTo('gurus')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Data Guru
                        </button>
                        <button class="sidebar-nav-item ${section==='referensi'?'active':''}" data-section="referensi" onclick="Admin.goTo('referensi')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/><circle cx="12" cy="12" r="10" opacity="0.3"/></svg>
                            Data Referensi
                        </button>
                    </div>
                    <div class="sidebar-nav-group">
                        <div class="sidebar-nav-label">Administrasi Portal</div>
                        <button class="sidebar-nav-item ${section==='users'?'active':''}" data-section="users" onclick="Admin.goTo('users')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Kelola User
                        </button>
                        <button class="sidebar-nav-item ${section==='modules'?'active':''}" data-section="modules" onclick="Admin.goTo('modules')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8"/></svg>
                            Kelola Modul
                        </button>
                    </div>
                    <div class="sidebar-nav-group">
                        <div class="sidebar-nav-label">Sistem</div>
                        <button class="sidebar-nav-item ${section==='settings'?'active':''}" data-section="settings" onclick="Admin.goTo('settings')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            Pengaturan
                        </button>
                        <button class="sidebar-nav-item ${section==='reset-data'?'active':''}" data-section="reset-data" onclick="Admin.goTo('reset-data')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="m12 7v5l4 2"/></svg>
                            Reset Data
                        </button>
                    </div>
                </nav>
                <div class="sidebar-footer">
                    <div class="sidebar-user">
                        <div class="sidebar-user-avatar">${App.getInitials(user.nama_lengkap)}</div>
                        <div class="sidebar-user-info">
                            <div class="name">${App.escapeHtml(user.nama_lengkap)}</div>
                            <div class="role">Super Admin</div>
                        </div>
                    </div>
                </div>
            </aside>
            <main class="admin-main">
                <header class="admin-topbar">
                    <div class="admin-topbar-left">
                        <button class="menu-toggle" onclick="Admin.toggleSidebar()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                        </button>
                        <h1 class="admin-page-title" id="adminPageTitle">Dashboard</h1>
                    </div>
                    <div class="admin-topbar-right">
                        <button class="back-to-portal" onclick="App.navigate('dashboard')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                            Kembali ke Portal
                        </button>
                    </div>
                </header>
                <div class="admin-content" id="adminContent">
                    <div class="skeleton skeleton-card" style="height:200px;margin-bottom:16px"></div>
                </div>
            </main>
        </div>`;

        this.loadSection(section);
    },

    goTo(section) {
        window.location.hash = '#/admin/' + section;
    },

    toggleSidebar() {
        $('#adminSidebar').toggleClass('show');
        $('#sidebarOverlay').toggleClass('show');
    },

    closeSidebar() {
        $('#adminSidebar').removeClass('show');
        $('#sidebarOverlay').removeClass('show');
    },

    loadSection(section) {
        this.closeSidebar();
        const titles = { dashboard:'Dashboard', users:'Kelola User', gurus:'Data Guru', students:'Data Siswa', 'foto-siswa':'Foto Siswa', referensi: 'Data Referensi', modules:'Kelola Modul', 'academic-years':'Tahun Ajaran', settings:'Pengaturan', 'reset-data':'Reset Data' };
        $('#adminPageTitle').text(titles[section] || 'Dashboard');
        $('.sidebar-nav-item').removeClass('active');
        $(`.sidebar-nav-item[data-section="${section}"]`).addClass('active');

        switch(section) {
            case 'dashboard': this.renderAdminDashboard(); break;
            case 'users': this.renderUserTabs(this.userTab || 'gurus'); break;
            case 'gurus': this.usersType = 'gurus'; this.renderUsers("gurus"); break;
            case 'students': this.renderStudents(); break;
            case 'foto-siswa': this.renderFotoSiswa(); break;
            case 'referensi': this.renderReferensi(); break;
            case 'modules': this.renderModules(); break;
            case 'academic-years': this.renderAcademicYears(); break;
            case 'settings': this.renderSettings(); break;
            case 'reset-data': this.renderResetData(); break;
            default: this.renderAdminDashboard();
        }
    },

    // ==================== ADMIN DASHBOARD ====================
    renderAdminDashboard() {
        const user = App.state.user;
        const activeYear = App.state.academicYear;

        $('#adminContent').html(`
            <div style="display:flex; flex-direction:column; gap:20px;">
                <div class="skeleton" style="height:120px; border-radius:16px;"></div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px;">
                    <div class="skeleton" style="height:90px; border-radius:12px;"></div>
                    <div class="skeleton" style="height:90px; border-radius:12px;"></div>
                    <div class="skeleton" style="height:90px; border-radius:12px;"></div>
                    <div class="skeleton" style="height:90px; border-radius:12px;"></div>
                </div>
                <div style="display:grid; grid-template-columns:1fr; gap:24px;">
                    <div class="skeleton" style="height:200px; border-radius:12px;"></div>
                </div>
            </div>
        `);

        App.api('api/users.php?action=stats').done(res => {
            if (!res.success) return;
            const s = res.data;

            const formatTimeAgo = (dateStr) => {
                try {
                    const date = new Date(dateStr.replace(/-/g, '/')); // Fix Safari parse
                    const seconds = Math.floor((new Date() - date) / 1000);
                    if (seconds < 60) return 'baru saja';
                    const minutes = Math.floor(seconds / 60);
                    if (minutes < 60) return `${minutes} menit lalu`;
                    const hours = Math.floor(minutes / 60);
                    if (hours < 24) return `${hours} jam lalu`;
                    const days = Math.floor(hours / 24);
                    return `${days} hari lalu`;
                } catch(e) {
                    return 'beberapa saat lalu';
                }
            };

            let recentJournalsHtml = '';
            if (s.recent_journals && s.recent_journals.length > 0) {
                recentJournalsHtml = s.recent_journals.map(j => {
                    const timeLabel = formatTimeAgo(j.created_at || j.tanggal);
                    return `
                        <div style="padding:12px 16px; border-left:4px solid var(--primary); background:#f8fafc; border-radius: 0 8px 8px 0; border-top:1px solid #e2e8f0; border-right:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                            <div>
                                <strong style="font-size:0.9rem; color:#1e293b; display:block;">${App.escapeHtml(j.guru_nama)}</strong>
                                <span style="font-size:0.78rem; color:#64748b;">Kelas: <strong>${App.escapeHtml(j.nama_kelas || '-')}</strong> • Mapel: <strong>${App.escapeHtml(j.nama_mapel || '-')}</strong> • Jam ke: ${App.escapeHtml(j.jam_ke || '-')}</span>
                            </div>
                            <span style="font-size:0.7rem; color:#94a3b8; font-weight:700; white-space:nowrap;">${timeLabel}</span>
                        </div>
                    `;
                }).join('');
            } else {
                recentJournalsHtml = `
                    <div style="text-align:center; padding:40px 20px; color:#94a3b8; font-style:italic; font-size:0.85rem; border: 1px dashed #cbd5e1; border-radius:8px; background:#f8fafc;">
                        Belum ada aktivitas pengisian jurnal guru hari ini.
                    </div>
                `;
            }

            $('#adminContent').html(`
                <style>
                    .admin-details-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 24px;
                    }
                    @media (max-width: 900px) {
                        .admin-details-grid {
                            grid-template-columns: 1fr !important;
                        }
                    }
                </style>
                <div style="display:flex; flex-direction:column; gap:24px;">
                    <!-- HERO BANNER -->
                    <div class="admin-welcome-hero fade-in" style="background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%); color: white; padding: 28px 32px; border-radius: 16px; box-shadow: 0 10px 25px rgba(37,99,235,0.15); display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden;">
                        <div style="z-index: 2;">
                            <h2 style="margin: 0 0 8px 0; font-size: 1.6rem; font-weight: 800; font-family: var(--font-heading);">Selamat Datang Kembali, ${App.escapeHtml(user.nama_lengkap)}! 👋</h2>
                            <p style="margin: 0; opacity: 0.85; font-size: 0.95rem;">Kelola administrasi portal sekolah, modul akademik, dan pengawasan aktivitas jurnal guru.</p>
                        </div>
                        <div style="z-index: 2; text-align: right; flex-shrink: 0;">
                            <span style="background: rgba(255,255,255,0.15); padding: 8px 16px; border-radius: 30px; font-size: 0.8rem; font-weight: 700; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                TA: ${activeYear?.tahun_ajaran ? App.escapeHtml(activeYear.tahun_ajaran) : 'Belum Diatur'} (${activeYear?.semester === '1' ? 'Ganjil' : 'Genap'})
                            </span>
                        </div>
                        <div style="position: absolute; right: -50px; top: -50px; width: 180px; height: 180px; background: rgba(255,255,255,0.08); border-radius: 50%; z-index: 1;"></div>
                        <div style="position: absolute; left: 40%; bottom: -80px; width: 220px; height: 220px; background: rgba(255,255,255,0.05); border-radius: 50%; z-index: 1;"></div>
                    </div>

                    <!-- METRICS GRID -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
                        <!-- Total Siswa -->
                        <div class="stat-card fade-in" style="background: white; padding: 22px 24px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 16px; border: 1px solid #f1f5f9;">
                            <div style="background: rgba(37,99,235,0.1); color: var(--primary); width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink:0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <div><h3 style="margin:0 0 2px 0; font-size:1.5rem; font-weight:800; color:#1e293b;">${s.total_students}</h3><p style="margin:0; color:#64748b; font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Total Siswa</p></div>
                        </div>
                        <!-- Total Guru -->
                        <div class="stat-card fade-in" style="background: white; padding: 22px 24px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 16px; border: 1px solid #f1f5f9; animation-delay:0.05s">
                            <div style="background: rgba(16,185,129,0.1); color: var(--success); width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink:0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <div><h3 style="margin:0 0 2px 0; font-size:1.5rem; font-weight:800; color:#1e293b;">${s.total_teachers}</h3><p style="margin:0; color:#64748b; font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Total Guru</p></div>
                        </div>
                        <!-- Total Kelas -->
                        <div class="stat-card fade-in" style="background: white; padding: 22px 24px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 16px; border: 1px solid #f1f5f9; animation-delay:0.1s">
                            <div style="background: rgba(245,158,11,0.1); color: #d97706; width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink:0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            </div>
                            <div><h3 style="margin:0 0 2px 0; font-size:1.5rem; font-weight:800; color:#1e293b;">${s.total_classes}</h3><p style="margin:0; color:#64748b; font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Total Kelas</p></div>
                        </div>
                        <!-- Jurnal Hari Ini -->
                        <div class="stat-card fade-in" style="background: white; padding: 22px 24px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 16px; border: 1px solid #f1f5f9; animation-delay:0.15s">
                            <div style="background: rgba(239,68,68,0.1); color: #dc2626; width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink:0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div><h3 style="margin:0 0 2px 0; font-size:1.5rem; font-weight:800; color:#1e293b;">${s.total_journals_today}</h3><p style="margin:0; color:#64748b; font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Jurnal Hari Ini</p></div>
                        </div>
                    </div>

                    <!-- DETAILS SPLIT SECTION -->
                    <div class="admin-details-grid">
                        <!-- LEFT: PORTAL SYSTEM & MODULES INFO -->
                        <div class="admin-card fade-in" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.01); display:flex; flex-direction:column; gap:16px; animation-delay:0.2s">
                            <h3 style="margin:0; font-size:1.05rem; font-family:var(--font-heading); color:#0f172a; display:flex; align-items:center; gap:8px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                Sistem Portal & Modul
                            </h3>
                            <div style="display:flex; flex-direction:column; gap:12px;">
                                <div style="padding:14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                                    <span style="color:#64748b; font-size:0.875rem;">Status Kepegawaian</span>
                                    <strong style="color:#1e293b;">${s.total_teachers} Guru Terdaftar</strong>
                                </div>
                                <div style="padding:14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                                    <span style="color:#64748b; font-size:0.875rem;">Total User Sistem</span>
                                    <strong style="color:#1e293b;">${s.total_users} Akun (${s.active_users} Aktif)</strong>
                                </div>
                                <div style="padding:14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                                    <span style="color:#64748b; font-size:0.875rem;">Modul Portal</span>
                                    <strong style="color:#1e293b;">${s.total_modules} Modul Aktif</strong>
                                </div>
                                <div style="padding:14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                                    <span style="color:#64748b; font-size:0.875rem;">Skema Migrasi DB</span>
                                    <strong style="color:#1e293b;">Versi 3.0.0</strong>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT: LIVE ACTIVITY JOURNAL FEED -->
                        <div class="admin-card fade-in" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.01); display:flex; flex-direction:column; gap:16px; animation-delay:0.25s">
                            <h3 style="margin:0; font-size:1.05rem; font-family:var(--font-heading); color:#0f172a; display:flex; align-items:center; gap:8px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                Jurnal Mengajar Terbaru (Live Feed)
                            </h3>
                            <div style="display:flex; flex-direction:column; gap:12px;">
                                ${recentJournalsHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `);
        });
    },


    // ==================== USERS & GURU SECTION ====================
    usersPage: 1,
    usersSearch: '',
    usersType: 'users', // 'users' or 'gurus'
    userTab: 'gurus',
    accountTeacherSearch: '',
    accountStudentSearch: '',
    accountStudentYearId: '',

    renderUserTabs(activeTab = 'gurus') {
        this.userTab = activeTab === 'students' ? 'students' : 'gurus';
        $('#adminContent').html(`
            <div class="user-tabs-shell">
                <div class="admin-card user-tabs-card">
                    <div class="user-tabs-hero">
                        <div class="user-tabs-hero-main">
                            <div class="user-tabs-hero-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <div>
                                <h3>Kelola User</h3>
                                <p>Kelola akun guru dan data siswa dari satu tempat dengan tab yang terpisah.</p>
                            </div>
                        </div>
                        <div class="admin-tabs user-management-tabs">
                            <button class="admin-tab ${this.userTab === 'gurus' ? 'active' : ''}" onclick="Admin.switchUserTab('gurus')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                Guru
                            </button>
                            <button class="admin-tab ${this.userTab === 'students' ? 'active' : ''}" onclick="Admin.switchUserTab('students')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10.5 12 5 2 10.5 12 16l10-5.5Z"/><path d="M6 13v4c2 1.5 10 1.5 12 0v-4"/></svg>
                                Siswa
                            </button>
                        </div>
                    </div>
                </div>
                <div id="userTabContent"></div>
            </div>
        `);
        this.renderUserTabContent(this.userTab);
    },

    switchUserTab(tab) {
        this.userTab = tab === 'students' ? 'students' : 'gurus';
        $('.user-management-tabs .admin-tab').removeClass('active');
        $(`.user-management-tabs .admin-tab:eq(${this.userTab === 'gurus' ? 0 : 1})`).addClass('active');
        this.renderUserTabContent(this.userTab);
    },

    renderUserTabContent(tab) {
        if (tab === 'students') {
            this.renderStudentAccounts();
            return;
        }
        this.renderTeacherAccounts();
    },

    renderTeacherAccounts() {
        $('#userTabContent').html(`
            <div class="admin-card account-card">
                <div class="admin-card-header">
                    <div>
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg> Akun Guru</h3>
                        <p class="admin-subtitle">Username akun guru otomatis memakai <strong>NIK</strong>. Password default: <strong>1234567</strong>.</p>
                    </div>
                    <div class="admin-toolbar account-toolbar-inline">
                        <div class="search-box">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" placeholder="Cari nama, NIK, username, jabatan..." id="accountTeacherSearch" value="${App.escapeHtml(this.accountTeacherSearch)}">
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="Admin.generateTeacherAccounts()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/><path d="M3 12a9 9 0 0 1 15.74-6.26L21 8"/><path d="M16 8h5V3"/></svg>
                            Generate Akun
                        </button>
                    </div>
                </div>
                <div class="account-summary" id="teacherAccountSummary">
                    <span>Memuat akun guru...</span>
                </div>
                <div class="admin-card-body">
                    <div class="data-table-wrapper" id="teacherAccountTable"><div class="skeleton" style="height:300px;border-radius:8px;"></div></div>
                </div>
            </div>
        `);
        $('#accountTeacherSearch').on('input', App.debounce(e => {
            this.accountTeacherSearch = e.target.value;
            this.loadTeacherAccounts();
        }, 300));
        this.loadTeacherAccounts();
    },

    loadTeacherAccounts() {
        const q = encodeURIComponent(this.accountTeacherSearch || '');
        App.api(`api/accounts.php?action=teachers&search=${q}`).done(res => {
            if (!res.success) return;
            const rows = res.data?.data || [];
            const ready = res.data?.ready || 0;
            const total = res.data?.total || 0;
            const missingNik = res.data?.missing_nik || 0;
            $('#teacherAccountSummary').html(`
                <span><strong>${ready}</strong> dari <strong>${total}</strong> akun guru sudah siap.</span>
                <span>Generate hanya memproses guru yang username-nya belum sama dengan NIK${missingNik ? ` - ${missingNik} guru belum punya NIK` : ''}.</span>
            `);
            if (!rows.length) {
                $('#teacherAccountTable').html('<div class="empty-state"><h3>Data Guru Tidak Ditemukan</h3><p>Coba kata kunci lain atau tambahkan Data Guru terlebih dahulu.</p></div>');
                return;
            }
            const html = rows.map(g => `
                <tr>
                    <td><div class="d-flex gap-sm" style="align-items:center">
                        <div class="user-avatar" style="width:32px;height:32px;font-size:11px;border:none;cursor:default;">${App.getInitials(g.nama_lengkap)}</div>
                        <div><strong style="font-size:13px">${App.escapeHtml(g.nama_lengkap)}</strong><br><span class="text-muted" style="font-size:11px">NIK: ${App.escapeHtml(g.nik || '-')} - ${App.escapeHtml(g.jabatan || '-')}</span></div>
                    </div></td>
                    <td>
                        <div class="account-credential"><code>${App.escapeHtml(g.username || '-')}</code></div>
                        ${g.has_generated_account ? '' : `<div class="account-muted">Saran: ${App.escapeHtml(g.suggested_username || 'Isi NIK dulu')}</div>`}
                    </td>
                    <td><code class="account-password">${App.escapeHtml(g.default_password || '1234567')}</code></td>
                    <td><span class="badge ${g.has_generated_account ? 'badge-success' : 'badge-warning'}">${App.escapeHtml(g.account_status || '-')}</span></td>
                    <td style="font-size:12px;color:var(--text-muted)">${g.last_login ? App.formatDate(g.last_login) : '-'}</td>
                    <td><div class="actions">
                        <button class="btn-icon" title="Ubah Username" onclick='Admin.editTeacherUsername(${JSON.stringify(g).replace(/'/g,"&#39;")})'><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button>
                        <button class="btn-icon" title="Reset Password" onclick="Admin.showResetPasswordModal(${g.id}, '${App.escapeHtml(g.nama_lengkap)}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg></button>
                        <button class="btn-icon" title="Reset Akun ke Default" onclick="Admin.resetTeacherAccount(${g.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>
                    </div></td>
                </tr>
            `).join('');
            $('#teacherAccountTable').html(`<table class="data-table account-table"><thead><tr><th>Guru</th><th>Username (NIK)</th><th>Password Default</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr></thead><tbody>${html}</tbody></table>`);
        }).fail(xhr => {
            $('#teacherAccountTable').html(`<div class="empty-state"><h3>Gagal Memuat</h3><p>${xhr.responseJSON?.message || 'Terjadi kesalahan.'}</p></div>`);
        });
    },

    editTeacherUsername(guru) {
        const current = guru.username || guru.suggested_username || '';
        EModal.form({
            title: 'Ubah Username Guru',
            size: 'md',
            form: `
                <div class="form-group">
                    <label class="form-label">Nama Guru</label>
                    <input class="form-input" value="${App.escapeHtml(guru.nama_lengkap || '-')}" readonly style="opacity:0.75">
                </div>
                <div class="form-group">
                    <label class="form-label">NIK</label>
                    <input class="form-input" value="${App.escapeHtml(guru.nik || '-')}" readonly style="opacity:0.75">
                </div>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input class="form-input" id="teacherUsernameInput" value="${App.escapeHtml(current)}" placeholder="contoh: 7201010203040001">
                    <p class="account-help">Disarankan sama dengan NIK agar konsisten dengan proses generate akun.</p>
                </div>
            `,
            confirmText: 'Simpan Username',
            onOpen: () => {
                setTimeout(() => $('#teacherUsernameInput').trigger('focus').select(), 50);
            },
            onConfirm: () => {
                const value = ($('#teacherUsernameInput').val() || '').trim();
                if (!value) {
                    EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Username wajib diisi.' });
                    return false;
                }
                App.api('api/accounts.php?action=update-teacher-username', {
                    method: 'POST',
                    data: { id: guru.id, username: value }
                }).done(res => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: `Username diubah menjadi ${res.data?.username || value}` });
                    this.loadTeacherAccounts();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Username gagal disimpan.' });
                });
                return false;
            }
        });
    },

    generateTeacherAccounts() {
        EModal.confirm({
            title: 'Generate Akun Guru',
            message: 'Generate akan menyamakan username guru menjadi NIK dan password default <strong>1234567</strong>. Guru tanpa NIK akan dilewati.',
            type: 'info',
            confirmText: 'Generate',
            onConfirm: () => {
                const loader = EModal.loading('Generate akun guru...');
                App.api('api/accounts.php?action=generate-teachers', { method: 'POST', data: {} }).done(res => {
                    EModal.close(loader);
                    EModal.info({ type: 'success', title: 'Selesai', message: `${res.data?.generated || 0} akun dibuat, ${res.data?.skipped || 0} dilewati${res.data?.failed ? `, ${res.data.failed} gagal` : ''}. Password default: ${res.data?.default_password || '1234567'}` });
                    this.loadTeacherAccounts();
                }).fail(xhr => {
                    EModal.close(loader);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Generate gagal.' });
                });
            }
        });
    },

    resetTeacherAccount(id) {
        EModal.confirm({
            title: 'Reset Akun Guru',
            message: 'Username akan diset ke NIK guru, dan password direset ke <strong>1234567</strong>.',
            type: 'warning',
            confirmText: 'Reset Akun',
            onConfirm: () => {
                const loader = EModal.loading('Reset akun guru...');
                App.api('api/accounts.php?action=reset-teacher', { method: 'POST', data: { id } }).done(res => {
                    EModal.close(loader);
                    EModal.info({ type: 'success', title: 'Akun Direset', message: `Username: <strong>${App.escapeHtml(res.data?.username || '-')}</strong><br>Password: <strong>${App.escapeHtml(res.data?.password || '1234567')}</strong>` });
                    this.loadTeacherAccounts();
                }).fail(xhr => {
                    EModal.close(loader);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Reset gagal.' });
                });
            }
        });
    },

    renderStudentAccounts() {
        const active = App.state.academicYear;
        $('#userTabContent').html(`
            <div class="admin-card account-card">
                <div class="admin-card-header">
                    <div>
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10.5 12 5 2 10.5 12 16l10-5.5Z"/><path d="M6 13v4c2 1.5 10 1.5 12 0v-4"/></svg> Akun Siswa</h3>
                        <p class="admin-subtitle">Username siswa memakai <strong>NIS</strong>, password memakai tanggal lahir format <strong>DDMMYYYY</strong>. Tahun aktif: <strong>${active?.tahun_ajaran ? App.escapeHtml(active.tahun_ajaran) + ' Semester ' + App.escapeHtml(active.semester || '-') : 'Belum diatur'}</strong></p>
                    </div>
                    <div class="admin-toolbar account-toolbar-inline">
                        <select class="form-select student-year-select" id="accountStudentYear"><option value="">Tahun aktif</option></select>
                        <div class="search-box">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" placeholder="Cari NIS, nama, kelas..." id="accountStudentSearch" value="${App.escapeHtml(this.accountStudentSearch)}">
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="Admin.generateStudentAccounts()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/><path d="M3 12a9 9 0 0 1 15.74-6.26L21 8"/><path d="M16 8h5V3"/></svg>
                            Generate Akun
                        </button>
                    </div>
                </div>
                <div class="account-summary" id="studentAccountSummary">
                    <span>Memuat akun siswa...</span>
                </div>
                <div class="admin-card-body">
                    <div class="data-table-wrapper" id="studentAccountTable"><div class="skeleton" style="height:300px;border-radius:8px;"></div></div>
                </div>
            </div>
        `);
        this.loadAcademicYearOptions('#accountStudentYear', this.accountStudentYearId);
        $('#accountStudentYear').on('change', e => {
            this.accountStudentYearId = e.target.value;
            this.loadStudentAccounts();
        });
        $('#accountStudentSearch').on('input', App.debounce(e => {
            this.accountStudentSearch = e.target.value;
            this.loadStudentAccounts();
        }, 300));
        this.loadStudentAccounts();
    },

    loadStudentAccounts() {
        const q = encodeURIComponent(this.accountStudentSearch || '');
        const year = this.accountStudentYearId ? `&academic_year_id=${this.accountStudentYearId}` : '';
        App.api(`api/accounts.php?action=students&search=${q}${year}`).done(res => {
            if (!res.success) return;
            const rows = res.data?.data || [];
            const ready = res.data?.ready || 0;
            const total = res.data?.total || 0;
            $('#studentAccountSummary').html(`
                <span><strong>${ready}</strong> dari <strong>${total}</strong> akun siswa sudah siap.</span>
                <span>Generate hanya membuat akun siswa yang belum punya akun.</span>
            `);
            if (!rows.length) {
                $('#studentAccountTable').html('<div class="empty-state"><h3>Data Siswa Tidak Ditemukan</h3><p>Coba kata kunci lain atau tambahkan Data Siswa terlebih dahulu.</p></div>');
                return;
            }
            const html = rows.map(s => `
                <tr>
                    <td><div class="d-flex gap-sm" style="align-items:center">
                        <div class="user-avatar" style="width:32px;height:32px;font-size:11px;border:none;cursor:default;">${App.getInitials(s.nama)}</div>
                        <div><strong style="font-size:13px">${App.escapeHtml(s.nama)}</strong><br><span class="text-muted" style="font-size:11px">${App.escapeHtml(s.kelas || '-')}</span></div>
                    </div></td>
                    <td>
                        <code>${App.escapeHtml(s.username || s.suggested_username || s.nis || '-')}</code>
                        ${s.username && s.username !== s.suggested_username ? `<div class="account-muted">Saran: ${App.escapeHtml(s.suggested_username || '-')}</div>` : ''}
                    </td>
                    <td><code class="account-password">${App.escapeHtml(s.default_password || '-')}</code></td>
                    <td><span class="badge ${s.has_generated_account ? 'badge-success' : 'badge-warning'}">${App.escapeHtml(s.account_label || '-')}</span></td>
                    <td style="font-size:12px;color:var(--text-muted)">${s.last_login ? App.formatDate(s.last_login) : '-'}</td>
                    <td><div class="actions">
                        <button class="btn-icon" title="Reset Akun" onclick="Admin.resetStudentAccount(${s.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/><path d="M12 7v5l3 2"/></svg></button>
                    </div></td>
                </tr>
            `).join('');
            $('#studentAccountTable').html(`<table class="data-table account-table"><thead><tr><th>Siswa</th><th>Username / NIS</th><th>Password Default</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr></thead><tbody>${html}</tbody></table>`);
        }).fail(xhr => {
            $('#studentAccountTable').html(`<div class="empty-state"><h3>Gagal Memuat</h3><p>${xhr.responseJSON?.message || 'Terjadi kesalahan.'}</p></div>`);
        });
    },

    generateStudentAccounts() {
        EModal.confirm({
            title: 'Generate Akun Siswa',
            message: 'Generate akan membuat akun dari Data Siswa yang belum punya akun. Akun yang sudah ada tidak akan dibuat ulang.',
            type: 'info',
            confirmText: 'Generate',
            onConfirm: () => {
                const loader = EModal.loading('Generate akun siswa...');
                App.api('api/accounts.php?action=generate-students', { method: 'POST', data: { academic_year_id: this.accountStudentYearId } }).done(res => {
                    EModal.close(loader);
                    EModal.info({ type: 'success', title: 'Selesai', message: `${res.data?.generated || 0} akun dibuat, ${res.data?.skipped || 0} dilewati${res.data?.failed ? `, ${res.data.failed} gagal karena data belum lengkap` : ''}.` });
                    this.loadStudentAccounts();
                }).fail(xhr => {
                    EModal.close(loader);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Generate gagal.' });
                });
            }
        });
    },

    resetStudentAccount(studentId) {
        EModal.confirm({
            title: 'Reset Akun Siswa',
            message: 'Username akan diset ke NIS dan password direset ke tanggal lahir format <strong>DDMMYYYY</strong>.',
            type: 'warning',
            confirmText: 'Reset Akun',
            onConfirm: () => {
                const loader = EModal.loading('Reset akun siswa...');
                App.api('api/accounts.php?action=reset-student', { method: 'POST', data: { student_id: studentId } }).done(res => {
                    EModal.close(loader);
                    EModal.info({ type: 'success', title: 'Akun Direset', message: `Username: <strong>${App.escapeHtml(res.data?.username || '-')}</strong><br>Password: <strong>${App.escapeHtml(res.data?.password || '-')}</strong>` });
                    this.loadStudentAccounts();
                }).fail(xhr => {
                    EModal.close(loader);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Reset gagal.' });
                });
            }
        });
    },

    renderUsers(type = "users", embedded = false) {
        this.usersType = type;
        const icon = type === 'gurus' 
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>' 
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>';
        
        const target = embedded ? '#userTabContent' : '#adminContent';
        $(target).html(`
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>${icon} ${type === 'gurus' ? 'Data Guru' : 'Kelola User'}</h3>
                    <div class="admin-toolbar">
                        <div class="search-box">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" placeholder="Cari nama, NIK, atau username..." id="userSearch" value="${this.usersSearch}">
                        </div>
                        <button class="btn btn-danger btn-sm" id="bulkDeleteUsersBtn" style="display:none;" onclick="Admin.bulkDeleteUsers('${type}')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Hapus Terpilih
                        </button>
                        <button class="btn btn-accent btn-sm" onclick="Admin.showImportModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Import Excel
                        </button>
                        <button class="btn btn-ghost btn-sm" onclick="Admin.exportUsersToExcel()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Ekspor Excel
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="Admin.showUserForm(null, '${type}')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah ${type === 'gurus' ? 'Guru' : 'User'}
                        </button>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="data-table-wrapper" id="usersTableWrapper">
                        <div class="skeleton" style="height:300px;border-radius:8px;"></div>
                    </div>
                </div>
            </div>
        `);

        $('#userSearch').on('input', App.debounce((e) => {
            this.usersSearch = e.target.value;
            this.usersPage = 1;
            this.loadUsersTable();
        }, 400));

        this.loadUsersTable();
    },

    updateKodeGuru(id, kode) {
        if (!kode.trim()) {
            EModal.toast({type: 'error', title: 'Gagal', message: 'Kode Guru tidak boleh kosong'});
            return;
        }
        App.api('api/users.php?action=update_kode_guru', {
            method: 'POST',
            data: { id: id, kode_guru: kode.trim().toUpperCase() }
        }).done(res => {
            if (res.success) {
                EModal.toast({type: 'success', title: 'Tersimpan', message: res.message});
            } else {
                EModal.toast({type: 'error', title: 'Gagal', message: res.message});
            }
        }).fail(xhr => {
            EModal.toast({type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menyimpan kode'});
        });
    },

    loadUsersTable() {
        const extraRole = this.usersType === 'gurus' ? '&role=guru' : '&exclude_guru=1';
        const params = `action=list&page=${this.usersPage}&per_page=50&search=${encodeURIComponent(this.usersSearch)}${extraRole}`;
        App.api(`api/users.php?${params}`).done(res => {
            if (!res.success) return;
            const d = res.data;
            if (!d.data.length) {
                $('#usersTableWrapper').html(`<div class="empty-state"><h3>Belum Ada ${this.usersType === 'gurus' ? 'Data Guru' : 'User'}</h3><p>Tambahkan ${this.usersType === 'gurus' ? 'guru' : 'user'} baru atau import dari Excel.</p></div>`);
                return;
            }
            let rows = d.data.map(u => {
                const safeName = App.escapeHtml(u.nama_lengkap);
                const photo = u.avatar ? `<img src="${App.baseUrl}${u.avatar}" class="user-avatar" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">` : `<div class="user-avatar" style="width:32px;height:32px;font-size:11px;border:none;cursor:default;">${App.getInitials(u.nama_lengkap)}</div>`;
                if (this.usersType === 'gurus') {
                    return `
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="user-checkbox" value="${u.id}" onchange="Admin.checkUserSelection()"></td>
                        <td><div class="d-flex gap-sm" style="align-items:center">
                            ${photo}
                            <div><strong style="font-size:13px">${safeName}</strong><br><span class="text-muted" style="font-size:11px">Username: ${App.escapeHtml(u.username || '-')}</span></div>
                        </div></td>
                        <td>
                            <input type="text" value="${App.escapeHtml(u.kode_guru || '')}" class="form-input" style="width: 70px; text-transform: uppercase; padding: 4px; height: 30px; font-weight: bold; font-size: 12px; text-align: center;" 
                                oninput="clearTimeout(this.timer); this.timer = setTimeout(() => Admin.updateKodeGuru(${u.id}, this.value), 600);" 
                                onkeydown="if(event.key === 'Enter') { clearTimeout(this.timer); Admin.updateKodeGuru(${u.id}, this.value); this.blur(); }"
                                title="Ketik untuk otomatis menyimpan">
                        </td>
                        <td><strong>${App.escapeHtml(u.nik || '-')}</strong><br><span class="text-muted" style="font-size:11px">${App.escapeHtml(u.email || '-')}</span></td>
                        <td style="font-size:12px">${App.escapeHtml(u.tempat_lahir || '-')}<br><span class="text-muted">${u.tgl_lahir ? App.formatDate(u.tgl_lahir) : '-'}</span></td>
                        <td>${App.escapeHtml(u.tupoksi || '-')}</td>
                        <td>${App.escapeHtml(u.jabatan || '-')}</td>
                        <td><span class="badge badge-info">${App.escapeHtml(u.status_guru || '-')}</span></td>
                        <td style="font-size:12px"><span class="badge ${u.tpg === 'Ya' ? 'badge-success' : 'badge-warning'}">TPG ${App.escapeHtml(u.tpg || 'Tidak')}</span><br><span class="text-muted">TMT ${u.tmt ? App.formatDate(u.tmt) : '-'}</span></td>
                        <td><span class="badge ${u.status==1?'badge-success':'badge-danger'}">${u.status==1?'Aktif':'Nonaktif'}</span></td>
                        <td><div class="actions">
                            <button class="btn-icon" title="Reset Password" onclick="Admin.showResetPasswordModal(${u.id}, '${safeName}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg></button>
                            <button class="btn-icon" title="Edit" onclick="Admin.showUserForm(${u.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="btn-icon danger" title="Hapus" onclick="Admin.deleteUser(${u.id},'${safeName}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div></td>
                    </tr>`;
                }
                return `
                <tr>
                    <td style="text-align: center;"><input type="checkbox" class="user-checkbox" value="${u.id}" onchange="Admin.checkUserSelection()"></td>
                    <td><div class="d-flex gap-sm" style="align-items:center">
                        ${u.avatar ? `<img src="${App.baseUrl}${u.avatar}" class="user-avatar" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">` : `<div class="user-avatar" style="width:32px;height:32px;font-size:11px;border:none;cursor:default;">${App.getInitials(u.nama_lengkap)}</div>`}
                        <div><strong style="font-size:13px">${safeName}</strong><br><span class="text-muted" style="font-size:11px">${u.jabatan ? App.escapeHtml(u.jabatan) : ''} @${App.escapeHtml(u.username)}</span></div>
                    </div></td>
                    <td><span class="badge ${u.role==='superadmin'?'badge-primary':'badge-warning'}">${u.role}</span></td>
                    <td><span class="badge ${u.status==1?'badge-success':'badge-danger'}">${u.status==1?'Aktif':'Nonaktif'}</span></td>
                    <td style="font-size:12px;color:var(--text-muted)">${u.last_login ? App.formatDate(u.last_login) : '-'}</td>
                    <td><div class="actions">
                        <button class="btn-icon" title="Reset Password" onclick="Admin.showResetPasswordModal(${u.id}, '${safeName}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg></button>
                        <button class="btn-icon" title="Edit" onclick="Admin.showUserForm(${u.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                        <button class="btn-icon danger" title="Hapus" onclick="Admin.deleteUser(${u.id},'${safeName}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    </div></td>
                </tr>`;
            }).join('');

            let pagination = '';
            if (d.total_pages > 1) {
                pagination = '<div class="pagination">';
                const current = d.page;
                const total = d.total_pages;

                // Tombol Previous
                pagination += `<button class="page-btn" ${current === 1 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : `onclick="Admin.usersPage=${current - 1};Admin.loadUsersTable()"`} title="Sebelumnya"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><polyline points="15 18 9 12 15 6"></polyline></svg></button>`;

                let startPage = Math.max(1, current - 1);
                let endPage = Math.min(total, current + 1);

                if (current === 1) {
                    endPage = Math.min(total, 3);
                } else if (current === total) {
                    startPage = Math.max(1, total - 2);
                }

                for (let i = startPage; i <= endPage; i++) {
                    pagination += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="Admin.usersPage=${i};Admin.loadUsersTable()">${i}</button>`;
                }

                // Tombol Next
                pagination += `<button class="page-btn" ${current === total ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : `onclick="Admin.usersPage=${current + 1};Admin.loadUsersTable()"`} title="Selanjutnya"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><polyline points="9 18 15 12 9 6"></polyline></svg></button>`;

                pagination += '</div>';
            }

            const headers = this.usersType === 'gurus'
                ? '<tr><th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllUsers" onclick="Admin.toggleSelectAllUsers(this)"></th><th>Nama</th><th>Kode</th><th>NIK / Email</th><th>TTL</th><th>Tupoksi</th><th>Jabatan</th><th>Status Guru</th><th>TPG / TMT</th><th>Status Akun</th><th>Aksi</th></tr>'
                : '<tr><th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllUsers" onclick="Admin.toggleSelectAllUsers(this)"></th><th>User</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr>';
            $('#usersTableWrapper').html(`
                <table class="data-table"><thead>${headers}</thead><tbody>${rows}</tbody></table>
                ${pagination}
            `);
        });
    },

    showResetPasswordModal(id, name) {
        const html = `
            <div class="form-group" style="text-align: left;">
                <label class="form-label" style="font-size: 1.1rem; margin-bottom: 15px;">Reset Password <strong>${App.escapeHtml(name)}</strong></label>
                <div style="margin-bottom: 15px; display: flex; gap: 15px;">
                    <label style="display:flex; align-items:center; cursor:pointer;"><input type="radio" name="reset_type" value="generate" checked onchange="$('#customPassWrap').hide()" style="margin-right: 8px;"> Generate Otomatis</label>
                    <label style="display:flex; align-items:center; cursor:pointer;"><input type="radio" name="reset_type" value="manual" onchange="$('#customPassWrap').show(); $('#resetCustomPass').focus();" style="margin-right: 8px;"> Buat Sendiri</label>
                </div>
                <div class="form-group" id="customPassWrap" style="display:none;">
                    <input type="text" id="resetCustomPass" class="form-input" placeholder="Ketik password baru...">
                    <small style="color: #666; display: block; margin-top: 5px;">Minimal 5 karakter</small>
                </div>
            </div>
        `;
        EModal.form({
            title: 'Reset Password',
            form: html,
            size: 'md',
            confirmText: 'Proses Reset',
            onConfirm: () => {
                const type = $('input[name="reset_type"]:checked').val();
                let customPass = $('#resetCustomPass').val();
                
                if (type === 'manual') {
                    if (!customPass) {
                        EModal.toast({type: 'warning', message: 'Password tidak boleh kosong'});
                        return false;
                    }
                    if (customPass.length < 5) {
                        EModal.toast({type: 'warning', message: 'Password minimal 5 karakter'});
                        return false;
                    }
                } else {
                    // Generate random 8 chars password
                    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                    customPass = Array.from({length: 8}, () => chars[Math.floor(Math.random() * chars.length)]).join('');
                }

                const loader = EModal.loading('Meriset password...');
                App.api('api/users.php?action=reset-password', {
                    method: 'POST',
                    data: { id: id, password: customPass }
                }).done(res => {
                    EModal.closeAll();
                    if (res.success) {
                        const resultHtml = `
                            <div style="text-align: center; margin-bottom: 15px;">
                                <p style="margin-bottom: 15px; color: #555;">Password baru untuk <strong>${App.escapeHtml(name)}</strong> berhasil diatur:</p>
                                <div style="display:flex; align-items:center; justify-content:center; gap:10px;">
                                    <input type="text" id="copyNewPass" value="${customPass}" readonly style="font-size: 1.2rem; padding: 10px 15px; background: #f3f4f6; border: 1px solid #ddd; border-radius: 8px; color: #111; text-align: center; width: 160px; font-family: monospace;">
                                    <button class="btn btn-primary" onclick="
                                        const input = document.getElementById('copyNewPass');
                                        input.select();
                                        document.execCommand('copy');
                                        EModal.toast({type:'success', message:'Password disalin ke clipboard!'});
                                    ">Salin</button>
                                </div>
                            </div>
                        `;
                        EModal.info({type: 'success', title: 'Berhasil', message: resultHtml, buttonText: 'Tutup'});
                    } else {
                        EModal.toast({type: 'error', message: res.message || 'Gagal reset password'});
                    }
                }).fail(xhr => {
                    EModal.close(loader);
                    EModal.toast({type: 'error', message: xhr.responseJSON?.message || 'Gagal reset password'});
                });
                return false;
            }
        });
    },

    showUserForm(id = null, type = 'users') {
        const isEdit = id !== null;
        let title = isEdit ? 'Edit User' : 'Tambah User Baru';
        if (type === 'gurus' || this.usersType === 'gurus') title = isEdit ? 'Edit Data Guru' : 'Tambah Data Guru';

        const modal = `
        <div class="admin-form-modal show" id="userFormModal" onclick="if(event.target===this)Admin.closeFormModal('userFormModal')">
            <div class="admin-form-panel" style="animation:scaleIn 0.3s cubic-bezier(0.34,1.56,0.64,1)">
                <div class="panel-header"><h3>${title}</h3>
                    <button class="panel-close" onclick="Admin.closeFormModal('userFormModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                </div>
                <div class="panel-body">
                    <input type="hidden" id="userId" value="${id||''}">
                    <div class="form-group"><label class="form-label">${this.usersType === 'gurus' ? 'Username (kosongkan = NIK)' : 'Username'}</label><input class="form-input" id="userUsername" placeholder="${this.usersType === 'gurus' ? 'Otomatis dari NIK' : 'Username'}" ${isEdit?'readonly style="opacity:0.6"':''}></div>
                    ${this.usersType !== 'gurus' ? `<div class="form-group"><label class="form-label">Password ${isEdit?'(kosongkan jika tidak diubah)':''}</label><input class="form-input" id="userPassword" type="password" placeholder="Password"></div>` : `<input type="hidden" id="userPassword" value="">`}
                    <div class="form-group"><label class="form-label">${this.usersType === 'gurus' ? 'Nama' : 'Nama Lengkap'}</label><input class="form-input" id="userNama" placeholder="${this.usersType === 'gurus' ? 'Nama Guru' : 'Nama Lengkap'}"></div>
                    ${this.usersType === 'gurus' ? `
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">NIK</label><input class="form-input" id="userNik" placeholder="NIK guru"></div>
                        <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" id="userEmail" placeholder="nama@email.com"></div>
                        <div class="form-group"><label class="form-label">No HP</label><input class="form-input" id="userNoHp" placeholder="08123..."></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Tempat Lahir</label><input class="form-input" id="userTempatLahir" placeholder="Tempat lahir"></div>
                        <div class="form-group"><label class="form-label">Tgl Lahir</label><input class="form-input" type="date" id="userTglLahir"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Tupoksi</label><select class="form-select" id="userTupoksi"><option value="">-- Pilih Tupoksi --</option></select></div>
                        <div class="form-group"><label class="form-label">Jabatan</label><select class="form-select" id="userJabatan"><option value="">-- Pilih Jabatan --</option></select></div>
                    </div>
                    <div class="form-group"><label class="form-label">Mata Pelajaran (Opsional)</label><input class="form-input" id="userMapel" placeholder="Contoh: Matematika"></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Status Guru</label><select class="form-select" id="userStatusGuru"><option value="">-- Pilih Status --</option></select></div>
                        <div class="form-group"><label class="form-label">TPG</label><select class="form-select" id="userTpg"><option value="Tidak">Tidak</option><option value="Ya">Ya</option></select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">TMT</label><input class="form-input" type="date" id="userTmt"></div>
                        <div class="form-group"><label class="form-label">Foto / Avatar (Opsional)</label><input class="form-input" type="file" id="userAvatar" accept="image/*" style="padding:10px"><div id="userAvatarPreview" class="mt-sm"></div></div>
                    </div>
                    ` : `<input type="hidden" id="userNik" value=""><input type="hidden" id="userEmail" value=""><input type="hidden" id="userNoHp" value=""><input type="hidden" id="userTempatLahir" value=""><input type="hidden" id="userTglLahir" value=""><input type="hidden" id="userTupoksi" value=""><input type="hidden" id="userJabatan" value=""><input type="hidden" id="userMapel" value=""><input type="hidden" id="userStatusGuru" value=""><input type="hidden" id="userTpg" value="Tidak"><input type="hidden" id="userTmt" value="">`}
                    ${this.usersType === 'gurus' ? `<input type="hidden" id="userRole" value="guru">` : `<div class="form-group"><label class="form-label">Role Akses</label><select class="form-select" id="userRole"><option value="user">User Standar</option><option value="superadmin">Superadmin</option></select></div>`}
                    ${isEdit?`<div class="form-group"><label class="form-label">Status Akun</label><select class="form-select" id="userStatus"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>`:''}
                </div>
                <div class="panel-footer">
                    <button class="btn btn-ghost" onclick="Admin.closeFormModal('userFormModal')">Batal</button>
                    <button class="btn btn-primary" id="saveUserBtn" onclick="Admin.saveUser()"><span class="btn-text">Simpan</span></button>
                </div>
            </div>
        </div>`;
        $('body').append(modal);

        if (this.usersType === 'gurus') {
            $.when(
                App.api('api/referensi.php?action=list&kategori=tupoksi'),
                App.api('api/referensi.php?action=list&kategori=jabatan'),
                App.api('api/referensi.php?action=list&kategori=status_guru')
            ).done((resTupoksi, resJab, resStatus) => {
                this.fillSelectOptions('#userTupoksi', resTupoksi[0]?.data || []);
                this.fillSelectOptions('#userJabatan', resJab[0]?.data || []);
                this.fillSelectOptions('#userStatusGuru', resStatus[0]?.data || []);
                if (isEdit) {
                    App.api(`api/users.php?action=get&id=${id}`).done(res => {
                        if (res.success) {
                            const u = res.data;
                            $('#userUsername').val(u.username);
                            $('#userNama').val(u.nama_lengkap);
                            $('#userNik').val(u.nik || '');
                            $('#userEmail').val(u.email || '');
                            $('#userNoHp').val(u.no_hp || '');
                            $('#userTempatLahir').val(u.tempat_lahir || '');
                            $('#userTglLahir').val(u.tgl_lahir || '');
                            this.setSelectValue('#userTupoksi', u.tupoksi || '');
                            this.setSelectValue('#userJabatan', u.jabatan || '');
                            $('#userMapel').val(u.mapel || '');
                            this.setSelectValue('#userStatusGuru', u.status_guru || '');
                            $('#userTpg').val(u.tpg || 'Tidak');
                            $('#userTmt').val(u.tmt || '');
                            $('#userStatus').val(u.status);
                            if (u.avatar) $('#userAvatarPreview').html(`<img src="${App.baseUrl}${u.avatar}" style="width:64px;height:64px;border-radius:8px;object-fit:cover;border:1px solid var(--border-color);" alt="Avatar">`);
                        }
                    });
                }
            });
        } else if (isEdit) {
            App.api(`api/users.php?action=get&id=${id}`).done(res => {
                if (res.success) {
                    const u = res.data;
                    $('#userUsername').val(u.username);
                    $('#userNama').val(u.nama_lengkap);
                    $('#userRole').val(u.role);
                    $('#userStatus').val(u.status);
                }
            });
        }
    },

    fillSelectOptions(selector, rows) {
        rows.forEach(r => $(selector).append($('<option>', { value: r.nama, text: r.nama })));
    },

    setSelectValue(selector, value) {
        const exists = $(selector).find('option').filter(function(){ return $(this).val() === value; }).length > 0;
        if (value && !exists) {
            $(selector).append($('<option>', { value, text: value }));
        }
        $(selector).val(value);
    },

    saveUser() {
        const id = $('#userId').val();
        const btn = document.getElementById('saveUserBtn');
        const data = new FormData();
        data.append('username', $('#userUsername').val().trim());
        data.append('password', $('#userPassword').val());
        data.append('nama_lengkap', $('#userNama').val().trim());
        data.append('nik', $('#userNik').val().trim());
        data.append('email', $('#userEmail').val().trim());
        data.append('no_hp', $('#userNoHp').val().trim());
        data.append('tempat_lahir', $('#userTempatLahir').val().trim());
        data.append('tgl_lahir', $('#userTglLahir').val());
        data.append('tupoksi', $('#userTupoksi').val() || '');
        data.append('jabatan', $('#userJabatan').val() || '');
        data.append('mapel', $('#userMapel').val().trim());
        data.append('status_guru', $('#userStatusGuru').val() || '');
        data.append('tpg', $('#userTpg').val() || 'Tidak');
        data.append('tmt', $('#userTmt').val());
        data.append('role', $('#userRole').val());

        const avatarFile = $('#userAvatar')[0]?.files[0];
        if (avatarFile) data.append('avatar', avatarFile);

        if (id) {
            data.append('id', id);
            data.append('status', $('#userStatus').val());
        }

        const role = data.get('role');
        let username = data.get('username');
        let password = data.get('password');
        let nama_lengkap = data.get('nama_lengkap');
        const nik = data.get('nik');

        if (!id && this.usersType === 'gurus') {
            if (!username && nik) { username = nik; data.set('username', username); }
            if (!password) { password = '1234567'; data.set('password', password); }
        }

        if (!nama_lengkap) { EModal.toast({type:'warning',title:'Perhatian',message:'Nama lengkap harus diisi.'}); return; }
        if (!id && (!username || (this.usersType !== 'gurus' && !password))) { EModal.toast({type:'warning',title:'Perhatian',message: this.usersType === 'gurus' ? 'NIK harus diisi agar username bisa digenerate.' : 'Username dan password harus diisi.'}); return; }

        EModal.btnLoading(btn, true);
        const action = id ? 'update' : 'create';
        
        App.api(`api/users.php?action=${action}`, { 
            method:'POST', 
            data: data,
            processData: false,
            contentType: false
        }).done(res => {
            if (res.success) {
                this.closeFormModal('userFormModal');
                EModal.info({type:'success',title:'Berhasil!',message:res.message});
                this.loadUsersTable();
            }
        }).fail(xhr => {
            EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Terjadi kesalahan.'});
        }).always(() => EModal.btnLoading(btn, false));
    },

    deleteUser(id, name) {
        EModal.confirm({
            title:'Hapus User', message:`Yakin ingin menghapus user <strong>${name}</strong>?`, type:'danger', confirmText:'Ya, Hapus',
            onConfirm: () => {
                const loader = EModal.loading('Menghapus...');
                App.api('api/users.php?action=delete',{method:'POST',data:{id}}).done(res => {
                    EModal.close(loader);
                    if (res.success) { EModal.info({type:'success',title:'Dihapus!',message:res.message}); this.loadUsersTable(); }
                }).fail(xhr => { EModal.close(loader); EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Error'}); });
            }
        });
    },

    deleteStudent(id, name) {
        EModal.confirm({
            title:'Hapus Siswa', message:`Yakin ingin menghapus siswa <strong>${name}</strong>? Data lain yang terkait siswa ini mungkin akan ikut terhapus.`, type:'danger', confirmText:'Ya, Hapus',
            onConfirm: () => {
                const loader = EModal.loading('Menghapus...');
                App.api('api/students.php?action=delete',{method:'POST',data:{id}}).done(res => {
                    EModal.close(loader);
                    if (res.success) { EModal.info({type:'success',title:'Dihapus!',message:res.message}); this.loadStudentsTable(); }
                }).fail(xhr => { EModal.close(loader); EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Error'}); });
            }
        });
    },

    toggleSelectAllStudents(el) {
        $('.student-checkbox').prop('checked', el.checked);
        this.checkStudentSelection();
    },

    checkStudentSelection() {
        const checkedCount = $('.student-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkDeleteStudentsBtn').show();
        } else {
            $('#bulkDeleteStudentsBtn').hide();
            $('#selectAllStudents').prop('checked', false);
        }
    },

    bulkDeleteStudents() {
        const ids = $('.student-checkbox:checked').map(function() { return $(this).val(); }).get();
        if (ids.length === 0) return;
        
        EModal.confirm({
            title: `Hapus ${ids.length} Siswa?`, 
            message: `Yakin ingin menghapus <strong>${ids.length}</strong> data siswa yang dipilih? Data terkait siswa (nilai, dll) mungkin akan terpengaruh.`, 
            type: 'danger', 
            confirmText: 'Ya, Hapus Terpilih',
            onConfirm: () => {
                const loader = EModal.loading('Menghapus data...');
                App.api('api/students.php?action=delete-bulk', {method: 'POST', data: {ids}}).done(res => {
                    EModal.close(loader);
                    if (res.success) { 
                        EModal.info({type: 'success', title: 'Dihapus!', message: res.message}); 
                        $('#bulkDeleteStudentsBtn').hide();
                        this.loadStudentsTable(); 
                    }
                }).fail(xhr => { 
                    EModal.close(loader); 
                    EModal.toast({type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error'}); 
                });
            }
        });
    },

    toggleSelectAllUsers(el) {
        $('.user-checkbox').prop('checked', el.checked);
        this.checkUserSelection();
    },

    checkUserSelection() {
        const checkedCount = $('.user-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkDeleteUsersBtn').show();
        } else {
            $('#bulkDeleteUsersBtn').hide();
            $('#selectAllUsers').prop('checked', false);
        }
    },

    bulkDeleteUsers(type) {
        const ids = $('.user-checkbox:checked').map(function() { return $(this).val(); }).get();
        if (ids.length === 0) return;
        
        const typeLabel = type === 'gurus' ? 'Guru' : 'User';
        
        EModal.confirm({
            title: `Hapus ${ids.length} ${typeLabel}?`, 
            message: `Yakin ingin menghapus <strong>${ids.length}</strong> data ${typeLabel} yang dipilih? Data tidak bisa dikembalikan.`, 
            type: 'danger', 
            confirmText: 'Ya, Hapus Terpilih',
            onConfirm: () => {
                const loader = EModal.loading('Menghapus data...');
                App.api('api/users.php?action=delete-bulk', {method: 'POST', data: {ids}}).done(res => {
                    EModal.close(loader);
                    if (res.success) { 
                        EModal.info({type: 'success', title: 'Dihapus!', message: res.message}); 
                        $('#bulkDeleteUsersBtn').hide();
                        this.loadUsersTable(); 
                    }
                }).fail(xhr => { 
                    EModal.close(loader); 
                    EModal.toast({type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error'}); 
                });
            }
        });
    },

    showImportModal() {
        const isGuruImport = this.usersType === 'gurus';
        const title = isGuruImport ? 'Import Data Guru dari Excel' : 'Import User dari Excel';
        const formatInfo = isGuruImport
            ? 'Format kolom Excel: <strong>Username (Opsional) | NIK | Email | Nama | Tempat Lahir | Tgl Lahir | Tupoksi | Jabatan | Mata Pelajaran | Status | TPG | TMT | Password</strong>. Jika username kosong akan otomatis memakai NIK. Jika password kosong default <strong>1234567</strong>.'
            : 'Format kolom Excel: <strong>Username | Password | Nama Lengkap | Role</strong>';
        const modal = `
        <div class="admin-form-modal show" id="importModal" onclick="if(event.target===this)Admin.closeFormModal('importModal')">
            <div class="admin-form-panel">
                <div class="panel-header"><h3>${title}</h3>
                    <button class="panel-close" onclick="Admin.closeFormModal('importModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                </div>
                <div class="panel-body">
                    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">${formatInfo}</p>
                    <button type="button" class="btn btn-ghost btn-sm" style="margin-bottom:12px;" onclick="Admin.downloadUserImportTemplate()">Download Template Excel</button>
                    <div class="drop-zone" id="excelDropZone" onclick="document.getElementById('excelFile').click()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <p>Klik atau drag file Excel di sini</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">.xlsx, .xls</p>
                    </div>
                    <input type="file" id="excelFile" accept=".xlsx,.xls" style="display:none" onchange="Admin.handleExcelFile(this)">
                    <div id="importPreview" class="mt-md"></div>
                </div>
                <div class="panel-footer">
                    <button class="btn btn-ghost" onclick="Admin.closeFormModal('importModal')">Batal</button>
                    <button class="btn btn-primary hidden" id="doImportBtn" onclick="Admin.doImport()"><span class="btn-text">Import Sekarang</span></button>
                </div>
            </div>
        </div>`;
        $('body').append(modal);

        // Drag and drop
        const dz = document.getElementById('excelDropZone');
        dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragover'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
        dz.addEventListener('drop', e => { e.preventDefault(); dz.classList.remove('dragover'); if(e.dataTransfer.files.length) Admin.handleExcelFile({files:e.dataTransfer.files}); });
    },

    importData: [],

    handleExcelFile(input) {
        const file = input.files[0];
        if (!file) return;

        this.loadSheetJS(() => this.parseExcel(file), () => {
            EModal.toast({type:'error',title:'Gagal',message:'Gagal memuat modul Excel. Periksa koneksi internet.'});
        });
    },

    parseExcel(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const wb = XLSX.read(e.target.result, {type:'binary'});
                const ws = wb.Sheets[wb.SheetNames[0]];
                const headers = this.usersType === 'gurus'
                    ? ['username','nik','email','nama_lengkap','tempat_lahir','tgl_lahir','tupoksi','jabatan','mapel','status_guru','tpg','tmt','password']
                    : ['username','password','nama_lengkap','role'];
                const data = XLSX.utils.sheet_to_json(ws, {header:headers, raw:false, defval:''});
                data.shift(); // Remove header row
                if (this.usersType === 'gurus') data.forEach(row => { row.role = 'guru'; });

                this.importData = data;
                $('#importPreview').html(`<p class="text-success" style="font-size:13px;font-weight:600;">${data.length} data ${this.usersType === 'gurus' ? 'guru' : 'user'} ditemukan</p>`);
                $('#doImportBtn').removeClass('hidden');
            } catch (err) {
                EModal.toast({type:'error',title:'Gagal',message:'Gagal membaca file Excel.'});
            }
        };
        reader.readAsBinaryString(file);
    },

    doImport() {
        if (!this.importData.length) return;
        const btn = document.getElementById('doImportBtn');
        EModal.btnLoading(btn, true);

        App.api('api/import.php?action=users',{method:'POST',data:{users:this.importData, type:this.usersType}}).done(res => {
            this.closeFormModal('importModal');
            const msg = res.data?.errors?.length ? res.message + '\n\nError:\n' + res.data.errors.slice(0,5).join('\n') : res.message;
            EModal.info({type: res.data?.failed_count > 0 ? 'warning' : 'success', title:'Import Selesai', message:msg});
            this.loadUsersTable();
        }).fail(xhr => {
            EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Error'});
        }).always(() => EModal.btnLoading(btn, false));
    },

    loadSheetJS(onReady, onFail = null) {
        if (window.XLSX) {
            onReady();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
        script.onload = () => onReady();
        script.onerror = () => {
            if (typeof onFail === 'function') onFail();
        };
        document.head.appendChild(script);
    },

    downloadUserImportTemplate() {
        const isGuruImport = this.usersType === 'gurus';
        this.loadSheetJS(() => {
            const headers = isGuruImport
                ? ['Username (Opsional)', 'NIK', 'Email', 'Nama', 'Tempat Lahir', 'Tgl Lahir', 'Tupoksi', 'Jabatan', 'Status', 'TPG', 'TMT', 'Password']
                : ['Username', 'Password', 'Nama Lengkap', 'Role'];
            const sample = isGuruImport
                ? ['', '7201010203040001', 'budi.santoso@email.com', 'Budi Santoso', 'Surabaya', '1986-12-10', 'Guru Kelas', 'Wali Kelas', 'PNS', 'Ya', '2010-01-01', '']
                : ['operator01', 'password123', 'Operator Sekolah', 'user'];
            const ws = XLSX.utils.aoa_to_sheet([headers, sample]);
            ws['!cols'] = headers.map(() => ({ wch: 20 }));
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Template');
            XLSX.writeFile(wb, isGuruImport ? 'Template-Import-Guru.xlsx' : 'Template-Import-User.xlsx');
        }, () => {
            EModal.toast({type:'error', title:'Gagal', message:'Tidak bisa memuat modul Excel.'});
        });
    },

    closeFormModal(id) {
        $(`#${id}`).remove();
    },

    // ==================== STUDENTS SECTION ====================
    studentsPage: 1,
    studentsSearch: '',
    studentsAcademicYearId: '',
    studentImportData: [],

    renderStudents(embedded = false) {
        const active = App.state.academicYear;
        const target = embedded ? '#userTabContent' : '#adminContent';
        $(target).html(`
            <div class="admin-card students-card">
                <div class="admin-card-header">
                    <div>
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10.5 12 5 2 10.5 12 16l10-5.5Z"/><path d="M6 13v4c2 1.5 10 1.5 12 0v-4"/></svg> Data Siswa</h3>
                        <p class="admin-subtitle">Tahun aktif: <strong>${active?.tahun_ajaran ? App.escapeHtml(active.tahun_ajaran) + ' Semester ' + App.escapeHtml(active.semester || '-') : 'Belum diatur'}</strong></p>
                    </div>
                    <div class="admin-toolbar students-toolbar-inline">
                        <select class="form-select student-year-select" id="studentYearFilter"><option value="">Tahun aktif</option></select>
                        <div class="search-box">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" placeholder="Cari NIS, NISN, nama, email, tempat lahir, kelas..." id="studentSearch" value="${this.studentsSearch}">
                        </div>
                        <button class="btn btn-danger btn-sm" id="bulkDeleteStudentsBtn" style="display:none;" onclick="Admin.bulkDeleteStudents()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Hapus Terpilih
                        </button>
                        <button class="btn btn-accent btn-sm" onclick="Admin.showStudentImportExcel()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Import Excel
                        </button>
                        <button class="btn btn-ghost btn-sm" onclick="Admin.exportStudentsToExcel()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Ekspor Excel
                        </button>
                        <button class="btn btn-warning btn-sm" onclick="Admin.showSetGuruWaliModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Set Guru Wali
                        </button>
                        <button class="btn btn-success btn-sm" onclick="Admin.showPromoteClassModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg>
                            Naik Kelas
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="Admin.showStudentForm()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah Siswa
                        </button>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="data-table-wrapper" id="studentsTableWrapper">
                        <div class="skeleton" style="height:300px;border-radius:8px;"></div>
                    </div>
                </div>
            </div>
        `);

        this.loadAcademicYearOptions('#studentYearFilter', this.studentsAcademicYearId);
        $('#studentYearFilter').on('change', e => {
            this.studentsAcademicYearId = e.target.value;
            this.studentsPage = 1;
            this.loadStudentsTable();
        });
        $('#studentSearch').on('input', App.debounce((e) => {
            this.studentsSearch = e.target.value;
            this.studentsPage = 1;
            this.loadStudentsTable();
        }, 400));

        this.loadStudentsTable();
    },

    loadStudentsTable() {
        const year = this.studentsAcademicYearId ? `&academic_year_id=${this.studentsAcademicYearId}` : '';
        const params = `action=list&page=${this.studentsPage}&per_page=50&search=${encodeURIComponent(this.studentsSearch)}${year}`;
        App.api(`api/students.php?${params}`).done(res => {
            if (!res.success) return;
            const d = res.data;
            if (!d.data.length) {
                $('#studentsTableWrapper').html(`
                    <div class="student-empty">
                        <div class="student-empty-illustration">
                            <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="18" y="28" width="84" height="68" rx="18" fill="#EFF6FF"/>
                                <path d="M24 48l36-20 36 20-36 20-36-20Z" fill="#2563EB"/>
                                <path d="M39 63v15c9 7 33 7 42 0V63" stroke="#1D4ED8" stroke-width="6" stroke-linecap="round"/>
                                <circle cx="60" cy="79" r="13" fill="#FBBF24"/>
                                <path d="M52 95c3-8 13-8 16 0" stroke="#111827" stroke-width="5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3>Belum Ada Data Siswa</h3>
                        <p>Tambahkan siswa satu per satu atau import Excel untuk tahun ajaran aktif. Data ini akan menjadi sumber utama E-Graduation.</p>
                        <div class="student-empty-actions">
                            <button class="btn btn-primary" onclick="Admin.showStudentForm()">Tambah Siswa</button>
                            <button class="btn btn-ghost" onclick="Admin.showStudentImportExcel()">Import Excel</button>
                            <button class="btn btn-ghost" onclick="Admin.exportStudentsToExcel()">Ekspor Excel</button>
                        </div>
                    </div>
                `);
                return;
            }

            const rows = d.data.map(s => {
                const photo = s.foto_path
                    ? `<img src="${App.baseUrl}${s.foto_path}" alt="${App.escapeHtml(s.nama)}" class="student-photo-sm">`
                    : `<div class="user-avatar student-photo-sm">${App.getInitials(s.nama)}</div>`;
                return `
                <tr>
                    <td style="text-align: center;"><input type="checkbox" class="student-checkbox" value="${s.id}" onchange="Admin.checkStudentSelection()"></td>
                    <td>${s.no_urut || '-'}</td>
                    <td><div class="d-flex gap-sm" style="align-items:center">
                        ${photo}
                        <div><strong style="font-size:13px">${App.escapeHtml(s.nama)}</strong><br><span class="text-muted" style="font-size:11px">${App.escapeHtml(s.nisn || '-')}</span></div>
                    </div></td>
                    <td><strong>${App.escapeHtml(s.nis || '-')}</strong></td>
                    <td>${App.escapeHtml(s.email || '-')}</td>
                    <td><span class="badge ${s.jenis_kelamin === 'L' ? 'badge-primary' : 'badge-warning'}">${s.jenis_kelamin}</span></td>
                    <td>${App.escapeHtml(s.tempat_lahir || '-')}<br><span class="text-muted">${App.escapeHtml(s.tanggal_lahir || '-')}</span></td>
                    <td><strong>${App.escapeHtml(s.kelas || '-')}</strong></td>
                    <td style="font-size:12px;color:var(--text-muted)">${App.escapeHtml(s.tahun_ajaran || '-')} / ${App.escapeHtml(s.semester || '-')}</td>
                    <td><div class="actions">
                        <button class="btn-icon" title="Edit" onclick="Admin.showStudentForm(${s.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                        <button class="btn-icon danger" title="Hapus" onclick="Admin.deleteStudent(${s.id},'${App.escapeHtml(s.nama)}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    </div></td>
                </tr>`;
            }).join('');

            let pagination = '';
            if (d.total_pages > 1) {
                pagination = '<div class="pagination">';
                const current = d.page;
                const total = d.total_pages;

                // Tombol Previous
                pagination += `<button class="page-btn" ${current === 1 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : `onclick="Admin.studentsPage=${current - 1};Admin.loadStudentsTable()"`} title="Sebelumnya"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><polyline points="15 18 9 12 15 6"></polyline></svg></button>`;

                let startPage = Math.max(1, current - 1);
                let endPage = Math.min(total, current + 1);

                if (current === 1) {
                    endPage = Math.min(total, 3);
                } else if (current === total) {
                    startPage = Math.max(1, total - 2);
                }

                for (let i = startPage; i <= endPage; i++) {
                    pagination += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="Admin.studentsPage=${i};Admin.loadStudentsTable()">${i}</button>`;
                }

                // Tombol Next
                pagination += `<button class="page-btn" ${current === total ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : `onclick="Admin.studentsPage=${current + 1};Admin.loadStudentsTable()"`} title="Selanjutnya"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><polyline points="9 18 15 12 9 6"></polyline></svg></button>`;

                pagination += '</div>';
            }

            $('#studentsTableWrapper').html(`
                <table class="data-table"><thead><tr><th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllStudents" onclick="Admin.toggleSelectAllStudents(this)"></th><th>No.</th><th>Siswa</th><th>NIS</th><th>Email</th><th>L/P</th><th>Tempat, Tgl Lahir</th><th>Kelas</th><th>Tahun Ajaran</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>
                ${pagination}
            `);
        }).fail(xhr => {
            $('#studentsTableWrapper').html(`<div class="empty-state"><h3>Gagal Memuat</h3><p>${xhr.responseJSON?.message || 'Pastikan migrasi database sudah dijalankan.'}</p></div>`);
        });
    },

    showStudentForm(id = null) {
        const isEdit = id !== null;
        const modal = `
        <div class="admin-form-modal show" id="studentFormModal" onclick="if(event.target===this)Admin.closeFormModal('studentFormModal')">
            <div class="admin-form-panel">
                <div class="panel-header"><h3>${isEdit ? 'Edit' : 'Tambah'} Data Siswa</h3>
                    <button class="panel-close" onclick="Admin.closeFormModal('studentFormModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                </div>
                <div class="panel-body">
                    <input type="hidden" id="studentId" value="${id || ''}">
                    <div class="form-group"><label class="form-label">Tahun Ajaran</label><select class="form-select" id="studentAcademicYear"></select></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">No.</label><input type="number" class="form-input" id="studentNo" min="0"></div>
                        <div class="form-group"><label class="form-label">Kelas</label><input class="form-input" id="studentKelas" placeholder="XII IPA 1"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">NIS</label><input class="form-input" id="studentNis" placeholder="NIS"></div>
                        <div class="form-group"><label class="form-label">NISN</label><input class="form-input" id="studentNisn" placeholder="NISN"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Email</label><input class="form-input" id="studentEmail" type="email" placeholder="nama@email.com"></div>
                        <div class="form-group"><label class="form-label">Nama</label><input class="form-input" id="studentNama" placeholder="Nama lengkap siswa"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">No HP Orang Tua</label><input class="form-input" id="studentNoHpOrtu" placeholder="0812..."></div>
                        <div class="form-group"><label class="form-label">No HP Siswa</label><input class="form-input" id="studentNoHpSiswa" placeholder="0857..."></div>
                    </div>
                    <div class="form-group"><label class="form-label">Guru Wali</label><input class="form-input" id="studentGuruWali" placeholder="Nama Wali Kelas"></div>
                    <div class="form-group"><label class="form-label">Tempat Lahir</label><input class="form-input" id="studentBirthPlace" placeholder="Tempat lahir"></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">L/P</label><select class="form-select" id="studentGender"><option value="L">L</option><option value="P">P</option></select></div>
                        <div class="form-group"><label class="form-label">Tanggal Lahir</label><input type="date" class="form-input" id="studentBirthDate"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Foto Siswa</label><input type="file" class="form-input" id="studentPhoto" accept="image/*" style="padding:10px"><div id="studentPhotoPreview" class="mt-sm"></div></div>
                </div>
                <div class="panel-footer">
                    <button class="btn btn-ghost" onclick="Admin.closeFormModal('studentFormModal')">Batal</button>
                    <button class="btn btn-primary" id="saveStudentBtn" onclick="Admin.saveStudent()"><span class="btn-text">Simpan</span></button>
                </div>
            </div>
        </div>`;
        $('body').append(modal);
        this.loadAcademicYearOptions('#studentAcademicYear', this.studentsAcademicYearId || (App.state.academicYear?.id || ''));

        if (isEdit) {
            App.api(`api/students.php?action=get&id=${id}`).done(res => {
                if (res.success) {
                    const s = res.data;
                    this.loadAcademicYearOptions('#studentAcademicYear', s.academic_year_id);
                    $('#studentNo').val(s.no_urut);
                    $('#studentNis').val(s.nis);
                    $('#studentNisn').val(s.nisn);
                    $('#studentEmail').val(s.email || '');
                    $('#studentNama').val(s.nama);
                    $('#studentNoHpOrtu').val(s.no_hp_ortu || '');
                    $('#studentNoHpSiswa').val(s.no_hp_siswa || '');
                    $('#studentGuruWali').val(s.guru_wali || '');
                    $('#studentBirthPlace').val(s.tempat_lahir || '');
                    $('#studentGender').val(s.jenis_kelamin);
                    $('#studentBirthDate').val(s.tanggal_lahir);
                    $('#studentKelas').val(s.kelas);
                    if (s.foto_path) $('#studentPhotoPreview').html(`<img src="${App.baseUrl}${s.foto_path}" class="student-photo-preview" alt="Foto siswa">`);
                }
            });
        }
    },

    saveStudent() {
        const id = $('#studentId').val();
        const btn = document.getElementById('saveStudentBtn');
        const fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('academic_year_id', $('#studentAcademicYear').val());
        fd.append('no_urut', $('#studentNo').val());
        fd.append('nis', $('#studentNis').val().trim());
        fd.append('nisn', $('#studentNisn').val().trim());
        fd.append('email', $('#studentEmail').val().trim());
        fd.append('nama', $('#studentNama').val().trim());
        fd.append('no_hp_ortu', $('#studentNoHpOrtu').val().trim());
        fd.append('no_hp_siswa', $('#studentNoHpSiswa').val().trim());
        fd.append('guru_wali', $('#studentGuruWali').val().trim());
        fd.append('tempat_lahir', $('#studentBirthPlace').val().trim());
        fd.append('jenis_kelamin', $('#studentGender').val());
        fd.append('tanggal_lahir', $('#studentBirthDate').val());
        fd.append('kelas', $('#studentKelas').val().trim());
        const photo = $('#studentPhoto')[0].files[0];
        if (photo) fd.append('foto', photo);

        if (!$('#studentNis').val().trim() || !$('#studentNama').val().trim() || !$('#studentBirthDate').val() || !$('#studentKelas').val().trim()) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'NIS, nama, tanggal lahir, dan kelas wajib diisi.' });
            return;
        }

        EModal.btnLoading(btn, true);
        App.api(`api/students.php?action=${id ? 'update' : 'create'}`, { method: 'POST', data: fd }).done(res => {
            if (res.success) {
                this.closeFormModal('studentFormModal');
                EModal.info({ type: 'success', title: 'Berhasil!', message: res.message });
                this.loadStudentsTable();
            }
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Terjadi kesalahan.' });
        }).always(() => EModal.btnLoading(btn, false));
    },

    deleteStudent(id, name) {
        EModal.confirm({
            title: 'Hapus Data Siswa',
            message: `Yakin ingin menghapus data <strong>${name}</strong>?`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                const loader = EModal.loading('Menghapus...');
                App.api('api/students.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    EModal.close(loader);
                    if (res.success) {
                        EModal.info({ type: 'success', title: 'Dihapus!', message: res.message });
                        this.loadStudentsTable();
                    }
                }).fail(xhr => {
                    EModal.close(loader);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error' });
                });
            }
        });
    },

    showSetGuruWaliModal() {
        const modal = `
        <div class="admin-form-modal show" id="setGuruWaliModal" onclick="if(event.target===this)Admin.closeFormModal('setGuruWaliModal')">
            <div class="admin-form-panel" style="max-width: 600px;">
                <div class="panel-header"><h3>Set Guru Wali Massal</h3>
                    <button class="panel-close" onclick="Admin.closeFormModal('setGuruWaliModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                </div>
                <div class="panel-body">
                    <div class="form-group" style="position:relative; z-index:9999;">
                        <label class="form-label">Nama Guru Wali</label>
                        <div class="sp-cs-wrapper" style="position:relative; width:100%;">
                            <input type="hidden" id="guruWaliInput" value="">
                            <div class="sp-cs-display" id="csGuruWaliDisplay" tabindex="0" style="padding:10px 14px; border:1px solid var(--border-color, #cbd5e1); border-radius:8px; background:var(--bg-panel, #fff); cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                                <span class="cs-placeholder text-muted" id="csGuruWaliText" style="font-size: 14px;">Pilih Guru Wali...</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </div>
                            <div class="sp-cs-dropdown" id="csGuruWaliDropdown" style="display:none; position:absolute; top:calc(100% + 4px); left:0; width:100%; background:var(--bg-panel, #fff); border:1px solid var(--border-color, #cbd5e1); border-radius:8px; z-index:99999; box-shadow:0 10px 25px rgba(0,0,0,0.15);">
                                <div class="cs-search-box" style="padding:10px; border-bottom:1px solid var(--border-color, #f1f5f9); background:var(--bg-body, #f8fafc); border-radius:8px 8px 0 0;">
                                    <input type="text" id="csGuruWaliSearch" class="form-input" placeholder="Ketik nama guru..." autocomplete="off" style="width:100%; padding:8px 12px; font-size:13px; border:1px solid var(--border-color, #cbd5e1); border-radius:6px; outline:none;">
                                </div>
                                <div class="cs-options-list" id="csGuruWaliOptions" style="max-height:220px; overflow-y:auto; padding:0;">
                                    <div style="padding: 10px; text-align: center;" class="text-muted">Memuat...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="position:relative; z-index:1;">
                        <label class="form-label">Pilih Kelas</label>
                        <select class="form-select" id="guruWaliKelas" onchange="Admin.loadSetGuruWaliStudents(this.value)">
                            <option value="">-- Pilih Kelas --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pilih Siswa</label>
                        <div class="data-table-wrapper" style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px;">
                            <table class="data-table" style="margin: 0;">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllWaliStudents" onclick="Admin.toggleSelectAllWaliStudents(this)"></th>
                                        <th>Nama Siswa</th>
                                        <th>Guru Wali Saat Ini</th>
                                    </tr>
                                </thead>
                                <tbody id="waliStudentsList">
                                    <tr><td colspan="3" style="text-align:center; padding: 20px;" class="text-muted">Silakan pilih kelas terlebih dahulu</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <button class="btn btn-ghost" onclick="Admin.closeFormModal('setGuruWaliModal')">Batal</button>
                    <button class="btn btn-primary" id="saveGuruWaliBtn" onclick="Admin.saveGuruWaliBulk()"><span class="btn-text">Simpan Guru Wali</span></button>
                </div>
            </div>
        </div>`;
        $('body').append(modal);

        // Load Gurus for custom dropdown
        App.api('api/users.php?action=list&role=guru&per_page=1000').done(res => {
            if (res.success && res.data && res.data.data) {
                const list = res.data.data.map(g => `<div class="sp-cs-option" data-value="${App.escapeHtml(g.nama_lengkap)}" style="padding: 10px 14px; cursor: pointer; font-size: 14px; border-bottom:1px solid var(--border-color, #f1f5f9);" onmouseover="this.style.background='var(--bg-hover, #f8fafc)'" onmouseout="this.style.background='transparent'">${App.escapeHtml(g.nama_lengkap)}</div>`).join('');
                $('#csGuruWaliOptions').html(list);
            }
        });

        // Dropdown Events
        $('#csGuruWaliDisplay').on('click', function() {
            $('#csGuruWaliDropdown').toggle();
            if ($('#csGuruWaliDropdown').is(':visible')) {
                $('#csGuruWaliSearch').val('').trigger('input').focus();
            }
        });

        $('#csGuruWaliSearch').on('input', function() {
            const val = $(this).val().toLowerCase();
            $('#csGuruWaliOptions .sp-cs-option').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(val));
            });
        });

        $('#csGuruWaliOptions').on('click', '.sp-cs-option', function() {
            const val = $(this).data('value');
            const text = $(this).text();
            $('#guruWaliInput').val(val);
            $('#csGuruWaliText').text(text).removeClass('text-muted');
            $('#csGuruWaliDropdown').hide();
        });

        $(document).on('click.csGuruWali', function(e) {
            if (!$(e.target).closest('.sp-cs-wrapper').length) {
                $('#csGuruWaliDropdown').hide();
            }
        });

        // Cleanup on close
        const originalClose = Admin.closeFormModal;
        Admin.closeFormModal = function(id) {
            if (id === 'setGuruWaliModal') {
                $(document).off('click.csGuruWali');
                Admin.closeFormModal = originalClose;
            }
            originalClose.call(Admin, id);
        };

        // Load Classes
        const activeYear = this.studentsAcademicYearId || (App.state.academicYear?.id || '');
        App.api(`api/students.php?action=get_classes&academic_year_id=${activeYear}`).done(res => {
            if (res.success && res.data) {
                const options = res.data.map(c => `<option value="${App.escapeHtml(c)}">${App.escapeHtml(c)}</option>`).join('');
                $('#guruWaliKelas').append(options);
            }
        });
    },

    loadSetGuruWaliStudents(kelas) {
        if (!kelas) {
            $('#waliStudentsList').html('<tr><td colspan="3" style="text-align:center; padding: 20px;" class="text-muted">Silakan pilih kelas terlebih dahulu</td></tr>');
            return;
        }
        $('#waliStudentsList').html('<tr><td colspan="3" style="text-align:center; padding: 20px;">Memuat data siswa...</td></tr>');
        
        const activeYear = this.studentsAcademicYearId || (App.state.academicYear?.id || '');
        App.api(`api/students.php?action=list&kelas=${encodeURIComponent(kelas)}&academic_year_id=${activeYear}&per_page=1000`).done(res => {
            if (res.success && res.data && res.data.data) {
                const students = res.data.data;
                if (students.length === 0) {
                    $('#waliStudentsList').html('<tr><td colspan="3" style="text-align:center; padding: 20px;" class="text-muted">Tidak ada siswa di kelas ini</td></tr>');
                    return;
                }
                const rows = students.map(s => `
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="wali-student-checkbox" value="${s.id}"></td>
                        <td><strong>${App.escapeHtml(s.nama)}</strong><br><span class="text-muted" style="font-size:11px">${s.nis}</span></td>
                        <td>${s.guru_wali ? App.escapeHtml(s.guru_wali) : '<span class="text-muted">-</span>'}</td>
                    </tr>
                `).join('');
                $('#waliStudentsList').html(rows);
                $('#selectAllWaliStudents').prop('checked', false);
            }
        });
    },

    toggleSelectAllWaliStudents(checkbox) {
        $('.wali-student-checkbox').prop('checked', checkbox.checked);
    },

    saveGuruWaliBulk() {
        const guruWali = $('#guruWaliInput').val().trim();
        if (!guruWali) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Nama Guru Wali harus diisi.' });
            return;
        }

        const selectedIds = [];
        $('.wali-student-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Pilih setidaknya satu siswa.' });
            return;
        }

        const btn = document.getElementById('saveGuruWaliBtn');
        EModal.btnLoading(btn, true);

        App.api('api/students.php?action=set_guru_wali_bulk', {
            method: 'POST',
            data: {
                guru_wali: guruWali,
                ids: selectedIds
            }
        }).done(res => {
            if (res.success) {
                this.closeFormModal('setGuruWaliModal');
                EModal.info({ type: 'success', title: 'Berhasil!', message: res.message });
                this.loadStudentsTable();
            }
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Terjadi kesalahan.' });
        }).always(() => EModal.btnLoading(btn, false));
    },
    showPromoteClassModal() {
        const activeYear = App.state.academicYear;
        if (!activeYear || !activeYear.id) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Tidak ada Tahun Ajaran aktif. Silakan aktifkan terlebih dahulu di menu Tahun Ajaran.' });
            return;
        }

        const modal = `
        <div class="admin-form-modal show" id="promoteClassModal" onclick="if(event.target===this)Admin.closeFormModal('promoteClassModal')">
            <div class="admin-form-panel" style="max-width: 650px;">
                <div class="panel-header"><h3>Proses Naik Kelas</h3>
                    <button class="panel-close" onclick="Admin.closeFormModal('promoteClassModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                </div>
                <div class="panel-body">
                    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">Pilih siswa dari kelas di tahun ajaran sebelumnya, dan pindahkan (naikkan) ke kelas baru pada Tahun Ajaran yang aktif saat ini.</p>
                    
                    <div style="background:var(--bg-body, #f8fafc); border:1px solid var(--border-color, #e2e8f0); border-radius:8px; padding:16px; margin-bottom:16px;">
                        <h4 style="margin:0 0 12px 0; font-size:14px; font-weight:600;">Sumber Data (Kelas Lama)</h4>
                        <div class="form-row">
                            <div class="form-group"><label class="form-label">Tahun Ajaran Sumber</label><select class="form-select" id="promoteSourceYear" onchange="Admin.loadPromoteSourceClasses(this.value)"><option value="">Memuat...</option></select></div>
                            <div class="form-group"><label class="form-label">Kelas Sumber</label><select class="form-select" id="promoteSourceClass" onchange="Admin.loadPromoteStudents()"><option value="">-- Pilih Tahun Ajaran Dulu --</option></select></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pilih Siswa yang Naik Kelas</label>
                        <div class="data-table-wrapper" style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px;">
                            <table class="data-table" style="margin: 0;">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllPromoteStudents" onclick="Admin.toggleSelectAllPromoteStudents(this)"></th>
                                        <th>Nama Siswa</th>
                                        <th>NIS</th>
                                    </tr>
                                </thead>
                                <tbody id="promoteStudentsList">
                                    <tr><td colspan="3" style="text-align:center; padding: 20px;" class="text-muted">Silakan pilih kelas sumber terlebih dahulu</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div style="background:var(--bg-body, #f8fafc); border:1px solid var(--border-color, #e2e8f0); border-radius:8px; padding:16px; margin-top:16px;">
                        <h4 style="margin:0 0 12px 0; font-size:14px; font-weight:600;">Tujuan (Kelas Baru)</h4>
                        <div class="form-row">
                            <div class="form-group"><label class="form-label">Tahun Ajaran Tujuan</label><input type="text" class="form-input" value="${App.escapeHtml(activeYear.tahun_ajaran + ' Semester ' + activeYear.semester)}" readonly style="opacity:0.7"></div>
                            <div class="form-group"><label class="form-label">Nama Kelas Baru</label><select class="form-select" id="promoteTargetClass"><option value="">Memuat...</option></select></div>
                        </div>
                    </div>

                </div>
                <div class="panel-footer">
                    <button class="btn btn-ghost" onclick="Admin.closeFormModal('promoteClassModal')">Batal</button>
                    <button class="btn btn-success" id="savePromoteBtn" onclick="Admin.savePromoteClass()"><span class="btn-text">Proses Naik Kelas</span></button>
                </div>
            </div>
        </div>`;
        $('body').append(modal);

        // Load Academic Years
        App.api('api/academic_years.php?action=list').done(res => {
            if (res.success && res.data) {
                let options = '<option value="">-- Pilih Tahun Ajaran Lama --</option>';
                res.data.forEach(y => {
                    if (y.id !== activeYear.id) {
                        options += `<option value="${y.id}">${App.escapeHtml(y.tahun_ajaran)} Semester ${App.escapeHtml(y.semester)}</option>`;
                    }
                });
                $('#promoteSourceYear').html(options);
            }
        });

        // Load Target Classes
        App.api('api/ref_kelas.php?action=list').done(res => {
            if (res.success && res.data) {
                let options = '<option value="">-- Pilih Kelas --</option>';
                res.data.forEach(c => {
                    options += `<option value="${App.escapeHtml(c.nama_kelas)}">${App.escapeHtml(c.nama_kelas)}</option>`;
                });
                $('#promoteTargetClass').html(options);
            } else {
                $('#promoteTargetClass').html('<option value="">-- Tidak ada data kelas --</option>');
            }
        }).fail(() => {
            $('#promoteTargetClass').html('<option value="">-- Gagal memuat kelas --</option>');
        });
    },

    loadPromoteSourceClasses(yearId) {
        $('#promoteSourceClass').html('<option value="">Memuat...</option>');
        $('#promoteStudentsList').html('<tr><td colspan="3" style="text-align:center; padding: 20px;" class="text-muted">Silakan pilih kelas sumber terlebih dahulu</td></tr>');
        
        if (!yearId) {
            $('#promoteSourceClass').html('<option value="">-- Pilih Tahun Ajaran Dulu --</option>');
            return;
        }

        App.api(`api/students.php?action=get_classes&academic_year_id=${yearId}`).done(res => {
            if (res.success && res.data) {
                let options = '<option value="">-- Pilih Kelas Lama --</option>';
                res.data.forEach(c => {
                    options += `<option value="${App.escapeHtml(c)}">${App.escapeHtml(c)}</option>`;
                });
                $('#promoteSourceClass').html(options);
            }
        });
    },

    loadPromoteStudents() {
        const yearId = $('#promoteSourceYear').val();
        const kelas = $('#promoteSourceClass').val();

        if (!yearId || !kelas) {
            $('#promoteStudentsList').html('<tr><td colspan="3" style="text-align:center; padding: 20px;" class="text-muted">Silakan pilih kelas sumber terlebih dahulu</td></tr>');
            return;
        }

        $('#promoteStudentsList').html('<tr><td colspan="3" style="text-align:center; padding: 20px;">Memuat data siswa...</td></tr>');
        
        App.api(`api/students.php?action=list&kelas=${encodeURIComponent(kelas)}&academic_year_id=${yearId}&per_page=1000`).done(res => {
            if (res.success && res.data && res.data.data) {
                const students = res.data.data;
                if (students.length === 0) {
                    $('#promoteStudentsList').html('<tr><td colspan="3" style="text-align:center; padding: 20px;" class="text-muted">Tidak ada siswa di kelas ini</td></tr>');
                    return;
                }
                const rows = students.map(s => `
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" checked class="promote-student-checkbox" value="${s.id}"></td>
                        <td><strong>${App.escapeHtml(s.nama)}</strong></td>
                        <td>${App.escapeHtml(s.nis || '-')}</td>
                    </tr>
                `).join('');
                $('#promoteStudentsList').html(rows);
                $('#selectAllPromoteStudents').prop('checked', true);
            }
        });
    },

    toggleSelectAllPromoteStudents(checkbox) {
        $('.promote-student-checkbox').prop('checked', checkbox.checked);
    },

    savePromoteClass() {
        const targetClass = $('#promoteTargetClass').val().trim();
        if (!targetClass) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Nama Kelas Baru harus diisi.' });
            return;
        }

        const selectedIds = [];
        $('.promote-student-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Pilih setidaknya satu siswa.' });
            return;
        }

        const btn = document.getElementById('savePromoteBtn');
        EModal.btnLoading(btn, true);

        const activeYear = App.state.academicYear;

        App.api('api/students.php?action=promote_students', {
            method: 'POST',
            data: {
                source_student_ids: selectedIds,
                target_academic_year_id: activeYear.id,
                target_kelas: targetClass
            }
        }).done(res => {
            if (res.success) {
                this.closeFormModal('promoteClassModal');
                EModal.info({ type: 'success', title: 'Berhasil!', message: res.message });
                this.loadStudentsTable();
            }
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Terjadi kesalahan.' });
        }).always(() => EModal.btnLoading(btn, false));
    },

    showStudentImportExcel() {
        this.studentImportData = [];
        const modal = `
        <div class="admin-form-modal show" id="studentImportModal" onclick="if(event.target===this)Admin.closeFormModal('studentImportModal')">
            <div class="admin-form-panel">
                <div class="panel-header"><h3>Import Siswa dari Excel</h3>
                    <button class="panel-close" onclick="Admin.closeFormModal('studentImportModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                </div>
                <div class="panel-body">
                    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">Format Excel: <strong>No, NIS, NISN, Nama, Email, Tempat Lahir, Jenis Kelamin, Tanggal Lahir, Kelas</strong>. Gunakan template agar konsisten.</p>
                    <div class="form-group"><label class="form-label">Tahun Ajaran</label><select class="form-select" id="studentImportYear"></select></div>
                    <button type="button" class="btn btn-ghost btn-sm" style="margin-bottom:12px" onclick="Admin.downloadStudentImportTemplate()">Download Template Excel</button>
                    <div class="drop-zone" id="studentExcelDropZone" onclick="document.getElementById('studentExcelFile').click()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <p>Klik atau drag file Excel di sini</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">.xlsx, .xls</p>
                    </div>
                    <input type="file" id="studentExcelFile" accept=".xlsx,.xls" style="display:none" onchange="Admin.handleStudentExcelFile(this)">
                    <div id="studentExcelName" class="mt-sm text-muted" style="font-size:13px"></div>
                    <div id="studentImportPreview" class="mt-sm text-muted" style="font-size:13px"></div>
                </div>
                <div class="panel-footer">
                    <button class="btn btn-ghost" onclick="Admin.closeFormModal('studentImportModal')">Batal</button>
                    <button class="btn btn-primary" id="importStudentBtn" onclick="Admin.importStudentExcel()"><span class="btn-text">Import</span></button>
                </div>
            </div>
        </div>`;
        $('body').append(modal);
        this.loadAcademicYearOptions('#studentImportYear', this.studentsAcademicYearId || (App.state.academicYear?.id || ''));

        const dz = document.getElementById('studentExcelDropZone');
        dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragover'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
        dz.addEventListener('drop', e => {
            e.preventDefault();
            dz.classList.remove('dragover');
            if (e.dataTransfer.files.length) Admin.handleStudentExcelFile({ files: e.dataTransfer.files });
        });
    },

    downloadStudentImportTemplate() {
        this.loadSheetJS(() => {
            const headers = ['No', 'NIS', 'NISN', 'Nama', 'Email', 'Tempat Lahir', 'Jenis Kelamin', 'Tanggal Lahir', 'Kelas'];
            const sample = ['1', '12345', '0012345678', 'Ahmad Fikri', 'ahmad.fikri@email.com', 'Surabaya', 'L', '2008-05-12', 'XII IPA 1'];
            const ws = XLSX.utils.aoa_to_sheet([headers, sample]);
            ws['!cols'] = [{ wch: 6 }, { wch: 14 }, { wch: 16 }, { wch: 28 }, { wch: 28 }, { wch: 18 }, { wch: 14 }, { wch: 14 }, { wch: 16 }];
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Template Siswa');
            XLSX.writeFile(wb, 'Template-Import-Siswa.xlsx');
        }, () => {
            EModal.toast({type:'error', title:'Gagal', message:'Tidak bisa memuat modul Excel.'});
        });
    },

    handleStudentExcelFile(input) {
        const file = input.files[0];
        if (!file) {
            return;
        }
        $('#studentExcelName').text(file.name || '');
        this.loadSheetJS(() => {
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const wb = XLSX.read(e.target.result, { type: 'binary' });
                    const ws = wb.Sheets[wb.SheetNames[0]];
                    const headers = ['no_urut', 'nis', 'nisn', 'nama', 'email', 'tempat_lahir', 'jenis_kelamin', 'tanggal_lahir', 'kelas'];
                    const data = XLSX.utils.sheet_to_json(ws, { header: headers, raw: false, defval: '' });
                    data.shift();
                    this.studentImportData = data.filter(row => Object.values(row).some(v => String(v ?? '').trim() !== ''));
                    $('#studentImportPreview').html(`<span class="text-success"><strong>${this.studentImportData.length}</strong> data siswa siap diimport.</span>`);
                } catch (err) {
                    this.studentImportData = [];
                    $('#studentImportPreview').html('<span class="text-danger">Gagal membaca file Excel.</span>');
                    EModal.toast({ type: 'error', title: 'Gagal', message: 'Format file Excel tidak valid.' });
                }
            };
            reader.readAsBinaryString(file);
        }, () => {
            EModal.toast({ type: 'error', title: 'Gagal', message: 'Gagal memuat modul Excel.' });
        });
    },

    importStudentExcel() {
        if (!this.studentImportData.length) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Pilih file Excel terlebih dahulu.' });
            return;
        }
        const btn = document.getElementById('importStudentBtn');
        const payload = {
            academic_year_id: $('#studentImportYear').val(),
            students: this.studentImportData
        };

        EModal.btnLoading(btn, true);
        App.api('api/students.php?action=import', { method: 'POST', data: payload }).done(res => {
            this.closeFormModal('studentImportModal');
            const errors = res.data?.errors?.length ? '<br><br>' + res.data.errors.slice(0, 8).map(App.escapeHtml).join('<br>') : '';
            EModal.info({ type: res.data?.failed > 0 ? 'warning' : 'success', title: 'Import Selesai', message: res.message + errors });
            this.loadStudentsTable();
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error' });
        }).always(() => EModal.btnLoading(btn, false));
    },

    exportUsersToExcel() {
        const type = this.usersType;
        const loader = EModal.loading(`Menyiapkan data ${type === 'gurus' ? 'guru' : 'user'}...`);
        const extraRole = type === 'gurus' ? '&role=guru' : '&exclude_guru=1';
        
        App.api(`api/users.php?action=list&per_page=9999${extraRole}`).done(res => {
            EModal.close(loader);
            if (!res.success || !res.data?.data?.length) {
                EModal.toast({ type: 'warning', title: 'Kosong', message: 'Tidak ada data untuk diekspor.' });
                return;
            }

            this.loadSheetJS(() => {
                const isGuru = type === 'gurus';
                const headers = isGuru
                    ? ['Username', 'NIK', 'Email', 'Nama', 'Tempat Lahir', 'Tgl Lahir', 'Tupoksi', 'Jabatan', 'Status Guru', 'TPG', 'TMT', 'Password']
                    : ['Username', 'Password', 'Nama Lengkap', 'Role'];
                
                const data = res.data.data.map(u => {
                    if (isGuru) {
                        return [
                            u.username || '',
                            u.nik || '',
                            u.email || '',
                            u.nama_lengkap || '',
                            u.tempat_lahir || '',
                            u.tgl_lahir || '',
                            u.tupoksi || '',
                            u.jabatan || '',
                            u.status_guru || '',
                            u.tpg || 'Tidak',
                            u.tmt || '',
                            '' // Password empty for export
                        ];
                    }
                    return [
                        u.username || '',
                        '', // Password empty
                        u.nama_lengkap || '',
                        u.role || 'user'
                    ];
                });

                const ws = XLSX.utils.aoa_to_sheet([headers, ...data]);
                ws['!cols'] = headers.map(() => ({ wch: 20 }));
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, isGuru ? 'Data Guru' : 'Data User');
                XLSX.writeFile(wb, isGuru ? `Data-Guru-${Date.now()}.xlsx` : `Data-User-${Date.now()}.xlsx`);
            });
        }).fail(() => {
            EModal.close(loader);
            EModal.toast({ type: 'error', title: 'Gagal', message: 'Gagal mengambil data dari server.' });
        });
    },

    exportStudentsToExcel() {
        const yearId = this.studentsAcademicYearId || (App.state.academicYear?.id || '');
        if (!yearId) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Pilih tahun ajaran terlebih dahulu.' });
            return;
        }

        const loader = EModal.loading('Menyiapkan data siswa...');
        App.api(`api/students.php?action=list&academic_year_id=${yearId}&per_page=9999`).done(res => {
            EModal.close(loader);
            if (!res.success || !res.data?.data?.length) {
                EModal.toast({ type: 'warning', title: 'Kosong', message: 'Tidak ada data siswa untuk diekspor.' });
                return;
            }

            this.loadSheetJS(() => {
                const headers = ['No', 'NIS', 'NISN', 'Nama', 'Email', 'Tempat Lahir', 'Jenis Kelamin', 'Tanggal Lahir', 'Kelas'];
                const data = res.data.data.map(s => [
                    s.no_urut || '',
                    s.nis || '',
                    s.nisn || '',
                    s.nama || '',
                    s.email || '',
                    s.tempat_lahir || '',
                    s.jenis_kelamin || '',
                    s.tanggal_lahir || '',
                    s.kelas || ''
                ]);

                const ws = XLSX.utils.aoa_to_sheet([headers, ...data]);
                ws['!cols'] = [{ wch: 6 }, { wch: 14 }, { wch: 16 }, { wch: 28 }, { wch: 28 }, { wch: 18 }, { wch: 14 }, { wch: 14 }, { wch: 16 }];
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Data Siswa');
                XLSX.writeFile(wb, `Data-Siswa-${Date.now()}.xlsx`);
            });
        }).fail(() => {
            EModal.close(loader);
            EModal.toast({ type: 'error', title: 'Gagal', message: 'Gagal mengambil data siswa.' });
        });
    },

    // ==================== ACADEMIC YEAR SECTION ====================
    renderAcademicYears() {
        $('#adminContent').html(`
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-5"/></svg> Tahun Ajaran</h3>
                        <p class="admin-subtitle">Tahun ajaran aktif menjadi konteks bersama untuk semua modul eportal.</p>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="Admin.showAcademicYearForm()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Tahun Ajaran</button>
                </div>
                <div class="admin-card-body" id="academicYearsWrapper"><div class="skeleton" style="height:220px;border-radius:8px"></div></div>
            </div>`);
        this.loadAcademicYears();
    },

    loadAcademicYears() {
        App.api('api/academic_years.php?action=list').done(res => {
            if (!res.success || !res.data.length) {
                $('#academicYearsWrapper').html('<div class="empty-state"><h3>Belum Ada Tahun Ajaran</h3><p>Tambahkan tahun ajaran dan aktifkan salah satunya.</p></div>');
                return;
            }
            const rows = res.data.map(y => `
                <tr>
                    <td><strong>${App.escapeHtml(y.tahun_ajaran)}</strong></td>
                    <td>Semester ${App.escapeHtml(y.semester)}</td>
                    <td>${App.escapeHtml(y.tanggal_mulai || '-')} s.d. ${App.escapeHtml(y.tanggal_selesai || '-')}</td>
                    <td><span class="badge ${y.is_active == 1 ? 'badge-success' : 'badge-warning'}">${y.is_active == 1 ? 'Aktif' : 'Nonaktif'}</span></td>
                    <td><div class="actions">
                        ${y.is_active == 1 ? '' : `<button class="btn-icon" title="Aktifkan" onclick="Admin.activateAcademicYear(${y.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></button>`}
                        <button class="btn-icon" title="Edit" onclick="Admin.showAcademicYearForm(${y.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                        ${y.is_active == 1 ? '' : `<button class="btn-icon danger" title="Hapus" onclick="Admin.deleteAcademicYear(${y.id},'${App.escapeHtml(y.tahun_ajaran)}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>`}
                    </div></td>
                </tr>`).join('');
            $('#academicYearsWrapper').html(`<table class="data-table"><thead><tr><th>Tahun Ajaran</th><th>Semester</th><th>Periode</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
        });
    },

    showAcademicYearForm(id = null) {
        const isEdit = id !== null;
        const modal = `
        <div class="admin-form-modal show" id="academicYearFormModal" onclick="if(event.target===this)Admin.closeFormModal('academicYearFormModal')">
            <div class="admin-form-panel">
                <div class="panel-header"><h3>${isEdit ? 'Edit' : 'Tambah'} Tahun Ajaran</h3><button class="panel-close" onclick="Admin.closeFormModal('academicYearFormModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
                <div class="panel-body">
                    <input type="hidden" id="yearId" value="${id || ''}">
                    <div class="form-group"><label class="form-label">Tahun Ajaran</label><input class="form-input" id="yearLabel" placeholder="2025/2026"></div>
                    <div class="form-group"><label class="form-label">Semester</label><select class="form-select" id="yearSemester"><option value="1">Semester 1</option><option value="2">Semester 2</option></select></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Tanggal Mulai</label><input type="date" class="form-input" id="yearStart"></div>
                        <div class="form-group"><label class="form-label">Tanggal Selesai</label><input type="date" class="form-input" id="yearEnd"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Status</label><select class="form-select" id="yearActive"><option value="0">Nonaktif</option><option value="1">Aktifkan sebagai tahun ajaran global</option></select></div>
                </div>
                <div class="panel-footer"><button class="btn btn-ghost" onclick="Admin.closeFormModal('academicYearFormModal')">Batal</button><button class="btn btn-primary" id="saveYearBtn" onclick="Admin.saveAcademicYear()"><span class="btn-text">Simpan</span></button></div>
            </div>
        </div>`;
        $('body').append(modal);
        if (isEdit) {
            App.api(`api/academic_years.php?action=get&id=${id}`).done(res => {
                if (res.success) {
                    const y = res.data;
                    $('#yearLabel').val(y.tahun_ajaran);
                    $('#yearSemester').val(y.semester);
                    $('#yearStart').val(y.tanggal_mulai);
                    $('#yearEnd').val(y.tanggal_selesai);
                    $('#yearActive').val(y.is_active);
                }
            });
        }
    },

    saveAcademicYear() {
        const id = $('#yearId').val();
        const btn = document.getElementById('saveYearBtn');
        const data = {
            tahun_ajaran: $('#yearLabel').val().trim(),
            semester: $('#yearSemester').val(),
            tanggal_mulai: $('#yearStart').val(),
            tanggal_selesai: $('#yearEnd').val(),
            is_active: parseInt($('#yearActive').val())
        };
        if (id) data.id = parseInt(id);
        if (!/^\d{4}\/\d{4}$/.test(data.tahun_ajaran)) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Format tahun ajaran harus seperti 2025/2026.' });
            return;
        }
        EModal.btnLoading(btn, true);
        App.api(`api/academic_years.php?action=${id ? 'update' : 'create'}`, { method: 'POST', data }).done(res => {
            this.closeFormModal('academicYearFormModal');
            EModal.info({ type: 'success', title: 'Berhasil!', message: res.message });
            this.refreshActiveYearState().always(() => this.loadAcademicYears());
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error' });
        }).always(() => EModal.btnLoading(btn, false));
    },

    activateAcademicYear(id) {
        const loader = EModal.loading('Mengaktifkan tahun ajaran...');
        App.api('api/academic_years.php?action=activate', { method: 'POST', data: { id } }).done(res => {
            EModal.close(loader);
            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
            this.refreshActiveYearState().always(() => this.loadAcademicYears());
        }).fail(xhr => {
            EModal.close(loader);
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error' });
        });
    },

    deleteAcademicYear(id, label) {
        EModal.confirm({
            title: 'Hapus Tahun Ajaran',
            message: `Yakin hapus tahun ajaran <strong>${label}</strong>?`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                const loader = EModal.loading('Menghapus...');
                App.api('api/academic_years.php?action=delete', { method: 'POST', data: { id } }).done(res => {
                    EModal.close(loader);
                    EModal.info({ type: 'success', title: 'Dihapus!', message: res.message });
                    this.loadAcademicYears();
                }).fail(xhr => {
                    EModal.close(loader);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error' });
                });
            }
        });
    },

    loadAcademicYearOptions(selector, selected = '') {
        return App.api('api/academic_years.php?action=list').done(res => {
            const $el = $(selector);
            $el.empty();
            if (selector === '#studentYearFilter') {
                $el.append('<option value="">Tahun aktif</option>');
            }
            if (res.success && res.data.length) {
                res.data.forEach(y => {
                    const active = y.is_active == 1 ? ' (Aktif)' : '';
                    $el.append(`<option value="${y.id}">${App.escapeHtml(y.tahun_ajaran)} - Semester ${App.escapeHtml(y.semester)}${active}</option>`);
                });
                if (selected) $el.val(selected);
            } else {
                $el.append('<option value="">Belum ada tahun ajaran</option>');
            }
        });
    },

    refreshActiveYearState() {
        return App.api('api/academic_years.php?action=active').done(res => {
            if (res.success) {
                App.state.academicYear = res.data;
                localStorage.setItem('eportal_academic_year', JSON.stringify(res.data));
            }
        });
    },

    // ==================== MODULES SECTION ====================
    renderModules() {
        $('#adminContent').html(`
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8"/></svg> Kelola Modul</h3>
                    <button class="btn btn-primary btn-sm" onclick="Admin.showModuleForm()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Modul</button>
                </div>
                <div class="admin-card-body" id="modulesTableWrapper"><div class="skeleton" style="height:200px;border-radius:8px"></div></div>
            </div>`);
        this.loadModulesTable();
    },

    loadModulesTable() {
        App.api('api/modules.php?action=list').done(res => {
            if (!res.success || !res.data.length) {
                $('#modulesTableWrapper').html('<div class="empty-state"><h3>Belum Ada Modul</h3><p>Tambahkan modul untuk dashboard.</p></div>');
                return;
            }
            const rows = res.data.map(m => `
                <tr>
                    <td><div class="d-flex gap-sm" style="align-items:center"><div style="width:36px;height:36px;border-radius:8px;background:${m.color}15;color:${m.color};display:flex;align-items:center;justify-content:center;">${m.icon_svg.replace(/width="\d+"/g,'width="18"').replace(/height="\d+"/g,'height="18"')}</div><strong style="font-size:13px">${App.escapeHtml(m.nama_modul)}</strong></div></td>
                    <td style="font-size:12px">${App.escapeHtml(m.slug)}</td>
                    <td><span class="badge ${m.status==1?'badge-success':'badge-danger'}">${m.status==1?'Aktif':'Nonaktif'}</span></td>
                    <td><div class="actions">
                        <button class="btn-icon" title="Edit" onclick='Admin.showModuleForm(${JSON.stringify(m).replace(/'/g,"&#39;")})'><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                        <button class="btn-icon danger" title="Hapus" onclick="Admin.deleteModule(${m.id},'${App.escapeHtml(m.nama_modul)}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    </div></td>
                </tr>`).join('');
            $('#modulesTableWrapper').html(`<table class="data-table"><thead><tr><th>Modul</th><th>Slug</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
        });
    },

    showModuleForm(mod = null) {
        const isEdit = mod !== null;
        const m = mod || {};
        const modal = `
        <div class="admin-form-modal show" id="moduleFormModal" onclick="if(event.target===this)Admin.closeFormModal('moduleFormModal')">
            <div class="admin-form-panel">
                <div class="panel-header"><h3>${isEdit?'Edit':'Tambah'} Modul</h3><button class="panel-close" onclick="Admin.closeFormModal('moduleFormModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
                <div class="panel-body">
                    <div class="form-group"><label class="form-label">Nama Modul</label><input class="form-input" id="modNama" value="${App.escapeHtml(m.nama_modul||'')}"></div>
                    <div class="form-group"><label class="form-label">Slug</label><input class="form-input" id="modSlug" value="${App.escapeHtml(m.slug||'')}" ${isEdit?'readonly style="opacity:0.6"':''}></div>
                    <div class="form-group"><label class="form-label">Deskripsi</label><input class="form-input" id="modDesc" value="${App.escapeHtml(m.deskripsi||'')}"></div>
                    <div class="form-group"><label class="form-label">URL Path</label><input class="form-input" id="modUrl" value="${App.escapeHtml(m.url_path||'')}"></div>
                    <div class="form-group"><label class="form-label">Warna</label><input type="color" class="form-input" id="modColor" value="${m.color||'#1565C0'}" style="height:50px;padding:8px;cursor:pointer"></div>
                    <div class="form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                            <label class="form-label" style="margin:0;">Icon SVG</label>
                            <select class="form-select" style="width: auto; padding: 4px 8px; font-size: 12px; height: 28px;" onchange="if(this.value)$('#modIcon').val(this.value)">
                                <option value="">Rekomendasi Icon...</option>
                                <optgroup label="Pendidikan & Akademik">
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>'>Materi / E-Learning</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'>Jadwal (E-Schedule)</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 11"/></svg>'>Ujian Online (CBT)</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M15 2H9a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1z"/><path d="M9 14h6"/><path d="M9 18h6"/><path d="M9 10h6"/></svg>'>E-Rapor / Nilai</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>'>Kelulusan / Alumni</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 20s-1-4 7-4 7 4 7 4"/></svg>'>Data Siswa</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="M10 21h4"/><path d="M12 16v5"/><path d="M8 8l4 4 4-4"/></svg>'>Data Guru / Kelas</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6"/><path d="M10 3v5c0 .6-.2 1.1-.4 1.6L5.3 16.2C4.5 17.6 5 19.5 6.4 20.6c.5.3 1.1.4 1.6.4h8c1.6 0 2.9-1.3 2.9-2.9 0-.5-.1-1-.4-1.6l-4.3-6.6c-.3-.5-.4-1-.4-1.6V3"/></svg>'>Laboratorium</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>'>Perpustakaan</option>
                                </optgroup>
                                <optgroup label="Umum & Operasional">
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'>Sarpras / Bangunan</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'>Kinerja (E-Performance)</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'>Keuangan / SPP</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>'>Website Publik / Portal</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'>Kepegawaian (HRD)</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>'>Pengumuman / Berita</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>'>Penghargaan / Sertifikat</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'>Statistik / Data</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2" ry="2"/><path d="M3 8l9 6 9-6"/></svg>'>Surat Menyurat / Email</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>'>Server / Database</option>
                                    <option value='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>'>Komputer / IT</option>
                                </optgroup>
                            </select>
                        </div>
                        <textarea class="form-input" id="modIcon" rows="3" placeholder='<svg>...</svg>'>${m.icon_svg||''}</textarea>
                    </div>
                    ${isEdit?'<div class="form-group"><label class="form-label">Status</label><select class="form-select" id="modStatus"><option value="1" '+(m.status==1?'selected':'')+'>Aktif</option><option value="0" '+(m.status==0?'selected':'')+'>Nonaktif</option></select></div>':''}
                </div>
                <div class="panel-footer"><button class="btn btn-ghost" onclick="Admin.closeFormModal('moduleFormModal')">Batal</button><button class="btn btn-primary" id="saveModBtn" onclick="Admin.saveModule(${isEdit?m.id:'null'})"><span class="btn-text">Simpan</span></button></div>
            </div>
        </div>`;
        $('body').append(modal);
        if(!isEdit) $('#modNama').on('input',function(){ $('#modSlug').val($(this).val().toLowerCase().replace(/\s+/g,'-').replace(/[^a-z0-9-]/g,'')); });
    },

    saveModule(id) {
        const btn=document.getElementById('saveModBtn');
        const data={nama_modul:$('#modNama').val().trim(),slug:$('#modSlug').val().trim(),deskripsi:$('#modDesc').val().trim(),url_path:$('#modUrl').val().trim(),color:$('#modColor').val(),icon_svg:$('#modIcon').val().trim()};
        if(id){data.id=id;data.status=parseInt($('#modStatus').val());}
        if(!data.nama_modul||!data.icon_svg){EModal.toast({type:'warning',title:'Perhatian',message:'Nama dan icon SVG wajib diisi.'});return;}
        EModal.btnLoading(btn,true);
        App.api(`api/modules.php?action=${id?'update':'create'}`,{method:'POST',data}).done(res=>{
            if(res.success){this.closeFormModal('moduleFormModal');EModal.info({type:'success',title:'Berhasil!',message:res.message});this.loadModulesTable();}
        }).fail(xhr=>EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Error'})).always(()=>EModal.btnLoading(btn,false));
    },

    deleteModule(id,name) {
        EModal.confirm({title:'Hapus Modul',message:`Yakin hapus modul <strong>${name}</strong>?`,type:'danger',confirmText:'Ya, Hapus',
            onConfirm:()=>{const l=EModal.loading('Menghapus...');App.api('api/modules.php?action=delete',{method:'POST',data:{id}}).done(res=>{EModal.close(l);if(res.success){EModal.info({type:'success',title:'Dihapus!',message:res.message});this.loadModulesTable();}}).fail(xhr=>{EModal.close(l);EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Error'});});}
        });
    },

    // ==================== SETTINGS SECTION ====================
    renderSettings() {
        $('#adminContent').html(`<div class="admin-card"><div class="admin-card-body" id="settingsContent"><div class="skeleton" style="height:300px;border-radius:8px"></div></div></div>`);
        App.api('api/settings.php?action=get').done(res => {
            if (!res.success) return;
            const s = res.data;
            $('#settingsContent').html(`
                <div class="settings-section">
                    <h4>Identitas Sekolah</h4><p>Atur nama dan ikon sekolah yang ditampilkan di portal.</p>
                    <div class="form-group"><label class="form-label">Nama Sekolah</label><input class="form-input" id="setNamaSekolah" value="${App.escapeHtml(s.nama_sekolah?.value||'')}"></div>
                    <div class="form-group"><label class="form-label">Nama Kepala Sekolah</label><input class="form-input" id="setKepalaSekolah" value="${App.escapeHtml(s.kepala_sekolah?.value||'')}" placeholder="Nama kepala sekolah"></div>
                    <div class="form-group"><label class="form-label">Icon/Logo Sekolah</label>
                        <div class="icon-preview" id="iconPreview">${s.icon_sekolah?.value?`<img src="${App.baseUrl}${s.icon_sekolah.value}">`:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'}</div>
                        <input type="file" class="form-input" id="schoolIconFile" accept="image/*" style="padding:10px" onchange="Admin.uploadSchoolIcon()">
                    </div>
                    <div class="form-group"><label class="form-label">Kop Surat</label>
                        <div class="kop-preview" id="kopPreview">${s.kop_surat?.value?`<img src="${App.baseUrl}${s.kop_surat.value}">`:'<span>Belum ada kop surat</span>'}</div>
                        <input type="file" class="form-input" id="kopSuratFile" accept="image/*" style="padding:10px" onchange="Admin.uploadKopSurat()">
                        <p class="admin-subtitle">Kop ini menjadi kop default untuk surat di semua modul.</p>
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                    <button class="btn btn-ghost btn-sm" onclick="Admin.goTo('reset-data')" style="color:var(--danger)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="margin-right:6px"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                        Reset Data Sistem
                    </button>
                    <button class="btn btn-primary" id="saveSettingsBtn" onclick="Admin.saveSettings()"><span class="btn-text">Simpan Pengaturan</span></button>
                </div>
            `);
        });
    },

    uploadSchoolIcon() {
        const file=$('#schoolIconFile')[0].files[0];
        if(!file)return;
        const fd=new FormData();fd.append('icon',file);
        const loader=EModal.loading('Mengupload icon...');
        $.ajax({url:App.baseUrl+'api/settings.php?action=upload-icon',method:'POST',data:fd,processData:false,contentType:false,headers:{'Authorization':'Bearer '+App.state.token}}).done(res=>{
            EModal.close(loader);
            if(res.success){
                $('#iconPreview').html(`<img src="${App.baseUrl}${res.data.path}">`);
                $('.sidebar-logo').html(`<img src="${App.baseUrl}${res.data.path}">`);
                $('#dynamicFavicon, #dynamicAppleIcon').attr('href', App.baseUrl + res.data.path + '?v=' + Date.now());
                App.state.school.icon=res.data.path;
                localStorage.setItem('eportal_school',JSON.stringify(App.state.school));
                EModal.toast({type:'success',title:'Berhasil',message:'Icon berhasil diupload.'});
            }
        }).fail(()=>{EModal.close(loader);EModal.toast({type:'error',title:'Gagal',message:'Gagal upload icon.'});});
    },

    uploadKopSurat() {
        const file=$('#kopSuratFile')[0].files[0];
        if(!file)return;
        const fd=new FormData();fd.append('kop_surat',file);
        const loader=EModal.loading('Mengupload kop surat...');
        $.ajax({url:App.baseUrl+'api/settings.php?action=upload-kop-surat',method:'POST',data:fd,processData:false,contentType:false,headers:{'Authorization':'Bearer '+App.state.token}}).done(res=>{
            EModal.close(loader);
            if(res.success){
                $('#kopPreview').html(`<img src="${App.baseUrl}${res.data.path}">`);
                EModal.toast({type:'success',title:'Berhasil',message:'Kop surat berhasil diupload.'});
            }
        }).fail(xhr=>{EModal.close(loader);EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Gagal upload kop surat.'});});
    },

    saveSettings() {
        const btn=document.getElementById('saveSettingsBtn');
        EModal.btnLoading(btn,true);
        const data={nama_sekolah:$('#setNamaSekolah').val().trim(),kepala_sekolah:$('#setKepalaSekolah').val().trim()};
        App.api('api/settings.php?action=update',{method:'POST',data}).done(res=>{
            if(res.success){App.state.school.nama=data.nama_sekolah;localStorage.setItem('eportal_school',JSON.stringify(App.state.school));EModal.info({type:'success',title:'Tersimpan!',message:'Pengaturan berhasil disimpan.'});}
        }).fail(xhr=>EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Error'})).always(()=>EModal.btnLoading(btn,false));
    },

    // ==================== RESET DATA SECTION ====================
    renderResetData() {
        $('#adminContent').html(`
            <div class="admin-card reset-card">
                <div class="reset-hero">
                    <div class="reset-hero-main">
                        <div class="reset-hero-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="m12 7v5l4 2"/></svg>
                        </div>
                        <div>
                            <h3>Reset Data Sistem</h3>
                            <p>Bersihkan data dari kategori tertentu secara permanen. Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="reset-options-grid">
                        <div class="reset-opt">
                            <label class="reset-opt-label">
                                <input type="checkbox" name="resetCat" value="students">
                                <div class="reset-opt-info">
                                    <strong>Data Siswa & Akun</strong>
                                    <span>Siswa, Nilai Kelulusan, Surat, Akun Login Siswa, Peserta Ujian.</span>
                                </div>
                            </label>
                        </div>
                        <div class="reset-opt">
                            <label class="reset-opt-label">
                                <input type="checkbox" name="resetCat" value="teachers">
                                <div class="reset-opt-info">
                                    <strong>Data Guru & Akses</strong>
                                    <span>Data Guru, Akses Modul Guru (Kelulusan, Ujian, Sarpras). *Akun Superadmin Aman.</span>
                                </div>
                            </label>
                        </div>
                        <div class="reset-opt">
                            <label class="reset-opt-label">
                                <input type="checkbox" name="resetCat" value="academic">
                                <div class="reset-opt-info">
                                    <strong>Tahun Ajaran</strong>
                                    <span>Menghapus daftar tahun ajaran. (Hanya jika data siswa kosong).</span>
                                </div>
                            </label>
                        </div>
                        <div class="reset-opt">
                            <label class="reset-opt-label">
                                <input type="checkbox" name="resetCat" value="sarpras">
                                <div class="reset-opt-info">
                                    <strong>Sarpras (Inventaris)</strong>
                                    <span>Tanah, Bangunan, Ruang, Barang, Peminjaman, Riwayat Perbaikan.</span>
                                </div>
                            </label>
                        </div>
                        <div class="reset-opt">
                            <label class="reset-opt-label">
                                <input type="checkbox" name="resetCat" value="schedule">
                                <div class="reset-opt-info">
                                    <strong>Jadwal (E-Schedule)</strong>
                                    <span>Jam Belajar, Mapel, Kelas, Distribusi Jam, Jadwal Pelajaran.</span>
                                </div>
                            </label>
                        </div>
                        <div class="reset-opt">
                            <label class="reset-opt-label">
                                <input type="checkbox" name="resetCat" value="exam">
                                <div class="reset-opt-info">
                                    <strong>Kartu Ujian (E-Xam Card)</strong>
                                    <span>Daftar Ujian, Peserta, Kelas Ujian, Pengaturan Kartu.</span>
                                </div>
                            </label>
                        </div>
                        <div class="reset-opt">
                            <label class="reset-opt-label">
                                <input type="checkbox" name="resetCat" value="graduation">
                                <div class="reset-opt-info">
                                    <strong>Kelulusan (E-Graduation)</strong>
                                    <span>Mapel Lulus, Grup Mapel, Pengaturan Surat, Nilai Lulus Siswa.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="reset-warning">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 9 2 2-2 2-2-2 2-2Z"/><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8Z"/><path d="M12 7v6"/><path d="M12 17h.01"/></svg>
                        <div>
                            <strong>Peringatan!</strong>
                            <p>Data yang telah dihapus tidak dapat dikembalikan. Pastikan Anda telah melakukan Backup Database jika diperlukan.</p>
                        </div>
                    </div>
                </div>
                <div class="admin-card-footer" style="text-align:right">
                    <button class="btn btn-danger" id="resetDataBtn" onclick="Admin.doResetData()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        Reset Data Terpilih
                    </button>
                </div>
            </div>
        `);
    },

    doResetData() {
        const checked = $('input[name="resetCat"]:checked');
        if (checked.length === 0) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Pilih minimal satu kategori data.' });
            return;
        }

        const categories = checked.map(function() { return $(this).val(); }).get();
        const labels = checked.map(function() { return $(this).closest('label').find('strong').text(); }).get();

        EModal.confirm({
            title: 'Konfirmasi Reset Data',
            message: `<p>Anda akan menghapus kategori berikut secara permanen:</p><ul style="margin-top:8px;padding-left:20px">${labels.map(l => `<li>${l}</li>`).join('')}</ul><p style="margin-top:12px;color:var(--danger);font-weight:600;">Ketik "RESET" untuk mengonfirmasi:</p><input type="text" id="confirmResetText" class="form-input mt-sm" placeholder="RESET">`,
            type: 'danger',
            confirmText: 'Ya, Reset Sekarang',
            onConfirm: () => {
                const confirmVal = $('#confirmResetText').val();
                if (confirmVal !== 'RESET') {
                    EModal.toast({ type: 'error', title: 'Gagal', message: 'Konfirmasi tidak sesuai. Harap ketik RESET.' });
                    return false;
                }

                const btn = document.getElementById('resetDataBtn');
                EModal.btnLoading(btn, true);
                const loader = EModal.loading('Sedang mereset data...');

                App.api('api/reset.php?action=reset', {
                    method: 'POST',
                    data: { categories: categories }
                }).done(res => {
                    EModal.close(loader);
                    EModal.info({ type: 'success', title: 'Reset Berhasil', message: res.message });
                    // Refresh current section if needed, or go to dashboard
                    this.goTo('dashboard');
                    setTimeout(() => window.location.reload(), 1500);
                }).fail(xhr => {
                    EModal.close(loader);
                    EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Terjadi kesalahan server.' });
                }).always(() => EModal.btnLoading(btn, false));
            }
        });
    },

    // ==================== REFERENSI SECTION ====================
    refCategory: 'jabatan',
    refSearch: '',
    refCategories: {
        jabatan: 'Jabatan',
        tupoksi: 'Tupoksi',
        status_guru: 'Status Guru',
        kelas: 'Data Kelas'
    },
    refCategoryDescriptions: {
        jabatan: 'Pilihan jabatan yang muncul pada form Data Guru.',
        tupoksi: 'Daftar tupoksi untuk pemetaan tugas guru.',
        status_guru: 'Status kepegawaian atau status guru.',
        kelas: 'Kelola referensi data kelas dan tingkat.'
    },

    renderReferensi() {
        const categoryButtons = Object.entries(this.refCategories).map(([key, label]) => `
            <button class="ref-category-btn ${this.refCategory === key ? 'active' : ''}" onclick="Admin.setRefCategory('${key}')">
                <span>${label}</span>
                <small class="ref-tab-count" data-cat="${key}">0</small>
            </button>
        `).join('');
        const label = this.refCategories[this.refCategory] || 'Referensi';
        const description = this.refCategoryDescriptions[this.refCategory] || 'Kelola data referensi yang digunakan sistem.';

        $('#adminContent').html(`
            <div class="admin-card ref-card">
                <div class="ref-hero">
                    <div class="ref-hero-main">
                        <div class="ref-hero-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/><circle cx="12" cy="12" r="10" opacity="0.35"/></svg>
                        </div>
                        <div>
                            <h3>Data Referensi Guru</h3>
                            <p>Kelola pilihan dropdown untuk Data Guru agar input jabatan, tupoksi, dan status tetap konsisten.</p>
                            <span class="active-year-pill" id="refActiveLabel">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                                ${label}
                            </span>
                        </div>
                    </div>
                    <div class="ref-hero-actions">
                        <button class="btn btn-primary btn-sm" onclick="Admin.showRefForm()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Tambah ${label}
                        </button>
                    </div>
                </div>
                <div class="ref-toolbar">
                    <div class="ref-category-group" aria-label="Kategori referensi">
                        ${categoryButtons}
                    </div>
                    <div class="ref-toolbar-right">
                        <div class="search-box ref-search">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="refSearch" placeholder="Cari ${label.toLowerCase()}..." value="${App.escapeHtml(this.refSearch)}">
                        </div>
                        <span class="ref-count" id="refCount">0 data</span>
                    </div>
                </div>
                <div class="ref-context">
                    <strong>${label}</strong>
                    <span>${description}</span>
                </div>
                <div class="admin-card-body" id="refTableWrapper"><div class="skeleton" style="height:260px;border-radius:8px"></div></div>
            </div>`);
        $('#refSearch').on('input', App.debounce(e => {
            this.refSearch = e.target.value;
            this.loadRefTable();
        }, 300));
        this.loadRefSummary();
        this.loadRefTable();
    },

    setRefCategory(category) {
        if (!this.refCategories[category]) return;
        this.refCategory = category;
        this.refSearch = '';
        this.renderReferensi();
    },

    loadRefSummary() {
        App.api('api/referensi.php?action=list').done(res => {
            if (!res.success) return;
            const counts = {};
            (res.data || []).forEach(r => {
                counts[r.kategori] = (counts[r.kategori] || 0) + 1;
            });
            Object.keys(this.refCategories).forEach(key => {
                if (key !== 'kelas') $(`.ref-tab-count[data-cat="${key}"]`).text(counts[key] || 0);
            });
        });
        App.api('api/ref_kelas.php?action=list').done(res => {
            if (res.success) {
                $(`.ref-tab-count[data-cat="kelas"]`).text(res.data.length);
            }
        });
    },

    loadRefTable() {
        if (this.refCategory === 'kelas') return this.loadKelasTable();
        const label = this.refCategories[this.refCategory] || 'Referensi';
        const query = (this.refSearch || '').trim().toLowerCase();
        App.api(`api/referensi.php?action=list&kategori=${this.refCategory}`).done(res => {
            if (!res.success) return;
            const allRows = res.data || [];
            const filteredRows = query
                ? allRows.filter(r => (`${r.nama || ''} ${r.keterangan || ''}`).toLowerCase().includes(query))
                : allRows;
            $('#refCount').text(query ? `${filteredRows.length} dari ${allRows.length} data` : `${allRows.length} data`);

            if (!filteredRows.length) {
                const emptyTitle = query ? 'Data Tidak Ditemukan' : `Belum Ada ${label}`;
                const emptyText = query ? 'Coba gunakan kata kunci lain atau kosongkan pencarian.' : `Tambahkan referensi ${label.toLowerCase()} untuk pilihan dropdown Data Guru.`;
                $('#refTableWrapper').html(`
                    <div class="reference-empty">
                        <div class="reference-empty-illustration">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6h16M4 12h10M4 18h7"/><circle cx="18" cy="16" r="3"/><path d="m21 19-1.5-1.5"/></svg>
                        </div>
                        <h3>${emptyTitle}</h3>
                        <p>${emptyText}</p>
                        ${query ? `<button class="btn btn-ghost btn-sm" onclick="Admin.refSearch='';Admin.renderReferensi()">Reset Pencarian</button>` : `<button class="btn btn-primary btn-sm" onclick="Admin.showRefForm()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah ${label}</button>`}
                    </div>`);
                return;
            }

            const rows = filteredRows.map((r, i) => `
                <tr>
                    <td>
                        <div class="reference-name">
                            <span class="reference-number">${i + 1}</span>
                            <div>
                                <strong>${App.escapeHtml(r.nama)}</strong>
                                <small>Dropdown ${label}</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-info">${label}</span></td>
                    <td class="reference-note">${App.escapeHtml(r.keterangan || '-')}</td>
                    <td style="text-align:right">
                        <div class="actions" style="justify-content:flex-end">
                            <button class="btn-icon" title="Edit" onclick="Admin.showRefForm(${r.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="btn-icon danger" title="Hapus" onclick="Admin.deleteRef(${r.id},'${App.escapeHtml(r.nama)}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>`).join('');
            $('#refTableWrapper').html(`
                <table class="data-table reference-table">
                    <thead><tr><th>Nama Referensi</th><th>Kategori</th><th>Keterangan</th><th style="text-align:right">Aksi</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        });
    },

    showRefForm(id=null) {
        if (this.refCategory === 'kelas') return this.showKelasForm(id);
        const isEdit=id!==null;
        const label = this.refCategories[this.refCategory] || 'Referensi';
        const modal=`
        <div class="admin-form-modal show" id="refFormModal" onclick="if(event.target===this)Admin.closeFormModal('refFormModal')">
            <div class="admin-form-panel">
                <div class="panel-header"><h3>${isEdit?'Edit':'Tambah'} Data Referensi</h3><button class="panel-close" onclick="Admin.closeFormModal('refFormModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
                <div class="panel-body">
                    <input type="hidden" id="refKategori" value="${this.refCategory}">
                    <div class="form-group"><label class="form-label">Nama Referensi (${label})</label><input class="form-input" id="refNama" placeholder="Nama ${label.toLowerCase()}"></div>
                    <div class="form-group"><label class="form-label">Keterangan (Opsional)</label><input class="form-input" id="refKet" placeholder="Keterangan singkat"></div>
                </div>
                <div class="panel-footer"><button class="btn btn-ghost" onclick="Admin.closeFormModal('refFormModal')">Batal</button><button class="btn btn-primary" id="saveRefBtn" onclick="Admin.saveRef(${id||'null'})"><span class="btn-text">Simpan</span></button></div>
            </div>
        </div>`;
        $('body').append(modal);
        if(isEdit){App.api(`api/referensi.php?action=get&id=${id}`).done(res=>{if(res.success){$('#refNama').val(res.data.nama);$('#refKet').val(res.data.keterangan);}});}
    },

    saveRef(id) {
        const btn=document.getElementById('saveRefBtn');
        const data={kategori:$('#refKategori').val().trim(),nama:$('#refNama').val().trim(),keterangan:$('#refKet').val().trim()};
        if(id)data.id=id;
        if(!data.nama){EModal.toast({type:'warning',title:'Perhatian',message:'Nama wajib diisi.'});return;}
        EModal.btnLoading(btn,true);
        App.api(`api/referensi.php?action=${id?'update':'create'}`,{method:'POST',data}).done(res=>{
            if(res.success){this.closeFormModal('refFormModal');EModal.info({type:'success',title:'Berhasil!',message:res.message});this.loadRefSummary();this.loadRefTable();}
        }).fail(xhr=>EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Error'})).always(()=>EModal.btnLoading(btn,false));
    },

    deleteRef(id,name) {
        EModal.confirm({title:'Hapus Referensi',message:`Yakin hapus referensi <strong>${name}</strong>?`,type:'danger',confirmText:'Ya, Hapus',
            onConfirm:()=>{const l=EModal.loading('Menghapus...');App.api('api/referensi.php?action=delete',{method:'POST',data:{id}}).done(res=>{EModal.close(l);if(res.success){EModal.info({type:'success',title:'Dihapus!',message:res.message});this.loadRefSummary();this.loadRefTable();}}).fail(xhr=>{EModal.close(l);EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Error'});});}
        });
    },
    loadKelasTable() {
        const query = (this.refSearch || '').trim().toLowerCase();
        App.api(`api/ref_kelas.php?action=list`).done(res => {
            if (!res.success) return;
            const allRows = res.data || [];
            const filteredRows = query
                ? allRows.filter(r => (`${r.tingkat || ''} ${r.nama_kelas || ''} ${r.keterangan || ''}`).toLowerCase().includes(query))
                : allRows;
            $('#refCount').text(query ? `${filteredRows.length} dari ${allRows.length} data` : `${allRows.length} data`);

            if (!filteredRows.length) {
                const emptyTitle = query ? 'Data Tidak Ditemukan' : 'Belum Ada Data Kelas';
                const emptyText = query ? 'Coba gunakan kata kunci lain.' : 'Tambahkan data kelas baru.';
                $('#refTableWrapper').html(`
                    <div class="reference-empty">
                        <div class="reference-empty-illustration">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6h16M4 12h10M4 18h7"/><circle cx="18" cy="16" r="3"/><path d="m21 19-1.5-1.5"/></svg>
                        </div>
                        <h3>${emptyTitle}</h3>
                        <p>${emptyText}</p>
                        ${query ? `<button class="btn btn-ghost btn-sm" onclick="Admin.refSearch='';Admin.renderReferensi()">Reset Pencarian</button>` : `<button class="btn btn-primary btn-sm" onclick="Admin.showKelasForm()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Data Kelas</button>`}
                    </div>`);
                return;
            }

            const rows = filteredRows.map((r, i) => `
                <tr>
                    <td>
                        <div class="reference-name">
                            <span class="reference-number">${i + 1}</span>
                            <div>
                                <strong>${App.escapeHtml(r.tingkat)}</strong>
                            </div>
                        </div>
                    </td>
                    <td><strong>${App.escapeHtml(r.nama_kelas)}</strong></td>
                    <td><span class="badge ${r.wali_kelas_nama ? 'badge-primary' : 'badge-danger'}">${App.escapeHtml(r.wali_kelas_nama || 'Belum Ditentukan')}</span></td>
                    <td class="reference-note">${App.escapeHtml(r.keterangan || '-')}</td>
                    <td style="text-align:right">
                        <div class="actions" style="justify-content:flex-end">
                            <button class="btn-icon" title="Edit" onclick="Admin.showKelasForm(${r.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="btn-icon danger" title="Hapus" onclick="Admin.deleteKelas(${r.id},'${App.escapeHtml(r.nama_kelas)}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>`).join('');
            $('#refTableWrapper').html(`
                <table class="data-table reference-table">
                    <thead><tr><th>Tingkat</th><th>Nama Kelas</th><th>Wali Kelas</th><th>Keterangan</th><th style="text-align:right">Aksi</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        });
    },

    showKelasForm(id=null) {
        const isEdit=id!==null;
        const loader = EModal.loading('Memuat data guru...');
        App.api('api/ref_kelas.php?action=list_teachers').done(res => {
            EModal.close(loader);
            const teachers = res.data || [];

            const modal=`
            <div class="admin-form-modal show" id="refFormModal" onclick="if(event.target===this){Admin.closeFormModal('refFormModal');$(document).off('click.waliDropdown');}">
                <div class="admin-form-panel">
                    <div class="panel-header"><h3>${isEdit?'Edit':'Tambah'} Data Kelas</h3><button class="panel-close" onclick="Admin.closeFormModal('refFormModal');$(document).off('click.waliDropdown');"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
                    <div class="panel-body">
                        <style>
                            #waliSelectBtn:hover { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
                            #waliSelectBtn.active { border-color: #3b82f6; }
                            .wali-opt:hover { background: #f1f5f9; }
                        </style>
                        <div class="form-group"><label class="form-label">Tingkat</label><input class="form-input" id="refTingkat" placeholder="Misal: X, XI, XII atau 7, 8, 9"></div>
                        <div class="form-group"><label class="form-label">Nama Kelas</label><input class="form-input" id="refNamaKelas" placeholder="Misal: MIPA 1"></div>
                        
                        <div class="form-group">
                            <label class="form-label">Wali Kelas</label>
                            <div class="sp-cs-container" id="waliSelectContainer" style="position:relative; user-select:none; margin-bottom:15px;">
                                <div class="sp-cs-btn" id="waliSelectBtn" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:10px 15px; height:44px; transition:all 0.2s;">
                                    <span id="waliSelectedText" style="color:#64748b; font-size:0.95rem;">-- Pilih Wali Kelas --</span>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </div>
                                <div class="sp-cs-dropdown" id="waliSelectDropdown" style="display:none; position:absolute; top:calc(100% + 5px); left:0; right:0; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:9999; overflow:hidden;">
                                    <div style="padding:10px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                                        <input type="text" id="waliSearchInput" class="form-input" placeholder="Cari nama guru..." style="width:100%; padding:8px 12px; height:38px; border-radius:6px; border:1px solid #cbd5e1; outline:none;" autocomplete="off">
                                    </div>
                                    <div id="waliOptionsList" style="max-height:200px; overflow-y:auto; padding:5px 0;">
                                        <div class="sp-cs-option wali-opt" data-id="" data-nama="-- Pilih Wali Kelas --" style="padding:10px 15px; cursor:pointer; border-bottom:1px solid #f1f5f9; font-style:italic; color:#94a3b8;">
                                            -- Tanpa Wali Kelas --
                                        </div>
                                        ${teachers.map(t => `
                                            <div class="sp-cs-option wali-opt" data-id="${t.id}" data-nama="${App.escapeHtml(t.nama_lengkap)}" style="padding:10px 15px; cursor:pointer; border-bottom:1px solid #f1f5f9; transition:background 0.2s;">
                                                <div class="cs-opt-m" style="font-weight:600; color:#1e293b; font-size:0.95rem;">${App.escapeHtml(t.nama_lengkap)}</div>
                                                <div class="cs-opt-k" style="font-size:0.8rem; color:#64748b; margin-top:2px;">Username: ${App.escapeHtml(t.username)}</div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="refWaliKelas" value="">
                        </div>

                        <div class="form-group"><label class="form-label">Keterangan (Opsional)</label><input class="form-input" id="refKet" placeholder="Keterangan singkat"></div>
                    </div>
                    <div class="panel-footer"><button class="btn btn-ghost" onclick="Admin.closeFormModal('refFormModal');$(document).off('click.waliDropdown');">Batal</button><button class="btn btn-primary" id="saveRefBtn" onclick="Admin.saveKelas(${id||'null'})"><span class="btn-text">Simpan</span></button></div>
                </div>
            </div>`;
            $('body').append(modal);

            // Bind dropdown click
            $('#waliSelectBtn').on('click', function(e) {
                e.stopPropagation();
                $(this).toggleClass('active');
                $('#waliSelectDropdown').toggle();
                if ($('#waliSelectDropdown').is(':visible')) {
                    $('#waliSearchInput').val('').trigger('input').focus();
                }
            });

            // Click outside handler
            $(document).on('click.waliDropdown', function(e) {
                if (!$(e.target).closest('#waliSelectContainer').length) {
                    $('#waliSelectDropdown').hide();
                    $('#waliSelectBtn').removeClass('active');
                }
            });

            // Filter options
            $('#waliSearchInput').on('input', function() {
                const term = $(this).val().toLowerCase();
                $('.wali-opt').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(term));
                });
            });

            // Option selection
            $('#waliOptionsList').on('click', '.wali-opt', function() {
                const optId = $(this).attr('data-id');
                const optNama = $(this).attr('data-nama');
                
                $('#refWaliKelas').val(optId);
                $('#waliSelectedText').text(optNama).css('color', optId ? '#1e293b' : '#64748b');
                
                $('#waliSelectDropdown').hide();
                $('#waliSelectBtn').removeClass('active');
            });

            if(isEdit){
                App.api(`api/ref_kelas.php?action=get&id=${id}`).done(res=>{
                    if(res.success){
                        $('#refTingkat').val(res.data.tingkat);
                        $('#refNamaKelas').val(res.data.nama_kelas);
                        $('#refKet').val(res.data.keterangan);
                        
                        if(res.data.wali_kelas_id) {
                            const matched = teachers.find(t => String(t.id) === String(res.data.wali_kelas_id));
                            if(matched) {
                                $('#refWaliKelas').val(res.data.wali_kelas_id);
                                $('#waliSelectedText').text(matched.nama_lengkap).css('color', '#1e293b');
                            }
                        }
                    }
                });
            }
        }).fail(() => {
            EModal.close(loader);
            EModal.toast({type:'error',title:'Gagal',message:'Gagal memuat data guru.'});
        });
    },

    saveKelas(id) {
        const btn=document.getElementById('saveRefBtn');
        const data={
            tingkat:$('#refTingkat').val().trim(),
            nama_kelas:$('#refNamaKelas').val().trim(),
            wali_kelas_id:$('#refWaliKelas').val() || null,
            keterangan:$('#refKet').val().trim()
        };
        if(id)data.id=id;
        if(!data.tingkat || !data.nama_kelas){EModal.toast({type:'warning',title:'Perhatian',message:'Tingkat dan Nama Kelas wajib diisi.'});return;}
        EModal.btnLoading(btn,true);
        App.api(`api/ref_kelas.php?action=${id?'update':'create'}`,{method:'POST',data}).done(res=>{
            if(res.success){this.closeFormModal('refFormModal');$(document).off('click.waliDropdown');EModal.info({type:'success',title:'Berhasil!',message:res.message});this.loadRefSummary();this.loadRefTable();}
        }).fail(xhr=>EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Error'})).always(()=>EModal.btnLoading(btn,false));
    },

    deleteKelas(id,name) {
        EModal.confirm({title:'Hapus Data Kelas',message:`Yakin hapus kelas <strong>${name}</strong>?`,type:'danger',confirmText:'Ya, Hapus',
            onConfirm:()=>{const l=EModal.loading('Menghapus...');App.api('api/ref_kelas.php?action=delete',{method:'POST',data:{id}}).done(res=>{EModal.close(l);if(res.success){EModal.info({type:'success',title:'Dihapus!',message:res.message});this.loadRefSummary();this.loadRefTable();}}).fail(xhr=>{EModal.close(l);EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message||'Error'});});}
        });
    },

    // ==================== FOTO SISWA ====================
    photoItems: [],

    renderFotoSiswa() {
        const active = App.state.academicYear;
        $('#adminContent').html(`
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> Foto Siswa</h3>
                        <p class="admin-subtitle">Upload foto siswa berdasarkan NIS. Nama file harus sesuai NIS (contoh: <strong>10001.jpg</strong>). Tahun aktif: <strong>${active?.tahun_ajaran ? App.escapeHtml(active.tahun_ajaran) + ' Semester ' + App.escapeHtml(active.semester || '-') : 'Belum diatur'}</strong></p>
                    </div>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <span class="active-year-pill" id="photoStats" style="white-space:nowrap;">Memuat...</span>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
                        <button class="btn btn-primary btn-sm" id="btnUploadPhoto" style="display:inline-flex;align-items:center;gap:6px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Upload Foto
                        </button>
                        <button class="btn btn-accent btn-sm" id="btnUploadZip" style="display:inline-flex;align-items:center;gap:6px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Upload ZIP (Batch)
                        </button>
                    </div>
                    <div id="photoUploadProgress" style="display:none;margin-bottom:16px;">
                        <div style="background:#e2e8f0;border-radius:8px;overflow:hidden;height:8px;">
                            <div id="photoProgressBar" style="width:0%;height:100%;background:linear-gradient(90deg,#1565C0,#0F766E);transition:width 0.3s;"></div>
                        </div>
                        <p class="text-muted" style="margin-top:6px;font-size:13px;" id="photoProgressText">Mengupload...</p>
                    </div>
                    <div id="photoZipResult" style="display:none;margin-bottom:16px;"></div>
                    <input type="file" id="photoFileInput" accept=".jpg,.jpeg,.png" style="display:none">
                    <input type="file" id="photoZipInput" accept=".zip" style="display:none">
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                        <div class="search-box" style="min-width:280px;flex:1;">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="photoSearchInput" placeholder="Cari nama atau NIS...">
                        </div>
                        <select id="photoFilterClass" class="form-select" style="width:170px;flex:0 0 170px;">
                            <option value="">Semua Kelas</option>
                        </select>
                        <select id="photoFilterStatus" class="form-select" style="width:170px;flex:0 0 170px;">
                            <option value="all">Semua</option>
                            <option value="has_photo">Sudah ada foto</option>
                            <option value="no_photo">Belum ada foto</option>
                        </select>
                    </div>
                    <div id="photoGallery" class="admin-photo-grid">
                        <div style="grid-column:1/-1;text-align:center;padding:40px;color:#94a3b8;">Memuat data...</div>
                    </div>
                </div>
            </div>
        `);

        $('#btnUploadPhoto').on('click', () => $('#photoFileInput').click());
        $('#btnUploadZip').on('click', () => $('#photoZipInput').click());
        $('#photoFileInput').on('change', (e) => this.handlePhotoUpload(e));
        $('#photoZipInput').on('change', (e) => this.handleZipUpload(e));
        $('#photoSearchInput').on('input', App.debounce(() => this.filterPhotoGallery(), 300));
        $('#photoFilterStatus').on('change', () => this.filterPhotoGallery());
        $('#photoFilterClass').on('change', () => this.loadPhotoGallery());

        this.loadPhotoGallery();
    },

    loadPhotoGallery() {
        const kelas = encodeURIComponent($('#photoFilterClass').val() || '');
        App.api(`api/photos.php?action=list&kelas=${kelas}`).done(res => {
            this.photoItems = res.data.items || [];
            const total = res.data.total_all || res.data.total || 0;
            const withPhoto = res.data.total_with_photo || 0;
            $('#photoStats').html(`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> ${withPhoto} / ${total} foto`);

            // Fill class filter
            const classes = res.data.classes || [];
            const currentClass = $('#photoFilterClass').val();
            let classOptions = '<option value="">Semua Kelas</option>';
            classes.forEach(k => { classOptions += `<option value="${App.escapeHtml(k)}">${App.escapeHtml(k)}</option>`; });
            $('#photoFilterClass').html(classOptions).val(currentClass);

            this.renderPhotoGrid();
        }).fail(xhr => {
            $('#photoGallery').html(`<div style="grid-column:1/-1;text-align:center;padding:40px;color:#ef4444;">Gagal memuat: ${xhr.responseJSON?.message || 'Error'}</div>`);
        });
    },

    renderPhotoGrid() {
        const items = this.photoItems || [];
        if (!items.length) {
            $('#photoGallery').html('<div style="grid-column:1/-1;text-align:center;padding:40px;color:#94a3b8;">Tidak ada data siswa untuk filter ini.</div>');
            return;
        }

        const html = items.map(s => {
            const photoHtml = s.has_photo
                ? `<img src="${s.foto_url}?t=${Date.now()}" alt="${App.escapeHtml(s.nama)}" loading="lazy">`
                : `<div class="admin-photo-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:40px;height:40px;color:#cbd5e1"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>`;
            const deleteBtn = s.has_photo
                ? `<button class="admin-photo-del" data-id="${s.id}" title="Hapus foto"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>`
                : '';
            return `
                <div class="admin-photo-card" data-nis="${App.escapeHtml(s.nis)}" data-nama="${App.escapeHtml(s.nama)}" data-has-photo="${s.has_photo ? '1' : '0'}">
                    <div class="admin-photo-img">${photoHtml}${deleteBtn}</div>
                    <div class="admin-photo-info">
                        <div class="admin-photo-name">${App.escapeHtml(s.nama)}</div>
                        <div class="admin-photo-nis">NIS: ${App.escapeHtml(s.nis)} • ${App.escapeHtml(s.kelas)}</div>
                    </div>
                </div>`;
        }).join('');

        $('#photoGallery').html(html);
        this.filterPhotoGallery();

        // Delete button handler
        $('#photoGallery').off('click', '.admin-photo-del').on('click', '.admin-photo-del', (e) => {
            e.stopPropagation();
            const id = $(e.currentTarget).data('id');
            const card = $(e.currentTarget).closest('.admin-photo-card');
            const nama = card.data('nama');
            EModal.confirm({
                title: 'Hapus Foto',
                message: `Yakin ingin menghapus foto <strong>${App.escapeHtml(nama)}</strong>?`,
                type: 'danger',
                confirmText: 'Ya, Hapus',
                onConfirm: () => {
                    App.api('api/photos.php?action=delete', {
                        method: 'POST',
                        data: { student_id: id }
                    }).done(res => {
                        EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                        this.loadPhotoGallery();
                    }).fail(xhr => {
                        EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error' });
                    });
                }
            });
        });
    },

    filterPhotoGallery() {
        const search = ($('#photoSearchInput').val() || '').toLowerCase();
        const filter = $('#photoFilterStatus').val();
        $('#photoGallery .admin-photo-card').each(function() {
            const nama = ($(this).data('nama') || '').toString().toLowerCase();
            const nis = ($(this).data('nis') || '').toString().toLowerCase();
            const hasPhoto = $(this).data('has-photo') === '1' || $(this).data('has-photo') === 1;
            let show = true;
            if (search && !nama.includes(search) && !nis.includes(search)) show = false;
            if (filter === 'has_photo' && !hasPhoto) show = false;
            if (filter === 'no_photo' && hasPhoto) show = false;
            $(this).toggle(show);
        });
    },

    handlePhotoUpload(e) {
        const file = e.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('photo', file);

        $('#photoUploadProgress').show();
        $('#photoProgressBar').css('width', '50%');
        $('#photoProgressText').text('Mengupload foto...');

        $.ajax({
            url: App.baseUrl + 'api/photos.php?action=upload',
            method: 'POST',
            headers: { Authorization: 'Bearer ' + App.state.token },
            data: fd,
            processData: false,
            contentType: false
        }).done(res => {
            $('#photoProgressBar').css('width', '100%');
            $('#photoProgressText').text(res.message);
            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
            setTimeout(() => {
                $('#photoUploadProgress').hide();
                this.loadPhotoGallery();
            }, 1200);
        }).fail(xhr => {
            $('#photoUploadProgress').hide();
            EModal.toast({ type: 'error', title: 'Gagal Upload', message: xhr.responseJSON?.message || 'Terjadi kesalahan.' });
        }).always(() => {
            $('#photoFileInput').val('');
        });
    },

    handleZipUpload(e) {
        const file = e.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('zipfile', file);

        $('#photoUploadProgress').show();
        $('#photoZipResult').hide();
        $('#photoProgressBar').css('width', '30%');
        $('#photoProgressText').text('Mengekstrak dan memproses file ZIP...');

        $.ajax({
            url: App.baseUrl + 'api/photos.php?action=upload-zip',
            method: 'POST',
            headers: { Authorization: 'Bearer ' + App.state.token },
            data: fd,
            processData: false,
            contentType: false
        }).done(res => {
            $('#photoProgressBar').css('width', '100%');
            $('#photoProgressText').text('Selesai!');

            const details = (res.data.details || []).map(d => `<div style="padding:4px 0;font-size:13px;">${App.escapeHtml(d)}</div>`).join('');
            $('#photoZipResult').show().html(`
                <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:16px;">
                    <strong style="color:#166534;">${res.message}</strong>
                    <div style="margin-top:8px;max-height:180px;overflow-y:auto;">${details}</div>
                </div>
            `);

            setTimeout(() => $('#photoUploadProgress').hide(), 1500);
            this.loadPhotoGallery();
        }).fail(xhr => {
            $('#photoUploadProgress').hide();
            EModal.toast({ type: 'error', title: 'Gagal Upload ZIP', message: xhr.responseJSON?.message || 'Terjadi kesalahan.' });
        }).always(() => {
            $('#photoZipInput').val('');
        });
    }
};
