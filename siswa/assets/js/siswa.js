/**
 * E-Portal Siswa App Logic
 * SPA Routing, Auth, Izin, Absensi, and BK Notes
 * Fully Optimized for Mobile & iOS PWA
 */

const App = {
    config: window.APP_CONFIG || { baseUrl: '/' },
    state: {
        student: null,
        token: null,
        currentRoute: 'dashboard'
    },

    init() {
        this.checkAuth();
        this.setupRouter();
        this.bindEvents();
    },

    checkAuth() {
        const token = localStorage.getItem('siswa_token') || this.getCookie('siswa_token');
        const storedStudent = localStorage.getItem('siswa_data');
        
        const loginPage = document.getElementById('loginPage');
        const appShell = document.getElementById('appShell');
        const loader = document.getElementById('globalLoader');

        if (token && storedStudent) {
            this.state.token = token;
            try {
                this.state.student = JSON.parse(storedStudent);
            } catch(e) {
                this.state.student = null;
            }
        }

        if (this.state.token && this.state.student) {
            // Synchronize cookie for persistent requests
            this.setCookie('siswa_token', this.state.token, 365);

            // Set header user info
            const initials = (this.state.student.nama || 'Siswa')
                .split(' ')
                .filter(Boolean)
                .map(w => w[0])
                .slice(0, 2)
                .join('')
                .toUpperCase();
                
            const avatarEl = document.getElementById('headerAvatar');
            const nameEl = document.getElementById('headerName');
            if (avatarEl) avatarEl.textContent = initials || 'S';
            if (nameEl) nameEl.textContent = this.state.student.nama || 'Siswa';

            if (loginPage) loginPage.style.display = 'none';
            if (appShell) appShell.style.display = 'flex';
            
            // Run initial routing
            let initialHash = window.location.hash.replace(/^#\/?/, '') || 'dashboard';
            this.navigate(initialHash);
        } else {
            if (appShell) appShell.style.display = 'none';
            if (loginPage) loginPage.style.display = 'flex';
        }

        if (loader) {
            setTimeout(() => {
                loader.style.opacity = '0';
                setTimeout(() => loader.style.display = 'none', 300);
            }, 250);
        }
    },

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    },

    setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
    },

    login(nis, tanggal_lahir) {
        const err = document.getElementById('loginError');
        const btn = document.getElementById('loginBtn');
        if (err) {
            err.style.display = 'none';
            err.innerText = '';
        }
        if (btn) btn.classList.add('loading');

        return fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nis, tanggal_lahir })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                this.state.token = data.data.token;
                this.state.student = data.data.student;
                localStorage.setItem('siswa_token', data.data.token);
                localStorage.setItem('siswa_data', JSON.stringify(data.data.student));
                this.setCookie('siswa_token', data.data.token, 365);
                this.showToast('Login berhasil. Selamat datang!', 'success');
                this.checkAuth();
            } else {
                const msg = data.message || 'Login gagal. Periksa NIS dan Tanggal Lahir.';
                if (err) {
                    err.innerText = msg;
                    err.style.display = 'block';
                }
                this.showToast(msg, 'error');
            }
        })
        .catch(error => {
            const msg = 'Koneksi ke server gagal. Pastikan terhubung internet.';
            if (err) {
                err.innerText = msg;
                err.style.display = 'block';
            }
            this.showToast(msg, 'error');
        })
        .finally(() => {
            if (btn) btn.classList.remove('loading');
        });
    },

    logout() {
        this.setCookie('siswa_token', '', -1);
        localStorage.removeItem('siswa_token');
        localStorage.removeItem('siswa_data');
        this.state.token = null;
        this.state.student = null;
        this.showToast('Anda telah keluar.', 'info');
        this.checkAuth();
    },

    setupRouter() {
        window.addEventListener('hashchange', () => {
            let hash = window.location.hash.replace(/^#\/?/, '');
            if (!hash) hash = 'dashboard';
            this.navigate(hash);
        });
    },

    navigate(route) {
        if (!this.state.student) return;
        
        this.state.currentRoute = route;
        
        // Update Bottom Nav Active Indicator
        document.querySelectorAll('.bottom-nav-item').forEach(el => {
            el.classList.remove('active');
            if (el.dataset.page === route) el.classList.add('active');
        });

        const contentDiv = document.getElementById('appContent');
        if (!contentDiv) return;

        contentDiv.innerHTML = '<div class="loader-spinner"></div>';
        this.loadView(route);
    },

    loadView(route) {
        const contentDiv = document.getElementById('appContent');
        if (!contentDiv) return;
        
        switch(route) {
            case 'dashboard':
                this.apiGet('api/dashboard.php').then(res => {
                    if (res.success) {
                        contentDiv.innerHTML = this.views.dashboard(res.data || {});
                    } else {
                        contentDiv.innerHTML = this.views.error('Gagal memuat ringkasan dashboard', () => this.loadView('dashboard'));
                    }
                });
                break;
            case 'izin':
                this.apiGet('api/kehadiran.php?action=list_izin').then(res => {
                    if (res.success) {
                        contentDiv.innerHTML = this.views.izin(res.data || []);
                    } else {
                        contentDiv.innerHTML = this.views.error('Gagal memuat daftar riwayat izin', () => this.loadView('izin'));
                    }
                });
                break;
            case 'kehadiran':
                this.apiGet('api/kehadiran.php?action=rekap').then(res => {
                    if (res.success) {
                        contentDiv.innerHTML = this.views.kehadiran(res.data || { logs: [], kelas: [] });
                    } else {
                        contentDiv.innerHTML = this.views.error('Gagal memuat rekap kehadiran harian', () => this.loadView('kehadiran'));
                    }
                });
                break;
            case 'bk':
                this.apiGet('api/bk.php').then(res => {
                    if (res.success) {
                        contentDiv.innerHTML = this.views.bk(res.data || []);
                    } else {
                        contentDiv.innerHTML = this.views.error('Gagal memuat catatan guru BK', () => this.loadView('bk'));
                    }
                });
                break;
            default:
                contentDiv.innerHTML = `
                    <div style="text-align:center; padding:50px 20px;">
                        <h3>Halaman tidak ditemukan</h3>
                        <button class="btn btn-primary mt-3" onclick="location.hash='#/dashboard'">Kembali ke Beranda</button>
                    </div>
                `;
        }
    },

    views: {
        error(msg, retryFn) {
            return `
                <div class="page-enter" style="text-align:center; padding:40px 20px; background:#fff; border-radius:18px; border:1px solid #e2e8f0; margin-top:20px;">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#ef4444" stroke-width="2" style="margin-bottom:12px;">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <h3 style="font-size:1.1rem; margin-bottom:6px;">Terjadi Kendala</h3>
                    <p style="font-size:0.85rem; color:#64748b; margin-bottom:16px;">${msg}</p>
                    <button class="btn btn-primary btn-sm" onclick="App.navigate(App.state.currentRoute)">Coba Lagi</button>
                </div>
            `;
        },

        dashboard(data) {
            const student = App.state.student || {};
            return `
                <div class="page-enter">
                    <!-- Welcome Hero Header -->
                    <div style="background: var(--primary-gradient); color: white; padding: 22px 20px 26px; border-radius: 22px; margin-bottom: 20px; box-shadow: var(--shadow-primary); position: relative; overflow: hidden;">
                        <div style="position: absolute; right: -25px; bottom: -25px; width: 130px; height: 130px; border-radius: 50%; background: rgba(255, 255, 255, 0.05); pointer-events: none;"></div>
                        <div style="position: absolute; right: 40px; top: -30px; width: 80px; height: 80px; border-radius: 50%; background: rgba(255, 255, 255, 0.03); pointer-events: none;"></div>
                        
                        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.8); margin-bottom: 4px; font-weight: 600;">Kartu Siswa Digital</div>
                        <h2 style="font-size: 1.45rem; margin-bottom: 4px; color: #ffffff; font-weight: 700;">${student.nama || 'Siswa'}</h2>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; opacity: 0.9; font-size: 0.85rem;">
                            <span>NIS: <strong>${student.nis || '-'}</strong></span>
                            <span>•</span>
                            <span>Kelas: <strong>${student.kelas || '-'}</strong></span>
                        </div>
                        
                        <!-- Kehadiran Box -->
                        <div style="background: rgba(255,255,255,0.12); border-radius: 16px; margin-top: 18px; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(255,255,255,0.18);">
                            <div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85; margin-bottom: 2px; font-weight: 500;">Kehadiran Bulan Ini</div>
                                <div style="font-size: 1.6rem; font-weight: 800; color:#fff;">${data.hadir || 0} <span style="font-size: 0.9rem; font-weight: 500; opacity: 0.85;">Hari</span></div>
                            </div>
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        </div>
                    </div>

                    <!-- Rekap Bulanan Grid -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h3 style="font-size: 1.1rem; color: var(--text-primary); margin:0;">Rekap Bulan Ini</h3>
                        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight:500;">${new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })}</span>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 22px;">
                        <div style="background: white; border-radius: 18px; padding: 16px 12px; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid #e2e8f0;">
                            <div style="color: var(--warning); font-size: 1.6rem; font-weight: 800; line-height: 1.1; margin-bottom: 4px;">${data.izin || 0}</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">Izin</div>
                        </div>
                        <div style="background: white; border-radius: 18px; padding: 16px 12px; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid #e2e8f0;">
                            <div style="color: var(--info); font-size: 1.6rem; font-weight: 800; line-height: 1.1; margin-bottom: 4px;">${data.sakit || 0}</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">Sakit</div>
                        </div>
                        <div style="background: white; border-radius: 18px; padding: 16px 12px; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid #e2e8f0;">
                            <div style="color: var(--danger); font-size: 1.6rem; font-weight: 800; line-height: 1.1; margin-bottom: 4px;">${data.alfa || 0}</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">Alfa</div>
                        </div>
                    </div>

                    <!-- Shortcut Action Cards -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <button class="card" onclick="location.hash='#/izin'" style="text-align: left; padding: 16px; border-radius: 18px; cursor: pointer; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; width: 100%;">
                            <div style="width: 40px; height: 40px; border-radius: 12px; background: var(--info-light); color: var(--info); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                            </div>
                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-primary);">Buat Izin</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Ajukan surat izin</div>
                            </div>
                        </button>
                        <button class="card" onclick="location.hash='#/bk'" style="text-align: left; padding: 16px; border-radius: 18px; cursor: pointer; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; width: 100%;">
                            <div style="width: 40px; height: 40px; border-radius: 12px; background: var(--purple-light); color: var(--purple); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-primary);">Catatan BK</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Lihat buku BK</div>
                            </div>
                        </button>
                    </div>
                </div>
            `;
        },

        izin(data) {
            const list = Array.isArray(data) ? data : [];
            let listHTML = list.length ? list.map(i => {
                const isApproved = i.status === 'Disetujui' || i.status === 'Approved';
                const isRejected = i.status === 'Ditolak' || i.status === 'Rejected';
                const badgeClass = isApproved ? 'badge-success' : (isRejected ? 'badge-danger' : 'badge-warning');
                const statusText = isApproved ? 'Disetujui' : (isRejected ? 'Ditolak' : 'Menunggu');

                return `
                    <div class="card" style="margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">📅 ${i.tanggal_indo || i.tanggal}</div>
                            <span class="badge ${badgeClass}">${statusText}</span>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 4px;">
                            Kategori: <strong style="color: var(--text-primary);">${i.jenis}</strong>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.45; background: #f8fafc; padding: 8px 12px; border-radius: 10px; border: 1px solid #f1f5f9;">
                            ${i.keterangan || '-'}
                        </div>
                    </div>
                `;
            }).join('') : `
                <div class="card" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.4; margin-bottom: 12px;">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <h4 style="margin: 0 0 4px; font-size: 1.05rem; color: var(--text-primary);">Belum Ada Riwayat Izin</h4>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Anda belum pernah mengajukan permohonan izin atau sakit.</p>
                </div>
            `;

            return `
                <div class="page-enter">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                        <div>
                            <h2 style="margin: 0 0 2px; font-size: 1.35rem;">Pengajuan Izin</h2>
                            <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">Riwayat dan permohonan tidak masuk sekolah</p>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="App.showIzinModal()" style="border-radius: 12px; box-shadow: var(--shadow-sm);">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Buat Izin
                        </button>
                    </div>
                    ${listHTML}
                </div>
            `;
        },

        kehadiran(data) {
            const logs = Array.isArray(data.logs) ? data.logs : [];
            const kelas = Array.isArray(data.kelas) ? data.kelas : [];

            let logsHTML = logs.length ? logs.map(l => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed #e2e8f0;">
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 1.05rem;">${l.waktu} WIB</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">Mesin Presensi Gerbang (Fingerprint/Face)</div>
                    </div>
                    <span class="badge ${l.status_absen == 0 ? 'badge-info' : 'badge-warning'}">
                        ${l.status_absen == 0 ? 'Masuk' : 'Pulang'}
                    </span>
                </div>
            `).join('') : '<div style="text-align: center; font-size: 0.85rem; color: var(--text-muted); padding: 20px 0;">Belum ada tap mesin presensi hari ini</div>';

            let kelasHTML = kelas.length ? kelas.map(k => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed #e2e8f0;">
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">Jam Pelajaran ke-${k.jam_ke}</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">Presensi oleh Guru Mata Pelajaran</div>
                    </div>
                    <div style="font-weight: 800; font-size: 1.15rem; color: ${k.status === 'H' ? 'var(--success)' : (k.status === 'I' ? 'var(--warning)' : (k.status === 'S' ? 'var(--info)' : 'var(--danger)'))};">
                        ${k.status}
                    </div>
                </div>
            `).join('') : '<div style="text-align: center; font-size: 0.85rem; color: var(--text-muted); padding: 20px 0;">Belum ada presensi kelas dari guru hari ini</div>';

            return `
                <div class="page-enter">
                    <div style="margin-bottom: 18px;">
                        <h2 style="margin: 0 0 2px; font-size: 1.35rem;">Kehadiran Hari Ini</h2>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">${new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</p>
                    </div>
                    
                    <!-- Presensi Gerbang Card -->
                    <div class="card" style="margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                            <div style="width: 34px; height: 34px; border-radius: 10px; background: var(--info-light); color: var(--info); display: flex; align-items: center; justify-content: center;">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 1.05rem; color: var(--text-primary);">Presensi Gerbang (E-Absen)</h3>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Data otomatis dari mesin sidik jari/wajah</div>
                            </div>
                        </div>
                        ${logsHTML}
                    </div>

                    <!-- Presensi Kelas Card -->
                    <div class="card">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                            <div style="width: 34px; height: 34px; border-radius: 10px; background: var(--success-light); color: var(--success); display: flex; align-items: center; justify-content: center;">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 1.05rem; color: var(--text-primary);">Presensi Kelas (Guru Mapel)</h3>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Data presensi di setiap jam pelajaran</div>
                            </div>
                        </div>
                        ${kelasHTML}
                    </div>
                </div>
            `;
        },

        bk(data) {
            const list = Array.isArray(data) ? data : [];
            const badgeClassMap = {
                'badge-warning': 'badge-warning',
                'badge-danger': 'badge-danger',
                'badge-success': 'badge-success',
                'badge-primary': 'badge-primary',
                'badge-info': 'badge-info'
            };

            let bkHTML = list.length ? list.map(b => {
                const badgeCls = badgeClassMap[b.warna_badge] || 'badge-info';
                return `
                    <div class="card" style="margin-bottom: 14px; position: relative;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 8px;">
                            <span class="badge ${badgeCls}">
                                ${b.jenis || 'Konsultasi'}
                            </span>
                            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">📅 ${b.tanggal_indo || b.tanggal}</div>
                        </div>
                        <div style="font-size: 0.92rem; color: var(--text-primary); line-height: 1.6; margin-bottom: 10px; white-space: pre-wrap; background: #f8fafc; padding: 10px 14px; border-radius: 12px; border: 1px solid #f1f5f9;">${b.catatan}</div>
                        ${b.dicatat_nama ? `
                            <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                Dicatat oleh: <strong style="color: var(--text-secondary);">${b.dicatat_nama}</strong>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('') : `
                <div class="card" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.4; margin-bottom: 12px;">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <h4 style="margin: 0 0 6px; font-size: 1.05rem; color: var(--text-primary);">Belum Ada Catatan</h4>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Buku penghubung & bimbingan konseling belum memiliki catatan untuk Anda.</p>
                </div>
            `;

            return `
                <div class="page-enter">
                    <div style="margin-bottom: 18px;">
                        <h2 style="margin: 0 0 2px; font-size: 1.35rem;">📖 Buku Penghubung & BK</h2>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">Catatan prestasi, kedisiplinan, konsultasi, dan perkembangan siswa</p>
                    </div>
                    ${bkHTML}
                </div>
            `;
        }
    },

    showIzinModal() {
        // Use local date formatted as YYYY-MM-DD
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const defaultDate = `${year}-${month}-${day}`;

        const modalHtml = `
            <div id="izinModalOverlay" class="modal-overlay">
                <div class="modal-sheet" id="izinModalSheet">
                    <div class="modal-sheet-header">
                        <h3 class="modal-sheet-title">Formulir Izin Siswa</h3>
                        <button type="button" class="modal-sheet-close" id="btnCloseIzinModal" title="Tutup">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form id="formIzinModal">
                        <div class="form-group">
                            <label class="form-label">Tanggal Izin / Sakit</label>
                            <input type="date" id="izinTanggal" class="form-control" required value="${defaultDate}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jenis Permohonan</label>
                            <select id="izinJenis" class="form-control" required style="background:#fff;">
                                <option value="Sakit">Sakit</option>
                                <option value="Izin" selected>Izin</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Keterangan / Alasan Lengkap</label>
                            <textarea id="izinKet" class="form-control" rows="3" required placeholder="Tuliskan keterangan izin atau sakit secara lengkap..." style="resize:vertical; min-height:80px;"></textarea>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <button type="button" class="btn btn-secondary btn-block" id="btnBatalIzin">Batal</button>
                            <button type="submit" class="btn btn-primary btn-block" id="btnSubmitIzin">
                                <span class="btn-label">Kirim Pengajuan</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        const container = document.getElementById('modalContainer');
        if (!container) return;
        container.innerHTML = modalHtml;

        const overlay = document.getElementById('izinModalOverlay');
        const sheet = document.getElementById('izinModalSheet');
        const btnClose = document.getElementById('btnCloseIzinModal');
        const btnBatal = document.getElementById('btnBatalIzin');
        const form = document.getElementById('formIzinModal');

        const closeModal = () => {
            if (overlay) {
                overlay.style.opacity = '0';
                if (sheet) sheet.style.transform = 'translateY(100%)';
                setTimeout(() => { overlay.remove(); }, 250);
            }
        };

        if (btnClose) btnClose.addEventListener('click', closeModal);
        if (btnBatal) btnBatal.addEventListener('click', closeModal);
        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeModal();
            });
        }

        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                App.submitIzin(closeModal);
            });
        }
    },

    submitIzin(onSuccessCallback) {
        const tglInput = document.getElementById('izinTanggal');
        const jenisInput = document.getElementById('izinJenis');
        const ketInput = document.getElementById('izinKet');
        const submitBtn = document.getElementById('btnSubmitIzin');

        if (!tglInput || !jenisInput || !ketInput) return;

        const payload = {
            tanggal: tglInput.value.trim(),
            jenis: jenisInput.value.trim(),
            keterangan: ketInput.value.trim()
        };

        if (!payload.tanggal || !payload.keterangan) {
            this.showToast('Harap lengkapi semua data formulir.', 'warning');
            return;
        }

        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }

        this.apiPost('api/kehadiran.php?action=submit_izin', payload)
            .then(res => {
                if (res.success) {
                    this.showToast('Pengajuan permohonan izin berhasil dikirim!', 'success');
                    if (typeof onSuccessCallback === 'function') {
                        onSuccessCallback();
                    } else {
                        const overlay = document.getElementById('izinModalOverlay');
                        if (overlay) overlay.remove();
                    }
                    // Refresh view
                    this.loadView('izin');
                } else {
                    this.showToast(res.message || 'Gagal mengirim pengajuan izin.', 'error');
                }
            })
            .catch(err => {
                this.showToast('Terjadi kesalahan jaringan saat mengirim izin.', 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                }
            });
    },

    apiGet(url) {
        const token = this.state.token || localStorage.getItem('siswa_token') || this.getCookie('siswa_token');
        return fetch(url, {
            headers: {
                'Authorization': 'Bearer ' + (token || ''),
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(res => {
            if (res.status === 401) {
                this.logout();
                throw new Error('Sesi telah berakhir');
            }
            return res.json();
        })
        .catch(err => {
            console.error('API GET Error:', err);
            return { success: false, message: err.message };
        });
    },

    apiPost(url, data) {
        const token = this.state.token || localStorage.getItem('siswa_token') || this.getCookie('siswa_token');
        return fetch(url, {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + (token || ''),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        })
        .then(res => {
            if (res.status === 401) {
                this.logout();
                throw new Error('Sesi telah berakhir');
            }
            return res.json();
        })
        .catch(err => {
            console.error('API POST Error:', err);
            return { success: false, message: err.message };
        });
    },

    showToast(msg, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        let iconSvg = '';
        if (type === 'success') {
            iconSvg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
        } else if (type === 'error') {
            iconSvg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--danger)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        } else {
            iconSvg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--warning)" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        }

        toast.innerHTML = `${iconSvg} <span>${msg}</span>`;
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3200);
    },

    bindEvents() {
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const nisInput = document.getElementById('loginUsername');
                const dobInput = document.getElementById('loginPassword');
                
                const nis = nisInput ? nisInput.value.trim() : '';
                const dob = dobInput ? dobInput.value.trim() : '';
                
                if (nis && dob) {
                    this.login(nis, dob);
                }
            });
        }
    }
};

// Auto initialize App on DOM ready
document.addEventListener('DOMContentLoaded', () => App.init());
