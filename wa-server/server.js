const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcodeTerminal = require('qrcode-terminal');
const qrcode = require('qrcode');
const express = require('express');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(express.json({ limit: '10mb' }));

// Set up CORS so E-Portal can access the API
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    if (req.method === 'OPTIONS') return res.sendStatus(200);
    next();
});

const AUTH_DIR = path.join(__dirname, '.wwebjs_auth');
let client = null;
let isReady = false;
let currentQR = '';
let isReinitializing = false;

// Sequential message queue to prevent concurrent spikes in Puppeteer
const sendQueue = [];
let isProcessingQueue = false;

/**
 * Deteksi path executable Google Chrome atau Microsoft Edge di Windows / Linux VPS
 */
function getChromeExecutable() {
    const candidates = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        path.join(process.env.LOCALAPPDATA || '', 'Google\\Chrome\\Application\\chrome.exe'),
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        // Linux / VPS paths
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser'
    ];
    for (const p of candidates) {
        if (p && fs.existsSync(p)) return p;
    }
    return undefined;
}

/**
 * Safe memory cleaning:
 * 1. Safe in-browser GC if exposed
 * 2. Chrome DevTools Protocol HeapProfiler garbage collection (tanpa merusak session keys/Signal protocol)
 * 3. Node.js V8 Garbage Collection
 */
async function cleanBrowserMemory() {
    if (!isReady || !client || !client.pupPage) return;
    try {
        // 1. Safe in-browser GC
        await client.pupPage.evaluate(() => {
            try {
                if (typeof window.gc === 'function') {
                    window.gc();
                }
            } catch (e) {}
        });

        // 2. Chrome DevTools Protocol HeapProfiler Garbage Collection
        try {
            const cdp = await client.pupPage.target().createCDPSession();
            await cdp.send('HeapProfiler.collectGarbage');
            await cdp.detach();
        } catch (cdpErr) {}

        // 3. Node.js V8 Garbage Collection
        if (typeof global.gc === 'function') {
            global.gc();
        }

        const mem = process.memoryUsage();
        const rssMb = Math.round(mem.rss / 1024 / 1024);
        const heapMb = Math.round(mem.heapUsed / 1024 / 1024);
        console.log(`[WA Memory] Pembersihan memori aman selesai. Node RSS: ${rssMb}MB, Heap: ${heapMb}MB`);
    } catch (err) {
        // Silently handle if page is navigating or closed
    }
}

/**
 * Process outgoing send queue sequentially
 */
async function processSendQueue() {
    if (isProcessingQueue) return;
    isProcessingQueue = true;

    while (sendQueue.length > 0) {
        const item = sendQueue.shift();
        try {
            const result = await executeSendMessage(item.chatId, item.message);
            item.resolve(result);
        } catch (err) {
            item.reject(err);
        }
        // Small breathing window between sends (300ms) to keep CPU & RAM flat
        await new Promise(r => setTimeout(r, 300));
    }

    isProcessingQueue = false;
}

/**
 * Execute actual message send via whatsapp-web.js
 * Dioptimalkan untuk pesan personal (@c.us) dan grup WhatsApp (@g.us)
 */
async function executeSendMessage(chatId, message) {
    const isGroup = chatId.endsWith('@g.us');
    const sendOptions = {
        linkPreview: false // Hindari overhead parsing link preview
    };

    // Timeout: 60 detik untuk group chat (karena enkripsi fan-out ke banyak anggota), 30 detik untuk personal
    const timeoutDuration = isGroup ? 60000 : 30000;

    let timeoutId;
    const timeoutPromise = new Promise((_, reject) => {
        timeoutId = setTimeout(() => {
            reject(new Error(`Timeout ${timeoutDuration / 1000}s: WhatsApp Web lambat/menunggu respon enkripsi ${isGroup ? 'grup' : 'chat'}.`));
        }, timeoutDuration);
    });

    try {
        const sendAction = (async () => {
            // Untuk group chat (@g.us): Pastikan model chat di WhatsApp Web sudah ter-load melalui getChatById
            if (isGroup) {
                try {
                    console.log(`[WA Send] Mempersiapkan chat grup ${chatId}...`);
                    const chat = await client.getChatById(chatId);
                    if (chat && typeof chat.sendMessage === 'function') {
                        const res = await chat.sendMessage(message, sendOptions);
                        if (res) return res;
                    }
                } catch (groupErr) {
                    console.log(`[WA Send Group] getChatById warning: ${groupErr.message}. Mencoba fallback client.sendMessage...`);
                }
            }

            // Direct send via client.sendMessage
            try {
                const res = await client.sendMessage(chatId, message, sendOptions);
                if (res) return res;
            } catch (firstErr) {
                console.log(`[WA Send] sendMessage direct gagal: ${firstErr.message}. Mencoba fallback getChatById...`);
                const chat = await client.getChatById(chatId);
                if (chat && typeof chat.sendMessage === 'function') {
                    return await chat.sendMessage(message, sendOptions);
                }
                throw firstErr;
            }

            // Jika return undefined/null, fallback ke chat instance
            const chat = await client.getChatById(chatId);
            if (chat && typeof chat.sendMessage === 'function') {
                return await chat.sendMessage(message, sendOptions);
            }

            throw new Error(`Gagal mengirim pesan ke ${chatId}: tidak ada respon dari WhatsApp Web`);
        })();

        const sentMsg = await Promise.race([sendAction, timeoutPromise]);
        clearTimeout(timeoutId);
        return sentMsg;
    } catch (err) {
        clearTimeout(timeoutId);
        throw err;
    }
}

/**
 * Initialize WhatsApp Client instance
 */
function initClient() {
    isReady = false;
    currentQR = '';
    isReinitializing = false;

    console.log('[WA] Menginisialisasi WhatsApp Client...');

    const chromePath = getChromeExecutable();
    if (chromePath) {
        console.log(`[WA] Menggunakan Chrome/Browser: ${chromePath}`);
    } else {
        console.log('[WA] Menggunakan browser bawaan Puppeteer.');
    }

    client = new Client({
        authStrategy: new LocalAuth({
            dataPath: AUTH_DIR
        }),
        webVersionCache: { type: 'none' },
        puppeteer: {
            headless: true,
            executablePath: chromePath,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu',
                '--disable-software-rasterizer',
                '--disable-default-apps',
                '--disable-extensions',
                '--disable-sync',
                '--disable-translate',
                '--hide-scrollbars',
                '--metrics-recording-only',
                '--mute-audio',
                '--no-default-browser-check',
                '--safebrowsing-disable-auto-update',
                // Optimasi Background & RAM aman
                '--disable-background-timer-throttling',
                '--disable-backgrounding-occluded-windows',
                '--disable-renderer-backgrounding'
            ]
        }
    });

    client.on('qr', async (qr) => {
        console.log('\n[WA] ======================================================');
        console.log('[WA] SILAKAN SCAN QR CODE DI BAWAH INI UNTUK LOGIN WHATSAPP:');
        console.log('[WA] Atau buka E-Portal -> Pengaturan WA untuk scan via web');
        console.log('[WA] ======================================================\n');
        qrcodeTerminal.generate(qr, { small: true });
        
        try {
            currentQR = await qrcode.toDataURL(qr);
        } catch (err) {
            console.error('[WA] Failed to generate QR Data URL', err);
        }
    });

    client.on('ready', () => {
        console.log('\n[WA] ======================================================');
        console.log('[WA] WhatsApp Client BERHASIL TERHUBUNG & SIAP DIGUNAKAN!');
        console.log('[WA] ======================================================\n');
        isReady = true;
        currentQR = '';
    });

    client.on('authenticated', () => {
        console.log('[WA] Sesi WhatsApp berhasil diautentikasi (Authenticated)!');
    });

    client.on('auth_failure', (msg) => {
        console.error('[WA] Auth failure:', msg);
        resetAndReinit(true);
    });

    client.on('disconnected', (reason) => {
        console.log('[WA] Client terputus (disconnected):', reason);
        isReady = false;
        currentQR = '';
        setTimeout(() => {
            resetAndReinit(false);
        }, 3000);
    });

    client.initialize().catch(err => {
        console.error('[WA] Initialize Error:', err.message);
    });
}

/**
 * Gracefully teardown old client, purge session files if requested, and spawn new client
 */
async function resetAndReinit(deleteSession = false) {
    if (isReinitializing) {
        console.log('[WA] Reset is already in progress, skipping duplicate call.');
        return;
    }
    isReinitializing = true;
    isReady = false;
    currentQR = '';

    console.log(`[WA] Mereset client WhatsApp (deleteSession: ${deleteSession})...`);

    if (client) {
        try {
            await Promise.race([
                client.logout().catch(() => {}),
                new Promise(res => setTimeout(res, 3000))
            ]);
        } catch (e) {}

        try {
            await client.destroy().catch(() => {});
        } catch (e) {}
        client = null;
    }

    if (deleteSession) {
        await new Promise(res => setTimeout(res, 1500));
        
        if (fs.existsSync(AUTH_DIR)) {
            try {
                fs.rmSync(AUTH_DIR, { recursive: true, force: true });
                console.log('[WA] Direktori sesi berhasil dibersihkan.');
            } catch (err) {
                console.warn('[WA] Warning deleting session dir:', err.message);
            }
        }
    }

    initClient();
}

// Start WhatsApp on startup
initClient();

// Handle graceful shutdown
const shutdownGracefully = async (signal) => {
    console.log(`\n[WA] Menerima sinyal ${signal}. Menutup client dengan aman...`);
    if (client) {
        try {
            await client.destroy();
            console.log('[WA] Client berhasil ditutup.');
        } catch (e) {
            console.error('[WA] Gagal menutup client:', e.message);
        }
    }
    process.exit(0);
};

process.on('SIGINT', () => shutdownGracefully('SIGINT'));
process.on('SIGTERM', () => shutdownGracefully('SIGTERM'));

// Health check endpoint
app.get('/', (req, res) => {
    const mem = process.memoryUsage();
    res.json({
        name: 'E-Portal WhatsApp Gateway API (Stable & Optimized)',
        status: isReady ? 'connected' : (currentQR ? 'qr_ready' : 'initializing'),
        isReady,
        hasQr: Boolean(currentQR),
        memory: {
            rss_mb: Math.round(mem.rss / 1024 / 1024),
            heap_mb: Math.round(mem.heapUsed / 1024 / 1024)
        },
        queue_length: sendQueue.length
    });
});

// Endpoint to check status and get QR Code
app.get('/status', (req, res) => {
    res.json({
        isReady,
        qr: currentQR
    });
});

// Endpoint to trigger manual memory cleanup
app.post('/clean-memory', async (req, res) => {
    await cleanBrowserMemory();
    const mem = process.memoryUsage();
    res.json({
        success: true,
        message: 'Pembersihan memori aman selesai.',
        memory: {
            rss_mb: Math.round(mem.rss / 1024 / 1024),
            heap_mb: Math.round(mem.heapUsed / 1024 / 1024)
        }
    });
});

// Endpoint to disconnect / logout device session
app.post('/logout', async (req, res) => {
    try {
        console.log('[WA] Memutuskan koneksi WhatsApp...');
        res.json({
            success: true,
            message: 'Koneksi WhatsApp berhasil diputuskan. Silakan tunggu beberapa detik dan lakukan scan Barcode baru.'
        });

        setImmediate(async () => {
            await resetAndReinit(true);
        });
    } catch (error) {
        console.error('[WA Logout Error]', error);
        res.status(500).json({ success: false, message: 'Gagal memutuskan koneksi: ' + error.message });
    }
});

// Endpoint to list WhatsApp Groups
app.get('/groups', async (req, res) => {
    if (!isReady || !client) {
        return res.status(503).json({ success: false, message: 'WhatsApp Client belum terhubung. Silakan lakukan scan QR Code terlebih dahulu.' });
    }

    try {
        console.log('[WA Groups] Mengambil daftar grup WhatsApp...');
        const chats = await client.getChats();
        let groups = [];
        
        if (Array.isArray(chats)) {
            groups = chats
                .filter(c => c && (c.isGroup || (c.id && (c.id._serialized || c.id.toString()).endsWith('@g.us'))))
                .map(g => ({
                    id: g.id ? (g.id._serialized || g.id.toString()) : '',
                    name: g.name || g.formattedTitle || 'Grup WhatsApp'
                }))
                .filter(g => g.id.length > 0);
        }

        console.log(`[WA Groups] Ditemukan ${groups.length} grup.`);
        res.json({ success: true, groups: groups || [] });
    } catch (error) {
        console.error('[WA Groups Error]', error);
        res.status(500).json({ success: false, message: 'Gagal mengambil daftar grup: ' + error.message });
    }
});

// Endpoint to send message (Supports individual numbers & group IDs)
app.post('/send', async (req, res) => {
    if (!isReady || !client) {
        return res.status(503).json({ success: false, message: 'WhatsApp Client belum terhubung. Harap scan QR Code terlebih dahulu.' });
    }

    const { number, message } = req.body;
    
    if (!number || !message) {
        return res.status(400).json({ success: false, message: 'Nomor tujuan atau pesan tidak boleh kosong.' });
    }

    try {
        let chatId = '';
        const numStr = String(number).trim();

        // Deteksi format Group vs Personal
        if (numStr.includes('@g.us')) {
            chatId = numStr;
        } else if (numStr.includes('@c.us')) {
            chatId = numStr;
        } else if (numStr.startsWith('120363') || numStr.includes('-') || numStr.endsWith('-group')) {
            // Group WhatsApp (format baru 120363... atau format lama nomor-timestamp)
            const cleanGroup = numStr.replace(/-group$/, '');
            chatId = cleanGroup.endsWith('@g.us') ? cleanGroup : `${cleanGroup}@g.us`;
        } else {
            // Personal Phone Number
            let formattedNumber = numStr.replace(/\D/g, ''); 
            if (formattedNumber.startsWith('0')) {
                formattedNumber = '62' + formattedNumber.substring(1);
            }
            chatId = `${formattedNumber}@c.us`;
        }

        // Masukkan ke antrean berurutan (Sequential Queue) untuk stabilitas
        const sendResult = await new Promise((resolve, reject) => {
            sendQueue.push({
                chatId,
                message,
                resolve,
                reject
            });
            processSendQueue();
        });

        console.log(`[WA] Berhasil mengirim pesan ke ${chatId}`);
        res.json({ success: true, message: 'Pesan berhasil terkirim ke WhatsApp.' });
    } catch (error) {
        console.error('[WA Send Error]', error.message);
        res.status(500).json({ success: false, message: 'Gagal mengirim pesan: ' + error.message });
    }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`\n======================================================`);
    console.log(`WhatsApp API Server running on port ${PORT}`);
    console.log(`Endpoint: POST http://localhost:${PORT}/send`);
    console.log(`Status:   GET  http://localhost:${PORT}/status`);
    console.log(`======================================================\n`);
});

// ==========================================
// MEMORY GUARD INTERVAL (Setiap 10 menit)
// ==========================================
setInterval(async () => {
    await cleanBrowserMemory();
}, 10 * 60 * 1000);

// Watchdog jika RAM Node melebihi batas 350MB
setInterval(async () => {
    const mem = process.memoryUsage();
    if (mem.rss > 350 * 1024 * 1024) {
        console.log(`[WA Memory Watchdog] RAM terdeteksi tinggi (${Math.round(mem.rss / 1024 / 1024)}MB), menjalankan pembersihan memori aman...`);
        await cleanBrowserMemory();
    }
}, 60 * 1000);

// ==========================================
// BACKGROUND AUTO-SYNC POLLING (Setiap 15 detik)
// Hanya mencatat log jika ADA data log baru
// ==========================================
setInterval(async () => {
    try {
        const res = await fetch('http://localhost/eportal/modules/e-absen/api/auto_sync.php?cron_token=eportal_auto_sync_secret');
        const data = await res.json();
        if (data && data.message && data.message.includes('baru') && !data.message.includes('0 log baru')) {
            console.log('[Auto-Sync]', data.message);
        }
    } catch (err) {
        // Abaikan error koneksi saat eportal offline
    }
}, 15000);
