/**
 * Guru App — SPA JavaScript
 * Hash-based routing, Auth, Jadwal, Jurnal
 */
(function() {
    'use strict';

    const CONFIG = window.GURU_CONFIG || {};
    const BASE_URL = CONFIG.baseUrl || '/eportal/';
    const API_URL = CONFIG.moduleUrl || (BASE_URL + 'guru/');

    // =============================================
    // UTILITY HELPERS
    // =============================================
    const $ = sel => document.querySelector(sel);
    const $$ = sel => document.querySelectorAll(sel);

    const HARI_ORDER = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const HARI_MAP = { 1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 0: 'Minggu' };

    function getHariIni() {
        const d = new Date();
        const day = d.getDay(); // 0=Sunday
        return HARI_MAP[day] || 'Senin';
    }

    function getTanggalIni() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function formatTanggal(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr + 'T00:00:00');
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return days[d.getDay()] + ', ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }

    function getInitials(name) {
        if (!name) return '?';
        return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // =============================================
    // AUTH MODULE
    // =============================================
    const Auth = {
        token: null,
        user: null,
        school: null,
        academicYear: null,

        init() {
            this.token = localStorage.getItem('guru_token');
            const userData = localStorage.getItem('guru_user');
            const schoolData = localStorage.getItem('guru_school');
            if (userData) this.user = JSON.parse(userData);
            if (schoolData) this.school = JSON.parse(schoolData);
        },

        save(data) {
            this.token = data.token;
            this.user = data.user;
            this.school = data.school || {};
            this.academicYear = data.academic_year || {};
            localStorage.setItem('guru_token', data.token);
            localStorage.setItem('guru_user', JSON.stringify(data.user));
            localStorage.setItem('guru_school', JSON.stringify(data.school || {}));
            localStorage.setItem('guru_academic_year', JSON.stringify(data.academic_year || {}));
        },

        clear() {
            this.token = null;
            this.user = null;
            this.school = null;
            localStorage.removeItem('guru_token');
            localStorage.removeItem('guru_user');
            localStorage.removeItem('guru_school');
            localStorage.removeItem('guru_academic_year');
        },

        isLoggedIn() {
            return !!this.token;
        },

        async check() {
            if (!this.token) return false;
            try {
                const res = await API.get('api/auth.php?action=check');
                if (res.success) {
                    this.user = res.data.user;
                    this.school = res.data.school;
                    this.academicYear = res.data.academic_year;
                    localStorage.setItem('guru_user', JSON.stringify(res.data.user));
                    localStorage.setItem('guru_school', JSON.stringify(res.data.school));
                    return true;
                }
                this.clear();
                return false;
            } catch (e) {
                // If offline, still allow access with cached data
                return !!this.user;
            }
        },

        async login(username, password) {
            const res = await API.post('api/auth.php?action=login', { username, password });
            if (res.success) {
                this.save(res.data);
            }
            return res;
        },

        async logout() {
            try {
                await API.get('api/auth.php?action=logout');
            } catch(e) {}
            this.clear();
            Router.navigate('login');
        }
    };

    // =============================================
    // API MODULE
    // =============================================
    const API = {
        async request(method, endpoint, body = null) {
            const url = API_URL + endpoint;
            const opts = {
                method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };
            if (Auth.token) {
                opts.headers['Authorization'] = 'Bearer ' + Auth.token;
            }
            if (body) {
                opts.body = JSON.stringify(body);
            }
            const res = await fetch(url, opts);
            const data = await res.json();
            if (res.status === 401) {
                Auth.clear();
                Router.navigate('login');
                throw new Error('Session expired');
            }
            return data;
        },

        get(endpoint) { return this.request('GET', endpoint); },
        post(endpoint, body) { return this.request('POST', endpoint, body); }
    };

    // =============================================
    // TOAST MODULE
    // =============================================
    const Toast = {
        show(message, type = 'info') {
            const container = $('#toastContainer');
            if (!container) return;

            const iconMap = {
                success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
                error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
                warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
            };

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon ${type}">${iconMap[type] || iconMap.info}</div>
                <div class="toast-body">${escapeHtml(message)}</div>
            `;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('removing');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }
    };

    // =============================================
    // ROUTER MODULE
    // =============================================
    const Router = {
        currentPage: null,

        init() {
            window.addEventListener('hashchange', () => this.resolve());
            this.resolve();
        },

        navigate(page) {
            window.location.hash = '#/' + page;
        },

        resolve() {
            const hash = decodeURIComponent(window.location.hash).replace('#/', '') || '';
            const [page, ...params] = hash.split('/');

            if (!Auth.isLoggedIn() && page !== 'login') {
                this.navigate('login');
                return;
            }

            if (Auth.isLoggedIn() && page === 'login') {
                this.navigate('home');
                return;
            }

            this.currentPage = page || 'home';
            this.render(this.currentPage, params);
        },

        render(page, params = []) {
            const content = $('#appContent');
            if (!content) return;

            // Scroll to top on page navigation
            window.scrollTo(0, 0);

            // Header visibility: On home page, hide the top sticky header so there's no duplicate header
            const header = $('.app-header');
            if (header) {
                header.style.display = (page === 'home' || !page) ? 'none' : 'flex';
            }

            // Update bottom nav
            $$('.bottom-nav-item').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.page === page);
            });

            switch(page) {
                case 'login':
                    Pages.renderLogin();
                    break;
                case 'home':
                    Pages.renderHome();
                    break;
                case 'jadwal':
                    Pages.renderJadwal();
                    break;
                case 'jurnal':
                    Pages.renderJurnal(params[0] || null);
                    break;
                case 'riwayat':
                    Pages.renderRiwayat();
                    break;
                case 'jurnal-kelas':
                    Pages.renderJurnalKelas();
                    break;
                case 'profil':
                    Pages.renderProfil();
                    break;
                case 'izin':
                    Pages.renderIzin();
                    break;
                case 'absen':
                    Pages.renderAbsen();
                    break;
                default:
                    Pages.renderHome();
            }
        }
    };

    // =============================================
    // PAGES MODULE
    // =============================================
    const Pages = {
        // --- LOGIN PAGE ---
        renderLogin() {
            const appShell = $('#appShell');
            const loginPage = $('#loginPage');
            if (appShell) appShell.style.display = 'none';
            if (loginPage) {
                loginPage.style.display = 'flex';
                // Set school logo
                if (Auth.school && Auth.school.icon) {
                    const logo = loginPage.querySelector('.login-logo');
                    if (logo) {
                        logo.innerHTML = `<img src="${BASE_URL}${Auth.school.icon}" alt="Logo" onerror="this.parentElement.innerHTML='<svg class=\\'login-logo-fallback\\' viewBox=\\'0 0 24 24\\' fill=\\'none\\' stroke=\\'currentColor\\' stroke-width=\\'1.5\\'><path d=\\'M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20\\'/></svg>'">`;
                    }
                }
            }
        },

        async handleLogin(e) {
            e.preventDefault();
            const btn = $('#loginBtn');
            const errorEl = $('#loginError');
            const username = $('#loginUsername').value.trim();
            const password = $('#loginPassword').value;

            if (!username || !password) {
                errorEl.textContent = 'Username dan password harus diisi.';
                errorEl.classList.add('show');
                return;
            }

            btn.classList.add('loading');
            errorEl.classList.remove('show');

            try {
                const res = await Auth.login(username, password);
                if (res.success) {
                    Toast.show('Selamat datang, ' + (Auth.user?.nama_lengkap || 'Guru') + '!', 'success');
                    const loginPage = $('#loginPage');
                    const appShell = $('#appShell');
                    if (loginPage) loginPage.style.display = 'none';
                    if (appShell) appShell.style.display = 'flex';
                    App.updateHeader();
                    App.updateWaliNav();
                    Router.navigate('home');
                } else {
                    errorEl.textContent = res.message || 'Login gagal.';
                    errorEl.classList.add('show');
                }
            } catch(err) {
                errorEl.textContent = 'Tidak dapat terhubung ke server.';
                errorEl.classList.add('show');
            } finally {
                btn.classList.remove('loading');
            }
        },

        // --- HOME / DASHBOARD ---
        async renderHome() {
            const content = $('#appContent');
            const hariIni = getHariIni();
            const tanggalIni = getTanggalIni();
            const isWali = !!Auth.user?.wali_kelas;
            const hasMapel = !!Auth.user?.has_mapel;

            let roleSubtitle = 'Tenaga Pendidik';
            if (isWali) {
                roleSubtitle = `Wali Kelas ${escapeHtml(Auth.user.wali_kelas.nama_kelas)}${!hasMapel ? ' (Non-KBM)' : ''}`;
            } else if (!hasMapel) {
                roleSubtitle = 'Tenaga Pendidik (Non-KBM)';
            }

            content.innerHTML = `
                <div class="page-enter">
                    <!-- Curved Gradient Header Block -->
                    <div class="welcome-header-block" style="background: var(--primary-gradient); color: white; padding: 24px 20px 30px; border-radius: 0 0 28px 28px; margin: -20px -16px 20px; box-shadow: 0 10px 25px -5px rgba(21, 101, 192, 0.25); position: relative; overflow: hidden;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div class="welcome-avatar" style="width:48px; height:48px; border-radius:50%; border:2px solid white; background:#fff; color:var(--primary); display:flex; align-items:center; justify-content:center; font-family:var(--font-heading); font-size:1.15rem; font-weight:800; overflow:hidden; flex-shrink:0;">
                                    ${Auth.user?.avatar 
                                        ? `<img src="${BASE_URL}${Auth.user.avatar}" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">` 
                                        : getInitials(Auth.user?.nama_lengkap)
                                    }
                                </div>
                                <div>
                                    <div style="font-size:0.75rem; opacity:0.8; font-weight:500;">Assalamu'alaikum,</div>
                                    <div style="font-family:var(--font-heading); font-size:1.05rem; font-weight:800; line-height:1.2;">${escapeHtml(Auth.user?.nama_lengkap || 'Guru')}</div>
                                    <div style="font-size:0.7rem; opacity:0.75; font-weight:600; margin-top:2px;">
                                        ${roleSubtitle}
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <div class="welcome-icon-btn" style="width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.15); display:flex; align-items:center; justify-content:center; position:relative;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                    <span style="position:absolute; top:8px; right:8px; width:7px; height:7px; background:#ef4444; border-radius:50%; border:1px solid white;"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Banner Hero -->
                        <div class="welcome-banner-hero" style="background:rgba(255,255,255,0.08); border-radius:16px; margin-top:20px; padding:16px; display:flex; justify-content:space-between; align-items:center; border:1px solid rgba(255,255,255,0.12);">
                            <div>
                                <div style="font-size:0.75rem; font-weight:700; opacity:0.95; color:#fff;">${formatTanggal(tanggalIni)}</div>
                                <div style="font-family:var(--font-heading); font-size:1.25rem; font-weight:800; margin-top:2px;">Selamat Hari Ini!</div>
                                <div style="font-size:0.75rem; opacity:0.85; margin-top:2px;">Semangat beraktivitas & menginspirasi ☀️</div>
                            </div>
                            <div style="font-size:2.2rem; opacity:0.95; padding-right:4px;">📚</div>
                        </div>
                        <div class="welcome-hero-decoration" style="position: absolute; right: -20px; bottom: -30px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255, 255, 255, 0.04); pointer-events: none;"></div>
                    </div>

                    <!-- Menu Cepat (Quick Shortcuts) -->
                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        Menu Cepat
                    </div>
                    <div class="quick-shortcuts-grid" style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:20px;">
                        ${hasMapel ? `
                            <div class="shortcut-card" onclick="location.hash='#/jadwal'" style="background:white; border-radius:16px; padding:14px 10px; text-align:center; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:8px; transition:transform 0.2s ease;">
                                <div style="width:40px; height:40px; border-radius:12px; background:rgba(59,130,246,0.1); color:#3b82f6; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </div>
                                <span style="font-size:0.75rem; font-weight:700; color:var(--text-primary);">Jadwal</span>
                            </div>
                        ` : `
                            <div class="shortcut-card" onclick="location.hash='#/jurnal'" style="background:white; border-radius:16px; padding:14px 10px; text-align:center; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:8px; transition:transform 0.2s ease;">
                                <div style="width:40px; height:40px; border-radius:12px; background:rgba(59,130,246,0.1); color:#3b82f6; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </div>
                                <span style="font-size:0.75rem; font-weight:700; color:var(--text-primary);">Isi Jurnal</span>
                            </div>
                        `}
                        <div class="shortcut-card" onclick="location.hash='#/riwayat'" style="background:white; border-radius:16px; padding:14px 10px; text-align:center; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:8px; transition:transform 0.2s ease;">
                            <div style="width:40px; height:40px; border-radius:12px; background:rgba(16,185,129,0.1); color:#10b981; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                            <span style="font-size:0.75rem; font-weight:700; color:var(--text-primary);">Riwayat Jurnal</span>
                        </div>
                            <div class="shortcut-card" onclick="location.hash='#/izin'" style="background:white; border-radius:16px; padding:14px 10px; text-align:center; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:8px; transition:transform 0.2s ease;">
                                <div style="width:40px; height:40px; border-radius:12px; background:rgba(239,68,68,0.1); color:#ef4444; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/><line x1="12" y1="12" x2="12" y2="18"/></svg>
                                </div>
                                <span style="font-size:0.75rem; font-weight:700; color:var(--text-primary);">Izin Guru</span>
                            </div>
                            <div class="shortcut-card" onclick="location.hash='#/absen'" style="background:white; border-radius:16px; padding:14px 10px; text-align:center; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:8px; transition:transform 0.2s ease;">
                                <div style="width:40px; height:40px; border-radius:12px; background:rgba(6,182,212,0.1); color:#06b6d4; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                                </div>
                                <span style="font-size:0.75rem; font-weight:700; color:var(--text-primary);">Rekap Absen</span>
                            </div>
                        ${isWali ? `
                            <div class="shortcut-card" onclick="location.hash='#/jurnal-kelas'" style="background:white; border-radius:16px; padding:14px 10px; text-align:center; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:8px; transition:transform 0.2s ease;">
                                <div style="width:40px; height:40px; border-radius:12px; background:rgba(245,158,11,0.1); color:#f59e0b; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                </div>
                                <span style="font-size:0.75rem; font-weight:700; color:var(--text-primary);">Jurnal Kelas</span>
                            </div>
                        ` : ''}

                    <!-- Ringkasan Absensi Kelas (Untuk Wali Kelas) -->
                    <div id="homeWaliAbsenSummary"></div>

                    <!-- Jadwal Mengajar / Jurnal Kegiatan Hari Ini -->
                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        ${hasMapel ? 'Jadwal Hari Ini' : 'Jurnal Kegiatan Hari Ini'}
                    </div>
                    <div id="homeTodaySchedule" style="margin-bottom:20px;">
                        <div class="skeleton skeleton-card" style="height:60px; margin-bottom:8px;"></div>
                        <div class="skeleton skeleton-card" style="height:60px; margin-bottom:8px;"></div>
                    </div>

                    <!-- Jurnal Terbaru -->
                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Jurnal Terbaru
                    </div>
                    <div id="homeRecentJournals">
                        <div class="skeleton skeleton-card" style="height:70px; margin-bottom:8px;"></div>
                    </div>
                </div>
            `;

            try {
                const res = await API.get(`api/jurnal.php?action=dashboard_stats`);
                if (res.success) {
                    const schedules = res.data.schedules || [];
                    const wali = res.data.wali_stats;
                    const recents = res.data.recent_journals || [];

                    // Render Wali Kelas stats if applicable
                    const waliContainer = $('#homeWaliAbsenSummary');
                    if (wali && waliContainer) {
                        let color = '#10b981';
                        if (wali.attendance_rate < 85) color = '#ef4444';
                        else if (wali.attendance_rate < 95) color = '#f59e0b';

                        waliContainer.innerHTML = `
                            <div class="section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                Ringkasan Absensi Kelas
                            </div>
                            <div class="attendance-summary-card" style="background:white; border-radius:16px; padding:16px; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; position:relative; overflow:hidden;">
                                <div style="flex:1; z-index:2;">
                                    <div style="font-size:0.75rem; font-weight:700; color:var(--text-secondary); margin-bottom:2px;">Kelas ${escapeHtml(wali.class_name)}</div>
                                    <div style="font-family:var(--font-heading); font-size:1.375rem; font-weight:800; color:var(--text-primary); margin-bottom:4px;">
                                        ${wali.present_students} <span style="font-size:0.875rem; font-weight:600; color:var(--text-secondary);">/ ${wali.total_students} Siswa Hadir</span>
                                    </div>
                                    <div class="student-rekap-bar-bg" style="margin-bottom:6px; height:6px;">
                                        <div class="student-rekap-bar-fill" style="width: ${wali.attendance_rate}%; background-color: ${color};"></div>
                                    </div>
                                    <div style="font-size:0.75rem; font-weight:700; color:${color};">${wali.attendance_rate}% Kehadiran</div>
                                </div>
                                <div style="font-size:2.5rem; opacity:0.85; margin-left:16px; z-index:2; filter:hue-rotate(200deg);">📋</div>
                            </div>
                        `;
                    }

                    // Render Schedule or Non-KBM Activity Box
                    const scheduleContainer = $('#homeTodaySchedule');
                    if (scheduleContainer) {
                        if (hasMapel) {
                            if (isWali) {
                                // Add button for Wali Kelas journal entry
                                const waliActionHtml = `
                                    <div style="margin-bottom:12px;">
                                        <button class="btn btn-sm" onclick="GuruApp.openKegiatanModal('wali_kelas')" style="width:100%; background:#eff6ff; color:#2563eb; border:1.5px dashed #93c5fd; border-radius:12px; padding:10px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                            <span>+ Isi Jurnal Guru Wali (${escapeHtml(Auth.user.wali_kelas.nama_kelas)})</span>
                                        </button>
                                    </div>
                                `;
                                scheduleContainer.innerHTML = waliActionHtml + '<div id="homeScheduleSlotsWrap"></div>';
                                this.renderScheduleSlots('#homeScheduleSlotsWrap', schedules, true);
                            } else {
                                this.renderScheduleSlots(scheduleContainer, schedules, true);
                            }
                        } else {
                            // Non-KBM teacher: direct activity box
                            scheduleContainer.innerHTML = `
                                <div class="non-kbm-card" style="background:white; border-radius:18px; padding:18px; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9;">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                                        <div>
                                            <div style="font-family:var(--font-heading); font-size:0.95rem; font-weight:800; color:var(--text-primary);">Kegiatan Hari Ini</div>
                                            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">Catat ringkasan aktivitas / tugas Anda hari ini</div>
                                        </div>
                                        <span class="badge badge-primary" style="font-size:0.7rem;">Non-KBM</span>
                                    </div>
                                    <button class="btn btn-primary btn-block" onclick="GuruApp.openKegiatanModal('non_kbm')" style="display:flex; align-items:center; justify-content:center; gap:8px; border-radius:12px; padding:12px; font-weight:700;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        <span>+ Tulis Catatan Kegiatan</span>
                                    </button>
                                </div>
                            `;
                        }
                    }

                    // Render Recent Journals
                    const recentContainer = $('#homeRecentJournals');
                    if (recentContainer) {
                        if (recents.length === 0) {
                            recentContainer.innerHTML = '<div class="text-center text-muted text-sm py-3" style="background:white; border-radius:16px; border:1.5px solid #f1f5f9; padding:20px;">Belum ada jurnal yang diisi.</div>';
                        } else {
                            recentContainer.innerHTML = recents.map(r => {
                                let cardTitle = escapeHtml(r.nama_mapel || 'Jurnal Kegiatan');
                                let cardBadge = `Jam ke-${escapeHtml(r.jam_ke || '-')}`;
                                let cardBadgeClass = 'badge-primary';
                                let cardSubtitle = `${formatTanggal(r.tanggal)} — Kelas ${escapeHtml(r.nama_kelas || '-')}`;
                                let noteSnippet = r.tujuan_pembelajaran ? `<strong>TP:</strong> ${escapeHtml(r.tujuan_pembelajaran)}` : (r.catatan ? escapeHtml(r.catatan) : '');

                                if (r.jenis_jurnal === 'non_kbm') {
                                    cardTitle = 'Jurnal Kegiatan Guru';
                                    cardBadge = 'Non-KBM';
                                    cardBadgeClass = 'badge-info';
                                    cardSubtitle = `${formatTanggal(r.tanggal)} — Tenaga Pendidik`;
                                    noteSnippet = escapeHtml(r.catatan || 'Tidak ada catatan.');
                                } else if (r.jenis_jurnal === 'wali_kelas') {
                                    cardTitle = 'Jurnal Guru Wali';
                                    cardBadge = `Kelas ${escapeHtml(r.nama_kelas || '')}`;
                                    cardBadgeClass = 'badge-warning';
                                    cardSubtitle = `${formatTanggal(r.tanggal)} — Aktivitas Wali Kelas`;
                                    noteSnippet = escapeHtml(r.catatan || 'Tidak ada catatan.');
                                }

                                return `
                                    <div class="recent-jurnal-card" onclick="GuruApp.viewJurnal(${r.id})" style="background:white; border-radius:16px; padding:14px 16px; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; margin-bottom:10px; display:flex; flex-direction:column; gap:8px; cursor:pointer; position:relative; transition:all 0.2s ease;">
                                        <div style="display:flex; justify-content:space-between; align-items:start;">
                                            <div style="font-family:var(--font-heading); font-size:0.875rem; font-weight:800; color:var(--text-primary);">${cardTitle}</div>
                                            <span class="badge ${cardBadgeClass}">${cardBadge}</span>
                                        </div>
                                        <div style="font-size:0.75rem; color:var(--text-muted); font-weight:500; display:flex; align-items:center; gap:4px; margin-top:-4px;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                            ${cardSubtitle}
                                        </div>
                                        ${noteSnippet ? `
                                            <div style="background:var(--bg-light); padding:8px 10px; border-radius:8px; font-size:0.75rem; color:var(--text-secondary); white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">
                                                ${noteSnippet}
                                            </div>
                                        ` : ''}
                                    </div>
                                `;
                            }).join('');
                        }
                    }
                }
            } catch(e) {
                console.error(e);
                $('#homeTodaySchedule').innerHTML = '<div class="text-center text-danger text-sm py-3">Gagal memuat dashboard.</div>';
            }
        },

        renderScheduleSlots(containerSel, jadwal, showJurnalBtn = false) {
            const container = typeof containerSel === 'string' ? $(containerSel) : containerSel;
            if (!container) return;

            if (!jadwal || jadwal.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <div class="empty-state-title">Tidak Ada Jadwal</div>
                        <div class="empty-state-desc">Tidak ada jadwal mengajar untuk hari ini.</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = jadwal.map(j => `
                <div class="schedule-slot ${j.jurnal_filled ? 'filled' : ''}" data-jadwal='${JSON.stringify(j).replace(/'/g, "&#39;")}' onclick="GuruApp.onScheduleSlotClick(this)">
                    <div class="schedule-slot-time">
                        <div class="schedule-slot-jam">${escapeHtml(String(j.jam_ke))}</div>
                        <div class="schedule-slot-label">Jam ke</div>
                    </div>
                    <div class="schedule-slot-info">
                        <div class="schedule-slot-mapel">${escapeHtml(j.nama_mapel)}</div>
                        <div class="schedule-slot-kelas">${escapeHtml(j.nama_kelas)}</div>
                    </div>
                    <div class="schedule-slot-status">
                        ${j.jurnal_filled
                            ? '<span class="badge badge-success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Sudah</span>'
                            : '<span class="badge badge-warning"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Isi</span>'
                        }
                    </div>
                </div>
            `).join('');
        },

        // --- JADWAL (WEEKLY) ---
        async renderJadwal() {
            const content = $('#appContent');
            const hariIni = getHariIni();

            content.innerHTML = `
                <div class="page-enter">
                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Jadwal Mingguan
                    </div>

                    <div class="schedule-day-tabs" id="dayTabs">
                        ${HARI_ORDER.map(h => `
                            <button class="day-tab ${h === hariIni ? 'today active' : ''}" data-hari="${h}" onclick="GuruApp.switchDay('${h}')">${h}</button>
                        `).join('')}
                    </div>

                    <div id="weeklyScheduleContent">
                        <div class="skeleton skeleton-card"></div>
                        <div class="skeleton skeleton-card"></div>
                        <div class="skeleton skeleton-card"></div>
                    </div>
                </div>
            `;

            try {
                const res = await API.get('api/jadwal.php?action=weekly');
                if (res.success) {
                    this._weeklyData = res.data.jadwal || {};
                    this.renderDaySchedule(hariIni);
                }
            } catch(e) {
                $('#weeklyScheduleContent').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                        <div class="empty-state-title">Gagal Memuat</div>
                        <div class="empty-state-desc">Tidak dapat memuat jadwal.</div>
                    </div>
                `;
            }
        },

        renderDaySchedule(hari) {
            const data = this._weeklyData || {};
            const jadwal = data[hari] || [];
            const container = $('#weeklyScheduleContent');

            // Update tab active state
            $$('.day-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.hari === hari);
            });

            if (jadwal.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                        <div class="empty-state-title">Tidak Ada Jadwal</div>
                        <div class="empty-state-desc">Hari ${hari} tidak ada jadwal mengajar.</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = jadwal.map(j => `
                <div class="schedule-slot">
                    <div class="schedule-slot-time">
                        <div class="schedule-slot-jam">${escapeHtml(String(j.jam_ke))}</div>
                        <div class="schedule-slot-label">Jam ke</div>
                    </div>
                    <div class="schedule-slot-info">
                        <div class="schedule-slot-mapel">${escapeHtml(j.nama_mapel)}</div>
                        <div class="schedule-slot-kelas">${escapeHtml(j.nama_kelas)}</div>
                    </div>
            `).join('');
        },

        // --- JURNAL FORM ---
        async renderJurnal(editId = null) {
            const content = $('#appContent');
            const tanggalIni = getTanggalIni();
            const hasMapel = !!Auth.user?.has_mapel;
            const isWali = !!Auth.user?.wali_kelas;

            if (!hasMapel) {
                // Non-KBM Teacher: Simplified Journal Form (Pilih Tanggal, Catatan Kegiatan, Simpan)
                content.innerHTML = `
                    <div class="page-enter">
                        <div class="section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Jurnal Kegiatan Harian
                        </div>
                        <p class="section-subtitle">Catat ringkasan aktivitas / tugas kerja Anda hari ini</p>

                        <div class="form-info-row mb-2">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>Tanggal: <strong>${formatTanggal(tanggalIni)}</strong></span>
                            <input type="hidden" id="jurnalDate" value="${tanggalIni}">
                        </div>

                        <div class="guru-card mb-3" style="background:white; border-radius:18px; padding:18px; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9;">
                            <div class="form-group">
                                <label class="form-label" style="font-weight:700; color:var(--text-primary);">Catatan Kegiatan</label>
                                <textarea class="form-textarea" id="directKegiatanCatatan" placeholder="Tuliskan uraian atau catatan kegiatan kerja Anda hari ini (misal bimbingan konseling, pembinaan, piket sekolah, administrasi, dll)..." style="min-height:120px;"></textarea>
                            </div>
                            <button class="btn btn-primary btn-block" id="directKegiatanSaveBtn" onclick="GuruApp.saveDirectKegiatan('non_kbm')" style="font-weight:700; padding:12px; border-radius:12px;">
                                <span class="btn-label">Simpan Kegiatan</span>
                            </button>
                        </div>

                        <div class="section-title mt-3">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Kegiatan Hari Ini
                        </div>
                        <div id="directTodayKegiatanList">
                            <div class="skeleton skeleton-card" style="height:70px; margin-bottom:8px;"></div>
                        </div>
                    </div>
                `;

                this.loadDirectTodayKegiatan('non_kbm');
                return;
            }

            // KBM Teacher with Teaching Schedule
            content.innerHTML = `
                <div class="page-enter">
                    ${isWali ? `
                        <div class="wali-journal-banner" onclick="GuruApp.openKegiatanModal('wali_kelas')" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1.5px solid #bfdbfe; border-radius: 16px; padding: 14px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; box-shadow: var(--shadow-sm); transition:transform 0.2s ease;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:38px; height:38px; border-radius:10px; background:#2563eb; color:white; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                </div>
                                <div>
                                    <div style="font-family:var(--font-heading); font-size:0.875rem; font-weight:800; color:#1e40af;">Jurnal Guru Wali (${escapeHtml(Auth.user.wali_kelas.nama_kelas)})</div>
                                    <div style="font-size:0.75rem; color:#3b82f6; font-weight:600;">Klik untuk catat kegiatan & pembinaan kelas</div>
                                </div>
                            </div>
                            <span class="btn btn-sm btn-primary" style="pointer-events:none; font-size:0.75rem; padding:6px 12px; border-radius:8px;">+ Isi</span>
                        </div>
                    ` : ''}

                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Jurnal Mengajar
                    </div>
                    <p class="section-subtitle">Pilih jadwal untuk mengisi jurnal hari ini</p>

                    <div class="form-info-row mb-2">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span>Jurnal Hari Ini: <strong>${formatTanggal(tanggalIni)}</strong></span>
                        <input type="hidden" id="jurnalDate" value="${tanggalIni}">
                    </div>

                    <div id="jurnalScheduleList">
                        <div class="skeleton skeleton-card"></div>
                        <div class="skeleton skeleton-card"></div>
                    </div>
                </div>
            `;

            this.loadJurnalSchedule();
        },

        async loadDirectTodayKegiatan(jenis = 'non_kbm') {
            const container = $('#directTodayKegiatanList');
            if (!container) return;
            const tanggalIni = getTanggalIni();

            try {
                const res = await API.get(`api/jurnal.php?action=list&tanggal=${tanggalIni}&jenis_jurnal=${jenis}`);
                if (res.success) {
                    const list = res.data || [];
                    if (list.length === 0) {
                        container.innerHTML = '<div class="text-center text-muted text-sm py-3" style="background:white; border-radius:16px; border:1.5px solid #f1f5f9; padding:20px;">Belum ada kegiatan yang dicatat hari ini.</div>';
                    } else {
                        container.innerHTML = list.map(item => `
                            <div class="jurnal-item" onclick="GuruApp.viewJurnal(${item.id})">
                                <div class="jurnal-item-header">
                                    <div class="jurnal-item-mapel">${item.jenis_jurnal === 'wali_kelas' ? 'Jurnal Guru Wali' : 'Jurnal Kegiatan'}</div>
                                    <span class="badge ${item.jenis_jurnal === 'wali_kelas' ? 'badge-warning' : 'badge-info'}">${item.jenis_jurnal === 'wali_kelas' ? 'Guru Wali' : 'Non-KBM'}</span>
                                </div>
                                <div class="jurnal-item-tp" style="margin-top:6px; font-size:0.85rem; color:var(--text-primary); line-height:1.5;">${escapeHtml(item.catatan || '-')}</div>
                                <div class="jurnal-item-footer" style="margin-top:10px;">
                                    <div class="jurnal-item-date">${formatTanggal(item.tanggal)}</div>
                                    <div class="jurnal-item-actions">
                                        <button onclick="event.stopPropagation(); GuruApp.editJurnal(${item.id})" title="Edit">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <button class="danger" onclick="event.stopPropagation(); GuruApp.deleteJurnal(${item.id})" title="Hapus">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                    }
                }
            } catch(e) {
                container.innerHTML = '<div class="text-center text-danger text-sm py-3">Gagal memuat catatan kegiatan.</div>';
            }
        },

        async saveDirectKegiatan(jenis = 'non_kbm') {
            const btn = $('#directKegiatanSaveBtn');
            const textarea = $('#directKegiatanCatatan');
            const catatan = textarea?.value?.trim() || '';

            if (!catatan) {
                Toast.show('Catatan kegiatan wajib diisi.', 'warning');
                if (textarea) textarea.focus();
                return;
            }

            if (btn) btn.classList.add('loading');

            try {
                const res = await API.post('api/jurnal.php?action=create', {
                    jenis_jurnal: jenis,
                    tanggal: getTanggalIni(),
                    catatan: catatan
                });

                if (res.success) {
                    Toast.show(res.message || 'Jurnal kegiatan berhasil disimpan!', 'success');
                    if (textarea) textarea.value = '';
                    this.loadDirectTodayKegiatan(jenis);
                } else {
                    Toast.show(res.message || 'Gagal menyimpan.', 'error');
                }
            } catch(e) {
                Toast.show('Tidak dapat terhubung ke server.', 'error');
            } finally {
                if (btn) btn.classList.remove('loading');
            }
        },

        // --- KEGIATAN / WALI KELAS MODAL FORM ---
        async openKegiatanModal(jenis = 'non_kbm', existingId = null) {
            let existing = null;
            if (existingId) {
                try {
                    const res = await API.get(`api/jurnal.php?action=get&id=${existingId}`);
                    if (res.success) existing = res.data;
                } catch(e) {}
            }

            const tanggal = getTanggalIni();
            const isWali = jenis === 'wali_kelas';
            const title = existing ? (isWali ? 'Edit Jurnal Guru Wali' : 'Edit Jurnal Kegiatan') : (isWali ? 'Isi Jurnal Guru Wali' : 'Isi Jurnal Kegiatan');
            const placeholder = isWali 
                ? 'Tuliskan catatan pembinaan siswa, koordinasi wali murid, atau kejadian di kelas hari ini...'
                : 'Tuliskan uraian kegiatan harian kerja Anda hari ini...';

            const overlay = document.createElement('div');
            overlay.className = 'guru-modal-overlay';
            overlay.id = 'kegiatanModal';
            overlay.innerHTML = `
                <div class="guru-modal">
                    <div class="guru-modal-header">
                        <h3>${title}</h3>
                        <button class="guru-modal-close" onclick="GuruApp.closeModal('kegiatanModal')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="guru-modal-body">
                        <div class="form-info-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>Tanggal: <strong>${formatTanggal(tanggal)}</strong></span>
                        </div>
                        ${isWali && Auth.user?.wali_kelas ? `
                            <div class="form-info-row">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <span>Wali Kelas: <strong>${escapeHtml(Auth.user.wali_kelas.nama_kelas)}</strong></span>
                            </div>
                        ` : ''}

                        <div class="form-group mt-2">
                            <label class="form-label">Catatan Kegiatan</label>
                            <textarea class="form-textarea" id="kegiatanCatatanText" placeholder="${placeholder}" style="min-height:120px;">${escapeHtml(existing?.catatan || '')}</textarea>
                        </div>
                    </div>
                    <div class="guru-modal-footer">
                        <button class="btn btn-ghost" onclick="GuruApp.closeModal('kegiatanModal')">Batal</button>
                        <button class="btn btn-primary" id="kegiatanSaveBtn" onclick="GuruApp.saveKegiatanJurnal('${jenis}', ${existing ? existing.id : 0})">
                            <span class="btn-label">${existing ? 'Perbarui' : 'Simpan'}</span>
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) GuruApp.closeModal('kegiatanModal');
            });
        },

        async saveKegiatanJurnal(jenis = 'non_kbm', existingId = 0) {
            const btn = $('#kegiatanSaveBtn');
            const textarea = $('#kegiatanCatatanText');
            const catatan = textarea?.value?.trim() || '';

            if (!catatan) {
                Toast.show('Catatan kegiatan wajib diisi.', 'warning');
                if (textarea) textarea.focus();
                return;
            }

            if (btn) btn.classList.add('loading');

            const payload = {
                jenis_jurnal: jenis,
                tanggal: getTanggalIni(),
                catatan: catatan
            };
            if (existingId > 0) payload.id = existingId;

            try {
                const action = existingId > 0 ? 'update' : 'create';
                const res = await API.post(`api/jurnal.php?action=${action}`, payload);

                if (res.success) {
                    Toast.show(res.message || 'Jurnal kegiatan berhasil disimpan!', 'success');
                    GuruApp.closeModal('kegiatanModal');
                    if (Router.currentPage === 'home') Pages.renderHome();
                    else if (Router.currentPage === 'jurnal') Pages.renderJurnal();
                    else if (Router.currentPage === 'riwayat') Pages.loadRiwayat();
                    else if (Router.currentPage === 'jurnal-kelas') Pages.loadWaliJurnal();
                } else {
                    Toast.show(res.message || 'Gagal menyimpan jurnal.', 'error');
                }
            } catch(e) {
                Toast.show('Tidak dapat terhubung ke server.', 'error');
            } finally {
                if (btn) btn.classList.remove('loading');
            }
        },

        async loadJurnalSchedule() {
            const tanggal = $('#jurnalDate')?.value || getTanggalIni();
            const container = $('#jurnalScheduleList');
            if (!container) return;

            // Determine the day of the week from the selected date
            const d = new Date(tanggal + 'T00:00:00');
            const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const hari = dayNames[d.getDay()];

            container.innerHTML = `
                <div class="skeleton skeleton-card"></div>
                <div class="skeleton skeleton-card"></div>
            `;

            try {
                const res = await API.get(`api/jadwal.php?action=today&hari=${hari}&tanggal=${tanggal}`);
                if (res.success) {
                    this.renderScheduleSlots(container, res.data.jadwal || [], true);
                }
            } catch(e) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-title">Gagal Memuat</div>
                        <div class="empty-state-desc">Tidak dapat memuat jadwal.</div>
                    </div>
                `;
            }
        },

        // --- JURNAL FORM MODAL (KBM) ---
        async openJurnalForm(jadwalData, existingJurnalId = null) {
            let existing = null;

            // If editing, fetch existing data
            if (existingJurnalId) {
                try {
                    const res = await API.get(`api/jurnal.php?action=get&id=${existingJurnalId}`);
                    if (res.success) existing = res.data;
                } catch(e) {}
            }

            const tanggal = $('#jurnalDate')?.value || getTanggalIni();

            const overlay = document.createElement('div');
            overlay.className = 'guru-modal-overlay';
            overlay.id = 'jurnalModal';
            overlay.innerHTML = `
                <div class="guru-modal">
                    <div class="guru-modal-header">
                        <h3>${existing ? 'Edit Jurnal' : 'Isi Jurnal'}</h3>
                        <button class="guru-modal-close" onclick="GuruApp.closeModal('jurnalModal')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="guru-modal-body">
                        <div class="form-info-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span><strong>${formatTanggal(tanggal)}</strong></span>
                        </div>
                        <div class="form-info-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            <span><strong>${escapeHtml(jadwalData.nama_mapel)}</strong> — ${escapeHtml(jadwalData.nama_kelas)} — Jam ke-${jadwalData.jam_ke}</span>
                        </div>

                        <div class="form-group mt-2">
                            <label class="form-label">Tujuan Pembelajaran</label>
                            <textarea class="form-textarea" id="jurnalTP" placeholder="Tuliskan tujuan pembelajaran...">${escapeHtml(existing?.tujuan_pembelajaran || '')}</textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Indikator TP</label>
                            <textarea class="form-textarea" id="jurnalIPTP" placeholder="Tuliskan indikator tujuan pembelajaran..." style="min-height:80px;">${escapeHtml(existing?.indikator_tp || '')}</textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-textarea" id="jurnalCatatan" placeholder="Catatan tambahan (opsional)..." style="min-height:70px;">${escapeHtml(existing?.catatan || '')}</textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <span>Absensi Siswa</span>
                                <span class="text-xs text-muted" id="absentSummary" style="font-weight:700;">Semua Siswa Hadir</span>
                            </label>
                            <div class="student-abs-list" id="jurnalAbsensiSiswa">
                                <div class="skeleton skeleton-card" style="height:50px; margin-bottom:8px;"></div>
                                <div class="skeleton skeleton-card" style="height:50px; margin-bottom:8px;"></div>
                                <div class="skeleton skeleton-card" style="height:50px; margin-bottom:8px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="guru-modal-footer">
                        <button class="btn btn-ghost" onclick="GuruApp.closeModal('jurnalModal')">Batal</button>
                        <button class="btn btn-primary" id="jurnalSaveBtn" onclick="GuruApp.saveJurnal(${existing ? existing.id : 0}, ${jadwalData.kelas_id}, ${jadwalData.mapel_id}, '${jadwalData.jam_ke}', '${tanggal}')">
                            <span class="btn-label">${existing ? 'Perbarui' : 'Simpan'}</span>
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            // Fetch students list
            try {
                const res = await API.get(`api/jurnal.php?action=students&kelas_id=${jadwalData.kelas_id}`);
                const listContainer = $('#jurnalAbsensiSiswa');
                if (res.success && listContainer) {
                    const students = res.data || [];
                    if (students.length === 0) {
                        listContainer.innerHTML = '<div class="text-center text-muted text-sm py-3">Tidak ada data siswa di kelas ini.</div>';
                    } else {
                        // Map existing absensi if editing
                        const existingAbsMap = {};
                        if (existing && existing.absensi) {
                            existing.absensi.forEach(a => {
                                existingAbsMap[a.student_id] = a.status;
                            });
                        }

                        listContainer.innerHTML = students.map(s => {
                            const curStatus = existingAbsMap[s.id] || 'H';
                            const activeClass = curStatus !== 'H' ? 'has-absent' : '';
                            return `
                                <div class="student-abs-row ${activeClass}" data-student-id="${s.id}" id="studRow_${s.id}">
                                    <div class="student-abs-info">
                                        <div class="student-abs-name">${escapeHtml(s.nama)}</div>
                                        <div class="student-abs-nis">NIS: ${escapeHtml(s.nis || '-')}</div>
                                    </div>
                                    <div class="abs-pills" data-student-id="${s.id}">
                                        <span class="abs-pill ${curStatus === 'H' ? 'active' : ''}" data-status="H">H</span>
                                        <span class="abs-pill ${curStatus === 'S' ? 'active' : ''}" data-status="S">S</span>
                                        <span class="abs-pill ${curStatus === 'I' ? 'active' : ''}" data-status="I">I</span>
                                        <span class="abs-pill ${curStatus === 'A' ? 'active' : ''}" data-status="A">A</span>
                                    </div>
                                </div>
                            `;
                        }).join('');

                        // Bind pill clicks
                        listContainer.querySelectorAll('.abs-pill').forEach(pill => {
                            pill.addEventListener('click', function() {
                                const parent = this.parentElement;
                                const studentId = parent.dataset.studentId;
                                const status = this.dataset.status;
                                
                                parent.querySelectorAll('.abs-pill').forEach(p => p.classList.remove('active'));
                                this.classList.add('active');
                                
                                const row = $(`#studRow_${studentId}`);
                                if (row) {
                                    row.classList.toggle('has-absent', status !== 'H');
                                }
                                
                                updateAbsentSummary();
                            });
                        });
                        
                        updateAbsentSummary();
                    }
                } else if (listContainer) {
                    listContainer.innerHTML = '<div class="text-center text-danger text-sm py-3">Gagal memuat siswa.</div>';
                }
            } catch(e) {
                const listContainer = $('#jurnalAbsensiSiswa');
                if (listContainer) listContainer.innerHTML = '<div class="text-center text-danger text-sm py-3">Gagal memuat siswa.</div>';
            }

            function updateAbsentSummary() {
                const rows = $$('.student-abs-row');
                let sakit = 0, izin = 0, alpa = 0;
                rows.forEach(r => {
                    const activePill = r.querySelector('.abs-pill.active');
                    if (activePill) {
                        const status = activePill.dataset.status;
                        if (status === 'S') sakit++;
                        else if (status === 'I') izin++;
                        else if (status === 'A') alpa++;
                    }
                });
                const summaryEl = $('#absentSummary');
                if (summaryEl) {
                    if (sakit === 0 && izin === 0 && alpa === 0) {
                        summaryEl.textContent = 'Semua Siswa Hadir';
                        summaryEl.style.color = '#10b981';
                    } else {
                        const parts = [];
                        if (sakit > 0) parts.push(`Sakit: ${sakit}`);
                        if (izin > 0) parts.push(`Izin: ${izin}`);
                        if (alpa > 0) parts.push(`Alpa: ${alpa}`);
                        summaryEl.textContent = parts.join(', ');
                        summaryEl.style.color = '#ef4444';
                    }
                }
            }

            // Clicking overlay closes modal
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) GuruApp.closeModal('jurnalModal');
            });
        },

        async saveJurnal(id, kelas_id, mapel_id, jam_ke, tanggal) {
            const btn = $('#jurnalSaveBtn');
            if (!btn) return;
            btn.classList.add('loading');

            // Gather student attendance data
            const absensi = [];
            $$('.student-abs-row').forEach(row => {
                const studentId = parseInt(row.dataset.studentId);
                const activePill = row.querySelector('.abs-pill.active');
                if (studentId && activePill) {
                    absensi.push({
                        student_id: studentId,
                        status: activePill.dataset.status
                    });
                }
            });

            const payload = {
                jenis_jurnal: 'kbm',
                kelas_id,
                mapel_id,
                jam_ke: String(jam_ke),
                tanggal,
                tujuan_pembelajaran: $('#jurnalTP')?.value || '',
                indikator_tp: $('#jurnalIPTP')?.value || '',
                catatan: $('#jurnalCatatan')?.value || '',
                absensi: absensi
            };

            if (id > 0) payload.id = id;

            try {
                const action = id > 0 ? 'update' : 'create';
                const res = await API.post(`api/jurnal.php?action=${action}`, payload);

                if (res.success) {
                    Toast.show(res.message || 'Jurnal berhasil disimpan!', 'success');
                    GuruApp.closeModal('jurnalModal');
                    // Refresh current page
                    if (Router.currentPage === 'home') Pages.renderHome();
                    else if (Router.currentPage === 'jurnal') Pages.loadJurnalSchedule();
                    else if (Router.currentPage === 'riwayat') Pages.loadRiwayat();
                } else {
                    Toast.show(res.message || 'Gagal menyimpan jurnal.', 'error');
                }
            } catch(e) {
                Toast.show('Tidak dapat terhubung ke server.', 'error');
            } finally {
                btn.classList.remove('loading');
            }
        },

        // --- RIWAYAT ---
        async renderRiwayat() {
            const content = $('#appContent');
            const tanggalIni = getTanggalIni();

            // Get first day of current month
            const d = new Date();
            const firstDay = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-01';

            content.innerHTML = `
                <div class="page-enter">
                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Riwayat Jurnal
                    </div>

                    <div class="date-picker-row">
                        <input type="date" id="riwayatDateFrom" value="${firstDay}">
                        <span class="text-muted text-sm">s/d</span>
                        <input type="date" id="riwayatDateTo" value="${tanggalIni}">
                        <button class="btn btn-sm btn-primary" onclick="GuruApp.loadRiwayat()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </button>
                    </div>

                    <div id="riwayatList">
                        <div class="skeleton skeleton-card"></div>
                        <div class="skeleton skeleton-card"></div>
                        <div class="skeleton skeleton-card"></div>
                    </div>
                </div>
            `;

            this.loadRiwayat();
        },

        async loadRiwayat() {
            const from = $('#riwayatDateFrom')?.value || getTanggalIni();
            const to = $('#riwayatDateTo')?.value || getTanggalIni();
            const container = $('#riwayatList');
            if (!container) return;

            container.innerHTML = `
                <div class="skeleton skeleton-card"></div>
                <div class="skeleton skeleton-card"></div>
            `;

            try {
                const res = await API.get(`api/jurnal.php?action=list&tanggal=${from}&tanggal_akhir=${to}`);
                if (res.success) {
                    const data = res.data || [];
                    if (data.length === 0) {
                        container.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                                <div class="empty-state-title">Belum Ada Jurnal</div>
                                <div class="empty-state-desc">Tidak ada jurnal pada rentang tanggal ini.</div>
                            </div>
                        `;
                        return;
                    }

                    container.innerHTML = `
                        <p class="text-sm text-muted mb-2">${data.length} jurnal ditemukan</p>
                        ${data.map(j => {
                            let title = escapeHtml(j.nama_mapel || 'Jurnal');
                            let badgeText = `Jam ke-${escapeHtml(j.jam_ke || '-')}`;
                            let badgeClass = 'badge-primary';
                            let subtitle = `Kelas ${escapeHtml(j.nama_kelas || '')}`;
                            let snippet = j.tujuan_pembelajaran ? `<strong>TP:</strong> ${escapeHtml(j.tujuan_pembelajaran)}` : (j.catatan ? escapeHtml(j.catatan) : '');

                            if (j.jenis_jurnal === 'non_kbm') {
                                title = 'Jurnal Kegiatan Guru';
                                badgeText = 'Non-KBM';
                                badgeClass = 'badge-info';
                                subtitle = 'Tenaga Pendidik / Non-Mapel';
                                snippet = escapeHtml(j.catatan || 'Tidak ada catatan.');
                            } else if (j.jenis_jurnal === 'wali_kelas') {
                                title = 'Jurnal Guru Wali';
                                badgeText = `Kelas ${escapeHtml(j.nama_kelas || '')}`;
                                badgeClass = 'badge-warning';
                                subtitle = 'Aktivitas & Pembinaan Siswa';
                                snippet = escapeHtml(j.catatan || 'Tidak ada catatan.');
                            }

                            return `
                                <div class="jurnal-item" onclick="GuruApp.viewJurnal(${j.id})">
                                    <div class="jurnal-item-header">
                                        <div class="jurnal-item-mapel">${title}</div>
                                        <span class="badge ${badgeClass}" style="font-size:0.7rem;">${badgeText}</span>
                                    </div>
                                    <div class="jurnal-item-kelas" style="font-size:0.75rem; color:var(--text-secondary); margin-top:-2px;">${subtitle}</div>
                                    ${snippet ? `<div class="jurnal-item-tp" style="margin-top:6px; font-size:0.8rem; color:var(--text-primary); line-height:1.4;">${snippet}</div>` : ''}
                                    <div class="jurnal-item-footer" style="margin-top:10px;">
                                        <div class="jurnal-item-date">${formatTanggal(j.tanggal)}</div>
                                        <div class="jurnal-item-actions">
                                            ${j.tanggal === getTanggalIni() ? `
                                                <button onclick="event.stopPropagation(); GuruApp.editJurnal(${j.id})" title="Edit">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>
                                                <button class="danger" onclick="event.stopPropagation(); GuruApp.deleteJurnal(${j.id})" title="Hapus">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            ` : `
                                                <span class="badge badge-success">Selesai</span>
                                            `}
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    `;
                }
            } catch(e) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-title">Gagal Memuat</div>
                        <div class="empty-state-desc">Tidak dapat memuat riwayat jurnal.</div>
                    </div>
                `;
            }
        },

        // --- PROFIL ---
        renderProfil() {
            const content = $('#appContent');
            const user = Auth.user || {};
            const school = Auth.school || {};

            content.innerHTML = `
                <div class="page-enter">
                    <div class="profile-card">
                        <div class="profile-avatar">
                            ${user.avatar
                                ? `<img src="${BASE_URL}${user.avatar}" alt="Avatar" onerror="this.parentElement.textContent='${getInitials(user.nama_lengkap)}';">`
                                : getInitials(user.nama_lengkap)
                            }
                        </div>
                        <div class="profile-name">${escapeHtml(user.nama_lengkap || '-')}</div>
                        <div class="profile-role">Guru</div>
                    </div>

                    <div class="guru-card">
                        <div class="guru-card-body">
                            <div class="profile-info-list">
                                <div class="profile-info-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <div>
                                        <div class="info-label">Username</div>
                                        <div class="info-value">${escapeHtml(user.username || '-')}</div>
                                    </div>
                                </div>
                                <div class="profile-info-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                    <div>
                                        <div class="info-label">Sekolah</div>
                                        <div class="info-value">${escapeHtml(school.nama || '-')}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="logout-section">
                        <button class="btn btn-danger btn-block" onclick="GuruApp.confirmLogout()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            <span class="btn-label">Keluar</span>
                        </button>
                    </div>
                </div>
            `;
        },

        // --- VIEW JURNAL DETAIL ---
        async viewJurnal(id) {
            try {
                const res = await API.get(`api/jurnal.php?action=get&id=${id}`);
                if (!res.success) {
                    Toast.show(res.message || 'Jurnal tidak ditemukan.', 'error');
                    return;
                }

                const j = res.data;
                const overlay = document.createElement('div');
                overlay.className = 'guru-modal-overlay';
                overlay.id = 'viewJurnalModal';

                let headerMetaHtml = '';
                let bodyContentHtml = '';
                let modalTitle = 'Detail Jurnal Mengajar';

                if (j.jenis_jurnal === 'non_kbm') {
                    modalTitle = 'Detail Jurnal Kegiatan';
                    headerMetaHtml = `
                        <div class="form-info-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span><strong>${formatTanggal(j.tanggal)}</strong> — Jurnal Kegiatan Guru</span>
                        </div>
                    `;
                    bodyContentHtml = `
                        <div class="form-group mt-2">
                            <label class="form-label">Catatan Kegiatan</label>
                            <div style="padding:12px 14px;background:var(--bg-light);border-radius:var(--radius-md);font-size:0.875rem;line-height:1.6;white-space:pre-wrap;">${escapeHtml(j.catatan || '-')}</div>
                        </div>
                    `;
                } else if (j.jenis_jurnal === 'wali_kelas') {
                    modalTitle = 'Detail Jurnal Guru Wali';
                    headerMetaHtml = `
                        <div class="form-info-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span><strong>${formatTanggal(j.tanggal)}</strong> — Jurnal Guru Wali</span>
                        </div>
                        ${j.nama_kelas ? `
                            <div class="form-info-row">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <span>Kelas: <strong>${escapeHtml(j.nama_kelas)}</strong></span>
                            </div>
                        ` : ''}
                    `;
                    bodyContentHtml = `
                        <div class="form-group mt-2">
                            <label class="form-label">Catatan Kegiatan Guru Wali</label>
                            <div style="padding:12px 14px;background:var(--bg-light);border-radius:var(--radius-md);font-size:0.875rem;line-height:1.6;white-space:pre-wrap;">${escapeHtml(j.catatan || '-')}</div>
                        </div>
                    `;
                } else {
                    headerMetaHtml = `
                        <div class="form-info-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span><strong>${formatTanggal(j.tanggal)}</strong> — Jam ke-${escapeHtml(j.jam_ke || '-')}</span>
                        </div>
                        <div class="form-info-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            <span><strong>${escapeHtml(j.nama_mapel || '')}</strong> — ${escapeHtml(j.nama_kelas || '')}</span>
                        </div>
                    `;
                    bodyContentHtml = `
                        ${j.tujuan_pembelajaran ? `
                            <div class="form-group mt-2">
                                <label class="form-label">Tujuan Pembelajaran</label>
                                <div style="padding:10px 14px;background:var(--bg-light);border-radius:var(--radius-md);font-size:0.875rem;line-height:1.6;">${escapeHtml(j.tujuan_pembelajaran)}</div>
                            </div>
                        ` : ''}

                        ${j.indikator_tp ? `
                            <div class="form-group">
                                <label class="form-label">Indikator TP</label>
                                <div style="padding:10px 14px;background:var(--bg-light);border-radius:var(--radius-md);font-size:0.875rem;line-height:1.6;">${escapeHtml(j.indikator_tp)}</div>
                            </div>
                        ` : ''}

                        ${j.catatan ? `
                            <div class="form-group">
                                <label class="form-label">Catatan</label>
                                <div style="padding:10px 14px;background:var(--bg-light);border-radius:var(--radius-md);font-size:0.875rem;line-height:1.6;">${escapeHtml(j.catatan)}</div>
                            </div>
                        ` : ''}

                        ${j.siswa_tidak_hadir ? `
                            <div class="form-group">
                                <label class="form-label">Siswa Tidak Hadir</label>
                                <div style="padding:10px 14px;background:var(--danger-light);border-radius:var(--radius-md);font-size:0.875rem;line-height:1.6;color:var(--danger);">${escapeHtml(j.siswa_tidak_hadir)}</div>
                            </div>
                        ` : ''}
                    `;
                }

                overlay.innerHTML = `
                    <div class="guru-modal">
                        <div class="guru-modal-header">
                            <h3>${modalTitle}</h3>
                            <button class="guru-modal-close" onclick="GuruApp.closeModal('viewJurnalModal')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="guru-modal-body">
                            ${headerMetaHtml}
                            ${bodyContentHtml}
                        </div>
                        <div class="guru-modal-footer">
                            <button class="btn btn-ghost" onclick="GuruApp.closeModal('viewJurnalModal')">Tutup</button>
                            ${j.tanggal === getTanggalIni() ? `
                                <button class="btn btn-primary" onclick="GuruApp.closeModal('viewJurnalModal'); GuruApp.editJurnal(${j.id})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    <span class="btn-label">Edit</span>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;

                document.body.appendChild(overlay);
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) GuruApp.closeModal('viewJurnalModal');
                });
            } catch(e) {
                Toast.show('Gagal memuat jurnal.', 'error');
            }
        },

        // --- WALI KELAS MONITORING ---
        async renderJurnalKelas() {
            if (!Auth.user || !Auth.user.wali_kelas) {
                Router.navigate('home');
                return;
            }

            const content = $('#appContent');
            const tanggalIni = getTanggalIni();

            // Get first day of current month
            const d = new Date();
            const firstDay = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-01';

            content.innerHTML = `
                <div class="page-enter">
                    <div class="section-title" style="margin-bottom:12px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Jurnal & Rekap Kelas (${escapeHtml(Auth.user.wali_kelas.nama_kelas)})
                    </div>

                    <div class="tabs-segment">
                        <button class="tab-segment-btn active" id="tabJurnalFeed" onclick="GuruApp.switchWaliTab('feed')">Jurnal Harian</button>
                        <button class="tab-segment-btn" id="tabAbsenRekap" onclick="GuruApp.switchWaliTab('rekap')">Rekap Absensi</button>
                    </div>

                    <!-- Tab 1: Jurnal Feed -->
                    <div id="waliFeedWrapper">
                        <div class="date-picker-row">
                            <input type="date" id="waliDateFrom" value="${firstDay}">
                            <span class="text-muted text-sm">s/d</span>
                            <input type="date" id="waliDateTo" value="${tanggalIni}">
                            <button class="btn btn-sm btn-primary" onclick="GuruApp.loadWaliJurnal()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </button>
                        </div>
                        <div id="waliJurnalList">
                            <div class="skeleton skeleton-card" style="height:72px; margin-bottom:8px;"></div>
                            <div class="skeleton skeleton-card" style="height:72px; margin-bottom:8px;"></div>
                        </div>
                    </div>

                    <!-- Tab 2: Rekap Absensi -->
                    <div id="waliRekapWrapper" style="display:none;">
                        <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:16px;">
                            <div class="rekap-search-wrap" style="margin-bottom:0;">
                                <input type="text" class="rekap-search-input" id="rekapSearchInput" placeholder="Cari nama siswa..." oninput="GuruApp.filterWaliRekap(this.value)">
                                <svg class="rekap-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                            </div>
                            
                            <div style="display:flex; gap:8px; align-items:center; background:white; padding:10px 12px; border-radius:12px; border:1px solid #f1f5f9; box-shadow:var(--shadow-sm);">
                                <input type="date" id="printAbsenTanggal" value="${tanggalIni}" style="border:1px solid #cbd5e1; border-radius:8px; padding:8px; font-size:0.8rem; width:135px; flex-shrink:0; font-family:inherit; outline:none;">
                                <button class="btn btn-sm btn-primary" onclick="GuruApp.printDailyAbsen()" style="padding:8px 14px; border-radius:8px; font-size:0.75rem; font-weight:700; display:flex; align-items:center; gap:6px; white-space:nowrap; flex:1; justify-content:center;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                    Cetak Absensi Harian
                                </button>
                            </div>
                        </div>
                        <div id="waliRekapList">
                            <div class="skeleton skeleton-card" style="height:90px; margin-bottom:10px;"></div>
                            <div class="skeleton skeleton-card" style="height:90px; margin-bottom:10px;"></div>
                        </div>
                    </div>
                </div>
            `;

            this.loadWaliJurnal();
        },

        switchWaliTab(tab) {
            const btnFeed = $('#tabJurnalFeed');
            const btnRekap = $('#tabAbsenRekap');
            const wrapFeed = $('#waliFeedWrapper');
            const wrapRekap = $('#waliRekapWrapper');
            
            if (tab === 'feed') {
                btnFeed?.classList.add('active');
                btnRekap?.classList.remove('active');
                if (wrapFeed) wrapFeed.style.display = 'block';
                if (wrapRekap) wrapRekap.style.display = 'none';
                this.loadWaliJurnal();
            } else {
                btnFeed?.classList.remove('active');
                btnRekap?.classList.add('active');
                if (wrapFeed) wrapFeed.style.display = 'none';
                if (wrapRekap) wrapRekap.style.display = 'block';
                this.loadWaliRekap();
            }
        },

        async loadWaliJurnal() {
            const from = $('#waliDateFrom')?.value || getTanggalIni();
            const to = $('#waliDateTo')?.value || getTanggalIni();
            const container = $('#waliJurnalList');
            if (!container) return;

            container.innerHTML = `
                <div class="skeleton skeleton-card" style="height:72px; margin-bottom:8px;"></div>
                <div class="skeleton skeleton-card" style="height:72px; margin-bottom:8px;"></div>
            `;

            try {
                const res = await API.get(`api/jurnal.php?action=wali_kelas_list&tanggal=${from}&tanggal_akhir=${to}`);
                if (res.success) {
                    const journalsData = res.data.journals || [];
                    if (journalsData.length === 0) {
                        container.innerHTML = `
                            <div class="empty-state" style="padding:40px 20px;">
                                <div class="empty-state-title">Tidak Ada Jurnal</div>
                                <div class="empty-state-desc">Belum ada jurnal yang diisi di kelas Anda untuk periode ini.</div>
                            </div>
                        `;
                    } else {
                        container.innerHTML = journalsData.map(j => {
                            let absentHtml = '';
                            if (j.siswa_tidak_hadir && j.siswa_tidak_hadir !== 'Semua Hadir') {
                                const names = j.siswa_tidak_hadir.split('\n').filter(Boolean);
                                absentHtml = `
                                    <div class="wali-jurnal-absent">
                                        <div class="wali-jurnal-absent-title">Siswa Absen / Tidak Hadir:</div>
                                        <div>
                                            ${names.map(n => `<span class="badge badge-danger" style="margin-right:4px; margin-bottom:4px; display:inline-block;">${escapeHtml(n)}</span>`).join('')}
                                        </div>
                                    </div>
                                `;
                            } else {
                                absentHtml = `
                                    <div class="wali-jurnal-absent">
                                        <div class="wali-jurnal-absent-title">Kehadiran Siswa:</div>
                                        <div>
                                            <span class="badge badge-success">Semua Siswa Hadir ✅</span>
                                        </div>
                                    </div>
                                `;
                            }

                            return `
                                <div class="wali-jurnal-card page-enter" style="border-left-color: var(--primary);" onclick="GuruApp.viewJurnal(${j.id})">
                                    <div class="wali-jurnal-header">
                                        <div class="wali-jurnal-meta">
                                            <div class="wali-jurnal-mapel">${escapeHtml(j.nama_mapel)}</div>
                                            <div class="wali-jurnal-guru">Guru: <strong>${escapeHtml(j.nama_guru || 'Guru')}</strong></div>
                                        </div>
                                        <span class="badge badge-primary">Jam ke-${escapeHtml(j.jam_ke)}</span>
                                    </div>
                                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600; display:flex; align-items:center; gap:4px; margin-top:-4px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                        ${formatTanggal(j.tanggal)}
                                    </div>
                                    ${j.tujuan_pembelajaran ? `
                                        <div class="wali-jurnal-content">
                                            <strong>TP:</strong> ${escapeHtml(j.tujuan_pembelajaran)}
                                        </div>
                                    ` : ''}
                                    ${absentHtml}
                                </div>
                            `;
                        }).join('');
                    }
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-title">Gagal Memuat</div>
                            <div class="empty-state-desc">${escapeHtml(res.message || 'Gagal memuat jurnal kelas.')}</div>
                        </div>
                    `;
                }
            } catch(e) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-title">Gagal Memuat</div>
                        <div class="empty-state-desc">Tidak dapat terhubung ke server.</div>
                    </div>
                `;
            }
        },

        async loadWaliRekap() {
            const container = $('#waliRekapList');
            if (!container) return;

            container.innerHTML = `
                <div class="skeleton skeleton-card" style="height:90px; margin-bottom:10px;"></div>
                <div class="skeleton skeleton-card" style="height:90px; margin-bottom:10px;"></div>
                <div class="skeleton skeleton-card" style="height:90px; margin-bottom:10px;"></div>
            `;

            try {
                const res = await API.get('api/jurnal.php?action=wali_kelas_rekap');
                if (res.success) {
                    const students = res.data.students || [];
                    this._rekapStudents = students;
                    this.renderWaliRekapList(students);
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-title">Gagal Memuat</div>
                            <div class="empty-state-desc">${escapeHtml(res.message || 'Gagal memuat rekap absensi.')}</div>
                        </div>
                    `;
                }
            } catch(e) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-title">Gagal Memuat</div>
                        <div class="empty-state-desc">Tidak dapat terhubung ke server.</div>
                    </div>
                `;
            }
        },

        renderWaliRekapList(students) {
            const container = $('#waliRekapList');
            if (!container) return;

            if (students.length === 0) {
                container.innerHTML = `
                    <div class="empty-state" style="padding:30px 20px;">
                        <div class="empty-state-title">Tidak Ada Siswa</div>
                        <div class="empty-state-desc">Tidak ada data siswa ditemukan untuk kelas ini.</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = students.map(s => {
                const p = s.stats.persentase;
                let color = '#10b981'; // Green
                if (p < 85) color = '#ef4444'; // Red
                else if (p < 95) color = '#f59e0b'; // Orange

                return `
                    <div class="student-rekap-card page-enter">
                        <div class="student-rekap-info">
                            <div class="student-abs-info">
                                <span class="student-rekap-name">${escapeHtml(s.nama)}</span>
                                <span class="student-abs-nis">NIS: ${escapeHtml(s.nis || '-')}</span>
                            </div>
                            <span class="student-rekap-percent" style="color: ${color}">${p}%</span>
                        </div>
                        <div class="student-rekap-bar-bg">
                            <div class="student-rekap-bar-fill" style="width: ${p}%; background-color: ${color};"></div>
                        </div>
                        <div class="student-rekap-details">
                            <span>Hadir <strong>${s.stats.hadir}</strong></span>
                            <span>Sakit <strong style="color:#3b82f6;">${s.stats.sakit}</strong></span>
                            <span>Izin <strong style="color:#f59e0b;">${s.stats.izin}</strong></span>
                            <span>Alpa <strong style="color:#ef4444;">${s.stats.alpha}</strong></span>
                            <span>Total JP <strong>${s.stats.total}</strong></span>
                        </div>
                    </div>
                `;
            }).join('');
        },

        filterWaliRekap(query) {
            if (!this._rekapStudents) return;
            const filtered = this._rekapStudents.filter(s => 
                s.nama.toLowerCase().includes(query.toLowerCase()) || 
                (s.nis && s.nis.includes(query))
            );
            this.renderWaliRekapList(filtered);
        },

        async printDailyAbsen() {
            const tanggal = $('#printAbsenTanggal')?.value || getTanggalIni();
            const loader = Toast.show('Menyiapkan laporan...', 'info');
            
            try {
                const res = await API.get(`api/jurnal.php?action=wali_kelas_daily_absen&tanggal=${tanggal}`);
                Toast.close(loader);
                if (!res.success) {
                    Toast.show(res.message || 'Gagal memuat data.', 'error');
                    return;
                }
                
                const data = res.data;
                const formattedDate = formatTanggal(data.tanggal);
                
                const printWin = window.open('', '_blank');
                if (!printWin) {
                    Toast.show('Pop-up terblokir! Izinkan pop-up untuk mencetak.', 'error');
                    return;
                }
                
                let rowsHtml = '';
                data.students.forEach((s, idx) => {
                    let cellsHtml = '';
                    for (let j = 1; j <= 10; j++) {
                        const val = s.jams[j] || '.';
                        cellsHtml += `<td style="text-align:center; font-family:monospace; font-weight:bold; font-size:10pt;">${val}</td>`;
                    }
                    rowsHtml += `
                        <tr>
                            <td style="text-align:center; font-size:9.5pt;">${idx + 1}</td>
                            <td style="text-align:center; font-size:9.5pt;">${escapeHtml(s.nis || '-')}</td>
                            <td style="font-size:9.5pt;">${escapeHtml(s.nama)}</td>
                            ${cellsHtml}
                        </tr>
                    `;
                });
                
                const htmlContent = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <title>Cetak Laporan Absensi - ${escapeHtml(data.kelas_name)}</title>
                        <style>
                            @page {
                                size: A4 landscape;
                                margin: 15mm;
                            }
                            body {
                                font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
                                margin: 0;
                                padding: 0;
                                color: #1e293b;
                                font-size: 11pt;
                                line-height: 1.4;
                            }
                            .title-block {
                                text-align: center;
                                margin-bottom: 20px;
                            }
                            .title-main {
                                font-size: 14pt;
                                font-weight: 700;
                                letter-spacing: 0.5px;
                                margin-bottom: 4px;
                                text-transform: uppercase;
                            }
                            .title-sub {
                                font-size: 11pt;
                                color: #475569;
                            }
                            table {
                                width: 100%;
                                border-collapse: collapse;
                                margin-top: 15px;
                            }
                            th, td {
                                border: 1px solid #000;
                                padding: 8px 10px;
                                font-size: 9.5pt;
                            }
                            th {
                                background-color: #f1f5f9;
                                font-weight: 700;
                                text-transform: uppercase;
                                font-size: 9pt;
                            }
                            .jam-col {
                                width: 40px;
                            }
                            @media print {
                                button { display: none; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="title-block">
                            <div class="title-main">Daftar Hadir Siswa dalam Pembelajaran</div>
                            <div class="title-sub">Tanggal: <strong>${formattedDate}</strong> &nbsp;|&nbsp; Kelas: <strong>${escapeHtml(data.kelas_name)}</strong></div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width:40px; text-align:center; vertical-align:middle;">No</th>
                                    <th rowspan="2" style="width:100px; text-align:center; vertical-align:middle;">NIS</th>
                                    <th rowspan="2" style="vertical-align:middle; text-align:left;">Nama Siswa</th>
                                    <th colspan="10" style="text-align:center;">Jam Ke-</th>
                                </tr>
                                <tr>
                                    <th class="jam-col" style="text-align:center;">1</th>
                                    <th class="jam-col" style="text-align:center;">2</th>
                                    <th class="jam-col" style="text-align:center;">3</th>
                                    <th class="jam-col" style="text-align:center;">4</th>
                                    <th class="jam-col" style="text-align:center;">5</th>
                                    <th class="jam-col" style="text-align:center;">6</th>
                                    <th class="jam-col" style="text-align:center;">7</th>
                                    <th class="jam-col" style="text-align:center;">8</th>
                                    <th class="jam-col" style="text-align:center;">9</th>
                                    <th class="jam-col" style="text-align:center;">10</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rowsHtml}
                            </tbody>
                        </table>
                        
                        <div style="margin-top:40px; display:flex; justify-content:flex-end;">
                            <div style="text-align:center; width:220px; font-size:10pt;">
                                <div>Mengetahui,</div>
                                <div style="font-weight:700; margin-top:5px;">Wali Kelas</div>
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
                
                printWin.document.open();
                printWin.document.write(htmlContent);
                printWin.document.close();
            } catch(e) {
                Toast.close(loader);
                Toast.show('Gagal menghubungi server.', 'error');
            }
        },

        async editJurnal(id) {
            try {
                const res = await API.get(`api/jurnal.php?action=get&id=${id}`);
                if (res.success) {
                    const j = res.data;
                    if (j.jenis_jurnal === 'non_kbm' || j.jenis_jurnal === 'wali_kelas') {
                        this.openKegiatanModal(j.jenis_jurnal, j.id);
                    } else {
                        this.openJurnalForm({
                            kelas_id: j.kelas_id,
                            mapel_id: j.mapel_id,
                            jam_ke: j.jam_ke,
                            nama_mapel: j.nama_mapel,
                            nama_kelas: j.nama_kelas
                        }, j.id);
                    }
                }
            } catch(e) {
                Toast.show('Gagal memuat data jurnal.', 'error');
            }
        },

        async renderIzin() {
            const content = $('#appContent');
            
            content.innerHTML = `
                <div class="page-enter">
                    <div class="section-title" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <span style="display:flex; align-items:center; gap:8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/><line x1="12" y1="12" x2="12" y2="18"/></svg>
                            Pengajuan Izin Guru
                        </span>
                        <button class="btn btn-sm btn-primary" onclick="GuruApp.openIzinModal()" style="border-radius:10px; padding:6px 12px; font-size:0.75rem; font-weight:700;">
                            + Buat Izin
                        </button>
                    </div>

                    <div id="izinList" style="margin-top: 16px;">
                        <div class="skeleton skeleton-card"></div>
                        <div class="skeleton skeleton-card"></div>
                    </div>
                </div>
            `;
            
            Pages.loadIzinList();
        },

        async loadIzinList() {
            const container = $('#izinList');
            if (!container) return;
            
            try {
                const res = await API.get('api/jurnal.php?action=list_izin');
                if (!res.success) {
                    container.innerHTML = `<div class="empty-state"><div class="empty-state-title">Gagal Memuat</div></div>`;
                    return;
                }
                
                const data = res.data || [];
                if (data.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state" style="background:white; border-radius:16px; padding:32px 16px; text-align:center; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9;">
                            <div style="font-size:2rem; margin-bottom:8px;">📝</div>
                            <div class="empty-state-title" style="font-weight:700; color:var(--text-primary); font-size:0.95rem;">Belum Ada Pengajuan</div>
                            <div class="empty-state-desc" style="font-size:0.75rem; color:var(--text-secondary); margin-top:4px;">Klik "+ Buat Izin" di atas untuk mengajukan izin.</div>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                data.forEach(item => {
                    let badgeColor = '#f59e0b'; // pending
                    let badgeBg = 'rgba(245,158,11,0.1)';
                    if (item.status === 'Approved') {
                        badgeColor = '#10b981';
                        badgeBg = 'rgba(16,185,129,0.1)';
                    } else if (item.status === 'Rejected') {
                        badgeColor = '#ef4444';
                        badgeBg = 'rgba(239,68,68,0.1)';
                    }
                    
                    html += `
                        <div class="riwayat-card" style="background:white; border-radius:16px; padding:16px; margin-bottom:12px; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; display:flex; flex-direction:column; gap:8px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-weight:800; font-family:var(--font-heading); color:var(--text-primary); font-size:0.95rem;">${escapeHtml(item.jenis)}</span>
                                <span style="font-size:0.7rem; font-weight:700; color:${badgeColor}; background:${badgeBg}; padding:4px 8px; border-radius:8px;">${item.status}</span>
                            </div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); display:flex; align-items:center; gap:6px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Tanggal Izin: <strong>${formatTanggal(item.tanggal)}</strong>
                            </div>
                            ${item.catatan ? `
                                <div style="font-size:0.75rem; background:#f8fafc; border-radius:8px; padding:8px 10px; color:var(--text-primary); border-left:3px solid var(--primary);">
                                    ${escapeHtml(item.catatan)}
                                </div>
                            ` : ''}
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            } catch(e) {
                container.innerHTML = `<div class="empty-state"><div class="empty-state-title">Gagal Memuat</div></div>`;
            }
        },

        openIzinModal() {
            const today = getTanggalIni();
            
            const overlay = document.createElement('div');
            overlay.className = 'guru-modal-overlay';
            overlay.id = 'izinModal';
            overlay.innerHTML = `
                <div class="guru-modal" style="animation:slideUp 0.3s ease-out;">
                    <div class="guru-modal-header">
                        <h4>Pengajuan Izin Baru</h4>
                        <button class="guru-modal-close" onclick="GuruApp.closeModal('izinModal')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="guru-modal-body" style="padding: 20px;">
                        <form id="fIzinForm" onsubmit="return false;">
                            <div class="jurnal-form-group">
                                <label class="jurnal-form-label">Tanggal Izin</label>
                                <input type="date" class="jurnal-form-input" id="fIzinTanggal" value="${today}" required>
                            </div>
                            
                            <div class="jurnal-form-group" style="margin-top:12px;">
                                <label class="jurnal-form-label">Jenis Izin</label>
                                <select class="jurnal-form-input" id="fIzinJenis" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px; background:white; font-size:0.875rem;" required>
                                    <option value="">Pilih Jenis Izin...</option>
                                    <option value="Sakit">Sakit</option>
                                    <option value="Cuti">Cuti</option>
                                    <option value="Tugas">Tugas</option>
                                    <option value="Izin">Izin (Lainnya)</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            
                            <div class="jurnal-form-group" style="margin-top:12px;">
                                <label class="jurnal-form-label">Keterangan / Catatan</label>
                                <textarea class="jurnal-form-input" id="fIzinCatatan" rows="3" placeholder="Masukkan alasan pengajuan izin secara detail..." required></textarea>
                            </div>
                            
                            <button type="button" class="btn btn-primary btn-block" id="btnSaveIzin" onclick="GuruApp.saveIzin()" style="margin-top: 20px; border-radius:10px; padding:12px; font-weight:700;">
                                <span class="btn-label">Kirim Pengajuan</span>
                            </button>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(overlay);
        },

        async saveIzin() {
            const tanggal = $('#fIzinTanggal').value;
            const jenis = $('#fIzinJenis').value;
            const catatan = $('#fIzinCatatan').value.trim();
            const btn = $('#btnSaveIzin');
            
            if (!tanggal) { Toast.show('Pilih tanggal izin.', 'error'); return; }
            if (!jenis) { Toast.show('Pilih jenis izin.', 'error'); return; }
            if (!catatan) { Toast.show('Masukkan keterangan / alasan.', 'error'); return; }
            
            btn.classList.add('loading');
            try {
                const res = await API.post('api/jurnal.php?action=create_izin', {
                    tanggal, jenis, catatan
                });
                
                if (res.success) {
                    Toast.show(res.message || 'Pengajuan izin berhasil dikirim.');
                    GuruApp.closeModal('izinModal');
                    Pages.loadIzinList();
                } else {
                    Toast.show(res.message || 'Gagal mengirim pengajuan.', 'error');
                }
            } catch(e) {
                Toast.show('Gagal terhubung ke server.', 'error');
            } finally {
                btn.classList.remove('loading');
            }
        }
    };

    // =============================================
    // APP CONTROLLER
    // =============================================
    const App = {
        async init() {
            Auth.init();

            if (Auth.isLoggedIn()) {
                const valid = await Auth.check();
                if (!valid) {
                    Auth.clear();
                }
            }

            // Hide loader
            const loader = $('#globalLoader');
            if (loader) loader.classList.add('hidden');

            // Show correct view
            if (Auth.isLoggedIn()) {
                const loginPage = $('#loginPage');
                const appShell = $('#appShell');
                if (loginPage) loginPage.style.display = 'none';
                if (appShell) appShell.style.display = 'flex';
                this.updateHeader();
                this.updateWaliNav();
            }

            // Bind login form
            const loginForm = $('#loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', (e) => Pages.handleLogin(e));
            }

            // Init router
            Router.init();

            // Register service worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('sw.js').catch(err => {
                    console.warn('SW registration failed:', err);
                });
            }
        },

        updateHeader() {
            const nameEl = $('#headerName');
            const greetEl = $('#headerGreeting');
            const avatarEl = $('#headerAvatar');

            if (Auth.user) {
                if (nameEl) nameEl.textContent = Auth.user.nama_lengkap || 'Guru';
                if (greetEl) {
                    const hour = new Date().getHours();
                    let greeting = 'Selamat Pagi';
                    if (hour >= 11 && hour < 15) greeting = 'Selamat Siang';
                    else if (hour >= 15 && hour < 18) greeting = 'Selamat Sore';
                    else if (hour >= 18) greeting = 'Selamat Malam';
                    greetEl.textContent = greeting + ' 👋';
                }
                if (avatarEl) {
                    if (Auth.user.avatar) {
                        avatarEl.innerHTML = `<img src="${BASE_URL}${Auth.user.avatar}" alt="Avatar" onerror="this.parentElement.textContent='${getInitials(Auth.user.nama_lengkap)}';">`;
                    } else {
                        avatarEl.textContent = getInitials(Auth.user.nama_lengkap);
                    }
                }
            }
        },

        updateWaliNav() {
            const nav = $('#navJurnalKelas');
            if (!nav) return;
            if (Auth.user && Auth.user.wali_kelas) {
                nav.style.display = 'flex';
            } else {
                nav.style.display = 'none';
            }
        },

        async renderAbsen(bulan = null) {
            const content = $('#appContent');
            if (!bulan) {
                const now = new Date();
                bulan = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
            }

            content.innerHTML = `
                <div class="page-enter">
                    <div class="page-header" style="margin-bottom:20px; display:flex; align-items:center; gap:12px;">
                        <button class="btn btn-icon" onclick="history.back()" style="background:white; border:1.5px solid #e2e8f0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <div>
                            <div class="page-title">Rekap Absen</div>
                            <div class="page-subtitle">Riwayat kehadiran mesin fingerprint</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label">Pilih Bulan</label>
                        <input type="month" id="absenBulan" class="form-control" value="${bulan}" onchange="Pages.renderAbsen(this.value)">
                    </div>

                    <div id="absenContainer" style="min-height:200px; display:flex; justify-content:center; align-items:center;">
                        <div class="loader"></div>
                    </div>
                </div>
            `;

            try {
                const res = await API.get(`api/absen.php?action=rekap_bulanan&bulan=${bulan}`);
                const container = $('#absenContainer');
                
                if (!res.success) {
                    container.innerHTML = `<div class="empty-state-desc">${escapeHtml(res.message)}</div>`;
                    return;
                }

                if (!res.data.mapped) {
                    container.innerHTML = `
                        <div class="empty-state" style="padding:40px 20px; text-align:center;">
                            <div class="empty-state-icon" style="background:#fee2e2; color:#ef4444; width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            </div>
                            <div style="font-weight:700; color:#1e293b; margin-bottom:8px;">Belum Terhubung dengan Mesin</div>
                            <div class="empty-state-desc" style="font-size:0.85rem;">Hubungi admin untuk menghubungkan akun ini dengan ID Mesin Fingerprint Anda.</div>
                        </div>
                    `;
                    return;
                }

                if (res.data.logs.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state" style="padding:40px 20px; text-align:center;">
                            <div style="font-weight:700; color:#1e293b; margin-bottom:8px;">Belum ada Data</div>
                            <div class="empty-state-desc" style="font-size:0.85rem;">Tidak ada riwayat kehadiran di bulan ini.</div>
                        </div>
                    `;
                    return;
                }

                let html = `
                    <div style="background:white; border-radius:16px; padding:16px; box-shadow:var(--shadow-sm); border:1px solid #f1f5f9; margin-bottom:16px;">
                        <div style="font-size:0.75rem; font-weight:600; color:var(--text-muted); margin-bottom:4px;">Total Kehadiran</div>
                        <div style="font-size:1.5rem; font-weight:800; color:var(--primary);">${res.data.stats.hadir} <span style="font-size:1rem; font-weight:600; color:var(--text-primary);">Hari</span></div>
                    </div>
                    
                    <div style="display:flex; flex-direction:column; gap:12px;">
                `;

                res.data.logs.forEach(log => {
                    html += `
                        <div style="background:white; border-radius:12px; padding:16px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 8px rgba(0,0,0,0.03); border:1px solid #f1f5f9;">
                            <div>
                                <div style="font-size:0.8rem; font-weight:700; color:var(--text-primary); margin-bottom:4px;">${formatTanggal(log.tanggal)}</div>
                                <div style="display:flex; gap:12px; font-size:0.75rem; font-weight:600; color:var(--text-muted);">
                                    <div style="display:flex; align-items:center; gap:4px; color:#10b981;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                        Masuk: ${log.jam_masuk}
                                    </div>
                                    <div style="display:flex; align-items:center; gap:4px; color:#f59e0b;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                        Pulang: ${log.jam_pulang}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += `</div>`;
                container.innerHTML = html;

            } catch(e) {
                $('#absenContainer').innerHTML = `<div class="empty-state-desc">Gagal memuat riwayat kehadiran.</div>`;
            }
        }
    };

    // =============================================
    // GLOBAL EXPOSED FUNCTIONS
    // =============================================
    window.GuruApp = {
        switchDay(hari) {
            Pages.renderDaySchedule(hari);
        },

        loadJurnalSchedule() {
            Pages.loadJurnalSchedule();
        },

        loadRiwayat() {
            Pages.loadRiwayat();
        },

        onScheduleSlotClick(el) {
            try {
                const data = JSON.parse(el.dataset.jadwal);
                if (data.jurnal_filled && data.jurnal_id) {
                    Pages.editJurnal(data.jurnal_id);
                } else {
                    Pages.openJurnalForm(data);
                }
            } catch(e) {
                console.error('Error parsing jadwal data', e);
            }
        },

        closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.style.animation = 'fadeOut 0.2s ease forwards';
                setTimeout(() => modal.remove(), 200);
            }
        },

        async saveJurnal(id, kelas_id, mapel_id, jam_ke, tanggal) {
            await Pages.saveJurnal(id, kelas_id, mapel_id, jam_ke, tanggal);
        },

        async viewJurnal(id) {
            await Pages.viewJurnal(id);
        },

        async editJurnal(id) {
            await Pages.editJurnal(id);
        },

        async deleteJurnal(id) {
            // Show confirm dialog
            const overlay = document.createElement('div');
            overlay.className = 'confirm-dialog-overlay';
            overlay.id = 'confirmDelete';
            overlay.innerHTML = `
                <div class="confirm-dialog">
                    <div class="confirm-dialog-icon danger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </div>
                    <h4>Hapus Jurnal?</h4>
                    <p>Data jurnal yang dihapus tidak dapat dikembalikan.</p>
                    <div class="confirm-dialog-buttons">
                        <button class="btn btn-ghost" onclick="GuruApp.closeModal('confirmDelete')">Batal</button>
                        <button class="btn btn-danger" id="confirmDeleteBtn" onclick="GuruApp.doDelete(${id})">
                            <span class="btn-label">Hapus</span>
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) GuruApp.closeModal('confirmDelete');
            });
        },

        async doDelete(id) {
            const btn = $('#confirmDeleteBtn');
            if (btn) btn.classList.add('loading');

            try {
                const res = await API.post('api/jurnal.php?action=delete', { id });
                if (res.success) {
                    Toast.show('Jurnal berhasil dihapus.', 'success');
                    GuruApp.closeModal('confirmDelete');
                    if (Router.currentPage === 'riwayat') Pages.loadRiwayat();
                    else if (Router.currentPage === 'home') Pages.renderHome();
                    else if (Router.currentPage === 'jurnal') Pages.loadJurnalSchedule();
                } else {
                    Toast.show(res.message || 'Gagal menghapus.', 'error');
                }
            } catch(e) {
                Toast.show('Gagal terhubung ke server.', 'error');
            } finally {
                if (btn) btn.classList.remove('loading');
            }
        },

        confirmLogout() {
            const overlay = document.createElement('div');
            overlay.className = 'confirm-dialog-overlay';
            overlay.id = 'confirmLogout';
            overlay.innerHTML = `
                <div class="confirm-dialog">
                    <div class="confirm-dialog-icon danger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </div>
                    <h4>Keluar dari Aplikasi?</h4>
                    <p>Anda harus login kembali setelah keluar.</p>
                    <div class="confirm-dialog-buttons">
                        <button class="btn btn-ghost" onclick="GuruApp.closeModal('confirmLogout')">Batal</button>
                        <button class="btn btn-danger" onclick="GuruApp.closeModal('confirmLogout'); GuruApp.doLogout()">
                            <span class="btn-label">Keluar</span>
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) GuruApp.closeModal('confirmLogout');
            });
        },

        async doLogout() {
            await Auth.logout();
            const loginPage = $('#loginPage');
            const appShell = $('#appShell');
            if (appShell) appShell.style.display = 'none';
            if (loginPage) loginPage.style.display = 'flex';
            const nav = $('#navJurnalKelas');
            if (nav) nav.style.display = 'none';
        },

        loadWaliJurnal() {
            Pages.loadWaliJurnal();
        },

        switchWaliTab(tab) {
            Pages.switchWaliTab(tab);
        },

        openKegiatanModal(jenis, existingId) {
            Pages.openKegiatanModal(jenis, existingId);
        },

        async saveKegiatanJurnal(jenis, existingId) {
            await Pages.saveKegiatanJurnal(jenis, existingId);
        },

        async saveDirectKegiatan(jenis) {
            await Pages.saveDirectKegiatan(jenis);
        },

        filterWaliRekap(query) {
            Pages.filterWaliRekap(query);
        },

        openIzinModal() {
            Pages.openIzinModal();
        },

        async saveIzin() {
            await Pages.saveIzin();
        },

        async printDailyAbsen() {
            await Pages.printDailyAbsen();
        }
    };

    // =============================================
    // BOOT
    // =============================================
    document.addEventListener('DOMContentLoaded', () => App.init());
})();
