/**
 * E-Portal Authentication Module
 * Login page rendering and auth logic
 */
const Auth = {
    /**
     * Render Login Page
     */
    renderLogin(container) {
        const schoolName = App.state.school.nama || 'E-Portal Sekolah';
        const schoolIcon = App.state.school.icon;

        const logoContent = schoolIcon 
            ? `<img src="${App.baseUrl}${schoolIcon}" alt="Logo">`
            : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 20h20"/><path d="M5 20V8.5L12 4l7 4.5V20"/><path d="M9 20v-4h6v4"/>
                <path d="M9 12h1"/><path d="M14 12h1"/>
               </svg>`;

        container.innerHTML = `
        <div class="login-wrapper">
            <div class="login-bg-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
                <div class="shape shape-4"></div>
                <div class="shape shape-5"></div>
            </div>
            <div class="login-card">
                <div class="login-logo">
                    <div class="logo-icon">${logoContent}</div>
                    <h1>E-Portal</h1>
                    <p class="school-name">${App.escapeHtml(schoolName)}</p>
                </div>
                <form class="login-form" id="loginForm" autocomplete="off">
                    <div class="form-group">
                        <div class="form-input-icon">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <input type="text" class="form-input" id="loginUsername" placeholder="Username / NIS" required autocomplete="username">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-input-icon">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <input type="password" class="form-input" id="loginPassword" placeholder="Password / Tgl Lahir (DDMMYYYY)" required autocomplete="current-password">
                            <button type="button" class="password-toggle" onclick="Auth.togglePassword()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="eyeIcon">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="login-btn" id="loginBtn">
                        <span class="btn-text">Masuk</span>
                    </button>
                </form>
                <div class="login-footer">
                    <p>© ${new Date().getFullYear()} E-Portal. All rights reserved.</p>
                    <span class="version">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        v1.0.0
                    </span>
                </div>
            </div>
        </div>`;

        this.bindLoginEvents();
    },

    /**
     * Bind login form events
     */
    bindLoginEvents() {
        $('#loginForm').on('submit', (e) => {
            e.preventDefault();
            this.doLogin();
        });

        // Enter key
        $('#loginPassword').on('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.doLogin();
            }
        });

        // Focus first input
        setTimeout(() => $('#loginUsername').focus(), 500);
    },

    /**
     * Perform login
     */
    doLogin() {
        const username = $('#loginUsername').val().trim();
        const password = $('#loginPassword').val();
        const btn = document.getElementById('loginBtn');

        if (!username || !password) {
            EModal.toast({ type: 'warning', title: 'Perhatian', message: 'Username dan password harus diisi.' });
            return;
        }

        // Button loading
        EModal.btnLoading(btn, true);

        const intendedModule = sessionStorage.getItem('eportal_intended_module');

        App.api('api/auth.php?action=login', {
            method: 'POST',
            data: { username, password, intended_module: intendedModule }
        })
        .done((res) => {
            if (res.success) {
                // Penanganan khusus untuk login Siswa (E-Xam Card atau E-Performance)
                if (res.data && res.data.is_student_perf) {
                    sessionStorage.removeItem('eportal_intended_module');
                    sessionStorage.removeItem('eportal_intended_slug');
                    EModal.info({
                        type: 'success',
                        title: 'Login Berhasil',
                        message: 'Anda akan dialihkan ke E-Performance.',
                        buttonText: 'Lanjutkan',
                        onClose: () => {
                            window.location.href = App.baseUrl + res.data.redirect_url;
                        }
                    });
                    return;
                }

                if (res.data && res.data.is_student) {
                    if (res.data.status_ujian === 'OKE') {
                        EModal.info({
                            type: 'success',
                            title: 'Status Kartu Ujian : OKE',
                            message: `Halo <b>${res.data.nama}</b><br>Kelas ${res.data.kelas}<br><br>Silakan klik tombol di bawah ini untuk melihat dan mengunduh kartu ujian`,
                            buttonText: 'OK',
                            duration: 0,
                            onClose: () => {
                                window.location.href = App.baseUrl + res.data.redirect_url;
                            }
                        });
                    } else {
                        EModal.info({
                            type: 'error',
                            title: 'Status Kartu Ujian : DITANGGUHKAN',
                            message: `Halo <b>${res.data.nama}</b><br>Kelas ${res.data.kelas}<br><br>Segera hubungi wali kelas atau waka. Kesiswaan untuk informasi lebih lanjut`,
                            buttonText: 'OK',
                            duration: 0
                        });
                    }
                    return;
                }

                // Normal Login (Admin/Guru)
                App.saveState(res.data.token, res.data.user, res.data.school, res.data.academic_year);

                const intendedModule = sessionStorage.getItem('eportal_intended_module');
                const intendedSlug = sessionStorage.getItem('eportal_intended_slug');

                EModal.info({
                    type: 'success',
                    title: 'Login Berhasil!',
                    message: `Selamat datang, ${res.data.user.nama_lengkap}`,
                    buttonText: intendedModule ? 'Buka Modul' : 'Masuk ke Portal',
                    onClose: () => {
                        if (intendedModule) {
                            sessionStorage.removeItem('eportal_intended_module');
                            sessionStorage.removeItem('eportal_intended_slug');
                            const url = App.baseUrl + intendedModule + '?token=' + App.state.token;
                            window.location.href = url; // Redirect langsung ke modul
                        } else {
                            App.navigate('dashboard');
                        }
                    }
                });
            }
        })
        .fail((xhr) => {
            const msg = xhr.responseJSON?.message || 'Terjadi kesalahan. Silakan coba lagi.';
            EModal.info({
                type: 'error',
                title: 'Login Gagal',
                message: msg
            });
        })
        .always(() => {
            EModal.btnLoading(btn, false);
        });
    },

    /**
     * Toggle password visibility
     */
    togglePassword() {
        const input = document.getElementById('loginPassword');
        const icon = document.getElementById('eyeIcon');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        } else {
            input.type = 'password';
            icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
    },

    /**
     * Perform logout
     */
    logout() {
        EModal.confirm({
            title: 'Logout',
            message: 'Apakah Anda yakin ingin keluar?',
            confirmText: 'Ya, Logout',
            cancelText: 'Batal',
            type: 'warning',
            onConfirm: () => {
                const loader = EModal.loading('Logging out...');

                App.api('api/auth.php?action=logout', { method: 'POST' })
                .always(() => {
                    EModal.close(loader);
                    App.clearState();
                    App.navigate('dashboard');
                    EModal.toast({ type: 'success', title: 'Logout Berhasil', message: 'Sampai jumpa kembali!' });
                });
            }
        });
    }
};
