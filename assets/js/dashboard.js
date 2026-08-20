/**
 * E-Portal Dashboard Module
 * Module grid and user interactions
 */
const Dashboard = {
    /**
     * Render Dashboard
     */
    render(container) {
        const user = App.state.user;
        const school = App.state.school;
        const now = new Date();
        const greeting = this.getGreeting();

        const schoolIcon = school.icon 
            ? `<img src="${App.baseUrl}${school.icon}" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;">`
            : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 36px; height: 36px; color: var(--primary);"><path d="M2 20h20"/><path d="M5 20V8.5L12 4l7 4.5V20"/><path d="M9 20v-4h6v4"/></svg>`;

        container.innerHTML = `
        <div class="dashboard-wrapper">
            <!-- Top Navbar Removed -->

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Welcome Section -->
                <div class="fade-in" style="text-align: center; padding: 20px 0 40px; color: #1565C0; margin-bottom: 20px;">
                    <h2 style="font-family: 'Times New Roman', Times, serif; font-size: 42px; margin-bottom: 4px; font-weight: 700; letter-spacing: 6px; text-transform: uppercase;">PORTAL DIGITAL</h2>
                    <p style="font-size: 18px; opacity: 0.9; margin-bottom: 0; font-weight: 500; letter-spacing: 0.5px;">Pusat Aplikasi & Layanan Terpadu SMA Wachid Hasyim 1 Surabaya</p>
                </div>
                <!-- Title Removed -->
                <div class="module-grid" id="moduleGrid">
                    <!-- Skeleton loading -->
                    ${this.renderSkeletonModules(6)}
                </div>
            </div>
        </div>`;

        // Close dropdown on outside click
        $(document).on('click', (e) => {
            if (!$(e.target).closest('#userAvatar, #userDropdown').length) {
                $('#userDropdown').removeClass('show');
            }
        });

        // Load data
        this.loadModules();
    },

    /**
     * Get greeting based on time
     */
    getGreeting() {
        const hour = new Date().getHours();
        if (hour < 11) return 'Selamat Pagi';
        if (hour < 15) return 'Selamat Siang';
        if (hour < 18) return 'Selamat Sore';
        return 'Selamat Malam';
    },

    /**
     * Toggle user dropdown
     */
    toggleUserMenu() {
        $('#userDropdown').toggleClass('show');
    },

    /**
     * Render skeleton module cards
     */
    renderSkeletonModules(count) {
        let html = '';
        for (let i = 0; i < count; i++) {
            html += `
            <div class="module-card list-view skeleton-module">
                <div class="module-icon skeleton"></div>
                <div class="module-info">
                    <div class="module-name skeleton"></div>
                    <div class="module-desc skeleton"></div>
                </div>
            </div>`;
        }
        return html;
    },

    /**
     * Load modules from API
     */
    loadModules() {
        App.api('api/modules.php?action=list')
        .done((res) => {
            if (res.success && res.data) {
                this.renderModules(res.data);
            }
        })
        .fail(() => {
            $('#moduleGrid').html(`
                <div class="empty-state" style="grid-column: 1/-1;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    <h3>Gagal Memuat</h3>
                    <p>Tidak dapat memuat modul. Silakan muat ulang.</p>
                    <button class="btn btn-primary btn-sm" onclick="Dashboard.loadModules()">Coba Lagi</button>
                </div>
            `);
        });
    },

    /**
     * Render module cards
     */
    renderModules(modules) {
        if (!modules.length) {
            $('#moduleGrid').html(`
                <div class="empty-state" style="grid-column: 1/-1;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8"/></svg>
                    <h3>Belum Ada Modul</h3>
                    <p>Modul akan muncul setelah ditambahkan oleh administrator.</p>
                </div>
            `);
            return;
        }

        const html = modules.map((mod, i) => {
            const color = mod.color || '#1565C0';
            return `
            <div class="module-card list-view slide-up" style="background-color: ${color}; animation-delay: ${i * 0.08}s;" onclick="Dashboard.openModule('${mod.slug}', '${mod.url_path}')">
                <div class="module-icon">
                    ${mod.icon_svg}
                </div>
                <div class="module-info">
                    <div class="module-name">${App.escapeHtml(mod.nama_modul)}</div>
                    <div class="module-desc">${App.escapeHtml(mod.deskripsi || '')}</div>
                </div>
            </div>`;
        }).join('');

        $('#moduleGrid').html(html);
    },

    /**
     * Open module
     */
    openModule(slug, urlPath) {
        const isExternal = urlPath && (urlPath.startsWith('http://') || urlPath.startsWith('https://'));

        if (!App.state.token && !isExternal) {
            EModal.toast({ type: 'warning', title: 'Akses Dibatasi', message: 'Silakan login terlebih dahulu untuk mengakses modul ini.' });
            if (urlPath) {
                sessionStorage.setItem('eportal_intended_module', urlPath);
                sessionStorage.setItem('eportal_intended_slug', slug);
            }
            App.navigate('login');
            return;
        }

        if (urlPath) {
            const url = isExternal ? urlPath : App.baseUrl + urlPath + '?token=' + App.state.token;
            window.open(url, '_blank');
        } else {
            EModal.info({
                type: 'info',
                title: 'Segera Hadir',
                message: `Modul ${slug.replace('e-', 'E-').replace(/^\w/, c => c.toUpperCase())} sedang dalam pengembangan.`,
                buttonText: 'OK, Mengerti'
            });
        }
    },

    /**
     * Convert hex to rgba
     */
    hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
};
