<?php
require_once __DIR__ . '/../../api/config.php';
$school_name = get_setting('nama_sekolah', 'E-Portal Sekolah');
$school_icon = get_setting('icon_sekolah', '');
$active = get_active_academic_year();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Siswa E-Graduation - <?php echo htmlspecialchars($school_name); ?></title>
    <?php if ($school_icon): ?><link rel="icon" href="<?php echo BASE_URL . $school_icon; ?>"><?php endif; ?>
    <style>
        :root{--ink:#0f172a;--muted:#64748b;--blue:#1454a8;--teal:#0f766e;--paper:#f8fafc;--line:#e2e8f0;--yellow:#facc15;--red:#dc2626}
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;min-height:100dvh;font-family:Arial,Helvetica,sans-serif;color:var(--ink);display:flex;align-items:center;justify-content:center;padding:24px;background:radial-gradient(circle at 18% 12%,rgba(226,246,255,.95) 0,rgba(226,246,255,.45) 22%,transparent 38%),radial-gradient(circle at 92% 84%,rgba(52,211,153,.25) 0,transparent 32%),linear-gradient(140deg,#123f60 0%,#164e63 50%,#0f766e 100%)}
        .portal{width:min(980px,100%);display:grid;grid-template-columns:.92fr 1.08fr;gap:22px;align-items:stretch}
        .hero,.card{background:rgba(255,255,255,.94);border:1px solid rgba(255,255,255,.7);border-radius:30px;box-shadow:0 26px 80px rgba(2,8,23,.22);overflow:hidden;backdrop-filter:blur(16px)}
        .hero{padding:34px;display:flex;flex-direction:column;justify-content:space-between;min-height:450px;background:linear-gradient(155deg,rgba(255,255,255,.98),rgba(239,253,250,.92))}
        .brand{display:flex;align-items:center;gap:14px}.brand img{width:54px;height:54px;object-fit:contain}.brand h1{font-size:23px;letter-spacing:.2px}.muted{color:var(--muted);font-size:14px;line-height:1.55}
        .hero-title{font-size:38px;line-height:1.04;margin:34px 0 14px;color:#082f49;letter-spacing:-1px}.pill{display:inline-flex;width:max-content;padding:8px 13px;border-radius:999px;background:#e0f2fe;color:#075985;font-weight:800;font-size:12px}
        .card{padding:30px}.login-title{font-size:24px;margin-bottom:8px}.form-group{margin:16px 0}.form-group label{display:block;font-weight:800;margin-bottom:7px;font-size:13px}.input{width:100%;padding:14px 15px;border:1px solid #d5dde8;border-radius:14px;font-size:15px;background:#fff}.input:focus{outline:3px solid rgba(20,84,168,.12);border-color:#93c5fd}
        .btn{border:0;border-radius:14px;padding:13px 17px;font-weight:800;cursor:pointer;transition:.18s ease;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}.btn:hover{transform:translateY(-1px)}.btn-primary{background:linear-gradient(135deg,#1454a8,#0f766e);color:#fff;width:100%;box-shadow:0 12px 24px rgba(13,71,161,.24)}.btn-light{background:#eef6ff;color:#0d47a1}.btn-danger{background:#fff1f2;color:#be123c}
        .portal.logged-in{grid-template-columns:minmax(320px,620px);justify-content:center}.portal.logged-in .hero{display:none}.portal.logged-in .card{padding:0;min-height:auto;background:#f8fafc}
        .dashboard{display:none}.student-head{display:flex;gap:14px;align-items:center;padding:26px 26px 22px;background:linear-gradient(135deg,#ffffff 0%,#edf7ff 100%);border-bottom:1px solid var(--line)}.student-head h2{font-size:28px;line-height:1.1;letter-spacing:-.5px}.avatar{width:68px;height:68px;flex:0 0 68px;border-radius:22px;background:linear-gradient(135deg,#dbeafe,#ecfeff);display:flex;align-items:center;justify-content:center;overflow:hidden;font-weight:900;color:#0d47a1;box-shadow:inset 0 0 0 1px rgba(20,84,168,.08)}.avatar img{width:100%;height:100%;object-fit:cover}.actions{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;padding:0 26px 24px}.actions .btn{width:100%}
        #scoresWrap{padding:0 26px 26px}.score-table{width:100%;border-collapse:separate;border-spacing:0;margin-top:14px;background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden}.score-table th,.score-table td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left}.score-table tr:last-child td{border-bottom:0}.score-table th{background:#f1f5f9;font-size:12px;text-transform:uppercase;letter-spacing:.35px;color:#475569}
        #statusPanel{padding:24px 26px 20px}.status-panel{position:relative;overflow:hidden;border-radius:26px;padding:26px;text-align:left;display:grid;grid-template-columns:auto 1fr;gap:18px;align-items:center;min-height:0}.status-panel:before{content:"";position:absolute;right:-42px;bottom:-58px;width:154px;height:154px;border-radius:50%;opacity:.18}.status-panel:after{content:"";position:absolute;right:22px;top:22px;width:56px;height:7px;border-radius:999px;opacity:.5}
        .status-panel.warning{background:linear-gradient(135deg,#fff8db 0%,#fff7ed 45%,#fff1f2 100%);border:1px solid #fed7aa;box-shadow:0 16px 40px rgba(220,38,38,.10)}.status-panel.warning:before{background:var(--red)}.status-panel.warning:after{background:var(--yellow)}
        .status-panel.success{background:linear-gradient(135deg,#dcfce7 0%,#f0fdfa 100%);border:1px solid #86efac}.status-panel.success:before{background:#16a34a}.status-panel.success:after{background:#22c55e}
        .status-panel.countdown-state{background:linear-gradient(135deg,#fef3c7 0%,#eff6ff 100%);border:1px solid #facc15}.status-panel.countdown-state:before{background:#f59e0b}.status-panel.countdown-state:after{background:#0ea5e9}
        .status-icon{width:58px;height:58px;border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;z-index:1}.warning .status-icon{background:#dc2626;color:#fff;box-shadow:0 12px 26px rgba(220,38,38,.22)}.success .status-icon{background:#16a34a;color:#fff}.countdown-state .status-icon{background:#f59e0b;color:#fff}
        .status-copy{position:relative;z-index:1}.status-eyebrow{display:block;margin-bottom:5px;font-size:11px;font-weight:900;letter-spacing:.7px;text-transform:uppercase}.warning .status-eyebrow{color:#b45309}.success .status-eyebrow{color:#15803d}.countdown-state .status-eyebrow{color:#b45309}
        .status-panel h3{font-size:24px;line-height:1.08;color:#7f1d1d;margin-bottom:8px;letter-spacing:-.4px}.status-panel.success h3{color:#14532d}.status-panel.countdown-state h3{color:#78350f}.status-panel p{font-size:14.5px;line-height:1.55;color:#7c2d12;max-width:420px}.status-panel.success p{color:#166534}.status-panel.countdown-state p{color:#92400e}
        .modal{position:fixed;inset:0;background:rgba(2,8,23,.62);display:none;align-items:center;justify-content:center;padding:18px;z-index:20}.modal.show{display:flex}.modal-card{width:min(520px,100%);background:#fff;border-radius:26px;padding:28px;text-align:center;box-shadow:0 24px 80px rgba(0,0,0,.28)}.modal-card h2{font-size:28px;margin-bottom:12px}.modal-photo{width:100px;height:120px;border-radius:18px;object-fit:cover;margin:0 auto 14px;background:#eef2f7}.countdown{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:18px 0}.countdown div{background:#eff6ff;border-radius:14px;padding:12px}.countdown strong{display:block;font-size:24px;color:#0d47a1}.hidden{display:none!important}
        .toast{position:fixed;right:18px;bottom:18px;background:#111827;color:#fff;padding:13px 16px;border-radius:14px;display:none}
        @media(max-width:760px){body{align-items:flex-start;padding:14px;background:radial-gradient(circle at 20% 8%,rgba(226,246,255,.9) 0,transparent 34%),linear-gradient(150deg,#123f60 0%,#17666a 100%)}.portal{grid-template-columns:1fr;gap:14px}.hero{min-height:auto;padding:24px;border-radius:26px}.hero-title{font-size:31px}.card{border-radius:28px;padding:24px}.portal.logged-in{width:100%;grid-template-columns:1fr}.portal.logged-in .card{border-radius:28px}.student-head{padding:22px 20px 18px}.student-head h2{font-size:25px}.avatar{width:62px;height:62px;flex-basis:62px;border-radius:20px}#statusPanel{padding:20px}.status-panel{grid-template-columns:1fr;text-align:center;justify-items:center;padding:24px 20px}.status-panel:after{left:50%;right:auto;transform:translateX(-50%);top:auto;bottom:18px}.status-panel h3{font-size:23px}.actions{grid-template-columns:1fr;padding:0 20px 22px}#scoresWrap{padding:0 20px 22px}.countdown strong{font-size:18px}}
        @media(max-width:420px){body{padding:10px}.portal.logged-in .card{border-radius:24px}.student-head{gap:12px}.student-head h2{font-size:23px}.muted{font-size:13px}.status-panel h3{font-size:21px}.status-panel p{font-size:14px}.status-icon{width:54px;height:54px;border-radius:18px}.modal-card{border-radius:22px;padding:22px}}
    </style>
</head>
<body>
    <main class="portal" id="portalWrap">
        <section class="hero">
            <div>
                <div class="brand">
                    <?php if ($school_icon): ?><img src="<?php echo BASE_URL . $school_icon; ?>" alt="Logo"><?php endif; ?>
                    <div><h1><?php echo htmlspecialchars($school_name); ?></h1><p class="muted">E-Graduation Siswa</p></div>
                </div>
                <h2 class="hero-title">Portal Pengumuman Kelulusan</h2>
                <p class="muted">Login menggunakan NIS dan password tanggal lahir format DDMMYYYY. Tahun aktif: <strong><?php echo htmlspecialchars(($active['tahun_ajaran'] ?? '-') . ' Semester ' . ($active['semester'] ?? '-')); ?></strong></p>
            </div>
            <span class="pill">SKL PDF + QR Verifikasi</span>
        </section>
        <section class="card">
            <div id="loginPanel">
                <h2 class="login-title">Login Siswa</h2>
                <p class="muted">Username: NIS. Password: tanggal lahir, contoh 01052007.</p>
                <div class="form-group"><label>Username / NIS</label><input class="input" id="username" autocomplete="username"></div>
                <div class="form-group"><label>Password</label><input class="input" id="password" type="password" autocomplete="current-password"></div>
                <button class="btn btn-primary" onclick="StudentPortal.login()">Masuk</button>
            </div>
            <div id="dashboardPanel" class="dashboard">
                <div class="student-head">
                    <div class="avatar" id="studentAvatar"></div>
                    <div><h2 id="studentName"></h2><p class="muted" id="studentMeta"></p></div>
                </div>
                <p class="muted" id="announcementText"></p>
                <div id="statusPanel"></div>
                <div class="actions" id="studentActions">
                    <button class="btn btn-light" onclick="StudentPortal.loadScores()">Lihat Nilai</button>
                    <button class="btn btn-light" onclick="StudentPortal.downloadSkl()">Download SKL</button>
                    <button class="btn btn-danger" onclick="StudentPortal.logout()">Logout</button>
                </div>
                <div id="scoresWrap"></div>
            </div>
        </section>
    </main>
    <div class="modal" id="messageModal"><div class="modal-card" id="messageBody"></div></div>
    <div class="toast" id="toast"></div>
    <script>
        const StudentPortal = {
            baseUrl: '<?php echo BASE_URL; ?>',
            apiUrl: '<?php echo BASE_URL; ?>modules/e-graduation/api/',
            schoolName: '<?php echo addslashes($school_name); ?>',
            token: localStorage.getItem('grad_student_token') || '',
            student: null,
            announcement: null,
            timer: null,
            init() {
                if (this.token) this.me();
                document.getElementById('password').addEventListener('keydown', e => { if (e.key === 'Enter') this.login(); });
            },
            api(endpoint, options = {}) {
                return fetch(this.apiUrl + endpoint, {
                    method: options.method || 'GET',
                    headers: Object.assign({'Authorization': 'Bearer ' + this.token}, options.headers || {}),
                    body: options.body
                }).then(async r => {
                    const data = await r.json().catch(() => ({}));
                    if (!r.ok || data.success === false) throw new Error(data.message || 'Terjadi kesalahan.');
                    return data;
                });
            },
            login() {
                const fd = new FormData();
                fd.append('username', document.getElementById('username').value);
                fd.append('password', document.getElementById('password').value);
                this.api('student_portal.php?action=login', {method:'POST', body:fd}).then(res => {
                    this.token = res.data.token;
                    localStorage.setItem('grad_student_token', this.token);
                    this.setState(res.data.student, res.data.announcement);
                }).catch(e => this.toast(e.message));
            },
            me() {
                this.api('student_portal.php?action=me').then(res => {
                    this.setState(res.data.student, res.data.announcement);
                }).catch(() => {
                    localStorage.removeItem('grad_student_token');
                    this.token = '';
                });
            },
            setState(student, announcement) {
                this.student = student;
                this.announcement = announcement;
                document.getElementById('loginPanel').style.display = 'none';
                document.getElementById('dashboardPanel').style.display = 'block';
                document.getElementById('portalWrap').classList.add('logged-in');
                document.getElementById('studentName').textContent = student.nama;
                document.getElementById('studentMeta').textContent = `NIS: ${student.nis} | Kelas: ${student.kelas}`;
                document.getElementById('studentAvatar').innerHTML = student.foto_url ? `<img src="${student.foto_url}">` : student.nama.substring(0,2).toUpperCase();
                document.getElementById('studentActions').classList.toggle('hidden', announcement.mode !== 'published');
                document.getElementById('announcementText').textContent = '';
                this.renderStatusPanel();
                this.showMessage();
            },
            renderStatusPanel() {
                const a = this.announcement;
                const panel = document.getElementById('statusPanel');
                if (a.mode === 'published') {
                    panel.innerHTML = `<div class="status-panel success"><div class="status-icon">✓</div><div class="status-copy"><span class="status-eyebrow">Status pengumuman</span><h3>Pengumuman Dibuka</h3><p style="font-size:15px;">Selamat! Kamu dinyatakan <strong style="font-size:22px; font-weight:900; color:#14532d; display:inline-block; margin:0 4px; padding:2px 8px; background:#bbf7d0; border-radius:6px;">LULUS</strong> dari ${this.schoolName}. Kamu bisa membuka nilai dan mengunduh SKL PDF melalui tombol di bawah.</p></div></div>`;
                } else if (a.mode === 'countdown') {
                    panel.innerHTML = `<div class="status-panel countdown-state"><div class="status-icon">H</div><div class="status-copy"><span class="status-eyebrow">Hitung mundur aktif</span><h3>Sedang Menunggu Waktu</h3><p>Pengumuman kelulusan sudah dijadwalkan. Tenang, halaman ini akan menampilkan hasil saat waktunya tiba.</p></div></div>`;
                } else {
                    panel.innerHTML = `<div class="status-panel warning"><div class="status-icon">!</div><div class="status-copy"><span class="status-eyebrow">Belum tersedia</span><h3>Pengumuman Belum Dibuka</h3><p>Mohon bersabar ya. Pengumuman kelulusan tahun pelajaran ${a.tahun_ajaran || '-'} belum ditetapkan oleh sekolah.</p></div></div>`;
                }
            },
            escapeHtml(str) {
                return String(str || '').replace(/[&<>"']/g, function(m) {
                    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
                });
            },
            showMessage() {
                const a = this.announcement;
                const year = this.escapeHtml(a.tahun_ajaran || '-');
                let html = '';
                if (a.mode === 'not_set') {
                    html = `<h2>MOHON MAAF</h2><p>PENGUMUMAN KELULUSAN TAHUN PELAJARAN ${year}<br><strong>BELUM DITETAPKAN</strong></p><br><button class="btn btn-primary" onclick="StudentPortal.closeMessage()">OK</button>`;
                } else if (a.mode === 'countdown') {
                    html = `<h2>MENUJU PENGUMUMAN</h2><p>KELULUSAN TAHUN PELAJARAN ${year}</p><div class="countdown" id="countdownBox"></div><button class="btn btn-primary" onclick="StudentPortal.closeMessage()">OK</button>`;
                } else {
                    const nameEscaped = this.escapeHtml(a.student_name);
                    const nisEscaped = this.escapeHtml(a.nis);
                    const kelasEscaped = this.escapeHtml(a.kelas);
                    html = `${a.foto_url ? `<img class="modal-photo" src="${a.foto_url}">` : ''}<h2>SELAMAT</h2><p><strong>${nameEscaped}</strong> / NIS : ${nisEscaped} / KELAS ${kelasEscaped}<br>DINYATAKAN <strong style="font-size:24px; color:#14532d;">LULUS</strong></p><br><button class="btn btn-primary" onclick="StudentPortal.closeMessage()">OK</button>`;
                }
                document.getElementById('messageBody').innerHTML = html;
                document.getElementById('messageModal').classList.add('show');
                if (a.mode === 'countdown') this.startCountdown(a.seconds_remaining || 0);
            },
            startCountdown(seconds) {
                clearInterval(this.timer);
                const box = document.getElementById('countdownBox');
                const render = () => {
                    const d = Math.floor(seconds / 86400);
                    const h = Math.floor(seconds % 86400 / 3600);
                    const m = Math.floor(seconds % 3600 / 60);
                    const s = Math.floor(seconds % 60);
                    box.innerHTML = `<div><strong>${d}</strong>Hari</div><div><strong>${h}</strong>Jam</div><div><strong>${m}</strong>Menit</div><div><strong>${s}</strong>Detik</div>`;
                    if (seconds <= 0) location.reload();
                    seconds--;
                };
                render();
                this.timer = setInterval(render, 1000);
            },
            closeMessage() {
                document.getElementById('messageModal').classList.remove('show');
            },
            loadScores() {
                this.api('student_portal.php?action=scores').then(res => {
                    const groups = {};
                    let totalScore = 0;
                    let countScore = 0;
                    
                    res.data.items.forEach(item => {
                        const key = (item.group_kode ? item.group_kode + '. ' : '') + item.group_nama;
                        if (!groups[key]) groups[key] = [];
                        groups[key].push(item);
                        
                        if (item.nilai_akhir !== null && item.nilai_akhir !== undefined && item.nilai_akhir !== '') {
                            const val = parseFloat(String(item.nilai_akhir).replace(',', '.'));
                            if (!isNaN(val)) {
                                totalScore += val;
                                countScore++;
                            }
                        }
                    });
                    
                    let rows = '';
                    for (const groupName in groups) {
                        rows += `<tr><td colspan="3" style="background:#f8fafc; font-weight:800; color:#1e293b; padding:12px 16px; border-bottom:2px solid #e2e8f0;">${groupName}</td></tr>`;
                        groups[groupName].forEach((item, i) => {
                            rows += `<tr>
                                <td style="text-align:center; color:#64748b; width:50px;">${i+1}</td>
                                <td style="font-weight:500; color:#334155;">${item.nama_mapel}</td>
                                <td style="text-align:center; font-weight:bold; color:#0f766e; width:80px; font-size:15px;">${item.nilai_akhir ?? '-'}</td>
                            </tr>`;
                        });
                    }
                    
                    const avg = countScore > 0 ? (totalScore / countScore).toFixed(2) : '-';
                    rows += `<tr>
                        <td colspan="2" style="text-align:right; font-weight:800; color:#1e293b; padding:14px 16px; text-transform:uppercase; border-top:2px solid #cbd5e1;">Rata - Rata</td>
                        <td style="text-align:center; font-weight:900; color:#0f766e; font-size:16px; border-top:2px solid #cbd5e1;">${avg}</td>
                    </tr>`;
                    
                    document.getElementById('scoresWrap').innerHTML = `<table class="score-table"><thead><tr><th style="text-align:center">No</th><th>Mata Pelajaran</th><th style="text-align:center">Nilai</th></tr></thead><tbody>${rows}</tbody></table>`;
                }).catch(e => this.toast(e.message));
            },
            downloadSkl() {
                window.open(`${this.apiUrl}reports.php?action=student-download-skl&student_token=${encodeURIComponent(this.token)}`, '_blank');
            },
            logout() {
                this.api('student_portal.php?action=logout', {method:'POST'}).finally(() => {
                    localStorage.removeItem('grad_student_token');
                    document.getElementById('portalWrap').classList.remove('logged-in');
                    location.reload();
                });
            },
            toast(msg) {
                const el = document.getElementById('toast');
                el.textContent = msg;
                el.style.display = 'block';
                setTimeout(() => el.style.display = 'none', 3000);
            }
        };
        StudentPortal.init();
    </script>
</body>
</html>
