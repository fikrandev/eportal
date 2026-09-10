<?php
/**
 * E-Examination — Student Exam Interface
 */
require_once __DIR__ . '/../../../api/config.php';

session_start();
if (!isset($_SESSION['exam_student'])) {
    header("Location: login.php");
    exit;
}

$session_id = (int)($_GET['session_id'] ?? 0);
if (!$session_id) {
    header("Location: dashboard.php");
    exit;
}

// Security: Prevent multiple tabs/windows
// (Handled partially in JS via localStorage and Visibility API)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CBT — Sedang Ujian</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/examination.css?v=<?php echo time(); ?>">
    
    <!-- KaTeX for Math Equations -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    
    <style>
        :root {
            --bg-color: #f1f5f9;
            --text-main: #0f172a;
            --primary: #2563EB;
            --border-color: #e2e8f0;
        }
        * { box-sizing: border-box; }
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            margin: 0; padding: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            height: 100dvh;
            overflow: hidden; /* Prevent body scroll, layout handles scrolling */
            user-select: none; /* Anti-copy */
        }
        /* Top Navigation Bar */
        .exam-header {
            background: white;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            z-index: 20;
            gap: 12px;
        }
        .exam-title {
            font-weight: 700;
            font-size: 17px;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .exam-title span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .exam-header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .btn-nav-mobile {
            display: none;
            align-items: center;
            gap: 6px;
            background: #eff6ff;
            color: #2563EB;
            border: 1px solid #bfdbfe;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-nav-mobile:hover { background: #dbeafe; }
        .exam-timer {
            font-family: monospace;
            font-size: 18px;
            font-weight: bold;
            color: #b91c1c;
            background: #fef2f2;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #fecaca;
            white-space: nowrap;
        }
        
        /* Main Layout */
        .exam-layout {
            display: flex;
            flex: 1;
            overflow: hidden;
            position: relative;
        }
        
        /* Sidebar (Navigasi Soal) */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(2px);
            z-index: 1040;
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .sidebar-backdrop.active {
            display: block;
            opacity: 1;
        }
        .exam-sidebar {
            width: 320px;
            max-width: 100%;
            background: white;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 50;
            overflow: hidden;
            box-sizing: border-box;
        }
        .sidebar-header {
            padding: 16px;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sidebar-legend {
            display: flex;
            justify-content: space-around;
            padding: 8px 12px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            font-size: 11.5px;
            color: #64748b;
        }
        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            border: 1px solid var(--border-color);
            background: white;
            display: inline-block;
        }
        .legend-dot.answered {
            background: #2563EB;
            border-color: #2563EB;
        }
        .legend-dot.doubt {
            background: #eab308;
            border-color: #eab308;
        }
        .btn-close-sidebar-mobile {
            display: none;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 18px;
            line-height: 1;
            color: #64748b;
            cursor: pointer;
            padding: 4px 8px;
            transition: 0.2s;
        }
        .btn-close-sidebar-mobile:hover {
            background: #fee2e2;
            color: #dc2626;
        }
        .nav-grid {
            padding: 12px;
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
            gap: 8px;
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1;
            width: 100%;
            box-sizing: border-box;
            align-content: start;
        }
        .nav-btn {
            width: 100% !important;
            aspect-ratio: 1 / 1 !important;
            min-width: 0 !important;
            min-height: 0 !important;
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            transition: 0.15s ease;
            position: relative;
            touch-action: manipulation;
            line-height: 1;
        }
        .nav-btn.answered {
            background: #2563EB;
            color: white;
            border-color: #2563EB;
        }
        .nav-btn.doubt {
            background: #eab308;
            color: white;
            border-color: #eab308;
        }
        .nav-btn.active {
            box-shadow: 0 0 0 3px rgba(37,99,235,0.3);
            border-color: #2563EB;
        }
        .nav-btn.active.answered {
            box-shadow: 0 0 0 3px rgba(37,99,235,0.4);
        }
        .nav-btn.active.doubt {
            box-shadow: 0 0 0 3px rgba(234,179,8,0.4);
        }
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border-color);
            text-align: center;
            background: white;
        }

        /* Main Content (Soal) */
        .exam-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-color);
            overflow-y: auto;
            position: relative;
            min-width: 0;
        }
        .question-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 28px;
            width: 100%;
            box-sizing: border-box;
            flex: 1;
        }
        .question-card {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            font-size: 16px;
            line-height: 1.6;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: #64748b;
        }
        .question-text img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 16px 0;
            display: block;
        }
        
        /* Options layout */
        .options-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 24px;
        }
        .option-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 16px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
        }
        .option-item:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .option-item.selected {
            background: #eff6ff;
            border-color: #2563EB;
        }
        .option-item input[type="radio"], .option-item input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            accent-color: #2563EB;
            flex-shrink: 0;
        }
        .option-label {
            flex: 1;
            user-select: none;
            line-height: 1.5;
            word-break: break-word;
        }
        .option-label img {
            max-width: 100%;
            height: auto;
        }
        
        /* Action Bar */
        .action-bar {
            background: white;
            border-top: 1px solid var(--border-color);
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            bottom: 0;
            z-index: 10;
        }
        .btn-exam {
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14.5px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-prev { background: #f1f5f9; color: #475569; }
        .btn-prev:hover { background: #e2e8f0; }
        .btn-next { background: #2563EB; color: white; }
        .btn-next:hover { background: #1D4ED8; }
        .btn-doubt { background: #fef08a; color: #854d0e; }
        .btn-doubt.active { background: #eab308; color: white; }
        .btn-finish { background: #10b981; color: white; }
        .btn-finish:hover { background: #059669; }

        /* Fullscreen overlay (Anti-cheat) */
        #fullscreenOverlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15,23,42,0.96);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            text-align: center;
            padding: 24px;
        }
        #fullscreenOverlay h2 { margin-bottom: 16px; }
        #fullscreenOverlay button {
            background: #2563EB; color: white; border: none; padding: 14px 28px;
            border-radius: 10px; font-weight: bold; font-size: 16px; cursor: pointer;
            box-shadow: 0 4px 14px rgba(37,99,235,0.4);
        }

        /* Utility classes */
        .hidden { display: none !important; }
        .flex { display: flex; }
        
        /* Input types for essay/short answer */
        .input-essay {
            width: 100%;
            padding: 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 15px;
            min-height: 140px;
            resize: vertical;
        }
        .input-short {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 15px;
        }

        /* ===== MOBILE RESPONSIVE DESIGN ===== */
        @media (max-width: 992px) {
            .exam-header {
                padding: 10px 12px;
                gap: 8px;
            }
            .exam-title {
                font-size: 14px;
                gap: 6px;
            }
            .exam-title svg {
                width: 18px;
                height: 18px;
            }
            .exam-title span {
                max-width: 130px;
            }
            .btn-nav-mobile {
                display: inline-flex;
                padding: 6px 10px;
                font-size: 12px;
                gap: 4px;
            }
            .exam-timer {
                font-size: 13.5px;
                padding: 5px 8px;
                border-radius: 6px;
            }

            /* Off-canvas sidebar drawer */
            .sidebar-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.6);
                backdrop-filter: blur(2px);
                z-index: 1040;
                opacity: 0;
                transition: opacity 0.25s ease;
            }
            .sidebar-backdrop.active {
                display: block;
                opacity: 1;
            }
            .exam-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: 300px;
                max-width: 85vw;
                height: 100vh;
                height: 100dvh;
                box-shadow: 6px 0 24px rgba(0,0,0,0.25);
                transform: translateX(-100%);
                z-index: 1050;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                box-sizing: border-box;
            }
            .exam-sidebar.mobile-open {
                transform: translateX(0);
            }
            .btn-close-sidebar-mobile {
                display: block;
            }
            .nav-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
                gap: 8px;
                padding: 12px;
                width: 100%;
                box-sizing: border-box;
                overflow-x: hidden;
                align-content: start;
            }
            .nav-btn {
                width: 100% !important;
                aspect-ratio: 1 / 1 !important;
                font-size: 13px;
                border-radius: 6px;
                min-width: 0 !important;
                min-height: 0 !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* Main question container */
            .question-container {
                padding: 12px 10px;
            }
            .question-card {
                padding: 16px 12px;
                border-radius: 10px;
                margin-bottom: 12px;
                font-size: 14.5px;
            }
            .question-header {
                margin-bottom: 14px;
                padding-bottom: 10px;
                font-size: 13px;
            }
            .question-text {
                font-size: 14.5px;
                line-height: 1.55;
            }
            .options-list {
                gap: 10px;
                margin-top: 16px;
            }
            .option-item {
                padding: 11px 12px;
                gap: 10px;
                border-radius: 8px;
                font-size: 13.5px;
            }
            .option-label {
                font-size: 13.5px;
            }

            /* Action bar buttons on mobile */
            .action-bar {
                padding: 8px 10px;
                gap: 6px;
                background: rgba(255,255,255,0.98);
                backdrop-filter: blur(8px);
                box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            }
            .btn-exam {
                padding: 10px 8px;
                font-size: 12px;
                gap: 4px;
                border-radius: 8px;
                flex: 1;
                justify-content: center;
                text-align: center;
                white-space: nowrap;
            }
            .btn-exam svg {
                width: 15px;
                height: 15px;
            }
            .btn-prev { flex: 0.9; }
            .btn-doubt { flex: 1.1; font-size: 11.5px; padding: 10px 4px; }
            .btn-next { flex: 1; }

            .input-essay {
                padding: 12px;
                font-size: 14px;
                min-height: 110px;
            }
            .input-short {
                padding: 10px;
                font-size: 14px;
            }
        }

        /* ===== ANTI-CHEAT: Block printing ===== */
        @media print {
            html, body { display: none !important; }
        }

        /* ===== ANTI-CHEAT: Disable text selection on question content ===== */
        .question-text, .option-label, .question-header {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        /* Allow selection only in answer input areas */
        .input-essay, .input-short { user-select: text; }
    </style>
</head>
<body>

    <!-- Fullscreen Enforcement Overlay -->
    <div id="fullscreenOverlay">
        <div style="animation: fsLockPulse 2s infinite alternate;">
            <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="#60a5fa" stroke-width="1.5" style="margin-bottom:16px;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        <h2>Ujian Membutuhkan Layar Penuh</h2>
        <p style="color:#94a3b8; margin-bottom:12px; max-width:500px;">
            Untuk alasan keamanan dan integritas ujian, Anda <strong style="color:#fca5a5;">diwajibkan</strong> menggunakan mode layar penuh (Fullscreen).
        </p>
        <p style="color:#64748b; margin-bottom:32px; font-size:13px; max-width:450px;">
            ⚠ Keluar dari mode layar penuh, berpindah tab, atau membuka aplikasi lain akan dicatat sebagai <strong style="color:#fca5a5;">pelanggaran</strong>. Pelanggaran 3× = ujian dihentikan otomatis.
        </p>
        <button id="btnEnterFullscreen" onclick="(function(){ if(window.ExamApp && typeof window.ExamApp.enterFullscreen === 'function'){ window.ExamApp.enterFullscreen(); } else { const el = document.getElementById('fullscreenOverlay'); if(el) el.style.display='none'; } })()">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:8px;">
                <polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>
            </svg>
            Masuk Mode Layar Penuh
        </button>
        <style>
            @keyframes fsLockPulse { from { transform:scale(1); opacity:0.8; } to { transform:scale(1.08); opacity:1; } }
        </style>
    </div>

    <!-- Header -->
    <header class="exam-header">
        <div class="exam-title">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#2563EB" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span id="uiUjianJudul">Memuat Ujian...</span>
        </div>
        <div class="exam-header-right">
            <button id="btnToggleNavMobile" class="btn-nav-mobile" onclick="toggleSidebarMobile()" title="Daftar Nomor Soal">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>Daftar Soal</span>
            </button>
            <div class="exam-timer" id="uiTimer">00:00:00</div>
        </div>
    </header>

    <!-- Layout -->
    <div class="exam-layout">
        <!-- Backdrop for mobile drawer -->
        <div id="sidebarBackdrop" class="sidebar-backdrop" onclick="closeSidebarMobile()"></div>
        
        <!-- Sidebar Navigation -->
        <aside class="exam-sidebar" id="sidebar">
            <div class="sidebar-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#2563EB" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Daftar Nomor Soal</span>
                </div>
                <button class="btn-close-sidebar-mobile" onclick="closeSidebarMobile()" aria-label="Tutup">&times;</button>
            </div>
            <div class="sidebar-legend">
                <div class="legend-item"><span class="legend-dot answered"></span> Terjawab</div>
                <div class="legend-item"><span class="legend-dot doubt"></span> Ragu-ragu</div>
                <div class="legend-item"><span class="legend-dot"></span> Belum</div>
            </div>
            <div class="nav-grid" id="uiNavGrid">
                <!-- Buttons injected by JS -->
            </div>
            <div class="sidebar-footer">
                <button class="btn-exam btn-finish" style="width:100%; justify-content:center;" onclick="ExamApp.finishExam()">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Selesai Ujian
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="exam-main">
            <div class="question-container" id="uiQuestionContainer" style="display:none;">
                <div class="question-card">
                    <div class="question-header">
                        <span id="uiSoalNo">Soal No. 1</span>
                        <span id="uiSoalTipe" style="background:#f1f5f9;padding:4px 8px;border-radius:6px;font-size:13px;">Pilihan Ganda</span>
                    </div>
                    
                    <div id="uiSoalMedia" style="margin-bottom:16px;"></div>
                    
                    <div class="question-text" id="uiSoalText">
                        <!-- Pertanyaan -->
                    </div>

                    <div id="uiSoalOpsi" class="options-list">
                        <!-- Opsi/Input Jawaban -->
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div id="uiLoading" style="display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; color:#64748b; padding:32px 16px;">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>
                <p style="margin-top:16px; font-weight:500;">Memuat soal ujian...</p>
                <style>@keyframes spin { 100% { transform:rotate(360deg); } }</style>
            </div>

            <!-- Action Bar -->
            <div class="action-bar" id="uiActionBar" style="display:none;">
                <button class="btn-exam btn-prev" id="btnPrev" onclick="ExamApp.prevSoal()">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    <span>Sebelumnya</span>
                </button>
                
                <button class="btn-exam btn-doubt" id="btnDoubt" onclick="ExamApp.toggleDoubt()">
                    <input type="checkbox" id="cbDoubt" style="pointer-events:none;">
                    <span>Ragu-ragu</span>
                </button>
                
                <button class="btn-exam btn-next" id="btnNext" onclick="ExamApp.nextSoal()">
                    <span>Selanjutnya</span>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div id="modalContainer"></div>
    <div id="toastContainer" class="toast-container"></div>

    <script src="<?php echo BASE_URL; ?>assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/modal.js"></script>
    <!-- KaTeX -->
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
    
    <!-- Mobile Sidebar Helpers -->
    <script>
        function toggleSidebarMobile() {
            const sb = document.getElementById('sidebar');
            const bd = document.getElementById('sidebarBackdrop');
            if (sb) sb.classList.toggle('mobile-open');
            if (bd) bd.classList.toggle('active');
        }
        function closeSidebarMobile() {
            const sb = document.getElementById('sidebar');
            const bd = document.getElementById('sidebarBackdrop');
            if (sb) sb.classList.remove('mobile-open');
            if (bd) bd.classList.remove('active');
        }
    </script>

    <!-- Core App Logic -->
    <script>
        const EXAM_SESSION_ID = <?php echo $session_id; ?>;
    </script>
    <script src="../assets/js/student_exam.js?v=<?php echo time(); ?>"></script>
</body>
</html>
