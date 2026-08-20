const ExamCard = {
    state: {
        baseUrl: window.EXAMCARD_CONFIG.baseUrl,
        apiUrl: `${window.EXAMCARD_CONFIG.baseUrl}modules/e-xam-card/api/`,
        token: window.EXAMCARD_CONFIG.token,
        user: window.EXAMCARD_CONFIG.user,
        school: window.EXAMCARD_CONFIG.school,
        academicYear: window.EXAMCARD_CONFIG.academicYear,
        route: "dashboard",
        exams: [],
        settingMeta: null,
        statusClasses: [],
        teachers: []
    },

    init() {
        this.bindEvents();
        this.renderSidebar();
        this.resolveRoute();
        setTimeout(() => {
            $("#globalLoader").addClass("hidden");
            setTimeout(() => $("#globalLoader").remove(), 450);
        }, 650);
    },

    bindEvents() {
        window.addEventListener("hashchange", () => this.resolveRoute());
        $("#menuToggle").on("click", () => this.toggleSidebar());
        $("#sidebarOverlay").on("click", () => this.toggleSidebar(false));
    },

    api(file, action, options = {}) {
        const params = new URLSearchParams({ action });
        if (options.query) {
            Object.keys(options.query).forEach((key) => {
                if (options.query[key] !== undefined && options.query[key] !== null) {
                    params.append(key, options.query[key]);
                }
            });
        }

        const ajaxOptions = {
            url: `${this.state.apiUrl}${file}?${params.toString()}`,
            dataType: "json",
            headers: { Authorization: `Bearer ${this.state.token}` },
            timeout: 30000,
            cache: false,
            ...options.ajax
        };

        if (ajaxOptions.data && typeof ajaxOptions.data === "object" && !(ajaxOptions.data instanceof FormData)) {
            ajaxOptions.data = JSON.stringify(ajaxOptions.data);
            ajaxOptions.contentType = "application/json";
        }

        return $.ajax(ajaxOptions);
    },

    escapeHtml(value) {
        const div = document.createElement("div");
        div.textContent = value == null ? "" : String(value);
        return div.innerHTML;
    },

    escapeAttr(value) {
        if (value == null) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    },

    formatDate(value) {
        if (!value) return "-";
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
    },

    routeTitle(route) {
        const map = {
            dashboard: ["Dashboard", "Ringkasan modul E-Xam Card"],
            exams: ["Master Ujian", "Input nama ujian dan periode pelaksanaan"],
            settings: ["Pengaturan Ujian", "Set kelas peserta, surat, dan kepala sekolah"],
            status: [this.state.user.is_teacher ? "Wali Kelas" : "Status Kartu", "Kelola akun siswa dan status OKE/DITANGGUHKAN"],
            reports: ["Laporan", "Rekap status dan download kartu ujian"],
            access: ["Akses Guru", "Pengaturan akses wali kelas"]
        };
        return map[route] || map.dashboard;
    },

    renderSidebar() {
        let nav = [
            {
                key: "dashboard",
                label: "Dashboard",
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>'
            },
            {
                key: "exams",
                label: "Master Ujian",
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M3 10h18"></path></svg>'
            },
            {
                key: "settings",
                label: "Pengaturan Ujian",
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-.33-1 1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1-.33 1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6c.36 0 .7-.13 1-.36.28-.22.49-.53.6-.87V3a2 2 0 1 1 4 0v.09c.11.34.32.65.6.87.3.23.64.36 1 .36a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c0 .36.13.7.36 1 .22.28.53.49.87.6H21a2 2 0 1 1 0 4h-.09c-.34.11-.65.32-.87.6-.23.3-.36.64-.36 1z"></path></svg>'
            },
            {
                key: "status",
                label: this.state.user.is_teacher ? "Wali Kelas" : "Status Kartu",
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 11V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6"></path><path d="M8 7h6"></path><path d="M8 11h4"></path><circle cx="17" cy="17" r="4"></circle><path d="m21 21-1.5-1.5"></path></svg>'
            },
            {
                key: "reports",
                label: "Laporan",
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"></path><path d="M7 15l4-4 3 3 5-6"></path></svg>'
            },
            {
                key: "access",
                label: "Akses Guru",
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>'
            }
        ];

        if (this.state.user.is_teacher) {
            nav = nav.filter(item => ["dashboard", "status", "reports"].includes(item.key));
        }

        const navButtons = nav
            .map((item) => `<button class="sp-nav-item ${item.key === this.state.route ? "active" : ""}" data-route="${item.key}">${item.icon}<span>${item.label}</span></button>`)
            .join("");
        $("#sidebarNav").html(`
            <div class="sp-nav-label">Menu Utama</div>
            ${navButtons}
        `);

        const role = (this.state.user.role || "").replace(/_/g, " ");
        const fullName = this.state.user.nama_lengkap || "";
        const initials = fullName
            .split(" ")
            .filter(Boolean)
            .slice(0, 2)
            .map((w) => w[0])
            .join("")
            .toUpperCase() || "US";
        $("#sidebarUser").html(`
            <div class="sp-sidebar-user" style="width: 100%;">
                <div class="sp-user-avatar">${this.escapeHtml(initials)}</div>
                <div class="sp-user-info">
                    <div class="sp-user-name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;">${this.escapeHtml(fullName)}</div>
                    <div class="sp-user-role">${this.escapeHtml(role)}</div>
                </div>
                <button class="sp-logout-btn" id="examLogoutBtn" title="Keluar Sesi">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                </button>
            </div>
        `);

        $("#sidebarNav .sp-nav-item").off("click").on("click", (event) => {
            const route = $(event.currentTarget).data("route");
            if (!route) return;
            location.hash = `#/${route}`;
            this.toggleSidebar(false);
        });
        $("#examResetBtn").off("click").on("click", () => {
            EModal.toast({
                type: "warning",
                title: "Fitur Selanjutnya",
                message: "Reset data akan disiapkan di phase berikutnya."
            });
        });
        $("#examLogoutBtn").off("click").on("click", () => {
            EModal.confirm({
                title: "Keluar Sesi",
                message: "Apakah Anda yakin ingin keluar dari sistem?",
                confirmText: "Ya, Keluar",
                onConfirm: () => {
                    const logoutUrl = `${this.state.baseUrl}api/auth.php?action=logout&token=${this.state.token}`;
                    $.get(logoutUrl).always(() => {
                        // Simpan info modul agar setelah login balik lagi ke sini
                        sessionStorage.setItem('eportal_intended_module', 'modules/e-xam-card/');
                        sessionStorage.setItem('eportal_intended_slug', 'e-xam-card');
                        window.location.href = this.state.baseUrl + '#/dashboard';
                    });
                }
            });
        });
    },

    toggleSidebar(force) {
        const shouldOpen = typeof force === "boolean" ? force : !$("#sidebar").hasClass("show");
        $("#sidebar").toggleClass("show", shouldOpen);
        $("#sidebarOverlay").toggleClass("show", shouldOpen);
    },

    resolveRoute() {
        const hash = (location.hash || "#/dashboard").replace("#/", "");
        const route = hash.split("/")[0] || "dashboard";
        this.state.route = ["dashboard", "exams", "settings", "status", "reports", "access"].includes(route) ? route : "dashboard";
        this.renderSidebar();

        const [title, subtitle] = this.routeTitle(this.state.route);
        $("#pageTitle").text(title);
        $("#pageSubTitle").text(subtitle);

        if (this.state.route === "exams") return this.renderExamMaster();
        if (this.state.route === "settings") return this.renderExamSettings();
        if (this.state.route === "status") return this.renderExamStatus();
        if (this.state.route === "reports") return this.renderReports();
        if (this.state.route === "access") return this.renderAccess();
        return this.renderDashboard();
    },

    renderDashboard() {
        const ay = this.state.academicYear || {};
        $("#mainContent").html(`
            <div class="sp-dashboard-grid">
                <div class="sp-card full" style="background: linear-gradient(135deg, #ecf2ff 0%, #f7faff 52%, #f0f9f7 100%); border: 1px solid #d7e2ff;">
                    <div class="sp-card-body" style="display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 20px 24px;">
                        <div>
                            <h3 style="margin: 0; font-size: 1.1rem; color: #102243; font-weight: 800; font-family: var(--font-heading);">Fondasi Modul E-Xam Card Siap Digunakan</h3>
                            <div style="font-size: 0.8rem; color: #50607f; margin-top: 4px;">Semua data tahun ajaran, siswa, dan guru tersinkron dari master E-Portal.</div>
                        </div>
                        <div class="badge badge-info" style="background: #def2ef; color: #196f64; padding: 8px 14px; font-size: 0.75rem;">${this.escapeHtml(ay.tahun_ajaran || "-")} Semester ${this.escapeHtml(ay.semester || "-")}</div>
                    </div>
                </div>
            </div>

            <div class="sp-stats-grid">
                <div class="sp-stat-card">
                    <div class="sp-stat-icon" style="background: #eaf0ff; color: #2e57b8;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    </div>
                    <div class="sp-stat-info">
                        <h4>${this.escapeHtml(ay.tahun_ajaran ? "1" : "0")}</h4>
                        <p>Tahun Ajaran Aktif</p>
                    </div>
                </div>

                <div class="sp-stat-card">
                    <div class="sp-stat-icon" style="background: #eaf6f3; color: #0f766e;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>
                    </div>
                    <div class="sp-stat-info">
                        <h4>4</h4>
                        <p>Langkah Utama</p>
                    </div>
                </div>

                <div class="sp-stat-card">
                    <div class="sp-stat-icon" style="background: #fff3e8; color: #b45309;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M7 8h10"></path><path d="M7 12h10"></path><path d="M7 16h6"></path></svg>
                    </div>
                    <div class="sp-stat-info">
                        <h4>3</h4>
                        <p>Scope Laporan</p>
                    </div>
                </div>

                <div class="sp-stat-card">
                    <div class="sp-stat-icon" style="background: #f1ecff; color: #6941c6;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3H5a2 2 0 0 0-2 2v3"></path><path d="M16 3h3a2 2 0 0 1 2 2v3"></path><path d="M8 21H5a2 2 0 0 1-2-2v-3"></path><path d="M16 21h3a2 2 0 0 0 2-2v-3"></path></svg>
                    </div>
                    <div class="sp-stat-info">
                        <h4>2</h4>
                        <p>Status Akses</p>
                    </div>
                </div>
            </div>

            <div class="sp-dashboard-grid">
                <div class="sp-card full" style="border: 1px solid #0f766e; background: #f0fdfa;">
                    <div class="sp-card-header" style="border-bottom-color: rgba(15, 118, 110, 0.1);">
                        <h3 style="color: #0f766e;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg> Link Login Siswa</h3>
                    </div>
                    <div class="sp-card-body" style="padding: 20px;">
                        <div style="font-size: 0.85rem; color: #115e59; margin-bottom: 12px; font-weight: 500;">Bagikan link ini kepada siswa untuk cek status kartu dan unduh kartu ujian secara mandiri:</div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" id="studentLoginLink" class="form-input" readonly value="${window.location.origin + this.state.baseUrl}modules/e-xam-card/student/login.php" style="background: #fff; flex: 1; border-color: #99f6e4; font-family: monospace; font-size: 0.85rem; color: #0f766e;">
                            <button class="btn btn-primary" id="btnCopyLink" style="background: #0f766e; border-color: #0f766e; border-radius: 12px; white-space: nowrap;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; margin-right: 8px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                Salin Link
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sp-dashboard-grid">
                <div class="sp-card full">
                    <div class="sp-card-header">
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg> Alur Kerja E-Xam Card</h3>
                    </div>
                    <div class="sp-card-body" style="display: grid; gap: 12px;">
                        <div style="border: 1px solid var(--bg-dark); border-radius: 12px; padding: 14px; background: var(--bg-light);">
                            <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">1. Input Master Ujian</div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">Buat nama ujian serta rentang tanggal pelaksanaannya.</div>
                        </div>
                        <div style="border: 1px solid var(--bg-dark); border-radius: 12px; padding: 14px; background: var(--bg-light);">
                            <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">2. Pengaturan Ujian</div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">Pilih kelas peserta, atur format nomor surat, tanggal surat, tanggal tanda tangan, dan kepala sekolah.</div>
                        </div>
                        <div style="border: 1px solid var(--bg-dark); border-radius: 12px; padding: 14px; background: var(--bg-light);">
                            <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">3. Status Ujian Siswa</div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">Isi ruang ujian, username/password ujian, lalu tentukan status OKE atau DITANGGUHKAN.</div>
                        </div>
                        <div style="border: 1px solid var(--bg-dark); border-radius: 12px; padding: 14px; background: var(--bg-light);">
                            <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">4. Laporan dan Unduh Kartu</div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">Unduh kartu per siswa, per kelas, atau semua kelas sesuai kebutuhan panitia ujian.</div>
                        </div>
                    </div>
                </div>
            </div>
        `);

        $("#btnCopyLink").off("click").on("click", () => {
            const link = document.getElementById("studentLoginLink");
            link.select();
            link.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(link.value);
            EModal.toast({ type: "success", title: "Disalin", message: "Link login siswa berhasil disalin ke clipboard." });
        });
    },

    renderExamMaster() {
        $("#mainContent").html(`
            <div class="sp-dashboard-grid" style="grid-template-columns: 4fr 6fr;">
                <div class="sp-card">
                    <div class="sp-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg> Form Ujian</h3>
                        <button class="btn btn-primary btn-sm" id="btnSaveExam" style="border-radius: 8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            Simpan Ujian
                        </button>
                    </div>
                    <div class="sp-card-body">
                        <div class="form-group">
                            <label class="form-label">Nama Ujian</label>
                            <input type="text" class="form-input" id="examName" placeholder="Contoh: Ujian Tengah Semester">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tanggal Mulai Ujian</label>
                            <input type="date" class="form-input" id="examStartDate">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tanggal Selesai Ujian</label>
                            <input type="date" class="form-input" id="examEndDate">
                        </div>
                    </div>
                </div>
                <div class="sp-card">
                    <div class="sp-card-header">
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg> Daftar Ujian</h3>
                    </div>
                    <div class="sp-card-body" style="padding: 0;">
                        <div class="sp-table-wrapper" id="examTableWrap" style="padding: 16px;">Memuat...</div>
                    </div>
                </div>
            </div>
        `);
        $("#btnSaveExam").on("click", () => this.saveExam());
        this.loadExamList();
    },

    loadExamList() {
        this.api("exams.php", "list").done((res) => {
            if (!res.success) {
                $("#examTableWrap").html(`<div>${this.escapeHtml(res.message || "Gagal memuat data ujian")}</div>`);
                return;
            }
            this.state.exams = res.data || [];
            if (!this.state.exams.length) {
                $("#examTableWrap").html(`<div style="padding:12px;color:#8090ad">Belum ada data ujian.</div>`);
                return;
            }
            const rows = this.state.exams.map((row, idx) => `
                <tr>
                    <td>${idx + 1}</td>
                    <td>${this.escapeHtml(row.exam_name)}</td>
                    <td>${this.formatDate(row.exam_start_date)}</td>
                    <td>${this.formatDate(row.exam_end_date)}</td>
                    <td style="text-align:center">${row.total_kelas || 0}</td>
                    <td style="text-align:center">${row.total_siswa || 0}</td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <button class="btn btn-primary btn-sm btn-tpl-exam" data-id="${row.id}" title="Upload Template Kartu">Template</button>
                            <button class="btn btn-outline btn-sm btn-del-exam" data-id="${row.id}">Hapus</button>
                        </div>
                    </td>
                </tr>
            `).join("");
            $("#examTableWrap").html(`
                <table class="sp-table">
                    <thead>
                        <tr><th>No</th><th>Nama Ujian</th><th>Mulai</th><th>Selesai</th><th>Kelas</th><th>Siswa</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
            $(".btn-del-exam").on("click", (event) => this.deleteExam(Number($(event.currentTarget).data("id"))));
            $(".btn-tpl-exam").on("click", (event) => this.openTemplateUploadModal(Number($(event.currentTarget).data("id"))));
        }).fail(() => $("#examTableWrap").html(`<div style="padding:12px;color:#b91c1c">Gagal memuat data ujian.</div>`));
    },

    saveExam() {
        const payload = {
            exam_name: ($("#examName").val() || "").trim(),
            exam_start_date: $("#examStartDate").val(),
            exam_end_date: $("#examEndDate").val()
        };
        if (!payload.exam_name || !payload.exam_start_date || !payload.exam_end_date) {
            EModal.toast({ type: "warning", title: "Data belum lengkap", message: "Lengkapi nama ujian dan tanggal." });
            return;
        }
        this.api("exams.php", "save", { ajax: { method: "POST", data: payload } }).done((res) => {
            if (!res.success) {
                EModal.toast({ type: "error", title: "Gagal", message: res.message || "Tidak dapat menyimpan ujian." });
                return;
            }
            EModal.toast({ type: "success", title: "Berhasil", message: res.message || "Ujian tersimpan." });
            $("#examName,#examStartDate,#examEndDate").val("");
            this.loadExamList();
        }).fail((err) => {
            console.error("Save exam failed:", err);
            let msg = "Terjadi gangguan saat menyimpan data.";
            if (err && err.responseJSON && err.responseJSON.message) {
                msg = err.responseJSON.message;
            }
            EModal.toast({ type: "error", title: "Error", message: msg });
        });
    },

    deleteExam(id) {
        if (!id) return;
        EModal.confirm({
            title: "Hapus Ujian",
            message: "Yakin ingin menghapus ujian ini? Data terkait juga akan terhapus.",
            confirmText: "Hapus",
            cancelText: "Batal",
            onConfirm: () => {
                this.api("exams.php", "delete", { ajax: { method: "POST", data: { id } } }).done((res) => {
                    if (res.success) {
                        EModal.toast({ type: "success", title: "Terhapus", message: res.message });
                        this.loadExamList();
                    } else {
                        EModal.toast({ type: "error", title: "Gagal", message: res.message || "Hapus gagal." });
                    }
                }).fail(() => EModal.toast({ type: "error", title: "Error", message: "Hapus gagal." }));
            }
        });
    },

    renderExamSettings() {
        $("#mainContent").html(`
            <div class="sp-dashboard-grid">
                <div class="sp-card full">
                    <div class="sp-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-.33-1 1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 9 4.6c.36 0 .7-.13 1-.36.28-.22.49-.53.6-.87V3a2 2 0 1 1 4 0v.09c.11.34.32.65.6.87.3.23.64.36 1 .36a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c0 .36.13.7.36 1 .22.28.53.49.87.6H21a2 2 0 1 1 0 4h-.09c-.34.11-.65.32-.87.6-.23.3-.36.64-.36 1z"></path></svg> Pengaturan Ujian</h3>
                        <button class="btn btn-primary btn-sm" id="btnSaveSettings" style="border-radius: 8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            Simpan Pengaturan
                        </button>
                    </div>
                    <div class="sp-card-body">
                        <div class="sp-form-row three">
                            <div>
                                <div class="form-group"><label class="form-label">Nama Ujian</label><select class="form-select" id="settingExamSelect"><option value="">Pilih Ujian</option></select></div>
                                <div class="form-group"><label class="form-label">Nomor Surat Awal</label><input type="text" class="form-input" id="letterManualNo" placeholder="Contoh: 112"></div>
                                <div class="form-group"><label class="form-label">Kode Surat</label><input type="text" class="form-input" id="letterCode" value="I04.1/SMA.WH1"></div>
                                <div class="form-group"><label class="form-label">Preview Nomor Surat</label><input type="text" class="form-input" id="letterPreview" readonly style="background:#f8fafc"></div>
                            </div>
                            <div>
                                <div class="form-group"><label class="form-label">Tanggal Surat</label><input type="date" class="form-input" id="letterDate"></div>
                                <div class="form-group"><label class="form-label">Tanggal Tanda Tangan</label><input type="date" class="form-input" id="signDate"></div>
                                <div class="form-group">
                                    <label class="form-label">Kepala Sekolah</label>
                                    <input type="hidden" id="headmasterSelect" value="">
                                    <input type="hidden" id="headmasterName" value="">
                                    <div class="sp-pj-cs" id="hmDropdown">
                                        <div class="sp-pj-cs-btn" id="hmBtn">
                                            <span id="hmLabel" style="font-size:0.9rem;color:#64748b">Pilih Kepala Sekolah/Guru...</span>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                        </div>
                                        <div class="sp-pj-cs-dd" id="hmMenu">
                                            <div class="sp-pj-cs-search">
                                                <input type="text" id="hmSearch" placeholder="Cari nama guru...">
                                            </div>
                                            <div class="sp-pj-cs-list" id="hmList"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="form-group"><label class="form-label">Pilih Kelas Peserta Ujian</label><div class="xc-checklist" id="classChecklist" style="max-height: 250px; overflow-y: auto; border: 1px solid var(--bg-dark); border-radius: var(--radius-md); padding: 10px; background: var(--bg-light);"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `);
        $("#settingExamSelect").on("change", () => this.loadSelectedExamSettings());
        
        // Custom Searchable Dropdown Events
        $("#hmBtn").on("click", (e) => {
            e.stopPropagation();
            $("#hmMenu").toggle();
            $("#hmBtn").toggleClass("active");
            if ($("#hmMenu").is(":visible")) {
                $("#hmSearch").focus();
            }
        });
        $("#hmSearch").on("input", () => this.filterTeacherOptions());
        $(document).on("click", (e) => {
            if (!$(e.target).closest("#hmDropdown").length) {
                $("#hmMenu").hide();
                $("#hmBtn").removeClass("active");
            }
        });
        
        $("#letterManualNo,#letterCode,#letterDate").on("input change", () => this.previewLetterNumber());
        $("#btnSaveSettings").on("click", () => this.saveExamSettings());
        this.loadSettingsMeta();
    },

    loadSettingsMeta() {
        this.api("settings.php", "meta").done((res) => {
            if (!res.success) return EModal.toast({ type: "error", title: "Gagal", message: res.message || "Meta setting gagal dimuat." });
            this.state.settingMeta = res.data || {};
            const exams = this.state.settingMeta.exams || [];
            $("#settingExamSelect").html(`<option value="">Pilih Ujian</option>${exams.map((exam) => `<option value="${exam.id}">${this.escapeHtml(exam.exam_name)} (${this.formatDate(exam.exam_start_date)} - ${this.formatDate(exam.exam_end_date)})</option>`).join("")}`);
            this.renderTeacherOptions(this.state.settingMeta.teachers || []);
            this.renderClassChecklist(this.state.settingMeta.classes || [], []);
        }).fail(() => EModal.toast({ type: "error", title: "Error", message: "Gagal memuat meta pengaturan." }));
    },

    renderTeacherOptions(teachers) {
        this.state.teachers = teachers || [];
        this.filterTeacherOptions();
    },

    filterTeacherOptions() {
        const q = ($("#hmSearch").val() || "").toLowerCase();
        const filtered = this.state.teachers.filter((teacher) => `${teacher.nama_lengkap || ""} ${teacher.username || ""}`.toLowerCase().includes(q));
        
        let html = "";
        if (filtered.length === 0) {
            html = `<div class="sp-pj-cs-opt none">Tidak ada guru ditemukan.</div>`;
        } else {
            html = filtered.map((t) => `
                <div class="sp-pj-cs-opt" data-id="${t.id}" data-name="${this.escapeHtml(t.nama_lengkap)}">
                    <div class="pj-n">${this.escapeHtml(t.nama_lengkap)}</div>
                    <div class="pj-j">${this.escapeHtml(t.username)}</div>
                </div>
            `).join("");
        }
        
        $("#hmList").html(html);
        
        // Bind selection event
        $(".sp-pj-cs-opt:not(.none)").on("click", function() {
            const id = $(this).data("id");
            const name = $(this).data("name");
            $("#headmasterSelect").val(id);
            $("#headmasterName").val(name);
            $("#hmLabel").text(name).css("color", "#1e293b");
            $("#hmMenu").hide();
            $("#hmBtn").removeClass("active");
        });
    },

    renderClassChecklist(classes, selectedClasses) {
        const selectedSet = new Set(selectedClasses || []);
        let html = '';
        
        if (classes && classes.length > 0) {
            // Add Select All checkbox
            const allSelected = classes.length > 0 && classes.every(c => selectedSet.has(c.kelas));
            html += `
                <label style="display: flex; align-items: center; gap: 8px; padding: 7px 0; font-size: 0.85rem; color: var(--exam-primary); cursor: pointer; border-bottom: 1px dashed var(--exam-border); margin-bottom: 8px; font-weight: 700;">
                    <input type="checkbox" id="chkAllClasses" ${allSelected ? "checked" : ""}>
                    <span>Pilih Semua Kelas</span>
                </label>
            `;

            html += classes.map((row) => `
                <label style="display: flex; align-items: center; gap: 8px; padding: 7px 0; font-size: 0.85rem; color: var(--text-primary); cursor: pointer;">
                    <input type="checkbox" class="chk-class" value="${this.escapeHtml(row.kelas)}" ${selectedSet.has(row.kelas) ? "checked" : ""}>
                    <span>${this.escapeHtml(row.kelas)} (${row.total_siswa || 0} siswa)</span>
                </label>
            `).join("");
        } else {
            html = `<div style="color:var(--text-muted); font-size: 0.8rem;">Belum ada kelas pada tahun ajaran aktif.</div>`;
        }

        $("#classChecklist").html(html);

        // Bind Select All event
        $("#chkAllClasses").on("change", function() {
            $(".chk-class").prop("checked", $(this).prop("checked"));
        });

        // Update Select All state when individual checkbox changes
        $(".chk-class").on("change", function() {
            const total = $(".chk-class").length;
            const checked = $(".chk-class:checked").length;
            $("#chkAllClasses").prop("checked", total === checked);
        });
    },

    loadSelectedExamSettings() {
        const examId = Number($("#settingExamSelect").val());
        if (!examId) return;
        this.api("settings.php", "get", { query: { exam_id: examId } }).done((res) => {
            if (!res.success) return EModal.toast({ type: "error", title: "Gagal", message: res.message || "Data setting tidak ditemukan." });
            const data = res.data || {};
            const s = data.setting || {};
            $("#letterManualNo").val(s.letter_manual_no || "");
            $("#letterCode").val(s.letter_code || "I04.1/SMA.WH1");
            $("#letterDate").val(s.letter_date || "");
            $("#signDate").val(s.sign_date || "");
            
            // Set Custom Dropdown Value
            $("#headmasterSelect").val(s.headmaster_user_id || "");
            $("#headmasterName").val(s.headmaster_name || "");
            if (s.headmaster_name) {
                $("#hmLabel").text(s.headmaster_name).css("color", "#1e293b");
            } else {
                $("#hmLabel").text("Pilih Kepala Sekolah/Guru...").css("color", "#64748b");
            }
            
            this.renderClassChecklist(this.state.settingMeta.classes || [], data.selected_classes || []);
            this.previewLetterNumber();
        }).fail(() => EModal.toast({ type: "error", title: "Error", message: "Gagal memuat detail setting ujian." }));
    },

    previewLetterNumber() {
        const no = ($("#letterManualNo").val() || "").trim();
        const code = ($("#letterCode").val() || "").trim();
        const date = $("#letterDate").val();
        if (!no || !code || !date) return $("#letterPreview").val("");
        const dt = new Date(date);
        const roman = ["I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
        $("#letterPreview").val(`${no}/${code}/${roman[dt.getMonth()]}/${dt.getFullYear()}`);
    },

    saveExamSettings() {
        const examId = Number($("#settingExamSelect").val());
        if (!examId) return EModal.toast({ type: "warning", title: "Pilih Ujian", message: "Pilih nama ujian sebelum menyimpan." });
        const classes = [];
        $(".chk-class:checked").each((_, el) => classes.push($(el).val()));
        const payload = {
            exam_id: examId,
            classes,
            letter_manual_no: ($("#letterManualNo").val() || "").trim(),
            letter_code: ($("#letterCode").val() || "").trim(),
            letter_date: $("#letterDate").val(),
            sign_date: $("#signDate").val(),
            headmaster_user_id: Number($("#headmasterSelect").val() || 0),
            headmaster_name: $("#headmasterName").val() || ""
        };
        const $btn = $("#btnSaveSettings");
        const originalHtml = $btn.html();
        $btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-right:6px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg><style>@keyframes spin { 100% { transform: rotate(360deg); } }</style> Menyimpan...').prop("disabled", true);

        this.api("settings.php", "save", { ajax: { method: "POST", data: payload } }).done((res) => {
            $btn.html(originalHtml).prop("disabled", false);
            if (!res.success) return EModal.toast({ type: "error", title: "Gagal", message: res.message || "Tidak dapat simpan pengaturan." });
            EModal.toast({ type: "success", title: "Berhasil", message: res.message || "Pengaturan tersimpan." });
            if (res.data?.letter_number_preview) $("#letterPreview").val(res.data.letter_number_preview);
        }).fail(() => {
            $btn.html(originalHtml).prop("disabled", false);
            EModal.toast({ type: "error", title: "Error", message: "Simpan pengaturan gagal." });
        });
    },

    renderExamStatus() {
        $("#mainContent").html(`
            <div class="sp-dashboard-grid">
                <div class="sp-card full">
                    <div class="sp-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 11V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6"></path><path d="M8 7h6"></path><path d="M8 11h4"></path><circle cx="17" cy="17" r="4"></circle><path d="m21 21-1.5-1.5"></path></svg> ${this.state.user.is_teacher ? "Akun Siswa Wali Kelas" : "Status Kartu per Kelas"}</h3>
                        <div style="display: flex; gap: 8px;">
                            ${this.state.user.is_teacher ? '' : `
                            <button class="btn btn-primary btn-sm" id="btnReloadStatus" style="border-radius: 8px;">Muat Data</button>
                            <button class="btn btn-primary btn-sm" id="btnImportExamAccount" style="border-radius: 8px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                Import User & Pass
                            </button>
                            `}
                        </div>
                    </div>
                    <div class="sp-card-body">
                        <div style="display: grid; grid-template-columns: repeat(${this.state.user.is_teacher ? '3' : '4'}, 1fr); gap: 12px; align-items: end;">
                            <div class="form-group" style="margin-bottom:0;"><label class="form-label">Nama Ujian</label><select class="form-select" id="statusExamSelect"><option value="">Pilih Ujian</option></select></div>
                            <div class="form-group" style="margin-bottom:0;"><label class="form-label">Pilih Kelas</label><select class="form-select" id="statusClassSelect"><option value="">Pilih Kelas</option></select></div>
                            <div class="form-group" style="margin-bottom:0;"><label class="form-label">Cari Nama Siswa</label><input type="text" class="form-input" id="statusSearchName" placeholder="Ketik nama untuk filter..."></div>
                            ${this.state.user.is_teacher ? '' : `
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">&nbsp;</label>
                                <select class="form-select" id="bulkStatusSelect">
                                    <option value="">-- Ubah Status Massal --</option>
                                    <option value="OKE">Set Semua OKE</option>
                                    <option value="DITANGGUHKAN">Set Semua DITANGGUHKAN</option>
                                </select>
                            </div>
                            `}
                        </div>
                        <div id="statusTableWrap" class="sp-table-wrapper" style="margin-top: 14px; border: 1px solid var(--bg-dark); border-radius: var(--radius-md);"></div>
                    </div>
                </div>
            </div>
        `);
        $("#statusExamSelect").on("change", () => this.loadStatusClasses());
        $("#statusClassSelect").on("change", () => this.loadStatusStudents());
        $("#btnReloadStatus").on("click", () => this.loadStatusStudents());
        $("#btnImportExamAccount").on("click", (e) => {
            e.preventDefault();
            this.showImportAccountModal();
        });
        
        // Live search name filter
        $("#statusSearchName").on("input", function() {
            const q = $(this).val().toLowerCase();
            $("#statusTableWrap tbody tr").each(function() {
                const name = $(this).find("td:nth-child(4)").text().toLowerCase();
                $(this).toggle(name.includes(q));
            });
        });

        $("#bulkStatusSelect").on("change", (e) => {
            const status = $(e.currentTarget).val();
            if (!status) return;
            const examId = Number($("#statusExamSelect").val());
            const kelas = $("#statusClassSelect").val();
            if (!examId || !kelas) {
                $(e.currentTarget).val("");
                return EModal.toast({ type: "warning", title: "Pilih data", message: "Pilih ujian dan kelas terlebih dahulu." });
            }
            EModal.confirm({
                title: "Ubah Status Massal",
                message: `Anda yakin mengubah semua status siswa ${kelas === "SEMUA" ? "di semua kelas" : "di kelas " + this.escapeHtml(kelas)} menjadi ${status}?`,
                confirmText: "Ubah Status",
                cancelText: "Batal",
                onConfirm: () => {
                    this.api("status.php", "bulk-status", { ajax: { method: "POST", data: { exam_id: examId, kelas, status } } }).done((res) => {
                        $("#bulkStatusSelect").val("");
                        if (!res.success) return EModal.toast({ type: "error", title: "Gagal", message: res.message || "Gagal mengubah status." });
                        EModal.toast({ type: "success", title: "Berhasil", message: "Status massal berhasil diubah." });
                        this.loadStatusStudents();
                    }).fail(() => {
                        $("#bulkStatusSelect").val("");
                        EModal.toast({ type: "error", title: "Error", message: "Gagal mengubah status." });
                    });
                },
                onCancel: () => $("#bulkStatusSelect").val("")
            });
        });
        this.loadExamOptionsTo("#statusExamSelect");
    },

    loadExamOptionsTo(selector) {
        this.api("exams.php", "list").done((res) => {
            if (!res.success) return;
            const exams = res.data || [];
            $(selector).html(`<option value="">Pilih Ujian</option>${exams.map((exam) => `<option value="${exam.id}">${this.escapeHtml(exam.exam_name)}</option>`).join("")}`);
        });
    },

    loadStatusClasses() {
        const examId = Number($("#statusExamSelect").val());
        $("#statusClassSelect").html(`<option value="">Pilih Kelas</option>`);
        $("#statusTableWrap").html("");
        if (!examId) return;
        this.api("status.php", "classes", { query: { exam_id: examId } }).done((res) => {
            if (!res.success) return EModal.toast({ type: "error", title: "Gagal", message: res.message || "Gagal memuat kelas." });
            this.state.statusClasses = res.data || [];
            
            const isTeacher = this.state.user.is_teacher;
            const managedClass = this.state.user.managed_class;
            
            let options = `<option value="">Pilih Kelas</option>`;
            if (!isTeacher) {
                options += `<option value="SEMUA">-- SEMUA KELAS --</option>`;
            }
            
            options += this.state.statusClasses.map((row) => `<option value="${this.escapeHtml(row.kelas)}">${this.escapeHtml(row.kelas)} (${row.total_siswa || 0} siswa)</option>`).join("");
            
            $("#statusClassSelect").html(options);
            
            if (isTeacher && managedClass) {
                $("#statusClassSelect").val(managedClass);
                this.loadStatusStudents();
            }
        });
    },

    loadStatusStudents() {
        const examId = Number($("#statusExamSelect").val());
        const kelas = $("#statusClassSelect").val();
        if (!examId || !kelas) return EModal.toast({ type: "warning", title: "Lengkapi pilihan", message: "Pilih ujian dan kelas." });
        $("#statusTableWrap").html(`<div style="padding:12px">Memuat data siswa...</div>`);
        this.api("status.php", "list", { query: { exam_id: examId, kelas } }).done((res) => {
            if (!res.success) return $("#statusTableWrap").html(`<div style="padding:12px;color:#b91c1c">${this.escapeHtml(res.message || "Gagal memuat data")}</div>`);
            const items = res.data?.items || [];
            if (!items.length) return $("#statusTableWrap").html(`<div style="padding:12px;color:#8090ad">Data siswa belum tersedia.</div>`);
            
            const isTeacher = this.state.user.is_teacher;
            const rows = items.map((row, idx) => `
                <tr data-row-id="${row.exam_student_id}">
                    <td>${idx + 1}</td>
                    <td>${this.escapeHtml(row.nis)}</td>
                    <td>${this.escapeHtml(row.nisn)}</td>
                    <td>${this.escapeHtml(row.nama)}</td>
                    ${isTeacher ? '' : `<td><input type="text" class="form-input st-ruang" value="${this.escapeHtml(row.ruang_ujian || "")}" style="width:120px; padding: 6px 10px;"></td>`}
                    ${isTeacher ? '' : `<td><input type="text" class="form-input st-username" value="${this.escapeHtml(row.username || "")}" style="width:120px; padding: 6px 10px;"></td>`}
                    ${isTeacher ? '' : `<td><input type="text" class="form-input st-password" value="${this.escapeHtml(row.password_raw || "")}" style="width:120px; padding: 6px 10px;"></td>`}
                    <td>
                        <select class="form-select st-status" style="padding: 6px 10px; width:150px; background-position: right 8px center;" ${isTeacher ? 'disabled' : ''}>
                            <option value="OKE" ${row.status === "OKE" ? "selected" : ""}>OKE</option>
                            <option value="DITANGGUHKAN" ${row.status === "DITANGGUHKAN" ? "selected" : ""}>DITANGGUHKAN</option>
                        </select>
                        <div class="st-note-wrap" style="margin-top:6px;${row.status === "DITANGGUHKAN" ? "" : "display:none"}">
                            <textarea class="form-input st-note" rows="2" style="width:150px; padding: 6px 10px;" ${isTeacher ? 'readonly' : ''}>${this.escapeHtml(row.suspension_note || "Silakan hubungi Wali Kelas / Waka. Kesiswaan")}</textarea>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            ${isTeacher ? '' : '<button class="btn btn-primary btn-sm btn-save-status">Simpan</button>'}
                            <button class="btn btn-outline btn-sm btn-print-single" data-student-id="${row.student_id}" title="Cetak Kartu">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join("");
            $("#statusTableWrap").html(`
                <table class="sp-table">
                    <thead><tr><th>No</th><th>NIS</th><th>NISN</th><th>Nama Siswa</th>${isTeacher ? '' : '<th>Ruang</th><th>User</th><th>Pass</th>'}<th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
            $("#statusTableWrap .st-status").on("change", function onChangeStatus() {
                const wrap = $(this).closest("td").find(".st-note-wrap");
                if ($(this).val() === "DITANGGUHKAN") wrap.show(); else wrap.hide();
            });
            $("#statusTableWrap .btn-save-status").on("click", (event) => this.saveStatusRow($(event.currentTarget).closest("tr")));
            $("#statusTableWrap .btn-print-single").on("click", (event) => {
                const studentId = $(event.currentTarget).data("student-id");
                const examId = Number($("#statusExamSelect").val());
                this.downloadCards("student", event.currentTarget, { examId, studentId });
            });
            // Trigger search filter in case there's text in the search box already
            $("#statusSearchName").trigger("input");
        }).fail(() => $("#statusTableWrap").html(`<div style="padding:12px;color:#b91c1c">Gagal memuat data status.</div>`));
    },

    saveStatusRow($row) {
        const payload = {
            exam_student_id: Number($row.data("row-id")),
            ruang_ujian: ($row.find(".st-ruang").val() || "").trim(),
            username: ($row.find(".st-username").val() || "").trim(),
            password: ($row.find(".st-password").val() || "").trim(),
            status: $row.find(".st-status").val(),
            suspension_note: ($row.find(".st-note").val() || "").trim()
        };
        this.api("status.php", "save", { ajax: { method: "POST", data: payload } }).done((res) => {
            if (!res.success) return EModal.toast({ type: "error", title: "Gagal", message: res.message || "Simpan status gagal." });
            EModal.toast({ type: "success", title: "Tersimpan", message: "Status siswa diperbarui." });
        }).fail(() => EModal.toast({ type: "error", title: "Error", message: "Simpan status gagal." }));
    },

    bulkGenerateStatus() {
        const examId = Number($("#statusExamSelect").val());
        const kelas = $("#statusClassSelect").val();
        if (!examId || !kelas) return EModal.toast({ type: "warning", title: "Pilih data", message: "Pilih ujian dan kelas." });
        EModal.confirm({
            title: "Generate Ulang User/Pass",
            message: `Aksi ini mengisi ulang username dan password default untuk ${kelas === "SEMUA" ? "semua kelas" : "kelas " + this.escapeHtml(kelas)}.`,
            confirmText: "Generate",
            cancelText: "Batal",
            onConfirm: () => {
                this.api("status.php", "bulk-generate", { ajax: { method: "POST", data: { exam_id: examId, kelas } } }).done((res) => {
                    if (!res.success) return EModal.toast({ type: "error", title: "Gagal", message: res.message || "Generate gagal." });
                    EModal.toast({ type: "success", title: "Berhasil", message: res.message || "Generate berhasil." });
                    this.loadStatusStudents();
                }).fail(() => EModal.toast({ type: "error", title: "Error", message: "Generate gagal." }));
            }
        });
    },

    renderReports() {
        $("#mainContent").html(`
            <div class="sp-dashboard-grid">
                <div class="sp-card full">
                    <div class="sp-card-header">
                        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"></path><path d="M7 15l4-4 3 3 5-6"></path></svg> Rekapitulasi Status Ujian & Unduh Kartu</h3>
                    </div>
                    <div class="sp-card-body">
                        <div class="sp-form-row three">
                            <div class="form-group"><label class="form-label">Nama Ujian</label><select class="form-select" id="reportExamSelect"><option value="">Pilih Ujian</option></select></div>
                            <div class="form-group"><label class="form-label">Pilih Kelas</label><select class="form-select" id="reportClassSelect"><option value="">Pilih Kelas</option></select></div>
                            <div class="form-group"><label class="form-label">Pilih Siswa</label><select class="form-select" id="reportStudentSelect"><option value="">Pilih Siswa</option></select></div>
                        </div>
                        <div class="sp-toolbar" style="margin-bottom:20px;">
                            <button class="btn btn-outline" id="btnLoadRecap">Muat Rekap</button>
                            <button class="btn btn-primary" id="btnDownloadStudent">Download Per Siswa</button>
                            <button class="btn btn-primary" id="btnDownloadClass">Download Per Kelas</button>
                            <button class="btn btn-danger" id="btnDownloadAll">Download Semua Kelas</button>
                        </div>
                        <div id="recapWrap" class="sp-table-wrapper" style="border: 1px solid var(--bg-dark); border-radius: var(--radius-md);"></div>
                    </div>
                </div>
            </div>
        `);
        this.loadExamOptionsTo("#reportExamSelect");
        $("#reportExamSelect").on("change", () => this.loadReportClassStudentOptions());
        $("#btnLoadRecap").on("click", () => this.loadRecap());
        $("#btnDownloadAll").on("click", (e) => this.downloadCards("all", e.currentTarget));
        $("#btnDownloadClass").on("click", (e) => this.downloadCards("class", e.currentTarget));
        $("#btnDownloadStudent").on("click", (e) => this.downloadCards("student", e.currentTarget));
    },

    loadReportClassStudentOptions() {
        const examId = Number($("#reportExamSelect").val());
        $("#reportClassSelect").html(`<option value="">Pilih Kelas</option>`);
        $("#reportStudentSelect").html(`<option value="">Pilih Siswa</option>`);
        if (!examId) return;

        this.api("status.php", "classes", { query: { exam_id: examId } }).done((res) => {
            if (!res.success) return;
            const validClasses = res.data || [];
            $("#reportClassSelect").html(`<option value="">Pilih Kelas</option>${validClasses.map((row) => `<option value="${this.escapeHtml(row.kelas)}">${this.escapeHtml(row.kelas)}</option>`).join("")}`);

            if (!validClasses.length) return;
            const requests = validClasses.map((row) => this.api("status.php", "list", { query: { exam_id: examId, kelas: row.kelas } }));
            $.when(...requests).done((...args) => {
                const responses = requests.length === 1 ? [args] : args;
                const students = [];
                const seenIds = new Set();
                
                responses.forEach((arg) => {
                    const payload = requests.length === 1 ? arg[0] : arg[0];
                    if (payload?.success && payload?.data?.items) {
                        payload.data.items.forEach((item) => {
                            if (!seenIds.has(item.student_id)) {
                                seenIds.add(item.student_id);
                                students.push(item);
                            }
                        });
                    }
                });
                
                students.sort((a, b) => `${a.kelas}-${a.nama}`.localeCompare(`${b.kelas}-${b.nama}`));
                $("#reportStudentSelect").html(`<option value="">Pilih Siswa</option>${students.map((student) => `<option value="${student.student_id}">${this.escapeHtml(student.kelas)} - ${this.escapeHtml(student.nama)} (${this.escapeHtml(student.nis)})</option>`).join("")}`);
            });
        });
    },

    loadRecap() {
        const examId = Number($("#reportExamSelect").val());
        if (!examId) return EModal.toast({ type: "warning", title: "Pilih Ujian", message: "Pilih ujian dulu." });
        $("#recapWrap").html(`<div style="padding:12px">Memuat rekap...</div>`);
        this.api("reports.php", "recap", { query: { exam_id: examId } }).done((res) => {
            if (!res.success) return $("#recapWrap").html(`<div style="padding:12px;color:#b91c1c">${this.escapeHtml(res.message || "Gagal memuat rekap")}</div>`);
            const items = res.data?.items || [];
            if (!items.length) return $("#recapWrap").html(`<div style="padding:12px;color:#8090ad">Belum ada data rekap.</div>`);
            const rows = items.map((row, idx) => `
                <tr>
                    <td>${idx + 1}</td>
                    <td>${this.escapeHtml(row.kelas)}</td>
                    <td>${row.total_siswa || 0}</td>
                    <td><span class="badge badge-success">${row.total_oke || 0} OKE</span></td>
                    <td><span class="badge badge-danger">${row.total_ditangguhkan || 0} DITANGGUHKAN</span></td>
                </tr>
            `).join("");
            $("#recapWrap").html(`
                <table class="sp-table">
                    <thead><tr><th>No</th><th>Kelas</th><th>Total Siswa</th><th>Status OKE</th><th>Status DITANGGUHKAN</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `);
        }).fail(() => $("#recapWrap").html(`<div style="padding:12px;color:#b91c1c">Gagal memuat data rekap.</div>`));
    },

    downloadCards(scope, btn = null, customParams = null) {
        const examId = customParams?.examId || Number($("#reportExamSelect").val());
        if (!examId) return EModal.toast({ type: "warning", title: "Pilih Ujian", message: "Pilih ujian dulu." });
        
        const params = new URLSearchParams({ action: "download-card", exam_id: examId, scope });
        if (scope === "class") {
            const kelas = $("#reportClassSelect").val();
            if (!kelas) return EModal.toast({ type: "warning", title: "Pilih Kelas", message: "Pilih kelas untuk download." });
            params.append("kelas", kelas);
        }
        if (scope === "student") {
            const studentId = customParams?.studentId || $("#reportStudentSelect").val();
            if (!studentId) return EModal.toast({ type: "warning", title: "Pilih Siswa", message: "Pilih siswa untuk download." });
            params.append("student_id", studentId);
        }
        params.append("token", this.state.token);

        // Loading state
        const $btn = $(btn);
        const originalHtml = $btn.html();
        if (btn) {
            $btn.prop("disabled", true).html(`
                <svg class="fa-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-right:8px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                Mohon tunggu...
            `);
        }

        fetch(`${this.state.apiUrl}reports.php?${params.toString()}`)
            .then(async (response) => {
                if (!response.ok) {
                    const txt = await response.text();
                    throw new Error(txt || "Download gagal.");
                }
                const filename = response.headers.get("Content-Disposition")?.split("filename=")[1]?.replace(/"/g, "") || "Kartu-Ujian.pdf";
                return response.blob().then((blob) => ({ blob, filename }));
            })
            .then(({ blob, filename }) => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.style.display = "none";
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                EModal.toast({ type: "success", title: "Selesai", message: "Kartu ujian berhasil diunduh." });
            })
            .catch((err) => {
                console.error("Download error:", err);
                EModal.toast({ type: "error", title: "Error", message: err.message || "Gagal mengunduh kartu ujian." });
            })
            .finally(() => {
                if (btn) $btn.prop("disabled", false).html(originalHtml);
            });
    },

    renderAccess() {
        if (!['superadmin', 'user'].includes(this.state.user.role)) {
            $("#mainContent").html('<div class="sp-empty"><h3>Akses Ditolak</h3><p>Hanya admin yang dapat mengatur akses guru.</p></div>');
            return;
        }
        $("#mainContent").html(`
            <div class="sp-dashboard-grid">
                <div class="sp-card full">
                    <div class="sp-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin:0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> Pengaturan Akses Guru</h3>
                            <div class="grad-muted" style="margin-top:4px;">Hanya guru yang ditambahkan di sini yang bisa akses modul E-Xam Card.</div>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="ExamCard.openAccessForm()" style="border-radius:8px;">Tambah Akses</button>
                    </div>
                    <div class="sp-card-body">
                        <div class="sp-table-wrapper" id="accessTable" style="border: 1px solid var(--bg-dark); border-radius: var(--radius-md);"><div class="skeleton" style="height:260px"></div></div>
                    </div>
                </div>
            </div>
        `);
        this.loadAccess();
    },

    loadAccess() {
        return this.api('access.php', 'list').done(res => {
            this.state.accessData = res.data || { accesses: [], teachers: [], classes: [] };
            this.renderAccessTable();
        }).fail(xhr => {
            EModal.toast({ type: 'danger', title: 'Gagal Memuat Akses', message: xhr.responseJSON?.message || 'Terjadi kesalahan' });
        });
    },

    renderAccessTable() {
        const accesses = this.state.accessData.accesses || [];
        if (!accesses.length) {
            $('#accessTable').html('<div class="sp-empty"><h3>Belum Ada Akses Guru</h3><p>Klik "Tambah Akses", cari guru, pilih kelas, lalu simpan.</p></div>');
            return;
        }

        const rows = accesses.map(t => `
            <tr>
                <td>
                    <strong>${this.escapeHtml(t.nama_lengkap)}</strong>
                    <div class="grad-muted" style="color:var(--text-muted); font-size:0.8rem;">${this.escapeHtml(t.jabatan || '-')}</div>
                </td>
                <td><code class="sp-code" style="background:var(--bg-light); padding:2px 6px; border-radius:4px;">${this.escapeHtml(t.username || '-')}</code></td>
                <td>${t.kelas ? `<span class="badge badge-success" style="background:#eaf6f3; color:#0f766e;">${this.escapeHtml(t.kelas)}</span>` : '<span class="badge badge-warning">Belum diatur</span>'}</td>
                <td><span class="badge ${t.access_status == 1 ? 'badge-primary' : 'badge-danger'}" style="${t.access_status == 1 ? 'background:#eaf0ff; color:#2e57b8;' : 'background:#ffebee; color:#c62828;'}">${t.access_status == 1 ? 'Aktif' : (t.access_id ? 'Nonaktif' : 'Belum Ada')}</span></td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn btn-outline btn-sm" title="Atur Akses" onclick="ExamCard.openAccessForm(${t.user_id})" style="padding:4px 8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
                        ${t.access_id ? `<button class="btn btn-outline btn-sm" title="Hapus Akses" onclick="ExamCard.deleteAccess(${t.user_id}, this.dataset.name)" data-name="${this.escapeAttr(t.nama_lengkap)}" style="padding:4px 8px; color:#e53e3e; border-color:#fed7d7;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>` : ''}
                    </div>
                </td>
            </tr>
        `).join('');

        $('#accessTable').html(`<table class="sp-table"><thead><tr><th>Guru</th><th>Username</th><th>Kelas Wali</th><th>Status</th><th>Aksi</th></tr></thead><tbody>${rows}</tbody></table>`);
    },

    openAccessForm(userId = null) {
        const accesses = this.state.accessData.accesses || [];
        const teachers = this.state.accessData.teachers || [];
        const classes = this.state.accessData.classes || [];
        const existing = accesses.find(t => String(t.user_id) === String(userId)) || {};
        const usedTeacherIds = new Set(accesses.map(t => String(t.user_id)));
        const teacherChoices = userId
            ? teachers.filter(t => String(t.id) === String(userId))
            : teachers.filter(t => !usedTeacherIds.has(String(t.id)));
        const classOptions = classes.map(k => `<option value="${this.escapeAttr(k.kelas)}">${this.escapeHtml(k.kelas)}</option>`).join('');
        if (!teacherChoices.length && !userId) {
            EModal.toast({ type: 'info', title: 'Semua guru sudah dipilih', message: 'Tidak ada guru tersisa untuk ditambahkan.' });
            return;
        }
        const teacherOptionItems = teacherChoices.map(t => `
            <div class="sp-cs-option grad-access-teacher-option"
                 data-user-id="${t.id}"
                 data-name="${this.escapeAttr(t.nama_lengkap || '')}"
                 data-username="${this.escapeAttr(t.username || '')}"
                 data-jabatan="${this.escapeAttr(t.jabatan || '')}"
                 style="padding: 10px; cursor: pointer; border-bottom: 1px solid #e2e8f0;">
                <div style="font-weight: 600; color: #1e293b;">${this.escapeHtml(t.nama_lengkap || '-')}</div>
                <div style="font-size: 0.8rem; color: #64748b;">${this.escapeHtml(t.username || '-')} | ${this.escapeHtml(t.jabatan || '-')}</div>
            </div>
        `).join('');

        EModal.form({
            title: userId ? 'Edit Akses Guru' : 'Tambah Akses Guru',
            size: 'md',
            form: `
                <style>
                    .grad-access-cs-container { position:relative; user-select:none; }
                    .grad-access-cs-btn {
                        cursor:pointer; display:flex; justify-content:space-between; align-items:center;
                        background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:10px 15px; height:48px; transition:all .2s;
                    }
                    .grad-access-cs-btn:hover { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
                    .grad-access-cs-btn.active { border-color:#3b82f6; }
                    .grad-access-cs-btn.disabled { opacity:.7; cursor:not-allowed; background:#f8fafc; }
                    .grad-access-cs-dropdown {
                        display:none; position:absolute; top:calc(100% + 5px); left:0; right:0;
                        background:#fff; border:1px solid #cbd5e1; border-radius:10px;
                        box-shadow:0 10px 25px rgba(0,0,0,.1); z-index:9999; overflow:hidden;
                    }
                    .grad-access-cs-search { padding:12px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
                    .grad-access-cs-search input {
                        width:100%; padding:10px 14px; height:42px; border-radius:8px; border:1px solid #cbd5e1; outline:none;
                    }
                    .grad-access-cs-search input:focus { border-color:#3b82f6; }
                    .grad-access-empty {
                        display:none; padding:12px 15px; font-size:.85rem; color:#64748b; text-align:center; border-top:1px solid #f1f5f9;
                    }
                    .grad-access-teacher-option:hover { background: #f8fafc; }
                </style>
                <input type="hidden" id="accessUser" value="">
                <div class="form-group">
                    <label class="form-label">Guru</label>
                    <div class="grad-access-cs-container" id="accessTeacherSelectContainer">
                        <div class="grad-access-cs-btn ${userId ? 'disabled' : ''}" id="accessTeacherSelectBtn">
                            <span id="accessTeacherSelectedText" style="color:#64748b; font-size:.95rem;">-- Pilih Guru --</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <div class="grad-access-cs-dropdown" id="accessTeacherDropdown">
                            <div class="grad-access-cs-search">
                                <input type="text" id="accessTeacherSearch" placeholder="Ketik nama guru..." autocomplete="off">
                            </div>
                            <div id="accessTeacherOptions" style="max-height:250px; overflow-y:auto; padding:5px 0;">
                                ${teacherOptionItems || '<div class="grad-access-empty" style="display:block;">Data guru tidak tersedia.</div>'}
                            </div>
                            <div class="grad-access-empty" id="accessTeacherEmpty">Tidak ada guru yang cocok.</div>
                        </div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Kelas Wali</label>
                        <select class="form-select" id="accessClass">
                            <option value="">Pilih kelas</option>
                            ${classOptions}
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="accessStatus">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div style="font-size: 0.85rem; color: #64748b; padding: 10px; background: #f8fafc; border-radius: 8px;">Guru yang dipilih akan diberi akses ke Status Ujian dan Laporan sesuai kelas wali.</div>
            `,
            confirmText: 'Simpan Akses',
            onOpen: () => {
                const setSelectedTeacher = (id) => {
                    const selected = teacherChoices.find(t => String(t.id) === String(id));
                    if (selected) {
                        $('#accessUser').val(String(selected.id));
                        $('#accessTeacherSelectedText').text(`${selected.nama_lengkap} - ${selected.username || '-'}`).css('color', '#1e293b');
                        return;
                    }
                    $('#accessUser').val('');
                    $('#accessTeacherSelectedText').text('-- Pilih Guru --').css('color', '#64748b');
                };

                const filterTeacherOptions = () => {
                    const term = String($('#accessTeacherSearch').val() || '').trim().toLowerCase();
                    let visible = 0;
                    $('#accessTeacherOptions .grad-access-teacher-option').each(function() {
                        const name = String($(this).data('name') || '').toLowerCase();
                        const username = String($(this).data('username') || '').toLowerCase();
                        const jabatan = String($(this).data('jabatan') || '').toLowerCase();
                        const show = !term || name.includes(term) || username.includes(term) || jabatan.includes(term);
                        $(this).toggle(show);
                        if (show) visible++;
                    });
                    $('#accessTeacherEmpty').toggle(visible === 0);
                };

                setSelectedTeacher(existing.user_id || '');
                $('#accessClass').val(existing.kelas || '');
                $('#accessStatus').val(existing.access_status == 0 ? '0' : '1');
                $(document).off('click.gradAccessTeacher');

                if (!userId) {
                    $('#accessTeacherSelectBtn').on('click', (e) => {
                        e.stopPropagation();
                        $('#accessTeacherSelectBtn').toggleClass('active');
                        $('#accessTeacherDropdown').toggle();
                        if ($('#accessTeacherDropdown').is(':visible')) {
                            $('#accessTeacherSearch').val('');
                            filterTeacherOptions();
                            $('#accessTeacherSearch').trigger('focus');
                        }
                    });

                    $('#accessTeacherSearch').on('input', filterTeacherOptions);

                    $('#accessTeacherOptions').on('click', '.grad-access-teacher-option', function() {
                        const selectedUserId = $(this).data('user-id');
                        const selectedTeacher = teacherChoices.find(t => String(t.id) === String(selectedUserId)) || {};
                        const selectedName = selectedTeacher.nama_lengkap || '';
                        const selectedUsername = selectedTeacher.username || '-';
                        $('#accessUser').val(String(selectedUserId));
                        $('#accessTeacherSelectedText').text(`${selectedName} - ${selectedUsername}`).css('color', '#1e293b');
                        $('#accessTeacherDropdown').hide();
                        $('#accessTeacherSelectBtn').removeClass('active');

                        const selectedAccess = accesses.find(t => String(t.user_id) === String(selectedUserId)) || {};
                        if (selectedAccess.kelas) {
                            $('#accessClass').val(selectedAccess.kelas);
                        }
                    });
                }

                $(document).on('click.gradAccessTeacher', (e) => {
                    if (!$(e.target).closest('#accessTeacherSelectContainer').length) {
                        $('#accessTeacherDropdown').hide();
                        $('#accessTeacherSelectBtn').removeClass('active');
                    }
                });
            },
            onConfirm: () => {
                const data = {
                    user_id: $('#accessUser').val(),
                    kelas: $('#accessClass').val(),
                    status: $('#accessStatus').val()
                };
                if (!data.user_id || !data.kelas) {
                    EModal.toast({ type: 'warning', title: 'Lengkapi data', message: 'Guru dan kelas wajib diisi.' });
                    return false;
                }
                this.api('access.php', 'save', { ajax: { method: 'POST', data } }).done(() => {
                    EModal.closeAll();
                    EModal.toast({ type: 'success', title: 'Berhasil', message: 'Akses guru disimpan.' });
                    this.loadAccess();
                }).fail(xhr => {
                    EModal.toast({ type: 'danger', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menyimpan akses' });
                });
                return false;
            }
        });
    },

    deleteAccess(userId, name) {
        EModal.confirm({
            title: 'Hapus Akses Guru',
            message: `Yakin hapus akses <strong>${this.escapeHtml(name)}</strong>?`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                this.api('access.php', 'delete', { ajax: { method: 'POST', data: { user_id: userId } } }).done(() => {
                    EModal.toast({ type: 'success', title: 'Dihapus', message: 'Akses guru dihapus.' });
                    this.loadAccess();
                }).fail(xhr => {
                    EModal.toast({ type: 'danger', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal menghapus akses' });
                });
            }
        });
    },

    openTemplateUploadModal(examId) {
        const exam = this.state.exams.find(e => String(e.id) === String(examId));
        if (!exam) return;

        const currentTemplate = exam.card_template ? `${this.state.baseUrl}${exam.card_template}` : '';

        EModal.form({
            title: 'Upload Template Kartu',
            size: 'md',
            form: `
                <div class="form-group">
                    <label class="form-label">Pilih File Template (JPG/PNG)</label>
                    <div style="margin-bottom: 12px; font-size: 0.75rem; color: #64748b; line-height: 1.6;">
                        • Ukuran standar kartu: 10 x 8 cm (Sekitar 378 x 302 px).<br>
                        • <strong>Ukuran KOP Atas: ± 2 cm</strong>.<br>
                        • Pastikan area KOP di file gambar Anda sesuai agar tidak tertimpa teks KOP sistem.
                    </div>
                    <input type="file" id="tplFileInput" class="form-input" accept="image/jpeg,image/png">
                </div>
                <div id="tplPreviewContainer" style="margin-top: 16px; border: 2px dashed #e2e8f0; border-radius: 8px; padding: 10px; min-height: 100px; display: flex; align-items: center; justify-content: center; background: #f8fafc;">
                    ${currentTemplate ? `<img src="${currentTemplate}" style="max-width: 100%; max-height: 200px; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">` : '<span style="color: #94a3b8; font-size: 0.85rem;">Preview template akan muncul di sini</span>'}
                </div>
            `,
            onConfirm: () => {
                const fileInput = document.getElementById('tplFileInput');
                const file = fileInput.files[0];
                if (!file && !currentTemplate) {
                    EModal.toast({ type: 'warning', title: 'Lengkapi data', message: 'Silakan pilih file gambar.' });
                    return false;
                }

                if (file) {
                    const formData = new FormData();
                    formData.append('id', examId);
                    formData.append('template', file);

                    EModal.loading('Mengunggah template...');
                    this.api('exams.php', 'upload-template', {
                        ajax: {
                            method: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false
                        }
                    }).done((res) => {
                        EModal.closeAll();
                        EModal.toast({ type: 'success', title: 'Berhasil', message: 'Template kartu berhasil diunggah.' });
                        this.loadExamList();
                    }).fail(xhr => {
                        EModal.closeAll();
                        EModal.toast({ type: 'danger', title: 'Gagal', message: xhr.responseJSON?.message || 'Gagal mengunggah template.' });
                    });
                } else {
                    EModal.closeAll();
                }
                return false;
            },
            onOpen: () => {
                $('#tplFileInput').on('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            $('#tplPreviewContainer').html(`<img src="${e.target.result}" style="max-width: 100%; max-height: 200px; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">`);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    },
    // ==================== IMPORT ACCOUNT SECTION ====================
    showImportAccountModal() {
        const examId = $("#statusExamSelect").val();
        if (!examId) return EModal.toast({ type: "warning", title: "Pilih Ujian", message: "Pilih nama ujian terlebih dahulu." });
        
        this.accountImportData = [];
        const modal = `
        <div class="sp-modal show" id="accountImportModal" onclick="if(event.target===this)ExamCard.closeFormModal('accountImportModal')">
            <div class="sp-modal-panel" style="max-width: 500px;">
                <div class="sp-modal-header">
                    <h3>Import User & Password Siswa</h3>
                    <button class="sp-modal-close" onclick="ExamCard.closeFormModal('accountImportModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                </div>
                <div class="sp-modal-body">
                    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;line-height:1.5;">Format Excel: <strong>NIS, Nama, Ruang, Username, Password</strong>.<br>Sistem akan mengupdate data siswa berdasarkan NIS yang terdaftar di ujian ini.</p>
                    <button type="button" class="btn btn-outline btn-sm" style="margin-bottom:16px; width:100%; justify-content:center; border-style:dashed;" onclick="ExamCard.downloadAccountTemplate()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="margin-right:8px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Download Template Excel
                    </button>
                    <div class="drop-zone" id="accountExcelDropZone" onclick="document.getElementById('accountExcelFile').click()" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.2s ease; background: #f8fafc;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48" style="color:#94a3b8; margin-bottom: 12px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <p style="font-size:14px; font-weight: 600; color:#475569;">Klik atau drag file Excel di sini</p>
                        <p style="font-size:12px;color:#94a3b8;margin-top:6px;">Hanya file .xlsx atau .xls</p>
                    </div>
                    <input type="file" id="accountExcelFile" accept=".xlsx,.xls" style="display:none" onchange="ExamCard.handleAccountExcelFile(this)">
                    <div id="accountExcelName" class="mt-sm text-muted" style="font-size:13px; margin-top: 12px; font-weight:500;"></div>
                    <div id="accountImportPreview" class="mt-sm" style="font-size:13px; margin-top: 8px;"></div>
                </div>
                <div class="sp-modal-footer">
                    <button class="btn btn-ghost" onclick="ExamCard.closeFormModal('accountImportModal')">Batal</button>
                    <button class="btn btn-primary" id="importAccountBtn" onclick="ExamCard.importAccountExcel()">Proses Import</button>
                </div>
            </div>
        </div>`;
        $('body').append(modal);

        const dz = document.getElementById('accountExcelDropZone');
        dz.addEventListener('dragover', e => { e.preventDefault(); dz.style.borderColor = 'var(--primary)'; dz.style.background = 'rgba(21, 101, 192, 0.05)'; });
        dz.addEventListener('dragleave', () => { dz.style.borderColor = 'var(--bg-dark)'; dz.style.background = 'none'; });
        dz.addEventListener('drop', e => {
            e.preventDefault();
            dz.style.borderColor = 'var(--bg-dark)'; dz.style.background = 'none';
            if (e.dataTransfer.files.length) ExamCard.handleAccountExcelFile({ files: e.dataTransfer.files });
        });
    },

    handleAccountExcelFile(input) {
        const file = input.files[0];
        if (!file) return;
        $('#accountExcelName').text(file.name);
        this.loadSheetJS(() => {
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const wb = XLSX.read(e.target.result, { type: 'binary' });
                    const ws = wb.Sheets[wb.SheetNames[0]];
                    const headers = ['nis', 'nama', 'ruang', 'username', 'password'];
                    const data = XLSX.utils.sheet_to_json(ws, { header: headers, raw: false, defval: '' });
                    data.shift(); // Remove header row
                    this.accountImportData = data.filter(row => String(row.nis).trim() !== '');
                    $('#accountImportPreview').html(`<span class="text-success" style="color:#059669; font-weight:600;">${this.accountImportData.length} data siap diimport.</span>`);
                } catch (err) {
                    this.accountImportData = [];
                    $('#accountImportPreview').html('<span class="text-danger" style="color:#dc2626;">Gagal membaca file Excel.</span>');
                }
            };
            reader.readAsBinaryString(file);
        });
    },

    loadSheetJS(callback) {
        if (window.XLSX) return callback();
        const script = document.createElement('script');
        script.src = "https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js";
        script.onload = callback;
        document.head.appendChild(script);
    },

    importAccountExcel() {
        if (!this.accountImportData || !this.accountImportData.length) {
            return EModal.toast({ type: 'warning', title: 'Data Kosong', message: 'Silakan pilih file Excel yang valid.' });
        }
        const examId = $("#statusExamSelect").val();
        const btn = document.getElementById('importAccountBtn');
        EModal.btnLoading(btn, true);
        
        this.api('status.php', 'import', {
            ajax: {
                method: 'POST',
                data: { exam_id: examId, data: this.accountImportData }
            }
        }).done(res => {
            this.closeFormModal('accountImportModal');
            if (res.success) {
                EModal.info({ type: 'success', title: 'Berhasil', message: res.message });
                this.loadStatusStudents();
            } else {
                EModal.toast({ type: 'error', title: 'Gagal', message: res.message });
            }
        }).fail(xhr => {
            EModal.toast({ type: 'error', title: 'Gagal', message: xhr.responseJSON?.message || 'Terjadi kesalahan.' });
        }).always(() => EModal.btnLoading(btn, false));
    },

    downloadAccountTemplate() {
        this.loadSheetJS(() => {
            const headers = ['NIS', 'Nama', 'Ruang', 'Username', 'Password'];
            const data = [headers];
            
            // Ambil data siswa yang sedang tampil di tabel (sesuai filter)
            $("#statusTableWrap tbody tr:visible").each(function() {
                const nis = $(this).find("td:nth-child(2)").text().trim();
                const nama = $(this).find("td:nth-child(4)").text().trim();
                const ruang = $(this).find(".st-ruang").val().trim();
                const username = $(this).find(".st-username").val().trim();
                // Password diambil sebagai string kosong secara default agar admin/guru bisa mengisinya,
                // tapi jika sudah ada bisa kita tampilkan juga
                const password = $(this).find(".st-password").val().trim();
                
                data.push([nis, nama, ruang, username, password]);
            });

            // Jika tabel kosong, beri contoh template default
            if (data.length === 1) {
                data.push(['12345', 'Ahmad Fikri', 'Ruang 01', 'ahmad123', 'pass123']);
            }

            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            
            // Nama file dinamis
            let filename = 'Template-Import-Akun.xlsx';
            const examId = $("#statusExamSelect").val();
            const kelas = $("#statusClassSelect").val();
            if (examId && kelas) {
                const kelasStr = kelas === 'SEMUA' ? 'Semua-Kelas' : kelas;
                filename = `Import-Akun-${kelasStr}.xlsx`.replace(/[^a-zA-Z0-9.\-]/g, '_');
            }

            XLSX.utils.book_append_sheet(wb, ws, 'Template Import Akun');
            XLSX.writeFile(wb, filename);
        });
    },

    closeFormModal(id) {
        $(`#${id}`).remove();
    }
};

window.ExamCard = ExamCard;
$(document).ready(() => ExamCard.init());
