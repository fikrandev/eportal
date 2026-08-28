/**
 * E-Performance Module — Single Page Application (SPA)
 * Elite Emerald Green — Premium Performance Evaluation System
 */

const Perf = {
    state: {
        currentRoute: 'dashboard',
        params: {},
        user: window.PERF_CONFIG.user,
        school: window.PERF_CONFIG.school,
        token: window.PERF_CONFIG.token,
        mode: window.PERF_CONFIG.mode,
        baseUrl: window.PERF_CONFIG.baseUrl,
        apiUrl: window.PERF_CONFIG.baseUrl + 'modules/e-performance/api/'
    },

    // Helpers
    getInitials(name) {
        return name ? name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) : '?';
    },

    doLogout() {
        EModal.confirm({
            title: 'Logout',
            message: 'Yakin ingin keluar dari E-Performance?',
            type: 'danger',
            confirmText: 'Ya, Logout',
            onConfirm: () => {
                const token = this.state.token;
                // Single logout call — backend now deletes from both sessions & perf_sessions
                // Send token both as header AND query param for maximum reliability
                $.ajax({
                    url: this.state.apiUrl + 'auth.php?action=logout&token=' + encodeURIComponent(token),
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token },
                    complete: () => {
                        // Set intended module so E-Portal login redirects back here
                        sessionStorage.setItem('eportal_intended_module', 'modules/e-performance/');
                        // Clear ALL localStorage keys to prevent SPA router auto-login
                        localStorage.removeItem('eportal_token');
                        localStorage.removeItem('eportal_user');
                        localStorage.removeItem('eportal_school');
                        localStorage.removeItem('eportal_academic_year');
                        localStorage.removeItem('perf_token');
                        localStorage.removeItem('perf_user');
                        // Redirect to E-Portal login page
                        window.location.href = this.state.baseUrl + '#/login';
                    }
                });
                return false;
            }
        });
    },

    showChangePasswordModal() {
        const html = `
            <div style="background-color: #FEE2E2; color: #EF4444; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; text-align: left;">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <strong>Perhatian:</strong> Password yang diubah di sini akan otomatis digunakan untuk login di <b>semua aplikasi</b> pada E-Portal.
            </div>
            <div class="form-group">
                <label>Password Lama <span class="pf-required">*</span></label>
                <input type="password" class="form-input" id="chOldPass" required placeholder="Masukkan password lama">
            </div>
            <div class="form-group">
                <label>Password Baru <span class="pf-required">*</span></label>
                <input type="password" class="form-input" id="chNewPass" required placeholder="Masukkan password baru">
            </div>
            <div class="form-group">
                <label>Konfirmasi Password Baru <span class="pf-required">*</span></label>
                <input type="password" class="form-input" id="chConfirmPass" required placeholder="Ketik ulang password baru">
            </div>
            <div class="form-group" style="margin-top: 10px;">
                <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal;">
                    <input type="checkbox" id="chShowPass" style="margin-right: 8px;" onchange="
                        const type = this.checked ? 'text' : 'password';
                        document.getElementById('chOldPass').type = type;
                        document.getElementById('chNewPass').type = type;
                        document.getElementById('chConfirmPass').type = type;
                    "> Lihat Password
                </label>
            </div>
        `;
        EModal.form({
            title: 'Ubah Password',
            form: html,
            type: 'default',
            confirmText: 'Simpan Password',
            onConfirm: () => {
                const old_password = $('#chOldPass').val();
                const new_password = $('#chNewPass').val();
                const confirm_password = $('#chConfirmPass').val();

                if (!old_password || !new_password || !confirm_password) {
                    EModal.toast({type:'warning', message: 'Semua kolom wajib diisi'});
                    return false; // prevent modal close
                }

                if (new_password !== confirm_password) {
                    EModal.toast({type:'error', message: 'Konfirmasi password baru tidak cocok'});
                    return false;
                }

                this.api('auth.php?action=change_password', {
                    method: 'POST',
                    contentType: 'application/x-www-form-urlencoded',
                    data: {
                        old_password: old_password,
                        new_password: new_password
                    }
                }).done(res => {
                    if (res.success) {
                        EModal.toast({type:'success', message: 'Password berhasil diubah. Seluruh login sekarang menggunakan password baru.'});
                        EModal.closeAll();
                    } else {
                        EModal.alert('Gagal', res.message);
                    }
                }).fail(xhr => {
                    let msg = 'Terjadi kesalahan sistem';
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.message) msg = res.message;
                    } catch(e) {}
                    EModal.alert('Error', msg);
                });
                return false; // manual close on success
            }
        });
    },

    init() {
        console.log('E-Performance Script Loaded (v2)');
        try {
            this.bindEvents();
            this.renderSidebar();
            this.loadRouteFromHash();

            setTimeout(() => {
                $('#globalLoader').addClass('hidden');
                setTimeout(() => $('#globalLoader').remove(), 500);
            }, 500);
        } catch(e) {
            console.error('Init failed', e);
            $('#globalLoader').remove();
        }
    },

    bindEvents() {
        window.addEventListener('hashchange', () => this.loadRouteFromHash());
        $('#menuToggle').on('click', () => this.toggleSidebar());
        $('#sidebarOverlay').on('click', () => this.toggleSidebar(false));
    },

    escapeHtml(unsafe) {
        return (unsafe || '').toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    },

    api(endpoint, options = {}) {
        let ct = options.contentType !== undefined 
            ? options.contentType 
            : (options.data instanceof FormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8');
        return $.ajax({
            url: this.state.apiUrl + endpoint,
            method: options.method || 'GET',
            data: options.data,
            processData: !(options.data instanceof FormData) && options.contentType !== 'application/json',
            contentType: ct,
            headers: { 'Authorization': 'Bearer ' + this.state.token }
        }).fail(xhr => {
            if (xhr.status === 401) {
                EModal.toast({ type: 'error', title: 'Sesi Berakhir', message: 'Silakan masuk kembali.' });
            }
        });
    },

    // ==============================================
    // SIDEBAR (Role-based)
    // ==============================================
    renderSidebar() {
        const u = this.state.user;
        const role = u.role;
        const perms = u.permissions || [];
        const isAdmin = (role === 'admin' || role === 'superadmin');
        let navHtml = '';

        if (isAdmin || perms.includes('dashboard') || perms.length === 0) {
            navHtml += `
                <div class="pf-nav-group">
                    <div class="pf-nav-label">Menu Utama</div>
                    <button class="pf-nav-item" data-route="dashboard">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Dashboard
                    </button>
                </div>`;
        }

        let masterHtml = '';
        if (isAdmin || perms.includes('ptk')) {
            masterHtml += `
                <button class="pf-nav-item" data-route="ptk">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Data PTK
                </button>`;
        }
        if (isAdmin || perms.includes('siswa')) {
            masterHtml += `
                <button class="pf-nav-item" data-route="siswa">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Data Siswa
                </button>`;
        }
        if (masterHtml) {
            navHtml += `<div class="pf-nav-group"><div class="pf-nav-label">Data Master</div>${masterHtml}</div>`;
        }

        let settingsHtml = '';
        if (isAdmin || perms.includes('periode')) {
            settingsHtml += `
                <button class="pf-nav-item" data-route="periode">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Periode Penilaian
                </button>`;
        }
        if (isAdmin || perms.includes('instrumen')) {
            settingsHtml += `
                <button class="pf-nav-item" data-route="instrumen">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Buat Penilaian
                </button>`;
        }
        if (isAdmin || perms.includes('progress')) {
            settingsHtml += `
                <button class="pf-nav-item" data-route="progress">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Progress Penilaian
                </button>`;
        }
        if (isAdmin || perms.includes('hasil')) {
            settingsHtml += `
                <button class="pf-nav-item" data-route="hasil">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Hasil Penilaian
                </button>`;
        }
        if (isAdmin) {
            settingsHtml += `
                <button class="pf-nav-item" data-route="acak_penilai">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    Acak Penilai
                </button>`;
            settingsHtml += `
                <button class="pf-nav-item" data-route="deskripsi">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Deskripsi Penilaian
                </button>`;
        }
        if (settingsHtml) {
            navHtml += `<div class="pf-nav-group"><div class="pf-nav-label">Pengaturan</div>${settingsHtml}</div>`;
        }

        let assessHtml = '';
        if (perms.includes('penilaian') || (!isAdmin && ['kepsek','guru','tu','it','pustakawan','siswa'].includes(role) && perms.length === 0)) {
            assessHtml += `
                <button class="pf-nav-item" data-route="penilaian">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Isi Penilaian
                </button>`;
        }
        if (assessHtml) {
            navHtml += `<div class="pf-nav-group"><div class="pf-nav-label">Penilaian</div>${assessHtml}</div>`;
        }

        let ptkHasilHtml = '';
        if (!isAdmin && ['kepsek','guru','tu','it','pustakawan'].includes(role) && perms.length === 0) {
            ptkHasilHtml += `
                <button class="pf-nav-item" data-route="hasil_saya">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Hasil Penilaian Saya
                </button>`;
        }
        if (ptkHasilHtml) {
            navHtml += `<div class="pf-nav-group"><div class="pf-nav-label">Laporan</div>${ptkHasilHtml}</div>`;
        }

        let sysHtml = '';
        if (isAdmin || perms.includes('akses_modul')) {
            sysHtml += `
                <button class="pf-nav-item" data-route="akses_modul">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Akses Modul
                </button>`;
        }
        if (sysHtml) {
            navHtml += `<div class="pf-nav-group"><div class="pf-nav-label">Sistem</div>${sysHtml}</div>`;
        }

        $('#sidebarNav').html(navHtml);
        $('.pf-nav-item').on('click', (e) => {
            this.navigate($(e.currentTarget).data('route'));
        });

        $('#sidebarAvatar').text(this.getInitials(u.nama_lengkap));
        $('#sidebarUserName').text(u.nama_lengkap);
        const roleLabels = { admin:'Administrator', kepsek:'Kepala Sekolah', guru:'Guru', siswa:'Siswa', tu:'Tata Usaha', it:'IT-Support', pustakawan:'Pustakawan' };
        $('#sidebarUserRole').text(u.tupoksi || roleLabels[role] || role);
    },

    // ==============================================
    // PHASE 2 - ISI PENILAIAN
    // ==============================================
    renderPenilaian($c) {
        const tupoksi = (this.state.user.tupoksi || '').toLowerCase();
        if (tupoksi === 'kepala sekolah') {
            this.renderMatrixKepsek($c);
            return;
        }

        $c.html(`
            <div class="pf-card">
                <div class="pf-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Daftar Target Penilaian</h3>
                </div>
                <div class="pf-card-body" id="targetContainer">
                    <div class="pf-empty">Memuat daftar...</div>
                </div>
            </div>
        `);
        this.loadTargetPenilaian();
    },

    loadTargetPenilaian() {
        this.api('penilaian.php?action=list_target&_t=' + Date.now(), {method: 'GET'}).done(res => {
            const data = res.data.targets ? res.data.targets : (res.data || []);
            const is_open = res.data.is_open !== false; // default true
            const msg_tutup = res.data.msg || 'Periode Ditutup';

            if (!data.length) {
                $('#targetContainer').html('<div class="pf-empty">Belum ada target penilaian untuk Anda di periode ini.</div>');
                return;
            }

            let html = '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:16px;">';
            data.forEach(t => {
                let badgeColor = t.target_type === 'Diri Sendiri' ? 'pf-badge-green' : (t.target_type === 'Teman Sejawat' ? 'pf-badge-blue' : 'pf-badge-purple');
                
                html += `
                <div style="border:1px solid #E5E7EB; border-radius:8px; padding:16px; display:flex; flex-direction:column; background:#fff; transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <div>
                            <div style="font-weight:600; font-size:16px; color:#111827;">${t.nama}</div>
                            <div style="font-size:13px; color:var(--text-muted); margin-top:2px;">${t.jenis_ptk}${t.mata_pelajaran && t.mata_pelajaran !== '-' ? ' — ' + t.mata_pelajaran : ''}</div>
                        </div>
                        <span class="pf-badge ${badgeColor}">${t.target_type}</span>
                    </div>
                    
                    ${t.progress_total > 0 ? `
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text-muted); margin-bottom:4px;">
                            <span>Progress</span>
                            <span>${t.progress_answered}/${t.progress_total}</span>
                        </div>
                        <div style="width:100%; height:6px; background:#E5E7EB; border-radius:3px; overflow:hidden;">
                            <div style="width:${Math.round((t.progress_answered/t.progress_total)*100)}%; height:100%; background:${t.is_completed ? '#10B981' : 'var(--primary)'}; border-radius:3px; transition:width 0.3s;"></div>
                        </div>
                    </div>` : ''}
                    
                    <div style="margin-top:auto; padding-top:16px;">
                        ${t.is_completed ? `
                        <button class="btn" style="width:100%; display:flex; justify-content:center; align-items:center; background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0;" onclick="Perf.openFormPenilaian(${t.target_id}, '${t.nama}', '${t.jenis_ptk}', true)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Sudah Selesai — Lihat Hasil
                        </button>` : !is_open ? `
                        <button class="btn" disabled style="width:100%; display:flex; justify-content:center; align-items:center; background:#F3F4F6; color:#9CA3AF; border:1px solid #E5E7EB; cursor:not-allowed;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            ${msg_tutup}
                        </button>
                        ` : t.progress_answered > 0 ? `
                        <button class="btn btn-primary" style="width:100%; display:flex; justify-content:center; align-items:center; background:#F59E0B; border-color:#F59E0B;" onclick="Perf.openFormPenilaian(${t.target_id}, '${t.nama}', '${t.jenis_ptk}', false)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                            Lanjutkan Penilaian
                        </button>` : `
                        <button class="btn btn-primary" style="width:100%; display:flex; justify-content:center; align-items:center;" onclick="Perf.openFormPenilaian(${t.target_id}, '${t.nama}', '${t.jenis_ptk}', false)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> 
                            Mulai Isi Penilaian
                        </button>`}
                    </div>
                </div>
                `;
            });
            html += '</div>';
            $('#targetContainer').html(html);
        }).fail(xhr => {
            const msg = xhr.responseJSON?.message || 'Gagal memuat target penilaian.';
            $('#targetContainer').html(`
                <div class="pf-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <h3 style="color:var(--danger)">${msg}</h3>
                </div>
            `);
        });
    },

    openFormPenilaian(target_id, nama, jenis_ptk, is_completed = false) {
        // Fetch questions and answers (backend auto-detects active periode_id if not sent)
        let url = `penilaian.php?action=get_form&target_id=${target_id}&target_jenis=${encodeURIComponent(jenis_ptk)}&_t=${Date.now()}`;
        if (this.state.selectedPeriodeId) {
            url += `&periode_id=${this.state.selectedPeriodeId}`;
        }
        
        this.api(url).done(res => {
            if(!res.success) {
                EModal.alert('Gagal', res.message);
                return;
            }
            
            const q = res.data.questions || [];
            const a = res.data.answers || {};
            const pt = res.data.penilai_type || '';
            
            this.renderFormPenilaian(target_id, nama, jenis_ptk, pt, q, a, is_completed);
        });
    },

    renderFormPenilaian(target_id, nama, jenis_ptk, penilai_type, questions, answers, is_completed = false) {
        if(!questions.length) {
            EModal.alert('Kosong', 'Belum ada instrumen pertanyaan untuk Anda.');
            return;
        }
        
        // Group by category
        const groups = {};
        questions.forEach(q => {
            if(!groups[q.kategori]) groups[q.kategori] = [];
            groups[q.kategori].push(q);
        });
        
        let html = `
        <div class="pf-card" style="margin-bottom:20px; animation: fadeIn 0.3s ease;">
            <div class="pf-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3>Formulir Penilaian Kinerja</h3>
                    <p style="margin-top:4px; color:var(--text-muted);">Menilai: <strong>${nama}</strong> <span class="pf-badge pf-badge-purple" style="margin-left:8px; font-size:12px;">${jenis_ptk}</span></p>
                </div>
                <button class="btn btn-outline" onclick="Perf.renderPenilaian($('#mainContent'))">← Kembali</button>
            </div>
            <div class="pf-card-body">
                <form id="formPenilaian" data-target-id="${target_id}" data-penilai-type="${penilai_type}">
        `;
        
        let no = 1;
        for(const kat in groups) {
            html += `<h4 style="margin-top:24px; margin-bottom:16px; color:var(--primary); border-bottom:2px solid #E5E7EB; padding-bottom:8px; font-weight:600;">Kategori: ${kat}</h4>`;
            
            groups[kat].forEach(q => {
                const ans = answers[q.instrumen_id];
                const nilai = ans ? parseInt(ans.nilai) : 0;
                
                html += `
                <div style="margin-bottom:24px; padding:20px; background:#F9FAFB; border-radius:12px; border:1px solid #E5E7EB;">
                    <div style="font-weight:500; margin-bottom:16px; font-size:15.5px; color:#1F2937; line-height:1.5;">${no}. ${q.pertanyaan}</div>
                    
                    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:8px;">
                        ${[1,2,3,4,5].map(n => `
                            <label class="pf-radio-btn" style="flex:1; min-width:140px; display:flex; justify-content:center; align-items:center; ${is_completed?'cursor:not-allowed':'cursor:pointer'}; text-align:center; background:${nilai===n?'#ECFDF5':'#fff'}; padding:12px 16px; border-radius:8px; border:2px solid ${nilai===n?'var(--primary)':'#D1D5DB'}; font-weight:${nilai===n?'600':'500'}; color:${nilai===n?'var(--primary)':'#4B5563'}; transition:all 0.2s;">
                                <input type="radio" name="q_${q.instrumen_id}" value="${n}" ${nilai===n?'checked':''} ${is_completed?'disabled':''} style="display:none;">
                                ${n === 1 ? 'Tidak Pernah' : n === 2 ? 'Jarang' : n === 3 ? 'Kadang-kadang' : n === 4 ? 'Sering' : 'Selalu'}
                            </label>
                        `).join('')}
                    </div>
                </div>
                `;
                no++;
            });
        }
        
        html += `
                    <div style="margin-top:32px; display:flex; justify-content:flex-end; gap:12px; border-top:1px solid #E5E7EB; padding-top:24px;">
                        <button type="button" class="btn btn-outline" onclick="Perf.renderPenilaian($('#mainContent'))">Kembali</button>
                        ${!is_completed ? `
                        <button type="submit" class="btn btn-primary" id="btnSubmitForm" style="padding:10px 24px; font-size:16px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Simpan Penilaian
                        </button>
                        ` : ''}
                    </div>
                </form>
            </div>
        </div>
        `;
        
        $('#mainContent').html(html);
        
        // Custom styling for radio buttons selection
        $('#formPenilaian input[type="radio"]').on('change', function() {
            const name = $(this).attr('name');
            $(`input[name="${name}"]`).parent().css({
                'border-color':'#D1D5DB', 
                'color':'#4B5563', 
                'background':'#fff',
                'font-weight':'500'
            });
            $(this).parent().css({
                'border-color':'var(--primary)', 
                'color':'var(--primary)', 
                'background':'#ECFDF5',
                'font-weight':'600'
            });
        });
        
        // Bind form submit
        $('#formPenilaian').on('submit', function(e) {
            e.preventDefault();
            const tid = $(this).data('target-id');
            const pt = $(this).data('penilai-type');
            Perf._doSubmitPenilaian(this, tid, pt);
        });
    },
    
    _doSubmitPenilaian(formEl, target_id, penilai_type) {
        const answers = [];
        const data = new FormData(formEl);
        
        for(let [k,v] of data.entries()) {
            if(k.startsWith('q_')) {
                answers.push({
                    instrumen_id: k.replace('q_', ''),
                    nilai: v,
                    catatan: ''
                });
            }
        }
        
        $('#btnSubmitForm').html('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pf-spin" style="margin-right:8px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Menyimpan...').prop('disabled', true);
        
        let payload = {
            target_id: target_id,
            penilai_type: penilai_type,
            answers: answers
        };
        if (this.state.selectedPeriodeId) {
            payload.periode_id = this.state.selectedPeriodeId;
        }

        this.api('penilaian.php?action=submit', {
            method: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json'
        }).done(res => {
            if(res.success) {
                EModal.toast({type:'success', message: 'Penilaian berhasil disimpan'});
                this.renderPenilaian($('#mainContent'));
            } else {
                EModal.alert('Gagal', res.message);
                $('#btnSubmitForm').html('Simpan Penilaian').prop('disabled', false);
            }
        }).fail(() => {
            $('#btnSubmitForm').html('Simpan Penilaian').prop('disabled', false);
            EModal.alert('Error', 'Terjadi kesalahan jaringan.');
        });
    },

    // ==============================================
    // KEPSEK MATRIX VIEW
    // ==============================================
    renderMatrixKepsek($c) {
        $c.html(`
            <div class="pf-card">
                <div class="pf-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg> Matriks Penilaian Kepala Sekolah</h3>
                </div>
                <div class="pf-card-body" style="max-width: 100%; overflow: hidden;">
                    <div id="kepsekMatrixLoading" class="pf-empty">Memuat matriks penilaian...</div>
                    <div id="kepsekMatrixContainer" style="display:none; max-width: 100%;"></div>
                </div>
            </div>
        `);

        let url = 'penilaian.php?action=get_matrix_kepsek&_t=' + Date.now();
        if (this.state.selectedPeriodeId) url += '&periode_id=' + this.state.selectedPeriodeId;

        this.api(url).done(res => {
            $('#kepsekMatrixLoading').hide();
            if (!res.success) {
                $('#kepsekMatrixContainer').html('<div class="pf-empty">Gagal memuat data matriks.</div>').show();
                return;
            }

            const data = res.data;
            const tabs = data.tabs;
            const answers = data.answers;
            const periode_id = data.periode_id;

            if (Object.keys(tabs).length === 0) {
                $('#kepsekMatrixContainer').html('<div class="pf-empty">Tidak ada target penilaian untuk Kepala Sekolah di periode ini.</div>').show();
                return;
            }

            let tabsHtml = '<div class="pf-tabs" style="display:flex; border-bottom:2px solid #E5E7EB; margin-bottom:16px; overflow-x:auto;">';
            let contentsHtml = '';

            let first = true;
            for (const tupoksi in tabs) {
                const activeClass = first ? 'active' : '';
                const display = first ? 'block' : 'none';
                
                tabsHtml += `<button class="pf-tab-btn ${activeClass}" data-tab="${tupoksi}" style="padding:12px 24px; background:none; border:none; border-bottom:2px solid transparent; font-weight:600; color:#4B5563; cursor:pointer; white-space:nowrap; transition:all 0.2s; margin-bottom:-2px;">${tupoksi} (${tabs[tupoksi].teachers.length})</button>`;
                
                let tableHtml = this.buildMatrixTableHtml(tabs[tupoksi].teachers, tabs[tupoksi].questions, answers, periode_id);
                contentsHtml += `<div class="pf-tab-content" id="tab-${tupoksi.replace(/\s+/g, '-')}" style="display:${display}; max-width: 100%;">${tableHtml}</div>`;
                
                first = false;
            }
            tabsHtml += '</div>';

            $('#kepsekMatrixContainer').html(tabsHtml + contentsHtml).show();

            // Setup custom styling and event listeners
            $('.pf-tab-btn').on('click', function() {
                $('.pf-tab-btn').css({ 'border-bottom-color':'transparent', 'color':'#4B5563' }).removeClass('active');
                $(this).css({ 'border-bottom-color':'var(--primary)', 'color':'var(--primary)' }).addClass('active');
                
                $('.pf-tab-content').hide();
                $('#tab-' + $(this).data('tab').replace(/\s+/g, '-')).fadeIn(200);
            });

            $('.pf-tab-btn.active').css({ 'border-bottom-color':'var(--primary)', 'color':'var(--primary)' });

            // Event listener for autosave
            // Inisialisasi state untuk radio yang sudah terpilih
            $('.matrix-radio:checked').data('waschecked', true);

            $('.matrix-radio').on('click', function(e) {
                let $radio = $(this);
                let wasChecked = $radio.data('waschecked') === true;
                
                if (wasChecked) {
                    $radio.prop('checked', false);
                    $radio.data('waschecked', false);
                    $radio.trigger('change');
                } else {
                    $(`input[name="${$radio.attr('name')}"]`).data('waschecked', false);
                    $radio.data('waschecked', true);
                }
            });

            $('.matrix-radio').on('change', function() {
                const target_id = $(this).data('target');
                const instrumen_id = $(this).data('instrumen');
                const isChecked = $(this).prop('checked');
                const nilai = isChecked ? $(this).val() : 0;
                
                const $cell = $(this).closest('td');
                const $indicator = $cell.find('.save-indicator');
                
                // Styling active label
                $cell.find('label').css({'background':'#fff', 'border-color':'#D1D5DB', 'color':'#4B5563'});
                if (isChecked) {
                    $(this).parent('label').css({'background':'#ECFDF5', 'border-color':'var(--primary)', 'color':'var(--primary)'});
                }
                
                $indicator.html('<span style="color:#F59E0B; font-size:11px;">Menyimpan...</span>').show();

                Perf.api('penilaian.php?action=submit_matrix_single', {
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        periode_id: periode_id,
                        target_id: target_id,
                        instrumen_id: instrumen_id,
                        nilai: nilai
                    })
                }).done(saveRes => {
                    if (saveRes.success) {
                        $indicator.html('<span style="color:#10B981; font-size:11px;">✓ ' + (nilai == 0 ? 'Dibatalkan' : 'Tersimpan') + '</span>');
                        setTimeout(() => $indicator.fadeOut(), 2000);
                    } else {
                        $indicator.html('<span style="color:#EF4444; font-size:11px;">✗ Gagal</span>');
                    }
                }).fail(() => {
                    $indicator.html('<span style="color:#EF4444; font-size:11px;">✗ Error</span>');
                });
            });
        }).fail((jqXHR, textStatus, errorThrown) => {
            console.error("API Error: ", textStatus, errorThrown, jqXHR.responseText);
            let errMsg = textStatus;
            try { 
                const errRes = JSON.parse(jqXHR.responseText); 
                if (errRes && errRes.message) errMsg = errRes.message; 
            } catch(e){}
            $('#kepsekMatrixLoading').hide();
            $('#kepsekMatrixContainer').html(`<div class="pf-empty" style="color:#EF4444;">Terjadi kesalahan sistem saat memuat data matriks. (${errMsg})</div>`).show();
        });

        // Event listener for massal apply
        $('#kepsekMatrixContainer').on('click', '.matrix-apply-all-btn', function() {
            const instrumen_id = $(this).data('instrumen');
            const nilai = $(this).siblings('.matrix-apply-all-select').val();
            const $indicator = $(this).siblings('.matrix-apply-indicator');
            
            if (!nilai) {
                EModal.toast({type:'warning', message:'Pilih nilai terlebih dahulu!'});
                return;
            }

            // Find all target ids in this row within the CURRENT tab's table
            const $table = $(this).closest('table');
            const $radios = $table.find(`input.matrix-radio[data-instrumen="${instrumen_id}"][value="${nilai}"]`);
            if ($radios.length === 0) return;

            let targets = [];
            $radios.each(function() {
                targets.push($(this).data('target'));
            });

            // Disable button during process
            const $btn = $(this);
            $btn.prop('disabled', true).css('opacity', '0.5');
            $indicator.html('<span style="color:#F59E0B;">Memproses...</span>').show();

            const periode_id = $(this).data('periode') || window.Perf?.state?.selectedPeriodeId || 0;

            Perf.api('penilaian.php?action=submit_matrix_massal', {
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    periode_id: periode_id,
                    instrumen_id: instrumen_id,
                    nilai: nilai,
                    targets: targets
                })
            }).done(saveRes => {
                $btn.prop('disabled', false).css('opacity', '1');
                if (saveRes.success) {
                    $indicator.html('<span style="color:#10B981;">✓ Sukses</span>');
                    
                    // Update UI for all radios
                    $radios.each(function() {
                        const $cell = $(this).closest('td');
                        $cell.find('label').css({'background':'#fff', 'border-color':'#D1D5DB', 'color':'#4B5563'});
                        $(this).parent('label').css({'background':'#ECFDF5', 'border-color':'var(--primary)', 'color':'var(--primary)'});
                        $(this).prop('checked', true);
                    });

                    setTimeout(() => $indicator.fadeOut(), 2000);
                } else {
                    $indicator.html('<span style="color:#EF4444;">✗ Gagal</span>');
                }
            }).fail(xhr => {
                $btn.prop('disabled', false).css('opacity', '1');
                let errMsg = 'Error';
                try { const errRes = JSON.parse(xhr.responseText); if (errRes && errRes.message) errMsg = errRes.message; } catch(e){}
                $indicator.html('<span style="color:#EF4444;">✗ Gagal</span>');
                EModal.toast({type:'error', message: errMsg});
            });
        });
    },

    buildMatrixTableHtml(teachers, questions, answers, periode_id) {
        if (!questions.length) return '<div class="pf-empty">Belum ada instrumen pertanyaan.</div>';
        if (!teachers.length) return '<div class="pf-empty">Belum ada PTK di tupoksi ini.</div>';

        let html = `
            <div style="width:100%; max-width:100%; overflow:auto; max-height:70vh; border:1px solid #E5E7EB; border-radius:8px; position:relative; box-sizing:border-box;">
                <table style="width:max-content; min-width:100%; border-collapse:separate; border-spacing:0; text-align:left; font-size:14px;">
                    <thead>
                        <tr>
                            <th style="position:sticky; top:0; left:0; z-index:20; background:#F9FAFB; padding:12px; border-bottom:2px solid #E5E7EB; border-right:2px solid #E5E7EB; width:350px; min-width:300px; max-width:400px; box-shadow:2px 2px 5px rgba(0,0,0,0.05); white-space:normal;">No. Pertanyaan</th>
        `;

        // Column headers for each teacher
        teachers.forEach(t => {
            html += `<th style="position:sticky; top:0; z-index:10; background:#F9FAFB; padding:12px; border-bottom:2px solid #E5E7EB; border-right:1px solid #E5E7EB; min-width:200px; text-align:center;">
                        <div style="font-weight:600; color:#111827; margin-bottom:4px;">${t.nama}</div>
                        <div style="font-size:11px; color:#6B7280; font-weight:normal;">${t.niy || t.jenis_ptk}</div>
                     </th>`;
        });
        
        html += `       </tr>
                    </thead>
                    <tbody>`;

        let no = 1;
        questions.forEach(q => {
            html += `<tr>
                        <td style="position:sticky; left:0; z-index:5; background:#fff; padding:12px; border-bottom:1px solid #E5E7EB; border-right:2px solid #E5E7EB; box-shadow:2px 0 5px rgba(0,0,0,0.02); width:350px; min-width:300px; max-width:400px; white-space:normal;">
                            <div style="font-weight:500; color:#374151;">${no}. ${q.pertanyaan}</div>
                            <div style="font-size:11px; color:#9CA3AF; margin-top:4px;">Kategori: ${q.kategori}</div>
                            <div style="margin-top:8px; display:flex; align-items:center; gap:4px; font-size:11px; background:#F3F4F6; padding:6px; border-radius:4px; width:fit-content;">
                                <span style="color:#4B5563; font-weight:500;">Isi masal:</span>
                                <select class="matrix-apply-all-select" data-instrumen="${q.instrumen_id}" style="padding:2px 6px; border:1px solid #D1D5DB; border-radius:4px; outline:none;">
                                    <option value="">-Nilai-</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                                <button type="button" class="matrix-apply-all-btn" data-periode="${periode_id}" data-instrumen="${q.instrumen_id}" style="padding:2px 8px; font-size:11px; background:var(--primary); color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:500;">Terapkan</button>
                                <span class="matrix-apply-indicator" data-instrumen="${q.instrumen_id}" style="margin-left:4px; display:none;"></span>
                            </div>
                        </td>`;
            
            teachers.forEach(t => {
                const key = `${q.instrumen_id}_${t.id}`;
                const val = answers[key] || 0;
                
                html += `<td style="padding:12px; border-bottom:1px solid #E5E7EB; border-right:1px solid #E5E7EB; text-align:center; vertical-align:middle; position:relative;">`;
                html += `<div style="display:flex; justify-content:center; gap:4px; flex-wrap:wrap;">`;
                
                [1,2,3,4,5].forEach(n => {
                    const isChecked = val === n;
                    const labelText = n === 1 ? 'Tidak Pernah' : n === 2 ? 'Jarang' : n === 3 ? 'Kadang-kadang' : n === 4 ? 'Sering' : 'Selalu';
                    html += `
                        <label style="cursor:pointer; padding:6px 4px; border-radius:4px; border:1px solid ${isChecked ? 'var(--primary)' : '#D1D5DB'}; background:${isChecked ? '#ECFDF5' : '#fff'}; color:${isChecked ? 'var(--primary)' : '#4B5563'}; transition:all 0.1s; flex:1; text-align:center; min-width:30px; display:flex; flex-direction:column; justify-content:center; align-items:center;" title="Nilai ${n}: ${labelText}">
                            <input type="radio" class="matrix-radio" name="q_${key}" value="${n}" data-target="${t.id}" data-instrumen="${q.instrumen_id}" ${isChecked ? 'checked' : ''} style="display:none;">
                            <span style="font-size:14px; font-weight:700;">${n}</span>
                            <span style="font-size:8px; line-height:1.1; margin-top:4px; opacity:0.85; max-width:100%; word-break:break-word;">${labelText}</span>
                        </label>`;
                });
                
                html += `</div>
                         <div class="save-indicator" data-periode="${periode_id}" style="position:absolute; bottom:2px; right:8px; display:none;"></div>
                         </td>`;
            });

            html += `</tr>`;
            no++;
        });

        html += `   </tbody>
                </table>
            </div>
            <div style="margin-top:12px; color:var(--text-muted); font-size:13px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:middle; margin-right:4px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Perubahan nilai akan disimpan secara otomatis (auto-save). Anda bebas berpindah antar tab.
            </div>`;
        return html;
    },

    renderMatrixManual($c) {
        $c.html(`
            <div class="pf-card" style="margin-bottom: 24px;">
                <div class="pf-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h2 style="font-size: 20px; color: #1F2937; margin: 0 0 4px 0;">Penilaian Lainnya</h2>
                        <div style="font-size: 14px; color: #6B7280;">Isi nilai secara massal berdasarkan tupoksi.</div>
                    </div>
                    <button class="btn btn-outline" onclick="Perf.renderLaporan($('#mainContent'))">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        Kembali ke Laporan
                    </button>
                </div>
                <div class="pf-card-body" id="manualMatrixContainer" style="display:none; padding-top:16px;">
                    <div style="text-align:center; padding:40px; color:#6B7280;">
                        <svg class="pf-spin" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" style="margin-bottom:16px;"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10"/></svg>
                        <div>Memuat matriks nilai...</div>
                    </div>
                </div>
            </div>
        `);

        $('#manualMatrixContainer').show();

        const pid = this.state.selectedPeriodeId;
        if (!pid) {
            $('#manualMatrixContainer').html('<div class="pf-empty">Silakan pilih periode aktif terlebih dahulu di halaman sebelumnya.</div>').show();
            return;
        }

        this.api(`manual_penilaian.php?action=get_matrix_manual&periode_id=${pid}`).done(res => {
            if (!res.success) {
                $('#manualMatrixContainer').html('<div class="pf-empty">Gagal memuat data matriks manual.</div>').show();
                return;
            }

            const data = res.data;
            const tabs = data.tabs;
            const answers = data.answers;
            const periode_id = data.periode_id;

            if (Object.keys(tabs).length === 0) {
                $('#manualMatrixContainer').html('<div class="pf-empty">Tidak ada target PTK yang tersedia.</div>').show();
                return;
            }

            let tabsHtml = '<div class="pf-tabs" style="display:flex; border-bottom:2px solid #E5E7EB; margin-bottom:16px; overflow-x:auto;">';
            let contentsHtml = '';

            let first = true;
            for (const tupoksi in tabs) {
                const activeClass = first ? 'active' : '';
                const display = first ? 'block' : 'none';
                
                tabsHtml += `<button class="pf-tab-btn manual-matrix-tab ${activeClass}" data-tab="manual-${tupoksi.replace(/\s+/g, '-')}" style="padding:12px 24px; background:none; border:none; border-bottom:2px solid transparent; font-weight:600; color:#4B5563; cursor:pointer; white-space:nowrap; transition:all 0.2s; margin-bottom:-2px;">${tupoksi} (${tabs[tupoksi].teachers.length})</button>`;
                
                let tableHtml = this.buildMatrixManualTableHtml(tabs[tupoksi].teachers, tabs[tupoksi].questions, answers, periode_id);
                contentsHtml += `<div class="pf-tab-content" id="tab-manual-${tupoksi.replace(/\s+/g, '-')}" style="display:${display}; max-width: 100%;">${tableHtml}</div>`;
                
                first = false;
            }
            tabsHtml += '</div>';

            $('#manualMatrixContainer').html(tabsHtml + contentsHtml).show();

            // Setup custom styling and event listeners
            $('.manual-matrix-tab').on('click', function() {
                $('.manual-matrix-tab').css({ 'border-bottom-color':'transparent', 'color':'#4B5563' }).removeClass('active');
                $(this).css({ 'border-bottom-color':'var(--primary)', 'color':'var(--primary)' }).addClass('active');
                
                $('.pf-tab-content').hide();
                $('#tab-' + $(this).data('tab').replace('tab-', '')).fadeIn(200);
            });

            $('.manual-matrix-tab.active').css({ 'border-bottom-color':'var(--primary)', 'color':'var(--primary)' });

            // Event listener for autosave on blur
            $('.matrix-manual-input').on('blur', function() {
                const target_id = $(this).data('target');
                const instrumen_id = $(this).data('instrumen');
                const nilai = parseFloat($(this).val()) || 0;
                
                const $cell = $(this).closest('td');
                const $indicator = $cell.find('.save-indicator');
                
                $indicator.html('<span style="color:#F59E0B; font-size:11px;">Menyimpan...</span>').show();

                Perf.api('manual_penilaian.php?action=submit_matrix_single', {
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        periode_id: periode_id,
                        target_id: target_id,
                        instrumen_id: instrumen_id,
                        nilai: nilai
                    })
                }).done(saveRes => {
                    if (saveRes.success) {
                        $indicator.html('<span style="color:#10B981; font-size:11px;">✓ Tersimpan</span>');
                        $(this).css('border-color', '#10B981');
                        setTimeout(() => {
                            $indicator.fadeOut();
                            $(this).css('border-color', '#E5E7EB');
                        }, 2000);
                    } else {
                        $indicator.html('<span style="color:#EF4444; font-size:11px;">✗ Gagal</span>');
                        $(this).css('border-color', '#EF4444');
                    }
                }).fail(() => {
                    $indicator.html('<span style="color:#EF4444; font-size:11px;">✗ Error</span>');
                    $(this).css('border-color', '#EF4444');
                });
            });

            // Event listener for Ya/Tidak buttons
            $('.matrix-btn-yatidak').on('click', function() {
                const target_id = $(this).data('target');
                const instrumen_id = $(this).data('instrumen');
                
                const isYa = $(this).find('span').first().text().trim() === 'Ya';
                const isActive = isYa ? $(this).hasClass('btn-success') : $(this).hasClass('btn-danger');
                
                let nilai_val = parseFloat($(this).data('val'));
                if (isNaN(nilai_val)) nilai_val = 0;
                
                const nilai = isActive ? -1 : nilai_val;
                
                const $cell = $(this).closest('td');
                const $indicator = $cell.find('.save-indicator');
                const $btns = $cell.find('.matrix-btn-yatidak');
                
                $indicator.html('<span style="color:#F59E0B; font-size:11px;">Menyimpan...</span>').show();

                Perf.api('manual_penilaian.php?action=submit_matrix_single', {
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        periode_id: periode_id,
                        target_id: target_id,
                        instrumen_id: instrumen_id,
                        nilai: nilai
                    })
                }).done(saveRes => {
                    if (saveRes.success) {
                        $indicator.html('<span style="color:#10B981; font-size:11px;">✓ ' + (nilai < 0 ? 'Dibatalkan' : 'Tersimpan') + '</span>');
                        
                        // Update button classes
                        $btns.removeClass('btn-success btn-danger').addClass('btn-outline');
                        if (nilai >= 0) {
                            if (isYa) {
                                $(this).removeClass('btn-outline').addClass('btn-success');
                            } else {
                                $(this).removeClass('btn-outline').addClass('btn-danger');
                            }
                        }

                        setTimeout(() => {
                            $indicator.fadeOut();
                        }, 2000);
                    } else {
                        $indicator.html('<span style="color:#EF4444; font-size:11px;">✗ Gagal</span>');
                    }
                }).fail(() => {
                    $indicator.html('<span style="color:#EF4444; font-size:11px;">✗ Error</span>');
                });
            });

            // Event listener for mass apply YA / TIDAK
            $('.matrix-manual-apply-all').on('click', function() {
                const instrumen_id = $(this).data('instrumen');
                const nilai = parseFloat($(this).data('val'));
                const type = $(this).data('type');
                const $row = $(this).closest('tr');
                
                const $allButtons = $row.find('.matrix-btn-yatidak');
                
                // Get all unique targets in this row
                let targets = [];
                $allButtons.each(function() {
                    let tid = $(this).data('target');
                    if (tid && !targets.includes(tid)) {
                        targets.push(tid);
                    }
                });

                if (targets.length === 0) {
                    console.error("No targets found in this row.");
                    return;
                }

                const $btnThis = $(this);
                const originalText = $btnThis.html();
                $btnThis.html('...').prop('disabled', true);

                Perf.api('manual_penilaian.php?action=submit_matrix_massal', {
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        periode_id: periode_id,
                        targets: targets,
                        instrumen_id: instrumen_id,
                        nilai: nilai
                    })
                }).done(saveRes => {
                    $btnThis.html(originalText).prop('disabled', false);
                    if (saveRes.success) {
                        // Update UI classes
                        $allButtons.removeClass('btn-success btn-danger').addClass('btn-outline');
                        
                        $allButtons.filter(function() {
                            return parseFloat($(this).data('val')) === nilai;
                        }).removeClass('btn-outline').addClass(type === 'ya' ? 'btn-success' : 'btn-danger');
                        
                        EModal.toast({type:'success', message:'Berhasil menerapkan semua.'});
                    } else {
                        EModal.toast({type:'error', message: saveRes.message || 'Gagal menerapkan semua.'});
                    }
                }).fail((jqXHR, textStatus, errorThrown) => {
                    console.error("Mass apply failed:", textStatus, errorThrown, jqXHR.responseText);
                    $btnThis.html(originalText).prop('disabled', false);
                    EModal.toast({type:'error', message:'Terjadi kesalahan server saat auto-save massal.'});
                });
            });

        }).fail((jqXHR, textStatus, errorThrown) => {
            console.error("API Error: ", textStatus, errorThrown, jqXHR.responseText);
            $('#manualMatrixContainer').html(`<div class="pf-empty" style="color:#EF4444;">Terjadi kesalahan sistem saat memuat data. Periksa koneksi atau console browser.</div>`).show();
        });
    },

    buildMatrixManualTableHtml(teachers, questions, answers, periode_id) {
        if (!questions || !questions.length) return '<div class="pf-empty">Belum ada soal manual untuk tupoksi ini.</div>';
        if (!teachers || !teachers.length) return '<div class="pf-empty">Belum ada PTK di tupoksi ini.</div>';

        let html = `
            <style>
                .no-spin::-webkit-inner-spin-button, 
                .no-spin::-webkit-outer-spin-button { 
                    -webkit-appearance: none; 
                    margin: 0; 
                }
                .no-spin {
                    -moz-appearance: textfield;
                }
            </style>
            <div style="width:100%; max-width:100%; overflow:auto; max-height:70vh; border:1px solid #E5E7EB; border-radius:8px; position:relative; box-sizing:border-box;">
                <table style="width:max-content; min-width:100%; border-collapse:separate; border-spacing:0; text-align:left; font-size:14px;">
                    <thead>
                        <tr>
                            <th style="position:sticky; top:0; left:0; z-index:20; background:#F9FAFB; padding:12px; border-bottom:2px solid #E5E7EB; border-right:2px solid #E5E7EB; width:350px; min-width:300px; max-width:400px; box-shadow:2px 2px 5px rgba(0,0,0,0.05); white-space:normal;">Pertanyaan</th>
        `;

        // Column headers for each teacher
        teachers.forEach(t => {
            html += `<th style="position:sticky; top:0; z-index:10; background:#F9FAFB; padding:12px; border-bottom:2px solid #E5E7EB; border-right:1px solid #E5E7EB; min-width:120px; text-align:center;">
                        <div style="font-weight:600; color:#111827; margin-bottom:4px; font-size:13px;">${t.nama}</div>
                        <div style="font-size:11px; color:#6B7280; font-weight:normal;">${t.niy || t.jenis_ptk}</div>
                     </th>`;
        });
        
        html += `       </tr>
                    </thead>
                    <tbody>`;

        let grouped = {};
        questions.forEach(q => {
            if(!grouped[q.kategori]) grouped[q.kategori] = [];
            grouped[q.kategori].push(q);
        });

        let no = 1;
        let charCode = 65; // 'A'
        for (const kategori in grouped) {
            const letter = String.fromCharCode(charCode++);
            
            // Category Header Row
            html += `<tr>
                        <td style="position:sticky; left:0; z-index:5; background:#F3F4F6; padding:12px; border-bottom:1px solid #E5E7EB; border-right:2px solid #E5E7EB; font-weight:bold; color:#1F2937;">
                            ${letter}. ${kategori}
                        </td>`;
            teachers.forEach(t => {
                html += `<td style="background:#F3F4F6; border-bottom:1px solid #E5E7EB; border-right:1px solid #E5E7EB;"></td>`;
            });
            html += `</tr>`;

            // Questions in this category
            grouped[kategori].forEach(q => {
                let applyAllHtml = '';
                if (q.tipe_jawaban === 'ya_tidak') {
                    let skor_ya = q.skor_ya !== undefined && q.skor_ya !== null ? q.skor_ya : 100;
                    let skor_tidak = q.skor_tidak !== undefined && q.skor_tidak !== null ? q.skor_tidak : 0;
                    applyAllHtml = `
                        <div style="margin-top:8px; display:flex; gap:6px;">
                            <button type="button" class="btn btn-sm btn-outline matrix-manual-apply-all" data-val="${skor_ya}" data-instrumen="${q.instrumen_id}" data-type="ya" style="padding:2px 6px; font-size:10px; border-radius:4px; flex:1;">Terapkan Semua Ya</button>
                            <button type="button" class="btn btn-sm btn-outline matrix-manual-apply-all" data-val="${skor_tidak}" data-instrumen="${q.instrumen_id}" data-type="tidak" style="padding:2px 6px; font-size:10px; border-radius:4px; flex:1;">Terapkan Semua Tidak</button>
                        </div>
                    `;
                }

                html += `<tr>
                            <td style="position:sticky; left:0; z-index:5; background:#fff; padding:12px 12px 12px 32px; border-bottom:1px solid #E5E7EB; border-right:2px solid #E5E7EB; box-shadow:2px 0 5px rgba(0,0,0,0.02); width:350px; min-width:300px; max-width:400px; white-space:normal;">
                                <div style="font-weight:500; color:#374151;">${no}. ${q.pertanyaan}</div>
                                ${applyAllHtml}
                            </td>`;
                
                teachers.forEach(t => {
                    const key = `${q.instrumen_id}_${t.id}`;
                    const val = answers[key] !== undefined ? answers[key] : '';
                    
                    let inputHtml = '';
                    if (q.tipe_jawaban === 'ya_tidak') {
                        let skor_ya = q.skor_ya !== undefined && q.skor_ya !== null ? q.skor_ya : 100;
                        let skor_tidak = q.skor_tidak !== undefined && q.skor_tidak !== null ? q.skor_tidak : 0;
                        inputHtml = `
                            <div style="display:flex; justify-content:center; gap:4px; width:100%;">
                                <button type="button" class="matrix-btn-yatidak ${val!=='' && val==skor_ya ? 'btn-success' : 'btn-outline'}" data-val="${skor_ya}" data-target="${t.id}" data-instrumen="${q.instrumen_id}" style="cursor:pointer; padding:6px 4px; border-radius:4px; flex:1; min-width:30px; display:flex; flex-direction:column; justify-content:center; align-items:center; transition:all 0.1s; height:auto; line-height:1.1;">
                                    <span style="font-size:14px; font-weight:700;">Ya</span>
                                    <span style="font-size:8px; margin-top:4px; opacity:0.85;">Poin: ${skor_ya}</span>
                                </button>
                                <button type="button" class="matrix-btn-yatidak ${val!=='' && val==skor_tidak ? 'btn-danger' : 'btn-outline'}" data-val="${skor_tidak}" data-target="${t.id}" data-instrumen="${q.instrumen_id}" style="cursor:pointer; padding:6px 4px; border-radius:4px; flex:1; min-width:30px; display:flex; flex-direction:column; justify-content:center; align-items:center; transition:all 0.1s; height:auto; line-height:1.1;">
                                    <span style="font-size:14px; font-weight:700;">Tidak</span>
                                    <span style="font-size:8px; margin-top:4px; opacity:0.85;">Poin: ${skor_tidak}</span>
                                </button>
                            </div>
                        `;
                    } else {
                        inputHtml = `
                            <input type="number" step="0.01" class="form-input matrix-manual-input no-spin" style="width:80px; text-align:center; font-weight:bold; transition: border-color 0.3s;" value="${val}" data-target="${t.id}" data-instrumen="${q.instrumen_id}" placeholder="0">
                        `;
                    }
                    
                    html += `<td style="padding:12px; border-bottom:1px solid #E5E7EB; border-right:1px solid #E5E7EB; text-align:center; vertical-align:middle; position:relative;">
                                ${inputHtml}
                                <div class="save-indicator" style="position:absolute; bottom:2px; right:8px; display:none;"></div>
                             </td>`;
                });

                html += `</tr>`;
                no++;
            });
        }

        html += `   </tbody>
                </table>
            </div>
            <div style="margin-top:12px; color:var(--text-muted); font-size:13px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:middle; margin-right:4px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Nilai akan disimpan secara otomatis saat Anda selesai mengetik angka dan memindahkan kursor ke tempat lain.
            </div>`;
        return html;
    },

    toggleSidebar(show = null) {
        if (show === null) {
            $('#pfSidebar').toggleClass('show');
            $('#sidebarOverlay').toggleClass('show');
        } else if (show) {
            $('#pfSidebar').addClass('show');
            $('#sidebarOverlay').addClass('show');
        } else {
            $('#pfSidebar').removeClass('show');
            $('#sidebarOverlay').removeClass('show');
        }
    },

    navigate(route, params = {}) {
        window.location.hash = `#/${route}` + (Object.keys(params).length ? `?${$.param(params)}` : '');
    },

    reloadCurrentPage() {
        this.renderPage(this.state.currentRoute, this.state.params);
    },

    loadRouteFromHash() {
        const hash = window.location.hash || '#/dashboard';
        const parts = hash.replace('#/', '').split('?');
        const route = parts[0] || 'dashboard';
        const searchParams = new URLSearchParams(parts[1] || '');
        const params = Object.fromEntries(searchParams.entries());

        this.state.currentRoute = route;
        this.state.params = params;

        $('.pf-nav-item').removeClass('active');
        $(`.pf-nav-item[data-route="${route}"]`).addClass('active');
        this.toggleSidebar(false);
        this.renderPage(route, params);
    },

    setBreadcrumbs(items) {
        const $b = $('#breadcrumb').empty();
        $b.append(`<a href="#/dashboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="margin-right:2px"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></a>`);
        items.forEach(item => {
            $b.append('<span class="sep">/</span>');
            if (item.route) $b.append(`<a href="#/${item.route}">${item.label}</a>`);
            else $b.append(`<span class="current">${item.label}</span>`);
        });
    },

    renderPage(route, params) {
        const $c = $('#mainContent');
        const $t = $('#pageTitle');

        switch (route) {
            case 'dashboard':
                $t.text('Dashboard'); this.setBreadcrumbs([]); this.renderDashboard($c); break;
            case 'ptk':
                $t.text('Data PTK'); this.setBreadcrumbs([{label:'Master'},{label:'Data PTK'}]); this.renderPtk($c); break;
            case 'siswa':
                $t.text('Data Siswa'); this.setBreadcrumbs([{label:'Master'},{label:'Siswa'}]); this.renderSiswa($c); break;
            case 'periode':
                $t.text('Periode Penilaian'); this.setBreadcrumbs([{label:'Pengaturan'},{label:'Periode'}]); this.renderPeriode($c); break;
            case 'instrumen':
                $t.text('Buat Penilaian'); this.setBreadcrumbs([{label:'Pengaturan'},{label:'Buat Penilaian'}]); this.renderInstrumen($c); break;
            case 'acak_penilai':
                $t.text('Hasil Acak Penilai'); this.setBreadcrumbs([{label:'Pengaturan'},{label:'Acak Penilai (Sejawat)'}]); this.renderAcakPenilai($c); break;
            case 'deskripsi':
                $t.text('Deskripsi Penilaian'); this.setBreadcrumbs([{label:'Pengaturan'},{label:'Deskripsi Penilaian'}]); this.renderDeskripsi($c); break;
            case 'progress':
                $t.text('Progress Penilaian'); this.setBreadcrumbs([{label:'Monitoring'},{label:'Progress Penilaian'}]); this.renderProgress($c); break;
            case 'penilaian':
                $t.text('Isi Penilaian'); this.setBreadcrumbs([{label:'Penilaian'}]); this.renderPenilaian($c); break;
            case 'hasil':
                $t.text('Hasil Penilaian'); this.setBreadcrumbs([{label:'Hasil'}]); this.renderLaporan($c); break;
            case 'hasil_saya':
                $t.text('Hasil Penilaian Saya'); this.setBreadcrumbs([{label:'Hasil Penilaian Saya'}]); this.renderHasilSaya($c); break;
            case 'akses_modul':
                $t.text('Akses Modul'); this.setBreadcrumbs([{label:'Sistem'},{label:'Akses Modul'}]); this.renderAksesModul($c); break;
            default:
                $c.html('<div class="pf-empty"><h3>Halaman Tidak Ditemukan</h3></div>');
        }
    },

    renderComingSoon($c, title, desc) {
        $c.html(`
            <div class="pf-card"><div class="pf-card-body">
                <div class="pf-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <h3>${title}</h3>
                    <p>${desc}</p>
                </div>
            </div></div>
        `);
    },

    // ==============================================
    // DASHBOARD
    // ==============================================
    renderDashboard($c) {
        const role = this.state.user.role;
        const perms = this.state.user.permissions || [];
        const isDashboardAdmin = (role === 'admin' || role === 'superadmin' || perms.includes('dashboard') || perms.includes('progress') || perms.includes('hasil') || perms.includes('akses_modul'));

        if (isDashboardAdmin) {
            $c.html(`
                <div class="pf-stats-grid">
                    <div class="pf-stat-card skeleton-stat"></div>
                    <div class="pf-stat-card skeleton-stat"></div>
                    <div class="pf-stat-card skeleton-stat"></div>
                    <div class="pf-stat-card skeleton-stat"></div>
                </div>

                <div class="pf-card">
                    <div class="pf-card-header">
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Aksi Cepat</h3>
                    </div>
                    <div class="pf-card-body" style="display:flex;gap:12px;flex-wrap:wrap">
                        <button class="btn btn-outline" onclick="Perf.navigate('ptk')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Kelola Data PTK</button>
                        <button class="btn btn-outline" onclick="Perf.navigate('siswa')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Kelola Data Siswa</button>
                        <button class="btn btn-primary" onclick="Perf.navigate('periode')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg> Atur Periode</button>
                    </div>
                </div>

                <div class="pf-card">
                    <div class="pf-card-header"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Komposisi PTK</h3></div>
                    <div class="pf-card-body" id="ptkComposition"><div class="skeleton" style="height:100px"></div></div>
                </div>
            `);

            this.api('ptk.php?action=stats').done(res => {
                if (res.success) {
                    const d = res.data;

                    $c.find('.pf-stats-grid').html(`
                        <div class="pf-stat-card pf-fade-in">
                            <div class="pf-stat-icon" style="background:#D1FAE5; color:#059669">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <div class="pf-stat-info">
                                <h4>${d.total_ptk}</h4>
                                <p>Total PTK</p>
                            </div>
                        </div>
                        <div class="pf-stat-card pf-fade-in" style="animation-delay:0.1s">
                            <div class="pf-stat-icon" style="background:#DBEAFE; color:#2563EB">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div class="pf-stat-info">
                                <h4>${d.total_siswa}</h4>
                                <p>Total Siswa</p>
                            </div>
                        </div>
                        <div class="pf-stat-card pf-fade-in" style="animation-delay:0.2s">
                            <div class="pf-stat-icon" style="background:#FEF3C7; color:#D97706">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div class="pf-stat-info">
                                <h4>${d.periode_aktif > 0 ? d.periode_aktif + ' Aktif' : 'Tidak Ada'}</h4>
                                <p>Periode Penilaian</p>
                            </div>
                        </div>
                        <div class="pf-stat-card pf-fade-in" style="animation-delay:0.3s">
                            <div class="pf-stat-icon" style="background:#EDE9FE; color:#7C3AED">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <div class="pf-stat-info">
                                <h4>${d.total_users}</h4>
                                <p>Total User</p>
                            </div>
                        </div>
                    `);

                    if (d.by_jenis && d.by_jenis.length) {
                        const labels = {guru:'Guru',kepsek:'Kepala Sekolah',tu:'Tata Usaha',it:'IT-Support',pustakawan:'Pustakawan'};
                        const colors = {guru:'#10B981',kepsek:'#3B82F6',tu:'#F59E0B',it:'#8B5CF6',pustakawan:'#EC4899'};
                        let html = '<div style="display:flex;flex-wrap:wrap;gap:16px">';
                        d.by_jenis.forEach(j => {
                            html += `<div style="flex:1;min-width:140px;background:var(--bg-light);border-radius:12px;padding:16px;text-align:center;border-left:4px solid ${colors[j.jenis_ptk]||'#94A3B8'}">
                                <div style="font-family:var(--font-heading);font-size:1.6rem;font-weight:900;color:${colors[j.jenis_ptk]||'#333'}">${j.total}</div>
                                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px">${labels[j.jenis_ptk]||j.jenis_ptk}</div>
                            </div>`;
                        });
                        html += '</div>';
                        $('#ptkComposition').html(html);
                    } else {
                        $('#ptkComposition').html('<div class="pf-empty"><h3>Belum ada data PTK</h3><p>Tambahkan data PTK terlebih dahulu.</p></div>');
                    }
                }
            });
        } else {
            $c.html(`
                <div class="pf-stats-grid">
                    <div class="pf-stat-card skeleton-stat"></div>
                    <div class="pf-stat-card skeleton-stat"></div>
                    <div class="pf-stat-card skeleton-stat"></div>
                    <div class="pf-stat-card skeleton-stat"></div>
                </div>
                <div class="pf-card" style="margin-top:24px;">
                    <div class="pf-card-body" style="text-align:center; padding:40px;">
                        <img src="assets/img/hero-illustration.svg" onerror="this.style.display='none'" style="max-width:300px; margin-bottom:20px;">
                        <h2>Selamat Datang di E-Performance</h2>
                        <p style="color:var(--text-muted); margin-top:8px;">Silakan klik menu <b>Isi Penilaian</b> untuk mulai memberikan penilaian.</p>
                        <button class="btn btn-primary" style="margin-top:20px;" onclick="Perf.navigate('penilaian')">Mulai Isi Penilaian</button>
                    </div>
                </div>
            `);

            this.api('penilaian.php?action=dashboard_stats').done(res => {
                if (res.success) {
                    const d = res.data;
                    const progressPct = d.total > 0 ? Math.round((d.selesai / d.total) * 100) : 0;
                    $c.find('.pf-stats-grid').html(`
                        <div class="pf-stat-card pf-fade-in">
                            <div class="pf-stat-icon" style="background:#DBEAFE; color:#2563EB">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            </div>
                            <div class="pf-stat-info">
                                <h4>${d.total}</h4>
                                <p>Jumlah Guru Diisi</p>
                            </div>
                        </div>
                        <div class="pf-stat-card pf-fade-in" style="animation-delay:0.1s">
                            <div class="pf-stat-icon" style="background:#FEF3C7; color:#D97706">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            </div>
                            <div class="pf-stat-info">
                                <h4>${d.belum}</h4>
                                <p>Jumlah Belum Diisi</p>
                            </div>
                        </div>
                        <div class="pf-stat-card pf-fade-in" style="animation-delay:0.2s">
                            <div class="pf-stat-icon" style="background:#D1FAE5; color:#059669">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <div class="pf-stat-info">
                                <h4>${d.selesai}</h4>
                                <p>Sudah Selesai / Proses</p>
                            </div>
                        </div>
                        <div class="pf-stat-card pf-fade-in" style="animation-delay:0.3s">
                            <div class="pf-stat-icon" style="background:#F3E8FF; color:#9333EA">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            </div>
                            <div class="pf-stat-info">
                                <h4>${progressPct}%</h4>
                                <p>Progress Penilaian</p>
                            </div>
                        </div>
                    `);
                }
            });
        }
    },

    // ==============================================
    // DATA PTK
    // ==============================================
    renderPtk($c) {
        $c.html(`
            <div class="pf-card">
                <div class="pf-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Data Pendidik & Tenaga Kependidikan</h3>
                    <div class="pf-toolbar">
                        <div id="ptkBulkActions" class="hidden pf-fade-in" style="margin-right:auto; display:flex; gap:10px; align-items:center">
                            <span class="pf-badge pf-badge-blue" id="ptkSelCount">0 terpilih</span>
                            <button class="btn btn-danger btn-sm" onclick="Perf.bulkDeletePtk()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Hapus Terpilih</button>
                        </div>
                        <button class="btn btn-outline btn-sm" onclick="Perf.exportPtk()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export</button>
                        <button class="btn btn-accent btn-sm" onclick="Perf.importPortalPtk()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg> Import dari E-Portal</button>
                    </div>
                </div>
                <div class="pf-card-body">
                    <div style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                        <span style="font-size:0.85rem">Filter:</span>
                        <select id="ptkFilter" class="form-select" style="width:180px">
                            <option value="">Semua Jenis</option>
                        </select>
                    </div>
                    <div class="pf-table-wrapper" id="ptkTable"><div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat data...</h3></div></div>
                </div>
            </div>
        `);

        $('#ptkFilter').on('change', () => this.loadPtk());
        
        // Load dynamic filter options
        this.api('instrumen.php?action=list_tupoksi', { method: 'GET' }).done(res => {
            if (res.success && res.data) {
                res.data.forEach(item => {
                    $('#ptkFilter').append(`<option value="${item.nama}">${item.nama}</option>`);
                });
            }
        });

        this.loadPtk();
    },

    loadPtk() {
        const filter = $('#ptkFilter').val() || '';
        const url = 'ptk.php?action=list' + (filter ? '&jenis=' + encodeURIComponent(filter) : '');
        this.api(url).done(res => {
            if (!res.data || !res.data.length) {
                $('#ptkTable').html('<div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/></svg><h3>Belum ada data PTK</h3><p>Gunakan fitur Import dari E-Portal untuk menyinkronkan data.</p></div>');
                $('#ptkBulkActions').addClass('hidden');
                return;
            }
            this.state.ptkData = res.data;
            const labels = {guru:'Guru',kepsek:'Kepsek',tu:'TU',it:'IT',pustakawan:'Pustk.'};
            const badgeColors = {guru:'green',kepsek:'blue',tu:'yellow',it:'gray',pustakawan:'red'};

            const rows = res.data.map((d, i) => `
                <tr>
                    <td><input type="checkbox" class="ptk-row-check" value="${d.id}"></td>
                    <td>${i+1}</td>
                    <td><strong>${d.niy}</strong></td>
                    <td><strong>${d.nama}</strong></td>
                    <td>${d.tmt || '-'}</td>
                    <td>${d.tempat_lahir ? d.tempat_lahir + (d.tgl_lahir ? ', ' + d.tgl_lahir : '') : '-'}</td>
                    <td>${d.jabatan || '-'}</td>
                    <td><span class="pf-badge pf-badge-${badgeColors[d.jenis_ptk]||'gray'}">${labels[d.jenis_ptk]||d.jenis_ptk}</span></td>
                    <td>
                        <div class="pf-actions">
                            <button class="pf-btn-icon danger" onclick="Perf.deletePtk(${d.id})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>
            `).join('');

            $('#ptkTable').html(`
                <table class="pf-table">
                    <thead><tr><th width="30"><input type="checkbox" id="ptkMasterCheck"></th><th>No</th><th>NIY</th><th>Nama</th><th>TMT</th><th>Tempat, Tgl Lahir</th><th>Jabatan</th><th>Tupoksi</th><th>Aksi</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);

            // Event Handlers
            $('#ptkMasterCheck').on('change', function() {
                $('.ptk-row-check').prop('checked', $(this).is(':checked')).trigger('change');
            });

            $('.ptk-row-check').on('change', () => {
                const checked = $('.ptk-row-check:checked');
                const count = checked.length;
                if (count > 0) {
                    $('#ptkBulkActions').removeClass('hidden');
                    $('#ptkSelCount').text(count + ' terpilih');
                } else {
                    $('#ptkBulkActions').addClass('hidden');
                }
            });
        });
    },

    formPtk(id = null) {
        const isEdit = id !== null;
        const row = isEdit ? (this.state.ptkData || []).find(x => x.id == id) : null;

        EModal.form({
            title: isEdit ? 'Edit Data PTK' : 'Tambah Data PTK',
            form: `
                <input type="hidden" id="fId" value="${id || ''}">
                <div class="pf-form-row">
                    <div class="form-group"><label>NIY</label><input class="form-input" id="fNiy" required value="${row?.niy||''}" placeholder="Nomor Induk Yayasan"></div>
                    <div class="form-group"><label>Nama Lengkap</label><input class="form-input" id="fNama" required value="${row?.nama||''}"></div>
                </div>
                <div class="pf-form-row">
                    <div class="form-group"><label>TMT</label><input type="date" class="form-input" id="fTmt" value="${row?.tmt||''}"></div>
                    <div class="form-group"><label>Jenis PTK</label>
                        <select class="form-select" id="fJenis">
                            <option value="guru" ${row?.jenis_ptk==='guru'?'selected':''}>Guru</option>
                            <option value="kepsek" ${row?.jenis_ptk==='kepsek'?'selected':''}>Kepala Sekolah</option>
                            <option value="tu" ${row?.jenis_ptk==='tu'?'selected':''}>Tata Usaha</option>
                            <option value="it" ${row?.jenis_ptk==='it'?'selected':''}>IT-Support</option>
                            <option value="pustakawan" ${row?.jenis_ptk==='pustakawan'?'selected':''}>Pustakawan</option>
                        </select>
                    </div>
                </div>
                <div class="pf-form-row">
                    <div class="form-group"><label>Tempat Lahir</label><input class="form-input" id="fTempat" value="${row?.tempat_lahir||''}"></div>
                    <div class="form-group"><label>Tanggal Lahir</label><input type="date" class="form-input" id="fTglLahir" value="${row?.tgl_lahir||''}"></div>
                </div>
                <div class="pf-form-row">
                    <div class="form-group"><label>Jabatan</label><input class="form-input" id="fJabatan" value="${row?.jabatan||''}"></div>
                    <div class="form-group"><label>Mata Pelajaran</label><input class="form-input" id="fMapel" value="${row?.mata_pelajaran||''}" placeholder="Kosongkan jika bukan guru"></div>
                </div>
            `,
            onConfirm: () => {
                const data = {
                    id: $('#fId').val(), niy: $('#fNiy').val(), nama: $('#fNama').val(),
                    tmt: $('#fTmt').val(), jenis_ptk: $('#fJenis').val(),
                    tempat_lahir: $('#fTempat').val(), tgl_lahir: $('#fTglLahir').val(),
                    jabatan: $('#fJabatan').val(), mata_pelajaran: $('#fMapel').val()
                };
                const act = isEdit ? 'update' : 'create';
                this.api('ptk.php?action=' + act, { method: 'POST', data }).done(res => {
                    if (res.success) {
                        EModal.closeAll();
                        this.reloadCurrentPage();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
                    }
                }).fail(xhr => EModal.toast({type:'error',title:'Gagal',message:xhr.responseJSON?.message}));
                return false;
            }
        });
    },

    deletePtk(id) {
        EModal.confirm({
            title: 'Hapus PTK', type: 'danger',
            message: 'Yakin menghapus data PTK ini? Akun login terkait juga akan dihapus.',
            onConfirm: () => {
                this.api('ptk.php?action=delete', {method:'POST', data:{id}}).done(res => {
                    this.reloadCurrentPage();
                    EModal.toast({type:'success', message:'PTK dihapus.'});
                });
            }
        });
    },

    // ==============================================
    // DATA SISWA
    // ==============================================
    renderSiswa($c) {
        $c.html(`
            <div class="pf-card">
                <div class="pf-card-header" style="display:flex; flex-wrap:wrap; gap:16px;">
                    <div style="display:flex; align-items:center; gap:16px; margin-right:auto;">
                        <h3 style="margin:0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Pengaturan Penilaian Siswa</h3>
                        <div style="display:flex; align-items:center; gap:10px; background:var(--bg-light); padding:4px 12px; border-radius:20px; border:1px solid var(--border-color);">
                            <span style="font-size:0.8rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Periode:</span>
                            <select class="form-input" id="siswaPeriodeSelector" style="flex-grow: 1; border:none; background:transparent; padding:0; height:auto; font-weight:600; color:var(--text-dark); cursor:pointer; outline:none; box-shadow:none;">
                                <option value="">-- Memuat Periode... --</option>
                            </select>
                        </div>
                    </div>
                    <div class="pf-toolbar" style="display:flex; gap:10px; align-items:center;">
                        <div id="siswaBulkActions" class="hidden pf-fade-in" style="display:flex; gap:10px; align-items:center">
                            <span class="pf-badge pf-badge-blue" id="siswaSelCount">0 terpilih</span>
                            <button class="btn btn-danger btn-sm" onclick="Perf.bulkDeleteSiswa()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Hapus Terpilih</button>
                        </div>
                        <button class="btn btn-accent btn-sm" onclick="Perf.modalPengaturanPenilaianSiswa()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Pengaturan Penilaian</button>
                    </div>
                </div>
                <div class="pf-card-body">
                    <div class="pf-table-wrapper" id="siswaTable"><div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat data...</h3></div></div>
                </div>
            </div>
        `);

        // Load Periode for dropdown
        this.api('periode.php?action=list', {method:'GET'}).done(res => {
            if(!res.data || res.data.length === 0) {
                $('#siswaTable').html(`<div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><h3>Silakan buat Periode terlebih dahulu di menu Pengaturan</h3></div>`);
                $('#siswaPeriodeSelector').html('<option value="">Tidak ada periode</option>');
                return;
            }

            let opts = '';
            res.data.forEach(p => {
                const sel = (p.id == this.state.selectedPeriodeId) ? 'selected' : '';
                opts += `<option value="${p.id}" ${sel}>${p.nama_periode} (${p.tahun_ajaran} - ${p.semester == 1 ? 'Ganjil' : 'Genap'})</option>`;
            });
            $('#siswaPeriodeSelector').html(opts);
            
            if(!this.state.selectedPeriodeId) {
                this.state.selectedPeriodeId = res.data[0].id;
                $('#siswaPeriodeSelector').val(this.state.selectedPeriodeId);
            }

            $('#siswaPeriodeSelector').on('change', (e) => {
                this.state.selectedPeriodeId = e.target.value;
                this.loadSiswa();
            });

            this.loadSiswa();
        });
    },

    loadSiswa() {
        this.api('siswa.php?action=list&periode_id=' + this.state.selectedPeriodeId).done(res => {
            if (!res.data || Object.keys(res.data).length === 0) {
                $('#siswaTable').html('<div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/></svg><h3>Belum ada siswa</h3></div>');
                return;
            }
            
            let html = '<div class="pf-accordion" style="display:flex; flex-direction:column; gap:10px;">';
            Object.keys(res.data).forEach(kelas => {
                const siswaList = res.data[kelas];
                let rows = siswaList.map((d, i) => `
                    <tr>
                        <td><input type="checkbox" class="siswa-row-check" value="${d.id}"></td>
                        <td>${i+1}</td>
                        <td><strong>${d.nama_siswa}</strong></td>
                        <td>${d.username ? '<span class="pf-badge pf-badge-green">'+d.username+'</span>' : '<span class="pf-badge pf-badge-gray">Belum</span>'}</td>
                        <td>${d.password_plain || '-'}</td>
                        <td>${d.target_penilaian !== '-' ? '<span style="font-size:0.85em; background:var(--bg-blue); color:var(--text-blue); padding:4px 8px; border-radius:4px;">'+d.target_penilaian+'</span>' : '<span style="color:var(--text-muted); font-style:italic">Belum ada penugasan</span>'}</td>
                        <td>
                            <div class="pf-actions">
                                <button class="pf-btn-icon primary" onclick="Perf.copyPenugasanModal(${d.id}, '${d.nama_siswa.replace(/'/g, "\\'")}')" title="Salin Pengaturan Penilaian"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
                                <button class="pf-btn-icon danger" onclick="Perf.deleteSiswa(${d.id})" title="Hapus Siswa"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </div>
                        </td>
                    </tr>
                `).join('');

                html += `
                    <div style="border:1px solid var(--border-color); border-radius:8px; overflow:hidden;">
                        <div style="background:var(--bg-light); padding:12px 16px; cursor:pointer; font-weight:600; display:flex; justify-content:space-between; align-items:center;" onclick="$(this).next().slideToggle()">
                            <span>Kelas ${kelas} (${siswaList.length} Siswa)</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                        <div style="display:none; padding:0;">
                            <table class="pf-table" style="margin:0; border:none; border-radius:0;">
                                <thead><tr><th width="30"><input type="checkbox" class="siswa-master-check"></th><th width="40">No</th><th>Nama Siswa</th><th>Username</th><th>Password</th><th>Target Penilaian</th><th width="80">Aksi</th></tr></thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    </div>
                `;
            });
            html += '</div>';

            $('#siswaTable').html(html);

            // Event Handlers for checkboxes
            $('.siswa-master-check').on('change', function() {
                $(this).closest('table').find('.siswa-row-check').prop('checked', $(this).is(':checked')).trigger('change');
            });

            $('.siswa-row-check').on('change', () => {
                const checked = $('.siswa-row-check:checked');
                const count = checked.length;
                if (count > 0) {
                    $('#siswaBulkActions').removeClass('hidden');
                    $('#siswaSelCount').text(count + ' terpilih');
                } else {
                    $('#siswaBulkActions').addClass('hidden');
                }
            });
        });
    },

    modalPengaturanPenilaianSiswa() {
        if(!this.state.selectedPeriodeId) return EModal.toast({type:'error', message:'Pilih periode terlebih dahulu'});

        let kelasOptions = '<option value="">-- Pilih Kelas --</option>';
        let tupoksiOptions = '<option value="">-- Pilih Tupoksi PTK --</option>';

        // Load data awal untuk dropdown Kelas & Tupoksi
        $.when(
            this.api('siswa.php?action=list_kelas'),
            this.api('siswa.php?action=list_ptk_target') // This now returns distinct jenis_ptk
        ).done((resKelas, resTupoksi) => {
            resKelas[0].data.forEach(k => { kelasOptions += `<option value="${k}">${k}</option>`; });
            resTupoksi[0].data.forEach(p => { tupoksiOptions += `<option value="${p.jenis_ptk}">${p.jenis_ptk}</option>`; });

            EModal.form({
                title: 'Pengaturan Penilaian Siswa',
                width: '700px',
                form: `
                    <div style="display:flex; gap:20px;">
                        <div style="flex:1;">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>1. Pilih Kelas</label>
                                <select class="form-input" id="fAssignKelas" onchange="Perf.loadSiswaList()">${kelasOptions}</select>
                            </div>
                            <div class="form-group" style="margin-top:16px;">
                                <label>Pilih Siswa Penilai</label>
                                <div id="siswaAssignContainer" style="padding:10px; border:1px solid var(--border-color); border-radius:8px; min-height:150px; max-height:250px; overflow-y:auto; background:var(--bg-light);">
                                    <div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Pilih Kelas terlebih dahulu.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="flex:1;">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>2. Pilih Tupoksi (Jenis PTK)</label>
                                <select class="form-input" id="fAssignTupoksi" onchange="Perf.loadPtkList()">${tupoksiOptions}</select>
                            </div>
                            <div class="form-group" style="margin-top:16px;">
                                <label>Pilih PTK Target Penilaian</label>
                                <div id="ptkAssignContainer" style="padding:10px; border:1px solid var(--border-color); border-radius:8px; min-height:150px; max-height:250px; overflow-y:auto; background:var(--bg-light);">
                                    <div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Pilih Tupoksi terlebih dahulu.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `,
                onConfirm: () => {
                    const kelas = $('#fAssignKelas').val();
                    
                    let checkedSiswa = [];
                    $('.chk-assign-siswa:checked').each(function(){ checkedSiswa.push($(this).val()); });
                    
                    let checkedPtk = [];
                    $('.chk-assign-ptk:checked').each(function(){ checkedPtk.push($(this).val()); });

                    const tupoksi = $('#fAssignTupoksi').val();

                    if (!kelas || !tupoksi || checkedSiswa.length === 0) {
                        EModal.toast({type:'error', message:'Pilih Kelas, Tupoksi, dan minimal 1 Siswa.'});
                        return false;
                    }

                    const data = {
                        periode_id: this.state.selectedPeriodeId,
                        kelas: kelas,
                        tupoksi: tupoksi,
                        ptk_ids: checkedPtk,
                        siswa_ids: checkedSiswa
                    };

                    this.api('siswa.php?action=save_penugasan', {method:'POST', data}).done(res => {
                        EModal.toast({type:'success', message: res.message + ' Silakan lanjutkan penugasan jika diperlukan.'});
                        
                        // Update in-memory state so it's realtime without reloading
                        checkedSiswa.forEach(sid => {
                            if (!this.state.siswaAssignments[sid]) this.state.siswaAssignments[sid] = [];
                            // Update only for this tupoksi? Actually it's easier to just re-fetch
                            // the state from backend for accurate realtime syncing.
                        });
                        
                        // Fetch the latest state to keep realtime UI accurate
                        this.api(`siswa.php?action=list_siswa_by_kelas&kelas=${encodeURIComponent(kelas)}&periode_id=${this.state.selectedPeriodeId}`).done(res2 => {
                            this.state.siswaAssignments = res2.data.assignments || {};
                            this.state.localAssignments = JSON.parse(JSON.stringify(this.state.siswaAssignments));
                        });

                        this.loadSiswa(); // Refresh table accordion di background
                    }).fail(xhr => EModal.toast({type:'error', message: xhr.responseJSON?.message}));
                    return false;
                }
            });
        });
    },

    loadSiswaList() {
        const kelas = $('#fAssignKelas').val();
        if (!kelas) {
            $('#siswaAssignContainer').html('<div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Pilih Kelas terlebih dahulu.</div>');
            return;
        }

        $('#siswaAssignContainer').html('<div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Memuat data siswa...</div>');

        this.api(`siswa.php?action=list_siswa_by_kelas&kelas=${encodeURIComponent(kelas)}&periode_id=${this.state.selectedPeriodeId}`).done(res => {
            if(!res.data.siswa || res.data.siswa.length === 0) {
                $('#siswaAssignContainer').html('<div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Tidak ada siswa di kelas ini.</div>');
                return;
            }

            this.state.siswaAssignments = res.data.assignments || {};
            this.state.localAssignments = JSON.parse(JSON.stringify(this.state.siswaAssignments));

            let h = '<div style="margin-bottom:10px; padding-bottom:10px; border-bottom:1px solid var(--border-color);"><label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" id="checkAllAssignSiswa"> <strong>Pilih Semua Siswa</strong></label></div>';
            h += '<div style="display:flex; flex-direction:column; gap:8px;">';
            res.data.siswa.forEach(s => {
                h += `<label style="display:flex; align-items:center; gap:8px; cursor:pointer; background:#fff; padding:6px 10px; border:1px solid var(--border-color); border-radius:4px;"><input type="checkbox" class="chk-assign-siswa" value="${s.id}"> <span>${s.nama_siswa}</span></label>`;
            });
            h += '</div>';

            $('#siswaAssignContainer').html(h);

            $('#checkAllAssignSiswa').on('change', function() {
                $('.chk-assign-siswa').prop('checked', $(this).is(':checked'));
            });
            
            // Re-render ptk if already selected
            if ($('#fAssignTupoksi').val()) {
                this.loadPtkList();
            }
        });
    },

    copyPenugasanModal(sourceSiswaId, namaSiswa) {
        if(!this.state.selectedPeriodeId) return EModal.toast({type:'error', message:'Pilih periode terlebih dahulu'});

        let kelasOptions = '<option value="">-- Pilih Kelas Target --</option>';

        this.api('siswa.php?action=list_kelas').done((resKelas) => {
            resKelas.data.forEach(k => { kelasOptions += `<option value="${k}">${k}</option>`; });

            EModal.form({
                title: 'Salin Pengaturan Penilaian',
                width: '500px',
                form: `
                    <div style="margin-bottom:15px; padding: 12px; background: var(--bg-light); border-radius: 8px;">
                        <p style="margin:0; color:var(--text-muted); font-size:0.9rem;">Menyalin pengaturan dari siswa:</p>
                        <strong style="font-size:1.1rem; color:var(--text-dark);">${namaSiswa}</strong>
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>Pilih Kelas Tujuan</label>
                        <select class="form-input" id="fCopyKelas" onchange="Perf.loadTargetSiswaList()">${kelasOptions}</select>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label>Pilih Siswa Tujuan</label>
                        <div id="siswaCopyContainer" style="padding:10px; border:1px solid var(--border-color); border-radius:8px; min-height:150px; max-height:250px; overflow-y:auto; background:var(--bg-light);">
                            <div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Pilih Kelas terlebih dahulu.</div>
                        </div>
                    </div>
                    <p style="color:var(--danger); font-size:0.85rem; margin-top:10px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:text-bottom"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Peringatan: Pengaturan sebelumnya pada siswa tujuan (untuk periode ini) akan tertimpa.
                    </p>
                `,
                onConfirm: () => {
                    let checkedSiswa = [];
                    $('.chk-copy-siswa:checked').each(function(){ checkedSiswa.push($(this).val()); });

                    if (checkedSiswa.length === 0) {
                        EModal.toast({type:'error', message:'Pilih minimal 1 Siswa tujuan.'});
                        return false;
                    }

                    const data = {
                        periode_id: this.state.selectedPeriodeId,
                        source_siswa_id: sourceSiswaId,
                        target_siswa_ids: checkedSiswa
                    };

                    this.api('siswa.php?action=copy_penugasan', {method:'POST', data}).done(res => {
                        EModal.toast({type:'success', message: res.message});
                        this.loadSiswa(); // Refresh table accordion
                    }).fail(xhr => EModal.toast({type:'error', message: xhr.responseJSON?.message}));
                    return false;
                }
            });
        });
    },

    loadTargetSiswaList() {
        const kelas = $('#fCopyKelas').val();
        if (!kelas) {
            $('#siswaCopyContainer').html('<div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Pilih Kelas terlebih dahulu.</div>');
            return;
        }

        $('#siswaCopyContainer').html('<div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Memuat data siswa...</div>');

        this.api('siswa.php?action=list_siswa_by_kelas&kelas=' + encodeURIComponent(kelas) + '&periode_id=' + this.state.selectedPeriodeId).done(res => {
            if(!res.data.siswa || res.data.siswa.length === 0) {
                $('#siswaCopyContainer').html('<div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Tidak ada siswa di kelas ini.</div>');
                return;
            }

            let h = '<div style="margin-bottom:10px; padding-bottom:10px; border-bottom:1px solid var(--border-color);"><label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" id="checkAllCopySiswa"> <strong>Pilih Semua Siswa</strong></label></div>';
            h += '<div style="display:flex; flex-direction:column; gap:8px;">';
            res.data.siswa.forEach(s => {
                h += `<label style="display:flex; align-items:center; gap:8px; cursor:pointer; background:#fff; padding:6px 10px; border:1px solid var(--border-color); border-radius:4px;"><input type="checkbox" class="chk-copy-siswa" value="${s.id}"> <span>${s.nama_siswa}</span></label>`;
            });
            h += '</div>';

            $('#siswaCopyContainer').html(h);

            $('#checkAllCopySiswa').on('change', function() {
                $('.chk-copy-siswa').prop('checked', $(this).is(':checked'));
            });
        });
    },



    loadPtkList() {
        const tupoksi = $('#fAssignTupoksi').val();
        const kelas = $('#fAssignKelas').val() || '';
        
        if (!tupoksi) {
            $('#ptkAssignContainer').html('<div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Pilih Tupoksi terlebih dahulu.</div>');
            return;
        }

        $('#ptkAssignContainer').html('<div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Memuat data PTK...</div>');

        this.api(`siswa.php?action=list_ptk_by_tupoksi&tupoksi=${encodeURIComponent(tupoksi)}&kelas=${encodeURIComponent(kelas)}&periode_id=${this.state.selectedPeriodeId}`).done(res => {
            const ptks = res.data.ptks;
            
            if (!ptks || !ptks.length) {
                $('#ptkAssignContainer').html('<div style="color:var(--text-muted); font-style:italic; text-align:center; margin-top:20px;">Tidak ada PTK.</div>');
                return;
            }

            let chkHtml = '<label style="display:block; margin-bottom:8px; font-weight:600;"><input type="checkbox" onchange="$(\'.chk-assign-ptk\').prop(\'checked\', this.checked).trigger(\'change\')"> Pilih Semua PTK</label><hr style="margin:8px 0; border-top:1px solid var(--border-color);">';
            
            ptks.forEach(p => {
                const mapelHtml = p.mata_pelajaran && p.mata_pelajaran !== '-' ? `<div style="font-size:0.8em; color:var(--text-muted);">${p.mata_pelajaran}</div>` : '';
                chkHtml += `<label style="display:flex; align-items:flex-start; gap:8px; margin-bottom:10px; cursor:pointer;"><input type="checkbox" class="chk-assign-ptk" value="${p.id}" onchange="Perf.onPtkChange(this)" style="margin-top:4px;"> <div><strong style="font-weight:500;">${p.nama}</strong>${mapelHtml}</div></label>`;
            });

            $('#ptkAssignContainer').html(chkHtml);
            this.updatePtkCheckboxes();
        });
    },

    onPtkChange(el) {
        const isChecked = $(el).prop('checked');
        const ptkId = String($(el).val());
        
        $('.chk-assign-siswa:checked').each(function() {
            const sid = String($(this).val());
            if (!Perf.state.localAssignments) Perf.state.localAssignments = {};
            if (!Perf.state.localAssignments[sid]) Perf.state.localAssignments[sid] = [];
            
            let arr = Perf.state.localAssignments[sid];
            if (isChecked) {
                if (!arr.includes(ptkId)) arr.push(ptkId);
            } else {
                Perf.state.localAssignments[sid] = arr.filter(id => String(id) !== ptkId);
            }
        });
    },

    updatePtkCheckboxes() {
        $('.chk-assign-ptk').prop('checked', false); // Reset dulu

        let unionPtks = new Set();
        let anyChecked = false;

        $('.chk-assign-siswa:checked').each(function() {
            anyChecked = true;
            const sid = $(this).val();
            const ptks = (Perf.state.localAssignments && Perf.state.localAssignments[sid]) ? Perf.state.localAssignments[sid] : [];
            ptks.forEach(p => unionPtks.add(String(p)));
        });

        if (anyChecked && unionPtks.size > 0) {
            $('.chk-assign-ptk').each(function() {
                if (unionPtks.has($(this).val())) {
                    $(this).prop('checked', true);
                }
            });
        }
    },

    formSiswa(id = null, nama = '', kelas = '') {
        EModal.form({
            title: id ? 'Edit Siswa' : 'Tambah Siswa',
            form: `
                <input type="hidden" id="fId" value="${id||''}">
                <div class="pf-form-row">
                    <div class="form-group"><label>Nama Siswa</label><input class="form-input" id="fNamaSiswa" required value="${nama}"></div>
                    <div class="form-group"><label>Kelas</label><input class="form-input" id="fKelas" required value="${kelas}" placeholder="Contoh: X-A"></div>
                </div>
            `,
            onConfirm: () => {
                const data = { id: $('#fId').val(), nama_siswa: $('#fNamaSiswa').val(), kelas: $('#fKelas').val() };
                const act = id ? 'update' : 'create';
                this.api('siswa.php?action=' + act, {method:'POST', data}).done(res => {
                    EModal.closeAll(); this.reloadCurrentPage();
                    EModal.toast({type:'success', message: res.message});
                }).fail(xhr => EModal.toast({type:'error', message: xhr.responseJSON?.message}));
                return false;
            }
        });
    },

    deleteSiswa(id) {
        EModal.confirm({
            title: 'Hapus Siswa', type: 'danger',
            message: 'Yakin menghapus siswa ini?',
            onConfirm: () => {
                this.api('siswa.php?action=delete', {method:'POST', data:{id}}).done(() => {
                    this.reloadCurrentPage();
                    EModal.toast({type:'success', message:'Siswa dihapus.'});
                });
            }
        });
    },

    modalMappingGuru(id, nama) {
        this.api('siswa.php?action=get_guru_mapping&id=' + id).done(res => {
            if (!res.success) return EModal.toast({type: 'error', message: res.message});
            
            const gurus = res.data.gurus || [];
            const mappedIds = res.data.mapped_ids || [];
            
            let checkboxesHtml = '';
            if (gurus.length === 0) {
                checkboxesHtml = '<div class="pf-empty">Belum ada data PTK untuk dinilai.</div>';
            } else {
                checkboxesHtml = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:400px; overflow-y:auto; padding-right:10px;">';
                gurus.forEach(g => {
                    const isChecked = mappedIds.includes(g.id.toString()) || mappedIds.includes(Number(g.id));
                    const roleBadge = g.jenis_ptk ? `<small style="color:var(--text-muted); font-size:11px;">(${g.jenis_ptk})</small>` : '';
                    checkboxesHtml += `
                        <label style="display:flex; align-items:center; gap:8px; padding:10px; background:#F9FAFB; border:1px solid #E5E7EB; border-radius:6px; cursor:pointer;">
                            <input type="checkbox" name="guru_ids[]" value="${g.id}" ${isChecked ? 'checked' : ''}>
                            <span>${g.nama} ${roleBadge}</span>
                        </label>
                    `;
                });
                checkboxesHtml += '</div>';
            }

            EModal.form({
                title: 'Pilih Guru untuk ' + nama,
                width: '600px',
                form: `
                    <div style="margin-bottom:15px; font-size:14px; color:var(--text-muted);">
                        Centang Guru/Kepsek/Staf yang akan dinilai oleh siswa ini (Staf TU otomatis muncul untuk dinilai).
                    </div>
                    <div id="formMappingGuru">
                        <input type="hidden" name="id" value="${id}">
                        ${checkboxesHtml}
                    </div>
                `,
                onConfirm: () => {
                    let guruIds = [];
                    document.querySelectorAll('input[name="guru_ids[]"]:checked').forEach(chk => {
                        guruIds.push(chk.value);
                    });
                    
                    this.api('siswa.php?action=save_guru_mapping', {
                        method: 'POST', 
                        data: { id: id, guru_ids: guruIds }
                    }).done(saveRes => {
                        EModal.closeAll();
                        EModal.toast({type: 'success', message: 'Mapping guru berhasil disimpan.'});
                    }).fail(xhr => {
                        EModal.toast({type: 'error', message: 'Gagal menyimpan mapping.'});
                    });
                    
                    return false;
                }
            });
        });
    },

    // ==============================================
    // PERIODE
    // ==============================================
    renderPeriode($c) {
        $c.html(`
            <div class="pf-card">
                <div class="pf-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Periode Penilaian</h3>
                    <div class="pf-toolbar">
                        <button class="btn btn-primary btn-sm" onclick="Perf.formPeriode()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Buat Periode</button>
                    </div>
                </div>
                <div class="pf-card-body">
                    <div class="pf-table-wrapper" id="periodeTable"><div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><h3>Memuat...</h3></div></div>
                </div>
            </div>
        `);
        this.loadPeriode();
    },

    loadPeriode() {
        this.api('periode.php?action=list').done(res => {
            this.state.periodeData = res.data || [];
            if (!this.state.periodeData.length) {
                $('#periodeTable').html('<div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/></svg><h3>Belum ada periode</h3><p>Buat periode penilaian untuk memulai.</p></div>');
                return;
            }

            const statusBadge = s => {
                if (s === 'aktif') return '<span class="pf-badge pf-badge-green">Aktif</span>';
                if (s === 'selesai') return '<span class="pf-badge pf-badge-gray">Selesai</span>';
                return '<span class="pf-badge pf-badge-yellow">Draft</span>';
            };

            const rows = res.data.map((d, i) => `
                <tr>
                    <td>${i+1}</td>
                    <td><strong>${d.nama_periode}</strong></td>
                    <td>${d.tahun_ajaran}</td>
                    <td>Semester ${d.semester}</td>
                    <td>${d.tgl_mulai || '-'} s/d ${d.tgl_selesai || '-'}</td>
                    <td>${statusBadge(d.status)}</td>
                    <td>
                        <div class="pf-actions">
                            ${d.status !== 'aktif' ? `<button class="pf-btn-icon" onclick="Perf.activatePeriode(${d.id},'aktif')" title="Aktifkan" style="background:#D1FAE5;color:#059669"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></button>` : `<button class="pf-btn-icon" onclick="Perf.activatePeriode(${d.id},'selesai')" title="Selesaikan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg></button>`}
                            <button class="pf-btn-icon" onclick="Perf.formPeriode(${d.id})" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="pf-btn-icon danger" onclick="Perf.deletePeriode(${d.id})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>
            `).join('');
            $('#periodeTable').html(`<table class="pf-table"><thead><tr><th>No</th><th>Nama Periode</th><th>Tahun Ajaran</th><th>Semester</th><th>Rentang</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
        });
    },

    formPeriode(id = null) {
        let existingData = null;
        if (id) {
            existingData = this.state.periodeData.find(p => p.id == id);
        }

        this.api('periode.php?action=get_all_years').done(res => {
            const years = res.data || [];
            const selectOptions = years.map(y => `
                <option value="${y.tahun_ajaran}" 
                        data-semester="${y.semester}" 
                        ${y.is_active ? 'selected' : ''}>
                    ${y.tahun_ajaran} (${y.semester == 1 ? 'Ganjil' : 'Genap'}) ${y.is_active ? '— Aktif' : ''}
                </option>
            `).join('');

            EModal.form({
                title: id ? 'Edit Periode' : 'Buat Periode Baru',
                form: `
                    <input type="hidden" id="fId" value="${id||''}">
                    <div class="form-group"><label>Nama Periode</label><input class="form-input" id="fNamaPeriode" required placeholder="Contoh: Penilaian Kinerja Semester 1"></div>
                    <div class="pf-form-row">
                        <div class="form-group">
                            <label>Tahun Ajaran</label>
                            <select class="form-select" id="fTahun" required>
                                <option value="">Pilih Tahun Ajaran</option>
                                ${selectOptions}
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester</label>
                            <select class="form-select" id="fSemester">
                                <option value="1">Semester 1 (Ganjil)</option>
                                <option value="2">Semester 2 (Genap)</option>
                            </select>
                        </div>
                    </div>
                    <div class="pf-form-row">
                        <div class="form-group"><label>Tanggal Mulai</label><input type="date" class="form-input" id="fTglMulai"></div>
                        <div class="form-group"><label>Tanggal Selesai</label><input type="date" class="form-input" id="fTglSelesai"></div>
                    </div>
                `,
                onOpen: () => {
                    // Sync semester when year changes
                    $('#fTahun').on('change', function() {
                        const sem = $(this).find(':selected').data('semester');
                        if (sem) $('#fSemester').val(sem);
                    });

                    if (id && existingData) {
                        $('#fNamaPeriode').val(existingData.nama_periode);
                        $('#fTahun').val(existingData.tahun_ajaran);
                        $('#fSemester').val(existingData.semester);
                        $('#fTglMulai').val(existingData.tgl_mulai);
                        $('#fTglSelesai').val(existingData.tgl_selesai);
                    } else {
                        // Trigger initial semester sync for the default selected year
                        $('#fTahun').trigger('change');
                    }
                },
            onConfirm: () => {
                const data = {
                    id: $('#fId').val(), nama_periode: $('#fNamaPeriode').val(),
                    tahun_ajaran: $('#fTahun').val(), semester: $('#fSemester').val(),
                    tgl_mulai: $('#fTglMulai').val(), tgl_selesai: $('#fTglSelesai').val()
                };
                const act = id ? 'update' : 'create';
                this.api('periode.php?action='+act, {method:'POST', data}).done(res => {
                    EModal.closeAll(); this.reloadCurrentPage();
                    EModal.toast({type:'success', message: res.message});
                }).fail(xhr => EModal.toast({type:'error', message: xhr.responseJSON?.message}));
                return false;
            }
        });
    });
},

    renderInstrumen($c) {
        $c.html(`
            <div class="pf-card" style="margin-bottom: 16px;">
                <div class="pf-card-body" style="display:flex; align-items:center; gap: 16px;">
                    <h3 style="margin:0; font-size: 1rem; width: 140px;">Pilih Periode:</h3>
                    <select class="form-input" id="periodeSelector" style="flex-grow: 1;">
                        <option value="">-- Memuat Periode... --</option>
                    </select>
                </div>
            </div>

            <div class="pf-card" id="instrumenContainer" style="display: none;">
                <div class="pf-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> Instrumen Penilaian Kinerja</h3>
                </div>
                <div class="pf-card-body">
                    <div class="pf-tabs">
                        <button class="pf-tab-btn active" data-tab="pertanyaan">Daftar Pertanyaan</button>
                        <button class="pf-tab-btn" data-tab="manual">Soal Penilaian Lainnya</button>
                        <button class="pf-tab-btn" data-tab="penilai">Penilai</button>
                        <button class="pf-tab-btn" data-tab="aturan_sejawat">Aturan Sejawat</button>
                    </div>
                    
                    <div id="tabContent-pertanyaan" class="pf-tab-content">
                        <div class="pf-toolbar" style="margin-top: 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px; max-width: 300px;">
                                <input type="text" class="form-input" placeholder="Cari pertanyaan..." id="searchPertanyaan" style="width: 100%; padding: 8px 12px; font-size: 0.85rem;">
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-outline btn-sm" onclick="Perf.bulkHapusPertanyaan()" id="btnBulkHapusPertanyaan" style="color:var(--danger); border-color:var(--danger); display:none;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Hapus Terpilih</button>
                                <button class="btn btn-outline btn-sm" onclick="Perf.modalTambahPertanyaan()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Pertanyaan</button>
                                <button class="btn btn-primary btn-sm" onclick="Perf.modalImportPertanyaan()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Import Pertanyaan</button>
                            </div>
                        </div>

                        <div class="pf-table-wrapper" id="instrumenTable">
                            <div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <h3>Belum Ada Pertanyaan</h3><p>Silakan klik Import atau Tambah Pertanyaan untuk memulai.</p></div>
                        </div>
                    </div>

                    <div id="tabContent-manual" class="pf-tab-content" style="display:none;">
                        <div class="pf-toolbar" style="margin-top: 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px; max-width: 300px;">
                                <input type="text" class="form-input" placeholder="Cari soal..." id="searchManualPertanyaan" style="width: 100%; padding: 8px 12px; font-size: 0.85rem;">
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-outline btn-sm" onclick="Perf.bulkHapusPertanyaan(1)" id="btnBulkHapusManual" style="color:var(--danger); border-color:var(--danger); display:none;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Hapus Terpilih</button>
                                <button class="btn btn-outline btn-sm" onclick="Perf.modalTambahPertanyaan(1)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Soal Penilaian Lainnya</button>
                                <button class="btn btn-primary btn-sm" onclick="Perf.modalImportPertanyaan(1)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Import Soal Penilaian Lainnya</button>
                            </div>
                        </div>
                        <div class="pf-table-wrapper" id="manualTable">
                            <div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <h3>Belum Ada Soal Penilaian Lainnya</h3><p>Silakan klik Import atau Tambah Soal Penilaian Lainnya untuk memulai.</p></div>
                        </div>
                    </div>

                    <div id="tabContent-penilai" class="pf-tab-content" style="display:none;">
                        <div class="pf-toolbar" style="margin-top: 16px; margin-bottom: 16px; display: flex; justify-content: flex-end;">
                            <button class="btn btn-primary btn-sm" onclick="Perf.modalTambahPenilai()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg> Tambah Penilai</button>
                        </div>
                        <div class="pf-table-wrapper" id="penilaiTable">
                            <div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <h3>Belum Ada Penilai</h3><p>Silakan tambahkan penilai terlebih dahulu.</p></div>
                        </div>
                    </div>
                    
                    <div id="tabContent-aturan_sejawat" class="pf-tab-content" style="display:none;">
                        <div class="pf-toolbar" style="margin-top: 16px; margin-bottom: 16px; display: flex; justify-content: flex-end;">
                            <button class="btn btn-primary btn-sm" onclick="Perf.modalTambahAturanSejawat()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg> Tambah Aturan</button>
                        </div>
                        <div class="pf-table-wrapper" id="aturanSejawatTable">
                            <div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <h3>Belum Ada Aturan Sejawat</h3><p>Tambahkan aturan siapa yang berhak menilai siapa.</p></div>
                        </div>
                    </div>
                </div>
            </div>
        `);

        // Load Periode for dropdown
        this.api('periode.php?action=list', {method:'GET'}).done(res => {
            let opts = '<option value="">-- Pilih Periode Penilaian --</option>';
            if(res.data) res.data.forEach(p => {
                opts += `<option value="${p.id}">${p.nama_periode} (${p.tahun_ajaran} (${p.semester == 1 ? 'Ganjil' : 'Genap'}))</option>`;
            });
            $('#periodeSelector').html(opts);
        });

        $('#periodeSelector').on('change', (e) => {
            const pid = $(e.target).val();
            if(!pid) {
                $('#instrumenContainer').hide();
                this.state.selectedPeriodeId = null;
            } else {
                this.state.selectedPeriodeId = pid;
                $('#instrumenContainer').show();
                $('.pf-tab-btn.active').trigger('click');
                this.loadPertanyaan();
                this.loadManualPertanyaan();
                this.loadPenilai();
                this.loadAturanSejawat();
            }
        });

        $('.pf-tab-btn').on('click', function() {
            $('.pf-tab-btn').removeClass('active');
            $(this).addClass('active');
            const target = $(this).data('tab');
            $('.pf-tab-content').hide();
            $('#tabContent-' + target).show();
        });

        $('#searchPertanyaan').on('input', function() {
            const val = $(this).val().toLowerCase();
            $('#instrumenTable tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
            });
        });
        
        $('#searchManualPertanyaan').on('input', function() {
            const val = $(this).val().toLowerCase();
            $('#manualTable tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
            });
        });
        
        // Initial tab load is delayed until period is selected
    },

    loadPertanyaan() {
        if(!this.state.selectedPeriodeId) return;
        this.api(`instrumen.php?action=list_pertanyaan&is_manual=0&periode_id=${this.state.selectedPeriodeId}`).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#instrumenTable').html(`
                    <div class="pf-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <h3>Belum Ada Pertanyaan</h3>
                        <p>Silakan klik Import, Tambah Pertanyaan, atau Salin dari Periode Sebelumnya untuk memulai.</p>
                        <button class="btn btn-primary" onclick="Perf.modalCopyInstrumen()" style="margin-top: 16px; width: max-content; display: inline-flex; align-items: center; padding: 8px 16px; font-size:14px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px !important; height:16px !important; margin-right:8px; vertical-align:middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Salin dari Periode Lain</button>
                    </div>
                `);
                return;
            }
            let html = '<table class="pf-table"><thead><tr><th width="40"><input type="checkbox" id="chkAllPertanyaan"></th><th width="40">No</th><th width="150">Kategori</th><th>Pertanyaan</th><th width="150">Penilai</th><th width="150">Dinilai</th><th width="80">Aksi</th></tr></thead><tbody>';
            data.forEach((r, i) => {
                const penilaiBadges = (r.target_jabatan || '').split(',').map(p => `<span class="pf-badge pf-badge-blue" style="margin-right:4px; margin-bottom:4px; display:inline-block">${p.trim()}</span>`).join('');
                const dinilaiBadges = (r.target_dinilai || '').split(',').map(p => `<span class="pf-badge pf-badge-purple" style="margin-right:4px; margin-bottom:4px; display:inline-block">${p.trim()}</span>`).join('');
                html += `<tr>
                    <td><input type="checkbox" class="chk-pertanyaan" value="${r.id}"></td>
                    <td>${i+1}</td>
                    <td><span class="pf-badge pf-badge-gray">${r.kategori}</span></td>
                    <td>${r.pertanyaan}</td>
                    <td>${penilaiBadges}</td>
                    <td>${dinilaiBadges}</td>
                    <td><button class="btn btn-outline btn-sm" onclick="Perf.hapusPertanyaan(${r.id})" style="color:var(--danger); border-color:var(--danger);">Hapus</button></td>
                </tr>`;
            });
            html += '</tbody></table>';
            $('#instrumenTable').html(html);
            $('#chkAllPertanyaan').on('change', function() {
                $('.chk-pertanyaan').prop('checked', $(this).prop('checked'));
                Perf.toggleBulkDeleteBtn();
            });
            $('.chk-pertanyaan').on('change', function() {
                const total = $('.chk-pertanyaan').length;
                const checked = $('.chk-pertanyaan:checked').length;
                $('#chkAllPertanyaan').prop('checked', total === checked);
                Perf.toggleBulkDeleteBtn();
            });
            Perf.toggleBulkDeleteBtn(0);
        });
    },

    loadManualPertanyaan() {
        if(!this.state.selectedPeriodeId) return;
        this.api(`instrumen.php?action=list_pertanyaan&is_manual=1&periode_id=${this.state.selectedPeriodeId}`).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#manualTable').html(`
                    <div class="pf-empty">
                        <h3>Belum Ada Soal Penilaian Lainnya</h3>
                        <p>Silakan klik Import atau Tambah Soal Penilaian Lainnya untuk memulai.</p>
                    </div>
                `);
                return;
            }
            let html = '<table class="pf-table"><thead><tr><th width="40"><input type="checkbox" id="chkAllManual"></th><th width="40">No</th><th width="150">Kategori</th><th>Soal Penilaian Lainnya</th><th width="150">Dinilai (Tupoksi)</th><th width="80">Aksi</th></tr></thead><tbody>';
            data.forEach((r, i) => {
                const dinilaiBadges = (r.target_dinilai || '').split(',').map(p => `<span class="pf-badge pf-badge-purple" style="margin-right:4px; margin-bottom:4px; display:inline-block">${p.trim()}</span>`).join('');
                html += `<tr>
                    <td><input type="checkbox" class="chk-manual" value="${r.id}"></td>
                    <td>${i+1}</td>
                    <td><span class="pf-badge pf-badge-gray">${r.kategori}</span></td>
                    <td>${r.pertanyaan}</td>
                    <td>${dinilaiBadges}</td>
                    <td><button class="btn btn-outline btn-sm" onclick="Perf.hapusPertanyaan(${r.id}, 1)" style="color:var(--danger); border-color:var(--danger);">Hapus</button></td>
                </tr>`;
            });
            html += '</tbody></table>';
            $('#manualTable').html(html);
            $('#chkAllManual').on('change', function() {
                $('.chk-manual').prop('checked', $(this).prop('checked'));
                Perf.toggleBulkDeleteBtn(1);
            });
            $('.chk-manual').on('change', function() {
                const total = $('.chk-manual').length;
                const checked = $('.chk-manual:checked').length;
                $('#chkAllManual').prop('checked', total === checked);
                Perf.toggleBulkDeleteBtn(1);
            });
            Perf.toggleBulkDeleteBtn(1);
        });
    },

    loadPenilai() {
        if(!this.state.selectedPeriodeId) return;
        this.api(`instrumen.php?action=list_penilai&periode_id=${this.state.selectedPeriodeId}`).done(res => {
            const data = res.data || [];
            this.state.penilaiList = data; // Store globally for checkboxes
            if (!data.length) {
                $('#penilaiTable').html('<div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><h3>Belum Ada Penugasan Penilai</h3><p>Tentukan siapa saja yang berhak melakukan penilaian.</p></div>');
                return;
            }
            let html = '<table class="pf-table"><thead><tr><th width="40">No</th><th>Jenis Penilai</th><th width="80">Aksi</th></tr></thead><tbody>';
            data.forEach((r, i) => {
                html += `<tr>
                    <td>${i+1}</td>
                    <td><span class="pf-badge pf-badge-purple" style="font-size:14px; padding:4px 8px;">${r.jenis_penilai}</span></td>
                    <td><button class="btn btn-outline btn-sm" onclick="Perf.hapusPenilai(${r.id})" style="color:var(--danger); border-color:var(--danger);">Hapus</button></td>
                </tr>`;
            });
            html += '</tbody></table>';
            $('#penilaiTable').html(html);
        });
    },

    loadAturanSejawat() {
        if(!this.state.selectedPeriodeId) return;
        this.api(`instrumen.php?action=list_aturan_sejawat&periode_id=${this.state.selectedPeriodeId}`).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#aturanSejawatTable').html('<div class="pf-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><h3>Belum Ada Aturan Sejawat</h3><p>Jika tidak ada aturan, penilai tidak akan mendapat teman sejawat secara acak.</p></div>');
                return;
            }
            let html = '<table class="pf-table"><thead><tr><th width="40">No</th><th>Penilai (Tupoksi)</th><th>Dinilai (Tupoksi Teman Sejawat)</th><th width="80">Aksi</th></tr></thead><tbody>';
            data.forEach((r, i) => {
                let dinilaiBadges = '';
                if (r.dinilai_list) {
                    const dl = r.dinilai_list.split('||');
                    dinilaiBadges = dl.map(d => `<span class="pf-badge pf-badge-purple" style="font-size:13px; margin-right:4px;">${d}</span>`).join('');
                }
                
                html += `<tr>
                    <td>${i+1}</td>
                    <td><span class="pf-badge pf-badge-blue" style="font-size:13px;">${r.penilai_jenis}</span></td>
                    <td>${dinilaiBadges}</td>
                    <td><button class="btn btn-outline btn-sm" onclick="Perf.hapusAturanSejawat('${r.penilai_jenis}')" style="color:var(--danger); border-color:var(--danger);">Hapus</button></td>
                </tr>`;
            });
            html += '</tbody></table>';
            $('#aturanSejawatTable').html(html);
        });
    },

    renderProgress($c) {
        this.api('periode.php?action=list', {method:'GET'}).done(pRes => {
            let opts = '<option value="">-- Pilih Periode Penilaian --</option>';
            if(pRes.data) pRes.data.forEach(p => {
                opts += `<option value="${p.id}">${p.nama_periode} (${p.tahun_ajaran} Sem ${p.semester})</option>`;
            });
            
            $c.html(`
                <div class="pf-card" style="margin-bottom: 16px;">
                    <div class="pf-card-body" style="display:flex; align-items:center; gap: 16px;">
                        <h3 style="margin:0; font-size: 1rem; width: 140px;">Pilih Periode:</h3>
                        <select class="form-input" id="progressPeriodeSelector" style="flex-grow: 1;">
                            ${opts}
                        </select>
                    </div>
                </div>

                <div class="pf-card" id="progressContainer" style="display: none;">
                    <div class="pf-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> Progress Pengisian Penilai</h3>
                        <button class="btn btn-outline btn-sm" onclick="Perf.copyLinkPenilaian()" title="Copy Link Universal">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px; vertical-align:middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy Link Login
                        </button>
                    </div>
                    <div class="pf-card-body">
                        <div class="pf-table-wrapper" id="progressTable">
                            <div class="pf-empty">Memuat Progress...</div>
                        </div>
                    </div>
                </div>
            `);

            $('#progressPeriodeSelector').on('change', (e) => {
                const pid = $(e.target).val();
                if(!pid) {
                    $('#progressContainer').hide();
                } else {
                    $('#progressContainer').show();
                    Perf.state.selectedPeriodeId = pid;
                    this.loadProgressPenilaian(pid);
                }
            });
        });
    },

    loadProgressPenilaian(periode_id) {
        if(!periode_id) return;
        this.api(`instrumen.php?action=list_progress&periode_id=${periode_id}`, {method:'GET'}).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#progressTable').html('<div class="pf-empty" style="padding:20px;"><p>Belum ada data penilai yang terdaftar.</p></div>');
                return;
            }

            let html = '<table class="pf-table"><thead><tr><th width="40">No</th><th>Nama Penilai</th><th width="150">Role</th><th width="250">Progress</th><th width="80" style="text-align:right">Aksi</th></tr></thead><tbody>';
            data.forEach((r, i) => {
                // Progress Bar styling
                const pct = r.percentage;
                let colorClass = 'pf-badge-gray';
                let barColor = '#9CA3AF'; // gray
                if (pct > 0 && pct < 100) { colorClass = 'pf-badge-blue'; barColor = '#3B82F6'; }
                if (pct === 100) { colorClass = 'pf-badge-green'; barColor = '#10B981'; }

                const barHtml = `
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="flex-grow:1; background:#E5E7EB; height:8px; border-radius:4px; overflow:hidden;">
                            <div style="width:${pct}%; background:${barColor}; height:100%; border-radius:4px; transition: width 0.3s ease;"></div>
                        </div>
                        <span style="font-size:12px; font-weight:600; min-width:35px; text-align:right;">${pct}%</span>
                    </div>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Diselesaikan: ${r.answered} dari ${r.total} form</div>
                `;

                html += `<tr>
                    <td>${i+1}</td>
                    <td><strong>${r.nama}</strong><br><small style="color:var(--text-muted)">Username: ${r.username}</small></td>
                    <td><span class="pf-badge ${colorClass}">${r.role}</span></td>
                    <td>${barHtml}</td>
                    <td style="text-align:right">
                        ${r.answered > 0 ? `<button class="btn btn-outline btn-sm" style="color:var(--danger-color); border-color:var(--danger-color);" onclick="Perf.resetPenilaian(${r.penilai_id}, '${r.penilai_type}', '${r.nama.replace(/'/g, "\\'")}')">Hapus</button>` : ''}
                    </td>
                </tr>`;
            });
            html += '</tbody></table>';
            $('#progressTable').html(html);
        });
    },

    resetPenilaian(penilai_id, penilai_type, nama) {
        EModal.form({
            title: 'Hapus Penilaian?',
            form: `
                <div style="margin-bottom:16px;">
                    Apakah Anda yakin ingin menghapus seluruh penilaian yang sudah dilakukan oleh <b>${nama}</b>?<br><br>
                    Data yang sudah dihapus tidak dapat dikembalikan.<br>
                    Ketik <b>HAPUS</b> untuk konfirmasi.
                </div>
                <div class="form-group">
                    <input type="text" id="confirmResetText" class="form-input" placeholder="Ketik HAPUS di sini..." autocomplete="off">
                </div>
            `,
            onConfirm: () => {
                const val = $('#confirmResetText').val().trim().toUpperCase();
                if (val !== 'HAPUS') {
                    EModal.toast({type: 'error', message: 'Ketik HAPUS untuk melanjutkan'});
                    return false;
                }
                
                EModal.toast({type:'info', message:'Menghapus...'});
                this.api('instrumen.php?action=reset_penilaian', {
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        penilai_id: penilai_id,
                        penilai_type: penilai_type,
                        periode_id: $('#progressPeriodeSelector').val() || this.state.selectedPeriodeId
                    })
                }).done(res => {
                    EModal.closeAll();
                    EModal.toast({type:'success', message: res.message});
                    this.loadProgressPenilaian($('#progressPeriodeSelector').val() || this.state.selectedPeriodeId);
                }).fail(xhr => {
                    EModal.toast({type:'error', message: xhr.responseJSON?.message || 'Gagal menghapus'});
                });
                
                return false;
            }
        });
    },

    copyLinkPenilaian() {
        const url = window.location.href.split('?')[0].split('#')[0]; // base e-performance URL
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(() => {
                EModal.toast({type: 'success', message: 'Link berhasil disalin!'});
            }).catch(() => {
                EModal.toast({type: 'error', message: 'Gagal menyalin link.'});
            });
        } else {
            // Fallback for non-https
            const textArea = document.createElement("textarea");
            textArea.value = url;
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                EModal.toast({type: 'success', message: 'Link berhasil disalin!'});
            } catch (err) {
                EModal.toast({type: 'error', message: 'Gagal menyalin link.'});
            }
            document.body.removeChild(textArea);
        }
    },

    renderLaporan($c) {
        this.api('periode.php?action=list', {method:'GET'}).done(pRes => {
            let opts = '<option value="">-- Pilih Periode Penilaian --</option>';
            if(pRes.data) {
                this.state.periodesData = pRes.data;
                pRes.data.forEach(p => {
                    opts += `<option value="${p.id}">${p.nama_periode} (${p.tahun_ajaran} Sem ${p.semester})</option>`;
                });
            }
            
            $c.html(`
                <div class="pf-card" style="margin-bottom: 16px;">
                    <div class="pf-card-body" style="display:flex; align-items:center; gap: 16px;">
                        <h3 style="margin:0; font-size: 1rem; width: 140px;">Pilih Periode:</h3>
                        <select class="form-input" id="laporanPeriodeSelector" style="flex-grow: 1;">
                            ${opts}
                        </select>
                    </div>
                </div>
                
                <div class="pf-card">
                    <div class="pf-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Daftar Progress Penilaian PTK</h3>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-primary btn-sm" onclick="Perf.renderMatrixManual($('#mainContent'))" style="background:var(--primary); color:white;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg> Penilaian Lainnya
                            </button>
                            <button id="btnReleaseHasil" class="btn btn-outline btn-sm" onclick="Perf.toggleReleaseHasil()" style="display:none; color:#10B981; border-color:#10B981;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <span>Release Hasil</span>
                            </button>
                            <button id="btnCetakRekapKeseluruhan" class="btn btn-primary btn-sm" onclick="window.open(window.PERF_CONFIG.moduleUrl + 'api/cetak_semua.php?periode_id=' + $('#laporanPeriodeSelector').val() + '&token=' + window.PERF_CONFIG.token, '_blank')" style="display:none; background:#10B981; border-color:#10B981; color:white;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Cetak Rekap Keseluruhan
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="Perf.loadLaporanData($('#laporanPeriodeSelector').val())">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="pf-card-body">
                        <div id="laporanTable">
                            <div class="pf-empty">Silakan pilih periode untuk melihat progress penilaian yang diterima PTK.</div>
                        </div>
                    </div>
                </div>
            `);

            $('#laporanPeriodeSelector').on('change', function() {
                const pid = $(this).val();
                Perf.state.selectedPeriodeId = pid || null;
                if(pid) {
                    Perf.loadLaporanData(pid);
                } else {
                    $('#laporanTable').html('<div class="pf-empty">Silakan pilih periode.</div>');
                }
            });

            if (this.state.selectedPeriodeId) {
                $('#laporanPeriodeSelector').val(this.state.selectedPeriodeId).trigger('change');
            }
        });
    },

    loadLaporanData(periode_id) {
        // Find periode to check is_released
        let p = this.state.periodesData ? this.state.periodesData.find(x => x.id == periode_id) : null;
        let isReleased = p && p.is_released == 1;
        let $btn = $('#btnReleaseHasil');
        let $btnCetak = $('#btnCetakRekapKeseluruhan');
        if (periode_id) {
            $btn.show();
            $btnCetak.show();
            if (isReleased) {
                $btn.html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><polyline points="20 6 9 17 4 12"/></svg> <span>Penilaian Telah Release</span>');
                $btn.css({color: '#4B5563', borderColor: '#D1D5DB', background: '#F3F4F6', cursor: 'pointer', opacity: '1'});
                $btn.prop('disabled', false);
            } else {
                $btn.html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <span>Release Hasil</span>');
                $btn.css({color: '#10B981', borderColor: '#10B981', background: 'transparent', cursor: 'pointer', opacity: '1'});
                $btn.prop('disabled', false);
            }
        } else {
            $btn.hide();
            $btnCetak.hide();
        }

        this.api(`instrumen.php?action=list_laporan&periode_id=${periode_id}`).done(res => {
            if(!res.data || !res.data.length) {
                $('#laporanTable').html('<div class="pf-empty">Tidak ada data PTK atau instruksi penilaian di periode ini.</div>');
                return;
            }

            let html = `<table class="pf-table">
                <thead>
                    <tr>
                        <th width="30%">Nama PTK</th>
                        <th width="15%">Tupoksi</th>
                        <th width="35%">Progress Diterima</th>
                        <th width="20%" style="text-align:right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;

            res.data.forEach(u => {
                let color = u.percentage < 50 ? '#EF4444' : (u.percentage < 100 ? '#F59E0B' : '#10B981');
                let barHtml = `
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:12px; font-weight:600;">
                        <span>${u.answered} / ${u.total} Penilai</span>
                        <span style="color:${color}">${u.percentage}%</span>
                    </div>
                    <div class="pf-progress-bg">
                        <div class="pf-progress-bar" style="width: ${u.percentage}%; background: ${color}"></div>
                    </div>
                `;

                html += `<tr>
                    <td><div style="font-weight:600; color:#1F2937;">${u.nama}</div></td>
                    <td><span class="pf-badge pf-badge-gray">${u.jenis_ptk}</span></td>
                    <td>${barHtml}</td>
                    <td style="text-align:right">
                        <div style="display:flex; gap:4px; justify-content:flex-end">

                            <button class="btn btn-outline btn-sm" onclick="EModal.alert('Fitur Belum Siap', 'Lihat Laporan akan segera hadir di pembaruan selanjutnya.')">Lihat Laporan</button>
                            <button class="btn btn-primary btn-sm" onclick="Perf.openModalCetak(${u.id}, '${u.nama.replace(/'/g, "\\'")}')" style="background:#4F46E5; color:white; border:none;">Cetak</button>
                        </div>
                    </td>
                </tr>`;
            });
            html += '</tbody></table>';
            $('#laporanTable').html(html);
        });
    },

    openModalCetak(ptk_id, ptk_nama) {
        if (!this.state.selectedPeriodeId) return EModal.toast({type: 'error', message: 'Pilih periode terlebih dahulu'});
        const url = `${window.PERF_CONFIG.moduleUrl}api/cetak_lampiran.php?ptk_id=${ptk_id}&periode_id=${this.state.selectedPeriodeId}&token=${window.PERF_CONFIG.token}&l1=1&l2=1&diri=1&siswa=1`;
        window.open(url, '_blank');
    },

    toggleReleaseHasil() {
        const pid = this.state.selectedPeriodeId;
        if (!pid) return EModal.toast({type: 'error', message: 'Pilih periode terlebih dahulu'});
        
        let p = this.state.periodesData ? this.state.periodesData.find(x => x.id == pid) : null;
        if (!p) return;
        
        let currentReleased = p.is_released == 1;
        let newStatus = currentReleased ? 0 : 1;
        
        let confirmMsg = currentReleased ? 
            'Anda yakin ingin <b style="color:#EF4444">menarik kembali (Batal Release)</b> hasil penilaian ini? PTK tidak akan bisa melihat hasilnya lagi.' : 
            'Anda yakin ingin <b style="color:#10B981">merilis</b> hasil penilaian ini? PTK akan dapat melihat hasilnya di akun mereka masing-masing.';
            
        EModal.confirm({
            title: 'Konfirmasi Release Hasil',
            message: confirmMsg,
            onConfirm: () => {
                this.api('periode.php?action=toggle_release', {
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ id: pid, is_released: newStatus })
                }).done(res => {
                    if (res.success) {
                        EModal.toast({type: 'success', message: res.message});
                        p.is_released = newStatus;
                        this.loadLaporanData(pid); // Refresh button UI
                    } else {
                        EModal.toast({type: 'error', message: res.message || 'Gagal mengubah status'});
                    }
                }).fail(xhr => {
                    EModal.toast({type: 'error', message: xhr.responseJSON?.message || 'Gagal terhubung ke server / Akses ditolak'});
                });
            }
        });
    },

    renderHasilSaya($c) {
        this.api('periode.php?action=list', {method:'GET'}).done(pRes => {
            let releasedPeriodes = [];
            if(pRes.data) {
                releasedPeriodes = pRes.data.filter(p => p.is_released == 1);
            }
            
            if(releasedPeriodes.length === 0) {
                $c.html(`
                    <div class="pf-card" style="margin-bottom: 16px;">
                        <div class="pf-card-body pf-empty">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 10px; color: var(--text-muted);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            <h3>Belum Ada Hasil Penilaian</h3>
                            <p>Saat ini belum ada hasil penilaian yang di-release (diterbitkan) untuk Anda.</p>
                        </div>
                    </div>
                `);
                return;
            }

            let opts = '';
            releasedPeriodes.forEach(p => {
                opts += `<option value="${p.id}">${p.nama_periode} (${p.tahun_ajaran} Sem ${p.semester})</option>`;
            });
            
            $c.html(`
                <div class="pf-card" style="margin-bottom: 16px;">
                    <div class="pf-card-body" style="display:flex; justify-content:space-between; align-items:center; gap: 16px;">
                        <div style="display:flex; align-items:center; gap: 16px; flex-grow:1;">
                            <h3 style="margin:0; font-size: 1rem; width: 140px;">Pilih Periode:</h3>
                            <select class="form-input" id="hasilSayaPeriodeSelector" style="max-width: 400px;">
                                ${opts}
                            </select>
                        </div>
                        <button id="btnDownloadHasilSaya" class="btn btn-primary" style="background:#4F46E5; color:white; border:none; display:none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Download / Cetak PDF
                        </button>
                    </div>
                </div>
                
                <div class="pf-card" id="hasilSayaContainer" style="display:none; padding: 0; overflow: hidden; border-radius: 8px;">
                    <iframe id="iframeHasilSaya" style="width: 100%; height: 800px; border: none; display: block; background: #525659;"></iframe>
                </div>
            `);

            $('#hasilSayaPeriodeSelector').on('change', function() {
                const pid = $(this).val();
                if(pid) {
                    $('#hasilSayaContainer').show();
                    $('#btnDownloadHasilSaya').show().off('click').on('click', function() {
                        window.open(`api/cetak_lampiran.php?periode_id=${pid}&ptk_id=${Perf.state.user.ptk_id}`, '_blank');
                    });
                    // Use iframe to show result directly in the app
                    $('#iframeHasilSaya').attr('src', `api/cetak_lampiran.php?periode_id=${pid}&ptk_id=${Perf.state.user.ptk_id}`);
                } else {
                    $('#hasilSayaContainer').hide();
                    $('#btnDownloadHasilSaya').hide();
                }
            });

            // Trigger load for the first item
            if (releasedPeriodes.length > 0) {
                $('#hasilSayaPeriodeSelector').trigger('change');
            }
        });
    },

    processExport(format, ptk_id, ptk_nama, btn) {
        const selected = $('.rekap-check:checked').map((_, el) => el.value).get();
        if (selected.length === 0) {
            return EModal.toast({type: 'warning', message: 'Pilih minimal satu laporan untuk dicetak.'});
        }
        
        if (btn) EModal.btnLoading(btn, true);
        
        let requests = [];

        if ((selected.includes('lampiran1') || selected.includes('lampiran2')) && format === 'pdf') {
            let url_params = [];
            if (selected.includes('lampiran1')) url_params.push('l1=1');
            if (selected.includes('lampiran2')) url_params.push('l2=1');
            const url = `${window.PERF_CONFIG.moduleUrl}api/cetak_lampiran.php?ptk_id=${ptk_id}&periode_id=${this.state.selectedPeriodeId}&token=${window.PERF_CONFIG.token}&${url_params.join('&')}`;
            window.open(url, '_blank');
        }

        if (selected.includes('diri_sendiri')) {
            requests.push(this.api(`rekap.php?action=get_diri_sendiri&periode_id=${this.state.selectedPeriodeId}&ptk_id=${ptk_id}`).done(res => {
                if(res.success && res.data) {
                    if(format === 'excel') this.exportDiriSendiriExcel(res.data, ptk_nama);
                    else if (format === 'pdf') this.exportDiriSendiriPDF(res.data, ptk_nama);
                }
            }));
        }

        if (selected.includes('siswa')) {
            requests.push(this.api(`rekap.php?action=get_siswa&periode_id=${this.state.selectedPeriodeId}&ptk_id=${ptk_id}`).done(res => {
                if(res.success && res.data) {
                    if(format === 'excel') this.exportSiswaExcel(res.data, ptk_nama);
                    else if (format === 'pdf') this.exportSiswaPDF(res.data, ptk_nama);
                }
            }));
        }

        $.when(...requests).always(() => {
            // Because html2pdf takes time asynchronously, the button might stop loading a bit early, but it's fine
            // We can wait a tiny bit to feel natural
            setTimeout(() => { if (btn) EModal.btnLoading(btn, false); }, 500);
        });
    },

    exportDiriSendiriExcel(data, ptk_nama) {
        this.loadSheetJS(() => {
            const rekap = data.rekap || [];
            if(rekap.length === 0) {
                return EModal.toast({type:'warning', message:'Tidak ada data penilaian diri sendiri untuk PTK ini.'});
            }
            
            // Format data for Excel
            let excelData = [];
            let no = 1;
            
            rekap.forEach(group => {
                let isFirstInGroup = true;
                group.pertanyaan.forEach(p => {
                    excelData.push({
                        'No': no++,
                        'Kompetensi': isFirstInGroup ? group.kategori : '',
                        'Pertanyaan': p.teks,
                        'SKOR': p.skor,
                        'RT2': isFirstInGroup ? group.rt2 : '',
                        'KONVERSI': isFirstInGroup ? group.konversi : ''
                    });
                    isFirstInGroup = false;
                });
            });
            
            const ws = XLSX.utils.json_to_sheet(excelData);
            
            // Adjust column widths
            const wscols = [
                {wch: 5},   // No
                {wch: 25},  // Kompetensi
                {wch: 80},  // Pertanyaan
                {wch: 8},   // SKOR
                {wch: 8},   // RT2
                {wch: 12}   // KONVERSI
            ];
            ws['!cols'] = wscols;

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Penilaian Diri Sendiri');
            XLSX.writeFile(wb, `Rekap_Penilaian_${ptk_nama.replace(/[^a-z0-9]/gi, '_')}.xlsx`);
        });
    },

    exportSiswaExcel(data, ptk_nama, btn) {
        this.loadSheetJS(() => {
            if (btn) EModal.btnLoading(btn, false);
            const rekap = data.rekap || [];
            if(rekap.length === 0) {
                return EModal.toast({type:'warning', message:'Tidak ada data penilaian siswa untuk PTK ini.'});
            }

            const muridLabels = data.murid_labels || [];
            let excelData = [];
            let no = 1;

            rekap.forEach(group => {
                group.pertanyaan.forEach(p => {
                    let row = {
                        'No': no++,
                        'Kompetensi': group.kategori,
                        'Pertanyaan': p.teks
                    };
                    muridLabels.forEach(lbl => {
                        row[lbl] = p.skor_murid[lbl] || 0;
                    });
                    excelData.push(row);
                });
            });

            const ws = XLSX.utils.json_to_sheet(excelData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Rekap Siswa');
            XLSX.writeFile(wb, `Rekap_Penilaian_Siswa_${ptk_nama.replace(/[^a-z0-9]/gi, '_')}.xlsx`);
        });
    },

    loadHtml2Pdf(cb) {
        if (window.html2pdf) { cb(); return; }
        const l = EModal.loading('Memuat komponen PDF...');
        $.getScript('https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js')
            .done(() => { EModal.close(l); cb(); })
            .fail(() => { EModal.close(l); EModal.toast({type:'error',message:'Gagal memuat html2pdf.'}); });
    },

    exportDiriSendiriPDF(data, ptk_nama) {
        this.loadHtml2Pdf(() => {
            const rekap = data.rekap || [];
            if(rekap.length === 0) {
                return EModal.toast({type:'warning', message:'Tidak ada data penilaian diri sendiri untuk PTK ini.'});
            }

            let trs = '';
            let no = 1;
            rekap.forEach(group => {
                let isFirstInGroup = true;
                const rowSpan = group.pertanyaan.length;
                
                group.pertanyaan.forEach(p => {
                    trs += `<tr style="page-break-inside: avoid;">
                        <td class="thin-border" style="padding:6px; text-align:center;">${no++}</td>`;
                    
                    if (isFirstInGroup) {
                        trs += `<td class="thin-border" rowspan="${rowSpan}" style="padding:6px; vertical-align:top;">${group.kategori}</td>`;
                    }
                    
                    trs += `<td class="thin-border" style="padding:6px;">${p.teks}</td>
                        <td class="thin-border" style="padding:6px; text-align:center;">${p.skor}</td>`;
                    
                    if (isFirstInGroup) {
                        trs += `<td class="thin-border" rowspan="${rowSpan}" style="padding:6px; text-align:center; vertical-align:top;">${group.rt2}</td>
                                <td class="thin-border" rowspan="${rowSpan}" style="padding:6px; text-align:center; vertical-align:top;">${group.konversi}</td>`;
                    }
                    
                    trs += `</tr>`;
                    isFirstInGroup = false;
                });
            });

            const htmlContent = `
                <style>
                    .thin-border { border: 0.5px solid #000; }
                </style>
                <div style="padding: 10px; font-family: 'Times New Roman', Times, serif; color: #000;">
                    <h2 style="text-align:center; font-size: 18px; margin-bottom: 20px; text-transform:uppercase;">REKAPITULASI PENILAIAN DIRI SENDIRI<br>${ptk_nama}</h2>
                    <table style="width:100%; border-collapse: collapse; font-size: 12.5px;">
                        <thead>
                            <tr>
                                <th class="thin-border" style="padding:8px; text-align:center;">No</th>
                                <th class="thin-border" style="padding:8px; text-align:center;">Kompetensi</th>
                                <th class="thin-border" style="padding:8px; text-align:center;">Pertanyaan</th>
                                <th class="thin-border" style="padding:8px; text-align:center;">SKOR</th>
                                <th class="thin-border" style="padding:8px; text-align:center;">RT2</th>
                                <th class="thin-border" style="padding:8px; text-align:center;">KONVERSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${trs}
                        </tbody>
                    </table>
                </div>
            `;

            const opt = {
                margin:       [10, 5, 10, 5], // Top, Right, Bottom, Left
                filename:     `Rekap_Penilaian_Diri_${ptk_nama.replace(/[^a-z0-9]/gi, '_')}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: [210, 330], orientation: 'portrait' },
                pagebreak:    { mode: ['css', 'legacy', 'avoid-all'] }
            };

            const element = document.createElement('div');
            element.innerHTML = htmlContent;
            html2pdf().set(opt).from(element).save();
        });
    },

    exportSiswaPDF(data, ptk_nama, btn) {
        this.loadHtml2Pdf(() => {
            if (btn) EModal.btnLoading(btn, false);
            const rekap = data.rekap || [];
            if(rekap.length === 0) {
                return EModal.toast({type:'warning', message:'Tidak ada data penilaian siswa untuk PTK ini.'});
            }

            const muridLabels = data.murid_labels || [];
            let trs = '';
            let no = 1;

            rekap.forEach(group => {
                let isFirstInGroup = true;
                const rowSpan = group.pertanyaan.length;
                
                group.pertanyaan.forEach(p => {
                    trs += `<tr style="page-break-inside: avoid;">
                        <td class="thin-border" style="padding:6px; text-align:center;">${no++}</td>`;
                    
                    if (isFirstInGroup) {
                        trs += `<td class="thin-border" rowspan="${rowSpan}" style="padding:6px; vertical-align:top;">${group.kategori}</td>`;
                    }
                    
                    trs += `<td class="thin-border" style="padding:6px;">${p.teks}</td>`;
                    
                    muridLabels.forEach(lbl => {
                        trs += `<td class="thin-border" style="padding:6px; text-align:center;">${p.skor_murid[lbl] || 0}</td>`;
                    });
                    
                    trs += `</tr>`;
                    isFirstInGroup = false;
                });
            });

            const thMurid = muridLabels.map(lbl => `<th class="thin-border" style="padding:8px; text-align:center;">${lbl}</th>`).join('');

            const htmlContent = `
                <style>
                    .thin-border { border: 0.5px solid #000; }
                </style>
                <div style="padding: 10px; font-family: 'Times New Roman', Times, serif; color: #000;">
                    <h2 style="text-align:center; font-size: 18px; margin-bottom: 20px; text-transform:uppercase;">REKAPITULASI PENILAIAN OLEH SISWA<br>${ptk_nama}</h2>
                    <table style="width:100%; border-collapse: collapse; font-size: 11px;">
                        <thead>
                            <tr>
                                <th class="thin-border" style="padding:8px; text-align:center; width:30px;">No</th>
                                <th class="thin-border" style="padding:8px; text-align:center; width:120px;">Kompetensi</th>
                                <th class="thin-border" style="padding:8px; text-align:center;">Pertanyaan</th>
                                ${thMurid}
                            </tr>
                        </thead>
                        <tbody>
                            ${trs}
                        </tbody>
                    </table>
                </div>
            `;

            const opt = {
                margin:       [10, 5, 10, 5],
                filename:     `Rekap_Penilaian_Siswa_${ptk_nama.replace(/[^a-z0-9]/gi, '_')}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: [210, 330], orientation: 'landscape' }, // Landscape mode
                pagebreak:    { mode: ['css', 'legacy', 'avoid-all'] }
            };

            const element = document.createElement('div');
            element.innerHTML = htmlContent;
            html2pdf().set(opt).from(element).save();
        });
    },

    openManualInput(ptk_id, ptk_nama) {
        if(!this.state.selectedPeriodeId) return EModal.toast({type:'error', message:'Pilih periode terlebih dahulu'});
        
        // Modal content with tabs for 4 aspects
        const formHtml = `
            <div id="manualInputContainer">
                <div class="pf-empty">Memuat data form...</div>
            </div>
        `;
        
        EModal.form({
            title: `Input Manual: ${ptk_nama}`,
            width: '1100px',
            form: formHtml,
            onConfirm: () => {
                this.saveManualInput(ptk_id);
                return false; // Prevent auto close to wait for API response
            }
        });
        
        // Load data from API
        this.api(`manual_penilaian.php?action=load&periode_id=${this.state.selectedPeriodeId}&ptk_id=${ptk_id}`).done(res => {
            this.currentManualData = res.data;
            this.renderManualInputForm();
        }).fail(() => {
            $('#manualInputContainer').html('<div class="pf-empty">Gagal memuat data.</div>');
        });
    },

    renderManualInputForm() {
        if (!this.currentManualData) return;
        
        let scrollTop = $('#manualInputScroll').scrollTop() || 0;
        
        let d = this.currentManualData.saved || {};
        let instrumen = this.currentManualData.instrumen || {};

        let rowsHtml = '';
        let no = 1;
        let categories = Object.keys(instrumen);

        if (categories.length === 0) {
            $('#manualInputContainer').html('<div class="pf-empty">Tidak ada soal manual untuk tupoksi PTK ini. Tambahkan Soal Manual terlebih dahulu.</div>');
            return;
        }

        categories.forEach((kat, i) => {
            rowsHtml += `
                <tr class="row-header">
                    <td class="col-no">${no++}</td>
                    <td style="font-weight:600">${kat}</td>
                    <td class="col-skor" id="skor_kat_${i}">0</td>
                </tr>
            `;

            instrumen[kat].forEach(q => {
                let val = d[q.id] || 0;
                rowsHtml += `
                    <tr>
                        <td></td>
                        <td>${q.pertanyaan}</td>
                        <td style="text-align:center; vertical-align:middle;">
                            <input type="number" class="form-input" style="width:60px; text-align:center; height: 32px;" value="${val}" oninput="if (!Perf.currentManualData.saved) Perf.currentManualData.saved = {}; Perf.currentManualData.saved['${q.id}'] = parseFloat(this.value)||0; Perf.calcManualScores()">
                        </td>
                    </tr>
                `;
            });
        });

        $('#manualInputContainer').html(`
            <style>
                .tbl-manual { width: 100%; border-collapse: collapse; font-size: 13px; font-family: sans-serif; }
                .tbl-manual th, .tbl-manual td { border: 1px solid #ccc; padding: 6px 8px; vertical-align: middle; }
                .tbl-manual th { text-align: center; font-weight: 600; text-transform: uppercase; background: #fff; }
                .tbl-manual .col-no { width: 40px; text-align: center; }
                .tbl-manual .col-skor { width: 80px; text-align: center; font-weight: bold; }
                /* Sembunyikan icon panah (spinner) pada input number */
                .tbl-manual input[type="number"]::-webkit-outer-spin-button,
                .tbl-manual input[type="number"]::-webkit-inner-spin-button {
                    -webkit-appearance: none;
                    margin: 0;
                }
                .tbl-manual input[type="number"] {
                    -moz-appearance: textfield;
                }
            </style>
            
            <div id="manualInputScroll" style="max-height: 70vh; overflow-y:auto; padding-right:4px;">
                <table class="tbl-manual">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th>ASPEK</th>
                            <th class="col-skor">NILAI</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f3f4f6; font-weight: bold; font-size: 15px;">
                            <td colspan="2" style="text-align: right; padding-right: 16px;">TOTAL NILAI KESELURUHAN (Rata-rata dari ${categories.length} Aspek):</td>
                            <td class="col-skor" id="skor_akhir_total" style="color: #059669; font-size: 18px;">0</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        `);
        
        if (scrollTop > 0) {
            $('#manualInputScroll').scrollTop(scrollTop);
        }

        this.calcManualScores();
    },
    
    calcManualScores() {
        let saved = this.currentManualData.saved || {};
        let instrumen = this.currentManualData.instrumen || {};
        
        let totalAll = 0;
        let katCount = 0;

        let categories = Object.keys(instrumen);
        categories.forEach((kat, i) => {
            let sum = 0;
            instrumen[kat].forEach(q => {
                sum += (parseFloat(saved[q.id]) || 0);
            });
            $('#skor_kat_' + i).text(sum);
            totalAll += sum;
            katCount++;
        });

        // TOTAL AKHIR (Rata-rata antar Aspek jika diinginkan, atau jumlah total. Mengikuti sistem sebelumnya yang merata-ratakan 4 aspek)
        let skorAkhir = katCount > 0 ? Math.round(totalAll / katCount) : 0;
        $('#skor_akhir_total').text(skorAkhir);
    },
    
    saveManualInput(ptk_id) {
        if(!this.currentManualData) return;
        
        const payload = {
            payload: this.currentManualData.saved || {}
        };
        
        // Show saving status on the button if possible, or just send request
        $('.pf-modal-footer .pf-btn-primary').prop('disabled', true).text('Menyimpan...');
        
        this.api('manual_penilaian.php?action=save&periode_id=' + this.state.selectedPeriodeId + '&ptk_id=' + ptk_id, {
            method: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json'
        }).done(res => {
            EModal.closeAll();
            EModal.toast({type:'success', message:res.message});
        }).fail(xhr => {
            $('.pf-modal-footer .pf-btn-primary').prop('disabled', false).text('Simpan');
            EModal.toast({type:'error', message:xhr.responseJSON?.message || 'Gagal menyimpan'});
        });
    },

    toggleBulkDeleteBtn(is_manual = 0) {
        if (is_manual === 1) {
            if ($('.chk-manual:checked').length > 0) {
                $('#btnBulkHapusManual').show();
            } else {
                $('#btnBulkHapusManual').hide();
            }
        } else {
            if ($('.chk-pertanyaan:checked').length > 0) {
                $('#btnBulkHapusPertanyaan').show();
            } else {
                $('#btnBulkHapusPertanyaan').hide();
            }
        }
    },

    hapusPertanyaan(id, is_manual = 0) {
        EModal.confirm({
            title: 'Konfirmasi',
            message: 'Yakin ingin menghapus pertanyaan ini?',
            onConfirm: () => {
                this.api('instrumen.php?action=delete_pertanyaan', {method:'POST', data: {id}}).done(res => {
                    EModal.closeAll();
                    if (is_manual === 1) this.loadManualPertanyaan(); else this.loadPertanyaan();
                    EModal.toast({type: 'success', message: res.message});
                }).fail(xhr => {
                    EModal.toast({type: 'error', message: xhr.responseJSON?.message || 'Gagal menghapus pertanyaan'});
                });
            }
        });
    },

    bulkHapusPertanyaan(is_manual = 0) {
        const ids = [];
        $(is_manual === 1 ? '.chk-manual:checked' : '.chk-pertanyaan:checked').each(function() {
            ids.push(parseInt($(this).val()));
        });
        if (ids.length === 0) return;
        
        EModal.confirm({
            title: 'Hapus Pertanyaan Terpilih',
            message: `Yakin ingin menghapus ${ids.length} pertanyaan yang dipilih? Jika ada pertanyaan yang sudah dinilai, maka pertanyaan tersebut tidak akan dihapus.`,
            onConfirm: () => {
                this.api('instrumen.php?action=bulk_delete_pertanyaan', {method:'POST', data: {ids}}).done(res => {
                    EModal.closeAll();
                    if (is_manual === 1) this.loadManualPertanyaan(); else this.loadPertanyaan();
                    if (res.data?.failed > 0) {
                        EModal.toast({type: 'warning', message: res.message, duration: 5000});
                    } else {
                        EModal.toast({type: 'success', message: res.message});
                    }
                }).fail(xhr => {
                    EModal.toast({type: 'error', message: xhr.responseJSON?.message || 'Gagal menghapus pertanyaan'});
                });
            }
        });
    },

    hapusPenilai(id) {
        EModal.confirm({
            title: 'Hapus Penilai',
            message: 'Yakin ingin menghapus jenis penilai ini?',
            onConfirm: () => {
                this.api('instrumen.php?action=delete_penilai', {method:'POST', data:{id}}).done(res => {
                    EModal.toast({type:'success', message:res.message});
                    this.loadPenilai();
                }).fail(xhr => EModal.toast({type:'error', message:xhr.responseJSON?.message}));
            }
        });
    },

    modalTambahPertanyaan(is_manual = 0) {
        if(!this.state.selectedPeriodeId) return EModal.toast({type:'error', message:'Pilih periode terlebih dahulu'});
        
        const reqPenilai = this.api('instrumen.php?action=list_penilai&periode_id=' + this.state.selectedPeriodeId);
        const reqDinilai = this.api('instrumen.php?action=list_dinilai');
        
        $.when(reqPenilai, reqDinilai).done((resPenilai, resDinilai) => {
            const basePenilai = resPenilai[0]?.data || [];
            let dinilaiList = resDinilai[0]?.data || [];
            
            let penilaiNames = new Set(basePenilai.map(p => p.jenis_penilai));
            dinilaiList.forEach(p => penilaiNames.add(p.jenis_ptk));
            
            // Filter Teman Sejawat & Diri Sendiri dari daftar Penilai sesuai permintaan
            penilaiNames.delete('Teman Sejawat');
            penilaiNames.delete('Diri Sendiri');
            
            const penilaiList = Array.from(penilaiNames).map(name => ({jenis_penilai: name}));
            
            // If it is manual question, we don't necessarily need Penilai (it will be manually inputted by admin/operator)
            // But if we want to keep the UI simple, we can hide the Penilai field or just set it internally.
            // Let's just keep the original logic but hide Penilai if is_manual == 1, because manual question is inputted directly.
            // Wait, the user said: "soal manual Katgori 1. soal Nilai 2. Soal Nilai. input nilainya langsung dari tabel"
            // Actually, manual inputs don't have a "Penilai" because they are filled by Admin/Operator from the report directly.
            // We can just hide "Siapa yang menilai" if is_manual = 1.
            let penilaiCheckboxes = penilaiList.map(p => 
                `<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <input type="checkbox" class="penilai-checkbox" value="${p.jenis_penilai}" ${is_manual ? 'checked' : ''}> ${p.jenis_penilai}
                </label>`
            ).join('');
            
            let dinilaiCheckboxes = dinilaiList.map(p => 
                `<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <input type="checkbox" class="dinilai-checkbox" value="${p.jenis_ptk}"> ${p.jenis_ptk}
                </label>`
            ).join('');

            EModal.form({
                title: is_manual ? 'Tambah Soal Manual' : 'Tambah Pertanyaan',
                form: `
                    <div class="form-group">
                        <label>Kategori Penilaian</label>
                        <input type="text" class="form-input" id="fKategori" placeholder="Contoh: ${is_manual ? 'Penilaian Kinerja' : 'Kompetensi Pedagogik'}" required>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px;">
                        <div class="form-group" style="${is_manual ? 'display:none;' : ''}">
                            <label>Siapa yang menilai?</label>
                            <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; max-height:120px; overflow-y:auto; background:var(--bg-light);">
                                ${penilaiCheckboxes}
                            </div>
                        </div>
                        <div class="form-group" style="${is_manual ? 'grid-column: span 2;' : ''}">
                            <label>Siapa yang dinilai? (Tupoksi)</label>
                            <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; max-height:120px; overflow-y:auto; background:var(--bg-light);">
                                ${dinilaiCheckboxes}
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label>Pertanyaan</label>
                        <textarea class="form-input" id="fPertanyaan" rows="3" placeholder="Tulis pertanyaan di sini..." required style="resize:vertical"></textarea>
                    </div>
                    ${is_manual ? `
                    <div class="form-group" style="margin-top:16px;">
                        <label>Tipe Isian</label>
                        <select class="form-input" id="fTipeJawaban" onchange="document.getElementById('divSkorYaTidak').style.display = this.value === 'ya_tidak' ? 'flex' : 'none';">
                            <option value="angka">Angka Bebas (Numerik)</option>
                            <option value="ya_tidak">Pilihan (Ya / Tidak)</option>
                        </select>
                    </div>
                    <div id="divSkorYaTidak" style="display:none; gap:16px; margin-top:16px;">
                        <div class="form-group" style="flex:1;">
                            <label>Nilai Jika Ya</label>
                            <input type="number" step="0.01" class="form-input" id="fSkorYa" value="100">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Nilai Jika Tidak</label>
                            <input type="number" step="0.01" class="form-input" id="fSkorTidak" value="0">
                        </div>
                    </div>
                    ` : ''}
                `,
                onConfirm: () => {
                    let checkedPenilai = [];
                    let checkedDinilai = [];
                    $('.penilai-checkbox:checked').each(function(){ checkedPenilai.push($(this).val()); });
                    $('.dinilai-checkbox:checked').each(function(){ checkedDinilai.push($(this).val()); });
                    
                    const data = {
                        kategori: $('#fKategori').val(),
                        pertanyaan: $('#fPertanyaan').val(),
                        target_penilai: is_manual ? ['Admin'] : checkedPenilai,
                        target_dinilai: checkedDinilai,
                        periode_id: this.state.selectedPeriodeId,
                        is_manual: is_manual,
                        tipe_jawaban: is_manual ? $('#fTipeJawaban').val() : 'angka',
                        skor_ya: is_manual ? $('#fSkorYa').val() : 100,
                        skor_tidak: is_manual ? $('#fSkorTidak').val() : 0
                    };
                    
                    if (!data.kategori || !data.pertanyaan) return false;
                    if (!is_manual && checkedPenilai.length === 0) {
                        EModal.toast({type:'warning', message:'Pilih minimal satu penilai.'});
                        return false;
                    }
                    if (checkedDinilai.length === 0) {
                        EModal.toast({type:'warning', message:'Pilih minimal satu yang dinilai.'});
                        return false;
                    }
                    
                    this.api('instrumen.php?action=create_pertanyaan', {method:'POST', data}).done(res => {
                        EModal.closeAll();
                        EModal.toast({type:'success', message: res.message});
                        if (is_manual) this.loadManualPertanyaan(); else this.loadPertanyaan();
                    }).fail(xhr => EModal.toast({type:'error', message: xhr.responseJSON?.message}));
                    return false;
                }
            });
        });
    },

    modalTambahAturanSejawat() {
        if(!this.state.selectedPeriodeId) return EModal.toast({type:'error', message:'Pilih periode terlebih dahulu'});
        
        const reqPenilai = this.api('instrumen.php?action=list_dinilai'); // tupoksi yang ada
        const reqDinilai = this.api('instrumen.php?action=list_dinilai');
        
        $.when(reqPenilai, reqDinilai).done((resPenilai, resDinilai) => {
            let penilaiList = resPenilai[0]?.data || [];
            let dinilaiList = resDinilai[0]?.data || [];
            
            // Filter out non-tupoksi roles
            penilaiList = penilaiList.filter(p => p.jenis_ptk !== 'Teman Sejawat' && p.jenis_ptk !== 'Diri Sendiri');
            dinilaiList = dinilaiList.filter(p => p.jenis_ptk !== 'Teman Sejawat' && p.jenis_ptk !== 'Diri Sendiri');

            let penilaiCheckboxes = penilaiList.map(p => 
                `<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <input type="radio" name="aturan_penilai" class="aturan-penilai-radio" value="${p.jenis_ptk}"> ${p.jenis_ptk}
                </label>`
            ).join('');
            
            let dinilaiCheckboxes = dinilaiList.map(p => 
                `<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <input type="checkbox" class="aturan-dinilai-checkbox" value="${p.jenis_ptk}"> ${p.jenis_ptk}
                </label>`
            ).join('');

            EModal.form({
                title: 'Tambah Aturan Teman Sejawat',
                form: `
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        <div class="form-group">
                            <label>Tupoksi Penilai</label>
                            <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; max-height:200px; overflow-y:auto; background:var(--bg-light);">
                                ${penilaiCheckboxes}
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Tupoksi Target (Teman Sejawat)</label>
                            <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; max-height:200px; overflow-y:auto; background:var(--bg-light);">
                                ${dinilaiCheckboxes}
                            </div>
                        </div>
                    </div>
                `,
                onConfirm: () => {
                    let checkedPenilai = [];
                    let checkedDinilai = [];
                    $('.aturan-penilai-radio:checked').each(function(){ checkedPenilai.push($(this).val()); });
                    $('.aturan-dinilai-checkbox:checked').each(function(){ checkedDinilai.push($(this).val()); });
                    
                    const data = {
                        penilai: checkedPenilai,
                        dinilai: checkedDinilai,
                        periode_id: this.state.selectedPeriodeId
                    };
                    
                    if (checkedPenilai.length === 0 || checkedDinilai.length === 0) {
                        EModal.toast({type:'warning', message:'Pilih minimal satu penilai dan satu target dinilai.'});
                        return false;
                    }
                    
                    this.api('instrumen.php?action=create_aturan_sejawat', {method:'POST', data}).done(res => {
                        EModal.closeAll();
                        EModal.toast({type:'success', message: res.message});
                        this.loadAturanSejawat();
                    }).fail(xhr => EModal.toast({type:'error', message: xhr.responseJSON?.message}));
                    return false;
                }
            });
        });
    },

    hapusAturanSejawat(penilai_jenis) {
        EModal.confirm({
            title: 'Hapus Aturan',
            message: `Yakin ingin menghapus semua aturan target untuk penilai: ${penilai_jenis}?`,
            onConfirm: () => {
                this.api('instrumen.php?action=delete_aturan_sejawat', {method:'POST', data:{
                    penilai_jenis: penilai_jenis,
                    periode_id: this.state.selectedPeriodeId
                }}).done(res => {
                    EModal.toast({type:'success', message:res.message});
                    this.loadAturanSejawat();
                }).fail(xhr => EModal.toast({type:'error', message:xhr.responseJSON?.message}));
            }
        });
    },

    downloadTemplate(is_manual = 0) {
        this.loadSheetJS(() => {
            let data = [];
            if (is_manual) {
                data = [
                    {'Kategori Penilaian': 'Kompetensi Pedagogik', 'Pertanyaan': 'Guru mampu mengelola pembelajaran dengan baik.', 'Tipe Isian': 'angka', 'Skor Ya': 100, 'Skor Tidak': 0},
                    {'Kategori Penilaian': 'Kedisiplinan', 'Pertanyaan': 'Apakah guru pernah mendapat surat peringatan?', 'Tipe Isian': 'ya_tidak', 'Skor Ya': 50, 'Skor Tidak': 0}
                ];
            } else {
                data = [
                    {'Kategori Penilaian': 'Kompetensi Pedagogik', 'Pertanyaan': 'Guru mampu mengelola pembelajaran dengan baik.'},
                    {'Kategori Penilaian': 'Kompetensi Kepribadian', 'Pertanyaan': 'Guru bertindak sesuai dengan norma agama dan hukum.'}
                ];
            }
            const ws = XLSX.utils.json_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Template');
            XLSX.writeFile(wb, 'Template_Import_Pertanyaan.xlsx');
        });
    },

    modalImportPertanyaan(is_manual = 0) {
        if(!this.state.selectedPeriodeId) return EModal.toast({type:'error', message:'Pilih periode terlebih dahulu'});
        
        const reqPenilai = this.api('instrumen.php?action=list_penilai&periode_id=' + this.state.selectedPeriodeId);
        const reqDinilai = this.api('instrumen.php?action=list_dinilai');
        
        $.when(reqPenilai, reqDinilai).done((resPenilai, resDinilai) => {
            const basePenilai = resPenilai[0]?.data || [];
            let dinilaiList = resDinilai[0]?.data || [];
            
            let penilaiNames = new Set(basePenilai.map(p => p.jenis_penilai));
            dinilaiList.forEach(p => penilaiNames.add(p.jenis_ptk));
            
            // Filter Teman Sejawat & Diri Sendiri dari daftar Penilai sesuai permintaan
            penilaiNames.delete('Teman Sejawat');
            penilaiNames.delete('Diri Sendiri');
            
            const penilaiList = Array.from(penilaiNames).map(name => ({jenis_penilai: name}));
            
            let penilaiCheckboxes = penilaiList.map(p => 
                `<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <input type="checkbox" class="chk-penilai" value="${p.jenis_penilai}" ${is_manual ? 'checked' : ''}> ${p.jenis_penilai}
                </label>`
            ).join('');
            
            let dinilaiCheckboxes = dinilaiList.map(p => 
                `<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <input type="checkbox" class="chk-dinilai" value="${p.jenis_ptk}"> ${p.jenis_ptk}
                </label>`
            ).join('');

            EModal.form({
                title: is_manual ? 'Import Soal Manual' : 'Import Pertanyaan',
                form: `
                    <div style="background:var(--bg-light); padding:16px; border-radius:12px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; gap:16px;">
                        <div>
                            <h4 style="margin:0; font-size:0.9rem; font-family:var(--font-heading);">Template Import</h4>
                            <p style="margin:4px 0 0; font-size:0.8rem; color:var(--text-muted);">${is_manual ? 'Gunakan format template ini (Kolom: Kategori Penilaian, Pertanyaan, Tipe Isian, Skor Ya, Skor Tidak). Isi kolom Tipe Isian dengan "angka" atau "ya_tidak".' : 'Gunakan format template ini (Kolom: Kategori Penilaian, Pertanyaan).'}</p>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="Perf.downloadTemplate(${is_manual})" style="flex-shrink:0;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Download
                        </button>
                    </div>
                    <div style="display:flex; gap:16px;">
                        <div class="form-group" style="flex:1; ${is_manual ? 'display:none;' : ''}">
                            <label>Penilai</label>
                            <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; max-height:160px; overflow-y:auto; background:var(--bg-light);">
                                ${penilaiCheckboxes}
                            </div>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Dinilai (Tupoksi target)</label>
                            <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; max-height:160px; overflow-y:auto; background:var(--bg-light);">
                                <label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                                    <input type="checkbox" class="chk-dinilai" value="Semua"> Semua PTK
                                </label>
                                ${dinilaiCheckboxes}
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label>File Import (Excel/CSV)</label>
                        <input type="file" class="form-input" accept=".xlsx,.xls,.csv" id="fFile">
                        <small style="color:var(--text-muted);display:block;margin-top:4px;">Pilih file yang sudah diisi sesuai template di atas.</small>
                    </div>
                `,
                onConfirm: () => {
                    let checkedPenilai = [];
                    $('.chk-penilai:checked').each(function(){ checkedPenilai.push($(this).val()); });
                    
                    let checkedDinilai = [];
                    $('.chk-dinilai:checked').each(function(){ checkedDinilai.push($(this).val()); });

                    const file = $('#fFile')[0].files[0];
                    if (!file) {
                        EModal.toast({type: 'error', message: 'Pilih file CSV terlebih dahulu'});
                        return false;
                    }
                    if (!is_manual && checkedPenilai.length === 0) {
                        EModal.toast({type: 'error', message: 'Pilih minimal satu penilai'});
                        return false;
                    }
                    if (checkedDinilai.length === 0) {
                        EModal.toast({type: 'error', message: 'Pilih minimal satu orang yang dinilai'});
                        return false;
                    }
                    
                    EModal.toast({type:'info', message:'Memproses import...'});
                    this.loadSheetJS(() => {
                        const reader = new FileReader();
                        reader.onload = (evt) => {
                            const wb = XLSX.read(evt.target.result, {type:'binary'});
                            const rows = XLSX.utils.sheet_to_row_object_array(wb.Sheets[wb.SheetNames[0]]);
                            if (rows.length > 0) {
                                const data = {
                                    periode_id: this.state.selectedPeriodeId,
                                    target_penilai: is_manual ? ['Admin'] : checkedPenilai,
                                    target_dinilai: checkedDinilai,
                                    is_manual: is_manual,
                                    data: rows
                                };
                                this.api('instrumen.php?action=import_pertanyaan', {method:'POST', data}).done(res => {
                                    EModal.closeAll();
                                    EModal.toast({type:'success', message: res.message});
                                    if (is_manual) this.loadManualPertanyaan(); else this.loadPertanyaan();
                                }).fail(xhr => EModal.toast({type:'error', message: xhr.responseJSON?.message}));
                            } else {
                                EModal.toast({type:'error', message:'Data kosong atau format salah'});
                            }
                        };
                        reader.readAsBinaryString(file);
                    });
                    return false;
                }
            });
        });
    },

    modalTambahPenilai() {
        if(!this.state.selectedPeriodeId) return EModal.toast({type:'error', message:'Pilih periode terlebih dahulu'});
        
        EModal.form({
            title: 'Tambah Penilai',
            form: `
                <div class="form-group">
                    <label>Penilai</label>
                    <select class="form-input" id="fJenisPenilai" required>
                        <option value="">-- Pilih Penilai --</option>
                        <option value="Siswa">Siswa</option>
                        <option value="Teman Sejawat">Teman Sejawat</option>
                    </select>
                    <small style="color:var(--text-muted); display:block; margin-top:4px;">
                        Pilihan bersumber dari Master Data Tupoksi (dan tambahan Siswa).
                    </small>
                </div>
            `,
            onConfirm: () => {
                const data = {
                    jenis_penilai: $('#fJenisPenilai').val(),
                    periode_id: this.state.selectedPeriodeId
                };
                if (!data.jenis_penilai) {
                    EModal.toast({type: 'warning', message: 'Silakan pilih penilai.'});
                    return false;
                }
                
                this.api('instrumen.php?action=create_penilai', {method:'POST', data}).done(res => {
                    EModal.closeAll();
                    EModal.toast({type:'success', message: res.message});
                    this.loadPenilai();
                }).fail(xhr => EModal.toast({type:'error', message: xhr.responseJSON?.message}));
                return false;
            }
        });

        // Load tupoksi options safely
        this.api('instrumen.php?action=list_tupoksi', { method: 'GET' }).done(res => {
            if (res.success && res.data) {
                res.data.forEach(item => {
                    $('#fJenisPenilai').append(`<option value="${item.nama}">${item.nama}</option>`);
                });
            }
        });
    },

    modalCopyInstrumen() {
        if(!this.state.selectedPeriodeId) return;
        
        // Fetch periods to show in dropdown
        this.api('periode.php?action=list', {method:'GET'}).done(res => {
            let opts = '<option value="">-- Pilih Periode Asal --</option>';
            if(res.data) res.data.forEach(p => {
                if (p.id != this.state.selectedPeriodeId) {
                    opts += `<option value="${p.id}">${p.nama_periode} (${p.tahun_ajaran} Sem ${p.semester})</option>`;
                }
            });
            
            EModal.form({
                title: 'Salin Instrumen',
                form: `
                    <div class="form-group">
                        <label>Pilih Periode Asal</label>
                        <select class="form-input" id="fFromPeriode">
                            ${opts}
                        </select>
                        <p style="font-size:12px; color:var(--text-muted); margin-top:8px;">Semua pertanyaan dan penugasan penilai dari periode asal akan disalin ke periode saat ini.</p>
                    </div>
                `,
                onConfirm: () => {
                    const fromId = $('#fFromPeriode').val();
                    if(!fromId) return EModal.toast({type:'error', message:'Pilih periode asal'});
                    
                    const data = {
                        from_periode_id: fromId,
                        to_periode_id: this.state.selectedPeriodeId
                    };
                    
                    this.api('instrumen.php?action=copy_from_period', {method:'POST', data}).done(res => {
                        EModal.closeAll();
                        this.loadPertanyaan();
                        this.loadPenilai();
                        EModal.toast({type: 'success', message: res.message});
                    }).fail(xhr => EModal.toast({type:'error', message: xhr.responseJSON?.message}));
                    return false;
                }
            });
        });
    },

    activatePeriode(id, status) {
        const label = status === 'aktif' ? 'mengaktifkan' : 'menyelesaikan';
        EModal.confirm({
            title: 'Konfirmasi',
            message: `Yakin ingin ${label} periode ini?`,
            onConfirm: () => {
                this.api('periode.php?action=activate', {method:'POST', data:{id, status}}).done(() => {
                    this.reloadCurrentPage();
                    EModal.toast({type:'success', message:'Status periode diperbarui.'});
                });
            }
        });
    },

    deletePeriode(id) {
        EModal.confirm({
            title: 'Hapus Periode', type: 'danger',
            message: '<strong>PERINGATAN:</strong> Menghapus periode akan menghapus semua data penilaian, sampling, dan penugasan terkait. Yakin?',
            onConfirm: () => {
                this.api('periode.php?action=delete', {method:'POST', data:{id}}).done(() => {
                    this.reloadCurrentPage();
                    EModal.toast({type:'success', message:'Periode dihapus.'});
                });
            }
        });
    },

    bulkDeletePtk() {
        const checked = $('.ptk-row-check:checked');
        const ids = checked.map((_, el) => el.value).get();
        if (!ids.length) return;

        EModal.confirm({
            title: 'Hapus Massal PTK', type: 'danger',
            message: `Yakin menghapus <strong>${ids.length}</strong> data PTK yang dipilih? Data hanya akan dihapus dari E-Performance.`,
            onConfirm: () => {
                const l = EModal.loading('Menghapus data...');
                this.api('ptk.php?action=bulk_delete', {method:'POST', data:{ids}}).done(res => {
                    EModal.close(l);
                    EModal.toast({type:'success', message: res.message});
                    this.reloadCurrentPage();
                });
            }
        });
    },

    bulkDeleteSiswa() {
        const checked = $('.siswa-row-check:checked');
        const ids = checked.map((_, el) => el.value).get();
        if (!ids.length) return;

        EModal.confirm({
            title: 'Hapus Massal Siswa', type: 'danger',
            message: `Yakin menghapus <strong>${ids.length}</strong> data siswa yang dipilih? Data hanya akan dihapus dari E-Performance.`,
            onConfirm: () => {
                const l = EModal.loading('Menghapus data...');
                this.api('siswa.php?action=bulk_delete', {method:'POST', data:{ids}}).done(res => {
                    EModal.close(l);
                    EModal.toast({type:'success', message: res.message});
                    this.reloadCurrentPage();
                });
            }
        });
    },

    // ==============================================
    // IMPORT HELPERS (SheetJS)
    // ==============================================
    loadSheetJS(cb) {
        if (window.XLSX) { cb(); return; }
        const l = EModal.loading('Memuat komponen Import/Export...');
        $.getScript('https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js')
            .done(() => { EModal.close(l); cb(); })
            .fail(() => { EModal.close(l); EModal.toast({type:'error',message:'Gagal memuat SheetJS.'}); });
    },

    importPtk() {
        this.loadSheetJS(() => {
            const f = document.createElement('input'); f.type='file'; f.accept='.xlsx,.xls,.csv';
            f.onchange = (e) => {
                const file = e.target.files[0]; if (!file) return;
                EModal.confirm({
                    title: 'Import Data PTK', type: 'danger',
                    message: 'Data yang NIY-nya sudah ada akan dilewati. Lanjutkan?',
                    onConfirm: () => {
                        const reader = new FileReader();
                        reader.onload = (evt) => {
                            const wb = XLSX.read(evt.target.result, {type:'binary'});
                            const rows = XLSX.utils.sheet_to_row_object_array(wb.Sheets[wb.SheetNames[0]]);
                            if (rows.length > 0) {
                                EModal.loading('Memproses import...');
                                this.api('ptk.php?action=import', {method:'POST', data:{data:rows}}).done(res => {
                                    EModal.closeAll();
                                    EModal.toast({type:'success', message: res.message});
                                    this.reloadCurrentPage();
                                });
                            }
                        };
                        reader.readAsBinaryString(file);
                    }
                });
            };
            f.click();
        });
    },

    importSiswa() {
        this.loadSheetJS(() => {
            const f = document.createElement('input'); f.type='file'; f.accept='.xlsx,.xls,.csv';
            f.onchange = (e) => {
                const file = e.target.files[0]; if (!file) return;
                const reader = new FileReader();
                reader.onload = (evt) => {
                    const wb = XLSX.read(evt.target.result, {type:'binary'});
                    const rows = XLSX.utils.sheet_to_row_object_array(wb.Sheets[wb.SheetNames[0]]);
                    if (rows.length > 0) {
                        EModal.loading('Import siswa...');
                        this.api('siswa.php?action=import', {method:'POST', data:{data:rows}}).done(res => {
                            EModal.closeAll();
                            EModal.toast({type:'success', message: res.message});
                            this.reloadCurrentPage();
                        });
                    }
                };
                reader.readAsBinaryString(file);
            };
            f.click();
        });
    },

    exportPtk() {
        this.loadSheetJS(() => {
            if (!this.state.ptkData?.length) { EModal.toast({type:'warning',message:'Tidak ada data.'}); return; }
            const data = this.state.ptkData.map(d => ({
                'NIY': d.niy, 'Nama': d.nama, 'TMT': d.tmt, 'Tempat Lahir': d.tempat_lahir,
                'Tgl Lahir': d.tgl_lahir, 'Jabatan': d.jabatan, 'Mata Pelajaran': d.mata_pelajaran,
                'Jenis PTK': d.jenis_ptk
            }));
            const ws = XLSX.utils.json_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Data PTK');
            XLSX.writeFile(wb, 'E-Performance_PTK.xlsx');
        });
    },

    importPortalSiswa() {
        const loader = EModal.loading('Menghubungkan ke E-Portal...');
        this.api('siswa.php?action=get_portal_students').done(res => {
            console.log('Portal Data Received:', res);
            EModal.close(loader);
            if (!res.success) return;

            const students = res.data.students;
            const classes = res.data.classes;
            const activeYear = res.data.active_year;

            EModal.form({
                title: 'Import Siswa dari E-Portal',
                size: 'lg',
                form: `
                    <div style="margin-bottom:15px; background:var(--bg-light); padding:12px; border-radius:10px; font-size:0.9rem; display:flex; justify-content:space-between; align-items:center">
                        <span>Tahun Ajaran Aktif: <strong>${activeYear}</strong></span>
                        <div style="display:flex; gap:8px">
                            <button class="btn btn-outline btn-sm" onclick="$('.import-check, .class-check').prop('checked', true)">Pilih Semua</button>
                            <button class="btn btn-outline btn-sm" onclick="$('.import-check, .class-check').prop('checked', false)">Batal Semua</button>
                        </div>
                    </div>
                    
                    <div class="pf-nav-label" style="margin-bottom:10px; color:var(--primary)">1. Pilih Berdasarkan Kelas</div>
                    <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:20px; background:#f8fafc; padding:15px; border-radius:10px; border:1px solid #e2e8f0">
                        ${classes.map(k => `
                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; background:white; padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:0.85rem">
                                <input type="checkbox" class="class-check" data-class="${k}"> <strong>${k}</strong>
                            </label>
                        `).join('')}
                    </div>

                    <div class="pf-nav-label" style="margin-bottom:10px; color:var(--primary)">2. Pilih Siswa Secara Individual</div>
                    <div style="margin-bottom:10px">
                        <input type="text" id="siswaSearch" class="form-input" placeholder="Cari nama siswa..." style="width:100%">
                    </div>
                    <div style="max-height:350px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:10px">
                        <table class="pf-table" id="importSiswaTable">
                            <thead><tr><th width="40"></th><th>Nama Siswa</th><th>Kelas</th></tr></thead>
                            <tbody>
                                ${students.map(s => `
                                    <tr class="import-row" data-kelas="${s.kelas}">
                                        <td><input type="checkbox" class="import-check" value="${s.id}" data-kelas="${s.kelas}"></td>
                                        <td class="s-nama">${s.nama}</td>
                                        <td><span class="pf-badge pf-badge-gray">${s.kelas}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `,
                onOpen: () => {
                    // Search Logic
                    $('#siswaSearch').on('input', function() {
                        const val = $(this).val().toLowerCase();
                        $('.import-row').each(function() {
                            const name = $(this).find('.s-nama').text().toLowerCase();
                            $(this).toggle(name.includes(val));
                        });
                    });

                    // Class Checkbox Logic
                    $('.class-check').on('change', function() {
                        const kelas = $(this).data('class');
                        const isChecked = $(this).is(':checked');
                        $(`.import-check[data-kelas="${kelas}"]`).prop('checked', isChecked);
                    });

                    // Individual Checkbox Logic (uncheck class if one student is unchecked)
                    $('.import-check').on('change', function() {
                        const kelas = $(this).data('kelas');
                        if (!$(this).is(':checked')) {
                            $(`.class-check[data-class="${kelas}"]`).prop('checked', false);
                        } else {
                            // If all students in class are checked, check the class box
                            const total = $(`.import-check[data-kelas="${kelas}"]`).length;
                            const checked = $(`.import-check[data-kelas="${kelas}"]:checked`).length;
                            if (total === checked) {
                                $(`.class-check[data-class="${kelas}"]`).prop('checked', true);
                            }
                        }
                    });
                },
                onConfirm: () => {
                    const selected = $('.import-check:checked').map((_, el) => el.value).get();
                    if (!selected.length) {
                        EModal.toast({type:'warning', message:'Pilih minimal satu siswa.'});
                        return false;
                    }

                    const l = EModal.loading('Sedang mengimport...');
                    this.api('siswa.php?action=import_portal_students', {method:'POST', data:{ids:selected}}).done(res => {
                        EModal.close(l); EModal.closeAll();
                        EModal.toast({type:'success', message:res.message});
                        this.reloadCurrentPage();
                    }).fail(xhr => {
                        EModal.close(l);
                        EModal.toast({type:'error', message: xhr.responseJSON?.message || 'Gagal import data.'});
                    });
                    return false;
                }
            });
        }).fail(xhr => {
            EModal.close(loader);
            EModal.toast({type:'error', message: xhr.responseJSON?.message || 'Gagal mengambil data dari portal.'});
        });
    },

    importPortalPtk() {
        const loader = EModal.loading('Menghubungkan ke E-Portal...');
        this.api('ptk.php?action=get_portal_ptk').done(res => {
            EModal.close(loader);
            if (!res.success) return;

            const teachers = res.data;

            EModal.form({
                title: 'Import Guru dari E-Portal',
                size: 'md',
                form: `
                    <div style="margin-bottom:15px; display:flex; gap:10px">
                        <button class="btn btn-outline btn-sm" onclick="$('.ptk-check').prop('checked', true)">Pilih Semua</button>
                        <button class="btn btn-outline btn-sm" onclick="$('.ptk-check').prop('checked', false)">Batal Semua</button>
                    </div>
                    <div style="max-height:400px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:10px">
                        <table class="pf-table">
                            <thead><tr><th width="40"></th><th>Nama Guru</th><th>Username</th><th>Tupoksi</th><th>Mata Pelajaran</th></tr></thead>
                            <tbody>
                                ${teachers.map(t => `
                                    <tr>
                                        <td><input type="checkbox" class="ptk-check" value="${t.id}"></td>
                                        <td>${t.nama_lengkap}</td>
                                        <td><code>${t.username}</code></td>
                                        <td><span class="pf-badge pf-badge-purple">${t.tupoksi || '-'}</span></td>
                                        <td><span class="pf-badge pf-badge-gray">${t.mapel || '-'}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `,
                onConfirm: () => {
                    const selected = $('.ptk-check:checked').map((_, el) => el.value).get();
                    if (!selected.length) {
                        EModal.toast({type:'warning', message:'Pilih minimal satu guru.'});
                        return false;
                    }

                    const l = EModal.loading('Sedang mengimport...');
                    this.api('ptk.php?action=import_portal_ptk', {method:'POST', data:{ids:selected}}).done(res => {
                        EModal.close(l); EModal.closeAll();
                        EModal.toast({type:'success', message:res.message});
                        this.reloadCurrentPage();
                    });
                    return false;
                }
            });
        });
    },

    // ==============================================
    // AKSES MODUL (Role & Akun)
    // ==============================================
    renderAksesModul($c) {
        $c.html(`
            <div class="pf-card" style="min-height: 400px;">
                <div class="pf-card-header" style="border-bottom:none; padding-bottom:0;">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Pengaturan Akses Modul & Akun</h3>
                </div>
                
                <div class="pf-tabs" style="display:flex; border-bottom:2px solid #E5E7EB; margin:0 24px 16px 24px;">
                    <button class="pf-tab-btn akses-tab active" data-tab="tab-roles" style="padding:12px 24px; background:none; border:none; border-bottom:2px solid var(--primary); font-weight:600; color:var(--primary); cursor:pointer;">Role Akses</button>
                    <button class="pf-tab-btn akses-tab" data-tab="tab-users" style="padding:12px 24px; background:none; border:none; border-bottom:2px solid transparent; font-weight:600; color:#4B5563; cursor:pointer;">Kelola Akun</button>
                </div>

                <div class="pf-card-body" style="padding-top:0;">
                    <!-- TAB ROLES -->
                    <div id="tab-roles" class="pf-tab-content pf-fade-in" style="display:block;">
                        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
                            <button class="btn btn-primary btn-sm" onclick="Perf.formRole()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Role</button>
                        </div>
                        <div class="pf-table-wrapper" id="rolesTable"><div class="pf-empty">Memuat data role...</div></div>
                    </div>

                    <!-- TAB USERS -->
                    <div id="tab-users" class="pf-tab-content pf-fade-in" style="display:none;">
                        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
                            <button class="btn btn-primary btn-sm" onclick="Perf.formUser()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Akun</button>
                        </div>
                        <div class="pf-table-wrapper" id="usersTable"><div class="pf-empty">Memuat data akun...</div></div>
                    </div>
                </div>
            </div>
        `);

        // Tab Switching Logic
        $('.akses-tab').on('click', function() {
            $('.akses-tab').css({ 'border-bottom-color':'transparent', 'color':'#4B5563' }).removeClass('active');
            $(this).css({ 'border-bottom-color':'var(--primary)', 'color':'var(--primary)' }).addClass('active');
            
            $('.pf-tab-content').hide();
            $('#' + $(this).data('tab')).fadeIn(200);
        });

        // Load data for both tabs
        this.loadRoles();
        this.loadUsers();
    },

    loadRoles() {
        this.api('roles.php?action=list').done(res => {
            if (!res.data || res.data.length === 0) {
                $('#rolesTable').html('<div class="pf-empty">Tidak ada data role.</div>');
                return;
            }
            this.state.rolesData = res.data;
            const rows = res.data.map((r, i) => `
                <tr>
                    <td>${i+1}</td>
                    <td><strong>${r.role_name}</strong> <span style="font-size:12px; color:#6B7280;">(${r.role_slug})</span></td>
                    <td>
                        <div style="display:flex; flex-wrap:wrap; gap:4px;">
                            ${(r.permissions||[]).map(p => `<span class="pf-badge pf-badge-gray" style="font-size:10px;">${p}</span>`).join('')}
                        </div>
                    </td>
                    <td>
                        <div class="pf-actions">
                            <button class="pf-btn-icon primary" onclick="Perf.formRole(${r.id})" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="pf-btn-icon danger" onclick="Perf.deleteRole(${r.id})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>
            `).join('');

            $('#rolesTable').html(`
                <table class="pf-table">
                    <thead><tr><th width="40">No</th><th>Nama Role</th><th>Hak Akses (Menu)</th><th width="100">Aksi</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        });
    },

    formRole(id = null) {
        const isEdit = id !== null;
        const row = isEdit ? this.state.rolesData.find(x => x.id == id) : null;
        const perms = row ? (row.permissions || []) : [];

        const menuOptions = [
            {val:'dashboard', label:'Dashboard'}, {val:'ptk', label:'Data PTK'},
            {val:'siswa', label:'Data Siswa'}, {val:'periode', label:'Periode Penilaian'},
            {val:'instrumen', label:'Buat Penilaian'}, {val:'progress', label:'Progress Penilaian'},
            {val:'hasil', label:'Hasil Penilaian'}, {val:'penilaian', label:'Isi Penilaian'},
            {val:'akses_modul', label:'Akses Modul'}
        ];

        let permsHtml = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">';
        menuOptions.forEach(m => {
            const checked = perms.includes(m.val) ? 'checked' : '';
            permsHtml += `<label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="permissions[]" value="${m.val}" ${checked}> ${m.label}</label>`;
        });
        permsHtml += '</div>';

        EModal.form({
            title: isEdit ? 'Edit Role' : 'Tambah Role Baru',
            form: `
                <input type="hidden" name="id" id="fRoleId" value="${id || ''}">
                <div class="pf-form-row">
                    <div class="form-group">
                        <label>Nama Role (Tampil) <span class="pf-required">*</span></label>
                        <input type="text" class="form-input" id="fRoleName" required value="${row?.role_name||''}" placeholder="Contoh: Admin TU">
                    </div>
                    <div class="form-group">
                        <label>Slug (Unik) <span class="pf-required">*</span></label>
                        <input type="text" class="form-input" id="fRoleSlug" required value="${row?.role_slug||''}" placeholder="Contoh: admin_tu" ${isEdit?'readonly':''}>
                    </div>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label>Hak Akses Menu <span class="pf-required">*</span></label>
                    <div style="background:#F9FAFB; padding:16px; border:1px solid #E5E7EB; border-radius:8px;">
                        ${permsHtml}
                    </div>
                </div>
            `,
            onConfirm: () => {
                const checkedPerms = [];
                $('input[name="permissions[]"]:checked').each(function() {
                    checkedPerms.push($(this).val());
                });

                const data = {
                    id: $('#fRoleId').val(),
                    role_name: $('#fRoleName').val(),
                    role_slug: $('#fRoleSlug').val().toLowerCase().replace(/[^a-z0-9_]/g, '_'),
                    permissions: checkedPerms
                };

                this.api('roles.php?action=save', { method: 'POST', data: JSON.stringify(data), contentType: 'application/json' }).done(res => {
                    if (res.success) {
                        EModal.closeAll();
                        this.loadRoles();
                        EModal.toast({ type: 'success', message: res.message });
                    } else {
                        EModal.toast({ type: 'error', message: res.message });
                    }
                }).fail(xhr => EModal.toast({type:'error', message:xhr.responseJSON?.message || 'Terjadi kesalahan'}));
                return false;
            }
        });
    },

    deleteRole(id) {
        EModal.confirm({
            title: 'Hapus Role', type: 'danger',
            message: 'Yakin menghapus role kustom ini? Pastikan tidak ada pengguna yang menggunakan role ini.',
            onConfirm: () => {
                this.api('roles.php?action=delete', {method:'POST', data:{id}}).done(res => {
                    if (res.success) {
                        this.loadRoles();
                        EModal.toast({type:'success', message:res.message});
                    } else {
                        EModal.toast({type:'error', message:res.message});
                    }
                }).fail(xhr => EModal.toast({type:'error', message:xhr.responseJSON?.message}));
            }
        });
    },

    loadUsers() {
        this.api('pengguna.php?action=list').done(res => {
            if (!res.data || res.data.length === 0) {
                $('#usersTable').html('<div class="pf-empty">Tidak ada data pengguna.</div>');
                return;
            }
            this.state.usersData = res.data;
            const rows = res.data.map((u, i) => `
                <tr>
                    <td>${i+1}</td>
                    <td><strong>${u.username}</strong></td>
                    <td>${u.nama_lengkap}</td>
                    <td><span class="pf-badge pf-badge-blue">${u.role_name || u.role}</span></td>
                    <td>${u.last_login ? u.last_login : '-'}</td>
                    <td>
                        <div class="pf-actions">
                            <button class="pf-btn-icon primary" onclick="Perf.formUser(${u.id})" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button class="pf-btn-icon" style="color:#F59E0B; background:#FEF3C7; border:none;" onclick="Perf.changePasswordModal(${u.id})" title="Ganti Password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></button>
                            <button class="pf-btn-icon danger" onclick="Perf.deleteUser(${u.id})" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                        </div>
                    </td>
                </tr>
            `).join('');

            $('#usersTable').html(`
                <table class="pf-table">
                    <thead><tr><th width="40">No</th><th>Username</th><th>Nama Lengkap</th><th>Role</th><th>Terakhir Login</th><th width="120">Aksi</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        });
    },

    formUser(id = null) {
        const isEdit = id !== null;
        const row = isEdit ? this.state.usersData.find(x => x.id == id) : null;

        this.api('roles.php?action=list').done(res => {
            let rolesHtml = '<select class="form-select" id="fUserRole" required>';
            if(res.data) {
                res.data.forEach(r => {
                    const sel = (row && row.role === r.role_slug) ? 'selected' : '';
                    rolesHtml += `<option value="${r.role_slug}" ${sel}>${r.role_name}</option>`;
                });
            }
            rolesHtml += '</select>';

            EModal.form({
                title: isEdit ? 'Edit Akun' : 'Tambah Akun Baru',
                form: `
                    <input type="hidden" id="fUserId" value="${id || ''}">
                    <div class="pf-form-row">
                        <div class="form-group">
                            <label>Username <span class="pf-required">*</span></label>
                            <input type="text" class="form-input" id="fUserUsername" required value="${row?.username||''}" ${row?.perf_ptk_id ? 'readonly' : ''}>
                        </div>
                        <div class="form-group">
                            <label>Role / Akses <span class="pf-required">*</span></label>
                            ${rolesHtml}
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Nama Lengkap <span class="pf-required">*</span></label>
                        <input type="text" class="form-input" id="fUserNama" required value="${row?.nama_lengkap||''}">
                    </div>
                    ${!isEdit ? `
                    <div class="form-group" style="margin-top:12px;">
                        <label>Password (Opsional)</label>
                        <input type="text" class="form-input" id="fUserPass" placeholder="Kosongkan jika ingin sama dengan username">
                    </div>` : ''}
                `,
                onConfirm: () => {
                    const data = {
                        id: $('#fUserId').val(),
                        username: $('#fUserUsername').val(),
                        nama_lengkap: $('#fUserNama').val(),
                        role: $('#fUserRole').val(),
                        password: $('#fUserPass').val()
                    };

                    this.api('pengguna.php?action=save', { method: 'POST', data }).done(saveRes => {
                        if (saveRes.success) {
                            EModal.closeAll();
                            this.loadUsers();
                            EModal.toast({ type: 'success', message: saveRes.message });
                        } else {
                            EModal.toast({ type: 'error', message: saveRes.message });
                        }
                    }).fail(xhr => EModal.toast({type:'error', message:xhr.responseJSON?.message || 'Terjadi kesalahan'}));
                    return false;
                }
            });
        });
    },

    changePasswordModal(id) {
        EModal.form({
            title: 'Ganti Password',
            form: `
                <div class="form-group">
                    <label>Password Baru <span class="pf-required">*</span></label>
                    <input type="text" class="form-input" id="fNewPass" required placeholder="Masukkan password baru">
                </div>
            `,
            onConfirm: () => {
                const pwd = $('#fNewPass').val();
                if(!pwd) {
                    EModal.toast({type:'error', message:'Password wajib diisi'});
                    return false;
                }
                this.api('pengguna.php?action=change_password', { method: 'POST', data: {id: id, password: pwd} }).done(res => {
                    if (res.success) {
                        EModal.closeAll();
                        EModal.toast({ type: 'success', message: res.message });
                    } else {
                        EModal.toast({ type: 'error', message: res.message });
                    }
                }).fail(xhr => EModal.toast({type:'error', message:xhr.responseJSON?.message}));
                return false;
            }
        });
    },

    deleteUser(id) {
        EModal.confirm({
            title: 'Hapus Akun', type: 'danger',
            message: 'Yakin menghapus akun pengguna manual ini?',
            onConfirm: () => {
                this.api('pengguna.php?action=delete', {method:'POST', data:{id}}).done(res => {
                    if (res.success) {
                        this.loadUsers();
                        EModal.toast({type:'success', message:res.message});
                    } else {
                        EModal.toast({type:'error', message:res.message});
                    }
                }).fail(xhr => EModal.toast({type:'error', message:xhr.responseJSON?.message}));
            }
        });
    },

    renderAcakPenilai($container) {
        let html = `
            <div class="pf-card" style="min-height: 400px;">
                <div class="pf-card-header" style="display:flex; flex-wrap:wrap; gap:16px;">
                    <div style="display:flex; align-items:center; gap:16px; margin-right:auto;">
                        <h3 style="margin:0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg> Hasil Pengacakan Penilai (Teman Sejawat)</h3>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="font-weight:600; color:var(--text-muted);">Periode:</span>
                            <select id="acakPeriodeSelector" class="form-input" style="width: 400px; max-width: 100%;"></select>
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <input type="text" id="acakSearch" class="form-input" placeholder="Cari nama penilai..." style="min-width: 200px;">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="pf-table">
                        <thead>
                            <tr>
                                <th style="width:50px; text-align:center;">No</th>
                                <th>Nama PTK (Target)</th>
                                <th>Dinilai Oleh Siapa Saja? (Sejawat)</th>
                                <th style="width:120px; text-align:center;">Jumlah Penilai</th>
                            </tr>
                        </thead>
                        <tbody id="acakTable">
                            <tr><td colspan="4" style="text-align:center; padding:30px;"><div class="pf-loader"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        $container.html(html);

        // Load periode
        this.api('periode.php?action=list', {method:'GET'}).done(res => {
            if(!res.data || res.data.length === 0) {
                $('#acakTable').html(`<tr><td colspan="4"><div class="pf-empty">Silakan buat Periode terlebih dahulu di menu Pengaturan</div></td></tr>`);
                $('#acakPeriodeSelector').html('<option value="">Tidak ada periode</option>');
                return;
            }
            
            const opts = res.data.map(p => {
                let name = p.nama_periode;
                if (p.tahun_ajaran) name += ` (${p.tahun_ajaran} (${p.semester == 1 ? 'Ganjil' : 'Genap'}))`;
                return `<option value="${p.id}">${Perf.escapeHtml(name)}</option>`;
            }).join('');
            $('#acakPeriodeSelector').html('<option value="">-- Pilih Periode --</option>' + opts).val('');
            this.state.selectedPeriodeId = '';
            
            $('#acakTable').html('<tr><td colspan="4" style="text-align:center; padding:30px;" class="text-muted">Silakan pilih Periode terlebih dahulu.</td></tr>');
            
            $('#acakPeriodeSelector').on('change', (e) => {
                this.state.selectedPeriodeId = $(e.target).val();
                if(this.state.selectedPeriodeId) {
                    this.loadAcakData();
                } else {
                    $('#acakTable').html('<tr><td colspan="4" style="text-align:center; padding:30px;" class="text-muted">Silakan pilih Periode terlebih dahulu.</td></tr>');
                }
            });
            $('#acakSearch').on('input', () => {
                if(this.state.selectedPeriodeId) this.loadAcakData();
            });
        });
    },

    loadAcakData() {
        if(!this.state.selectedPeriodeId) return;
        $('#acakTable').html('<tr><td colspan="4" style="text-align:center; padding:30px;"><div class="pf-loader"></div></td></tr>');
        
        this.api(`pengaturan_sejawat.php?action=list&periode_id=${this.state.selectedPeriodeId}`, {method:'GET'}).done(res => {
            if(!res.data || res.data.length === 0) {
                $('#acakTable').html('<tr><td colspan="4" style="text-align:center; padding:30px;" class="text-muted">Tidak ada data PTK.</td></tr>');
                return;
            }
            
            this.state.acakData = res.data;
            this.renderAcakTable();
        });
    },

    renderAcakTable() {
        const q = ($('#acakSearch').val() || '').toLowerCase();
        let html = '';
        let no = 1;

        const data = this.state.acakData.filter(d => 
            (d.target_nama || '').toLowerCase().includes(q) || 
            (d.detail_penilai && d.detail_penilai.some(t => (t.penilai_nama || '').toLowerCase().includes(q)))
        );

        if(data.length === 0) {
            $('#acakTable').html('<tr><td colspan="4" style="text-align:center; padding:30px;" class="text-muted">Data tidak ditemukan.</td></tr>');
            return;
        }

        data.forEach(d => {
            const jumlah = parseInt(d.jumlah_penilai);
            // Tambah 1 untuk menghitung diri sendiri (jika bukan kepala sekolah/lainnya)
            const total = jumlah + 1;
            let badge = '';
            let targetHtml = '';

            if (jumlah === 0) {
                badge = `<span class="pf-badge" style="background:#e2e8f0; color:#64748b;">Hanya Diri Sendiri</span>`;
                targetHtml = `<span class="text-muted">Belum ada teman sejawat yang ditugaskan menilai orang ini.</span>`;
            } else if (total > 2) {
                badge = `<span class="pf-badge pf-badge-danger">${total} Penilai</span>`;
            } else {
                badge = `<span class="pf-badge pf-badge-primary">${total} Penilai</span>`;
            }

            if (jumlah > 0) {
                targetHtml = `<div style="display:flex; flex-direction:column; gap:8px;">`;
                d.detail_penilai.forEach(t => {
                    targetHtml += `
                        <div draggable="true" ondragstart="Perf.dragStart(event, ${t.id}, '${Perf.escapeHtml(t.penilai_nama)}')" style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-light); padding:8px 12px; border-radius:6px; border:1px solid var(--border-color); cursor:grab;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted); cursor:grab;"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
                                <div>
                                    <strong>${Perf.escapeHtml(t.penilai_nama)}</strong>
                                    <div style="font-size:0.8rem; color:var(--text-muted);">${Perf.escapeHtml(t.penilai_jenis)}</div>
                                </div>
                            </div>
                            <button class="pf-btn-icon danger" onclick="Perf.deletePenugasanSejawat(${t.id})" title="Hapus Tugas Ini">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    `;
                });
                targetHtml += `</div>`;
            }

            html += `
                <tr ondragover="Perf.dragOver(event)" ondragleave="Perf.dragLeave(event)" ondrop="Perf.drop(event, ${d.target_id})" style="transition: background-color 0.2s;">
                    <td style="text-align:center;">${no++}</td>
                    <td>
                        <div style="font-weight:600;">${Perf.escapeHtml(d.target_nama)}</div>
                        <div style="font-size:0.8rem; color:var(--text-muted);">${Perf.escapeHtml(d.target_jenis)}</div>
                    </td>
                    <td>${targetHtml}</td>
                    <td style="text-align:center;">
                        ${badge}
                        ${total > 2 ? '<div style="font-size:0.75rem; color:var(--danger); margin-top:4px; font-weight: 600;">Terlalu Banyak</div>' : ''}
                    </td>
                </tr>
            `;
        });

        $('#acakTable').html(html);
    },

    dragStart(e, penugasanId, penilaiNama) {
        e.dataTransfer.setData('text/plain', penugasanId);
        e.dataTransfer.effectAllowed = 'move';
        // Add styling class to dragged item if needed
        e.target.style.opacity = '0.4';
        
        // Restore opacity after drag
        e.target.addEventListener('dragend', function() {
            this.style.opacity = '1';
        }, {once: true});
    },

    dragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const tr = $(e.target).closest('tr');
        if (!tr.hasClass('pf-drag-over')) {
            tr.addClass('pf-drag-over');
            tr.css('background-color', '#f0f9ff'); // Light blue highlighting
        }
    },

    dragLeave(e) {
        const tr = $(e.target).closest('tr');
        tr.removeClass('pf-drag-over');
        tr.css('background-color', '');
    },

    drop(e, newTargetId) {
        e.preventDefault();
        const tr = $(e.target).closest('tr');
        tr.removeClass('pf-drag-over');
        tr.css('background-color', '');

        const penugasanId = e.dataTransfer.getData('text/plain');
        if (!penugasanId) return;

        // Tampilkan konfirmasi
        EModal.confirm({
            title: 'Pindahkan Penilai',
            type: 'warning',
            message: 'Apakah Anda yakin ingin memindahkan penilai ini? Nilai yang sudah tersimpan untuk target sebelumnya akan dihapus otomatis.',
            onConfirm: () => {
                this.api('pengaturan_sejawat.php?action=move', {
                    method: 'POST',
                    data: { id: penugasanId, new_target_id: newTargetId }
                }).done(res => {
                    if (res.success) {
                        EModal.toast({type: 'success', message: res.message});
                        this.loadAcakData();
                    } else {
                        EModal.toast({type: 'error', message: res.message});
                    }
                }).fail(xhr => EModal.toast({type:'error', message:xhr.responseJSON?.message}));
            }
        });
    },

    deletePenugasanSejawat(id) {
        EModal.confirm({
            title: 'Hapus Penugasan', 
            type: 'danger',
            message: 'Apakah Anda yakin ingin menghapus penugasan ini? Jika penilai sudah terlanjur mengisi nilai untuk orang ini, nilainya juga akan ikut terhapus!',
            onConfirm: () => {
                this.api('pengaturan_sejawat.php?action=delete', {
                    method: 'POST',
                    data: { id: id }
                }).done(res => {
                    if(res.success) {
                        EModal.toast({type:'success', message:res.message});
                        this.loadAcakData();
                    } else {
                        EModal.toast({type:'error', message:res.message});
                    }
                }).fail(xhr => EModal.toast({type:'error', message:xhr.responseJSON?.message}));
            }
        });
    },

    // ==============================================
    // DESKRIPSI PENILAIAN
    // ==============================================
    renderDeskripsi($c) {
        $c.html(`
            <div class="pf-card">
                <div class="pf-card-header">
                    <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> Data Deskripsi Penilaian</h3>
                    <button class="btn btn-primary btn-sm" onclick="Perf.addDeskripsiModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Deskripsi
                    </button>
                </div>
                <div class="pf-card-body">
                    <div class="pf-table-wrapper" id="deskripsiTableContainer">
                        <div class="pf-empty">Memuat data...</div>
                    </div>
                </div>
            </div>
        `);
        this.loadDeskripsi();
    },

    loadDeskripsi() {
        this.api('deskripsi.php?action=list').done(res => {
            if (!res.data || !res.data.length) {
                $('#deskripsiTableContainer').html('<div class="pf-empty">Belum ada data deskripsi.</div>');
                return;
            }
            let html = `
                <table class="pf-table">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="20%">Tupoksi</th>
                            <th width="15%">Min Nilai</th>
                            <th width="15%">Max Nilai</th>
                            <th width="35%">Deskripsi</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            res.data.forEach((d, i) => {
                html += `
                    <tr>
                        <td>${i+1}</td>
                        <td>${d.tupoksi}</td>
                        <td>${d.min_nilai}</td>
                        <td>${d.max_nilai}</td>
                        <td>${d.deskripsi}</td>
                        <td>
                            <button class="pf-btn-icon danger" onclick="Perf.deleteDeskripsi(${d.id})" title="Hapus">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </td>
                    </tr>
                `;
            });
            html += '</tbody></table>';
            $('#deskripsiTableContainer').html(html);
        });
    },

    addDeskripsiModal() {
        this.api('instrumen.php?action=list_tupoksi').done(res => {
            let tupoksiOptions = '<option value="">Pilih Tupoksi</option>';
            if (res.data) {
                res.data.forEach(item => {
                    tupoksiOptions += `<option value="${item.nama}">${item.nama}</option>`;
                });
            }
            const html = `
                <div class="form-group">
                    <label>Tupoksi <span class="pf-required">*</span></label>
                    <select class="form-input" id="fDeskripsiTupoksi">${tupoksiOptions}</select>
                </div>
                <div style="display:flex; gap:16px;">
                    <div class="form-group" style="flex:1;">
                        <label>Min Nilai (0-100) <span class="pf-required">*</span></label>
                        <input type="text" inputmode="decimal" class="form-input" id="fDeskripsiMin" placeholder="0">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Max Nilai (0-100) <span class="pf-required">*</span></label>
                        <input type="text" inputmode="decimal" class="form-input" id="fDeskripsiMax" placeholder="100">
                    </div>
                </div>
                <div class="form-group">
                    <label>Deskripsi <span class="pf-required">*</span></label>
                    <textarea class="form-input" id="fDeskripsiTeks" rows="4" placeholder="Contoh: Telah melaksanakan tugas dengan sangat baik..."></textarea>
                </div>
            `;
            EModal.form({
                title: 'Tambah Deskripsi Penilaian',
                form: html,
                confirmText: 'Simpan',
                onConfirm: () => {
                    const t = $('#fDeskripsiTupoksi').val();
                    const min = $('#fDeskripsiMin').val();
                    const max = $('#fDeskripsiMax').val();
                    const teks = $('#fDeskripsiTeks').val();
                    
                    if (!t || min === '' || max === '' || !teks) {
                        EModal.toast({type: 'warning', message: 'Semua kolom wajib diisi'});
                        return false;
                    }
                    
                    this.api('deskripsi.php?action=create', {
                        method: 'POST',
                        contentType: 'application/x-www-form-urlencoded',
                        data: {
                            tupoksi: t,
                            min_nilai: min,
                            max_nilai: max,
                            deskripsi: teks
                        }
                    }).done(r => {
                        if (r.success) {
                            EModal.toast({type: 'success', message: 'Berhasil disimpan'});
                            EModal.closeAll();
                            this.loadDeskripsi();
                        } else {
                            EModal.alert('Gagal', r.message);
                        }
                    });
                    return false;
                }
            });
        });
    },

    deleteDeskripsi(id) {
        EModal.confirm({
            title: 'Hapus Deskripsi',
            message: 'Yakin ingin menghapus deskripsi ini?',
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('deskripsi.php?action=delete', {
                    method: 'POST',
                    contentType: 'application/x-www-form-urlencoded',
                    data: { id: id }
                }).done(res => {
                    if (res.success) {
                        EModal.toast({type: 'success', message: 'Berhasil dihapus'});
                        this.loadDeskripsi();
                    } else {
                        EModal.toast({type: 'error', message: res.message});
                    }
                });
            }
        });
    }
};

$(document).ready(() => Perf.init());

