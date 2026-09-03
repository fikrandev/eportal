/**
 * E-Absen Module SPA
 * Handles Machine Config, Log Syncing, Mapping, and Reports
 */

const Absen = {
    state: {
        user: window.ABSEN_CONFIG ? window.ABSEN_CONFIG.user : null,
        token: new URLSearchParams(window.location.search).get('token'),
        school: window.ABSEN_CONFIG ? window.ABSEN_CONFIG.school : null,
        currentRoute: 'dashboard',
        params: {}
    },
    
    baseUrl: window.ABSEN_CONFIG ? window.ABSEN_CONFIG.baseUrl : '/',
    moduleUrl: window.ABSEN_CONFIG ? window.ABSEN_CONFIG.moduleUrl : 'modules/e-absen/',

    init() {
        try {
            this.bindEvents();
        this.renderSidebar();
        this.loadRouteFromHash();

        if (this.state.user) {
            $('#sidebarAvatar').text(this.getInitials(this.state.user.nama_lengkap));
            $('#sidebarUserName').text(this.state.user.nama_lengkap);
            $('#sidebarUserRole').text(this.state.user.role);
        }

        setTimeout(() => {
            $('#globalLoader').addClass('hidden');
            setTimeout(() => $('#globalLoader').remove(), 600);
        }, 500);
        } catch (e) {
            $('#globalLoader').remove();
            $('#mainContent').html(`<div style="color:red; padding:20px;"><h3>JavaScript Error:</h3><pre>${e.stack || e.message || e}</pre></div>`);
        }
    },

    bindEvents() {
        window.addEventListener('hashchange', () => this.loadRouteFromHash());
        $('#menuToggle').on('click', () => this.toggleSidebar());
        $('#sidebarOverlay').on('click', () => this.toggleSidebar(false));
    },

    renderSidebar() {
        const isAdmin = this.state.user.role === 'superadmin';
        
        let navHtml = `
            <div class="ea-nav-group">
                <div class="ea-nav-label">Menu Utama</div>
                <button class="ea-nav-item" data-route="dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Dashboard
                </button>
                <button class="ea-nav-item" data-route="rekap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Data Kehadiran
                </button>
            </div>
        `;

        if (isAdmin) {
            navHtml += `
                <div class="ea-nav-group">
                    <div class="ea-nav-label">Pengaturan Mesin</div>
                    <button class="ea-nav-item" data-route="mesin">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
                        Koneksi Mesin
                    </button>
                    <button class="ea-nav-item" data-route="mapping">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Mapping Pegawai
                    </button>
                    <button class="ea-nav-item" data-route="log">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        Data Log / Sinkronisasi
                    </button>
                    <button class="ea-nav-item" data-route="wagateway">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        WhatsApp Gateway
                    </button>
                </div>
            `;
        }

        $('#sidebarNav').html(navHtml);

        $('.ea-nav-item').on('click', function() {
            Absen.navigate($(this).data('route'));
        });
    },

    toggleSidebar(show = null) {
        if (show === null) {
            $('#eaSidebar').toggleClass('show');
            $('#sidebarOverlay').toggleClass('show');
        } else if (show) {
            $('#eaSidebar').addClass('show');
            $('#sidebarOverlay').addClass('show');
        } else {
            $('#eaSidebar').removeClass('show');
            $('#sidebarOverlay').removeClass('show');
        }
    },

    navigate(route) {
        window.location.hash = `#/${route}`;
    },

    loadRouteFromHash() {
        const hash = window.location.hash || '#/dashboard';
        const route = hash.replace('#/', '').split('?')[0] || 'dashboard';

        this.state.currentRoute = route;

        $('.ea-nav-item').removeClass('active');
        $(`.ea-nav-item[data-route="${route}"]`).addClass('active');

        this.toggleSidebar(false);
        this.renderPage(route);
    },

    renderPage(route) {
        const $content = $('#mainContent');
        const $title = $('#pageTitle');

        switch (route) {
            case 'dashboard':
                $title.text('Dashboard');
                this.renderDashboard($content);
                break;
            case 'mesin':
                $title.text('Pengaturan Mesin');
                this.renderMesin($content);
                break;
            case 'mapping':
                $title.text('Mapping Pegawai');
                this.renderMapping($content);
                break;
            case 'log':
                $title.text('Data Log / Sinkronisasi');
                this.renderLog($content);
                break;
            case 'rekap':
                $title.text('Data Kehadiran');
                this.renderRekap($content);
                break;
            case 'wagateway':
                $title.text('WhatsApp Gateway');
                this.renderWAGateway($content);
                break;
            default:
                this.navigate('dashboard');
        }
    },

    // ==================== DASHBOARD ====================
    renderDashboard($container) {
        $container.html(`
            <div class="ea-stats" id="dashboardStats">
                <div class="skeleton-card" style="height: 100px;"></div>
                <div class="skeleton-card" style="height: 100px;"></div>
                <div class="skeleton-card" style="height: 100px;"></div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Fingerprint System (E-Absen)</h3>
                </div>
                <div class="card-body">
                    <p>Modul E-Absen terintegrasi dengan mesin sidik jari Interactive F4000 melalui jaringan lokal. Pastikan mesin dalam keadaan aktif dan terhubung dengan jaringan.</p>
                    <div style="margin-top:20px; display:flex; gap:10px;">
                        ${this.state.user.role === 'superadmin' ? `
                            <button class="btn btn-primary" onclick="Absen.navigate('log')">Tarik Data Sekarang</button>
                            <button class="btn btn-outline" onclick="Absen.navigate('mesin')">Pengaturan Mesin</button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `);

        this.api('rekap.php?action=dashboard_stats').done(res => {
            if (!res.success) return;
            const s = res.data;
            $('#dashboardStats').html(`
                <div class="ea-stat-card slide-up">
                    <div class="ea-stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="ea-stat-info">
                        <h4>${s.Hadir}</h4>
                        <p>Hadir Hari Ini</p>
                    </div>
                </div>
                <div class="ea-stat-card slide-up" style="animation-delay: 0.1s">
                    <div class="ea-stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="ea-stat-info">
                        <h4>${s.Terlambat}</h4>
                        <p>Terlambat</p>
                    </div>
                </div>
                <div class="ea-stat-card slide-up" style="animation-delay: 0.2s">
                    <div class="ea-stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #EF4444;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <div class="ea-stat-info">
                        <h4>${s.Alpha}</h4>
                        <p>Alpha / Belum Absen</p>
                    </div>
                </div>
            `);
        });
    },

    // ==================== PENGATURAN MESIN ====================
    // ==================== PENGATURAN MESIN ====================
    renderMesin($container) {
        $container.html(`
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3>Daftar Mesin Fingerprint</h3>
                        <p class="text-muted">Mendukung koneksi Langsung (IP) dan ADMS/Cloud Server.</p>
                    </div>
                    <button class="btn btn-primary" onclick="Absen.showMesinModal()">+ Tambah Mesin</button>
                </div>
                <div class="card-body">
                    <div class="ea-table-container">
                        <table class="ea-table">
                            <thead>
                                <tr>
                                    <th>Nama Mesin</th>
                                    <th>Serial Number (SN)</th>
                                    <th>IP Address / Mode</th>
                                    <th>Port</th>
                                    <th>Status</th>
                                    <th>Terakhir Sync</th>
                                    <th style="text-align:right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="mesinTbody">
                                <tr><td colspan="7" style="text-align:center;">Memuat data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `);

        this.loadMesinList();
    },

    loadMesinList() {
        this.api('mesin.php?action=list').done(res => {
            const data = res.data || [];
            if (data.length === 0) {
                $('#mesinTbody').html('<tr><td colspan="7" style="text-align:center;">Belum ada mesin.</td></tr>');
                return;
            }
            let html = '';
            data.forEach(m => {
                const isAdms = !m.ip_address || m.ip_address.trim() === '';
                const snDisplay = m.sn ? `<code>${this.escapeHtml(m.sn)}</code>` : '<span style="color:#ef4444; font-style:italic;">(Wajib diisi untuk ADMS)</span>';
                const ipDisplay = isAdms ? '<span class="badge badge-info" style="background:#e0f2fe; color:#0369a1;">ADMS (Push)</span>' : `<code>${this.escapeHtml(m.ip_address)}</code>`;

                html += `
                    <tr>
                        <td><strong>${this.escapeHtml(m.nama_mesin)}</strong></td>
                        <td>${snDisplay}</td>
                        <td>${ipDisplay}</td>
                        <td>${m.port}</td>
                        <td>${m.status == 1 ? '<span style="color:green; font-weight:600;">Aktif</span>' : '<span style="color:red; font-weight:600;">Nonaktif</span>'}</td>
                        <td><strong>${m.last_sync || '-'}</strong></td>
                        <td style="text-align:right">
                            ${!isAdms ? `<button class="btn btn-outline" style="padding:4px 8px; font-size:12px;" onclick="Absen.testMesin('${this.escapeHtml(m.ip_address)}', ${m.port})">Tes</button>` : ''}
                            <button class="btn btn-outline" style="padding:4px 8px; font-size:12px;" onclick='Absen.showMesinModal(${JSON.stringify(m).replace(/'/g, "&apos;")})'>Edit</button>
                            <button class="btn btn-outline" style="padding:4px 8px; font-size:12px; color:red; border-color:red;" onclick="Absen.deleteMesin(${m.id}, '${this.escapeHtml(m.nama_mesin)}')">Hapus</button>
                        </td>
                    </tr>
                `;
            });
            $('#mesinTbody').html(html);
        });
    },

    showMesinModal(m = null) {
        const isEdit = !!m;
        const d = m || { id: 0, nama_mesin: 'Mesin Fingerprint', ip_address: '', port: 4370, sn: '', com_key: '0', status: 1 };
        
        EModal.form({
            title: isEdit ? 'Edit Mesin' : 'Tambah Mesin',
            size: 'md',
            form: `
                <input type="hidden" id="formMesinId" value="${d.id}">
                <div class="form-group">
                    <label class="form-label">Nama Mesin</label>
                    <input type="text" class="form-input" id="formMesinNama" value="${this.escapeHtml(d.nama_mesin)}">
                </div>
                <div style="display:flex; gap:16px;">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">IP Address (Kosongkan jika ADMS)</label>
                        <input type="text" class="form-input" id="formMesinIp" value="${this.escapeHtml(d.ip_address)}" placeholder="192.168.1.xxx">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">SN Mesin (Untuk ADMS)</label>
                        <input type="text" class="form-input" id="formMesinSn" value="${this.escapeHtml(d.sn || '')}" placeholder="Serial Number">
                    </div>
                </div>
                <div style="display:flex; gap:16px;">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">Port (Default: 4370)</label>
                        <input type="number" class="form-input" id="formMesinPort" value="${d.port}">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">Com Key (Default: 0)</label>
                        <input type="text" class="form-input" id="formMesinKey" value="${this.escapeHtml(d.com_key)}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="formMesinStatus">
                        <option value="1" ${d.status == 1 ? 'selected' : ''}>Aktif</option>
                        <option value="0" ${d.status == 0 ? 'selected' : ''}>Nonaktif</option>
                    </select>
                </div>
            `,
            confirmText: 'Simpan',
            onConfirm: () => {
                const data = {
                    id: $('#formMesinId').val(),
                    nama_mesin: $('#formMesinNama').val(),
                    ip_address: $('#formMesinIp').val(),
                    port: $('#formMesinPort').val(),
                    sn: $('#formMesinSn').val(),
                    com_key: $('#formMesinKey').val(),
                    status: $('#formMesinStatus').val()
                };
                if (!data.ip_address && !data.sn) return EModal.toast({ type: 'warning', message: 'IP Address atau SN harus diisi!' }), false;
                
                this.api('mesin.php?action=save', { method: 'POST', data }).done(res => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', message: res.message });
                    this.loadMesinList();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', message: xhr.responseJSON?.message || 'Error' });
                });
                return false;
            }
        });
    },

    deleteMesin(id, nama) {
        EModal.confirm({
            title: 'Hapus Mesin',
            message: `Apakah Anda yakin ingin menghapus <strong>${this.escapeHtml(nama)}</strong>?`,
            type: 'danger',
            confirmText: 'Hapus',
            onConfirm: () => {
                this.api('mesin.php?action=delete', { method: 'POST', data: { id: id } }).done(res => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', message: res.message });
                    this.loadMesinList();
                }).fail(xhr => {
                    EModal.toast({ type: 'error', message: 'Gagal menghapus mesin.' });
                });
                return false;
            }
        });
    },

    testMesin(ip, port) {
        EModal.toast({ type: 'info', message: 'Mencoba terhubung...' });
        this.api('mesin.php?action=test', { method: 'POST', data: { ip_address: ip, port: port } }).done(res => {
            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Mesin tidak terjangkau.' });
        });
    },

    // ==================== WHATSAPP GATEWAY ====================
    renderWAGateway($container) {
        $container.html(`
            <div style="display:grid; grid-template-columns: 1fr 1.5fr; gap:20px; align-items:start;">
                <!-- QR Code & Status Panel -->
                <div class="card">
                    <div class="card-header" style="text-align:center;">
                        <h3>Status Koneksi WA</h3>
                    </div>
                    <div class="card-body" style="text-align:center; padding-bottom:30px;">
                        <div id="waStatusBadge" style="display:inline-block; padding:6px 12px; border-radius:20px; font-size:0.85rem; font-weight:600; margin-bottom:20px; background:#f3f4f6; color:#6b7280;">
                            Memeriksa Server...
                        </div>
                        <div id="waQrContainer" style="min-height:220px; display:flex; align-items:center; justify-content:center; border:2px dashed var(--border-light); border-radius:12px; background:#fafafa;">
                            <div class="skeleton-card" style="width:200px; height:200px; margin:0;"></div>
                        </div>
                        <p id="waQrInstruction" style="margin-top:15px; font-size:0.9rem; color:var(--text-muted); display:none;">
                            Buka WhatsApp di HP Anda, pilih <b>Perangkat Taut (Linked Devices)</b> lalu scan Barcode di atas.
                        </p>
                    </div>
                </div>

                <!-- Konfigurasi Panel -->
                <div class="card">
                    <div class="card-header">
                        <h3>Konfigurasi & Testing</h3>
                        <p class="text-muted">Pengaturan Gateway dan Template Pesan.</p>
                    </div>
                    <div class="card-body" id="waFormWrapper">
                        <div class="skeleton-card" style="height:200px;"></div>
                    </div>
                </div>
            </div>
        `);

        this.loadWaSettings();
        this.checkWaStatus();
        
        // Polling status setiap 5 detik
        if (this.waPollingInterval) clearInterval(this.waPollingInterval);
        this.waPollingInterval = setInterval(() => this.checkWaStatus(), 5000);
    },

    checkWaStatus() {
        if (this.state.currentRoute !== 'wagateway') {
            clearInterval(this.waPollingInterval);
            return;
        }

        this.api('settings.php?action=wa_status').done(res => {
            if (!res.success) {
                // PHP merespon sukses HTTP 200 tapi isinya success: false karena curl ke node.js gagal
                $('#waStatusBadge').css({background: '#fee2e2', color: '#991b1b'}).text('❌ Server Node.js Mati/Offline');
                $('#waQrContainer').html('<p style="color:red; font-size:0.9rem;">Server WA mandiri (Node.js) tidak merespon di port 3000. Pastikan Anda telah menjalankan <code>node server.js</code> di VPS.</p>');
                $('#waQrInstruction').hide();
                return;
            }

            const data = res.data || {};
            if (data.isReady) {
                $('#waStatusBadge').css({background: '#dcfce7', color: '#166534'}).text('✅ WhatsApp Terhubung!');
                $('#waQrContainer').html('<div style="color:var(--primary);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="64" height="64" style="margin-bottom:10px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><br><b>Server WhatsApp Aktif</b></div>');
                $('#waQrInstruction').hide();
            } else if (data.qr) {
                $('#waStatusBadge').css({background: '#fef3c7', color: '#b45309'}).text('⏳ Menunggu Scan Barcode');
                $('#waQrContainer').html(`<img src="${data.qr}" alt="QR Code" style="width:200px; height:200px; display:block; margin:0 auto; border-radius:8px;">`);
                $('#waQrInstruction').show();
            } else {
                $('#waStatusBadge').css({background: '#fef3c7', color: '#b45309'}).text('⏳ Menginisiasi WhatsApp...');
                $('#waQrContainer').html('<p style="color:var(--text-muted);">Menunggu generate QR Code...</p>');
                $('#waQrInstruction').hide();
            }
        }).fail(() => {
            $('#waStatusBadge').css({background: '#fee2e2', color: '#991b1b'}).text('❌ Server Node.js Mati/Offline');
            $('#waQrContainer').html('<p style="color:red; font-size:0.9rem;">Server WA mandiri (Node.js) tidak merespon di port 3000. Pastikan Anda telah menjalankan <code>node server.js</code> di VPS.</p>');
            $('#waQrInstruction').hide();
        });
    },

    loadWaSettings() {
        this.api('settings.php?action=get_wa').done(res => {
            const s = res.data || {};
            $('#waFormWrapper').html(`
                <div class="form-group">
                    <label class="form-label">URL Gateway Lokal</label>
                    <input type="text" class="form-input" id="waUrl" value="${this.escapeHtml(s.wa_gateway_url || 'http://localhost:3000/send')}">
                    <small class="text-muted">Pastikan URL sama dengan port di Node.js (Default: http://localhost:3000/send)</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Template Pesan Notifikasi</label>
                    <textarea class="form-input" id="waTemplate" rows="4" style="resize:vertical;">${this.escapeHtml(s.wa_message_template || 'Halo Orang Tua/Wali dari {nama}. Menginformasikan bahwa ananda telah {status_absen} di sekolah pada {waktu}. Terima Kasih.')}</textarea>
                    <small class="text-muted" style="display:block; margin-bottom:15px;">Gunakan <b>{nama}</b>, <b>{status_absen}</b>, <b>{waktu}</b> sebagai variabel.</small>
                </div>
                <div style="border-top:1px solid var(--border-light); padding-top:15px; margin-top:10px;">
                    <label class="form-label">Testing Pengiriman Pesan</label>
                    <div style="display:flex; gap:10px; margin-bottom:15px;">
                        <input type="text" class="form-input" id="waTestPhone" placeholder="Nomor HP, contoh: 0812xxx" style="flex:1;">
                        <button class="btn btn-outline" onclick="Absen.testWa()">Tes Kirim Pesan</button>
                    </div>
                </div>
                <div style="text-align:right;">
                    <button class="btn btn-primary" onclick="Absen.saveWaSettings()">Simpan Pengaturan Utama</button>
                </div>
            `);
            // Check status segera setelah form di render
            this.checkWaStatus();
        });
    },

    testWa() {
        const phone = $('#waTestPhone').val();
        
        if (!phone) {
            return EModal.toast({ type: 'warning', message: 'Masukkan Nomor HP untuk testing!' });
        }

        EModal.toast({ type: 'info', message: 'Mengirim pesan percobaan...' });
        
        this.api('settings.php?action=wa_test', {
            method: 'POST',
            data: { number: phone, message: 'Halo! Ini adalah pesan pengujian WhatsApp Gateway dari E-Portal.' }
        }).done(res => {
            EModal.toast({ type: 'success', title: 'Terkirim', message: res.message });
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal mengirim pesan.' });
        });
    },

    saveWaSettings() {
        const data = {
            wa_gateway_url: $('#waUrl').val(),
            wa_message_template: $('#waTemplate').val()
        };
        this.api('settings.php?action=save_wa', { method: 'POST', data }).done(res => {
            EModal.toast({ type: 'success', message: res.message });
            this.checkWaStatus();
        }).fail(xhr => {
            EModal.toast({ type: 'error', message: 'Gagal menyimpan pengaturan.' });
        });
    },

    // ==================== MAPPING PEGAWAI ====================
    renderMapping($container) {
        $container.html(`
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3>Mapping ID Pegawai & Mesin</h3>
                        <p class="text-muted">Cocokkan ID/PIN di mesin fingerprint dengan data guru/karyawan di portal.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div id="mappingTableWrapper"><div class="skeleton-card" style="height:300px;"></div></div>
                    <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                        <button class="btn btn-primary" onclick="Absen.saveMapping()">💾 Simpan Semua Mapping</button>
                    </div>
                </div>
            </div>
        `);

        this.api('mapping.php?action=list').done(res => {
            const data = res.data || [];
            const rows = data.map((u, idx) => `
                <tr>
                    <td>${idx + 1}</td>
                    <td><strong>${this.escapeHtml(u.nama_lengkap)}</strong></td>
                    <td>${this.escapeHtml(u.nik || '-')}</td>
                    <td><span class="badge badge-info">${this.escapeHtml(u.role)}</span></td>
                    <td>
                        <input type="text" class="form-input mapping-input" 
                               data-userid="${u.user_id}" 
                               value="${this.escapeHtml(u.mesin_pin || '')}" 
                               placeholder="PIN di Mesin" style="max-width:150px;">
                    </td>
                </tr>
            `).join('');

            $('#mappingTableWrapper').html(`
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr><th width="50">No</th><th>Nama Pegawai</th><th>NIK</th><th>Role</th><th>PIN di Mesin Fingerprint</th></tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `);
        });
    },

    saveMapping() {
        const mappings = [];
        $('.mapping-input').each(function() {
            const user_id = $(this).data('userid');
            const mesin_pin = $(this).val().trim();
            if (mesin_pin) {
                mappings.push({ user_id, mesin_pin });
            }
        });

        this.api('mapping.php?action=save', { method: 'POST', data: { mappings } }).done(res => {
            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menyimpan.' });
        });
    },

    // ==================== TARIK DATA / LOG ====================
    renderLog($container) {
        const today = new Date().toISOString().split('T')[0];
        $container.html(`
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 style="display:flex; align-items:center; gap:10px;">
                            Data Log Mesin 
                            <span style="font-size:0.75rem; padding:4px 8px; background:#dcfce7; color:#166534; border-radius:12px;">Auto-Sync Aktif</span>
                        </h3>
                        <p class="text-muted">Sistem otomatis menarik data dari mesin setiap saat.</p>
                    </div>
                    <div class="ea-toolbar">
                        <button class="btn btn-outline" onclick="Absen.pullData()" id="btnPullData">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            Tarik Manual (Darurat)
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="filter-bar">
                        <div class="filter-item"><label>Tanggal Log</label><input type="date" class="form-input" id="logTanggal" value="${today}"></div>
                        <div class="filter-item"><button class="btn btn-outline" onclick="Absen.loadLogs()">🔍 Filter</button></div>
                    </div>
                    <div id="logTableWrapper"><div class="skeleton-card" style="height:200px;"></div></div>
                </div>
            </div>
        `);
        this.loadLogs();
    },

    pullData() {
        const $btn = $('#btnPullData');
        $btn.html('Menarik Data... ⏳').prop('disabled', true);
        
        this.api('sync.php?action=pull').done(res => {
            EModal.toast({ type: 'success', title: 'Sinkronisasi Manual Selesai', message: res.message });
            this.loadLogs();
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Mesin tidak merespon.' });
        }).always(() => {
            $btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> Tarik Manual (Darurat)').prop('disabled', false);
        });
    },

    loadLogs() {
        const tgl = $('#logTanggal').val();
        this.api(`sync.php?action=logs&tanggal=${tgl}`).done(res => {
            const data = res.data || [];
            if (!data.length) {
                $('#logTableWrapper').html(`<div class="empty-state"><h3>Kosong</h3><p>Belum ada data log yang ditarik untuk tanggal ini.</p></div>`);
                return;
            }
            
            const stateLabels = {0: 'Check-In', 1: 'Check-Out', 2: 'Break-Out', 3: 'Break-In', 4: 'OT-In', 5: 'OT-Out'};
            const typeLabels = {0: 'Password', 1: 'Fingerprint', 2: 'Card', 3: 'Face'};
            
            const rows = data.map(l => `
                <tr>
                    <td>${this.escapeHtml(l.waktu_absen)}</td>
                    <td>${this.escapeHtml(l.mesin_pin)}</td>
                    <td><strong>${this.escapeHtml(l.nama_pegawai || '(Belum di-mapping)')}</strong></td>
                    <td><span class="badge ${l.status_absen == 0 ? 'badge-success' : 'badge-warning'}">${stateLabels[l.status_absen] || l.status_absen}</span></td>
                    <td>${typeLabels[l.verify_type] || l.verify_type}</td>
                    <td>${this.escapeHtml(l.nama_mesin)}</td>
                </tr>
            `).join('');
            
            $('#logTableWrapper').html(`
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead><tr><th>Waktu Tap</th><th>PIN Mesin</th><th>Nama (Mapping)</th><th>Status Mesin</th><th>Metode</th><th>Nama Mesin</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `);
        });
    },

    // ==================== REKAP ====================
    renderRekap($container) {
        const today = new Date().toISOString().split('T')[0];
        const isAdmin = this.state.user.role === 'superadmin';
        
        $container.html(`
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3>Rekapitulasi Kehadiran</h3>
                        <p class="text-muted">Data jam masuk dan pulang hasil kalkulasi dari log mesin.</p>
                    </div>
                    ${isAdmin ? `
                    <div class="ea-toolbar">
                        <button class="btn btn-primary" onclick="Absen.generateRekap()" id="btnGenRekap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21.5 2v6h-6M2.13 15.57a10 10 0 1 0 14.9-10.42L21.5 8M2.5 22v-6h6M21.87 8.43a10 10 0 1 0-14.9 10.42L2.5 16"/></svg>
                            Generate Rekap Hari Ini
                        </button>
                    </div>
                    ` : ''}
                </div>
                <div class="card-body">
                    <div class="filter-bar">
                        <div class="filter-item"><label>Tanggal</label><input type="date" class="form-input" id="rekapTanggal" value="${today}"></div>
                        <div class="filter-item" style="flex-direction:row; gap:8px;">
                            <button class="btn btn-outline" onclick="Absen.loadRekap()">🔍 Tampilkan</button>
                            <button class="btn btn-success" onclick="Absen.exportExcel()" style="background:#10B981; color:white; border:none;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 3v5h5M16 13H8M16 17H8M10 9H8"/></svg>
                                Export Excel
                            </button>
                        </div>
                    </div>
                    
                    ${isAdmin ? `
                    <div style="margin-bottom: 16px; border-bottom: 1px solid var(--border-color); display: flex; gap: 16px;">
                        <button class="rekap-tab-btn active" data-tab="guru" onclick="Absen.switchRekapTab('guru')" style="background: none; border: none; padding: 8px 16px; cursor: pointer; border-bottom: 2px solid var(--primary-color); font-weight: 600; color: var(--primary-color);">Guru / Pegawai</button>
                        <button class="rekap-tab-btn" data-tab="siswa" onclick="Absen.switchRekapTab('siswa')" style="background: none; border: none; padding: 8px 16px; cursor: pointer; border-bottom: 2px solid transparent; color: var(--text-muted);">Siswa</button>
                    </div>
                    <div id="rekapTableWrapperGuru" class="rekap-tab-content active"><div class="skeleton-card" style="height:200px;"></div></div>
                    <div id="rekapTableWrapperSiswa" class="rekap-tab-content" style="display:none;"><div class="skeleton-card" style="height:200px;"></div></div>
                    <input type="hidden" id="activeRekapTab" value="guru">
                    ` : `
                    <div id="rekapTableWrapperGuru" class="rekap-tab-content active"><div class="skeleton-card" style="height:200px;"></div></div>
                    <input type="hidden" id="activeRekapTab" value="guru">
                    `}
                </div>
            </div>
        `);
        this.loadRekap();
    },

    switchRekapTab(tab) {
        $('.rekap-tab-btn').css({ 'border-bottom': '2px solid transparent', 'color': 'var(--text-muted)', 'font-weight': 'normal' });
        $(`.rekap-tab-btn[data-tab="${tab}"]`).css({ 'border-bottom': '2px solid var(--primary-color)', 'color': 'var(--primary-color)', 'font-weight': '600' });
        
        $('.rekap-tab-content').hide();
        $(`#rekapTableWrapper${tab === 'guru' ? 'Guru' : 'Siswa'}`).fadeIn(200);
        $('#activeRekapTab').val(tab);
    },

    generateRekap() {
        const tgl = $('#rekapTanggal').val();
        $('#btnGenRekap').text('Processing...').prop('disabled', true);
        
        this.api(`rekap.php?action=generate&tanggal=${tgl}`).done(res => {
            EModal.toast({ type: 'success', title: 'Berhasil', message: res.message });
            this.loadRekap();
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Error.' });
        }).always(() => {
            $('#btnGenRekap').html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21.5 2v6h-6M2.13 15.57a10 10 0 1 0 14.9-10.42L21.5 8M2.5 22v-6h6M21.87 8.43a10 10 0 1 0-14.9 10.42L2.5 16"/></svg> Generate Rekap Hari Ini').prop('disabled', false);
        });
    },

    loadRekap() {
        const tgl = $('#rekapTanggal').val();
        this.api(`rekap.php?action=report&tanggal=${tgl}`).done(res => {
            const data = res.data || { guru: [], siswa: [] };
            
            const badgeMap = { 'Hadir': 'badge-success', 'Terlambat': 'badge-warning', 'Alpha': 'badge-danger', 'Izin': 'badge-info', 'Sakit': 'badge-info' };
            
            // 1. Render Guru Table
            if (!data.guru || !data.guru.length) {
                $('#rekapTableWrapperGuru').html(`<div class="empty-state"><h3>Belum Ada Data Rekap</h3><p>Pilih tanggal lalu klik Generate Rekap.</p></div>`);
            } else {
                const rowsGuru = data.guru.map((r, idx) => `
                    <tr>
                        <td>${idx + 1}</td>
                        <td><strong>${this.escapeHtml(r.nama_lengkap)}</strong></td>
                        <td><span class="badge ${badgeMap[r.status] || 'badge-info'}">${this.escapeHtml(r.status)}</span></td>
                        <td><strong>${r.jam_masuk || '-'}</strong></td>
                        <td><strong>${r.jam_pulang || '-'}</strong></td>
                    </tr>
                `).join('');
                $('#rekapTableWrapperGuru').html(`
                    <div class="data-table-wrapper">
                        <table class="data-table" id="rekapTableExportGuru">
                            <thead><tr><th width="50">No</th><th>Nama Pegawai</th><th>Status</th><th>Jam Masuk</th><th>Jam Pulang</th></tr></thead>
                            <tbody>${rowsGuru}</tbody>
                        </table>
                    </div>
                `);
            }

            // 2. Render Siswa Table (if admin)
            if ($('#rekapTableWrapperSiswa').length) {
                if (!data.siswa || !data.siswa.length) {
                    $('#rekapTableWrapperSiswa').html(`<div class="empty-state"><h3>Belum Ada Data Siswa</h3><p>Siswa melakukan presensi via mesin atau sinkronisasi absen.</p></div>`);
                } else {
                    const rowsSiswa = data.siswa.map((r, idx) => `
                        <tr>
                            <td>${idx + 1}</td>
                            <td><strong>${this.escapeHtml(r.nama_lengkap)}</strong><br><small class="text-muted">NIS: ${this.escapeHtml(r.nik)}</small></td>
                            <td>${this.escapeHtml(r.jabatan)}</td>
                            <td><span class="badge ${badgeMap[r.status] || 'badge-info'}">${this.escapeHtml(r.status)}</span></td>
                            <td><strong>${r.jam_masuk || '-'}</strong></td>
                            <td><strong>${r.jam_pulang || '-'}</strong></td>
                        </tr>
                    `).join('');
                    $('#rekapTableWrapperSiswa').html(`
                        <div class="data-table-wrapper">
                            <table class="data-table" id="rekapTableExportSiswa">
                                <thead><tr><th width="50">No</th><th>Nama Siswa</th><th>Kelas</th><th>Status</th><th>Jam Masuk</th><th>Jam Pulang</th></tr></thead>
                                <tbody>${rowsSiswa}</tbody>
                            </table>
                        </div>
                    `);
                }
            }
        });
    },

    exportExcel() {
        const tgl = $('#rekapTanggal').val();
        const activeTab = $('#activeRekapTab').val() || 'guru';
        const tableId = activeTab === 'guru' ? 'rekapTableExportGuru' : 'rekapTableExportSiswa';
        
        const table = document.getElementById(tableId);
        if (!table) {
            EModal.toast({ type: 'warning', title: 'Kosong', message: 'Tidak ada data untuk diekspor.' });
            return;
        }

        const wb = XLSX.utils.table_to_book(table, {sheet: "Rekap " + activeTab});
        XLSX.writeFile(wb, `Rekap_Absen_${activeTab}_${tgl}.xlsx`);
    },

    // ==================== HELPERS ====================
    api(endpoint, options = {}) {
        const defaults = {
            url: this.moduleUrl + 'api/' + endpoint,
            dataType: 'json',
            contentType: 'application/json',
            timeout: 10000, // ZKLibrary operations might take time
            headers: { 'Authorization': 'Bearer ' + (this.state.token || '') }
        };
        if (options.data && typeof options.data === 'object' && !(options.data instanceof FormData)) {
            options.data = JSON.stringify(options.data);
        }
        return $.ajax({ ...defaults, ...options });
    },

    doLogout() {
        EModal.confirm({
            title: 'Logout',
            message: 'Yakin ingin keluar dari E-Absen?',
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
        return name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
    },

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

$(document).ready(() => Absen.init());

