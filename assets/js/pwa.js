/**
 * E-Portal PWA Module
 * Service Worker registration, install prompt, update notification
 */
const PWA = {
    deferredPrompt: null,

    init() {
        this.registerSW();
        this.handleInstallPrompt();
    },

    registerSW() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                const baseUrl = window.APP_CONFIG ? window.APP_CONFIG.baseUrl : '/';
                navigator.serviceWorker.register(baseUrl + 'sw.js')
                    .then((reg) => {
                        console.log('SW registered:', reg.scope);
                        reg.update();
                        reg.addEventListener('updatefound', () => {
                            const newWorker = reg.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'activated') {
                                    if (navigator.serviceWorker.controller) {
                                        window.location.reload();
                                    }
                                }
                            });
                        });
                    })
                    .catch((err) => console.log('SW registration failed:', err));
            });
        }
    },

    handleInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            setTimeout(() => {
                const prompt = document.getElementById('pwaInstallPrompt');
                if (prompt) prompt.classList.remove('hidden');
            }, 2000);
        });

        window.addEventListener('appinstalled', () => {
            this.deferredPrompt = null;
            const prompt = document.getElementById('pwaInstallPrompt');
            if (prompt) prompt.classList.add('hidden');
            EModal.toast({ type: 'success', title: 'Terinstall!', message: 'E-Portal berhasil ditambahkan ke homescreen.' });
        });
    },

    install() {
        if (!this.deferredPrompt) {
            EModal.toast({ type: 'info', title: 'Info', message: 'Gunakan menu browser untuk menginstall aplikasi.' });
            return;
        }
        this.deferredPrompt.prompt();
        this.deferredPrompt.userChoice.then((result) => {
            if (result.outcome === 'accepted') {
                console.log('PWA installed');
            }
            this.deferredPrompt = null;
        });
    }
};

// Auto-init PWA
PWA.init();
