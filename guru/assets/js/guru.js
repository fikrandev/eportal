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
            const hash = window.location.hash.replace('#/', '') || '';
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
                case 'profil':
                    Pages.renderProfil();
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

            content.innerHTML = `
                <div class="page-enter">
                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Hari Ini — ${hariIni}
                    </div>
                    <p class="section-subtitle">${formatTanggal(tanggalIni)}</p>

                    <div class="stats-grid" id="homeStats">
                        <div class="skeleton skeleton-stat"></div>
                        <div class="skeleton skeleton-stat"></div>
                        <div class="skeleton skeleton-stat"></div>
                        <div class="skeleton skeleton-stat"></div>
                    </div>

                    <div class="section-title mt-2">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Jadwal Hari Ini
                    </div>
                    <div id="homeTodaySchedule">
                        <div class="skeleton skeleton-card"></div>
                        <div class="skeleton skeleton-card"></div>
                        <div class="skeleton skeleton-card"></div>
                    </div>
                </div>
            `;

            try {
                const res = await API.get(`api/jadwal.php?action=today&tanggal=${tanggalIni}`);
                if (res.success) {
                    const jadwal = res.data.jadwal || [];
                    const filled = jadwal.filter(j => j.jurnal_filled).length;
                    const total = jadwal.length;
                    const pending = total - filled;

                    $('#homeStats').innerHTML = `
                        <div class="stat-card">
                            <div class="stat-card-icon blue">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div class="stat-card-value">${total}</div>
                            <div class="stat-card-label">Jadwal Hari Ini</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-icon green">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <div class="stat-card-value">${filled}</div>
                            <div class="stat-card-label">Jurnal Diisi</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-icon yellow">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </div>
                            <div class="stat-card-value">${pending}</div>
                            <div class="stat-card-label">Belum Diisi</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-icon ${total > 0 ? (filled === total ? 'green' : 'red') : 'blue'}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            </div>
                            <div class="stat-card-value">${total > 0 ? Math.round((filled / total) * 100) : 0}%</div>
                            <div class="stat-card-label">Progress</div>
                        </div>
                    `;

                    this.renderScheduleSlots('#homeTodaySchedule', jadwal, true);
                }
            } catch(e) {
                $('#homeTodaySchedule').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <div class="empty-state-title">Gagal Memuat</div>
                        <div class="empty-state-desc">Tidak dapat terhubung ke server.</div>
                    </div>
                `;
                $('#homeStats').innerHTML = '';
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
                    <div class="schedule-slot-status">
                        <span class="badge badge-primary">${escapeHtml(j.kode_mapel || '')}</span>
                    </div>
                </div>
            `).join('');
        },

        // --- JURNAL FORM ---
        async renderJurnal(editId = null) {
            const content = $('#appContent');
            const tanggalIni = getTanggalIni();

            content.innerHTML = `
                <div class="page-enter">
                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Jurnal Mengajar
                    </div>
                    <p class="section-subtitle">Pilih jadwal untuk mengisi jurnal hari ini</p>

                    <div class="form-info-row mb-2">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
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

        // --- JURNAL FORM MODAL ---
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
                            <label class="form-label">Siswa Tidak Hadir</label>
                            <textarea class="form-textarea" id="jurnalSiswaTidakHadir" placeholder="Nama siswa yang tidak hadir, pisahkan dengan baris baru..." style="min-height:70px;">${escapeHtml(existing?.siswa_tidak_hadir || '')}</textarea>
                            <div class="form-hint">Satu nama per baris</div>
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

            // Clicking overlay closes modal
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) GuruApp.closeModal('jurnalModal');
            });
        },

        async saveJurnal(id, kelas_id, mapel_id, jam_ke, tanggal) {
            const btn = $('#jurnalSaveBtn');
            if (!btn) return;
            btn.classList.add('loading');

            const payload = {
                kelas_id,
                mapel_id,
                jam_ke: String(jam_ke),
                tanggal,
                tujuan_pembelajaran: $('#jurnalTP')?.value || '',
                indikator_tp: $('#jurnalIPTP')?.value || '',
                catatan: $('#jurnalCatatan')?.value || '',
                siswa_tidak_hadir: $('#jurnalSiswaTidakHadir')?.value || ''
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
                        ${data.map(j => `
                            <div class="jurnal-item" onclick="GuruApp.viewJurnal(${j.id})">
                                <div class="jurnal-item-header">
                                    <div class="jurnal-item-mapel">${escapeHtml(j.nama_mapel)}</div>
                                    <div class="jurnal-item-jam">Jam ${escapeHtml(j.jam_ke)}</div>
                                </div>
                                <div class="jurnal-item-kelas">${escapeHtml(j.nama_kelas)}</div>
                                ${j.tujuan_pembelajaran ? `<div class="jurnal-item-tp">${escapeHtml(j.tujuan_pembelajaran)}</div>` : ''}
                                <div class="jurnal-item-footer">
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
                        `).join('')}
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
                overlay.innerHTML = `
                    <div class="guru-modal">
                        <div class="guru-modal-header">
                            <h3>Detail Jurnal</h3>
                            <button class="guru-modal-close" onclick="GuruApp.closeModal('viewJurnalModal')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="guru-modal-body">
                            <div class="form-info-row">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <span><strong>${formatTanggal(j.tanggal)}</strong> — Jam ke-${escapeHtml(j.jam_ke)}</span>
                            </div>
                            <div class="form-info-row">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                <span><strong>${escapeHtml(j.nama_mapel)}</strong> — ${escapeHtml(j.nama_kelas)}</span>
                            </div>

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

        async editJurnal(id) {
            try {
                const res = await API.get(`api/jurnal.php?action=get&id=${id}`);
                if (res.success) {
                    const j = res.data;
                    this.openJurnalForm({
                        kelas_id: j.kelas_id,
                        mapel_id: j.mapel_id,
                        jam_ke: j.jam_ke,
                        nama_mapel: j.nama_mapel,
                        nama_kelas: j.nama_kelas
                    }, j.id);
                }
            } catch(e) {
                Toast.show('Gagal memuat data jurnal.', 'error');
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
        }
    };

    // =============================================
    // BOOT
    // =============================================
    document.addEventListener('DOMContentLoaded', () => App.init());
})();
