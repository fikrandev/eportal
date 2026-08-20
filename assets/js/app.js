/**
 * E-Portal Core Application
 * SPA Router, Global State, Helper Functions
 */
const App = {
    // Global state
    state: {
        user: null,
        token: null,
        school: { nama: 'E-Portal Sekolah', icon: '' },
        academicYear: null,
        currentPage: null
    },

    // Base URL
    baseUrl: window.APP_CONFIG ? window.APP_CONFIG.baseUrl : '/',

    /**
     * Initialize Application
     */
    init() {
        // Initialize from server-injected settings
        if (window.SCHOOL_SETTINGS) {
            this.state.school = {
                nama: window.SCHOOL_SETTINGS.nama || 'E-Portal Sekolah',
                icon: window.SCHOOL_SETTINGS.icon || ''
            };
        }
        if (window.ACTIVE_ACADEMIC_YEAR) {
            this.state.academicYear = window.ACTIVE_ACADEMIC_YEAR;
        }

        // Load saved state (overwrites school if exists in localStorage)
        this.loadState();

        // Setup AJAX defaults
        this.setupAjax();

        // Router listener
        window.addEventListener('hashchange', () => this.handleRoute());

        // If token exists, verify with server before routing
        if (this.state.token) {
            this.verifySession();
        } else {
            this.handleRoute();
            setTimeout(() => {
                document.getElementById('globalLoader').classList.add('hidden');
            }, 600);
        }
    },

    /**
     * Verify session token with server
     * Ensures token in localStorage is still valid on the backend
     */
    verifySession() {
        $.ajax({
            url: this.baseUrl + 'api/auth.php?action=check',
            headers: { 'Authorization': 'Bearer ' + this.state.token },
            dataType: 'json',
            timeout: 10000
        }).done((res) => {
            if (res.success && res.data) {
                // Refresh state with latest server data
                this.state.user = res.data.user;
                if (res.data.school) this.state.school = res.data.school;
                if (res.data.academic_year) this.state.academicYear = res.data.academic_year;
                localStorage.setItem('eportal_user', JSON.stringify(res.data.user));
                if (res.data.school) localStorage.setItem('eportal_school', JSON.stringify(res.data.school));
                if (res.data.academic_year) localStorage.setItem('eportal_academic_year', JSON.stringify(res.data.academic_year));
            }
            this.handleRoute();
        }).fail(() => {
            // Token expired/invalid — clear and redirect to login
            this.clearState();
            this.handleRoute();
        }).always(() => {
            setTimeout(() => {
                document.getElementById('globalLoader').classList.add('hidden');
            }, 600);
        });
    },

    /**
     * Load state from localStorage
     */
    loadState() {
        try {
            const token = localStorage.getItem('eportal_token');
            const user = localStorage.getItem('eportal_user');
            const school = localStorage.getItem('eportal_school');
            const academicYear = localStorage.getItem('eportal_academic_year');

            if (token) this.state.token = token;
            if (user) this.state.user = JSON.parse(user);
            if (school) this.state.school = JSON.parse(school);
            if (academicYear) this.state.academicYear = JSON.parse(academicYear);
        } catch (e) {
            this.clearState();
        }
    },

    /**
     * Save state to localStorage
     */
    saveState(token, user, school, academicYear = null) {
        this.state.token = token;
        this.state.user = user;
        if (school) this.state.school = school;
        if (academicYear) this.state.academicYear = academicYear;

        localStorage.setItem('eportal_token', token);
        localStorage.setItem('eportal_user', JSON.stringify(user));
        if (school) localStorage.setItem('eportal_school', JSON.stringify(school));
        if (academicYear) localStorage.setItem('eportal_academic_year', JSON.stringify(academicYear));
    },

    /**
     * Clear all state
     */
    clearState() {
        this.state.token = null;
        this.state.user = null;
        this.state.academicYear = null;
        localStorage.removeItem('eportal_token');
        localStorage.removeItem('eportal_user');
        localStorage.removeItem('eportal_school');
        localStorage.removeItem('eportal_academic_year');
    },

    /**
     * Setup jQuery AJAX defaults
     */
    setupAjax() {
        $.ajaxSetup({
            beforeSend: (xhr) => {
                if (this.state.token) {
                    xhr.setRequestHeader('Authorization', 'Bearer ' + this.state.token);
                }
            },
            error: (xhr) => {
                if (xhr.status === 401) {
                    this.clearState();
                    this.navigate('login');
                    EModal.toast({ type: 'warning', title: 'Sesi Berakhir', message: 'Silakan login kembali.' });
                }
            }
        });
    },

    /**
     * Navigate to a hash route
     */
    navigate(page) {
        window.location.hash = '#/' + page;
    },

    /**
     * Handle route changes
     */
    handleRoute() {
        const hash = window.location.hash.slice(2) || ''; // Remove #/
        const page = hash.split('/')[0] || 'dashboard';

        // Auth guard - protect admin route only (dashboard is public)
        if (page === 'admin' && !this.state.token) {
            this.navigate('login');
            return;
        }

        // If logged in and trying to access login, redirect to dashboard
        if (page === 'login' && this.state.token) {
            this.navigate('dashboard');
            return;
        }

        // Route to page
        this.state.currentPage = page;
        const app = document.getElementById('app');

        switch (page) {
            case 'login':
                Auth.renderLogin(app);
                break;
            case 'dashboard':
                Dashboard.render(app);
                break;
            case 'admin':
                const subPage = hash.split('/')[1] || 'dashboard';
                Admin.render(app, subPage);
                break;
            default:
                this.navigate('dashboard');
        }
    },

    /**
     * API call helper
     */
    api(url, options = {}) {
        const defaults = {
            url: this.baseUrl + url,
            dataType: 'json',
            contentType: 'application/json',
            timeout: 30000
        };

        if (options.data && typeof options.data === 'object' && !(options.data instanceof FormData)) {
            options.data = JSON.stringify(options.data);
        }

        if (options.data instanceof FormData) {
            delete defaults.contentType;
            options.processData = false;
            options.contentType = false;
        }

        return $.ajax({ ...defaults, ...options });
    },

    /**
     * Format date
     */
    formatDate(dateStr) {
        const d = new Date(dateStr);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
        return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
    },

    /**
     * Get day name in Indonesian
     */
    getDayName(date) {
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return days[date.getDay()];
    },

    /**
     * Get user initials
     */
    getInitials(name) {
        return name
            .split(' ')
            .map(w => w[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();
    },

    /**
     * Debounce function
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize when DOM ready
$(document).ready(() => App.init());
