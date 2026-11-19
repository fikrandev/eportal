/**
 * E-Portal Shared Core Frontend Utilities
 */
(function(window) {
    'use strict';

    const HTML_ESCAPE_MAP = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    };

    const EPortalUtils = {
        /**
         * Strict HTML attribute & text escaping (&, <, >, ", ')
         */
        escapeHtml(val) {
            if (val === null || val === undefined) return '';
            return String(val).replace(/[&<>"']/g, function(ch) {
                return HTML_ESCAPE_MAP[ch];
            });
        },

        /**
         * Local YYYY-MM-DD date string (avoids UTC midnight date shift in WIB/WITA)
         */
        localDateISO(date) {
            const d = date instanceof Date ? date : new Date();
            if (isNaN(d.getTime())) return '';
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },

        /**
         * Guarded JSON.parse returning fallback on invalid JSON
         */
        safeJsonParse(str, fallback = null) {
            if (typeof str !== 'string' || !str) return fallback;
            try {
                return JSON.parse(str);
            } catch (e) {
                return fallback;
            }
        },

        /**
         * Safe print popup opener handling popup blockers
         */
        openPrintWindow(htmlContent, autoPrint = false) {
            const win = window.open('', '_blank');
            if (!win) {
                if (window.EModal && typeof window.EModal.alert === 'function') {
                    window.EModal.alert('Popup Diblokir', 'Mohon izinkan pop-up di browser Anda untuk mencetak dokumen.');
                } else {
                    alert('Pop-up diblokir oleh browser. Mohon izinkan pop-up untuk mencetak.');
                }
                return null;
            }
            win.document.open();
            win.document.write(htmlContent);
            win.document.close();
            if (autoPrint) {
                win.focus();
                setTimeout(() => { try { win.print(); } catch (e) {} }, 300);
            }
            return win;
        }
    };

    window.EPortalUtils = EPortalUtils;
})(window);
