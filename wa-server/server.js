const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcodeTerminal = require('qrcode-terminal');
const qrcode = require('qrcode');
const express = require('express');

const app = express();
app.use(express.json());

// Set up CORS so E-Portal (which is on same host but maybe different port) can access it
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type');
    if (req.method === 'OPTIONS') return res.sendStatus(200);
    next();
});

const client = new Client({
    authStrategy: new LocalAuth(),
    puppeteer: {
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    }
});

let isReady = false;
let currentQR = '';

client.on('qr', async (qr) => {
    console.log('SCAN QR CODE DI BAWAH INI UNTUK LOGIN WHATSAPP:');
    qrcodeTerminal.generate(qr, { small: true });
    
    try {
        currentQR = await qrcode.toDataURL(qr);
    } catch (err) {
        console.error('Failed to generate QR Data URL', err);
    }
});

client.on('ready', () => {
    console.log('WhatsApp Client is ready!');
    isReady = true;
    currentQR = '';
});

client.on('disconnected', (reason) => {
    console.log('Client was disconnected', reason);
    isReady = false;
});

client.initialize();

// Endpoint to check status and get QR Code
app.get('/status', (req, res) => {
    res.json({
        isReady,
        qr: currentQR
    });
});

// Endpoint to send message
app.post('/send', async (req, res) => {
    if (!isReady) {
        return res.status(503).json({ success: false, message: 'WhatsApp Client is not ready yet. Please wait or scan QR.' });
    }

    const { number, message } = req.body;
    
    if (!number || !message) {
        return res.status(400).json({ success: false, message: 'Missing number or message in request body.' });
    }

    try {
        let formattedNumber = number.replace(/\D/g, ''); 
        if (formattedNumber.startsWith('0')) {
            formattedNumber = '62' + formattedNumber.substring(1);
        }
        const chatId = `${formattedNumber}@c.us`;

        await client.sendMessage(chatId, message);
        console.log(`[WA] Sent message to ${formattedNumber}`);
        
        res.json({ success: true, message: 'Message sent successfully.' });
    } catch (error) {
        console.error('[WA Error]', error);
        res.status(500).json({ success: false, message: 'Failed to send message: ' + error.message });
    }
});

const PORT = 3000;
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
        // Ignore errors if server is down or reachable
    }
}, 10000); // 10 seconds

