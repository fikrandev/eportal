/**
 * E-Portal Custom Modal System
 * Animated info, confirm, loading modals with premium feel
 */
const EModal = {
    /**
     * Show Info Modal (Success / Error / Warning / Info)
     */
    info(options = {}) {
        const defaults = {
            type: 'success', // success, error, warning, info
            title: 'Berhasil',
            message: '',
            buttonText: 'OK',
            onClose: null,
            duration: 2000
        };
        const opt = { ...defaults, ...options };
        
        const icons = {
            success: `<svg class="modal-status-icon success" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="36" fill="none" stroke="#10B981" stroke-width="3" class="circle-anim"/>
                <path d="M24 42 L35 53 L56 28" fill="none" stroke="#10B981" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" class="check-anim"/>
            </svg>`,
            error: `<svg class="modal-status-icon error" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="36" fill="none" stroke="#EF4444" stroke-width="3" class="circle-anim"/>
                <line x1="28" y1="28" x2="52" y2="52" stroke="#EF4444" stroke-width="3.5" stroke-linecap="round" class="x-anim-1"/>
                <line x1="52" y1="28" x2="28" y2="52" stroke="#EF4444" stroke-width="3.5" stroke-linecap="round" class="x-anim-2"/>
            </svg>`,
            warning: `<svg class="modal-status-icon warning" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="36" fill="none" stroke="#F59E0B" stroke-width="3" class="circle-anim"/>
                <line x1="40" y1="26" x2="40" y2="46" stroke="#F59E0B" stroke-width="3.5" stroke-linecap="round" class="warn-line"/>
                <circle cx="40" cy="55" r="2.5" fill="#F59E0B" class="warn-dot"/>
            </svg>`,
            info: `<svg class="modal-status-icon info" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="36" fill="none" stroke="#3B82F6" stroke-width="3" class="circle-anim"/>
                <circle cx="40" cy="27" r="2.5" fill="#3B82F6" class="info-dot"/>
                <line x1="40" y1="36" x2="40" y2="56" stroke="#3B82F6" stroke-width="3.5" stroke-linecap="round" class="info-line"/>
            </svg>`
        };

        const btnClasses = {
            success: 'btn-success',
            error: 'btn-danger',
            warning: 'btn-accent',
            info: 'btn-primary'
        };

        const html = `
        <div class="emodal-overlay" id="emodal-${Date.now()}">
            <div class="emodal-card emodal-info">
                <div class="emodal-icon-wrapper">
                    ${icons[opt.type]}
                </div>
                <h3 class="emodal-title">${opt.title}</h3>
                ${opt.message ? `<p class="emodal-message">${opt.message}</p>` : ''}
                ${opt.duration > 0 ? '' : `<button class="btn ${btnClasses[opt.type]} btn-block emodal-btn" onclick="EModal.close(this)">${opt.buttonText}</button>`}
            </div>
        </div>`;

        const container = document.getElementById('modalContainer');
        container.insertAdjacentHTML('beforeend', html);
        
        const overlay = container.lastElementChild;
        requestAnimationFrame(() => overlay.classList.add('show'));

        // Store callback
        overlay._onClose = opt.onClose;

        if (opt.duration > 0) {
            setTimeout(() => this.close(overlay), opt.duration);
        }

        return overlay;
    },

    /**
     * Show Alert Modal (Alias for info error/warning)
     */
    alert(title, message, type = 'error') {
        return this.info({
            type: type,
            title: title,
            message: message,
            duration: 0
        });
    },

    /**
     * Show Confirm Modal
     */
    confirm(options = {}) {
        const defaults = {
            title: 'Konfirmasi',
            message: 'Apakah Anda yakin?',
            confirmText: 'Ya, Lanjutkan',
            cancelText: 'Batal',
            type: 'warning', // warning, danger
            onConfirm: null,
            onCancel: null
        };
        const opt = { ...defaults, ...options };

        const iconSvg = opt.type === 'danger' 
            ? `<svg class="modal-status-icon error" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="36" fill="none" stroke="#EF4444" stroke-width="3" class="circle-anim"/>
                <line x1="40" y1="24" x2="40" y2="46" stroke="#EF4444" stroke-width="3.5" stroke-linecap="round" class="warn-line"/>
                <circle cx="40" cy="55" r="2.5" fill="#EF4444" class="warn-dot"/>
               </svg>`
            : `<svg class="modal-status-icon warning" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="36" fill="none" stroke="#F59E0B" stroke-width="3" class="circle-anim"/>
                <line x1="40" y1="24" x2="40" y2="46" stroke="#F59E0B" stroke-width="3.5" stroke-linecap="round" class="warn-line"/>
                <circle cx="40" cy="55" r="2.5" fill="#F59E0B" class="warn-dot"/>
               </svg>`;

        const html = `
        <div class="emodal-overlay" id="emodal-${Date.now()}">
            <div class="emodal-card emodal-confirm">
                <div class="emodal-icon-wrapper">
                    ${iconSvg}
                </div>
                <h3 class="emodal-title">${opt.title}</h3>
                <p class="emodal-message">${opt.message}</p>
                <div class="emodal-actions">
                    <button class="btn btn-ghost emodal-cancel-btn" onclick="EModal.handleCancel(this)">${opt.cancelText}</button>
                    <button class="btn ${opt.type === 'danger' ? 'btn-danger' : 'btn-accent'} emodal-confirm-btn" onclick="EModal.handleConfirm(this)">${opt.confirmText}</button>
                </div>
            </div>
        </div>`;

        const container = document.getElementById('modalContainer');
        container.insertAdjacentHTML('beforeend', html);
        
        const overlay = container.lastElementChild;
        overlay._onConfirm = opt.onConfirm;
        overlay._onCancel = opt.onCancel;
        
        requestAnimationFrame(() => overlay.classList.add('show'));

        return overlay;
    },

    /**
     * Show Loading Modal
     */
    loading(message = 'Memproses...') {
        const html = `
        <div class="emodal-overlay emodal-loading-overlay" id="emodal-loading-${Date.now()}">
            <div class="emodal-card emodal-loading">
                <div class="emodal-spinner">
                    <svg viewBox="0 0 50 50">
                        <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="var(--primary)" class="spinner-circle"/>
                    </svg>
                </div>
                <p class="emodal-loading-text">${message}</p>
            </div>
        </div>`;

        const container = document.getElementById('modalContainer');
        container.insertAdjacentHTML('beforeend', html);
        
        const overlay = container.lastElementChild;
        requestAnimationFrame(() => overlay.classList.add('show'));

        return overlay;
    },

    /**
     * Close a modal
     */
    close(btnOrOverlay) {
        const overlay = btnOrOverlay.closest ? btnOrOverlay.closest('.emodal-overlay') : btnOrOverlay;
        if (!overlay) return;

        overlay.classList.remove('show');
        overlay.classList.add('hiding');

        setTimeout(() => {
            if (overlay._onClose) overlay._onClose();
            overlay.remove();
        }, 300);
    },

    /**
     * Close loading modal
     */
    closeLoading() {
        const loader = document.querySelector('.emodal-loading-overlay');
        if (loader) this.close(loader);
    },

    /**
     * Handle confirm button
     */
    handleConfirm(btn) {
        const overlay = btn.closest('.emodal-overlay');
        if (overlay._onConfirm) overlay._onConfirm();
        this.close(overlay);
    },

    /**
     * Handle cancel button
     */
    handleCancel(btn) {
        const overlay = btn.closest('.emodal-overlay');
        if (overlay._onCancel) overlay._onCancel();
        this.close(overlay);
    },

    /**
     * Toast notification
     */
    toast(options = {}) {
        const defaults = {
            type: 'success',
            title: 'Berhasil',
            message: '',
            duration: 2000
        };
        const opt = { ...defaults, ...options };

        const icons = {
            success: `<svg class="toast-icon success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
            error: `<svg class="toast-icon error" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
            warning: `<svg class="toast-icon warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
            info: `<svg class="toast-icon info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`
        };

        const html = `
        <div class="toast toast-${opt.type}">
            ${icons[opt.type]}
            <div class="toast-body">
                <div class="toast-title">${opt.title}</div>
                ${opt.message ? `<div class="toast-message">${opt.message}</div>` : ''}
            </div>
            <button class="toast-close" onclick="this.closest('.toast').classList.add('removing'); setTimeout(() => this.closest('.toast').remove(), 300);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>`;

        const container = document.getElementById('toastContainer');
        container.insertAdjacentHTML('beforeend', html);

        const toast = container.lastElementChild;
        
        if (opt.duration > 0) {
            setTimeout(() => {
                toast.classList.add('removing');
                setTimeout(() => toast.remove(), 300);
            }, opt.duration);
        }

        return toast;
    },

    /**
     * Button loading state
     */
    btnLoading(btn, loading = true) {
        if (loading) {
            btn.classList.add('loading');
            btn.dataset.originalText = btn.innerHTML;
            const text = btn.querySelector('.btn-text');
            if (text) text.style.visibility = 'hidden';
            btn.disabled = true;
        } else {
            btn.classList.remove('loading');
            if (btn.dataset.originalText) {
                btn.innerHTML = btn.dataset.originalText;
            }
            btn.disabled = false;
        }
    },

    /**
     * Show Modal Form
     */
    form(options = {}) {
        const defaults = {
            title: 'Formulir',
            form: '',
            size: 'md', // md, lg, xl
            className: '',
            confirmText: 'Simpan',
            cancelText: 'Batal',
            onOpen: null,
            onConfirm: null,
            onCancel: null
        };
        const opt = { ...defaults, ...options };
        const modalId = `emodal-form-${Date.now()}`;
        
        const html = `
        <div class="emodal-overlay emodal-form-overlay" id="${modalId}">
            <div class="emodal-card emodal-full emodal-${opt.size} ${opt.className}">
                <div class="emodal-header">
                    <h3 class="emodal-title">${opt.title}</h3>
                    <button class="emodal-close-x" onclick="EModal.close(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="emodal-body">
                    <form id="${modalId}-form" onsubmit="return false;">${opt.form}</form>
                </div>
                <div class="emodal-footer">
                    <button class="btn btn-ghost" onclick="EModal.handleCancel(this)">${opt.cancelText}</button>
                    <button class="btn btn-primary emodal-save-btn" onclick="EModal.handleFormConfirm(this)">${opt.confirmText}</button>
                </div>
            </div>
        </div>`;

        const container = document.getElementById('modalContainer') || document.body;
        container.insertAdjacentHTML('beforeend', html);
        
        const overlay = container.lastElementChild;
        overlay._onConfirm = opt.onConfirm;
        overlay._onCancel = opt.onCancel;
        
        requestAnimationFrame(() => overlay.classList.add('show'));
        if (opt.onOpen) opt.onOpen();
        
        return overlay;
    },

    /**
     * Handle Form Confirm
     */
    handleFormConfirm(btn) {
        const overlay = btn.closest('.emodal-overlay');
        if (overlay._onConfirm) {
            // In E-Sarpras, onConfirm handles its own closing and returns false or calls closeAll
            const result = overlay._onConfirm();
            if (result === false) return;
        }
        this.close(overlay);
    },

    /**
     * Close all open modals
     */
    closeAll() {
        const overlays = document.querySelectorAll('.emodal-overlay');
        overlays.forEach(ov => this.close(ov));
    }
};

/* =============================================
   MODAL CSS (injected via JS)
   ============================================= */
(function() {
    const style = document.createElement('style');
    style.textContent = `
    /* Modal Overlay */
    .emodal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: var(--z-modal, 1000);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        padding: 16px;
    }

    .emodal-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    .emodal-overlay.hiding {
        opacity: 0;
        visibility: hidden;
    }

    /* Modal Card */
    .emodal-card {
        background: var(--bg-white, #fff);
        border-radius: var(--radius-xl, 20px);
        padding: 40px 32px 32px;
        max-width: 380px;
        width: 100%;
        text-align: center;
        transform: scale(0.8) translateY(20px);
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
        max-height: 90vh;
        overflow-y: auto;
    }

    .emodal-card.emodal-full {
        max-width: 600px;
        padding: 0;
        text-align: left;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .emodal-card.emodal-lg { max-width: 900px; }
    .emodal-card.emodal-xl { max-width: 1140px; }

    .emodal-card.emodal-full .emodal-header {
        padding: 24px 32px;
        border-bottom: 1px solid var(--bg-dark, #F3F4F6);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .emodal-card.emodal-full .emodal-body {
        padding: 32px;
        overflow-y: auto;
        flex: 1;
    }

    .emodal-card.emodal-full .emodal-footer {
        padding: 20px 32px;
        border-top: 1px solid var(--bg-dark, #F3F4F6);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    .emodal-close-x {
        width: 32px; height: 32px;
        border-radius: 50%;
        border: none;
        background: var(--bg-light, #F9FAFB);
        color: var(--text-secondary, #6B7280);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.2s;
    }
    .emodal-close-x:hover { background: #fee2e2; color: #ef4444; }
    .emodal-close-x svg { width: 18px; height: 18px; }

    .emodal-overlay.show .emodal-card {
        transform: scale(1) translateY(0);
    }

    .emodal-overlay.hiding .emodal-card {
        transform: scale(0.8) translateY(20px);
    }

    /* Icon Wrapper */
    .emodal-icon-wrapper {
        margin-bottom: 20px;
        display: flex;
        justify-content: center;
    }

    .modal-status-icon {
        width: 80px;
        height: 80px;
    }

    /* Circle animation */
    .circle-anim {
        stroke-dasharray: 226;
        stroke-dashoffset: 226;
        animation: circleIn 0.6s ease 0.2s forwards;
    }

    /* Checkmark animation */
    .check-anim {
        stroke-dasharray: 50;
        stroke-dashoffset: 50;
        animation: checkIn 0.4s ease 0.6s forwards;
    }

    /* X animation */
    .x-anim-1 {
        stroke-dasharray: 34;
        stroke-dashoffset: 34;
        animation: checkIn 0.3s ease 0.5s forwards;
    }
    .x-anim-2 {
        stroke-dasharray: 34;
        stroke-dashoffset: 34;
        animation: checkIn 0.3s ease 0.7s forwards;
    }

    /* Warning/Info line animation */
    .warn-line, .info-line {
        stroke-dasharray: 22;
        stroke-dashoffset: 22;
        animation: checkIn 0.3s ease 0.5s forwards;
    }

    .warn-dot, .info-dot {
        opacity: 0;
        animation: dotIn 0.3s ease 0.8s forwards;
    }

    @keyframes circleIn {
        to { stroke-dashoffset: 0; }
    }

    @keyframes checkIn {
        to { stroke-dashoffset: 0; }
    }

    @keyframes dotIn {
        to { opacity: 1; }
    }

    /* Title & Message */
    .emodal-title {
        font-family: var(--font-heading, 'Outfit', sans-serif);
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary, #1A1A2E);
        margin-bottom: 8px;
    }

    .emodal-message {
        font-size: 0.9rem;
        color: var(--text-secondary, #6B7280);
        line-height: 1.5;
        margin-bottom: 24px;
    }

    /* Confirm Actions */
    .emodal-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }

    .emodal-actions .btn {
        flex: 1;
    }

    /* Loading Modal */
    .emodal-loading {
        padding: 48px 32px;
    }

    .emodal-spinner {
        width: 56px;
        height: 56px;
        margin: 0 auto 20px;
    }

    .emodal-spinner svg {
        width: 100%;
        height: 100%;
        animation: rotate 1.4s linear infinite;
    }

    .spinner-circle {
        stroke-linecap: round;
        stroke-dasharray: 1, 200;
        stroke-dashoffset: 0;
        animation: dash 1.4s ease-in-out infinite;
    }

    .emodal-loading-text {
        font-family: var(--font-heading, 'Outfit', sans-serif);
        font-size: 0.9rem;
        color: var(--text-secondary, #6B7280);
        font-weight: 500;
    }

    .emodal-loading-overlay {
        cursor: default;
    }

    @keyframes rotate {
        100% { transform: rotate(360deg); }
    }

    @keyframes dash {
        0% { stroke-dasharray: 1, 200; stroke-dashoffset: 0; }
        50% { stroke-dasharray: 89, 200; stroke-dashoffset: -35; }
        100% { stroke-dasharray: 89, 200; stroke-dashoffset: -124; }
    }

    /* Responsive */
    @media (max-width: 480px) {
        .emodal-card {
            padding: 32px 24px 24px;
            max-width: calc(100vw - 32px);
        }
    }
    `;
    document.head.appendChild(style);
})();
