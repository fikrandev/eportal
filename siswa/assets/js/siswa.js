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
        
        if (token && storedStudent) {
            this.state.token = token;
            this.state.student = JSON.parse(storedStudent);
            if(window.location.pathname.includes('login.php')) {
                window.location.href = './';
            } else {
                this.renderAppShell();
                this.navigate(this.state.currentRoute);
            }
        } else {
            if(!window.location.pathname.includes('login.php')) {
                window.location.href = 'login.php';
            }
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
        return fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nis, tanggal_lahir })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Save token and data to localStorage
                localStorage.setItem('siswa_token', data.data.token);
                localStorage.setItem('siswa_data', JSON.stringify(data.data.student));
                window.location.href = './';
            } else {
                this.showToast(data.message, 'error');
            }
        })
        .catch(err => this.showToast('Koneksi gagal', 'error'));
    },

    logout() {
        this.setCookie('siswa_token', '', -1); // Clear fallback cookie just in case
        localStorage.removeItem('siswa_token');
        localStorage.removeItem('siswa_data');
        window.location.href = 'login.php';
    },

    renderAppShell() {
        const appDiv = document.getElementById('app');
        if(!appDiv) return;

        const initials = this.state.student.nama.substring(0, 2).toUpperCase();

        appDiv.innerHTML = `
            <div class="top-header">
                <div class="header-title">E-Portal</div>
                <div class="user-profile-btn" onclick="App.logout()" title="Logout">${initials}</div>
            </div>
            
            <div id="page-content" class="page-content animate-fade-in">
                <!-- Views will be loaded here -->
            </div>

            <div class="bottom-nav">
                <a href="#dashboard" class="nav-item active" data-route="dashboard">
                    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    Beranda
                </a>
                <a href="#izin" class="nav-item" data-route="izin">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Izin
                </a>
                <a href="#kehadiran" class="nav-item" data-route="kehadiran">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Absen
                </a>
                <a href="#bk" class="nav-item" data-route="bk">
                    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Catatan BK
                </a>
            </div>
        `;
    },

    setupRouter() {
        window.addEventListener('hashchange', () => {
            let hash = window.location.hash.substring(1);
            if (!hash) hash = 'dashboard';
            this.navigate(hash);
        });

        // Initialize route based on hash
        if (window.location.pathname.includes('index.php') || window.location.pathname.endsWith('siswa/')) {
            let initialHash = window.location.hash.substring(1) || 'dashboard';
            this.navigate(initialHash);
        }
    },

    navigate(route) {
        if (!this.state.student) return;
        
        this.state.currentRoute = route;
        
        // Update nav UI
        document.querySelectorAll('.nav-item').forEach(el => {
            el.classList.remove('active');
            if (el.dataset.route === route) el.classList.add('active');
        });

        const contentDiv = document.getElementById('page-content');
        if(!contentDiv) return;

        contentDiv.innerHTML = '<div class="text-center mt-4"><div class="text-muted">Memuat...</div></div>';

        // Fetch view
        this.loadView(route);
    },

    loadView(route) {
        const contentDiv = document.getElementById('page-content');
        
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
                <div class="animate-slide-up">
                    <h2 class="mb-2">Halo, ${App.state.student.nama} 👋</h2>
                    <p class="text-muted mb-4">NIS: ${App.state.student.nis} | Kelas: ${App.state.student.kelas}</p>

                    <div class="stat-card">
                        <div class="stat-label">Kehadiran Bulan Ini</div>
                        <div class="stat-value">${data.hadir || 0} <span style="font-size: 1rem">Hari</span></div>
                    </div>

                    <div class="flex gap-3 mb-4">
                        <div class="card" style="flex: 1; text-align: center;">
                            <div class="text-warning font-bold" style="font-size: 1.5rem">${data.izin || 0}</div>
                            <div class="text-muted" style="font-size: 0.8rem">Izin</div>
                        </div>
                        <div class="card" style="flex: 1; text-align: center;">
                            <div class="text-info font-bold" style="font-size: 1.5rem">${data.sakit || 0}</div>
                            <div class="text-muted" style="font-size: 0.8rem">Sakit</div>
                        </div>
                        <div class="card" style="flex: 1; text-align: center;">
                            <div class="text-danger font-bold" style="font-size: 1.5rem">${data.alfa || 0}</div>
                            <div class="text-muted" style="font-size: 0.8rem">Alfa</div>
                        </div>
                    </div>
                </div>
            `;
        },
        izin(data) {
            let listHTML = data.length ? data.map(i => `
                <div class="card mb-2 animate-fade-in">
                    <div class="flex justify-between items-center mb-2">
                        <div class="font-semibold">${i.tanggal_indo}</div>
                        <div class="badge badge-${i.status.toLowerCase()}">${i.status}</div>
                    </div>
                    <div class="text-sm text-muted">Jenis: ${i.jenis}</div>
                    <div class="text-sm">${i.keterangan}</div>
                </div>
            `).join('') : '<div class="text-center text-muted mt-4">Belum ada riwayat izin</div>';

            return `
                <div class="animate-slide-up">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold">Pengajuan Izin</h3>
                        <button class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem" onclick="App.showIzinModal()">+ Buat Izin</button>
                    </div>
                    ${listHTML}
                </div>
            `;
        },
        kehadiran(data) {
            let logsHTML = data.logs.length ? data.logs.map(l => `
                <div class="list-item">
                    <div>
                        <div class="font-semibold">${l.waktu}</div>
                        <div class="text-sm text-muted">Tap Mesin</div>
                    </div>
                    <div class="text-primary font-bold">${l.status_absen == 0 ? 'Masuk' : 'Pulang'}</div>
                </div>
            `).join('') : '<div class="text-center text-muted mt-2 mb-4">Tidak ada data mesin hari ini</div>';

            let kelasHTML = data.kelas.length ? data.kelas.map(k => `
                <div class="list-item">
                    <div>
                        <div class="font-semibold">Jam ke-${k.jam_ke}</div>
                    </div>
                    <div class="text-${k.status == 'H' ? 'success' : 'danger'} font-bold">${k.status}</div>
                </div>
            `).join('') : '<div class="text-center text-muted mt-2">Belum ada absen kelas hari ini</div>';

            return `
                <div class="animate-slide-up">
                    <h3 class="font-bold mb-3">Kehadiran Hari Ini</h3>
                    <div class="card mb-4">
                        <h4 class="font-semibold text-primary mb-2 border-b pb-2">Log Mesin Absen</h4>
                        ${logsHTML}
                    </div>
                    <div class="card">
                        <h4 class="font-semibold text-primary mb-2 border-b pb-2">Absen Kelas (Guru)</h4>
                        ${kelasHTML}
                    </div>
                </div>
            `;
        },
        bk(data) {
            let bkHTML = data.length ? data.map(b => `
                <div class="card mb-3 animate-fade-in">
                    <div class="flex justify-between items-center mb-2">
                        <div class="font-semibold text-primary">${b.jenis}</div>
                        <div class="text-sm text-muted">${b.tanggal_indo}</div>
                    </div>
                    <p class="text-sm mt-2">${b.catatan}</p>
                </div>
            `).join('') : '<div class="text-center text-muted mt-4">Belum ada catatan dari guru BK.</div>';

            return `
                <div class="animate-slide-up">
                    <h3 class="font-bold mb-4">Catatan Guru / BK</h3>
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
        // Form Login handled in login.php via inline script
    }
};

// Initialize App
document.addEventListener('DOMContentLoaded', () => App.init());
