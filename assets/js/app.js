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

// ==========================================
// GLOBAL TABLE PAGINATION SYSTEM
// ==========================================
function applyTablePagination(table) {
    const $table = $(table);
    // Exclude special tables that shouldn't be paginated (e.g., calendar, details, or very small tables)
    if ($table.hasClass('no-pagination') || $table.parents('.calendar, .no-pagination-container').length > 0) {
        return;
    }

    const $tbody = $table.find('tbody');
    if ($tbody.length === 0) return;

    // Get all valid rows (exclude header rows, empty indicator rows, etc.)
    const $rows = $tbody.find('> tr').not('.no-row-data, .empty-row, .no-paginate');
    const totalRows = $rows.length;
    
    // Get target insertion point (after the horizontal scroll wrapper if exists)
    let $targetInsert = $table;
    if ($table.parent().hasClass('table-responsive')) {
        $targetInsert = $table.parent();
    }
    let $wrapper = $targetInsert.next('.table-pagination-wrapper');
    
    if (totalRows === 0) {
        if ($wrapper.length) $wrapper.remove();
        return;
    }
    
    // Read or set default page size & current page
    let pageSize = parseInt($table.attr('data-page-size')) || 20;
    let currentPage = parseInt($table.attr('data-current-page')) || 1;
    
    // If total rows <= 5, hide pagination controls and show all rows
    if (totalRows <= 5) {
        $rows.show();
        if ($wrapper.length) $wrapper.remove();
        $table.removeAttr('data-paginated');
        return;
    }
    
    // Calculate total pages
    let totalPages = Math.ceil(totalRows / pageSize);
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    
    // Save state on the table element
    $table.attr('data-page-size', pageSize);
    $table.attr('data-current-page', currentPage);
    $table.attr('data-row-count', totalRows);
    $table.attr('data-paginated', 'true');
    
    // Hide/show rows based on page
    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = startIndex + pageSize;
    
    $rows.each(function(index) {
        if (index >= startIndex && index < endIndex) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
    
    // Render pagination controls
    if ($wrapper.length === 0) {
        $wrapper = $('<div class="table-pagination-wrapper"></div>');
        $targetInsert.after($wrapper);
    }
    
    $wrapper.html(`
        <div class="table-pagination-left">
            <span>Tampilkan</span>
            <select class="table-page-size-select">
                <option value="20" ${pageSize === 20 ? 'selected' : ''}>20</option>
                <option value="60" ${pageSize === 60 ? 'selected' : ''}>60</option>
                <option value="80" ${pageSize === 80 ? 'selected' : ''}>80</option>
                <option value="100" ${pageSize === 100 ? 'selected' : ''}>100</option>
            </select>
            <span>baris per halaman (Total <strong>${totalRows}</strong> data)</span>
        </div>
        <div class="table-pagination-right">
            <button class="table-page-btn btn-prev" ${currentPage === 1 ? 'disabled' : ''}>Sebelumnya</button>
            <span class="table-page-info">Halaman ${currentPage} dari ${totalPages}</span>
            <button class="table-page-btn btn-next" ${currentPage === totalPages ? 'disabled' : ''}>Berikutnya</button>
        </div>
    `);
    
    // Bind events
    $wrapper.find('.table-page-size-select').off('change').on('change', function() {
        const newSize = parseInt($(this).val());
        $table.attr('data-page-size', newSize);
        $table.attr('data-current-page', 1);
        applyTablePagination(table);
    });
    
    $wrapper.find('.btn-prev').off('click').on('click', function() {
        if (currentPage > 1) {
            $table.attr('data-current-page', currentPage - 1);
            applyTablePagination(table);
        }
    });
    
    $wrapper.find('.btn-next').off('click').on('click', function() {
        if (currentPage < totalPages) {
            $table.attr('data-current-page', currentPage + 1);
            applyTablePagination(table);
        }
    });
}

// Global Mutation Observer to automatically paginate any table added or modified in the DOM
function initGlobalTablePagination() {
    const observer = new MutationObserver((mutations) => {
        let needsPaginationCheck = false;
        
        for (let mutation of mutations) {
            if (mutation.addedNodes.length || mutation.removedNodes.length) {
                needsPaginationCheck = true;
                break;
            }
        }
        
        if (needsPaginationCheck) {
            $('table:not(.no-pagination)').each(function() {
                const table = this;
                const $table = $(table);
                
                if ($table.parents('.calendar, .no-pagination-container').length > 0) {
                    return;
                }
                
                const $tbody = $table.find('tbody');
                if ($tbody.length === 0) return;
                
                const currentCount = $tbody.find('> tr').not('.no-row-data, .empty-row, .no-paginate').length;
                const lastCount = parseInt($table.attr('data-row-count')) || 0;
                const isPaginated = $table.attr('data-paginated') === 'true';
                
                if (!isPaginated || currentCount !== lastCount) {
                    if (isPaginated && currentCount !== lastCount) {
                        $table.attr('data-current-page', 1);
                    }
                    applyTablePagination(table);
                }
            });
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Initial run on page load
    $('table:not(.no-pagination)').each(function() {
        applyTablePagination(this);
    });
}

// Initialize when DOM ready
$(document).ready(() => {
    App.init();
    initGlobalTablePagination();
});
