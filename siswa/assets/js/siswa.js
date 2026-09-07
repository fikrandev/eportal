/**
 * E-Portal Siswa App Logic
 * Handles SPA Routing, Auth, and API Calls
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
        const token = localStorage.getItem('siswa_token');
        const storedStudent = localStorage.getItem('siswa_data');
        
        const loginPage = document.getElementById('loginPage');
        const appShell = document.getElementById('appShell');
        const loader = document.getElementById('globalLoader');

        if (token && storedStudent) {
            this.state.token = token;
            this.state.student = JSON.parse(storedStudent);
            
            // Set header info
            const initials = this.state.student.nama.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
            document.getElementById('headerAvatar').textContent = initials;
            document.getElementById('headerName').textContent = this.state.student.nama;

            if (loginPage) loginPage.style.display = 'none';
            if (appShell) appShell.style.display = 'block';
            
            // Run routing logic
            let initialHash = window.location.hash.replace(/^#\/?/, '') || 'dashboard';
            this.navigate(initialHash);
        } else {
            if (appShell) appShell.style.display = 'none';
            if (loginPage) loginPage.style.display = 'block';
        }

        if (loader) {
            setTimeout(() => {
                loader.style.opacity = '0';
                setTimeout(() => loader.style.display = 'none', 300);
            }, 300);
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
            date.setTime(date.getTime() + (days*24*60*60*1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "")  + expires + "; path=/";
    },

    login(nis, tanggal_lahir) {
        const err = document.getElementById('loginError');
        return fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nis, tanggal_lahir })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                localStorage.setItem('siswa_token', data.data.token);
                localStorage.setItem('siswa_data', JSON.stringify(data.data.student));
                this.checkAuth();
            } else {
                if (err) err.innerText = data.message;
                this.showToast(data.message, 'error');
            }
        })
        .catch(error => {
            if (err) err.innerText = 'Koneksi gagal';
            this.showToast('Koneksi gagal', 'error');
        });
    },

    logout() {
        this.setCookie('siswa_token', '', -1);
        localStorage.removeItem('siswa_token');
        localStorage.removeItem('siswa_data');
        this.state.token = null;
        this.state.student = null;
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
        
        // Update nav UI
        document.querySelectorAll('.bottom-nav-item').forEach(el => {
            el.classList.remove('active');
            if (el.dataset.page === route) el.classList.add('active');
        });

        const contentDiv = document.getElementById('appContent');
        if(!contentDiv) return;

        contentDiv.innerHTML = '<div class="loader-spinner"></div>';

        // Fetch view
        this.loadView(route);
    },

    loadView(route) {
        const contentDiv = document.getElementById('appContent');
        
        switch(route) {
            case 'dashboard':
                this.apiGet('api/dashboard.php').then(res => {
                    if(res.success) {
                        contentDiv.innerHTML = this.views.dashboard(res.data);
                    }
                });
                break;
            case 'izin':
                this.apiGet('api/kehadiran.php?action=list_izin').then(res => {
                    if(res.success) {
                        contentDiv.innerHTML = this.views.izin(res.data);
                    }
                });
                break;
            case 'kehadiran':
                this.apiGet('api/kehadiran.php?action=rekap').then(res => {
                    if(res.success) {
                        contentDiv.innerHTML = this.views.kehadiran(res.data);
                    }
                });
                break;
            case 'bk':
                this.apiGet('api/bk.php').then(res => {
                    if(res.success) {
                        contentDiv.innerHTML = this.views.bk(res.data);
                    }
                });
                break;
            default:
                contentDiv.innerHTML = '<h2>Halaman tidak ditemukan</h2>';
        }
    },

    views: {
        dashboard(data) {
            return `
                <div class="page-enter">
                    <div class="welcome-header-block" style="background: var(--primary-gradient); color: white; padding: 24px 20px 30px; border-radius: 0 0 28px 28px; margin: -20px -16px 20px; box-shadow: var(--shadow-primary); position: relative; overflow: hidden;">
                        <div style="position: absolute; right: -20px; bottom: -30px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255, 255, 255, 0.04); pointer-events: none;"></div>
                        <h2 style="font-size: 1.5rem; margin-bottom: 4px; color: white;">Halo, ${App.state.student.nama}</h2>
                        <p style="opacity: 0.85; font-size: 0.9rem;">NIS: ${App.state.student.nis} • Kelas: ${App.state.student.kelas}</p>
                        
                        <div style="background:rgba(255,255,255,0.08); border-radius:16px; margin-top:20px; padding:16px; display:flex; justify-content:space-between; align-items:center; border:1px solid rgba(255,255,255,0.12);">
                            <div>
                                <div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; opacity:0.8; margin-bottom:4px;">Kehadiran Bulan Ini</div>
                                <div style="font-size:1.5rem; font-weight:700;">${data.hadir || 0} <span style="font-size:1rem; font-weight:400; opacity:0.8;">Hari</span></div>
                            </div>
                            <div style="width:40px; height:40px; border-radius:12px; background:rgba(255,255,255,0.15); display:flex; align-items:center; justify-content:center;">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                        </div>
                    </div>

                    <div class="section-title" style="margin-bottom: 12px;">
                        <h3>Rekap Bulanan</h3>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin-bottom:24px;">
                        <div style="background:white; border-radius:16px; padding:16px 10px; text-align:center; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9;">
                            <div style="color:var(--warning); font-size:1.5rem; font-weight:700; margin-bottom:4px;">${data.izin || 0}</div>
                            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:500;">Izin</div>
                        </div>
                        <div style="background:white; border-radius:16px; padding:16px 10px; text-align:center; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9;">
                            <div style="color:var(--info); font-size:1.5rem; font-weight:700; margin-bottom:4px;">${data.sakit || 0}</div>
                            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:500;">Sakit</div>
                        </div>
                        <div style="background:white; border-radius:16px; padding:16px 10px; text-align:center; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9;">
                            <div style="color:var(--danger); font-size:1.5rem; font-weight:700; margin-bottom:4px;">${data.alfa || 0}</div>
                            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:500;">Alfa</div>
                        </div>
                    </div>
                </div>
            `;
        },
        izin(data) {
            let listHTML = data.length ? data.map(i => `
                <div style="background:white; border-radius:16px; padding:16px; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <div style="font-weight:600; color:var(--text-primary);">${i.tanggal_indo}</div>
                        <div class="badge" style="background: ${i.status === 'Disetujui' ? 'var(--success-light)' : (i.status === 'Ditolak' ? 'var(--danger-light)' : 'var(--warning-light)')}; color: ${i.status === 'Disetujui' ? 'var(--success)' : (i.status === 'Ditolak' ? 'var(--danger)' : 'var(--warning)')}; font-size:0.75rem; padding:4px 8px; border-radius:6px; font-weight:600;">${i.status}</div>
                    </div>
                    <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:4px;">Jenis: <span style="font-weight:500; color:var(--text-primary);">${i.jenis}</span></div>
                    <div style="font-size:0.85rem; color:var(--text-secondary); line-height:1.4;">${i.keterangan}</div>
                </div>
            `).join('') : '<div style="text-align:center; padding:30px 20px; color:var(--text-muted);"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.5; margin-bottom:12px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><br>Belum ada riwayat izin</div>';

            return `
                <div class="page-enter">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2 style="margin:0; font-size:1.5rem;">Pengajuan Izin</h2>
                        <button class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem; border-radius:10px; display:flex; align-items:center; gap:6px;" onclick="App.showIzinModal()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Buat Izin
                        </button>
                    </div>
                    ${listHTML}
                </div>
            `;
        },
        kehadiran(data) {
            let logsHTML = data.logs.length ? data.logs.map(l => `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px dashed var(--border-color);">
                    <div>
                        <div style="font-weight:600; color:var(--text-primary); font-size:1.05rem;">${l.waktu}</div>
                        <div style="font-size:0.8rem; color:var(--text-muted);">Mesin Fingerprint/ADMS</div>
                    </div>
                    <div style="font-weight:700; color:${l.status_absen == 0 ? 'var(--primary)' : 'var(--warning)'}; background:${l.status_absen == 0 ? 'var(--info-light)' : 'var(--warning-light)'}; padding:4px 12px; border-radius:20px; font-size:0.8rem;">
                        ${l.status_absen == 0 ? 'Masuk' : 'Pulang'}
                    </div>
                </div>
            `).join('') : '<div style="text-align:center; font-size:0.85rem; color:var(--text-muted); padding:20px 0;">Belum ada tap mesin hari ini</div>';

            let kelasHTML = data.kelas.length ? data.kelas.map(k => `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px dashed var(--border-color);">
                    <div>
                        <div style="font-weight:600; color:var(--text-primary);">Jam ke-${k.jam_ke}</div>
                        <div style="font-size:0.8rem; color:var(--text-muted);">Absensi Guru Mata Pelajaran</div>
                    </div>
                    <div style="font-weight:700; color:${k.status == 'H' ? 'var(--success)' : 'var(--danger)'}; font-size:1.1rem;">
                        ${k.status}
                    </div>
                </div>
            `).join('') : '<div style="text-align:center; font-size:0.85rem; color:var(--text-muted); padding:20px 0;">Belum ada absensi kelas oleh guru hari ini</div>';

            return `
                <div class="page-enter">
                    <h2 style="margin-bottom:20px; font-size:1.5rem;">Kehadiran Hari Ini</h2>
                    
                    <div style="background:white; border-radius:16px; padding:20px; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; margin-bottom:20px;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                            <div style="width:32px; height:32px; border-radius:8px; background:var(--info-light); color:var(--primary); display:flex; align-items:center; justify-content:center;">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <h3 style="margin:0; font-size:1.1rem; color:var(--text-primary);">Absen Gerbang (Mesin)</h3>
                        </div>
                        <p style="font-size:0.8rem; color:var(--text-muted); margin-top:-10px; margin-bottom:12px; margin-left:40px;">Data otomatis dari mesin sidik jari/wajah E-Absen.</p>
                        ${logsHTML}
                    </div>

                    <div style="background:white; border-radius:16px; padding:20px; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                            <div style="width:32px; height:32px; border-radius:8px; background:var(--success-light); color:var(--success); display:flex; align-items:center; justify-content:center;">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            </div>
                            <h3 style="margin:0; font-size:1.1rem; color:var(--text-primary);">Absen Kelas (Guru)</h3>
                        </div>
                        <p style="font-size:0.8rem; color:var(--text-muted); margin-top:-10px; margin-bottom:12px; margin-left:40px;">Data kehadiran yang diinput oleh Guru di tiap jam pelajaran (E-Curriculum).</p>
                        ${kelasHTML}
                    </div>
                </div>
            `;
        },
        bk(data) {
            let bkHTML = data.length ? data.map(b => `
                <div style="background:white; border-radius:16px; padding:16px; box-shadow:var(--shadow-sm); border:1.5px solid #f1f5f9; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <div style="font-weight:600; color:var(--primary); font-size:1.05rem;">${b.jenis}</div>
                        <div style="font-size:0.8rem; color:var(--text-muted);">${b.tanggal_indo}</div>
                    </div>
                    <div style="font-size:0.9rem; color:var(--text-secondary); line-height:1.5;">${b.catatan}</div>
                </div>
            `).join('') : '<div style="text-align:center; padding:30px 20px; color:var(--text-muted);"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.5; margin-bottom:12px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><br>Belum ada catatan dari guru BK.</div>';

            return `
                <div class="page-enter">
                    <h2 style="margin-bottom:20px; font-size:1.5rem;">Catatan Bimbingan Konseling</h2>
                    ${bkHTML}
                </div>
            `;
        }
    },

    showIzinModal() {
        const modalHtml = `
            <div id="izinModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center; padding:1.5rem;">
                <div class="card animate-slide-up" style="width: 100%; max-width: 400px; padding: 2rem;">
                    <h3 class="font-bold mb-4">Form Pengajuan Izin</h3>
                    <form id="formIzin" onsubmit="event.preventDefault(); App.submitIzin()">
                        <div class="form-group">
                            <label class="form-label">Tanggal</label>
                            <input type="date" id="izinTanggal" class="form-control" required value="${new Date().toISOString().split('T')[0]}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jenis</label>
                            <select id="izinJenis" class="form-control" required>
                                <option value="Sakit">Sakit</option>
                                <option value="Izin">Izin</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Keterangan / Alasan</label>
                            <textarea id="izinKet" class="form-control" required placeholder="Tuliskan alasan izin/sakit secara detail..."></textarea>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <button type="button" class="btn btn-block" style="background:#e2e8f0; color:#475569;" onclick="document.getElementById('izinModal').remove()">Batal</button>
                            <button type="submit" class="btn btn-primary btn-block">Kirim</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.getElementById('modalContainer').innerHTML = modalHtml;
    },

    submitIzin() {
        const payload = {
            tanggal: document.getElementById('izinTanggal').value,
            jenis: document.getElementById('izinJenis').value,
            keterangan: document.getElementById('izinKet').value,
        };

        this.apiPost('api/kehadiran.php?action=submit_izin', payload).then(res => {
            if(res.success) {
                document.getElementById('izinModal').remove();
                this.showToast('Pengajuan izin berhasil dikirim', 'success');
                this.loadView('izin'); // refresh
            } else {
                this.showToast(res.message, 'error');
            }
        });
    },

    apiGet(url) {
        return fetch(url, { headers: { 'Authorization': 'Bearer ' + this.state.token } })
            .then(res => res.json())
            .catch(err => {
                this.showToast('Gagal memuat data', 'error');
                return { success: false };
            });
    },

    apiPost(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + this.state.token, 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .catch(err => {
            this.showToast('Gagal mengirim data', 'error');
            return { success: false };
        });
    },

    showToast(msg, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerText = msg;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    },

    bindEvents() {
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const btn = document.getElementById('loginBtn');
                const err = document.getElementById('loginError');
                btn.classList.add('loading');
                err.innerText = '';
                
                const nis = document.getElementById('loginUsername').value;
                const dob = document.getElementById('loginPassword').value;
                
                this.login(nis, dob).finally(() => {
                    btn.classList.remove('loading');
                });
            });
        }
    }
};

// Initialize App
document.addEventListener('DOMContentLoaded', () => App.init());
