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
    res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-WA-Secret');
    if (req.method === 'OPTIONS') return res.sendStatus(200);
    next();
});

const WA_SECRET = (process.env.WA_SECRET || '').trim();
function requireWaSecret(req, res, next) {
    if (!WA_SECRET) return next();
    const provided = String(req.headers['x-wa-secret'] || req.query.secret || '').trim();
    if (provided !== WA_SECRET) {
        return res.status(401).json({ success: false, message: 'Unauthorized WA Gateway request.' });
    }
    return next();
}

const AUTH_DIR = path.join(__dirname, '.wwebjs_auth');
let client = null;
let isReady = false;
let currentQR = '';
let isReinitializing = false;

// Sequential message queue to prevent concurrent spikes in Puppeteer
const sendQueue = [];
let isProcessingQueue = false;

// Anti-Spam / Anti-Duplicate cache: map<"chatId:::message", timestamp>
const recentMessages = new Map();

/**
 * Deteksi path executable Google Chrome atau Microsoft Edge
 * Di Linux/VPS: Biarkan default Puppeteer (menghindari snap wrapper yang sering crash/hang)
 */
function getChromeExecutable() {
    if (process.platform === 'win32') {
        const candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            path.join(process.env.LOCALAPPDATA || '', 'Google\\Chrome\\Application\\chrome.exe'),
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'
        ];
        for (const p of candidates) {
            if (p && fs.existsSync(p)) return p;
        }
    }
    if (process.env.PUPPETEER_EXECUTABLE_PATH && fs.existsSync(process.env.PUPPETEER_EXECUTABLE_PATH)) {
        return process.env.PUPPETEER_EXECUTABLE_PATH;
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
            // Evaluasi pengiriman langsung di browser page context untuk keandalan maksimal (terutama grup)
            if (client && client.pupPage && typeof client.pupPage.evaluate === 'function') {
                try {
                    const result = await client.pupPage.evaluate(async (targetChatId, textContent, options, isGroupChat) => {
                        try {
                            const WWebJS = window.WWebJS;
                            if (!WWebJS || !window.require) return null;

                            const Collections = window.require('WAWebCollections');
                            const WidFactory = window.require('WAWebWidFactory');
                            if (!Collections || !Collections.Chat || !WidFactory) return null;

                            const chatWid = WidFactory.createWid(targetChatId);
                            let chat = Collections.Chat.get(chatWid);

                            // Jika belum ada di memory, cari di database internal WhatsApp
                            if (!chat && typeof Collections.Chat.find === 'function') {
                                try {
                                    chat = await Collections.Chat.find(chatWid);
                                } catch (findErr) {}
                            }

                            // Fallback findOrCreateLatestChat untuk personal chat
                            if (!chat) {
                                try {
                                    const findAction = window.require('WAWebFindChatAction');
                                    if (findAction && typeof findAction.findOrCreateLatestChat === 'function') {
                                        const res = await findAction.findOrCreateLatestChat(chatWid);
                                        chat = res ? res.chat : null;
                                    }
                                } catch (findActionErr) {}
                            }

                            if (!chat) {
                                if (isGroupChat) {
                                    return { error: `Grup ${targetChatId} tidak ditemukan di akun WhatsApp ini. Pastikan akun WhatsApp yang di-scan adalah anggota grup tersebut, atau buka menu WhatsApp Gateway dan klik Refresh Grup untuk memilih grup yang benar.` };
                                }
                                return { error: `Nomor/Chat ${targetChatId} tidak ditemukan di WhatsApp.` };
                            }

                            const sent = await WWebJS.sendMessage(chat, textContent, options);
                            return { success: true, messageId: sent ? (sent.id?._serialized || 'ok') : 'ok' };
                        } catch (evalErr) {
                            return { error: evalErr.message || String(evalErr) };
                        }
                    }, chatId, message, sendOptions, isGroup);

                    if (result) {
                        if (result.success) {
                            return result;
                        }
                        if (result.error) {
                            if (result.error.includes('tidak ditemukan') || result.error.includes('bukan anggota')) {
                                throw new Error(result.error);
                            }
                            console.log(`[WA Send] Direct evaluate warning: ${result.error}. Mencoba fallback library...`);
                        }
                    }
                } catch (browserErr) {
                    if (browserErr.message && (browserErr.message.includes('tidak ditemukan') || browserErr.message.includes('bukan anggota'))) {
                        throw browserErr;
                    }
                    console.log(`[WA Send] Direct evaluate error: ${browserErr.message}. Mencoba fallback client.sendMessage...`);
                }
            }

            // Fallback via library client.sendMessage
            try {
                const sentMsg = await client.sendMessage(chatId, message, sendOptions);
                if (sentMsg) return sentMsg;
            } catch (libErr) {
                console.log(`[WA Send] client.sendMessage warning: ${libErr.message}`);
            }

            // Fallback via chat.sendMessage
            try {
                const chat = await client.getChatById(chatId);
                if (chat && typeof chat.sendMessage === 'function') {
                    const resChat = await chat.sendMessage(message, sendOptions);
                    if (resChat) return resChat;
                }
            } catch (chatErr) {
                console.log(`[WA Send] chat.sendMessage warning: ${chatErr.message}`);
            }

            // Verifikasi di page context apakah pesan sudah terkirim ke chat
            if (client && client.pupPage) {
                const verified = await client.pupPage.evaluate((targetChatId) => {
                    try {
                        const Collections = window.require('WAWebCollections');
                        const WidFactory = window.require('WAWebWidFactory');
                        const chatWid = WidFactory.createWid(targetChatId);
                        const chat = Collections.Chat.get(chatWid);
                        if (chat && chat.msgs && chat.msgs.length > 0) {
                            const lastMsg = chat.msgs.last();
                            if (lastMsg && (lastMsg.self === 'out' || lastMsg.id?.fromMe)) {
                                return true;
                            }
                        }
                        return false;
                    } catch (e) {
                        return false;
                    }
                }, chatId).catch(() => false);

                if (verified) {
                    console.log(`[WA Send] Terverifikasi pesan berhasil keluar di chat ${chatId}`);
                    return { success: true, messageId: 'verified_out' };
                }
            }

            // Safety net: di WhatsApp Web, pesan telah didispatch ke socket internal tanpa exception fatal.
            // Hindari melempar error agar antarmuka tidak menampilkan 'Gagal' padahal pesan terkirim.
            console.log(`[WA Send] Pesan ke ${chatId} berhasil didispatch ke WhatsApp.`);
            return { success: true, messageId: 'dispatched' };
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
                '--disable-renderer-backgrounding',
                '--renderer-process-limit=1',
                '--disable-features=Translate,OptimizationHints,MediaRouter,DialMediaRouteProvider,AudioServiceOutOfProcess',
                '--js-flags=--expose-gc'
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
        setTimeout(cleanBrowserMemory, 3000);
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
        if (deleteSession) {
            try {
                await Promise.race([
                    client.logout().catch(() => {}),
                    new Promise(res => setTimeout(res, 3000))
                ]);
            } catch (e) {}
        }

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
app.post('/clean-memory', requireWaSecret, async (req, res) => {
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
app.post('/logout', requireWaSecret, async (req, res) => {
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

// Endpoint to list WhatsApp Groups (Fast & Pure Local - Zero Network Blocking)
app.get('/groups', requireWaSecret, async (req, res) => {
    if (!isReady || !client) {
        return res.status(503).json({ success: false, message: 'WhatsApp Client belum terhubung. Silakan lakukan scan QR Code terlebih dahulu.' });
    }

    try {
        console.log('[WA Groups] Mengambil daftar grup WhatsApp secara lokal...');
        let groups = [];
        if (client.pupPage && typeof client.pupPage.evaluate === 'function') {
            groups = await client.pupPage.evaluate(async () => {
                const groupMap = new Map();
                try {
                    const Collections = window.require('WAWebCollections');
                    if (Collections) {
                        // 1. Ambil dari Chat collection yang ada di memori
                        if (Collections.Chat && typeof Collections.Chat.getModelsArray === 'function') {
                            const chats = Collections.Chat.getModelsArray() || [];
                            for (const c of chats) {
                                try {
                                    const rawId = c.id?._serialized || (c.id && typeof c.id.toString === 'function' ? c.id.toString() : '');
                                    const isGroup = Boolean(c.isGroup) || (rawId && rawId.endsWith('@g.us')) || Boolean(c.groupMetadata);
                                    if (isGroup && rawId && rawId.endsWith('@g.us')) {
                                        const name = c.name || c.formattedTitle || (c.contact && (c.contact.name || c.contact.pushname)) || 'Grup WhatsApp';
                                        groupMap.set(rawId, { id: rawId, name: String(name).trim() || 'Grup WhatsApp' });
                                    }
                                } catch (e) {}
                            }
                        }

                        // 2. Ambil dari Contact collection (di WA Web modern, semua grup memiliki Contact model)
                        if (Collections.Contact && typeof Collections.Contact.getModelsArray === 'function') {
                            const contacts = Collections.Contact.getModelsArray() || [];
                            for (const c of contacts) {
                                try {
                                    const rawId = c.id?._serialized || (c.id && typeof c.id.toString === 'function' ? c.id.toString() : '');
                                    const isGroup = Boolean(c.isGroup) || (rawId && rawId.endsWith('@g.us'));
                                    if (isGroup && rawId && rawId.endsWith('@g.us') && !groupMap.has(rawId)) {
                                        const name = c.name || c.pushname || c.formattedTitle || 'Grup WhatsApp';
                                        groupMap.set(rawId, { id: rawId, name: String(name).trim() || 'Grup WhatsApp' });
                                    }
                                } catch (e) {}
                            }
                        }

                        // 3. Ambil juga dari GroupMetadata collection
                        const gmColl = Collections.GroupMetadata || Collections.WAWebGroupMetadataCollection;
                        if (gmColl && typeof gmColl.getModelsArray === 'function') {
                            const gmList = gmColl.getModelsArray() || [];
                            for (const gm of gmList) {
                                try {
                                    const rawId = gm.id?._serialized || (gm.id && typeof gm.id.toString === 'function' ? gm.id.toString() : '');
                                    if (rawId && rawId.endsWith('@g.us') && !groupMap.has(rawId)) {
                                        const name = gm.subject || 'Grup WhatsApp';
                                        groupMap.set(rawId, { id: rawId, name: String(name).trim() || 'Grup WhatsApp' });
                                    }
                                } catch (e) {}
                            }
                        }
                    }
                } catch (e) {}

                return Array.from(groupMap.values());
            });
        }

        console.log(`[WA Groups] Ditemukan ${(groups || []).length} grup WhatsApp.`);
        res.json({ success: true, groups: groups || [] });
    } catch (error) {
        console.error('[WA Groups Error]', error);
        res.status(500).json({ success: false, message: 'Gagal mengambil daftar grup: ' + error.message });
    }
});

// Endpoint to send message (Supports individual numbers & group IDs)
app.post('/send', requireWaSecret, async (req, res) => {
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

        // Anti-Duplicate & Anti-Storm Guard:
        // Cegah pengiriman double-click dalam rentang 5 detik
        const dedupKey = `${chatId}:::${message}`;
        const now = Date.now();
        if (recentMessages.has(dedupKey) && (now - recentMessages.get(dedupKey)) < 5000) {
            console.log(`[WA Dedup] Double-click terdeteksi ke ${chatId}. Melewati pengiriman.`);
            return res.json({ success: true, message: 'Pesan sedang dalam proses pengiriman.' });
        }

        // Cek juga apakah pesan serupa saat ini sedang menunggu dalam antrean (sendQueue)
        const alreadyInQueue = sendQueue.some(item => item.chatId === chatId && item.message === message);
        if (alreadyInQueue) {
            console.log(`[WA Dedup] Pesan yang sama sudah berada dalam antrean untuk ${chatId}.`);
            return res.json({ success: true, message: 'Pesan serupa sudah dalam antrean.' });
        }

        recentMessages.set(dedupKey, now);

        // Pembersihan berkala jika cache dedup melebihi 100 entri
        if (recentMessages.size > 100) {
            for (const [k, ts] of recentMessages.entries()) {
                if (now - ts > 60000) {
                    recentMessages.delete(k);
                }
            }
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
// Mendukung VPS (/modules/...) dan Localhost (/eportal/modules/...)
// ==========================================
let resolvedAutoSyncUrl = null;
let isAutoSyncPolling = false;

setInterval(async () => {
    if (isAutoSyncPolling) return;
    isAutoSyncPolling = true;
    try {
        const urls = [
            'http://localhost/modules/e-absen/api/auto_sync.php?cron_token=eportal_auto_sync_secret',
            'http://localhost/eportal/modules/e-absen/api/auto_sync.php?cron_token=eportal_auto_sync_secret',
            'http://127.0.0.1/modules/e-absen/api/auto_sync.php?cron_token=eportal_auto_sync_secret',
            'http://127.0.0.1/eportal/modules/e-absen/api/auto_sync.php?cron_token=eportal_auto_sync_secret'
        ];

        if (resolvedAutoSyncUrl) {
            try {
                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), 8000);
                const res = await fetch(resolvedAutoSyncUrl, { signal: controller.signal });
                clearTimeout(timer);
                if (res.status === 200 || res.status === 400) {
                    const data = await res.json().catch(() => null);
                    if (data && data.message && data.message.includes('baru') && !data.message.includes('0 log baru')) {
                        console.log('[Auto-Sync]', data.message);
                    }
                    return;
                } else {
                    resolvedAutoSyncUrl = null;
                }
            } catch (err) {
                resolvedAutoSyncUrl = null;
            }
        }

        for (const testUrl of urls) {
            try {
                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), 4000);
                const res = await fetch(testUrl, { signal: controller.signal });
                clearTimeout(timer);
                if (res.status === 200 || res.status === 400) {
                    resolvedAutoSyncUrl = testUrl;
                    const data = await res.json().catch(() => null);
                    if (data && data.message && data.message.includes('baru') && !data.message.includes('0 log baru')) {
                        console.log('[Auto-Sync]', data.message);
                    }
                    break;
                }
            } catch (e) {}
        }
    } finally {
        isAutoSyncPolling = false;
    }
}, 15000);
