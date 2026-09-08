const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcodeTerminal = require('qrcode-terminal');
const qrcode = require('qrcode');
const express = require('express');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(express.json());

// Set up CORS so E-Portal (which is on same host or different port) can access it
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

/**
 * Initialize new WhatsApp Client instance
 */
function initClient() {
    isReady = false;
    currentQR = '';
    isReinitializing = false;

    console.log('[WA] Menginisialisasi WhatsApp Client...');

    client = new Client({
        authStrategy: new LocalAuth({
            dataPath: AUTH_DIR
        }),
        puppeteer: {
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ]
        }
    });

    client.on('qr', async (qr) => {
        console.log('[WA] SCAN QR CODE DI BAWAH INI UNTUK LOGIN WHATSAPP:');
        qrcodeTerminal.generate(qr, { small: true });
        
        try {
            currentQR = await qrcode.toDataURL(qr);
        } catch (err) {
            console.error('[WA] Failed to generate QR Data URL', err);
        }
    });

    client.on('ready', () => {
        console.log('[WA] WhatsApp Client is ready & connected!');
        isReady = true;
        currentQR = '';
    });

    client.on('authenticated', () => {
        console.log('[WA] Authenticated successfully!');
    });

    client.on('auth_failure', (msg) => {
        console.error('[WA] Auth failure:', msg);
        resetAndReinit(true);
    });

    client.on('disconnected', (reason) => {
        console.log('[WA] Client was disconnected:', reason);
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

    console.log(`[WA] Resetting client (deleteSession: ${deleteSession})...`);

    if (client) {
        try {
            // Attempt clean logout with 3s timeout
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
        // Wait 1.5s to let OS release file locks on Chromium profile directory
        await new Promise(res => setTimeout(res, 1500));
        
        if (fs.existsSync(AUTH_DIR)) {
            try {
                fs.rmSync(AUTH_DIR, { recursive: true, force: true });
                console.log('[WA] Session directory deleted successfully.');
            } catch (err) {
                console.error('[WA] Warning deleting session dir:', err.message);
                await new Promise(res => setTimeout(res, 1000));
                try {
                    fs.rmSync(AUTH_DIR, { recursive: true, force: true });
                } catch (e2) {}
            }
        }
    }

    // Spawn fresh instance
    initClient();
}

// Start WhatsApp on startup
initClient();

// Health check endpoint
app.get('/', (req, res) => {
    res.json({
        name: 'E-Portal WhatsApp Gateway API',
        status: isReady ? 'connected' : (currentQR ? 'qr_ready' : 'initializing'),
        isReady,
        hasQr: Boolean(currentQR)
    });
});

// Endpoint to check status and get QR Code
app.get('/status', (req, res) => {
    res.json({
        isReady,
        qr: currentQR
    });
});

// Endpoint to disconnect / logout device session
app.post('/logout', async (req, res) => {
    try {
        console.log('[WA] Memutuskan koneksi WhatsApp...');
        
        // Respond immediately to avoid cURL timeouts on client/PHP
        res.json({
            success: true,
            message: 'Koneksi WhatsApp berhasil diputuskan. Silakan tunggu beberapa detik dan lakukan scan Barcode baru.'
        });

        // Background execution of complete reset & session purge
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
        let groups = [];

        // Fast path: direct evaluation inside browser context (instantaneous, avoids heavy chat serialization)
        try {
            if (client.pupPage && typeof client.pupPage.evaluate === 'function') {
                groups = await client.pupPage.evaluate(() => {
                    try {
                        const collections = window.require('WAWebCollections');
                        if (!collections || !collections.Chat) return null;
                        const chats = collections.Chat.getModelsArray();
                        if (!Array.isArray(chats)) return null;

                        return chats
                            .filter(c => c && (c.isGroup || (c.id && (c.id._serialized || c.id.toString()).endsWith('@g.us'))))
                            .map(c => ({
                                id: c.id ? (c.id._serialized || c.id.toString()) : '',
                                name: c.name || c.formattedTitle || 'Grup WA'
                            }))
                            .filter(g => g.id.length > 0);
                    } catch (e) {
                        return null;
                    }
                });
            }
        } catch (fastErr) {
            console.log('[WA Groups] Fast evaluate fallback:', fastErr.message);
        }

        // Fallback: standard whatsapp-web.js getChats()
        if (!groups || groups.length === 0) {
            const chats = await client.getChats();
            groups = chats
                .filter(c => c.isGroup)
                .map(g => ({
                    id: g.id._serialized || g.id,
                    name: g.name || g.formattedTitle || 'Grup WA'
                }));
        }

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

        if (numStr.includes('@g.us') || numStr.includes('@c.us')) {
            chatId = numStr;
        } else if (numStr.endsWith('-group') || numStr.startsWith('120363')) {
            chatId = numStr.endsWith('@g.us') ? numStr : `${numStr}@g.us`;
        } else {
            let formattedNumber = numStr.replace(/\D/g, ''); 
            if (formattedNumber.startsWith('0')) {
                formattedNumber = '62' + formattedNumber.substring(1);
            }
            chatId = `${formattedNumber}@c.us`;
        }

        await client.sendMessage(chatId, message);
        console.log(`[WA] Berhasil mengirim pesan ke ${chatId}`);
        
        res.json({ success: true, message: 'Pesan berhasil terkirim ke WhatsApp.' });
    } catch (error) {
        console.error('[WA Send Error]', error);
        res.status(500).json({ success: false, message: 'Gagal mengirim pesan: ' + error.message });
    }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`WhatsApp API Server running on port ${PORT}`);
    console.log(`Endpoint: POST http://localhost:${PORT}/send`);
});

// ==========================================
// BACKGROUND AUTO-SYNC POLLING (Real-time)
// ==========================================
setInterval(async () => {
    try {
        const res = await fetch('http://localhost/eportal/modules/e-absen/api/auto_sync.php?cron_token=eportal_auto_sync_secret');
        const data = await res.json();
        if (data && data.message && data.message.includes('baru')) {
            console.log('[Auto-Sync]', data.message);
        }
    } catch (err) {
        // Ignore errors if server is down or unreachable
    }
}, 10000);
